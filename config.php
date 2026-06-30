<?php
require_once __DIR__ . '/bootstrap.php';
// Cấu hình kết nối cơ sở dữ liệu (MySQL)
$DB_HOST = getenv('DB_HOST') ?: 'db';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: 'root_password';
$DB_NAME = getenv('DB_NAME') ?: 'icfwqfud_diy';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3306);
// Kiểm tra database name có hợp lệ không nếu không hợp lệ thì set về default
if (!preg_match('/^[a-zA-Z0-9_]+$/', $DB_NAME)) {
    $DB_NAME = 'icfwqfud_diy';
}
// Kết nối database
$ithanhloc = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
if ($ithanhloc->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Hệ thống đang gặp sự cố, vui lòng thử lại sau...', 'msg' => $ithanhloc->connect_error]));
}
// Tạo database nếu chưa tồn tại
$safeDbName = str_replace('`', '``', $DB_NAME);
@$ithanhloc->query("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Chọn database
if (!@$ithanhloc->select_db($DB_NAME)) {
    http_response_code(500);
    die(json_encode(['error' => 'Hệ thống đang gặp sự cố, vui lòng thử lại sau...', 'msg' => $ithanhloc->error]));
}
//  Đặt múi giờ và charset
$ithanhloc->set_charset('utf8mb4');
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tự động tạo bảng log_password_reset nếu chưa tồn tại
$ithanhloc->query("CREATE TABLE IF NOT EXISTS `log_password_reset` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`email`),
    INDEX (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!function_exists('app_refresh_user_session_from_db')) {
    /**
     * Đồng bộ lại session theo dữ liệu mới nhất trong bảng users (theo $_SESSION['user_id']).
     * Lưu ý: không lưu password vào session để tránh rủi ro bảo mật.
     */
    function app_refresh_user_session_from_db(mysqli $db): bool {
        if (!isset($_SESSION['user_id'])) return false;

        $userId = (int)$_SESSION['user_id'];
        if ($userId <= 0) {
            unset(
                $_SESSION['user_id'], $_SESSION['role'], $_SESSION['user_name'],
                $_SESSION['username'], $_SESSION['user_phone'], $_SESSION['phone'],
                $_SESSION['user'],
                $_SESSION['user_full_name'], $_SESSION['user_email'], $_SESSION['user_address'],
                $_SESSION['user_avatar'], $_SESSION['user_gender'], $_SESSION['user_birthday'],
                $_SESSION['user_created_at'], $_SESSION['user_balance']
            );
            return false;
        }

        $sql = 'SELECT id, username, full_name, password, phone, address, avatar, gender, birthday, email, role, created_at, balance '
             . 'FROM users WHERE id=? LIMIT 1';
        $stmt = $db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            unset(
                $_SESSION['user_id'], $_SESSION['role'], $_SESSION['user_name'],
                $_SESSION['username'], $_SESSION['user_phone'], $_SESSION['phone'],
                $_SESSION['user'],
                $_SESSION['user_full_name'], $_SESSION['user_email'], $_SESSION['user_address'],
                $_SESSION['user_avatar'], $_SESSION['user_gender'], $_SESSION['user_birthday'],
                $_SESSION['user_created_at'], $_SESSION['user_balance']
            );
            return false;
        }

        $username = trim((string)($row['username'] ?? ''));
        $role = (string)($row['role'] ?? 'user');
        $phone = trim((string)($row['phone'] ?? ''));
        $balance = (int)($row['balance'] ?? 0);

        $_SESSION['user_id'] = (int)($row['id'] ?? $userId);
        $_SESSION['role'] = $role;
        $_SESSION['user_name'] = $username;
        $_SESSION['username'] = $username;
        $_SESSION['user_full_name'] = (string)($row['full_name'] ?? '');
        $_SESSION['user_email'] = (string)($row['email'] ?? '');
        $_SESSION['user_address'] = (string)($row['address'] ?? '');
        $_SESSION['user_avatar'] = (string)($row['avatar'] ?? '');
        $_SESSION['user_gender'] = (string)($row['gender'] ?? '');
        $_SESSION['user_birthday'] = (string)($row['birthday'] ?? '');
        $_SESSION['user_created_at'] = (string)($row['created_at'] ?? '');
        $_SESSION['user_balance'] = $balance;
        // Kiểm tra và gán số điện thoại
        if ($phone !== '') {
            $_SESSION['user_phone'] = $phone;
            $_SESSION['phone'] = $phone;
        } else {
            unset($_SESSION['user_phone'], $_SESSION['phone']);
        }

        $_SESSION['user'] = [
            'id' => $_SESSION['user_id'],
            'username' => $username,
            'full_name' => $_SESSION['user_full_name'],
            'phone' => $phone,
            'address' => $_SESSION['user_address'],
            'avatar' => $_SESSION['user_avatar'],
            'gender' => $_SESSION['user_gender'],
            'birthday' => $_SESSION['user_birthday'],
            'email' => $_SESSION['user_email'],
            'role' => $role,
            'created_at' => $_SESSION['user_created_at'],
            'balance' => $balance,
        ];

        return true;
    }
}

// Luôn đồng bộ session theo users trước khi đọc các biến $isLoggedIn/$isAdmin...
$__sessionSynced = app_refresh_user_session_from_db($ithanhloc);

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Thông tin người dùng đang đăng nhập (session)
$__sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$isLoggedIn = $__sessionUserId > 0;
$isAdmin = $isLoggedIn && !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isUser = $isLoggedIn && !empty($_SESSION['role']) && $_SESSION['role'] === 'user';
$userId = $isLoggedIn ? $__sessionUserId : null;
$userName = isset($_SESSION['user_name']) ? trim((string)$_SESSION['user_name']) : '';

// Ưu tiên dùng dữ liệu vừa refresh để tránh query lại
$USER_INFO = null;
if ($isLoggedIn && !$isAdmin && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $u = $_SESSION['user'];
    $displayName = trim((string)($u['full_name'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string)($u['username'] ?? ''));
    }
    $USER_INFO = [
        'id' => (int)($u['id'] ?? ($_SESSION['user_id'] ?? 0)),
        'name' => $displayName,
        'role' => (string)($u['role'] ?? ($_SESSION['role'] ?? 'user')),
        'email' => (string)($u['email'] ?? ($_SESSION['user_email'] ?? '')),
        'phone' => (string)($u['phone'] ?? ($_SESSION['user_phone'] ?? ($_SESSION['phone'] ?? ''))),
        'address' => (string)($u['address'] ?? ($_SESSION['user_address'] ?? '')),
        'avatar' => (string)($u['avatar'] ?? ($_SESSION['user_avatar'] ?? '')),
    ];
} else {
    // fallback giữ nguyên hành vi cũ (và trả null nếu role=admin)
    $USER_INFO = app_load_user_info($ithanhloc);
}

// Khởi tạo các biến cấu hình mặc định trước khi áp dụng override từ database
$ECOMMERCE_REGIONS = [];
$ECOMMERCE_XU = [];
$ECOMMERCE_VAT_DEFAULT = 8.0;
$COMPANY_INFO = [];
$ECOMMERCE_PAYMENT_METHODS = ['cod' => 'Thanh toán khi nhận hàng'];

// Áp các giá trị cấu hình ghi đè từ bảng site_setting/bot_setting
app_apply_bot_setting_overrides($ithanhloc);

// --- Cấu hình chung của website (Identity & URLs) ---
$site_logo = app_get_config_value_by_path('SITE_LOGO');
$site_title = app_get_config_value_by_path('SITE_TITLE');
$_SiteTitle = ($site_title ?: 'Paint&More');
$site_url = app_get_config_value_by_path('SITE_URL');
// Mô tả website (meta description mặc định) — đổi được trong Setting.
$site_description = trim((string)app_get_config_value_by_path('SITE_DESCRIPTION'));
if ($site_description === '') {
    $site_description = 'Công ty Cổ phần PAINT & MORE là đơn vị phân phối sơn và giải pháp hoàn thiện bề mặt cho nhà thầu, kiến trúc sư và khách hàng cá nhân.';
}

// Cấu hình URL gốc và path (baseUrl, basePath) cho toàn hệ thống
$domain = $site_url;
$baseUrl = $site_url;
// Dev helper: khi chạy localhost thì ưu tiên baseUrl theo host hiện tại để tránh CORS
if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $hostRaw = trim((string)$_SERVER['HTTP_HOST']);
    $hostNoPort = preg_replace('/:\d+$/', '', strtolower($hostRaw));
    if (in_array($hostNoPort, ['localhost', '127.0.0.1', '::1'], true)) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $isHttps ? 'https' : 'http';
        $projectRoot = str_replace('\\', '/', __DIR__);
        $docRoot = str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $requestBasePath = '';
        if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
            $requestBasePath = substr($projectRoot, strlen($docRoot));
        }
        $requestBasePath = str_replace('\\', '/', $requestBasePath);
        $requestBasePath = rtrim($requestBasePath, '/');
        $baseUrl = $scheme . '://' . $hostRaw . $requestBasePath;
        $domain = $baseUrl;
    }
}
$basePath = parse_url($baseUrl, PHP_URL_PATH);
$basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
$_SiteUrl = ($site_url ?: ($baseUrl ?: '/'));

