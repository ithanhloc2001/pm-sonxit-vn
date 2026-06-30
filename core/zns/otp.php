<?php
require_once __DIR__ . '/../../config.php'; // load DB, session, functions.php, helpers
require_once __DIR__ . '/conf.php';          // load $zalo_config, $templateId

// --- Kiểm tra phụ thuộc cURL ---
if (!function_exists('curl_init')) {
    jOut(['success' => false, 'message' => 'Máy chủ không hỗ trợ cURL, không thể gửi OTP.'], 500);
}

// --- Chỉ chấp nhận POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jOut(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], 405);
}

$action = trim((string)($_POST['action'] ?? 'send'));

// --- Kiểm tra cấu hình Zalo ---
if (!isset($zalo_config) || !is_array($zalo_config)) {
    jOut(['success' => false, 'message' => 'Thiếu cấu hình Zalo OA.'], 500);
}

$appId        = trim((string)($zalo_config['app_id']      ?? ''));
$secretKey    = trim((string)($zalo_config['secret_key']  ?? ''));
$accessToken  = trim((string)($zalo_config['accessToken'] ?? ''));
$refreshToken = trim((string)($zalo_config['refreshToken'] ?? ''));

if ($accessToken === '' || $appId === '' || $secretKey === '') {
    jOut(['success' => false, 'message' => 'Cấu hình Zalo OA chưa đầy đủ.'], 500);
}

$otpTemplateId = trim((string)(is_array($templateId ?? null) ? ($templateId['OTP'] ?? '') : ''));
if ($otpTemplateId === '') {
    $otpTemplateId = '501209';
}

const ZNS_ENDPOINT = 'https://business.openapi.zalo.me/message/template';

// ============================================================
// Helper functions (ZNS-scoped)
// ============================================================

/**
 * Chuẩn hoá số điện thoại VN về dạng 0xxxxxxxxx.
 */
function normalize_phone_otp(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null || $digits === '') return '';

    // Bỏ tiền tố quốc tế 00 (0084xxx → 84xxx)
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    // 84xxxxxxxxx → 0xxxxxxxxx
    if (strpos($digits, '84') === 0 && strlen($digits) > 2) {
        $digits = '0' . substr($digits, 2);
    }
    // Thêm 0 nếu thiếu
    if (strpos($digits, '0') !== 0) {
        $digits = '0' . $digits;
    }
    return $digits;
}

/**
 * Kiểm tra số điện thoại VN hợp lệ (dạng 0xxxxxxxxx).
 */
function is_valid_vn_phone_otp(string $phone): bool {
    return $phone !== '' && strpos($phone, '0') === 0 && strlen($phone) >= 9 && strlen($phone) <= 11;
}

/**
 * Gọi Zalo Business API (POST JSON).
 */
function zalo_call_api_otp(string $endpoint, string $token, array $payload): array {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'access_token: ' . $token,
        ],
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    return ['http' => $httpCode, 'raw' => $raw, 'error' => $err];
}

/**
 * Refresh Zalo access token.
 */
function zalo_refresh_token_otp(string $refreshToken, string $appId, string $secretKey): array {
    if ($refreshToken === '' || $appId === '' || $secretKey === '') return [];

    $ch = curl_init('https://oauth.zaloapp.com/v4/oa/access_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'refresh_token' => $refreshToken,
            'app_id'        => $appId,
            'grant_type'    => 'refresh_token',
        ]),
        CURLOPT_HTTPHEADER => ['secret_key: ' . $secretKey],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Gửi OTP qua Zalo ZNS. Tự động refresh token nếu hết hạn (error -190).
 */
function zalo_send_otp_message(
    string $endpoint,
    string $accessToken,
    string $refreshToken,
    string $appId,
    string $secretKey,
    string $templateId,
    string $phone,
    string $otp
): array {
    $payload = [
        'phone'         => $phone,
        'template_id'   => $templateId,
        'template_data' => ['otp' => $otp],
    ];

    $resp = zalo_call_api_otp($endpoint, $accessToken, $payload);
    $data = json_decode((string)($resp['raw'] ?? ''), true);

    // Token hết hạn → refresh rồi gửi lại
    if (isset($data['error']) && (int)$data['error'] === -190) {
        $newTokens = zalo_refresh_token_otp($refreshToken, $appId, $secretKey);
        if (isset($newTokens['access_token'])) {
            $resp = zalo_call_api_otp($endpoint, (string)$newTokens['access_token'], $payload);
            $data = json_decode((string)($resp['raw'] ?? ''), true);
            $resp['refreshed_access_token'] = (string)$newTokens['access_token'];
            if (isset($newTokens['refresh_token'])) {
                $resp['refreshed_refresh_token'] = (string)$newTokens['refresh_token'];
            }
        }
    }

    $resp['data'] = is_array($data) ? $data : [];
    $resp['ok']   = isset($data['error']) && (int)$data['error'] === 0;
    return $resp;
}

