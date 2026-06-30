<?php
/**
 * Cronjob: Tự động quản lý Voucher và Đơn hàng
 * 1. Tự động hủy đơn hàng thanh toán online quá 24h mà chưa hoàn tất.
 * 2. Tự động tặng Voucher đền bù (20k) cho khách hàng nếu đơn hàng giao chậm hơn dự kiến 1 ngày.
 */

require_once __DIR__ . '/../../../config.php';

// Bắt buộc hiển thị văn bản
header('Refresh: 300');
header('Content-Type: text/plain; charset=utf-8');

// --- CẤU HÌNH THAM SỐ ---
$voucherValue = 20000;      // Giá trị voucher đền bù
$voucherValidDays = 7;      // Thời hạn sử dụng voucher (ngày)
$graceSeconds = 86400;      // Thời gian ân hạn (1 ngày) trước khi coi là giao chậm

// --- 1. KHỞI TẠO BẢNG (Nếu chưa có) ---
$ithanhloc->query("CREATE TABLE IF NOT EXISTS `ecommerce_voucher` (
    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` varchar(64) NOT NULL UNIQUE,
    `type` varchar(20) NOT NULL DEFAULT 'fixed',
    `value` decimal(15,2) NOT NULL DEFAULT 0,
    `min_subtotal` decimal(15,2) DEFAULT 0,
    `discount_target` varchar(20) NOT NULL DEFAULT 'order',
    `apply_scope` varchar(20) NOT NULL DEFAULT 'all',
    `start_at` datetime NULL,
    `end_at` datetime NULL,
    `max_uses` int(11) DEFAULT 1,
    `used_count` int(11) NOT NULL DEFAULT 0,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$ithanhloc->query("CREATE TABLE IF NOT EXISTS `ecommerce_order_refund` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT(11) NOT NULL,
    `coupon_code` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


// --- 2. TỰ ĐỘNG HỦY ĐƠN HÀNG THANH TOÁN ONLINE QUÁ 24H ---
// Chỉ áp dụng cho đơn chưa thanh toán (pending/expired) và không phải COD
$sqlCancel = "UPDATE ecommerce_order 
              SET status = 'canceled', 
                  payment_status = 'expired', 
                  updated_at = NOW(), 
                  canceled_at = COALESCE(canceled_at, NOW())
              WHERE status = 'pending' 
                AND created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND LOWER(COALESCE(payment_method,'')) <> 'cod'
                AND (LOWER(COALESCE(payment_status,'')) IN ('pending', 'failed') OR payment_status IS NULL OR payment_status = '')";

$ithanhloc->query($sqlCancel);
$autoCanceled = $ithanhloc->affected_rows;


// --- 3. TỰ ĐỘNG TẶNG VOUCHER ĐỀN BÙ GIAO CHẬM ---
// Lấy các đơn đang giao, đã quá ngày dự kiến + ân hạn, và chưa được tặng voucher trước đó
$sqlCheckLate = "SELECT o.order_id, o.user_id, o.shipping_eta 
                 FROM ecommerce_order o
                 LEFT JOIN ecommerce_order_refund r ON o.order_id = r.order_id
                 WHERE o.status = 'shipping' 
                   AND o.shipping_eta IS NOT NULL AND o.shipping_eta <> ''
                   AND r.id IS NULL";

$res = $ithanhloc->query($sqlCheckLate);
$awarded = 0;
$now = time();

while ($row = $res->fetch_assoc()) {
    $etaTs = strtotime($row['shipping_eta']);
    if (!$etaTs || $now <= ($etaTs + $graceSeconds)) continue;

    $orderId = $row['order_id'];
    $userId  = (int)$row['user_id'];
    
    // Tạo mã voucher độc nhất: LATE20K-[ID đơn hàng]-[Ngẫu nhiên]
    $suffix = strtoupper(bin2hex(random_bytes(2)));
    $code   = 'LATE20K-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $orderId), 0, 8)) . '-' . $suffix;
    
    $startAt = date('Y-m-d H:i:s');
    $endAt   = date('Y-m-d H:i:s', $now + ($voucherValidDays * 86400));

    $ithanhloc->begin_transaction();
    try {
        // 1. Tạo Voucher
        $stmtV = $ithanhloc->prepare("INSERT INTO ecommerce_voucher (code, value, start_at, end_at, max_uses) VALUES (?, ?, ?, ?, 1)");
        $stmtV->bind_param('sdss', $code, $voucherValue, $startAt, $endAt);
        $stmtV->execute();

        // 2. Đánh dấu đơn hàng đã được đền bù
        $stmtR = $ithanhloc->prepare("INSERT INTO ecommerce_order_refund (order_id, user_id, coupon_code) VALUES (?, ?, ?)");
        $stmtR->bind_param('sis', $orderId, $userId, $code);
        $stmtR->execute();

        $ithanhloc->commit();
        $awarded++;
    } catch (Exception $e) {
        $ithanhloc->rollback();
    }
}

echo "--- CRON VOUCHER & ORDERS ---\n";
echo "1. Đã hủy đơn quá hạn (24h): $autoCanceled\n";
echo "2. Đã tặng voucher giao chậm: $awarded\n";
echo "Hoàn tất: " . date('Y-m-d H:i:s') . "\n";
