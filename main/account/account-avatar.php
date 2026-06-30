<?php
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    jOut(['ok' => false, 'msg' => 'Phiên đăng nhập đã hết hạn.']);
}

$userId = (int)$_SESSION['user_id'];

$userData = ecommerce_user_load($ithanhloc, $userId);
if (!$userData) {
    jOut(['ok' => false, 'msg' => 'Không tìm thấy tài khoản.']);
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    jOut(['ok' => false, 'msg' => 'Vui lòng chọn ảnh đại diện.']);
}

if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    jOut(['ok' => false, 'msg' => 'Tải lên thất bại, thử lại sau.']);
}

$file = $_FILES['avatar'];
if ($file['size'] > 2 * 1024 * 1024) {
    jOut(['ok' => false, 'msg' => 'Ảnh tối đa 2MB.']);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
if ($finfo) {
    finfo_close($finfo);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!$mime || !isset($allowed[$mime])) {
    jOut(['ok' => false, 'msg' => 'Định dạng ảnh không hỗ trợ (jpg, png, webp).']);
}

$uploadFolderSetting = $uploadFolder ?? 'uploads';
$uploadRoot = realpath(__DIR__ . '/../../' . $uploadFolderSetting) ?: (__DIR__ . '/../../' . $uploadFolderSetting);
$targetDir  = $uploadRoot . '/avatars';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$filename   = 'avatar_' . $userId . '_' . time() . '.' . $allowed[$mime];
$targetPath = $targetDir . '/' . $filename;
$publicPath = $uploadFolderSetting . '/avatars/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jOut(['ok' => false, 'msg' => 'Không thể lưu ảnh, kiểm tra quyền ghi thư mục ' . $uploadFolderSetting . '/avatars.']);
}
if (function_exists('media_publish_local_file')) {
    media_publish_local_file($publicPath);
}

$oldAvatar = $userData['avatar'] ?? '';
if ($oldAvatar && !preg_match('/^https?:\/\//i', $oldAvatar)) {
    if (function_exists('media_delete_remote')) {
        media_delete_remote(ltrim($oldAvatar, '/\\'));
    }
    $oldFile = dirname(__DIR__, 2) . '/' . ltrim($oldAvatar, '/\\');
    $realOld = realpath($oldFile);
    $realDir = realpath($targetDir);
    if ($realOld && $realDir && strpos($realOld, $realDir) === 0 && is_file($realOld)) {
        @unlink($realOld);
    }
}

$up = $ithanhloc->prepare('UPDATE users SET avatar = ? WHERE id = ?');
$up->bind_param('si', $publicPath, $userId);
if (!$up->execute()) {
    jOut(['ok' => false, 'msg' => 'Lưu ảnh thất bại.']);
}

jOut([
    'ok'   => true,
    'msg'  => 'Ảnh đại diện đã cập nhật.',
    'data' => [
        'avatar' => '/' . ltrim($publicPath, '/\\')
    ]
]);
