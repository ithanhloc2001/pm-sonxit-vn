<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/payment.php';
require_once __DIR__ . '/shipping.php';
require_once __DIR__ . '/voucher.php';
require_once __DIR__ . '/promotion_helper.php';
?>
<?php
// Các hàm tiện ích và xử lý AJAX cho giỏ hàng, thanh toán, vận chuyển, voucher, profile, v.v... được đặt trong file này để dễ quản lý
$productTable             = 'ecommerce_product';
// variantTable có thể không tồn tại nếu shop không dùng biến thể, nên sẽ kiểm tra tồn tại trước khi dùng
$variantTable             = 'ecommerce_product_variants';
// categoryTable có thể không tồn tại nếu shop không dùng chuyên mục, nên sẽ kiểm tra tồn tại trước khi dùng
$categoryTable            = 'ecommerce_category';
// bảng hệ thống sơn chi tiết cho sản phẩm
$coatingTable             = 'ecommerce_product_coating_system';
// bảng dữ liệu thi công cho sản phẩm
$constructionTable        = 'ecommerce_product_construction';
// bảng nhóm phân loại hàng
$groupTable               = 'ecommerce_product_variant_groups';
// Kiểm tra sự tồn tại của bảng sản phẩm chính
$productCols              = $productTable !== '' ? listColumns($ithanhloc, $productTable) : [];
// Kiểm tra sự tồn tại của  bảng phân loại sản phẩm (variant)
$variantCols              = $variantTable !== '' ? listColumns($ithanhloc, $variantTable) : [];
// Kiểm tra sự tồn tại của  bảng danh mục sản phẩm
$categoryCols             = $categoryTable !== '' ? listColumns($ithanhloc, $categoryTable) : [];
// Kiểm tra sự tồn tại của bảng hệ thống sơn chi tiết
$coatingCols              = $coatingTable !== '' ? listColumns($ithanhloc, $coatingTable) : [];
// Kiểm tra sự tồn tại của bảng dữ liệu thi công
$constructionCols         = $constructionTable !== '' ? listColumns($ithanhloc, $constructionTable) : [];
// Xác định biểu thức điều kiện để lọc sản phẩm đang hoạt động (active) dựa trên các cột có sẵn (status hoặc is_active)
$productActiveExpr        = '';
if (hasCol($productCols, 'status')) {
    $productActiveExpr    = "(p.status = TRUE OR p.status = 1 OR p.status = '1' OR LOWER(p.status) = 'true')";
} elseif (hasCol($productCols, 'is_active')) {
    $productActiveExpr    = 'p.is_active = 1';
}

// Kiểm tra sự tồn tại của cột trạng thái trong bảng phân loại (variant) để xây dựng biểu thức lọc phân loại đang hoạt động nếu có
$categoryActiveExpr                 = '';
$categoryActiveExistsExpr           = '';
if ($categoryTable !== '' && $categoryCols) {
    if (hasCol($categoryCols, 'status')) {
        $categoryActiveExpr         = "(c.status = TRUE OR c.status = 1 OR c.status = '1' OR LOWER(c.status) = 'true')";
        $categoryActiveExistsExpr   = "EXISTS (SELECT 1 FROM `{$categoryTable}` cat WHERE cat.id = p.category_id AND (cat.status = TRUE OR cat.status = 1 OR cat.status = '1' OR LOWER(cat.status) = 'true'))";
    } elseif (hasCol($categoryCols, 'is_active')) {
        $categoryActiveExpr         = 'c.is_active = 1';
        $categoryActiveExistsExpr   = "EXISTS (SELECT 1 FROM `{$categoryTable}` cat WHERE cat.id = p.category_id AND cat.is_active = 1)";
    }
}

// Điều kiện lọc phân loại (variant) đang hoạt động nếu bảng có cột trạng thái
$variantActiveWhereExtra = '';
if ($variantCols && hasCol($variantCols, 'status')) {
    $variantActiveWhereExtra = " AND (status = 1 OR status = '1' OR LOWER(status) = 'true')";
} elseif ($variantCols && hasCol($variantCols, 'is_active')) {
    $variantActiveWhereExtra = ' AND is_active = 1';
}

// === CSRF PROTECTION FOR STATE-CHANGING ACTIONS ===
$stateChangingActions = [
    'cart_add', 'cart_remove', 'cart_update', 'cart_clear', 
    'cart_set_single', 'cart_add_free', 'cart_add_combo', 
    'checkout', 'order_create', 'cart_update_variant', 'cart_change_variant'
];
$action = (string)($_REQUEST['action'] ?? '');
if ($action !== '' && in_array($action, $stateChangingActions, true)) {
    app_verify_csrf();
}

// === GIỚI HẠN BẢO MẬT GIỎ HÀNG ===
define('CART_MAX_QTY_PER_ITEM', 100);   // V2: Số lượng tối đa 1 loại sản phẩm
define('CART_MAX_ITEMS', 30);            // V10: Số dòng tối đa trong giỏ hàng


// ** LẤY GIỎ HÀNG HIỆN TẠI TỪ SESSION (SHOP_CART) ***

// ** ĐỒNG BỘ SẢN PHẨM TẶNG MUA X TẶNG Y VÀO SESSION CART **
function syncBxgyGiftsToSessionCart(mysqli $ithanhloc): array {
    $cart = ecommerce_cart_get();
    if (!$cart) {
        return [];
    }

    $baseItems = [];
    $otherItems = [];

    foreach ($cart as $it) {
        $isGift = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0);
        $isBxgyGift = $isGift && !empty($it['bxgy_promo_id']);
        if ($isBxgyGift) {
            continue;
        }
        if ($isGift) {

            $otherItems[] = $it;
        } else {
            $baseItems[] = $it;
        }
    }

    if (!$baseItems) {
        $newCart = $otherItems;
        ecommerce_cart_set($newCart);
        return $newCart;
    }

    $promos = bxgy_get_active_promos($ithanhloc);
    if (!$promos) {
        $newCart = array_merge($baseItems, $otherItems);
        ecommerce_cart_set($newCart);
        return $newCart;
    }

    $bxgyRes = bxgy_compute_discount_for_items($baseItems, $promos);
    $gifts = isset($bxgyRes['gifts']) && is_array($bxgyRes['gifts']) ? $bxgyRes['gifts'] : [];

    $bxgyGiftItems = [];
    if ($gifts) {
        foreach ($gifts as $g) {
            if (!is_array($g)) continue;
            $giftPid = (int)($g['product_id'] ?? 0);
            $qtyFree = (int)($g['qty_free'] ?? 0);
            $promoId = (int)($g['promo_id'] ?? 0);
            $promoName = (string)($g['promo_name'] ?? '');
            $giftVariantId = (int)($g['gift_variant_id'] ?? 0);
            if ($giftPid <= 0 || $qtyFree <= 0 || $promoId <= 0) continue;

            // Tìm sản phẩm gốc trong giỏ để kế thừa thông tin (tên, biến thể, ảnh...)
            $base = null;
            $baseKey = (string)($g['base_item_key'] ?? '');
            if ($baseKey !== '') {
                foreach ($baseItems as $it) {
                    if ((string)($it['key'] ?? '') === $baseKey) {
                        $base = $it;
                        break;
                    }
                }
            }
            if ($base === null) {
                foreach ($baseItems as $it) {
                    $pidIt = (int)($it['product_id'] ?? $it['id'] ?? 0);
                    if ($pidIt !== $giftPid) continue;
                    if ($giftVariantId > 0) {
                        $variantIt = (int)($it['variant_id'] ?? 0);
                        if ($variantIt !== $giftVariantId) continue;
                    }
                    $base = $it;
                    $baseKey = (string)($it['key'] ?? '');
                    break;
                }
            }

            // Nếu không có trong baseItems (tặng sản phẩm khác hoặc khác phân loại), build từ DB với biến thể được cấu hình (nếu có)
            $giftItem = null;
            if ($base !== null && $giftVariantId === (int)($base['variant_id'] ?? 0)) {
                $giftItem = $base;
            } else {
                $giftItem = ecommerce_build_cart_item($ithanhloc, $giftPid, $giftVariantId, $qtyFree, '');
            }
            if (!$giftItem) {
                $giftItem = $base;
            }
            if (!$giftItem) continue;
            $giftItem['qty']              = $qtyFree;
            $giftItem['price']            = 0.0;
            $giftItem['is_gift']          = 1;
            $giftItem['bxgy_promo_id']    = $promoId;
            $giftItem['bxgy_promo_name']  = $promoName;
            $rawKey                       = (string)($giftItem['key'] ?? ($giftPid . ':0:'));
            $baseKeyToUse                 = ($baseKey !== '') ? $baseKey : $rawKey;
            $giftItem['key']              = $baseKeyToUse . '|BXGY-' . $promoId . '-' . $giftVariantId;

            $bxgyGiftItems[] = $giftItem;
        }
    }

    $newCart = array_merge($baseItems, $otherItems, $bxgyGiftItems);
    ecommerce_cart_set($newCart);
    return $newCart;
}

// ** ĐỒNG BỘ QUÀ TẶNG HÓA ĐƠN (PROMO GIFT) TỰ ĐỘNG THEO NGƯỠNG SUBTOTAL **
// Chỉ tự động thêm quà khi:
//  - Có đúng 1 sản phẩm quà cấu hình trong các chiến dịch gift đang hoạt động
//  - Hoá đơn (không tính các dòng quà) đạt ngưỡng threshold của sản phẩm đó
//  - Trong giỏ chưa có dòng quà tương ứng
// Các trường hợp nhiều sản phẩm quà (khách được chọn) vẫn giữ nguyên behavior thủ công.
function syncInvoiceGiftToSessionCart(mysqli $ithanhloc): array {
    global $variantTable, $variantActiveWhereExtra;

    $cart = ecommerce_cart_get();
    if (!$cart) {
        return [];
    }

    // Tính subtotal chỉ tính các dòng không phải quà tặng
    $subtotal = 0.0;
    foreach ($cart as $it) {
        $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0 && (int)($it['qty'] ?? 0) === 1);
        if ($isGiftItem) continue;
        $subtotal += (float)($it['price'] ?? 0) * (int)($it['qty'] ?? 0);
    }

    // Lấy cấu hình các chiến dịch gift đang hoạt động và map threshold cho từng sản phẩm quà
    $giftThresholdByProductId = [];
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
    } catch (Throwable $e) {
        $hasPromoTbl = false;
    }
    if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
        $nowPromo = nowStr();
        $stmtPg = $ithanhloc->prepare("SELECT promo_type, config_json FROM ecommerce_product_promo WHERE promo_type = 'gift' AND is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)");
        if ($stmtPg) {
            $stmtPg->bind_param('ss', $nowPromo, $nowPromo);
            $stmtPg->execute();
            $resPg = $stmtPg->get_result();
            while ($rowPg = $resPg->fetch_assoc()) {
                $cfg = json_decode((string)($rowPg['config_json'] ?? ''), true);
                if (!is_array($cfg)) continue;
                $thresholdGift = (int)($cfg['threshold_amount'] ?? 0);
                if (empty($cfg['gift_product_ids']) || !is_array($cfg['gift_product_ids'])) continue;
                foreach ($cfg['gift_product_ids'] as $gid) {
                    $gid = (int)$gid;
                    if ($gid <= 0) continue;
                    if (!isset($giftThresholdByProductId[$gid]) || ($thresholdGift > 0 && $thresholdGift < $giftThresholdByProductId[$gid])) {
                        $giftThresholdByProductId[$gid] = $thresholdGift;
                    }
                }
            }
            $stmtPg->close();
        }
    }

    if (!$giftThresholdByProductId) {
        // Không có chiến dịch quà tặng đang hoạt động
        return $cart;
    }

    $giftPids = array_keys($giftThresholdByProductId);
    if (count($giftPids) !== 1) {
        // Nhiều sản phẩm quà -> giữ behavior khách tự chọn
        return $cart;
    }

    $giftPid = (int)$giftPids[0];
    $threshold = (int)$giftThresholdByProductId[$giftPid];

    // Kiểm tra xem giỏ đã có dòng quà tương ứng chưa
    $hasGiftLine = false;
    foreach ($cart as $it) {
        $pidIt = (int)($it['product_id'] ?? $it['id'] ?? 0);
        $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0 && (int)($it['qty'] ?? 0) === 1);
        if ($isGiftItem && $pidIt === $giftPid) {
            $hasGiftLine = true;
            break;
        }
    }

    // Nếu chưa đạt ngưỡng, loại bỏ mọi dòng quà của sản phẩm này (nếu có)
    if ($threshold > 0 && $subtotal < $threshold) {
        $cart = array_values(array_filter($cart, function($it) use ($giftPid) {
            $pidIt = (int)($it['product_id'] ?? $it['id'] ?? 0);
            $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0 && (int)($it['qty'] ?? 0) === 1);
            if ($isGiftItem && $pidIt === $giftPid) {
                return false;
            }
            return true;
        }));
        ecommerce_cart_set($cart);
        return $cart;
    }

    // Đã đạt ngưỡng và chưa có dòng quà -> tự động thêm quà tặng vào giỏ
    if ($threshold > 0 && $subtotal >= $threshold && !$hasGiftLine) {
        $variantId = 0;
        if ($variantTable !== '') {
            $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
            if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
                $stmtGV->bind_param('i', $giftPid);
                $stmtGV->execute();
                $rowGV = $stmtGV->get_result()->fetch_assoc();
                $stmtGV->close();
                if ($rowGV && !empty($rowGV['id'])) {
                    $variantId = (int)$rowGV['id'];
                }
            }
        }

        $newItem = ecommerce_build_cart_item($ithanhloc, $giftPid, $variantId, 1, '');
        if ($newItem) {
            $newItem['price'] = 0.0;
            $newItem['qty'] = 1;
            $newItem['is_gift'] = 1;
            // Khoá key riêng cho dòng quà để không trùng (gộp/đè) với dòng mua cùng product+variant
            $newItem['key'] = (string)($newItem['key'] ?? ($giftPid . ':' . $variantId . ':')) . '|GIFT';
            $cart[] = $newItem;
            $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
            ecommerce_cart_set($cart);
        }
    }

    return $cart;
}

// ** XÂY DỰNG PHẦN META LIÊN QUAN ĐẾN PROMO MUA X TẶNG Y (BXGY) ĐỂ TRẢ VỀ CHO CART/CHECKOUT RESPONSE **

function enrichCartWithVariants(mysqli $ithanhloc, array $cart): array {
    $productIds = [];
    foreach ($cart as $it) {
        $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
        if ($pid > 0) $productIds[] = $pid;
    }
    if (!$productIds) return $cart;
    $productIds = array_unique($productIds);
    $idList = implode(',', array_map('intval', $productIds));

    // Lấy ảnh sản phẩm để làm fallback + cờ đặt trước
    $productImages = [];
    $preorderMap = [];
    $pRes = $ithanhloc->query("SELECT id, image_url, preorder_enabled FROM ecommerce_product WHERE id IN ($idList)");
    if ($pRes) {
        while ($p = $pRes->fetch_assoc()) {
            $productImages[(int)$p['id']] = trim((string)($p['image_url'] ?? ''));
            $preorderMap[(int)$p['id']] = (int)($p['preorder_enabled'] ?? 0) === 1;
        }
    }

    $variantTable = 'ecommerce_product_variants';
    $variantsMap = [];
    global $variantActiveWhereExtra;
    $sql = "SELECT id, product_id, variant_name, price, image_url, stock_quantity, shipping_weight_value, shipping_weight_unit 
            FROM `{$variantTable}` 
            WHERE product_id IN ($idList) {$variantActiveWhereExtra}";
    $res = $ithanhloc->query($sql);
    if ($res) {
        while ($v = $res->fetch_assoc()) {
            $pid = (int)$v['product_id'];
            $price = (float)($v['price'] ?? 0);
            $stock = $v['stock_quantity'];
            $thumb = trim((string)($v['image_url'] ?? ''));
            $pThumb = $productImages[$pid] ?? '';

            // LOGIC KHOÁ CHẶT (Đồng bộ với ecommerce_build_cart_item):
            // 1. Phải có giá > 0
            if ($price <= 0) continue;
            // 2. Phải còn hàng (nếu có quản lý kho, stock !== null) — BỎ QUA nếu là hàng đặt trước
            if ($stock !== null && (int)$stock <= 0 && empty($preorderMap[$pid])) continue;
            // 3. Phải có ảnh (biến thể hoặc gốc)
            if ($thumb === '' && $pThumb === '') continue;

            if (!isset($variantsMap[$pid])) $variantsMap[$pid] = [];
            $vName = cleanVariantWeightLegacy(($v['variant_name'] ?? ''), (float)($v['shipping_weight_value'] ?? 0), (string)($v['shipping_weight_unit'] ?? 'kg'));
            $variantsMap[$pid][] = [
                'id' => (int)$v['id'],
                'name' => $vName,
                'shipping_weight_value' => (float)($v['shipping_weight_value'] ?? 0),
                'shipping_weight_unit' => (string)($v['shipping_weight_unit'] ?? 'kg'),
                'price' => $price
            ];
        }
    }

    // Map giới hạn phân loại quà theo từng promo BXGY (gift_variant_ids[pid])
    $bxgyGiftVarMapByPromo = null;

    foreach ($cart as &$it) {
        $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
        $allVariants = $variantsMap[$pid] ?? [];

        // Với dòng quà BXGY: chỉ cho đổi sang các phân loại được cấu hình trong promo
        $isBxgyGift = !empty($it['is_gift']) && !empty($it['bxgy_promo_id']);
        if ($isBxgyGift) {
            if ($bxgyGiftVarMapByPromo === null) {
                $bxgyGiftVarMapByPromo = [];
                try {
                    foreach (bxgy_get_active_promos($ithanhloc) as $p) {
                        if (!is_array($p)) continue;
                        $pidPromo = (int)($p['id'] ?? 0);
                        if ($pidPromo > 0) {
                            $bxgyGiftVarMapByPromo[$pidPromo] = isset($p['gift_variant_ids']) && is_array($p['gift_variant_ids']) ? $p['gift_variant_ids'] : [];
                        }
                    }
                } catch (Throwable $e) {
                    $bxgyGiftVarMapByPromo = [];
                }
            }
            $promoId = (int)$it['bxgy_promo_id'];
            $giftVarMap = $bxgyGiftVarMapByPromo[$promoId] ?? [];
            $allowed = [];
            if (isset($giftVarMap[$pid]) && is_array($giftVarMap[$pid])) {
                $allowed = array_map('intval', $giftVarMap[$pid]);
            } elseif (isset($giftVarMap[(string)$pid]) && is_array($giftVarMap[(string)$pid])) {
                $allowed = array_map('intval', $giftVarMap[(string)$pid]);
            }
            if ($allowed) {
                $allowedSet = array_flip($allowed);
                $allVariants = array_values(array_filter($allVariants, fn($v) => isset($allowedSet[(int)($v['id'] ?? 0)])));
            }
        }

        $it['available_variants'] = $allVariants;
        $it['is_preorder'] = !empty($preorderMap[$pid]);
    }
    unset($it);
    return $cart;
}

// ** XÂY DỰNG PHẦN RESPONSE CHO AJAX CART/CHECKOUT, KẾT HỢP VỚI META PROMO BXGY **
function buildCartResponse(mysqli $ithanhloc, array $cart, array $extra = []): array {
    $enrichedCart = enrichCartWithVariants($ithanhloc, $cart);
    $base = [
        'ok' => true,
        'data' => $enrichedCart,
        'count' => ecommerce_cart_count(),
        'allowed_payments' => ecommerce_cart_allowed_payment_keys($cart),
    ];
    return array_merge($base, ecommerce_build_bxgy_meta($ithanhloc, $cart), $extra);
}


$ajax = $_GET['ajax'] ?? null;
$requestAction = (string)($_REQUEST['action'] ?? '');
$readOnlyAjaxActions = [
    'payment_methods',
    'wallet_summary',
    'address_ai',
    'categories',
    'site_colors',
    'products',
    'related_products',
    'product_detail',
];

if (in_array((string)$ajax, $readOnlyAjaxActions, true) && !in_array($requestAction, ['cart_add', 'cart_add_free', 'cart_add_combo', 'cart_update_qty', 'cart_remove', 'cart_remove_bulk', 'cart_set_single', 'cart_set_selected', 'cart_clear', 'profile_save', 'checkout_save'], true)) {
    app_release_session_lock();
}

// === CSRF PROTECTION FOR STATE-CHANGING ACTIONS ===
$stateChangingActions = [
    'cart_add', 'cart_add_free', 'cart_add_combo', 'cart_update_qty', 'cart_remove', 
    'cart_remove_bulk', 'cart_set_single', 'cart_set_selected', 'cart_clear', 
    'profile_save', 'checkout_save', 'cart_set_bxgy_choice', 'cart_change_gift_variant'
];
if (in_array($ajax, $stateChangingActions) || in_array($requestAction, $stateChangingActions)) {
    app_verify_csrf();
}

// === V7: RATE LIMITING CHO CÁC ACTION WRITE (IP-aware) ===
// Dùng app_rate_limit_response() — hàm này kết hợp IP + session key để ngăn bypass
// bằng cách xóa cookie PHPSESSID. Xem chi tiết tại functions.php::app_rate_limit().
$rateLimitedActions = [
    'cart_add', 'cart_add_free', 'cart_add_combo', 'checkout_save',
    'cart_update_qty', 'cart_remove', 'cart_remove_bulk', 'cart_clear',
];
$currentWriteAction = $requestAction ?: $action;
if ($currentWriteAction !== '' && in_array($currentWriteAction, $rateLimitedActions, true)
    && function_exists('app_rate_limit_response')) {
    app_rate_limit_response(
        'cart_write',
        20,  // Giảm từ 30 → 20 để chặt hơn
        60,
        'Bạn đang thực hiện quá nhiều thao tác giỏ hàng. Vui lòng chờ vài giây rồi thử lại.'
    );
}




// ======== Profile ========
if ($ajax === 'payment_methods') {
    jOut(['ok' => true, 'data' => ecommerce_get_enabled_payment_methods()]);
}

if ($ajax === 'profile_get') {
    if (!isset($_SESSION['user_id'])) jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
   
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
    $stmt = $ithanhloc->prepare('SELECT * FROM user_address WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$profile) {
        $profile = [
            'user_id' => $uid,
            'user_name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'province' => '',
            'district' => '',
            'ward' => '',
            'shipping_fee' => null,
        ];
    }
    $stmtUser = $ithanhloc->prepare('SELECT full_name, username, phone, email, address FROM users WHERE id=? LIMIT 1');
    if ($stmtUser) {
        $stmtUser->bind_param('i', $uid);
        $stmtUser->execute();
        $userRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        if ($userRow) {
            $fallbackName = trim((string)($userRow['full_name'] ?? ''));
            if ($fallbackName === '') {
                $fallbackName = trim((string)($userRow['username'] ?? ''));
            }
            if (trim((string)($profile['user_name'] ?? '')) === '' && $fallbackName !== '') {
                $profile['user_name'] = $fallbackName;
            }
            if (trim((string)($profile['phone'] ?? '')) === '' && trim((string)($userRow['phone'] ?? '')) !== '') {
                $profile['phone'] = trim((string)$userRow['phone']);
            }
            if (trim((string)($profile['email'] ?? '')) === '' && trim((string)($userRow['email'] ?? '')) !== '') {
                $profile['email'] = trim((string)$userRow['email']);
            }
            if (trim((string)($profile['address'] ?? '')) === '' && trim((string)($userRow['address'] ?? '')) !== '') {
                $profile['address'] = trim((string)$userRow['address']);
            }
        }
    }
    $subtotalIn = (float)($_GET['subtotal'] ?? 0);
    $preferredMethod = trim((string)($_GET['shipping_method'] ?? ''));
    $shippingItems = shippingItemsFromRequest();
    $quote = buildShippingQuoteByLocation($ithanhloc, $subtotalIn, $profile, $preferredMethod, $shippingItems);
    $ship = (float)($quote['shipping_fee'] ?? 0);
    $selectedLocation = getSelectedLocationContext();
    jOut([
        'ok' => true,
        'data' => $profile,
        'shipping_fee' => $ship,
        'shipping_quote' => $quote,
        'selected_location' => $selectedLocation,
    ]);
}

if ($ajax === 'wallet_summary') {
    if (!isset($_SESSION['user_id'])) jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
    $balance = getWalletBalance($ithanhloc, $uid, false);
    $cfg = getXuConfig();
    jOut([
        'ok' => true,
        'balance' => $balance,
        'vnd_per_xu' => (int)$cfg['vnd_per_xu'],
        'max_use_percent' => (int)$cfg['max_use_percent'],
        'earn_percent' => (float)$cfg['earn_percent'],
    ]);
}

// Bảng màu site (lấy từ bảng site_color_options) cho UI chọn màu (trang chi tiết sản phẩm)
if ($ajax === 'site_colors') {
    $siteColorTable = function_exists('first_existing_table')
        ? first_existing_table($ithanhloc, ['site_color_options'])
        : 'site_color_options';

    if ($siteColorTable === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu bảng site_color_options']);
    }

    $rows = [];
    try {
        // Lấy đầy đủ cột để client có thể dùng khi cần, ưu tiên các màu đang active
        $sql = "SELECT id, code, name, hex, rgb, group_code, tone, is_active, sort_order, created_at, updated_at
                FROM `{$siteColorTable}`
                WHERE is_active = 1
                ORDER BY sort_order ASC, id ASC";
        $res = $ithanhloc->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    } catch (Throwable $e) {
        jOut(['ok' => false, 'msg' => 'Lỗi khi đọc bảng màu']);
    }

    // Trả về theo chuẩn { ok, data: [...] } để product-detail.php sử dụng trực tiếp
    jOut(['ok' => true, 'data' => $rows]);
}

if ($ajax === 'address_ai') {
    $q = trim((string)($_GET['q'] ?? $_GET['address'] ?? ''));
    if ($q === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập địa chỉ']);
    jOut(aiSuggestAddress($q));
}

// ===== Geocoding qua OpenStreetMap Nominatim (proxy server-side, miễn phí) =====
// Tách 1 chuỗi địa chỉ Nominatim thành các phần dùng cho form (street/ward/district/province).
// Map mã ISO3166-2 (ổn định, không đổi khi sáp nhập địa giới) → tên tỉnh/TP theo danh mục GHN (cũ).
// Dùng cho TP trực thuộc TW — nơi OSM (data mới 2025) đặt quận vào field 'city' và bỏ tên tỉnh.
if (!function_exists('cart_iso_to_province')) {
    function cart_iso_to_province(string $iso): string {
        static $map = [
            'VN-SG' => 'Hồ Chí Minh',  // Sài Gòn
            'VN-HN' => 'Hà Nội',
            'VN-DN' => 'Đà Nẵng',
            'VN-HP' => 'Hải Phòng',
            'VN-CT' => 'Cần Thơ',
        ];
        return $map[strtoupper(trim($iso))] ?? '';
    }
}
if (!function_exists('cart_geo_parse_address')) {
    function cart_geo_parse_address(array $a): array {
        // Nominatim (VN) trả nhiều khoá tuỳ vùng & thay đổi sau sáp nhập 2025.
        // Vì tầng địa giới OSM (mới) có thể lệch với GHN (cũ), ta thu thập NHIỀU ứng viên cho
        // mỗi cấp rồi để frontend thử match lần lượt với danh mục GHN (tên + NameExtension).
        $state   = trim((string)($a['state'] ?? ''));
        $city    = trim((string)($a['city'] ?? ''));
        $county  = trim((string)($a['county'] ?? $a['city_district'] ?? $a['district'] ?? ''));
        $suburb  = trim((string)($a['suburb'] ?? ''));
        $quarter = trim((string)($a['quarter'] ?? ''));
        $ward    = trim((string)($a['ward'] ?? ''));
        $village = trim((string)($a['village'] ?? $a['town'] ?? ''));
        $iso     = trim((string)($a['ISO3166-2-lvl4'] ?? ''));

        // ---- Tỉnh/Thành ----
        $isoProvince = cart_iso_to_province($iso);
        // Ứng viên tỉnh: ISO (ưu tiên, ổn định) → state → city.
        $provinceCandidates = array_values(array_filter(array_unique([$isoProvince, $state, $city])));
        $province = $provinceCandidates[0] ?? '';

        // ---- Quận/Huyện ----
        // Ứng viên quận: county → city (khi city KHÁC tỉnh, vd 'Thành phố Thủ Đức') → suburb.
        $districtCandidates = array_values(array_filter(array_unique([
            $county,
            ($city !== '' && $city !== $province) ? $city : '',
            $suburb,
        ])));
        $district = $districtCandidates[0] ?? '';

        // ---- Phường/Xã ----
        // Ứng viên phường: ward → quarter → suburb → village. (suburb có thể là phường khi city là quận)
        $wardCandidates = array_values(array_filter(array_unique([
            $ward, $quarter, $suburb, $village,
            trim((string)($a['neighbourhood'] ?? '')),
        ])));
        $wardName = $wardCandidates[0] ?? '';

        $streetParts = [];
        if (!empty($a['house_number'])) $streetParts[] = trim((string)$a['house_number']);
        if (!empty($a['road']))         $streetParts[] = trim((string)$a['road']);
        $street = trim(implode(' ', $streetParts));

        return [
            'street'   => $street,
            'ward'     => $wardName,
            'district' => $district,
            'province' => $province,
            // Mảng ứng viên cho frontend match đa cấp (xử lý lệch tầng OSM mới vs GHN cũ).
            'province_candidates' => $provinceCandidates,
            'district_candidates' => $districtCandidates,
            'ward_candidates'     => $wardCandidates,
        ];
    }
}
if (!function_exists('cart_nominatim_get')) {
    function cart_nominatim_get(string $url): ?array {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            // Nominatim yêu cầu User-Agent định danh ứng dụng.
            CURLOPT_USERAGENT => 'PaintAndMore/1.0 (account address lookup)',
            CURLOPT_HTTPHEADER => ['Accept-Language: vi,en'],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || $raw === false) return null;
        $json = json_decode((string)$raw, true);
        return is_array($json) ? $json : null;
    }
}