/**
 * Gửi email OTP qua SMTP (Gmail) bằng PHPMailer.
 * Trả về ['ok' => bool, 'error' => string].
 */
function otp_send_email_smtp(array $smtp, string $toEmail, string $otp): array {
    global $site_title, $baseUrl;

    $siteTitle = (isset($site_title) && $site_title !== '') ? $site_title : 'Paint & More';

    // Site URL: ưu tiên $baseUrl, fallback về cấu hình SITE_URL (settings).
    $siteUrl = isset($baseUrl) ? trim((string)$baseUrl) : '';
    if ($siteUrl === '' && function_exists('app_get_config_value_by_path')) {
        $siteUrl = trim((string)app_get_config_value_by_path('SITE_URL'));
    }
    $siteUrl = rtrim($siteUrl, '/');

    $otpMinutes = 5;
    if (function_exists('app_get_config_value_by_path')) {
        $configMin = app_get_config_value_by_path('OTP_TIMEOUT_MINUTES');
        if ($configMin !== null) {
            $otpMinutes = (int)$configMin;
        }
    }

    // Dựng nội dung HTML từ template (nếu có); luôn kèm bản plain text.
    $html = '';
    $templatePath = __DIR__ . '/email_html/otp.html';
    if (is_file($templatePath)) {
        $html = (string)file_get_contents($templatePath);
        $html = str_replace(
            ['{{SITE_TITLE}}', '{{OTP_CODE}}', '{{EXPIRE_MINUTES}}', '{{YEAR}}', '{{SITE_URL}}'],
            [$siteTitle, $otp, (string)$otpMinutes, date('Y'), $siteUrl],
            $html
        );
    }

    $altBody =
        "Mã xác thực OTP của bạn là: {$otp}\r\n\r\n" .
        "Mã này có hiệu lực trong vòng {$otpMinutes} phút.\r\n" .
        "Vui lòng không chia sẻ mã này với bất kỳ ai.";

    return app_send_smtp_mail($smtp, $toEmail, 'Mã xác thực OTP - Paint & More', $html, $altBody);
}

