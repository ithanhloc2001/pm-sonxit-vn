<?php
/**
 * SSE (Server-Sent Events) endpoint – đồng bộ realtime trạng thái đơn hàng.
 *
 * GET /core_user/ecommerce/ajax/order-stream.php?order_id=ORD123&last_log_id=0
 *
 * Client kết nối qua EventSource. Server poll DB mỗi 5 giây và emit event
 * khi có log mới. Sau 55 giây sẽ emit heartbeat để giữ kết nối và tránh
 * timeout của proxy/Apache, sau đó tự đóng để client reconnect.
 *
 * Events phát ra:
 *   - "order_updated" : có thay đổi log mới, data = JSON payload đầy đủ của đơn
 *   - "heartbeat"     : ping giữ kết nối, data = unix timestamp
 *   - "end"           : server sắp đóng, client sẽ tự reconnect
 */

require_once __DIR__ . '/../../../config.php';

// Chỉ cho phép GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$orderId = trim((string)($_GET['order_id'] ?? ''));
if ($orderId === '') {
    http_response_code(400);
    echo "data: {\"error\":\"missing order_id\"}\n\n";
    exit;
}

// Xác thực quyền sở hữu đơn hàng (khách hoặc admin)
$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$sessionRole   = (string)($_SESSION['role'] ?? '');
$isAdmin       = ($sessionRole === 'admin');

if (!$isAdmin) {
    // Khách: kiểm tra ownership nhẹ (chỉ check order tồn tại và user_id khớp hoặc order_id dạng guest)
    $stmtCheck = $ithanhloc->prepare(
        'SELECT id FROM ecommerce_order WHERE order_id = ? AND (user_id = ? OR user_id = 0 OR user_id IS NULL) LIMIT 1'
    );
    if ($stmtCheck) {
        $stmtCheck->bind_param('si', $orderId, $sessionUserId);
        $stmtCheck->execute();
        $found = (bool)$stmtCheck->get_result()->fetch_row();
        $stmtCheck->close();
        if (!$found && $sessionUserId > 0) {
            // Nếu đã login mà không match → deny
            http_response_code(403);
            exit;
        }
    }
}

// Đảm bảo bảng tồn tại
ecommerce_order_log_ensure_table($ithanhloc);

$lastLogId = max(0, (int)($_GET['last_log_id'] ?? 0));

// Giải phóng session lock ngay sau khi đã đọc xong session data.
// PHP session dùng file lock — nếu không close ở đây, mọi request
// khác từ cùng browser sẽ bị block chờ session trong suốt 55 giây.
session_write_close();

// SSE headers
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Tắt buffering của Nginx nếu có
header('Connection: keep-alive');

// Tắt output buffering của PHP
while (ob_get_level() > 0) {
    ob_end_flush();
}

set_time_limit(0);
ignore_user_abort(true);

$startTime   = time();
$maxLifetime = 55;  // Giây: tự đóng để client reconnect, tránh timeout

function sseEmit(string $event, string $data): void {
    echo "event: {$event}\n";
    echo "data: {$data}\n\n";
    flush();
}

// Hàm lấy payload đơn hàng đầy đủ để gửi cho client
function fetchOrderPayloadForSse(mysqli $ithanhloc, string $orderId): ?array {
    // Load minimal — chỉ cần status, timeline, actions
    $stmt = $ithanhloc->prepare(
        'SELECT o.*, o.status as status
         FROM ecommerce_order o
         WHERE o.order_id = ?
         LIMIT 1'
    );
    if (!$stmt) return null;
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;

    // Build timeline từ ecommerce_order_log
    $timeline = buildTimelineForSse($ithanhloc, $orderId, $row);

    // Status info
    $statusInfo = function_exists('ecommerce_order_status_info')
        ? ecommerce_order_status_info((string)($row['status'] ?? 'pending'))
        : [];

    return [
        'order_id'     => $orderId,
        'status'       => (string)($statusInfo['key'] ?? $row['status'] ?? ''),
        'status_label' => (string)($statusInfo['label'] ?? $row['status'] ?? ''),
        'timeline'     => $timeline,
        'updated_at'   => (string)($row['updated_at'] ?? ''),
    ];
}

function buildTimelineForSse(mysqli $ithanhloc, string $orderId, array $row): array {
    $logs = ecommerce_order_log_fetch($ithanhloc, $orderId);
    $statusLabels = [
        'pending'          => 'Chờ xác nhận',
        'processing'       => 'Đã xác nhận — Đang chuẩn bị hàng',
        'shipping'         => 'Đang giao hàng',
        'delivered'        => 'Đã giao hàng thành công',
        'cancel_requested' => 'Yêu cầu hủy đơn — Chờ xét duyệt',
        'canceled'         => 'Đơn hàng đã hủy',
        'return_requested' => 'Yêu cầu trả hàng — Chờ xét duyệt',
        'returned'         => 'Đã hoàn trả hàng',
        'refunded'         => 'Đã hoàn tiền',
    ];
    $actorLabels = [
        'admin'    => 'Admin',
        'customer' => 'Khách hàng',
        'system'   => 'Hệ thống',
        'carrier'  => 'Đơn vị vận chuyển',
    ];
    $timeline = [];
    if (!empty($row['created_at'])) {
        $timeline[] = [
            'label'      => 'Tạo đơn hàng',
            'time'       => $row['created_at'],
            'time_human' => date('H:i d/m/Y', strtotime((string)$row['created_at'])),
            'actor'      => 'Khách hàng',
            'note'       => '',
        ];
    }
    foreach ($logs as $log) {
        $statusTo  = (string)($log['status_to'] ?? '');
        $label     = $statusLabels[$statusTo] ?? ('Cập nhật: ' . $statusTo);
        $actorType = (string)($log['actor_type'] ?? 'system');
        $timeline[] = [
            'label'      => $label,
            'time'       => $log['created_at'],
            'time_human' => date('H:i d/m/Y', strtotime((string)$log['created_at'])),
            'actor'      => $actorLabels[$actorType] ?? $actorType,
            'note'       => (string)($log['note'] ?? ''),
        ];
    }
    return $timeline;
}

// Vòng lặp SSE
while (!connection_aborted()) {
    $elapsed = time() - $startTime;

    if ($elapsed >= $maxLifetime) {
        sseEmit('end', json_encode(['reason' => 'reconnect']));
        break;
    }

    // Kiểm tra log mới
    $stmt = $ithanhloc->prepare(
        'SELECT COALESCE(MAX(id), 0) FROM ecommerce_order_log WHERE order_id = ?'
    );
    $currentMaxId = 0;
    if ($stmt) {
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $currentMaxId = (int)$stmt->get_result()->fetch_row()[0];
        $stmt->close();
    }

    if ($currentMaxId > $lastLogId) {
        $lastLogId = $currentMaxId;
        $payload   = fetchOrderPayloadForSse($ithanhloc, $orderId);
        if ($payload !== null) {
            $payload['last_log_id'] = $lastLogId;
            sseEmit('order_updated', json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
    } else {
        // Heartbeat mỗi lần poll để giữ kết nối sống
        sseEmit('heartbeat', (string)time());
    }

    sleep(5);
}
