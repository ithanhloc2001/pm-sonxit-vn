<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
// Các hàm liên quan đến xử lý thanh toán, tạo URL thanh toán, v.v.
function buildVnpayUrl(array $params, string $hashSecret, string $baseUrl): string {
    ksort($params);
    $hashData = '';
    $query = '';
    $i = 0;
    foreach ($params as $key => $value) {
        $val = (string)$value;
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode($val);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode($val);
            $i = 1;
        }
        $query .= urlencode($key) . '=' . urlencode($val) . '&';
    }
    $secureHash = hash_hmac('sha512', $hashData, $hashSecret);
    return $baseUrl . '?' . $query . 'vnp_SecureHash=' . $secureHash;
}
// Lưu ý: các hàm MoMo phụ thuộc vào cấu hình được lấy từ app_get_momo_config_by_env(), được định nghĩa trong config.php.
function momoConfig(): array {
    $cfg = app_get_momo_config_by_env();
    return [
        'enabled' => !empty($cfg['enabled']),
        'partnerCode' => trim((string)($cfg['partnerCode'] ?? '')),
        'accessKey' => trim((string)($cfg['accessKey'] ?? '')),
        'secretKey' => trim((string)($cfg['secretKey'] ?? '')),
        'redirectUrl' => trim((string)($cfg['redirectUrl'] ?? '')),
        'ipnUrl' => trim((string)($cfg['ipnUrl'] ?? '')),
        'createUrl' => trim((string)($cfg['createUrl'] ?? '')),
        'requestType' => 'captureWallet',
    ];
}
// Tạo chữ ký cho payload MoMo
function momoSignCreate(array $payload, string $secretKey): string {
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
// Gửi yêu cầu tạo đơn thanh toán MoMo và nhận về kết quả
function momoHttpPostJson(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'msg' => $err !== '' ? $err : 'Không gọi được MoMo API'];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => 'MoMo API trả về dữ liệu không hợp lệ', 'http_code' => $httpCode];
    }
    return ['ok' => true, 'http_code' => $httpCode, 'data' => $json];
}
// Tạo đơn thanh toán MoMo QR và nhận về URL QR code để khách hàng quét
function createMomoQrPayment(array $args): array {
    $cfg = momoConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'msg' => 'MoMo chưa được bật trong cấu hình'];
    }
    if ($cfg['partnerCode'] === '' || $cfg['accessKey'] === '' || $cfg['secretKey'] === '' || $cfg['redirectUrl'] === '' || $cfg['ipnUrl'] === '') {
        return ['ok' => false, 'msg' => 'Thiếu cấu hình MoMo (partnerCode/accessKey/secretKey/redirectUrl/ipnUrl)'];
    }

    $orderId = trim((string)($args['order_id'] ?? ''));
    $amount = (int)round((float)($args['amount'] ?? 0));
    if ($orderId === '' || $amount <= 0) {
        return ['ok' => false, 'msg' => 'Thông tin đơn MoMo không hợp lệ'];
    }

    $requestId = $orderId . '_' . time();
    $orderInfo = trim((string)($args['order_info'] ?? ('Thanh toán đơn hàng ' . $orderId)));
    $extraData = trim((string)($args['extra_data'] ?? ''));

    $payload = [
        'partnerCode' => $cfg['partnerCode'],
        'partnerName' => 'PaintMore',
        'storeId' => 'PaintMoreStore',
        'requestId' => $requestId,
        'amount' => (string)$amount,
        'orderId' => $orderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $cfg['redirectUrl'],
        'ipnUrl' => $cfg['ipnUrl'],
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => $cfg['requestType'],
        'autoCapture' => true,
    ];
    $payload['signature'] = momoSignCreate([
        'accessKey' => $cfg['accessKey'],
        'amount' => $payload['amount'],
        'extraData' => $payload['extraData'],
        'ipnUrl' => $payload['ipnUrl'],
        'orderId' => $payload['orderId'],
        'orderInfo' => $payload['orderInfo'],
        'partnerCode' => $payload['partnerCode'],
        'redirectUrl' => $payload['redirectUrl'],
        'requestId' => $payload['requestId'],
        'requestType' => $payload['requestType'],
    ], $cfg['secretKey']);

    $res = momoHttpPostJson($cfg['createUrl'], $payload);
    if (!($res['ok'] ?? false)) {
        return $res;
    }
    $data = is_array($res['data'] ?? null) ? $res['data'] : [];
    $code = (int)($data['resultCode'] ?? -1);
    if ($code !== 0) {
        return [
            'ok' => false,
            'msg' => (string)($data['message'] ?? 'MoMo tạo QR thất bại'),
            'result' => $data,
        ];
    }

    return [
        'ok' => true,
        'result' => $data,
        'request_id' => (string)($payload['requestId'] ?? ''),
        'qr_url' => (string)($data['qrCodeUrl'] ?? ''),
        'pay_url' => (string)($data['payUrl'] ?? ''),
        'deeplink' => (string)($data['deeplink'] ?? ''),
    ];
}
