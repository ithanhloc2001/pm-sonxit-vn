<?php
require_once __DIR__ . '/vnpay_config.php';
header('Content-Type: application/json; charset=utf-8');

$cfg = vnpay_get_config();
$tmnCode = $cfg['tmnCode'] ?? '';
$hashSecret = $cfg['hashSecret'] ?? '';
$returnUrl = $cfg['returnUrl'] ?? '';
$payUrl = $cfg['payUrl'] ?? '';

if ($tmnCode === '' || $hashSecret === '' || $returnUrl === '') {
    echo json_encode([
        'ok' => false,
        'message' => 'Missing VNPAY config (tmnCode/hashSecret/returnUrl).',
    ]);
    exit;
}

$orderId = trim((string)($_POST['order_id'] ?? ''));
$amountRaw = trim((string)($_POST['amount'] ?? ''));
$orderInfo = trim((string)($_POST['order_info'] ?? ''));
$bankCode = trim((string)($_POST['bank_code'] ?? ''));
$locale = trim((string)($_POST['locale'] ?? $cfg['locale'] ?? 'vn'));

if ($orderId === '' || $amountRaw === '') {
    echo json_encode([
        'ok' => false,
        'message' => 'Missing order_id or amount.',
    ]);
    exit;
}

$amount = (int)round((float)$amountRaw * 100);
if ($amount <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid amount.',
    ]);
    exit;
}

$ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$createDate = $now->format('YmdHis');
$expireDate = $now->modify('+30 minutes')->format('YmdHis');
$orderInfo = $company_bank_content_text .' - '. $orderId;

$inputData = [
    'vnp_Version' => $cfg['version'] ?? '2.1.0',
    'vnp_Command' => 'pay',
    'vnp_TmnCode' => $tmnCode,
    'vnp_Amount' => $amount,
    'vnp_CurrCode' => $cfg['currency'] ?? 'VND',
    'vnp_TxnRef' => $orderId,
    'vnp_OrderInfo' => $orderInfo,
    'vnp_OrderType' => 'other',
    'vnp_Locale' => $locale !== '' ? $locale : 'vn',
    'vnp_ReturnUrl' => $returnUrl,
    'vnp_IpAddr' => $ipAddr,
    'vnp_CreateDate' => $createDate,
    'vnp_ExpireDate' => $expireDate,
];

if ($bankCode !== '') {
    $inputData['vnp_BankCode'] = $bankCode;
}

$paymentUrl = vnpay_build_payment_url($inputData, $hashSecret, $payUrl);

echo json_encode([
    'ok' => true,
    'payment_url' => $paymentUrl,
    'order_id' => $orderId,
]);
