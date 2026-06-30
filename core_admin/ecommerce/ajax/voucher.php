<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
if (empty($isAdmin)) {
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);
}
?>
<?php
// Quản lý Mã giảm giá (Voucher)
$voucherPaymentOptions = ecommerce_get_enabled_payment_methods();

// Các phương thức vận chuyển chuẩn
$ghnShippingOptions = [];
foreach (defaultShippingMethodLabelMap() as $mKey => $mLabel) {
    $ghnShippingOptions[] = ['key' => (string)$mKey, 'label' => (string)$mLabel];
}

// ===== ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $voucher_template = trim((string)($_POST['voucher_template'] ?? ''));

        // Giá trị ưu đãi + đơn vị
        $value_unit = strtolower(trim((string)($_POST['value_unit'] ?? 'fixed')));
        if ($value_unit !== 'percent' && $value_unit !== 'fixed') {
            $value_unit = 'fixed';
        }
        $type = $value_unit; 
        $value = (float)($_POST['value'] ?? 0);
        
        // Đơn tối thiểu + đơn vị
        $min_subtotal = (float)($_POST['min_subtotal'] ?? 0);
        $min_subtotal_unit = strtolower(trim((string)($_POST['min_subtotal_unit'] ?? 'fixed')));
        if ($min_subtotal_unit !== 'percent' && $min_subtotal_unit !== 'fixed') {
            $min_subtotal_unit = 'fixed';
        }

        // Giảm tối đa + đơn vị
        $max_discount_raw = trim((string)($_POST['max_discount'] ?? ''));
        $max_discount = ($max_discount_raw === '') ? null : (float)$max_discount_raw;
        $max_discount_unit = strtolower(trim((string)($_POST['max_discount_unit'] ?? 'fixed')));
        if ($max_discount_unit !== 'percent' && $max_discount_unit !== 'fixed') {
            $max_discount_unit = 'fixed';
        }
        
        $start_at_raw = trim((string)($_POST['start_at'] ?? ''));
        $end_at_raw = trim((string)($_POST['end_at'] ?? ''));
        $start_at = ($start_at_raw === '') ? null : $start_at_raw;
        $end_at = ($end_at_raw === '') ? null : $end_at_raw;
        $is_active = isset($_POST['is_active']) ? (int)($_POST['is_active'] ? 1 : 0) : 1;
        
        $targetsRaw = $_POST['discount_targets'] ?? [];
        $targets = [];
        foreach ((array)$targetsRaw as $t) {
            $t = ecommerce_normalize_discount_target((string)$t);
            if (!in_array($t, $targets, true)) $targets[] = $t;
        }
        if (!$targets) jOut(['ok' => false, 'msg' => 'Vui lòng chọn Mục tiêu giảm']);
        $discount_target = implode(',', $targets);
        
        $max_uses_raw = trim((string)($_POST['max_uses'] ?? ''));
        $max_uses = ($max_uses_raw === '') ? null : (int)$max_uses_raw;

        // Phạm vi áp dụng theo sản phẩm hay toàn bộ
        $apply_scope = (($_POST['apply_scope'] ?? 'all') === 'products') ? 'products' : 'all';
        $apply_product_ids = null;
        $apply_variant_group_ids = '';
        $apply_variant_ids = '';

        if ($apply_scope === 'products' && $voucher_template !== 'category_discount') {
            $p_ids = normalizeProductIds((string)($_POST['apply_product_ids'] ?? ''));
            $apply_product_ids = $p_ids ? implode(',', $p_ids) : null;
            
            $vg_ids = normalizeProductIds((string)($_POST['apply_variant_group_ids'] ?? ''));
            $apply_variant_group_ids = $vg_ids ? implode(',', $vg_ids) : '';
            
            $v_ids = normalizeProductIds((string)($_POST['apply_variant_ids'] ?? ''));
            $apply_variant_ids = $v_ids ? implode(',', $v_ids) : '';

            if (!$apply_product_ids && !$apply_variant_group_ids && !$apply_variant_ids) {
                 jOut(['ok' => false, 'msg' => 'Vui lòng chọn ít nhất 1 sản phẩm, nhóm hoặc biến thể']);
            }
        }

        $apply_category_ids = '';
        $rawCat = (string)($_POST['apply_category_ids'] ?? '');
        if (trim($rawCat) !== '') {
            $catIds = normalizeProductIds($rawCat);
            $apply_category_ids = $catIds ? implode(',', $catIds) : '';
        }

        // Voucher "giảm theo ngành hàng" bắt buộc phải chọn ít nhất 1 danh mục,
        // nếu không sẽ áp cho mọi sản phẩm — sai bản chất template.
        if ($voucher_template === 'only_category_discount' && $apply_category_ids === '') {
            jOut(['ok' => false, 'msg' => 'Vui lòng chọn danh mục (ngành hàng) áp dụng']);
        }

        if ($voucher_template === 'category_discount') {
            $apply_scope = 'all';
            $apply_product_ids = null;
        }

        $apply_user_ids = implode(',', normalizeProductIds($_POST['apply_user_ids'] ?? ''));
        $exclude_product_ids = implode(',', normalizeProductIds($_POST['exclude_product_ids'] ?? ''));
        $payment_methods = ecommerce_normalize_csv_input($_POST['payment_methods'] ?? '');
        $shipping_methods = ecommerce_normalize_csv_input($_POST['shipping_methods'] ?? '');

        // Chuẩn hoá "giới hạn phương thức": nếu chọn TẤT CẢ (hoặc không chọn) thì lưu rỗng
        // = không giới hạn. Tránh việc form tick sẵn toàn bộ khiến mọi voucher đều bị coi là
        // "giới hạn payment/shipping" và bị phân loại / lọc sai.
        $allPaymentKeys = array_map(fn($m) => strtolower((string)$m['key']), $voucherPaymentOptions);
        $selectedPayment = $payment_methods === '' ? [] : explode(',', $payment_methods);
        if (!$allPaymentKeys || !array_diff($allPaymentKeys, $selectedPayment)) {
            // đã chọn đủ tất cả phương thức hiện có => coi như không giới hạn
            $payment_methods = '';
        }
        // payment_methods được lưu cho MỌI loại voucher (không còn ép rỗng theo template).

        $allShippingKeys = array_map(fn($m) => strtolower((string)$m['key']), $ghnShippingOptions);
        $selectedShipping = $shipping_methods === '' ? [] : explode(',', $shipping_methods);
        if (!$allShippingKeys || !array_diff($allShippingKeys, $selectedShipping)) {
            $shipping_methods = '';
        }
        // shipping_methods chỉ có ý nghĩa với voucher ưu đãi vận chuyển
        if ($voucher_template !== 'shipping_discount') {
            $shipping_methods = '';
        }
        $promo_note = trim((string)($_POST['promo_note'] ?? ''));
        if ($promo_note === '') {
            $promo_note = 'Lượt sử dụng có hạn. Nhanh tay kẻo lỡ bạn nhé!';
        }
        
        $detail_text = trim((string)($_POST['detail_text'] ?? ''));
        if ($detail_text === '') {
            $detail_text = ecommerce_voucher_build_detail_text([
                'code' => $code,
                'value' => $value,
                'value_unit' => $value_unit,
                'max_discount' => $max_discount,
                'max_discount_unit' => $max_discount_unit,
                'min_subtotal' => $min_subtotal,
                'min_subtotal_unit' => $min_subtotal_unit,
                'end_at' => $end_at,
                'max_uses' => $max_uses,
            ]);
        }
        
        $max_discount_param = ($max_discount === null) ? null : (string)$max_discount;
        $max_uses_param = ($max_uses === null) ? null : (string)$max_uses;
        
        if ($code === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập code']);
        
        if ($id > 0) {
            $stmt = $ithanhloc->prepare('UPDATE ecommerce_voucher SET code=?, voucher_template=?, value=?, value_unit=?, min_subtotal=?, min_subtotal_unit=?, max_discount=?, max_discount_unit=?, start_at=?, end_at=?, is_active=?, discount_target=?, apply_scope=?, apply_product_ids=?, apply_category_ids=?, apply_variant_group_ids=?, apply_variant_ids=?, max_uses=?, promo_note=?, detail_text=?, apply_user_ids=?, exclude_product_ids=?, payment_methods=?, shipping_methods=? WHERE id=?');
            if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error]);
            $stmt->bind_param(
                'ssssssssssssssssssssssssi',
                $code, $voucher_template, $value, $value_unit, $min_subtotal, $min_subtotal_unit,
                $max_discount_param, $max_discount_unit, $start_at, $end_at, $is_active,
                $discount_target, $apply_scope, $apply_product_ids, $apply_category_ids,
                $apply_variant_group_ids, $apply_variant_ids,
                $max_uses_param, $promo_note, $detail_text, $apply_user_ids, $exclude_product_ids,
                $payment_methods, $shipping_methods, $id
            );
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã lưu' : $err]);
        } else {
            $stmt = $ithanhloc->prepare('INSERT INTO ecommerce_voucher (code, voucher_template, value, value_unit, min_subtotal, min_subtotal_unit, max_discount, max_discount_unit, start_at, end_at, is_active, discount_target, apply_scope, apply_product_ids, apply_category_ids, apply_variant_group_ids, apply_variant_ids, max_uses, promo_note, detail_text, apply_user_ids, exclude_product_ids, payment_methods, shipping_methods) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error]);
            $stmt->bind_param(
                'ssssssssssssssssssssssss',
                $code, $voucher_template, $value, $value_unit, $min_subtotal, $min_subtotal_unit,
                $max_discount_param, $max_discount_unit, $start_at, $end_at, $is_active,
                $discount_target, $apply_scope, $apply_product_ids, $apply_category_ids,
                $apply_variant_group_ids, $apply_variant_ids,
                $max_uses_param, $promo_note, $detail_text, $apply_user_ids, $exclude_product_ids,
                $payment_methods, $shipping_methods
            );
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã tạo mã' : $err]);
        }
    }


    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = $ithanhloc->query('DELETE FROM ecommerce_voucher WHERE id=' . $id);
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xóa' : $ithanhloc->error]);
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $state = (int)($_POST['is_active'] ?? 1);
        $stmt = $ithanhloc->prepare('UPDATE ecommerce_voucher SET is_active=? WHERE id=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error]);
        $stmt->bind_param('ii', $state, $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật' : $err]);
    }
}

