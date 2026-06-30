<?php
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
}

$userId = (int)$_SESSION['user_id'];

$stmt = $ithanhloc->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    jOut(['ok' => false, 'msg' => 'Không tìm thấy tài khoản.']);
}

$hash    = $row['password'];
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['new_password_confirm'] ?? '';
$action  = trim((string)($_POST['action'] ?? 'change'));

if ($action === 'verify_current') {
    if ($current === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập mật khẩu hiện tại.']);
    }
    
    if (!password_verify($current, $hash) && $current !== $hash) {
        jOut(['ok' => false, 'msg' => 'Mật khẩu hiện tại không đúng.']);
    }
    
    jOut(['ok' => true, 'msg' => 'Xác minh thành công.']);
}

if ($new === '' || $confirm === '') {
    jOut(['ok' => false, 'msg' => 'Vui lòng nhập đủ mật khẩu mới.']);
}

if ($new !== $confirm) {
    jOut(['ok' => false, 'msg' => 'Mật khẩu nhập lại không khớp.']);
}

if (!password_verify($current, $hash) && $current !== $hash) {
    jOut(['ok' => false, 'msg' => 'Mật khẩu hiện tại không đúng.']);
}

if (strlen($new) < 6) {
    jOut(['ok' => false, 'msg' => 'Mật khẩu mới cần tối thiểu 6 ký tự.']);
}

$newHash = password_hash($new, PASSWORD_BCRYPT);
$up      = $ithanhloc->prepare('UPDATE users SET password = ? WHERE id = ?');
$up->bind_param('si', $newHash, $userId);

if (!$up->execute()) {
    jOut(['ok' => false, 'msg' => 'Không thể cập nhật mật khẩu.']);
}
$up->close();

app_user_log($ithanhloc, $userId, 'password_change', 'Đổi mật khẩu');
app_user_notify_structured($ithanhloc, $userId, [
    'title'    => 'Bảo mật tài khoản',
    'content'  => 'Mật khẩu của bạn vừa được thay đổi thành công.',
    'type'     => 'system',
    'link'     => '#',
    'template' => 'tpl4',
    'meta'     => [
        'module' => 'system',
        'event'  => 'password_changed',
        'status' => 'success',
    ],
]);

jOut(['ok' => true, 'msg' => 'Đã cập nhật mật khẩu.']);
