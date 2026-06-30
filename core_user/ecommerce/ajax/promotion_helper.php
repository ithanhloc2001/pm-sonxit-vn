<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/util.php';
?>
<?php
// Lấy giá (chưa VAT) của 1 biến thể quà theo variant_id, có cache để tránh query lặp.
// Dùng cho việc tính giá trị quà tặng đúng theo phân loại thực nhận.
if (!function_exists('bxgy_variant_price')) {
    function bxgy_variant_price(int $variantId): float {
        static $cache = [];
        $variantId = (int)$variantId;
        if ($variantId <= 0) return 0.0;
        if (isset($cache[$variantId])) return $cache[$variantId];
        $conn = $GLOBALS['ithanhloc'] ?? null;
        if (!($conn instanceof mysqli)) return 0.0;
        $price = 0.0;
        if ($stmt = $conn->prepare("SELECT price FROM ecommerce_product_variants WHERE id = ? LIMIT 1")) {
            $stmt->bind_param('i', $variantId);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $price = (float)($row['price'] ?? 0);
            }
            $stmt->close();
        }
        $cache[$variantId] = $price;
        return $price;
    }
}

// Tải danh sách chiến dịch Mua X tặng Y đang hoạt động
function bxgy_get_active_promos(mysqli $ithanhloc): array {
    static $promoTableExists = null;
    if ($promoTableExists === null) {
        try {
            $hasPromoTbl = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_product_promo'");
            $promoTableExists = ($hasPromoTbl instanceof mysqli_result) && ($hasPromoTbl->num_rows > 0);
        } catch (Throwable $e) {
            $promoTableExists = false;
        }
    }
    if (!$promoTableExists) return [];

    $now = nowStr();
    $stmt = $ithanhloc->prepare("SELECT id, name, promo_type, config_json, is_active, start_at, end_at, priority
        FROM ecommerce_product_promo
        WHERE promo_type = 'bxgy'
          AND is_active = 1
          AND (start_at IS NULL OR start_at <= ?)
          AND (end_at IS NULL OR end_at >= ?)
        ORDER BY priority DESC, id DESC");
    if (!$stmt) return [];
    $stmt->bind_param('ss', $now, $now);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $promos = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $cfg = json_decode((string)($row['config_json'] ?? ''), true);
        if (!is_array($cfg)) $cfg = [];
        $buyQty = (int)($cfg['buy_qty'] ?? 0);
        $giftQty = (int)($cfg['gift_qty'] ?? 0);
        if ($buyQty <= 0 || $giftQty <= 0) continue;
        $mainIds = [];
        if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
            foreach ($cfg['main_product_ids'] as $mid) {
                $mid = (int)$mid;
                if ($mid > 0 && !in_array($mid, $mainIds, true)) $mainIds[] = $mid;
            }
        }
        if (!$mainIds) continue;

        $mainVariantMap = [];
        if (!empty($cfg['main_variant_ids']) && is_array($cfg['main_variant_ids'])) {
            foreach ($cfg['main_variant_ids'] as $pidKey => $arr) {
                $pid = (int)$pidKey;
                if ($pid <= 0 || !in_array($pid, $mainIds, true)) continue;
                if (!is_array($arr)) continue;
                $uniq = [];
                foreach ($arr as $vid) {
                    $vid = (int)$vid;
                    if ($vid > 0) $uniq[$vid] = $vid;
                }
                if ($uniq) $mainVariantMap[$pid] = array_values($uniq);
            }
        }

        $giftProductIds = [];
        if (!empty($cfg['gift_product_ids']) && is_array($cfg['gift_product_ids'])) {
            foreach ($cfg['gift_product_ids'] as $gid) {
                $gid = (int)$gid;
                if ($gid > 0 && !in_array($gid, $giftProductIds, true)) $giftProductIds[] = $gid;
            }
        }

        $giftVariantMap = [];
        if (!empty($cfg['gift_variant_ids']) && is_array($cfg['gift_variant_ids'])) {
            foreach ($cfg['gift_variant_ids'] as $pidKey => $arr) {
                $pid = (int)$pidKey;
                if ($pid <= 0) continue;
                if (!is_array($arr)) continue;
                $uniq = [];
                foreach ($arr as $vid) {
                    $vid = (int)$vid;
                    if ($vid > 0) $uniq[$vid] = $vid;
                }
                if ($uniq) $giftVariantMap[$pid] = array_values($uniq);
            }
        }

        $promos[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'buy_qty' => $buyQty,
            'gift_qty' => $giftQty,
            'main_product_ids' => $mainIds,
            'main_variant_ids' => $mainVariantMap,
            // legacy: fixed gift product
            'gift_product_id' => (int)($cfg['gift_product_id'] ?? 0),
            // new: allow customer to choose from this list (if non-empty)
            'gift_product_ids' => $giftProductIds,
            'gift_variant_id' => (int)($cfg['gift_variant_id'] ?? 0),
            'gift_variant_ids' => $giftVariantMap,
            'max_gift_per_order' => (int)($cfg['max_gift_per_order'] ?? 0),
        ];
    }
    return $promos;
}

