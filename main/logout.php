<?php
require_once __DIR__ . "/../config.php";

// Log user action before clearing session
if (isset($_SESSION['user_id'])) {
    app_user_log($ithanhloc, (int)$_SESSION['user_id'], 'logout', 'Đăng xuất khỏi hệ thống');
}

// 1. Clear all session variables
$_SESSION = [];

// 2. Destroy the session cookie + application cookies.
// Nếu logout được nạp qua front controller (head.php đã render) thì headers đã gửi,
// setcookie sẽ vô hiệu — khi đó dùng JS xoá cookie ở phía client làm fallback.
$appCookies = ['selected_location', 'selected_locations', 'selected_region'];
$headersSent = headers_sent();
if (!$headersSent) {
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $secure,
            'httponly' => $params["httponly"],
            'samesite' => $params["samesite"] ?? 'Lax'
        ]);
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    foreach ($appCookies as $cookieName) {
        if (isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }
    }
}
foreach ($appCookies as $cookieName) {
    unset($_COOKIE[$cookieName]);
}

// 3. Finally destroy the session on server (luôn chạy được, không cần header)
session_destroy();

// 4. Redirect về trang chủ + xoá cookie phía client (fallback khi headers đã gửi)
$redirectBaseUrl = trim((string)($baseUrl ?? ''));
if ($redirectBaseUrl === '') {
    $redirectBaseUrl = '/';
}
$cookieNamesJs = json_encode(array_merge([session_name()], $appCookies));
echo '<script type="text/javascript">'
   . 'try{var _ck=' . $cookieNamesJs . ';_ck.forEach(function(n){'
   . 'document.cookie=n+"=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";});}catch(e){}'
   . 'window.location.href=' . json_encode($redirectBaseUrl) . ';'
   . '</script>';
exit;
?>