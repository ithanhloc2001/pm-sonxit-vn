<?php
require_once __DIR__. '/../../config.php';
// VNPAY IP whitelist (both sandbox and production)

// Danh sách IP VNPAY chính thức (sandbox & production)
$VNPAY_IP_WHITELIST = [
    // Sandbox
    '113.160.92.202',
    '203.205.17.226',
    '103.220.84.4',
    // Production
    '113.52.45.78',
    '116.97.245.130',
    '42.118.107.252',
    '113.20.97.250',
    '203.171.19.146',
    '103.220.87.4',
    '103.220.86.4',
    '103.220.86.10',
    '103.220.87.10',
    '103.220.86.139',
    '103.220.86.139',
    '103.220.87.139',
];

// Kiểm tra IP private (LAN, loopback)
function vnpIsPrivateIp(string $ip): bool {
    $ip = trim($ip);
    if ($ip === '' ) return false;
    // IPv4 private / loopback
    if (preg_match('~^(10\.|127\.0\.0\.1|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)~', $ip)) {
        return true;
    }
    // IPv6 loopback
    if ($ip === '::1') return true;
    return false;
}

// Chuẩn hoá IP VNPAY: xử lý trường hợp proxy và IPv6 mapped (::ffff:x.x.x.x)
function vnpDetectClientIp(): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

    // Ưu tiên IP thật từ X-Forwarded-For nếu server đứng sau reverse proxy
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $candidate = trim($parts[0] ?? '');
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            $remote = $candidate;
        }
    }

    // IPv6 mapped IPv4: ::ffff:113.160.92.202
    if (strpos($remote, '::ffff:') === 0) {
        $remote = substr($remote, 7);
    }

    return $remote;
}

// IP client gọi IPN sau khi chuẩn hoá
$clientIp = vnpDetectClientIp();



