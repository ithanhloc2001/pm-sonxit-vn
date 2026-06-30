<?php
require_once __DIR__ . '/../../../config.php';

// Auth check
if (!$isAdmin || !$isLoggedIn) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);
}

set_time_limit(0);
ini_set('memory_limit', '128M');

// Facebook Catalog sync helpers
require_once __DIR__ . '/../lib/facebook_catalog.php';
// Google Merchant Center sync helpers
require_once __DIR__ . '/../lib/google_merchant.php';

// Default VAT
$VAT_DEFAULT = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;

// Database tables configuration
$variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
$variantGroupTable = 'ecommerce_product_variant_groups';
$coatingTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_coating_system']) : 'ecommerce_product_coating_system';
$constructionTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_construction']) : 'ecommerce_product_construction';

$productCols = listColumns($ithanhloc, 'ecommerce_product');
$productHasSkuColumn = hasCol($productCols, 'sku');

// Optimized: ALTER TABLE only executed when needed
if (isset($productCols['storage']) && stripos($productCols['storage']['Type'] ?? '', 'text') === false) {
    $ithanhloc->query("ALTER TABLE ecommerce_product MODIFY COLUMN storage TEXT");
}

$variantHasColorOptionsColumn = false;
$variantHasStatusColumn = false;
$variantHasGroupIdColumn = false;

if ($variantTable !== '') {
    $vCols = listColumns($ithanhloc, $variantTable);
    $variantHasColorOptionsColumn = hasCol($vCols, 'color_options');
    $variantHasStatusColumn = hasCol($vCols, 'status');
    $variantHasGroupIdColumn = hasCol($vCols, 'group_id');
}

$catCols = listColumns($ithanhloc, 'ecommerce_category');
$categoryThumbColumn = '';
if (hasCol($catCols, 'thumb_image')) {
    $categoryThumbColumn = 'thumb_image';
} elseif (hasCol($catCols, 'image_url')) {
    $categoryThumbColumn = 'image_url';
}

// Đảm bảo có cột sort_order để sắp xếp danh mục bằng kéo-thả.
$categoryHasSortOrder = hasCol($catCols, 'sort_order');
if (!$categoryHasSortOrder) {
    if (@$ithanhloc->query("ALTER TABLE `ecommerce_category` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0")) {
        // Khởi tạo sort_order theo id hiện tại để giữ thứ tự đang có.
        @$ithanhloc->query("UPDATE `ecommerce_category` SET `sort_order` = `id` WHERE `sort_order` = 0");
        $catCols = listColumns($ithanhloc, 'ecommerce_category', true);
        $categoryHasSortOrder = hasCol($catCols, 'sort_order');
    }
}
$categoryOrderBy = $categoryHasSortOrder ? 'sort_order ASC, id ASC' : 'id ASC';

$uploadDir = __DIR__ . '/../../../' . ($uploadFolder ?? 'uploads');
pm_ensure_writable_dir($uploadDir);

// =================================================================================
// HELPER FUNCTIONS
// =================================================================================

function pm_ensure_writable_dir(string $path): bool {
    if (!is_dir($path)) {
        if (!@mkdir($path, 0755, true)) return false;
    }
    if (is_writable($path)) return true;
    $testFile = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'wtest_' . uniqid() . '.tmp';
    if (@file_put_contents($testFile, '1')) {
        @unlink($testFile);
        return true;
    }
    return false;
}

function cons_parse_method_files(string $raw): array {
    $txt = trim($raw);
    if ($txt === '') return [];
    if (strlen($txt) > 1 && $txt[0] === '[') {
        $arr = json_decode($txt, true);
        if (is_array($arr)) {
            $out = [];
            foreach ($arr as $item) {
                $p = trim((string)$item);
                if ($p !== '') $out[] = $p;
            }
            return array_values(array_unique($out));
        }
    }
    return [$txt];
}

function cons_pack_method_files(array $paths): string {
    $clean = [];
    foreach ($paths as $p) {
        $p = trim((string)$p);
        if ($p === '') continue;
        $clean[] = $p;
    }
    $clean = array_values(array_unique($clean));
    if (!$clean) return '';
    if (count($clean) === 1) return (string)$clean[0];
    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cons_sanitize_relpath(string $relPath): string {
    $relPath = str_replace(['..\\','../'], '', $relPath);
    $relPath = ltrim($relPath, '/\\');
    $relPath = str_replace('\\', '/', $relPath);
    return $relPath;
}

function cons_is_allowed_pdf_path(string $relPath): bool {
    global $uploadFolder;
    $relPath = str_replace('\\', '/', $relPath);
    return strpos($relPath, ($uploadFolder ?? 'uploads') . '/construction/') === 0;
}

function generateSKU() { return 'SP-' . strtoupper(substr(md5(uniqid()), 0, 6)); }

function normalizePaymentMethodKey(string $key): string {
    $normalized = strtolower(trim($key));
    if ($normalized === '') return '';
    $normalized = preg_replace('/[^a-z0-9_\-]/', '', $normalized);
    if ($normalized === '') return '';
    $aliasMap = [
        'cod' => 'cod',
        'cash' => 'cod',
        'cashondelivery' => 'cod',
        'vnpay' => 'vnpay',
        'vnp' => 'vnpay',
        'bank' => 'vnpay',
        'transfer' => 'vnpay',
        'chuyenkhoan' => 'vnpay',
        'card' => 'vnpay',
        'atm' => 'vnpay',
        'zalopay' => 'zalopay',
        'zalo' => 'zalopay',
        'momo' => 'momo',
        'momoqr' => 'momo',
        'ewallet' => 'momo',
        'wallet' => 'momo',
        'qr' => 'momo',
        'installment' => 'vnpay',
    ];
    return $aliasMap[$normalized] ?? '';
}

function sanitizePaymentOptionsInput($raw): array {
    $parsed = [];
    if (is_array($raw)) {
        $parsed = $raw;
    } elseif (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $parsed = $decoded;
        }
    }
    $allowedOrder = ['cod', 'zalopay', 'vnpay', 'momo'];
    $picked = [];
    foreach ($parsed as $item) {
        if (is_array($item)) {
            $item = $item['key'] ?? $item['value'] ?? '';
        }
        $key = normalizePaymentMethodKey((string)$item);
        if ($key === '') continue;
        $picked[$key] = true;
    }
    $result = [];
    foreach ($allowedOrder as $allowedKey) {
        if (isset($picked[$allowedKey])) {
            $result[] = $allowedKey;
        }
    }
    return $result;
}

function shippingEtaDefaults(string $methodKey): array {
    switch ($methodKey) {
        case 'hoa_toc': return ['min' => 0, 'max' => 1, 'text' => 'Nhận dự kiến hôm nay - ngày mai'];
        case 'cong_kenh': return ['min' => 2, 'max' => 5, 'text' => 'Nhận dự kiến sau 2 - 5 ngày'];
        case 'tu_den_lay': return ['min' => 0, 'max' => 0, 'text' => 'Sẵn sàng để nhận tại chi nhánh'];
        case 'nhanh':
        default:
            return ['min' => 1, 'max' => 3, 'text' => 'Nhận dự kiến sau 1 - 3 ngày'];
    }
}

function buildShippingEtaText(int $etaMin, int $etaMax): string {
    $from = date('d/m', strtotime('+' . max(0, $etaMin) . ' days'));
    $to = date('d/m', strtotime('+' . max(0, $etaMax) . ' days'));
    return 'Nhận dự kiến ' . $from . ' - ' . $to;
}

function processImageSmart($sourcePath, $targetPath, $quality=80, $canvasSize=300, $padding=20) {
    if (!file_exists($sourcePath)) return false;
    if(!function_exists('imagewebp')) return false;
    $info = getimagesize($sourcePath); if (!$info) return false; 
    $mime = $info['mime']; $srcW = $info[0]; $srcH = $info[1];

    switch ($mime) { 
        case 'image/jpeg': $source = imagecreatefromjpeg($sourcePath); break; 
        case 'image/png': $source = imagecreatefrompng($sourcePath); break; 
        case 'image/gif': $source = imagecreatefromgif($sourcePath); break; 
        case 'image/webp': $source = imagecreatefromwebp($sourcePath); break; 
        default: return false; 
    }

    $dest = imagecreatetruecolor($canvasSize, $canvasSize);
    $white = imagecolorallocate($dest, 255, 255, 255);
    imagefill($dest, 0, 0, $white);

    $maxWidth = $canvasSize - ($padding * 2);
    $maxHeight = $canvasSize - ($padding * 2);
    $ratio = min($maxWidth / $srcW, $maxHeight / $srcH);
    
    $newW = floor($srcW * $ratio);
    $newH = floor($srcH * $ratio);
    
    $x = floor(($canvasSize - $newW) / 2);
    $y = floor(($canvasSize - $newH) / 2);

    if ($mime == 'image/png') { imagealphablending($source, true); imagesavealpha($source, true); }
    
    imagecopyresampled($dest, $source, $x, $y, 0, 0, $newW, $newH, $srcW, $srcH);

    $res = imagewebp($dest, $targetPath, $quality);
    imagedestroy($source); imagedestroy($dest);
    return $res;
}

function convertImageToWebpIfPossible(string $srcPath): string {
    if (!is_file($srcPath)) return '';
    if (!function_exists('imagewebp')) return basename($srcPath);

    $info = @getimagesize($srcPath);
    if (!$info || empty($info['mime'])) return basename($srcPath);
    $mime = $info['mime'];

    if ($mime === 'image/webp') return basename($srcPath);

    $src = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $src = @imagecreatefromjpeg($srcPath);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $src = @imagecreatefrompng($srcPath);
    } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        $src = @imagecreatefromgif($srcPath);
    }

    if (!$src) return basename($srcPath);

    $dir = dirname($srcPath);
    $base = pathinfo($srcPath, PATHINFO_FILENAME);
    $target = $dir . DIRECTORY_SEPARATOR . $base . '.webp';

    $ok = @imagewebp($src, $target, 80);
    imagedestroy($src);

    if ($ok && is_file($target)) {
        @unlink($srcPath);
        return basename($target);
    }

    return basename($srcPath);
}

/**
 * Tải ảnh từ URL ngoài về file tạm. Trả về đường dẫn file tạm khi thành công, '' khi thất bại.
 * Ưu tiên cURL, fallback sang allow_url_fopen. Giới hạn 12MB, chỉ nhận content-type image/*.
 */
function downloadRemoteImageToTmp(string $url): string {
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return '';

    $maxBytes = 12 * 1024 * 1024; // 12MB
    $data = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ProductImageFetcher/1.0)',
            CURLOPT_BUFFERSIZE => 65536,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use ($maxBytes) {
                return ($dlTotal > $maxBytes || $dlNow > $maxBytes) ? 1 : 0;
            },
        ]);
        $data = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($data === false || $httpCode < 200 || $httpCode >= 300) $data = false;
    }

    if ($data === false && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 30, 'follow_location' => 1, 'max_redirects' => 5,
                       'user_agent' => 'Mozilla/5.0 (compatible; ProductImageFetcher/1.0)'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $data = @file_get_contents($url, false, $ctx, 0, $maxBytes + 1);
    }

    if ($data === false || $data === '' || strlen($data) > $maxBytes) return '';

    $tmp = tempnam(sys_get_temp_dir(), 'pimg_');
    if ($tmp === false) return '';
    if (file_put_contents($tmp, $data) === false) { @unlink($tmp); return ''; }

    // Xác thực thực sự là ảnh (đừng tin mỗi content-type)
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime']) || strpos((string)$info['mime'], 'image/') !== 0) {
        @unlink($tmp);
        return '';
    }

    return $tmp;
}

// =================================================================================
// AJAX HANDLERS
// =================================================================================

