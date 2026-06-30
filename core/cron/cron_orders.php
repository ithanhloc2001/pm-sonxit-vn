<?php
/**
 * Cronjob: Tự động hóa nghiệp vụ Đơn hàng (Đồng bộ n8n, Cập nhật trạng thái/Hủy quá hạn, Tự động hoàn thành)
 * Tần suất khuyến nghị: chạy mỗi 5-15 phút.
 */

// 1. Cấu hình bật/tắt các tiến trình con trực tiếp trong file
if (!isset($cron_orders_config)) {
    $cron_orders_config = [
        'n8n_orders'       => true, // Đồng bộ đơn hàng mới qua Webhook n8n
        'order_status'     => true, // Hủy đơn quá hạn, xóa đơn đã hủy quá lâu, chuẩn hóa thanh toán
        'complete_orders'  => true, // Tự động hoàn thành đơn hàng sau N ngày
    ];
}

require_once __DIR__ . '/../../config.php';

// Thiết lập header cho trình duyệt
header('Refresh: 300');
header('Content-Type: text/plain; charset=utf-8');

echo "=== TIẾN TRÌNH CRON ĐƠN HÀNG (" . date('H:i:s d/m/Y') . ") ===\n\n";

// ===== TIẾN TRÌNH 1: ĐỒNG BỘ N8N (n8n_orders) =================================
if ($cron_orders_config['n8n_orders']) {
    echo "--- [1] ĐỒNG BỘ ĐƠN HÀNG QUA WEBHOOK N8N ---\n";
    
    $webhookUrl = trim($_GET['n8n_url'] ?? $_GET['url'] ?? 'https://zalo.onecoat.vn/webhook/api');
    $n8nLimit   = max(1, min(50, (int)($_GET['n8n_limit'] ?? $_GET['limit'] ?? 10)));
    $lockName1  = 'cron_n8n_orders';

    // Đảm bảo bảng log tồn tại để ghi vết
    $ithanhloc->query("CREATE TABLE IF NOT EXISTS `log_n8n` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `order_id` VARCHAR(64) NOT NULL UNIQUE,
        `webhook_url` VARCHAR(255) NOT NULL,
        `ok` TINYINT(1) NOT NULL DEFAULT 0,
        `http_code` INT NULL,
        `attempts` INT NOT NULL DEFAULT 0,
        `last_attempt_at` DATETIME NULL,
        `sent_at` DATETIME NULL,
        `response_body` MEDIUMTEXT NULL,
        `error_text` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_ok_sent_at` (`ok`, `sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Kiểm tra Lock tránh việc chạy trùng lặp nhiều tiến trình cùng lúc
    $lock1 = $ithanhloc->query("SELECT GET_LOCK('$lockName1', 1) AS l")->fetch_assoc();
    if (!$lock1 || !$lock1['l']) {
        echo "Tiến trình n8n_orders đang chạy ở luồng khác, bỏ qua.\n\n";
    } else {
        // Lấy danh sách đơn hàng cần đồng bộ (chưa gửi thành công)
        $sql = "SELECT o.* FROM ecommerce_order o 
                LEFT JOIN log_n8n l ON o.order_id = l.order_id AND l.ok = 1
                WHERE o.status = 'pending' AND l.id IS NULL 
                ORDER BY o.id ASC LIMIT $n8nLimit";

        $res = $ithanhloc->query($sql);
        if (!$res || $res->num_rows === 0) {
            echo "Không có đơn hàng mới cần đồng bộ.\n";
        } else {
            $sent = 0; $failed = 0;
            while ($order = $res->fetch_assoc()) {
                $oid = $order['order_id'];
                $items = json_decode($order['items_json'] ?? '[]', true);
                
                // Xây dựng gói dữ liệu (Payload) gửi sang n8n
                $payload = [
                    'event'    => 'order.created',
                    'order_id' => $oid,
                    'status'   => ['order_status' => $order['status'], 'created_at' => $order['created_at']],
                    'customer' => ['name' => $order['user_name'], 'phone' => $order['phone']],
                    'totals'   => ['total' => (float)$order['total_amount']],
                    'items'    => $items,
                    'sent_at'  => date('c')
                ];

                // Ghi nhận lần thử gửi này
                $ithanhloc->query("INSERT INTO log_n8n (order_id, webhook_url, attempts, last_attempt_at) VALUES ('$oid', '$webhookUrl', 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()");

                // Gửi dữ liệu qua CURL (POST JSON)
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 15
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                curl_close($ch);

                $ok = ($error === '' && $httpCode >= 200 && $httpCode < 300) ? 1 : 0;

                // Cập nhật kết quả vào log
                $stmt = $ithanhloc->prepare("UPDATE log_n8n SET ok=?, http_code=?, response_body=?, error_text=?, sent_at=IF(?, NOW(), sent_at) WHERE order_id=?");
                $respBody = mb_substr($response ?: '', 0, 1000);
                $stmt->bind_param('iissis', $ok, $httpCode, $respBody, $error, $ok, $oid);
                $stmt->execute();
                $stmt->close();

                if ($ok) $sent++; else $failed++;
            }
            echo "Hoàn tất đồng bộ. Thành công: $sent, Thất bại: $failed\n";
        }
        $ithanhloc->query("SELECT RELEASE_LOCK('$lockName1')");
        echo "\n";
    }
} else {
    echo "--- [1] ĐỒNG BỘ ĐƠN HÀNG QUA WEBHOOK N8N (TẮT) ---\n\n";
}

// ===== TIẾN TRÌNH 2: CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG (order_status) ===============
if ($cron_orders_config['order_status']) {
    echo "--- [2] CẬP NHẬT TRẠNG THÁI & DỌN DẸP ĐƠN HÀNG ---\n";

    // 1. Hủy các đơn hàng quá hạn 15 phút (trừ thanh toán khi nhận hàng - COD)
    $sqlPick = "SELECT * FROM ecommerce_order
                WHERE status = 'pending'
                  AND created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                  AND LOWER(COALESCE(payment_method,'')) <> 'cod'
                  AND LOWER(COALESCE(payment_status,'')) IN ('', 'pending', 'failed')
                LIMIT 500";
    $resCancel = $ithanhloc->query($sqlPick);
    $canceled = 0;
    if ($resCancel) {
        while ($orderRow = $resCancel->fetch_assoc()) {
            try {
                markOrderExpiredCancel($ithanhloc, $orderRow);
                $canceled++;
            } catch (Throwable $e) {
                error_log('cron markOrderExpiredCancel failed for ' . ($orderRow['order_id'] ?? '?') . ': ' . $e->getMessage());
            }
        }
    }

    // 2. Xóa các đơn hàng đã hủy quá 30 ngày
    $sqlIds = "SELECT order_id FROM ecommerce_order 
               WHERE status = 'canceled' 
                 AND created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 AND LOWER(COALESCE(payment_status,'')) IN ('', 'pending', 'failed', 'expired')
               LIMIT 500";
    $res = $ithanhloc->query($sqlIds);
    $ids = [];
    while ($row = $res->fetch_assoc()) $ids[] = $row['order_id'];

    $deleted = 0;
    if ($ids) {
        $idList = "'" . implode("','", $ids) . "'";
        $ithanhloc->query("DELETE FROM ecommerce_order_invoice WHERE order_id IN ($idList)");
        $ithanhloc->query("DELETE FROM ecommerce_order_review WHERE order_id IN ($idList)");
        $ithanhloc->query("DELETE FROM ecommerce_order_refund WHERE order_id IN ($idList)");
        
        $ithanhloc->query("DELETE FROM ecommerce_order WHERE order_id IN ($idList)");
        $deleted = $ithanhloc->affected_rows;
    }

    // 3. Chuẩn hóa trạng thái thanh toán
    $sqlNormalize = "UPDATE ecommerce_order SET payment_status = CASE 
                        WHEN LOWER(COALESCE(payment_method,''))='cod' THEN 'unpaid'
                        ELSE 'pending'
                     END
                     WHERE (LOWER(COALESCE(payment_status,''))='cod' OR TRIM(COALESCE(payment_status,''))='')
                     LIMIT 5000";
    $ithanhloc->query($sqlNormalize);
    $normalized = $ithanhloc->affected_rows;

    echo "1. Đã tự động hủy quá hạn (15p): $canceled đơn\n";
    echo "2. Đã xóa vĩnh viễn đơn cũ (30 ngày): $deleted đơn\n";
    echo "3. Đã chuẩn hóa trạng thái thanh toán: $normalized đơn\n\n";
} else {
    echo "--- [2] CẬP NHẬT TRẠNG THÁI & DỌN DẸP ĐƠN HÀNG (TẮT) ---\n\n";
}

// ===== TIẾN TRÌNH 3: TỰ ĐỘNG HOÀN THÀNH ĐƠN HÀNG (complete_orders) =============
if ($cron_orders_config['complete_orders']) {
    echo "--- [3] TỰ ĐỘNG HOÀN THÀNH ĐƠN HÀNG SAU N NGÀY ---\n";

    $days      = max(1, min(60, (int)($_GET['days'] ?? 7)));
    $compLimit = max(1, min(500, (int)($_GET['comp_limit'] ?? $_GET['limit'] ?? 200)));
    $lockName3 = 'cron_complete_orders';

    // Kiểm tra Lock tránh chạy trùng nhiều tiến trình
    $lock3 = $ithanhloc->query("SELECT GET_LOCK('$lockName3', 1) AS l")->fetch_assoc();
    if (!$lock3 || !$lock3['l']) {
        echo "Tiến trình complete_orders đang chạy ở luồng khác, bỏ qua.\n\n";
    } else {
        // Phát hiện các cột thời gian có sẵn
        $cols = [];
        if ($rc = $ithanhloc->query("SHOW COLUMNS FROM ecommerce_order")) {
            while ($c = $rc->fetch_assoc()) { $cols[$c['Field']] = true; }
        }

        // Cột mốc thời gian để đo ngày: ưu tiên delivered_at, fallback updated_at -> created_at
        $timeCol = isset($cols['delivered_at']) ? 'delivered_at'
                 : (isset($cols['updated_at']) ? 'updated_at' : 'created_at');

        // Lấy danh sách đơn đã giao đủ điều kiện
        $sql = "SELECT order_id, user_id, status, $timeCol AS ref_time
                FROM ecommerce_order
                WHERE status = 'delivered'
                  AND COALESCE($timeCol, created_at) <= (NOW() - INTERVAL $days DAY)
                ORDER BY id ASC
                LIMIT $compLimit";

        $res = $ithanhloc->query($sql);
        if (!$res || $res->num_rows === 0) {
            echo "Không có đơn hàng nào đủ điều kiện tự động hoàn thành.\n";
        } else {
            $set = "status='completed'";
            if (isset($cols['updated_at']))   { $set .= ", updated_at=NOW()"; }
            if (isset($cols['completed_at'])) { $set .= ", completed_at=COALESCE(completed_at, NOW())"; }
            $updStmt = $ithanhloc->prepare("UPDATE ecommerce_order SET $set WHERE order_id=? AND status='delivered'");

            $done = 0; $skipped = 0;
            while ($order = $res->fetch_assoc()) {
                $oid = (string)$order['order_id'];

                if (!$updStmt) { $skipped++; continue; }
                $updStmt->bind_param('s', $oid);
                $updStmt->execute();

                if ($updStmt->affected_rows < 1) { $skipped++; continue; }

                // Đồng bộ nghiệp vụ xu/điểm theo trạng thái
                if (function_exists('syncXuByOrderStatus')) {
                    try { syncXuByOrderStatus($ithanhloc, $oid, 'completed'); }
                    catch (Throwable $e) { error_log('cron_complete_orders syncXu failed: ' . $e->getMessage()); }
                }

                // Ghi vết vào timeline đơn hàng
                if (function_exists('ecommerce_order_log_insert')) {
                    ecommerce_order_log_insert(
                        $ithanhloc, $oid, 'system', 0,
                        'status_changed', 'delivered', 'completed',
                        "Tự động hoàn thành sau {$days} ngày kể từ khi giao hàng"
                    );
                }

                $done++;
            }
            $updStmt && $updStmt->close();
            echo "Hoàn tất. Đã hoàn thành: $done đơn, Bỏ qua: $skipped đơn (Điều kiện: đủ $days ngày, mốc: $timeCol).\n";
        }
        $ithanhloc->query("SELECT RELEASE_LOCK('$lockName3')");
        echo "\n";
    }
} else {
    echo "--- [3] TỰ ĐỘNG HOÀN THÀNH ĐƠN HÀNG SAU N NGÀY (TẮT) ---\n\n";
}

echo "=== HOÀN TẤT CHẠY CRON LÚC: " . date('H:i:s d/m/Y') . " ===\n";
