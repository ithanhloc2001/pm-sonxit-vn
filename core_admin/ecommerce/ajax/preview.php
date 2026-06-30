<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
if (empty($isAdmin)) {
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);
}
?>
<?php
function rs_jOut($data, int $status = 200): void {
    while (ob_get_level()) {
        @ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Bảng review ngắn gọn cho sản phẩm
// Xác định tên bảng, ưu tiên dùng helper first_existing_table nếu có
$table = 'ecommerce_review_short';
if (function_exists('first_existing_table')) {
    try {
        $t = first_existing_table($ithanhloc, ['ecommerce_review_short']);
        if (is_string($t) && $t !== '') {
            $table = $t;
        }
    } catch (Throwable $e) {
        // Giữ nguyên fallback mặc định
    }
}

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list')));

if ($action === 'list') {
    $items = [];
    $sql = "SELECT rs.*, p.product_name
            FROM `{$table}` rs
            LEFT JOIN ecommerce_product p ON p.id = rs.product_id
            ORDER BY rs.sort_order ASC, rs.id DESC";
    $res = $ithanhloc->query($sql);
    if ($res) {
        $items = $res->fetch_all(MYSQLI_ASSOC);
    }
    rs_jOut(['ok' => true, 'items' => $items]);
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $creator = trim((string)($_POST['creator_name'] ?? ''));
    $youtubeUrl = trim((string)($_POST['youtube_url'] ?? ''));
    $videoUrl = trim((string)($_POST['video_url'] ?? ''));
    $thumbUrl = trim((string)($_POST['thumb_url'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

    if ($productId <= 0) {
        rs_jOut(['ok' => false, 'msg' => 'Vui lòng nhập ID sản phẩm']);
    }

    $now = date('Y-m-d H:i:s');

    if ($id > 0) {
        $stmt = $ithanhloc->prepare("UPDATE `{$table}`
            SET product_id=?, title=?, creator_name=?, youtube_url=?, video_url=?, thumb_url=?, sort_order=?, is_active=?, updated_at=?
            WHERE id=?");
        if (!$stmt) {
            rs_jOut(['ok' => false, 'msg' => 'Không thể lưu dữ liệu']);
        }
        $stmt->bind_param('isssssiisi', $productId, $title, $creator, $youtubeUrl, $videoUrl, $thumbUrl, $sortOrder, $isActive, $now, $id);
        $ok = $stmt->execute();
        $stmt->close();
        rs_jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật video review' : 'Cập nhật thất bại']);
    }

    $stmt = $ithanhloc->prepare("INSERT INTO `{$table}`
        (product_id, title, creator_name, youtube_url, video_url, thumb_url, sort_order, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        rs_jOut(['ok' => false, 'msg' => 'Không thể thêm mới']);
    }
    $stmt->bind_param('isssssiiss', $productId, $title, $creator, $youtubeUrl, $videoUrl, $thumbUrl, $sortOrder, $isActive, $now, $now);
    $ok = $stmt->execute();
    $stmt->close();
    rs_jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã thêm video review' : 'Thêm mới thất bại']);
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) rs_jOut(['ok' => false, 'msg' => 'Thiếu ID']);
    $stmt = $ithanhloc->prepare("DELETE FROM `{$table}` WHERE id=?");
    if (!$stmt) rs_jOut(['ok' => false, 'msg' => 'Không thể xóa']);
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    rs_jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xóa video review' : 'Xóa thất bại']);
}

if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) rs_jOut(['ok' => false, 'msg' => 'Thiếu ID']);
    $stmt = $ithanhloc->prepare("UPDATE `{$table}` SET is_active = 1 - is_active, updated_at = ? WHERE id=?");
    if (!$stmt) rs_jOut(['ok' => false, 'msg' => 'Không thể cập nhật trạng thái']);
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param('si', $now, $id);
    $ok = $stmt->execute();
    $stmt->close();
    rs_jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật trạng thái' : 'Cập nhật thất bại']);
}

if ($action === 'product_lookup') {
    $pid = (int)($_GET['product_id'] ?? 0);
    if ($pid <= 0) rs_jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
    $stmt = $ithanhloc->prepare('SELECT id, product_name, image_url FROM ecommerce_product WHERE id=? LIMIT 1');
    if (!$stmt) rs_jOut(['ok' => false, 'msg' => 'Không thể đọc sản phẩm']);
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) rs_jOut(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    rs_jOut(['ok' => true, 'item' => $row]);
}

if ($action === 'upload_video' && isset($_FILES['file'])) {
    $tmp = $_FILES['file']['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        rs_jOut(['ok' => false, 'msg' => 'File không hợp lệ']);
    }
    $orig = (string)($_FILES['file']['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $size = (int)($_FILES['file']['size'] ?? 0);
    if ($ext !== 'mp4') {
        rs_jOut(['ok' => false, 'msg' => 'Video chỉ hỗ trợ MP4']);
    }
    if ($size > 30 * 1024 * 1024) {
        rs_jOut(['ok' => false, 'msg' => 'Video tối đa 30MB']);
    }

    global $uploadFolder;
    $uploadDir = __DIR__ . '/../../../' . ($uploadFolder ?? 'uploads') . '/review_short';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $safe = 'review_' . time() . '_' . mt_rand(1000, 9999) . '.mp4';
    $target = $uploadDir . '/' . $safe;
    if (!move_uploaded_file($tmp, $target)) {
        rs_jOut(['ok' => false, 'msg' => 'Không lưu được file']);
    }
    $url = ($uploadFolder ?? 'uploads') . '/review_short/' . $safe;
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file($url);
    }
    rs_jOut(['ok' => true, 'url' => $url]);
}

rs_jOut(['ok' => false, 'msg' => 'Không hỗ trợ hành động'], 400);