if (isset($_GET['ajax'])) {
    $act = $_GET['ajax'];

    $allowedShippingKeysForList = ecommerce_get_shipping_method_label_map($ithanhloc);
    $legacyShippingMapForList = [
        'nhanh' => 'ghn_nhanh',
        'cong_kenh' => 'ghn_tiet_kiem',
        'hoa_toc' => 'ghn_hoa_toc',
    ];

    $productShippingHasAnyActive = static function($raw) use ($allowedShippingKeysForList, $legacyShippingMapForList): bool {
        if (!is_string($raw) || trim($raw) === '') return false;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !$decoded) return false;

        foreach ($decoded as $methodItem) {
            $methodKey = '';
            $isActive = true;
            if (is_string($methodItem)) {
                $methodKey = strtolower(trim($methodItem));
            } elseif (is_array($methodItem)) {
                $methodKey = strtolower(trim((string)($methodItem['key'] ?? $methodItem['value'] ?? '')));
                if (array_key_exists('active', $methodItem)) {
                    $isActive = !empty($methodItem['active']);
                }
            }
            if (!$isActive) continue;
            $methodKey = preg_replace('/[^a-z0-9_\-]/', '', $methodKey);
            if (isset($legacyShippingMapForList[$methodKey])) {
                $methodKey = $legacyShippingMapForList[$methodKey];
            }
            if ($methodKey === '') continue;
            if (!isset($allowedShippingKeysForList[$methodKey])) continue;
            return true;
        }
        return false;
    };
    
    if ($act === 'categories') {
        $res = $ithanhloc->query("SELECT * FROM ecommerce_category ORDER BY {$categoryOrderBy}");
        $data = [];
        while($r = $res->fetch_assoc()){
            $data[] = $r;
        }
        jOut(['data' => $data]);
    }
    
    if ($act === 'products_by_category') {
        $catId = (int)($_GET['category_id'] ?? 0);
        $where = '1=1';
        $params = [];
        $types = '';
        if($catId > 0){
            $where .= ' AND category_id = ?';
            $params[] = $catId;
            $types .= 'i';
        }
        $sql = "SELECT id, product_name, (SELECT sku_variant FROM ecommerce_product_variants v WHERE v.product_id = ecommerce_product.id AND TRIM(COALESCE(v.sku_variant,'')) <> '' ORDER BY v.price ASC, v.id ASC LIMIT 1) AS sku, image_url FROM ecommerce_product WHERE $where ORDER BY product_name ASC LIMIT 200";
        $stmt = $ithanhloc->prepare($sql);
        if($types){ $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while($row = $res->fetch_assoc()){
            $rows[] = $row;
        }
        $stmt->close();
        jOut(['ok'=>true,'products'=>$rows]);
    }
    
    if ($act === 'product_coating') {
        if ($coatingTable === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu bảng hệ thống sơn']);
        }
        $pid = intval($_GET['pid'] ?? 0);
        if ($pid <= 0) {
            jOut(['ok' => true, 'rows' => []]);
        }
        $sql = "SELECT c.id, c.product_id, c.category_id, c.suggest_product_id, c.layer_type, c.layer_count, c.sort_order,
                   p.product_name AS suggest_product_name,
                   cat.name AS category_name
                FROM `{$coatingTable}` c
                LEFT JOIN ecommerce_product p ON p.id = c.suggest_product_id
                LEFT JOIN ecommerce_category cat ON cat.id = c.category_id
                WHERE c.product_id = ?
                ORDER BY c.sort_order ASC, c.id ASC";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Lỗi chuẩn bị truy vấn hệ thống sơn']);
        }
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        jOut(['ok' => true, 'rows' => $rows]);
    }
    
    if ($act === 'product_construction') {
        if ($constructionTable === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu bảng dữ liệu thi công']);
        }
        $pid = intval($_GET['pid'] ?? 0);
        if ($pid <= 0) {
            jOut(['ok' => true, 'row' => null]);
        }
        $stmt = $ithanhloc->prepare("SELECT * FROM `{$constructionTable}` WHERE product_id = ? LIMIT 1");
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Lỗi chuẩn bị truy vấn dữ liệu thi công']);
        }
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        jOut(['ok' => true, 'row' => $row ?: null]);
    }
    
    if ($act === 'partners') {
        $data = [];
        $partnerTable = '';
        $tableCheckNew = $ithanhloc->query("SHOW TABLES LIKE 'site_partner'");
        if($tableCheckNew && $tableCheckNew->num_rows > 0){
            $partnerTable = 'site_partner';
        }
        if($partnerTable !== ''){
            $res = $ithanhloc->query("SELECT * FROM {$partnerTable} ORDER BY id ASC");
            while($res && ($r = $res->fetch_assoc())){
                $data[] = $r;
            }
        }
        jOut(['data' => $data]);
    }
    
    if ($act === 'get_variants') {
        if ($variantTable === '') jOut(['ok'=>false, 'msg'=>'Thiếu bảng phân loại sản phẩm']);
        $pid = intval($_GET['pid']);
        $sql = "SELECT v.*, g.name AS group_name FROM `{$variantTable}` v LEFT JOIN `ecommerce_product_variant_groups` g ON g.id = v.group_id WHERE v.product_id=$pid ORDER BY (CASE WHEN v.image_url IS NULL OR v.image_url = '' THEN 0 ELSE 1 END) ASC, v.id DESC";
        $res = $ithanhloc->query($sql);
        $data = [];
        while($r = $res->fetch_assoc()){
            $data[] = $r;
        }
        jOut(['ok'=>true, 'data'=>$data]);
    }
    
    if ($act === 'get_variant_groups') {
        $pid = intval($_GET['pid'] ?? 0);
        if($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
        $res = $ithanhloc->query("SELECT * FROM `ecommerce_product_variant_groups` WHERE product_id=$pid ORDER BY sort_order ASC, id ASC");
        $data = [];
        while($r = $res->fetch_assoc()){
            $data[] = $r;
        }
        jOut(['ok' => true, 'data' => $data]);
    }

    if ($act === 'product_detail') {
        $pid = intval($_GET['pid'] ?? 0);
        if($pid <= 0) jOut(['ok'=>false, 'msg'=>'Thiếu sản phẩm']);
        $stmt = $ithanhloc->prepare("SELECT * FROM ecommerce_product WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if(!$product) jOut(['ok'=>false, 'msg'=>'Không tìm thấy sản phẩm']);
        if ($variantTable === '') jOut(['ok'=>false, 'msg'=>'Thiếu bảng phân loại sản phẩm']);
        $sql = "SELECT v.*, g.name AS group_name FROM `{$variantTable}` v LEFT JOIN `ecommerce_product_variant_groups` g ON g.id = v.group_id WHERE v.product_id=$pid ORDER BY (CASE WHEN v.image_url IS NULL OR v.image_url = '' THEN 0 ELSE 1 END) ASC, v.id DESC";
        $res = $ithanhloc->query($sql);
        $variants = [];
        while($r = $res->fetch_assoc()){
            $variants[] = $r;
        }
        $resG = $ithanhloc->query("SELECT * FROM `ecommerce_product_variant_groups` WHERE product_id=$pid ORDER BY sort_order ASC, id ASC");
        $groups = [];
        while($resG && ($rg = $resG->fetch_assoc())) { $groups[] = $rg; }
        jOut(['ok'=>true, 'product'=>$product, 'variants'=>$variants, 'groups'=>$groups]);
    }

    if ($act === 'products') {
        $draw = intval($_GET['draw'] ?? 1); $start = max(0, intval($_GET['start'] ?? 0)); 
        $len = intval($_GET['length'] ?? 10);
        $search = trim($_GET['search']['value'] ?? ''); 
        $catFilter = intval($_GET['cat_filter'] ?? 0); 
        $customSort = $_GET['custom_sort'] ?? 'newest';

        $filterNoPrice = intval($_GET['filter_no_price'] ?? 0);
        $filterNoImage = intval($_GET['filter_no_image'] ?? 0);
        $stockFilter = trim((string)($_GET['stock_filter'] ?? ''));
        $statusFilter = trim((string)($_GET['status_filter'] ?? ''));

        $orderSQL = "p.id DESC"; 
        switch ($customSort) {
            case 'oldest': $orderSQL = "p.id ASC"; break;
            case 'id_asc': $orderSQL = "p.id ASC"; break;
            case 'id_desc': $orderSQL = "p.id DESC"; break;
            case 'name_asc': $orderSQL = "p.product_name ASC"; break;
            case 'name_desc': $orderSQL = "p.product_name DESC"; break;
            case 'price_asc': $orderSQL = "(SELECT MIN(v.price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) ASC"; break;
            case 'price_desc': $orderSQL = "(SELECT MIN(v.price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) DESC"; break;
            case 'stock_asc': $orderSQL = "(SELECT COALESCE(SUM(v.stock_quantity),0) FROM ecommerce_product_variants v WHERE v.product_id = p.id) ASC"; break;
            case 'stock_desc': $orderSQL = "(SELECT COALESCE(SUM(v.stock_quantity),0) FROM ecommerce_product_variants v WHERE v.product_id = p.id) DESC"; break;
        }

        $where = "WHERE 1=1"; $params = []; $types = "";
        if($search){
            $where .= " AND (p.product_name LIKE ? OR EXISTS (SELECT 1 FROM ecommerce_product_variants v WHERE v.product_id = p.id AND v.sku_variant LIKE ?))";
            $like = "%$search%";
            $params = [$like, $like];
            $types = "ss";
        }
        if($catFilter > 0){ $where .= " AND p.category_id = ?"; $params[] = $catFilter; $types .= "i"; }

        if($filterNoPrice === 1){
            $where .= " AND NOT EXISTS (
                SELECT 1 FROM ecommerce_product_variants vp
                WHERE vp.product_id = p.id
                  AND vp.price IS NOT NULL
                  AND TRIM(CAST(vp.price AS CHAR)) <> ''
                  AND CAST(vp.price AS DECIMAL(18,2)) > 0
            )";
        }

        if($filterNoImage === 1){
            $where .= " AND (p.image_url IS NULL OR TRIM(p.image_url) = '')";
        }

        if($stockFilter === 'in_stock'){
            $where .= " AND COALESCE((SELECT SUM(vs.stock_quantity) FROM ecommerce_product_variants vs WHERE vs.product_id = p.id), 0) > 0";
        } elseif($stockFilter === 'out_of_stock'){
            $where .= " AND COALESCE((SELECT SUM(vs.stock_quantity) FROM ecommerce_product_variants vs WHERE vs.product_id = p.id), 0) <= 0";
        }

        if($statusFilter === 'true' || $statusFilter === 'false'){
            $where .= " AND p.status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }

        $stmtC = $ithanhloc->prepare("SELECT COUNT(*) c FROM ecommerce_product p $where"); if($types) $stmtC->bind_param($types, ...$params); $stmtC->execute();
        $recordsFiltered = $stmtC->get_result()->fetch_assoc()['c']; 
        $recordsTotal = $ithanhloc->query("SELECT COUNT(*) c FROM ecommerce_product")->fetch_assoc()['c'];

        if($len < 0){
            $sql = "SELECT p.* FROM ecommerce_product p $where ORDER BY $orderSQL";
            $stmt = $ithanhloc->prepare($sql);
            if($types) $stmt->bind_param($types, ...$params);
            $stmt->execute();
        } else {
            if($len === 0) $len = 10;
            $sql = "SELECT p.* FROM ecommerce_product p $where ORDER BY $orderSQL LIMIT ?,?";
            $stmt = $ithanhloc->prepare($sql);
            $typesPaged = $types . "ii";
            $paramsPaged = $params;
            $paramsPaged[] = $start;
            $paramsPaged[] = $len;
            $stmt->bind_param($typesPaged, ...$paramsPaged);
            $stmt->execute();
        }
        $res = $stmt->get_result();
        
        $data = [];
        while($r = $res->fetch_assoc()){
            $r['thumb'] = $r['image_url'] ? $r['image_url'] : ''; 
            $vQ = $ithanhloc->query("SELECT MIN(price) min_price, MAX(price) max_price, SUM(stock_quantity) total_stock FROM ecommerce_product_variants WHERE product_id=".$r['id']);
            $vR = $vQ->fetch_assoc();
            if($vR['min_price'] !== null){
                $r['gia_text'] = ($vR['min_price'] == $vR['max_price']) ? formatVND($vR['min_price']) : formatVND($vR['min_price']) . ' - ' . formatVND($vR['max_price']);
                $r['kho_text'] = number_format($vR['total_stock']);
            } else { $r['gia_text'] = '<span class="text-muted small">Chưa nhập</span>'; $r['kho_text'] = '0'; }

            $r['shipping_config_missing'] = !$productShippingHasAnyActive($r['shipping_methods'] ?? '');
            $data[] = $r;
        }
        jOut(['draw'=>$draw, 'recordsTotal'=>$recordsTotal, 'recordsFiltered'=>$recordsFiltered, 'data'=>$data]);
    }
}

// =================================================================================
// POST ACTIONS
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // ===== FACEBOOK CATALOG =====
    if ($act === 'fb_save_config') {
        $catalogId = trim((string)($_POST['catalog_id'] ?? ''));
        $token     = trim((string)($_POST['token'] ?? ''));
        $version   = trim((string)($_POST['version'] ?? ''));
        $auto      = !empty($_POST['auto']) ? '1' : '0';
        app_upsert_bot_setting_value($ithanhloc, 'FACEBOOK_CATALOG_ID', $catalogId, true);
        // Chỉ ghi đè token khi người dùng nhập mới (tránh xoá token cũ khi để trống)
        if ($token !== '') app_upsert_bot_setting_value($ithanhloc, 'FACEBOOK_CATALOG_TOKEN', $token, true);
        if ($version !== '') app_upsert_bot_setting_value($ithanhloc, 'FACEBOOK_GRAPH_VERSION', $version, true);
        app_upsert_bot_setting_value($ithanhloc, 'FACEBOOK_CATALOG_AUTO', $auto, true);
        jOut(['ok' => true, 'msg' => 'Đã lưu cấu hình Facebook Catalog.']);
    }

    if ($act === 'fb_sync_product') {
        $pid = intval($_POST['id'] ?? $_POST['product_id'] ?? 0);
        $r = fb_catalog_sync_product($ithanhloc, $pid);
        jOut(['ok' => $r['ok'], 'msg' => $r['msg']]);
    }

    if ($act === 'fb_sync_all') {
        $r = fb_catalog_sync_all($ithanhloc);
        jOut(['ok' => $r['ok'], 'msg' => $r['msg'], 'synced' => $r['synced'], 'skipped' => $r['skipped']]);
    }

    // ===== GOOGLE MERCHANT CENTER =====
    if ($act === 'gmc_save_config') {
        $merchantId = trim((string)($_POST['merchant_id'] ?? ''));
        $saJson     = trim((string)($_POST['sa_json'] ?? ''));
        $country    = trim((string)($_POST['target_country'] ?? ''));
        $auto       = !empty($_POST['auto']) ? '1' : '0';
        app_upsert_bot_setting_value($ithanhloc, 'GOOGLE_MERCHANT_ID', $merchantId, false);
        // Chỉ ghi đè service-account JSON khi người dùng nhập mới (tránh xoá cái cũ khi để trống)
        if ($saJson !== '') app_upsert_bot_setting_value($ithanhloc, 'GOOGLE_MERCHANT_SA_JSON', $saJson, true);
        if ($country !== '') app_upsert_bot_setting_value($ithanhloc, 'GOOGLE_MERCHANT_TARGET_COUNTRY', strtoupper($country), false);
        app_upsert_bot_setting_value($ithanhloc, 'GOOGLE_MERCHANT_AUTO', $auto, false);
        jOut(['ok' => true, 'msg' => 'Đã lưu cấu hình Google Merchant.']);
    }

    if ($act === 'gmc_sync_all') {
        $r = gmc_sync_all($ithanhloc);
        jOut(['ok' => $r['ok'], 'msg' => $r['msg'], 'synced' => $r['synced'], 'skipped' => $r['skipped']]);
    }

    if ($act === 'gmc_products') {
        $catId = (int)($_POST['category_id'] ?? $_GET['category_id'] ?? 0);
        $r = gmc_list_products_for_picker($ithanhloc, $catId);
        jOut(['ok' => $r['ok'], 'products' => $r['products'] ?? []]);
    }

    if ($act === 'gmc_sync_selected') {
        $ids = (array)($_POST['ids'] ?? []);
        $r = gmc_sync_ids($ithanhloc, $ids);
        jOut(['ok' => $r['ok'], 'msg' => $r['msg'], 'synced' => $r['synced'], 'skipped' => $r['skipped']]);
    }

    if ($act === 'gmc_delete_selected') {
        $ids = (array)($_POST['ids'] ?? []);
        $r = gmc_delete_ids($ithanhloc, $ids);
        jOut(['ok' => $r['ok'], 'msg' => $r['msg'], 'deleted' => $r['deleted'], 'failed' => $r['failed']]);
    }

    if ($act === 'bulk_update_variants') {
        $p_id = intval($_POST['product_id'] ?? 0);
        $ids = $_POST['ids'] ?? [];
        if(!is_array($ids) || empty($ids)) jOut(['ok'=>false, 'msg'=>'Chưa chọn phân loại nào']);
        
        $fields = [];
        $params = [];
        $types = "";

        if(isset($_POST['group_id'])) {
            $fields[] = "group_id = ?";
            $params[] = intval($_POST['group_id']);
            $types .= "i";
        }
        if(isset($_POST['image_url'])) {
            $fields[] = "image_url = ?";
            $params[] = $_POST['image_url'];
            $types .= "s";
        }
        if(isset($_POST['shipping_weight_value'])) {
            $fields[] = "shipping_weight_value = ?";
            $params[] = floatval($_POST['shipping_weight_value']);
            $types .= "d";
        }
        if(isset($_POST['shipping_weight_unit'])) {
            $fields[] = "shipping_weight_unit = ?";
            $params[] = $_POST['shipping_weight_unit'];
            $types .= "s";
        }
        if(isset($_POST['price'])) {
            $fields[] = "price = ?";
            $params[] = floatval($_POST['price']);
            $types .= "d";
        }
        if(isset($_POST['sku_variant'])) {
            $fields[] = "sku_variant = ?";
            $params[] = $_POST['sku_variant'];
            $types .= "s";
        }
        if(isset($_POST['shipping_length_cm'])) {
            $fields[] = "shipping_length_cm = ?";
            $params[] = intval($_POST['shipping_length_cm']);
            $types .= "i";
        }
        if(isset($_POST['shipping_width_cm'])) {
            $fields[] = "shipping_width_cm = ?";
            $params[] = intval($_POST['shipping_width_cm']);
            $types .= "i";
        }
        if(isset($_POST['shipping_height_cm'])) {
            $fields[] = "shipping_height_cm = ?";
            $params[] = intval($_POST['shipping_height_cm']);
            $types .= "i";
        }
        if(isset($_POST['status'])) {
            $fields[] = "status = ?";
            $params[] = $_POST['status'];
            $types .= "s";
        }

        if(empty($fields)) jOut(['ok'=>false, 'msg'=>'Không có gì để cập nhật']);

        $ids_str = implode(',', array_map('intval', $ids));
        $sql = "UPDATE `{$variantTable}` SET " . implode(', ', $fields) . " WHERE id IN ($ids_str) AND product_id = $p_id";
        
        $stmt = $ithanhloc->prepare($sql);
        if(!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if($stmt->execute()){
            jOut(['ok'=>true, 'msg'=>'Đã cập nhật hàng loạt thành công']);
        } else {
            jOut(['ok'=>false, 'msg'=>'Cập nhật thất bại: ' . $stmt->error]);
        }
        $stmt->close();
    }

    if ($act === 'repair_shipping_methods') {
        $apply = !empty($_POST['apply']) && (string)$_POST['apply'] !== '0' && strtolower((string)$_POST['apply']) !== 'false';
        $limit = (int)($_POST['limit'] ?? 0);
        if ($limit < 0) $limit = 0;

        $allowedShippingKeys = ecommerce_get_shipping_method_label_map($ithanhloc);
        $legacyShippingMap = [
            'nhanh' => 'ghn_nhanh',
            'cong_kenh' => 'ghn_tiet_kiem',
            'hoa_toc' => 'ghn_hoa_toc',
        ];

        $sql = 'SELECT id, shipping_methods FROM ecommerce_product';
        if ($limit > 0) {
            $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit;
        }

        $res = $ithanhloc->query($sql);
        if (!$res) {
            jOut(['ok' => false, 'msg' => 'Không đọc được danh sách sản phẩm: ' . $ithanhloc->error]);
        }

        $updated = 0;
        $scanned = 0;
        $errors = 0;
        $changedIds = [];

        $stmtUpd = null;
        if ($apply) {
            $stmtUpd = $ithanhloc->prepare('UPDATE ecommerce_product SET shipping_methods=? WHERE id=?');
            if (!$stmtUpd) {
                jOut(['ok' => false, 'msg' => 'Không chuẩn bị được câu lệnh cập nhật: ' . $ithanhloc->error]);
            }
        }

        while ($row = $res->fetch_assoc()) {
            $scanned++;
            $id = (int)($row['id'] ?? 0);
            $raw = $row['shipping_methods'] ?? '';

            $decoded = null;
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
            }

            $selectedKeyMap = [];
            if (is_array($decoded)) {
                foreach ($decoded as $methodItem) {
                    $methodKey = '';
                    $isActive = true;
                    if (is_string($methodItem)) {
                        $methodKey = strtolower(trim($methodItem));
                    } elseif (is_array($methodItem)) {
                        $methodKey = strtolower(trim((string)($methodItem['key'] ?? $methodItem['value'] ?? '')));
                        if (array_key_exists('active', $methodItem)) {
                            $isActive = !empty($methodItem['active']);
                        }
                    }
                    if (!$isActive) continue;
                    $methodKey = preg_replace('/[^a-z0-9_\-]/', '', $methodKey);
                    if (isset($legacyShippingMap[$methodKey])) {
                        $methodKey = $legacyShippingMap[$methodKey];
                    }
                    if ($methodKey === '') continue;
                    if (!isset($allowedShippingKeys[$methodKey])) continue;
                    $selectedKeyMap[$methodKey] = true;
                }
            }

            if (!$selectedKeyMap) {
                foreach ($allowedShippingKeys as $methodKey => $methodLabel) {
                    $selectedKeyMap[$methodKey] = true;
                }
            }

            $normalized = [];
            foreach ($allowedShippingKeys as $methodKey => $methodLabel) {
                if (!isset($selectedKeyMap[$methodKey])) continue;
                $normalized[] = ['key' => $methodKey, 'label' => $methodLabel, 'active' => true];
            }
            $normalizedJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);

            $oldNormalizedComparable = '';
            if (is_string($raw) && trim($raw) !== '') {
                $oldDecoded = json_decode($raw, true);
                if (is_array($oldDecoded)) {
                    $oldKeyMap = [];
                    foreach ($oldDecoded as $methodItem) {
                        $methodKey = '';
                        $isActive = true;
                        if (is_string($methodItem)) {
                            $methodKey = strtolower(trim($methodItem));
                        } elseif (is_array($methodItem)) {
                            $methodKey = strtolower(trim((string)($methodItem['key'] ?? $methodItem['value'] ?? '')));
                            if (array_key_exists('active', $methodItem)) {
                                $isActive = !empty($methodItem['active']);
                            }
                        }
                        if (!$isActive) continue;
                        $methodKey = preg_replace('/[^a-z0-9_\-]/', '', $methodKey);
                        if (isset($legacyShippingMap[$methodKey])) {
                            $methodKey = $legacyShippingMap[$methodKey];
                        }
                        if ($methodKey === '') continue;
                        if (!isset($allowedShippingKeys[$methodKey])) continue;
                        $oldKeyMap[$methodKey] = true;
                    }
                    if (!$oldKeyMap) {
                        foreach ($allowedShippingKeys as $methodKey => $methodLabel) {
                            $oldKeyMap[$methodKey] = true;
                        }
                    }
                    $oldNormalized = [];
                    foreach ($allowedShippingKeys as $methodKey => $methodLabel) {
                        if (!isset($oldKeyMap[$methodKey])) continue;
                        $oldNormalized[] = ['key' => $methodKey, 'label' => $methodLabel, 'active' => true];
                    }
                    $oldNormalizedComparable = json_encode($oldNormalized, JSON_UNESCAPED_UNICODE);
                }
            }
            if ($oldNormalizedComparable === '') {
                $oldNormalizedComparable = json_encode(array_map(function($k, $l){ return ['key'=>$k,'label'=>$l,'active'=>true]; }, array_keys($allowedShippingKeys), array_values($allowedShippingKeys)), JSON_UNESCAPED_UNICODE);
            }

            if ($normalizedJson !== $oldNormalizedComparable) {
                $updated++;
                if (count($changedIds) < 50) {
                    $changedIds[] = $id;
                }
                if ($apply && $stmtUpd) {
                    if ($id > 0) {
                        $stmtUpd->bind_param('si', $normalizedJson, $id);
                        if (!$stmtUpd->execute()) {
                            $errors++;
                        }
                    }
                }
            }
        }
        if ($stmtUpd) {
            $stmtUpd->close();
        }

        jOut([
            'ok' => true,
            'apply' => $apply,
            'scanned' => $scanned,
            'updated' => $updated,
            'errors' => $errors,
            'sample_changed_ids' => $changedIds,
        ]);
    }

    if ($act === 'convert_all_webp') { 
        set_time_limit(300); 
        $rootDir = dirname(dirname(dirname(__DIR__)));
        $q = $ithanhloc->query("SELECT id, image_url FROM ecommerce_product WHERE image_url != ''"); 
        $count = 0; 
        while($row = $q->fetch_assoc()){ 
            $oldFile = $rootDir . '/' . ltrim($row['image_url'], '/'); 
            if(file_exists($oldFile)){ 
                $fInfo = pathinfo($oldFile);
                if(strtolower($fInfo['extension']) !== 'webp') {
                    $newName = $fInfo['filename'].'.webp';
                    $newPath = $rootDir . '/' . ($uploadFolder ?? 'uploads') . '/' . $newName;
                    if(processImageSmart($oldFile, $newPath, 80, 800, 0)){
                        $dbLink = ($uploadFolder ?? 'uploads') . '/' . $newName;
                        $ithanhloc->query("UPDATE ecommerce_product SET image_url='$dbLink' WHERE id=".$row['id']);
                        @unlink($oldFile); $count++;
                    }
                }
            }
        } 
        jOut(['ok'=>true, 'msg'=>"Đã tối ưu $count ảnh sang WebP!"]); 
    }

    if ($act === 'save_product_quick') {
        $id = intval($_POST['id'] ?? 0); $name = trim((string)($_POST['product_name'] ?? '')); $slugInput = trim((string)($_POST['slug'] ?? ''));
        $statusRaw = (string)($_POST['status'] ?? 'true'); $catId = intval($_POST['category_id'] ?? 0); $skuInput = trim((string)($_POST['sku'] ?? ''));
        $sku = $skuInput !== '' ? strtoupper($skuInput) : '';
        if($id <= 0 || $name === '') jOut(['ok'=>false, 'msg'=>'Thiếu ID hoặc tên sản phẩm']);
        $status = ($statusRaw === 'false' || $statusRaw === '0') ? 'false' : 'true';
        if ($status === 'true' && !ecommerce_product_has_variants($ithanhloc, $variantTable, $id)) jOut(['ok'=>false, 'msg'=>'Không thể bật sản phẩm khi chưa có phân loại. Vui lòng tạo ít nhất 1 phân loại trước.']);
        $slugFinal = $slugInput !== '' ? pm_slugify($slugInput) : pm_slugify($name);
        if ($productHasSkuColumn) {
            $stmt = $ithanhloc->prepare('UPDATE ecommerce_product SET product_name=?, slug=?, sku=?, status=?, category_id=? WHERE id=?');
            $stmt->bind_param('ssssii', $name, $slugFinal, $sku, $status, $catId, $id);
        } else {
            $stmt = $ithanhloc->prepare('UPDATE ecommerce_product SET product_name=?, slug=?, status=?, category_id=? WHERE id=?');
            $stmt->bind_param('sssii', $name, $slugFinal, $status, $catId, $id);
        }
        if(!$stmt->execute()) jOut(['ok'=>false, 'msg'=>'Lỗi execute: '.$stmt->error]);
        jOut(['ok'=>true]);
    }

    if ($act === 'clone_product') {
        $srcId = intval($_POST['id'] ?? 0);
        if($srcId <= 0){
            jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm nguồn để nhân bản']);
        }

        $stmtSrc = $ithanhloc->prepare("SELECT * FROM ecommerce_product WHERE id=? LIMIT 1");
        if(!$stmtSrc){
            jOut(['ok' => false, 'msg' => 'Không thể chuẩn bị truy vấn sản phẩm nguồn']);
        }
        $stmtSrc->bind_param('i', $srcId);
        $stmtSrc->execute();
        $resSrc = $stmtSrc->get_result();
        $row = $resSrc ? $resSrc->fetch_assoc() : null;
        $stmtSrc->close();

        if(!$row){
            jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm nguồn']);
        }

        $cat = (int)($row['category_id'] ?? 0);
        $name = (string)($row['product_name'] ?? '');
        $origSlug = trim((string)($row['slug'] ?? ''));
        if ($origSlug === '') {
            if (function_exists('pm_slugify')) {
                $baseSlug = pm_slugify($name !== '' ? $name : ('san-pham-' . $srcId));
            } else {
                $baseSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name !== '' ? $name : ('san-pham-' . $srcId)));
                $baseSlug = trim($baseSlug, '-');
            }
        } else {
            if (function_exists('pm_slugify')) {
                $baseSlug = pm_slugify($origSlug);
            } else {
                $baseSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $origSlug));
                $baseSlug = trim($baseSlug, '-');
            }
        }
        if ($baseSlug === '') {
            $baseSlug = 'san-pham-' . $srcId;
        }

        $slug = $baseSlug;
        $suffix = 2;
        while (true) {
            $stmtCheck = $ithanhloc->prepare('SELECT id FROM ecommerce_product WHERE slug = ? LIMIT 1');
            if (!$stmtCheck) break;
            $stmtCheck->bind_param('s', $slug);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $exists = $resCheck && $resCheck->num_rows > 0;
            $stmtCheck->close();
            if (!$exists) break;
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        $brand = (string)($row['manufacturer'] ?? '');
        $region = (string)($row['region_scope'] ?? '');
        $p1 = (string)($row['resin_type'] ?? '');
        $p2 = (string)($row['voc'] ?? '');
        $p3 = (string)($row['solid_content'] ?? '');
        $p4 = (string)($row['coverage'] ?? '');
        $p5 = (string)($row['gloss_level'] ?? '');
        $p6 = (string)($row['drying_time'] ?? '');
        $d1 = (string)($row['description'] ?? '');
        $d2 = (string)($row['key_features'] ?? '');
        $d3 = (string)($row['applications'] ?? '');
        $d4 = (string)($row['coating_system'] ?? '');
        $d7 = (string)($row['storage'] ?? '');
        $paintSpace = (string)($row['paint_space'] ?? '');
        $paintPositionsJSON = (string)($row['paint_positions'] ?? '[]');
        $paintNeedsJSON = (string)($row['paint_needs'] ?? '[]');
        $paymentJSON = (string)($row['payment_options'] ?? '[]');
        $mediaJSON = (string)($row['media_gallery'] ?? '[]');
        $shippingJSON = (string)($row['shipping_methods'] ?? '[]');
        $imageRatio = (string)($row['image_ratio'] ?? '1:1');
        if ($imageRatio !== '1:1' && $imageRatio !== '3:4') {
            $imageRatio = '1:1';
        }
        $preorderEnabled = (string)($row['preorder_enabled'] ?? '0');
        $bulkPriceTiersJSON = (string)($row['bulk_price_tiers'] ?? '[]');
        $vat_enabled = (int)($row['vat_enabled'] ?? 1) ? 1 : 0;
        $vat = (float)($row['vat'] ?? $VAT_DEFAULT);

        $insertTypes = 'i' . str_repeat('s', 24) . 'id';
        $stmt = $ithanhloc->prepare("INSERT INTO ecommerce_product (category_id, product_name, slug, manufacturer, region_scope, resin_type, voc, solid_content, coverage, gloss_level, drying_time, description, key_features, applications, coating_system, storage, paint_space, paint_positions, paint_needs, payment_options, media_gallery, shipping_methods, image_ratio, preorder_enabled, bulk_price_tiers, vat_enabled, vat, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'true')");
        if(!$stmt){
            jOut(['ok' => false, 'msg' => 'Không thể chuẩn bị câu lệnh nhân bản sản phẩm']);
        }
        $stmt->bind_param($insertTypes, $cat, $name, $slug, $brand, $region, $p1,$p2,$p3,$p4,$p5,$p6, $d1,$d2,$d3,$d4,$d7, $paintSpace, $paintPositionsJSON, $paintNeedsJSON, $paymentJSON, $mediaJSON, $shippingJSON, $imageRatio, $preorderEnabled, $bulkPriceTiersJSON, $vat_enabled, $vat);
        $ok = $stmt->execute();
        $newId = $ok ? (int)$ithanhloc->insert_id : 0;
        $stmt->close();

        if(!$ok || $newId <= 0){
            jOut(['ok' => false, 'msg' => 'Không thể nhân bản sản phẩm']);
        }

        $allowCustomColor = (int)($row['allow_custom_color'] ?? 0);
        $stmtFlag = $ithanhloc->prepare("UPDATE ecommerce_product SET allow_custom_color=? WHERE id=?");
        if ($stmtFlag) {
            $stmtFlag->bind_param('ii', $allowCustomColor, $newId);
            $stmtFlag->execute();
            $stmtFlag->close();
        }

        if ($variantTable !== '') {
            $colRes = $ithanhloc->query("SHOW COLUMNS FROM `{$variantTable}`");
            $cols = [];
            while ($colRes && ($c = $colRes->fetch_assoc())) {
                $cols[] = $c['Field'];
            }
            if ($cols) {
                $colsEscaped = array_map(function($c){ return '`' . $c . '`'; }, $cols);
                $colsList = implode(',', $colsEscaped);
                if (in_array('product_id', $cols, true)) {
                    $colsWithoutId = array_values(array_filter($cols, function($c){ return $c !== 'id'; }));
                    $colsWithoutIdEscaped = array_map(function($c){ return '`' . $c . '`'; }, $colsWithoutId);
                    $colsInsert = implode(',', $colsWithoutIdEscaped);
                    $selectCols = [];
                    foreach ($colsWithoutId as $c) {
                        if ($c === 'product_id') {
                            $selectCols[] = (string)$newId . ' AS `product_id`';
                        } else {
                            $selectCols[] = '`' . $c . '`';
                        }
                    }
                    $selectList = implode(',', $selectCols);
                    $sqlCloneVariant = "INSERT INTO `{$variantTable}` ({$colsInsert}) SELECT {$selectList} FROM `{$variantTable}` WHERE product_id = " . (int)$srcId;
                    $ithanhloc->query($sqlCloneVariant);
                }
            }
        }

        if ($coatingTable !== '') {
            $sqlCloneCoating = "INSERT INTO `{$coatingTable}` (product_id, category_id, suggest_product_id, layer_type, layer_count, sort_order) SELECT " . (int)$newId . ", category_id, suggest_product_id, layer_type, layer_count, sort_order FROM `{$coatingTable}` WHERE product_id = " . (int)$srcId;
            $ithanhloc->query($sqlCloneCoating);
            ecommerce_product_rebuild_coating_summary($ithanhloc, $coatingTable, $newId);
        }

        if ($constructionTable !== '') {
            $colRes2 = $ithanhloc->query("SHOW COLUMNS FROM `{$constructionTable}`");
            $cols2 = [];
            while ($colRes2 && ($c2 = $colRes2->fetch_assoc())) {
                $cols2[] = $c2['Field'];
            }
            if ($cols2 && in_array('product_id', $cols2, true)) {
                $colsWithoutId2 = array_values(array_filter($cols2, function($c){ return $c !== 'id'; }));
                $colsInsert2 = implode(',', array_map(function($c){ return '`' . $c . '`'; }, $colsWithoutId2));
                $selectCols2 = [];
                foreach ($colsWithoutId2 as $c) {
                    if ($c === 'product_id') {
                        $selectCols2[] = (int)$newId . ' AS `product_id`';
                    } else {
                        $selectCols2[] = '`' . $c . '`';
                    }
                }
                $selectList2 = implode(',', $selectCols2);
                $sqlCloneConstruction = "INSERT INTO `{$constructionTable}` ({$colsInsert2}) SELECT {$selectList2} FROM `{$constructionTable}` WHERE product_id = " . (int)$srcId . " LIMIT 1";
                $ithanhloc->query($sqlCloneConstruction);
            }
        }

        jOut(['ok' => true, 'new_id' => $newId]);
    }

    if ($act === 'quick_update_cat') {
        $id = intval($_POST['id']);
        $val = intval($_POST['value']);
        $ithanhloc->query("UPDATE ecommerce_product SET category_id=$val WHERE id=$id");
        jOut(['ok'=>true]);
    }

    if ($act === 'upload_media') {
        $kind = $_POST['media_kind'] ?? 'image';
        if (empty($_FILES['file'])) jOut(['ok' => false, 'msg' => 'Không tìm thấy file upload']);
        $file = $_FILES['file'];
        if (!is_uploaded_file($file['tmp_name'])) jOut(['ok' => false, 'msg' => 'File không hợp lệ']);

        $origName = $file['name'] ?? 'media.png';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        
        $targetSubDir = $uploadDir . '/media';
        pm_ensure_writable_dir($targetSubDir);

        $newName = 'media_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target = $targetSubDir . '/' . $newName;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $relPath = ($uploadFolder ?? 'uploads') . '/media/' . $newName;
            if ($kind === 'image') {
                $webpName = convertImageToWebpIfPossible($target);
                $relPath = ($uploadFolder ?? 'uploads') . '/media/' . $webpName;
            }
            if (function_exists('media_publish_local_file')) {
                media_publish_local_file($relPath);
            }
            jOut(['ok' => true, 'url' => $relPath]);
        } else {
            jOut(['ok' => false, 'msg' => 'Không thể lưu file media']);
        }
    }

    if ($act === 'up_cat_thumb' && isset($_FILES['img'])) {
        global $categoryThumbColumn;
        $catId = intval($_POST['id'] ?? 0);
        if ($catId <= 0) {
            jOut(['ok'=>false,'msg'=>'Thiếu danh mục']);
        }
        if ($categoryThumbColumn === '') {
            jOut(['ok'=>false,'msg'=>'Thiếu cột ảnh thumb cho danh mục (thumb_image hoặc image_url)']);
        }

        $tmpName = $_FILES['img']['tmp_name'] ?? '';
        if(!is_uploaded_file($tmpName)){
            jOut(['ok'=>false,'msg'=>'File không hợp lệ']);
        }

        $origExt = strtolower(pathinfo($_FILES['img']['name'] ?? '', PATHINFO_EXTENSION));
        if(!$origExt) $origExt = 'jpg';

        $name = 'c_'.time().rand(100,999).'.webp';
        $target = $uploadDir.'/'.$name;
        $ok = false;

        if (function_exists('processImageSmart')) {
            $ok = processImageSmart($tmpName, $target, 85, 600, 30);
        }

        if(!$ok){
            $fallbackName = 'c_'.time().rand(100,999).'.'.$origExt;
            $fallbackTarget = $uploadDir.'/'.$fallbackName;
            if(move_uploaded_file($tmpName, $fallbackTarget)){
                $name = $fallbackName;
                $ok = true;
            }
        }

        if(!$ok){
            jOut(['ok'=>false,'msg'=>'Không xử lý được ảnh danh mục']);
        }

        $dbLink = ($uploadFolder ?? 'uploads') . '/' . $name;
        if (function_exists('media_publish_local_file')) {
            media_publish_local_file($dbLink);
        }
        $sql = "UPDATE ecommerce_category SET `{$categoryThumbColumn}` = ? WHERE id = ?";
        $stmtU = $ithanhloc->prepare($sql);
        if ($stmtU) {
            $stmtU->bind_param('si', $dbLink, $catId);
            $stmtU->execute();
            $stmtU->close();
        }
        jOut(['ok'=>true,'url'=>$dbLink]);
    }

    if ($act === 'up_img_url') {
        $id = intval($_POST['id'] ?? 0);
        $url = trim((string)($_POST['url'] ?? ''));
        if($id <= 0) jOut(['ok'=>false, 'msg'=>'Thiếu ID sản phẩm']);
        if($url === '') jOut(['ok'=>false, 'msg'=>'Thiếu URL ảnh']);

        // Tải ảnh ngoài về + nén thành webp 800px nội bộ (giảm dung lượng, cắt phụ thuộc server bên thứ ba).
        // Nếu không tải/nén được thì giữ nguyên URL gốc để không chặn người dùng.
        $dbLink = $url;
        $tmp = downloadRemoteImageToTmp($url);
        if ($tmp !== '') {
            $name = 'p_' . $id . '_' . time() . '.webp';
            $target = $uploadDir . '/' . $name;
            $ok = false;
            if (function_exists('processImageSmart')) {
                $ok = processImageSmart($tmp, $target, 85, 800, 10);
            }
            @unlink($tmp);
            if ($ok) {
                $dbLink = ($uploadFolder ?? 'uploads') . '/' . $name;
            }
        }

        $stmt = $ithanhloc->prepare("UPDATE ecommerce_product SET image_url = ? WHERE id = ?");
        $stmt->bind_param('si', $dbLink, $id);
        if($stmt->execute()){
            $stmt->close();
            jOut(['ok'=>true, 'url'=>$dbLink]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok'=>false, 'msg'=>'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'up_img' && isset($_FILES['img'])) {
        $pid = intval($_POST['id'] ?? 0);
        if ($pid <= 0) jOut(['ok'=>false, 'msg'=>'Thiếu ID sản phẩm']);
        
        $tmpName = $_FILES['img']['tmp_name'] ?? '';
        if(!is_uploaded_file($tmpName)) jOut(['ok'=>false, 'msg'=>'File không hợp lệ']);
        
        $origName = $_FILES['img']['name'] ?? '';
        $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if(!$origExt) $origExt = 'jpg';

        $name = 'p_' . $pid . '_' . time() . '.webp';
        $target = $uploadDir . '/' . $name;
        $ok = false;

        if (function_exists('processImageSmart')) {
            $ok = processImageSmart($tmpName, $target, 85, 800, 10);
        }

        if(!$ok){
            $name = 'p_' . $pid . '_' . time() . '.' . $origExt;
            $target = $uploadDir . '/' . $name;
            if(move_uploaded_file($tmpName, $target)){
                $ok = true;
            }
        }

        if ($ok) {
            $dbLink = ($uploadFolder ?? 'uploads') . '/' . $name;
            if (function_exists('media_publish_local_file')) {
                media_publish_local_file($dbLink);
            }
            $stmt = $ithanhloc->prepare("UPDATE ecommerce_product SET image_url = ? WHERE id = ?");
            $stmt->bind_param('si', $dbLink, $pid);
            $stmt->execute();
            $stmt->close();
            jOut(['ok'=>true, 'url'=>$dbLink]);
        } else {
            jOut(['ok'=>false, 'msg'=>'Không xử lý được ảnh']);
        }
    }

    if ($act === 'up_variant_img_url') {
        $pid = intval($_POST['pid'] ?? 0);
        $vid = intval($_POST['variant_id'] ?? 0);
        $url = trim((string)($_POST['url'] ?? ''));
        if($vid <= 0) jOut(['ok'=>false, 'msg'=>'Thiếu ID phân loại']);
        if($url === '') jOut(['ok'=>false, 'msg'=>'Thiếu URL ảnh']);
        
        $stmt = $ithanhloc->prepare("UPDATE `{$variantTable}` SET image_url = ? WHERE id = ? AND product_id = ?");
        $stmt->bind_param('sii', $url, $vid, $pid);
        if($stmt->execute()){
            $stmt->close();
            jOut(['ok'=>true, 'url'=>$url]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok'=>false, 'msg'=>'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'up_variant_img' && isset($_FILES['img'])) {
        $pid = intval($_POST['pid'] ?? 0);
        $vid = intval($_POST['variant_id'] ?? 0);
        if ($vid <= 0) jOut(['ok'=>false, 'msg'=>'Thiếu ID phân loại']);
        
        $tmpName = $_FILES['img']['tmp_name'] ?? '';
        if(!is_uploaded_file($tmpName)) jOut(['ok'=>false, 'msg'=>'File không hợp lệ']);
        
        $origName = $_FILES['img']['name'] ?? '';
        $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if(!$origExt) $origExt = 'jpg';

        $name = 'v_' . $pid . '_' . $vid . '_' . time() . '.webp';
        $target = $uploadDir . '/' . $name;
        $ok = false;

        if (function_exists('processImageSmart')) {
            $ok = processImageSmart($tmpName, $target, 85, 800, 10);
        }

        if(!$ok){
            $name = 'v_' . $pid . '_' . $vid . '_' . time() . '.' . $origExt;
            $target = $uploadDir . '/' . $name;
            if(move_uploaded_file($tmpName, $target)){
                $ok = true;
            }
        }

        if ($ok) {
            $dbLink = ($uploadFolder ?? 'uploads') . '/' . $name;
            if (function_exists('media_publish_local_file')) {
                media_publish_local_file($dbLink);
            }
            $stmt = $ithanhloc->prepare("UPDATE `{$variantTable}` SET image_url = ? WHERE id = ? AND product_id = ?");
            $stmt->bind_param('sii', $dbLink, $vid, $pid);
            $stmt->execute();
            $stmt->close();
            jOut(['ok'=>true, 'url'=>$dbLink]);
        } else {
            jOut(['ok'=>false, 'msg'=>'Không xử lý được ảnh phân loại']);
        }
    }

    if ($act === 'save_variant_group') {
        $id = intval($_POST['id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $status = intval($_POST['status'] ?? 1);
        $sortOrder = intval($_POST['sort_order'] ?? 0);

        if($productId <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
        if($name === '') jOut(['ok' => false, 'msg' => 'Thiếu tên nhóm']);

        if($id <= 0){
            $stmt = $ithanhloc->prepare("INSERT INTO `ecommerce_product_variant_groups` (product_id, name, slug, status, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('issii', $productId, $name, $slug, $status, $sortOrder);
        } else {
            $stmt = $ithanhloc->prepare("UPDATE `ecommerce_product_variant_groups` SET name=?, slug=?, status=?, sort_order=? WHERE id=? AND product_id=?");
            $stmt->bind_param('ssiiii', $name, $slug, $status, $sortOrder, $id, $productId);
        }

        if($stmt->execute()){
            $newId = ($id <= 0) ? intval($ithanhloc->insert_id) : $id;
            $stmt->close();
            jOut(['ok' => true, 'id' => $newId]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'del_variant_group') {
        $id = intval($_POST['id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        if($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID nhóm']);
        
        $ithanhloc->query("UPDATE `{$variantTable}` SET group_id = 0 WHERE group_id = $id AND product_id = $productId");
        
        $stmt = $ithanhloc->prepare("DELETE FROM `ecommerce_product_variant_groups` WHERE id=? AND product_id=?");
        $stmt->bind_param('ii', $id, $productId);
        if($stmt->execute()){
            $stmt->close();
            jOut(['ok' => true]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'sort_variant_groups') {
        $productId = intval($_POST['product_id'] ?? 0);
        $order = $_POST['order'] ?? [];
        if(!is_array($order)) $order = explode(',', (string)$order);
        $order = array_map('intval', $order);
        
        foreach($order as $idx => $groupId){
            $stmt = $ithanhloc->prepare("UPDATE `ecommerce_product_variant_groups` SET sort_order=? WHERE id=? AND product_id=?");
            $stmt->bind_param('iii', $idx, $groupId, $productId);
            $stmt->execute();
            $stmt->close();
        }
        jOut(['ok' => true]);
    }

    if ($act === 'save_variant') {
        $vId = intval($_POST['v_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $groupId = intval($_POST['group_id'] ?? 0);
        $name = trim((string)($_POST['variant_name'] ?? ''));
        $price = trim((string)($_POST['price'] ?? '0'));
        $stock = trim((string)($_POST['stock_quantity'] ?? '0'));
        $sku = trim((string)($_POST['sku_variant'] ?? ''));
        $weight = floatval($_POST['shipping_weight_value'] ?? 0);
        $unit = trim((string)($_POST['shipping_weight_unit'] ?? 'kg'));
        $len = intval($_POST['shipping_length_cm'] ?? 20);
        $wid = intval($_POST['shipping_width_cm'] ?? 20);
        $hei = intval($_POST['shipping_height_cm'] ?? 20);
        $status = intval($_POST['status'] ?? 1);

        if($productId <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);

        if($vId <= 0){
            $stmt = $ithanhloc->prepare("INSERT INTO `{$variantTable}` (product_id, group_id, variant_name, price, stock_quantity, sku_variant, shipping_weight_value, shipping_weight_unit, shipping_length_cm, shipping_width_cm, shipping_height_cm, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissssdsiiii', $productId, $groupId, $name, $price, $stock, $sku, $weight, $unit, $len, $wid, $hei, $status);
        } else {
            $stmt = $ithanhloc->prepare("UPDATE `{$variantTable}` SET group_id=?, variant_name=?, price=?, stock_quantity=?, sku_variant=?, shipping_weight_value=?, shipping_weight_unit=?, shipping_length_cm=?, shipping_width_cm=?, shipping_height_cm=?, status=? WHERE id=? AND product_id=?");
            $stmt->bind_param('issssdssiiiii', $groupId, $name, $price, $stock, $sku, $weight, $unit, $len, $wid, $hei, $status, $vId, $productId);
        }

        if($stmt->execute()){
            $finalVId = ($vId <= 0) ? intval($ithanhloc->insert_id) : $vId;
            $stmt->close();
            jOut(['ok' => true, 'v_id' => $finalVId]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'del_variant') {
        $id = intval($_POST['id'] ?? 0);
        error_log("DEL_VARIANT DEBUG: act=del_variant, id=$id, variantTable=$variantTable");
        if($id <= 0) {
            error_log("DEL_VARIANT DEBUG: Missing or invalid ID");
            jOut(['ok' => false, 'msg' => 'Thiếu ID biến thể']);
        }
        $stmt = $ithanhloc->prepare("DELETE FROM `{$variantTable}` WHERE id=?");
        if (!$stmt) {
            error_log("DEL_VARIANT DEBUG: Prepare failed: " . $ithanhloc->error);
            jOut(['ok' => false, 'msg' => 'Lỗi chuẩn bị câu lệnh: ' . $ithanhloc->error]);
        }
        $stmt->bind_param('i', $id);
        if($stmt->execute()){
            $affected = $stmt->affected_rows;
            error_log("DEL_VARIANT DEBUG: Execute success, affected_rows=$affected");
            $stmt->close();
            jOut(['ok' => true, 'affected' => $affected]);
        } else {
            $err = $stmt->error;
            error_log("DEL_VARIANT DEBUG: Execute failed: " . $err);
            $stmt->close();
            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'save_product') {
        $id = intval($_POST['id'] ?? 0);
        $catId = intval($_POST['category_id'] ?? 0);
        $name = trim((string)($_POST['product_name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $sku = trim((string)($_POST['sku'] ?? ''));
        $brand = trim((string)($_POST['manufacturer'] ?? ''));
        $vatEnabled = intval($_POST['vat_enabled'] ?? 0);
        $vat = floatval($_POST['vat'] ?? 0);
        $region = $_POST['region_scope'] ?? '[]';
        $resin = $_POST['resin_type'] ?? '';
        $voc = $_POST['voc'] ?? '';
        $solid = $_POST['solid_content'] ?? '';
        $coverage = $_POST['coverage'] ?? '';
        $stockQty = trim((string)($_POST['stock_quantily'] ?? ''));
        $gloss = $_POST['gloss_level'] ?? '';
        $drying = $_POST['drying_time'] ?? '';
        $desc = $_POST['description'] ?? '';
        $features = $_POST['key_features'] ?? '';
        $apps = $_POST['applications'] ?? '';
        $storage = $_POST['storage'] ?? '';
        $paintSpace = $_POST['paint_space'] ?? '';
        $paintPos = $_POST['paint_positions'] ?? '[]';
        $paintNeeds = $_POST['paint_needs'] ?? '[]';
        $ratio = $_POST['image_ratio'] ?? '1:1';
        $preorder = $_POST['preorder_enabled'] ?? '0';
        $shipMethods = $_POST['shipping_methods'] ?? '[]';
        $media = $_POST['media_gallery'] ?? '[]';
        $bulkTiers = $_POST['bulk_price_tiers'] ?? '[]';
        $status = ($_POST['status'] ?? 'true') === 'false' ? 'false' : 'true';
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));

        if($name === '') jOut(['ok' => false, 'msg' => 'Thiếu tên sản phẩm']);

        if ($status === 'true') {
            if ($id <= 0) {
                $variantsJson = $_POST['variants'] ?? '';
                $vData = json_decode($variantsJson, true);
                if (!is_array($vData) || empty($vData)) {
                    jOut(['ok' => false, 'msg' => 'Không thể bật sản phẩm khi chưa có phân loại. Vui lòng tạo ít nhất 1 phân loại trước.']);
                }
            } else {
                if (!ecommerce_product_has_variants($ithanhloc, $variantTable, $id)) {
                    jOut(['ok' => false, 'msg' => 'Không thể bật sản phẩm khi chưa có phân loại. Vui lòng tạo ít nhất 1 phân loại trước.']);
                }
            }
        }

        $ithanhloc->begin_transaction();

        if($id <= 0){
            $stmt = $ithanhloc->prepare("INSERT INTO ecommerce_product (category_id, product_name, slug, sku, manufacturer, vat_enabled, vat, region_scope, resin_type, voc, solid_content, coverage, stock_quantily, gloss_level, drying_time, description, key_features, applications, storage, paint_space, paint_positions, paint_needs, image_ratio, preorder_enabled, shipping_methods, media_gallery, bulk_price_tiers, image_url, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('issssidssssssssssssssssssssss', $catId, $name, $slug, $sku, $brand, $vatEnabled, $vat, $region, $resin, $voc, $solid, $coverage, $stockQty, $gloss, $drying, $desc, $features, $apps, $storage, $paintSpace, $paintPos, $paintNeeds, $ratio, $preorder, $shipMethods, $media, $bulkTiers, $imageUrl, $status);
        } else {
            $stmt = $ithanhloc->prepare("UPDATE ecommerce_product SET category_id=?, product_name=?, slug=?, sku=?, manufacturer=?, vat_enabled=?, vat=?, region_scope=?, resin_type=?, voc=?, solid_content=?, coverage=?, stock_quantily=?, gloss_level=?, drying_time=?, description=?, key_features=?, applications=?, storage=?, paint_space=?, paint_positions=?, paint_needs=?, image_ratio=?, preorder_enabled=?, shipping_methods=?, media_gallery=?, bulk_price_tiers=?, image_url=?, status=? WHERE id=?");
            $stmt->bind_param('issssidssssssssssssssssssssssi', $catId, $name, $slug, $sku, $brand, $vatEnabled, $vat, $region, $resin, $voc, $solid, $coverage, $stockQty, $gloss, $drying, $desc, $features, $apps, $storage, $paintSpace, $paintPos, $paintNeeds, $ratio, $preorder, $shipMethods, $media, $bulkTiers, $imageUrl, $status, $id);
        }

        if($stmt->execute()){
            $finalId = ($id <= 0) ? intval($ithanhloc->insert_id) : $id;
            $stmt->close();
            
            if ($id <= 0) {
                // 1. Coating system
                $coatingSystemJson = $_POST['coating_system'] ?? '';
                if ($coatingSystemJson !== '') {
                    $cRows = json_decode($coatingSystemJson, true);
                    if (is_array($cRows)) {
                        foreach ($cRows as $row) {
                            $catIdVal = intval($row['category_id'] ?? 0);
                            $prodIdVal = intval($row['suggest_product_id'] ?? 0);
                            $layerType = trim((string)($row['layer_type'] ?? ''));
                            $layerCount = intval($row['layer_count'] ?? 0);
                            
                            $stmtCS = $ithanhloc->prepare("INSERT INTO `{$coatingTable}` (product_id, category_id, suggest_product_id, layer_type, layer_count) VALUES (?, ?, ?, ?, ?)");
                            if (!$stmtCS) {
                                $ithanhloc->rollback();
                                jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $ithanhloc->error]);
                            }
                            $stmtCS->bind_param('iiisi', $finalId, $catIdVal, $prodIdVal, $layerType, $layerCount);
                            if (!$stmtCS->execute()) {
                                $err = $stmtCS->error;
                                $stmtCS->close();
                                $ithanhloc->rollback();
                                jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
                            }
                            $stmtCS->close();
                        }
                    }
                }
                
                // 2. Construction
                $constructionDataJson = $_POST['construction_data'] ?? '';
                if ($constructionDataJson !== '') {
                    $cData = json_decode($constructionDataJson, true);
                    if (is_array($cData)) {
                        $tools = $cData['tools'] ?? '';
                        $surfaceEnabled = intval($cData['surface_prep_enabled'] ?? 0);
                        $surfaceNew = $cData['surface_prep_new'] ?? '';
                        $surfaceOld = $cData['surface_prep_old'] ?? '';
                        $methodText = $cData['method_text'] ?? '';
                        $methodFile = $cData['method_file'] ?? '[]';
                        
                        $stmtC = $ithanhloc->prepare("INSERT INTO `{$constructionTable}` (product_id, tools, surface_prep_enabled, surface_prep_new, surface_prep_old, method_text, method_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmtC) {
                            $ithanhloc->rollback();
                            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $ithanhloc->error]);
                        }
                        $stmtC->bind_param('isissss', $finalId, $tools, $surfaceEnabled, $surfaceNew, $surfaceOld, $methodText, $methodFile);
                        if (!$stmtC->execute()) {
                            $err = $stmtC->error;
                            $stmtC->close();
                            $ithanhloc->rollback();
                            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
                        }
                        $stmtC->close();
                        if (function_exists('ecommerce_product_rebuild_construction_summary')) {
                             ecommerce_product_rebuild_construction_summary($ithanhloc, $constructionTable, $finalId);
                        }
                    }
                }
                
                // 3. Variant Groups & Variants
                $variantGroupsJson = $_POST['variant_groups'] ?? '';
                $variantsJson = $_POST['variants'] ?? '';
                
                if ($variantGroupsJson !== '' && $variantsJson !== '') {
                    $vGroups = json_decode($variantGroupsJson, true);
                    $vData = json_decode($variantsJson, true);
                    
                    if (is_array($vGroups) && is_array($vData)) {
                        $groupIdMap = [];
                        foreach ($vGroups as $g) {
                            $oldGroupId = intval($g['id'] ?? 0);
                            $gName = trim((string)($g['name'] ?? ''));
                            $gSlug = trim((string)($g['slug'] ?? ''));
                            $gStatus = intval($g['status'] ?? 1);
                            $gSort = intval($g['sort_order'] ?? 0);
                            
                            $stmtG = $ithanhloc->prepare("INSERT INTO ecommerce_product_variant_groups (product_id, name, slug, status, sort_order) VALUES (?, ?, ?, ?, ?)");
                            if (!$stmtG) {
                                $ithanhloc->rollback();
                                jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $ithanhloc->error]);
                            }
                            $stmtG->bind_param('issii', $finalId, $gName, $gSlug, $gStatus, $gSort);
                            if (!$stmtG->execute()) {
                                $err = $stmtG->error;
                                $stmtG->close();
                                $ithanhloc->rollback();
                                jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
                            }
                            $newGroupId = intval($ithanhloc->insert_id);
                            $stmtG->close();
                            
                            $groupIdMap[$oldGroupId] = $newGroupId;
                        }
                        
                        foreach ($vData as $v) {
                            $oldGroupId = intval($v['group_id'] ?? 0);
                            $realGroupId = isset($groupIdMap[$oldGroupId]) ? $groupIdMap[$oldGroupId] : 0;
                            $vName = trim((string)($v['variant_name'] ?? ''));
                            $vColor = trim((string)($v['color'] ?? ''));
                            $vSku = trim((string)($v['sku_variant'] ?? ''));
                            $vWeightValue = floatval($v['shipping_weight_value'] ?? 1.0);
                            $vWeightUnit = trim((string)($v['shipping_weight_unit'] ?? 'kg'));
                            $vLength = intval($v['shipping_length_cm'] ?? 20);
                            $vWidth = intval($v['shipping_width_cm'] ?? 20);
                            $vHeight = intval($v['shipping_height_cm'] ?? 20);
                            $vPrice = trim((string)($v['price'] ?? '0'));
                            $vStock = trim((string)($v['stock_quantity'] ?? '0'));
                            $vStatus = intval($v['status'] ?? 1);
                            $vImgUrl = trim((string)($v['image_url'] ?? ''));
                            
                            $stmtV = $ithanhloc->prepare("INSERT INTO ecommerce_product_variants (product_id, group_id, variant_name, color, sku_variant, shipping_weight_value, shipping_weight_unit, shipping_length_cm, shipping_width_cm, shipping_height_cm, price, stock_quantity, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if (!$stmtV) {
                                $ithanhloc->rollback();
                                jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $ithanhloc->error]);
                            }
                            $stmtV->bind_param('iisssdsiiissss', $finalId, $realGroupId, $vName, $vColor, $vSku, $vWeightValue, $vWeightUnit, $vLength, $vWidth, $vHeight, $vPrice, $vStock, $vStatus, $vImgUrl);
                            if (!$stmtV->execute()) {
                                $err = $stmtV->error;
                                $stmtV->close();
                                $ithanhloc->rollback();
                                jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
                            }
                            $stmtV->close();
                        }
                    }
                }
            }
            $ithanhloc->commit();
            // Tự động đồng bộ lên Facebook Catalog (nếu bật) — không chặn nếu lỗi
            fb_catalog_auto_sync($ithanhloc, (int)$finalId, 'save');
            // Tự động đồng bộ lên Google Merchant Center (nếu bật) — không chặn nếu lỗi
            gmc_auto_sync($ithanhloc, (int)$finalId, 'save');
            jOut(['ok' => true, 'new_id' => ($id <= 0 ? $finalId : 0)]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            $ithanhloc->rollback();
            jOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $err]);
        }
    }

    if ($act === 'bulk_update') {
        $ids = $_POST['ids'] ?? [];
        if(!is_array($ids)) $ids = explode(',', (string)$ids);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if(!$ids) jOut(['ok'=>false, 'msg'=>'Chưa chọn sản phẩm']);

        $fields = [];
        $params = [];
        $types = '';

        if(isset($_POST['category_id']) && $_POST['category_id'] !== ''){
            $fields[] = 'category_id=?';
            $params[] = intval($_POST['category_id']);
            $types .= 'i';
        }

        if(isset($_POST['status']) && $_POST['status'] !== ''){
            $status = $_POST['status'] === 'true' ? 'true' : 'false';
            if ($status === 'true' && $variantTable !== '') {
                $safeIds = implode(',', array_map('intval', $ids));
                $missing = [];
                try {
                    $resMissing = $ithanhloc->query("SELECT p.id FROM ecommerce_product p LEFT JOIN `{$variantTable}` v ON v.product_id = p.id WHERE p.id IN ({$safeIds}) AND v.id IS NULL");
                    if ($resMissing instanceof mysqli_result) {
                        while ($rowM = $resMissing->fetch_assoc()) {
                            $missing[] = (int)($rowM['id'] ?? 0);
                        }
                    }
                } catch (Throwable $e) {
                    $missing = [];
                }
                $missing = array_values(array_filter($missing, fn($v) => $v > 0));
                if ($missing) {
                    $sample = array_slice($missing, 0, 20);
                    jOut([
                        'ok' => false,
                        'msg' => 'Không thể bật trạng thái bán cho ' . count($missing) . ' sản phẩm chưa có phân loại. ID: ' . implode(',', $sample) . (count($missing) > 20 ? '...' : ''),
                    ]);
                }
            }

            $fields[] = 'status=?';
            $params[] = $status;
            $types .= 's';
        }

        if (isset($_POST['vat_enabled']) && $_POST['vat_enabled'] !== '') {
            $vatEnabled = $_POST['vat_enabled'] === '1' ? 1 : 0;
            $fields[] = 'vat_enabled=?';
            $params[] = $vatEnabled;
            $types .= 'i';
        }

        $vatSetDefaultFlag = isset($_POST['vat_set_default']) && $_POST['vat_set_default'] !== '' && $_POST['vat_set_default'] !== '0' && $_POST['vat_set_default'] !== 'false';
        if ($vatSetDefaultFlag) {
            $vatDefaultBulk = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : $VAT_DEFAULT;
            $vatValue = (float)$vatDefaultBulk;
            if ($vatValue < 0) $vatValue = 0.0;
            if ($vatValue > 100) $vatValue = 100.0;
            $fields[] = 'vat=?';
            $params[] = $vatValue;
            $types .= 'd';
        }

        $brand = trim((string)($_POST['manufacturer'] ?? ''));
        if($brand !== ''){
            $fields[] = 'manufacturer=?';
            $params[] = $brand;
            $types .= 's';
        }

        if (isset($_POST['preorder_enabled']) && $_POST['preorder_enabled'] !== '') {
            $preorderEnabled = ($_POST['preorder_enabled'] === '1' || $_POST['preorder_enabled'] === 'true') ? '1' : '0';
            $fields[] = 'preorder_enabled=?';
            $params[] = $preorderEnabled;
            $types .= 's';
        }

        $shippingMethodsRaw = $_POST['shipping_methods'] ?? '';
        if (is_string($shippingMethodsRaw)) {
            $shippingMethodsRaw = trim($shippingMethodsRaw);
        } else {
            $shippingMethodsRaw = '';
        }
        if ($shippingMethodsRaw !== '') {
            $rawShippingMethods = json_decode($shippingMethodsRaw, true);
            if (is_array($rawShippingMethods)) {
                $allowedShippingKeys = ecommerce_get_shipping_method_label_map($ithanhloc);
                $legacyShippingMap = [
                    'nhanh' => 'ghn_nhanh',
                    'cong_kenh' => 'ghn_tiet_kiem',
                    'hoa_toc' => 'ghn_hoa_toc',
                ];

                $selectedShippingKeyMap = [];
                foreach ($rawShippingMethods as $methodItem) {
                    $methodKey = '';
                    $isActive = true;
                    if (is_string($methodItem)) {
                        $methodKey = strtolower(trim($methodItem));
                    } elseif (is_array($methodItem)) {
                        $methodKey = strtolower(trim((string)($methodItem['key'] ?? $methodItem['value'] ?? '')));
                        if (array_key_exists('active', $methodItem)) {
                            $isActive = !empty($methodItem['active']);
                        }
                    }

                    if (!$isActive) {
                        continue;
                    }

                    $methodKey = preg_replace('/[^a-z0-9_\-]/', '', $methodKey);
                    if (isset($legacyShippingMap[$methodKey])) {
                        $methodKey = $legacyShippingMap[$methodKey];
                    }
                    if ($methodKey === '') continue;
                    if (!isset($allowedShippingKeys[$methodKey])) continue;
                    $selectedShippingKeyMap[$methodKey] = true;
                }

                if ($selectedShippingKeyMap) {
                    $shippingMethods = [];
                    foreach ($allowedShippingKeys as $methodKey => $methodLabel) {
                        $shippingMethods[] = [
                            'key' => $methodKey,
                            'label' => $methodLabel,
                            'active' => isset($selectedShippingKeyMap[$methodKey]),
                        ];
                    }
                    $shippingJSON = json_encode($shippingMethods, JSON_UNESCAPED_UNICODE);
                    $fields[] = 'shipping_methods=?';
                    $params[] = $shippingJSON;
                    $types .= 's';
                }
            }
        }

        $paymentMode = trim((string)($_POST['payment_mode'] ?? ''));
        $paymentSelected = sanitizePaymentOptionsInput($_POST['payment_options'] ?? '[]');
        
        $paymentModeAllowed = ['replace','add','remove','clear'];
        $hasPaymentOperation = in_array($paymentMode, $paymentModeAllowed, true);

        if(!$fields && !$hasPaymentOperation) jOut(['ok'=>false, 'msg'=>'Chưa chọn thay đổi']);

        if($fields){
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $typesBase = $types . str_repeat('i', count($ids));
            $paramsBase = array_merge($params, $ids);

            $stmt = $ithanhloc->prepare("UPDATE ecommerce_product SET " . implode(',', $fields) . " WHERE id IN ($placeholders)");
            $stmt->bind_param($typesBase, ...$paramsBase);
            $stmt->execute();
        }

        if($hasPaymentOperation){
            $stmtSel = $ithanhloc->prepare("SELECT id, payment_options FROM ecommerce_product WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $typesSel = str_repeat('i', count($ids));
            $stmtSel->bind_param($typesSel, ...$ids);
            $stmtSel->execute();
            $rows = $stmtSel->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSel->close();

            $stmtUpd = $ithanhloc->prepare("UPDATE ecommerce_product SET payment_options=? WHERE id=?");
            foreach($rows as $row){
                $pid = (int)($row['id'] ?? 0);
                if($pid <= 0) continue;
                $current = sanitizePaymentOptionsInput($row['payment_options'] ?? '[]');

                $next = $current;
                if($paymentMode === 'replace'){
                    $next = $paymentSelected;
                } elseif($paymentMode === 'add'){
                    $next = array_values(array_unique(array_merge($current, $paymentSelected)));
                } elseif($paymentMode === 'remove'){
                    $next = array_values(array_filter($current, function($x) use ($paymentSelected){
                        return !in_array($x, $paymentSelected, true);
                    }));
                } elseif($paymentMode === 'clear'){
                    $next = [];
                }

                $nextJson = json_encode($next, JSON_UNESCAPED_UNICODE);
                $stmtUpd->bind_param('si', $nextJson, $pid);
                $stmtUpd->execute();
            }
            $stmtUpd->close();
        }

        jOut(['ok'=>true]);
    }

    if ($act === 'save_cat') {
        $id = intval($_POST['id'] ?? 0);
        $nameInput = isset($_POST['name']) ? (string)$_POST['name'] : (string)($_POST['ten_danhmuc'] ?? '');
        $name = trim($nameInput);
        $rawSlug = trim((string)($_POST['slug'] ?? ''));
        $descriptionInput = isset($_POST['description']) ? (string)$_POST['description'] : (string)($_POST['mo_ta'] ?? '');
        $description = trim($descriptionInput);
        $statusRaw = (string)($_POST['status'] ?? '1');
        $status = ($statusRaw === '0' || strtolower($statusRaw) === 'false') ? 0 : 1;

        if ($name === '') {
            jOut(['ok' => false, 'msg' => 'Tên danh mục không được để trống']);
        }

        if ($rawSlug !== '') {
            if (function_exists('pm_slugify')) {
                $slug = pm_slugify($rawSlug);
            } else {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawSlug));
                $slug = trim($slug, '-');
            }
        } else {
            if (function_exists('pm_slugify')) {
                $slug = pm_slugify($name);
            } else {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
                $slug = trim($slug, '-');
            }
        }

        $baseSlug = $slug;
        $suffix = 1;
        while (true) {
            $sqlCheck = "SELECT id FROM ecommerce_category WHERE slug = ?" . ($id > 0 ? " AND id <> ?" : "") . " LIMIT 1";
            $stmtCheck = $ithanhloc->prepare($sqlCheck);
            if ($stmtCheck) {
                if ($id > 0) {
                    $stmtCheck->bind_param('si', $slug, $id);
                } else {
                    $stmtCheck->bind_param('s', $slug);
                }
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result();
                $exists = $resCheck && $resCheck->num_rows > 0;
                $stmtCheck->close();
                if (!$exists) {
                    break;
                }
            } else {
                break;
            }
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        if ($id == 0) {
            $stmt = $ithanhloc->prepare("INSERT INTO ecommerce_category (name, slug, description, status) VALUES (?,?,?,?)");
            if (!$stmt) {
                jOut(['ok' => false, 'msg' => 'Không thể lưu danh mục (INSERT)']);
            }
            $stmt->bind_param('sssi', $name, $slug, $description, $status);
        } else {
            $stmt = $ithanhloc->prepare("UPDATE ecommerce_category SET name = ?, slug = ?, description = ?, status = ? WHERE id = ?");
            if (!$stmt) {
                jOut(['ok' => false, 'msg' => 'Không thể lưu danh mục (UPDATE)']);
            }
            $stmt->bind_param('sssii', $name, $slug, $description, $status, $id);
        }

        if (!$stmt->execute()) {
            $msg = 'Không thể lưu danh mục';
            if ($stmt->error) {
                $msg .= ': ' . $stmt->error;
            }
            $stmt->close();
            jOut(['ok' => false, 'msg' => $msg]);
        }
        $stmt->close();

        jOut(['ok' => true]);
    }

    if ($act === 'del_cat') { 
        $id = intval($_POST['id']); 
        if($ithanhloc->query("SELECT id FROM ecommerce_product WHERE category_id=$id LIMIT 1")->num_rows > 0) {
            jOut(['ok'=>false, 'msg'=>'Có SP trong danh mục']);
        } else { 
            $ithanhloc->query("DELETE FROM ecommerce_category WHERE id=$id"); 
            jOut(['ok'=>true]); 
        } 
    }

    if ($act === 'toggle_cat') {
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0){ jOut(['ok'=>false, 'msg'=>'Thiếu danh mục']); }
        $statusRaw = (string)($_POST['status'] ?? '1');
        $status = ($statusRaw === '0' || strtolower($statusRaw) === 'false') ? 0 : 1;
        $stmt = $ithanhloc->prepare("UPDATE ecommerce_category SET status = ? WHERE id = ?");
        if(!$stmt){ jOut(['ok'=>false, 'msg'=>'Không thể cập nhật trạng thái danh mục']); }
        $stmt->bind_param('ii', $status, $id);
        if(!$stmt->execute()){
            $msg = 'Không thể cập nhật trạng thái danh mục';
            if($stmt->error){ $msg .= ': ' . $stmt->error; }
            $stmt->close();
            jOut(['ok'=>false, 'msg'=>$msg]);
        }
        $stmt->close();
        jOut(['ok'=>true]);
    }

    // Sắp xếp lại thứ tự danh mục bằng kéo-thả: nhận mảng id theo thứ tự mới.
    if ($act === 'reorder_cats') {
        if (!$categoryHasSortOrder) {
            jOut(['ok'=>false, 'msg'=>'Bảng danh mục chưa hỗ trợ sắp xếp (thiếu cột sort_order)']);
        }
        $rawIds = $_POST['ids'] ?? [];
        if (!is_array($rawIds)) {
            $decoded = json_decode((string)$rawIds, true);
            $rawIds = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', (string)$rawIds)));
        }
        $ids = array_values(array_filter(array_map('intval', $rawIds), fn($v) => $v > 0));
        if (!$ids) {
            jOut(['ok'=>false, 'msg'=>'Danh sách thứ tự không hợp lệ']);
        }

        $ithanhloc->begin_transaction();
        try {
            $stmt = $ithanhloc->prepare("UPDATE ecommerce_category SET sort_order = ? WHERE id = ?");
            if (!$stmt) throw new Exception('Prepare failed');
            $order = 1;
            foreach ($ids as $cid) {
                $stmt->bind_param('ii', $order, $cid);
                if (!$stmt->execute()) throw new Exception($stmt->error ?: 'Update failed');
                $order++;
            }
            $stmt->close();
            $ithanhloc->commit();
        } catch (Throwable $e) {
            $ithanhloc->rollback();
            jOut(['ok'=>false, 'msg'=>'Không thể lưu thứ tự: ' . $e->getMessage()]);
        }
        jOut(['ok'=>true, 'msg'=>'Đã lưu thứ tự danh mục']);
    }

    // Nhân bản danh mục: sao chép name (thêm hậu tố), slug mới, mô tả, thumb, status=ẩn.
    if ($act === 'dup_cat') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) jOut(['ok'=>false, 'msg'=>'Thiếu danh mục cần nhân bản']);

        $stmtSrc = $ithanhloc->prepare("SELECT * FROM ecommerce_category WHERE id = ? LIMIT 1");
        if (!$stmtSrc) jOut(['ok'=>false, 'msg'=>'Không thể đọc danh mục']);
        $stmtSrc->bind_param('i', $id);
        $stmtSrc->execute();
        $src = $stmtSrc->get_result()->fetch_assoc();
        $stmtSrc->close();
        if (!$src) jOut(['ok'=>false, 'msg'=>'Không tìm thấy danh mục']);

        $newName = (string)($src['name'] ?? 'Danh mục') . ' (Bản sao)';
        // Tạo slug mới không trùng.
        if (function_exists('pm_slugify')) {
            $baseSlug = pm_slugify($newName);
        } else {
            $baseSlug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $newName)), '-');
        }
        if ($baseSlug === '') $baseSlug = 'danh-muc-copy';
        $slug = $baseSlug;
        $suffix = 1;
        while (true) {
            $chk = $ithanhloc->prepare("SELECT id FROM ecommerce_category WHERE slug = ? LIMIT 1");
            if (!$chk) break;
            $chk->bind_param('s', $slug);
            $chk->execute();
            $chk->store_result();
            $exists = $chk->num_rows > 0;
            $chk->close();
            if (!$exists) break;
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
            if ($suffix > 100) { $slug = $baseSlug . '-' . time(); break; }
        }

        $description = (string)($src['description'] ?? '');
        $thumb = $categoryThumbColumn !== '' ? (string)($src[$categoryThumbColumn] ?? '') : '';
        $status = 0; // bản sao mặc định ẩn để admin kiểm tra trước

        // Đặt sort_order ngay sau bản gốc nếu hỗ trợ.
        $cols = ['name', 'slug', 'description', 'status'];
        $vals = [$newName, $slug, $description, $status];
        $types = 'sssi';
        if ($categoryThumbColumn !== '') {
            $cols[] = $categoryThumbColumn; $vals[] = $thumb; $types .= 's';
        }
        if ($categoryHasSortOrder) {
            $srcOrder = (int)($src['sort_order'] ?? 0);
            $newOrder = $srcOrder + 1;
            // Dịch các danh mục phía sau xuống 1 để chèn bản sao ngay sau bản gốc.
            $shift = $ithanhloc->prepare("UPDATE ecommerce_category SET sort_order = sort_order + 1 WHERE sort_order > ?");
            if ($shift) { $shift->bind_param('i', $srcOrder); $shift->execute(); $shift->close(); }
            $cols[] = 'sort_order'; $vals[] = $newOrder; $types .= 'i';
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO ecommerce_category (`" . implode('`,`', $cols) . "`) VALUES ($placeholders)";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) jOut(['ok'=>false, 'msg'=>'Không thể nhân bản danh mục']);
        $stmt->bind_param($types, ...$vals);
        if (!$stmt->execute()) {
            $msg = 'Không thể nhân bản danh mục' . ($stmt->error ? ': ' . $stmt->error : '');
            $stmt->close();
            jOut(['ok'=>false, 'msg'=>$msg]);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        jOut(['ok'=>true, 'msg'=>'Đã nhân bản danh mục', 'id'=>$newId, 'name'=>$newName]);
    }

    if ($act === 'del_items') {
        $idArr = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0));
        // Xoá khỏi Facebook Catalog + Google Merchant trước khi xoá DB (cần retailer_id/offerId từ DB)
        foreach ($idArr as $delPid) {
            fb_catalog_auto_sync($ithanhloc, $delPid, 'delete');
            gmc_auto_sync($ithanhloc, $delPid, 'delete');
        }
        $ids = implode(',', $idArr);
        if ($ids !== '') $ithanhloc->query("DELETE FROM ecommerce_product WHERE id IN ($ids)");
        jOut(['ok'=>true]);
    }

    if ($act === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $s = ($_POST['s'] ?? '') == 'true' ? 'false' : 'true';
        if ($s === 'true' && !ecommerce_product_has_variants($ithanhloc, $variantTable, $id)) {
            jOut(['ok'=>false, 'msg'=>'Không thể bật sản phẩm khi chưa có phân loại. Vui lòng tạo ít nhất 1 phân loại trước.']);
        }
        $ithanhloc->query("UPDATE ecommerce_product SET status='$s' WHERE id=" . $id);
        // Đồng bộ trạng thái lên Facebook + Google (bật -> insert/UPDATE, tắt -> DELETE; xử lý trong sync_product)
        fb_catalog_auto_sync($ithanhloc, $id, 'save');
        gmc_auto_sync($ithanhloc, $id, 'save');
        jOut(['ok'=>true]);
    }

    if ($act === 'save_construction') {
        $pid = intval($_POST['product_id'] ?? 0);
        if($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
        
        $tools = $_POST['tools'] ?? '';
        $surfaceEnabled = intval($_POST['surface_enabled'] ?? 0);
        $surfaceNew = $_POST['surface_new'] ?? '';
        $surfaceOld = $_POST['surface_old'] ?? '';
        $methodText = $_POST['method_text'] ?? '';
        
        $existingFiles = [];
        $resExist = $ithanhloc->query("SELECT method_file FROM `{$constructionTable}` WHERE product_id = $pid");
        if($resExist && $rowExist = $resExist->fetch_assoc()){
            $existingFiles = cons_parse_method_files($rowExist['method_file'] ?? '');
        }

        $newFiles = $existingFiles;
        if(!empty($_FILES['method_files'])){
            $files = $_FILES['method_files'];
            $targetSubDir = $uploadDir . '/construction';
            pm_ensure_writable_dir($targetSubDir);
            
            foreach($files['name'] as $idx => $name){
                if($files['error'][$idx] === UPLOAD_ERR_OK){
                    $tmpName = $files['tmp_name'][$idx];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if($ext !== 'pdf') continue;
                    
                    $newName = 'cons_' . $pid . '_' . time() . '_' . $idx . '.pdf';
                    $target = $targetSubDir . '/' . $newName;
                    if(move_uploaded_file($tmpName, $target)){
                        $consRel = ($uploadFolder ?? 'uploads') . '/construction/' . $newName;
                        if (function_exists('media_publish_local_file')) {
                            media_publish_local_file($consRel);
                        }
                        $newFiles[] = $consRel;
                    }
                }
            }
        }
        
        $methodFile = cons_pack_method_files($newFiles);
        
        $stmtCheck = $ithanhloc->prepare("SELECT id FROM `{$constructionTable}` WHERE product_id = ?");
        $stmtCheck->bind_param('i', $pid);
        $stmtCheck->execute();
        $exists = $stmtCheck->get_result()->num_rows > 0;
        $stmtCheck->close();
        
        if($exists){
            $stmt = $ithanhloc->prepare("UPDATE `{$constructionTable}` SET tools=?, surface_prep_enabled=?, surface_prep_new=?, surface_prep_old=?, method_text=?, method_file=? WHERE product_id=?");
            $stmt->bind_param('sissssi', $tools, $surfaceEnabled, $surfaceNew, $surfaceOld, $methodText, $methodFile, $pid);
        } else {
            $stmt = $ithanhloc->prepare("INSERT INTO `{$constructionTable}` (product_id, tools, surface_prep_enabled, surface_prep_new, surface_prep_old, method_text, method_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isissss', $pid, $tools, $surfaceEnabled, $surfaceNew, $surfaceOld, $methodText, $methodFile);
        }
        
        if($stmt->execute()){
            $stmt->close();
            if (function_exists('ecommerce_product_rebuild_construction_summary')) {
                 ecommerce_product_rebuild_construction_summary($ithanhloc, $constructionTable, $pid);
            }
            $resNew = $ithanhloc->query("SELECT * FROM `{$constructionTable}` WHERE product_id = $pid");
            jOut(['ok' => true, 'data' => $resNew->fetch_assoc()]);
        } else {
            jOut(['ok' => false, 'msg' => 'Lỗi lưu dữ liệu thi công']);
        }
    }

    if ($act === 'del_construction_pdf') {
        $pid = intval($_POST['product_id'] ?? 0);
        $fileToRemove = trim((string)($_POST['file'] ?? ''));
        if($pid <= 0 || $fileToRemove === '') jOut(['ok' => false, 'msg' => 'Thiếu thông tin']);

        $resExist = $ithanhloc->query("SELECT method_file FROM `{$constructionTable}` WHERE product_id = $pid");
        if($resExist && $rowExist = $resExist->fetch_assoc()){
            $files = cons_parse_method_files($rowExist['method_file'] ?? '');
            $newFiles = array_filter($files, function($f) use ($fileToRemove){
                return $f !== $fileToRemove;
            });
            
            $physicalPath = dirname(dirname(dirname(__DIR__))) . '/' . ltrim($fileToRemove, '/');
            if (file_exists($physicalPath)) @unlink($physicalPath);

            $methodFile = cons_pack_method_files(array_values($newFiles));
            $ithanhloc->query("UPDATE `{$constructionTable}` SET method_file = '" . $ithanhloc->real_escape_string($methodFile) . "' WHERE product_id = $pid");
            
            if (function_exists('ecommerce_product_rebuild_construction_summary')) {
                 ecommerce_product_rebuild_construction_summary($ithanhloc, $constructionTable, $pid);
            }

            $resNew = $ithanhloc->query("SELECT * FROM `{$constructionTable}` WHERE product_id = $pid");
            jOut(['ok' => true, 'data' => $resNew->fetch_assoc()]);
        }
        jOut(['ok' => false, 'msg' => 'Không tìm thấy dữ liệu']);
    }

    if ($act === 'del_construction') {
        $pid = intval($_POST['product_id'] ?? 0);
        if($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
        
        $ithanhloc->query("DELETE FROM `{$constructionTable}` WHERE product_id = $pid");
        if (function_exists('ecommerce_product_rebuild_construction_summary')) {
             ecommerce_product_rebuild_construction_summary($ithanhloc, $constructionTable, $pid);
        }
        jOut(['ok' => true]);
    }

    if ($act === 'save_coating_row') {
        if ($coatingTable === '') jOut(['ok' => false, 'msg' => 'Thiếu bảng hệ thống sơn']);
        $pid = intval($_POST['product_id'] ?? 0);
        $id = intval($_POST['id'] ?? 0);
        if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
        
        $catId = intval($_POST['category_id'] ?? 0);
        $prodId = intval($_POST['suggest_product_id'] ?? 0);
        $layerType = trim((string)($_POST['layer_type'] ?? ''));
        $layerCount = intval($_POST['layer_count'] ?? 0);
        
        if ($id <= 0) {
            $stmt = $ithanhloc->prepare("INSERT INTO `{$coatingTable}` (product_id, category_id, suggest_product_id, layer_type, layer_count) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('iiisi', $pid, $catId, $prodId, $layerType, $layerCount);
        } else {
            $stmt = $ithanhloc->prepare("UPDATE `{$coatingTable}` SET category_id = ?, suggest_product_id = ?, layer_type = ?, layer_count = ? WHERE id = ? AND product_id = ?");
            $stmt->bind_param('iisiii', $catId, $prodId, $layerType, $layerCount, $id, $pid);
        }
        
        if ($stmt && $stmt->execute()) {
            $stmt->close();
            if (function_exists('ecommerce_product_rebuild_coating_summary')) {
                ecommerce_product_rebuild_coating_summary($ithanhloc, $coatingTable, $pid);
            }
            jOut(['ok' => true]);
        } else {
            jOut(['ok' => false, 'msg' => 'Lỗi lưu hệ thống sơn: ' . ($stmt ? $stmt->error : 'Prepare failed')]);
        }
    }

    if ($act === 'del_coating_row') {
        if ($coatingTable === '') jOut(['ok' => false, 'msg' => 'Thiếu bảng hệ thống sơn']);
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID']);
        
        $res = $ithanhloc->query("SELECT product_id FROM `{$coatingTable}` WHERE id = $id");
        $row = $res->fetch_assoc();
        if (!$row) jOut(['ok' => false, 'msg' => 'Không tìm thấy dòng để xóa']);
        $pid = (int)$row['product_id'];
        
        if ($ithanhloc->query("DELETE FROM `{$coatingTable}` WHERE id = $id")) {
            if (function_exists('ecommerce_product_rebuild_coating_summary')) {
                ecommerce_product_rebuild_coating_summary($ithanhloc, $coatingTable, $pid);
            }
            jOut(['ok' => true]);
        } else {
            jOut(['ok' => false, 'msg' => 'Lỗi xóa hệ thống sơn']);
        }
    }
}
