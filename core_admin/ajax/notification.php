<?php
require_once __DIR__ . '/../../config.php';
function normalize_send_at(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    $raw = str_replace('T', ' ', $raw);
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $raw)) {
        return null;
    }
    if (strlen($raw) === 16) {
        $raw .= ':00';
    }
    return $raw;
}

function extract_notify_meta(string $body, string $type): array {
    $meta = [
        'module' => ($type === 'order') ? 'order' : 'system',
        'event' => 'manual_admin_notification',
        'category' => 'manual',
    ];
    $decoded = json_decode(trim($body), true);
    if (is_array($decoded) && (($decoded['schema'] ?? '') === 'notx_v2')) {
        foreach (['status', 'amount', 'order_id', 'module', 'event', 'category'] as $key) {
            if (array_key_exists($key, $decoded) && $decoded[$key] !== '') {
                $meta[$key] = $decoded[$key];
            }
        }
    }
    return $meta;
}

function notify_store_data_image(string $value): string {
    $value = trim($value);
    if ($value === '' || stripos($value, 'data:image/') !== 0) {
        return $value;
    }

    if (!preg_match('#^data:(image\/[^;]+);base64,(.+)$#i', $value, $m)) {
        return $value;
    }

    $mime = strtolower(trim($m[1]));
    $base64 = $m[2];
    $binary = base64_decode($base64, true);
    if ($binary === false || $binary === '') {
        return $value;
    }

    $rootDir = dirname(__DIR__, 2); // .../htdocs
    global $uploadFolder;
    $uploadDir = $rootDir . '/' . ($uploadFolder ?? 'uploads');
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    $unique = (string)round(microtime(true) * 1000) . '_' . bin2hex(random_bytes(3));
    $targetWebp = $uploadDir . '/p_' . $unique . '.webp';

    // Cố gắng convert sang WebP nếu có GD + imagewebp
    if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
        $src = @imagecreatefromstring($binary);
        if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);
            if ($w > 0 && $h > 0) {
                $dst = imagecreatetruecolor($w, $h);
                if ($dst !== false) {
                    // Giữ alpha nếu có
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                    @imagewebp($dst, $targetWebp, 80);
                    imagedestroy($dst);
                    imagedestroy($src);
                    if (is_file($targetWebp)) {
                        return '/' . ($uploadFolder ?? 'uploads') . '/' . basename($targetWebp);
                    }
                } else {
                    imagedestroy($src);
                }
            } else {
                imagedestroy($src);
            }
        }
    }

    // Fallback: lưu theo định dạng gốc nếu không convert được WebP
    $ext = '.img';
    if (strpos($mime, 'png') !== false) {
        $ext = '.png';
    } elseif (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
        $ext = '.jpg';
    } elseif (strpos($mime, 'gif') !== false) {
        $ext = '.gif';
    } elseif (strpos($mime, 'webp') !== false) {
        $ext = '.webp';
    }

    $target = $uploadDir . '/p_' . $unique . $ext;
    if (@file_put_contents($target, $binary) !== false) {
        return '/' . ($uploadFolder ?? 'uploads') . '/' . basename($target);
    }

    // Nếu mọi thứ đều thất bại, trả lại giá trị gốc để không làm hỏng dữ liệu
    return $value;
}

/**
 * Chuẩn hoá body JSON notx_v2: mọi thumb_image/main_banner/banners dạng data:image/... sẽ được lưu thành file trong uploads.
 */