// Tính giảm giá Mua X tặng Y theo danh sách item giỏ hàng
// Trả về: total_discount, per_product_discount[product_id], gifts[] (tóm tắt sản phẩm tặng)
function bxgy_compute_discount_for_items(array $items, array $promos): array {
    $totalDiscount = 0.0;
    $perProductDiscount = [];
    $giftsSummary = [];
    $appliedPromosByKey = [];

    if (!$promos || !$items) {
        return [
            'total_discount' => 0.0,
            'per_product_discount' => [],
            'gifts' => [],
            'applied_promos' => [],
        ];
    }

    foreach ($promos as $promo) {
        if (!is_array($promo)) continue;
        $promoId = (int)($promo['id'] ?? 0);
        $promoName = (string)($promo['name'] ?? '');
        $buyQty = max(1, (int)($promo['buy_qty'] ?? 0));
        $giftQty = max(1, (int)($promo['gift_qty'] ?? 0));
        $mainIds = isset($promo['main_product_ids']) && is_array($promo['main_product_ids'])
            ? array_values(array_filter(array_map('intval', $promo['main_product_ids']), fn($v) => $v > 0))
            : [];
        if (!$mainIds) continue;
        $mainVariantMap = isset($promo['main_variant_ids']) && is_array($promo['main_variant_ids']) ? $promo['main_variant_ids'] : [];
        $giftProductIdCfg = (int)($promo['gift_product_id'] ?? 0);
        $giftProductIdsCfg = isset($promo['gift_product_ids']) && is_array($promo['gift_product_ids'])
            ? array_values(array_filter(array_map('intval', $promo['gift_product_ids']), fn($v) => $v > 0))
            : [];
        $giftVariantIdCfg = (int)($promo['gift_variant_id'] ?? 0);
        $giftVariantMap = isset($promo['gift_variant_ids']) && is_array($promo['gift_variant_ids']) ? $promo['gift_variant_ids'] : [];

        // Giới hạn tổng số quà / đơn cho riêng chiến dịch này (nếu > 0)
        $maxGiftPerOrder = max(0, (int)($promo['max_gift_per_order'] ?? 0));
        $grantedGiftsForPromo = 0;

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            // Bỏ qua dòng đã là quà tặng / deal khác
            if (!empty($it['is_gift'])) continue;
            $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
            if ($pid <= 0 || !in_array($pid, $mainIds, true)) continue;
            $qty = (int)($it['qty'] ?? 0);
            $price = (float)($it['price'] ?? 0);
            $variantId = (int)($it['variant_id'] ?? 0);
            if ($qty <= 0 || $price <= 0) continue;

            // Nếu promo giới hạn phân loại cho sản phẩm chính, chỉ áp dụng khi variant_id nằm trong danh sách
            $allowedMainVariants = [];
            if ($mainVariantMap && isset($mainVariantMap[$pid]) && is_array($mainVariantMap[$pid])) {
                $allowedMainVariants = array_values(array_filter(array_map('intval', $mainVariantMap[$pid]), fn($v) => $v > 0));
            } elseif ($mainVariantMap && isset($mainVariantMap[(string)$pid]) && is_array($mainVariantMap[(string)$pid])) {
                // fallback nếu key là string
                $allowedMainVariants = array_values(array_filter(array_map('intval', $mainVariantMap[(string)$pid]), fn($v) => $v > 0));
            }
            if ($allowedMainVariants) {
                if ($variantId <= 0 || !in_array($variantId, $allowedMainVariants, true)) {
                    continue;
                }
            }

            // Mua X tặng Y: cứ mỗi X sản phẩm mua (tính tiền) sẽ được tặng Y sản phẩm
            // Ví dụ: cấu hình Mua 2 tặng 1 (buy_qty=2, gift_qty=1)
            //  - Khách mua 2 sp  -> tặng 1 sp (hệ thống tự add 1 dòng quà)
            //  - Khách mua 4 sp  -> tặng 2 sp
            // => pattern = buy_qty, quà là sản phẩm tặng thêm, không tính vào X
            if ($buyQty <= 0) continue;
            $sets = intdiv($qty, $buyQty);
            if ($sets <= 0) continue;

            // Số quà lý thuyết sinh ra từ dòng này (chưa áp dụng giới hạn / đơn)
            $freeUnitsCandidate = $sets * $giftQty;
            if ($freeUnitsCandidate <= 0) continue;

            // Áp dụng giới hạn tối đa quà / đơn cho chiến dịch này (nếu có)
            if ($maxGiftPerOrder > 0) {
                $remaining = $maxGiftPerOrder - $grantedGiftsForPromo;
                if ($remaining <= 0) {
                    // Đã đạt trần quà của chiến dịch trong đơn này
                    continue;
                }
                $freeUnits = min($freeUnitsCandidate, $remaining);
            } else {
                $freeUnits = $freeUnitsCandidate;
            }

            if ($freeUnits <= 0) continue;

            // Xác định sản phẩm + biến thể được tặng
            // Ưu tiên:
            //  1) choice từ client theo promo_id (bxgy_gift_choice[promo_id])
            //  2) danh sách gift_product_ids trong config (lấy phần tử đầu làm mặc định nếu client không chọn)
            //  3) legacy gift_product_id (fixed)
            //  4) fallback: tặng cùng sản phẩm chính
            $choiceMap = $it['bxgy_gift_choice'] ?? null;
            if (is_string($choiceMap) && $choiceMap !== '') {
                $tmp = json_decode($choiceMap, true);
                if (is_array($tmp)) $choiceMap = $tmp;
            }
            $chosenGiftPid = 0;
            if (is_array($choiceMap) && isset($choiceMap[$promoId])) {
                $chosenGiftPid = (int)$choiceMap[$promoId];
            }

            // Nếu promo có danh sách quà cấu hình, chỉ chấp nhận lựa chọn nằm trong danh sách đó
            if ($chosenGiftPid > 0 && $giftProductIdsCfg && !in_array($chosenGiftPid, $giftProductIdsCfg, true)) {
                $chosenGiftPid = 0;
            }

            if ($chosenGiftPid > 0) {
                $giftPid = $chosenGiftPid;
                $giftVariantId = 0;
            } elseif ($giftProductIdsCfg) {
                $giftPid = (int)$giftProductIdsCfg[0];
                $giftVariantId = 0;
            } elseif ($giftProductIdCfg > 0) {
                $giftPid = $giftProductIdCfg;
                $giftVariantId = 0;
            } else {
                $giftPid = $pid;
                $giftVariantId = 0;
            }

            // (discount được tính bên dưới, sau khi đã xác định phân loại quà thực)

            // Chọn phân loại quà theo cấu hình (ưu tiên gift_variant_ids[product_id][0])
            $giftVariantIdsAllowed = [];
            if ($giftVariantMap && isset($giftVariantMap[$giftPid]) && is_array($giftVariantMap[$giftPid])) {
                $giftVariantIdsAllowed = array_values(array_filter(array_map('intval', $giftVariantMap[$giftPid]), fn($v) => $v > 0));
            } elseif ($giftVariantMap && isset($giftVariantMap[(string)$giftPid]) && is_array($giftVariantMap[(string)$giftPid])) {
                $giftVariantIdsAllowed = array_values(array_filter(array_map('intval', $giftVariantMap[(string)$giftPid]), fn($v) => $v > 0));
            }
            // Ưu tiên choice variant từ client theo promo_id (bxgy_gift_choice_variant[promo_id])
            $choiceVarMap = $it['bxgy_gift_choice_variant'] ?? null;
            if (is_string($choiceVarMap) && $choiceVarMap !== '') {
                $tmp = json_decode($choiceVarMap, true);
                if (is_array($tmp)) $choiceVarMap = $tmp;
            }
            $chosenGiftVariantId = 0;
            if (is_array($choiceVarMap) && isset($choiceVarMap[$promoId])) {
                $chosenGiftVariantId = (int)$choiceVarMap[$promoId];
            }
            // Nếu promo có danh sách phân loại quà cấu hình, chỉ chấp nhận lựa chọn nằm trong danh sách đó
            if ($chosenGiftVariantId > 0 && $giftVariantIdsAllowed && !in_array($chosenGiftVariantId, $giftVariantIdsAllowed, true)) {
                $chosenGiftVariantId = 0;
            }

            if ($chosenGiftVariantId > 0) {
                $giftVariantId = $chosenGiftVariantId;
            } elseif ($giftPid === $pid && $variantId > 0 && (!$giftVariantIdsAllowed || in_array($variantId, $giftVariantIdsAllowed, true))) {
                // (B) Mặc định tặng ĐÚNG phân loại khách đang mua nếu hợp lệ -> tránh nhảy sang variant lạ
                $giftVariantId = $variantId;
            } elseif ($giftVariantIdsAllowed) {
                $giftVariantId = (int)$giftVariantIdsAllowed[0];
            } elseif ($giftVariantIdCfg > 0) {
                $giftVariantId = $giftVariantIdCfg;
            } else {
                $giftVariantId = ($giftPid === $pid) ? $variantId : 0;
            }

            // (A) Tính discount = giá trị quà THỰC NHẬN (theo phân loại quà), không phải giá SP mua.
            // - Có variant quà cụ thể -> dùng giá variant đó.
            // - Quà cùng SP chính, không rõ variant -> fallback giá variant khách mua.
            $discount = 0.0;
            $giftUnitPrice = $giftVariantId > 0 ? bxgy_variant_price($giftVariantId) : 0.0;
            if ($giftUnitPrice <= 0 && $giftPid === $pid) {
                $giftUnitPrice = $price; // fallback: cùng SP chính, dùng giá variant mua
            }
            if ($giftUnitPrice > 0) {
                $discount = $freeUnits * $giftUnitPrice;
                $totalDiscount += $discount;
                if (!isset($perProductDiscount[$giftPid])) $perProductDiscount[$giftPid] = 0.0;
                $perProductDiscount[$giftPid] += $discount;
            }

            $itemKey = (string)($it['key'] ?? '');
            $key = $giftPid . ':' . $promoId . ':' . $giftVariantId . ':' . $itemKey;
            if (!isset($giftsSummary[$key])) {
                $giftsSummary[$key] = [
                    'promo_id' => $promoId,
                    'promo_name' => $promoName,
                    'product_id' => $giftPid,
                    'gift_variant_id' => $giftVariantId,
                    'qty_free' => 0,
                    'buy_qty' => $buyQty,
                    'gift_qty' => $giftQty,
                    'base_item_key' => $itemKey,
                ];
            }
            $giftsSummary[$key]['qty_free'] += $freeUnits;

            if ($itemKey !== '') {
                if (!isset($appliedPromosByKey[$itemKey]) || !is_array($appliedPromosByKey[$itemKey])) {
                    $appliedPromosByKey[$itemKey] = [];
                }
                $appliedPromosByKey[$itemKey][(string)$promoId] = [
                    'promo_id' => $promoId,
                    'promo_name' => $promoName,
                    'sets' => $sets,
                    'qty_free' => $freeUnits,
                    'gift_pid' => $giftPid,
                    'gift_variant_id' => $giftVariantId,
                ];
            }

            // Cập nhật tổng số quà đã cấp cho chiến dịch này trong đơn
            $grantedGiftsForPromo += $freeUnits;

        }
    }

    return [
        'total_discount' => $totalDiscount,
        'per_product_discount' => $perProductDiscount,
        'gifts' => array_values($giftsSummary),
        'applied_promos' => $appliedPromosByKey,
    ];
}

// Map giảm giá theo product_id từ giỏ hàng trong session (phục vụ voucher)
function bxgy_compute_discount_map_for_session_cart(mysqli $ithanhloc): array {
    $cart = isset($_SESSION['shop_cart']) && is_array($_SESSION['shop_cart']) ? $_SESSION['shop_cart'] : [];
    if (!$cart) return [];
    $promos = bxgy_get_active_promos($ithanhloc);
    if (!$promos) return [];
    $res = bxgy_compute_discount_for_items($cart, $promos);
    $map = $res['per_product_discount'] ?? [];
    return is_array($map) ? $map : [];
}