// ===== Goong.io (geocoding địa chỉ VN, chính xác hơn OSM) =====
if (!function_exists('cart_goong_get')) {
    function cart_goong_get(string $url): ?array {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || $raw === false) return null;
        $json = json_decode((string)$raw, true);
        return is_array($json) ? $json : null;
    }
}
// Trích mảng NameExtension (các biến thể tên GHN) từ raw_json để frontend match tên OSM chính xác hơn.
if (!function_exists('ghnNameAlts')) {
    function ghnNameAlts(?string $rawJson): array {
        if (!$rawJson) return [];
        $j = json_decode($rawJson, true);
        if (!is_array($j) || empty($j['NameExtension']) || !is_array($j['NameExtension'])) return [];
        $alts = [];
        foreach ($j['NameExtension'] as $alt) {
            $alt = trim((string)$alt);
            if ($alt !== '') $alts[] = $alt;
        }
        return array_values(array_unique($alts));
    }
}

if (!function_exists('normalizeKey')) {
    function normalizeKey(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false && $converted !== null) {
            $text = $converted;
        }
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $text = trim((string)preg_replace('/\s+/', ' ', $text));
        return $text;
    }
}

if (!function_exists('normalizeDbLocationName')) {
    function normalizeDbLocationName(string $name): string {
        $s = normalizeKey($name);
        $s = preg_replace('/^(thanh pho|tinh|quan|huyen|thi xa|thi tran|phuong|xa)\s+/i', '', $s);
        return trim($s);
    }
}

if (!function_exists('extractDbNumberToken')) {
    function extractDbNumberToken(string $name): string {
        if (preg_match('/\d+/', $name, $matches)) {
            return $matches[0];
        }
        return '';
    }
}