function ensureVnpayIpnLogTable(mysqli $ithanhloc): void {
    $checkSql = "SHOW TABLES LIKE 'vnpay_ipn_log'";
    $res = $ithanhloc->query($checkSql);
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->close();
        if ($exists) return;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS `vnpay_ipn_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_id` VARCHAR(64) NOT NULL,
        `response_code` VARCHAR(8) NOT NULL,
        `transaction_status` VARCHAR(8) NOT NULL,
        `amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
        `bank_code` VARCHAR(32) DEFAULT NULL,
        `bank_tran_no` VARCHAR(64) DEFAULT NULL,
        `gateway_tran_no` VARCHAR(64) DEFAULT NULL,
        `is_valid` TINYINT(1) NOT NULL DEFAULT 0,
        `ip` VARCHAR(64) DEFAULT NULL,
        `raw_payload` LONGTEXT,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_order_id` (`order_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$ithanhloc->query($createSql)) {
        error_log('VNPAY IPN: failed to create vnpay_ipn_log table: ' . $ithanhloc->error);
    }
}

function refundXuForFailedPayment(mysqli $ithanhloc, array $order): void {
    $userId = (int)($order['user_id'] ?? 0);
    $orderId = (string)($order['order_id'] ?? '');
    $xuUsed = max(0, (int)($order['xu_used'] ?? 0));
    if ($userId <= 0 || $orderId === '' || $xuUsed <= 0) return;
    $ithanhloc->begin_transaction();
    try {
        // (Đã bỏ câu UPDATE users SET balance = balance — không có tác dụng, chỉ là copy-paste thừa.)
        $txType = 'order_refund';
        $txNote = 'Hoàn xu do thanh toán thất bại: ' . $orderId;
        $stmtTx = $ithanhloc->prepare('INSERT IGNORE INTO user_balance_log (user_id, ref_order_id, type, amount, note) VALUES (?,?,?,?,?)');
        if ($stmtTx) {
            $stmtTx->bind_param('issis', $userId, $orderId, $txType, $xuUsed, $txNote);
            $stmtTx->execute();
            $inserted = $stmtTx->affected_rows > 0;
            $stmtTx->close();
            if ($inserted) {
                $stmtW = $ithanhloc->prepare('UPDATE users SET balance = COALESCE(balance,0) + ? WHERE id=?');
                if ($stmtW) {
                    $stmtW->bind_param('ii', $xuUsed, $userId);
                    $stmtW->execute();
                    $stmtW->close();
                }
                $stmtO = $ithanhloc->prepare('UPDATE ecommerce_order SET xu_refunded = COALESCE(xu_refunded,0) + ? WHERE order_id=?');
                if ($stmtO) {
                    $stmtO->bind_param('is', $xuUsed, $orderId);
                    $stmtO->execute();
                    $stmtO->close();
                }
            }
        }

        $ithanhloc->commit();
    } catch (Throwable $e) {
        $ithanhloc->rollback();
    }
}

function logVnpayIpn($ithanhloc, array $data) {
    $stmt = $ithanhloc->prepare('INSERT INTO vnpay_ipn_log (order_id, response_code, transaction_status, amount, bank_code, bank_tran_no, gateway_tran_no, is_valid, ip, raw_payload)
        VALUES (?,?,?,?,?,?,?,?,?,?)');
    if (!$stmt) {
        error_log('VNPAY IPN: prepare failed for vnpay_ipn_log insert: ' . $ithanhloc->error);
        return;
    }
    $stmt->bind_param(
        'sssdsssiss',
        $data['order_id'],
        $data['response_code'],
        $data['transaction_status'],
        $data['amount'],
        $data['bank_code'],
        $data['bank_tran_no'],
        $data['gateway_tran_no'],
        $data['is_valid'],
        $data['ip'],
        $data['raw_payload']
    );
    $stmt->execute();
    if ($stmt->errno) {
        error_log('VNPAY IPN: insert error for vnpay_ipn_log: ' . $stmt->error);
    }
    $stmt->close();
}

function vnpayResponse($code, $message) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['RspCode' => $code, 'Message' => $message]);
    exit;
}

$cfg = is_array($ECOMMERCE_PAYMENT_METHODS['vnpay'] ?? null) ? $ECOMMERCE_PAYMENT_METHODS['vnpay'] : [];
$hashSecret = (string)($cfg['hashSecret'] ?? '');
if ($hashSecret === '') {
    vnpayResponse('99', 'Missing VNPAY hashSecret');
}

$inputData = [];
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) === 'vnp_') {
        $inputData[$key] = $value;
    }
}

$vnpSecureHash = $inputData['vnp_SecureHash'] ?? '';
unset($inputData['vnp_SecureHash'], $inputData['vnp_SecureHashType']);
ksort($inputData);
$hashData = '';
$i = 0;
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
    } else {
        $hashData .= urlencode($key) . '=' . urlencode($value);
        $i = 1;
    }
}
$secureHash = hash_hmac('sha512', $hashData, $hashSecret);
$isValid = ($secureHash === $vnpSecureHash);

$orderId = (string)($inputData['vnp_TxnRef'] ?? '');
$amount = isset($inputData['vnp_Amount']) ? ((float)$inputData['vnp_Amount'] / 100.0) : 0.0;
$responseCode = (string)($inputData['vnp_ResponseCode'] ?? '');
$tranStatus = (string)($inputData['vnp_TransactionStatus'] ?? '');
$bankCode = (string)($inputData['vnp_BankCode'] ?? '');
$bankTranNo = (string)($inputData['vnp_BankTranNo'] ?? '');
$gatewayTranNo = (string)($inputData['vnp_TransactionNo'] ?? '');

// Đảm bảo bảng log tồn tại trước khi ghi nhận
ensureVnpayIpnLogTable($ithanhloc);

logVnpayIpn($ithanhloc, [
    'order_id' => $orderId,
    'response_code' => $responseCode,
    'transaction_status' => $tranStatus,
    'amount' => $amount,
    'bank_code' => $bankCode,
    'bank_tran_no' => $bankTranNo,
    'gateway_tran_no' => $gatewayTranNo,
    'is_valid' => $isValid ? 1 : 0,
    'ip' => $clientIp,
    'raw_payload' => json_encode($_GET, JSON_UNESCAPED_UNICODE),
]);

// Sau khi đã ghi log đầy đủ, áp dụng whitelist IP cho VNPAY IPN
if ($clientIp === '' || !in_array($clientIp, $VNPAY_IP_WHITELIST, true)) {
    vnpayResponse('03', 'IP address not allowed');
}

if (!$isValid) {
    vnpayResponse('97', 'Invalid signature');
}

if ($orderId === '') {
    vnpayResponse('01', 'Order not found');
}

$cols = listColumns($ithanhloc, 'ecommerce_order');
if (!$cols) {
    vnpayResponse('99', 'Order table missing');
}

$stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE order_id=? LIMIT 1');
if (!$stmt) {
    vnpayResponse('99', 'Order lookup failed');
}
$stmt->bind_param('s', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    vnpayResponse('01', 'Order not found');
}

if (hasCol($cols, 'total_amount') && isset($order['total_amount'])) {
    $expected = (float)($order['total_amount'] ?? 0);
    if ($expected > 0 && abs($expected - $amount) > 0.5) {
        vnpayResponse('04', 'Invalid amount');
    }
}

// Trạng thái thanh toán hiện tại của đơn (trước khi cập nhật theo IPN này).
$prevPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));

// Đơn đã 'paid' rồi -> IPN trùng, không xử lý lại (chống double-confirm).
if (hasCol($cols, 'payment_status') && $prevPaymentStatus === 'paid') {
    vnpayResponse('02', 'Order already confirmed');
}

$success = ($responseCode === '00' && $tranStatus === '00');
$set = [];
if (hasCol($cols, 'payment_status')) { $set['payment_status'] = $success ? 'paid' : 'failed'; }
if (hasCol($cols, 'payment_gateway')) { $set['payment_gateway'] = 'vnpay'; }
if (hasCol($cols, 'payment_ref')) { $set['payment_ref'] = $orderId; }
if (hasCol($cols, 'bank_code')) { $set['bank_code'] = $bankCode; }
if (hasCol($cols, 'bank_tran_no')) { $set['bank_tran_no'] = $bankTranNo; }
if (hasCol($cols, 'gateway_tran_no')) { $set['gateway_tran_no'] = $gatewayTranNo; }
if (hasCol($cols, 'payment_response_code')) { $set['payment_response_code'] = $responseCode; }
if ($success && hasCol($cols, 'paid_at')) { $set['paid_at'] = date('Y-m-d H:i:s'); }
if (hasCol($cols, 'status')) { $set['status'] = $success ? 'processing' : 'pending'; }

if ($set) {
    $fields = array_keys($set);
    $types = '';
    $params = [];
    foreach ($fields as $f) {
        $types .= 's';
        $params[] = (string)$set[$f];
    }
    $params[] = $orderId;
    $types .= 's';
    $sql = "UPDATE ecommerce_order SET " . implode(', ', array_map(fn($f) => "`$f`=?", $fields)) . " WHERE order_id=?";
    $stmtU = $ithanhloc->prepare($sql);
    if ($stmtU) {
        $stmtU->bind_param($types, ...$params);
        $stmtU->execute();
        $stmtU->close();
    }
}

// Chỉ hoàn xu khi đơn LẦN ĐẦU chuyển sang thất bại (prev chưa phải 'failed').
// Tránh hoàn xu lặp khi VNPAY gửi nhiều IPN failed (retry). Kết hợp với UNIQUE
// (ref_order_id, type) ở user_balance_log để chống double-refund 2 lớp.
if (!$success && $prevPaymentStatus !== 'failed') {
    refundXuForFailedPayment($ithanhloc, $order);
}

vnpayResponse('00', 'Confirm Success');

