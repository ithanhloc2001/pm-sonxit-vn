<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
// Kiểm tra action hợp lệ
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));
if ($action !== 'suggest_today') {
    RespondJSON(['ok' => false, 'msg' => 'Lỗi không hợp lệ'], 400);
}

// Tham số phân trang
$offset = max(0, intval($_GET['offset'] ?? 0));
$limit = intval($_GET['limit'] ?? 10);
if ($limit <= 0) $limit = 10;
if ($limit > 24) $limit = 24;

// Gọi hàm tập trung từ functions.php để lấy dữ liệu
$items = GetProducts($ithanhloc, [
    'offset' => $offset, 
    'limit' => $limit
]);

$count = count($items);
$nextOffset = $offset + $count;
$hasMore = ($count === $limit);

// Trả về JSON chuẩn qua hàm RespondJSON
RespondJSON([
    'ok' => true,
    'items' => $items,
    'next_offset' => $nextOffset,
    'has_more' => $hasMore,
]);