if (!function_exists('matchDbRegionName')) {
    function matchDbRegionName(string $keyword, string $mainName, array $alts, bool $numAware): bool {
        $kwNum = $numAware ? extractDbNumberToken($keyword) : '';
        $candidates = array_merge([$mainName], $alts);
        foreach ($candidates as $candidate) {
            $txt = normalizeDbLocationName($candidate);
            if ($txt === '') continue;
            if ($numAware) {
                $txtNum = extractDbNumberToken($txt);
                if ($kwNum !== '' && $txtNum !== '' && $kwNum !== $txtNum) {
                    continue;
                }
            }
            if ($txt === $keyword || strpos($txt, $keyword) !== false || strpos($keyword, $txt) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('resolveProvinceIdFromDb')) {
    function resolveProvinceIdFromDb(mysqli $db, string $provinceName): int {
        $keyword = normalizeDbLocationName($provinceName);
        if ($keyword === '') return 0;
        $res = $db->query("SELECT region_id, name, raw_json FROM ghn_region WHERE level = 'province'");
        while ($res && ($row = $res->fetch_assoc())) {
            $alts = ghnNameAlts($row['raw_json'] ?? null);
            if (matchDbRegionName($keyword, $row['name'], $alts, false)) {
                return (int)$row['region_id'];
            }
        }
        return 0;
    }
}

if (!function_exists('resolveDistrictIdFromDb')) {
    function resolveDistrictIdFromDb(mysqli $db, int $provinceId, string $districtName): int {
        $keyword = normalizeDbLocationName($districtName);
        if ($keyword === '') return 0;
        $stmt = $db->prepare("SELECT region_id, name, raw_json FROM ghn_region WHERE level = 'district' AND parent_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $provinceId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $alts = ghnNameAlts($row['raw_json'] ?? null);
                if (matchDbRegionName($keyword, $row['name'], $alts, true)) {
                    $stmt->close();
                    return (int)$row['region_id'];
                }
            }
            $stmt->close();
        }
        return 0;
    }
}

if (!function_exists('resolveWardCodeFromDb')) {
    function resolveWardCodeFromDb(mysqli $db, int $districtId, string $wardName): string {
        $keyword = normalizeDbLocationName($wardName);
        if ($keyword === '') return '';
        $stmt = $db->prepare("SELECT code, name, raw_json FROM ghn_region WHERE level = 'ward' AND parent_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $districtId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $alts = ghnNameAlts($row['raw_json'] ?? null);
                if (matchDbRegionName($keyword, $row['name'], $alts, true)) {
                    $stmt->close();
                    return trim((string)$row['code']);
                }
            }
            $stmt->close();
        }
        return '';
    }
}

if (!function_exists('ghnRegionNameById')) {
    function ghnRegionNameById(mysqli $ithanhloc, string $level, int $regionId): string {
        if ($regionId <= 0) {
            return '';
        }
        $stmt = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level = ? AND region_id = ? LIMIT 1");
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('si', $level, $regionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return trim((string)($row['name'] ?? ''));
    }
}

if (!function_exists('cart_resolve_ghn_ids')) {
    function cart_resolve_ghn_ids(mysqli $db, string $province, string $district, string $ward): array {
        $provinceId = 0;
        $districtId = 0;
        $wardCode   = '';

        if ($province !== '') {
            $provinceId = resolveProvinceIdFromDb($db, $province);
        }
        if ($provinceId > 0 && $district !== '') {
            $districtId = resolveDistrictIdFromDb($db, $provinceId, $district);
        }
        if ($districtId > 0 && $ward !== '') {
            $wardCode = resolveWardCodeFromDb($db, $districtId, $ward);
        }

        // Smart fallback logic for merged districts/wards
        if ($provinceId > 0 && $wardCode === '' && $ward !== '') {
            $wardKeyword = normalizeDbLocationName($ward);
            if ($wardKeyword !== '') {
                $stmtWardMatch = $db->prepare("
                    SELECT w.code, w.parent_id, w.name, w.raw_json 
                    FROM ghn_region w
                    JOIN ghn_region d ON w.parent_id = d.region_id
                    WHERE w.level = 'ward' 
                      AND d.level = 'district' 
                      AND d.parent_id = ?
                ");
                if ($stmtWardMatch) {
                    $stmtWardMatch->bind_param('i', $provinceId);
                    $stmtWardMatch->execute();
                    $resWards = $stmtWardMatch->get_result();
                    $possibleMatches = [];
                    while ($resWards && ($rowWard = $resWards->fetch_assoc())) {
                        $alts = ghnNameAlts($rowWard['raw_json'] ?? null);
                        if (matchDbRegionName($wardKeyword, $rowWard['name'], $alts, true)) {
                            $possibleMatches[] = $rowWard;
                        }
                    }
                    $stmtWardMatch->close();

                    if (!empty($possibleMatches)) {
                        $matchedWard = null;
                        if ($districtId > 0) {
                            foreach ($possibleMatches as $pm) {
                                if ((int)$pm['parent_id'] === $districtId) {
                                    $matchedWard = $pm;
                                    break;
                                }
                            }
                        }
                        if (!$matchedWard) {
                            $matchedWard = $possibleMatches[0];
                        }

                        $wardCode = trim((string)$matchedWard['code']);
                        $districtId = (int)$matchedWard['parent_id'];
                    }
                }
            }
        }

        return [
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'ward_code'   => $wardCode,
        ];
    }
}

// Chuẩn hoá compound{province,district,commune} của Goong → cấu trúc form (kèm candidates để match GHN).
if (!function_exists('cart_goong_parse_compound')) {
    function cart_goong_parse_compound(array $c): array {
        $province = trim((string)($c['province'] ?? ''));
        $district = trim((string)($c['district'] ?? ''));
        $ward     = trim((string)($c['commune'] ?? ''));

        // Helper: bỏ prefix hành chính (Thành phố, Tỉnh, Quận, Huyện, Phường, Xã...)
        // để tạo thêm candidate tên rút gọn khớp với GHN.
        $stripPrefix = function(string $s): string {
            return preg_replace(
                '/^(Thành phố|Tỉnh|Thị xã|Thị trấn|Quận|Huyện|Phường|Xã)\s+/ui',
                '',
                trim($s)
            );
        };

        $provinceShort = $stripPrefix($province);
        $districtShort = $stripPrefix($district);
        $wardShort     = $stripPrefix($ward);

        // candidates: tên đầy đủ trước, tên rút gọn sau (khác nhau mới thêm)
        $provinceCandidates = array_values(array_unique(array_filter(
            $provinceShort !== $province ? [$province, $provinceShort] : [$province]
        )));
        $districtCandidates = array_values(array_unique(array_filter(
            $districtShort !== $district ? [$district, $districtShort] : [$district]
        )));
        $wardCandidates = array_values(array_unique(array_filter(
            $wardShort !== $ward ? [$ward, $wardShort] : [$ward]
        )));

        return [
            'province' => $province,
            'district' => $district,
            'ward'     => $ward,
            'province_candidates' => $provinceCandidates,
            'district_candidates' => $districtCandidates,
            'ward_candidates'     => $wardCandidates,
        ];
    }
}

// Reverse: lat/lng -> địa chỉ (dùng cho nút "Dùng vị trí hiện tại")
if ($ajax === 'geo_reverse') {
    $lat = (float)($_GET['lat'] ?? 0);
    $lng = (float)($_GET['lng'] ?? 0);
    if ($lat == 0.0 && $lng == 0.0) jOut(['ok' => false, 'msg' => 'Thiếu toạ độ']);

    $parsed = null;

    // Ưu tiên Goong (chính xác địa chỉ VN) nếu có key.
    if (!empty($goong_api_key)) {
        $url = 'https://rsapi.goong.io/Geocode?latlng=' . urlencode($lat . ',' . $lng) . '&api_key=' . urlencode($goong_api_key);
        $res = cart_goong_get($url);
        $top = (is_array($res) && !empty($res['results'][0])) ? $res['results'][0] : null;
        if ($top) {
            $parsed = cart_goong_parse_compound($top['compound'] ?? []);
            $parsed['full'] = trim((string)($top['formatted_address'] ?? ''));
            $loc = $top['geometry']['location'] ?? [];
            $parsed['lat'] = isset($loc['lat']) ? (float)$loc['lat'] : $lat;
            $parsed['lng'] = isset($loc['lng']) ? (float)$loc['lng'] : $lng;
        }
        // Goong lỗi → rơi xuống Nominatim.
    }

    if (!$parsed) {
        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=vi&lat=' . urlencode((string)$lat) . '&lon=' . urlencode((string)$lng);
        $res = cart_nominatim_get($url);
        if ($res && !empty($res['address'])) {
            $parsed = cart_geo_parse_address($res['address']);
            $parsed['full'] = trim((string)($res['display_name'] ?? ''));
            $parsed['lat'] = isset($res['lat']) ? (float)$res['lat'] : $lat;
            $parsed['lng'] = isset($res['lon']) ? (float)$res['lon'] : $lng;
        }
    }

    if (!$parsed) {
        jOut(['ok' => false, 'msg' => 'Không xác định được địa chỉ từ vị trí']);
    }

    $ghnIds = cart_resolve_ghn_ids($ithanhloc, $parsed['province'] ?? '', $parsed['district'] ?? '', $parsed['ward'] ?? '');
    $parsed['province_id'] = $ghnIds['province_id'];
    $parsed['district_id'] = $ghnIds['district_id'];
    $parsed['ward_code']   = $ghnIds['ward_code'];

    jOut(['ok' => true, 'data' => $parsed]);
}

// Search: text -> danh sách gợi ý địa chỉ (autocomplete khi gõ)
if ($ajax === 'geo_search') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 3) jOut(['ok' => true, 'data' => []]);

    // Ưu tiên Goong AutoComplete (gợi ý địa chỉ VN chuẩn). Mỗi prediction kèm place_id để lấy toạ độ khi chọn.
    if (!empty($goong_api_key)) {
        $url = 'https://rsapi.goong.io/Place/AutoComplete?input=' . urlencode($q) . '&api_key=' . urlencode($goong_api_key);
        $res = cart_goong_get($url);
        if (is_array($res) && !empty($res['predictions'])) {
            $list = [];
            foreach ($res['predictions'] as $pred) {
                if (!is_array($pred)) continue;
                $sf = $pred['structured_formatting'] ?? [];
                $list[] = [
                    'full'     => trim((string)($pred['description'] ?? '')),
                    'street'   => trim((string)($sf['main_text'] ?? '')),
                    'sub'      => trim((string)($sf['secondary_text'] ?? '')),
                    'place_id' => trim((string)($pred['place_id'] ?? '')),
                    // Khu vực + toạ độ sẽ lấy qua geo_place_detail khi user chọn (Goong AutoComplete không trả sẵn).
                    'province' => '', 'district' => '', 'ward' => '',
                    'lat' => null, 'lng' => null,
                ];
            }
            jOut(['ok' => true, 'data' => $list]);
        }
        // Goong lỗi → rơi xuống Nominatim.
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&accept-language=vi&countrycodes=vn&limit=6&q=' . urlencode($q);
    $res = cart_nominatim_get($url);
    if (!is_array($res)) jOut(['ok' => true, 'data' => []]);
    $list = [];
    foreach ($res as $item) {
        if (!is_array($item) || empty($item['address'])) continue;
        $p = cart_geo_parse_address($item['address']);
        $p['full'] = trim((string)($item['display_name'] ?? ''));
        $p['lat'] = isset($item['lat']) ? (float)$item['lat'] : null;
        $p['lng'] = isset($item['lon']) ? (float)$item['lon'] : null;
        $list[] = $p;
    }
    jOut(['ok' => true, 'data' => $list]);
}

// Place Detail: place_id (từ Goong AutoComplete) -> toạ độ + khu vực đầy đủ (khi user chọn 1 gợi ý)
if ($ajax === 'geo_place_detail') {
    $placeId = trim((string)($_GET['place_id'] ?? ''));
    if ($placeId === '') jOut(['ok' => false, 'msg' => 'Thiếu place_id']);
    if (empty($goong_api_key)) jOut(['ok' => false, 'msg' => 'Chưa cấu hình Goong API key']);

    $url = 'https://rsapi.goong.io/Place/Detail?place_id=' . urlencode($placeId) . '&api_key=' . urlencode($goong_api_key);
    $res = cart_goong_get($url);
    $r = (is_array($res) && !empty($res['result'])) ? $res['result'] : null;
    if (!$r) jOut(['ok' => false, 'msg' => 'Không lấy được chi tiết địa chỉ']);

    $parsed = cart_goong_parse_compound($r['compound'] ?? []);
    $parsed['full'] = trim((string)($r['formatted_address'] ?? ''));
    // street: main_text nếu có (tên đường), fallback name.
    $parsed['street'] = trim((string)($r['name'] ?? ''));
    $loc = $r['geometry']['location'] ?? [];
    $parsed['lat'] = isset($loc['lat']) ? (float)$loc['lat'] : null;
    $parsed['lng'] = isset($loc['lng']) ? (float)$loc['lng'] : null;

    $ghnIds = cart_resolve_ghn_ids($ithanhloc, $parsed['province'] ?? '', $parsed['district'] ?? '', $parsed['ward'] ?? '');
    $parsed['province_id'] = $ghnIds['province_id'];
    $parsed['district_id'] = $ghnIds['district_id'];
    $parsed['ward_code']   = $ghnIds['ward_code'];

    jOut(['ok' => true, 'data' => $parsed]);
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'profile_save') {
    if (!isset($_SESSION['user_id'])) jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
    $src = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
    $user_name = trim((string)($src['user_name'] ?? ''));
    $phone = trim((string)($src['phone'] ?? ''));
    $email = trim((string)($src['email'] ?? ''));
    $address = trim((string)($src['address'] ?? ''));
    $province = trim((string)($src['province'] ?? ''));
    $district = trim((string)($src['district'] ?? ''));
    $ward = trim((string)($src['ward'] ?? ''));
    $shipping_fee = $src['shipping_fee'] ?? null;
    if ($shipping_fee === '' || $shipping_fee === null) $shipping_fee = null;
    if ($shipping_fee !== null) $shipping_fee = (float)$shipping_fee;

    $stmt = $ithanhloc->prepare('INSERT INTO user_address (user_id, user_name, phone, email, address, province, district, ward, shipping_fee, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), phone=VALUES(phone), email=VALUES(email), address=VALUES(address), province=VALUES(province), district=VALUES(district), ward=VALUES(ward), shipping_fee=VALUES(shipping_fee), updated_at=NOW()');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Prepare failed']);
    $stmt->bind_param('isssssssd', $uid, $user_name, $phone, $email, $address, $province, $district, $ward, $shipping_fee);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    $ship = computeShippingFeeByLocation($ithanhloc, 0, [
        'shipping_fee' => $shipping_fee,
        'province' => $province,
        'address' => $address,
    ]);
    if ($uid >= 0) {
        app_user_log($ithanhloc, $uid, 'shipping_profile', 'Cập nhật thông tin nhận hàng', [
            'address' => $address,
            'phone' => $phone
        ]);
    }
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã lưu thông tin nhận hàng' : $err, 'shipping_fee' => $ship]);
}

// ======== Cart (Session) ========
if ($ajax === 'cart_get') {
    $cartRaw = ecommerce_cart_get();

    $cart = $cartRaw;
    // ===== ÁP DỤNG ĐIỀU KIỆN QUÀ TẶNG THEO HÓA ĐƠN (PROMO GIFT) =====
    // Hỗ trợ nhiều chiến dịch quà tặng: mỗi sản phẩm quà có threshold riêng, chỉ giữ lại khi subtotal đạt ngưỡng của chính sản phẩm đó
    $giftThresholdByProductId = [];
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
    } catch (Throwable $e) {
        $hasPromoTbl = false;
    }
    if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
        $nowPromo = nowStr();
        $stmtPg = $ithanhloc->prepare("SELECT promo_type, config_json FROM ecommerce_product_promo WHERE promo_type = 'gift' AND is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)");
        if ($stmtPg) {
            $stmtPg->bind_param('ss', $nowPromo, $nowPromo);
            $stmtPg->execute();
            $resPg = $stmtPg->get_result();
            while ($rowPg = $resPg->fetch_assoc()) {
                $cfg = json_decode((string)($rowPg['config_json'] ?? ''), true);
                if (!is_array($cfg)) continue;
                $thresholdGift = (int)($cfg['threshold_amount'] ?? 0);
                if (empty($cfg['gift_product_ids']) || !is_array($cfg['gift_product_ids'])) continue;
                foreach ($cfg['gift_product_ids'] as $gid) {
                    $gid = (int)$gid;
                    if ($gid <= 0) continue;
                    if (!isset($giftThresholdByProductId[$gid]) || ($thresholdGift > 0 && $thresholdGift < $giftThresholdByProductId[$gid])) {
                        $giftThresholdByProductId[$gid] = $thresholdGift;
                    }
                }
            }
            $stmtPg->close();
        }
    }

    // Tính subtotal trước khi lọc quà: chỉ tính các dòng không phải quà tặng
    $subtotal = 0;
    foreach ($cart as $it) {
        $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0 && (int)($it['qty'] ?? 0) === 1);
        if ($isGiftItem) continue;
        $subtotal += (float)($it['price'] ?? 0) * (int)($it['qty'] ?? 0);
    }

    if ($giftThresholdByProductId) {
        $cart = array_values(array_filter($cart, function($it) use ($giftThresholdByProductId, $subtotal) {
            $pidIt = (int)($it['product_id'] ?? $it['id'] ?? 0);
            if (!isset($giftThresholdByProductId[$pidIt])) return true;
            $thresholdGift = (int)$giftThresholdByProductId[$pidIt];
            if ($thresholdGift <= 0) return true;
            $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0 && (int)($it['qty'] ?? 0) === 1);
            if ($isGiftItem && empty($it['bxgy_promo_id']) && $subtotal < $thresholdGift) {
                // Chưa đạt ngưỡng của sản phẩm quà này: loại khỏi giỏ
                return false;
            }
            return true;
        }));
    }

    if ($cart !== $cartRaw) {
        ecommerce_cart_set($cart);
    }
    // Tính thông tin Mua X tặng Y chỉ để biết danh sách quà tặng; không trừ thêm giảm giá vì quà đã là sản phẩm 0đ
    $bxgyPromos = bxgy_get_active_promos($ithanhloc);
    $bxgyResult = bxgy_compute_discount_for_items($cart, $bxgyPromos);
    $bxgyDiscount = 0.0;
    $bxgyGifts = isset($bxgyResult['gifts']) && is_array($bxgyResult['gifts']) ? array_values($bxgyResult['gifts']) : [];
    $bxgyAppliedPromos = isset($bxgyResult['applied_promos']) && is_array($bxgyResult['applied_promos']) ? $bxgyResult['applied_promos'] : [];

    // Enrich BXGY promos with gift products so cart/checkout can render selectors
    $bxgyGiftAllIds = [];
    foreach ($bxgyPromos as $p) {
        $ids = (isset($p['gift_product_ids']) && is_array($p['gift_product_ids'])) ? $p['gift_product_ids'] : [];
        foreach ($ids as $gid) {
            $gid = (int)$gid;
            if ($gid > 0) $bxgyGiftAllIds[$gid] = $gid;
        }
    }
    if ($bxgyGiftAllIds) {
        $idList = implode(',', array_map('intval', array_values($bxgyGiftAllIds)));
        $giftMap = [];
        $q = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) AS min_price FROM ecommerce_product p WHERE p.id IN ($idList)");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $gid = (int)($r['id'] ?? 0);
                if ($gid <= 0) continue;
                $giftMap[$gid] = [
                    'product_id' => $gid,
                    'name' => (string)($r['product_name'] ?? ''),
                    'thumb' => (string)($r['image_url'] ?? ''),
                    'price' => (float)($r['min_price'] ?? 0),
                ];
            }
        }
        foreach ($bxgyPromos as &$p) {
            $ids = (isset($p['gift_product_ids']) && is_array($p['gift_product_ids'])) ? $p['gift_product_ids'] : [];
            $p['gift_products'] = [];
            foreach ($ids as $gid) {
                $gid = (int)$gid;
                if ($gid > 0 && isset($giftMap[$gid])) {
                    $p['gift_products'][] = $giftMap[$gid];
                }
            }
        }
        unset($p);
    }

    $productIds = productIdsFromItems($cart);
    $shippingPreview = buildShippingQuoteByLocation($ithanhloc, $subtotal, null, '', $cart);
    $shippingFeePreview = (float)($shippingPreview['shipping_fee'] ?? 0);
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
    $vouchersOrder = listAvailableVouchers($ithanhloc, $subtotal, $productIds, 'order', $shippingFeePreview, '', '', 'web', $uid);
    $vouchersShipping = listAvailableVouchers($ithanhloc, $subtotal, $productIds, 'shipping', $shippingFeePreview, '', '', 'web', $uid);

    // Tách riêng voucher ưu đãi thanh toán (payment_discount) để frontend chỉ áp dụng ở panel thanh toán
    $vouchersPayment = [];
    if (is_array($vouchersOrder) && $vouchersOrder) {
        $filtered = [];
        foreach ($vouchersOrder as $vv) {
            $tpl = strtolower(trim((string)($vv['voucher_template'] ?? '')));
            if ($tpl === 'payment_discount') {
                $vouchersPayment[] = $vv;
                continue;
            }
            $filtered[] = $vv;
        }
        $vouchersOrder = $filtered;
    }
    // Với user đã đăng nhập, lấy danh sách mã đã lưu để ưu tiên gợi ý.
    // Với khách vãng lai (uid = 0), không có khái niệm "mã đã lưu" nhưng vẫn cho phép dùng voucher nhập tay.
    $savedCodes = [];
    if ($uid >= 0) {
        $savedCodes = ecommerce_get_user_saved_voucher_codes($ithanhloc, $uid);
    }
    $vouchersOrder = markSavedVouchers($vouchersOrder, $savedCodes);
    $vouchersShipping = markSavedVouchers($vouchersShipping, $savedCodes);
    $vouchersPayment = markSavedVouchers($vouchersPayment, $savedCodes);
    $savedVouchersOrder = extractSavedVouchers($vouchersOrder);
    $savedVouchersShipping = extractSavedVouchers($vouchersShipping);
    $savedVouchersPayment = extractSavedVouchers($vouchersPayment);
    $suggestedVoucherOrder = $savedVouchersOrder ? $savedVouchersOrder[0] : null;
    $suggestedVoucherShipping = $savedVouchersShipping ? $savedVouchersShipping[0] : null;
    $selectedVoucherCodeOrder = ecommerce_session_voucher_get('order');
    if ($selectedVoucherCodeOrder !== '') {
        // Với user đăng nhập: chỉ giữ lại mã đang chọn nếu còn nằm trong thư viện đã lưu.
        // Với khách vãng lai: không ràng buộc theo savedCodes, chỉ cần validate điều kiện áp dụng.
        if ($uid >= 0 && ($savedCodes && !in_array($selectedVoucherCodeOrder, $savedCodes, true))) {
            ecommerce_session_voucher_clear('order');
            $selectedVoucherCodeOrder = '';
        } else {
            $checkSelected = validateVoucher($ithanhloc, $selectedVoucherCodeOrder, $subtotal, $productIds, 'order', $shippingFeePreview, '', '', 'web', $uid);
            if (!($checkSelected['ok'] ?? false)) {
                ecommerce_session_voucher_clear('order');
                $selectedVoucherCodeOrder = '';
            }
        }
    }

    $selectedVoucherCodeShipping = ecommerce_session_voucher_get('shipping');
    if ($selectedVoucherCodeShipping !== '') {
        if ($uid >= 0 && ($savedCodes && !in_array($selectedVoucherCodeShipping, $savedCodes, true))) {
            ecommerce_session_voucher_clear('shipping');
            $selectedVoucherCodeShipping = '';
        } else {
            $checkSelectedShip = validateVoucher($ithanhloc, $selectedVoucherCodeShipping, $subtotal, $productIds, 'shipping', $shippingFeePreview, '', '', 'web', $uid, 'list');
            if (!($checkSelectedShip['ok'] ?? false)) {
                ecommerce_session_voucher_clear('shipping');
                $selectedVoucherCodeShipping = '';
            }
        }
    }

    jOut([
        'ok' => true,
        'data' => enrichCartWithVariants($ithanhloc, $cart),
        'count' => ecommerce_cart_count(),
        'allowed_payments' => ecommerce_cart_allowed_payment_keys($cart),
        'vouchers' => $vouchersOrder,
        'vouchers_order' => $vouchersOrder,
        'vouchers_shipping' => $vouchersShipping,
        'vouchers_payment' => $vouchersPayment,
        'saved_vouchers' => $savedVouchersOrder,
        'saved_vouchers_order' => $savedVouchersOrder,
        'saved_vouchers_shipping' => $savedVouchersShipping,
        'saved_vouchers_payment' => $savedVouchersPayment,
        'suggested_voucher' => $suggestedVoucherOrder,
        'suggested_voucher_order' => $suggestedVoucherOrder,
        'suggested_voucher_shipping' => $suggestedVoucherShipping,
        'selected_voucher_code' => $selectedVoucherCodeOrder,
        'selected_voucher_code_order' => $selectedVoucherCodeOrder,
        'selected_voucher_code_shipping' => $selectedVoucherCodeShipping,
        'bxgy_promos' => $bxgyPromos,
        'bxgy_applied_promos' => $bxgyAppliedPromos,
        'bxgy' => [
            'discount' => $bxgyDiscount,
            'gifts' => $bxgyGifts,
        ],
    ]);
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_set_bxgy_choice') {
    $key = (string)($_REQUEST['key'] ?? '');
    $promoId = (int)($_REQUEST['promo_id'] ?? 0);
    $giftPid = (int)($_REQUEST['gift_pid'] ?? 0);
    $giftVariantId = (int)($_REQUEST['gift_variant_id'] ?? 0);
    if ($key === '' || $promoId <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu thông tin lựa chọn quà']);
    }

    // Validate theo cấu hình BXGY (nếu có) để tránh lưu lựa chọn không hợp lệ
    $promoCfg = null;
    try {
        $allPromos = bxgy_get_active_promos($ithanhloc);
        if (is_array($allPromos)) {
            foreach ($allPromos as $p) {
                if (!is_array($p)) continue;
                if ((int)($p['id'] ?? 0) === $promoId) {
                    $promoCfg = $p;
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        $promoCfg = null;
    }
    if (is_array($promoCfg)) {
        $giftProductIdCfg = (int)($promoCfg['gift_product_id'] ?? 0);
        $giftProductIdsCfg = isset($promoCfg['gift_product_ids']) && is_array($promoCfg['gift_product_ids'])
            ? array_values(array_filter(array_map('intval', $promoCfg['gift_product_ids']), fn($v) => $v > 0))
            : [];
        if ($giftPid > 0) {
            if ($giftProductIdsCfg) {
                if (!in_array($giftPid, $giftProductIdsCfg, true)) {
                    $giftPid = 0;
                    $giftVariantId = 0;
                }
            } elseif ($giftProductIdCfg > 0) {
                // Promo quà cố định: ép về gift_product_id
                $giftPid = $giftProductIdCfg;
            }
        }

        if ($giftVariantId > 0 && $giftPid > 0) {
            $giftVariantMap = isset($promoCfg['gift_variant_ids']) && is_array($promoCfg['gift_variant_ids']) ? $promoCfg['gift_variant_ids'] : [];
            $allowed = [];
            if ($giftVariantMap && isset($giftVariantMap[$giftPid]) && is_array($giftVariantMap[$giftPid])) {
                $allowed = array_values(array_filter(array_map('intval', $giftVariantMap[$giftPid]), fn($v) => $v > 0));
            } elseif ($giftVariantMap && isset($giftVariantMap[(string)$giftPid]) && is_array($giftVariantMap[(string)$giftPid])) {
                $allowed = array_values(array_filter(array_map('intval', $giftVariantMap[(string)$giftPid]), fn($v) => $v > 0));
            }
            if ($allowed && !in_array($giftVariantId, $allowed, true)) {
                $giftVariantId = 0;
            }
        }
    }

    $baseKey = $key;
    $parts = explode('|BXGY-', $key);
    if (count($parts) > 1) {
        $baseKey = $parts[0];
    }

    $cart = ecommerce_cart_get();
    $updated = false;
    foreach ($cart as &$it) {
        if ((string)($it['key'] ?? '') !== $baseKey) continue;
        // không cho set choice trên dòng quà / combo
        if (!empty($it['is_gift']) || !empty($it['is_combo'])) {
            continue;
        }

        $choice = $it['bxgy_gift_choice'] ?? [];
        if (is_string($choice) && $choice !== '') {
            $tmp = json_decode($choice, true);
            if (is_array($tmp)) $choice = $tmp;
        }
        if (!is_array($choice)) $choice = [];
        if ($giftPid > 0) {
            $choice[$promoId] = $giftPid;
        } else {
            unset($choice[$promoId]);
        }
        $it['bxgy_gift_choice'] = $choice;

        $choiceVar = $it['bxgy_gift_choice_variant'] ?? [];
        if (is_string($choiceVar) && $choiceVar !== '') {
            $tmp = json_decode($choiceVar, true);
            if (is_array($tmp)) $choiceVar = $tmp;
        }
        if (!is_array($choiceVar)) $choiceVar = [];
        if ($giftVariantId > 0) {
            $choiceVar[$promoId] = $giftVariantId;
        } else {
            unset($choiceVar[$promoId]);
        }
        $it['bxgy_gift_choice_variant'] = $choiceVar;

        $updated = true;
        break;
    }
    unset($it);

    if (!$updated) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy dòng sản phẩm để cập nhật']);
    }

    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_clear') {
    $beforeCount = ecommerce_cart_count();
    ecommerce_cart_set([]);
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
    if ($uid >= 0) {
        app_user_log($ithanhloc, $uid, 'cart_clear', 'Xóa toàn bộ giỏ hàng', [
            'count' => $beforeCount
        ]);
    }
    jOut(buildCartResponse($ithanhloc, []));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_remove') {
    $key = (string)($_REQUEST['key'] ?? '');
    $cart = ecommerce_cart_get();
    $removed = null;
    foreach ($cart as $it) {
        if ((string)($it['key'] ?? '') === $key) { $removed = $it; break; }
    }
    
    // Nếu sản phẩm bị xóa là quà tặng BXGY, tìm promo_id liên quan để xóa luôn các sản phẩm chính kích hoạt nó
    $promoIdToRemove = 0;
    if ($removed) {
        $isGift = !empty($removed['is_gift']) || ((float)($removed['price'] ?? 0) <= 0.0);
        $isBxgyGift = $isGift && !empty($removed['bxgy_promo_id']);
        if ($isBxgyGift) {
            $promoIdToRemove = (int)$removed['bxgy_promo_id'];
        }
    }

    // Tính trước danh sách product_id chính kích hoạt promo (1 lần) thay vì gọi
    // bxgy_get_active_promos() lặp lại trong mỗi vòng filter (tránh N+1 query).
    $mainIdsToRemove = [];
    if ($promoIdToRemove > 0) {
        foreach (bxgy_get_active_promos($ithanhloc) as $promo) {
            if ((int)$promo['id'] === $promoIdToRemove) {
                $mainIdsToRemove = array_map('intval', (array)($promo['main_product_ids'] ?? []));
                break;
            }
        }
    }

    $cart = array_values(array_filter($cart, function($it) use ($key, $mainIdsToRemove){
        if ((string)($it['key'] ?? '') === $key) {
            return false;
        }
        if ($mainIdsToRemove) {
            $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
            if (in_array($pid, $mainIdsToRemove, true) && empty($it['is_gift'])) {
                return false;
            }
        }
        return true;
    }));
    
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    // Cập nhật lại quà BXGY và quà tặng hóa đơn khi xóa một dòng sản phẩm
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0 && $removed) {
        app_user_log($ithanhloc, $uid, 'cart_remove', 'Xóa sản phẩm khỏi giỏ', [
            'product_id' => (int)($removed['product_id'] ?? $removed['id'] ?? 0),
            'name' => (string)($removed['name'] ?? ''),
            'qty' => (int)($removed['qty'] ?? 0)
        ]);
    }
    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_remove_bulk') {
    $keys = $_REQUEST['keys'] ?? [];
    if (!is_array($keys)) {
        $keys = array_filter(array_map('trim', explode(',', (string)$keys)));
    }
    $keySet = array_flip(array_map('strval', $keys));
    $cart = ecommerce_cart_get();
    $beforeCount = count($cart);
    
    // Tìm các promo_id của các quà tặng BXGY bị xóa
    $promoIdsToRemove = [];
    foreach ($cart as $it) {
        $itKey = (string)($it['key'] ?? '');
        if (isset($keySet[$itKey])) {
            $isGift = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0);
            $isBxgyGift = $isGift && !empty($it['bxgy_promo_id']);
            if ($isBxgyGift) {
                $promoIdsToRemove[] = (int)$it['bxgy_promo_id'];
            }
        }
    }

    $cart = array_values(array_filter($cart, function($it) use ($keySet, $promoIdsToRemove, $ithanhloc){
        $itKey = (string)($it['key'] ?? '');
        if (isset($keySet[$itKey])) {
            return false;
        }
        if ($promoIdsToRemove) {
            $promos = bxgy_get_active_promos($ithanhloc);
            foreach ($promos as $promo) {
                if (in_array((int)$promo['id'], $promoIdsToRemove, true)) {
                    $mainIds = $promo['main_product_ids'] ?? [];
                    $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
                    if (in_array($pid, $mainIds, true) && empty($it['is_gift'])) {
                        return false;
                    }
                }
            }
        }
        return true;
    }));
    
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    // Cập nhật lại quà BXGY và quà tặng hóa đơn khi xóa nhiều dòng sản phẩm
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $removedCount = max(0, $beforeCount - count($cart));
        app_user_log($ithanhloc, $uid, 'cart_remove_bulk', 'Xóa nhiều sản phẩm khỏi giỏ', [
            'count' => $removedCount
        ]);
    }
    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_update_qty') {
    $key = (string)($_REQUEST['key'] ?? '');
    // V2: Giới hạn qty tối đa phía server
    $qty = max(1, min((int)($_REQUEST['qty'] ?? 1), CART_MAX_QTY_PER_ITEM));
    $cart = ecommerce_cart_get();
    $changed = null;
    $oldQty = 0;
    $msg = '';
    $removeKey = '';
    foreach ($cart as &$it) {
        if ((string)($it['key'] ?? '') === $key) {
            $oldQty = (int)($it['qty'] ?? 0);
            // Khóa số lượng cho sản phẩm tặng (quà tặng / deal miễn phí) ở mức 1
            if (!empty($it['is_gift']) || (float)($it['price'] ?? 0) <= 0.0) {
                $qty = 1;
            }

            // Enforce stock for variants (server-side)
            $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
            $variantId = (int)($it['variant_id'] ?? 0);
            // Hàng đặt trước: không enforce tồn kho khi cập nhật số lượng
            $isPreorderItem = ($pid > 0) && ecommerce_product_is_preorder($ithanhloc, $pid);
            if ($isPreorderItem) {
                $it['is_preorder'] = true;
                $it['qty'] = $qty;
            } elseif ($pid > 0 && $variantId > 0 && empty($it['is_gift'])) {
                $stmtS = $ithanhloc->prepare("SELECT stock_quantity FROM `{$variantTable}` WHERE id=? AND product_id=?{$variantActiveWhereExtra} LIMIT 1");
                if ($stmtS) {
                    $stmtS->bind_param('ii', $variantId, $pid);
                    $stmtS->execute();
                    $rowS = $stmtS->get_result()->fetch_assoc();
                    $stmtS->close();
                    $stock = isset($rowS['stock_quantity']) ? (int)$rowS['stock_quantity'] : 0;
                    if ($stock <= 0) {
                        $removeKey = $key;
                        $msg = 'Sản phẩm đã hết hàng và đã được xóa khỏi giỏ.';
                        break;
                    }
                    if ($qty > $stock) {
                        $qty = $stock;
                        $msg = 'Số lượng đã được điều chỉnh theo tồn kho.';
                    }
                }
            } elseif ($pid > 0 && $variantId <= 0 && empty($it['is_gift'])) {
                // Enforce stock for non-variant products (if stock column exists)
                $pCols = listColumns($ithanhloc, 'ecommerce_product');
                $stockCol = '';
                if ($pCols) {
                    foreach (['stock_quantity', 'stock', 'kho', 'ton_kho', 'so_luong'] as $c) {
                        if (hasCol($pCols, $c)) { $stockCol = $c; break; }
                    }
                }
                if ($stockCol !== '') {
                    $stmtP = $ithanhloc->prepare('SELECT `' . $stockCol . '` AS stock_qty FROM ecommerce_product WHERE id=? LIMIT 1');
                    if ($stmtP) {
                        $stmtP->bind_param('i', $pid);
                        $stmtP->execute();
                        $rowP = $stmtP->get_result()->fetch_assoc();
                        $stmtP->close();
                        $stock = isset($rowP['stock_qty']) ? (int)$rowP['stock_qty'] : 0;
                        if ($stock <= 0) {
                            $removeKey = $key;
                            $msg = 'Sản phẩm đã hết hàng và đã được xóa khỏi giỏ.';
                            break;
                        }
                        if ($qty > $stock) {
                            $qty = $stock;
                            $msg = 'Số lượng đã được điều chỉnh theo tồn kho.';
                        }
                    }
                }
            }
            $it['qty'] = $qty;
            $changed = $it;
            break;
        }
    }
    unset($it);

    if ($removeKey !== '') {
        $cart = array_values(array_filter($cart, function($it) use ($removeKey){
            return (string)($it['key'] ?? '') !== (string)$removeKey;
        }));
    }
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    // Đồng bộ quà tặng BXGY và quà tặng hóa đơn theo số lượng mới
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0 && $changed) {
        app_user_log($ithanhloc, $uid, 'cart_update_qty', 'Cập nhật số lượng sản phẩm', [
            'product_id' => (int)($changed['product_id'] ?? $changed['id'] ?? 0),
            'name' => (string)($changed['name'] ?? ''),
            'from' => $oldQty,
            'to' => $qty
        ]);
    }
    $extra = [];
    if ($msg !== '') $extra['msg'] = $msg;
    jOut(buildCartResponse($ithanhloc, $cart, $extra));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_update_variant') {
    $key = (string)($_REQUEST['key'] ?? '');
    $newVariantId = (int)($_REQUEST['variant_id'] ?? 0);
    if ($key === '' || $newVariantId <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu thông tin phân loại mới']);
    }

    $cart = ecommerce_cart_get();
    $foundIdx = -1;
    foreach ($cart as $idx => $it) {
        if ((string)($it['key'] ?? '') === $key) {
            $foundIdx = $idx;
            break;
        }
    }

    if ($foundIdx === -1) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm trong giỏ']);
    }

    $oldItem = $cart[$foundIdx];
    if (!empty($oldItem['is_gift']) || !empty($oldItem['is_combo'])) {
        jOut(['ok' => false, 'msg' => 'Không thể đổi phân loại cho sản phẩm này']);
    }

    $pid = (int)($oldItem['product_id'] ?? $oldItem['id'] ?? 0);
    $qty = (int)($oldItem['qty'] ?? 1);
    $color = (string)($oldItem['color_code'] ?? '');

    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $newVariantId, $qty, $color);
    if (!$newItem) {
        jOut(['ok' => false, 'msg' => 'Không thể tạo phân loại mới']);
    }

    unset($cart[$foundIdx]);
    $merged = false;
    foreach ($cart as &$it) {
        if ((string)($it['key'] ?? '') === (string)$newItem['key']) {
            $it['qty'] += $newItem['qty'];
            $merged = true;
            break;
        }
    }
    unset($it);

    if (!$merged) {
        $cart[] = $newItem;
    }

    $cart = array_values($cart);
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_update_variant', 'Thay đổi phân loại sản phẩm', [
            'product_id' => $pid,
            'old_variant_id' => (int)($oldItem['variant_id'] ?? 0),
            'new_variant_id' => $newVariantId
        ]);
    }
    jOut(buildCartResponse($ithanhloc, $cart, ['msg' => 'Đã cập nhật phân loại']));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_add') {
    $pid = (int)($_REQUEST['pid'] ?? 0);
    $variantId = (int)($_REQUEST['variant_id'] ?? 0);
    // V2: Giới hạn qty tối đa phía server
    $qty = max(1, min((int)($_REQUEST['qty'] ?? 1), CART_MAX_QTY_PER_ITEM));
    $colorCode = (string)($_REQUEST['color_code'] ?? '');
    $bxgyGiftChoiceRaw = $_REQUEST['bxgy_gift_choice'] ?? null;
    $bxgyGiftChoiceVariantRaw = $_REQUEST['bxgy_gift_choice_variant'] ?? null;
    if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);

    // V10: Giới hạn số lượng dòng trong giỏ hàng
    $currentCart = ecommerce_cart_get();
    $nonGiftCount = count(array_filter($currentCart, fn($it) => empty($it['is_gift'])));
    $isNewKey = true;
    $newKey = $pid . ':' . $variantId . ':' . strtoupper((string)$colorCode);
    foreach ($currentCart as $existIt) {
        if ((string)($existIt['key'] ?? '') === $newKey) { $isNewKey = false; break; }
    }
    if ($isNewKey && $nonGiftCount >= CART_MAX_ITEMS) {
        jOut(['ok' => false, 'msg' => 'Giỏ hàng đã đạt giới hạn tối đa ' . CART_MAX_ITEMS . ' loại sản phẩm. Vui lòng xóa bớt trước khi thêm mới.']);
    }

    // If client did not send variant_id, auto-pick the cheapest active variant
    if ($variantId <= 0 && $variantTable !== '') {
        $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
        if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
            $stmtGV->bind_param('i', $pid);
            $stmtGV->execute();
            $rowGV = $stmtGV->get_result()->fetch_assoc();
            $stmtGV->close();
            if ($rowGV && !empty($rowGV['id'])) {
                $variantId = (int)$rowGV['id'];
            }
        }
    }

    // Variant is required for purchase. If product has no active variant, block add-to-cart.
    if ($variantTable !== '' && $variantId <= 0) {
        $hasActiveVariant = false;
        $stmtHasV = $ithanhloc->prepare("SELECT 1 FROM `{$variantTable}` WHERE product_id=?{$variantActiveWhereExtra} LIMIT 1");
        if ($stmtHasV) {
            $stmtHasV->bind_param('i', $pid);
            $stmtHasV->execute();
            $hasActiveVariant = (bool)$stmtHasV->get_result()->fetch_assoc();
            $stmtHasV->close();
        }
        jOut(['ok' => false, 'msg' => $hasActiveVariant
            ? 'Vui lòng chọn phân loại (biến thể) của sản phẩm trước khi thêm vào giỏ'
            : 'Sản phẩm chưa có phân loại (biến thể) nên không thể thêm vào giỏ'
        ]);
    }

    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qty, $colorCode);
    if (!$newItem) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    if ((int)($newItem['qty'] ?? 0) <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
    }
    if ((float)($newItem['price'] ?? 0) <= 0.0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm chưa có giá, vui lòng liên hệ cửa hàng trước khi đặt hàng.']);
    }

    // Lưu lựa chọn quà BXGY theo promo_id (nếu client gửi)
    $bxgyGiftChoice = [];
    if ($bxgyGiftChoiceRaw !== null && $bxgyGiftChoiceRaw !== '') {
        if (is_array($bxgyGiftChoiceRaw)) {
            $bxgyGiftChoice = $bxgyGiftChoiceRaw;
        } else {
            $tmp = json_decode((string)$bxgyGiftChoiceRaw, true);
            if (is_array($tmp)) $bxgyGiftChoice = $tmp;
        }
    }
    if (is_array($bxgyGiftChoice) && $bxgyGiftChoice) {
        $clean = [];
        foreach ($bxgyGiftChoice as $k => $v) {
            $promoId = (int)$k;
            $giftPid = (int)$v;
            if ($promoId > 0 && $giftPid > 0) $clean[$promoId] = $giftPid;
        }
        if ($clean) $newItem['bxgy_gift_choice'] = $clean;
    }

    // V5+V6: Lưu và validate lựa chọn phân loại quà BXGY theo promo_id
    $bxgyGiftChoiceVariant = [];
    if ($bxgyGiftChoiceVariantRaw !== null && $bxgyGiftChoiceVariantRaw !== '') {
        if (is_array($bxgyGiftChoiceVariantRaw)) {
            $bxgyGiftChoiceVariant = $bxgyGiftChoiceVariantRaw;
        } else {
            $tmp = json_decode((string)$bxgyGiftChoiceVariantRaw, true);
            if (is_array($tmp)) $bxgyGiftChoiceVariant = $tmp;
        }
    }
    if (is_array($bxgyGiftChoiceVariant) && $bxgyGiftChoiceVariant) {
        // V5+V6: Lấy cấu hình tất cả promo BXGY để validate variant choice
        $allBxgyPromos = [];
        try { $allBxgyPromos = bxgy_get_active_promos($ithanhloc); } catch (Throwable $e) {}
        $allBxgyPromoMap = [];
        if (is_array($allBxgyPromos)) {
            foreach ($allBxgyPromos as $bp) {
                $bpId = (int)($bp['id'] ?? 0);
                if ($bpId > 0) $allBxgyPromoMap[$bpId] = $bp;
            }
        }

        $clean = [];
        foreach ($bxgyGiftChoiceVariant as $k => $v) {
            $promoId = (int)$k;
            $giftVid = (int)$v;
            if ($promoId <= 0 || $giftVid <= 0) continue;

            // Validate: giftVid phải thuộc danh sách gift_variant_ids của promo (nếu cấu hình)
            $bpCfg = $allBxgyPromoMap[$promoId] ?? null;
            if (is_array($bpCfg)) {
                // Lấy giftPid tương ứng để tra cứu allowed variant IDs
                $chosenGiftPid = (int)(($bxgyGiftChoice[$promoId] ?? $bxgyGiftChoiceRaw[$promoId] ?? 0));
                if ($chosenGiftPid <= 0 && isset($bpCfg['gift_product_id'])) {
                    $chosenGiftPid = (int)$bpCfg['gift_product_id'];
                }
                $giftVariantMap = isset($bpCfg['gift_variant_ids']) && is_array($bpCfg['gift_variant_ids'])
                    ? $bpCfg['gift_variant_ids'] : [];
                $allowedVids = [];
                if ($chosenGiftPid > 0 && $giftVariantMap) {
                    $key1 = $chosenGiftPid;
                    $key2 = (string)$chosenGiftPid;
                    if (isset($giftVariantMap[$key1]) && is_array($giftVariantMap[$key1])) {
                        $allowedVids = array_map('intval', $giftVariantMap[$key1]);
                    } elseif (isset($giftVariantMap[$key2]) && is_array($giftVariantMap[$key2])) {
                        $allowedVids = array_map('intval', $giftVariantMap[$key2]);
                    }
                }
                // Nếu promo có cấu hình gift_variant_ids, validate; nếu không, chấp nhận mọi variant hợp lệ
                if ($allowedVids && !in_array($giftVid, $allowedVids, true)) {
                    // Override về variant đầu tiên được phép thay vì báo lỗi
                    $giftVid = $allowedVids[0];
                }
            }
            $clean[$promoId] = $giftVid;
        }
        if ($clean) $newItem['bxgy_gift_choice_variant'] = $clean;
    }

    $cart = ecommerce_cart_get();
    $found = false;
    foreach ($cart as &$it) {
        if ((string)($it['key'] ?? '') === (string) $newItem['key']) {
            $candidateQty = (int)($it['qty'] ?? 0) + (int)($newItem['qty'] ?? 0);
            // V2: Clamp theo CART_MAX_QTY_PER_ITEM trước khi check stock
            $candidateQty = min($candidateQty, CART_MAX_QTY_PER_ITEM);
            $maxStock = $newItem['stock_quantity'] ?? null;
            if ($maxStock !== null && empty($newItem['is_preorder'])) {
                $maxStock = (int)$maxStock;
                if ($maxStock <= 0) {
                    jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
                }
                $candidateQty = min($candidateQty, $maxStock);
            }
            $it['qty'] = max(1, $candidateQty);
            if (!empty($newItem['is_preorder'])) $it['is_preorder'] = true;
            $it['price'] = (float)$newItem['price'];
            $it['payment_options'] = $newItem['payment_options'];
            if (isset($newItem['bxgy_gift_choice'])) {
                $it['bxgy_gift_choice'] = $newItem['bxgy_gift_choice'];
            }
            if (isset($newItem['bxgy_gift_choice_variant'])) {
                $it['bxgy_gift_choice_variant'] = $newItem['bxgy_gift_choice_variant'];
            }
            $found = true;
            break;
        }
    }
    unset($it);
    if (!$found) $cart[] = $newItem;
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    // Sau khi thêm sản phẩm chính, tự động cộng quà BXGY và quà tặng hóa đơn nếu đủ điều kiện
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_add', 'Thêm sản phẩm vào giỏ', [
            'product_id' => (int)($newItem['product_id'] ?? $newItem['id'] ?? 0),
            'name' => (string)($newItem['name'] ?? ''),
            'qty' => (int)($newItem['qty'] ?? 0)
        ]);
    }
    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_add_free') {
    $pid = (int)($_REQUEST['pid'] ?? 0);
    $variantId = (int)($_REQUEST['variant_id'] ?? 0);
    $mainVariantId = (int)($_REQUEST['main_variant_id'] ?? 0); // Nhận main_variant_id từ frontend
    $qty = 1; // tối đa 1 sản phẩm quà tặng
    $colorCode = (string)($_REQUEST['color_code'] ?? '');
    if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Không có sản phẩm để thêm vào giỏ']);


    // Validate: sản phẩm phải nằm trong chiến dịch gift/bxgy đang hoạt động
    $isValidGiftPid = false;
    $promoTypeFound = '';
    $matchedPromo = null;
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
        if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
            $nowChk = nowStr();
            $stmtChk = $ithanhloc->prepare("SELECT promo_type, config_json FROM ecommerce_product_promo WHERE promo_type IN ('gift','bxgy') AND is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)");
            if ($stmtChk) {
                $stmtChk->bind_param('ss', $nowChk, $nowChk);
                $stmtChk->execute();
                $resChk = $stmtChk->get_result();
                while ($rowChk = $resChk->fetch_assoc()) {
                    $cfgChk = json_decode((string)($rowChk['config_json'] ?? ''), true);
                    if (!is_array($cfgChk)) continue;
                    $giftIds = [];
                    if (!empty($cfgChk['gift_product_ids']) && is_array($cfgChk['gift_product_ids'])) {
                        $giftIds = array_map('intval', $cfgChk['gift_product_ids']);
                    }
                    $legacyGid = (int)($cfgChk['gift_product_id'] ?? 0);
                    if ($legacyGid > 0) $giftIds[] = $legacyGid;
                    if (in_array($pid, $giftIds, true)) {
                        $isValidGiftPid = true;
                        $promoTypeFound = $rowChk['promo_type'];
                        $matchedPromo = $cfgChk;
                        break;
                    }
                }
                $stmtChk->close();
            }
        }
    } catch (Throwable $e) {}
    if (!$isValidGiftPid) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm không nằm trong chương trình quà tặng đang hoạt động']);
    }

    // Nếu là chiến dịch BXGY, kiểm tra xem giỏ hàng đã đủ điều kiện nhận quà chưa
    if ($promoTypeFound === 'bxgy' && is_array($matchedPromo)) {
        $buyQty = max(1, (int)($matchedPromo['buy_qty'] ?? 0));
        $mainProductIds = isset($matchedPromo['main_product_ids']) && is_array($matchedPromo['main_product_ids'])
            ? array_map('intval', $matchedPromo['main_product_ids'])
            : [];
        
        $cart = ecommerce_cart_get();
        $currentMainQty = 0;
        if (is_array($cart)) {
            foreach ($cart as $it) {
                if (!is_array($it)) continue;
                if (!empty($it['is_gift'])) continue;
                $itPid = (int)($it['product_id'] ?? $it['id'] ?? 0);
                if ($itPid > 0 && in_array($itPid, $mainProductIds, true)) {
                    $currentMainQty += (int)($it['qty'] ?? 0);
                }
            }
        }
        
        if ($currentMainQty < $buyQty) {
            jOut(['ok' => false, 'msg' => 'Bạn chưa đủ điều kiện mua hàng để nhận sản phẩm quà tặng này (yêu cầu mua tối thiểu ' . $buyQty . ' sản phẩm chính).']);
        }
    }

    // Nếu là chiến dịch quà tặng hóa đơn, kiểm tra xem tổng đơn có đạt ngưỡng tối thiểu
    if ($promoTypeFound === 'gift' && is_array($matchedPromo)) {
        $threshold = (int)($matchedPromo['threshold_amount'] ?? 0);
        if ($threshold > 0) {
            $cart = ecommerce_cart_get();
            $subtotal = 0.0;
            if (is_array($cart)) {
                foreach ($cart as $it) {
                    if (!is_array($it)) continue;
                    $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0 && (int)($it['qty'] ?? 0) === 1);
                    if ($isGiftItem) continue;
                    $subtotal += (float)($it['price'] ?? 0) * (int)($it['qty'] ?? 0);
                }
            }
            if ($subtotal < $threshold) {
                jOut(['ok' => false, 'msg' => 'Đơn hàng cần đạt tối thiểu ' . number_format($threshold, 0, ',', '.') . 'đ để nhận quà tặng.']);
            }
        }
    }
    // Nếu chưa chỉ định variant_id, tự chọn biến thể rẻ nhất (đang bán)
    if ($variantId <= 0 && $variantTable !== '') {
        $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
        if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
            $stmtGV->bind_param('i', $pid);
            $stmtGV->execute();
            $rowGV = $stmtGV->get_result()->fetch_assoc();
            $stmtGV->close();
            if ($rowGV && !empty($rowGV['id'])) {
                $variantId = (int)$rowGV['id'];
            }
        }
    }

    if ($variantTable !== '' && $variantId <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm chưa có phân loại (biến thể) nên không thể thêm vào giỏ']);
    }

    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qty, $colorCode);
    if (!$newItem) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);

    // Ép giá về 0đ cho sản phẩm deal miễn phí và đánh dấu là quà tặng
    $newItem['price'] = 0.0;
    $newItem['qty'] = 1;
    $newItem['is_gift'] = 1;
    if ($mainVariantId > 0) {
        $newItem['main_variant_id'] = $mainVariantId;
    }
    // Key riêng cho dòng quà -> không bao giờ trùng/đè lên dòng mua cùng product+variant
    $newItem['key'] = (string)($newItem['key'] ?? ($pid . ':' . $variantId . ':')) . '|GIFT';

    $cart = ecommerce_cart_get();

    $found = false;
    foreach ($cart as &$it) {
        // Chỉ gộp với dòng quà (cùng key |GIFT), tuyệt đối không chạm dòng mua tính tiền
        if (!empty($it['is_gift']) && (string)($it['key'] ?? '') === (string)$newItem['key']) {
            // Giới hạn tối đa 1 sản phẩm quà tặng
            $it['qty'] = 1;
            $it['price'] = 0.0;
            $it['is_gift'] = 1;
            $it['payment_options'] = $newItem['payment_options'];
            $found = true;
            break;
        }
    }
    unset($it);
    if (!$found) $cart[] = $newItem;
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_add_free', 'Thêm sản phẩm deal miễn phí vào giỏ', [
            'product_id' => (int)($newItem['product_id'] ?? $newItem['id'] ?? 0),
            'name' => (string)($newItem['name'] ?? ''),
            'qty' => (int)($newItem['qty'] ?? 0)
        ]);
    }
    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_add_combo') {
    $pid = (int)($_REQUEST['pid'] ?? 0);
    $mainPid = (int)($_REQUEST['main_pid'] ?? 0);
    $variantId = (int)($_REQUEST['variant_id'] ?? 0);
    $mainVariantId = (int)($_REQUEST['main_variant_id'] ?? 0);
    $qty = 1; // mỗi deal chỉ tối đa 1 sản phẩm trong mỗi lần áp dụng
    $colorCode = (string)($_REQUEST['color_code'] ?? '');
    if ($pid <= 0 || $mainPid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm hoặc sản phẩm chính']);

    // Yêu cầu trong giỏ phải có sản phẩm chính thì mới cho áp dụng giá deal
    $cart = ecommerce_cart_get();
    $hasMain = false;
    foreach ($cart as $it) {
        $pIdIt = (int)($it['product_id'] ?? $it['id'] ?? 0);
        if ($pIdIt === $mainPid) {
            $hasMain = true;
            break;
        }
    }
    if (!$hasMain) {
        jOut(['ok' => false, 'msg' => 'Bạn cần có sản phẩm chính trong giỏ để áp dụng deal combo này']);
    }

    // Tìm chiến dịch combo hợp lệ và lấy giá khuyến mãi từ server (không thông tin client)
    $promoPrice = null;
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
    } catch (Throwable $e) {
        $hasPromoTbl = false;
    }
    if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
        $nowPromo = nowStr();
        $stmtPromo = $ithanhloc->prepare("SELECT config_json FROM ecommerce_product_promo WHERE promo_type = 'combo' AND is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)");
        if ($stmtPromo) {
            $stmtPromo->bind_param('ss', $nowPromo, $nowPromo);
            $stmtPromo->execute();
            $resPromo = $stmtPromo->get_result();
            while ($rowPromo = $resPromo->fetch_assoc()) {
                $cfg = json_decode((string)($rowPromo['config_json'] ?? ''), true);
                if (!is_array($cfg)) continue;
                $mainSet = [];
                if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                    foreach ($cfg['main_product_ids'] as $mid) {
                        $mid = (int)$mid;
                        if ($mid > 0) $mainSet[$mid] = true;
                    }
                }
                $legacyMain = (int)($cfg['main_product_id'] ?? 0);
                if ($legacyMain > 0) $mainSet[$legacyMain] = true;
                if (!$mainSet || empty($mainSet[$mainPid]) || empty($cfg['items']) || !is_array($cfg['items'])) continue;
                foreach ($cfg['items'] as $it) {
                    $itPid = (int)($it['product_id'] ?? 0);
                    if ($itPid === $pid) {
                        $promoPrice = (int)($it['promo_price'] ?? -1);
                        break 2;
                    }
                }
            }
            $stmtPromo->close();
        }
    }

    if ($promoPrice === null || $promoPrice < 0) {
        jOut(['ok' => false, 'msg' => 'Deal không còn hiệu lực hoặc không hợp lệ']);
    }

    // Nếu chưa chỉ định variant_id, tự chọn biến thể rẻ nhất (đang bán)
    if ($variantId <= 0 && $variantTable !== '') {
        $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
        if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
            $stmtGV->bind_param('i', $pid);
            $stmtGV->execute();
            $rowGV = $stmtGV->get_result()->fetch_assoc();
            $stmtGV->close();
            if ($rowGV && !empty($rowGV['id'])) {
                $variantId = (int)$rowGV['id'];
            }
        }
    }

    if ($variantTable !== '' && $variantId <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm chưa có phân loại (biến thể) nên không thể thêm vào giỏ']);
    }

    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qty, $colorCode);
    if (!$newItem) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    if ((int)($newItem['qty'] ?? 0) <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
    }

    // Gán giá deal và đánh dấu là sản phẩm combo
    $newItem['price'] = (float)$promoPrice;
    $newItem['qty'] = 1;
    $newItem['is_combo'] = 1;

    $found = false;
    foreach ($cart as &$it) {
        if ((string)($it['key'] ?? '') === (string)$newItem['key']) {
            // Giới hạn tối đa 1 sản phẩm combo cho mỗi deal
            $it['qty'] = 1;
            $it['price'] = (float)$newItem['price'];
            $it['payment_options'] = $newItem['payment_options'];
            $it['is_combo'] = 1;
            $found = true;
            break;
        }
    }
    unset($it);
    if (!$found) $cart[] = $newItem;
    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    // Đồng bộ quà BXGY và quà tặng hóa đơn sau khi thêm deal combo
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_add_combo', 'Thêm sản phẩm deal combo vào giỏ', [
            'product_id' => (int)($newItem['product_id'] ?? $newItem['id'] ?? 0),
            'name' => (string)($newItem['name'] ?? ''),
            'qty' => (int)($newItem['qty'] ?? 0),
            'main_product_id' => $mainPid,
        ]);
    }
    jOut(buildCartResponse($ithanhloc, $cart));
}