// Cờ môi trường dev tường minh — xác định theo host, KHÔNG dựa vào PHP_OS.
// Dùng để nới lỏng rate-limit khi kiểm thử cục bộ (xem core/zns/otp.php).
if (!defined('APP_IS_DEV')) {
    $__devHost = '';
    if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
        $__devHost = preg_replace('/:\d+$/', '', strtolower(trim((string)$_SERVER['HTTP_HOST'])));
    }
    $__isDevEnv = (getenv('APP_ENV') === 'dev')
        || in_array($__devHost, ['localhost', '127.0.0.1', '::1'], true)
        || ($__devHost !== '' && preg_match('/\.(local|test)$/', $__devHost) === 1);
    define('APP_IS_DEV', $__isDevEnv);
}

// Các khoá API cơ bản từ biến môi trường
$API_OPENAI_KEY = h(getenv('API_OPENAI_KEY'));
$API_GEMINI_KEY = h(getenv('API_GEMINI_KEY'));
$TINYMCE_API_KEY = h(getenv('TINYMCE_API_KEY'));
// Google Maps API key (đổi được trong Setting → tab AI). Dùng cho bản đồ + geocoding địa chỉ.
$google_maps_api_key = trim((string)app_get_config_value_by_path('GOOGLE_MAPS_API_KEY'));
// Goong.io REST API key — dùng cho gợi ý/geocode địa chỉ VN (chính xác hơn OSM/Nominatim).
$goong_api_key = trim((string)app_get_config_value_by_path('GOONG_API_KEY'));
if ($goong_api_key === '') $goong_api_key = trim((string)(getenv('GOONG_API_KEY') ?: ''));
// Goong.io Map key — dùng để hiển thị bản đồ Goong Maps JS SDK (khác REST key).
$goong_map_key = trim((string)app_get_config_value_by_path('GOONG_MAP_KEY'));
if ($goong_map_key === '') $goong_map_key = trim((string)(getenv('GOONG_MAP_KEY') ?: ''));

