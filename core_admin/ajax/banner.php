<?php
require_once __DIR__ . '/../../config.php';
if (!$isAdmin){
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Chỉ dành cho quản trị viên'], JSON_UNESCAPED_UNICODE);
    exit;
}
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'] ?? 0, $fatalTypes, true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Lỗi hệ thống máy chủ'], JSON_UNESCAPED_UNICODE);
});
function normalizePageKey(string $raw): string {
    $page = strtolower(trim($raw));
    return in_array($page, ['home_user', 'home_guest'], true) ? $page : 'home_user';
}
function getUploadRootPath(): string {
    return dirname(__DIR__, 2);
}
// Các hàm liên quan đến xử lý upload ảnh banner, đảm bảo an toàn và tối ưu dung lượng
function ensureBannerUploadDir(): array {
    $root = getUploadRootPath();
    global $uploadFolder;
    // Lưu banner vào thư mục uploads/ để đồng bộ với các upload khác
    $webDir = '/' . ($uploadFolder ?? 'uploads');
    $fsDir = $root . str_replace('/', DIRECTORY_SEPARATOR, $webDir);
    if (!is_dir($fsDir)) {
        @mkdir($fsDir, 0777, true);
    }
    if (!is_dir($fsDir)) {
        global $uploadFolder;
        return ['ok' => false, 'msg' => 'Không thể tạo thư mục ' . ($uploadFolder ?? 'uploads') . '/banners'];
    }
    // Nếu là Windows, bỏ qua is_writable, thử ghi file test
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $testFile = $fsDir . DIRECTORY_SEPARATOR . '.__writetest';
        $canWrite = @file_put_contents($testFile, 'test') !== false;
        if ($canWrite) {
            @unlink($testFile);
        }
        if (!$canWrite) {
            global $uploadFolder;
            return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/banners (test file)'];
        }
    } else {
        if (!is_writable($fsDir)) {
            global $uploadFolder;
            return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/banners'];
        }
    }
    return ['ok' => true, 'fs' => $fsDir, 'web' => $webDir];
}

/**
 * - Ưu tiên dùng GD (imagewebp), nếu không được thì thử dùng Imagick.
 * - Hỗ trợ nguồn JPEG/PNG/GIF; nếu không hỗ trợ thì giữ nguyên file gốc.
 */
function convertImageToWebpIfPossible(string $srcFsPath, string $webDir): string {
    if (!is_file($srcFsPath)) {
        return $webDir . '/' . basename($srcFsPath);
    }

    $ext = strtolower(pathinfo($srcFsPath, PATHINFO_EXTENSION));

    // Nếu là video hoặc đã là webp/gif thì không cần xử lý
    if (in_array($ext, ['webp', 'gif', 'mp4', 'webm', 'mov', 'ogg'], true)) {
        return $webDir . '/' . basename($srcFsPath);
    }

    $dirFs = dirname($srcFsPath);
    $baseName = pathinfo($srcFsPath, PATHINFO_FILENAME);
    $webpName = $baseName . '.webp';
    $destFsPath = $dirFs . DIRECTORY_SEPARATOR . $webpName;

    // 1) Thử convert bằng GD nếu có imagewebp
    if (function_exists('imagewebp')) {
        $image = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $image = @imagecreatefromjpeg($srcFsPath);
                }
                break;
            case 'png':
                if (function_exists('imagecreatefrompng')) {
                    $image = @imagecreatefrompng($srcFsPath);
                    if ($image && function_exists('imagealphablending') && function_exists('imagesavealpha')) {
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                    }
                }
                break;
        }

        if ($image) {
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($image);
            }
            $ok = @imagewebp($image, $destFsPath, 80);
            imagedestroy($image);
            if ($ok && is_file($destFsPath)) {
                @unlink($srcFsPath);
                return $webDir . '/' . $webpName;
            }
        }
    }

    // 2) Nếu GD không hỗ trợ hoặc thất bại, thử dùng Imagick nếu có
    if (class_exists('Imagick')) {
        try {
            $img = new Imagick($srcFsPath);
            // Một số bản Imagick dùng setImageFormat, một số dùng setformat
            if (method_exists($img, 'setImageFormat')) {
                $img->setImageFormat('webp');
            }
            $ok = $img->writeImage($destFsPath);
            $img->clear();
            $img->destroy();
            if ($ok && is_file($destFsPath)) {
                @unlink($srcFsPath);
                return $webDir . '/' . $webpName;
            }
        } catch (Throwable $e) {
            // Bỏ qua lỗi, fallback bên dưới
        }
    }

    // 3) Nếu mọi cách đều thất bại, giữ nguyên file gốc
    return $webDir . '/' . basename($srcFsPath);
}

