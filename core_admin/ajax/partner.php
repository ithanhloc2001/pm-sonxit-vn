<?php
if (defined('AJAX_ONLY')) { return; }
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once __DIR__ . '/../../config.php';
$ithanhloc->set_charset('utf8mb4');
if (function_exists('ensure_site_partner_schema')) {
    ensure_site_partner_schema($ithanhloc);
}

if (!$isAdmin){
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Chỉ dành cho quản trị viên'], JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;
    while (ob_get_level()) { @ob_end_clean(); }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Server error',
        'error' => $err['message'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});






function workspaceRootPath(): string {
    return dirname(__DIR__, 2);
}

function ensurePartnerUploadDir(): array {
    $root = workspaceRootPath();
    global $uploadFolder;
    $webDir = '/' . ($uploadFolder ?? 'uploads') . '/partner';
    $fsDir = $root . str_replace('/', DIRECTORY_SEPARATOR, $webDir);
    if (!is_dir($fsDir)) {
        @mkdir($fsDir, 0777, true);
    }
    if (!is_dir($fsDir) || !is_writable($fsDir)) {
        global $uploadFolder;
        return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/partner'];
    }
    return ['ok' => true, 'fs' => $fsDir, 'web' => $webDir];
}

function saveUploadedPartnerImage(string $fileKey, bool $required): array {
    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh đối tác'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }

    $file = $_FILES[$fileKey];
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh đối tác'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Upload ảnh thất bại'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'Ảnh tối đa 5MB'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'Tệp upload không hợp lệ'];
    }

    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowExt, true)) {
        return ['ok' => false, 'msg' => 'Chỉ hỗ trợ jpg, jpeg, png, webp, gif'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    $allowMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if ($mime !== '' && !in_array($mime, $allowMime, true)) {
        return ['ok' => false, 'msg' => 'Định dạng ảnh không hợp lệ'];
    }

    $dir = ensurePartnerUploadDir();
    if (!$dir['ok']) {
        return ['ok' => false, 'msg' => $dir['msg']];
    }

    $fileName = 'partner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
    if (!@move_uploaded_file($tmp, $destFs)) {
        return ['ok' => false, 'msg' => 'Không thể lưu ảnh tải lên'];
    }

    $partnerRel = $dir['web'] . '/' . $fileName;
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file($partnerRel);
    }
    return ['ok' => true, 'has_file' => true, 'path' => $partnerRel];
}

function deletePartnerImageIfLocal(string $imageUrl): void {
    $raw = trim($imageUrl);
    global $uploadFolder;
    $prefix = '/' . ($uploadFolder ?? 'uploads') . '/partner/';
    if ($raw === '' || strpos($raw, $prefix) !== 0) {
        return;
    }
    if (function_exists('media_delete_remote')) {
        media_delete_remote(ltrim($raw, '/'));
    }
    $root = workspaceRootPath();
    $full = $root . str_replace('/', DIRECTORY_SEPARATOR, $raw);
    if (is_file($full)) {
        @unlink($full);
    }
}

$action = trim((string)($_REQUEST['action'] ?? 'list'));

// SECURITY: Enforce CSRF token for all state-changing administrative actions
if (in_array($action, ['save', 'toggle', 'sort', 'delete'], true)) {
    app_verify_csrf();
}

if ($action === 'list') {
    $rows = [];
    $res = $ithanhloc->query("SELECT * FROM site_partner ORDER BY sort_order ASC, id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $r['is_active'] = (int)($r['is_active'] ?? 0);
            $rows[] = $r;
        }
    }
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['partner_name'] ?? ''));
    if ($name === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên đối tác']);

    $intro = trim((string)($_POST['intro'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 100);
    $isActive = ((int)($_POST['is_active'] ?? 0) === 1) ? 1 : 0;

    $oldImage = '';
    if ($id > 0) {
        $stmtOld = $ithanhloc->prepare('SELECT image_url FROM site_partner WHERE id=? LIMIT 1');
        if ($stmtOld) {
            $stmtOld->bind_param('i', $id);
            $stmtOld->execute();
            $stmtOld->bind_result($oldImageDb);
            if ($stmtOld->fetch()) {
                $oldImage = (string)$oldImageDb;
            }
            $stmtOld->close();
        }
    }

    $upload = saveUploadedPartnerImage('partner_image', $id <= 0);
    if (!$upload['ok']) {
        jOut(['ok' => false, 'msg' => $upload['msg']]);
    }

    $currentImage = trim((string)($_POST['current_image'] ?? ''));
    $imageUrl = $upload['has_file'] ? (string)$upload['path'] : $currentImage;

    if ($id > 0) {
        $stmt = $ithanhloc->prepare('UPDATE site_partner SET partner_name=?, intro=?, image_url=?, sort_order=?, is_active=? WHERE id=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể chuẩn bị câu lệnh cập nhật']);
        $stmt->bind_param('sssiii', $name, $intro, $imageUrl, $sortOrder, $isActive, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $upload['has_file'] && $oldImage !== '' && $oldImage !== $imageUrl) {
            deletePartnerImageIfLocal($oldImage);
        }
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật đối tác thành công' : 'Không thể cập nhật đối tác']);
    } else {
        $stmt = $ithanhloc->prepare('INSERT INTO site_partner (partner_name, intro, image_url, sort_order, is_active) VALUES (?,?,?,?,?)');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể chuẩn bị câu lệnh thêm mới']);
        $stmt->bind_param('sssii', $name, $intro, $imageUrl, $sortOrder, $isActive);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã thêm đối tác mới thành công' : 'Không thể thêm đối tác']);
    }
}

if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thi?u ID đối tác']);

    $stmt = $ithanhloc->prepare('UPDATE site_partner SET is_active = IF(is_active=1,0,1) WHERE id=?');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể cập nhật trạng thái']);
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã đổi trạng thái hiển thị' : 'Không thể cập nhật trạng thái']);
}

if ($action === 'sort') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        jOut(['ok' => false, 'msg' => 'Danh sách ID không hợp lệ']);
    }

    $ithanhloc->begin_transaction();
    try {
        $stmt = $ithanhloc->prepare('UPDATE site_partner SET sort_order = ? WHERE id = ?');
        foreach ($ids as $index => $id) {
            $order = ($index + 1) * 10;
            $partnerId = (int)$id;
            $stmt->bind_param('ii', $order, $partnerId);
            $stmt->execute();
        }
        $stmt->close();
        $ithanhloc->commit();
        jOut(['ok' => true, 'msg' => 'Đã cập nhật thứ tự hiển thị']);
    } catch (Exception $e) {
        $ithanhloc->rollback();
        jOut(['ok' => false, 'msg' => 'Lỗi khi cập nhật thứ tự: ' . $e->getMessage()]);
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID đối tác']);

    $oldImage = '';
    $stmtOld = $ithanhloc->prepare('SELECT image_url FROM site_partner WHERE id=? LIMIT 1');
    if ($stmtOld) {
        $stmtOld->bind_param('i', $id);
        $stmtOld->execute();
        $stmtOld->bind_result($oldImageDb);
        if ($stmtOld->fetch()) {
            $oldImage = (string)$oldImageDb;
        }
        $stmtOld->close();
    }

    $stmt = $ithanhloc->prepare('DELETE FROM site_partner WHERE id=?');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể xóa đối tác']);
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && $oldImage !== '') {
        deletePartnerImageIfLocal($oldImage);
    }

    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xóa đối tác' : 'Không thể xóa đối tác']);
}

jOut(['ok' => false, 'msg' => 'Action không hợp lệ']);