// Chuyển sang dùng local (Self-hosting) thay vì Cloud CDN để tránh giới hạn quota
$_TinyMceUrl = $_SiteUrl . '/assets/tinymce/tinymce.min.js';
$_TinyMceLangUrl = $_SiteUrl . '/assets/tinymce/langs/vi.js';

$site_theme_color = app_get_config_value_by_path('SITE_THEME_COLOR');
$site_fallback_logo = app_get_config_value_by_path('SITE_FALLBACK_LOGO') ?: $site_logo;
$site_hotline = app_get_config_value_by_path('SITE_HOTLINE');

if (empty($site_hotline)) {
    $site_hotline = app_get_config_value_by_path('COMPANY_INFO.hotline');
}
$hotline = $site_hotline ?? '';
$site_fallback_logo = app_get_config_value_by_path('SITE_FALLBACK_LOGO') ?: $site_logo;
$site_hotline = app_get_config_value_by_path('SITE_HOTLINE');
if (empty($site_hotline)) {
    $site_hotline = $company_hotline;
}
$hotline = $site_hotline ?? '';
// Thông tin doanh nghiệp dùng cho xuất hoá đơn / thanh toán
$company_name = app_get_config_value_by_path('COMPANY_INFO.name');
$company_tax_code = app_get_config_value_by_path('COMPANY_INFO.tax_code');
$company_address = app_get_config_value_by_path('COMPANY_INFO.address');
$company_hotline = app_get_config_value_by_path('COMPANY_INFO.hotline');
$company_email = app_get_config_value_by_path('COMPANY_INFO.email');
$company_bank_name = app_get_config_value_by_path('COMPANY_INFO.bank_name');
$company_bank_account = app_get_config_value_by_path('COMPANY_INFO.bank_account');
$company_bank_branch = app_get_config_value_by_path('COMPANY_INFO.bank_branch');
$company_bank_qr_image = app_get_config_value_by_path('COMPANY_INFO.bank_qr_image');
$company_responsible_person = "LÊ PHI HÙNG";
$company_bank_content_text = "";

