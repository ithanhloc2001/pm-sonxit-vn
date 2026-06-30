<?php
$orderId = trim($_GET['order_id'] ?? '');
if ($orderId === '' && isset($_GET['vnp_TxnRef'])) {
    $orderId = trim((string)$_GET['vnp_TxnRef']);
}
if ($orderId === '' && isset($_GET['orderId'])) {
    $rawId = trim((string)$_GET['orderId']);
    // Tách lấy mã đơn gốc nếu có suffix (vd từ MoMo: DH123T1683... hoặc DH123_1683...)
    if (strpos($rawId, 'T') !== false) {
        $parts = explode('T', $rawId);
        $orderId = trim($parts[0]);
    } elseif (strpos($rawId, '_') !== false) {
        $parts = explode('_', $rawId);
        $orderId = trim($parts[0]);
    } else {
        $orderId = $rawId;
    }
}
if ($orderId === '' && isset($_GET['requestId'])) {
    $req = trim((string)$_GET['requestId']);
    if ($req !== '') {
        $parts = explode('_', $req);
        $orderId = trim((string)($parts[0] ?? $req));
        if (strpos($orderId, 'T') !== false) {
            $partsT = explode('T', $orderId);
            $orderId = trim($partsT[0]);
        }
    }
}
// Với ZaloPay, nếu chưa có order_id thì tách từ apptransid (vd: 260406_DH-2604067143)
if ($orderId === '' && isset($_GET['apptransid'])) {
    $appTransId = trim((string)$_GET['apptransid']);
    if ($appTransId !== '') {
        $parts = explode('_', $appTransId);
        if (count($parts) >= 2) {
            $orderId = trim((string)$parts[1]);
        } else {
            $orderId = trim((string)$appTransId);
        }
    }
}
$zaloStatus = '';
$vnpParams = [];
$vnpValid = null;
$vnpMessage = '';
$vnpStatus = '';
$momoStatus = '';
$momoMessage = '';

// Xử lý kết quả trả về từ các cổng thanh toán (ZaloPay, VNPAY, MoMo)
if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
    [$orderId, $zaloStatus] = orderConfirmHandleZaloReturn($ithanhloc, $orderId);
    [$vnpStatus, $vnpMessage] = orderConfirmHandleVnpayReturn($ithanhloc, $orderId, $ECOMMERCE_PAYMENT_METHODS ?? []);
    [$momoStatus, $momoMessage] = orderConfirmHandleMomoReturn($ithanhloc, $orderId);
}

// Chuyển hướng để xóa các tham số callback trên URL (tránh lộ thông tin và lỗi hiển thị)
if ($orderId !== '' && (isset($_GET['apptransid']) || isset($_GET['vnp_ResponseCode']) || isset($_GET['resultCode']) || isset($_GET['vnp_TxnRef']) || isset($_GET['requestId']))) {
    $cleanUrl = rtrim((string)($baseUrl ?? ''), '/') . '/order-confirm?order_id=' . urlencode($orderId);
    if (!headers_sent()) {
        header("Location: " . $cleanUrl);
        exit;
    } else {
        echo '<script>window.location.replace(' . json_encode($cleanUrl) . ');</script>';
        exit;
    }
}

$bootstrapOrder = null;
$orderNotFound = false;
$allowChangePayment = isset($_GET['change_payment']) && $_GET['change_payment'] !== '0';
$changePaymentMethods = [];
if (isset($ECOMMERCE_PAYMENT_METHODS) && is_array($ECOMMERCE_PAYMENT_METHODS)) {
    $methodLabels = [
        'cod' => 'Thanh toán khi nhận hàng',
        'momo' => 'Ví MoMo',
        'vnpay' => 'Ví VN PAY',
        'zalopay' => 'Ví ZaloPay',
    ];
    foreach (['cod', 'zalopay', 'vnpay', 'momo'] as $key) {
        $cfg = is_array($ECOMMERCE_PAYMENT_METHODS[$key] ?? null) ? $ECOMMERCE_PAYMENT_METHODS[$key] : [];
        if (!empty($cfg['enabled'])) {
            $changePaymentMethods[] = [
                'key' => $key,
                'label' => $methodLabels[$key] ?? strtoupper($key),
            ];
        }
    }
}


