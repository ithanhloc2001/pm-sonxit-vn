<?php
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
}

// CSRF Guard check
$clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
    jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ hoặc hết hạn phiên làm việc (CSRF). Vui lòng tải lại trang.'], 403);
}

$userId = (int)$_SESSION['user_id'];

$userData = ecommerce_user_load($ithanhloc, $userId);
if (!$userData) {
    jOut(['ok' => false, 'msg' => 'Không tìm thấy tài khoản.']);
}

$newUsername = trim($_POST['username'] ?? '');
$fullName    = trim($_POST['full_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$address     = trim($_POST['address'] ?? '');
$gender      = trim($_POST['gender'] ?? '');
$birthday    = trim($_POST['birthday'] ?? '');

if ($newUsername === '') {
    jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên đăng nhập.']);
}

if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newUsername)) {
    jOut(['ok' => false, 'msg' => 'Tên đăng nhập không hợp lệ (3-30 ký tự, chỉ gồm chữ không dấu, số, dấu gạch dưới).']);
}

if ($fullName !== '' && (preg_match('/[<>\"\'=;()]/', $fullName) || stripos($fullName, 'script') !== false)) {
    jOut(['ok' => false, 'msg' => 'Họ tên chứa ký tự không hợp lệ.']);
}

if ($address !== '' && (preg_match('/[<>\"\'=;()]/', $address) || stripos($address, 'script') !== false)) {
    jOut(['ok' => false, 'msg' => 'Địa chỉ chứa ký tự không hợp lệ.']);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jOut(['ok' => false, 'msg' => 'Email không hợp lệ.']);
}

if ($phone !== '' && !preg_match('/^[0-9+().\s-]{6,20}$/', $phone)) {
    jOut(['ok' => false, 'msg' => 'Số điện thoại không đúng định dạng.']);
}

$gender        = $gender === '' ? null : $gender;
$allowedGender = ['male', 'female', 'other'];
if ($gender !== null && !in_array($gender, $allowedGender, true)) {
    jOut(['ok' => false, 'msg' => 'Giới tính không hợp lệ.']);
}

if ($birthday !== '') {
    if (!preg_match('/^(19|20)\d\d-\d\d-\d\d$/', $birthday) || strtotime($birthday) === false) {
        jOut(['ok' => false, 'msg' => 'Ngày sinh không hợp lệ.']);
    }
} else {
    $birthday = null;
}

$checkUser = $ithanhloc->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
$checkUser->bind_param('si', $newUsername, $userId);
$checkUser->execute();
$hasUser = $checkUser->get_result()->fetch_assoc();
$checkUser->close();

if ($hasUser) {
    jOut(['ok' => false, 'msg' => 'Tên đăng nhập đã tồn tại.']);
}

if ($email !== '') {
    $checkEmail = $ithanhloc->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $checkEmail->bind_param('si', $email, $userId);
    $checkEmail->execute();
    $hasEmail = $checkEmail->get_result()->fetch_assoc();
    $checkEmail->close();
    
    if ($hasEmail) {
        jOut(['ok' => false, 'msg' => 'Email đã được sử dụng. Vui lòng dùng email khác.']);
    }
}

// Chặn trùng số điện thoại — so cả 2 biến thể (0xxx và 84xxx), loại trừ chính user này
if ($phone !== '') {
    $digits = preg_replace('/\D+/', '', $phone);
    $p84 = $digits;
    $p0  = $digits;
    if (strpos($digits, '84') === 0) { $p84 = $digits; if (strlen($digits) > 2) $p0 = '0' . substr($digits, 2); }
    elseif (strpos($digits, '0') === 0) { if (strlen($digits) > 1) $p84 = '84' . substr($digits, 1); }
    // So khớp cả số gốc người dùng nhập lẫn 2 biến thể chuẩn hoá
    $checkPhone = $ithanhloc->prepare('SELECT id FROM users WHERE id <> ? AND phone IS NOT NULL AND phone <> "" AND (phone = ? OR phone = ? OR phone = ?) LIMIT 1');
    $checkPhone->bind_param('isss', $userId, $phone, $p84, $p0);
    $checkPhone->execute();
    $hasPhone = $checkPhone->get_result()->fetch_assoc();
    $checkPhone->close();

    if ($hasPhone) {
        jOut(['ok' => false, 'msg' => 'Số điện thoại đã được sử dụng. Vui lòng dùng số khác.']);
    }
}

$email    = $email === '' ? null : $email;
$phone    = $phone === '' ? null : $phone;
$address  = $address === '' ? null : $address;
$fullName = $fullName === '' ? null : $fullName;

$up = $ithanhloc->prepare('UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, address = ?, gender = ?, birthday = ? WHERE id = ?');
$up->bind_param('sssssssi', $newUsername, $fullName, $email, $phone, $address, $gender, $birthday, $userId);
try {
    $upSuccess = $up->execute();
} catch (mysqli_sql_exception $e) {
    $up->close();
    if (stripos($e->getMessage(), 'uk_users_email') !== false) {
        jOut(['ok' => false, 'msg' => 'Email đã được sử dụng. Vui lòng dùng email khác.']);
    }
    if (stripos($e->getMessage(), 'uk_users_phone') !== false) {
        jOut(['ok' => false, 'msg' => 'Số điện thoại đã được sử dụng. Vui lòng dùng số khác.']);
    }
    jOut(['ok' => false, 'msg' => 'Không thể cập nhật lúc này.']);
}
$up->close();

if (!$upSuccess) {
    jOut(['ok' => false, 'msg' => 'Không thể cập nhật lúc này.']);
}

$updated     = loadUser($ithanhloc, $userId);
$displayName = $updated['full_name'] ?: $updated['username'];

app_user_log($ithanhloc, $userId, 'profile_update', 'Cập nhật thông tin hồ sơ');
app_user_notify_structured($ithanhloc, $userId, [
    'title'    => 'Cập nhật thông tin cá nhân',
    'content'  => 'Hồ sơ tài khoản của bạn vừa được cập nhật.',
    'type'     => 'system',
    'link'     => '#',
    'template' => 'tpl4',
    'meta'     => [
        'module' => 'system',
        'event'  => 'profile_updated',
        'status' => 'success',
    ],
]);

jOut([
    'ok'   => true,
    'msg'  => 'Đã lưu thông tin hồ sơ.',
    'data' => [
        'username'     => $updated['username'],
        'full_name'    => $updated['full_name'],
        'email'        => $updated['email'],
        'phone'        => $updated['phone'],
        'address'      => $updated['address'],
        'gender'       => $updated['gender'],
        'birthday'     => $updated['birthday'],
        'display_name' => $displayName
    ]
]);