// Cấu hình thuế VAT mặc định
$vatDefault = (float)(function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0);
$vatDefault = max(0, min(100, $vatDefault));
$vatDefaultLabel = fmod($vatDefault, 1.0) === 0.0 ? (string)(int)$vatDefault : rtrim(rtrim(number_format($vatDefault, 2, '.', ''), '0'), '.');
$config_vat_default = app_get_config_value_by_path('ECOMMERCE_VAT_DEFAULT');

// Cấu hình danh sách vùng miền phục vụ vận chuyển / hiển thị
$regionOptions = (isset($ECOMMERCE_REGIONS) && is_array($ECOMMERCE_REGIONS) && !empty($ECOMMERCE_REGIONS))
    ? array_values($ECOMMERCE_REGIONS)
    : ['MIỀN BẮC', 'MIỀN TRUNG', 'MIỀN NAM'];
$region_north = app_get_config_value_by_path('ECOMMERCE_REGIONS.north');
$region_central = app_get_config_value_by_path('ECOMMERCE_REGIONS.central');
$region_south = app_get_config_value_by_path('ECOMMERCE_REGIONS.south');

// Cấu hình liên kết mạng xã hội và thông tin liên hệ
$social_facebook = app_get_config_value_by_path('SOCIAL_FACEBOOK');
$social_zalo = app_get_config_value_by_path('SOCIAL_ZALO');
$social_youtube = app_get_config_value_by_path('SOCIAL_YOUTUBE');
$social_tiktok = app_get_config_value_by_path('SOCIAL_TIKTOK');
$social_instagram = app_get_config_value_by_path('SOCIAL_INSTAGRAM');
$social_phone = app_get_config_value_by_path('SOCIAL_PHONE');
$social_email = app_get_config_value_by_path('SOCIAL_EMAIL');

