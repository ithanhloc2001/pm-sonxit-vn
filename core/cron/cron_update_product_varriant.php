<?php
// File này chạy định kỳ để tự động tạo variant mặc định cho các sản phẩm đang thiếu, tránh lỗi khi admin cố gắng publish sản phẩm nhưng bị chặn do thiếu variant (vì trước đây variant là bắt buộc nhưng sau này đổi sang không bắt buộc nên có thể tồn tại sản phẩm không có variant nào).

require_once __DIR__ . '/../../config.php';
$ithanhloc->set_charset('utf8mb4');



function cronIntParam(string $key, int $default): int {
    $val = null;
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach ($argv as $arg) {
            if (strpos($arg, $key . '=') === 0) {
                $val = substr($arg, strlen($key) + 1);
                break;
            }
        }
    } else {
        $val = $_GET[$key] ?? $_POST[$key] ?? null;
    }
    if ($val === null) return $default;
    $n = (int)$val;
    return $n > 0 ? $n : $default;
}

function cronBoolParam(string $key, bool $default = false): bool {
    $val = null;
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach ($argv as $arg) {
            if (strpos($arg, $key . '=') === 0) {
                $val = substr($arg, strlen($key) + 1);
                break;
            }
        }
    } else {
        $val = $_GET[$key] ?? $_POST[$key] ?? null;
    }
    if ($val === null) return $default;
    $normalized = strtolower(trim((string)$val));
    if ($normalized === '') return $default;
    return !in_array($normalized, ['0', 'false', 'no', 'off'], true);
}

$productTable = function_exists('first_existing_table')
    ? first_existing_table($ithanhloc, ['ecommerce_product'])
    : (tableExists($ithanhloc, 'ecommerce_product') ? 'ecommerce_product' : '');

$variantTable = function_exists('first_existing_table')
    ? first_existing_table($ithanhloc, ['ecommerce_product_variants'])
    : (tableExists($ithanhloc, 'ecommerce_product_variants') ? 'ecommerce_product_variants' : '');

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

if ($productTable === '' || !tableExists($ithanhloc, $productTable)) {
    echo "ERROR: Missing product table\n";
    exit;
}
if ($variantTable === '' || !tableExists($ithanhloc, $variantTable)) {
    echo "ERROR: Missing variant table\n";
    exit;
}

$productCols = listColumns($ithanhloc, $productTable);
$variantCols = listColumns($ithanhloc, $variantTable);

$limit = cronIntParam('limit', 500);
$dryRun = cronBoolParam('dry_run', false);
$debug = cronBoolParam('debug', false);

$select = [
    'p.id AS id',
    'p.product_name AS product_name',
];
$hasLegacyPrice = isset($productCols['price']);
$hasLegacyStock = isset($productCols['stock_quantity']);
if ($hasLegacyPrice) $select[] = 'p.price AS legacy_price';
if ($hasLegacyStock) $select[] = 'p.stock_quantity AS legacy_stock';

// Find products with no variants
$sql = "SELECT " . implode(', ', $select) . "\n"
    . "FROM `{$productTable}` p\n"
    . "LEFT JOIN `{$variantTable}` v ON v.product_id = p.id\n"
    . "WHERE v.id IS NULL\n"
    . "ORDER BY p.id ASC\n"
    . "LIMIT " . (int)$limit;

$res = @$ithanhloc->query($sql);
if (!$res) {
    echo "ERROR: Query failed: " . $ithanhloc->error . "\n";
    exit;
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    $pid = (int)($r['id'] ?? 0);
    if ($pid <= 0) continue;
    $rows[] = $r;
}

if (!$rows) {
    echo "OK: No products missing variants\n";
    exit;
}

if ($debug) {
    $ids = array_map(static fn($x) => (int)($x['id'] ?? 0), $rows);
    $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
    echo 'DEBUG_IDS=' . implode(',', $ids) . "\n";
}

if ($dryRun) {
    echo "DRY_RUN\n";
    echo "MISSING_PRODUCTS: " . count($rows) . "\n";
    exit;
}

