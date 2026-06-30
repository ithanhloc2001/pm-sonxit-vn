<?php

// Ảnh fallback dùng khi sản phẩm không có hình
$fallbackPath = $site_fallback_logo ?: '';
$fallbackImage = function_exists('to_abs_url') ? to_abs_url($fallbackPath, $baseUrl) : (rtrim($baseUrl, '/') . '/' . ltrim($fallbackPath, '/'));

// Xác định bảng sản phẩm / biến thể / đánh giá / danh mục / voucher đang dùng
$categories = [];
$productTable = first_existing_table($ithanhloc, ['ecommerce_product']);
$variantTable = first_existing_table($ithanhloc, ['ecommerce_product_variants']);
$productReviewTable = first_existing_table($ithanhloc, ['ecommerce_product_review']);
$categoryTable = first_existing_table($ithanhloc, ['ecommerce_category']);
$voucherTable = first_existing_table($ithanhloc, ['ecommerce_voucher']);
// Bảng chứa cấu hình video review short cho trang chủ
$reviewShortTable = first_existing_table($ithanhloc, ['ecommerce_review_short']);

// Lấy danh sách khuyến mãi cho trang chủ
$hUserId = (int)($_SESSION['user_id'] ?? 0);
$homePromos = [];
// Thông báo khuyến mãi nay nằm chung bảng user_notification, lọc theo type
$promoCols = list_table_columns($ithanhloc, 'user_notification');
$promoActiveExpr = in_array('is_active', $promoCols, true)
    ? "COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'"
    : "1=1";
$promoSortExpr = in_array('sort_order', $promoCols, true)
    ? "n.sort_order ASC, n.id DESC"
    : "n.id DESC";

$sqlHP = "SELECT n.id, n.title, n.body, n.link, n.meta_json, n.type, n.created_at
          FROM user_notification n
          WHERE {$promoActiveExpr}
            AND LOWER(TRIM(CAST(n.type AS CHAR))) IN ('promotion','promo','voucher','coupon')
            AND (n.send_at IS NULL OR TRIM(CAST(n.send_at AS CHAR))='' OR n.send_at <= NOW())
            AND (n.user_id = 0 OR n.user_id = ?)
          ORDER BY {$promoSortExpr} LIMIT 24";
$stmtHP = $ithanhloc->prepare($sqlHP);
if ($stmtHP) {
    $stmtHP->bind_param('i', $hUserId);
    $stmtHP->execute();
    $homePromos = $stmtHP->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtHP->close();
}

// Chuẩn hoá điều kiện filter sản phẩm đang hoạt động (tuỳ DB dùng cột nào)
$productCols = $productTable !== '' ? list_table_columns($ithanhloc, $productTable) : [];
$categoryCols = $categoryTable !== '' ? list_table_columns($ithanhloc, $categoryTable) : [];
$variantCols = $variantTable !== '' ? list_table_columns($ithanhloc, $variantTable) : [];
// Cột ảnh thumb cho danh mục: ưu tiên thumb_image, fallback image_url nếu có
$categoryThumbColumn = '';
if (!empty($categoryCols)) {
    if (in_array('thumb_image', $categoryCols, true)) {
        $categoryThumbColumn = 'thumb_image';
    } elseif (in_array('image_url', $categoryCols, true)) {
        $categoryThumbColumn = 'image_url';
    }
}
$hasVatColumn = in_array('vat', $productCols, true);
$hasVatEnabledColumn = in_array('vat_enabled', $productCols, true);
$defaultVatPercent = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
$vatMultiplierExpr = $hasVatColumn
    ? ($hasVatEnabledColumn
        ? "(CASE WHEN COALESCE(p.vat_enabled, 1) = 1 THEN (1 + (COALESCE(p.vat, {$defaultVatPercent}) / 100)) ELSE 1 END)"
        : "(1 + (COALESCE(p.vat, {$defaultVatPercent}) / 100))")
    : (string)(1.0 + ($defaultVatPercent / 100.0));
$productActiveExprSql = '';
if (in_array('status', $productCols, true)) {
    $productActiveExprSql = "LOWER(TRIM(CAST(p.status AS CHAR))) IN ('true','1','on','yes','active','enabled')";
} elseif (in_array('is_active', $productCols, true)) {
    $productActiveExprSql = "LOWER(TRIM(CAST(p.is_active AS CHAR))) IN ('1','true','on','yes')";
}
$productActiveWhere = $productActiveExprSql !== '' ? " AND ({$productActiveExprSql})" : '';

// Điều kiện filter danh mục đang bật (status / is_active) để dùng lại ở nhiều query
$categoryActiveWhereSql = '';
$categoryActiveExistsExpr = '';
if ($categoryTable !== '' && $categoryCols) {
    if (in_array('status', $categoryCols, true)) {
        $categoryActiveWhereSql = "WHERE LOWER(TRIM(CAST(status AS CHAR))) IN ('true','1','on','yes','active','enabled')";
        $categoryActiveExistsExpr = "EXISTS (SELECT 1 FROM `{$categoryTable}` cat WHERE cat.id = p.category_id AND LOWER(TRIM(CAST(cat.status AS CHAR))) IN ('true','1','on','yes','active','enabled'))";
    } elseif (in_array('is_active', $categoryCols, true)) {
        $categoryActiveWhereSql = "WHERE LOWER(TRIM(CAST(is_active AS CHAR))) IN ('1','true','on','yes')";
        $categoryActiveExistsExpr = "EXISTS (SELECT 1 FROM `{$categoryTable}` cat WHERE cat.id = p.category_id AND LOWER(TRIM(CAST(cat.is_active AS CHAR))) IN ('1','true','on','yes'))";
    }
}
$categoryActiveWhereForProduct = $categoryActiveExistsExpr !== '' ? " AND {$categoryActiveExistsExpr}" : '';

// Lấy danh sách danh mục (id, tên, thumb) để hiển thị và gợi ý - chỉ lấy danh mục đang bật nếu có cột trạng thái
$catRes = false;
if ($categoryTable !== '') {
    $selectCols = 'id, name, slug';
    if ($categoryThumbColumn !== '') {
        $selectCols .= ', `' . $categoryThumbColumn . '`';
    }
    if (in_array('sort_order', $categoryCols, true)) {
        $selectCols .= ', `sort_order`';
    }
    // Sắp xếp theo sort_order (thứ tự kéo-thả trong admin), fallback id ASC nếu chưa có cột.
    $categoryOrderBy = in_array('sort_order', $categoryCols, true) ? 'sort_order ASC, id ASC' : 'id ASC';
    $sqlCat = "SELECT {$selectCols} FROM `{$categoryTable}` " . ($categoryActiveWhereSql !== '' ? $categoryActiveWhereSql . ' ' : '') . "ORDER BY {$categoryOrderBy}";
    $catRes = $ithanhloc->query($sqlCat);
}
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}
// Lấy điểm rating trung bình và số lượt đánh giá cho từng sản phẩm
// Ví dụ mẫu: rating_avg = 4.5, rating_count = 20
$ratingSelect = ($productReviewTable !== '')
    ? "(SELECT AVG(rating) FROM `{$productReviewTable}` WHERE product_id = p.id) AS rating_avg, (SELECT COUNT(*) FROM `{$productReviewTable}` WHERE product_id = p.id) AS rating_count,"
    : "0 AS rating_avg, 0 AS rating_count,";

// Chuẩn bị các phần dùng chung trong SQL: tên danh mục, join danh mục và giá tối thiểu
$categoryNameExpr = $categoryTable !== '' ? 'c.name AS category_name,' : "'' AS category_name,";
$categoryJoin = $categoryTable !== '' ? "LEFT JOIN `{$categoryTable}` c ON c.id = p.category_id" : '';
$categoryPriorityOrder = $categoryTable !== '' ? "(CASE WHEN c.name LIKE '%sơn tường%' THEN 0 ELSE 1 END) ASC, " : '';
// Giá tối thiểu: lấy từ phân loại (variant) đang hoạt động, ép kiểu số để đảm bảo min theo giá trị
$variantActiveWhereExtra = '';
if (!empty($variantCols)) {
    if (in_array('status', $variantCols, true)) {
        $variantActiveWhereExtra = " AND (status = 1 OR status = '1' OR LOWER(status) = 'true')";
    } elseif (in_array('is_active', $variantCols, true)) {
        $variantActiveWhereExtra = ' AND is_active = 1';
    }
}

$variantMinExpr = $variantTable !== ''
    ? "(COALESCE((SELECT MIN(CAST(price AS DECIMAL(18,2))) FROM `{$variantTable}` WHERE product_id = p.id{$variantActiveWhereExtra}), 0) * {$vatMultiplierExpr}) AS price_min"
    : "(0 * {$vatMultiplierExpr}) AS price_min";

// Lấy danh sách sản phẩm ngẫu nhiên dùng cho block "Gợi ý sản phẩm cho bạn"
// Ví dụ mẫu: (id, product_name, image_url) để hiển thị tên và ảnh, link sẽ xây dựng dựa trên id
$randomLimit = 24;
$randomProducts = [];
$randomRes = false;
if ($productTable !== '') {
    $minId = 0;
    $maxId = 0;
    $minMaxRes = $ithanhloc->query("SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM `{$productTable}`");
    if ($minMaxRes) {
        $mmRow = $minMaxRes->fetch_assoc();
        $minId = (int)($mmRow['min_id'] ?? 0);
        $maxId = (int)($mmRow['max_id'] ?? 0);
    }

    if ($minId > 0 && $maxId >= $minId) {
        $randStart = function_exists('random_int') ? random_int($minId, $maxId) : mt_rand($minId, $maxId);
        $randomSql = "SELECT p.id, p.product_name, p.image_url FROM `{$productTable}` p WHERE p.id >= {$randStart}{$productActiveWhere}{$categoryActiveWhereForProduct} ORDER BY p.id ASC LIMIT $randomLimit";
        $randomRes = $ithanhloc->query($randomSql);
        if (!$randomRes || $randomRes->num_rows < $randomLimit) { //max(6, (int) floor($randomLimit / 2))
            $randomSql = "SELECT p.id, p.product_name, p.image_url FROM `{$productTable}` p WHERE 1=1{$productActiveWhere}{$categoryActiveWhereForProduct} ORDER BY p.id DESC LIMIT $randomLimit";
            $randomRes = $ithanhloc->query($randomSql);
        }
    } else {
        $randomSql = "SELECT p.id, p.product_name, p.image_url FROM `{$productTable}` p WHERE 1=1{$productActiveWhere}{$categoryActiveWhereForProduct} ORDER BY p.id DESC LIMIT $randomLimit";
        $randomRes = $ithanhloc->query($randomSql);
    }
}
if ($randomRes) {
    while ($row = $randomRes->fetch_assoc()) {
        $randomProducts[] = $row;
    }
}


// Lấy danh sách voucher đang còn hiệu lực để dùng ở nhiều nơi (flash voucher, demo giảm giá...)
// Ví dụ voucher: ['code' => 'SAVE10', 'value' => 10, 'apply_scope' => 'all', 'apply_product_ids' => '', 'discount_target' => 'order', ...]
$activeVouchers = [];
if ($voucherTable !== '') {
    $now = date('Y-m-d H:i:s');
    $couponCols = list_table_columns($ithanhloc, $voucherTable);
    $scopeExpr = in_array('apply_scope', $couponCols, true) ? 'c.apply_scope' : "'all'";
    $idsExpr = in_array('apply_product_ids', $couponCols, true) ? 'c.apply_product_ids' : "''";
    $targetExpr = in_array('discount_target', $couponCols, true) ? 'c.discount_target' : "'order'";
    $unitExpr = in_array('value_unit', $couponCols, true) ? 'c.value_unit' : "''";
    $stmtVoucher = $ithanhloc->prepare("SELECT c.code, c.value, c.min_subtotal, c.max_discount, {$scopeExpr} AS apply_scope, {$idsExpr} AS apply_product_ids
                , {$targetExpr} AS discount_target, {$unitExpr} AS value_unit
        FROM `{$voucherTable}` c
        WHERE c.is_active = 1
          AND (c.start_at IS NULL OR c.start_at <= ?)
          AND (c.end_at IS NULL OR c.end_at >= ?)
          AND (c.max_uses IS NULL OR COALESCE(c.used_count, 0) < c.max_uses)
        ORDER BY c.id DESC");
    if ($stmtVoucher) {
        $stmtVoucher->bind_param('ss', $now, $now);
        $stmtVoucher->execute();
        $activeVouchers = $stmtVoucher->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtVoucher->close();
    }
}

// Tách voucher theo loại đích: giảm đơn (orderCoupons) và giảm phí ship (shipCoupons)
// Ví dụ mẫu (shipCoupons): ['code' => 'FREESHIP', 'value' => 10000, 'discount_target' => 'shipping', ...]
$orderCoupons = [];
$shipCoupons = [];
if (!empty($activeVouchers)) {
    foreach ($activeVouchers as $coupon) {
        $target = strtolower(trim((string)($coupon['discount_target'] ?? 'order')));
        if ($target === 'shipping') {
            $shipCoupons[] = $coupon;
        } else {
            $orderCoupons[] = $coupon;
        }
    }
}

// Hàm format nhãn freeship (ví dụ 10000 -> 10K, 50000 -> 50K, 100% ...)
if (!function_exists('home_ship_badge_label')) {
    function home_ship_badge_label(float $value, string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'percent') {
            $pct = max(0, (float)$value);
            if ($pct >= 100) return '100%';
            return rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.') . '%';
        }
        $amount = max(0, (float)$value);
        if ($amount >= 1000) {
            $k = $amount / 1000;
            $txt = (abs($k - round($k)) < 0.01)
                ? (string)round($k)
                : number_format($k, 1, '.', '');
            return $txt . 'K';
        }
        return ((int)round($amount)) . 'đ';
    }
}

$promoNotices = [];
$userIdForPromo = (int)($_SESSION['user_id'] ?? 0);
if ($userIdForPromo > 0) {
    $promoSql = "SELECT DISTINCT n.id, n.title, n.body, n.type, n.link, n.created_at
            FROM user_notification n
            LEFT JOIN user_notification_read r ON r.notification_id=n.id AND r.user_id=?
            WHERE (n.user_id=0 OR n.user_id=?)
                AND LOWER(TRIM(CAST(n.type AS CHAR))) IN ('promotion','promo','voucher','coupon')
                AND COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'
                AND (n.send_at IS NULL OR TRIM(CAST(n.send_at AS CHAR))='' OR n.send_at <= NOW())
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 6";
    $stmtPromo = $ithanhloc->prepare($promoSql);
    if ($stmtPromo) {
        $stmtPromo->bind_param('ii', $userIdForPromo, $userIdForPromo);
        $stmtPromo->execute();
        $promoNotices = $stmtPromo->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPromo->close();
    }
} else {
    $promoSql = "SELECT n.id, n.title, n.body, n.type, n.link, n.created_at
            FROM user_notification n
            WHERE n.user_id=0
                AND LOWER(TRIM(CAST(n.type AS CHAR))) IN ('promotion','promo','voucher','coupon')
                AND COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'
                AND (n.send_at IS NULL OR TRIM(CAST(n.send_at AS CHAR))='' OR n.send_at <= NOW())
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 6";
    $resPromo = $ithanhloc->query($promoSql);
    if ($resPromo) {
        $promoNotices = $resPromo->fetch_all(MYSQLI_ASSOC);
    }
}

// Rút gọn nội dung thông báo (lấy subtitle / content và cắt chiều dài)
if (!function_exists('home_notice_excerpt')) {
    function home_notice_excerpt(string $body, int $limit = 90): string
    {
        $text = '';
        $decoded = json_decode(trim($body), true);
        if (is_array($decoded) && (($decoded['schema'] ?? '') === 'notx_v2')) {
            $text = trim((string)($decoded['subtitle'] ?? ''));
            if ($text === '') {
                $text = strip_tags((string)($decoded['content'] ?? ''));
            }
        } else {
            $text = strip_tags($body);
        }
        $text = preg_replace('/\s+/', ' ', trim((string)$text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') > $limit) {
                return mb_substr($text, 0, $limit, 'UTF-8') . '...';
            }
            return $text;
        }
        if (strlen($text) > $limit) {
            return substr($text, 0, $limit) . '...';
        }
        return $text;
    }
}

// Lấy ảnh hiển thị cho thông báo khuyến mãi từ JSON schema notx_v2
if (!function_exists('home_notice_image')) {
    function home_notice_image(string $body, string $baseUrl = ''): string
    {
        $decoded = json_decode(trim($body), true);
        if (!is_array($decoded) || (($decoded['schema'] ?? '') !== 'notx_v2')) {
            return '';
        }

        $template = strtolower(trim((string)($decoded['template'] ?? '')));
        $mainBanner = trim((string)($decoded['main_banner'] ?? ''));
        $thumbImage = trim((string)($decoded['thumb_image'] ?? ''));
        $banners = $decoded['banners'] ?? [];

        $bannerList = [];
        if (is_array($banners)) {
            foreach ($banners as $item) {
                $val = trim((string)$item);
                if ($val !== '') $bannerList[] = $val;
            }
        } elseif (is_string($banners)) {
            $parts = array_map('trim', explode(',', $banners));
            foreach ($parts as $val) {
                if ($val !== '') $bannerList[] = $val;
            }
        }

        $picked = '';
        if (in_array($template, ['tpl1', 'tpl4'], true)) {
            // Mẫu 1 & 4: banner chính
            if ($mainBanner !== '') {
                $picked = $mainBanner;
            } elseif (!empty($bannerList)) {
                $picked = $bannerList[0];
            }
        } elseif (in_array($template, ['tpl2', 'tpl3'], true)) {
            // Mẫu 2 & 3: lấy banner đầu tiên trong danh sách
            if (!empty($bannerList)) {
                $picked = $bannerList[0];
            }
        }
        if ($picked === '' && $thumbImage !== '') {
            $picked = $thumbImage;
        }
        if ($picked === '') return '';

        if (function_exists('to_abs_url')) {
            return to_abs_url($picked, $baseUrl);
        }
        if (preg_match('#^(https?:)?//#i', $picked)) return $picked;
        if ($baseUrl === '') return $picked;
        return $baseUrl . '/' . ltrim($picked, '/');
    }
}

// Chuẩn bị dữ liệu gợi ý sản phẩm theo từng danh mục (hover danh mục bên trái)
$catSuggestions = [];
foreach ($categories as $cat) {
    $catId = intval($cat['id'] ?? 0);
    $catName = trim((string)($cat['name'] ?? ''));
    if ($catId <= 0 || $catName === '') {
        continue;
    }

    // Ảnh thumb danh mục: ưu tiên thumb_image / image_url từ bảng ecommerce_category, fallback icon nếu rỗng
    $catThumbRel = '';
    if ($categoryThumbColumn !== '' && array_key_exists($categoryThumbColumn, $cat)) {
        $catThumbRel = trim((string)$cat[$categoryThumbColumn]);
    }
    $catThumbUrl = $catThumbRel !== ''
        ? (function_exists('to_abs_url') ? to_abs_url($catThumbRel, $baseUrl) : ($baseUrl . '/' . ltrim($catThumbRel, '/')))
        : '';

    $catSuggestions[$catId] = ['name' => $catName, 'icon_image_url' => $catThumbUrl, 'products' => []];
}

if ($productTable !== '') {
    $suggestSql = "
        SELECT p.id, p.product_name, p.image_url, p.category_id,
               {$variantMinExpr}
        FROM `{$productTable}` p
        WHERE 1=1{$productActiveWhere}
        ORDER BY p.id DESC";

    $suggestRes = $ithanhloc->query($suggestSql);
    if ($suggestRes) {
        while ($prow = $suggestRes->fetch_assoc()) {
            $catId = intval($prow['category_id'] ?? 0);
            if (!isset($catSuggestions[$catId])) {
                continue;
            }
            if (count($catSuggestions[$catId]['products']) >= 10) {
                continue;
            }
            $productName = trim((string)($prow['product_name'] ?? ''));
            if ($productName === '') {
                continue;
            }

            $productThumbUrl = to_abs_url((string)($prow['image_url'] ?? ''), $baseUrl);

            $catSuggestions[$catId]['products'][] = [
                'id' => intval($prow['id'] ?? 0),
                'name' => $productName,
                'thumb_url' => $productThumbUrl,
                'price_text' => (($priceVal = normalize_price($prow['price_min'] ?? 0)) > 0 ? format_vnd($priceVal) : 'Liên hệ')
            ];
        }
    }
}

// Sắp xếp danh mục theo thứ tự đã thiết lập trong phần quản trị (sort_order ASC, id ASC)
usort($categories, function (array $a, array $b): int {
    $aSort = intval($a['sort_order'] ?? 0);
    $bSort = intval($b['sort_order'] ?? 0);
    if ($aSort !== $bSort) {
        return $aSort <=> $bSort;
    }
    return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
});

// Xác định trang home để lấy banner carousel và danh sách đối tác
$homePageKey = isset($homePageKey) && is_string($homePageKey) && trim($homePageKey) !== ''
    ? trim($homePageKey)
    : 'home_user';
$homeCarouselItems = get_home_carousel_banners($ithanhloc, $homePageKey, $baseUrl);
$homePartners = get_home_partner_store($ithanhloc, $baseUrl);
$partnerNameMap = [];
foreach ($homePartners as $partnerItem) {
    $partnerName = trim((string)($partnerItem['partner_name'] ?? ''));
    if ($partnerName === '') continue;
    $partnerKey = normalize_search_text($partnerName);
    if ($partnerKey === '') continue;
    if (!isset($partnerNameMap[$partnerKey])) {
        $partnerNameMap[$partnerKey] = $partnerName;
    }
}

// Định nghĩa các "segment" (khối sản phẩm) theo từng danh mục
$segmentDefinitions = [];
foreach ($categories as $cat) {
    $catId = intval($cat['id'] ?? 0);
    $catName = trim((string)($cat['name'] ?? ''));
    if ($catId > 0 && $catName !== '') {
        $segmentDefinitions['cat_' . $catId] = ['title' => $catName, 'category_id' => $catId];
    }
}

// Dữ liệu sản phẩm của từng segment (mỗi segment là 1 danh mục)
$segmentProducts = [];
foreach ($segmentDefinitions as $segmentKey => $segmentDef) {
    $segmentProducts[$segmentKey] = [
        'title' => $segmentDef['title'],
        'products' => [],
        'brand_set' => [],
    ];
}

// Câu SQL nguồn cho danh sách sản phẩm: join danh mục, rating, giá tối thiểu, trạng thái...
$productActiveExpr = $productActiveExprSql !== ''
    ? "CASE WHEN {$productActiveExprSql} THEN 1 ELSE 0 END"
    : '1';
$segmentSourceSql = "SELECT p.id, p.product_name, p.image_url, p.sold_count,
    p.category_id,
    p.manufacturer,
    {$categoryNameExpr}
    {$ratingSelect}
    {$variantMinExpr},
    {$productActiveExpr} AS is_active
    FROM `{$productTable}` p
    {$categoryJoin}
    WHERE 1=1{$productActiveWhere}{$categoryActiveWhereForProduct}
    ORDER BY p.sold_count DESC, p.id DESC
    LIMIT 400";

// Thực thi query nguồn và build 2 mảng: segmentProducts (theo danh mục) và allProducts (tất cả sản phẩm)
$segmentSourceRes = $productTable !== '' ? $ithanhloc->query($segmentSourceSql) : false;
$allProducts = [];