// Cấu hình khoá API TinyMCE (trình soạn thảo)
$tinyMceApiKey = trim((string)(app_get_config_value_by_path('TINYMCE_API_KEY') ?? ''));
if (empty($tinyMceApiKey)) {
    $tinyMceApiKey = trim((string)(getenv('TINYMCE_API_KEY') ?: 'txuhjwh153p7ftwaz7qmpjld118svnfamxi9x5ofyyk77c76'));
}
// Cấu hình hệ thống điểm thưởng (xu) cho khách hàng
$xu_review_reward = app_get_config_value_by_path('ECOMMERCE_XU.review_reward');
$xu_vnd_per_xu = app_get_config_value_by_path('ECOMMERCE_XU.vnd_per_xu');
$xu_max_use_percent = app_get_config_value_by_path('ECOMMERCE_XU.max_use_percent');
$xu_earn_percent = app_get_config_value_by_path('ECOMMERCE_XU.earn_percent');

// Cấu hình đăng nhập Google
$google_login_enabled = app_get_config_value_by_path('GOOGLE_LOGIN.enabled');
$google_login_client_id = app_get_config_value_by_path('GOOGLE_LOGIN.client_id');
$google_login_auto_register = app_get_config_value_by_path('GOOGLE_LOGIN.auto_register');
$google_login_redirect_uri = app_get_config_value_by_path('GOOGLE_LOGIN.redirect_uri');
$google_login_scope = app_get_config_value_by_path('GOOGLE_LOGIN.scope');

$GOOGLE_LOGIN = [
    'enabled' => h($google_login_enabled ?? getenv('GOOGLE_LOGIN_ENABLED') ?? ''),
    'client_id' => h($google_login_client_id ?? getenv('GOOGLE_LOGIN_CLIENT_ID') ?? ''),
    'auto_register' => h($google_login_auto_register ?? getenv('GOOGLE_LOGIN_AUTO_REGISTER') ?? ''),
    'redirect_uri' => h($google_login_redirect_uri ?? getenv('GOOGLE_LOGIN_REDIRECT_URI') ?? ''),
    'scope' => h($google_login_scope ?? getenv('GOOGLE_LOGIN_SCOPE') ?? 'openid email profile'),
    'allowed_issuers' => ['accounts.google.com', 'https://accounts.google.com'],
    'verify_endpoint' => 'https://oauth2.googleapis.com/tokeninfo',
];

// Cấu hình đăng nhập Zalo
$zalo_login_enabled = app_get_config_value_by_path('ZALO_LOGIN.enabled');
$zalo_login_auth_url = app_get_config_value_by_path('ZALO_LOGIN.auth_url');
$zalo_login_app_id = app_get_config_value_by_path('ZALO_LOGIN.app_id');
$zalo_login_secret = app_get_config_value_by_path('ZALO_LOGIN.secret');
$ZALO_LOGIN = [
    'enabled' => h($zalo_login_enabled ?? getenv('ZALO_LOGIN_ENABLED') ?? ''),
    'auth_url' => h($zalo_login_auth_url ?? getenv('ZALO_LOGIN_AUTH_URL') ?? '')
];

// Cấu hình phương thức thanh toán đang bật/tắt
$cod_enabled = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.cod.enabled');

// Cấu hình thanh toán MoMo (sản xuất / test)
$momo_env = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_env');
$momo_enabled = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.enabled');
$momo_partner_code = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.partnerCode');
$momo_access_key = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.accessKey');
$momo_secret_key = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.secretKey');
$momo_redirect_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.redirectUrl');
$momo_ipn_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.ipnUrl');
$momo_create_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.createUrl');
$momo_query_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.queryUrl');

$momo_test_partner_code = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.partnerCode');
$momo_test_access_key = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.accessKey');
$momo_test_secret_key = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.secretKey');
$momo_test_redirect_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.redirectUrl');
$momo_test_ipn_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.ipnUrl');
$momo_test_create_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.createUrl');
$momo_test_query_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.queryUrl');

// Cấu hình thanh toán VNPAY
$vnpay_enabled = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.enabled');
$vnpay_tmn_code = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.tmnCode');
$vnpay_hash_secret = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.hashSecret');
$vnpay_return_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.returnUrl');
$vnpay_ipn_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.ipnUrl');
$vnpay_pay_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.payUrl');
$vnpay_api_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.apiUrl');