// Change variant for gift items (invoice gifts or BXGY gifts)
// - Invoice/normal gifts: allow any variant of the gift product
// - BXGY gifts: only allow variants within promo config (if configured), and update bxgy choice maps then resync gifts
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_change_gift_variant') {
    global $variantTable, $variantActiveWhereExtra;

    $key = (string)($_REQUEST['key'] ?? '');
    $variantIdReq = (int)($_REQUEST['variant_id'] ?? 0);
    if ($key === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu dòng giỏ hàng']);
    }

    $cart = ecommerce_cart_get();
    $idxFound = -1;
    $item = null;
    foreach ($cart as $i => $it) {
        if ((string)($it['key'] ?? '') === $key) {
            $idxFound = (int)$i;
            $item = $it;
            break;
        }
    }
    if ($idxFound < 0 || !is_array($item)) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy dòng quà tặng để cập nhật']);
    }

    $pid = (int)($item['product_id'] ?? $item['id'] ?? 0);
    if ($pid <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm không hợp lệ']);
    }

    $isGift = !empty($item['is_gift']) || ((float)($item['price'] ?? 0) <= 0.0);
    if (!$isGift) {
        jOut(['ok' => false, 'msg' => 'Dòng này không phải quà tặng']);
    }

    // Resolve variant_id: allow 0 to mean "pick default active variant"
    $variantId = $variantIdReq;
    if ($variantId <= 0 && $variantTable !== '') {
        $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
        if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
            $stmtGV->bind_param('i', $pid);
            $stmtGV->execute();
            $rowGV = $stmtGV->get_result()->fetch_assoc();
            $stmtGV->close();
            if ($rowGV && !empty($rowGV['id'])) {
                $variantId = (int)$rowGV['id'];
            }
        }
    }

    // Validate that variant belongs to product (when variants exist)
    if ($variantId > 0 && $variantTable !== '') {
        $stmtV = $ithanhloc->prepare("SELECT 1 FROM `{$variantTable}` WHERE id=? AND product_id=?{$variantActiveWhereExtra} LIMIT 1");
        if (!$stmtV) {
            jOut(['ok' => false, 'msg' => 'Không thể kiểm tra phân loại']);
        }
        $stmtV->bind_param('ii', $variantId, $pid);
        $stmtV->execute();
        $row = $stmtV->get_result()->fetch_assoc();
        $stmtV->close();
        if (!$row) {
            jOut(['ok' => false, 'msg' => 'Phân loại không hợp lệ']);
        }
    }

    $promoId = (int)($item['bxgy_promo_id'] ?? 0);
    if ($promoId > 0) {
        // BXGY gift: enforce promo rules and persist choice into base lines, then resync gifts
        $promos = bxgy_get_active_promos($ithanhloc);
        $promo = null;
        foreach ($promos as $p) {
            if (is_array($p) && (int)($p['id'] ?? 0) === $promoId) { $promo = $p; break; }
        }
        if (!is_array($promo)) {
            jOut(['ok' => false, 'msg' => 'Chương trình quà tặng không còn hiệu lực']);
        }

        $giftProductIdsCfg = isset($promo['gift_product_ids']) && is_array($promo['gift_product_ids'])
            ? array_values(array_filter(array_map('intval', $promo['gift_product_ids']), fn($v) => $v > 0))
            : [];
        $giftProductIdCfg = (int)($promo['gift_product_id'] ?? 0);

        // If promo explicitly defines gift products, enforce that current gift product is allowed
        if ($giftProductIdsCfg) {
            if (!in_array($pid, $giftProductIdsCfg, true)) {
                jOut(['ok' => false, 'msg' => 'Quà tặng không thuộc danh sách chương trình']);
            }
        } elseif ($giftProductIdCfg > 0) {
            if ($pid !== $giftProductIdCfg) {
                jOut(['ok' => false, 'msg' => 'Quà tặng không thuộc chương trình']);
            }
        }

        // Enforce allowed gift variants if configured
        $giftVariantMap = isset($promo['gift_variant_ids']) && is_array($promo['gift_variant_ids']) ? $promo['gift_variant_ids'] : [];
        $allowedGiftVariants = [];
        if ($giftVariantMap && isset($giftVariantMap[$pid]) && is_array($giftVariantMap[$pid])) {
            $allowedGiftVariants = array_values(array_filter(array_map('intval', $giftVariantMap[$pid]), fn($v) => $v > 0));
        } elseif ($giftVariantMap && isset($giftVariantMap[(string)$pid]) && is_array($giftVariantMap[(string)$pid])) {
            $allowedGiftVariants = array_values(array_filter(array_map('intval', $giftVariantMap[(string)$pid]), fn($v) => $v > 0));
        }
        if ($variantId > 0 && $allowedGiftVariants && !in_array($variantId, $allowedGiftVariants, true)) {
            jOut(['ok' => false, 'msg' => 'Phân loại quà tặng không hợp lệ theo chương trình']);
        }

        $buyQty = max(1, (int)($promo['buy_qty'] ?? 0));
        $mainIds = isset($promo['main_product_ids']) && is_array($promo['main_product_ids'])
            ? array_values(array_filter(array_map('intval', $promo['main_product_ids']), fn($v) => $v > 0))
            : [];
        $mainVariantMap = isset($promo['main_variant_ids']) && is_array($promo['main_variant_ids']) ? $promo['main_variant_ids'] : [];

        $baseKey = '';
        $parts = explode('|BXGY-', $key);
        if (count($parts) > 1) {
            $baseKey = $parts[0];
        }

        $updatedBase = false;
        if ($baseKey !== '') {
            foreach ($cart as &$it) {
                if ((string)($it['key'] ?? '') === $baseKey) {
                    $choice = $it['bxgy_gift_choice'] ?? [];
                    if (is_string($choice) && $choice !== '') {
                        $tmp = json_decode($choice, true);
                        if (is_array($tmp)) $choice = $tmp;
                    }
                    if (!is_array($choice)) $choice = [];
                    $choice[$promoId] = $pid;
                    $it['bxgy_gift_choice'] = $choice;

                    $choiceVar = $it['bxgy_gift_choice_variant'] ?? [];
                    if (is_string($choiceVar) && $choiceVar !== '') {
                        $tmp = json_decode($choiceVar, true);
                        if (is_array($tmp)) $choiceVar = $tmp;
                    }
                    if (!is_array($choiceVar)) $choiceVar = [];
                    $choiceVar[$promoId] = $variantId;
                    $it['bxgy_gift_choice_variant'] = $choiceVar;

                    $updatedBase = true;
                    break;
                }
            }
            unset($it);
        }

        if (!$updatedBase) {
            foreach ($cart as &$it) {
                if (!is_array($it)) continue;
                if (!empty($it['is_gift'])) continue;
                if (!empty($it['is_combo'])) continue;
                $pidIt = (int)($it['product_id'] ?? $it['id'] ?? 0);
                if ($pidIt <= 0 || !$mainIds || !in_array($pidIt, $mainIds, true)) continue;
                $qtyIt = (int)($it['qty'] ?? 0);
                $priceIt = (float)($it['price'] ?? 0);
                if ($qtyIt < $buyQty || $priceIt <= 0) continue;

                // If promo restricts main variants, enforce
                $allowedMain = [];
                if ($mainVariantMap && isset($mainVariantMap[$pidIt]) && is_array($mainVariantMap[$pidIt])) {
                    $allowedMain = array_values(array_filter(array_map('intval', $mainVariantMap[$pidIt]), fn($v) => $v > 0));
                } elseif ($mainVariantMap && isset($mainVariantMap[(string)$pidIt]) && is_array($mainVariantMap[(string)$pidIt])) {
                    $allowedMain = array_values(array_filter(array_map('intval', $mainVariantMap[(string)$pidIt]), fn($v) => $v > 0));
                }
                if ($allowedMain) {
                    $vidIt = (int)($it['variant_id'] ?? 0);
                    if ($vidIt <= 0 || !in_array($vidIt, $allowedMain, true)) {
                        continue;
                    }
                }

                $choice = $it['bxgy_gift_choice'] ?? [];
                if (is_string($choice) && $choice !== '') {
                    $tmp = json_decode($choice, true);
                    if (is_array($tmp)) $choice = $tmp;
                }
                if (!is_array($choice)) $choice = [];
                $choice[$promoId] = $pid;
                $it['bxgy_gift_choice'] = $choice;

                $choiceVar = $it['bxgy_gift_choice_variant'] ?? [];
                if (is_string($choiceVar) && $choiceVar !== '') {
                    $tmp = json_decode($choiceVar, true);
                    if (is_array($tmp)) $choiceVar = $tmp;
                }
                if (!is_array($choiceVar)) $choiceVar = [];
                $choiceVar[$promoId] = $variantId;
                $it['bxgy_gift_choice_variant'] = $choiceVar;
                break;
            }
            unset($it);
        }

        $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
        ecommerce_cart_set($cart);
        $cart = syncBxgyGiftsToSessionCart($ithanhloc);
        $cart = syncInvoiceGiftToSessionCart($ithanhloc);
        jOut(buildCartResponse($ithanhloc, $cart));
    }

    // Non-BXGY gift: rebuild item with requested variant and keep it as gift
    $qty = (int)($item['qty'] ?? 1);
    if ($qty <= 0) $qty = 1;
    $colorCode = (string)($item['color_code'] ?? '');
    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qty, $colorCode);
    if (!$newItem) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    }
    $newItem['price'] = 0.0;
    $newItem['is_gift'] = 1;
    // Key riêng cho dòng quà -> không trùng/đè dòng mua cùng product+variant
    $newItem['key'] = (string)($newItem['key'] ?? ($pid . ':' . $variantId . ':')) . '|GIFT';

    unset($cart[$idxFound]);
    $cart = array_values($cart);
    $cart[] = $newItem;

    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_change_variant') {
    $oldKey = (string)($_REQUEST['key'] ?? '');
    $pid = (int)($_REQUEST['pid'] ?? 0);
    $variantId = (int)($_REQUEST['variant_id'] ?? 0);
    $qty = (int)($_REQUEST['qty'] ?? 1);
    $colorCode = (string)($_REQUEST['color_code'] ?? '');
    if ($oldKey === '' || $pid <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu thông tin sản phẩm hoặc dòng giỏ hàng']);
    }
    if ($qty <= 0) $qty = 1;

    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qty, $colorCode);
    if (!$newItem) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    if ((int)($newItem['qty'] ?? 0) <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
    }
    if ((float)($newItem['price'] ?? 0) <= 0.0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm chưa có giá, vui lòng liên hệ cửa hàng trước khi đặt hàng.']);
    }

    $cart = ecommerce_cart_get();

    // Giữ lại lựa chọn quà BXGY trên dòng cũ (nếu có)
    $oldBxgyChoice = null;
    $oldBxgyChoiceVar = null;
    foreach ($cart as $it) {
        if ((string)($it['key'] ?? '') !== $oldKey) continue;
        $oldBxgyChoice = $it['bxgy_gift_choice'] ?? null;
        $oldBxgyChoiceVar = $it['bxgy_gift_choice_variant'] ?? null;
        break;
    }
    if ($oldBxgyChoice !== null && !isset($newItem['bxgy_gift_choice'])) {
        $newItem['bxgy_gift_choice'] = $oldBxgyChoice;
    }
    if ($oldBxgyChoiceVar !== null && !isset($newItem['bxgy_gift_choice_variant'])) {
        $newItem['bxgy_gift_choice_variant'] = $oldBxgyChoiceVar;
    }

    // Xoá dòng cũ theo key
    foreach ($cart as $idx => $it) {
        if ((string)($it['key'] ?? '') === $oldKey) {
            unset($cart[$idx]);
            break;
        }
    }
    $cart = array_values($cart);

    // Gộp vào dòng mới nếu đã có cùng key, giống logic cart_add
    $found = false;
    foreach ($cart as &$it) {
        if ((string)($it['key'] ?? '') === (string)$newItem['key']) {
            $candidateQty = (int)($it['qty'] ?? 0) + (int)($newItem['qty'] ?? 0);
            $maxStock = $newItem['stock_quantity'] ?? null;
            if ($maxStock !== null && empty($newItem['is_preorder'])) {
                $maxStock = (int)$maxStock;
                if ($maxStock <= 0) {
                    jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
                }
                $candidateQty = min($candidateQty, $maxStock);
            }
            $it['qty'] = max(1, $candidateQty);
            if (!empty($newItem['is_preorder'])) $it['is_preorder'] = true;
            $it['price'] = (float)$newItem['price'];
            $it['payment_options'] = $newItem['payment_options'];

            // Merge lựa chọn BXGY nếu có
            if (isset($newItem['bxgy_gift_choice'])) {
                $it['bxgy_gift_choice'] = $newItem['bxgy_gift_choice'];
            }
            if (isset($newItem['bxgy_gift_choice_variant'])) {
                $it['bxgy_gift_choice_variant'] = $newItem['bxgy_gift_choice_variant'];
            }

            $found = true;
            break;
        }
    }
    unset($it);
    if (!$found) $cart[] = $newItem;

    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);
    // Đồng bộ lại quà BXGY và quà tặng hóa đơn sau khi đổi phân loại
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_change_variant', 'Đổi phân loại sản phẩm trong giỏ', [
            'product_id' => (int)($newItem['product_id'] ?? $newItem['id'] ?? 0),
            'qty' => (int)($newItem['qty'] ?? 0),
        ]);
    }

    jOut(buildCartResponse($ithanhloc, $cart));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_set_single') {
    $pid = (int)($_REQUEST['pid'] ?? 0);
    $variantId = (int)($_REQUEST['variant_id'] ?? 0);
    // Clamp số lượng theo giới hạn server (giống cart_add) — trước đây "Mua ngay" thiếu chặn này.
    $qty = max(1, min((int)($_REQUEST['qty'] ?? 1), CART_MAX_QTY_PER_ITEM));
    $colorCode = (string)($_REQUEST['color_code'] ?? '');
    $bxgyGiftChoiceRaw = $_REQUEST['bxgy_gift_choice'] ?? null;
    $bxgyGiftChoiceVariantRaw = $_REQUEST['bxgy_gift_choice_variant'] ?? null;
    if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);

    // If product has variants but client did not send variant_id, auto-pick default active variant
    if ($variantId <= 0 && $variantTable !== '') {
        $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
        if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
            $stmtGV->bind_param('i', $pid);
            $stmtGV->execute();
            $rowGV = $stmtGV->get_result()->fetch_assoc();
            $stmtGV->close();
            if ($rowGV && !empty($rowGV['id'])) {
                $variantId = (int)$rowGV['id'];
            }
        }
    }
    $newItem = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qty, $colorCode);
    if (!$newItem) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    if ((int)($newItem['qty'] ?? 0) <= 0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
    }
    if ((float)($newItem['price'] ?? 0) <= 0.0) {
        jOut(['ok' => false, 'msg' => 'Sản phẩm chưa có giá, vui lòng liên hệ cửa hàng trước khi đặt hàng.']);
    }

    // Lưu lựa chọn quà BXGY theo promo_id (nếu client gửi)
    $bxgyGiftChoice = [];
    if ($bxgyGiftChoiceRaw !== null && $bxgyGiftChoiceRaw !== '') {
        if (is_array($bxgyGiftChoiceRaw)) {
            $bxgyGiftChoice = $bxgyGiftChoiceRaw;
        } else {
            $tmp = json_decode((string)$bxgyGiftChoiceRaw, true);
            if (is_array($tmp)) $bxgyGiftChoice = $tmp;
        }
    }
    if (is_array($bxgyGiftChoice) && $bxgyGiftChoice) {
        $clean = [];
        foreach ($bxgyGiftChoice as $k => $v) {
            $promoId = (int)$k;
            $giftPid = (int)$v;
            if ($promoId > 0 && $giftPid > 0) $clean[$promoId] = $giftPid;
        }
        if ($clean) $newItem['bxgy_gift_choice'] = $clean;
    }

    // Lưu lựa chọn phân loại quà BXGY theo promo_id (nếu client gửi)
    $bxgyGiftChoiceVariant = [];
    if ($bxgyGiftChoiceVariantRaw !== null && $bxgyGiftChoiceVariantRaw !== '') {
        if (is_array($bxgyGiftChoiceVariantRaw)) {
            $bxgyGiftChoiceVariant = $bxgyGiftChoiceVariantRaw;
        } else {
            $tmp = json_decode((string)$bxgyGiftChoiceVariantRaw, true);
            if (is_array($tmp)) $bxgyGiftChoiceVariant = $tmp;
        }
    }
    if (is_array($bxgyGiftChoiceVariant) && $bxgyGiftChoiceVariant) {
        $clean = [];
        foreach ($bxgyGiftChoiceVariant as $k => $v) {
            $promoId = (int)$k;
            $giftVid = (int)$v;
            if ($promoId > 0 && $giftVid > 0) $clean[$promoId] = $giftVid;
        }
        if ($clean) $newItem['bxgy_gift_choice_variant'] = $clean;
    }
    $single = ecommerce_hydrate_cart_payment_options($ithanhloc, [$newItem]);
    // Lưu đơn hàng 1 sản phẩm vào giỏ
    ecommerce_cart_set($single);
    $logData = "SET_SINGLE PID: $pid, VAR: $variantId, PRICE: " . ($single[0]['price'] ?? 'N/A') . "\n";
// file_put_contents remove
    
    // Đồng bộ quà tặng BXGY và quà tặng hóa đơn cho trường hợp Mua ngay
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);
    
    $logData = "AFTER_SYNC COUNT: " . count($cart) . "\n";
// file_put_contents remove

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_set_single', 'Mua ngay sản phẩm', [
            'product_id' => (int)($newItem['product_id'] ?? $newItem['id'] ?? 0),
            'name' => (string)($newItem['name'] ?? ''),
            'qty' => (int)($newItem['qty'] ?? 0)
        ]);
    }
    jOut(['ok' => true, 'data' => $cart, 'count' => ecommerce_cart_count(), 'allowed_payments' => ecommerce_cart_allowed_payment_keys($cart)]);
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cart_set_selected') {
    $keys = $_REQUEST['keys'] ?? [];
    if (!is_array($keys)) {
        $keys = array_filter(array_map('trim', explode(',', (string)$keys)));
    }
    if (!$keys) jOut(['ok' => false, 'msg' => 'Chưa chọn sản phẩm']);
    $keySet = array_flip(array_map('strval', $keys));
    $cart = ecommerce_cart_get();
    $cart = array_values(array_filter($cart, function($it) use ($keySet){
        return isset($keySet[(string)($it['key'] ?? '')]);
    }));

    // Enforce stock on selected lines (server-side)
    $removedCount = 0;
    $clampedCount = 0;
    $pCols = null;
    $productStockCol = '';
    foreach ($cart as $idx => $it) {
        $isGift = !empty($it['is_gift']) || (float)($it['price'] ?? 0) <= 0.0;
        if ($isGift) continue;

        $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
        $variantId = (int)($it['variant_id'] ?? 0);
        $qty = max(1, (int)($it['qty'] ?? 1));
        if ($pid <= 0) continue;

        if ($variantId > 0 && $variantTable !== '') {
            $stmtS = $ithanhloc->prepare("SELECT stock_quantity FROM `{$variantTable}` WHERE id=? AND product_id=?{$variantActiveWhereExtra} LIMIT 1");
            if ($stmtS) {
                $stmtS->bind_param('ii', $variantId, $pid);
                $stmtS->execute();
                $rowS = $stmtS->get_result()->fetch_assoc();
                $stmtS->close();
                $stock = isset($rowS['stock_quantity']) ? (int)$rowS['stock_quantity'] : 0;
                if ($stock <= 0) {
                    unset($cart[$idx]);
                    $removedCount++;
                    continue;
                }
                if ($qty > $stock) {
                    $cart[$idx]['qty'] = $stock;
                    $clampedCount++;
                }
            }
        } else {
            // Non-variant product stock validation if stock column exists
            if ($pCols === null) {
                $pCols = listColumns($ithanhloc, 'ecommerce_product');
                if ($pCols) {
                    foreach (['stock_quantity', 'stock', 'kho', 'ton_kho', 'so_luong'] as $c) {
                        if (hasCol($pCols, $c)) { $productStockCol = $c; break; }
                    }
                }
            }
            if ($productStockCol !== '') {
                $stmtP = $ithanhloc->prepare('SELECT `' . $productStockCol . '` AS stock_qty FROM ecommerce_product WHERE id=? LIMIT 1');
                if ($stmtP) {
                    $stmtP->bind_param('i', $pid);
                    $stmtP->execute();
                    $rowP = $stmtP->get_result()->fetch_assoc();
                    $stmtP->close();
                    $stock = isset($rowP['stock_qty']) ? (int)$rowP['stock_qty'] : 0;
                    if ($stock <= 0) {
                        unset($cart[$idx]);
                        $removedCount++;
                        continue;
                    }
                    if ($qty > $stock) {
                        $cart[$idx]['qty'] = $stock;
                        $clampedCount++;
                    }
                }
            }
        }
    }
    $cart = array_values($cart);

    $cart = ecommerce_hydrate_cart_payment_options($ithanhloc, $cart);
    ecommerce_cart_set($cart);

    // Sync gifts after selection (BXGY + invoice gifts)
    $cart = syncBxgyGiftsToSessionCart($ithanhloc);
    $cart = syncInvoiceGiftToSessionCart($ithanhloc);

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        app_user_log($ithanhloc, $uid, 'cart_set_selected', 'Chọn sản phẩm để thanh toán', [
            'count' => count($cart)
        ]);
    }

    $msgParts = [];
    if ($removedCount > 0) $msgParts[] = 'Một số sản phẩm đã hết hàng và đã được xóa khỏi danh sách thanh toán.';
    if ($clampedCount > 0) $msgParts[] = 'Một số sản phẩm đã được điều chỉnh số lượng theo tồn kho.';
    $payload = ['ok' => true, 'data' => $cart, 'count' => ecommerce_cart_count(), 'allowed_payments' => ecommerce_cart_allowed_payment_keys($cart)];
    if ($msgParts) $payload['msg'] = implode(' ', $msgParts);
    jOut($payload);
}

// ======== Catalog ========
if ($ajax === 'categories') {
    if ($categoryTable === '' || $productTable === '') {
        jOut(['ok' => true, 'data' => []]);
    }
    $activeWhere = $productActiveExpr !== '' ? ('WHERE ' . $productActiveExpr) : '';
    $sql = "SELECT c.*, stats.cnt AS product_count, stats.thumb AS sample_thumb
            FROM `{$categoryTable}` c
            INNER JOIN (
                SELECT category_id, COUNT(*) AS cnt,
                       MAX(CASE WHEN image_url <> '' THEN image_url END) AS thumb
                FROM `{$productTable}` p
                {$activeWhere}
                GROUP BY category_id
            ) AS stats ON stats.category_id = c.id
            WHERE stats.cnt > 0";
    if ($categoryActiveExpr !== '') {
        $sql .= " AND {$categoryActiveExpr}";
    }
    $sql .= ' ORDER BY c.id ASC';
    $res = $ithanhloc->query($sql);
    $data = [];
    while ($r = $res->fetch_assoc()) { $data[] = $r; }
    jOut(['ok' => true, 'data' => $data]);
}

if ($ajax === 'brands') {
    if ($productTable === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu bảng sản phẩm']);
    }
    $brandConds = ["manufacturer IS NOT NULL AND TRIM(manufacturer) <> ''"];
    if ($productActiveExpr !== '') {
        $brandConds[] = $productActiveExpr;
    }
    if ($categoryActiveExistsExpr !== '') {
        $brandConds[] = $categoryActiveExistsExpr;
    }
    $brandWhereSQL = implode(' AND ', $brandConds);
    $sql = "SELECT DISTINCT TRIM(manufacturer) AS brand
            FROM `{$productTable}` p
            WHERE {$brandWhereSQL}
            ORDER BY TRIM(manufacturer) ASC";
    $res = $ithanhloc->query($sql);
    $data = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $brand = trim((string)($r['brand'] ?? ''));
            if ($brand !== '') {
                $data[] = $brand;
            }
        }
    }
    jOut(['ok' => true, 'data' => $data]);
}