function saveUploadedBannerImage(string $fileKey, bool $required): array {
    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh tải lên'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }

    $file = $_FILES[$fileKey];
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh tải lên'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Upload ảnh thất bại'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 20 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'Ảnh tối đa 20MB'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'Tệp upload không hợp lệ'];
    }

    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    
    $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if ($fileKey === 'carousel_image') {
        $allowExt = array_merge($allowExt, ['mp4', 'webm', 'mov', 'ogg']);
    }
    
    if (!in_array($ext, $allowExt, true)) {
        if ($fileKey === 'carousel_image') {
            return ['ok' => false, 'msg' => 'Chỉ hỗ trợ ảnh (jpg, png, webp, gif) hoặc video (mp4, webm, mov, ogg)'];
        }
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
    if ($fileKey === 'carousel_image') {
        $allowMime = array_merge($allowMime, ['video/mp4', 'video/webm', 'video/quicktime', 'video/ogg']);
    }
    
    if ($mime !== '' && !in_array($mime, $allowMime, true)) {
        if ($fileKey === 'carousel_image') {
            return ['ok' => false, 'msg' => 'Định dạng ảnh hoặc video không hợp lệ'];
        }
        return ['ok' => false, 'msg' => 'Định dạng ảnh không hợp lệ'];
    }

    $dir = ensureBannerUploadDir();
    if (!$dir['ok']) {
        return ['ok' => false, 'msg' => $dir['msg']];
    }

    $fileName = 'banner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
    if (!@move_uploaded_file($tmp, $destFs)) {
        return ['ok' => false, 'msg' => 'Không thể lưu ảnh tải lên'];
    }
    // Sau khi upload thành công, cố gắng chuyển sang WebP để tối ưu
    $finalWebPath = convertImageToWebpIfPossible($destFs, $dir['web']);

    // Đẩy file đã hoàn thiện lên media VPS (no-op nếu remote tắt).
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file($finalWebPath);
    }

    return ['ok' => true, 'has_file' => true, 'path' => $finalWebPath];
}

function deleteBannerImageIfLocal(string $imageUrl): void {
    global $uploadFolder;
    $raw = trim($imageUrl);
    $folder = $uploadFolder ?? 'uploads';
    // Chấp nhận cả 'uploads/...' lẫn '/uploads/...'
    $relCheck = ltrim($raw, '/');
    if ($relCheck === '' || strpos($relCheck, $folder . '/') !== 0) {
        return;
    }
    // Xoá trên VPS nếu remote bật (no-op nếu tắt).
    if (function_exists('media_delete_remote')) {
        media_delete_remote($relCheck);
    }
    $root = getUploadRootPath();
    $full = $root . str_replace('/', DIRECTORY_SEPARATOR, $raw);
    if (is_file($full)) {
        @unlink($full);
    }
}

$action = trim((string)($_REQUEST['action'] ?? 'list'));
$pageKey = normalizePageKey((string)($_REQUEST['page_key'] ?? 'home_user'));

// SECURITY: Enforce CSRF token for all state-changing administrative actions
if (in_array($action, ['save_ad', 'save_carousel', 'reorder_carousel', 'delete_carousel'], true)) {
    app_verify_csrf();
}