// Cấu hình thanh toán ZaloPay
$zalopay_enabled = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.enabled');
$zalopay_app_id = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.app_id');
$zalopay_key1 = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key1');
$zalopay_key2 = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key2');
$zalopay_create_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.createUrl');
$zalopay_callback_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.callbackUrl');
$zalopay_redirect_url = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.redirectUrl');

// Cấu hình Giao Hàng Nhanh (GHN)
$ECOMMERCE_GHN = [
    'env' => "prod",
    'enabled' => in_array(strtolower((string)(getenv('ECOMMERCE_GHN_ENABLED') ?: '0')), ['1', 'true', 'yes', 'on'], true),
    'base_url' => getenv('ECOMMERCE_GHN_BASE_URL') ?: 'https://online-gateway.ghn.vn/shiip/public-api',
    'token' => getenv('ECOMMERCE_GHN_TOKEN') ?: '',
    'shop_id' => (int)(getenv('ECOMMERCE_GHN_SHOP_ID') ?: 0),
    'from_name' => getenv('ECOMMERCE_GHN_FROM_NAME') ?: '',
    'from_phone' => getenv('ECOMMERCE_GHN_FROM_PHONE') ?: '',
    'from_address' => getenv('ECOMMERCE_GHN_FROM_ADDRESS') ?: '',
    'from_district_id' => (int)(getenv('ECOMMERCE_GHN_FROM_DISTRICT_ID') ?: 0),
    'from_ward_code' => getenv('ECOMMERCE_GHN_FROM_WARD_CODE') ?: '',
    'default_weight' => (int)(getenv('ECOMMERCE_GHN_DEFAULT_WEIGHT') ?: 1200),
    'default_length' => (int)(getenv('ECOMMERCE_GHN_DEFAULT_LENGTH') ?: 20),
    'default_width' => (int)(getenv('ECOMMERCE_GHN_DEFAULT_WIDTH') ?: 20),
    'default_height' => (int)(getenv('ECOMMERCE_GHN_DEFAULT_HEIGHT') ?: 20),
    'insurance_value' => (int)(getenv('ECOMMERCE_GHN_INSURANCE_VALUE') ?: 0),
    // Secret token để xác thực webhook callback của GHN. Cấu hình giống nhau ở 2 bên (GHN portal & .env).
    // GHN gửi token qua header "Token" hoặc query param ?token=...
    'webhook_secret' => getenv('ECOMMERCE_GHN_WEBHOOK_SECRET') ?: '',
];

// Cấu hình reCAPTCHA
$GOOGLE_RECAPTCHA = [
    'site_key' => '6LccKzMtAAAAACWII6jOV1A6pVgTrdmNvSRr82bv',
    'secret_key' => '6LccKzMtAAAAANNrgf9GHaW8Se6_eCVia-E1PDK2',
];

// Cấu hình gửi email qua SMTP (Gmail). Dùng để gửi OTP qua email.
// Với Gmail cần bật xác minh 2 bước và tạo "App Password" (16 ký tự) đặt vào SMTP_PASS.
$SMTP_CONFIG = [
    'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'port'       => (int)(getenv('SMTP_PORT') ?: 587),
    // 'tls' (STARTTLS, port 587) hoặc 'ssl' (port 465)
    'encryption' => strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls')),
    'username'   => getenv('SMTP_USER') ?: 'beta.kunloc.01@gmail.com',
    'password'   => getenv('SMTP_PASS') ?: 'qgwfckrrxhdocgwr',
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: 'beta.kunloc.01@gmail.com'),
    'from_name'  => getenv('SMTP_FROM_NAME') ?: 'Paint&More',
];

// Cấu hình phí vận chuyển chung
$ECOMMERCE_SHIPPING = [
    'standard_fee' => (int)(getenv('ECOMMERCE_SHIPPING_STANDARD_FEE') ?: 30000),
];