if ($ajax === 'products') {
    if ($productTable === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu bảng sản phẩm']);
    }
    $productColsNow = listColumns($ithanhloc, $productTable);
    $vatMultiplierExpr = '(1 + (COALESCE(p.vat, 8) / 100))';
    if ($productColsNow && hasCol($productColsNow, 'vat_enabled') && hasCol($productColsNow, 'vat')) {
        $vatMultiplierExpr = '(CASE WHEN COALESCE(p.vat_enabled, 1) = 1 THEN (1 + (COALESCE(p.vat, 8) / 100)) ELSE 1 END)';
    }
    $draw = intval($_GET['draw'] ?? 1);
    $start = max(0, intval($_GET['start'] ?? 0));
    $length = intval($_GET['length'] ?? 24);
    if ($length <= 0) { $length = 24; }

    $search = '';
    if (isset($_GET['search']) && is_array($_GET['search']) && isset($_GET['search']['value'])) {
        $search = trim($_GET['search']['value']);
    }
    $catFilter = intval($_GET['cat_filter'] ?? 0);
    $catSlug = trim((string)($_GET['cat_slug'] ?? ''));
    if ($catFilter <= 0 && $catSlug !== '' && $categoryTable !== '') {
        $stmtSlug = $ithanhloc->prepare("SELECT id FROM `{$categoryTable}` WHERE slug = ? LIMIT 1");
        if ($stmtSlug) {
            $stmtSlug->bind_param('s', $catSlug);
            $stmtSlug->execute();
            $rowSlug = $stmtSlug->get_result()->fetch_assoc();
            $stmtSlug->close();
            if ($rowSlug) {
                $catFilter = (int)$rowSlug['id'];
            }
        }
    }
    $catFiltersRaw = trim((string)($_GET['cat_filters'] ?? ''));
    $brandFilterRaw = trim((string)($_GET['brand_filter'] ?? ''));
    $brandFiltersRaw = trim((string)($_GET['brand_filters'] ?? ''));
    $priceMin = isset($_GET['price_min']) ? (float)$_GET['price_min'] : null;
    $priceMax = isset($_GET['price_max']) ? (float)$_GET['price_max'] : null;
    $ratingMin = isset($_GET['rating_min']) ? (int)$_GET['rating_min'] : 0;
    $customSort = $_GET['custom_sort'] ?? 'newest';
    $priorityPromo = isset($_GET['priority_promo']) ? (int)$_GET['priority_promo'] : 1;
        $orderSQL = 'p.id DESC';
        if ($categoryTable !== '') {
		$prioritySQL = "(CASE WHEN EXISTS (SELECT 1 FROM `{$categoryTable}` c WHERE c.id = p.category_id AND c.name LIKE '%sơn tường%') THEN 0 ELSE 1 END) ASC";
		$orderSQL = $prioritySQL . ', p.id DESC';
        }
    switch ($customSort) {
        case 'oldest': $orderSQL = 'p.id ASC'; break;
        case 'name_asc': $orderSQL = 'p.product_name ASC'; break;
        case 'name_desc': $orderSQL = 'p.product_name DESC'; break;
        case 'brand_asc': $orderSQL = "TRIM(COALESCE(p.manufacturer, '')) ASC, p.id DESC"; break;
        case 'brand_desc': $orderSQL = "TRIM(COALESCE(p.manufacturer, '')) DESC, p.id DESC"; break;
        case 'category_asc':
            $orderSQL = $categoryTable !== '' ? 'c.name ASC, p.id DESC' : 'p.category_id ASC, p.id DESC';
            break;
        case 'category_desc':
            $orderSQL = $categoryTable !== '' ? 'c.name DESC, p.id DESC' : 'p.category_id DESC, p.id DESC';
            break;
        case 'promo':
            // Giữ sắp xếp gốc theo mới nhất, phía dưới sẽ ưu tiên khuyến mãi nếu priority_promo=1
            $orderSQL = 'p.id DESC';
            break;
        case 'price_asc':
            $orderSQL = $variantTable !== ''
                ? "(COALESCE((SELECT MIN(price) FROM `{$variantTable}` WHERE product_id = p.id), 0) * {$vatMultiplierExpr}) ASC"
                : "(0 * {$vatMultiplierExpr}) ASC";
            break;
        case 'price_desc':
            $orderSQL = $variantTable !== ''
                ? "(COALESCE((SELECT MIN(price) FROM `{$variantTable}` WHERE product_id = p.id), 0) * {$vatMultiplierExpr}) DESC"
                : "(0 * {$vatMultiplierExpr}) DESC";
            break;
        case 'rating':
            $ratingAvgSql = "COALESCE((SELECT AVG(rating) FROM ecommerce_product_review WHERE product_id = p.id AND status = 1 AND rating > 0), 0)";
            $orderSQL = "{$ratingAvgSql} DESC, p.id DESC";
            break;
    }

    $baseWhere = ['1=1'];
    if ($productActiveExpr !== '') {
        $baseWhere[] = $productActiveExpr;
    }
    if ($categoryActiveExistsExpr !== '') {
        $baseWhere[] = $categoryActiveExistsExpr;
    }
    $where = $baseWhere;
    $stockOnly = (int)($_GET['stock_only'] ?? 0);
    $promoOnly = (int)($_GET['promo_only'] ?? 0);
    if ($stockOnly === 1 && $variantTable !== '') {
        $where[] = "EXISTS (SELECT 1 FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.stock_quantity > 0 {$variantActiveWhereExtra})";
    }
    if ($promoOnly === 1) {
        $now = nowStr();
        $pIds = [];
        // 1. Promo Campaign (Combo, BXGY, Gift)
        $resP = $ithanhloc->query("SELECT config_json FROM ecommerce_product_promo WHERE is_active=1 AND (start_at IS NULL OR start_at <= '{$now}') AND (end_at IS NULL OR end_at >= '{$now}')");
        if ($resP) {
            while ($row = $resP->fetch_assoc()) {
                $cfg = json_decode((string)$row['config_json'], true);
                if (is_array($cfg)) {
                    foreach (['main_product_ids', 'gift_product_ids'] as $f) {
                        if (!empty($cfg[$f]) && is_array($cfg[$f])) {
                            foreach ($cfg[$f] as $id) $pIds[(int)$id] = (int)$id;
                        }
                    }
                    if (!empty($cfg['main_product_id'])) $pIds[(int)$cfg['main_product_id']] = (int)$cfg['main_product_id'];
                    if (!empty($cfg['gift_product_id'])) $pIds[(int)$cfg['gift_product_id']] = (int)$cfg['gift_product_id'];
                }
            }
        }
        // 2. Specific Vouchers
        $resV = $ithanhloc->query("SELECT apply_product_ids FROM ecommerce_voucher WHERE is_active=1 AND discount_target='order' AND apply_scope='products' AND (start_at IS NULL OR start_at <= '{$now}') AND (end_at IS NULL OR end_at >= '{$now}') AND (max_uses IS NULL OR used_count < max_uses)");
        if ($resV) {
            while ($row = $resV->fetch_assoc()) {
                $vIds = json_decode((string)$row['apply_product_ids'], true);
                if (is_array($vIds)) {
                    foreach ($vIds as $id) $pIds[(int)$id] = (int)$id;
                }
            }
        }
        if ($pIds) {
            $where[] = "p.id IN (" . implode(',', array_keys($pIds)) . ")";
        } else {
            $where[] = "1=0";
        }
    }
    $params = [];
    $types = '';

    $catFilters = [];
    if ($catFilter > 0) {
        $catFilters[] = $catFilter;
    }
    if ($catFiltersRaw !== '') {
        foreach (explode(',', $catFiltersRaw) as $raw) {
            $val = (int)trim($raw);
            if ($val > 0) { $catFilters[] = $val; }
        }
    }
    $catFilters = array_values(array_unique($catFilters));
    if ($catFilters) {
        $placeholders = implode(',', array_fill(0, count($catFilters), '?'));
        $where[] = "p.category_id IN ($placeholders)";
        foreach ($catFilters as $val) {
            $params[] = $val;
            $types .= 'i';
        }
    }

    $brandFilters = [];
    if ($brandFilterRaw !== '') {
        $brandFilters[] = mb_strtolower(trim($brandFilterRaw), 'UTF-8');
    }
    if ($brandFiltersRaw !== '') {
        foreach (explode(',', $brandFiltersRaw) as $raw) {
            $val = mb_strtolower(trim((string)$raw), 'UTF-8');
            if ($val !== '') {
                $brandFilters[] = $val;
            }
        }
    }
    $brandFilters = array_values(array_unique($brandFilters));
    if ($brandFilters) {
        $placeholders = implode(',', array_fill(0, count($brandFilters), '?'));
        $where[] = "LOWER(TRIM(COALESCE(p.manufacturer, ''))) IN ($placeholders)";
        foreach ($brandFilters as $brandVal) {
            $params[] = $brandVal;
            $types .= 's';
        }
    }

    $paintSpaceFiltersRaw = trim((string)($_GET['paint_space_filters'] ?? ''));
    if ($paintSpaceFiltersRaw !== '') {
        $psFilters = array_filter(array_map('trim', explode(',', $paintSpaceFiltersRaw)));
        if ($psFilters) {
            $placeholders = implode(',', array_fill(0, count($psFilters), '?'));
            $where[] = "p.paint_space IN ($placeholders)";
            foreach ($psFilters as $psVal) {
                $params[] = $psVal;
                $types .= 's';
            }
        }
    }

    $paintPositionsFiltersRaw = trim((string)($_GET['paint_positions_filters'] ?? ''));
    if ($paintPositionsFiltersRaw !== '') {
        $ppFilters = array_filter(array_map('trim', explode(',', $paintPositionsFiltersRaw)));
        if ($ppFilters) {
            $placeholders = implode(',', array_fill(0, count($ppFilters), '?'));
            $where[] = "p.paint_positions IN ($placeholders)";
            foreach ($ppFilters as $ppVal) {
                $params[] = $ppVal;
                $types .= 's';
            }
        }
    }

    $paintNeedsFiltersRaw = trim((string)($_GET['paint_needs_filters'] ?? ''));
    if ($paintNeedsFiltersRaw !== '') {
        $pnFilters = array_filter(array_map('trim', explode(',', $paintNeedsFiltersRaw)));
        if ($pnFilters) {
            $placeholders = implode(',', array_fill(0, count($pnFilters), '?'));
            $where[] = "p.paint_needs IN ($placeholders)";
            foreach ($pnFilters as $pnVal) {
                $params[] = $pnVal;
                $types .= 's';
            }
        }
    }

    // Text search theo từ khóa (tên SP, SKU, hãng, slug)
    if ($search !== '') {
        $searchNorm = mb_strtolower($search, 'UTF-8');
        $searchLike = '%' . $searchNorm . '%';

        $searchConds = [];
        if ($productColsNow && hasCol($productColsNow, 'product_name')) {
            $searchConds[] = "LOWER(TRIM(COALESCE(p.product_name, ''))) LIKE ?";
        }
        // SKU search must come from variants (sku_variant) because product-level SKU is removed.
        if ($variantTable !== '') {
            $searchConds[] = "EXISTS (SELECT 1 FROM `{$variantTable}` v WHERE v.product_id = p.id{$variantActiveWhereExtra} AND LOWER(TRIM(COALESCE(v.sku_variant, ''))) LIKE ?)";
        }
        if ($productColsNow && hasCol($productColsNow, 'manufacturer')) {
            $searchConds[] = "LOWER(TRIM(COALESCE(p.manufacturer, ''))) LIKE ?";
        }
        if ($productColsNow && hasCol($productColsNow, 'slug')) {
            $searchConds[] = "LOWER(TRIM(COALESCE(p.slug, ''))) LIKE ?";
        }

        if ($searchConds) {
            $where[] = '(' . implode(' OR ', $searchConds) . ')';
            $likeParamsCount = count($searchConds);
            for ($i = 0; $i < $likeParamsCount; $i++) {
                $params[] = $searchLike;
                $types .= 's';
            }
        }
    }

    if ($priceMin !== null) {
        if ($variantTable !== '') {
            $where[] = "(COALESCE((SELECT MIN(price) FROM `{$variantTable}` WHERE product_id = p.id), 0) * {$vatMultiplierExpr}) >= ?";
        } else {
            $where[] = "(0 * {$vatMultiplierExpr}) >= ?";
        }
        $params[] = $priceMin;
        $types .= 'd';
    }
    if ($priceMax !== null) {
        if ($variantTable !== '') {
            $where[] = "(COALESCE((SELECT MIN(price) FROM `{$variantTable}` WHERE product_id = p.id), 0) * {$vatMultiplierExpr}) <= ?";
        } else {
            $where[] = "(0 * {$vatMultiplierExpr}) <= ?";
        }
        $params[] = $priceMax;
        $types .= 'd';
    }
    // Lọc theo số sao tối thiểu dựa trên bảng ecommerce_product_review
    if ($ratingMin > 0) {
        $where[] = "COALESCE((SELECT AVG(rating) FROM ecommerce_product_review WHERE product_id = p.id AND status = 1 AND rating > 0), 0) >= ?";
        $params[] = $ratingMin;
        $types .= 'i';
    }

    $whereSQL = implode(' AND ', $where);
    $baseWhereSQL = implode(' AND ', $baseWhere);

    $stmtCount = $ithanhloc->prepare("SELECT COUNT(*) c FROM `{$productTable}` p WHERE $whereSQL");
    if ($types) { $stmtCount->bind_param($types, ...$params); }
    $stmtCount->execute();
    $filtered = $stmtCount->get_result()->fetch_assoc()['c'] ?? 0;

    $totalRes = $ithanhloc->query("SELECT COUNT(*) c FROM `{$productTable}` p WHERE {$baseWhereSQL}");
    $total = $totalRes ? ($totalRes->fetch_assoc()['c'] ?? 0) : 0;

    // ===== Ưu tiên sản phẩm khuyến mãi lên đầu (sắp xếp TOÀN CỤC, trước khi phân trang) =====
    // Gom danh sách product_id có khuyến mãi cụ thể theo sản phẩm: promo campaign (combo/bxgy)
    // + voucher áp theo sản phẩm. (Không tính "gift" vì áp cho mọi hóa đơn → không phân biệt SP.)
    if ($priorityPromo === 1) {
        $promoPriorityIds = [];
        $nowPrio = nowStr();

        // 1. Promo campaign combo/bxgy → main_product_ids / gift_product_ids
        $resPrio = $ithanhloc->query("SELECT config_json FROM ecommerce_product_promo WHERE is_active=1 AND promo_type IN ('combo','bxgy') AND (start_at IS NULL OR start_at <= '{$nowPrio}') AND (end_at IS NULL OR end_at >= '{$nowPrio}')");
        if ($resPrio) {
            while ($rowPrio = $resPrio->fetch_assoc()) {
                $cfgPrio = json_decode((string)$rowPrio['config_json'], true);
                if (is_array($cfgPrio)) {
                    foreach (['main_product_ids', 'gift_product_ids'] as $f) {
                        if (!empty($cfgPrio[$f]) && is_array($cfgPrio[$f])) {
                            foreach ($cfgPrio[$f] as $id) { $id = (int)$id; if ($id > 0) $promoPriorityIds[$id] = $id; }
                        }
                    }
                    if (!empty($cfgPrio['main_product_id'])) { $id = (int)$cfgPrio['main_product_id']; if ($id > 0) $promoPriorityIds[$id] = $id; }
                }
            }
        }

        // 2. Voucher áp theo sản phẩm cụ thể
        $resVPrio = $ithanhloc->query("SELECT apply_product_ids FROM ecommerce_voucher WHERE is_active=1 AND apply_scope='products' AND (start_at IS NULL OR start_at <= '{$nowPrio}') AND (end_at IS NULL OR end_at >= '{$nowPrio}') AND (max_uses IS NULL OR used_count < max_uses)");
        if ($resVPrio) {
            while ($rowVPrio = $resVPrio->fetch_assoc()) {
                $vIdsPrio = json_decode((string)$rowVPrio['apply_product_ids'], true);
                if (is_array($vIdsPrio)) {
                    foreach ($vIdsPrio as $id) { $id = (int)$id; if ($id > 0) $promoPriorityIds[$id] = $id; }
                }
            }
        }

        if ($promoPriorityIds) {
            $promoIdList = implode(',', array_map('intval', array_keys($promoPriorityIds)));
            // Đẩy SP có khuyến mãi (0) lên trước, sau đó giữ nguyên thứ tự sắp xếp gốc
            $orderSQL = "(CASE WHEN p.id IN ({$promoIdList}) THEN 0 ELSE 1 END) ASC, " . $orderSQL;
        }
    }

    // Thống kê rating trung bình và số lượng đánh giá có sao từ ecommerce_product_review
    $ratingAvgSql = "COALESCE((SELECT AVG(rating) FROM ecommerce_product_review WHERE product_id = p.id AND status = 1 AND rating > 0), 0)";
    $ratingCountSql = "COALESCE((SELECT COUNT(*) FROM ecommerce_product_review WHERE product_id = p.id AND status = 1 AND rating > 0), 0)";
    $categoryJoin = $categoryTable !== '' ? "LEFT JOIN `{$categoryTable}` c ON c.id = p.category_id" : '';
    $categorySelect = $categoryTable !== '' ? ', c.name AS category_name' : ", '' AS category_name";
    $dataSql = "SELECT p.*, {$ratingAvgSql} AS rating_avg, {$ratingCountSql} AS rating_count{$categorySelect}
        FROM `{$productTable}` p
        {$categoryJoin}
        WHERE $whereSQL
        ORDER BY $orderSQL
        LIMIT ?, ?";
    $stmtData = $ithanhloc->prepare($dataSql);
    $paramsData = $params;
    $typesData = $types . 'ii';
    $paramsData[] = $start;
    $paramsData[] = $length;
    $stmtData->bind_param($typesData, ...$paramsData);
    $stmtData->execute();
    $res = $stmtData->get_result();

    $activeVouchersForDemo = getActiveVouchersForDemo($ithanhloc);

    // ===== PROMO: ecommerce_product_promo (gift + combo + bxgy) dùng cho grid sản phẩm (shopping) =====
    $promoGiftSubtitle = '';
    $promoComboMap = [];
    $promoBxgyMap = [];
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
    } catch (Throwable $e) {
        $hasPromoTbl = false;
    }
    if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
        $nowPromo = nowStr();
        $stmtPromo = $ithanhloc->prepare("SELECT id, name, promo_type, config_json, is_active, start_at, end_at, priority FROM ecommerce_product_promo WHERE is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?) ORDER BY CASE WHEN priority = 0 THEN 99999 ELSE priority END ASC, id DESC");
        if ($stmtPromo) {
            $stmtPromo->bind_param('ss', $nowPromo, $nowPromo);
            $stmtPromo->execute();
            $resPromo = $stmtPromo->get_result();
            while ($rowPromo = $resPromo->fetch_assoc()) {
                $typePromo = (string)($rowPromo['promo_type'] ?? '');
                $cfg = json_decode((string)($rowPromo['config_json'] ?? ''), true);
                if (!is_array($cfg)) $cfg = [];

                if ($typePromo === 'gift') {
                    if ($promoGiftSubtitle === '') {
                        $threshold = (int)($cfg['threshold_amount'] ?? 0);
                        if ($threshold > 0) {
                            $promoGiftSubtitle = 'Quà tặng hóa đơn trên ' . formatVND($threshold) . 'đ';
                        } else {
                            $promoGiftSubtitle = 'Quà tặng cho hóa đơn đủ điều kiện';
                        }
                    }
                } elseif ($typePromo === 'combo') {
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
                    foreach ($mainIdsSet as $mainId) {
                        if ($mainId > 0 && !isset($promoComboMap[$mainId])) {
                            $promoComboMap[$mainId] = 'Mua thêm deal sốc - siêu hời';
                        }
                    }
                } elseif ($typePromo === 'bxgy') {
                    $buyQty = (int)($cfg['buy_qty'] ?? 0);
                    $giftQty = (int)($cfg['gift_qty'] ?? 0);
                    if ($buyQty > 0 && $giftQty > 0 && !empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                        $label = 'Ưu đãi - Mua ' . $buyQty . '  tặng ' . $giftQty . ' với số lượng có hạn';
                        foreach ($cfg['main_product_ids'] as $mid) {
                            $mid = (int)$mid;
                            if ($mid > 0 && empty($promoBxgyMap[$mid])) {
                                $promoBxgyMap[$mid] = $label;
                            }
                        }
                    }
                }
            }
            $stmtPromo->close();
        }
    }

    $rows = [];
    $rowIndex = 0;
    while ($r = $res->fetch_assoc()) {
        $r['thumb'] = $r['image_url'] ?: '';

        $vatEnabled = isset($r['vat_enabled']) ? (int)$r['vat_enabled'] : 1;
        $vatFactor = 1.0;
        if ($vatEnabled === 1) {
            $defaultVat = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
            $vat = isset($r['vat']) ? (float)$r['vat'] : (float)$defaultVat;
            if ($vat < 0) $vat = 0.0;
            if ($vat > 100) $vat = 100.0;
            $vatFactor = 1.0 + ($vat / 100.0);
        }

        if ($variantTable !== '') {
            // Lấy giá thấp nhất trong các phân loại đang hoạt động cho sản phẩm này (ưu tiên variant có giá nhỏ nhất)
            $pidInt = intval($r['id']);
            $whereVariants = 'product_id = ' . $pidInt . $variantActiveWhereExtra;

            // CAST về số để tránh lỗi so sánh chuỗi ("1836000" < "506000")
            $sqlMin = 'SELECT CAST(price AS DECIMAL(18,2)) AS price_num FROM `' . $variantTable . '` WHERE ' . $whereVariants . ' ORDER BY price_num ASC LIMIT 1';
            $sqlMax = 'SELECT CAST(price AS DECIMAL(18,2)) AS price_num FROM `' . $variantTable . '` WHERE ' . $whereVariants . ' ORDER BY price_num DESC LIMIT 1';
            $sqlStock = 'SELECT SUM(stock_quantity) AS total_stock FROM `' . $variantTable . '` WHERE ' . $whereVariants;

            $minRow = $ithanhloc->query($sqlMin);
            $maxRow = $ithanhloc->query($sqlMax);
            $stockRow = $ithanhloc->query($sqlStock);

            $minPrice = $minRow && ($m = $minRow->fetch_assoc()) && $m['price_num'] !== null ? (float)$m['price_num'] : null;
            $maxPrice = $maxRow && ($mx = $maxRow->fetch_assoc()) && $mx['price_num'] !== null ? (float)$mx['price_num'] : $minPrice;
            $totalStock = $stockRow && ($st = $stockRow->fetch_assoc()) && $st['total_stock'] !== null ? (int)$st['total_stock'] : 0;

            if ($minPrice !== null) {
                $minVat = (float)round($minPrice * $vatFactor);
                $maxVat = (float)round($maxPrice * $vatFactor);
                $r['gia_min'] = $minVat;
                $r['gia_max'] = $maxVat;
                $r['gia_text'] = ($minVat == $maxVat) ? formatVND($minVat) : formatVND($minVat) . ' - ' . formatVND($maxVat);
                $r['kho_text'] = number_format($totalStock);
            } else {
                $r['gia_min'] = 0;
                $r['gia_max'] = 0;
                $r['gia_text'] = '<span class="text-muted small">Chưa nhập</span>';
                $r['kho_text'] = '0';
            }
        } else {
            $r['gia_min'] = 0;
            $r['gia_max'] = 0;
            $r['gia_text'] = '<span class="text-muted small">Chưa nhập</span>';
            $r['kho_text'] = '0';
        }

        $priceMin = (float)($r['gia_min'] ?? 0);
        // Dùng chung logic chọn voucher demo với home_user (best_coupon_demo_for_product)
        $demo = best_coupon_demo_for_product($activeVouchersForDemo, (int)($r['id'] ?? 0), $priceMin);
        $discount = (float)($demo['discount'] ?? 0);
        $hasDemo = $priceMin > 0 && $discount > 0;
        $newPrice = max(0, $priceMin - $discount);
        
        $r['price_text'] = $priceMin > 0 ? (formatVND($priceMin) . 'đ') : 'Liên hệ';
        $r['old_price_text'] = $hasDemo ? (formatVND($priceMin) . 'đ') : '';
        $r['new_price_text'] = $hasDemo ? (formatVND($newPrice) . 'đ') : '';

        // Thông tin badge giảm giá: copy chuẩn từ home_user (allProducts)
        $r['discount_percent'] = 0;
        $r['voucher_badge'] = '';
        
        if ($hasDemo && $priceMin > 0) {
            // Chuẩn hoá: luôn tính % dựa trên số tiền giảm thực tế trên tổng giá trị
            $discountPercent = max(1, (int)round(($discount / max($priceMin, 1)) * 100));
            $r['discount_percent'] = $discountPercent;

            $voucherBadge = '';
            $demoType = strtolower((string)($demo['type'] ?? ''));
            $demoValue = (float)($demo['value'] ?? 0);
            $valueLabel = format_voucher_value_label($demoType === '' ? 'fixed' : $demoType, $demoValue);

            // Nếu thực tế giảm đủ 100% đơn hàng thì ưu tiên hiển thị "Miễn phí đơn hàng"
            if ($discountPercent >= 100) {
                $voucherBadge = 'Miễn phí đơn hàng';
            } elseif (strtolower($valueLabel) === 'miễn phí') {
                $voucherBadge = 'Miễn phí đơn hàng';
            } elseif ($valueLabel !== '') {
                // Ghép tiền tố "Giảm" với mệnh giá đã chuẩn hoá (10K, 10%)
                $voucherBadge = 'Giảm ' . $valueLabel;
            }

            $r['voucher_badge'] = $voucherBadge;
        }
        $r['has_ship_demo'] = false;
        $r['ship_label'] = '';
        if ($priceMin > 0) {
            // Lấy các voucher vận chuyển đang hoạt động để demo (áp dụng tương tự home_user), không cần phân trang, ưu tiên voucher mới hơn
            $nowShip = nowStr();
            $shipSql = "SELECT * FROM ecommerce_voucher
                WHERE is_active=1
                  AND discount_target='shipping'
                  AND (start_at IS NULL OR start_at <= ?)
                  AND (end_at IS NULL OR end_at >= ?)
                  AND (max_uses IS NULL OR used_count < max_uses)
                ORDER BY id DESC";
            if ($stmtShip = $ithanhloc->prepare($shipSql)) {
                $stmtShip->bind_param('ss', $nowShip, $nowShip);
                $stmtShip->execute();
                $shipRows = $stmtShip->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtShip->close();

                $bestPercent = 0.0;
                $bestFixed = 0.0;
                $shipMinSubtotal = null; // đơn tối thiểu nhỏ nhất trong các voucher ship áp dụng
                foreach ($shipRows as $coupon) {
                    if (!couponAppliesToProductIds($coupon, [(int)($r['id'] ?? 0)])) continue;
                    $minSubtotal = (float)($coupon['min_subtotal'] ?? 0);
                    // Luôn hiện badge để quảng cáo (không bỏ qua khi giá SP < đơn tối thiểu);
                    // điều kiện đơn tối thiểu sẽ được ghi kèm trên badge.
                    $r['has_ship_demo'] = true;
                    if ($shipMinSubtotal === null || $minSubtotal < $shipMinSubtotal) {
                        $shipMinSubtotal = $minSubtotal;
                    }
                    // Loại giảm lưu ở cột value_unit (percent/fixed); fallback 'type' nếu có
                    $type = strtolower(trim((string)($coupon['value_unit'] ?? $coupon['type'] ?? '')));
                    $value = (float)($coupon['value'] ?? 0);
                    if ($type === 'percent') {
                        if ($value >= 100) {
                            $bestPercent = 100.0;
                        } else {
                            $bestPercent = max($bestPercent, $value);
                        }
                    } else {
                        $bestFixed = max($bestFixed, $value);
                    }
                }

                if ($r['has_ship_demo']) {
                    if ($bestPercent >= 100.0) {
                        $r['ship_label'] = shipBadgeLabelForDemo(100, 'percent');
                    } elseif ($bestFixed > 0.0) {
                        $r['ship_label'] = shipBadgeLabelForDemo($bestFixed, 'fixed');
                    } elseif ($bestPercent > 0.0) {
                        $r['ship_label'] = shipBadgeLabelForDemo($bestPercent, 'percent');
                    }
                    // Đơn tối thiểu để hưởng (0 = không yêu cầu) — frontend ghi kèm lên badge
                    $r['ship_min_subtotal'] = ($shipMinSubtotal !== null && $shipMinSubtotal > 0) ? (float)$shipMinSubtotal : 0;
                }
            }
        }

        // Nhãn khuyến mãi hiển thị trên pcard: "Mua kèm deal sốc" + "Mua X tặng Y" + "Quà tặng hóa đơn" giống home_user
        $promoHighlights = [];
        $pidRow = (int)($r['id'] ?? 0);
        if ($pidRow > 0 && !empty($promoComboMap[$pidRow])) {
            $promoHighlights[] = (string)$promoComboMap[$pidRow];
        }
        if ($pidRow > 0 && !empty($promoBxgyMap[$pidRow])) {
            $promoHighlights[] = (string)$promoBxgyMap[$pidRow];
        }
        if ($promoGiftSubtitle !== '') {
            $promoHighlights[] = $promoGiftSubtitle;
        }
        if (count($promoHighlights) > 2) {
            $promoHighlights = array_slice($promoHighlights, 0, 2);
        }
        // Ưu tiên hiển thị subtitle của combo > bxgy > gift nếu có, vì combo/bxgy thường cụ thể hơn gift chung chung
        $promoSubtitle = '';
        if ($pidRow > 0 && !empty($promoComboMap[$pidRow])) {
            $promoSubtitle = (string)$promoComboMap[$pidRow];
        } elseif ($pidRow > 0 && !empty($promoBxgyMap[$pidRow])) {
            $promoSubtitle = (string)$promoBxgyMap[$pidRow];
        } elseif ($promoGiftSubtitle !== '') {
            $promoSubtitle = $promoGiftSubtitle;
        }

        $r['promo_subtitle'] = $promoSubtitle;
        $r['promo_highlights'] = $promoHighlights;

        // Tính điểm ưu tiên: sản phẩm có cả promo + voucher được đẩy lên trước
        $hasVoucherFlag = $hasDemo || !empty($r['has_ship_demo']);
        $hasPromoFlag = $promoSubtitle !== '';
        $priorityScore = 0;
        if ($hasPromoFlag && $hasVoucherFlag) {
            $priorityScore = 2;
        } elseif ($hasPromoFlag || $hasVoucherFlag) {
            $priorityScore = 1;
        }
        $r['_priority_score'] = $priorityScore;
        $r['_orig_idx'] = $rowIndex++;

        $rows[] = $r;
    }

    // Sắp xếp lại trong PHP để ưu tiên sản phẩm có khuyến mãi / voucher lên đầu,
    // vẫn giữ thứ tự sắp xếp gốc trong từng nhóm ưu tiên.
    if ($priorityPromo === 1) {
        usort($rows, function($a, $b) {
            $pa = isset($a['_priority_score']) ? (int)$a['_priority_score'] : 0;
            $pb = isset($b['_priority_score']) ? (int)$b['_priority_score'] : 0;
            if ($pa !== $pb) {
                return $pb <=> $pa; // Ưu tiên điểm cao hơn
            }
            $ia = isset($a['_orig_idx']) ? (int)$a['_orig_idx'] : 0;
            $ib = isset($b['_orig_idx']) ? (int)$b['_orig_idx'] : 0;
            return $ia <=> $ib; // Giữ thứ tự ban đầu trong cùng nhóm
        });
    }

    // Check which products are liked by the current user / guest
    $likedProductIds = [];
    if (!empty($rows)) {
        $rowPids = array_map(fn($r) => (int)$r['id'], $rows);
        $pidPlaceholderList = implode(',', $rowPids);
        $userIdForFav = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $guestKeyForFav = function_exists('app_guest_key') ? app_guest_key() : '';

        $likedSql = "SELECT product_id FROM ecommerce_product_favorite WHERE product_id IN ($pidPlaceholderList) AND (user_id = ? AND user_id != 0 OR ip_address = ?)";
        $stmtLiked = $ithanhloc->prepare($likedSql);
        if ($stmtLiked) {
            $stmtLiked->bind_param('is', $userIdForFav, $guestKeyForFav);
            $stmtLiked->execute();
            $resLiked = $stmtLiked->get_result();
            while ($lRow = $resLiked->fetch_assoc()) {
                $likedProductIds[(int)$lRow['product_id']] = true;
            }
            $stmtLiked->close();
        }
    }

    // Loại bỏ các field nội bộ trước khi trả về JSON và thêm liked status
    foreach ($rows as &$rowTmp) {
        $rowTmp['liked'] = isset($likedProductIds[(int)$rowTmp['id']]);
        unset($rowTmp['_priority_score'], $rowTmp['_orig_idx']);
    }
    unset($rowTmp);

    jOut([
        'ok' => true,
        'draw' => $draw,
        'recordsTotal' => intval($total),
        'recordsFiltered' => intval($filtered),
        'data' => $rows
    ]);
}

