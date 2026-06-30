<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/promotion_helper.php';

if (isset($ithanhloc) && $ithanhloc instanceof mysqli && function_exists('ensure_user_saved_voucher_schema')) {
    ensure_user_saved_voucher_schema($ithanhloc);
}
?>
<?php
// Core voucher parsing logic migrated to functions.php

if (!function_exists('listAvailableVouchers')) {
    function listAvailableVouchers(mysqli $ithanhloc, float $subtotal, array $productIds = [], string $target = 'order', float $shippingFee = 0, string $paymentMethod = '', string $shippingMethod = '', string $channel = 'web', int $userId = 0): array {
        $target = ecommerce_normalize_discount_target($target);
        $rows = ecommerce_voucher_fetch_active_list($ithanhloc, nowStr());

        $list = [];
        $itemContexts = [];
        $normalizedProductIds = normalizeProductIds($productIds);
        
        $cart = $_SESSION['shop_cart'] ?? [];
        if (is_array($cart) && count($cart) > 0) {
            foreach ($cart as $it) {
                if (!is_array($it) || !empty($it['is_gift'])) continue;
                $itemContexts[] = [
                    'pid' => (int)($it['product_id'] ?? $it['id'] ?? 0),
                    'vid' => (int)($it['variant_id'] ?? 0),
                    'gid' => (int)($it['group_id'] ?? 0),
                ];
            }
        } else {
            foreach ($normalizedProductIds as $pid) {
                $pid = (int)$pid;
                if ($pid <= 0) continue;
                $itemContexts[] = ['pid' => $pid, 'vid' => 0, 'gid' => 0];
            }
        }

        foreach ($rows as $row) {
            if (ecommerce_voucher_filter_eligible_keys($ithanhloc, $row, $itemContexts) === []) continue;

            $validated = ecommerce_voucher_validate($ithanhloc, (string)($row['code'] ?? ''), $subtotal, $normalizedProductIds, $target, $shippingFee, $paymentMethod, $shippingMethod, $channel, $userId, 'list');
            if (!($validated['ok'] ?? false)) continue;

            $code = strtoupper(trim((string)($row['code'] ?? '')));
            if ($code === '') continue;

            $value = (float)($row['value'] ?? 0);
            $valueUnit = ecommerce_voucher_normalize_value_unit($row['value_unit'] ?? '', $row['type'] ?? 'fixed');
            
            if (function_exists('format_voucher_value_label')) {
                $valueLabel = format_voucher_value_label($valueUnit, $value);
            } else {
                $valueLabel = ($valueUnit === 'percent') ? (rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%') : (string)((int)round($value));
            }

            $allowedTargets = ecommerce_voucher_allowed_targets($row);
            $list[] = [
                'id' => (int)($row['id'] ?? 0),
                'code' => $code,
                'voucher_template' => (string)($row['voucher_template'] ?? ''),
                'discount_target' => $allowedTargets ? implode(',', $allowedTargets) : $target,
                'type' => $valueUnit,
                'value_unit' => $valueUnit,
                'value' => $value,
                'value_label' => $valueLabel,
                'min_subtotal' => (float)($row['min_subtotal'] ?? 0),
                'min_subtotal_unit' => ecommerce_voucher_normalize_max_discount_unit($row['min_subtotal_unit'] ?? 'fixed'),
                'max_discount' => (float)($row['max_discount'] ?? 0),
                'max_discount_unit' => ecommerce_voucher_normalize_max_discount_unit($row['max_discount_unit'] ?? 'fixed'),
                'start_at' => $row['start_at'] ?? null,
                'end_at' => $row['end_at'] ?? null,
                'is_active' => (int)($row['is_active'] ?? 1),
                'promo_note' => (string)($row['promo_note'] ?? ''),
                'detail_text' => (string)($row['detail_text'] ?? ''),
                'used_count' => (int)($row['used_count'] ?? 0),
                'max_uses' => isset($row['max_uses']) ? (int)$row['max_uses'] : null,
                // Giới hạn phương thức thanh toán / vận chuyển (rỗng = không giới hạn).
                // Frontend cần để không tự đề xuất/áp voucher khi PT chưa khớp.
                'payment_methods' => ecommerce_voucher_parse_csv_list($row['payment_methods'] ?? ''),
                'shipping_methods' => ecommerce_voucher_parse_csv_list($row['shipping_methods'] ?? ''),
                // Số tiền giảm CHÍNH XÁC theo server (đã áp scope ngành hàng/sản phẩm + cap).
                // Frontend dùng để so chọn voucher tốt nhất, tránh tự tính sai trên toàn giỏ.
                'server_discount' => (float)($validated['discount'] ?? 0),
            ];
        }
        return $list;
    }
}


// Đánh dấu các voucher đã lưu trong danh sách voucher
function markSavedVouchers(array $coupons, array $savedCodes): array {
    if (!$coupons) return [];
    $savedSet = [];
    foreach ($savedCodes as $code) {
        $c = strtoupper(trim((string)$code));
        if ($c !== '') $savedSet[$c] = true;
    }
    foreach ($coupons as &$coupon) {
        $code = strtoupper(trim((string)($coupon['code'] ?? '')));
        $isSaved = isset($savedSet[$code]);
        // Backward-compatible flags
        $coupon['is_saved'] = $isSaved;
        $coupon['saved'] = $isSaved;
    }
    unset($coupon);
    return $coupons;
}

// Lọc ra các voucher đã lưu từ danh sách voucher
function extractSavedVouchers(array $coupons): array {
    $saved = [];
    foreach ($coupons as $coupon) {
        if (!is_array($coupon)) continue;
        if (!empty($coupon['saved'])) $saved[] = $coupon;
    }
    return array_values($saved);
}

function getActiveVouchersForDemo(mysqli $ithanhloc): array {
    $rows = ecommerce_voucher_fetch_active_list($ithanhloc, nowStr());
    if (!$rows) return [];
    return array_values(array_filter($rows, static function ($row) {
        return in_array('order', ecommerce_voucher_allowed_targets($row), true);
    }));
}

// Tạo label badge cho voucher giảm ship (demo)
function shipBadgeLabelForDemo(float $value, string $type): string {
    $type = strtolower(trim($type));
    if ($type === 'percent') {
        $pct = max(0, (float)$value);
        if ($pct >= 100) return '100%';
        $txt = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');
        return $txt . '%';
    }
    $amount = max(0, (float)$value);
    if ($amount >= 1000) {
        $k = $amount / 1000.0;
        $txt = (abs($k - round($k)) < 0.01)
            ? (string)round($k)
            : number_format($k, 1, '.', '');
        $txt = rtrim(rtrim($txt, '0'), '.');
        return $txt . 'K';
    }
    return ((int)round($amount)) . 'đ';
}
// Format label hiển thị cho mệnh giá voucher
if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {

    if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
        jOut(['ok' => false, 'msg' => 'Không thể kết nối cơ sở dữ liệu']);
    }

    $ajax = (string)($_REQUEST['ajax'] ?? $_GET['ajax'] ?? '');
    $action = (string)($_REQUEST['action'] ?? '');

    $setSessionVoucherCode = function (string $code, string $target = 'order'): void {
        $target = ecommerce_normalize_discount_target($target);
        $sessionKey = $target === 'shipping' ? 'selected_voucher_code_shipping' : 'selected_voucher_code_order';
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            unset($_SESSION[$sessionKey]);
            if ($target === 'order') unset($_SESSION['selected_voucher_code']);
            return;
        }
        $_SESSION[$sessionKey] = $normalized;
        if ($target === 'order') {
            $_SESSION['selected_voucher_code'] = $normalized;
        }
    };

    $clearSessionVoucherCode = function (string $target = 'order'): void {
        $target = ecommerce_normalize_discount_target($target);
        $sessionKey = $target === 'shipping' ? 'selected_voucher_code_shipping' : 'selected_voucher_code_order';
        unset($_SESSION[$sessionKey]);
        if ($target === 'order') unset($_SESSION['selected_voucher_code']);
    };

    // ======== Read-only AJAX ========
    // Rate limit cho validate_voucher (anti brute-force) — V8: dùng IP-based thay vì session-only
    // Lý do nâng cấp: cơ chế session-only dễ bypass bằng cách xóa cookie PHPSESSID.
    if ($ajax === 'validate_voucher' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'voucher_validate',
            10,  // tối đa 10 lần kiểm tra mã/phút/IP (giảm từ 20 → 10)
            60,
            'Bạn đã kiểm tra quá nhiều mã giảm giá. Vui lòng chờ 60 giây rồi thử lại.'
        );
    }

    if ($ajax === 'validate_voucher') {
        $code = $_GET['code'] ?? '';
        $target = ecommerce_normalize_discount_target($_GET['target'] ?? 'order');
        $subtotal = (float)($_GET['subtotal'] ?? 0);
        $shippingFee = (float)($_GET['shipping_fee'] ?? 0);
        $paymentMethod = (string)($_GET['payment_method'] ?? '');
        $shippingMethod = (string)($_GET['shipping_method'] ?? '');
        $channel = (string)($_GET['channel'] ?? 'web');
        $productIds = [];

        // Ưu tiên lấy danh sách product_id thực tế từ giỏ hàng trong session
        // để tự động loại trừ các dòng sản phẩm quà tặng (is_gift), tránh
        // trường hợp voucher được áp dụng chỉ vì trong giỏ có dòng quà 0đ.
        $cart = $_SESSION['shop_cart'] ?? [];
        $cartProductIds = productIdsFromItems(is_array($cart) ? $cart : []);

        if ($cartProductIds) {
            $productIds = $cartProductIds;
        } else {
            // Fallback: chỉ khi chưa có giỏ thực tế (ví dụ check demo ngoài giỏ),
            // mới dùng danh sách product_ids gửi từ client.
            if (isset($_GET['product_ids'])) {
                $raw = $_GET['product_ids'];
                $productIds = is_array($raw) ? normalizeProductIds($raw) : normalizeProductIds((string)$raw);
            }
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $result = ecommerce_voucher_validate($ithanhloc, $code, $subtotal, $productIds, $target, $shippingFee, $paymentMethod, $shippingMethod, $channel, $uid);
        jOut($result);
    }

    if ($ajax === 'vouchers_public') {
        $target = strtolower(trim((string)($_GET['target'] ?? 'all')));
        if (!in_array($target, ['all', 'order', 'shipping'], true)) $target = 'all';
        
        $rows = ecommerce_voucher_fetch_active_list($ithanhloc, nowStr());
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $channel = (string)($_GET['channel'] ?? 'web');
        $paymentMethod = (string)($_GET['payment_method'] ?? '');
        $shippingMethod = (string)($_GET['shipping_method'] ?? '');

        // Lọc theo product_ids nếu có
        $itemContexts = [];
        $filterProductIds = [];
        if (isset($_GET['product_ids'])) {
            $filterProductIds = normalizeProductIds($_GET['product_ids']);
        }

        if ($filterProductIds || (isset($_SESSION['shop_cart']) && is_array($_SESSION['shop_cart']) && count($_SESSION['shop_cart']) > 0)) {
            if (isset($_SESSION['shop_cart']) && is_array($_SESSION['shop_cart']) && count($_SESSION['shop_cart']) > 0) {
                foreach ($_SESSION['shop_cart'] as $it) {
                    if (!is_array($it) || !empty($it['is_gift'])) continue;
                    $itemContexts[] = [
                        'pid' => (int)($it['product_id'] ?? $it['id'] ?? 0),
                        'vid' => (int)($it['variant_id'] ?? 0),
                        'gid' => (int)($it['group_id'] ?? 0),
                    ];
                }
            } else {
                foreach ($filterProductIds as $pid) {
                    $itemContexts[] = ['pid' => (int)$pid, 'vid' => 0, 'gid' => 0];
                }
            }
        }

        $filtered = [];
        foreach ($rows as $row) {
            // 1. Kiểm tra target
            $allowedTargets = ecommerce_voucher_allowed_targets($row);
            if ($target !== 'all' && !in_array($target, $allowedTargets, true)) continue;

            // 2. Kiểm tra sản phẩm
            if ($itemContexts && ecommerce_voucher_filter_eligible_keys($ithanhloc, $row, $itemContexts) === []) continue;

            // 3. Kiểm tra kênh/thanh toán/vận chuyển/user
            if (!ecommerce_voucher_allows_channel($row, $channel)) continue;
            
            $pmAllowed = ecommerce_voucher_parse_csv_list($row['payment_methods'] ?? '');
            if ($pmAllowed && $paymentMethod !== '' && !in_array(strtolower($paymentMethod), $pmAllowed, true)) continue;
            
            $smAllowed = ecommerce_voucher_parse_csv_list($row['shipping_methods'] ?? '');
            if ($smAllowed && $shippingMethod !== '' && !in_array(strtolower($shippingMethod), $smAllowed, true)) continue;

            $userAllowed = ecommerce_voucher_parse_product_ids($row['apply_user_ids'] ?? '');
            if ($userAllowed && ($uid <= 0 || !in_array($uid, $userAllowed, true))) continue;

            // Chuẩn hoá dữ liệu trả về
            $value = (float)($row['value'] ?? 0);
            $unit = ecommerce_voucher_normalize_value_unit($row['value_unit'] ?? '', $row['type'] ?? 'fixed');
            
            $row['discount_target'] = implode(',', $allowedTargets);
            $row['type'] = $unit;
            $row['value_unit'] = $unit;
            $row['value'] = $value;
            $row['max_discount_unit'] = ecommerce_voucher_normalize_max_discount_unit($row['max_discount_unit'] ?? 'fixed');

            if (function_exists('format_voucher_value_label')) {
                $row['value_label'] = format_voucher_value_label($unit, $value);
            } else {
                $row['value_label'] = ($unit === 'percent') ? (rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%') : (string)((int)round($value));
            }
            
            $filtered[] = $row;
        }

        $savedCodes = $uid > 0 ? ecommerce_get_user_saved_voucher_codes($ithanhloc, $uid) : [];
        $final = markSavedVouchers($filtered, $savedCodes);

        if ((int)($_GET['library_only'] ?? 0) === 1) {
            $final = array_values(array_filter($final, function ($row) { return !empty($row['is_saved']); }));
        }

        jOut(['ok' => true, 'data' => $final]);
    }

    // Chi tiết 1 voucher theo mã (dùng cho trang xem chi tiết view-voucher).
    // Khác với vouchers_public: vẫn trả về voucher đã hết hạn / hết lượt để trang
    // chi tiết hiển thị được trạng thái thay vì báo "không tồn tại".
    if ($ajax === 'voucher_detail') {
        $code = strtoupper(trim((string)($_GET['code'] ?? '')));
        if ($code === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu mã voucher']);
        }

        $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_voucher WHERE UPPER(code)=? LIMIT 1');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Lỗi truy vấn']);
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            jOut(['ok' => false, 'msg' => 'Mã không tồn tại']);
        }

        // Chuẩn hoá dữ liệu giống vouchers_public để frontend dùng chung helper.
        $allowedTargets = ecommerce_voucher_allowed_targets($row);
        $value = (float)($row['value'] ?? 0);
        $unit = ecommerce_voucher_normalize_value_unit($row['value_unit'] ?? '', $row['type'] ?? 'fixed');

        $row['discount_target'] = implode(',', $allowedTargets);
        $row['type'] = $unit;
        $row['value_unit'] = $unit;
        $row['value'] = $value;
        $row['max_discount_unit'] = ecommerce_voucher_normalize_max_discount_unit($row['max_discount_unit'] ?? 'fixed');
        $row['min_subtotal_unit'] = ecommerce_voucher_normalize_max_discount_unit($row['min_subtotal_unit'] ?? 'fixed');

        if (function_exists('format_voucher_value_label')) {
            $row['value_label'] = format_voucher_value_label($unit, $value);
        } else {
            $row['value_label'] = ($unit === 'percent') ? (rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%') : (string)((int)round($value));
        }

        // Tính trạng thái hiệu lực theo thời gian + lượt dùng.
        $nowTs = time();
        $parseTs = static function (string $raw, bool $isEnd): ?int {
            $s = trim($raw);
            if ($s === '' || $s === '0000-00-00 00:00:00' || $s === '0000-00-00') return null;
            $s = str_replace('T', ' ', $s);
            $s = preg_replace('/\.(\d{1,6})(Z|[\+\-]\d{2}:?\d{2})?$/', '', (string)$s);
            $s = trim(preg_replace('/Z$/i', '', (string)$s));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) $s .= $isEnd ? ' 23:59:59' : ' 00:00:00';
            $ts = @strtotime($s);
            return $ts === false ? null : (int)$ts;
        };
        $startTs = $parseTs((string)($row['start_at'] ?? ''), false);
        $endTs = $parseTs((string)($row['end_at'] ?? ''), true);
        $maxUses = (isset($row['max_uses']) && $row['max_uses'] !== '' && $row['max_uses'] !== null) ? (int)$row['max_uses'] : null;
        $usedCount = (int)($row['used_count'] ?? 0);

        $status = 'active';
        if ((int)($row['is_active'] ?? 1) !== 1) {
            $status = 'inactive';
        } elseif ($startTs !== null && $nowTs < $startTs) {
            $status = 'scheduled';
        } elseif ($endTs !== null && $nowTs > $endTs) {
            $status = 'expired';
        } elseif ($maxUses !== null && $maxUses > 0 && $usedCount >= $maxUses) {
            $status = 'used_up';
        }
        $row['status'] = $status;
        $row['saveable'] = ($status === 'active' || $status === 'scheduled');

        // Đánh dấu đã lưu cho user hiện tại.
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $row['is_saved'] = false;
        $row['saved'] = false;
        if ($uid > 0) {
            $savedCodes = ecommerce_get_user_saved_voucher_codes($ithanhloc, $uid);
            $savedSet = array_map(static fn($c) => strtoupper(trim((string)$c)), $savedCodes);
            $isSaved = in_array($code, $savedSet, true);
            $row['is_saved'] = $isSaved;
            $row['saved'] = $isSaved;
        }
        $row['logged_in'] = $uid > 0;

        jOut(['ok' => true, 'data' => $row]);
    }

    if ($ajax === 'my_saved_vouchers') {
        if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 0) {
            jOut(['ok' => true, 'codes' => []]);
        }
        $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
        $codes = $uid >= 0 ? ecommerce_get_user_saved_voucher_codes($ithanhloc, $uid) : [];
        jOut(['ok' => true, 'codes' => $codes]);
    }

    // ======== Actions (write) ========
    if (strpos($action, 'voucher_') === 0) {
        app_verify_csrf(); // Bảo mật: Chỉ cho phép request có CSRF token hợp lệ

        // Rate limit cho write actions voucher (chống bot lưu/xóa mã hàng loạt)
        if (in_array($action, ['voucher_save', 'voucher_unsave', 'voucher_session_set'], true)
            && function_exists('app_rate_limit_response')) {
            app_rate_limit_response(
                'voucher_write',
                15, // tối đa 15 lần write/phút/IP
                60,
                'Bạn đang thực hiện quá nhiều thao tác với mã giảm giá. Vui lòng thử lại sau.'
            );
        }
    }

    if ($action === 'voucher_save') {
        if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 0) {
            jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $code = strtoupper(trim((string)($_REQUEST['code'] ?? '')));
        if ($code === '') {
            jOut(['ok' => false, 'msg' => 'Vui lòng nhập mã']);
        }
        if (!ecommerce_voucher_exists($ithanhloc, $code)) {
            jOut(['ok' => false, 'msg' => 'Mã không tồn tại']);
        }
        $stmt = $ithanhloc->prepare('INSERT INTO user_saved_voucher (user_id, voucher_code, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE created_at = NOW()');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không lưu được mã']);
        }
        $stmt->bind_param('is', $uid, $code);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $uid >= 0 && function_exists('app_user_log')) {
            app_user_log($ithanhloc, $uid, 'voucher_save', 'Lưu mã giảm giá', ['code' => $code]);
        }
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã lưu mã vào thư viện' : 'Không lưu được mã', 'code' => $code]);
    }

    if ($action === 'voucher_unsave') {
        if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 0) {
            jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $code = strtoupper(trim((string)($_REQUEST['code'] ?? '')));
        if ($code === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu mã']);
        }
        $stmt = $ithanhloc->prepare('DELETE FROM user_saved_voucher WHERE user_id=? AND voucher_code=?');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không xoá được mã']);
        }
        $stmt->bind_param('is', $uid, $code);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $uid >= 0 && function_exists('app_user_log')) {
            app_user_log($ithanhloc, $uid, 'voucher_unsave', 'Bỏ lưu mã giảm giá', ['code' => $code]);
        }
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xoá khỏi thư viện mã' : 'Không xoá được mã', 'code' => $code]);
    }

    if ($action === 'voucher_session_clear') {
        $target = ecommerce_normalize_discount_target($_REQUEST['target'] ?? 'order');
        $clearSessionVoucherCode($target);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid >= 0 && function_exists('app_user_log')) {
            app_user_log($ithanhloc, $uid, 'voucher_session_clear', 'Bỏ chọn mã giảm giá trong phiên', ['target' => $target]);
        }
        jOut(['ok' => true, 'msg' => 'Đã bỏ chọn mã', 'target' => $target]);
    }

    if ($action === 'voucher_session_set') {
        $target = ecommerce_normalize_discount_target($_REQUEST['target'] ?? 'order');
        $code = strtoupper(trim((string)($_REQUEST['code'] ?? '')));
        if ($code === '') {
            $clearSessionVoucherCode($target);
            jOut(['ok' => true, 'msg' => 'Đã bỏ chọn mã', 'target' => $target]);
        }
        if (!ecommerce_voucher_exists($ithanhloc, $code)) {
            jOut(['ok' => false, 'msg' => 'Mã không tồn tại', 'code' => $code, 'target' => $target]);
        }
        $setSessionVoucherCode($code, $target);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid >= 0 && function_exists('app_user_log')) {
            app_user_log($ithanhloc, $uid, 'voucher_session_set', 'Chọn mã giảm giá cho phiên', ['code' => $code, 'target' => $target]);
        }
        jOut(['ok' => true, 'msg' => 'Đã chọn mã', 'code' => $code, 'target' => $target]);
    }

    jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
}