function orderConfirmParseFlexibleTs($value): int
{
    if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)))) {
        $num = (int)$value;
        if ($num > 1000000000000) {
            $num = (int)floor($num / 1000);
        }
        return $num > 0 ? $num : 0;
    }
    $txt = trim((string)$value);
    if ($txt === '') return 0;
    $ts = strtotime($txt);
    return $ts !== false ? (int)$ts : 0;
}
// Hàm này cố gắng tìm timestamp hết hạn của đơn hàng từ nhiều trường khác nhau trong payment_meta (đặc biệt với MoMo có nhiều biến thể tên trường và format khác nhau)
function orderConfirmExtractMomoExpireTs(array $paymentMeta): int
{
    $raw = is_array($paymentMeta['raw'] ?? null) ? $paymentMeta['raw'] : [];
    $candidates = [
        $paymentMeta['expire_ts'] ?? null,
        $paymentMeta['expired_ts'] ?? null,
        $paymentMeta['expireTime'] ?? null,
        $paymentMeta['expiredTime'] ?? null,
        $paymentMeta['expiresAt'] ?? null,
        $paymentMeta['expire_at'] ?? null,
        $raw['expire_ts'] ?? null,
        $raw['expired_ts'] ?? null,
        $raw['expireTime'] ?? null,
        $raw['expiredTime'] ?? null,
        $raw['expiresAt'] ?? null,
        $raw['expire_at'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $ts = orderConfirmParseFlexibleTs($candidate);
        if ($ts > 0) return $ts;
    }

    $createdRaw = trim((string)($paymentMeta['created_at'] ?? ($raw['created_at'] ?? '')));
    if ($createdRaw !== '') {
        $createdTs = orderConfirmParseFlexibleTs($createdRaw);
        if ($createdTs > 0) return $createdTs + (15 * 60);
    }
    return 0;
}

/**
 * Xử lý callback/redirect từ ZaloPay: Cảnh báo: Redirect URL của ZaloPay KHÔNG có chữ ký.
 * Do đó ta KHÔNG ĐƯỢC tin vào tham số trên URL để cập nhật DB. 
 * Ta phải chủ động gọi API Query của ZaloPay để xác thực.
 */
function orderConfirmHandleZaloReturn(mysqli $ithanhloc, string $orderId): array
{
    $zaloStatus = '';
    if (!isset($_GET['apptransid'], $_GET['status'])) return [$orderId, $zaloStatus];

    $zStatus = trim((string)$_GET['status']);
    $zAppTransId = trim((string)$_GET['apptransid']);

    if ($zStatus === '1' && $orderId !== '') {
        $cfg = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay');
        $appId = (string)($cfg['app_id'] ?? '');
        $key1 = (string)($cfg['key1'] ?? '');
        $queryUrl = (string)($cfg['queryUrl'] ?? 'https://sb-openapi.zalopay.vn/v2/query');

        if ($appId !== '' && $key1 !== '') {
            $data = $appId . "|" . $zAppTransId . "|" . $key1;
            $mac = hash_hmac('sha256', $data, $key1);
            $params = ['app_id' => (int)$appId, 'app_trans_id' => $zAppTransId, 'mac' => $mac];

            $ch = curl_init($queryUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$res, true);

            if (isset($json['return_code']) && (int)$json['return_code'] === 1) {
                $cols = listColumns($ithanhloc, 'ecommerce_order');
                if ($cols) {
                    // Merge zp_trans_id từ query response vào payment_meta_json để dùng cho refund tự động sau này
                    $zpTransId = (string)($json['zp_trans_id'] ?? '');
                    $existingMeta = [];
                    $stMeta = $ithanhloc->prepare('SELECT payment_meta_json FROM ecommerce_order WHERE order_id=? LIMIT 1');
                    if ($stMeta) {
                        $stMeta->bind_param('s', $orderId);
                        $stMeta->execute();
                        $row = $stMeta->get_result()->fetch_assoc();
                        $stMeta->close();
                        if ($row && !empty($row['payment_meta_json'])) {
                            $tmp = json_decode($row['payment_meta_json'], true);
                            if (is_array($tmp)) $existingMeta = $tmp;
                        }
                    }
                    if ($zpTransId !== '') $existingMeta['zp_trans_id'] = $zpTransId;
                    $existingMeta['last_raw'] = $json;
                    $existingMeta['paid_at'] = date('Y-m-d H:i:s');
                    $newMetaJson = json_encode($existingMeta, JSON_UNESCAPED_UNICODE);

                    $set = ['payment_status' => 'paid', 'payment_gateway' => 'zalopay', 'status' => 'processing', 'paid_at' => date('Y-m-d H:i:s'), 'payment_meta_json' => $newMetaJson];
                    if (isset($cols['bank_tran_no']) && $zpTransId !== '') {
                        $set['bank_tran_no'] = $zpTransId;
                    }
                    $fields = array_keys($set);
                    $types = str_repeat('s', count($fields)) . 's';
                    $vals = array_values($set);
                    $vals[] = $orderId;
                    $sql = "UPDATE ecommerce_order SET " . implode(', ', array_map(fn($f) => "`$f`=?", $fields)) . " WHERE order_id=? AND payment_status != 'paid'";
                    $st = $ithanhloc->prepare($sql);
                    if ($st) {
                        bindParamsDynamic($st, $types, $vals);
                        $st->execute();
                        $st->close();
                    }
                }
                $zaloStatus = '1';
            } else {
                $zaloStatus = 'fail_query';
            }
        }
    }
    return [$orderId, $zaloStatus];
}

/**
 * Xử lý callback VNPAY: kiểm tra chữ ký, cập nhật trạng thái thanh toán, tạo thông báo.
 */
function orderConfirmHandleVnpayReturn(mysqli $ithanhloc, string $orderId, array $paymentMethods): array
{
    $vnpStatus = '';
    $vnpMessage = '';

    if (!isset($_GET['vnp_ResponseCode'])) {
        return [$vnpStatus, $vnpMessage];
    }

    $vnpParams = [];
    $vnpValid = null;

    $cfg = is_array($paymentMethods['vnpay'] ?? null) ? $paymentMethods['vnpay'] : [];
    $hashSecret = (string)($cfg['hashSecret'] ?? '');

    foreach ($_GET as $key => $value) {
        if (substr($key, 0, 4) === 'vnp_') {
            $vnpParams[$key] = $value;
        }
    }
    $vnpSecureHash = $vnpParams['vnp_SecureHash'] ?? '';
    unset($vnpParams['vnp_SecureHash'], $vnpParams['vnp_SecureHashType']);
    ksort($vnpParams);
    $hashData = '';
    $i = 0;
    foreach ($vnpParams as $key => $value) {
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode($value);
            $i = 1;
        }
    }
    $secureHash = $hashSecret !== '' ? hash_hmac('sha512', $hashData, $hashSecret) : '';
    $vnpValid = ($secureHash !== '' && $secureHash === $vnpSecureHash);

    $respCode = (string)($vnpParams['vnp_ResponseCode'] ?? '');
    $tranStatus = (string)($vnpParams['vnp_TransactionStatus'] ?? '');
    if ($vnpValid && $respCode === '00' && $tranStatus === '00') {
        $vnpStatus = 'success';
        $vnpMessage = 'Thanh toán VNPAY thành công.';
    } elseif ($vnpValid) {
        $vnpStatus = 'fail';
        $vnpMessage = 'Thanh toán VNPAY chưa thành công. Vui lòng kiểm tra lại đơn hàng.';
    } else {
        $vnpStatus = 'invalid';
        $vnpMessage = 'Chữ ký không hợp lệ. Vui lòng liên hệ hỗ trợ.';
    }

    if ($vnpValid && $respCode === '00' && $tranStatus === '00' && $orderId !== '') {
        $cols = listColumns($ithanhloc, 'ecommerce_order');
        if ($cols) {
            $stmt = $ithanhloc->prepare('SELECT user_id, payment_status, total_amount FROM ecommerce_order WHERE order_id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $orderId);
                $stmt->execute();
                $orderRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $alreadyPaid = isset($orderRow['payment_status']) && $orderRow['payment_status'] === 'paid';
                $orderUserId = (int)($orderRow['user_id'] ?? 0);
                if (!$alreadyPaid) {
                    $amount = isset($vnpParams['vnp_Amount']) ? ((float)$vnpParams['vnp_Amount'] / 100.0) : 0.0;
                    if (isset($orderRow['total_amount'])) {
                        $expected = (float)($orderRow['total_amount'] ?? 0);
                        if ($expected > 0 && abs($expected - $amount) > 0.5) {
                            $amount = 0.0;
                        }
                    }

                    $set = [];
                    if (hasCol($cols, 'payment_status')) {
                        $set['payment_status'] = 'paid';
                    }
                    if (hasCol($cols, 'payment_gateway')) {
                        $set['payment_gateway'] = 'vnpay';
                    }
                    if (hasCol($cols, 'payment_ref')) {
                        $set['payment_ref'] = $orderId;
                    }
                    if (hasCol($cols, 'bank_code')) {
                        $set['bank_code'] = (string)($vnpParams['vnp_BankCode'] ?? '');
                    }
                    if (hasCol($cols, 'bank_tran_no')) {
                        $set['bank_tran_no'] = (string)($vnpParams['vnp_BankTranNo'] ?? '');
                    }
                    if (hasCol($cols, 'gateway_tran_no')) {
                        $set['gateway_tran_no'] = (string)($vnpParams['vnp_TransactionNo'] ?? '');
                    }
                    if (hasCol($cols, 'payment_response_code')) {
                        $set['payment_response_code'] = $respCode;
                    }
                    if (hasCol($cols, 'paid_at')) {
                        $set['paid_at'] = date('Y-m-d H:i:s');
                    }
                    if (hasCol($cols, 'status')) {
                        $set['status'] = 'processing';
                    }

                    if ($set) {
                        $fields = array_keys($set);
                        $types = str_repeat('s', count($fields)) . 's';
                        $params = array_values($set);
                        $params[] = $orderId;
                        $sql = "UPDATE ecommerce_order SET " . implode(', ', array_map(fn($f) => "`$f`=?", $fields)) . " WHERE order_id=?";
                        $stmtU = $ithanhloc->prepare($sql);
                        if ($stmtU) {
                            bindParamsDynamic($stmtU, $types, $params);
                            $stmtU->execute();
                            $stmtU->close();
                        }
                    }

                    if ($orderUserId > 0) {
                        $notifyAmount = $amount > 0 ? $amount : (float)($orderRow['total_amount'] ?? 0);
                        app_user_notify_template($ithanhloc, $orderUserId, 'payment_success', [
                            'order_id' => $orderId,
                            'amount' => number_format($notifyAmount, 0, '.', ''),
                            'gateway' => 'VNPAY',
                            'time' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
        }
    }

    return [$vnpStatus, $vnpMessage];
}

/**
 * Xử lý callback MoMo: đọc resultCode, cập nhật trạng thái thanh toán, tạo thông báo.
 */
function orderConfirmHandleMomoReturn(mysqli $ithanhloc, string $orderId): array
{
    $momoStatus = '';
    $momoMessage = '';
    if (!isset($_GET['resultCode'], $_GET['signature'])) return [$momoStatus, $momoMessage];

    $cfg = app_get_momo_config_by_env();
    $secretKey = (string)($cfg['secretKey'] ?? '');

    $params = $_GET;
    $momoSig = $params['signature'] ?? '';
    unset($params['signature']);
    ksort($params);
    $rawHash = "";
    foreach ($params as $key => $val) {
        $rawHash .= ($rawHash === "" ? "" : "&") . $key . "=" . $val;
    }
    $expectedSig = hash_hmac("sha256", $rawHash, $secretKey);

    if ($momoSig !== $expectedSig) {
        return ['invalid_sig', 'Chữ ký MoMo không hợp lệ!'];
    }

    $momoResultCode = (string)($_GET['resultCode'] ?? '');
    if ($momoResultCode === '0' && $orderId !== '') {
        $cols = listColumns($ithanhloc, 'ecommerce_order');
        if ($cols) {
            $set = ['payment_status' => 'paid', 'payment_gateway' => 'momo', 'status' => 'processing', 'paid_at' => date('Y-m-d H:i:s')];
            $fields = array_keys($set);
            $types = str_repeat('s', count($fields)) . 's';
            $vals = array_values($set);
            $vals[] = $orderId;
            $sql = "UPDATE ecommerce_order SET " . implode(', ', array_map(fn($f) => "`$f`=?", $fields)) . " WHERE order_id=? AND payment_status != 'paid'";
            $st = $ithanhloc->prepare($sql);
            if ($st) {
                bindParamsDynamic($st, $types, $vals);
                $st->execute();
                $st->close();
            }
            $momoStatus = 'success';
            $momoMessage = 'Thanh toán MoMo thành công.';
        }
    } else {
        $momoStatus = 'fail';
        $momoMessage = 'Thanh toán MoMo thất bại.';
    }

    return [$momoStatus, $momoMessage];
}

if (!function_exists('momo_create_signature_for_confirm')) {
    function momo_create_signature_for_confirm(array $payload, string $secretKey): string
    {
        $raw = 'accessKey=' . ($payload['accessKey'] ?? '')
            . '&amount=' . ($payload['amount'] ?? '')
            . '&extraData=' . ($payload['extraData'] ?? '')
            . '&ipnUrl=' . ($payload['ipnUrl'] ?? '')
            . '&orderId=' . ($payload['orderId'] ?? '')
            . '&orderInfo=' . ($payload['orderInfo'] ?? '')
            . '&partnerCode=' . ($payload['partnerCode'] ?? '')
            . '&redirectUrl=' . ($payload['redirectUrl'] ?? '')
            . '&requestId=' . ($payload['requestId'] ?? '')
            . '&requestType=' . ($payload['requestType'] ?? 'captureWallet');
        return hash_hmac('sha256', $raw, $secretKey);
    }
}

if (!function_exists('momo_post_json_for_confirm')) {
    function momo_post_json_for_confirm(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'msg' => $err !== '' ? $err : 'MoMo API unreachable'];
        }
        $json = json_decode((string)$raw, true);
        return is_array($json) ? ['ok' => true, 'data' => $json] : ['ok' => false, 'msg' => 'MoMo API invalid JSON'];
    }
}

if (!function_exists('momo_regenerate_qr_for_confirm')) {
    function momo_regenerate_qr_for_confirm(mysqli $ithanhloc, array $orderRow): array
    {
        $cfg = app_get_momo_config_by_env();
        if (empty($cfg['enabled'])) return [];

        $partnerCode = trim((string)($cfg['partnerCode'] ?? ''));
        $accessKey = trim((string)($cfg['accessKey'] ?? ''));
        $secretKey = trim((string)($cfg['secretKey'] ?? ''));
        $redirectUrl = trim((string)($cfg['redirectUrl'] ?? ''));
        $ipnUrl = trim((string)($cfg['ipnUrl'] ?? ''));
        $createUrl = trim((string)($cfg['createUrl'] ?? ''));
        if ($createUrl === '') {
            $createUrl = 'https://test-payment.momo.vn/v2/gateway/api/create';
        }
        if ($partnerCode === '' || $accessKey === '' || $secretKey === '' || $redirectUrl === '' || $ipnUrl === '') {
            return [];
        }

        $orderId = trim((string)($orderRow['order_id'] ?? ''));
        $amount = (int)round((float)($orderRow['total_amount'] ?? 0));
        if ($orderId === '' || $amount <= 0) return [];

        $requestId = $orderId . '_' . time();
        $payload = [
            'partnerCode' => $partnerCode,
            'partnerName' => 'PaintMore',
            'storeId' => 'PaintMoreStore',
            'requestId' => $requestId,
            'amount' => (string)$amount,
            'orderId' => $orderId,
            'orderInfo' => 'THANH_TOAN_DON_HANG_' . $orderId,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => '',
            'requestType' => 'captureWallet',
            'autoCapture' => true,
        ];
        $payload['signature'] = momo_create_signature_for_confirm([
            'accessKey' => $accessKey,
            'amount' => $payload['amount'],
            'extraData' => $payload['extraData'],
            'ipnUrl' => $payload['ipnUrl'],
            'orderId' => $payload['orderId'],
            'orderInfo' => $payload['orderInfo'],
            'partnerCode' => $payload['partnerCode'],
            'redirectUrl' => $payload['redirectUrl'],
            'requestId' => $payload['requestId'],
            'requestType' => $payload['requestType'],
        ], $secretKey);

        $res = momo_post_json_for_confirm($createUrl, $payload);
        if (!($res['ok'] ?? false)) return [];
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        if ((int)($data['resultCode'] ?? -1) !== 0) return [];

        $meta = [
            'gateway' => 'momo',
            'requestId' => (string)($payload['requestId'] ?? ''),
            'qrCodeUrl' => (string)($data['qrCodeUrl'] ?? ''),
            'payUrl' => (string)($data['payUrl'] ?? ''),
            'deeplink' => (string)($data['deeplink'] ?? ''),
            'raw' => $data,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $expireTs = orderConfirmExtractMomoExpireTs($meta);
        $expireAt = $expireTs > 0 ? date('Y-m-d H:i:s', $expireTs) : date('Y-m-d H:i:s', time() + 900);
        $cols = listColumns($ithanhloc, 'ecommerce_order');
        $set = [];
        $vals = [];
        $types = '';
        if (hasCol($cols, 'payment_meta_json')) {
            $set[] = 'payment_meta_json=?';
            $vals[] = $metaJson;
            $types .= 's';
        }
        if (hasCol($cols, 'payment_expires_at')) {
            $set[] = 'payment_expires_at=?';
            $vals[] = $expireAt;
            $types .= 's';
        }
        if (hasCol($cols, 'updated_at')) {
            $set[] = 'updated_at=NOW()';
        }
        if ($set) {
            $sql = 'UPDATE ecommerce_order SET ' . implode(', ', $set) . ' WHERE order_id=? LIMIT 1';
            $stmt = $ithanhloc->prepare($sql);
            if ($stmt) {
                $vals[] = $orderId;
                $types .= 's';
                bindParamsDynamic($stmt, $types, $vals);
                $stmt->execute();
                $stmt->close();
            }
        }

        return [
            'qr_url' => (string)($meta['qrCodeUrl'] ?? ''),
            'pay_url' => (string)($meta['payUrl'] ?? ''),
            'deeplink' => (string)($meta['deeplink'] ?? ''),
        ];
    }
}
// Nếu orderId không rỗng thì lấy thông tin đơn hàng
if ($orderId !== '') {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $orderStmt = $ithanhloc->prepare('SELECT order_id, user_id, created_at, status, payment_method, payment_gateway, payment_status, payment_expires_at, total_amount, payment_meta_json, products_json, shipping_fee, discount_amount, user_name, phone, email, address, shipping_snapshot_json FROM ecommerce_order WHERE order_id=? LIMIT 1');
    if ($orderStmt) {
        $orderStmt->bind_param('s', $orderId);
        $orderStmt->execute();
        $orderRow = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        if ($orderRow && ($userId <= 0 || (int)($orderRow['user_id'] ?? 0) === $userId)) {
            // Helper to parse items for bootstrap
            $parseItems = function ($row) {
                $items = [];
                if (!empty($row['products_json'])) {
                    $decoded = json_decode($row['products_json'], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $it) {
                            $items[] = [
                                'id' => $it['id'] ?? $it['product_id'] ?? null,
                                'name' => $it['name'] ?? $it['product_name'] ?? 'Sản phẩm',
                                'qty' => $it['qty'] ?? $it['quantity'] ?? 1,
                                'price' => (float)($it['price'] ?? 0),
                                'line_total' => (float)($it['line_total'] ?? 0),
                                'variant' => $it['variant'] ?? '',
                                'thumb' => $it['thumb'] ?? '',
                                'is_gift' => !empty($it['is_gift']) ? 1 : 0
                            ];
                        }
                    }
                }
                return $items;
            };

            $paymentMetaRaw = trim((string)($orderRow['payment_meta_json'] ?? ''));
            $paymentMeta = $paymentMetaRaw !== '' ? json_decode($paymentMetaRaw, true) : [];
            if (!is_array($paymentMeta)) $paymentMeta = [];
            $rawMeta = is_array($paymentMeta['raw'] ?? null) ? $paymentMeta['raw'] : [];
            $pick = static function (array $vals): string {
                foreach ($vals as $v) {
                    $t = trim((string)$v);
                    if ($t !== '') return $t;
                }
                return '';
            };
            $qrUrl = $pick([
                $paymentMeta['qr_url'] ?? '',
                $paymentMeta['qrCodeUrl'] ?? '',
                $rawMeta['qr_url'] ?? '',
                $rawMeta['qrCodeUrl'] ?? '',
            ]);
            $payUrl = $pick([
                $paymentMeta['pay_url'] ?? '',
                $paymentMeta['payUrl'] ?? '',
                $rawMeta['pay_url'] ?? '',
                $rawMeta['payUrl'] ?? '',
                $rawMeta['shortLink'] ?? '',
            ]);
            $deeplink = $pick([
                $paymentMeta['deeplink'] ?? '',
                $rawMeta['deeplink'] ?? '',
                $rawMeta['deeplinkMiniApp'] ?? '',
            ]);

            $statusKey = strtolower(trim((string)($orderRow['status'] ?? '')));
            $payMethodKey = strtolower(trim((string)($orderRow['payment_method'] ?? '')));
            if ($payMethodKey === 'momo' && $statusKey === 'pending' && $qrUrl === '' && $payUrl === '') {
                $regen = momo_regenerate_qr_for_confirm($ithanhloc, $orderRow);
                if (!empty($regen['qr_url']) || !empty($regen['pay_url'])) {
                    $qrUrl = trim((string)($regen['qr_url'] ?? ''));
                    $payUrl = trim((string)($regen['pay_url'] ?? ''));
                    $deeplink = trim((string)($regen['deeplink'] ?? ''));
                }
            }

            $expRaw = trim((string)($orderRow['payment_expires_at'] ?? ''));
            $expTs = 0;
            if ($expRaw !== '') {
                try {
                    $expDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expRaw, new DateTimeZone('Asia/Ho_Chi_Minh'));
                    if ($expDt instanceof DateTimeImmutable) {
                        $expTs = (int)$expDt->getTimestamp();
                    }
                } catch (Throwable $e) {
                    $expTs = 0;
                }
            }
            if ($payMethodKey === 'momo') {
                $metaExpireTs = orderConfirmExtractMomoExpireTs(array_merge($paymentMeta, ['raw' => $rawMeta]));
                if ($metaExpireTs > 0) {
                    $expTs = $metaExpireTs;
                }
            }

            $shipSnap = !empty($orderRow['shipping_snapshot_json']) ? json_decode($orderRow['shipping_snapshot_json'], true) : [];
            $dest = (string)($shipSnap['destination'] ?? $orderRow['address'] ?? '');

            $bootstrapOrder = [
                'order_id' => (string)($orderRow['order_id'] ?? $orderId),
                'created_at' => (string)($orderRow['created_at'] ?? ''),
                'status' => (string)($orderRow['status'] ?? ''),
                'status_label' => (string)(ecommerce_order_status_info($orderRow['status'] ?? '')['label'] ?? $orderRow['status'] ?? ''),
                'payment_method' => (string)($orderRow['payment_method'] ?? ''),
                'payment_gateway' => (string)($orderRow['payment_gateway'] ?? ''),
                'payment_status' => (string)($orderRow['payment_status'] ?? ''),
                'payment_expires_at' => $expRaw,
                'payment_expires_ts' => $expTs,
                'payment_expires_human' => $expTs > 0 ? date('H:i:s d/m/Y', $expTs) : '',
                'total_amount' => (float)($orderRow['total_amount'] ?? 0),
                'items' => $parseItems($orderRow),
                'customer' => [
                    'name' => (string)($orderRow['user_name'] ?? ''),
                    'phone' => (string)($orderRow['phone'] ?? ''),
                    'email' => (string)($orderRow['email'] ?? ''),
                    'address' => (string)($orderRow['address'] ?? ''),
                ],
                'shipping' => [
                    'destination' => $dest,
                ],
                'payment_meta' => [
                    'qr_url' => $qrUrl,
                    'pay_url' => $payUrl,
                    'deeplink' => $deeplink,
                    'created_at' => (string)($paymentMeta['created_at'] ?? ($rawMeta['created_at'] ?? '')),
                    'expire_ts' => ($payMethodKey === 'momo') ? orderConfirmExtractMomoExpireTs(array_merge($paymentMeta, ['raw' => $rawMeta])) : 0,
                    'last_message' => ($qrUrl === '' && $payUrl === '') ? 'MoMo chÆ°a tráº£ QR cho Ä‘Æ¡n nÃ y. Vui lÃ²ng kiá»ƒm tra cáº¥u hÃ¬nh tÃ i khoáº£n MoMo.' : '',
                ],
            ];
        } else {
            // Không tìm thấy đơn hàng (hoặc không thuộc về user hiện tại)
            $orderNotFound = true;
            $logMsg = "ORDER_NOT_FOUND: order_id=$orderId, session_user_id=$userId, order_user_id=" . ($orderRow['user_id'] ?? 'N/A') . "\n";
            $scratchDir = __DIR__ . '/scratch';
            if (!is_dir($scratchDir)) {
                @mkdir($scratchDir, 0777, true);
            }
            @file_put_contents($scratchDir . '/order_debug.log', $logMsg, FILE_APPEND);
        }
    }
}

// Thông tin chuyển khoản từ cấu hình COMPANY_INFO
$COMPANY_BANK_NAME = app_get_config_value_by_path('COMPANY_INFO.bank_name');
$COMPANY_BANK_ACCOUNT = app_get_config_value_by_path('COMPANY_INFO.bank_account');
$COMPANY_BANK_BRANCH = app_get_config_value_by_path('COMPANY_INFO.bank_branch');
$COMPANY_NAME = app_get_config_value_by_path('COMPANY_INFO.name');
$COMPANY_BANK_QR = app_get_config_value_by_path('COMPANY_INFO.bank_qr_image');
$COMPANY_BANK_QR_URL = $COMPANY_BANK_QR ? rtrim((string)($baseUrl ?? ''), '/') . '/' . ltrim((string)$COMPANY_BANK_QR, '/') : '';
?>
<style>
    .order-thumb-wrap {
        position: relative;
        flex-shrink: 0;
        width: 64px;
        height: 64px;
    }

    .order-thumb {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 10px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .order-thumb:hover {
        transform: scale(1.06);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    }

    .order-qty-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        background: var(--theme-primary, #3b82f6);
        color: #fff;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        border: 2px solid #fff;
        box-shadow: 0 4px 10px rgba(var(--theme-primary-rgb, 59, 130, 246), 0.3);
        z-index: 5;
    }

    /* Align list-group-item for clean borders */
    .confirm-item-row {
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .confirm-item-row:last-child {
        border-bottom: 0 !important;
    }
</style>
<div class="confirm-shell">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
        <div class="d-flex gap-2">
            <?php if (isset($userId) && $userId > 0): ?>
                <a class="btn btn-sm bg-white border text-decoration-none fw-medium text-dark px-3 rounded-pill" href="<?= h($baseUrl) ?>/order">
                    <i class="bi bi-arrow-left me-1"></i>Đơn mua
                </a>
            <?php endif; ?>
            <a class="btn btn-sm bg-white border text-decoration-none fw-medium text-dark px-3 rounded-pill" href="<?= h($baseUrl) ?>">
                <i class="bi bi-house me-1"></i>Trang chủ
            </a>
        </div>
        <div>
            <a class="btn btn-sm bg-white border text-decoration-none fw-medium shadow-sm px-3 rounded-pill" href="<?= h($baseUrl) ?>/shopping">
                <i class="bi bi-cart me-1"></i>Mua tiếp
            </a>
        </div>
    </div>
    <div class="confirm-card p-4 border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="text-center mb-4">
            <?php if ($orderNotFound): ?>
                <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                </div>
            <?php else: ?>
                <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-check-lg fs-1"></i>
                </div>
            <?php endif; ?>
            <h3 class="fw-bold mb-2"><?php echo $orderNotFound ? 'Đơn hàng không tồn tại' : 'Đặt hàng thành công'; ?></h3>
            <p class="text-muted mb-0"><?php echo $orderNotFound ? 'Vui lòng kiểm tra lại mã đơn hàng hoặc liên hệ hỗ trợ.' : 'Đơn hàng của bạn đã được ghi nhận vào hệ thống.'; ?></p>
        </div>

        <?php if ($orderNotFound): ?>
            <div class="alert alert-danger text-center border-0 shadow-sm rounded-3 mb-0">
                Không tìm thấy thông tin đơn hàng với mã <strong>"<?php echo h($orderId ?: 'không xác định'); ?>"</strong>.<br>
                Đơn hàng có thể đã bị huỷ hoặc đã được xoá khỏi hệ thống.
            </div>
        <?php else: ?>
            <div class="row g-3 mb-4 text-center border rounded-3 p-3 bg-light">
                <div class="col-6 col-md-3 border-end border-md-end-0 border-md-bottom-0">
                    <div class="text-muted small mb-1">Mã đơn hàng</div>
                    <div class="fw-bold d-flex align-items-center justify-content-center gap-2">
                        <span id="orderIdText" class="text-primary">#<?= h($orderId ?: '---') ?></span>
                        <?php if ($orderId): ?>
                            <button type="button" class="btn btn-link p-0 text-secondary" id="copyOrderIdBtn" title="Sao chép">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6 col-md-3 border-end">
                    <div class="text-muted small mb-1">Tổng tiền</div>
                    <div class="fw-bold text-danger fs-6" id="confirmOrderAmount">--</div>
                </div>
                <div class="col-6 col-md-3 border-end">
                    <div class="text-muted small mb-1">Thanh toán</div>
                    <div class="fw-bold fs-6" id="confirmPaymentStatus">--</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small mb-1">Trạng thái</div>
                    <div class="fw-bold text-warning fs-6" id="confirmOrderStatus">Đang xử lý</div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-none bg-light rounded-3">
                        <div class="card-body">
                            <h6 class="card-title fw-bold border-bottom pb-2 mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Thông tin người nhận</h6>
                            <div id="confirmCustomerInfo" class="small">Đang tải...</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-none bg-light rounded-3">
                        <div class="card-body">
                            <h6 class="card-title fw-bold border-bottom pb-2 mb-3"><i class="bi bi-credit-card me-2 text-primary"></i>Chi tiết thanh toán</h6>
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted">Phương thức:</span>
                                <span class="fw-medium text-end" id="confirmPaymentMethod">--</span>
                            </div>
                            <?php if ($vnpStatus): ?>
                                <!-- Hiển thị kết quả thanh toán VNPAY nếu có -->
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span class="text-muted">Vnpay:</span>
                                    <span class="fw-medium text-end <?= h($vnpStatus) ?>"><?= h($vnpMessage) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($changePaymentMethods): ?>
                                <!-- Nút tiếp tục thanh toán hoặc đổi phương thức nếu đơn hàng chưa thanh toán -->
                                <div id="confirmPayNowBox" style="display:none;" class="mt-3 text-center border-top pt-3">
                                    <button class="btn btn-sm btn-primary w-100 mb-1" type="button" id="confirmPayNowBtn"><i class="bi bi-credit-card"></i> Tiếp tục thanh toán</button>
                                    <small class="text-muted d-block" id="confirmPayNowNote">Cổng thanh toán vẫn còn hiệu lực.</small>
                                </div>
                                <!-- Nút đổi phương thức thanh toán nếu có thể -->
                                <div id="changePaymentRow" style="display:none;" class="mt-3 text-center border-top pt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-1" id="openChangePaymentModal"><i class="bi bi-arrow-repeat"></i> Đổi thanh toán</button>
                                    <small class="text-muted d-block" id="changePaymentNote">Chọn phương thức mới.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center text-danger" id="confirmNoteText">
                Vui lòng theo dõi cập nhật trạng thái đơn hàng tại mục đơn hàng của bạn.
            </div>
            <div class="card border border-light rounded-3 shadow-none mb-3">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Sản phẩm đã đặt</h6>
                </div>
                <div class="card-body p-3">
                    <div id="confirmOrderItems">Đang tải danh sách sản phẩm...</div>
                </div>
            </div>

            <?php if (!$orderNotFound && ($COMPANY_BANK_NAME || $COMPANY_BANK_ACCOUNT || $COMPANY_BANK_QR_URL)): ?>
                <div class="card border border-light rounded-3 shadow-none mb-3 confirm-bank-card d-none">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="fw-bold mb-0"><i class="bi bi-bank me-2 text-primary"></i>Thông tin chuyển khoản</h6>
                    </div>
                    <div class="card-body p-3 confirm-body">
                        <div class="confirm-row confirm-bank-box mb-0 border-0 p-0">
                            <div class="confirm-value w-100">
                                <div class="row align-items-center g-4">
                                    <?php if ($COMPANY_BANK_NAME || $COMPANY_BANK_ACCOUNT): ?>
                                        <div class="<?= $COMPANY_BANK_QR_URL ? 'col-md-7 border-md-end' : 'col-12' ?>">
                                            <ul class="list-group list-group-flush bg-transparent">
                                                <?php if ($COMPANY_BANK_NAME): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                                        <span class="text-muted small">Ngân hàng</span>
                                                        <span class="fw-medium text-end"><?= h($COMPANY_BANK_NAME) ?></span>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($COMPANY_BANK_ACCOUNT): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                                        <span class="text-muted small">Số tài khoản</span>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="fw-bold text-primary fs-6" id="bankAccountText"><?= h($COMPANY_BANK_ACCOUNT) ?></span>
                                                            <button type="button" class="btn btn-sm btn-light border py-0 px-2 copy-generic-btn" data-target="bankAccountText" title="Sao chép">
                                                                <i class="bi bi-clipboard"></i>
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                                    <span class="text-muted small">Chủ tài khoản</span>
                                                    <span class="fw-medium text-end"><?= h($COMPANY_NAME) ?></span>
                                                </li>
                                                <?php if ($COMPANY_BANK_BRANCH): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                                        <span class="text-muted small">Chi nhánh</span>
                                                        <span class="fw-medium text-end"><?= h($COMPANY_BANK_BRANCH) ?></span>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (!empty($orderId)): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent border-bottom-0">
                                                        <span class="text-muted small">Nội dung (Bắt buộc)</span>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="fw-bold text-danger" id="bankContentText"><?= h($company_bank_content_text . ' ' . $orderId) ?></span>
                                                            <button type="button" class="btn btn-sm btn-light border py-0 px-2 copy-generic-btn" data-target="bankContentText" title="Sao chép">
                                                                <i class="bi bi-clipboard"></i>
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($COMPANY_BANK_QR_URL): ?>
                                        <div class="<?= ($COMPANY_BANK_NAME || $COMPANY_BANK_ACCOUNT) ? 'col-md-5' : 'col-12' ?> text-center">
                                            <div class="p-3 bg-white rounded-3 shadow-sm d-inline-block border">
                                                <div class="small text-primary fw-bold mb-2">Quét mã QR để thanh toán</div>
                                                <img src="<?= h($COMPANY_BANK_QR_URL) ?>" alt="QR chuyển khoản" class="img-fluid rounded" loading="lazy" decoding="async" style="max-width: 180px;">
                                                <div class="small text-muted mt-2">Sử dụng App Ngân hàng hoặc MoMo/ZaloPay</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!$orderNotFound): ?>
        <!-- Card riêng cho thông tin hoá đơn -->
        <div class="card mt-3 invoice-toggle-card" style="border:1px solid #eee; border-radius:8px;">
            <div class="card-header d-flex align-items-center justify-content-between invoice-toggle-header" style="cursor:pointer;user-select:none;padding:0.5rem 1rem;">
                <span><i class="bi bi-receipt"></i> Thông tin xuất hoá đơn</span>
                <button type="button" class="btn btn-sm btn-link invoice-toggle-btn" style="text-decoration:none;">
                    <span class="show-label">Hiện</span><span class="hide-label d-none">Ẩn</span> <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="card-body invoice-toggle-body d-none" id="confirmInvoiceInfo" style="padding:1rem;">
                Không yêu cầu xuất hoá đơn.
            </div>
        </div>
    <?php endif; ?>


</div>

<?php if ($changePaymentMethods && !$orderNotFound): ?>
    <!-- Popup chọn phương thức thanh toán -->
    <div class="modal fade" id="changePaymentModal" tabindex="-1" aria-labelledby="changePaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePaymentModalLabel">Chọn phương thức thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Vui lòng chọn phương thức thanh toán mới cho đơn hàng này.</p>
                    <div class="d-grid gap-2" id="changePaymentPanel">
                        <?php foreach ($changePaymentMethods as $m): ?>
                            <button class="btn btn-sm btn-outline-primary text-center" type="button" data-method="<?= h($m['key']) ?>">
                                <?= h($m['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="small text-muted mt-3" id="changePaymentModalNote">Chọn phương thức để tạo lại phiên thanh toán.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>



<div class="confirm-support text-center mt-3 mb-3">
    <div class="mb-2 text-muted">Cần hỗ trợ thêm về đơn hàng?</div>
    <a href="tel:<?= h($company_hotline) ?>" class="btn btn-outline-secondary me-2">LIÊN HỆ HỖ TRỢ</a>
    <a href="<?= h($social_facebook) ?>" target="_blank" rel="noopener" class="btn btn-primary">LIÊN HỆ FACEBOOK</a>
</div>
</div>

<script>
    // Copy order id to clipboard
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('copyOrderIdBtn');
        var txt = document.getElementById('orderIdText');
        if (btn && txt) {
            btn.addEventListener('click', function() {
                var val = txt.textContent || txt.innerText || '';
                if (!val) return;
                navigator.clipboard.writeText(val).then(function() {
                    btn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';
                    setTimeout(function() {
                        btn.innerHTML = '<i class="bi bi-clipboard"></i>';
                    }, 1200);
                });
            });
        }
    });
    // Generic copy buttons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.copy-generic-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = btn.getAttribute('data-target');
                var txt = document.getElementById(targetId);
                if (txt) {
                    var val = txt.textContent || txt.innerText || '';
                    if (!val) return;
                    navigator.clipboard.writeText(val).then(function() {
                        var originalHTML = btn.innerHTML;
                        btn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';
                        setTimeout(function() {
                            btn.innerHTML = originalHTML;
                        }, 1200);
                    });
                }
            });
        });
    });
    // --- Invoice toggle popup ---
    (function() {
        const orderId = <?= json_encode($orderId, JSON_UNESCAPED_UNICODE) ?>;
        const orderNotFound = <?= $orderNotFound ? 'true' : 'false' ?>;
        if (!orderId || orderNotFound) return;
        const bootstrapOrder = <?= json_encode($bootstrapOrder, JSON_UNESCAPED_UNICODE) ?>;
        const allowChangePayment = <?= $allowChangePayment ? 'true' : 'false' ?>;

        const apiUrl = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/order.php';
        const $status = document.getElementById('confirmOrderStatus');
        const $items = document.getElementById('confirmOrderItems');
        const $amount = document.getElementById('confirmOrderAmount');
        const $payStatus = document.getElementById('confirmPaymentStatus');
        const $payMethod = document.getElementById('confirmPaymentMethod');
        const $changeRow = document.getElementById('changePaymentRow');
        const $changeNote = document.getElementById('changePaymentNote') || document.getElementById('changePaymentModalNote');
        const $changeModal = document.getElementById('changePaymentModal');
        const $openChangeModalBtn = document.getElementById('openChangePaymentModal');
        const $payNowBox = document.getElementById('confirmPayNowBox');
        const $payNowBtn = document.getElementById('confirmPayNowBtn');
        const $payNowNote = document.getElementById('confirmPayNowNote');

        const $headerTitle = document.querySelector('.confirm-title');
        const $headerSub = document.querySelector('.confirm-sub');

        let changingPayment = false;
        let lastPaymentStatus = 'pending';

        function esc(v) {
            return String(v == null ? '' : v);
        }

        function escapeHtml(str) {
            if (str == null) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function slugify(text) {
            if (!text) return '';
            return text.toString().toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[đĐ]/g, 'd')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
        }

        function buildProductDetailUrl(item) {
            const base = '<?= h($baseUrl) ?>';
            const id = Number(item.product_id || item.pid || 0);
            if (id <= 0) return '#';
            let slug = String(item.slug || item.product_slug || '').trim();
            if (!slug) {
                slug = slugify(item.name || item.product_name || 'product');
            }
            return base + '/product/' + encodeURIComponent(slug) + '-' + id;
        }

        // Ưu tiên media domain (toMediaUrl từ head.php); fallback ghép baseUrl.
        function toAbs(url) {
            const raw = String(url || '').trim();
            if (!raw) return '';
            if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(raw);
            if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
            const base = '<?= h(rtrim((string)$baseUrl, '/')) ?>';
            if (!base) return raw;
            return base + (raw.startsWith('/') ? raw : '/' + raw);
        }

        function buildItemThumb(item) {
            const src = item && (item.variant_image_url || item.variant_thumb || item.image || item.thumbnail || item.img || item.thumb || '');
            if (!src) return `<div class="order-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-box"></i></div>`;
            const safeSrc = escapeHtml(toAbs(src));
            return `<img src="${safeSrc}" class="order-thumb" alt="thumb" onerror="this.style.display='none'">`;
        }

        function fmtMoney(value) {
            const n = Number(value || 0);
            if (!Number.isFinite(n) || n <= 0) return 'Miễn phí';
            return new Intl.NumberFormat('vi-VN').format(n) + ' đ';
        }

        function renderOrderItems(order) {
            if (!$items) return;
            const list = Array.isArray(order && order.items) ? order.items : [];
            if (!list.length) {
                $items.innerHTML = '<div class="text-muted text-center py-3">Chưa có sản phẩm.</div>';
                return;
            }
            $items.innerHTML = '<div class="list-group list-group-flush">' + list.map(item => {
                const name = escapeHtml(item && item.name ? item.name : 'Sản phẩm');
                const qty = Number(item && item.qty ? item.qty : 1);
                const variantText = item && item.variant ? ('Phân loại: ' + item.variant) : 'Phân loại: Mặc định';
                const isGift = item && (Number(item.is_gift) === 1 || item.is_gift === true || String(item.is_gift) === '1');

                const price = item && item.line_total_fmt ? escapeHtml(item.line_total_fmt) : fmtMoney(item && item.line_total);
                const badgeHtml = isGift ? '<span class="badge bg-primary rounded-pill fw-normal ms-2 mb-1" style="font-size: 0.65rem; vertical-align: middle;"><i class="bi bi-gift me-1"></i>Quà tặng</span>' : '';

                return `
                <div class="list-group-item border-0 border-bottom py-3 px-0 confirm-item-row bg-transparent">
                    <div class="d-flex align-items-center gap-3 w-100">
                        <div class="order-thumb-wrap">
                            <a href="${buildProductDetailUrl(item)}">${buildItemThumb(item)}</a>
                            <span class="order-qty-badge">${qty}</span>
                        </div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <a href="${buildProductDetailUrl(item)}" class="text-decoration-none text-dark fw-bold d-block text-truncate mb-1" style="font-size: 0.95rem;" title="${name}">${name}${badgeHtml}</a>
                            <div class="text-muted small text-truncate">${escapeHtml(variantText)}</div>
                        </div>
                        <div class="text-end flex-shrink-0" style="min-width:90px;">
                            <div class="fw-bold ${isGift ? 'text-success' : 'text-danger'}" style="font-size: 0.95rem;white-space:nowrap;">${isGift ? 'Quà tặng' : price}</div>
                        </div>
                    </div>
                </div>
            `;
            }).join('') + '</div>';
        }

        function applyOrderData(order) {
            if (!order) return;
            if ($status) $status.textContent = esc(order.status_label || 'Đang xử lý');
            renderOrderItems(order);

            // Thông tin người nhận
            if (order.customer && $status) {
                const name = esc(order.customer.name || '');
                const phone = esc(order.customer.phone || '');
                const address = esc((order.shipping && order.shipping.destination) || order.shipping_address || order.customer.address || '');
                const $cust = document.getElementById('confirmCustomerInfo');
                if ($cust) {
                    const parts = [];
                    if (name) parts.push('<strong>' + name + '</strong>');
                    if (phone) parts.push('<span style="margin-left:6px;">(' + phone + ')</span>');
                    if (address) parts.push('<div class="small text-muted" style="margin-top:2px;">' + address + '</div>');
                    $cust.innerHTML = parts.length ? parts.join(' ') : 'Chưa có thông tin người nhận.';
                }
            }

            // Thông tin hoá đơn (render vào popup toggle)
            const $inv = document.getElementById('confirmInvoiceInfo');
            if ($inv) {
                const inv = order.invoice || {};
                const has = inv.has_invoice === true || !!(inv.buyer_name || inv.company_name || inv.tax_code || inv.address || inv.email);
                if (!has) {
                    $inv.innerHTML = 'Không yêu cầu xuất hoá đơn.';
                } else {
                    const lines = [];
                    const typeKey = String(inv.invoice_type || '').toLowerCase();
                    const typeLabel = typeKey === 'company' ? 'Công ty' : 'Cá nhân';
                    lines.push('<div class="mb-1"><strong>Loại hoá đơn:</strong> ' + esc(typeLabel) + '</div>');
                    if (inv.buyer_name) {
                        lines.push('<div class="mb-1"><strong>Người mua:</strong> ' + esc(inv.buyer_name) + '</div>');
                    }
                    if (inv.company_name) {
                        lines.push('<div class="mb-1"><strong>Tên công ty:</strong> ' + esc(inv.company_name) + '</div>');
                    }
                    if (inv.tax_code) {
                        lines.push('<div class="mb-1"><strong>Mã số thuế:</strong> ' + esc(inv.tax_code) + '</div>');
                    }
                    if (inv.address) {
                        lines.push('<div class="mb-1"><strong>Địa chỉ xuất hoá đơn:</strong> ' + esc(inv.address) + '</div>');
                    }
                    if (inv.email) {
                        lines.push('<div class="small text-muted"><strong>Email hoá đơn:</strong> ' + esc(inv.email) + '</div>');
                    }
                    $inv.innerHTML = lines.join('');
                }
            }

            if ($amount) {
                const total = (order && order.totals && order.totals.grand_total) ?
                    String(order.totals.grand_total) :
                    fmtMoney(order && order.total_amount ? order.total_amount : 0);
                $amount.textContent = total;
            }

            if ($payStatus) {
                const label = order && order.payment_status_label ?
                    String(order.payment_status_label) :
                    'Chưa cập nhật';
                $payStatus.textContent = label;
            }

            if ($payMethod) {
                const key = String(order.payment_gateway || order.payment_method || '').toLowerCase();
                const map = {
                    cod: 'Tiền mặt (COD)',
                    momo: 'Ví MoMo',
                    vnpay: 'Ví VN PAY',
                    zalopay: 'Ví ZaloPay',
                };
                const label = String(order.payment_method_label || order.payment_gateway_label || order.payment_label || '').trim();
                $payMethod.textContent = label || map[key] || (key ? key.toUpperCase() : 'Chưa cập nhật');
            }
            const payStatusKeyRaw = String(order.payment_status || '').toLowerCase();
            const payStatusKey = payStatusKeyRaw !== '' ? payStatusKeyRaw : 'pending';
            const payStatusKeyNorm = (payStatusKey === 'unpaid') ? 'pending' : payStatusKey;
            const statusKey = String(order.status || '').toLowerCase();
            const methodKey = String(order.payment_gateway || order.payment_method || '').toLowerCase();
            const meta = order && order.payment_meta ? order.payment_meta : {};
            const directUrl = String(
                meta.pay_url || meta.payUrl || meta.order_url || meta.orderUrl || meta.qr_url || meta.qrCodeUrl || meta.deeplink || ''
            ).trim();
            const $note = document.getElementById('confirmNoteText');

            // Cập nhật tiêu đề trạng thái tổng quát theo trạng thái đơn hàng
            if ($headerTitle && $headerSub) {
                let titleText = 'Đặt hàng thành công';
                let subText = 'Đơn hàng đã được ghi nhận trong hệ thống.';

                switch (statusKey) {
                    case 'pending':
                        titleText = 'Đơn hàng đang chờ xử lý';
                        subText = 'Đơn đã ghi nhận, đang chờ cửa hàng xác nhận hoặc thanh toán.';
                        break;
                    case 'processing':
                    case 'confirmed':
                        titleText = 'Đơn hàng đang được xử lý';
                        subText = 'Cửa hàng đang chuẩn bị và xử lý đơn hàng của bạn.';
                        break;
                    case 'shipping':
                    case 'shipped':
                        titleText = 'Đơn hàng đang giao';
                        subText = 'Đơn hàng đang được vận chuyển tới bạn.';
                        break;
                    case 'completed':
                    case 'done':
                    case 'delivered':
                        titleText = 'Đơn hàng đã hoàn tất';
                        subText = 'Cảm ơn bạn đã mua sắm tại cửa hàng.';
                        break;
                    case 'canceled':
                    case 'cancelled':
                        titleText = 'Đơn hàng đã bị huỷ';
                        subText = 'Nếu đây là nhầm lẫn, vui lòng liên hệ bộ phận hỗ trợ.';
                        break;
                    default:
                        break;
                }

                $headerTitle.textContent = titleText;
                $headerSub.textContent = subText;
            }

            lastPaymentStatus = payStatusKeyNorm;

            // Rule: payment_status=expired => đóng toàn bộ thanh toán & không cho đổi
            const isExpired = (payStatusKeyNorm === 'expired');
            const isPaid = (payStatusKeyNorm === 'paid');
            const isPendingOrder = (statusKey === 'pending');
            const isCodMethod = (methodKey === 'cod');

            // Online retry: đơn pending + payment_status pending/failed
            const isRetryablePayment = (!isExpired && !isPaid && isPendingOrder && (payStatusKeyNorm === 'pending' || payStatusKeyNorm === 'failed'));

            // COD pending: cho phép đổi PTTT sang online (dù payment_status có thể là 'cod' / 'unpaid' / ...)
            const canChangePaymentFromCodPending = (!isExpired && !isPaid && isPendingOrder && isCodMethod);

            if ($note) {
                if (isExpired) {
                    $note.textContent = 'Đơn hàng đã hết hạn thanh toán.';
                } else if (canChangePaymentFromCodPending) {
                    $note.textContent = 'Đơn đang chờ xác nhận. Bạn có thể đổi phương thức thanh toán sang online nếu cần.';
                } else if (isRetryablePayment) {
                    $note.textContent = 'Bạn có thể thanh toán lại hoặc đổi phương thức thanh toán. (Lưu ý: đơn sẽ tự huỷ sau 15 phút nếu chưa thanh toán; đơn huỷ quá 30 ngày sẽ tự động xoá.)';
                } else {
                    $note.textContent = 'Vui lòng theo dõi cập nhật trạng thái đơn hàng tại mục đơn hàng của bạn.';
                }
            }

            // --- Box: Thanh toán lại / tiếp tục thanh toán ---
            if ($payNowBox && $payNowBtn) {
                const isOnlineMethod = (methodKey === 'momo' || methodKey === 'vnpay' || methodKey === 'zalopay');
                if (isRetryablePayment && isOnlineMethod) {
                    // Luôn hiển thị để người dùng reset link thanh toán mới khi cần.
                    $payNowBox.style.display = 'grid';
                    $payNowBtn.dataset.href = directUrl;
                    $payNowBtn.dataset.method = methodKey;
                    if ($payNowNote) {
                        $payNowNote.textContent = payStatusKey === 'failed' ?
                            'Thanh toán bị lỗi. Bạn có thể bấm “Tiếp tục thanh toán” để tạo lại link mới, hoặc đổi phương thức.' :
                            'Đơn đang chờ thanh toán. Bạn có thể tiếp tục thanh toán hoặc đổi phương thức để tạo link mới.';
                    }
                } else {
                    $payNowBox.style.display = 'none';
                    $payNowBtn.dataset.href = '';
                    $payNowBtn.dataset.method = '';
                }
            }

            // --- Hàng + popup: Đổi phương thức thanh toán ---
            const buttons = Array.from(document.querySelectorAll('#changePaymentModal button[data-method]'));
            const availableMethods = buttons
                .map(btn => String(btn.getAttribute('data-method') || '').toLowerCase())
                .filter(Boolean);
            const hasAlternative = availableMethods.some(m => m !== methodKey);

            const canChangePayment = ((isRetryablePayment || canChangePaymentFromCodPending) && hasAlternative);

            if ($changeRow) {
                $changeRow.style.display = canChangePayment ? '' : 'none';
            }
            if (buttons.length) {
                buttons.forEach(btn => {
                    const m = String(btn.getAttribute('data-method') || '').toLowerCase();
                    btn.disabled = !canChangePayment || (m === methodKey);
                    btn.hidden = !canChangePayment || (m === methodKey);
                });
            }
            if ($changeNote) {
                if (canChangePayment) {
                    $changeNote.textContent = 'Chọn phương thức thanh toán mới để tạo lại link thanh toán.';
                } else if (isExpired) {
                    $changeNote.textContent = 'Đơn đã hết hạn thanh toán, không thể đổi phương thức.';
                }
            }
        }

        function fetchDetail() {
            const url = apiUrl + '?action=detail&order_id=' + encodeURIComponent(orderId);
            return fetch(url, {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.ok) throw new Error(res?.msg || 'Không tải được đơn hàng');
                    applyOrderData(res.data || {});
                });
        }

        function syncPayment() {
            const url = apiUrl + '?action=sync_payment&order_id=' + encodeURIComponent(orderId);
            fetch(url, {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.ok) return;
                    applyOrderData(res.data || {});
                })
                .catch(() => {});
        }

        function changePaymentMethod(method, options = {}) {
            if (!method || changingPayment) return;
            changingPayment = true;
            if ($changeNote) $changeNote.textContent = 'Đang xử lý...';
            const body = new URLSearchParams();
            body.set('action', 'change_payment');
            body.set('order_id', orderId);
            body.set('payment_method', method);
            if (options && options.force_new_session) {
                body.set('force_new_session', '1');
            }
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            if (csrfToken) body.set('csrf_token', csrfToken);
            fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: body.toString(),
                })
                .then(r => {
                    // Token CSRF hết hạn/không khớp: tải lại trang để lấy token mới,
                    // tránh để khách hàng thấy thông báo "CSRF error".
                    if (r.status === 403 && !options.csrf_retried) {
                        try {
                            sessionStorage.setItem('pm_retry_change_payment', method);
                        } catch (e) {}
                        window.location.reload();
                        return null;
                    }
                    return r.json();
                })
                .then(res => {
                    if (res === null) return;
                    if (!res || !res.ok) {
                        if (res && res.error_type === 'momo_duplicate') {
                            if (confirm(res.msg || 'Yêu cầu MoMo bị trùng. Bạn có muốn tạo link thanh toán mới không?')) {
                                changingPayment = false;
                                changePaymentMethod(method, {
                                    force_new_session: 1
                                });
                                return;
                            }
                        }
                        // Không hiển thị thông báo kỹ thuật "CSRF error" cho khách hàng.
                        let msg = (res && res.msg) ? res.msg : 'Không đổi được Phương thức Thanh Toán';
                        if (/CSRF/i.test(msg)) {
                            msg = 'Không đổi được phương thức thanh toán, vui lòng tải lại trang và thử lại.';
                        }
                        if ($changeNote) $changeNote.textContent = msg;
                        return;
                    }
                    const methodKey = String(res.payment_method || method).toLowerCase();
                    const directUrl = String((res && res.payment_url) || '').trim();

                    // Quy ước: nếu là COD thì KHÔNG bao giờ chuyển sang trang thanh toán bên thứ 3,
                    // kể cả khi API có trả về payment_url do cấu hình cũ.
                    if (directUrl && methodKey && methodKey !== 'cod') {
                        if ($changeNote && res && res.reused_previous_payment) {
                            $changeNote.textContent = String(res.msg || 'Đã tìm thấy link thanh toán trước đó, đang chuyển hướng...');
                        }
                        window.location.href = directUrl;
                        return;
                    }

                    // Với COD (hoặc khi không có payment_url), chỉ reload lại trang xác nhận đơn
                    window.location.href = '<?= h($baseUrl) ?>/order-confirm?order_id=' + encodeURIComponent(orderId);
                })
                .catch(() => {
                    if ($changeNote) $changeNote.textContent = 'Không thể đổi phương thức thanh toán lúc này.';
                })
                .finally(() => {
                    changingPayment = false;
                });
        }

        if (bootstrapOrder && typeof bootstrapOrder === 'object') {
            applyOrderData(bootstrapOrder);
        }

        if (String(bootstrapOrder && bootstrapOrder.payment_method || '').toLowerCase() === 'momo' &&
            String(bootstrapOrder && bootstrapOrder.payment_status || '').toLowerCase() === 'pending') {
            syncPayment();
        }

        fetchDetail().catch(() => {
            if ($status && (!$status.textContent || $status.textContent.includes('Đang xử lý'))) {
                $status.textContent = 'Không tải được trạng thái đơn';
            }
        });

        if ($openChangeModalBtn && $changeModal && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            const modalInstance = new window.bootstrap.Modal($changeModal);
            $openChangeModalBtn.addEventListener('click', () => {
                if ($changeNote) {
                    $changeNote.textContent = 'Chọn phương thức thanh toán mới.';
                }
                modalInstance.show();
            });
        }

        // Sau khi tải lại trang do token CSRF hết hạn: thử đổi PT thanh toán lại đúng 1 lần.
        try {
            const pendingMethod = sessionStorage.getItem('pm_retry_change_payment');
            if (pendingMethod) {
                sessionStorage.removeItem('pm_retry_change_payment');
                changePaymentMethod(pendingMethod, { csrf_retried: true });
            }
        } catch (e) {}

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('#changePaymentModal button[data-method]');
            if (!btn) return;
            const method = btn.getAttribute('data-method') || '';
            if (!method) return;
            changePaymentMethod(method);
            if ($changeModal && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const inst = window.bootstrap.Modal.getInstance($changeModal);
                if (inst) inst.hide();
            }
        });

        if ($payNowBtn) {
            $payNowBtn.addEventListener('click', () => {
                const href = String($payNowBtn.dataset.href || '').trim();
                // Nếu phiên thanh toán trước đó đã bị huỷ hoặc lỗi, buộc phải tạo phiên mới thay vì dùng link cũ
                const isFailed = (lastPaymentStatus === 'failed');

                if (href && !isFailed) {
                    window.location.href = href;
                    return;
                }
                const method = String($payNowBtn.dataset.method || '').toLowerCase();
                if (method && method !== 'cod') {
                    changePaymentMethod(method);
                }
            });
        }
        // Toggle popup for invoice info
        const $invoiceToggleHeader = document.querySelector('.invoice-toggle-header');
        const $invoiceToggleBtn = document.querySelector('.invoice-toggle-btn');
        const $invoiceToggleBody = document.querySelector('.invoice-toggle-body');
        if ($invoiceToggleHeader && $invoiceToggleBody && $invoiceToggleBtn) {
            function setToggle(open) {
                if (open) {
                    $invoiceToggleBody.classList.remove('d-none');
                    $invoiceToggleBtn.querySelector('.show-label').classList.add('d-none');
                    $invoiceToggleBtn.querySelector('.hide-label').classList.remove('d-none');
                    $invoiceToggleBtn.querySelector('i').classList.remove('bi-chevron-down');
                    $invoiceToggleBtn.querySelector('i').classList.add('bi-chevron-up');
                } else {
                    $invoiceToggleBody.classList.add('d-none');
                    $invoiceToggleBtn.querySelector('.show-label').classList.remove('d-none');
                    $invoiceToggleBtn.querySelector('.hide-label').classList.add('d-none');
                    $invoiceToggleBtn.querySelector('i').classList.add('bi-chevron-down');
                    $invoiceToggleBtn.querySelector('i').classList.remove('bi-chevron-up');
                }
            }
            let open = false;
            $invoiceToggleHeader.addEventListener('click', function(e) {
                open = !open;
                setToggle(open);
            });
            $invoiceToggleBtn.addEventListener('click', function(e) {
                open = !open;
                setToggle(open);
                e.stopPropagation();
            });
            setToggle(false);
        }
        // --- Auto redirect to clean order-confirm?order_id=... after payment return ---
        (function() {
            const params = new URLSearchParams(window.location.search);
            let orderId = params.get('order_id') || params.get('vnp_TxnRef') || params.get('orderId');
            const hasVnpay = Array.from(params.keys()).some(k => k.startsWith('vnp_'));
            const hasMomo = params.has('resultCode');
            const appTransId = params.get('apptransid') || params.get('app_trans_id');
            const hasZalo = !!appTransId;

            // Nếu return từ ZaloPay và chưa có order_id thì tách từ apptransid (vd: 260406_DH-2604064171)
            if (!orderId && appTransId) {
                const parts = String(appTransId).split('_');
                orderId = parts.length >= 2 ? parts[1] : appTransId;
            }
            // Nếu là return từ VNPAY, lưu trạng thái vào localStorage trước khi redirect
            if (orderId && hasVnpay) {
                // Lấy trạng thái VNPAY từ giao diện nếu có
                var vnpStatus = '';
                var vnpMessage = '';
                // Ưu tiên lấy từ PHP render ra nếu có
                try {
                    vnpStatus = <?= json_encode($vnpStatus, JSON_UNESCAPED_UNICODE) ?>;
                    vnpMessage = <?= json_encode($vnpMessage, JSON_UNESCAPED_UNICODE) ?>;
                } catch (e) {}
                // Nếu không có thì lấy từ query
                if (!vnpStatus) vnpStatus = params.get('vnp_ResponseCode') === '00' && params.get('vnp_TransactionStatus') === '00' ? 'success' : 'fail';
                if (!vnpMessage) vnpMessage = vnpStatus === 'success' ? 'Thanh toán VNPAY thành công.' : 'Thanh toán VNPAY chưa thành công.';
                // Lưu vào localStorage
                localStorage.setItem('vnpay_status_' + orderId, JSON.stringify({
                    status: vnpStatus,
                    message: vnpMessage
                }));
            }
            // Redirect nếu có extra params (VNPAY / MoMo / ZaloPay)
            if (orderId && (hasVnpay || hasMomo || hasZalo)) {
                const hasNormalOrderConfirm = params.get('normal') === 'order-confirm';
                const cleanParams = new URLSearchParams();
                if (hasNormalOrderConfirm) {
                    cleanParams.set('normal', 'order-confirm');
                }
                cleanParams.set('order_id', orderId);

                const cleanQuery = cleanParams.toString();
                if (params.toString() !== cleanQuery) {
                    const cleanUrl = `${window.location.origin}${window.location.pathname}?${cleanQuery}`;
                    window.location.replace(cleanUrl);
                }
            }
        })();

        // --- Hiển thị lại trạng thái VNPAY sau khi redirect ---
        (function() {
            const params = new URLSearchParams(window.location.search);
            const orderId = params.get('order_id');
            if (!orderId) return;
            const key = 'vnpay_status_' + orderId;
            const data = localStorage.getItem(key);
            if (data) {
                try {
                    const obj = JSON.parse(data);
                    if (obj && obj.status && obj.message) {
                        // Hiển thị lên giao diện
                        let box = null;
                        document.querySelectorAll('.confirm-row .confirm-label').forEach(el => {
                            if (el.textContent.includes('Thanh toán VNPAY')) {
                                box = el;
                            }
                        });
                        // Nếu đã có box VNPAY thì cập nhật, nếu chưa thì tạo mới
                        var parent = document.querySelector('.confirm-body');
                        if (parent) {
                            var exist = parent.querySelector('.confirm-row-vnpay');
                            if (!exist) {
                                var row = document.createElement('div');
                                row.className = 'confirm-row confirm-row-vnpay';
                                row.innerHTML = '<div class="confirm-label">Thanh toán VNPAY</div>' +
                                    '<div class="confirm-value"><div class="confirm-status ' + obj.status + '">' + obj.message + '</div></div>';
                                parent.insertBefore(row, parent.firstChild.nextSibling); // sau dòng mã đơn hàng
                            } else {
                                exist.querySelector('.confirm-status').className = 'confirm-status ' + obj.status;
                                exist.querySelector('.confirm-status').textContent = obj.message;
                            }
                        }
                    }
                } catch (e) {}
                // Xóa trạng thái sau khi hiển thị
                localStorage.removeItem(key);
            }
        })();
    })();
</script>