// Cấu hình path fallback (URL tuyệt đối được tính SAU khối media bên dưới,
// vì cần $mediaBaseUrl đã sẵn sàng để route sang media domain).
$fallbackPath = $site_fallback_logo ?: '';


// Cấu hình upload file
$max_UpfileSize = 20;
$total_UpfileSize = 1024 * 1024 * $max_UpfileSize; // 20MB tính bằng byte
$time_UpfileVideo = 10; // 10 phút
// Cấu hình thư mục upload
$uploadFolder = 'uploads';

// ============================================================
// MEDIA DOMAIN (Pha 0 — tách đường ĐỌC ảnh/video sang domain riêng)
// ------------------------------------------------------------
// $mediaBaseUrl: gốc URL để phục vụ file media (ảnh/video/file upload).
//   - Mặc định = baseUrl + '/uploads' để TƯƠNG THÍCH NGƯỢC (chạy y như cũ).
//   - Production: trỏ về subdomain media, vd 'https://media.paintandmore.vn'
//     (subdomain này map vào chính thư mục uploads/ trên hosting media).
// Quy ước: DB lưu path tương đối có tiền tố 'uploads/...'. Khi build URL,
// hàm to_abs_url() sẽ bỏ tiền tố 'uploads/' rồi ghép vào $mediaBaseUrl.
// Để rollback: chỉ cần để trống / xoá MEDIA_BASE_URL trong config DB.
// ============================================================
// MEDIA_MODE (nút switch khẩn cấp): 'vps' (mặc định) | 'read_origin' | 'local'.
// read_origin/local → ĐỌC ảnh từ server gốc dù MEDIA_BASE_URL vẫn lưu (để bật lại nhanh).
$mediaMode = 'vps';
if (function_exists('app_get_config_value_by_path')) {
    $rawMode = strtolower(trim((string)app_get_config_value_by_path('MEDIA_MODE')));
    if (in_array($rawMode, ['vps', 'read_origin', 'local'], true)) {
        $mediaMode = $rawMode;
    }
}

$mediaBaseUrl = '';
// Chỉ đọc từ media domain khi mode = vps.
if ($mediaMode === 'vps' && function_exists('app_get_config_value_by_path')) {
    $mediaBaseUrl = trim((string)app_get_config_value_by_path('MEDIA_BASE_URL'));
}

// Cờ: có GIỮ tiền tố 'uploads/' trong URL media không?
//   - true  (mặc định): URL = mediaBaseUrl + '/uploads/<path>'
//       → dùng khi subdomain map vào THƯ MỤC CHA (bên trong có uploads/).
//   - false: URL = mediaBaseUrl + '/<path>' (bỏ 'uploads/')
//       → dùng khi subdomain map THẲNG vào thư mục uploads/.
$mediaKeepUploadsPrefix = true;
if (function_exists('app_get_config_value_by_path')) {
    $rawKeep = app_get_config_value_by_path('MEDIA_KEEP_UPLOADS_PREFIX');
    if (is_bool($rawKeep)) {
        $mediaKeepUploadsPrefix = $rawKeep;
    } elseif ($rawKeep !== null && $rawKeep !== '') {
        $mediaKeepUploadsPrefix = in_array(strtolower(trim((string)$rawKeep)), ['1', 'true', 'yes', 'on'], true);
    }
}

if ($mediaBaseUrl === '') {
    // Fallback an toàn: phục vụ ngay từ server chính như trước khi tách domain.
    // Ở chế độ này luôn dùng baseUrl + '/uploads' và GIỮ nguyên path đầy đủ.
    $mediaBaseUrl = rtrim((string)$baseUrl, '/');
    $mediaKeepUploadsPrefix = true;
}
$mediaBaseUrl = rtrim($mediaBaseUrl, '/');

