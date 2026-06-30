<?php
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

/**
 * favorite.php - Handle product likes/favorites
 */

// Auto-setup table if not exists (Self-healing)
$sqlSetup = "CREATE TABLE IF NOT EXISTS ecommerce_product_favorite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT DEFAULT 0,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (product_id, user_id, ip_address)
)";
$ithanhloc->query($sqlSetup);

$action = $_POST['action'] ?? 'get';
$pid = (int)($_POST['pid'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($pid <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID sản phẩm không hợp lệ']);
    exit;
}

if ($action === 'toggle') {
    app_verify_csrf(); // Bảo mật: Chỉ cho phép request có CSRF token hợp lệ

    // Rate limit: tối đa 10 lần toggle yêu thích/phút/IP (chống spam click liên tục)
    if (function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'favorite_toggle',
            10, // tối đa 10 lần
            60, // trong 60 giây
            'Bạn đang thao tác yêu thích quá nhanh. Vui lòng chờ vài giây rồi thử lại.'
        );
    }

    // Sử dụng Guest UUID thay vì IP address để tránh Sybil attack và sai lệch IP
    $guestKey = app_guest_key();
    
    // Check if already liked
    $checkSql = "SELECT id FROM ecommerce_product_favorite WHERE product_id = ? AND (user_id = ? AND user_id != 0 OR ip_address = ?)";
    $stmt = $ithanhloc->prepare($checkSql);
    $stmt->bind_param('iis', $pid, $userId, $guestKey);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        // Unlike
        $deleteSql = "DELETE FROM ecommerce_product_favorite WHERE product_id = ? AND (user_id = ? AND user_id != 0 OR ip_address = ?)";
        $stmtDel = $ithanhloc->prepare($deleteSql);
        $stmtDel->bind_param('iis', $pid, $userId, $guestKey);
        $stmtDel->execute();
        $isLiked = false;
    } else {
        // Like
        $insertSql = "INSERT IGNORE INTO ecommerce_product_favorite (product_id, user_id, ip_address) VALUES (?, ?, ?)";
        $stmtIns = $ithanhloc->prepare($insertSql);
        $stmtIns->bind_param('iis', $pid, $userId, $guestKey);
        $stmtIns->execute();
        $isLiked = true;
    }
}

// Get total likes for this product
$countSql = "SELECT COUNT(*) as total FROM ecommerce_product_favorite WHERE product_id = ?";
$stmtCount = $ithanhloc->prepare($countSql);
$stmtCount->bind_param('i', $pid);
$stmtCount->execute();
$countRes = $stmtCount->get_result()->fetch_assoc();
$totalLikes = (int)($countRes['total'] ?? 0);

// Check if current user liked it
$userLiked = false;
$guestKey = app_guest_key();
$checkUserSql = "SELECT id FROM ecommerce_product_favorite WHERE product_id = ? AND (user_id = ? AND user_id != 0 OR ip_address = ?)";
$stmtUser = $ithanhloc->prepare($checkUserSql);
$stmtUser->bind_param('iis', $pid, $userId, $guestKey);
$stmtUser->execute();
if ($stmtUser->get_result()->num_rows > 0) {
    $userLiked = true;
}

echo json_encode([
    'ok' => true,
    'liked' => $userLiked,
    'count' => $totalLikes,
    'msg' => 'Thao tác thành công'
]);
