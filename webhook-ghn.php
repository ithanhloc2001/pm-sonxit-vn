<?php
/**
 * Webhook endpoint nhận callback từ Giao Hàng Nhanh (GHN).
 *
 * Cấu hình trên GHN portal:
 *   URL: https://<your-domain>/webhook-ghn.php?token=<ECOMMERCE_GHN_WEBHOOK_SECRET>
 *   Hoặc gửi header: Token: <ECOMMERCE_GHN_WEBHOOK_SECRET>
 *
 * GHN gửi POST JSON với các field như:
 *   { OrderCode, Status, Type, Description, Reason, ReasonCode, Time, CODAmount, ... }
 *
 * Endpoint sẽ:
 *   1. Xác thực token
 *   2. Tra ghn_order theo OrderCode → lấy system_order_id
 *   3. Gọi ghn_sync_ecommerce_order_status() (đã có guard rails)
 *   4. Gọi ghn_order_update_status() để cập nhật bảng ghn_order + log
 *   5. Trả 200 OK với {code:200, message:"Success"}
 *
 * QUAN TRỌNG: GHN không có chữ ký HMAC trên webhook. Bảo mật DUY NHẤT là secret token.
 * Bảo vệ token + dùng HTTPS.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core_admin/giaohangnhanh/lib/ghn_admin.php';

header('Content-Type: application/json; charset=utf-8');

function ghn_webhook_respond(int $httpCode, array $payload): void {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ghn_webhook_log(string $orderCode, string $event, array $payload, string $note = ''): void {
    $logDir = __DIR__ . '/scratch';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    $line = sprintf("[%s] order=%s event=%s note=%s payload=%s\n",
        date('Y-m-d H:i:s'),
        $orderCode,
        $event,
        $note,
        json_encode($payload, JSON_UNESCAPED_UNICODE)
    );
    @file_put_contents($logDir . '/ghn_webhook.log', $line, FILE_APPEND);
}

// 1. Xác thực secret token
$expectedSecret = trim((string)($ECOMMERCE_GHN['webhook_secret'] ?? ''));
if ($expectedSecret === '') {
    ghn_webhook_respond(503, ['code' => 503, 'message' => 'Webhook secret chưa cấu hình']);
}

$providedSecret = '';
if (isset($_SERVER['HTTP_TOKEN'])) {
    $providedSecret = trim((string)$_SERVER['HTTP_TOKEN']);
} elseif (isset($_GET['token'])) {
    $providedSecret = trim((string)$_GET['token']);
}

if (!hash_equals($expectedSecret, $providedSecret)) {
    ghn_webhook_log('', 'auth_failed', ['provided_token_len' => strlen($providedSecret)], 'Token không khớp');
    ghn_webhook_respond(401, ['code' => 401, 'message' => 'Unauthorized']);
}

// 2. Chỉ chấp nhận POST
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ghn_webhook_respond(405, ['code' => 405, 'message' => 'Method Not Allowed']);
}

// 3. Đọc body JSON
$rawBody = file_get_contents('php://input');
$payload = json_decode((string)$rawBody, true);
if (!is_array($payload)) {
    ghn_webhook_log('', 'invalid_json', ['raw_len' => strlen((string)$rawBody)], 'Body không phải JSON');
    ghn_webhook_respond(400, ['code' => 400, 'message' => 'Invalid JSON body']);
}

// 4. Trích xuất các field GHN gửi (case-insensitive — GHN dùng PascalCase)
$pick = static function(array $arr, array $keys): string {
    foreach ($keys as $k) {
        if (isset($arr[$k]) && trim((string)$arr[$k]) !== '') return trim((string)$arr[$k]);
    }
    return '';
};

$orderCode  = $pick($payload, ['OrderCode', 'order_code']);
$ghnStatus  = $pick($payload, ['Status', 'status']);
$statusText = $pick($payload, ['Description', 'description', 'Reason', 'reason']);
$reasonCode = $pick($payload, ['ReasonCode', 'reason_code']);
$ghnTime    = $pick($payload, ['Time', 'time']);

if ($orderCode === '' || $ghnStatus === '') {
    ghn_webhook_log($orderCode, 'missing_fields', $payload, 'Thiếu OrderCode hoặc Status');
    ghn_webhook_respond(400, ['code' => 400, 'message' => 'Missing OrderCode or Status']);
}

// 5. Tra cứu system_order_id từ ghn_order
if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    ghn_webhook_log($orderCode, 'db_unavailable', $payload, 'mysqli connection chưa khởi tạo');
    ghn_webhook_respond(500, ['code' => 500, 'message' => 'DB unavailable']);
}

$systemOrderId = '';
$stmt = $ithanhloc->prepare('SELECT system_order_id FROM ghn_order WHERE order_code=? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('s', $orderCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $systemOrderId = (string)($row['system_order_id'] ?? '');
}

if ($systemOrderId === '') {
    // GHN có thể gửi callback cho vận đơn chưa từng được hệ thống tạo (rare). Log + trả 200 để GHN không retry.
    ghn_webhook_log($orderCode, 'system_order_not_found', $payload, 'Không tìm thấy mapping ghn_order → system order');
    ghn_webhook_respond(200, ['code' => 200, 'message' => 'OK (no mapping)']);
}

// 6. Cập nhật ghn_order + log
try {
    ghn_order_update_status($ithanhloc, $orderCode, $ghnStatus, $statusText, $payload);
} catch (Throwable $e) {
    ghn_webhook_log($orderCode, 'ghn_order_update_status_failed', $payload, $e->getMessage());
}

// 7. Đồng bộ ecommerce_order (đã có guard rails: không đè cancel_requested, không downgrade, audit log)
try {
    ghn_sync_ecommerce_order_status($ithanhloc, $systemOrderId, $ghnStatus, $orderCode, $statusText);
} catch (Throwable $e) {
    ghn_webhook_log($orderCode, 'ghn_sync_ecommerce_failed', $payload, $e->getMessage());
    ghn_webhook_respond(500, ['code' => 500, 'message' => 'Internal error']);
}

ghn_webhook_log($orderCode, 'processed', [
    'system_order_id' => $systemOrderId,
    'ghn_status' => $ghnStatus,
    'reason_code' => $reasonCode,
    'time' => $ghnTime,
], 'OK');

ghn_webhook_respond(200, ['code' => 200, 'message' => 'Success']);