if ($ajax === 'related_products') {
    if ($productTable === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu bảng sản phẩm']);
    }

    $pid = (int)($_GET['pid'] ?? 0);
    $shuffle = (int)($_GET['shuffle'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit <= 0) $limit = 10;
    if ($limit > 20) $limit = 20;
    if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);

    // Constraints: 1. Active products, 2. Stock > 0
    $stockCols = ['stock_quantity', 'stock', 'kho', 'ton_kho', 'so_luong'];
    $stockExpr = '';
    foreach ($stockCols as $sc) {
        if (hasCol($productCols, $sc)) {
            $stockExpr = "p.{$sc} > 0";
            break;
        }
    }

    $variantStockExpr = '';
    if ($variantTable !== '' && $variantCols && hasCol($variantCols, 'stock_quantity')) {
        $variantStockExpr = "EXISTS (SELECT 1 FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.stock_quantity > 0" . ($variantActiveWhereExtra ?: "") . ")";
    }

    $finalStockExpr = "";
    if ($stockExpr !== '' && $variantStockExpr !== '') {
        $finalStockExpr = "({$stockExpr} OR {$variantStockExpr})";
    } elseif ($stockExpr !== '') {
        $finalStockExpr = $stockExpr;
    } elseif ($variantStockExpr !== '') {
        $finalStockExpr = $variantStockExpr;
    }

    // Fetch current product signals
    $sel = ['id'];
    if (hasCol($productCols, 'product_name')) $sel[] = 'product_name';
    if (hasCol($productCols, 'slug')) $sel[] = 'slug';
    if (hasCol($productCols, 'category_id')) $sel[] = 'category_id';
    if (hasCol($productCols, 'manufacturer')) $sel[] = 'manufacturer';
    if (hasCol($productCols, 'paint_needs')) $sel[] = 'paint_needs';
    if (hasCol($productCols, 'paint_positions')) $sel[] = 'paint_positions';
    if (hasCol($productCols, 'paint_space')) $sel[] = 'paint_space';

    $sqlCur = 'SELECT ' . implode(', ', array_map(fn($c) => "`$c`", $sel)) . " FROM `{$productTable}` WHERE id=? LIMIT 1";
    $stmtCur = $ithanhloc->prepare($sqlCur);
    if (!$stmtCur) jOut(['ok' => false, 'msg' => 'Không đọc được sản phẩm']);
    $stmtCur->bind_param('i', $pid);
    $stmtCur->execute();
    $cur = $stmtCur->get_result()->fetch_assoc();
    $stmtCur->close();
    if (!$cur) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);

    $curCatId = (int)($cur['category_id'] ?? 0);
    $curManu = trim((string)($cur['manufacturer'] ?? ''));
    $curNeeds = trim((string)($cur['paint_needs'] ?? ''));
    $curPos = trim((string)($cur['paint_positions'] ?? ''));
    $curSpace = trim((string)($cur['paint_space'] ?? ''));
    $curName = trim((string)($cur['product_name'] ?? ''));

    $extractKeywords = static function(string $raw, int $max = 3): array {
        $raw = trim($raw);
        if ($raw === '') return [];
        $parts = [];
        // JSON array
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $v) {
                $t = trim((string)$v);
                if ($t !== '') $parts[] = $t;
            }
        } else {
            $parts = preg_split('/\r?\n|\||,|;|\//', $raw) ?: [];
        }

        $out = [];
        foreach ($parts as $p) {
            $t = trim((string)$p);
            if ($t === '') continue;
            // avoid too short tokens
            if (mb_strlen($t, 'UTF-8') < 2) continue;
            if (!in_array($t, $out, true)) $out[] = $t;
            if (count($out) >= $max) break;
        }
        return $out;
    };

    $keywords = array_values(array_unique(array_merge(
        $extractKeywords($curNeeds, 2),
        $extractKeywords($curPos, 1),
        $extractKeywords($curSpace, 1)
    )));

    // Build candidate query (broad filter), then score in PHP.
    $selectCols = [
        'p.*',
    ];
    $join = '';
    if ($categoryTable !== '' && $categoryCols && hasCol($categoryCols, 'name')) {
        $selectCols[] = 'c.name AS category_name';
        $join = " LEFT JOIN `{$categoryTable}` c ON c.id = p.category_id";
        if ($categoryActiveExpr !== '') {
            $join .= " AND {$categoryActiveExpr}";
        }
    }

    $where = [];
    $params = [];
    $types = '';

    $where[] = 'p.id <> ?';
    $params[] = $pid;
    $types .= 'i';
    if ($productActiveExpr !== '') {
        $where[] = $productActiveExpr;
    }
    if ($categoryActiveExistsExpr !== '') {
        $where[] = $categoryActiveExistsExpr;
    }
    if ($finalStockExpr !== '') {
        $where[] = $finalStockExpr;
    }

    $or = [];
    if ($curCatId > 0 && hasCol($productCols, 'category_id')) {
        $or[] = 'p.category_id = ?';
        $params[] = $curCatId;
        $types .= 'i';
    }
    if ($curManu !== '' && hasCol($productCols, 'manufacturer')) {
        $or[] = 'p.manufacturer = ?';
        $params[] = $curManu;
        $types .= 's';
    }
    if ($keywords && hasCol($productCols, 'paint_needs')) {
        foreach (array_slice($keywords, 0, 4) as $kw) {
            $or[] = 'p.paint_needs LIKE ?';
            $params[] = '%' . $kw . '%';
            $types .= 's';
        }
    }
    if ($curName !== '' && hasCol($productCols, 'product_name')) {
        $nameKw = preg_split('/\s+/', $curName) ?: [];
        $nameKw = array_values(array_filter(array_map('trim', $nameKw), fn($t) => $t !== '' && mb_strlen($t, 'UTF-8') >= 3));
        if (!empty($nameKw[0])) {
            $or[] = 'p.product_name LIKE ?';
            $params[] = '%' . $nameKw[0] . '%';
            $types .= 's';
        }
    }
    if ($or) {
        $where[] = '(' . implode(' OR ', $or) . ')';
    }

    $orderBy = $shuffle ? 'RAND()' : 'p.id DESC';
    $sql = 'SELECT ' . implode(', ', $selectCols) . " FROM `{$productTable}` p" . $join . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT 60';
    $stmt = $ithanhloc->prepare($sql);
    if (!$stmt) {
        jOut(['ok' => true, 'items' => []]);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();

    $activeVouchersForDemo = getActiveVouchersForDemo($ithanhloc);
    $norm = static function($v): string {
        return mb_strtolower(trim((string)$v), 'UTF-8');
    };
    $curManuNorm = $norm($curManu);
    $curNeedsNorm = $norm($curNeeds);
    $curPosNorm = $norm($curPos);
    $curSpaceNorm = $norm($curSpace);

    foreach ($rows as &$r) {
        $score = 0;
        $catId = (int)($r['category_id'] ?? 0);
        $manu = $norm($r['manufacturer'] ?? '');
        $needs = $norm($r['paint_needs'] ?? '');
        $name = $norm($r['product_name'] ?? '');

        if ($curCatId > 0 && $catId === $curCatId) $score += 30;
        if ($curManuNorm !== '' && $manu !== '' && $manu === $curManuNorm) $score += 20;

        foreach ($keywords as $kw) {
            $kwN = $norm($kw);
            if ($kwN === '') continue;
            if ($needs !== '' && mb_strpos($needs, $kwN) !== false) $score += 6;
            if ($curNeedsNorm !== '' && $needs !== '' && (mb_strpos($curNeedsNorm, $kwN) !== false)) $score += 2;
            if ($curPosNorm !== '' && mb_strpos($curPosNorm, $kwN) !== false && $needs !== '' && mb_strpos($needs, $kwN) !== false) $score += 1;
            if ($curSpaceNorm !== '' && mb_strpos($curSpaceNorm, $kwN) !== false && $needs !== '' && mb_strpos($needs, $kwN) !== false) $score += 1;
        }

        if ($curName !== '') {
            $tok = preg_split('/\s+/', $curName) ?: [];
            $tok = array_values(array_filter(array_map('trim', $tok), fn($t) => $t !== '' && mb_strlen($t, 'UTF-8') >= 3));
            if (!empty($tok[0])) {
                $t0 = $norm($tok[0]);
                if ($t0 !== '' && $name !== '' && mb_strpos($name, $t0) !== false) $score += 3;
            }
        }

        $r['_score'] = $score;

        // Hydrate pricing and discounts
        $vatEnabled = isset($r['vat_enabled']) ? (int)$r['vat_enabled'] : 1;
        $vatFactor = 1.0;
        if ($vatEnabled === 1) {
            $defaultVat = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
            $vat = isset($r['vat']) ? (float)$r['vat'] : (float)$defaultVat;
            if ($vat < 0) $vat = 0.0;
            if ($vat > 100) $vat = 100.0;
            $vatFactor = 1.0 + ($vat / 100.0);
        }

        $r['gia_min'] = 0;
        if ($variantTable !== '') {
            $pidInt = (int)$r['id'];
            $sqlMin = "SELECT MIN(CAST(price AS DECIMAL(18,2))) AS min_price FROM `{$variantTable}` WHERE product_id = {$pidInt}" . $variantActiveWhereExtra;
            $minRow = $ithanhloc->query($sqlMin);
            $minPrice = ($minRow && ($m = $minRow->fetch_assoc()) && $m['min_price'] !== null) ? (float)$m['min_price'] : null;
            if ($minPrice !== null) {
                $r['gia_min'] = (float)round($minPrice * $vatFactor);
            }
        }

        $priceMin = (float)($r['gia_min'] ?? 0);
        $demo = best_coupon_demo_for_product($activeVouchersForDemo, (int)($r['id'] ?? 0), $priceMin);
        $discount = (float)($demo['discount'] ?? 0);
        $hasDemo = $priceMin > 0 && $discount > 0;
        
        $r['price'] = $priceMin - $discount;
        $r['price_old'] = $hasDemo ? $priceMin : 0;
        $r['thumb'] = (string)($r['image_url'] ?? '');
    }
    unset($r);

    usort($rows, function($a, $b) {
        $sa = (int)($a['_score'] ?? 0);
        $sb = (int)($b['_score'] ?? 0);
        if ($sa !== $sb) return $sb <=> $sa;
        $soldA = (int)($a['sold_count'] ?? 0);
        $soldB = (int)($b['sold_count'] ?? 0);
        if ($soldA !== $soldB) return $soldB <=> $soldA;
        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    });

    $items = [];
    foreach ($rows as $r) {
        if (count($items) >= $limit) break;
        $items[] = [
            'id' => (int)($r['id'] ?? 0),
            'product_name' => (string)($r['product_name'] ?? ''),
            'slug' => (string)($r['slug'] ?? ''),
            'category_id' => (int)($r['category_id'] ?? 0),
            'category_name' => (string)($r['category_name'] ?? ''),
            'manufacturer' => (string)($r['manufacturer'] ?? ''),
            'paint_needs' => (string)($r['paint_needs'] ?? ''),
            'thumb' => (string)($r['thumb'] ?? ''),
            'price' => (float)($r['price'] ?? 0),
            'price_old' => (float)($r['price_old'] ?? 0),
        ];
    }

    jOut(['ok' => true, 'items' => $items]);
}

if ($ajax === 'product_detail') {
    if ($productTable === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu bảng sản phẩm']);
    }
    $pid = intval($_GET['pid'] ?? 0);
    if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
    $stmt = $ithanhloc->prepare("SELECT * FROM `{$productTable}` WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    
    $curCatId = (int)($product['category_id'] ?? 0);

    $vatEnabled = isset($product['vat_enabled']) ? (int)$product['vat_enabled'] : 1;
    $vatFactor = 1.0;
    if ($vatEnabled === 1) {
        $defaultVat = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
        $vat = isset($product['vat']) ? (float)$product['vat'] : (float)$defaultVat;
        if ($vat < 0) $vat = 0.0;
        if ($vat > 100) $vat = 100.0;
        $vatFactor = 1.0 + ($vat / 100.0);
    }
    // NOTE: product-level price is deprecated; prices come from variants.

    if (isset($_SESSION['user_id'])) {
        app_user_log($ithanhloc, (int)$_SESSION['user_id'], 'view_product', 'Đã xem sản phẩm', [
            'product_id' => $pid,
            'name' => (string)($product['product_name'] ?? '')
        ]);
    }

    $variants = [];
    if ($variantTable !== '') {
        $sqlV = "SELECT * FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY variant_name ASC, price ASC";
        $stmtV = $ithanhloc->prepare($sqlV);
        $stmtV->bind_param('i', $pid);
        $stmtV->execute();
        $resV = $stmtV->get_result();
        while ($r = $resV->fetch_assoc()) {
            $r['price'] = (float)round(((float)($r['price'] ?? 0)) * $vatFactor);
            $r['variant_name'] = cleanVariantWeightLegacy($r['variant_name'] ?? '', (float)($r['shipping_weight_value'] ?? 0), (string)($r['shipping_weight_unit'] ?? ''));
            $variants[] = $r;
        }
        $stmtV->close();
    }

    // Default selected variant: the cheapest one (prefer price > 0)
    $defaultVariantId = 0;
    $bestVariantPrice = null;
    foreach ($variants as $vTmp) {
        $vidTmp = (int)($vTmp['id'] ?? 0);
        if ($vidTmp <= 0) continue;
        $pTmp = (float)($vTmp['price'] ?? 0);
        if ($pTmp <= 0) continue;
        if ($bestVariantPrice === null || $pTmp < $bestVariantPrice) {
            $bestVariantPrice = $pTmp;
            $defaultVariantId = $vidTmp;
        }
    }
    if ($defaultVariantId <= 0 && !empty($variants[0]['id'])) {
        $defaultVariantId = (int)$variants[0]['id'];
    }

    $minG = null; $maxG = null;
    foreach ($variants as $v) {
        $g = (float)($v['price'] ?? 0);
        $minG = ($minG === null) ? $g : min($minG, $g);
        $maxG = ($maxG === null) ? $g : max($maxG, $g);
    }
    // Nếu có variant thì ưu tiên hiển thị giá min/max của variant, nếu không có thì dùng giá của product        
    $ratingAvg = 0.0;
    $ratingCount = 0;
    $soldCount = isset($product['sold_count']) ? (int)$product['sold_count'] : 0;

    $locationCtx = getSelectedLocationContext();

    $shippingQuoteDetail = buildShippingQuoteByLocation($ithanhloc, 0, null, '', [[
        'id' => $pid,
        'product_id' => $pid,
        'variant_id' => $defaultVariantId,
        'qty' => 1,
        'price' => (float)($minG !== null ? $minG : 0),
    ]]);
    $shippingMethods = is_array($shippingQuoteDetail['methods'] ?? null) ? $shippingQuoteDetail['methods'] : [];
    $shippingPrimary = null;
    $defaultMethodKey = strtolower(trim((string)($shippingQuoteDetail['shipping_method'] ?? '')));
    foreach ($shippingMethods as $method) {
        if (!is_array($method)) continue;
        $methodKey = strtolower(trim((string)($method['key'] ?? '')));
        if ($methodKey !== '' && $methodKey === $defaultMethodKey) {
            $shippingPrimary = $method;
            break;
        }
    }
    if (!$shippingPrimary && !empty($shippingMethods[0]) && is_array($shippingMethods[0])) {
        $shippingPrimary = $shippingMethods[0];
    }
    // Nếu vẫn không có phương thức vận chuyển nào, tạo phương thức mặc định để tránh lỗi ở frontend
    if (!$shippingPrimary) {
        $shippingPrimary = [
            'key' => 'tieu_chuan',
            'label' => 'Tiêu chuẩn',
            'fee' => 30000,
            'fee_text' => formatVND(30000) . 'đ',
        ];
    }
    $shippingDestination = trim((string)($shippingQuoteDetail['destination'] ?? ''));
    if ($shippingDestination === '') {
        $shippingDestination = 'Chưa thiết lập địa chỉ giao hàng';
    }

    // ===== MÃ GIẢM GIÁ ĐỀ XUẤT DỰA TRÊN GIÁ TỐI THIỂU =====
    $suggest = [];
    $sampleSubtotal = (float)($minG !== null ? $minG : 0);
    $now = nowStr();
    $stmtC = $ithanhloc->prepare("SELECT code, value, min_subtotal, max_discount, apply_scope, apply_product_ids FROM ecommerce_voucher
        WHERE is_active=1
            AND discount_target = 'order'
            AND (start_at IS NULL OR start_at <= ?)
            AND (end_at IS NULL OR end_at >= ?)
            AND (max_uses IS NULL OR used_count < max_uses)
        ORDER BY id DESC LIMIT 30");
    if ($stmtC) {
        $stmtC->bind_param('ss', $now, $now);
        $stmtC->execute();
        $rows = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtC->close();
        $productIdsForDetail = [$pid];
        foreach ($rows as $c) {
            $min = (float)($c['min_subtotal'] ?? 0);
            if ($sampleSubtotal < $min) continue;
            if (!couponAppliesToProductIds($c, $productIdsForDetail)) continue;
            $disc = 0;
            if (($c['type'] ?? '') === 'percent') {
                $disc = $sampleSubtotal * (float)($c['value'] ?? 0) / 100.0;
                if ($c['max_discount'] !== null && $c['max_discount'] !== '') {
                    $disc = min($disc, (float)$c['max_discount']);
                }
            } else {
                $disc = (float)($c['value'] ?? 0);
            }
            $disc = max(0, min($disc, $sampleSubtotal));
            if ($disc <= 0) continue;
            $suggest[] = [
                'code' => (string)$c['code'],
                'discount' => $disc,
            ];
        }
        usort($suggest, fn($a,$b) => ($b['discount'] <=> $a['discount']));
        $suggest = array_slice($suggest, 0, 3);
    }

    // ===== DỮ LIỆU THI CÔNG =====
    $construction = null;
    try {
        if ($constructionTable !== '' && $constructionCols) {
            $stmtCons = $ithanhloc->prepare("SELECT * FROM `{$constructionTable}` WHERE product_id = ? LIMIT 1");
            if ($stmtCons) {
                $stmtCons->bind_param('i', $pid);
                $stmtCons->execute();
                $construction = $stmtCons->get_result()->fetch_assoc();
                $stmtCons->close();
            }
        }
    } catch (Throwable $e) {
        // bỏ qua lỗi, không chặn trang chi tiết sản phẩm
    }
    // ===== PROMOS  (CHIẾN DỊCH ƯU ĐÃI) =====
    $promoGift = null;
    $promoComboList = [];
    $promoBxgyList = [];
    $giftAllIds = [];
    $giftThresholdByProductId = [];
    $bxgyGiftAllIds = [];
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
    } catch (Throwable $e) {
        $hasPromoTbl = false;
    }
    if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
        $nowPromo = nowStr();
        $stmtPromo = $ithanhloc->prepare("SELECT id, name, promo_type, config_json, is_active, start_at, end_at, priority FROM ecommerce_product_promo WHERE is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?) ORDER BY CASE WHEN priority = 0 THEN 99999 ELSE priority END ASC, id DESC");
        if ($stmtPromo) {
            $stmtPromo->bind_param('ss', $nowPromo, $nowPromo);
            $stmtPromo->execute();
            $resPromo = $stmtPromo->get_result();
            while ($rowPromo = $resPromo->fetch_assoc()) {
                $typePromo = (string)($rowPromo['promo_type'] ?? '');
                $cfg = json_decode((string)($rowPromo['config_json'] ?? ''), true);
                if (!is_array($cfg)) $cfg = [];

                if ($typePromo === 'gift') {
                    // Kiểm tra main_product_ids nếu có quy định (lọc theo sản phẩm)
                    if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                        $mainSet = [];
                        foreach ($cfg['main_product_ids'] as $mid) {
                            $mid = (int)$mid;
                            if ($mid > 0) $mainSet[$mid] = true;
                        }
                        if (!($pid > 0 && !empty($mainSet[$pid]))) {
                            continue;
                        }
                    }

                    $threshold = (int)($cfg['threshold_amount'] ?? 0);
                    $giftIds = [];
                    if (!empty($cfg['gift_product_ids']) && is_array($cfg['gift_product_ids'])) {
                        foreach ($cfg['gift_product_ids'] as $gid) {
                            $gid = (int)$gid;
                            if ($gid > 0) {
                                $giftIds[$gid] = $gid;
                                $giftAllIds[$gid] = $gid;
                                if (!isset($giftThresholdByProductId[$gid]) || ($threshold > 0 && $threshold < $giftThresholdByProductId[$gid])) {
                                    $giftThresholdByProductId[$gid] = $threshold;
                                }
                            }
                        }
                    }
                } elseif ($typePromo === 'bxgy') {
                    $mainSet = [];
                    if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                        foreach ($cfg['main_product_ids'] as $mid) {
                            $mid = (int)$mid;
                            if ($mid > 0) $mainSet[$mid] = true;
                        }
                    }
                    $isMatch = ($pid > 0 && !empty($mainSet[$pid]));
                    if (!$isMatch && $curCatId > 0) {
                        $catIds = !empty($cfg['category_ids']) && is_array($cfg['category_ids']) ? $cfg['category_ids'] : [];
                        if (in_array($curCatId, $catIds)) $isMatch = true;
                    }
                    if (!$isMatch) {
                        continue;
                    }

                    $buyQty = (int)($cfg['buy_qty'] ?? 0);
                    $giftQty = (int)($cfg['gift_qty'] ?? 0);
                    if ($buyQty <= 0 || $giftQty <= 0) {
                        continue;
                    }

                    // Hỗ trợ danh sách quà (mới) + field legacy gift_product_id
                    $giftPidLegacy = (int)($cfg['gift_product_id'] ?? 0);
                    $giftIds = [];
                    if (!empty($cfg['gift_product_ids']) && is_array($cfg['gift_product_ids'])) {
                        foreach ($cfg['gift_product_ids'] as $gid) {
                            $gid = (int)$gid;
                            if ($gid > 0) {
                                $giftIds[$gid] = $gid;
                                $bxgyGiftAllIds[$gid] = $gid;
                            }
                        }
                    }
                    if (!$giftIds) {
                        if ($giftPidLegacy > 0) {
                            $giftIds[$giftPidLegacy] = $giftPidLegacy;
                            $bxgyGiftAllIds[$giftPidLegacy] = $giftPidLegacy;
                        } else {
                            // Tặng cùng sản phẩm chính
                            $giftIds[$pid] = $pid;
                            $bxgyGiftAllIds[$pid] = $pid;
                        }
                    }

                    $maxPerOrder = (int)($cfg['max_gift_per_order'] ?? 0);

                    // Chuẩn hoá map giới hạn phân loại (key = product_id) để UI lọc đúng phân loại được gán
                    $normVariantMap = static function ($raw): array {
                        $out = [];
                        if (!empty($raw) && is_array($raw)) {
                            foreach ($raw as $pidKey => $arr) {
                                $vp = (int)$pidKey;
                                if ($vp <= 0 || !is_array($arr)) continue;
                                $uniq = [];
                                foreach ($arr as $vid) {
                                    $vid = (int)$vid;
                                    if ($vid > 0) $uniq[$vid] = $vid;
                                }
                                if ($uniq) $out[$vp] = array_values($uniq);
                            }
                        }
                        return $out;
                    };
                    $mainVariantMap = $normVariantMap($cfg['main_variant_ids'] ?? null);
                    $giftVariantMap = $normVariantMap($cfg['gift_variant_ids'] ?? null);

                    $promoBxgyList[] = [
                        'id' => (int)($rowPromo['id'] ?? 0),
                        'name' => (string)($rowPromo['name'] ?? ''),
                        'type' => 'bxgy',
                        'buy_qty' => $buyQty,
                        'gift_qty' => $giftQty,
                        'gift_product_id' => $giftPidLegacy,
                        'gift_product_ids' => array_values($giftIds),
                        'gift_variant_id' => (int)($cfg['gift_variant_id'] ?? 0),
                        'gift_variant_ids' => (object)$giftVariantMap,
                        'main_variant_ids' => (object)$mainVariantMap,
                        'max_gift_per_order' => $maxPerOrder,
                        'main_product_ids' => array_keys($mainSet),
                    ];
                } elseif ($typePromo === 'combo') {
                    $mainSet = [];
                    if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                        foreach ($cfg['main_product_ids'] as $mid) {
                            $mid = (int)$mid;
                            if ($mid > 0) $mainSet[$mid] = true;
                        }
                    }
                    $legacyMain = (int)($cfg['main_product_id'] ?? 0);
                    if ($legacyMain > 0) $mainSet[$legacyMain] = true;
                    if (!($pid > 0 && !empty($mainSet[$pid]))) {
                        continue;
                    }
                    $mainId = $pid;
                    if ($mainId > 0) {
                        $items = [];
                        $rawItems = [];
                        $comboIds = [];
                        if (!empty($cfg['items']) && is_array($cfg['items'])) {
                            foreach ($cfg['items'] as $it) {
                                $itPid = (int)($it['product_id'] ?? 0);
                                $itPrice = (int)($it['promo_price'] ?? 0);
                                if ($itPid > 0 && $itPrice >= 0) {
                                    $rawItems[] = [
                                        'product_id' => $itPid,
                                        'promo_price' => $itPrice,
                                    ];
                                    $comboIds[$itPid] = $itPid;
                                }
                            }
                        }
                        if ($rawItems) {
                            $idList = implode(',', array_map('intval', array_values($comboIds)));
                            $comboMap = [];
                            $qCombo = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE id IN ($idList)");
                            if ($qCombo) {
                                while ($cp = $qCombo->fetch_assoc()) {
                                    $cId = (int)($cp['id'] ?? 0);
                                    $comboMap[$cId] = $cp;
                                }
                            }
                            foreach ($rawItems as $rit) {
                                $pidIt = (int)$rit['product_id'];
                                $promoPrice = (int)$rit['promo_price'];
                                $rowP = $comboMap[$pidIt] ?? null;

                                // Tính giá gốc hiển thị giống trang chi tiết cho sản phẩm combo
                                $origPrice = 0;
                                if ($rowP) {
                                    $cVatEnabled = isset($rowP['vat_enabled']) ? (int)$rowP['vat_enabled'] : 1;
                                    $cVat = isset($rowP['vat']) ? (float)$rowP['vat'] : (function_exists('app_get_default_vat_percent') ? (float)app_get_default_vat_percent() : 8.0);
                                    if ($cVat < 0) $cVat = 0.0;
                                    if ($cVat > 100) $cVat = 100.0;
                                    $minPriceCombo = null;
                                    if ($variantTable !== '' && $pidIt > 0) {
                                        if ($stmtVc = $ithanhloc->prepare("SELECT MIN(price) AS min_g FROM `{$variantTable}` WHERE product_id = ?")) {
                                            $stmtVc->bind_param('i', $pidIt);
                                            $stmtVc->execute();
                                            $rowVc = $stmtVc->get_result()->fetch_assoc();
                                            $stmtVc->close();
                                            if ($rowVc && $rowVc['min_g'] !== null) {
                                                $minPriceCombo = (float)$rowVc['min_g'];
                                            }
                                        }
                                    }
                                    if ($minPriceCombo !== null && $minPriceCombo > 0) {
                                        $vatFactorCombo = ($cVatEnabled === 1) ? (1.0 + ($cVat / 100.0)) : 1.0;
                                        $origPrice = (int)round($minPriceCombo * $vatFactorCombo);
                                    }
                                }

                                // Lấy phân loại mặc định (variant) nếu có cho sản phẩm trong combo
                                $variantLabel = '';
                                if ($variantTable !== '' && $pidIt > 0) {
                                    $stmtCV = $ithanhloc->prepare("SELECT variant_name, shipping_weight_value, shipping_weight_unit FROM `{$variantTable}` WHERE product_id = ? ORDER BY price ASC, variant_name ASC LIMIT 1");
                                    if ($stmtCV) {
                                        $stmtCV->bind_param('i', $pidIt);
                                        $stmtCV->execute();
                                        $vRow = $stmtCV->get_result()->fetch_assoc();
                                        $stmtCV->close();
                                        if ($vRow) {
                                            $variantLabel = cleanVariantWeightLegacy(($vRow['variant_name'] ?? ''), (float)($vRow['shipping_weight_value'] ?? 0), (string)($vRow['shipping_weight_unit'] ?? 'kg'));
                                        }
                                    }
                                }

                                $items[] = [
                                    'product_id' => $pidIt,
                                    'promo_price' => $promoPrice,
                                    'promo_price_text' => formatVND($promoPrice) . 'đ',
                                    'name' => (string)($rowP['product_name'] ?? ''),
                                    'thumb' => (string)($rowP['image_url'] ?? ''),
                                    'price' => $origPrice,
                                    'price_text' => formatVND($origPrice) . 'đ',
                                    'variant' => $variantLabel,
                                ];
                            }
                        }
                        if ($items) {
                            $promoComboList[] = [
                                'id' => (int)($rowPromo['id'] ?? 0),
                                'name' => (string)($rowPromo['name'] ?? ''),
                                'type' => 'combo',
                                'main_product_id' => $mainId,
                                'items' => $items,
                                'label' => (string)($rowPromo['name'] ?: 'Mua thêm deal sốc'),
                            ];
                        }
                    }
                }
            }
            $stmtPromo->close();
        }
    }

    // Enrich BXGY gift products info for UI (single query)
    if ($promoBxgyList && $bxgyGiftAllIds) {
        $idList = implode(',', array_map('intval', array_values($bxgyGiftAllIds)));
        $giftMap = [];
        $q = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) AS min_price FROM `{$productTable}` p WHERE p.id IN ($idList)");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $gid = (int)($r['id'] ?? 0);
                if ($gid <= 0) continue;
                $giftMap[$gid] = [
                    'product_id' => $gid,
                    'name' => (string)($r['product_name'] ?? ''),
                    'thumb' => (string)($r['image_url'] ?? ''),
                    'price' => (float)($r['min_price'] ?? 0),
                ];
            }
        }

        foreach ($promoBxgyList as &$bx) {
            $ids = isset($bx['gift_product_ids']) && is_array($bx['gift_product_ids']) ? $bx['gift_product_ids'] : [];
            $bx['gift_products'] = [];
            foreach ($ids as $gid) {
                $gid = (int)$gid;
                if ($gid > 0 && isset($giftMap[$gid])) {
                    $bx['gift_products'][] = $giftMap[$gid];
                }
            }
        }
        unset($bx);
    }

    // Gộp tất cả chiến dịch quà tặng thành một cấu trúc promoGift duy nhất với threshold riêng cho từng sản phẩm
    if ($giftAllIds) {
        $giftProducts = [];
        $idList = implode(',', array_map('intval', array_values($giftAllIds)));
        $qGift = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE id IN ($idList)");
        if ($qGift) {
            while ($gp = $qGift->fetch_assoc()) {
                $gId = (int)($gp['id'] ?? 0);
                if ($gId <= 0) continue;

                $gVatEnabled = isset($gp['vat_enabled']) ? (int)$gp['vat_enabled'] : 1;
                $gVat = isset($gp['vat']) ? (float)$gp['vat'] : (function_exists('app_get_default_vat_percent') ? (float)app_get_default_vat_percent() : 8.0);
                if ($gVat < 0) $gVat = 0.0;
                if ($gVat > 100) $gVat = 100.0;
                $minPrice = null;
                if ($variantTable !== '' && $gId > 0) {
                    if ($stmtVg = $ithanhloc->prepare("SELECT MIN(price) AS min_g FROM `{$variantTable}` WHERE product_id = ?")) {
                        $stmtVg->bind_param('i', $gId);
                        $stmtVg->execute();
                        $rowVg = $stmtVg->get_result()->fetch_assoc();
                        $stmtVg->close();
                        if ($rowVg && $rowVg['min_g'] !== null) {
                            $minPrice = (float)$rowVg['min_g'];
                        }
                    }
                }
                $gPrice = 0;
                if ($minPrice !== null && $minPrice > 0) {
                    $vatFactorGift = ($gVatEnabled === 1) ? (1.0 + ($gVat / 100.0)) : 1.0;
                    $gPrice = (int)round($minPrice * $vatFactorGift);
                }

                $variantLabel = '';
                if ($variantTable !== '' && $gId > 0) {
                    $stmtGV = $ithanhloc->prepare("SELECT variant_name, shipping_weight_value, shipping_weight_unit FROM `{$variantTable}` WHERE product_id = ? ORDER BY price ASC, variant_name ASC LIMIT 1");
                    if ($stmtGV) {
                        $stmtGV->bind_param('i', $gId);
                        $stmtGV->execute();
                        $vRow = $stmtGV->get_result()->fetch_assoc();
                        $stmtGV->close();
                        if ($vRow) {
                            $variantLabel = cleanVariantWeightLegacy(($vRow['variant_name'] ?? ''), (float)($vRow['shipping_weight_value'] ?? 0), (string)($vRow['shipping_weight_unit'] ?? 'kg'));
                        }
                    }
                }

                $thresholdForGift = (int)($giftThresholdByProductId[$gId] ?? 0);

                $giftProducts[] = [
                    'product_id' => $gId,
                    'name' => (string)($gp['product_name'] ?? ''),
                    'thumb' => (string)($gp['image_url'] ?? ''),
                    'price' => $gPrice,
                    'price_text' => formatVND($gPrice) . 'đ',
                    'variant' => $variantLabel,
                    'threshold_amount' => $thresholdForGift,
                ];
            }
        }

        if ($giftProducts) {
            $promoGift = [
                'type' => 'gift',
                'threshold_amount' => 0,
                'gift_product_ids' => array_values($giftAllIds),
                'gift_choice_limit' => 1,
                'label' => 'Quà tặng hóa đơn',
                'products' => $giftProducts,
            ];
        }
    }

    // Product-level color_options is deprecated (variant-only). Keep key for backward compatibility.
    $colorOptions = [];
    $variantColorOptionsMap = [];
    foreach ($variants as $vItem) {
        $vid = (int)($vItem['id'] ?? 0);
        if ($vid <= 0) continue;
        $vColors = sanitizeColorOptions($vItem['color_options'] ?? null);
        if ($vColors) {
            $variantColorOptionsMap[$vid] = $vColors;
        }
    }
    $paymentKeys = ecommerce_sanitize_payment_keys($product['payment_options'] ?? null);
    if (!$paymentKeys) { $paymentKeys = ecommerce_all_enabled_payment_keys(); }
    $paymentMethods = ecommerce_map_payment_labels($paymentKeys);
    $mediaGallery = sanitizeMediaGallery($product['media_gallery'] ?? null);

    // Hệ thống sơn chi tiết cho sản phẩm
    $coatingLayers = [];
    if ($coatingTable !== '' && $productTable !== '' && $categoryTable !== '' && !empty($coatingCols)) {
        try {
                 $sqlCs = "SELECT c.id, c.product_id, c.category_id, c.suggest_product_id, c.layer_type, c.layer_count, c.sort_order,
                         p.product_name AS suggest_product_name,
                         p.image_url AS suggest_product_thumb,
                         cat.name AS category_name
                      FROM `{$coatingTable}` c
                      LEFT JOIN `{$productTable}` p ON p.id = c.suggest_product_id
                      LEFT JOIN `{$categoryTable}` cat ON cat.id = c.category_id
                      WHERE c.product_id = ?
                      ORDER BY c.sort_order ASC, c.id ASC";
            $stmtCs = $ithanhloc->prepare($sqlCs);
            if ($stmtCs) {
                $stmtCs->bind_param('i', $pid);
                $stmtCs->execute();
                $resCs = $stmtCs->get_result();
                while ($row = $resCs->fetch_assoc()) {
                    $coatingLayers[] = $row;
                }
                $stmtCs->close();
            }
        } catch (Throwable $e) {
            // bỏ qua lỗi, không chặn trang chi tiết sản phẩm
        }
    }

    $groups = [];
    if ($groupTable !== '') {
        $sqlG = "SELECT * FROM `{$groupTable}` WHERE product_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC";
        if ($stmtG = $ithanhloc->prepare($sqlG)) {
            $stmtG->bind_param('i', $pid);
            $stmtG->execute();
            $resG = $stmtG->get_result();
            while ($rg = $resG->fetch_assoc()) {
                $groups[] = $rg;
            }
            $stmtG->close();
        }
    }

    jOut([
        'ok' => true,
        'data' => [
            'product' => $product,
            'variants' => $variants,
            'groups' => $groups,
            'price_min' => $minG,
            'price_max' => $maxG,
            'summary' => [
                'rating_avg' => $ratingAvg,
                'rating_count' => $ratingCount,
                'sold_count' => $soldCount,
                'total_comments' => 0,
                'distribution' => ['5'=>0,'4'=>0,'3'=>0,'2'=>0,'1'=>0],
            ],
            'shipping' => [
                'destination' => $shippingDestination,
                'methods' => $shippingMethods,
                'default_method_key' => (string)($shippingPrimary['key'] ?? 'tieu_chuan'),
                'default_fee' => (float)($shippingPrimary['fee'] ?? 0),
                'default_fee_text' => (string)($shippingPrimary['fee_text'] ?? (formatVND(0) . 'đ')),
            ],
            'suggested_coupons' => $suggest,
            'color_options' => $colorOptions,
            'variant_color_options' => $variantColorOptionsMap,
            'payment_methods' => $paymentMethods,
            'payment_method_keys' => $paymentKeys,
            'media_gallery' => $mediaGallery,
            'construction' => $construction,
            'coating_layers' => $coatingLayers,
            'promos' => [
                'gift' => $promoGift,
                'combo' => $promoComboList,
                'bxgy' => $promoBxgyList,
            ],
            'reviews' => [],
        ]
    ]);
}

