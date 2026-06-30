<?php
/*
|--------------------------------------------------------------------------
| Khởi tạo runtime (không phụ thuộc DB)
|--------------------------------------------------------------------------
| - Bật/tắt chế độ báo lỗi PHP
| - Thiết lập session, cookie, bảo mật cơ bản
| - Chuẩn hoá baseUrl/domain cho toàn hệ thống
| - Nạp khoá API và cấu hình mặc định
| - Nạp các hàm trợ giúp từ functions.php
*/

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Đảm bảo mọi output và xử lý chuỗi sử dụng UTF-8.
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
require_once __DIR__ . '/functions.php';
$forwardedProto = strtolower(app_server_header_first('HTTP_X_FORWARDED_PROTO'));
$isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || $forwardedProto === 'https'
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 86400 * 30;

    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => $isHttpsRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Auto-login as admin in localhost development
if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $hostRaw = trim((string)$_SERVER['HTTP_HOST']);
    $hostNoPort = preg_replace('/:\d+$/', '', strtolower($hostRaw));
    if (in_array($hostNoPort, ['localhost', '127.0.0.1', '::1'], true)) {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = 'admin';
        $_SESSION['user_name'] = 'admin';
    }
}



// Các cấu hình môi trường và API keys sẽ được nạp trong config.php sau khi có kết nối DB nếu cần.