// Lấy thông tin khuyến mãi combo / quà tặng để hiển thị nhãn trên thẻ sản phẩm (đồng bộ với shopping grid)
$promoGiftSubtitle = '';
$promoComboMap = [];
// Danh sách thẻ khuyến mãi theo sản phẩm để hiển thị ở section "Ưu đãi & Khuyến mãi"
// (mua X tặng Y, combo, quà tặng...). Mỗi phần tử có: title, type_label, benefit,
// product_ids[] (sản phẩm áp dụng) — sẽ resolve ảnh/tên/giá ở bước sau.
$productPromoRaw = [];
try {
    $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
} catch (Throwable $e) {
    $hasPromoTbl = false;
}
if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
    $nowPromo = date('Y-m-d H:i:s');
    $stmtPromo = $ithanhloc->prepare("SELECT id, name, promo_type, config_json, is_active, start_at, end_at, priority FROM ecommerce_product_promo WHERE is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?) ORDER BY CASE WHEN priority = 0 THEN 99999 ELSE priority END ASC, id DESC");
    if ($stmtPromo) {
        $stmtPromo->bind_param('ss', $nowPromo, $nowPromo);
        $stmtPromo->execute();
        $resPromo = $stmtPromo->get_result();
        while ($rowPromo = $resPromo->fetch_assoc()) {
            $typePromo = (string)($rowPromo['promo_type'] ?? '');
            $cfg = json_decode((string)($rowPromo['config_json'] ?? ''), true);
            if (!is_array($cfg)) $cfg = [];

            // Gom các product_id "chính" (sản phẩm cần mua) để hiển thị thẻ
            $mainIdsSet = [];
            if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                foreach ($cfg['main_product_ids'] as $mid) {
                    $mid = (int)$mid;
                    if ($mid > 0) $mainIdsSet[$mid] = $mid;
                }
            }
            $legacyMain = (int)($cfg['main_product_id'] ?? 0);
            if ($legacyMain > 0 && !$mainIdsSet) {
                $mainIdsSet[$legacyMain] = $legacyMain;
            }

            if ($typePromo === 'gift') {
                $threshold = (int)($cfg['threshold_amount'] ?? 0);
                if ($promoGiftSubtitle === '') {
                    $promoGiftSubtitle = $threshold > 0
                        ? 'Quà tặng hóa đơn trên ' . format_vnd($threshold)
                        : 'Quà tặng cho hóa đơn đủ điều kiện';
                }
                $benefit = $threshold > 0 ? ('Quà tặng cho đơn từ ' . format_vnd($threshold)) : 'Quà tặng kèm đơn hàng';
                $productPromoRaw[] = [
                    'title' => trim((string)($rowPromo['name'] ?? '')) ?: 'Quà tặng hấp dẫn',
                    'type_label' => 'Quà tặng',
                    'type_key' => 'gift',
                    'benefit' => $benefit,
                    'product_ids' => array_values($mainIdsSet),
                ];
            } elseif ($typePromo === 'combo') {
                foreach ($mainIdsSet as $mainId) {
                    if ($mainId > 0 && !isset($promoComboMap[$mainId])) {
                        $promoComboMap[$mainId] = 'Mua thêm deal sốc';
                    }
                }
                $productPromoRaw[] = [
                    'title' => trim((string)($rowPromo['name'] ?? '')) ?: 'Combo tiết kiệm',
                    'type_label' => 'Combo',
                    'type_key' => 'combo',
                    'benefit' => 'Mua combo giá tốt',
                    'product_ids' => array_values($mainIdsSet),
                ];
            } elseif ($typePromo === 'bxgy') {
                $buyQty = (int)($cfg['buy_qty'] ?? 0);
                $giftQty = (int)($cfg['gift_qty'] ?? 0);
                $benefit = ($buyQty > 0 && $giftQty > 0)
                    ? ('Mua ' . $buyQty . ' tặng ' . $giftQty)
                    : 'Mua kèm nhận quà';
                $productPromoRaw[] = [
                    'title' => trim((string)($rowPromo['name'] ?? '')) ?: $benefit,
                    'type_label' => 'Ưu đãi',
                    'type_key' => 'bxgy',
                    'benefit' => $benefit,
                    'product_ids' => array_values($mainIdsSet),
                ];
            }
        }
        $stmtPromo->close();
    }
}

// Resolve ảnh/tên/giá cho sản phẩm đại diện của từng thẻ khuyến mãi sản phẩm
$productPromoCards = [];
if (!empty($productPromoRaw) && $productTable !== '') {
    $allPromoPids = [];
    foreach ($productPromoRaw as $pr) {
        foreach ($pr['product_ids'] as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) $allPromoPids[$pid] = $pid;
        }
    }
    $promoProductInfo = [];
    if (!empty($allPromoPids)) {
        $ph = implode(',', array_fill(0, count($allPromoPids), '?'));
        $types = str_repeat('i', count($allPromoPids));
        $sqlPP = "SELECT p.id, p.product_name, p.image_url, {$variantMinExpr}
                  FROM `{$productTable}` p WHERE p.id IN ({$ph})";
        $stmtPP = $ithanhloc->prepare($sqlPP);
        if ($stmtPP) {
            $idsArr = array_values($allPromoPids);
            $stmtPP->bind_param($types, ...$idsArr);
            $stmtPP->execute();
            $resPP = $stmtPP->get_result();
            while ($rPP = $resPP->fetch_assoc()) {
                $pid = (int)($rPP['id'] ?? 0);
                if ($pid <= 0) continue;
                $priceVal = normalize_price($rPP['price_min'] ?? 0);
                $thumb = trim((string)($rPP['image_url'] ?? ''));
                $pname = trim((string)($rPP['product_name'] ?? ''));

                // Áp khuyến mãi (voucher giảm giá) để có giá ưu đãi + giá gốc gạch chéo
                $ppDemo = best_coupon_demo_for_product($orderCoupons, $pid, $priceVal);
                $ppHasDemo = $priceVal > 0 && (float)($ppDemo['discount'] ?? 0) > 0;
                $ppNewPrice = max(0, $priceVal - (float)($ppDemo['discount'] ?? 0));
                if ($ppHasDemo) {
                    // price_text = giá ưu đãi (đỏ), old_price_text = giá niêm yết (gạch chéo)
                    $ppPriceText = format_vnd($ppNewPrice);
                    $ppOldPriceText = format_vnd($priceVal);
                } else {
                    $ppPriceText = $priceVal > 0 ? format_vnd($priceVal) : 'Liên hệ';
                    $ppOldPriceText = '';
                }

                $promoProductInfo[$pid] = [
                    'id' => $pid,
                    'name' => $pname,
                    'thumb_url' => $thumb !== '' ? to_abs_url($thumb, $baseUrl) : $fallbackImage,
                    'price_text' => $ppPriceText,
                    'old_price_text' => $ppOldPriceText,
                    'link' => function_exists('pm_product_url') ? pm_product_url($pid, $pname, $baseUrl) : ($baseUrl . '/view-product?id=' . $pid),
                ];
            }
            $stmtPP->close();
        }
    }

    foreach ($productPromoRaw as $pr) {
        $rep = null;
        $count = 0;
        foreach ($pr['product_ids'] as $pid) {
            $pid = (int)$pid;
            if (isset($promoProductInfo[$pid])) {
                $count++;
                if ($rep === null) $rep = $promoProductInfo[$pid];
            }
        }
        // Bỏ qua thẻ không gắn sản phẩm nào (ví dụ gift theo ngưỡng hóa đơn toàn shop)
        if ($rep === null) continue;

        $productPromoCards[] = [
            'title' => $pr['title'],
            'type_label' => $pr['type_label'],
            'type_key' => $pr['type_key'],
            'benefit' => $pr['benefit'],
            'product' => $rep,
            'more_count' => max(0, $count - 1),
            'link' => $rep['link'],
        ];
    }
}

// Lấy danh sách video review short để hiển thị trên trang chủ
$reviewShortItems = [];
if ($reviewShortTable !== '' && $productTable !== '') {
    $rsCols = list_table_columns($ithanhloc, $reviewShortTable);
    $rsActiveExpr = in_array('is_active', $rsCols, true) ? "rs.is_active = 1" : "1=1";
    $rsSortExpr = in_array('sort_order', $rsCols, true) ? "rs.sort_order ASC, rs.id DESC" : "rs.id DESC";

    $shortSql = "SELECT rs.*, p.product_name, p.image_url, {$variantMinExpr}
                 FROM `{$reviewShortTable}` rs
                 LEFT JOIN `{$productTable}` p ON p.id = rs.product_id
                 WHERE {$rsActiveExpr}{$productActiveWhere}{$categoryActiveWhereForProduct}
                 ORDER BY {$rsSortExpr}
                 LIMIT 10";
    $shortRes = $ithanhloc->query($shortSql);
    if ($shortRes) {
        while ($row = $shortRes->fetch_assoc()) {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0) continue;

            $priceVal = normalize_price($row['price_min'] ?? 0);

            // Áp khuyến mãi (voucher giảm giá) giống các block khác để có giá ưu đãi + giá gốc gạch chéo
            $rsDemo = best_coupon_demo_for_product($orderCoupons, $pid, $priceVal);
            $rsHasDemo = $priceVal > 0 && (float)($rsDemo['discount'] ?? 0) > 0;
            $rsNewPrice = max(0, $priceVal - (float)($rsDemo['discount'] ?? 0));

            if ($rsHasDemo) {
                // price_text = giá ưu đãi (đỏ), old_price_text = giá gốc (gạch chéo)
                $priceText = format_vnd($rsNewPrice);
                $oldPriceText = format_vnd($priceVal);
            } else {
                $priceText = $priceVal > 0 ? format_vnd($priceVal) : 'Liên hệ';
                $oldPriceText = '';
            }

            $thumb = trim((string)($row['image_url'] ?? ''));
            $thumbUrl = $thumb !== '' ? to_abs_url($thumb, $baseUrl) : $fallbackImage;

            $reviewShortItems[] = [
                'id' => (int)($row['id'] ?? 0),
                'product_id' => $pid,
                'product_name' => (string)($row['product_name'] ?? 'Sản phẩm'),
                'title' => (string)($row['title'] ?? ''),
                'creator_name' => (string)($row['creator_name'] ?? ''),
                'youtube_url' => (string)($row['youtube_url'] ?? ''),
                'video_url' => (string)($row['video_url'] ?? ''),
                'thumb_url' => $thumbUrl,
                'price_text' => $priceText,
                'old_price_text' => $oldPriceText,
            ];
        }
    }
}

// Build dữ liệu cho từng segment và danh sách tất cả sản phẩm
if ($segmentSourceRes instanceof mysqli_result) {
    while ($row = $segmentSourceRes->fetch_assoc()) {
        $pid = intval($row['id'] ?? 0);
        if ($pid <= 0) continue;

        $nameRaw = trim((string)($row['product_name'] ?? ''));
        if ($nameRaw === '') continue;

        $categoryId = intval($row['category_id'] ?? 0);
        $segmentKey = 'cat_' . $categoryId;

        if (!isset($segmentProducts[$segmentKey])) {
            continue;
        }

        $priceValue = normalize_price($row['price_min'] ?? 0);

        // Demo voucher giảm giá đơn hàng
        $demo = best_coupon_demo_for_product($orderCoupons, $pid, $priceValue);
        $hasDemo = $priceValue > 0 && (float)($demo['discount'] ?? 0) > 0;
        $newPriceValue = max(0, $priceValue - (float)($demo['discount'] ?? 0));
        $discountPercent = 0;
        if ($hasDemo) {
            $discountPercent = max(1, (int)round(((float)($demo['discount'] ?? 0) / max($priceValue, 1)) * 100));
        }

        // Nhãn ưu đãi (badge) theo mệnh giá voucher
        $voucherBadge = '';
        if ($hasDemo) {
            $demoType = strtolower((string)($demo['type'] ?? ''));
            $demoValue = (float)($demo['value'] ?? 0);
            $valueLabel = format_voucher_value_label($demoType === '' ? 'fixed' : $demoType, $demoValue);

            if ($discountPercent >= 100) {
                $voucherBadge = 'Miễn phí đơn hàng';
            } elseif (strtolower($valueLabel) === 'miễn phí') {
                $voucherBadge = 'Miễn phí đơn hàng';
            } elseif ($valueLabel !== '') {
                $voucherBadge = 'Giảm ' . $valueLabel;
            }
        }

        // Demo voucher vận chuyển
        $hasShipDemo = false;
        $shipLabel = '';
        if (!empty($shipCoupons) && $priceValue > 0) {
            $bestPercent = 0.0;
            $bestFixed = 0.0;
            foreach ($shipCoupons as $coupon) {
                if (!coupon_applies_to_product($coupon, $pid)) continue;
                $minSubtotal = (float)($coupon['min_subtotal'] ?? 0);
                if ($priceValue < $minSubtotal) continue;
                $hasShipDemo = true;
                // Loại giảm lưu ở cột value_unit (percent/fixed); fallback 'type' nếu có
                $type = strtolower(trim((string)($coupon['value_unit'] ?? $coupon['type'] ?? '')));
                $value = (float)($coupon['value'] ?? 0);
                if ($type === 'percent') {
                    if ($value >= 100) {
                        $bestPercent = 100;
                        break;
                    }
                    $bestPercent = max($bestPercent, $value);
                } else {
                    $bestFixed = max($bestFixed, $value);
                }
            }
            if ($hasShipDemo) {
                if ($bestPercent >= 100) {
                    $shipLabel = home_ship_badge_label(100, 'percent');
                } elseif ($bestFixed > 0) {
                    $shipLabel = home_ship_badge_label($bestFixed, 'fixed');
                } elseif ($bestPercent > 0) {
                    $shipLabel = home_ship_badge_label($bestPercent, 'percent');
                }
            }
        }

        $sold = intval($row['sold_count'] ?? 0);
        $ratingAvg = isset($row['rating_avg']) ? (float)$row['rating_avg'] : 0.0;
        $ratingCount = intval($row['rating_count'] ?? 0);
        $ratingValue = $ratingCount > 0 ? clamp_rating_0_5($ratingAvg) : 0.0;
        $ratingText = number_format($ratingValue, 1);

        $thumb = trim((string)($row['image_url'] ?? ''));
        $thumbUrl = $thumb !== '' ? to_abs_url($thumb, $baseUrl) : $fallbackImage;

        $promoHighlights = [];
        if (!empty($promoComboMap[$pid])) {
            $promoHighlights[] = (string)$promoComboMap[$pid];
        }
        if ($promoGiftSubtitle !== '') {
            $promoHighlights[] = $promoGiftSubtitle;
        }
        if (count($promoHighlights) > 2) {
            $promoHighlights = array_slice($promoHighlights, 0, 2);
        }

        $segmentProducts[$segmentKey]['products'][] = [
            'id' => $pid,
            'name' => $nameRaw,
            'thumb_url' => $thumbUrl,
            'price_value' => $priceValue,
            'price_text' => $priceValue > 0 ? format_vnd($priceValue) : 'Liên hệ',
            'new_price_text' => $hasDemo ? format_vnd($newPriceValue) : '',
            'old_price_text' => $hasDemo ? format_vnd($priceValue) : '',
            'discount_percent' => $discountPercent,
            'voucher_badge' => $voucherBadge,
            'sold' => $sold,
            'rating_value' => $ratingValue,
            'rating_text' => $ratingText,
            'rating_count' => $ratingCount,
            'category_text' => trim((string)($row['category_name'] ?? '')) ?: 'Sơn tường',
            'has_demo' => $hasDemo,
            'promo_highlights' => $promoHighlights,
        ];

        $promoSubtitle = '';
        if (!empty($promoComboMap[$pid])) {
            $promoSubtitle = (string)$promoComboMap[$pid];
        } elseif ($promoGiftSubtitle !== '') {
            $promoSubtitle = $promoGiftSubtitle;
        }

        $allProducts[] = [
            'id' => $pid,
            'name' => $nameRaw,
            'thumb_url' => $thumbUrl,
            'price_value' => $priceValue,
            'price_text' => $priceValue > 0 ? format_vnd($priceValue) : 'Liên hệ',
            'new_price_text' => $hasDemo ? format_vnd($newPriceValue) : '',
            'old_price_text' => $hasDemo ? format_vnd($priceValue) : '',
            'discount_percent' => $discountPercent,
            'voucher_badge' => $voucherBadge,
            'has_ship_demo' => $hasShipDemo,
            'ship_label' => $shipLabel,
            'sold' => $sold,
            'rating_value' => $ratingValue,
            'rating_text' => $ratingText,
            'rating_count' => $ratingCount,
            'is_active' => !empty($row['is_active']) && (string)$row['is_active'] !== '0',
            'rating_html' => render_star_rating_html($ratingValue, $ratingCount),
            'promo_subtitle' => $promoSubtitle,
            'promo_highlights' => $promoHighlights,
        ];

        $brand = map_partner_brand_name((string)($row['manufacturer'] ?? ''), $partnerNameMap);
        if ($brand !== '') {
            $segmentProducts[$segmentKey]['brand_set'][$brand] = true;
        }
    }
}

// Sắp xếp sản phẩm trong từng segment: ưu tiên có khuyến mãi, nhiều đã bán, mới hơn
foreach ($segmentProducts as $segmentKey => &$segmentData) {
    if (empty($segmentData['products'])) {
        unset($segmentProducts[$segmentKey]);
        continue;
    }
    usort($segmentData['products'], static function (array $a, array $b): int {
        if (($a['has_demo'] ?? false) !== ($b['has_demo'] ?? false)) {
            return ($a['has_demo'] ?? false) ? -1 : 1;
        }
        $soldCmp = (int)($b['sold'] ?? 0) <=> (int)($a['sold'] ?? 0);
        if ($soldCmp !== 0) return $soldCmp;
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    });

    $segmentData['products'] = array_slice($segmentData['products'], 0, 5);
    $segmentData['brands'] = array_slice(array_keys($segmentData['brand_set']), 0, 5);
}
unset($segmentData);

// Sắp xếp toàn bộ allProducts để dùng cho block "Tất cả sản phẩm"
usort($allProducts, static function (array $a, array $b): int {
    $prioA = [
        !empty($a['price_value']) && (float)$a['price_value'] > 0,
        !empty($a['discount_percent']) && (int)$a['discount_percent'] > 0,
        !empty($a['is_active'])
    ];
    $prioB = [
        !empty($b['price_value']) && (float)$b['price_value'] > 0,
        !empty($b['discount_percent']) && (int)$b['discount_percent'] > 0,
        !empty($b['is_active'])
    ];
    for ($i = 0; $i < 3; $i += 1) {
        if ($prioA[$i] !== $prioB[$i]) return $prioA[$i] ? -1 : 1;
    }
    $soldCmp = (int)($b['sold'] ?? 0) <=> (int)($a['sold'] ?? 0);
    if ($soldCmp !== 0) return $soldCmp;
    return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
});

// Lấy danh sách bài viết blog mới nhất để hiển thị trên trang chủ
$homeBlogPosts = [];
try {
    $stmtBlog = $ithanhloc->prepare('SELECT b.id, b.title, b.slug, b.excerpt, b.thumbnail_url, b.published_at, c.name AS category_name
        FROM ecommerce_blog b
        LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id
        WHERE b.is_active = 1
        ORDER BY b.published_at DESC, b.id DESC
        LIMIT 10');
    if ($stmtBlog) {
        $stmtBlog->execute();
        $resBlog = $stmtBlog->get_result();
        while ($row = $resBlog->fetch_assoc()) {
            $homeBlogPosts[] = $row;
        }
        $stmtBlog->close();
    }
} catch (Throwable $e) {
    $homeBlogPosts = [];
}

// Lấy danh sách chi nhánh
$footerBranches = [];
if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
    $tableExists = $ithanhloc->query("SHOW TABLES LIKE 'site_store'");
    if ($tableExists instanceof mysqli_result && $tableExists->num_rows > 0) {
        $res = $ithanhloc->query("SELECT id, branch_name, region, hotline, address_detail, map_url, avatar_image, gallery_images_json, opening_hours_json FROM site_store WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $branchName = trim((string)($row['branch_name'] ?? ''));
                $region = trim((string)($row['region'] ?? ''));
                $hotlineRaw = trim((string)($row['hotline'] ?? ''));
                $address = trim((string)($row['address_detail'] ?? ''));
                $mapUrl = trim((string)($row['map_url'] ?? ''));

                if ($mapUrl !== '' && !preg_match('#^https?://#i', $mapUrl)) {
                    $mapUrl = 'https://' . ltrim($mapUrl, '/');
                }
                $hotlineTel = preg_replace('/[^0-9+]/', '', $hotlineRaw);

                // Normalize avatar image URL
                $avatarRaw = trim((string)($row['avatar_image'] ?? ''));
                $avatar = '';
                if ($avatarRaw !== '') {
                    $avatar = (function_exists('to_abs_url') ? to_abs_url($avatarRaw, $baseUrl) : (preg_match('#^https?://#i', $avatarRaw) ? $avatarRaw : rtrim($baseUrl, '/') . '/' . ltrim($avatarRaw, '/')));
                }

                // Normalize gallery images
                $galleryRaw = trim((string)($row['gallery_images_json'] ?? ''));
                $gallery = [];
                if ($galleryRaw !== '') {
                    $decoded = json_decode($galleryRaw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $g) {
                            $g = trim((string)$g);
                            if ($g === '') continue;
                            $gallery[] = (function_exists('to_abs_url') ? to_abs_url($g, $baseUrl) : (preg_match('#^https?://#i', $g) ? $g : rtrim($baseUrl, '/') . '/' . ltrim($g, '/')));
                        }
                    }
                }

                // Opening hours
                $hoursJson = trim((string)($row['opening_hours_json'] ?? ''));
                $hoursToday = '';
                if ($hoursJson !== '') {
                    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    $dayIdx = (int)date('w'); // 0=Sun
                    $dayKey = $days[$dayIdx === 0 ? 6 : $dayIdx - 1];
                    $hoursData = json_decode($hoursJson, true);
                    if (is_array($hoursData) && isset($hoursData[$dayKey])) {
                        $d = $hoursData[$dayKey];
                        $hoursToday = !empty($d['enabled']) ? (($d['open'] ?? '') . ' - ' . ($d['close'] ?? '')) : 'Đóng cửa';
                    }
                }

                $footerBranches[] = [
                    'id'          => (int)($row['id'] ?? 0),
                    'branch_name' => $branchName,
                    'region'      => $region,
                    'region_key'  => function_exists('footer_region_key') ? footer_region_key($region) : 'south',
                    'hotline_raw' => $hotlineRaw,
                    'hotline_tel' => $hotlineTel,
                    'address'     => $address,
                    'map_url'     => $mapUrl,
                    'avatar'      => $avatar,
                    'gallery'     => $gallery,
                    'hours_today' => $hoursToday,
                ];
            }
        }
    }
}

// Group branches by region
$branchesByRegion = [
    'north' => ['label' => 'Miền Bắc', 'items' => []],
    'central' => ['label' => 'Miền Trung', 'items' => []],
    'south' => ['label' => 'Miền Nam', 'items' => []],
];
foreach ($footerBranches as $b) {
    $key = $b['region_key'];
    if (isset($branchesByRegion[$key])) {
        $branchesByRegion[$key]['items'][] = $b;
    } else {
        $branchesByRegion['south']['items'][] = $b;
    }
}
?>
<h1 class="visually-hidden">DIY Paint & More - Hệ thống siêu thị sơn Mỹ cao cấp chính hãng</h1>
<!-- HERO SECTION: full-viewport carousel (placeholder slides) -->
<section class="d-none home-hero-section">
    <div id="homeHeroCarousel" class="carousel slide carousel-fade home-hero-carousel" data-bs-ride="carousel" data-bs-interval="5000" data-bs-pause="false">
        <div class="carousel-indicators home-hero-indicators">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <!-- <button type="button" data-bs-target="#homeHeroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $i + 1 ?>"></button> -->
            <?php endfor; ?>
        </div>
        <div class="carousel-inner">
            <?php
            $heroSlideVideos = [
                0 => '/image/bg-rust-oleum.mp4',
                1 => '/image/bg-rust-oleum2.mp4',
                2 => '/image/bg-rust-oleum3.mp4',
            ];
            $heroSlidePosters = [
                0 => '/image/bg-rust-oleum.jpg',
                1 => '/image/bg-rust-oleum.jpg',
                2 => '/image/bg-rust-oleum.jpg',
            ];
            ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
                    <div class="home-hero-slide" data-slide-index="<?= $i ?>">
                        <?php if (isset($heroSlideVideos[$i])): ?>
                            <video class="home-hero-video" autoplay muted loop playsinline preload="auto" poster="<?= h($baseUrl . ($heroSlidePosters[$i] ?? '')) ?>">
                                <source src="<?= h($baseUrl . $heroSlideVideos[$i]) ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>
                        <div class="home-hero-overlay"></div>
                        <div class="home-hero-content">
                            <div class="home-hero-eyebrow">PAINT &amp; MORE</div>
                            <h1 class="home-hero-title">
                                <span class="title-primary">SƠN TỰ LÀM</span>
                                <span class="title-secondary">CẢI TẠO VÀ SÁNG TẠO</span>
                            </h1>
                            <p class="home-hero-desc">Nơi cung cấp giải pháp sơn chính hãng từ Mỹ.</p>
                            <div class="home-hero-cta">
                                <a href="#homeContent" class="btn btn-primary btn-sm home-hero-btn">Khám phá ngay</a>
                                <a href="<?= h($baseUrl) ?>/shopping" class="btn btn-outline-light btn-sm home-hero-btn">Xem sản phẩm</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <button class="carousel-control-prev d-none" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next d-none" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
        <!-- <a href="#homeContent" class="home-hero-scroll" aria-label="Cuộn xuống">
            <i class="bi bi-chevron-down"></i>
        </a> -->
    </div>
    <!-- Ticker -->
    <div class="fb-header-ticker">
        <div class="fb-header-ticker-inner">
            <div class="fb-header-ticker-track">
                <div class="fb-header-ticker-item"><span>Rust-Oleum - Thương hiệu sơn xịt hàng đầu Hoa Kỳ</span></div>
                <div class="fb-header-ticker-item"><span>SƠN NHẬP MỸ - SỐ 1 HOA KỲ</span></div>
                <div class="fb-header-ticker-item"><span>SẢN PHẨM CHÍNH HÃNG</span></div>
                <div class="fb-header-ticker-item"><span>TỰ SẢN XUẤT</span></div>
                <div class="fb-header-ticker-item"><span>CUNG CẤP GIẢI PHÁP SƠN</span></div>
                <div class="fb-header-ticker-item"><span>PHÂN PHỐI TOÀN QUỐC</span></div>
                <div class="fb-header-ticker-item"><span>BẢO HÀNH LÊN ĐẾN 10 NĂM</span></div>

            </div>
        </div>
    </div>
    <!-- END Ticker -->