if ($action === 'list') {
    // Lấy banner quảng cáo 2 bên + banner popup (modal ảnh)
    $ads = ['ad_left' => null, 'ad_right' => null, 'popup' => null];
    $stmt = $ithanhloc->prepare('SELECT id, slot_key, image_url, link_url, is_active, sort_order FROM site_banner WHERE page_key=? AND slot_key IN (\'ad_left\',\'ad_right\',\'popup\') ORDER BY id DESC');
    if ($stmt) {
        $stmt->bind_param('s', $pageKey);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $row) {
            $slot = (string)($row['slot_key'] ?? '');
            if ($slot === '' || !array_key_exists($slot, $ads)) {
                continue;
            }
            // Với mỗi slot, chỉ lấy bản ghi mới nhất
            if ($ads[$slot] === null) {
                $ads[$slot] = $row;
            }
        }
    }

    $carousel = [];
    $stmt = $ithanhloc->prepare('SELECT id, image_url, link_url, title, content, is_active, sort_order FROM site_banner WHERE page_key=? AND slot_key=\'carousel\' ORDER BY sort_order ASC, id ASC');
    if ($stmt) {
        $stmt->bind_param('s', $pageKey);
        $stmt->execute();
        $carousel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }

    jOut(['ok' => true, 'page_key' => $pageKey, 'ads' => $ads, 'carousel' => $carousel]);
}

if ($action === 'save_ad') {
    $slot = trim((string)($_POST['slot_key'] ?? ''));
    // Hỗ trợ 3 slot: ad_left, ad_right, popup (modal ảnh)
    if (!in_array($slot, ['ad_left', 'ad_right', 'popup'], true)) {
        jOut(['ok' => false, 'msg' => 'Slot quảng cáo không hợp lệ']);
    }

    $oldImage = '';
    $stmtOld = $ithanhloc->prepare('SELECT image_url FROM site_banner WHERE page_key=? AND slot_key=? ORDER BY id DESC LIMIT 1');
    if ($stmtOld) {
        $stmtOld->bind_param('ss', $pageKey, $slot);
        $stmtOld->execute();
        $stmtOld->bind_result($oldImageDb);
        if ($stmtOld->fetch()) {
            $oldImage = (string)$oldImageDb;
        }
        $stmtOld->close();
    }

    $upload = saveUploadedBannerImage('ad_image', false);
    if (!$upload['ok']) {
        jOut(['ok' => false, 'msg' => $upload['msg']]);
    }

    $currentImage = trim((string)($_POST['current_image'] ?? ''));
    $imageUrl = $upload['has_file'] ? (string)$upload['path'] : $currentImage;
    $isActive = (int)($_POST['is_active'] ?? 1) ? 1 : 0;
    $linkUrl = trim((string)($_POST['link_url'] ?? ''));

    $stmtDel = $ithanhloc->prepare('DELETE FROM site_banner WHERE page_key=? AND slot_key=?');
    if ($stmtDel) {
        $stmtDel->bind_param('ss', $pageKey, $slot);
        $stmtDel->execute();
        $stmtDel->close();
    }

    if ($imageUrl !== '') {
        $sort = 1;
        $stmtIns = $ithanhloc->prepare('INSERT INTO site_banner (page_key, slot_key, image_url, link_url, is_active, sort_order) VALUES (?,?,?,?,?,?)');
        if (!$stmtIns) {
            jOut(['ok' => false, 'msg' => 'Không thể lưu banner quảng cáo']);
        }
        $stmtIns->bind_param('ssssii', $pageKey, $slot, $imageUrl, $linkUrl, $isActive, $sort);
        $ok = $stmtIns->execute();
        $stmtIns->close();
        if ($ok && $upload['has_file'] && $oldImage !== '' && $oldImage !== $imageUrl) {
            deleteBannerImageIfLocal($oldImage);
        }
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã lưu banner quảng cáo' : 'Không thể lưu banner quảng cáo']);
    }

    if ($oldImage !== '') {
        deleteBannerImageIfLocal($oldImage);
    }

    jOut(['ok' => true, 'msg' => 'Đã xoá banner quảng cáo']);
}