function normalize_notify_body_images(string $body): string {
    $txt = trim($body);
    if ($txt === '') return $body;
    $decoded = json_decode($txt, true);
    if (!is_array($decoded) || (($decoded['schema'] ?? '') !== 'notx_v2')) {
        return $body;
    }

    foreach (['thumb_image', 'main_banner'] as $field) {
        if (!empty($decoded[$field]) && is_string($decoded[$field])) {
            $decoded[$field] = notify_store_data_image($decoded[$field]);
        }
    }

    if (!empty($decoded['banners']) && is_array($decoded['banners'])) {
        foreach ($decoded['banners'] as $idx => $val) {
            if (is_string($val) && stripos($val, 'data:image/') === 0) {
                $decoded['banners'][$idx] = notify_store_data_image($val);
            }
        }
    }

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function promo_notify_insert(mysqli $ithanhloc, int $userId, string $title, string $body, string $type, string $link, int $createdBy, ?string $sendAt): bool {
    $metaJson = json_encode(extract_notify_meta($body, $type), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $createdAt = function_exists('nowStr') ? nowStr() : date('Y-m-d H:i:s');
    $isRead = 0;
    $isActive = 1;

    $stmt = $ithanhloc->prepare('INSERT INTO user_notification (user_id, title, body, type, link, meta_json, is_read, is_active, created_by, created_at, send_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return false;
    $stmt->bind_param('isssssiiiss', $userId, $title, $body, $type, $link, $metaJson, $isRead, $isActive, $createdBy, $createdAt, $sendAt);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

try {
    if (!$isAdmin) {
        jOut(['ok' => false, 'msg' => 'Chỉ dành cho quản trị viên']);
    }
    $action = strtolower(trim((string)($_POST['action'] ?? 'save')));
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $type = trim((string)($_POST['type'] ?? 'system'));
    $link = trim((string)($_POST['link'] ?? ''));
    $target = trim((string)($_POST['target'] ?? 'all'));
    $role = trim((string)($_POST['role'] ?? 'user'));
    $userId = (int)($_POST['user_id'] ?? 0);
    $createdBy = (int)($_SESSION['user_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    $sendAtRaw = trim((string)($_POST['send_at'] ?? ''));
    $sendAt = normalize_send_at($sendAtRaw);

    // SECURITY: Enforce CSRF token for all state-changing administrative actions
    if (in_array($action, ['save', 'delete', 'toggle', 'sort'], true)) {
        app_verify_csrf();
    }

    // Chuẩn hoá body: nếu là payload notx_v2 có chứa ảnh base64, lưu file vào /uploads và thay bằng đường dẫn
    if ($body !== '') {
        $body = normalize_notify_body_images($body);
    }

    if ($action === 'delete') {
        if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID']);
        $stmt = $ithanhloc->prepare('DELETE FROM user_notification WHERE id=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể xóa']);
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xóa thông báo' : 'Không thể xóa', 'id' => $id]);
    }

    if ($action === 'toggle') {
        if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID']);
        $state = (int)($_POST['is_active'] ?? 1);
        $stmt = $ithanhloc->prepare('UPDATE user_notification SET is_active=?, updated_at=NOW() WHERE id=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể cập nhật']);
        $stmt->bind_param('ii', $state, $id);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật trạng thái' : 'Không thể cập nhật', 'id' => $id, 'is_active' => $state]);
    }

    if ($action === 'sort') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) jOut(['ok' => false, 'msg' => 'Thiếu danh sách ID']);
        
        // Ensure column exists
        $ithanhloc->query("ALTER TABLE user_notification ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0");
        
        $ithanhloc->begin_transaction();
        try {
            $stmt = $ithanhloc->prepare('UPDATE user_notification SET sort_order = ? WHERE id = ?');
            foreach ($ids as $index => $id) {
                $order = $index + 1;
                $valId = (int)$id;
                $stmt->bind_param('ii', $order, $valId);
                $stmt->execute();
            }
            $stmt->close();
            $ithanhloc->commit();
            jOut(['ok' => true, 'msg' => 'Đã cập nhật thứ tự']);
        } catch (Exception $e) {
            $ithanhloc->rollback();
            jOut(['ok' => false, 'msg' => 'Lỗi: ' . $e->getMessage()]);
        }
    }

    if ($action !== 'save') {
        jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
    }

    if ($title === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập tiêu đề']);
    }

    $type = $type !== '' ? substr($type, 0, 40) : 'system';
    $link = $link !== '' ? substr($link, 0, 255) : '';

    if ($id > 0) {
        $stmt = $ithanhloc->prepare('UPDATE user_notification SET title=?, body=?, type=?, link=?, send_at=?, updated_at=NOW() WHERE id=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể cập nhật']);
        $stmt->bind_param('sssssi', $title, $body, $type, $link, $sendAt, $id);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật thông báo' : 'Không thể cập nhật', 'id' => $id]);
    }

    if ($target === 'all') {
        $ok = promo_notify_insert($ithanhloc, 0, $title, $body, $type, $link, $createdBy, $sendAt);
        $newId = $ok ? (int)$ithanhloc->insert_id : 0;
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã gửi thông báo cho tất cả' : 'Không gửi được thông báo', 'id' => $newId]);
    }

    if ($target === 'user') {
        if ($userId <= 0) jOut(['ok' => false, 'msg' => 'Thiếu User ID']);
        $ok = promo_notify_insert($ithanhloc, $userId, $title, $body, $type, $link, $createdBy, $sendAt);
        $newId = $ok ? (int)$ithanhloc->insert_id : 0;
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã gửi thông báo cho user' : 'Không gửi được thông báo', 'id' => $newId]);
    }

    if ($target === 'role') {
        if ($role === '') jOut(['ok' => false, 'msg' => 'Thiếu vai trò']);
        $stmt = $ithanhloc->prepare('SELECT id FROM users WHERE role=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể lấy danh sách user']);
        $stmt->bind_param('s', $role);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        $sent = 0;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $uid = (int)($row['id'] ?? 0);
                if ($uid <= 0) continue;
                if (promo_notify_insert($ithanhloc, $uid, $title, $body, $type, $link, $createdBy, $sendAt)) {
                    $sent++;
                }
            }
        }

        jOut(['ok' => true, 'msg' => 'Đã gửi cho ' . $sent . ' user']);
    }

    jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Dữ liệu ảnh quá lớn') !== false) {
        jOut(['ok' => false, 'msg' => 'Dữ liệu ảnh quá lớn. Vui lòng giảm dung lượng ảnh trước khi gửi.']);
    }
    jOut(['ok' => false, 'msg' => 'Lỗi máy chủ: ' . $msg]);
}