// Build dynamic INSERT based on actual variant columns
$insertCols = [];
$placeholders = [];
$types = '';

$push = function(string $col, string $type) use (&$insertCols, &$placeholders, &$types): void {
    $insertCols[] = $col;
    $placeholders[] = '?';
    $types .= $type;
};

// Required
$push('product_id', 'i');

// Optional/common columns
if (isset($variantCols['variant_name'])) $push('variant_name', 's');
if (isset($variantCols['color'])) $push('color', 's');
if (isset($variantCols['sku_variant'])) $push('sku_variant', 's');

if (isset($variantCols['shipping_weight_value'])) $push('shipping_weight_value', 'd');
if (isset($variantCols['shipping_weight_unit'])) $push('shipping_weight_unit', 's');
if (isset($variantCols['shipping_length_cm'])) $push('shipping_length_cm', 'i');
if (isset($variantCols['shipping_width_cm'])) $push('shipping_width_cm', 'i');
if (isset($variantCols['shipping_height_cm'])) $push('shipping_height_cm', 'i');

if (isset($variantCols['price'])) $push('price', 'i');
if (isset($variantCols['stock_quantity'])) $push('stock_quantity', 'i');

if (isset($variantCols['color_options'])) $push('color_options', 's');
if (isset($variantCols['status'])) $push('status', 's');

if (!$insertCols || !in_array('product_id', $insertCols, true)) {
    echo "ERROR: Variant table missing product_id\n";
    exit;
}

$sqlIns = "INSERT INTO `{$variantTable}` (`" . implode('`,`', $insertCols) . "`) VALUES (" . implode(',', $placeholders) . ")";
$stmtIns = @$ithanhloc->prepare($sqlIns);
if (!$stmtIns) {
    echo "ERROR: Prepare failed: " . $ithanhloc->error . "\n";
    exit;
}

$created = 0;
$failed = 0;

foreach ($rows as $r) {
    $pid = (int)($r['id'] ?? 0);
    if ($pid <= 0) continue;

    $legacyPrice = $hasLegacyPrice ? (float)($r['legacy_price'] ?? 0) : 0.0;
    $legacyStock = $hasLegacyStock ? (int)($r['legacy_stock'] ?? 0) : 0;

    $vals = [];
    foreach ($insertCols as $col) {
        switch ($col) {
            case 'product_id': $vals[] = $pid; break;
            case 'variant_name': $vals[] = 'Mặc định'; break;
            case 'color': $vals[] = ''; break;
            case 'sku_variant': $vals[] = ''; break;

            case 'shipping_weight_value': $vals[] = 1.0; break;
            case 'shipping_weight_unit': $vals[] = 'kg'; break;
            case 'shipping_length_cm': $vals[] = 20; break;
            case 'shipping_width_cm': $vals[] = 20; break;
            case 'shipping_height_cm': $vals[] = 20; break;

            case 'price':
                // Preserve legacy product price if still available; otherwise 0
                $vals[] = (int)round(max(0, $legacyPrice));
                break;
            case 'stock_quantity':
                $vals[] = max(0, (int)$legacyStock);
                break;

            case 'color_options': $vals[] = '[]'; break;
            case 'status':
                // Default variant is active; admin will still block product publish if variants are missing (now fixed by this cron).
                $vals[] = '1';
                break;

            default:
                $vals[] = null;
                break;
        }
    }

    // bind_param requires references
    $bind = [];
    $bind[] = $types;
    foreach ($vals as $k => $v) {
        $bind[] = &$vals[$k];
    }

    try {
        @call_user_func_array([$stmtIns, 'bind_param'], $bind);
        $ok = @$stmtIns->execute();
        if ($ok) {
            $created++;
        } else {
            $failed++;
        }
    } catch (Throwable $e) {
        $failed++;
    }
}

$stmtIns->close();

echo "DONE\n";
echo "MISSING_PRODUCTS: " . count($rows) . "\n";
echo "CREATED_VARIANTS: {$created}\n";
echo "FAILED: {$failed}\n";