if ($action === 'save_carousel') {
    $id = (int)($_POST['id'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 1) ? 1 : 0;
    $sortOrder = max(1, (int)($_POST['sort_order'] ?? 1));
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $linkUrl = trim((string)($_POST['link_url'] ?? ''));

    $upload = saveUploadedBannerImage('carousel_image', $id <= 0);
    if (!$upload['ok']) {
        jOut(['ok' => false, 'msg' => $upload['msg']]);
    }

    $currentImage = trim((string)($_POST['current_image'] ?? ''));
    $imageUrl = $upload['has_file'] ? (string)$upload['path'] : $currentImage;

    if ($imageUrl === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng chọn ảnh carousel']);
    }

    if ($id > 0) {
        $oldImage = '';
        $stmtOld = $ithanhloc->prepare('SELECT image_url FROM site_banner WHERE id=? AND page_key=? AND slot_key=\'carousel\' LIMIT 1');
        if ($stmtOld) {
            $stmtOld->bind_param('is', $id, $pageKey);
            $stmtOld->execute();
            $stmtOld->bind_result($oldImageDb);
            if ($stmtOld->fetch()) {
                $oldImage = (string)$oldImageDb;
            }
            $stmtOld->close();
        }

        $stmt = $ithanhloc->prepare('UPDATE site_banner SET image_url=?, link_url=?, title=?, content=?, is_active=?, sort_order=? WHERE id=? AND page_key=? AND slot_key=\'carousel\'');
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể cập nhật carousel']);
        $stmt->bind_param('ssssiiis', $imageUrl, $linkUrl, $title, $content, $isActive, $sortOrder, $id, $pageKey);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $upload['has_file'] && $oldImage !== '' && $oldImage !== $imageUrl) {
            deleteBannerImageIfLocal($oldImage);
        }
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật carousel' : 'Không thể cập nhật carousel']);
    }

    $slot = 'carousel';
    $stmt = $ithanhloc->prepare('INSERT INTO site_banner (page_key, slot_key, image_url, link_url, title, content, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?)');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể thêm carousel']);
    $stmt->bind_param('ssssssii', $pageKey, $slot, $imageUrl, $linkUrl, $title, $content, $isActive, $sortOrder);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã thêm carousel' : 'Không thể thêm carousel']);
}

if ($action === 'reorder_carousel') {
    $rawIds = $_POST['ids'] ?? '[]';
    if (is_string($rawIds)) {
        $decoded = json_decode($rawIds, true);
        $ids = is_array($decoded) ? $decoded : [];
    } elseif (is_array($rawIds)) {
        $ids = $rawIds;
    } else {
        $ids = [];
    }

    $cleanIds = [];
    foreach ($ids as $item) {
        $id = (int)$item;
        if ($id > 0) $cleanIds[] = $id;
    }
    if (!$cleanIds) {
        jOut(['ok' => false, 'msg' => 'Không có dữ liệu sắp xếp']);
    }

    $stmt = $ithanhloc->prepare("UPDATE site_banner SET sort_order=? WHERE id=? AND page_key=? AND slot_key='carousel'");
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể cập nhật thứ tự']);
    }

    $sort = 1;
    foreach ($cleanIds as $id) {
        $stmt->bind_param('iis', $sort, $id, $pageKey);
        $stmt->execute();
        $sort++;
    }
    $stmt->close();
    jOut(['ok' => true, 'msg' => 'Đã cập nhật thứ tự carousel']);
}

if ($action === 'delete_carousel') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID carousel']);

    $oldImage = '';
    $stmtOld = $ithanhloc->prepare('SELECT image_url FROM site_banner WHERE id=? AND page_key=? AND slot_key=\'carousel\' LIMIT 1');
    if ($stmtOld) {
        $stmtOld->bind_param('is', $id, $pageKey);
        $stmtOld->execute();
        $stmtOld->bind_result($oldImageDb);
        if ($stmtOld->fetch()) {
            $oldImage = (string)$oldImageDb;
        }
        $stmtOld->close();
    }

    $stmt = $ithanhloc->prepare('DELETE FROM site_banner WHERE id=? AND page_key=? AND slot_key=\'carousel\'');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể xoá carousel']);
    $stmt->bind_param('is', $id, $pageKey);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $oldImage !== '') {
        deleteBannerImageIfLocal($oldImage);
    }
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xoá carousel' : 'Không thể xoá carousel']);
}

jOut(['ok' => false, 'msg' => 'Action không hợp lệ']);