// ===== DATA =====
$Vouchers = [];
$q = $ithanhloc->query('SELECT * FROM ecommerce_voucher ORDER BY id DESC');
while ($r = $q->fetch_assoc()) $Vouchers[] = $r;

$productOptions = [];
try {
    $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
} catch (Throwable $e) {
    $variantTable = 'ecommerce_product_variants';
}

$skuExpr = $variantTable
    ? "COALESCE((SELECT sku_variant FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.sku_variant <> '' ORDER BY v.id ASC LIMIT 1), '') AS sku"
    : "'' AS sku";

// Giá gốc tối thiểu (giá biến thể rẻ nhất) để hiển thị trong picker.
$priceMinExpr = $variantTable
    ? "COALESCE((SELECT MIN(price) FROM `{$variantTable}` v WHERE v.product_id = p.id), 0) AS price_min"
    : '0 AS price_min';

$qProducts = $ithanhloc->query("SELECT p.id, p.product_name, {$skuExpr}, p.category_id, {$priceMinExpr} FROM ecommerce_product p ORDER BY p.product_name ASC, p.id DESC");
if ($qProducts) {
    $p_list = [];
    $p_ids = [];
    while ($p = $qProducts->fetch_assoc()) {
        $pid = (int)$p['id'];
        $p_list[$pid] = [
            'id' => $pid,
            'name' => (string)($p['product_name'] ?? ''),
            'sku' => (string)($p['sku'] ?? ''),
            'category_id' => (int)($p['category_id'] ?? 0),
            'price' => (int)($p['price_min'] ?? 0),
            'groups' => []
        ];
        $p_ids[] = $pid;
    }

    if ($p_ids) {
        $p_ids_str = implode(',', $p_ids);
        // Load Groups
        $qGroups = $ithanhloc->query("SELECT * FROM ecommerce_product_variant_groups WHERE product_id IN ($p_ids_str) ORDER BY sort_order ASC, id ASC");
        $group_map = [];
        if ($qGroups) {
            while ($g = $qGroups->fetch_assoc()) {
                $pid = (int)$g['product_id'];
                $gid = (int)$g['id'];
                $group_data = [
                    'id' => $gid,
                    'name' => (string)$g['name'],
                    'variants' => []
                ];
                $p_list[$pid]['groups'][] = &$group_data;
                $group_map[$gid] = &$group_data;
                unset($group_data);
            }
        }

        // Load Variants
        $qVariants = $ithanhloc->query("SELECT id, product_id, group_id, variant_name, sku_variant, price, shipping_weight_value, shipping_weight_unit FROM ecommerce_product_variants WHERE product_id IN ($p_ids_str) ORDER BY id ASC");
        if ($qVariants) {
            while ($v = $qVariants->fetch_assoc()) {
                $pid = (int)$v['product_id'];
                $gid = (int)$v['group_id'];
                // Tên biến thể KHÔNG kèm dung tích/trọng lượng (nhất quán với promotion.php & toàn hệ thống).
                // shipping_weight_value là DECIMAL (vd "1.000" = 1.0) — nối thẳng sẽ ra "1.000l" gây hiểu nhầm 1000 lít.
                // Dùng cleanVariantWeightLegacy để loại bỏ dung tích lỡ lẫn trong variant_name (data cũ).
                $vName = function_exists('cleanVariantWeightLegacy')
                    ? cleanVariantWeightLegacy((string)$v['variant_name'], (float)($v['shipping_weight_value'] ?? 0), (string)($v['shipping_weight_unit'] ?? ''))
                    : trim((string)$v['variant_name']);
                $v_data = [
                    'id' => (int)$v['id'],
                    'name' => $vName,
                    'sku' => (string)$v['sku_variant'],
                    'price' => (int)($v['price'] ?? 0)
                ];
                if ($gid > 0 && isset($group_map[$gid])) {
                    $group_map[$gid]['variants'][] = $v_data;
                } else {
                    // Variants without group or group not found
                    if (!isset($p_list[$pid]['no_group_variants'])) {
                        $p_list[$pid]['no_group_variants'] = [];
                    }
                    $p_list[$pid]['no_group_variants'][] = $v_data;
                }
            }
        }
    }
    $productOptions = array_values($p_list);
}


