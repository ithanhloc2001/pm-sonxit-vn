<?php
require_once __DIR__ . '/../../config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

function vnpay_get_config(): array {
    global $ECOMMERCE_PAYMENT_METHODS, $baseUrl;

    $cfg = is_array($ECOMMERCE_PAYMENT_METHODS['vnpay'] ?? null) ? $ECOMMERCE_PAYMENT_METHODS['vnpay'] : [];

    $payUrl = trim((string)($cfg['payUrl'] ?? ''));
    if ($payUrl === '') {
        $payUrl = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    }

    $returnUrl = trim((string)($cfg['returnUrl'] ?? ''));
    if ($returnUrl === '' && $baseUrl !== '') {
        $returnUrl = $baseUrl . '/order-confirm';
    }

    return [
        'tmnCode' => (string)($cfg['tmnCode'] ?? ''),
        'hashSecret' => (string)($cfg['hashSecret'] ?? ''),
        'returnUrl' => $returnUrl,
        'ipnUrl' => (string)($cfg['ipnUrl'] ?? ''),
        'payUrl' => $payUrl,
        'apiUrl' => (string)($cfg['apiUrl'] ?? ''),
        'version' => '2.1.0',
        'locale' => 'vn',
        'currency' => 'VND',
    ];
}

function vnpay_build_hash_data(array $inputData): string {
    ksort($inputData);
    $i = 0;
    $hashData = '';
    foreach ($inputData as $key => $value) {
        if ($i === 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode((string)$value);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode((string)$value);
            $i = 1;
        }
    }
    return $hashData;
}

function vnpay_build_payment_url(array $inputData, string $hashSecret, string $payUrl): string {
    ksort($inputData);
    $query = '';
    $i = 0;
    $hashData = '';
    foreach ($inputData as $key => $value) {
        if ($i === 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode((string)$value);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode((string)$value);
            $i = 1;
        }
        $query .= urlencode($key) . '=' . urlencode((string)$value) . '&';
    }
    $secureHash = hash_hmac('sha512', $hashData, $hashSecret);
    return rtrim($payUrl, '?') . '?' . $query . 'vnp_SecureHash=' . $secureHash;
}