</section>

<!-- Tại sao nên chọn Sơn Xịt? (Nền tảng siêu thị sơn mỹ chất lượng cao) -->
<section id="why-son-xit" class="d-none py-5 pt-0 position-relative overflow-hidden">
    <div class="why-son-xit-overlay"></div>
    <div class="container position-relative" style="z-index:2;">
        <div class="why-son-xit-content">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-12 col-lg-6">
                    <div class="why-preview-wrapper">
                        <div class="why-desktop-preview">
                            <img src="<?= to_abs_url('uploads/media_1778678217_b6244814.png', $baseUrl) ?>" alt="pc" class="img-fluid">
                        </div>
                        <div class="why-mobile-preview">
                            <img src="<?= h($baseUrl) ?>/page_home/img/phone.png" alt="mobile" class="img-fluid">
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <h2 class="fw-bold display-5 mb-3" style="color:#2d2a22;">Nền tảng siêu thị <span style="color:var(--theme-primary);">sơn mỹ chất lượng cao</span></h2>
                    <p class="mb-3 fs-5 text-dark">
                        Giải pháp đột phá cho mọi bề mặt công trình. Mang lại sự chuyên nghiệp, nhanh chóng và bền bỉ tuyệt đối cho ngôi nhà Việt.
                    </p>
                    <ul class="why-son-xit-points mb-3">
                        <!--li>Thi công nhanh, khô nhanh, tiết kiệm thời gian.</li>
                        <li>Bề mặt mịn đều, hạn chế vệt sơn.</li>
                        <li>Dễ phủ góc hẹp và chi tiết khó xử lý.</li-->
                    </ul>
                    <div class="why-son-xit-table-wrap">
                        <table class="why-son-xit-table">
                            <thead>
                                <tr>
                                    <th>Lợi ích</th>
                                    <th>Sơn Mỹ</th>
                                    <th>Sơn truyền thống</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Thời gian khô</td>
                                    <td>Khô nhanh, giúp tiết kiệm thời gian chờ đợi.</td>
                                    <td>Thời gian khô chậm hơn.</td>
                                </tr>
                                <tr>
                                    <td>Độ mịn bề mặt</td>
                                    <td>Bề mặt sau khi sơn mịn và đều màu.</td>
                                    <td>Dễ để lại vệt cọ, bề mặt không đồng nhất.</td>
                                </tr>
                                <tr>
                                    <td>Góc hẹp/chi tiết</td>
                                    <td>Dễ dàng che phủ các góc khuất và chi tiết nhỏ.</td>
                                    <td>Khó xử lý ở những vị trí phức tạp hoặc phạm vi hẹp.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="<?= h($baseUrl) ?>/shopping" class="btn btn-primary rounded-pill px-4 py-2 fw-bold text-uppercase shadow-sm" style="background-color: #16613a; border-color: #16613a;">
                            <i class="bi bi-cart3 me-2"></i> MUA SẮM
                        </a>
                        <a href="tel:0909143900" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-bold text-uppercase shadow-sm">
                            <i class="bi bi-telephone-outbound me-2"></i> LIÊN HỆ TƯ VẤN
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- /./ -->
<div id="homeContent" class="_dash-shell _home-content">
    <div class="___mb-3">
        <!-- ===== HERO SECTION ) ===== -->
        <?php
        // Lấy banner đầu tiên trong carousel (nếu có) để dùng làm ảnh hero, fallback ảnh mặc định
        $pmHeroBanner = $homeCarouselItems[0] ?? [];
        $pmHeroImgRaw = trim((string)($pmHeroBanner['image_url'] ?? ''));
        $pmHeroImg = $pmHeroImgRaw !== ''
            ? (function_exists('to_abs_url') ? to_abs_url($pmHeroImgRaw, $baseUrl) : $pmHeroImgRaw)
            : $fallbackImage;
        $pmHeroTitle = trim((string)($pmHeroBanner['title'] ?? '')) ?: 'Sơn nhập Mỹ chính hãng';
        $pmHeroDesc = trim((string)($pmHeroBanner['content'] ?? '')) ?: 'Bền màu vượt trội. Phủ đều, khô nhanh.';
        $pmHeroLink = trim((string)($pmHeroBanner['link_url'] ?? '')) ?: ($baseUrl . '/shopping');
        // Chỉ hiển thị tối đa 10 danh mục ở sidebar hero để gọn gàng
        $pmHeroCats = array_slice($categories, 0, 10);

        // Đếm số sản phẩm đang có ưu đãi (giảm giá) để hiện badge động trên tab Flash Sale
        $pmFlashCount = 0;
        if (!empty($allProducts) && is_array($allProducts)) {
            foreach ($allProducts as $pmP) {
                if ((int)($pmP['discount_percent'] ?? 0) > 0) {
                    $pmFlashCount++;
                }
            }
        }

        // Xác định tab nav đang active dựa theo URL hiện tại (so khớp đường dẫn cuối)
        $pmCurrentPath = strtolower(trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/'));
        $pmIsActive = function (string $slug) use ($pmCurrentPath): string {
            $slug = trim($slug, '/');
            if ($slug === '') {
                // Tab "Trang chủ": active khi path rỗng hoặc là trang chủ
                $active = $pmCurrentPath === '' || in_array($pmCurrentPath, ['main', 'home', 'index.php'], true);
            } else {
                $active = $pmCurrentPath === $slug || str_ends_with($pmCurrentPath, '/' . $slug);
            }
            return $active ? ' active' : '';
        };

        // Danh mục dùng cho mega-menu "Sản phẩm" (tái dùng suggestMap qua catSuggestions)
        $pmMegaCats = array_slice($categories, 0, 12);
        ?>
        <section class="pm-hero" id="pmHeroSection" aria-label="Khu vực nổi bật">
            <div class="pm-hero-grid">
                <!-- Sidebar danh mục -->
                <aside class="pm-hero-cats" id="pmHeroCats">
                    <div class="pm-hero-cats-head"><i class="bi bi-grid-3x3-gap-fill"></i> DANH MỤC SẢN PHẨM</div>
                    <ul class="pm-hero-cats-list">
                        <?php foreach ($pmHeroCats as $cat): ?>
                            <?php
                            $catId = intval($cat['id'] ?? 0);
                            if ($catId <= 0) continue;
                            $catName = trim((string)($cat['name'] ?? 'Danh mục'));
                            $catSlug = trim((string)($cat['slug'] ?? ''));
                            $catUrl = $catSlug !== '' ? ($baseUrl . '/product-category/' . $catSlug) : ($baseUrl . '/shopping');
                            $catThumb = trim((string)($catSuggestions[$catId]['icon_image_url'] ?? ''));
                            $catHasProducts = !empty($catSuggestions[$catId]['products']);
                            ?>
                            <li>
                                <a href="<?= h($catUrl) ?>" class="pm-hero-cat-link<?= $catHasProducts ? ' has-suggest' : '' ?>" data-cat-id="<?= $catId ?>" data-slug="<?= h($catSlug) ?>">
                                    <span class="pm-hero-cat-ico">
                                        <?php if ($catThumb !== ''): ?>
                                            <img src="<?= h($catThumb) ?>" alt="<?= h($catName) ?>" loading="lazy" decoding="async" onerror="this.outerHTML='<i class=&quot;bi bi-tag&quot;></i>';">
                                        <?php else: ?>
                                            <i class="bi bi-tag"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="pm-hero-cat-name"><?= h($catName) ?></span>
                                    <i class="bi bi-chevron-right pm-hero-cat-chev"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>

                <!-- Banner chính -->
                <div class="pm-hero-main">
                    <nav class="pm-hero-nav" id="pmHeroNav">
                        <!-- <a href="<?= h($baseUrl) ?>" class="pm-hero-nav-link<?= $pmIsActive('') ?>">Trang chủ</a> -->
                        <div class="pm-hero-nav-item has-mega" id="pmHeroNavProducts">
                            <a href="<?= h($baseUrl) ?>/shopping" class="pm-hero-nav-link<?= $pmIsActive('shopping') ?>">Tất cả sản phẩm <i class="bi bi-chevron-down pm-hero-nav-caret"></i></a>
                            <!-- Mega-menu danh mục -->
                            <div class="pm-hero-mega" id="pmHeroMega">
                                <div class="pm-hero-mega-grid">
                                    <?php foreach ($pmMegaCats as $cat): ?>
                                        <?php
                                        $mCatId = intval($cat['id'] ?? 0);
                                        if ($mCatId <= 0) continue;
                                        $mCatName = trim((string)($cat['name'] ?? 'Danh mục'));
                                        $mCatSlug = trim((string)($cat['slug'] ?? ''));
                                        $mCatUrl = $mCatSlug !== '' ? ($baseUrl . '/shopping/' . $mCatSlug) : ($baseUrl . '/shopping?cat=' . $mCatId);
                                        $mCatThumb = trim((string)($catSuggestions[$mCatId]['icon_image_url'] ?? ''));
                                        ?>
                                        <a href="<?= h($mCatUrl) ?>" class="pm-hero-mega-cat" data-cat-id="<?= $mCatId ?>">
                                            <span class="pm-hero-mega-ico">
                                                <?php if ($mCatThumb !== ''): ?>
                                                    <img src="<?= h($mCatThumb) ?>" alt="<?= h($mCatName) ?>" loading="lazy" decoding="async" onerror="this.outerHTML='<i class=&quot;bi bi-tag&quot;></i>';">
                                                <?php else: ?>
                                                    <i class="bi bi-tag"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="pm-hero-mega-name"><?= h($mCatName) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <a href="<?= h($baseUrl) ?>/shopping" class="pm-hero-mega-all">Xem tất cả sản phẩm <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                        <!-- Ưu đãi: hover hiện panel khuyến mãi phủ lên banner (#pmHeroPromoPanel) -->
                        <a href="<?= h($baseUrl) ?>/notifications" class="pm-hero-nav-link<?= $pmIsActive('notifications') ?>" data-hero-panel="promo">Chương trình ưu đãi <span class="pm-hero-tag new">NEW</span></a>

                        <!-- Tin tức: hover hiện panel blog phủ lên banner (#pmHeroNewsPanel) -->
                        <a href="<?= h($baseUrl) ?>/blog" class="pm-hero-nav-link<?= $pmIsActive('blog') ?>" data-hero-panel="news">Tin tức mới nhất</a>
                        <!-- <a href="<?= h($baseUrl) ?>/flash-sale" class="pm-hero-nav-link<?= $pmIsActive('flash-sale') ?>">Flash Sale
                            <?php if ($pmFlashCount > 0): ?>
                                <span class="pm-hero-tag hot"><?= $pmFlashCount > 99 ? '99+' : $pmFlashCount ?></span>
                            <?php else: ?>
                                <span class="pm-hero-tag hot">HOT</span>
                            <?php endif; ?>
                        </a> -->
                        <!-- <a href="<?= h($baseUrl) ?>/lien-he" class="pm-hero-nav-link<?= $pmIsActive('lien-he') ?>">Liên hệ</a> -->
                    </nav>
                    <?php
                    // Slideshow banner (ảnh/video) đã thiết lập trong hệ thống; nếu trống → 1 slide mặc định
                    $pmSlides = !empty($homeCarouselItems) ? $homeCarouselItems : [[
                        'image_url' => $pmHeroImg,
                        'title' => $pmHeroTitle,
                        'content' => $pmHeroDesc,
                        'link_url' => $pmHeroLink,
                    ]];
                    $pmHasMany = count($pmSlides) > 1;
                    ?>
                    <div id="pmHeroCarousel" class="pm-hero-banner carousel slide carousel-fade" <?= $pmHasMany ? 'data-bs-ride="carousel" data-bs-interval="5000"' : '' ?>>
                        <div class="carousel-inner">
                            <?php foreach ($pmSlides as $sIdx => $bn): ?>
                                <?php
                                $sImgRaw = trim((string)($bn['image_url'] ?? ''));
                                $sImg = $sImgRaw !== '' ? h($sImgRaw) : h($fallbackImage);
                                $sFallback = h($fallbackImage);
                                $sTitle = trim((string)($bn['title'] ?? '')) ?: $pmHeroTitle;
                                $sContent = trim((string)($bn['content'] ?? '')) ?: $pmHeroDesc;
                                $sLink = trim((string)($bn['link_url'] ?? '')) ?: ($baseUrl . '/shopping');
                                $sExt = strtolower(pathinfo(parse_url($sImgRaw, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                                $sIsVideo = in_array($sExt, ['mp4', 'webm', 'ogg', 'mov'], true);
                                ?>
                                <div class="carousel-item<?= $sIdx === 0 ? ' active' : '' ?>">
                                    <div class="pm-hero-slide">
                                        <?php if ($sIsVideo): ?>
                                            <video class="pm-hero-media" src="<?= $sImg ?>" autoplay muted loop playsinline></video>
                                        <?php else: ?>
                                            <img class="pm-hero-media" src="<?= $sImg ?>" alt="<?= h($sTitle) ?>" loading="<?= $sIdx === 0 ? 'eager' : 'lazy' ?>" decoding="async" onerror="if(this.src!=='<?= $sFallback ?>'){this.onerror=null;this.src='<?= $sFallback ?>';}">
                                        <?php endif; ?>
                                        <div class="pm-hero-banner-inner">
                                            <!-- <div class="pm-hero-eyebrow">PAINT &amp; MORE</div>
                                            <h2 class="pm-hero-headline"><?= h($sTitle) ?></h2>
                                            <p class="pm-hero-sub"><?= h($sContent) ?></p>
                                            <div class="pm-hero-actions">
                                                <a href="<?= h($sLink) ?>" class="pm-hero-btn">MUA NGAY</a>
                                                <a href="<?= h($baseUrl) ?>/shopping" class="pm-hero-link">Xem tất cả sản phẩm <i class="bi bi-arrow-right"></i></a>
                                            </div> -->
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($pmHasMany): ?>
                            <button class="carousel-control-prev pm-hero-ctrl" type="button" data-bs-target="#pmHeroCarousel" data-bs-slide="prev">
                                <i class="bi bi-chevron-left"></i><span class="visually-hidden">Trước</span>
                            </button>
                            <button class="carousel-control-next pm-hero-ctrl" type="button" data-bs-target="#pmHeroCarousel" data-bs-slide="next">
                                <i class="bi bi-chevron-right"></i><span class="visually-hidden">Sau</span>
                            </button>
                            <div class="carousel-indicators pm-hero-dots">
                                <?php foreach ($pmSlides as $sIdx => $bn): ?>
                                    <button type="button" data-bs-target="#pmHeroCarousel" data-bs-slide-to="<?= $sIdx ?>" <?= $sIdx === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $sIdx + 1 ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <!-- Ảnh lon sơn nổi ở góc phải banner (trang trí) -->
                        <!-- <img class="pm-hero-product-float" src="<?= h($baseUrl) ?>/image/home_product_1.png?v=<?= time() ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" onerror="this.style.display='none';"> -->

                    </div>
                </div>

                <!-- Flyout gợi ý sản phẩm khi hover danh mục (phủ lên vùng banner) -->
                <div class="pm-hero-flyout" id="pmHeroFlyout">
                    <div class="pm-hero-flyout-title" id="pmHeroFlyoutTitle">Gợi ý theo danh mục</div>
                    <div class="pm-hero-flyout-grid" id="pmHeroFlyoutGrid"></div>
                </div>

                <!-- Panel KHUYẾN MÃI (hover "Chương trình ưu đãi") — phủ lên banner như flyout -->
                <div class="pm-hero-flyout pm-hero-flyout--list" id="pmHeroPromoPanel" data-hero-panel-body="promo">
                    <div class="pm-hero-flyout-head">
                        <span class="pm-hero-flyout-title"><i class="bi bi-gift"></i> Khuyến mãi mới nhất</span>
                        <a href="<?= h($baseUrl) ?>/notifications" class="pm-hero-flyout-all">Tất cả <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!empty($promoNotices)): ?>
                        <div class="pm-hero-flyout-list">
                            <?php foreach (array_slice($promoNotices, 0, 6) as $notice): ?>
                                <?php
                                $dpId = (int)($notice['id'] ?? 0);
                                $dpTitle = trim((string)($notice['title'] ?? 'Khuyến mãi')) ?: 'Khuyến mãi';
                                $dpBody = (string)($notice['body'] ?? '');
                                $dpTimeRaw = trim((string)($notice['created_at'] ?? ''));
                                $dpTimeText = $dpTimeRaw !== '' ? date('d/m/Y', strtotime($dpTimeRaw)) : 'Vừa xong';
                                $dpLinkRaw = trim((string)($notice['link'] ?? ''));
                                if ($dpLinkRaw !== '') {
                                    $dpLink = preg_match('#^(https?:)?//#i', $dpLinkRaw) ? $dpLinkRaw : ($baseUrl . '/' . ltrim($dpLinkRaw, '/'));
                                } else {
                                    $dpLink = nf_build_url($dpId, $dpTitle, $baseUrl);
                                }
                                $dpImg = home_notice_image($dpBody, $baseUrl);
                                $dpSub = home_notice_excerpt($dpBody, 70);
                                ?>
                                <a class="pm-hero-litem" href="<?= h($dpLink) ?>">
                                    <span class="pm-hero-litem-thumb">
                                        <?php if ($dpImg !== ''): ?>
                                            <img src="<?= h($dpImg) ?>" alt="<?= h($dpTitle) ?>" loading="lazy" decoding="async" onerror="this.outerHTML='<i class=&quot;bi bi-gift&quot;></i>';">
                                        <?php else: ?>
                                            <i class="bi bi-gift"></i>
                                        <?php endif; ?>
                                        <span class="pm-hero-litem-overlay">
                                            <span class="pm-hero-litem-title"><?= h($dpTitle) ?></span>
                                            <span class="pm-hero-litem-sub"><?= h($dpSub !== '' ? $dpSub : ('Cập nhật ' . $dpTimeText)) ?></span>
                                        </span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pm-hero-flyout-empty"><i class="bi bi-tag"></i> Chưa có chương trình khuyến mãi.</div>
                    <?php endif; ?>
                </div>

                <!-- Panel TIN TỨC (hover "Tin tức mới nhất") — phủ lên banner như flyout -->
                <div class="pm-hero-flyout pm-hero-flyout--list" id="pmHeroNewsPanel" data-hero-panel-body="news">
                    <div class="pm-hero-flyout-head">
                        <span class="pm-hero-flyout-title"><i class="bi bi-newspaper"></i> Bài viết mới nhất</span>
                        <a href="<?= h(rtrim((string)$baseUrl, '/') . '/blog') ?>" class="pm-hero-flyout-all">Tất cả <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!empty($homeBlogPosts)): ?>
                        <div class="pm-hero-flyout-list">
                            <?php foreach (array_slice($homeBlogPosts, 0, 6) as $post): ?>
                                <?php
                                $dbTitle = trim((string)($post['title'] ?? 'Bài viết')) ?: 'Bài viết';
                                $dbSlug = trim((string)($post['slug'] ?? ''));
                                $dbCat = trim((string)($post['category_name'] ?? ''));
                                $dbTimeRaw = trim((string)($post['published_at'] ?? ''));
                                $dbTimeText = $dbTimeRaw !== '' ? date('d/m/Y', strtotime($dbTimeRaw)) : '';
                                $dbThumb = trim((string)($post['thumbnail_url'] ?? ''));
                                $dbThumb = $dbThumb !== '' ? to_abs_url($dbThumb, $baseUrl) : '';
                                $dbSub = trim((string)($post['excerpt'] ?? ''));
                                $dbLink = $dbSlug !== ''
                                    ? (rtrim((string)$baseUrl, '/') . '/blog/' . rawurlencode($dbSlug))
                                    : (rtrim((string)$baseUrl, '/') . '/blog');
                                ?>
                                <a class="pm-hero-litem" href="<?= h($dbLink) ?>">
                                    <span class="pm-hero-litem-thumb">
                                        <?php if ($dbThumb !== ''): ?>
                                            <img src="<?= h($dbThumb) ?>" alt="<?= h($dbTitle) ?>" loading="lazy" decoding="async" onerror="this.outerHTML='<i class=&quot;bi bi-newspaper&quot;></i>';">
                                        <?php else: ?>
                                            <i class="bi bi-newspaper"></i>
                                        <?php endif; ?>
                                        <?php if ($dbCat !== ''): ?><span class="pm-hero-litem-badge"><?= h($dbCat) ?></span><?php endif; ?>
                                        <span class="pm-hero-litem-overlay">
                                            <span class="pm-hero-litem-title"><?= h($dbTitle) ?></span>
                                            <span class="pm-hero-litem-sub"><?= h($dbSub !== '' ? $dbSub : ($dbTimeText !== '' ? ('Đăng ' . $dbTimeText) : 'Xem bài viết')) ?></span>
                                        </span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pm-hero-flyout-empty"><i class="bi bi-newspaper"></i> Chưa có bài viết nào.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hàng trust badges -->
            <?php
            $pmTrustItems = [
                ['bi-truck', 'Miễn phí vận chuyển', 'Đơn từ 499k'],
                ['bi-arrow-repeat', 'Đổi trả dễ dàng', 'Trong 7 ngày'],
                ['bi-shield-check', 'Thanh toán an toàn', 'Nhiều hình thức'],
                ['bi-patch-check', 'Sản phẩm chính hãng', 'Cam kết 100%'],
                ['bi-headset', 'Hỗ trợ 24/7', '1900 1234'],
            ];
            ?>
            <div class="pm-hero-trust">
                <!-- track: nhân đôi item để chạy băng chuyền liên tục trên mobile -->
                <div class="pm-hero-trust-track">
                    <?php for ($pmDup = 0; $pmDup < 2; $pmDup++): ?>
                        <?php foreach ($pmTrustItems as $pmTi): ?>
                            <div class="pm-hero-trust-item" <?= $pmDup === 1 ? ' aria-hidden="true"' : '' ?>>
                                <i class="bi <?= h($pmTi[0]) ?>"></i>
                                <div><strong><?= h($pmTi[1]) ?></strong><span><?= h($pmTi[2]) ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </section>
        <style>
            .pm-hero {
                /* margin-bottom: 1.25rem */
            }

            .pm-hero-grid {
                display: grid;
                grid-template-columns: 260px 1fr;
                gap: 16px;
                align-items: stretch
            }

            .pm-hero-cats {
                background: #fff;
                border: 1px solid #eef0f4;
                border-radius: 14px;
                overflow: hidden;
                box-shadow: 0 4px 18px rgba(20, 24, 40, .05)
            }

            .pm-hero-cats-head {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #fff;
                color: #333;
                font-weight: 700;
                font-size: .82rem;
                letter-spacing: .3px;
                padding: 13px 16px
            }

            .pm-hero-cats-list {
                list-style: none;
                margin: 0;
                padding: 6px 0;
                max-height: 330px;
                overflow: auto;
                /* Ẩn scrollbar nhưng vẫn cuộn được (Firefox + IE) */
                scrollbar-width: none;
                -ms-overflow-style: none
            }

            /* Ẩn scrollbar trên Chrome/Safari/Edge */
            .pm-hero-cats-list::-webkit-scrollbar {
                display: none
            }

            .pm-hero-cat-link {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 9px 16px;
                color: #3a3f51;
                font-size: .86rem;
                text-decoration: none;
                transition: background .15s, color .15s
            }

            .pm-hero-cat-link:hover {
                background: var(--theme-primary-soft, rgba(12, 76, 41, .08));
                color: var(--theme-primary, #0c4c29)
            }

            .pm-hero-cat-ico {
                width: 22px;
                height: 22px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--theme-primary, #0c4c29);
                flex: 0 0 auto
            }

            .pm-hero-cat-ico img {
                width: 22px;
                height: 22px;
                object-fit: contain
            }

            .pm-hero-cat-name {
                flex: 1;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis
            }

            .pm-hero-cat-chev {
                font-size: .7rem;
                opacity: .45
            }

            .pm-hero-main {
                display: flex;
                flex-direction: column;
                gap: 10px;
                min-width: 0;
                position: relative
            }

            .pm-hero-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 6px 20px;
                align-items: center;
                background: #fff;
                border: 1px solid #eef0f4;
                border-radius: 12px;
                padding: 11px 18px;
                box-shadow: 0 4px 18px rgba(20, 24, 40, .05);
                position: sticky;
                top: 8px;
                z-index: 50;
                transition: box-shadow .2s
            }

            .pm-hero-nav.is-stuck {
                box-shadow: 0 6px 22px rgba(20, 24, 40, .14)
            }

            .pm-hero-nav-item {
                position: relative;
                display: inline-flex;
                align-items: center
            }

            .pm-hero-nav-link {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                color: #4a4f63;
                font-weight: 600;
                font-size: .86rem;
                text-decoration: none;
                padding: 2px 0
            }

            /* Gạch chân động trượt mượt khi hover / active */
            .pm-hero-nav-link::after {
                content: "";
                position: absolute;
                left: 0;
                right: 100%;
                bottom: -3px;
                height: 2px;
                border-radius: 2px;
                background: var(--theme-primary, #0c4c29);
                transition: right .22s ease
            }

            .pm-hero-nav-link:hover,
            .pm-hero-nav-link.active {
                color: var(--theme-primary, #0c4c29)
            }

            .pm-hero-nav-link:hover::after,
            .pm-hero-nav-link.active::after {
                right: 0
            }

            .pm-hero-nav-caret {
                font-size: .62rem;
                transition: transform .2s
            }

            .pm-hero-nav-item.has-mega:hover .pm-hero-nav-caret {
                transform: rotate(180deg)
            }

            /* Mega-menu danh mục dưới tab "Sản phẩm" */
            .pm-hero-mega {
                position: absolute;
                top: calc(100% + 12px);
                left: 0;
                width: 540px;
                max-width: 70vw;
                background: #fff;
                border: 1px solid #eef0f4;
                border-radius: 14px;
                box-shadow: 0 18px 44px rgba(20, 24, 40, .16);
                padding: 14px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(6px);
                transition: opacity .18s, transform .18s, visibility .18s;
                z-index: 60
            }

            .pm-hero-mega::before {
                content: "";
                position: absolute;
                top: -12px;
                left: 0;
                right: 0;
                height: 12px
            }

            .pm-hero-nav-item.has-mega:hover .pm-hero-mega {
                opacity: 1;
                visibility: visible;
                transform: translateY(0)
            }

            .pm-hero-mega-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 6px
            }

            .pm-hero-mega-cat {
                display: flex;
                align-items: center;
                gap: 9px;
                padding: 8px 10px;
                border-radius: 10px;
                text-decoration: none;
                color: #3a3f51;
                font-size: .82rem;
                transition: background .15s, color .15s
            }

            .pm-hero-mega-cat:hover {
                background: var(--theme-primary-soft, rgba(12, 76, 41, .08));
                color: var(--theme-primary, #0c4c29)
            }

            .pm-hero-mega-ico {
                width: 24px;
                height: 24px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--theme-primary, #0c4c29);
                flex: 0 0 auto
            }

            .pm-hero-mega-ico img {
                width: 24px;
                height: 24px;
                object-fit: contain
            }

            .pm-hero-mega-name {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis
            }

            .pm-hero-mega-all {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-top: 10px;
                padding-top: 11px;
                border-top: 1px solid #f1f1f6;
                width: 100%;
                color: var(--theme-primary, #0c4c29);
                font-weight: 700;
                font-size: .82rem;
                text-decoration: none
            }

            .pm-hero-mega-all:hover {
                gap: 9px
            }

            .pm-hero-tag {
                font-size: .55rem;
                font-weight: 800;
                color: #fff;
                padding: 1px 5px;
                border-radius: 6px;
                vertical-align: top;
                margin-left: 2px
            }

            .pm-hero-tag.hot {
                background: #ef4444
            }

            .pm-hero-tag.new {
                background: #16a34a
            }

            .pm-hero-banner {
                position: relative;
                flex: 1;
                min-height: 280px;
                border-radius: 16px;
                overflow: hidden
            }

            .pm-hero-banner .carousel-inner,
            .pm-hero-banner .carousel-item {
                height: 100%
            }

            /* Mỗi slide: media phủ full + overlay gradient + nội dung text */
            .pm-hero-slide {
                position: relative;
                min-height: 280px;
                height: 100%;
                display: flex;
                align-items: center
            }

            .pm-hero-media {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                background: #f1f4f8
            }

            /* Lon sơn trang trí: đè nổi góc phải banner, nghiêng nhẹ cho cảm giác 3D */
            .pm-hero-product-float {
                position: absolute;
                top: 0px;
                right: 38px;
                bottom: 0px;
                height: 100%;
                width: auto;
                max-height: 400px;
                z-index: 2;
                /* z-index:2 nằm trên gradient (1), dưới nút điều hướng (5) */
                pointer-events: none;
                object-fit: contain;
                transform-origin: bottom center;
                filter: drop-shadow(-10px 16px 22px rgba(0, 0, 0, .35));
                /* Bật lên khi tải trang (pop-in) rồi chuyển sang trôi nổi nghiêng 3D liên tục */
                animation:
                    pmHeroPop .7s cubic-bezier(.18, .89, .32, 1.28) both,
                    pmHeroFloat3d 4.5s ease-in-out 0.7s infinite;
            }

            /* Pop-in: từ nhỏ + thẳng đứng → phóng to nhẹ + nghiêng 12° */
            @keyframes pmHeroPop {
                0% {
                    opacity: 0;
                    transform: translateY(10px) scale(.7) rotate(0deg);
                }

                60% {
                    opacity: 1;
                    transform: translateY(-8px) scale(1.06) rotate(0deg);
                }

                100% {
                    opacity: 1;
                    transform: translateY(0) scale(1) rotate(0deg);
                }
            }

            /* Trôi nổi + đong đưa góc nghiêng để có chiều sâu 3D */
            @keyframes pmHeroFloat3d {

                0%,
                100% {
                    transform: translateY(0) rotate(0deg);
                }

                50% {
                    transform: translateY(-5px) rotate(0deg);
                }
            }

            @media (max-width: 768px) {

                /* Màn nhỏ: thu nhỏ lon sơn cho khỏi che chữ */
                .pm-hero-product-float {
                    height: 60%;
                    right: 12px;
                    opacity: .92;
                }
            }

            @media (max-width: 520px) {
                .pm-hero-product-float {
                    display: none;
                }
            }

            /* Tôn trọng người dùng tắt hiệu ứng chuyển động */
            @media (prefers-reduced-motion: reduce) {
                .pm-hero-product-float {
                    animation: none;
                    transform: rotate(12deg);
                }
            }

            .pm-hero-slide::before {
                content: "";
                position: absolute;
                inset: 0;
                z-index: 1;
                /* Overlay xanh thương hiệu, gradient chéo nhã từ trái → phải trong suốt
                   để chữ rõ mà vẫn lộ ảnh nền */
                /* background:
                    linear-gradient(100deg, rgb(7 54 29 / 90%) 0%, rgb(11 75 40 / 58%) 45%, rgb(11 75 40 / 10%) 74%, transparent 100%),
                    linear-gradient(0deg, rgb(6 48 26 / 22%) 0%, transparent 30%); */
            }

            /* Mũi tên điều hướng + chấm chỉ mục */
            .pm-hero-ctrl {
                width: 42px;
                opacity: 0;
                transition: opacity .2s;
                z-index: 5
            }

            .pm-hero-banner:hover .pm-hero-ctrl {
                opacity: .85
            }

            .pm-hero-ctrl i {
                font-size: 1.4rem;
                color: #fff;
                text-shadow: 0 1px 4px rgba(0, 0, 0, .4)
            }

            .pm-hero-dots {
                z-index: 5;
                margin-bottom: 8px
            }

            .pm-hero-dots [data-bs-target] {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                border: 0
            }

            .pm-hero-banner-inner {
                position: relative;
                z-index: 2;
                padding: 28px 34px;
                max-width: 500px;
                color: #fff;
                text-shadow: 0 1px 3px rgba(0, 0, 0, .35)
            }

            /* Eyebrow nhã: chữ trắng spaced + đường kẻ mảnh dẫn vào, không nền */
            .pm-hero-eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                font-size: .7rem;
                font-weight: 700;
                letter-spacing: 3px;
                margin-bottom: 18px;
                color: rgb(255 255 255 / 82%);
                text-shadow: none;
            }

            /* Đường kẻ ngắn trước chữ — chi tiết tinh tế thay cho badge */
            .pm-hero-eyebrow::before {
                content: "";
                width: 26px;
                height: 2px;
                border-radius: 2px;
                background: rgb(255 255 255 / 55%);
            }

            .pm-hero-headline {
                font-size: 2.35rem;
                color: #fff;
                line-height: 1.14;
                font-weight: 800;
                letter-spacing: -.4px;
                margin: 0 0 14px;
                text-shadow: 0 2px 14px rgba(0, 0, 0, .32)
            }

            .pm-hero-sub {
                font-size: 1.02rem;
                line-height: 1.5;
                opacity: .92;
                margin: 0 0 24px;
                max-width: 30ch
            }

            .pm-hero-actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 16px
            }

            .pm-hero-btn {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                overflow: hidden;
                background: #fff;
                color: var(--theme-primary, #0c4c29);
                font-weight: 800;
                font-size: .85rem;
                letter-spacing: .5px;
                padding: 12px 28px;
                border-radius: 30px;
                text-decoration: none;
                text-shadow: none;
                box-shadow: 0 8px 22px rgba(0, 0, 0, .18);
                transition: transform .18s ease, box-shadow .18s ease
            }

            /* Hover: nút trắng → fill xanh thương hiệu, chữ trắng. Mượt, sang. */
            .pm-hero-btn:hover {
                transform: translateY(-2px);
                background: var(--theme-primary, #0c4c29);
                color: #fff;
                box-shadow: 0 14px 30px rgba(0, 0, 0, .28)
            }

            .pm-hero-btn::before {
                /* mũi tên dùng ký tự, tách khỏi text-shadow của vùng nội dung */
                content: "\2192";
                font-weight: 800;
                order: 2;
                transition: transform .18s ease
            }

            .pm-hero-btn:hover::before {
                transform: translateX(3px)
            }

            .pm-hero-link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: #fff;
                font-weight: 600;
                font-size: .85rem;
                text-decoration: none;
                opacity: .92;
                transition: opacity .18s ease
            }

            .pm-hero-link i {
                transition: transform .18s ease
            }

            .pm-hero-link:hover {
                color: #fff;
                opacity: 1
            }

            .pm-hero-link:hover i {
                transform: translateX(4px)
            }

            .pm-hero-trust {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 14px;
                margin-top: 16px;
                background: #fff;
                border: 1px solid #eef0f4;
                border-radius: 14px;
                padding: 16px 18px;
                box-shadow: 0 4px 18px rgba(20, 24, 40, .05)
            }

            /* Desktop: track trong suốt để grid áp lên item; ẩn bản nhân đôi */
            .pm-hero-trust-track {
                display: contents
            }

            .pm-hero-trust-item[aria-hidden="true"] {
                display: none
            }

            .pm-hero-trust-item {
                display: flex;
                align-items: center;
                gap: 11px
            }

            .pm-hero-trust-item i {
                font-size: 1.5rem;
                color: var(--theme-primary, #0c4c29);
                flex: 0 0 auto
            }

            .pm-hero-trust-item strong {
                display: block;
                font-size: .82rem;
                color: #2b2f40;
                line-height: 1.2
            }

            .pm-hero-trust-item span {
                display: block;
                font-size: .74rem;
                color: #8b90a0
            }

            /* Flyout gợi ý sản phẩm khi hover danh mục */
            .pm-hero-grid {
                position: relative
            }

            .pm-hero-cat-link.has-suggest .pm-hero-cat-chev {
                opacity: .7
            }

            /* Flyout phủ lên vùng banner: bắt đầu sau sidebar (260px) + gap (16px) */
            .pm-hero-flyout {
                position: absolute;
                top: 0;
                left: 276px;
                right: 0;
                bottom: 0;
                background: #fff;
                border: 1px solid #eef0f4;
                border-radius: 16px;
                box-shadow: 0 18px 44px rgba(20, 24, 40, .16);
                padding: 16px 18px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(6px);
                transition: opacity .18s, transform .18s, visibility .18s;
                z-index: 40;
                overflow: hidden;
                display: flex;
                flex-direction: column
            }

            .pm-hero-flyout.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0)
            }

            /* Panel list (khuyến mãi/tin tức): cho phép cao hơn banner để 2 hàng thẻ
               hiện đủ, không bị cắt mất tiêu đề ở mép dưới. */
            .pm-hero-flyout--list {
                bottom: auto;
                max-height: 560px
            }

            .pm-hero-flyout-title {
                font-weight: 700;
                font-size: .85rem;
                color: var(--theme-primary, #0c4c29);
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid var(--theme-primary-soft, rgba(12, 76, 41, .08));
                flex: 0 0 auto
            }

            .pm-hero-flyout-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                overflow: auto;
                flex: 1;
                align-content: start
            }

            /* ===== Panel KHUYẾN MÃI / TIN TỨC (tái dùng .pm-hero-flyout, dạng list) ===== */
            .pm-hero-flyout-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid var(--theme-primary-soft, rgba(12, 76, 41, .08));
                flex: 0 0 auto
            }

            .pm-hero-flyout-head .pm-hero-flyout-title {
                margin: 0;
                padding: 0;
                border: 0;
                display: inline-flex;
                align-items: center;
                gap: 6px
            }

            .pm-hero-flyout-all {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                color: var(--theme-primary, #0c4c29);
                font-size: .8rem;
                font-weight: 700;
                text-decoration: none
            }

            .pm-hero-flyout-all:hover {
                gap: 7px
            }

            .pm-hero-flyout-list {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
                overflow: auto;
                flex: 1;
                align-content: start;
                padding-bottom: 4px
            }

            @media (max-width: 1199px) {
                .pm-hero-flyout-list {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            /* Item dạng THẺ ẢNH: tiêu đề + tiêu đề con ĐÈ lên banner (overlay gradient) */
            .pm-hero-litem {
                position: relative;
                display: block;
                border-radius: 12px;
                overflow: hidden;
                text-decoration: none;
                background: #f1f4f8;
                border: 1px solid #f0f1f6;
                transition: border-color .15s, transform .15s, box-shadow .15s
            }

            .pm-hero-litem:hover {
                border-color: rgba(12, 76, 41, .35);
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(20, 24, 40, .12)
            }

            .pm-hero-litem-thumb {
                position: relative;
                width: 100%;
                aspect-ratio: 16 / 10;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f1f4f8;
                color: var(--theme-primary, #0c4c29);
                font-size: 2rem
            }

            .pm-hero-litem-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform .3s ease
            }

            .pm-hero-litem:hover .pm-hero-litem-thumb img {
                transform: scale(1.05)
            }

            /* Lớp chữ đè lên đáy ảnh + gradient tối để chữ trắng nổi rõ */
            .pm-hero-litem-overlay {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                padding: 34px 12px 14px;
                display: flex;
                flex-direction: column;
                gap: 4px;
                background: linear-gradient(to top, rgba(0, 0, 0, .88) 0%, rgba(0, 0, 0, .5) 50%, transparent 100%);
                z-index: 2
            }

            .pm-hero-litem-title {
                font-size: .85rem;
                color: #fff;
                font-weight: 700;
                line-height: 1.35;
                text-shadow: 0 1px 3px rgba(0, 0, 0, .6);
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden
            }

            .pm-hero-litem-sub {
                font-size: .73rem;
                color: rgba(255, 255, 255, .88);
                line-height: 1.35;
                text-shadow: 0 1px 2px rgba(0, 0, 0, .6);
                display: -webkit-box;
                -webkit-line-clamp: 1;
                line-clamp: 1;
                -webkit-box-orient: vertical;
                overflow: hidden
            }

            /* Badge danh mục góc trên-trái (blog) */
            .pm-hero-litem-badge {
                position: absolute;
                top: 8px;
                left: 8px;
                z-index: 2;
                background: var(--theme-primary, #0c4c29);
                color: #fff;
                font-size: .68rem;
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 999px;
                box-shadow: 0 2px 6px rgba(0, 0, 0, .25)
            }

            .pm-hero-flyout-empty {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                color: #9aa0b0;
                font-size: .85rem
            }

            .pm-hero-sug-item {
                display: flex;
                align-items: center;
                gap: 9px;
                padding: 7px;
                border-radius: 10px;
                text-decoration: none;
                background: #fafafd;
                border: 1px solid #f0f1f6;
                transition: background .15s, border-color .15s, transform .15s
            }

            .pm-hero-sug-item:hover {
                background: var(--theme-primary-soft, rgba(12, 76, 41, .08));
                border-color: rgba(var(--theme-primary-rgb, 12, 76, 41), .35);
                transform: translateY(-1px)
            }

            .pm-hero-sug-thumb {
                width: 46px;
                height: 46px;
                border-radius: 8px;
                object-fit: cover;
                background: #f1f4f8;
                flex: 0 0 auto
            }

            .pm-hero-sug-info {
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 2px
            }

            .pm-hero-sug-name {
                font-size: .78rem;
                color: #2b2f40;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden
            }

            .pm-hero-sug-price {
                font-size: .78rem;
                font-weight: 700;
                color: #e8505b
            }

            .pm-hero-sug-empty {
                grid-column: 1/-1;
                text-align: center;
                color: #9aa0b0;
                font-size: .82rem;
                padding: 18px 0
            }

            .pm-hero-sug-skel {
                height: 60px;
                border-radius: 10px;
                background: linear-gradient(90deg, #f2f3f7 25%, #e9ebf1 37%, #f2f3f7 63%);
                background-size: 400% 100%;
                animation: pmHeroSkel 1.2s ease infinite
            }

            @keyframes pmHeroSkel {
                0% {
                    background-position: 100% 0
                }

                100% {
                    background-position: -100% 0
                }
            }

            @media(max-width:991px) {
                .pm-hero-grid {
                    grid-template-columns: 1fr
                }

                .pm-hero-cats {
                    display: none
                }

                .pm-hero-trust {
                    grid-template-columns: repeat(2, 1fr)
                }

                /* Trên thiết bị cảm ứng: tắt mega-menu + flyout hover (khuyến mãi/tin tức)
                   + caret, nav không sticky. Tránh panel phủ absolute (left:276px) đè lên
                   banner khiến nav trông như bị thu nhỏ khi chạm/hover trên mobile. */
                .pm-hero-mega,
                .pm-hero-flyout,
                .pm-hero-nav-caret {
                    display: none !important
                }

                .pm-hero-nav {
                    position: static;
                    top: auto
                }

                /* Mobile/tablet: nav dạng "chip" cuộn ngang 1 hàng — chạm = đi thẳng tới
                   trang. Mỗi chip có nền bo tròn, vùng chạm đủ lớn (>=40px), phản hồi khi nhấn. */
                .pm-hero-nav-link {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    padding: 9px 16px;
                    min-height: 40px;
                    border-radius: 999px;
                    background: #f1f3f7;
                    border: 1px solid transparent;
                    color: #3a3f52;
                    font-weight: 600;
                    white-space: nowrap;
                    -webkit-tap-highlight-color: transparent;
                    transition: background .15s, color .15s, border-color .15s, transform .1s
                }

                /* Chip của trang đang xem: tô màu thương hiệu cho nổi bật */
                .pm-hero-nav-link.active {
                    background: var(--theme-primary, #0c4c29);
                    border-color: var(--theme-primary, #0c4c29);
                    color: #fff
                }

                /* Phản hồi xúc giác khi chạm (nhấn xuống nhẹ) */
                .pm-hero-nav-link:active {
                    transform: scale(.96);
                    background: var(--theme-primary-soft, rgba(12, 76, 41, .1))
                }

                .pm-hero-nav-link.active:active {
                    background: var(--theme-primary, #0c4c29)
                }

                /* Bỏ gạch chân động (vốn dành cho hover desktop) trên mobile */
                .pm-hero-nav-link::after {
                    display: none
                }
            }

            @media(max-width:575px) {

                /* Nav: cuộn ngang 1 hàng các chip, không wrap lộn xộn */
                .pm-hero-nav {
                    flex-wrap: nowrap;
                    justify-content: flex-start;
                    gap: 8px;
                    overflow-x: auto;
                    padding: 8px 12px;
                    border-radius: 12px;
                    scroll-snap-type: x proximity;
                    -webkit-overflow-scrolling: touch;
                    scrollbar-width: none
                }

                .pm-hero-nav::-webkit-scrollbar {
                    display: none
                }

                .pm-hero-nav-item,
                .pm-hero-nav>.pm-hero-nav-link {
                    flex: 0 0 auto;
                    scroll-snap-align: start
                }

                .pm-hero-nav-link {
                    white-space: nowrap;
                    font-size: .9rem
                }

                .pm-hero-banner,
                .pm-hero-slide {
                    min-height: 220px
                }

                .pm-hero-slide::before {
                    /* Mobile: phủ đậm hơn từ đáy lên để chữ luôn đọc rõ dù ảnh nền sáng */
                    /* background:
                        linear-gradient(105deg, rgb(8 58 31 / 90%) 0%, rgb(11 75 40 / 62%) 55%, rgb(11 75 40 / 30%) 100%),
                        linear-gradient(0deg, rgb(6 48 26 / 45%) 0%, transparent 45%); */
                }

                .pm-hero-banner-inner {
                    padding: 20px;
                    max-width: 100%
                }

                .pm-hero-headline {
                    font-size: 1.6rem
                }

                .pm-hero-btn {
                    padding: 11px 24px;
                    font-size: .85rem
                }

                .pm-hero-sub {
                    font-size: .9rem;
                    margin-bottom: 16px
                }

                .pm-hero-actions {
                    gap: 12px
                }

                /* Trust badges: băng chuyền tự chạy liên tục (marquee) trên mobile */
                .pm-hero-trust {
                    display: block;
                    grid-template-columns: none;
                    overflow: hidden;
                    padding: 12px 0
                }

                .pm-hero-trust-track {
                    display: flex;
                    flex-wrap: nowrap;
                    width: max-content;
                    gap: 10px;
                    padding-left: 10px;
                    /* Chạy hết 50% (1 bản gốc) rồi lặp lại liền mạch nhờ bản nhân đôi */
                    animation: pmTrustMarquee 18s linear infinite;
                    will-change: transform
                }

                /* Tạm dừng khi người dùng chạm vào để đọc */
                .pm-hero-trust:active .pm-hero-trust-track {
                    animation-play-state: paused
                }

                /* Hiện cả bản nhân đôi để băng chuyền liền mạch */
                .pm-hero-trust-item[aria-hidden="true"] {
                    display: flex
                }

                .pm-hero-trust-item {
                    flex: 0 0 auto;
                    gap: 9px;
                    padding: 9px 13px;
                    background: #f8f9fc;
                    border: 1px solid #eef0f4;
                    border-radius: 12px
                }

                .pm-hero-trust-item i {
                    font-size: 1.25rem
                }

                .pm-hero-trust-item strong {
                    white-space: nowrap;
                    font-size: .78rem
                }

                .pm-hero-trust-item span {
                    white-space: nowrap;
                    font-size: .7rem
                }

                @keyframes pmTrustMarquee {
                    from {
                        transform: translateX(0)
                    }

                    to {
                        /* Dịch đúng 50% chiều rộng track (1 bản) + bù 1 khoảng gap để khớp liền mạch */
                        transform: translateX(calc(-50% - 5px))
                    }
                }

                /* Tôn trọng người dùng tắt hiệu ứng chuyển động */
                @media (prefers-reduced-motion: reduce) {
                    .pm-hero-trust-track {
                        animation: none
                    }

                    .pm-hero-trust {
                        overflow-x: auto;
                        scrollbar-width: none
                    }
                }
            }
        </style>
        <!-- ===== /HERO SECTION MỚI ===== -->
        <?php /*    
        <!-- Trang đầu: Danh mục -->
        <div class="hero-combo" id="heroRealContent">
            <div class="cat-panel card border-0 shadow-sm">
                <!-- <h5>DANH MỤC</h5> -->
                <div class="cat-toggle-list" id="catToggleList">
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $catId = intval($cat['id'] ?? 0);
                        if ($catId <= 0) continue;
                        $catName = trim((string)($cat['name'] ?? 'Danh mục'));
                        $catThumb = trim((string)($catSuggestions[$catId]['icon_image_url'] ?? ''));
                        ?>
                        <div class="cat-toggle-item" data-cat-id="<?= $catId ?>" data-slug="<?= h($cat['slug'] ?? '') ?>">
                            <span class="cat-toggle-label">
                                <?php if ($catThumb !== ''): ?>
                                    <span class="cat-toggle-thumb"><img src="<?= h($catThumb) ?>" alt="<?= h($catName) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.parentNode.innerHTML='<i class=&quot;bi bi-grid&quot;></i>';this.parentNode.classList.add('cat-toggle-icon');this.parentNode.classList.remove('cat-toggle-thumb');"></span>
                                <?php else: ?>
                                    <span class="cat-toggle-icon"><i class="bi bi-grid"></i></span>
                                <?php endif; ?>
                                <span class="cat-toggle-name"><?= h($catName) ?></span>
                            </span>
                            <i class="bi bi-chevron-right cat-toggle-chevron"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Trang giữa: Carousel + Đối tác -->
            <div class="hero-middle d-none d-sm-block">
                <div class="hero-wrap">
                    <div id="homeCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4000">

                        <div class="carousel-inner">
                            <?php foreach ($homeCarouselItems as $idx => $bn): ?>
                                <?php
                                $imgRaw = trim((string)($bn['image_url'] ?? ''));
                                $img = $imgRaw !== '' ? h($imgRaw) : h($fallbackImage);
                                $fallbackAttr = h($fallbackImage);
                                $title = trim((string)($bn['title'] ?? ''));
                                $content = trim((string)($bn['content'] ?? ''));
                                $link = trim((string)($bn['link_url'] ?? ''));
                                $active = $idx === 0 ? ' active' : '';
                                ?>
                                <div class="carousel-item<?= $active ?>">
                                    <div class="carousel-placeholder">
                                        <?php
                                        $ext = strtolower(pathinfo(parse_url($imgRaw, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                                        $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov'], true);
                                        ?>
                                        <?php if ($isVideo): ?>
                                            <?php if ($link !== ''): ?>
                                                <a href="<?= h($link) ?>" target="_blank" rel="noopener">
                                                    <video src="<?= $img ?>" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;border-radius:0 0 14px 14px;background:#f1f4f8;"></video>
                                                </a>
                                            <?php else: ?>
                                                <video src="<?= $img ?>" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;border-radius:0 0 14px 14px;background:#f1f4f8;"></video>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($link !== ''): ?>
                                                <a href="<?= h($link) ?>" target="_blank" rel="noopener">
                                                    <img src="<?= $img ?>" alt="Banner" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;border-radius:0 0 14px 14px;background:#f1f4f8;" onerror="if(this.src!=='<?= $fallbackAttr ?>'){this.onerror=null;this.src='<?= $fallbackAttr ?>';}">
                                                </a>
                                            <?php else: ?>
                                                <img src="<?= $img ?>" alt="Banner" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;border-radius:0 0 14px 14px;background:#f1f4f8;" onerror="if(this.src!=='<?= $fallbackAttr ?>'){this.onerror=null;this.src='<?= $fallbackAttr ?>';}">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($title !== '' || $content !== '' || $link !== ''): ?>
                                            <div class="carousel-caption-box">
                                                <?php if ($title !== ''): ?>
                                                    <h5 class="carousel-caption-title"><?= h($title) ?></h5>
                                                <?php endif; ?>
                                                <?php if ($content !== ''): ?>
                                                    <p class="carousel-caption-content"><?= h($content) ?></p>
                                                <?php endif; ?>
                                                <?php if ($link !== ''): ?>
                                                    <div class="mt-2">
                                                        <a href="<?= h($link) ?>" class="btn btn-primary btn-sm px-3 py-1.5 rounded-pill fw-bold text-white shadow-sm" style="background-color: var(--theme-primary, #0c4c29); border-color: var(--theme-primary, #0c4c29); font-size: 0.8rem;">
                                                            Khám phá ngay <i class="bi bi-arrow-right ms-1"></i>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <div class="cat-suggest-card" id="catSuggestCard">
                        <div class="cat-suggest-title" id="catSuggestTitle">Giá bán theo danh mục</div>
                        <div class="cat-suggest-grid" id="catSuggestGrid"></div>
                    </div>
                </div>
                <?php if (!empty($homePartners)): ?>
                    <div class="partner-home-section d-none d-sm-block">
                        <!-- THÔNG BÁO KHUYẾM MÃI (3-Column Banner) 
                <div id="promotion_banner" class="promotion-banner-section">
                    <div class="promotion-banner-track">
                        <?php if (!empty($homePromos)): ?>
                            <?php foreach ($homePromos as $p):
                                $payload = json_decode($p['body'] ?? '', true);
                                $isNotx = is_array($payload) && ($payload['schema'] ?? '') === 'notx_v2';
                                $displayImg = '';
                                if ($isNotx) {
                                    $displayImg = trim((string)($payload['main_banner'] ?? ''));
                                    if ($displayImg === '') {
                                        $displayImg = trim((string)($payload['thumb_image'] ?? ''));
                                    }
                                }
                                if ($displayImg === '') {
                                    $meta = json_decode($p['meta_json'] ?? '', true) ?: [];
                                    $displayImg = trim((string)($meta['thumb_image'] ?? ''));
                                }
                                if ($displayImg === '') continue;

                                $link = $isNotx ? ($payload['link'] ?? $p['link']) : $p['link'];
                                if (!$link) $link = nf_build_url((int)$p['id'], (string)($p['title'] ?? ''), $baseUrl);
                            ?>
                                <a href="<?= h($link) ?>" class="promotion-banner-item">
                                    <img src="<?= h($displayImg) ?>" class="promotion-banner-img" alt="Banner" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>-->
                        <!-- Đơn vị đồng hành -->
                        <div class="partner-home-line mb-2 mt-3">Đồng hành cùng chúng tôi</div>
                        <div class="partner-marquee">
                            <div class="partner-track">
                                <?php foreach ($homePartners as $partner): ?>
                                    <?php
                                    $partnerName = trim((string)($partner['partner_name'] ?? 'Đối tác'));
                                    $partnerImage = trim((string)($partner['image_url'] ?? ''));
                                    ?>
                                    <div class="partner-item">
                                        <img class="partner-home-logo" src="<?= h($partnerImage) ?>" alt="<?= h($partnerName) ?>" loading="lazy" decoding="async" onerror="this.src='<?= h($fallbackImage) ?>';">
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($homePartners as $partner): ?>
                                    <?php
                                    $partnerName = trim((string)($partner['partner_name'] ?? 'Đối tác'));
                                    $partnerImage = trim((string)($partner['image_url'] ?? ''));
                                    ?>
                                    <div class="partner-item">
                                        <img class="partner-home-logo" src="<?= h($partnerImage) ?>" alt="<?= h($partnerName) ?>" loading="lazy" decoding="async" onerror="this.src='<?= h($fallbackImage) ?>';">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Trang Khuyến mãi / Blog tin tức -->
            <div class="promo-panel shadow-sm">
                <ul class="nav nav-tabs promo-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active position-relative" id="blog-tab" data-bs-toggle="tab" data-bs-target="#blog-tab-pane" type="button" role="tab" aria-controls="blog-tab-pane" aria-selected="true">
                            Tin tức
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="promo-tab" data-bs-toggle="tab" data-bs-target="#promo-tab-pane" type="button" role="tab" aria-controls="promo-tab-pane" aria-selected="false">
                            Khuyến mãi
                        </button>
                    </li>
                </ul>
                <div class="tab-content promo-tab-content">
                    <!-- Tab Khuyến mãi: thông báo khuyến mãi hệ thống -->
                    <div class="tab-pane fade" id="promo-tab-pane" role="tabpanel" aria-labelledby="promo-tab">
                        <div class="promo-list">
                            <?php if (!empty($promoNotices)): ?>
                                <?php foreach ($promoNotices as $notice): ?>
                                    <?php
                                    $noticeId = (int)($notice['id'] ?? 0);
                                    $noticeTitle = trim((string)($notice['title'] ?? 'Khuyến mãi'));
                                    $noticeBody = (string)($notice['body'] ?? '');
                                    $noticeExcerpt = home_notice_excerpt($noticeBody, 90);
                                    if ($noticeExcerpt === '') {
                                        $noticeExcerpt = 'Xem chi tiết khuyến mãi.';
                                    }
                                    $noticeTimeRaw = trim((string)($notice['created_at'] ?? ''));
                                    $noticeTimeText = $noticeTimeRaw !== '' ? date('d/m/Y H:i', strtotime($noticeTimeRaw)) : 'Vừa xong';
                                    $noticeTimestamp = $noticeTimeRaw !== '' ? strtotime($noticeTimeRaw) : false;
                                    $isHot = $noticeTimestamp ? ($noticeTimestamp >= (time() - 86400)) : false;
                                    $linkRaw = trim((string)($notice['link'] ?? ''));
                                    if ($linkRaw !== '') {
                                        if (preg_match('#^(https?:)?//#i', $linkRaw)) {
                                            $detailLink = $linkRaw;
                                        } else {
                                            $detailLink = $baseUrl . '/' . ltrim($linkRaw, '/');
                                        }
                                    } else {
                                        $detailLink = nf_build_url($noticeId, $noticeTitle, $baseUrl);
                                    }
                                    // Cố gắng lấy ảnh đại diện từ nội dung thông báo
                                    $noticeImage = home_notice_image($noticeBody, $baseUrl);
                                    ?>
                                    <a class="promo-item" href="<?= h($detailLink) ?>">
                                        <?php if ($noticeImage !== ''): ?>
                                            <div class="promo-media">
                                                <?php if ($isHot): ?>
                                                    <span class="promo-badge">Hot</span>
                                                <?php endif; ?>
                                                <img src="<?= h($noticeImage) ?>" alt="<?= h($noticeTitle !== '' ? $noticeTitle : 'Khuyen mai') ?>" loading="lazy" decoding="async">
                                            </div>
                                        <?php else: ?>
                                            <div class="promo-media"><i class="bi bi-gift"></i></div>
                                        <?php endif; ?>
                                        <div class="promo-text">
                                            <div class="promo-code"><?= h($noticeTitle !== '' ? $noticeTitle : 'Khuyen mai') ?></div>
                                            <!--div class="promo-desc d-none"><?= h($noticeExcerpt) ?></div-->
                                            <div class="promo-meta"><i class="bi bi-clock"></i><?= h($noticeTimeText) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-4 text-center text-secondary">
                                    <i class="bi bi-tag fs-1 text-light"></i>
                                    <p class="mt-2 mb-0" style="font-size: 0.9rem;">Chưa có chương trình khuyến mãi nào.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab Blog tin tức: bài viết blog mới nhất -->
                    <div class="tab-pane fade show active" id="blog-tab-pane" role="tabpanel" aria-labelledby="blog-tab">
                        <div class="promo-list">
                            <?php if (!empty($homeBlogPosts)): ?>
                                <?php foreach ($homeBlogPosts as $post): ?>
                                    <?php
                                    $blogTitle = trim((string)($post['title'] ?? 'Bài viết'));
                                    $blogSlug = trim((string)($post['slug'] ?? ''));
                                    $blogExcerpt = trim((string)($post['excerpt'] ?? ''));
                                    $blogCatName = trim((string)($post['category_name'] ?? ''));
                                    $blogTimeRaw = trim((string)($post['published_at'] ?? ''));
                                    $blogTimeText = $blogTimeRaw !== '' ? date('d/m/Y', strtotime($blogTimeRaw)) : '';
                                    $thumbRaw = trim((string)($post['thumbnail_url'] ?? ''));
                                    $thumbUrl = '';
                                    if ($thumbRaw !== '') {
                                        if (function_exists('to_abs_url')) {
                                            $thumbUrl = to_abs_url($thumbRaw, $baseUrl);
                                        } elseif (preg_match('~^https?://~i', $thumbRaw) || strpos($thumbRaw, 'data:image/') === 0) {
                                            $thumbUrl = $thumbRaw;
                                        } else {
                                            $thumbUrl = rtrim((string)$baseUrl, '/') . '/' . ltrim($thumbRaw, '/\\');
                                        }
                                    }
                                    $blogLink = $blogSlug !== ''
                                        ? (rtrim((string)$baseUrl, '/') . '/blog/' . rawurlencode($blogSlug))
                                        : (rtrim((string)$baseUrl, '/') . '/blog');
                                    ?>
                                    <a class="promo-item" href="<?= h($blogLink) ?>">
                                        <?php if ($thumbUrl !== ''): ?>
                                            <div class="promo-media">
                                                <img src="<?= h($thumbUrl) ?>" alt="<?= h($blogTitle !== '' ? $blogTitle : 'Blog') ?>" loading="lazy" decoding="async">
                                            </div>
                                        <?php else: ?>
                                            <div class="promo-media"><i class="bi bi-newspaper"></i></div>
                                        <?php endif; ?>
                                        <div class="promo-text">
                                            <div class="promo-code"><?= h($blogTitle !== '' ? $blogTitle : 'Blog') ?></div>
                                            <?php if ($blogTimeText !== ''): ?>
                                                <div class="promo-meta"><i class="bi bi-clock"></i><?= h($blogTimeText) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                                <div class="text-center mt-2">
                                    <a href="<?= h(rtrim((string)$baseUrl, '/') . '/blog') ?>" class="btn btn-sm btn-outline-secondary" style="font-size: 0.85rem;">Xem tất cả bài viết</a>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-secondary">
                                    <i class="bi bi-newspaper fs-1 text-light"></i>
                                    <p class="mt-2 mb-0" style="font-size: 0.9rem;">Chưa có bài viết blog nào được xuất bản.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        */ ?>
    </div>
    <?php $hasOffersSection = ($voucherTable !== '' && !empty($activeVouchers)) || !empty($productPromoCards) || !empty($homePromos); ?>
    <?php if ($hasOffersSection): ?>

        <!-- ƯU ĐÃI & KHUYẾN MÃI  -->
        <section class="_home-offers-section mt-2 mb-2" id="offers-panel">

            <!-- <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="hpromo-section-title mb-0"><i class="bi bi-gift-fill me-1"></i>ƯU ĐÃI &amp; KHUYẾN MÃI</h2>
                <a href="<?= h($baseUrl) ?>/voucher" class="hpromo-btn-view-all">Xem tất cả</a>
            </div> -->

            <div class="row _g-3 _home-offers-row">
                <!-- Mã giảm giá -->
                <?php if ($voucherTable !== '' && !empty($activeVouchers)): ?>
                    <div class="col-12 col-md-6 mb-2">
                        <div class="home-offers-col">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h3 class="home-offers-subtitle mb-0"><i class="bi bi-fire me-1"></i>Mã giảm giá</h3>
                                <!-- <a class="flash-voucher-more" href="<?= h($baseUrl) ?>/voucher">Kho voucher<i class="bi bi-chevron-right"></i></a> -->
                            </div>
                            <div class="flash-voucher-wrap" id="flashVoucherBlock">
                                <div class="flash-voucher-skeleton" id="flashVoucherSkeleton" aria-hidden="true">
                                    <?php for ($s = 0; $s < 2; $s++): ?>
                                        <div class="fv-skel-card skeleton-shimmer">
                                            <div class="fv-skel-icon"></div>
                                            <div class="fv-skel-main">
                                                <span class="fv-skel-line fv-skel-line--lg"></span>
                                                <span class="fv-skel-line fv-skel-line--md"></span>
                                                <span class="fv-skel-line fv-skel-line--sm"></span>
                                            </div>
                                            <div class="fv-skel-side">
                                                <span class="fv-skel-btn"></span>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <div class="flash-voucher-list flash-voucher-list--col" id="flashVoucherList" hidden>
                                    <div class="flash-voucher-empty" id="flashVoucherEmpty">Đang tải mã khuyến mãi...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Khuyến mãi theo sản phẩm -->
                <?php if (!empty($productPromoCards)): ?>
                    <div class="col-12 col-md-6">
                        <div class="home-offers-col">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h3 class="home-offers-subtitle mb-0"><i class="bi bi-bag-heart-fill me-1"></i>Ưu đãi theo sản phẩm</h3>
                                <!-- <a class="flash-voucher-more" href="<?= h($baseUrl) ?>/shopping">Xem thêm<i class="bi bi-chevron-right"></i></a> -->
                            </div>
                            <div class="pp-promo-list pp-promo-list--col">
                                <?php foreach ($productPromoCards as $pp): ?>
                                    <a href="<?= h($pp['link']) ?>" class="pp-promo-card pp-promo-<?= h($pp['type_key']) ?>">
                                        <div class="pp-promo-thumb is-loading">
                                            <img src="<?= h($pp['product']['thumb_url']) ?>" alt="<?= h($pp['product']['name']) ?>" loading="lazy" decoding="async" onload="this.closest('.pp-promo-thumb').classList.remove('is-loading');" onerror="this.onerror=null;this.src='<?= h($fallbackImage) ?>';this.closest('.pp-promo-thumb').classList.remove('is-loading');">
                                            <span class="pp-promo-benefit"><?= h($pp['benefit']) ?></span>
                                        </div>
                                        <div class="pp-promo-body">
                                            <span class="pp-promo-tag"><?= h($pp['type_label']) ?></span>
                                            <div class="pp-promo-name"><?= h($pp['product']['name']) ?><?= $pp['more_count'] > 0 ? ' +' . (int)$pp['more_count'] . ' SP' : '' ?></div>
                                            <div class="pp-promo-foot">
                                                <span class="pp-promo-prices">
                                                    <span class="pp-promo-price"><?= h($pp['product']['price_text']) ?></span>
                                                    <?php if (!empty($pp['product']['old_price_text'])): ?>
                                                        <span class="pp-promo-price-old"><?= h($pp['product']['old_price_text']) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="pp-promo-cta">Mua ngay<i class="bi bi-chevron-right"></i></span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Khuyến mãi / chương trình -->
                <?php if (!empty($homePromos)): ?>
                    <div class="home-offers-promos mt-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h3 class="home-offers-subtitle mb-0"><i class="bi bi-megaphone-fill me-1"></i>Chương trình đang diễn ra</h3>
                            <div class="d-flex align-items-center gap-2">
                                <button class="hpromo-nav-btn" id="hpromoNavPrev" aria-label="Trước"><i class="bi bi-chevron-left"></i></button>
                                <button class="hpromo-nav-btn" id="hpromoNavNext" aria-label="Tiếp"><i class="bi bi-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="hpromo-scroll-container" id="hpromoScrollContainer">
                            <?php foreach ($homePromos as $p):
                                $payload = json_decode($p['body'] ?? '', true);
                                $isNotx = is_array($payload) && ($payload['schema'] ?? '') === 'notx_v2';
                                $template = $isNotx ? strtolower(trim((string)($payload['template'] ?? 'tpl1'))) : 'tpl1';
                                $title = $isNotx ? ($payload['title'] ?? $p['title']) : $p['title'];
                                $subtitle = $isNotx ? trim((string)($payload['subtitle'] ?? '')) : '';
                                $content = $isNotx ? ($payload['content'] ?? '') : $p['body'];

                                $meta = json_decode($p['meta_json'] ?? '', true) ?: [];
                                $thumbType = $isNotx ? trim((string)($payload['thumb_type'] ?? '')) : '';
                                $thumbImg = $isNotx ? trim((string)($payload['thumb_image'] ?? ($meta['thumb_image'] ?? ''))) : '';
                                $heroBanner = $isNotx ? trim((string)($payload['main_banner'] ?? '')) : '';

                                // Footer banners theo template
                                $footerBanners = [];
                                if ($isNotx && is_array($payload['banners'] ?? null)) {
                                    foreach ($payload['banners'] as $b) {
                                        $b = trim((string)$b);
                                        if ($b !== '') $footerBanners[] = $b;
                                    }
                                }

                                // Fallback hero nếu không có main_banner: dùng banner đầu (tpl1/tpl4) hoặc thumb
                                if ($heroBanner === '' && in_array($template, ['tpl1', 'tpl4'], true) && !empty($footerBanners)) {
                                    $heroBanner = $footerBanners[0];
                                }
                                if ($heroBanner === '' && $thumbType !== 'icon' && $thumbImg !== '') {
                                    // không lấy thumb làm hero để giữ đúng phong cách preview
                                }

                                $typeLabel = 'Khuyến mãi';
                                if ($p['type'] === 'order') $typeLabel = 'Đơn hàng';
                                else if ($p['type'] === 'security') $typeLabel = 'Bảo mật';
                                else if ($p['type'] === 'system') $typeLabel = 'Hệ thống';

                                $createdAtTs = strtotime($p['created_at']);
                                $sendAtRaw = trim((string)($p['send_at'] ?? ''));
                                $footerTs = $sendAtRaw !== '' ? strtotime($sendAtRaw) : $createdAtTs;
                                $footerTime = $footerTs ? date('H:i d-m-Y', $footerTs) : '';

                                $link = $isNotx ? ($payload['link'] ?? $p['link']) : $p['link'];
                                if (!$link) $link = nf_build_url((int)$p['id'], (string)($p['title'] ?? ''), $baseUrl);

                                // Subtitle fallback: lấy text từ content nếu trống
                                if ($subtitle === '') {
                                    $subtitle = trim(mb_strimwidth(strip_tags((string)$content), 0, 90, '…'));
                                }

                                $isIconThumb = $thumbType === 'icon' || ($thumbImg === '' && $template === 'tpl4');
                                $isCoverLayout = in_array($template, ['tpl1', 'tpl4'], true);
                            ?>
                                <div class="hpromo-item-wrapper">
                                    <a href="<?= h($link) ?>" class="notice-preview-card hpromo-notice-card" data-template="<?= h($template) ?>">
                                        <span class="hpromo-badge"><?= h($typeLabel) ?></span>
                                        <?php if ($heroBanner !== ''): ?>
                                            <div class="notice-preview-hero">
                                                <img src="<?= h($heroBanner) ?>" alt="<?= h($title) ?>" loading="lazy">
                                            </div>
                                        <?php endif; ?>
                                        <div class="notice-preview-main">
                                            <div class="notice-preview-thumb">
                                                <?php if ($isIconThumb): ?>
                                                    <i class="bi bi-megaphone-fill"></i>
                                                <?php elseif ($thumbImg !== ''): ?>
                                                    <img src="<?= h($thumbImg) ?>" alt="thumb" loading="lazy">
                                                <?php else: ?>
                                                    <i class="bi bi-image"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="notice-preview-head">
                                                <div class="notice-preview-title"><?= h(html_entity_decode((string)$title, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></div>
                                                <?php if ($subtitle !== ''): ?>
                                                    <div class="notice-preview-subtitle"><?= h(html_entity_decode((string)$subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="notice-preview-footer <?= $isCoverLayout ? 'is-cover-layout' : '' ?>">
                                            <?php if (!$isCoverLayout && !empty($footerBanners)): ?>
                                                <div class="notice-preview-banners">
                                                    <?php foreach (array_slice($footerBanners, 0, 3) as $b): ?>
                                                        <img src="<?= h($b) ?>" alt="banner" loading="lazy">
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="notice-preview-time"><?= h($footerTime) ?></span>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </section>
    <?php endif; ?>

    <!-- Ticker -->
    <div class="fb-header-ticker mt-3 mb-3">
        <div class="fb-header-ticker-inner">
            <div class="fb-header-ticker-track">
                <div class="fb-header-ticker-item"><span>CHỐNG NỨT</span></div>
                <div class="fb-header-ticker-item"><span>CHỐNG KIỀM</span></div>
                <div class="fb-header-ticker-item"><span>CHỐNG RỈ SÉT</span></div>
                <div class="fb-header-ticker-item"><span>CHỐNG UV</span></div>
                <div class="fb-header-ticker-item"><span>CHỐNG THẤM</span></div>
                <div class="fb-header-ticker-item"><span>SƠN NHẬP MỸ - SỐ 1 HOA KỲ</span></div>
                <div class="fb-header-ticker-item"><span>SẢN PHẨM CHÍNH HÃNG</span></div>
                <div class="fb-header-ticker-item"><span>TỰ SẢN XUẤT</span></div>
                <div class="fb-header-ticker-item"><span>CUNG CẤP GIẢI PHÁP SƠN</span></div>
                <div class="fb-header-ticker-item"><span>PHÂN PHỐI TOÀN QUỐC</span></div>
                <div class="fb-header-ticker-item"><span>BẢO HÀNH LÊN ĐẾN 10 NĂM</span></div>

            </div>
        </div>
    </div>
    <!-- END Ticker -->

    <!-- Gợi ý sản phẩm -->

    <?php if (!empty($randomProducts)): ?>
        <div class="random-product-grid">
            <?php foreach ($randomProducts as $p): ?>
                <?php
                $pid = intval($p['id'] ?? 0);
                $name = h($p['product_name'] ?? 'Sản phẩm');
                $thumb = trim((string)($p['image_url'] ?? ''));
                $thumbUrl = $thumb !== '' ? to_abs_url($thumb, $baseUrl) : $fallbackImage;
                ?>
                <a class="random-product-item" href="<?= h(pm_product_url($pid, (string)($p['product_name'] ?? ''), $baseUrl)) ?>">
                    <img class="random-product-thumb" src="<?= h($thumbUrl) ?>" alt="<?= $name ?>" loading="lazy" decoding="async" onerror="this.src='<?= h($fallbackImage) ?>'">
                    <div class="random-product-name"><?= $name ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="d-none text-muted small">Chưa có sản phẩm để hiển thị.</div>
    <?php endif; ?>
    <!-- end gợi ý sản phẩm -->


    <!-- <div>
        <h3 class="segment-title">SỰ KIỆN SẮP DIỄN RA</h3>
        <div class="section-sub">Giới thiệu những sản phẩm hấp dẫn mạng lại giải pháp àn toàn cho phòng em bé.</div>
        <br>
        <a href="https://kids.paintandmore.vn" target="_blank" rel="noopener" style="position:relative;display:block;">
            <img class="" src="<?= h($baseUrl) ?>/image/cungbelonkhon.webp" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;border-radius:14px;display:block;">
            <span class="btn btn-primary shadow-sm" style="position:absolute;right:16px;bottom:16px;font-weight:600;">
                Tìm hiểu ngay <i class="bi bi-arrow-right ms-1"></i>
            </span>
        </a>
    </div> -->

    <script>
        (function() {
            const track = document.getElementById('hpromoScrollContainer');
            const btnPrev = document.getElementById('hpromoNavPrev');
            const btnNext = document.getElementById('hpromoNavNext');
            if (!track) return;
            const step = () => (track.querySelector('.hpromo-item-wrapper')?.offsetWidth || 300) + 12;
            if (btnPrev) btnPrev.addEventListener('click', () => track.scrollBy({
                left: -step(),
                behavior: 'smooth'
            }));
            if (btnNext) btnNext.addEventListener('click', () => track.scrollBy({
                left: step(),
                behavior: 'smooth'
            }));
            let isDown = false,
                startX = 0,
                startScroll = 0,
                isDragging = false,
                wasDragging = false;
            const DRAG_THRESHOLD = 8;

            track.addEventListener('mousedown', (e) => {
                if (e.button !== 0) return;
                if (e.target.closest('button, input, select, textarea')) return;
                isDown = true;
                isDragging = false;
                wasDragging = false;
                startX = e.pageX;
                startScroll = track.scrollLeft;
                e.preventDefault();
            });

            // Chặn native dragstart trên link/ảnh con
            track.addEventListener('dragstart', (e) => {
                e.preventDefault();
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                const dx = e.pageX - startX;
                if (!isDragging && Math.abs(dx) > DRAG_THRESHOLD) {
                    isDragging = true;
                    track.classList.add('is-dragging');
                    track.style.scrollSnapType = 'none';
                }
                if (isDragging) {
                    track.scrollLeft = startScroll - dx;
                    e.preventDefault();
                }
            });

            window.addEventListener('mouseup', () => {
                if (!isDown) return;
                isDown = false;
                if (isDragging) {
                    wasDragging = true;
                    track.classList.remove('is-dragging');
                    setTimeout(() => {
                        track.style.scrollSnapType = '';
                    }, 80);
                    // Reset cờ sau microtask để click handler kịp đọc
                    setTimeout(() => {
                        wasDragging = false;
                    }, 0);
                }
                isDragging = false;
            });

            // Chỉ chặn click khi vừa thực sự drag xong, không chặn click thường
            track.addEventListener('click', (e) => {
                if (wasDragging) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);

            // Shift+wheel hoặc trackpad ngang vẫn dùng được mặc định.
            // Wheel dọc cũng cho phép cuộn ngang để mượt hơn trên chuột thường.
            track.addEventListener('wheel', (e) => {
                if (e.deltaY !== 0 && Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                    track.scrollLeft += e.deltaY;
                    e.preventDefault();
                }
            }, {
                passive: false
            });
        })();
    </script>
    <!-- end khuyến mãi -->

    <!-- Review ngắn (video sản phẩm) -->
    <?php if (!empty($reviewShortItems)): ?>
        <section class="review-short-section">
            <div class="section-head">
                <div>
                    <h3 class="segment-title mb-1">REVIEW SẢN PHẨM</h3>
                    <div class="section-sub">Video ngắn sản phẩm từ chúng tôi</div>
                </div>
                <!-- Link tuỳ chỉnh tới kênh YouTube chính (nếu có) -->
                <a href="https://www.youtube.com/@paintandmoreasia" class="section-sub text-decoration-none">Xem YouTube</a>
            </div>
            <div class="review-short-list">
                <?php foreach ($reviewShortItems as $it): ?>
                    <?php
                    $pid = (int)($it['product_id'] ?? 0);
                    $pname = (string)($it['product_name'] ?? 'Sản phẩm');
                    $href = function_exists('pm_product_url')
                        ? pm_product_url($pid, $pname, $baseUrl)
                        : ($baseUrl . '/view-product?pid=' . urlencode((string)$pid));

                    $thumbUrl = (string)($it['thumb_url'] ?? $fallbackImage);
                    $title = trim((string)($it['title'] ?? ''));
                    $creator = trim((string)($it['creator_name'] ?? ''));
                    $priceText = (string)($it['price_text'] ?? 'Liên hệ');
                    $oldPriceText = (string)($it['old_price_text'] ?? '');
                    $ytUrl = trim((string)($it['youtube_url'] ?? ''));
                    $videoUrl = trim((string)($it['video_url'] ?? ''));

                    // Trích videoId YouTube để dùng facade (thumbnail + play),
                    // tránh nhúng iframe nặng ngay khi tải trang.
                    $ytId = '';
                    $embedUrl = '';
                    if ($ytUrl !== '') {
                        $u = $ytUrl;
                        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:shorts/|watch\?v=|embed/))([A-Za-z0-9_-]{6,})~', $u, $m)) {
                            $ytId = $m[1];
                            $embedUrl = 'https://www.youtube.com/embed/' . $ytId;
                        } else {
                            $embedUrl = $u;
                        }
                    }
                    ?>
                    <article class="review-short-card">
                        <div class="review-short-video">
                            <?php if ($ytId !== ''): ?>
                                <?php // Facade: chỉ tạo iframe khi người dùng bấm play (lazy) 
                                ?>
                                <button type="button" class="ytlite" data-ytid="<?= h($ytId) ?>" aria-label="Phát video review">
                                    <img class="ytlite-thumb" src="https://i.ytimg.com/vi/<?= h($ytId) ?>/hqdefault.jpg" alt="<?= h($pname) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='https://i.ytimg.com/vi/<?= h($ytId) ?>/0.jpg';">
                                    <span class="ytlite-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
                                </button>
                            <?php elseif ($embedUrl !== ''): ?>
                                <iframe src="<?= h($embedUrl) ?>" title="Review video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                            <?php elseif ($videoUrl !== ''): ?>
                                <video src="<?= h($videoUrl) ?>" muted playsinline controls preload="metadata"></video>
                            <?php endif; ?>
                            <!--div class="review-short-play">
                                <div class="review-short-play-inner"><i class="bi bi-play-fill"></i></div>
                            </div-->
                        </div>
                        <a href="<?= h($href) ?>" class="text-decoration-none">
                            <div class="review-short-meta">
                                <img class="review-short-product-thumb" src="<?= h($thumbUrl) ?>" alt="<?= h($pname) ?>" loading="lazy" decoding="async" onerror="this.src='<?= h($fallbackImage) ?>';">
                                <div>
                                    <div class="review-short-info-title"><?= h($pname) ?></div>
                                    <?php if ($creator !== ''): ?>
                                        <!-- <div class="review-short-info-creator">Đăng bởi: <?= h($creator) ?></div> -->
                                    <?php endif; ?>

                                    <div class="review-short-info-price-main">
                                        <?= h($priceText) ?>
                                        <?php if ($oldPriceText !== ''): ?>
                                            <span class="review-short-info-price-old"><?= h($oldPriceText) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="review-short-nav">
                <button type="button" class="btn btn-light rounded-circle shadow-sm review-short-prev" aria-label="Xem video trước"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-light rounded-circle shadow-sm review-short-next" aria-label="Xem video tiếp theo"><i class="bi bi-chevron-right"></i></button>
            </div>
        </section>
    <?php endif; ?>
    <!-- Tất cả sản phẩm -->
    <section class="scard sborder-0 sshadow-sm product-panel col-12 mt-3 mb-3">
        <div class="section-head">
            <div>
                <h3 class="segment-title mb-1">Tất cả sản phẩm</h3>
                <div class="section-sub">Ưu tiên sản phẩm có giá, đang giảm, và đang bật</div>
            </div>
            <a href="<?= h($baseUrl . '/shopping'); ?>" class="section-sub text-decoration-none">Xem tất cả</a>
        </div>
        <div class="scard-body">
            <div class="mb-3">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input id="searchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
                </div>
            </div>

            <div id="productGrid" class="shop-grid shopping product-list-container"></div>
            <div id="emptyProducts" class="products-empty-card" style="display:none;" role="status" aria-live="polite">
                <div class="products-empty-inner">
                    <div class="products-empty-icon" aria-hidden="true">
                        <i class="bi bi-bag-x"></i>
                    </div>
                    <h4 class="products-empty-title">Không tìm thấy sản phẩm</h4>
                    <p class="products-empty-desc">Hãy thử từ khóa khác hoặc xem tất cả sản phẩm trong cửa hàng.</p>
                    <div class="products-empty-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="emptyClearSearch">
                            <i class="bi bi-x-circle me-1"></i>Xóa tìm kiếm
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="emptyRetry">
                            <i class="bi bi-arrow-clockwise me-1"></i>Tải lại
                        </button>
                    </div>
                </div>
            </div>
            <style>
                .products-empty-card {
                    background: #fff;
                    border: 1px dashed #e2e6ec;
                    border-radius: 14px;
                    padding: 32px 20px;
                    margin: 8px 0;
                    text-align: center
                }

                .products-empty-card.is-error {
                    border-color: #f5c2c7;
                    background: #fff8f8
                }

                .products-empty-inner {
                    max-width: 420px;
                    margin: 0 auto;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 10px
                }

                .products-empty-icon {
                    width: 64px;
                    height: 64px;
                    border-radius: 50%;
                    background: #f1f4f8;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 30px;
                    color: #6c7a89
                }

                .products-empty-card.is-error .products-empty-icon {
                    background: #fde8ea;
                    color: #c0392b
                }

                .products-empty-title {
                    margin: 4px 0 0;
                    font-size: 1.05rem;
                    font-weight: 600;
                    color: #2c3e50
                }

                .products-empty-desc {
                    margin: 0;
                    color: #6c757d;
                    font-size: .9rem
                }

                .products-empty-actions {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                    justify-content: center;
                    margin-top: 6px
                }
            </style>
            <div class="text-center mt-3" id="loadMoreWrap" style="display:none;">
                <button class="btn btn-outline-primary btn-sm px-4" id="loadMoreBtn">Xem thêm</button>
            </div>
            <div id="productSentinel" style="height: 10px;"></div>
        </div>
    </section>

    <!-- Gợi ý bài viết blog -->
    <?php if (!empty($homeBlogPosts)): ?>
        <section class="home-blog-section">
            <div class="section-head">
                <div>
                    <h3 class="segment-title mb-1">BÀI VIẾT MỚI</h3>
                    <div class="section-sub">Tin tức, mẹo sơn và cập nhật từ Paintmore</div>
                </div>
                <a href="<?= h($baseUrl) ?>/blog" class="section-sub text-decoration-none">Xem tất cả</a>
            </div>
            <div class="home-blog-grid" id="homeBlogList">
                <?php foreach ($homeBlogPosts as $post): ?>
                    <?php
                    $blogUrl = $baseUrl . '/blog/' . urlencode((string)($post['slug'] ?? ''));
                    $thumbRaw = trim((string)($post['thumbnail_url'] ?? ''));
                    if ($thumbRaw !== '') {
                        if (function_exists('to_abs_url')) {
                            $thumbUrl = to_abs_url($thumbRaw, $baseUrl);
                        } elseif (preg_match('#^(https?:)?//#i', $thumbRaw)) {
                            $thumbUrl = $thumbRaw;
                        } else {
                            $thumbUrl = $baseUrl . '/' . ltrim($thumbRaw, '/');
                        }
                    } else {
                        $thumbUrl = $fallbackImage;
                    }
                    $title = (string)($post['title'] ?? '');
                    $excerpt = (string)($post['excerpt'] ?? '');
                    $catName = (string)($post['category_name'] ?? '');
                    $publishedAt = $post['published_at'] ?? null;
                    ?>
                    <article class="home-blog-card">
                        <a href="<?= h($blogUrl) ?>" class="home-blog-thumb-wrap">
                            <img class="home-blog-thumb" src="<?= h($thumbUrl) ?>" alt="<?= h($title !== '' ? $title : 'Bài viết') ?>" loading="lazy" decoding="async" onerror="this.src='<?= h($fallbackImage) ?>';">
                        </a>
                        <div class="home-blog-body">
                            <?php if ($catName !== ''): ?>
                                <div class="home-blog-cat"><?= h($catName) ?></div>
                            <?php endif; ?>
                            <h4 class="home-blog-title">
                                <a href="<?= h($blogUrl) ?>"><?= h($title !== '' ? $title : 'Bài viết') ?></a>
                            </h4>
                            <?php if ($excerpt !== ''): ?>
                                <p class="home-blog-excerpt"><?= h($excerpt) ?></p>
                            <?php endif; ?>
                            <div class="home-blog-meta">
                                <?php if ($publishedAt): ?>
                                    <span><i class="bi bi-calendar3"></i> <?= h(date('d/m/Y', strtotime((string)$publishedAt))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="review-short-nav home-blog-nav">
                <button type="button" class="btn btn-light rounded-circle shadow-sm" id="homeBlogPrev" aria-label="Bài trước"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-light rounded-circle shadow-sm" id="homeBlogNext" aria-label="Bài tiếp theo"><i class="bi bi-chevron-right"></i></button>
            </div>
        </section>
    <?php endif; ?>

    <!-- Chi nhánh (Explorer Dashboard) -->
    <section id="co-so" class="py-3">
        <style>
            .branch-dashboard {
                overflow: hidden;
            }

            /* Left Banner (Part 4) */
            .branch-ad-banner {
                position: relative;
                height: 100%;
                min-height: 400px;
                background: #0c4c29;
                overflow: hidden;
            }

            .branch-ad-banner img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                filter: brightness(0.7);
                transition: transform 10s linear;
            }

            .branch-ad-banner:hover img {
                transform: scale(1.1);
            }

            .branch-ad-content {
                position: absolute;
                inset: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 40px;
                z-index: 2;
                background: linear-gradient(to right, rgba(12, 76, 41, 0.9), transparent);
                color: #fff;
            }

            /* Master List (Part 3 inside 8) */
            .branch-master-col {
                background: #f8fafc;
                border-right: 1px solid #e2e8f0;
                height: auto;
                overflow-y: auto;
            }

            .branch-region-select {
                position: sticky;
                top: 0;
                z-index: 10;
                background: #f8fafc;
                padding: 15px;
                border-bottom: 1px solid #e2e8f0;
            }

            .br-select {
                width: 100%;
                padding: 8px 12px;
                border-radius: 10px;
                border: 1px solid #cbd5e1;
                font-weight: 700;
                font-size: 0.85rem;
                color: #1e293b;
            }

            .branch-item-card {
                padding: 15px;
                border-bottom: 1px solid #e2e8f0;
                cursor: pointer;
                transition: all 0.3s ease;
                background: #fff;
                display: flex;
                gap: 12px;
            }

            .branch-item-card:hover {
                background: #f1f5f9;
            }

            .branch-item-card.active {
                background: #fff;
                box-shadow: inset 4px 0 0 var(--theme-primary, #0c4c29);
            }

            .bic-thumb {
                width: 80px;
                height: 80px;
                border-radius: 12px;
                overflow: hidden;
                flex-shrink: 0;
                position: relative;
            }

            .bic-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .bic-status-badge {
                position: absolute;
                bottom: 5px;
                left: 5px;
                padding: 2px 6px;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(4px);
                border-radius: 4px;
                color: #fff;
                font-size: 0.55rem;
                font-weight: 800;
                display: flex;
                align-items: center;
                gap: 3px;
            }

            .bic-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #4ade80;
            }

            .status-closed .bic-status-dot {
                background: #f87171;
            }

            .bic-info {
                flex-grow: 1;
                min-width: 0;
            }

            .bic-name {
                font-size: 0.9rem;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 4px;
                text-transform: uppercase;
            }

            .bic-addr {
                font-size: 0.75rem;
                color: #64748b;
                line-height: 1.4;
                margin-bottom: 8px;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .bic-actions {
                display: flex;
                gap: 4px;
            }

            .bic-btn {
                flex: 1;
                padding: 6px 2px;
                background: #f1f5f9;
                border-radius: 6px;
                font-size: 0.6rem;
                font-weight: 700;
                color: #475569;
                text-align: center;
                text-decoration: none;
                transition: background 0.2s;
            }

            .bic-btn:hover {
                background: #e2e8f0;
                color: #0f172a;
            }

            /* Detail View (Part 9 inside 8) */
            .branch-detail-col {
                height: 650px;
                overflow-y: auto;
                background: #fff;
                padding: 0;
            }

            .bd-cover {
                width: 100%;
                height: 280px;
                position: relative;
                overflow: hidden;
            }

            .bd-cover img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .bd-header {
                padding: 30px;
                margin-top: -60px;
                position: relative;
                z-index: 5;
            }

            .bd-title-box {
                background: #fff;
                padding: 25px;
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
                border: 1px solid #f1f5f9;
            }

            .bd-name {
                font-size: 1.5rem;
                font-weight: 900;
                color: var(--theme-primary, #0c4c29);
                margin-bottom: 5px;
            }

            .bd-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
                padding: 0 30px 30px;
            }

            .bd-info-item {
                display: flex;
                gap: 15px;
                align-items: flex-start;
            }

            .bd-icon {
                width: 40px;
                height: 40px;
                background: #ecfdf5;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #16a34a;
                font-size: 1.1rem;
                flex-shrink: 0;
            }

            .bd-label {
                font-size: 0.65rem;
                font-weight: 800;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 2px;
            }

            .bd-val {
                font-size: 0.85rem;
                font-weight: 600;
                color: #1e293b;
                line-height: 1.4;
            }

            .bd-gallery-section {
                padding: 0 30px 30px;
            }

            .bd-gallery-title {
                font-size: 0.75rem;
                font-weight: 800;
                color: #94a3b8;
                text-transform: uppercase;
                margin-bottom: 15px;
                letter-spacing: 0.1em;
            }

            .bd-gallery-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .bd-gallery-item {
                border-radius: 12px;
                overflow: hidden;
                height: 80px;
                cursor: pointer;
            }

            .bd-gallery-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }

            .bd-gallery-item:hover img {
                transform: scale(1.1);
            }

            .branch-detail-col::-webkit-scrollbar {
                width: 6px;
            }

            .branch-detail-col::-webkit-scrollbar-track {
                background: #f1f5f9;
            }

            .branch-detail-col::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 10px;
            }

            #branchDetailModal .modal-content {
                background: #fff;
            }

            #branchDetailModal .btn-close {
                background-color: #fff;
                opacity: 0.8;
                transition: all 0.3s ease;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            #branchDetailModal .btn-close:hover {
                opacity: 1;
                transform: rotate(90deg);
            }

            .branch-dashboard {
                overflow: hidden;
                height: 500px;
                /* Reduced height for a more compact look */
            }

            .branch-dashboard>.row {
                height: 100%;
                margin: 0;
            }

            @media (max-width: 991px) {
                .branch-dashboard {
                    border-radius: 0;
                    border: none;
                    height: auto;
                }
            }

            .branch-master-col {
                height: 100%;
                overflow-y: auto;
                background: #f8fafc;
                border-right: 1px solid #e2e8f0;
            }

            .branch-map-col {
                height: 100%;
                background: #f1f5f9;
                position: relative;
                border-right: 1px solid #e2e8f0;
                overflow: hidden;
            }

            .branch-map-overlay {
                position: absolute;
                top: 20px;
                left: 20px;
                z-index: 5;
                pointer-events: none;
            }

            .map-badge {
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(8px);
                padding: 8px 16px;
                border-radius: 100px;
                font-weight: 700;
                font-size: 0.7rem;
                color: var(--theme-primary);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(0, 0, 0, 0.05);
                display: flex;
                align-items: center;
                gap: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            #branch-map-frame {
                width: 100%;
                height: 100%;
                border: none;
                filter: grayscale(0.2) contrast(1.1);
                transition: filter 0.3s ease;
            }

            .branch-map-col:hover #branch-map-frame {
                filter: grayscale(0) contrast(1);
            }

            .branch-detail-col {
                height: 100%;
                overflow-y: auto;
                background: #fff;
                padding: 0;
                scroll-behavior: smooth;
            }

            .bd-cover-wrap {
                position: relative;
                height: 180px;
                overflow: hidden;
            }

            .bd-cover-wrap img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.8s cubic-bezier(0.2, 0, 0.2, 1);
            }

            .branch-detail-col:hover .bd-cover-wrap img {
                transform: scale(1.05);
            }

            .bd-cover-overlay {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                padding: 40px 25px 20px 25px;
                background: linear-gradient(to top, rgba(15, 23, 42, 0.9) 0%, transparent 100%);
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
            }

            .bd-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: rgba(34, 197, 94, 0.2);
                color: #4ade80;
                padding: 4px 12px;
                border-radius: 100px;
                font-size: 0.65rem;
                font-weight: 700;
                backdrop-filter: blur(4px);
                border: 1px solid rgba(34, 197, 94, 0.3);
                width: fit-content;
            }

            .bd-name-light {
                color: #fff;
                font-size: 1.25rem;
                font-weight: 800;
                margin: 8px 0 0 0;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }

            .bd-content-inner {
                padding: 25px;
            }

            .bd-info-card {
                background: #f8fafc;
                border-radius: 16px;
                padding: 18px;
                margin-bottom: 12px;
                border: 1px solid #f1f5f9;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                gap: 15px;
                align-items: flex-start;
            }

            .bd-info-card:hover {
                background: #fff;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                border-color: var(--theme-warning);
                transform: translateY(-3px);
            }

            .bd-info-card i {
                font-size: 1.2rem;
                color: var(--theme-primary);
                background: #fff;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            }

            .bd-info-card .bd-label {
                font-size: 0.7rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                font-weight: 700;
                color: #64748b;
                margin-bottom: 2px;
            }

            .bd-info-card .bd-val {
                font-size: 0.9rem;
                font-weight: 600;
                color: #1e293b;
                line-height: 1.4;
            }

            @media (max-width: 991px) {

                .branch-master-col,
                .branch-map-col,
                .branch-detail-col {
                    height: auto;
                    border-right: none;
                }

                .branch-master-col {
                    height: 400px;
                    border-bottom: 1px solid #e2e8f0;
                }

                .branch-map-col {
                    height: 350px;
                    border-bottom: 1px solid #e2e8f0;
                }

                .branch-detail-col {
                    min-height: 400px;
                }
            }

            .no-scrollbar::-webkit-scrollbar {
                display: none;
            }
        </style>
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-4 reveal-up">
                <div>
                    <h2 class="font-display h3 fw-bold text-primary text-uppercase mb-1">HỆ THỐNG CHI NHÁNH</h2>
                    <div class="bg-warning rounded-pill" style="height:4px;width:60px;"></div>
                </div>
                <a href="tel:+84909090909" class="btn btn-outline-primary btn-sm rounded-pill fw-bold d-none d-md-inline-flex align-items-center gap-1">
                    <i class="bi bi-telephone"></i> GỌI TRỰC TIẾP
                </a>
            </div>
            <div class="branch-dashboard reveal-up">
                <div class="row g-0">
                    <!-- Column 4: List Area -->
                    <div class="col-lg-4 branch-master-col no-scrollbar">
                        <div class="branch-region-select">
                            <select class="br-select" onchange="filterBranchRegion(this.value)">
                                <option value="all">Tất cả khu vực</option>
                                <?php foreach ($branchesByRegion as $regionId => $regionData): ?>
                                    <option value="<?php echo $regionId; ?>"><?php echo h($regionData['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="master-branch-list">
                            <?php
                            $allBranches = [];
                            foreach ($branchesByRegion as $regionId => $regionData) {
                                foreach ($regionData['items'] as $item) {
                                    $item['region_id'] = $regionId;
                                    $allBranches[] = $item;
                                }
                            }
                            foreach ($allBranches as $idx => $branch):
                                $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                                if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
                                $branchJson = htmlspecialchars(json_encode($branch, $jsonFlags), ENT_QUOTES, 'UTF-8');
                                $thumb = !empty($branch['avatar']) ? h($branch['avatar']) : 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=200&q=70';
                            ?>
                                <div class="branch-item-card <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    data-region="<?php echo $branch['region_id']; ?>"
                                    data-branch="<?php echo $branchJson; ?>"
                                    onclick="selectBranchExplorer(this)">
                                    <div class="bic-thumb">
                                        <img src="<?php echo $thumb; ?>" alt="">
                                        <div class="bic-status-badge">
                                            <div class="bic-status-dot"></div> ĐANG MỞ
                                        </div>
                                    </div>
                                    <div class="bic-info">
                                        <div class="bic-name"><?php echo h($branch['branch_name']); ?></div>
                                        <div class="bic-addr text-truncate"><?php echo h($branch['address']); ?></div>
                                        <div class="bic-actions">
                                            <a href="tel:<?php echo h($branch['hotline_tel']); ?>" class="bic-btn" onclick="event.stopPropagation();">GỌI</a>
                                            <div class="bic-btn">BẢN ĐỒ</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Column 4: Map Area -->
                    <div class="col-lg-4 branch-map-col">
                        <div class="branch-map-overlay">
                            <div class="map-badge">
                                <i class="bi bi-geo-fill"></i> Vị trí chi nhánh
                            </div>
                        </div>
                        <?php
                        $firstAddr = !empty($allBranches) ? $allBranches[0]['address'] : 'Việt Nam';
                        $mapQuery = urlencode($firstAddr);
                        if (!empty($allBranches) && !empty($allBranches[0]['map_url']) && strpos($allBranches[0]['map_url'], 'query=') !== false) {
                            $parsedUrl = parse_url($allBranches[0]['map_url']);
                            if (!empty($parsedUrl['query'])) {
                                parse_str($parsedUrl['query'], $queryParams);
                                if (!empty($queryParams['query'])) {
                                    $mapQuery = urlencode($queryParams['query']);
                                }
                            }
                        }
                        $mapUrl = "https://maps.google.com/maps?q=" . $mapQuery . "&t=&z=15&ie=UTF8&iwloc=&output=embed";
                        ?>
                        <iframe id="branch-map-frame" src="<?php echo $mapUrl; ?>" allowfullscreen="" loading="lazy"></iframe>
                    </div>

                    <!-- Column 4: Info Area -->
                    <div class="col-lg-4 branch-detail-col no-scrollbar" id="branch-info-panel">
                        <?php if (!empty($allBranches)):
                            $first = $allBranches[0];
                            $cover = !empty($first['avatar']) ? h($first['avatar']) : 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=1200&q=80';
                        ?>
                            <div class="bd-cover-wrap">
                                <img id="info-cover" src="<?php echo $cover; ?>" alt="Cover">
                                <div class="bd-cover-overlay">
                                    <div class="bd-status-pill">
                                        <i class="bi bi-circle-fill" style="font-size: 0.35rem; animation: statusPulse 2s infinite;"></i> ĐANG MỞ CỬA
                                    </div>
                                    <h3 class="bd-name-light" id="info-name"><?php echo h($first['branch_name']); ?></h3>
                                </div>
                            </div>
                            <div class="bd-content-inner">
                                <div class="bd-info-card">
                                    <i class="bi bi-geo-alt"></i>
                                    <div>
                                        <div class="bd-label">Địa chỉ</div>
                                        <div class="bd-val" id="info-addr"><?php echo h($first['address']); ?></div>
                                    </div>
                                </div>
                                <div class="bd-info-card">
                                    <i class="bi bi-telephone"></i>
                                    <div>
                                        <div class="bd-label">Hotline</div>
                                        <div class="bd-val" id="info-phone"><?php echo h($first['hotline_raw']); ?></div>
                                    </div>
                                </div>
                                <div class="bd-info-card">
                                    <i class="bi bi-clock"></i>
                                    <div>
                                        <div class="bd-label">Giờ mở cửa</div>
                                        <div class="bd-val" id="info-hours"><?php echo h($first['hours_today'] ?: '08:00 - 21:00'); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function selectBranchExplorer(el) {
            let data;
            try {
                data = JSON.parse(el.getAttribute('data-branch'));
            } catch (e) {
                console.error("Error parsing branch data:", e);
                return;
            }
            document.querySelectorAll('.branch-item-card').forEach(c => c.classList.remove('active'));
            el.classList.add('active');

            // 1. Update Map Iframe
            const mapFrame = document.getElementById('branch-map-frame');
            if (mapFrame) {
                let encodedAddr = encodeURIComponent(data.address);
                if (data.map_url && data.map_url.includes('query=')) {
                    try {
                        const url = new URL(data.map_url);
                        if (url.searchParams.has('query')) {
                            encodedAddr = encodeURIComponent(url.searchParams.get('query'));
                        }
                    } catch (err) {
                        console.error('Lỗi parse map_url:', err);
                    }
                }
                mapFrame.src = `https://maps.google.com/maps?q=${encodedAddr}&t=&z=15&ie=UTF8&iwloc=&output=embed`;
            }

            // 2. Update Info Panel (Right Column)
            const panel = document.getElementById('branch-info-panel');
            if (panel) {
                panel.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
                if (document.getElementById('info-cover')) document.getElementById('info-cover').src = data.avatar || 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=1200&q=80';
                if (document.getElementById('info-name')) document.getElementById('info-name').textContent = data.branch_name;
                if (document.getElementById('info-addr')) document.getElementById('info-addr').textContent = data.address;
                if (document.getElementById('info-phone')) document.getElementById('info-phone').textContent = data.hotline_raw;
                if (document.getElementById('info-hours')) document.getElementById('info-hours').textContent = data.hours_today || '08:00 - 21:00';
            }
        }

        function filterBranchRegion(regionId) {
            const cards = document.querySelectorAll('.branch-item-card');
            cards.forEach(card => {
                if (regionId === 'all' || card.dataset.region === regionId) {
                    card.classList.remove('d-none');
                } else {
                    card.classList.add('d-none');
                }
            });
        }
    </script>
    <!-- /./ -->
</div>
<!-- /./ -->

<script>
    // ===== Helper dùng chung cho các block gợi ý danh mục bên dưới =====
    // esc: thoát HTML an toàn. pmBuildSuggestItem: dựng 1 item gợi ý sản phẩm
    // (ảnh + tên + giá), nhận classPrefix để tái dùng cho cả 2 layout
    // (cat-suggest-* và pm-hero-sug-*).
    (function() {
        const BASE_URL = '<?= h($baseUrl) ?>';
        const FALLBACK_IMG = '<?= h($fallbackImage) ?>';

        window.pmEsc = window.pmEsc || function(str) {
            return $('<div>').text(String(str || '')).html();
        };

        window.pmBuildSuggestItem = function(item, classPrefix) {
            const esc = window.pmEsc;
            const pid = Number(item && item.id ? item.id : 0);
            const name = item && item.name ? item.name : 'Sản phẩm';
            const thumb = item && item.thumb_url ? String(item.thumb_url) : FALLBACK_IMG;
            const price = item && item.price_text ? item.price_text : 'Liên hệ';
            const href = pid ?
                (window.pmBuildProductUrl ? window.pmBuildProductUrl(pid, name) : BASE_URL + '/view-product/?pid=' + encodeURIComponent(pid)) :
                '';
            const tag = pid ? 'a' : 'div';
            const hrefAttr = pid ? ` href="${esc(href)}"` : '';
            return `
            <${tag} class="${classPrefix}-item"${hrefAttr}>
                <img class="${classPrefix}-thumb" src="${esc(thumb)}" alt="${esc(name)}" loading="lazy" decoding="async" onerror="this.src='${FALLBACK_IMG}'">
                <span class="${classPrefix}-info">
                    <span class="${classPrefix}-name">${esc(name)}</span>
                    <span class="${classPrefix}-price">${esc(price)}</span>
                </span>
            </${tag}>`;
        };
    })();

    (function() {
        const BASE_URL = '<?= h($baseUrl) ?>';
        const suggestMap = <?= json_encode($catSuggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
        const $toggleList = $('#catToggleList');
        const $suggestCard = $('#catSuggestCard');
        const $suggestTitle = $('#catSuggestTitle');
        const $suggestGrid = $('#catSuggestGrid');
        const $heroWrap = $('.hero-wrap');

        let hideTimer = null;
        let renderTimer = null;

        const esc = window.pmEsc;
        const buildProductItem = (item) => window.pmBuildSuggestItem(item, 'cat-suggest');

        function showSuggestion(catId) {
            const data = suggestMap[String(catId)] || suggestMap[catId];
            if (!data) {
                if (renderTimer) {
                    clearTimeout(renderTimer);
                    renderTimer = null;
                }
                $suggestCard.removeClass('show');
                $heroWrap.removeClass('suggest-open');
                return;
            }

            if (renderTimer) {
                clearTimeout(renderTimer);
                renderTimer = null;
            }

            $suggestTitle.text('Gợi ý theo danh mục: ' + (data.name || 'Danh mục'));
            $suggestGrid.html(
                '<div class="cat-suggest-loading">' +
                '<div class="cat-suggest-skeleton"><div class="skeleton-line w-90"></div></div>' +
                '<div class="cat-suggest-skeleton"><div class="skeleton-line w-75"></div></div>' +
                '<div class="cat-suggest-skeleton"><div class="skeleton-line w-60"></div></div>' +
                '<div class="cat-suggest-skeleton"><div class="skeleton-line w-82"></div></div>' +
                '</div>'
            );
            $heroWrap.addClass('suggest-open');
            $suggestCard.addClass('show');

            renderTimer = setTimeout(() => {
                const products = Array.isArray(data.products) ? data.products : [];
                const html = products.slice(0, 8).map(buildProductItem).join('');
                if (html) {
                    $suggestGrid.html(html);
                } else {
                    $suggestGrid.html('<div class="cat-suggest-item"><span class="cat-suggest-info"><span class="cat-suggest-name">Chưa có dữ liệu sản phẩm</span></span></div>');
                }
                renderTimer = null;
            }, 0);
        }

        function clearHideTimer() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
        }

        function scheduleHide() {
            clearHideTimer();
            hideTimer = setTimeout(() => {
                if (renderTimer) {
                    clearTimeout(renderTimer);
                    renderTimer = null;
                }
                $toggleList.find('.cat-toggle-item').removeClass('active');
                $suggestCard.removeClass('show');
                $heroWrap.removeClass('suggest-open');
            }, 120);
        }

        $toggleList.on('mouseenter', '.cat-toggle-item', function() {
            clearHideTimer();
            const catId = $(this).data('cat-id');
            $toggleList.find('.cat-toggle-item').removeClass('active');
            $(this).addClass('active');
            showSuggestion(catId);
        });

        $toggleList.on('mouseleave', scheduleHide);
        $suggestCard.on('mouseenter', clearHideTimer);
        $suggestCard.on('mouseleave', scheduleHide);

        $toggleList.on('click', '.cat-toggle-item', function() {
            const catId = $(this).data('cat-id');
            const slug = $(this).data('slug');
            if (!catId) return;
            const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            const origin = isLocalhost ? '' : BASE_URL;
            if (slug) {
                window.location.href = origin + '/shopping/' + encodeURIComponent(slug);
            } else {
                window.location.href = origin + '/shopping?cat=' + encodeURIComponent(catId);
            }
        });
    })();

    /* Hover gợi ý sản phẩm cho sidebar danh mục trong pmHeroSection */
    (function() {
        const $cats = $('#pmHeroCats');
        const $flyout = $('#pmHeroFlyout');
        const $flyoutTitle = $('#pmHeroFlyoutTitle');
        const $flyoutGrid = $('#pmHeroFlyoutGrid');
        if (!$cats.length || !$flyout.length) return;

        const suggestMap = <?= json_encode($catSuggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};

        let hideTimer = null;

        const buildItem = (item) => window.pmBuildSuggestItem(item, 'pm-hero-sug');

        function showFlyout(catId, fallbackName) {
            const data = suggestMap[String(catId)] || suggestMap[catId];
            const products = data && Array.isArray(data.products) ? data.products : [];
            $flyoutTitle.text('Gợi ý: ' + ((data && data.name) || fallbackName || 'Danh mục'));
            if (!products.length) {
                $flyoutGrid.html('<div class="pm-hero-sug-empty"><i class="bi bi-box-seam"></i><br>Chưa có sản phẩm cho danh mục này</div>');
            } else {
                $flyoutGrid.html(products.slice(0, 9).map(buildItem).join(''));
            }
            $flyout.addClass('show');
        }

        function hideFlyout() {
            $flyout.removeClass('show');
        }

        function clearHide() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
        }

        function scheduleHide() {
            clearHide();
            hideTimer = setTimeout(hideFlyout, 140);
        }

        $cats.on('mouseenter', '.pm-hero-cat-link', function() {
            clearHide();
            const catId = $(this).data('cat-id');
            const name = $(this).find('.pm-hero-cat-name').text();
            if (catId) showFlyout(catId, name);
        });
        $cats.on('mouseleave', '.pm-hero-cats-list', scheduleHide);
        $flyout.on('mouseenter', clearHide);
        $flyout.on('mouseleave', scheduleHide);

        // ===== Panel KHUYẾN MÃI / TIN TỨC phủ lên banner khi hover link nav =====
        // Tái dùng cơ chế .pm-hero-flyout (đã phủ đúng vùng banner). Hover link nav
        // -> .show panel tương ứng; rời link/panel -> ẩn sau 140ms (cho phép rê chuột vào panel).
        const $navLinks = $('[data-hero-panel]');
        const $panels = $('[data-hero-panel-body]');
        let panelHideTimer = null;

        function showPanel(key) {
            $panels.each(function() {
                $(this).toggleClass('show', this.getAttribute('data-hero-panel-body') === key);
            });
        }

        function clearPanelHide() {
            if (panelHideTimer) {
                clearTimeout(panelHideTimer);
                panelHideTimer = null;
            }
        }

        function schedulePanelHide() {
            clearPanelHide();
            panelHideTimer = setTimeout(() => $panels.removeClass('show'), 140);
        }

        // Chỉ bật panel preview bằng hover trên thiết bị CÓ chuột (desktop).
        // Trên mobile/touch: tap = điều hướng thẳng tới /uu-dai, /tin-tuc (không mở panel).
        const pmHasHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (pmHasHover) {
            $navLinks.on('mouseenter', function() {
                clearPanelHide();
                showPanel(this.getAttribute('data-hero-panel'));
            });
            $navLinks.on('mouseleave', schedulePanelHide);
            $panels.on('mouseenter', clearPanelHide);
            $panels.on('mouseleave', schedulePanelHide);
        }

        // Sticky nav: thêm class is-stuck khi thanh nav dính lên đỉnh để đổ bóng rõ hơn
        const navEl = document.getElementById('pmHeroNav');
        if (navEl && 'IntersectionObserver' in window) {
            // Sentinel đặt ngay trước nav để phát hiện thời điểm nav chạm mép trên
            const sentinel = document.createElement('div');
            sentinel.style.cssText = 'position:absolute;top:0;height:1px;width:1px;';
            navEl.parentNode.insertBefore(sentinel, navEl);
            new IntersectionObserver(function(entries) {
                navEl.classList.toggle('is-stuck', !entries[0].isIntersecting);
            }, {
                rootMargin: '-9px 0px 0px 0px',
                threshold: 0
            }).observe(sentinel);
        }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        const homeContent = document.getElementById('homeContent');
        if (homeContent) {
            homeContent.classList.add('show');
        }

        // Lazy-load YouTube facade: chỉ tạo iframe khi bấm play (REVIEW SẢN PHẨM).
        // Giúp trang nhẹ + thumbnail được trình duyệt cache, không tải player thừa.
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.ytlite');
            if (!btn) return;
            const id = btn.getAttribute('data-ytid');
            if (!id) return;
            const iframe = document.createElement('iframe');
            iframe.setAttribute('src', 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0&playsinline=1');
            iframe.setAttribute('title', 'Review video');
            iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
            iframe.setAttribute('allowfullscreen', '');
            btn.replaceWith(iframe);
        });

        // Carousel ngang dùng chung: nút prev/next cuộn theo bề rộng 1 card (+ gap).
        function initCarousel(list, btnPrev, btnNext, cardSelector, fallbackWidth) {
            if (!list || !btnPrev || !btnNext) return;
            const getStep = () => {
                const firstCard = list.querySelector(cardSelector);
                if (firstCard) {
                    return firstCard.getBoundingClientRect().width + 16; // 16px = gap trong CSS
                }
                return list.clientWidth || fallbackWidth;
            };
            const scroll = (dir) => list.scrollBy({
                left: dir * getStep(),
                behavior: 'smooth'
            });
            btnPrev.addEventListener('click', () => scroll(-1));
            btnNext.addEventListener('click', () => scroll(1));
        }

        // Carousel-control cho block REVIEW SẢN PHẨM
        initCarousel(
            document.querySelector('.review-short-list'),
            document.querySelector('.review-short-prev'),
            document.querySelector('.review-short-next'),
            '.review-short-card',
            300
        );

        // Carousel-control cho block BÀI VIẾT MỚI (home blog)
        initCarousel(
            document.getElementById('homeBlogList'),
            document.getElementById('homeBlogPrev'),
            document.getElementById('homeBlogNext'),
            '.home-blog-card',
            280
        );
    });
</script>
<!-- CATEGORY LIST -->
<?php
$CATEGORY_MAP = [];
foreach ($categories as $cat) {
    $catId = intval($cat['id'] ?? 0);
    $catName = trim((string)($cat['name'] ?? ''));
    if ($catId > 0 && $catName !== '') {
        $CATEGORY_MAP[$catId] = $catName;
    }
}
?>
<script>
    // Inject CATEGORY_MAP for JS
    const CATEGORY_MAP = <?= json_encode($CATEGORY_MAP, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    (function() {
        // Flash voucher strip - reuse API & layout from voucher.php
        if (typeof jQuery === 'undefined') return;
        const $ = jQuery;
        const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
        const DETAIL_URL = '<?= h($baseUrl) ?>/view-voucher';
        const $wrap = $('#flashVoucherBlock');
        const $list = $('#flashVoucherList');
        const $empty = $('#flashVoucherEmpty');
        const $skeleton = $('#flashVoucherSkeleton');
        if (!$wrap.length || !$list.length) return;

        // Ẩn skeleton, hiện danh sách thật khi đã có dữ liệu (thành công / rỗng / lỗi)
        function revealVoucherList() {
            $skeleton.remove();
            $list.prop('hidden', false);
        }

        const esc = (value) => $('<div>').text(String(value ?? '')).html();

        function formatCurrency(n) {
            n = Number(n) || 0;
            if (window.pmVoucher && typeof window.pmVoucher.formatVNDShort === 'function') {
                return window.pmVoucher.formatVNDShort(n);
            }
            if (n >= 10000) {
                let k = Math.round(n / 1000);
                return k + 'k';
            }
            return new Intl.NumberFormat('vi-VN').format(n) + ' đ';
        }

        function formatDate(raw) {
            const txt = String(raw || '').trim();
            if (!txt) return '';
            const d = txt.split(' ')[0];
            const parts = d.split('-');
            if (parts.length !== 3) return txt;
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }

        function buildCard(row) {
            const code = String(row.code || '').trim();
            const safeCode = esc(code);
            const meta = (window.pmVoucherCard && typeof window.pmVoucherCard.computeMeta === 'function') ?
                window.pmVoucherCard.computeMeta(row || {}, {
                    categoryMap: CATEGORY_MAP
                }) :
                null;

            const tplVariant = meta ? meta.variant : 'order';
            const cardCls = 'tpl-voux-card tpl-voux-' + tplVariant;
            const iconHtml = meta ? meta.iconHtml : '<i class="bi bi-percent"></i>';
            const typeLabel = meta ? meta.typeLabel : 'Giảm đơn';
            const targetPrimary = meta ? meta.primaryTarget : 'order';

            const titleText = meta ? meta.titleText : (code ? 'Voucher ' + code : 'Voucher ưu đãi');
            const minLabel = meta ? meta.minText : '';
            // Ưu tiên lấy maxText từ meta nếu có, fallback về helper chung
            const maxLabel = (meta && meta.maxText) ?
                meta.maxText :
                (window.pmVoucher && typeof window.pmVoucher.maxText === 'function' ?
                    (window.pmVoucher.maxText(row || {}) || '') :
                    '');

            const qtyLabel = meta ? meta.qtyLabel : '';
            const categoryNames = meta ? (meta.categoryNames || []) : [];
            let categoryBadgesHtml = '';
            if (categoryNames.length) {
                const maxShow = 1;
                const total = categoryNames.length;
                const visible = categoryNames.slice(0, maxShow);
                categoryBadgesHtml = visible.map(name => '<span class="tpl-voux-badge">' + esc(name) + '</span>').join(' ');
                if (total > maxShow) {
                    const moreCount = total - maxShow;
                    categoryBadgesHtml += ' <span class="tpl-voux-badge">+' + esc(moreCount) + '</span>';
                }
            }

            // Badge phương thức thanh toán: hiển thị cho MỌI loại voucher có giới hạn
            // payment_methods (không chỉ voucher loại payment_discount). Voucher không
            // giới hạn (payment_methods rỗng) = áp dụng mọi phương thức → không gắn badge.
            function paymentLabelOf(k) {
                k = String(k || '').trim().toLowerCase();
                if (k === 'cod') return 'COD';
                if (k === 'vnpay') return 'VN PAY';
                if (k === 'momo') return 'MOMO';
                if (k === 'zalopay') return 'ZALOPAY';
                return k.toUpperCase();
            }
            let paymentLabels = String(row.payment_methods || '')
                .split(',')
                .map(k => k.trim())
                .filter(Boolean)
                .map(paymentLabelOf);
            // Fallback: nếu row không có cột nhưng meta đã tính sẵn
            if (!paymentLabels.length && meta && Array.isArray(meta.paymentLabels)) {
                paymentLabels = meta.paymentLabels;
            }
            let paymentBadgesHtml = '';
            if (paymentLabels.length) {
                paymentBadgesHtml = paymentLabels.map(l => '<span class="tpl-voux-badge tpl-voux-badge--pay"><i class="bi bi-credit-card-2-front"></i> ' + esc(l) + '</span>').join(' ');
            }

            // Dòng mô tả phụ: ưu tiên đơn tối thiểu, nếu không có thì hiển thị
            // "Áp dụng mọi đơn" để thẻ luôn có nội dung mô tả.
            const subLabel = minLabel || 'Áp dụng mọi đơn';

            const detailHref = DETAIL_URL + '?code=' + safeCode;

            // Trạng thái đã lưu: ưu tiên cờ is_saved/saved từ API, fallback meta.isSaved
            const saved = !!(row.is_saved || row.saved || (meta && meta.isSaved));
            const saveBtnHtml = saved ?
                '<button type="button" class="tpl-voux-btn is-saved" data-flash-save="' + safeCode + '" data-flash-target="' + esc(targetPrimary) + '" disabled><i class="bi bi-check-lg"></i> Đã lưu</button>' :
                '<button type="button" class="tpl-voux-btn" data-flash-save="' + safeCode + '" data-flash-target="' + esc(targetPrimary) + '">Lấy mã</button>';

            return (
                '<div class="flash-voucher-item">' +
                '  <article class="' + cardCls + '">' +
                (qtyLabel ? '<span class="tpl-voux-qty">' + esc(qtyLabel) + '</span>' : '') +
                '    <div class="tpl-voux-accent"></div>' +
                '    <div class="tpl-voux-brand">' +
                '        <span class="tpl-voux-logo-icon">' + iconHtml + '</span>' +
                '        <div class="tpl-voux-brand-name">' + esc(typeLabel) + '</div>' +
                '    </div>' +
                '    <div class="tpl-voux-main">' +
                '        <div class="tpl-voux-main-title">' + esc(titleText) + '</div>'

                +
                '        <div class="tpl-voux-sub">' + esc(subLabel) + '</div>' +
                (maxLabel ? '        <div class="tpl-voux-sub">' + esc(maxLabel) + '</div>' : '') +
                (categoryBadgesHtml ? '<div>' + categoryBadgesHtml + '</div>' : '') +
                (paymentBadgesHtml ? '<div>' + paymentBadgesHtml + '</div>' : '') +
                '    </div>' +
                '    <div class="tpl-voux-side">' +
                '        ' + saveBtnHtml +
                '        <span class="tpl-voux-tag"><a href="' + detailHref + '" class="vcp-tnc" data-voucher="' + safeCode + '">Điều kiện</a></span>' +
                '    </div>' +
                '  </article>' +
                '</div>'
            );
        }

        function loadFlashVouchers() {
            $.get(API, {
                    ajax: 'vouchers_public',
                    target: 'all'
                })
                .done((res) => {
                    revealVoucherList();
                    if (!res || !res.ok || !Array.isArray(res.data) || !res.data.length) {
                        $empty.text('Hiện chưa có mã khuyến mãi phù hợp.');
                        return;
                    }
                    // Ưu tiên mã sắp hết hạn và còn số lượng
                    const rows = res.data.slice().sort((a, b) => {
                        const aEnd = new Date(a.end_at || '').getTime() || Infinity;
                        const bEnd = new Date(b.end_at || '').getTime() || Infinity;
                        return aEnd - bEnd;
                    }).slice(0, 8);

                    const html = rows.map(buildCard).join('');
                    $list.html(html);
                })
                .fail(() => {
                    revealVoucherList();
                    $empty.text('Lỗi tải mã khuyến mãi.');
                });
        }

        // Đổi nút sang trạng thái "Đã lưu" (vô hiệu hoá, đổi giao diện)
        function markBtnSaved($btn) {
            $btn.prop('disabled', true)
                .addClass('is-saved')
                .html('<i class="bi bi-check-lg"></i> Đã lưu');
        }

        $list.on('click', '[data-flash-save]', function() {
            const $btn = $(this);
            const code = String($btn.data('flash-save') || '').trim();
            if (!code || $btn.prop('disabled')) return;

            // Khoá nút trong lúc gọi server để tránh bấm nhiều lần
            const prevHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="bi bi-arrow-repeat"></i> Đang lưu...');

            $.post(API, {
                    action: 'voucher_save',
                    code: code
                })
                .done((res) => {
                    if (!res || !res.ok) {
                        if (window.toastr && typeof toastr.warning === 'function') toastr.warning((res && res.msg) || 'Không lưu được mã');
                        // Mở lại nút để người dùng thử lại (ví dụ chưa đăng nhập)
                        $btn.prop('disabled', false).html(prevHtml);
                        return;
                    }
                    if (window.toastr && typeof toastr.success === 'function') toastr.success(res.msg || ('Đã lưu mã: ' + code));
                    markBtnSaved($btn);
                })
                .fail(() => {
                    if (window.toastr && typeof toastr.error === 'function') toastr.error('Lỗi kết nối server');
                    $btn.prop('disabled', false).html(prevHtml);
                });
        });

        // Helper pmVoucherCard/pmVoucher được nạp ở foot.php (sau home_user.php),
        // nên chờ chúng sẵn sàng rồi mới render để thẻ voucher có đúng tiêu đề/loại
        // thay vì rơi vào fallback "Voucher <CODE>".
        function startFlashVouchers(tries) {
            tries = tries || 0;
            const ready = window.pmVoucherCard && typeof window.pmVoucherCard.computeMeta === 'function';
            if (ready || tries >= 40) { // tối đa ~2s, sau đó vẫn render (có fallback)
                loadFlashVouchers();
                return;
            }
            setTimeout(() => startFlashVouchers(tries + 1), 50);
        }
        $(startFlashVouchers);
    })();
</script>

<script>
    (function() {
        if (typeof jQuery === 'undefined') return;
        const $ = jQuery;

        const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
        const BASE_URL = '<?= h($baseUrl) ?>';
        const FALLBACK_IMG = '<?= h($fallbackImage) ?>';
        const FAVORITE_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/favorite.php';

        // Cấu hình số lượng sản phẩm trên mỗi hàng
        const PRODUCT_LIMIT_PC = 6;
        const PRODUCT_LIMIT_MOBILE = 2;
        const pageSize = 24;

        let page = 0;
        let hasMore = true;
        let loading = false;

        const $grid = $('#productGrid');
        const $search = $('#searchBox');
        const $loadMore = $('#loadMoreWrap');
        const $empty = $('#emptyProducts');

        if ($grid.length) {
            $grid[0].style.setProperty('--pc-cols', PRODUCT_LIMIT_PC);
            $grid[0].style.setProperty('--mobile-cols', PRODUCT_LIMIT_MOBILE);
        }

        function esc(str) {
            if (str === null || typeof str === 'undefined') return '';
            return String(str).replace(/[&<>"']/g, function(s) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [s];
            });
        }

        function toAbs(url) {
            if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
            return url && url.indexOf('http') !== 0 ? BASE_URL + '/' + url.replace(/^\/+/, '') : url;
        }

        function fmtPrice(n) {
            return n.toLocaleString('vi-VN') + ' ₫';
        }

        function highlightPromoText(text) {
            return text.replace(/(\d+[\s%]*[kK%]*)/g, '<span class="promo-highlight">$1</span>');
        }


        function cardTemplate(p) {
            const pid = Number(p.id || 0);
            const href = (window.pmBuildProductUrl ?
                window.pmBuildProductUrl(pid, p.product_name || p.name || '') :
                (BASE_URL + '/view-product/?pid=' + encodeURIComponent(pid)));

            const img = p.thumb ? toAbs(p.thumb) : FALLBACK_IMG;

            // Gallery media: gom ảnh (bỏ video), chuẩn hoá tuyệt đối, loại trùng
            let gallery = [];
            try {
                if (p.media_gallery) {
                    gallery = typeof p.media_gallery === 'string' ? JSON.parse(p.media_gallery) : p.media_gallery;
                }
            } catch (e) {
                console.error('Lỗi parse media_gallery sản phẩm:', e);
            }
            if (!Array.isArray(gallery)) gallery = [];
            gallery = gallery
                .map(item => {
                    if (typeof item === 'string') return item.trim();
                    if (item && typeof item === 'object') {
                        const type = String(item.type || 'image').toLowerCase();
                        if (type === 'video') return '';
                        return String(item.url || '').trim();
                    }
                    return '';
                })
                .filter(url => url !== '')
                .map(url => toAbs(url));
            gallery = [...new Set(gallery)];

            const absImg = toAbs(img);
            const mediaCount = gallery.filter(u => u !== absImg).length;
            if (absImg && !gallery.includes(absImg)) gallery.unshift(absImg);

            let galleryHtml = '';
            if (mediaCount >= 1) {
                galleryHtml = `
                    <div class="gallery-carousel-container">
                        <div class="gallery-carousel-slides">
                            ${gallery.map((gImg, idx) => `
                                <img class="gallery-slide-img ${idx === 0 ? 'active' : ''}" src="${esc(gImg)}" loading="lazy" decoding="async" onerror="this.src='${esc(FALLBACK_IMG)}'">
                            `).join('')}
                        </div>
                        <button type="button" class="gallery-prev-btn" aria-label="Previous image"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="gallery-next-btn" aria-label="Next image"><i class="bi bi-chevron-right"></i></button>
                        <div class="gallery-dots">
                            ${gallery.map((_, idx) => `<span class="gdot ${idx === 0 ? 'active' : ''}"></span>`).join('')}
                        </div>
                    </div>
                `;
            }

            const safeName = esc(p.product_name || p.name || 'Sản phẩm');
            const priceMin = Number(p.gia_min ?? 0);
            let basePriceLabel = String(p.price_text || '').trim();
            if (!basePriceLabel) basePriceLabel = priceMin > 0 ? fmtPrice(priceMin) : 'Liên hệ';

            const safePrice = esc(basePriceLabel);
            const oldPrice = p.old_price_text ? esc(String(p.old_price_text)) : '';
            const newPrice = p.new_price_text ? esc(String(p.new_price_text)) : '';

            const ratingCount = Number(p.rating_count || 0);
            const ratingVal = Number.isFinite(Number(p.rating_avg)) ? Number(p.rating_avg) : (Number(p.rating_value) || 0);

            const soldCount = Number(p.sold_count || p.sold || p.sold_qty || 0);
            const soldTextRaw = String(p.sold_text || '').trim();
            const fmtSoldCount = (n) => {
                const num = Number(n);
                if (!Number.isFinite(num) || num < 0) return '0';
                if (num >= 1000) {
                    const k = Math.floor(num / 100) / 10;
                    return String(k).replace(/\.0$/, '') + 'k+';
                }
                return String(num);
            };
            const soldText = soldTextRaw ? soldTextRaw : ('Đã bán ' + fmtSoldCount(soldCount));

            const promoSubtitle = p.promo_subtitle ? String(p.promo_subtitle) : '';
            const promoHighlights = Array.isArray(p.promo_highlights) ? p.promo_highlights : [];

            const discount = Number(p.discount_percent || 0);
            const voucherBadge = p.voucher_badge ? String(p.voucher_badge) : '';
            const hasShip = !!p.has_ship_demo;
            const shipLabel = p.ship_label ? String(p.ship_label) : '';
            const shipMinSubtotal = Number(p.ship_min_subtotal || 0);

            // Badge giảm giá: ưu tiên voucher text — định dạng "Giảm 11%"
            let discountText = '';
            if (voucherBadge) {
                let raw = voucherBadge.toString().trim();
                let label = raw;
                const m = raw.match(/^Giảm\s+(\d+)\s*%?$/i);
                if (m) label = 'Giảm ' + m[1] + '%';
                else if (/^\d+\s*[kK]$/.test(raw)) {
                    const num = raw.replace(/[^0-9]/g, '');
                    label = num ? 'Giảm ' + num + 'K' : raw;
                } else if (/^Giảm\s+\d+[kK]?/i.test(raw)) label = raw;
                else if (/^\d+$/.test(raw)) label = 'Giảm ' + raw + '%';
                else if (/^-/.test(raw)) label = 'Giảm ' + raw.replace(/^-\s*/, '');
                else label = raw;
                discountText = label;
            } else if (discount > 0) {
                discountText = (discount >= 100) ? 'Free' : ('Giảm ' + discount + '%');
            }

            const discountBadgeHtml = discountText ?
                `<div class="badge-discount badge-discount-v2"><i class="bi bi-tag-fill"></i><span>${esc(discountText)}</span></div>` :
                '';

            // Badge voucher/freeship — kèm điều kiện đơn tối thiểu
            let voucherHtml = '';
            if (hasShip) {
                const raw = (shipLabel || '').toString().trim();
                const isFree = (raw === '100%' || raw === '100' || raw === '');
                // raw đã kèm đơn vị từ server (vd "10%", "20K"); chỉ thêm dấu "-" cho rõ là giảm
                let label = isFree ? 'Freeship' : ('Ship -' + raw);
                if (shipMinSubtotal > 0) {
                    const minShort = shipMinSubtotal >= 1000 ?
                        (shipMinSubtotal / 1000).toString().replace(/\.0$/, '') + 'K' :
                        String(shipMinSubtotal);
                    label += ' đơn ' + minShort;
                }
                voucherHtml = `<div class="badge-voucher" title="${esc(label)}"><i class="bi bi-truck"></i><span class="bv-text">${esc(label)}</span></div>`;
            }

            // Danh mục: mặc định ẩn (đồng bộ layout shopping) — không render badge category.

            let promoLine = '';
            if (promoHighlights.length > 0) promoLine = String(promoHighlights.find(t => String(t || '').trim()) || '').trim();
            if (!promoLine && promoSubtitle) promoLine = String(promoSubtitle).trim();
            const promoHtml = promoLine ? `<div class="badge-promo">${highlightPromoText(promoLine)}</div>` : '';

            const priceHtml = (oldPrice && newPrice) ?
                `<span class="sp-price">${newPrice}</span><span class="sp-old-price">${oldPrice}</span>` :
                `<span class="sp-price">${safePrice}</span>`;

            const safeRating = Math.max(0, Math.min(5, ratingVal || 0));
            const starHtml = `<i class="bi bi-star-fill is-on"></i>`;
            const ratingText = safeRating.toFixed(1) + ' (' + ratingCount + ')';
            const ratingHtml = `<div class="sp-rating"><span class="sp-stars">${starHtml}</span><span>${esc(ratingText)}</span></div>`;

            return `
            <a href="${esc(href)}" class="shopping-product-card shadow-sm">
                <div class="shopping-img-wrapper">
                    <img class="main-prod-img" src="${esc(img)}" alt="${safeName}" loading="lazy" decoding="async" onerror="this.src='${esc(FALLBACK_IMG)}'">
                    ${galleryHtml}
                    ${discountBadgeHtml}
                    ${voucherHtml}
                    <button type="button" class="btn-favorite-card ${p.liked ? 'active' : ''}" data-pid="${pid}" title="${p.liked ? 'Bỏ yêu thích' : 'Yêu thích'}">
                        <i class="bi bi-heart"></i>
                    </button>
                </div>

                <div class="shopping-product-content">
                    <div class="shopping-product-title">${safeName}</div>
                    ${promoHtml}

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">${priceHtml}</div>

                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-1">
                        ${ratingHtml}
                        <div class="product-card-add-cart-icon product-card-add-cart" data-pid="${pid}" data-name="${safeName}" title="Thêm vào giỏ hàng">
                            <i class="bi bi-cart-plus"></i>
                        </div>
                        <div class="sd-none sp-sold d-none">${esc(soldText)}</div>

                    </div>
                </div>

                <div class="add-cart-btn">
                    <span type="button" class="product-card-add-cart" data-pid="${pid}" data-name="${safeName}">Thêm vào giỏ hàng</span>
                </div>
            </a>
        `;
        }

        function skeletonCardTemplate() {
            return `
            <article class="shopping-product-card is-skeleton" aria-hidden="true">
                <div class="shopping-img-wrapper">
                    <div class="shopping-skeleton" style="position:absolute;inset:0;"></div>
                </div>
                <div class="shopping-product-content">
                    <div class="shopping-skeleton" style="height:10px;width:92%;margin-bottom:8px;"></div>
                    <div class="shopping-skeleton" style="height:10px;width:72%;margin-bottom:10px;"></div>
                    <div class="shopping-skeleton" style="height:12px;width:60%;"></div>
                </div>
            </article>
        `;
        }

        function renderSkeleton(count, replace) {
            const safeCount = Math.max(1, Number(count) || 1);
            const html = new Array(safeCount).fill('').map(() => skeletonCardTemplate()).join('');
            if (replace) $grid.html(html);
            else $grid.append(html);
        }

        function clearSkeleton() {
            $grid.find('.shopping-product-card.is-skeleton').remove();
        }

        let searchTerm = '';

        function fetchProducts(reset) {
            if (loading) return;
            if (!hasMore && !reset) return;

            if (reset) {
                page = 0;
                hasMore = true;
                renderSkeleton(Math.min(pageSize, 8), true);
            } else {
                renderSkeleton(4, false);
            }

            loading = true;
            hideEmpty();

            const start = page * pageSize;
            const params = {
                ajax: 'products',
                draw: 1,
                start: start,
                length: pageSize,
                'search[value]': searchTerm,
                custom_sort: 'newest'
            };

            $.get(API, params, function(res) {
                clearSkeleton();
                let list = [];
                let total = 0;
                let hadValidShape = false;

                if (Array.isArray(res)) {
                    list = res;
                    total = list.length;
                    hadValidShape = true;
                } else if (res && typeof res === 'object') {
                    if (Array.isArray(res.data)) {
                        list = res.data;
                        total = (typeof res.recordsFiltered !== 'undefined') ? (res.recordsFiltered || list.length) : list.length;
                        hadValidShape = true;
                    } else if (res.ok === true) {
                        list = [];
                        total = 0;
                        hadValidShape = true;
                    }
                }

                list.forEach(p => $grid.append(cardTemplate(p)));

                const gridCount = $grid.find('.shopping-product-card').not('.is-skeleton').length;
                if (reset && gridCount === 0) {
                    showEmpty(hadValidShape ? 'empty' : 'error');
                    $loadMore.hide();
                } else {
                    hideEmpty();
                }

                page += 1;
                hasMore = total ? (start + list.length < total) : (list.length === pageSize);
                $loadMore.toggle(hasMore && gridCount > 0);
            }).fail(function() {
                clearSkeleton();
                const gridCount = $grid.find('.shopping-product-card').not('.is-skeleton').length;
                if (gridCount === 0) {
                    showEmpty('error');
                    $loadMore.hide();
                }
            }).always(function() {
                clearSkeleton();
                loading = false;
            });
        }

        function showEmpty(kind) {
            const isError = kind === 'error';
            const $icon = $empty.find('.products-empty-icon i');
            const $title = $empty.find('.products-empty-title');
            const $desc = $empty.find('.products-empty-desc');
            const $clear = $empty.find('#emptyClearSearch');
            const $retry = $empty.find('#emptyRetry');

            $empty.toggleClass('is-error', isError);
            if (isError) {
                $icon.attr('class', 'bi bi-wifi-off');
                $title.text('Không tải được sản phẩm');
                $desc.text('Đã có lỗi khi kết nối tới máy chủ. Vui lòng thử lại.');
                $clear.hide();
                $retry.show();
            } else if (searchTerm) {
                $icon.attr('class', 'bi bi-search');
                $title.text('Không tìm thấy sản phẩm phù hợp');
                $desc.text('Không có kết quả cho "' + searchTerm + '". Hãy thử từ khóa khác.');
                $clear.show();
                $retry.hide();
            } else {
                $icon.attr('class', 'bi bi-bag-x');
                $title.text('Chưa có sản phẩm nào');
                $desc.text('Hiện tại cửa hàng chưa có sản phẩm để hiển thị. Vui lòng quay lại sau.');
                $clear.hide();
                $retry.show();
            }
            $empty.show();
        }

        function hideEmpty() {
            $empty.hide();
        }

        $empty.on('click', '#emptyClearSearch', function() {
            searchTerm = '';
            $search.val('');
            fetchProducts(true);
        });
        $empty.on('click', '#emptyRetry', function() {
            fetchProducts(true);
        });

        $search.on('keyup', function() {
            searchTerm = String($(this).val() || '').trim();
            fetchProducts(true);
        });

        $('#loadMoreBtn').on('click', function() {
            fetchProducts(false);
        });

        // Infinite Scroll: Tự động load khi cuộn xuống
        if ('IntersectionObserver' in window) {
            const sentinel = document.getElementById('productSentinel');
            if (sentinel) {
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting && !loading && hasMore) {
                        fetchProducts(false);
                    }
                }, {
                    rootMargin: '120px'
                });
                observer.observe(sentinel);
            }
        }

        $grid.on('click', '.product-card-add-cart', function(ev) {
            ev.preventDefault();
            ev.stopPropagation();
            const $btn = $(this);
            const pid = Number($btn.data('pid') || 0);
            const name = String($btn.data('name') || '').trim();

            const $card = $btn.closest('.shopping-product-card');
            const $img = $card.find('.shopping-img-wrapper img');

            if (window.addToCartFromCard) {
                window.addToCartFromCard(pid, name, $img[0]);
            }
        });

        $grid.on('click', '.btn-favorite-card', function(ev) {
            ev.preventDefault();
            ev.stopPropagation();
            const $btn = $(this);
            const pid = Number($btn.data('pid') || 0);
            if (!pid) return;
            $btn.prop('disabled', true);
            $.post(FAVORITE_API, {
                action: 'toggle',
                pid: pid
            }, function(res) {
                if (res && res.ok) {
                    const liked = !!res.liked;
                    $btn.toggleClass('active', liked);
                    $btn.attr('title', liked ? 'Bỏ yêu thích' : 'Yêu thích');
                    if (window.toastr) {
                        if (liked) toastr.success('Đã thêm vào yêu thích');
                        else toastr.info('Đã bỏ yêu thích');
                    }
                } else if (window.toastr) {
                    toastr.error((res && res.msg) || 'Thao tác thất bại');
                }
            }).fail(function() {
                if (window.toastr) toastr.error('Không thể kết nối đến máy chủ');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });

        // ===== Gallery carousel: hover hiện ảnh media + nút prev/next =====
        function goToSlide($container, idx) {
            const $slides = $container.find('.gallery-slide-img');
            const $dots = $container.find('.gallery-dots .gdot');
            const n = $slides.length;
            if (n <= 1) return;
            idx = ((idx % n) + n) % n;
            $slides.removeClass('active').eq(idx).addClass('active');
            $dots.removeClass('active').eq(idx).addClass('active');
        }

        function currentIdx($container) {
            const i = $container.find('.gallery-slide-img.active').index();
            return i < 0 ? 0 : i;
        }

        $grid.on('click', '.gallery-prev-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $container = $(this).closest('.gallery-carousel-container');
            goToSlide($container, currentIdx($container) - 1);
        });

        $grid.on('click', '.gallery-next-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $container = $(this).closest('.gallery-carousel-container');
            goToSlide($container, currentIdx($container) + 1);
        });

        // Hover vào -> hiện ảnh media đầu tiên; rời chuột -> về ảnh đại diện
        $grid.on('mouseenter', '.shopping-img-wrapper', function() {
            const $container = $(this).find('.gallery-carousel-container');
            if ($container.find('.gallery-slide-img').length > 1) goToSlide($container, 1);
        });
        $grid.on('mouseleave', '.shopping-img-wrapper', function() {
            const $container = $(this).find('.gallery-carousel-container');
            if ($container.find('.gallery-slide-img').length > 1) goToSlide($container, 0);
        });

        // Khởi tạo Carousel trang chủ tự chạy
        const homeCarouselEl = document.getElementById('homeCarousel');
        if (homeCarouselEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Carousel(homeCarouselEl, {
                interval: 4000,
                ride: 'carousel'
            });
        }

        refreshCartBadge();
        fetchProducts(true);

        // 3-Column Banner Mouse Drag Scrolling
        (function() {
            const track = document.querySelector('.promotion-banner-track');
            if (!track) return;

            let isDown = false;
            let startX;
            let scrollLeft;

            track.addEventListener('mousedown', (e) => {
                isDown = true;
                track.classList.add('active');
                startX = e.pageX - track.offsetLeft;
                scrollLeft = track.scrollLeft;
            });

            track.addEventListener('mouseleave', () => {
                isDown = false;
                track.classList.remove('active');
            });

            track.addEventListener('mouseup', () => {
                isDown = false;
                track.classList.remove('active');
            });

            track.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - track.offsetLeft;
                const walk = (x - startX) * 2;
                track.scrollLeft = scrollLeft - walk;
            });
        })();
    })();
</script>