$userOptions = [];
$qUsers = $ithanhloc->query("SELECT id, full_name, username, phone FROM users ORDER BY id DESC");
if ($qUsers) {
    while ($u = $qUsers->fetch_assoc()) {
        $name = trim((string)($u['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($u['username'] ?? ''));
        }
        $userOptions[] = [
            'id' => (int)($u['id'] ?? 0),
            'name' => $name,
            'phone' => (string)($u['phone'] ?? ''),
        ];
    }
}

$categoryOptions = [];
$categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category', 'list_category']) : '';
if ($categoryTable !== '') {
    $catRes = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY id ASC");
    if ($catRes) {
        while ($row = $catRes->fetch_assoc()) {
            $categoryOptions[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
            ];
        }
    }
}

function VoucherValueText($c) {
    return ecommerce_voucher_format_label($c['value_unit'] ?? 'fixed', (float)($c['value'] ?? 0), $c['max_discount'] ?? null);
}

function determineVoucherTemplateKey($c) {
    $rawTpl = strtolower(trim((string)($c['voucher_template'] ?? '')));
    $allowed = ['order_discount','shipping_discount','only_category_discount','category_discount','payment_discount'];
    if (in_array($rawTpl, $allowed, true)) {
        return $rawTpl;
    }

    $rawTarget = strtolower(trim((string)($c['discount_target'] ?? 'order')));
    $targets = preg_split('/[\s,;|]+/', $rawTarget);
    $targets = array_filter(array_map('trim', (array)$targets), static function($v) {
        return $v !== '';
    });
    $hasShipping = in_array('shipping', $targets, true);

    $hasPayment = trim((string)($c['payment_methods'] ?? '')) !== '';
    $hasCategories = trim((string)($c['apply_category_ids'] ?? '')) !== '';
    $applyScope = strtolower((string)($c['apply_scope'] ?? 'all'));

    if ($hasShipping && !$hasPayment) {
        return 'shipping_discount';
    }
    if ($hasPayment) {
        return 'payment_discount';
    }
    if ($hasCategories) {
        return $applyScope === 'products' ? 'only_category_discount' : 'category_discount';
    }

    return 'order_discount';
}

function VoucherScopeText($c) {
    global $categoryOptions, $voucherPaymentOptions, $ghnShippingOptions;

    $scope = (string)($c['apply_scope'] ?? 'all');
    $applyProducts = normalizeProductIds((string)($c['apply_product_ids'] ?? ''));

    $catIdsRaw = trim((string)($c['apply_category_ids'] ?? ''));
    $catIds = $catIdsRaw !== '' ? array_filter(array_map('intval', explode(',', $catIdsRaw))) : [];
    $catNames = [];
    if ($catIds && is_array($categoryOptions)) {
        $map = [];
        foreach ($categoryOptions as $opt) {
            $id = (int)($opt['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = (string)($opt['name'] ?? '');
            }
        }
        foreach ($catIds as $id) {
            if (!empty($map[$id])) {
                $catNames[] = $map[$id];
            }
        }
    }

    $parts = [];

    if ($scope !== 'products' && !$catIds) {
        $parts[] = 'Sản phẩm: Tất cả';
    } else {
        if ($catNames) {
            $label = 'Danh mục: ' . implode(', ', array_slice($catNames, 0, 3));
            if (count($catNames) > 3) {
                $label .= ' +' . (count($catNames) - 3);
            }
            $parts[] = $label;
        } elseif ($catIds) {
            $parts[] = 'Danh mục: ' . count($catIds) . ' mục';
        }

        if ($applyProducts) {
            $parts[] = 'Sản phẩm: ' . count($applyProducts) . ' mục';
        }
    }

    $payRaw = trim((string)($c['payment_methods'] ?? ''));
    if ($payRaw !== '') {
        $keys = array_filter(array_map('trim', explode(',', $payRaw)), fn($v) => $v !== '');
        $labelMap = [];
        if (is_array($voucherPaymentOptions)) {
            foreach ($voucherPaymentOptions as $opt) {
                $k = (string)($opt['key'] ?? '');
                if ($k !== '') {
                    $labelMap[$k] = (string)($opt['label'] ?? $k);
                }
            }
        }
        $names = [];
        foreach ($keys as $k) {
            $names[] = $labelMap[$k] ?? strtoupper($k);
        }
        if ($names) {
            $parts[] = 'Thanh toán: ' . implode(', ', $names);
        }
    }

    $shipRaw = trim((string)($c['shipping_methods'] ?? ''));
    if ($shipRaw !== '') {
        $keys = array_filter(array_map('trim', explode(',', $shipRaw)), fn($v) => $v !== '');
        $labelMap = [];
        if (is_array($ghnShippingOptions)) {
            foreach ($ghnShippingOptions as $opt) {
                $k = (string)($opt['key'] ?? '');
                if ($k !== '') {
                    $labelMap[$k] = (string)($opt['label'] ?? $k);
                }
            }
        }
        $names = [];
        foreach ($keys as $k) {
            $names[] = $labelMap[$k] ?? strtoupper($k);
        }
        if ($names) {
            $parts[] = 'Vận chuyển: ' . implode(', ', $names);
        }
    }

    $userRaw = trim((string)($c['apply_user_ids'] ?? ''));
    if ($userRaw !== '') {
        $ids = array_filter(array_map('trim', explode(',', $userRaw)), fn($v) => $v !== '');
        $parts[] = 'Khách hàng: ' . count($ids) . ' người';
    } else {
        $parts[] = 'Khách hàng: Tất cả';
    }

    return implode(' · ', $parts);
}

function VoucherStatusText($c) {
    return ecommerce_voucher_get_status_text($c);
}

// Tóm tắt gọn ngành hàng/danh mục + phạm vi sản phẩm
function VoucherCategorySummary($c) {
    global $categoryOptions;

    $scope = (string)($c['apply_scope'] ?? 'all');
    $applyProducts = normalizeProductIds((string)($c['apply_product_ids'] ?? ''));

    $catIdsRaw = trim((string)($c['apply_category_ids'] ?? ''));
    $catIds = $catIdsRaw !== '' ? array_filter(array_map('intval', explode(',', $catIdsRaw))) : [];
    $catNames = [];
    if ($catIds && is_array($categoryOptions)) {
        $map = [];
        foreach ($categoryOptions as $opt) {
            $id = (int)($opt['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = (string)($opt['name'] ?? '');
            }
        }
        foreach ($catIds as $id) {
            if (!empty($map[$id])) {
                $catNames[] = $map[$id];
            }
        }
    }
    if (!$catIds && $scope !== 'products') {
        return 'Tất cả sản phẩm';
    }
    $parts = [];
    // Nếu có chọn áp dụng theo sản phẩm thì ưu tiên hiển thị số lượng sản phẩm hơn là số lượng danh mục
    if ($catNames) {
        $label = implode(', ', array_slice($catNames, 0, 2));
        if (count($catNames) > 2) {
            $label .= ' +' . (count($catNames) - 2);
        }
        $parts[] = $label;
    } elseif ($catIds) {
        $parts[] = count($catIds) . ' danh mục';
    }
    // Nếu có chọn áp dụng theo sản phẩm thì ưu tiên hiển thị số lượng sản phẩm hơn là số lượng danh mục
    if ($scope === 'products' && $applyProducts) {
        $parts[] = count($applyProducts) . ' Sản phẩm';
    }

    if (!$parts) {
        return $scope === 'products' ? 'Một số sản phẩm' : 'Tất cả sản phẩm';
    }

    return implode(' - Hiện có: ', $parts);
}

// Tóm tắt gọn thời gian hiệu lực
function VoucherTimeSummary($c) {
    return ecommerce_voucher_get_time_summary($c);
}

// đếm theo template cho tab
$templateCounts = [
    'all' => count($Vouchers),
    'order_discount' => 0,
    'shipping_discount' => 0,
    'only_category_discount' => 0,
    'category_discount' => 0,
    'payment_discount' => 0,
];
foreach ($Vouchers as $vc) {
    $k = determineVoucherTemplateKey($vc);
    if (isset($templateCounts[$k])) {
        $templateCounts[$k]++;
    }
}
?>