<?php
require_once __DIR__ . '/vnpay_config.php';

$cfg = vnpay_get_config();
$hashSecret = $cfg['hashSecret'] ?? '';

$inputData = [];
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) === 'vnp_') {
        $inputData[$key] = $value;
    }
}

$vnpSecureHash = $inputData['vnp_SecureHash'] ?? '';
unset($inputData['vnp_SecureHash'], $inputData['vnp_SecureHashType']);

$hashData = vnpay_build_hash_data($inputData);
$secureHash = $hashSecret !== '' ? hash_hmac('sha512', $hashData, $hashSecret) : '';

$isValid = ($secureHash !== '' && $secureHash === $vnpSecureHash);
$respCode = (string)($_GET['vnp_ResponseCode'] ?? '');
$tranStatus = (string)($_GET['vnp_TransactionStatus'] ?? '');
$success = $isValid && $respCode === '00' && $tranStatus === '00';

$orderId = (string)($_GET['vnp_TxnRef'] ?? '');
$amount = (string)($_GET['vnp_Amount'] ?? '');
$bankCode = (string)($_GET['vnp_BankCode'] ?? '');
$payDate = (string)($_GET['vnp_PayDate'] ?? '');
$transactionNo = (string)($_GET['vnp_TransactionNo'] ?? '');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VNPAY Return</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f6f7fb;margin:0;padding:20px;}
        .card{max-width:680px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;box-shadow:0 6px 18px rgba(15,23,42,.06);} 
        .title{font-size:18px;font-weight:700;margin:0 0 12px;} 
        .row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px dashed #e5e7eb;font-size:14px;}
        .row:last-child{border-bottom:0;}
        .label{color:#6b7280;}
        .value{font-weight:600;color:#111827;}
        .status{margin-top:12px;font-weight:700;color:#0f172a;}
        .ok{color:#16a34a;}
        .fail{color:#dc2626;}
    </style>
</head>
<body>
    <div class="card">
        <div class="title">VNPAY Return</div>
        <div class="row"><div class="label">Order ID</div><div class="value"><?php echo htmlspecialchars($orderId); ?></div></div>
        <div class="row"><div class="label">Amount</div><div class="value"><?php echo htmlspecialchars($amount); ?></div></div>
        <div class="row"><div class="label">Bank Code</div><div class="value"><?php echo htmlspecialchars($bankCode); ?></div></div>
        <div class="row"><div class="label">Transaction No</div><div class="value"><?php echo htmlspecialchars($transactionNo); ?></div></div>
        <div class="row"><div class="label">Pay Date</div><div class="value"><?php echo htmlspecialchars($payDate); ?></div></div>
        <div class="row"><div class="label">Signature</div><div class="value"><?php echo $isValid ? 'Valid' : 'Invalid'; ?></div></div>
        <div class="status <?php echo $success ? 'ok' : 'fail'; ?>">
            <?php echo $success ? 'Payment Success' : 'Payment Failed'; ?>
        </div>
    </div>
</body>
</html>