// ============================================================
// Action: send
// ============================================================
if ($action === 'send') {
    // Lớp bảo vệ input: loại null-byte/ký tự điều khiển, giới hạn độ dài (chống XSS lưu trữ + oversize)
    $phoneRaw = clean_input($_POST['phone'] ?? '', 254);
    if ($phoneRaw === '') {
        jOut(['success' => false, 'message' => 'Vui lòng nhập số điện thoại hoặc email.'], 400);
    }

    $ip          = get_client_ip(); // helper từ functions.php
    // Chỉ nới rate-limit ở môi trường dev tường minh (APP_IS_DEV từ config.php).
    // KHÔNG dựa vào PHP_OS để tránh vô hiệu hoá rate-limit khi chạy production trên Windows Server.
    $isLocalhost = defined('APP_IS_DEV') && APP_IS_DEV;
    $isEmail     = filter_var($phoneRaw, FILTER_VALIDATE_EMAIL) !== false;
    $phone       = '';

    if ($isEmail) {
        // Chuẩn hoá + validate lại email (chống header injection, ép chữ thường) trước khi lưu session
        $phone = clean_email($phoneRaw, 254);
        if ($phone === '') {
            jOut(['success' => false, 'message' => 'Email không hợp lệ.'], 400);
        }
    } else {
        $phone = normalize_phone_otp($phoneRaw);
        if (!is_valid_vn_phone_otp($phone)) {
            jOut(['success' => false, 'message' => 'Số điện thoại không hợp lệ.'], 400);
        }
    }

    // --- Rate limiting (chống spam) ---
    if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
        $ithanhloc->query("CREATE TABLE IF NOT EXISTS `log_otp` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `phone`      VARCHAR(100) NOT NULL,
            `ip`         VARCHAR(45)  NOT NULL,
            `action`     VARCHAR(20)  NOT NULL,
            `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX (`phone`), INDEX (`ip`), INDEX (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Đảm bảo cột phone có độ dài đủ lớn (100) để lưu email
        $ithanhloc->query("ALTER TABLE `log_otp` MODIFY COLUMN `phone` VARCHAR(100) NOT NULL");

        $today = date('Y-m-d');

        // Bỏ qua hoặc tăng giới hạn nếu chạy ở localhost phục vụ kiểm thử
        $cooldownSeconds = $isLocalhost ? 2 : 60;
        $maxIpDaily = $isLocalhost ? 100 : 5;
        $maxPhoneDaily = $isLocalhost ? 100 : 5;

        // Cooldown theo SĐT/Email
        $stmt = $ithanhloc->prepare("SELECT COUNT(*) FROM log_otp WHERE phone = ? AND action = 'send' AND created_at > (NOW() - INTERVAL ? SECOND)");
        $stmt->bind_param('si', $phone, $cooldownSeconds);
        $stmt->execute();
        $stmt->bind_result($recentPhoneCount);
        $stmt->fetch();
        $stmt->close();
        if ($recentPhoneCount > 0) {
            jOut(['success' => false, 'message' => "Vui lòng đợi {$cooldownSeconds} giây trước khi yêu cầu mã mới."], 429);
        }

        // Tối đa số lần/ngày theo IP
        $stmt = $ithanhloc->prepare("SELECT COUNT(*) FROM log_otp WHERE ip = ? AND action = 'send' AND DATE(created_at) = ?");
        $stmt->bind_param('ss', $ip, $today);
        $stmt->execute();
        $stmt->bind_result($ipDailyCount);
        $stmt->fetch();
        $stmt->close();
        if ($ipDailyCount >= $maxIpDaily) {
            jOut(['success' => false, 'message' => 'Yêu cầu gửi mã OTP đã vượt giới hạn cho phép trong ngày. Vui lòng thử lại sau.'], 429);
        }

        // Tối đa số lần/ngày theo SĐT/Email
        $stmt = $ithanhloc->prepare("SELECT COUNT(*) FROM log_otp WHERE phone = ? AND action = 'send' AND DATE(created_at) = ?");
        $stmt->bind_param('ss', $phone, $today);
        $stmt->execute();
        $stmt->bind_result($phoneDailyCount);
        $stmt->fetch();
        $stmt->close();
        if ($phoneDailyCount >= $maxPhoneDaily) {
            jOut(['success' => false, 'message' => $isEmail ? 'Email này đã nhận quá số lượng mã OTP cho phép trong ngày.' : 'Số điện thoại này đã nhận quá số lượng mã OTP cho phép trong ngày.'], 429);
        }

        // Ghi log
        $stmt = $ithanhloc->prepare("INSERT INTO log_otp (phone, ip, action) VALUES (?, ?, 'send')");
        $stmt->bind_param('ss', $phone, $ip);
        $stmt->execute();
        $stmt->close();
    }

    // --- Xác thực reCAPTCHA ---
    $recaptchaToken  = trim((string)($_POST['g-recaptcha-response'] ?? ''));
    $recaptchaSecret = trim((string)($GOOGLE_RECAPTCHA['secret_key'] ?? ''));

    if ($recaptchaToken === '') {
        jOut(['success' => false, 'message' => 'Vui lòng xác nhận bạn không phải là robot.'], 400);
    }

    $verifyUrl  = 'https://www.google.com/recaptcha/api/siteverify';
    $chV        = curl_init($verifyUrl);
    curl_setopt_array($chV, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['secret' => $recaptchaSecret, 'response' => $recaptchaToken]),
    ]);
    $verifyResp = curl_exec($chV);
    curl_close($chV);

    $verifyData = json_decode((string)$verifyResp, true);
    if (empty($verifyData['success']) || (isset($verifyData['score']) && (float)$verifyData['score'] < 0.5)) {
        jOut(['success' => false, 'message' => 'Xác thực reCAPTCHA thất bại hoặc bị nghi ngờ là bot.'], 400);
    }

    // --- Sinh OTP (chỉ lưu session SAU khi gửi thành công để không để OTP "treo") ---
    $otp = (string) random_int(100000, 999999);
    $otpData = [
        'phone'      => $phone,
        'code'       => $otp,
        'created_at' => time(),
        'expires_at' => time() + 300,
        'attempts'   => 0, // số lần nhập sai (chống brute-force)
    ];

    if ($isEmail) {
        // Gửi OTP qua email bằng SMTP (Gmail)
        global $SMTP_CONFIG;
        $smtpCfg = (isset($SMTP_CONFIG) && is_array($SMTP_CONFIG)) ? $SMTP_CONFIG : [];

        $sendRes = otp_send_email_smtp($smtpCfg, $phone, $otp);
        if (!($sendRes['ok'] ?? false)) {
            error_log('[OTP][SMTP] Gửi email thất bại tới ' . $phone . ': ' . ($sendRes['error'] ?? ''));
            jOut(['success' => false, 'message' => 'Không thể gửi email OTP lúc này. Vui lòng thử lại sau.']);
        }
        $_SESSION['otp_data'] = $otpData;
        jOut(['success' => true, 'message' => 'Đã gửi mã OTP. Vui lòng kiểm tra hộp thư email của bạn.', 'expires_in' => 300]);
    } else {
        // Gửi OTP qua Zalo ZNS (dạng 84xxxxxxxxx)
        $phone84 = '84' . substr($phone, 1);
        $resp    = zalo_send_otp_message(ZNS_ENDPOINT, $accessToken, $refreshToken, $appId, $secretKey, $otpTemplateId, $phone84, $otp);

        if (!($resp['ok'] ?? false)) {
            $msg = !empty($resp['data']['message']) ? (string)$resp['data']['message'] : 'Không thể gửi OTP. Vui lòng thử lại.';
            jOut(['success' => false, 'message' => $msg]);
        }

        $_SESSION['otp_data'] = $otpData;
        jOut(['success' => true, 'message' => 'Đã gửi mã OTP. Vui lòng kiểm tra tin nhắn Zalo.', 'expires_in' => 300]);
    }
}

// ============================================================
// Action: verify
// ============================================================
if ($action === 'verify') {
    // Chỉ nhận tối đa 6 chữ số; loại mọi ký tự khác (chống injection/oversize)
    $otpInput = clean_phone_digits($_POST['otp'] ?? '');
    $otpInput = substr($otpInput, 0, 6);
    if ($otpInput === '') {
        jOut(['success' => false, 'message' => 'Vui lòng nhập mã OTP.'], 400);
    }

    if (!isset($_SESSION['otp_data']) || !is_array($_SESSION['otp_data'])) {
        jOut(['success' => false, 'message' => 'Không tìm thấy yêu cầu OTP. Vui lòng gửi lại.'], 400);
    }

    $otpData = $_SESSION['otp_data'];
    if (time() > (int)($otpData['expires_at'] ?? 0)) {
        unset($_SESSION['otp_data']);
        jOut(['success' => false, 'message' => 'Mã OTP đã hết hạn. Vui lòng gửi lại.'], 400);
    }

    // Chống brute-force: tối đa 5 lần nhập sai cho mỗi mã OTP.
    $maxAttempts = 5;
    $attempts = (int)($otpData['attempts'] ?? 0);
    if ($attempts >= $maxAttempts) {
        unset($_SESSION['otp_data']);
        jOut(['success' => false, 'message' => 'Bạn đã nhập sai quá số lần cho phép. Vui lòng gửi lại mã OTP mới.'], 429);
    }

    if (!hash_equals((string)($otpData['code'] ?? ''), $otpInput)) {
        $_SESSION['otp_data']['attempts'] = $attempts + 1;
        $remaining = $maxAttempts - ($attempts + 1);
        $suffix = $remaining > 0 ? " Bạn còn {$remaining} lần thử." : ' Vui lòng gửi lại mã OTP mới.';
        jOut(['success' => false, 'message' => 'Mã OTP không chính xác.' . $suffix], 400);
    }

    unset($_SESSION['otp_data']);
    $_SESSION['otp_verified']       = true;
    $_SESSION['otp_verified_phone'] = (string)($otpData['phone'] ?? '');
    $_SESSION['otp_verified_at']    = time(); // dùng để giới hạn thời gian tiêu thụ phía login.php

    jOut(['success' => true, 'message' => 'Xác nhận OTP thành công.']);
}

jOut(['success' => false, 'message' => 'Hành động không hợp lệ.'], 400);