// ============================================================
// MEDIA UPLOAD REMOTE (Pha 2 — đẩy file upload lên VPS media)
// ------------------------------------------------------------
// Khi bật, file sau khi xử lý xong (resize/WebP) ở local sẽ được ĐẨY lên VPS
// qua receiver.php rồi XOÁ bản local. Đọc ảnh vẫn theo $mediaBaseUrl ở trên.
//   - MEDIA_REMOTE_ENABLED : '1' để bật.
//   - MEDIA_RECEIVER_URL    : URL receiver, vd https://media.paintandmore.vn/receiver.php
//   - MEDIA_SECRET          : chuỗi bí mật chung 2 bên để ký HMAC.
// Tắt (mặc định) → ghi local như cũ, không ảnh hưởng gì.
// ============================================================
$mediaRemoteEnabled = false;
$mediaReceiverUrl = '';
$mediaSecret = '';
if (function_exists('app_get_config_value_by_path')) {
    $rawEnabled = app_get_config_value_by_path('MEDIA_REMOTE_ENABLED');
    // Key này khai báo type=bool nên có thể trả về boolean thật hoặc chuỗi '1'/'true'.
    if (is_bool($rawEnabled)) {
        $mediaRemoteEnabled = $rawEnabled;
    } elseif ($rawEnabled !== null && $rawEnabled !== '') {
        $mediaRemoteEnabled = in_array(strtolower(trim((string)$rawEnabled)), ['1', 'true', 'yes', 'on'], true);
    }
    $mediaReceiverUrl = trim((string)app_get_config_value_by_path('MEDIA_RECEIVER_URL'));
    $mediaSecret = trim((string)app_get_config_value_by_path('MEDIA_SECRET'));
}
// An toàn: thiếu URL hoặc secret thì tắt remote để không làm hỏng upload.
if ($mediaReceiverUrl === '' || $mediaSecret === '') {
    $mediaRemoteEnabled = false;
}
// Chỉ đẩy upload lên VPS ở chế độ vps; read_origin/local → ghi local.
if ($mediaMode !== 'vps') {
    $mediaRemoteEnabled = false;
}

// Tính URL fallback SAU khi $mediaBaseUrl đã sẵn sàng → route đúng media domain.
// (idempotent: nếu đã là URL tuyệt đối thì giữ nguyên).
$fallbackUrl = function_exists('to_abs_url')
    ? to_abs_url((string) $fallbackPath, (string) $baseUrl)
    : (rtrim((string) $baseUrl, '/') . '/' . ltrim((string) $fallbackPath, '/'));
$fallbackImage = $fallbackUrl;

// Các function xử lý logic liên quan đến checkout sẽ được định nghĩa ở đây, bao gồm cả các function chung cho nhiều action khác nhau (như formatPhoneVN, voucherAllowsPayment, v.v.) để tránh trùng lặp code giữa các action.
$paymentMethods = [];
if (isset($ECOMMERCE_PAYMENT_METHODS) && is_array($ECOMMERCE_PAYMENT_METHODS)) {
    foreach ($ECOMMERCE_PAYMENT_METHODS as $key => $cfg) {
        if (!is_array($cfg)) continue;
        if (!empty($cfg['enabled'])) {
            $rawLabel = (string)($cfg['label'] ?? $key);
            $normalizedLabel = $rawLabel;
            if ((string)$key === 'cod') {
                $normalizedLabel = 'Thanh toán khi nhận hàng';
            } elseif ((string)$key === 'vnpay') {
                $normalizedLabel = 'VNpay';
            } elseif ((string)$key === 'momo') {
                $normalizedLabel = 'Momo';
            } elseif ((string)$key === 'zalopay') {
                $normalizedLabel = 'Zalopay';
            }
            $paymentMethods[] = [
                'key' => (string)$key,
                'label' => $normalizedLabel,
            ];
        }
    }
}
if (!$paymentMethods) {
    $paymentMethods = [['key' => 'cod', 'label' => 'Thanh toán khi nhận hàng']];
}

$paymentLabelMap = [];
foreach ($paymentMethods as $m) {
    $paymentLabelMap[$m['key']] = $m['label'];
}

?>