// ======== Checkout ========
// ======== Checkout ========
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'checkout_save') {
    // Chặn checkout qua GET để tránh lộ PII trong access log
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jOut(['ok' => false, 'msg' => 'Phương thức không hợp lệ, vui lòng gửi qua POST']);
    }
    $src = $_POST;

    // Validate and consume checkout token to prevent double-submit / spam ordering
    $checkout_token = trim($src['checkout_token'] ?? '');
    if ($checkout_token === '' || empty($_SESSION['checkout_token']) || $checkout_token !== $_SESSION['checkout_token']) {
        jOut(['ok' => false, 'msg' => 'Yêu cầu đặt hàng không hợp lệ hoặc đang được xử lý. Vui lòng tải lại trang hoặc thử lại.']);
    }
    unset($_SESSION['checkout_token']);

    $user_name       = trim($src['user_name'] ?? '');
    $phone           = trim($src['phone'] ?? '');
    $email           = trim($src['email'] ?? '');
    $address         = trim($src['address'] ?? '');
    $note            = trim($src['note'] ?? '');
    $payment_method  = $src['payment_method'] ?? 'cod';
    $shipping_method = trim((string)($src['shipping_method'] ?? ''));
    // Chuẩn hoá key vận chuyển để validate voucher đồng nhất (voucher.shipping_methods lưu dạng ghn_*).
    // Client gửi key thô ('nhanh'/'cong_kenh'/'hoa_toc'); map sang key chuẩn dùng cho order/payment voucher.
    $shipping_method_norm = (function (string $m): string {
        $m = strtolower(trim($m));
        return match ($m) {
            'nhanh'     => 'ghn_nhanh',
            'cong_kenh' => 'ghn_tiet_kiem',
            'hoa_toc'   => 'ghn_hoa_toc',
            default     => $m,
        };
    })($shipping_method);
    $products_json   = $src['products_json'] ?? '[]';
    $voucher_order_code = trim((string)($src['voucher_order_code'] ?? $src['voucher_code'] ?? ''));
    $voucher_ship_code  = trim((string)($src['voucher_ship_code'] ?? ''));
    $voucher_payment_code = trim((string)($src['voucher_payment_code'] ?? ''));
    $xu_use_request     = max(0, (int)($src['xu_use'] ?? 0));

    $invoice_want         = (int)($src['invoice_want'] ?? 0);
    $invoice_type         = trim((string)($src['invoice_type'] ?? ''));
    $invoice_buyer_name   = trim((string)($src['invoice_buyer_name'] ?? ''));
    $invoice_company_name = trim((string)($src['invoice_company_name'] ?? ''));
    $invoice_tax_code     = trim((string)($src['invoice_tax_code'] ?? ''));
    $invoice_address      = trim((string)($src['invoice_address'] ?? ''));
    $invoice_email        = trim((string)($src['invoice_email'] ?? ''));

    $invoiceTableExists = false;
    $invoiceSaved       = false;
    $uidInt = (int)($_SESSION['user_id'] ?? 0);

    $items = json_decode($products_json, true);
    if (!is_array($items)) $items = [];

    // Fallback: nếu client payload rỗng (race condition/load cart chưa kịp), lấy giỏ hàng từ session
    // Server-side luôn là source of truth
    if (!$items) {
        $sessionCart = ecommerce_cart_get();
        if (is_array($sessionCart) && $sessionCart) {
            $fallback = [];
            foreach ($sessionCart as $sit) {
                if (!is_array($sit)) continue;
                $pid = (int)($sit['product_id'] ?? $sit['id'] ?? 0);
                $qty = (int)($sit['qty'] ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;
                $fallback[] = [
                    'id' => $pid,
                    'product_id' => $pid,
                    'variant_id' => (int)($sit['variant_id'] ?? $sit['v_id'] ?? $sit['vid'] ?? 0),
                    'color_code' => (string)($sit['color_code'] ?? ''),
                    'qty' => $qty,
                    'is_gift' => !empty($sit['is_gift']) ? 1 : 0,
                    'is_combo' => !empty($sit['is_combo']) ? 1 : 0,
                ];
            }
            $items = $fallback;
            // BUG7: ghi nhận khi phải fallback sang giỏ session (payload client rỗng) để giám sát race/lỗi JS.
            if ($items && $uidInt > 0 && function_exists('app_user_log')) {
                app_user_log($ithanhloc, $uidInt, 'checkout_fallback_cart', 'Checkout dùng giỏ session do payload client rỗng', [
                    'item_count' => count($items),
                ]);
            }
        }
    }

    // Server-side source of truth: map gift/combo flags from current session cart
    // to avoid accidentally counting gift lines into subtotal when client payload is missing flags.
    $sessionGiftMap = [];
    $sessionComboMap = [];
    $sessionCart = ecommerce_cart_get();
    if (is_array($sessionCart)) {
        foreach ($sessionCart as $sit) {
            if (!is_array($sit)) continue;
            $spid = (int)($sit['product_id'] ?? $sit['id'] ?? 0);
            $svid = (int)($sit['variant_id'] ?? 0);
            $scc  = strtoupper(trim((string)($sit['color_code'] ?? '')));
            if ($spid <= 0) continue;
            $skey = $spid . ':' . $svid . ':' . $scc;
            if (!empty($sit['is_gift'])) $sessionGiftMap[$skey] = true;
            if (!empty($sit['is_combo'])) $sessionComboMap[$skey] = true;
        }
    }

    // Snapshot shipping dimensions từ variant
    if ($items && $variantTable !== '' && $variantCols) {
        $variantIds = [];
        foreach ($items as $it) {
            $vid = (int)($it['variant_id'] ?? 0);
            if ($vid > 0) $variantIds[$vid] = $vid;
        }
        if ($variantIds) {
            $select = ['id', 'product_id'];
            foreach (['shipping_weight_value','shipping_weight_unit','shipping_length_cm','shipping_width_cm','shipping_height_cm'] as $c) {
                if (hasCol($variantCols, $c)) $select[] = $c;
            }
            $idList = array_values($variantIds);
            $ph = implode(',', array_fill(0, count($idList), '?'));
            $st = $ithanhloc->prepare('SELECT ' . implode(',', $select) . " FROM `{$variantTable}` WHERE id IN ($ph)");
            if ($st) {
                $st->bind_param(str_repeat('i', count($idList)), ...$idList);
                $st->execute();
                $resV = $st->get_result();
                $vMap = [];
                while ($v = $resV->fetch_assoc()) {
                    $vid = (int)($v['id'] ?? 0);
                    if ($vid > 0) $vMap[$vid] = $v;
                }
                $st->close();
                foreach ($items as &$it) {
                    if (!is_array($it)) continue;
                    $pid = (int)($it['id'] ?? $it['product_id'] ?? 0);
                    $vid = (int)($it['variant_id'] ?? 0);
                    if ($pid <= 0 || $vid <= 0 || !isset($vMap[$vid])) continue;
                    $v = $vMap[$vid];
                    if ((int)($v['product_id'] ?? 0) !== $pid) continue;
                    $wVal  = (float)($v['shipping_weight_value'] ?? 0);
                    $wUnit = normalizeWeightUnit((string)($v['shipping_weight_unit'] ?? 'kg'));
                    $wGram = convertWeightToGram($wVal, $wUnit);
                    if ($wGram > 0)  $it['weight'] = (int)$wGram;
                    $l  = (int)($v['shipping_length_cm'] ?? 0);
                    $wi = (int)($v['shipping_width_cm'] ?? 0);
                    $h  = (int)($v['shipping_height_cm'] ?? 0);
                    if ($l > 0)  { $it['length'] = $l; $it['length_cm'] = $l; }
                    if ($wi > 0) { $it['width'] = $wi;  $it['width_cm'] = $wi; }
                    if ($h > 0)  { $it['height'] = $h;  $it['height_cm'] = $h; }
                    if ($wVal > 0) { $it['weight_value'] = $wVal; $it['weight_unit'] = $wUnit; }
                    $it['weight_locked'] = 1;
                }
                unset($it);
            }
        }
    }

    // Fallback tên khách
    if ($user_name === '' && $uidInt > 0) {
        $st = $ithanhloc->prepare('SELECT full_name, username FROM users WHERE id=? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $uidInt);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) $user_name = trim((string)($r['full_name'] ?? '')) ?: trim((string)($r['username'] ?? ''));
        }
    }
    if ($user_name === '') $user_name = 'Khách hàng';

    if (!$items) jOut(['ok' => false, 'msg' => 'Giỏ hàng trống, không thể đặt hàng (có thể phiên làm việc đã hết hạn)']);

    // Rebuild pricing server-side (source of truth)
    $rebuiltItems = [];
    $subtotalBase = 0.0;
    $vatTotal     = 0.0;

    // 1. Chỉ giữ lại các sản phẩm chính (không phải quà tặng) để tính toán từ đầu
    $baseItemsOnly = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $pid       = (int)($it['id'] ?? $it['product_id'] ?? 0);
        $variantId = (int)($it['variant_id'] ?? 0);
        $colorCode = trim((string)($it['color_code'] ?? ''));
        $cartKey   = $pid . ':' . $variantId . ':' . strtoupper($colorCode);

        // Bỏ qua dòng bị coi là quà tặng từ phía client/session
        $isGift = !empty($it['is_gift']) || isset($sessionGiftMap[$cartKey]);
        if ($isGift) {
            continue;
        }
        $baseItemsOnly[] = $it;
    }

    // 2. Rebuild sản phẩm chính từ Database
    foreach ($baseItemsOnly as $it) {
        $pid       = (int)($it['id'] ?? $it['product_id'] ?? 0);
        $qtyReq    = (int)($it['qty'] ?? 0);
        $variantId = (int)($it['variant_id'] ?? 0);
        $colorCode = trim((string)($it['color_code'] ?? ''));
        if ($pid <= 0) continue;
        if ($qtyReq <= 0) jOut(['ok' => false, 'msg' => 'Số lượng sản phẩm không hợp lệ']);

        $canon = ecommerce_build_cart_item($ithanhloc, $pid, $variantId, $qtyReq, $colorCode);
        if (!$canon || !is_array($canon)) jOut(['ok' => false, 'msg' => 'Không đọc được thông tin sản phẩm để tạo đơn']);

        $canon['is_gift']  = 0;
        $canon['is_combo'] = !empty($it['is_combo']) || isset($sessionComboMap[$pid . ':' . $variantId . ':' . strtoupper($colorCode)]) ? 1 : 0;
        $canon['is_preorder'] = !empty($canon['is_preorder']) ? 1 : 0;
        
        foreach (['weight','length','width','height','weight_value','weight_unit','length_cm','width_cm','height_cm','weight_locked'] as $k) {
            if (array_key_exists($k, $it)) $canon[$k] = $it[$k];
        }

        $rebuiltItems[] = $canon;
    }

    // 3. Xác thực và Áp dụng giá Combo
    $comboPromoConfigs = [];
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
        if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
            $nowPromo = nowStr();
            $stmtPromo = $ithanhloc->prepare("SELECT config_json FROM ecommerce_product_promo WHERE promo_type = 'combo' AND is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)");
            if ($stmtPromo) {
                $stmtPromo->bind_param('ss', $nowPromo, $nowPromo);
                $stmtPromo->execute();
                $resPromo = $stmtPromo->get_result();
                while ($rowPromo = $resPromo->fetch_assoc()) {
                    $cfg = json_decode((string)($rowPromo['config_json'] ?? ''), true);
                    if (is_array($cfg)) $comboPromoConfigs[] = $cfg;
                }
                $stmtPromo->close();
            }
        }
    } catch (Throwable $e) {}

    foreach ($rebuiltItems as &$item) {
        $priceBase = (float)($item['price_base'] ?? 0);
        
        if (!empty($item['is_combo'])) {
            $pid = (int)($item['product_id'] ?? $item['id'] ?? 0);
            $isValidCombo = false;
            $comboPrice = null;
            
            foreach ($comboPromoConfigs as $cfg) {
                $mainSet = [];
                if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
                    foreach ($cfg['main_product_ids'] as $mid) {
                        $mid = (int)$mid;
                        if ($mid > 0) $mainSet[$mid] = true;
                    }
                }
                $legacyMain = (int)($cfg['main_product_id'] ?? 0);
                if ($legacyMain > 0) $mainSet[$legacyMain] = true;
                
                if (empty($cfg['items']) || !is_array($cfg['items'])) continue;
                
                $foundInCombo = false;
                foreach ($cfg['items'] as $cItem) {
                    if ((int)($cItem['product_id'] ?? 0) === $pid) {
                        $comboPrice = (float)($cItem['promo_price'] ?? 0);
                        $foundInCombo = true;
                        break;
                    }
                }
                if (!$foundInCombo) continue;
                
                // Kiểm tra xem sản phẩm chính của combo có nằm trong danh sách mua không
                foreach ($rebuiltItems as $mainIt) {
                    $mainPid = (int)($mainIt['product_id'] ?? $mainIt['id'] ?? 0);
                    if (!empty($mainSet[$mainPid]) && empty($mainIt['is_gift']) && empty($mainIt['is_combo'])) {
                        $isValidCombo = true;
                        break 2;
                    }
                }
            }
            
            if ($isValidCombo && $comboPrice !== null) {
                $priceBase = $comboPrice;
            } else {
                $item['is_combo'] = 0; // Hủy cờ combo do không hợp lệ (thiếu sản phẩm chính)
            }
        }
        
        $qty = (int)($item['qty'] ?? 1);
        if ($qty <= 0) jOut(['ok' => false, 'msg' => 'Sản phẩm đã hết hàng']);
        if ($priceBase <= 0 && !$item['is_combo']) {
            jOut(['ok' => false, 'msg' => 'Một số sản phẩm chưa có giá, vui lòng liên hệ cửa hàng trước khi đặt hàng.']);
        }
        
        $item['price'] = $priceBase;
        $item['price_base'] = $priceBase;
        $item['price_includes_vat'] = 0;
        $item['vat_enabled'] = 0;

        $lineBase = $priceBase * $qty;
        $subtotalBase += $lineBase;
        
        if ($invoice_want) {
            $vatPct = max(0.0, min(100.0, (float)($item['vat_percent'] ?? 0)));
            if ($vatPct > 0 && $lineBase > 0) {
                $vatTotal += round($lineBase * $vatPct / 100.0);
            }
        }
    }
    unset($item);

    // 4. Server tự động tính và chèn quà BXGY
    $promos = bxgy_get_active_promos($ithanhloc);
    $bxgyCalc = bxgy_compute_discount_for_items($rebuiltItems, $promos);
    $bxgyDiscount = (float)($bxgyCalc['total_discount'] ?? 0);
    $gifts = isset($bxgyCalc['gifts']) && is_array($bxgyCalc['gifts']) ? $bxgyCalc['gifts'] : [];

    foreach ($gifts as $g) {
        if (!is_array($g)) continue;
        $giftPid = (int)($g['product_id'] ?? 0);
        $qtyFree = (int)($g['qty_free'] ?? 0);
        $promoId = (int)($g['promo_id'] ?? 0);
        $promoName = (string)($g['promo_name'] ?? '');
        $giftVariantId = (int)($g['gift_variant_id'] ?? 0);
        if ($giftPid <= 0 || $qtyFree <= 0) continue;

        $giftItem = ecommerce_build_cart_item($ithanhloc, $giftPid, $giftVariantId, $qtyFree, '');
        if ($giftItem) {
            $giftItem['qty']              = $qtyFree;
            $giftItem['price']            = 0.0;
            $giftItem['price_base']       = 0.0;
            $giftItem['is_gift']          = 1;
            $giftItem['bxgy_promo_id']    = $promoId;
            $giftItem['bxgy_promo_name']  = $promoName;
            $rebuiltItems[] = $giftItem;
        }
    }

    // 5. Server tự động tính và chèn Quà tặng Hóa đơn (Invoice Gift)
    $giftThresholdByProductId = [];
    try {
        $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
        if ($hasPromoTbl && $hasPromoTbl->num_rows > 0) {
            $nowPromo = nowStr();
            $stmtPg = $ithanhloc->prepare("SELECT config_json FROM ecommerce_product_promo WHERE promo_type = 'gift' AND is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)");
            if ($stmtPg) {
                $stmtPg->bind_param('ss', $nowPromo, $nowPromo);
                $stmtPg->execute();
                $resPg = $stmtPg->get_result();
                while ($rowPg = $resPg->fetch_assoc()) {
                    $cfg = json_decode((string)($rowPg['config_json'] ?? ''), true);
                    if (!is_array($cfg)) continue;
                    $thresholdGift = (int)($cfg['threshold_amount'] ?? 0);
                    if (empty($cfg['gift_product_ids']) || !is_array($cfg['gift_product_ids'])) continue;
                    foreach ($cfg['gift_product_ids'] as $gid) {
                        $gid = (int)$gid;
                        if ($gid <= 0) continue;
                        if (!isset($giftThresholdByProductId[$gid]) || ($thresholdGift > 0 && $thresholdGift < $giftThresholdByProductId[$gid])) {
                            $giftThresholdByProductId[$gid] = $thresholdGift;
                        }
                    }
                }
                $stmtPg->close();
            }
        }
    } catch (Throwable $e) {}

    if ($giftThresholdByProductId && count($giftThresholdByProductId) === 1) {
        $giftPid = (int)array_key_first($giftThresholdByProductId);
        $threshold = (int)$giftThresholdByProductId[$giftPid];
        if ($threshold > 0 && $subtotalBase >= $threshold) {
            $variantId = 0;
            if ($variantTable !== '') {
                $sqlGV = "SELECT id FROM `{$variantTable}` WHERE product_id = ?{$variantActiveWhereExtra} ORDER BY price ASC, variant_name ASC LIMIT 1";
                if ($stmtGV = $ithanhloc->prepare($sqlGV)) {
                    $stmtGV->bind_param('i', $giftPid);
                    $stmtGV->execute();
                    $rowGV = $stmtGV->get_result()->fetch_assoc();
                    $stmtGV->close();
                    if ($rowGV && !empty($rowGV['id'])) {
                        $variantId = (int)$rowGV['id'];
                    }
                }
            }
            $invoiceGift = ecommerce_build_cart_item($ithanhloc, $giftPid, $variantId, 1, '');
            if ($invoiceGift) {
                $invoiceGift['price'] = 0.0;
                $invoiceGift['price_base'] = 0.0;
                $invoiceGift['qty'] = 1;
                $invoiceGift['is_gift'] = 1;
                $rebuiltItems[] = $invoiceGift;
            }
        }
    }

    $items = $rebuiltItems;
    if (!$items) jOut(['ok' => false, 'msg' => 'Giỏ hàng trống, không thể đặt hàng']);

    // Validate cơ bản
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    if ($address === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập địa chỉ giao hàng']);
    if ($phone === '' || strlen($phoneDigits) < 9) jOut(['ok' => false, 'msg' => 'Vui lòng nhập số điện thoại giao hàng hợp lệ']);

    $subtotal   = max(0.0, round($subtotalBase));
    $vatTotal   = max(0.0, round($vatTotal));
    $productIds = productIdsFromItems($items);

    // Đồng bộ session cart = items đã rebuild (giá DB chuẩn) TRƯỚC khi validate voucher.
    // ecommerce_voucher_validate() đọc $_SESSION['shop_cart'] để xác định scope ngành hàng/sản phẩm
    // và tính base_amount cho voucher category -> phải dùng giá DB, không phải giá cũ trong session.
    ecommerce_cart_set($items);

    // BUG5: Chuẩn hoá payment_method TRƯỚC khi validate voucher.
    // Nếu method client chọn không còn được bật, ép về 'cod' ngay để các bước validateVoucher
    // bên dưới dùng đúng method cuối cùng (tránh áp voucher chỉ-dành-cho-method-đã-tắt).
    $enabledKeys = array_map(fn($m) => $m['key'], ecommerce_get_enabled_payment_methods());
    if (!in_array($payment_method, $enabledKeys, true)) $payment_method = 'cod';

    // Voucher đơn hàng
    $voucherOrder = validateVoucher($ithanhloc, $voucher_order_code, $subtotal, $productIds, 'order', 0, $payment_method, $shipping_method_norm, 'web', $uidInt);
    // Nếu user có chọn mã giảm đơn nhưng mã không còn hợp lệ lúc đặt -> chặn + báo (không tạo đơn sai kỳ vọng)
    if ($voucher_order_code !== '' && !($voucherOrder['ok'] ?? false)) {
        jOut(['ok' => false, 'msg' => (string)($voucherOrder['msg'] ?? 'Mã giảm giá đơn hàng không còn khả dụng')]);
    }
    $discount     = ($voucherOrder['ok'] ?? false) ? (float)($voucherOrder['discount'] ?? 0) : 0;

    // Voucher ưu đãi thanh toán (tách riêng, không thay thế voucher giảm đơn)
    $voucherPayment = ['ok' => true, 'code' => '', 'discount' => 0, 'target' => 'order', 'msg' => ''];
    $payment_discount = 0.0;
    $voucher_payment_code = strtoupper(trim((string)$voucher_payment_code));
    if ($voucher_payment_code !== '') {
        // Không cho trùng với voucher giảm đơn/ship để tránh trừ 2 lần cùng 1 mã
        if ($voucher_order_code !== '' && strtoupper(trim((string)$voucher_order_code)) === $voucher_payment_code) {
            jOut(['ok' => false, 'msg' => 'Ưu đãi thanh toán không được trùng mã giảm giá đơn hàng.']);
        }
        if ($voucher_ship_code !== '' && strtoupper(trim((string)$voucher_ship_code)) === $voucher_payment_code) {
            jOut(['ok' => false, 'msg' => 'Ưu đãi thanh toán không được trùng mã giảm ship.']);
        }

        // Chỉ chấp nhận đúng template payment_discount
        $tpl = '';
        $stTpl = $ithanhloc->prepare('SELECT voucher_template FROM ecommerce_voucher WHERE UPPER(code)=? LIMIT 1');
        if ($stTpl) {
            $stTpl->bind_param('s', $voucher_payment_code);
            $stTpl->execute();
            $rTpl = $stTpl->get_result()->fetch_assoc();
            $stTpl->close();
            $tpl = strtolower(trim((string)($rTpl['voucher_template'] ?? '')));
        }
        if ($tpl !== 'payment_discount') {
            jOut(['ok' => false, 'msg' => 'Mã ưu đãi thanh toán không hợp lệ.']);
        }

        $voucherPayment = validateVoucher($ithanhloc, $voucher_payment_code, $subtotal, $productIds, 'order', 0, $payment_method, $shipping_method_norm, 'web', $uidInt);
        $payment_discount = ($voucherPayment['ok'] ?? false) ? (float)($voucherPayment['discount'] ?? 0) : 0.0;
        if (!($voucherPayment['ok'] ?? false)) {
            jOut(['ok' => false, 'msg' => (string)($voucherPayment['msg'] ?? 'Ưu đãi thanh toán không khả dụng')]);
        }
    }

    $selectedLocation = getSelectedLocationContext();
    $profile = null;
    if ($uidInt > 0) {
        $st = $ithanhloc->prepare('SELECT * FROM user_address WHERE user_id=? LIMIT 1');
        if ($st) { $st->bind_param('i', $uidInt); $st->execute(); $profile = $st->get_result()->fetch_assoc(); $st->close(); }
    }

    // Shipping
    $shippingQuote = buildShippingQuoteByLocation($ithanhloc, $subtotal, $profile, $shipping_method, $items);
    if (!empty($shippingQuote['blocked'])) {
        jOut(['ok' => false, 'msg' => trim((string)($shippingQuote['block_reason'] ?? '')) ?: 'Không thể đặt hàng do sản phẩm chưa cấu hình phương thức vận chuyển']);
    }
    $shipping_fee        = (float)($shippingQuote['shipping_fee'] ?? 0);
    $shipping_method_key = strtolower(trim((string)($shippingQuote['shipping_method'] ?? '')));
    if ($shipping_method_key === '') {
        $fb = strtolower(trim($shipping_method));
        $shipping_method_key = match($fb) {
            'nhanh'    => 'ghn_nhanh',
            'cong_kenh'=> 'ghn_tiet_kiem',
            'hoa_toc'  => 'ghn_hoa_toc',
            default    => $fb,
        };
    }
    if ($shipping_method_key === '') jOut(['ok' => false, 'msg' => 'Vui lòng chọn phương thức vận chuyển']);

    $shipping_method_label  = trim((string)($shippingQuote['shipping_method_text'] ?? '')) ?: strtoupper($shipping_method_key);
    $shipping_carrier       = (string)($shippingQuote['carrier_name'] ?? 'Giao hàng nhanh');
    $shipping_eta           = (string)($shippingQuote['eta_text'] ?? '');
    $shipping_rule_id       = (int)($shippingQuote['shipping_rule_id'] ?? 0);
    $shipping_snapshot_json = json_encode($shippingQuote, JSON_UNESCAPED_UNICODE);

    // Voucher shipping
    $voucherShip     = validateVoucher($ithanhloc, $voucher_ship_code, $subtotal, $productIds, 'shipping', $shipping_fee, $payment_method, $shipping_method_key, 'web', $uidInt);
    // Mã giảm ship được chọn nhưng không còn hợp lệ lúc đặt -> chặn + báo
    if ($voucher_ship_code !== '' && !($voucherShip['ok'] ?? false)) {
        jOut(['ok' => false, 'msg' => (string)($voucherShip['msg'] ?? 'Mã giảm phí vận chuyển không còn khả dụng')]);
    }
    $shipping_discount = ($voucherShip['ok'] ?? false) ? (float)($voucherShip['discount'] ?? 0) : 0;

    $pre_xu_total = max(0, ($subtotal + ($invoice_want ? $vatTotal : 0.0)) - $discount - $payment_discount + max(0, $shipping_fee - $shipping_discount));

    $xuCfg         = getXuConfig();
    $vndPerXu      = (int)$xuCfg['vnd_per_xu'];
    $maxUsePercent = (int)$xuCfg['max_use_percent'];

    // (payment_method đã được chuẩn hoá/ép 'cod' ở trên, trước khối validate voucher — xem BUG5)

    // Sinh mã đơn hàng
    $orderCode   = 'DH-' . date('ymd') . rand(1000, 9999);
    $orderId     = $orderCode;
    $orderIdType = getColumnType($ithanhloc, 'ecommerce_order', 'order_id');
    $useNumeric = ($orderIdType === '' || isNumericSqlType($orderIdType));
    if ($orderIdType !== '' && !isNumericSqlType($orderIdType)) $useNumeric = false;

    if ($useNumeric) {
        $typeLow = strtolower($orderIdType);
        $maxInt  = (strpos($typeLow, 'bigint') !== false) ? PHP_INT_MAX : ((strpos($typeLow, 'unsigned') !== false) ? 4294967295 : 2147483647);
        $base    = time();
        $picked  = null;
        $st = $ithanhloc->prepare('SELECT 1 FROM ecommerce_order WHERE order_id=? LIMIT 1');
        if ($st) {
            for ($i = 0; $i < 200; $i++) {
                $cand = min($base + random_int(0, 999999) + $i, $maxInt);
                if ($cand <= 0) continue;
                $cs = (string)$cand;
                $st->bind_param('s', $cs);
                $st->execute();
                if (!$st->get_result()->fetch_assoc()) { $picked = $cs; break; }
            }
            $st->close();
        }
        $orderId = $picked ?? (string)max(1, $base + random_int(1, 9999999));
    }

    $createdAt      = nowStr();
    $initialStatus  = 'pending';
    // payment_status chỉ phản ánh trạng thái thanh toán (paid/pending/failed/expired/unpaid...)
    // COD là "chưa thu tiền" nên dùng unpaid (không dùng 'cod' để tránh lẫn với payment_method)
    $paymentStatus  = ($payment_method === 'cod') ? 'unpaid' : 'pending';
    $paymentGateway = match($payment_method) {
        'vnpay' => 'vnpay', 'momo' => 'momo', 'zalopay' => 'zalopay', default => '',
    };

    // Validate & reserve stock (bỏ qua quà tặng)
    $reserveOps = [];
    foreach ($items as $it) {
        $pid = (int)($it['id'] ?? $it['product_id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        if ($qty <= 0 || $pid <= 0 || !empty($it['is_gift'])) continue;

        // Hàng đặt trước: cho mua vượt kho (kho có thể âm), không chặn "không đủ kho"
        $isPreorderItem = ecommerce_product_is_preorder($ithanhloc, $pid);

        $variantId = (int)($it['variant_id'] ?? 0);
        if ($variantId > 0) {
            $st = $ithanhloc->prepare('SELECT stock_quantity FROM ecommerce_product_variants WHERE id=? AND product_id=? LIMIT 1');
            if ($st) {
                $st->bind_param('ii', $variantId, $pid);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$isPreorderItem && (int)($row['stock_quantity'] ?? 0) < $qty) jOut(['ok' => false, 'msg' => 'Sản phẩm biến thể không đủ kho']);
                $reserveOps[] = ['pid' => $pid, 'vid' => $variantId, 'qty' => $qty, 'preorder' => $isPreorderItem];
            }
        } else {
            if ($variantTable !== '') {
                $hasV = false;
                $st = $ithanhloc->prepare("SELECT 1 FROM `{$variantTable}` WHERE product_id=?{$variantActiveWhereExtra} LIMIT 1");
                if ($st) { $st->bind_param('i', $pid); $st->execute(); $hasV = (bool)$st->get_result()->fetch_assoc(); $st->close(); }
                jOut(['ok' => false, 'msg' => $hasV
                    ? 'Vui lòng chọn phân loại (biến thể) của sản phẩm trước khi đặt hàng'
                    : 'Sản phẩm chưa có phân loại (biến thể) nên không thể đặt hàng']);
            }
            $pCols = listColumns($ithanhloc, 'ecommerce_product');
            $stockCol = '';
            foreach (['stock_quantity','stock','kho','ton_kho','so_luong'] as $c) {
                if ($pCols && hasCol($pCols, $c)) { $stockCol = $c; break; }
            }
            if ($stockCol !== '') {
                $st = $ithanhloc->prepare("SELECT `{$stockCol}` AS sq FROM ecommerce_product WHERE id=? LIMIT 1");
                if ($st) {
                    $st->bind_param('i', $pid); $st->execute();
                    $row = $st->get_result()->fetch_assoc(); $st->close();
                    if (!$isPreorderItem && (int)($row['sq'] ?? 0) < $qty) jOut(['ok' => false, 'msg' => 'Sản phẩm không đủ kho']);
                }
                // BUG1: sản phẩm bán theo cột kho ở ecommerce_product (không biến thể) cũng phải
                // được TRỪ kho, không chỉ kiểm tra. Đẩy vào reserveOps để trừ atomic trong transaction.
                $reserveOps[] = ['pid' => $pid, 'vid' => 0, 'qty' => $qty, 'preorder' => $isPreorderItem, 'stock_col' => $stockCol];
            }
        }
    }

    // Build column set (schema-adaptive)
    $cols = listColumns($ithanhloc, 'ecommerce_order');
    if (!$cols) jOut(['ok' => false, 'msg' => 'Không đọc được cấu trúc bảng ecommerce_order']);

    // Cờ đơn có hàng đặt trước (để admin lọc nhanh)
    $orderHasPreorder = 0;
    foreach ($items as $itChk) {
        if (!empty($itChk['is_preorder'])) { $orderHasPreorder = 1; break; }
    }

    $colMap = [
        'order_id' => $orderId, 'user_id' => (string)$uidInt, 'user_name' => $user_name,
        'created_at' => $createdAt, 'status' => $initialStatus, 'contact' => $user_name,
        'has_preorder' => $orderHasPreorder,
        'note' => $note, 'email' => $email, 'phone' => $phone, 'address' => $address,
        'payment_method' => $payment_method, 'payment_status' => $paymentStatus,
        'payment_gateway' => $paymentGateway, 'payment_ref' => $orderId, 'bank_code' => '',
        'products_json' => json_encode($items, JSON_UNESCAPED_UNICODE), 'subtotal' => $subtotal,
        'shipping_fee' => $shipping_fee, 'shipping_rule_id' => $shipping_rule_id,
        'shipping_method' => $shipping_method_key, 'shipping_method_label' => $shipping_method_label,
        'shipping_carrier' => $shipping_carrier, 'shipping_eta' => $shipping_eta,
        'shipping_snapshot_json' => $shipping_snapshot_json,
        'order_code' => $orderCode, 'system_order_id' => $orderCode,
        'voucher_code' => $voucher_order_code, 'voucher_shipping_code' => $voucher_ship_code,
        'discount_amount' => $discount, 'shipping_discount_amount' => $shipping_discount,
        'promo_discount_amount' => $bxgyDiscount,
    ];

    // Schema optional fields for separate payment discount
    foreach (['voucher_payment_code' => $voucher_payment_code, 'payment_discount_amount' => $payment_discount] as $k => $v) {
        if (hasCol($cols, $k)) {
            $colMap[$k] = $v;
        }
    }
    $set = [];
    foreach ($colMap as $k => $v) {
        if (hasCol($cols, $k)) $set[$k] = $v;
    }

    // payment_expires_at
    if (hasCol($cols, 'payment_expires_at') && $payment_method !== 'cod') {
        $expMin = match($payment_method) { 'momo' => 15, 'vnpay' => 30, default => 1440 };
        try {
            $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdAt, $tz) ?: new DateTimeImmutable('now', $tz);
            $set['payment_expires_at'] = ($expMin >= 1440)
                ? $dt->setTime(23, 59, 59)->format('Y-m-d H:i:s')
                : $dt->modify("+{$expMin} minutes")->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $set['payment_expires_at'] = date('Y-m-d H:i:s', time() + $expMin * 60);
        }
    }

    // VAT / Invoice flag
    foreach (['vat_amount','vat_total','tax_amount','tax_total','invoice_vat_amount'] as $c) {
        if (hasCol($cols, $c)) { $set[$c] = $invoice_want ? $vatTotal : 0.0; break; }
    }
    foreach (['invoice_want','want_invoice','is_invoice'] as $c) {
        if (hasCol($cols, $c)) { $set[$c] = $invoice_want ? 1 : 0; break; }
    }

    // Legacy fallback
    if (hasCol($cols, 'product') && !hasCol($cols, 'products_json')) {
        $set['product'] = implode(', ', array_filter(array_map(fn($it) => trim((string)($it['name'] ?? '')), $items)));
    }

    if (!isset($set['order_id']) || !isset($set['user_id'])) {
        jOut(['ok' => false, 'msg' => 'Bảng ecommerce_order thiếu thông tin order_id/user_id']);
    }

    // === Transaction ===
    $ithanhloc->begin_transaction();
    try {
        // Xu
        if ($uidInt > 0) {
            $walletBalance     = getWalletBalance($ithanhloc, $uidInt, true);
            $maxXuAllowed      = calcMaxXuRedeem($walletBalance, $pre_xu_total, $vndPerXu, $maxUsePercent);
            $xu_used           = min($xu_use_request, $maxXuAllowed);
            $xu_discount_amount = (float)($xu_used * $vndPerXu);
        } else {
            $xu_used = 0;
            $xu_discount_amount = 0.0;
        }

        $total_amount = max(0, $pre_xu_total - $xu_discount_amount);
        if ($total_amount > 0) $total_amount = (float)(round($total_amount / 1000) * 1000);
        if ($total_amount >= 100000000) {
            $ithanhloc->rollback();
            jOut(['ok' => false, 'msg' => 'Giá trị đơn hàng vượt giới hạn cho phép (tối đa 100.000.000 đ). Vui lòng tách thành nhiều đơn nhỏ hơn.']);
        }

        foreach (['total_amount' => $total_amount, 'xu_used' => $xu_used, 'xu_discount_amount' => $xu_discount_amount, 'xu_earned' => 0, 'xu_refunded' => 0] as $k => $v) {
            if (hasCol($cols, $k)) $set[$k] = $v;
        }

        // Build bind params & INSERT
        $fields = array_keys($set);
        $types  = '';
        $params = [];
        foreach ($fields as $f) {
            $v = $set[$f];
            $types .= is_float($v) ? 'd' : (is_int($v) ? 'i' : 's');
            $params[] = $v;
        }
        $ph  = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO ecommerce_order (`" . implode('`,`', $fields) . "`) VALUES ($ph)";
        $st  = $ithanhloc->prepare($sql);
        if (!$st) throw new Exception('Lỗi chuẩn bị câu lệnh: ' . $ithanhloc->error);
        if (!bindParamsDynamic($st, $types, $params)) { $st->close(); throw new Exception('Lỗi bind tham số'); }
        if (!$st->execute()) { $err = $st->error; $st->close(); throw new Exception('Không thể tạo đơn: ' . $err); }
        $st->close();

        // Lưu hoá đơn
        if ($invoice_want && $orderId !== '') {
            $invCols = listColumns($ithanhloc, 'ecommerce_order_invoice');
            if ($invCols) {
                $invoiceTableExists = true;
                $invMap = [
                    'order_id' => $orderId, 'user_id' => $uidInt,
                    'invoice_type' => ($invoice_type === 'company') ? 'company' : 'personal',
                    'buyer_name' => $invoice_buyer_name !== '' ? $invoice_buyer_name : $user_name,
                    'company_name' => $invoice_company_name, 'tax_code' => $invoice_tax_code,
                    'address' => $invoice_address !== '' ? $invoice_address : $address,
                    'email' => $invoice_email !== '' ? $invoice_email : $email,
                    'created_at' => $createdAt, 'updated_at' => $createdAt,
                ];
                $inv = [];
                foreach ($invMap as $k => $v) { if (in_array($k, $invCols, true)) $inv[$k] = $v; }
                if ($inv) {
                    $iFields = array_keys($inv);
                    $iTypes = ''; $iParams = [];
                    foreach ($iFields as $f) {
                        $v = $inv[$f];
                        $iTypes .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
                        $iParams[] = $v;
                    }
                    $iPh = implode(',', array_fill(0, count($iFields), '?'));
                    $st  = $ithanhloc->prepare("INSERT INTO ecommerce_order_invoice (`" . implode('`,`', $iFields) . "`) VALUES ($iPh)");
                    if (!$st) throw new Exception('Không thể lưu thông tin hoá đơn: ' . $ithanhloc->error);
                    if (!bindParamsDynamic($st, $iTypes, $iParams)) { $st->close(); throw new Exception('Lỗi bind tham số hoá đơn'); }
                    if (!$st->execute()) { $err = $st->error; $st->close(); throw new Exception('Không thể lưu thông tin hoá đơn: ' . $err); }
                    $invoiceSaved = true;
                    $st->close();
                }
            }
        }

        // Trừ xu (atomic: chỉ trừ khi balance >= xu_used, tránh race condition)
        if ($uidInt > 0 && $xu_used > 0) {
            $st = $ithanhloc->prepare('UPDATE users SET balance = balance - ? WHERE id=? AND COALESCE(balance,0) >= ?');
            if (!$st) throw new Exception('Không thể trừ số dư');
            $st->bind_param('iii', $xu_used, $uidInt, $xu_used);
            if (!$st->execute()) { $err = $st->error; $st->close(); throw new Exception('Không thể trừ xu: ' . $err); }
            if ($st->affected_rows <= 0) { $st->close(); throw new Exception('Số dư xu không đủ, vui lòng thử lại'); }
            $st->close();

            $txType = 'order_spent';
            $txAmount = -$xu_used;
            $txNote = 'Dùng xu cho đơn ' . $orderId;
            $st = $ithanhloc->prepare('INSERT IGNORE INTO user_balance_log (user_id, ref_order_id, type, amount, note) VALUES (?,?,?,?,?)');
            if (!$st) throw new Exception('Không thể ghi lịch sử ví xu');
            $st->bind_param('issis', $uidInt, $orderId, $txType, $txAmount, $txNote);
            $st->execute();
            if ($st->affected_rows <= 0) { $st->close(); throw new Exception('Giao dịch dùng xu đã tồn tại'); }
            $st->close();
        }

        // Reserve stock
        foreach ($reserveOps as $op) {
            // BUG1: vid === 0 => sản phẩm không biến thể, trừ kho trên bảng ecommerce_product theo cột tồn kho.
            // $op['stock_col'] luôn đến từ whitelist cố định ở trên nên an toàn để nội suy vào SQL.
            if ((int)($op['vid'] ?? 0) <= 0) {
                $stockCol = (string)($op['stock_col'] ?? '');
                if ($stockCol === '') continue; // không có cột kho => không quản lý tồn, bỏ qua
                if (!empty($op['preorder'])) {
                    // Hàng đặt trước: cho phép kho âm
                    $st = $ithanhloc->prepare("UPDATE ecommerce_product SET `{$stockCol}` = `{$stockCol}` - ? WHERE id=?");
                    if ($st) {
                        $st->bind_param('ii', $op['qty'], $op['pid']);
                        $st->execute();
                        $st->close();
                    }
                } else {
                    // Hàng thường (atomic: chỉ trừ khi còn đủ hàng)
                    $st = $ithanhloc->prepare("UPDATE ecommerce_product SET `{$stockCol}` = `{$stockCol}` - ? WHERE id=? AND `{$stockCol}` >= ?");
                    if ($st) {
                        $st->bind_param('iii', $op['qty'], $op['pid'], $op['qty']);
                        $st->execute();
                        if ($st->affected_rows <= 0) {
                            $st->close();
                            throw new Exception('Sản phẩm không đủ kho (#' . $op['pid'] . ')');
                        }
                        $st->close();
                    }
                }
            } elseif (!empty($op['preorder'])) {
                // Hàng đặt trước: trừ kho cho phép âm (không chặn theo tồn kho)
                $st = $ithanhloc->prepare('UPDATE ecommerce_product_variants SET stock_quantity = stock_quantity - ? WHERE id=? AND product_id=?');
                if ($st) {
                    $st->bind_param('iii', $op['qty'], $op['vid'], $op['pid']);
                    $st->execute();
                    $st->close();
                }
            } else {
                // Hàng thường (atomic: chỉ trừ khi còn đủ hàng, tránh race condition)
                $st = $ithanhloc->prepare('UPDATE ecommerce_product_variants SET stock_quantity = stock_quantity - ? WHERE id=? AND product_id=? AND stock_quantity >= ?');
                if ($st) {
                    $st->bind_param('iiii', $op['qty'], $op['vid'], $op['pid'], $op['qty']);
                    $st->execute();
                    if ($st->affected_rows <= 0) {
                        $st->close();
                        throw new Exception('Sản phẩm không đủ kho (biến thể #' . $op['vid'] . ')');
                    }
                    $st->close();
                }
            }
        }

        // Save profile
        if ($uidInt > 0) {
            $st = $ithanhloc->prepare('INSERT INTO user_address (user_id, user_name, phone, email, address, updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), phone=VALUES(phone), email=VALUES(email), address=VALUES(address), updated_at=NOW()');
            if ($st) { $st->bind_param('issss', $uidInt, $user_name, $phone, $email, $address); $st->execute(); $st->close(); }
        }

        // Increment voucher usage (atomic: chỉ tăng khi chưa vượt max_uses, tránh race condition)
        $usedCodes = array_values(array_unique(array_filter([
            ($voucherOrder['ok'] ?? false) && $voucher_order_code !== '' ? strtoupper($voucher_order_code) : '',
            ($voucherShip['ok'] ?? false) && $voucher_ship_code !== '' ? strtoupper($voucher_ship_code) : '',
            ($voucherPayment['ok'] ?? false) && $voucher_payment_code !== '' ? strtoupper($voucher_payment_code) : '',
        ])));
        if ($usedCodes) {
            // NOTE: max_uses/used_count may be stored as LONGTEXT in this DB.
            // Avoid string-based comparisons (e.g. '2' < '10' is false) by casting to unsigned.
            $st = $ithanhloc->prepare("UPDATE ecommerce_voucher
                SET used_count = CAST(COALESCE(used_count, '0') AS UNSIGNED) + 1
                WHERE UPPER(code)=?
                  AND (
                        max_uses IS NULL OR max_uses = '' OR CAST(max_uses AS UNSIGNED) <= 0
                        OR CAST(COALESCE(used_count, '0') AS UNSIGNED) < CAST(max_uses AS UNSIGNED)
                      )");
            if ($st) {
                foreach ($usedCodes as $c) {
                    $st->bind_param('s', $c);
                    $st->execute();
                    if ($st->affected_rows <= 0) {
                        $st->close();
                        throw new Exception('Mã giảm giá ' . $c . ' đã hết lượt sử dụng');
                    }
                }
                $st->close();
            }
        }

        $ithanhloc->commit();
    } catch (Throwable $e) {
        $ithanhloc->rollback();
        jOut(['ok' => false, 'msg' => $e->getMessage()]);
    }

    // Post-commit: log & notify
    if ($uidInt > 0) {
        $orderLink = '/view-order?order_id=' . urlencode($orderId);
        app_user_log($ithanhloc, $uidInt, 'checkout', 'Đặt hàng thành công', [
            'order_id' => $orderId, 'total' => $total_amount, 'payment_method' => $payment_method,
        ]);
		app_user_notify_template($ithanhloc, $uidInt, 'order_created', [
			'order_id' => $orderId,
			'amount' => number_format((float)$total_amount, 0, ',', '.'),
			'status' => $initialStatus,
			'time' => date('Y-m-d H:i:s'),
			'link' => $orderLink,
			'subtitle' => 'Bạn có đơn hàng mới với số tiền: ' . number_format((float)$total_amount, 0, ',', '.') . 'đ',
			'event' => 'order_created',
		]);
    }

    ecommerce_cart_set([]);
    if ($uidInt <= 0) {
        if (!isset($_SESSION['guest_orders'])) $_SESSION['guest_orders'] = [];
        $_SESSION['guest_orders'][] = $orderId;
    }

    $payload = [
        'ok' => true, 'order_id' => $orderId, 'subtotal' => $subtotal,
        'vat_total' => $invoice_want ? $vatTotal : 0.0, 'discount' => $discount,
        'shipping_discount' => $shipping_discount,
        'xu_used' => $xu_used ?? 0, 'xu_discount' => $xu_discount_amount ?? 0.0,
        'total' => $total_amount,
        'invoice_meta' => ['want' => $invoice_want, 'has_table' => $invoiceTableExists, 'saved' => $invoiceSaved],
    ];

    // === Payment Gateways ===
    if ($payment_method === 'vnpay') {
        $cfg        = is_array($ECOMMERCE_PAYMENT_METHODS['vnpay'] ?? null) ? $ECOMMERCE_PAYMENT_METHODS['vnpay'] : [];
        $tmnCode    = (string)($cfg['tmnCode'] ?? '');
        $hashSecret = (string)($cfg['hashSecret'] ?? '');
        $returnUrl  = (string)($cfg['returnUrl'] ?? '');
        $baseUrl    = rtrim((string)($baseUrl ?? ''), '/');
        if ($returnUrl === '' || stripos($returnUrl, 'order-confirm') !== false) {
            if ($baseUrl !== '') $returnUrl = $baseUrl . '/order-confirm';
        }
        $vnpUrl = (string)($cfg['payUrl'] ?? '') ?: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

        if ($tmnCode === '' || $hashSecret === '' || $returnUrl === '') {
            $payload['payment_error'] = 'Đang gặp sự cố với cổng thanh toán VNPAY, vui lòng chọn phương thức khác hoặc liên hệ cửa hàng';
        } else {
            date_default_timezone_set('Asia/Ho_Chi_Minh');
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
            $inputData = [
                'vnp_Version' => '2.1.0', 'vnp_Command' => 'pay', 'vnp_TmnCode' => $tmnCode,
                'vnp_Amount' => (int)round($total_amount * 100), 'vnp_CurrCode' => 'VND',
                'vnp_TxnRef' => $orderId, 'vnp_OrderInfo' => $company_bank_content_text . $orderId,
                'vnp_OrderType' => 'other', 'vnp_Locale' => 'vn', 'vnp_ReturnUrl' => $returnUrl,
                'vnp_IpAddr' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'vnp_CreateDate' => $now->format('YmdHis'),
                'vnp_ExpireDate' => $now->modify('+30 minutes')->format('YmdHis'),
            ];
            $paymentUrl = buildVnpayUrl($inputData, $hashSecret, $vnpUrl);
            $payload['payment_url'] = $paymentUrl;
            if (hasCol($cols, 'payment_meta_json') && $paymentUrl !== '') {
                $metaJson = json_encode(['gateway' => 'vnpay', 'pay_url' => $paymentUrl, 'created_at' => nowStr()], JSON_UNESCAPED_UNICODE);
                $st = $ithanhloc->prepare('UPDATE ecommerce_order SET payment_gateway="vnpay", payment_meta_json=?, updated_at=NOW() WHERE order_id=? LIMIT 1');
                if ($st) { $st->bind_param('ss', $metaJson, $orderId); $st->execute(); $st->close(); }
            }
        }

    } elseif ($payment_method === 'momo') {
        $momoResult = createMomoQrPayment([
            'order_id' => $orderId, 'amount' => $total_amount,
            'order_info' => $company_bank_content_text . $orderId, 'extra_data' => '',
        ]);
        if (!($momoResult['ok'] ?? false)) {
            $payload['payment_error'] = (string)($momoResult['msg'] ?? 'Không tạo được QR MoMo');
        } else {
            $meta = [
                'gateway' => 'momo', 'requestId' => (string)($momoResult['request_id'] ?? ''),
                'qrCodeUrl' => (string)($momoResult['qr_url'] ?? ''), 'payUrl' => (string)($momoResult['pay_url'] ?? ''),
                'deeplink' => (string)($momoResult['deeplink'] ?? ''), 'raw' => $momoResult['result'] ?? [], 'created_at' => nowStr(),
            ];
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $gatewayRef = (string)($momoResult['request_id'] ?? '');
            $st = $ithanhloc->prepare('UPDATE ecommerce_order SET payment_gateway="momo", payment_ref=?, payment_meta_json=?, gateway_tran_no=?, updated_at=NOW() WHERE order_id=? LIMIT 1');
            if ($st) { $st->bind_param('ssss', $orderId, $metaJson, $gatewayRef, $orderId); $st->execute(); $st->close(); }
            $payload['momo'] = [
                'qr_url' => (string)($momoResult['qr_url'] ?? ''), 'pay_url' => (string)($momoResult['pay_url'] ?? ''),
                'deeplink' => (string)($momoResult['deeplink'] ?? ''), 'request_id' => $gatewayRef,
            ];
        }

    } elseif ($payment_method === 'zalopay') {
        $cfg         = is_array($ECOMMERCE_PAYMENT_METHODS['zalopay'] ?? null) ? $ECOMMERCE_PAYMENT_METHODS['zalopay'] : [];
        $appId       = (int)($cfg['app_id'] ?? 0);
        $key1        = (string)($cfg['key1'] ?? '');
        $createUrl   = (string)($cfg['createUrl'] ?? '');
        $callbackUrl = (string)($cfg['callbackUrl'] ?? '');
        $redirectUrl = (string)($cfg['redirectUrl'] ?? '');

        if ($appId <= 0 || $key1 === '' || $createUrl === '') {
            $payload['payment_error'] = 'Đang gặp sự cố với cổng thanh toán ZaloPay, vui lòng chọn phương thức khác hoặc liên hệ cửa hàng';
        } else {
            $nowMs      = (int)round(microtime(true) * 1000);
            $appTransId = date('ymd') . '_' . $orderId . '_' . substr((string)$nowMs, -6);
            $embedData  = json_encode([
                'order_id' => $orderId,
                'redirecturl' => $redirectUrl !== '' ? $redirectUrl : (rtrim((string)($baseUrl ?? ''), '/') . '/order-confirm?order_id=' . $orderId),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $item = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $zpParams = [
                'app_id' => $appId, 'app_user' => $uidInt > 0 ? 'user_' . $uidInt : 'guest',
                'app_time' => $nowMs, 'amount' => (int)round($total_amount), 'app_trans_id' => $appTransId,
                'bank_code' => '', 'embed_data' => $embedData, 'item' => $item,
                'callback_url' => $callbackUrl, 'description' => $company_bank_content_text . $orderId,
            ];
            $zpParams['mac'] = hash_hmac('sha256', implode('|', [
                $zpParams['app_id'], $zpParams['app_trans_id'], $zpParams['app_user'],
                $zpParams['amount'], $zpParams['app_time'], $zpParams['embed_data'], $zpParams['item'],
            ]), $key1);

            $ch = curl_init($createUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($zpParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                $payload['payment_error'] = $err !== '' ? $err : 'Không thể kết nối đến ZaloPay';
            } else {
                $json = json_decode((string)$raw, true);
                if (!is_array($json)) {
                    $payload['payment_error'] = 'Đang gặp sự cố với cổng thanh toán ZaloPay (lỗi phân tích phản hồi)';
                } elseif ((int)($json['return_code'] ?? 0) !== 1) {
                    $payload['payment_error'] = (string)($json['return_message'] ?? 'Đang gặp sự cố với cổng thanh toán ZaloPay');
                } else {
                    $orderUrl = (string)($json['order_url'] ?? '');
                    $zpToken  = (string)($json['zp_trans_token'] ?? '');
                    $metaJson = json_encode(['gateway' => 'zalopay', 'zp_trans_token' => $zpToken, 'order_url' => $orderUrl, 'raw' => $json, 'created_at' => nowStr()], JSON_UNESCAPED_UNICODE);
                    if (hasCol($cols, 'payment_meta_json')) {
                        $st = $ithanhloc->prepare('UPDATE ecommerce_order SET payment_gateway="zalopay", payment_meta_json=?, gateway_tran_no=?, updated_at=NOW() WHERE order_id=? LIMIT 1');
                        if ($st) { $st->bind_param('sss', $metaJson, $zpToken, $orderId); $st->execute(); $st->close(); }
                    }
                    if ($orderUrl !== '') $payload['payment_url'] = $orderUrl;
                }
            }
        }
    }

    jOut($payload);
}

jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);


