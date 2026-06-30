<?php

require_once __DIR__ . '/../../config.php';

$zaloAppId  = '667224493114305501';
$zaloSecret = '8Y6481Sk592MN7t6qY6M';

if (function_exists('app_get_config_value_by_path')) {
    $zaloAppId  = trim((string)(app_get_config_value_by_path('ZALO_LOGIN.app_id') ?? ''));
    $zaloSecret = trim((string)(app_get_config_value_by_path('ZALO_LOGIN.secret') ?? ''));
}
if ($zaloAppId === '') {
    $zaloAppId = trim((string)(getenv('ZALO_LOGIN_APP_ID') ?: ''));
}
if ($zaloSecret === '') {
    $zaloSecret = trim((string)(getenv('ZALO_LOGIN_SECRET') ?: ''));
}

$zaloRedirectUri = rtrim((string)($baseUrl ?? ''), '/') . '/core/zalo/callback.php';

if (function_exists('app_get_config_value_by_path')) {
    $override = (string)(app_get_config_value_by_path('ZALO_LOGIN.redirect_uri') ?? '');
    if ($override !== '') {
        $zaloRedirectUri = $override;
    }
}

function zalo_redirect_with_error(string $message): void {
    global $baseUrl;
    $target = rtrim((string)($baseUrl ?? ''), '/') . '/login';
    $param  = 'zalo_error=' . urlencode($message);
    if (strpos($target, '?') === false) {
        $target .= '?' . $param;
    } else {
        $target .= '&' . $param;
    }
    header('Location: ' . $target);
    exit;
}

if ($zaloAppId === '' || $zaloSecret === '') {
    zalo_redirect_with_error('Không thể liên kết đăng nhập bằng Zalo vào lúc này.');
}

if (!isset($_GET['code'])) {
    zalo_redirect_with_error('Không tìm thấy thông tin xác thực từ Zalo.');
}

$code = trim((string)$_GET['code']);
if ($code === '') {
    zalo_redirect_with_error('Yêu cầu đăng nhập Zalo không hợp lệ.');
}

$codeVerifier = '';
if (!empty($_COOKIE['z_pkce_verifier'])) {
    $codeVerifier = trim((string)$_COOKIE['z_pkce_verifier']);
}

$tokenEndpoint = 'https://oauth.zaloapp.com/v4/access_token';
$tokenParams   = [
    'app_id'       => $zaloAppId,
    'app_secret'   => $zaloSecret,
    'grant_type'   => 'authorization_code',
    'code'         => $code,
    'redirect_uri' => $zaloRedirectUri,
];
if ($codeVerifier !== '') {
    $tokenParams['code_verifier'] = $codeVerifier;
}
$query = http_build_query($tokenParams);

$tokenResponse = null;
if (function_exists('curl_init')) {
    $ch = curl_init($tokenEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => $query,
    ]);
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status === 200 && $raw !== false) {
        $tokenResponse = json_decode($raw, true);
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $query,
            'timeout' => 10,
        ],
    ]);
    $raw = @file_get_contents($tokenEndpoint, false, $context);
    if ($raw !== false) {
        $tokenResponse = json_decode($raw, true);
    }
}

if (!is_array($tokenResponse)) {
    zalo_redirect_with_error('Xác thực tài khoản Zalo thất bại. Vui lòng thử lại.');
}

$accessToken = (string)($tokenResponse['access_token'] ?? '');
if ($accessToken === '') {
    zalo_redirect_with_error('Đăng nhập bằng Zalo không thành công. Vui lòng thử lại sau.');
}

$userInfoEndpoint = 'https://graph.zalo.me/v2.0/me';
$userQuery        = http_build_query([
    'access_token' => $accessToken,
    'fields'       => 'id,name,picture'
]);
$userUrl = $userInfoEndpoint . '?' . $userQuery;

$userData = null;
if (function_exists('curl_init')) {
    $ch = curl_init($userUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status === 200 && $raw !== false) {
        $userData = json_decode($raw, true);
    }
} else {
    $raw = @file_get_contents($userUrl);
    if ($raw !== false) {
        $userData = json_decode($raw, true);
    }
}

if (!is_array($userData) || empty($userData['id'])) {
    zalo_redirect_with_error('Không thể lấy thông tin cá nhân từ tài khoản Zalo của bạn.');
}

$zaloId   = (string)$userData['id'];
$zaloName = (string)($userData['name'] ?? 'Zalo User');

if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    zalo_redirect_with_error('Hệ thống đang bận. Vui lòng thử lại sau.');
}

$pseudoEmail = 'zalo_' . preg_replace('/[^0-9]/', '', $zaloId) . '@zalo.local';

$stmt = $ithanhloc->prepare('SELECT id, username, role FROM users WHERE email = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('s', $pseudoEmail);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->close();
    }
    $stmt->close();
} else {
    $user = null;
}

if ($user) {
    $userId                = (int)($user['id'] ?? 0);
    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $user['username'] ?? $zaloName;
    $_SESSION['role']      = $user['role'] ?? 'user';

    if (function_exists('app_user_log')) {
        app_user_log($ithanhloc, $userId, 'login', 'Đăng nhập bằng Zalo thành công', [
            'method' => 'zalo',
        ]);
    }
} else {
    $baseUsername = 'z' . substr(preg_replace('/\s+/', '', strtolower($zaloName)), 0, 12);
    if ($baseUsername === '' || !preg_match('/^[a-z0-9_]+$/', $baseUsername)) {
        $baseUsername = 'zalo' . substr($zaloId, -6);
    }

    $username  = $baseUsername;
    $suffix    = 1;
    $checkStmt = $ithanhloc->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if ($checkStmt) {
        while (true) {
            $checkStmt->bind_param('s', $username);
            $checkStmt->execute();
            $r = $checkStmt->get_result();
            if (!$r || $r->num_rows === 0) {
                if ($r) {
                    $r->close();
                }
                break;
            }
            if ($r) {
                $r->close();
            }
            $suffix++;
            $username = $baseUsername . $suffix;
            if ($suffix > 999) {
                $username = 'zalo' . substr(md5($zaloId . microtime(true)), 0, 6);
                break;
            }
        }
        $checkStmt->close();
    }

    $randomPassword = '12345';
    $hash           = password_hash($randomPassword, PASSWORD_BCRYPT);

    $insert = $ithanhloc->prepare('INSERT INTO users (username, password, phone, email, role) VALUES (?, ?, ?, ?, "user")');
    if (!$insert) {
        zalo_redirect_with_error('Khởi tạo tài khoản mới thất bại. Vui lòng liên hệ bộ phận hỗ trợ.');
    }
    $emptyPhone = '';
    $insert->bind_param('ssss', $username, $hash, $emptyPhone, $pseudoEmail);
    $ok        = $insert->execute();
    $newUserId = (int)$ithanhloc->insert_id;
    $insert->close();

    if (!$ok || $newUserId <= 0) {
        zalo_redirect_with_error('Khởi tạo tài khoản mới thất bại. Vui lòng liên hệ bộ phận hỗ trợ.');
    }

    $_SESSION['user_id']   = $newUserId;
    $_SESSION['user_name'] = $username;
    $_SESSION['role']      = 'user';

    if (function_exists('app_user_log')) {
        app_user_log($ithanhloc, $newUserId, 'register', 'Đăng ký tài khoản bằng Zalo', [
            'method' => 'zalo',
        ]);
    }
}

$redirectBaseUrl = rtrim((string)($baseUrl ?? ''), '/');
header('Location: ' . $redirectBaseUrl . '/');
exit;
