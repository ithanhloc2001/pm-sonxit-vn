<?php
if (isset($_SESSION['user_id'])) {
    $redirectBaseUrl = trim((string)($baseUrl ?? ''));
    if ($redirectBaseUrl === '') {
         $redirectBaseUrl = '/';
    }
    
    // Nếu là AJAX request mà đã đăng nhập, trả về JSON thành công luôn
    $__isAjaxRequest = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (($_POST['is_ajax'] ?? '') === '1')
    );
    if ($__isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Bạn đã đăng nhập.', 'redirect' => $redirectBaseUrl]);
        exit;
    }

    exit('<script type="text/javascript">window.location.href = "'.$redirectBaseUrl.'";</script>');
}

$__isDirectLoginPhp = isset($_SERVER['SCRIPT_NAME']) && preg_match('~/main/login\.php$~i', (string)$_SERVER['SCRIPT_NAME']);
$__isAjaxRequest = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (($_POST['is_ajax'] ?? '') === '1')
);
if ($__isDirectLoginPhp && !$__isAjaxRequest && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
    $loginUrl = rtrim((string)($baseUrl ?? ''), '/') . '/login';
    $qs = isset($_SERVER['QUERY_STRING']) ? trim((string)$_SERVER['QUERY_STRING']) : '';
    if ($qs !== '') {
        $loginUrl .= (strpos($loginUrl, '?') === false ? '?' : '&') . $qs;
    }
    header('Location: ' . $loginUrl);
    exit;
}

$login_error = '';
$register_msg = '';
$googleLoginConfig = $GOOGLE_LOGIN ?? [];
$googleClientId = trim($googleLoginConfig['client_id'] ?? '');
$googleLoginEnabled = !empty($googleLoginConfig['enabled']) && $googleClientId !== '' && stripos($googleClientId, 'YOUR_GOOGLE_CLIENT_ID') === false;
$zaloLoginConfig = $ZALO_LOGIN ?? [];
$zaloLoginAuthUrl = trim((string)($zaloLoginConfig['auth_url'] ?? ''));
$zaloLoginEnabled = !empty($zaloLoginConfig['enabled']) && $zaloLoginAuthUrl !== '';
$authAjaxEndpoint = rtrim((string)($baseUrl ?? ''), '/') . '/login/';
$otpEndpoint = rtrim((string)($baseUrl ?? ''), '/') . '/core/zns/otp.php';

// Nếu có lỗi trả về từ Zalo OAuth (qua tham số zalo_error), hiển thị lên khung lỗi đăng nhập
if (isset($_GET['zalo_error']) && $login_error === '') {
    $login_error = (string)$_GET['zalo_error'];
}

function auth_str($v): string {
    return trim((string)($v ?? ''));
}

function auth_phone_normalize(string $raw): string {
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    if (strpos($digits, '84') === 0) return $digits;
    if (strpos($digits, '0') === 0) return '84' . substr($digits, 1);
    return $digits;
}

function auth_phone_variants(string $normalized): array {
    $p84 = $normalized;
    $p0 = $normalized;
    if (strpos($normalized, '84') === 0 && strlen($normalized) > 2) $p0 = '0' . substr($normalized, 2);
    if (strpos($normalized, '0') === 0 && strlen($normalized) > 1) $p84 = '84' . substr($normalized, 1);
    return [$p84, $p0];
}

function auth_username_exists(mysqli $db, string $username): bool {
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res ? ($res->num_rows > 0) : false;
    $stmt->close();
    return $exists;
}

function auth_generate_username(mysqli $db, string $seed): string {
    $seed = strtolower(preg_replace('/[^a-z0-9_]/', '', $seed));
    if ($seed === '') $seed = 'user';
    $base = substr($seed, 0, 20);
    $candidate = $base;
    for ($i = 0; $i < 50 && auth_username_exists($db, $candidate); $i++) {
        $suffix = (string)random_int(10, 9999);
        $candidate = substr($base, 0, max(1, 20 - strlen($suffix))) . $suffix;
    }
    if (auth_username_exists($db, $candidate)) {
        $candidate = 'u' . substr(md5((string)microtime(true)), 0, 10);
    }
    return $candidate;
}

function auth_finalize_login_session(array $user): void {
    $_SESSION['user_id'] = (int)($user['id'] ?? 0);
    $_SESSION['role'] = (string)($user['role'] ?? 'user');
    $_SESSION['user_name'] = (string)($user['username'] ?? '');
    $phone = (string)($user['phone'] ?? '');
    if ($phone !== '') {
        $_SESSION['user_phone'] = $phone;
        $_SESSION['phone'] = $phone;
    }
}

function auth_get_client_ua(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function auth_detect_device_label(string $ua): string {
    $u = strtolower($ua);
    if ($u === '') return 'Unknown';
    foreach (['iphone' => 'iPhone', 'ipad' => 'iPad', 'android' => 'Android', 'windows' => 'Windows', 'macintosh' => 'Mac', 'mac os' => 'Mac', 'linux' => 'Linux'] as $k => $v) {
        if (strpos($u, $k) !== false) return $v;
    }
    return 'Thiết bị khác';
}

function auth_get_last_login_info(mysqli $db, int $userId): ?array {
    if ($userId <= 0) return null;
    $stmt = $db->prepare("SELECT ip, user_agent, created_at FROM user_logs WHERE user_id=? AND action='login' ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function auth_notify_security_login_if_changed(mysqli $db, int $userId, ?array $lastLogin, string $ip, string $ua): void {
    if ($userId <= 0 || !$lastLogin) return;
    if ((string)($lastLogin['ip'] ?? '') === $ip && (string)($lastLogin['user_agent'] ?? '') === $ua) return;
    if (function_exists('app_user_notify_template')) {
        app_user_notify_template($db, $userId, 'security_login', [
            'ip' => $ip,
            'device' => auth_detect_device_label($ua),
            'time' => date('Y-m-d H:i:s'),
        ]);
    }
}

function auth_fetch_google_token_payload(string $credential, string $endpoint): ?array {
    $url = rtrim($endpoint, '?') . '?id_token=' . urlencode($credential);
    $response = false;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $response = @file_get_contents($url);
        $status = $response !== false ? 200 : 0;
    }

    if ($status !== 200 || !$response) return null;
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function auth_get_user_by_email(mysqli $db, string $email): ?array {
    $stmt = $db->prepare('SELECT id, username, role, phone, email, full_name, avatar FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function auth_find_user_for_password_login(mysqli $db, string $login): ?array {
    $login = auth_str($login);
    if ($login === '') return null;

    $p = auth_phone_normalize($login);

    if ($p !== '') {
        [$p84, $p0] = auth_phone_variants($p);
        $stmt = $db->prepare('SELECT id, username, password, role, phone, email, full_name, avatar FROM users WHERE username=? OR email=? OR phone=? OR phone=? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('ssss', $login, $login, $p84, $p0);
    } else {
        $stmt = $db->prepare('SELECT id, username, password, role, phone, email, full_name, avatar FROM users WHERE username=? OR email=? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('ss', $login, $login);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function auth_verify_password(string $input, string $storedHash): bool {
    $input = (string)$input;
    $storedHash = (string)$storedHash;
    if ($storedHash === '') return false;
    // Chỉ chấp nhận mật khẩu đã hash. Không so sánh plaintext (tránh backdoor với dữ liệu cũ chưa hash).
    return password_verify($input, $storedHash);
}

/**
 * Sinh mật khẩu ngẫu nhiên đã hash cho tài khoản tạo qua social/OTP
 * (không dùng mật khẩu mặc định đoán được như "12345").
 */
function auth_random_password_hash(): string {
    return password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
}

function auth_verify_recaptcha(string $token, string $secret): bool {
    if ($token === '') return false;
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $resp = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['secret' => $secret, 'response' => $token]));
        $resp = curl_exec($ch);
        curl_close($ch);
    } else {
        $resp = @file_get_contents($url . '?secret=' . urlencode($secret) . '&response=' . urlencode($token));
    }
    if (!$resp) return false;
    $data = json_decode($resp, true);
    // Đối với v3, kiểm tra thành công và điểm số (score >= 0.5)
    return !empty($data['success']) && (isset($data['score']) ? (float)$data['score'] >= 0.5 : true);
}

function process_login(mysqli $db, array $input): array {
    global $GOOGLE_RECAPTCHA;
    // Lớp bảo vệ input: trim, loại null-byte/ký tự điều khiển, giới hạn độ dài.
    // username có thể là email/sđt/tên đăng nhập -> giới hạn 254 ký tự.
    $login = clean_input($input['username'] ?? '', 254);
    // Mật khẩu giữ nguyên ký tự nhưng vẫn loại null-byte và chặn oversize (chống DoS hash).
    $password = (string)($input['password'] ?? '');
    $password = str_replace("\0", '', $password);
    if (strlen($password) > 200) $password = substr($password, 0, 200);
    $recaptchaToken = (string)($input['g-recaptcha-response'] ?? '');

    if ($login === '' || $password === '') {
        return ['success' => false, 'message' => 'Vui lòng nhập tài khoản và mật khẩu.'];
    }

    // Verify reCAPTCHA
    if (!auth_verify_recaptcha($recaptchaToken, $GOOGLE_RECAPTCHA['secret_key'] ?? '')) {
        return ['success' => false, 'message' => 'Vui lòng xác nhận bạn không phải là robot.'];
    }

    $user = auth_find_user_for_password_login($db, $login);
    if (!$user || !auth_verify_password($password, (string)($user['password'] ?? ''))) {
        return ['success' => false, 'message' => 'Sai tài khoản hoặc mật khẩu'];
    }

    $userId = (int)($user['id'] ?? 0);
    $lastLogin = auth_get_last_login_info($db, $userId);
    $ip = function_exists('get_client_ip') ? get_client_ip() : '';
    $ua = auth_get_client_ua();

    auth_finalize_login_session($user);
    if (function_exists('app_user_log')) {
        app_user_log($db, $userId, 'login', 'Đăng nhập thành công', ['method' => 'password']);
    }
    auth_notify_security_login_if_changed($db, $userId, $lastLogin, $ip, $ua);

    return ['success' => true, 'message' => 'Đăng nhập thành công. Đang chuyển hướng...', 'redirect' => '/'];
}

function process_registration(mysqli $db, array $input): array {
    global $GOOGLE_RECAPTCHA;
    // Lớp bảo vệ input phía backend
    $username = clean_input($input['reg_username'] ?? '', 30);
    $password = (string)($input['reg_password'] ?? '');
    $password = str_replace("\0", '', $password);
    if (strlen($password) > 200) $password = substr($password, 0, 200);
    // clean_email: chuẩn hoá + validate, chống email-header injection
    $email = clean_email($input['reg_email'] ?? '', 254);
    $recaptchaToken = (string)($input['g-recaptcha-response'] ?? '');

    if ($username === '' || $password === '' || trim((string)($input['reg_email'] ?? '')) === '') {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        return ['success' => false, 'message' => 'Tên đăng nhập không hợp lệ (3-30 ký tự, chỉ gồm chữ cái không dấu, số, dấu gạch dưới).'];
    }

    // Verify reCAPTCHA
    if (!auth_verify_recaptcha($recaptchaToken, $GOOGLE_RECAPTCHA['secret_key'] ?? '')) {
        return ['success' => false, 'message' => 'Vui lòng xác nhận bạn không phải là robot.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email không hợp lệ.'];
    }
    if (auth_username_exists($db, $username)) {
        return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại.'];
    }
    if (auth_get_user_by_email($db, $email)) {
        return ['success' => false, 'message' => 'Email đã được sử dụng.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $fullName = '';
    $phone = '';
    $avatar = '';
    $role = 'user';
    $createdAt = date('Y-m-d H:i:s');
    $balance = 0;

    $stmt = $db->prepare('INSERT INTO users (username, full_name, password, phone, email, avatar, role, created_at, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Không thể đăng ký lúc này.'];
    }
    $stmt->bind_param('ssssssssi', $username, $fullName, $hash, $phone, $email, $avatar, $role, $createdAt, $balance);
    try {
        $ok = $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        // Trùng do UNIQUE index (race condition) -> báo thân thiện
        if (stripos($e->getMessage(), 'uk_users_email') !== false) {
            return ['success' => false, 'message' => 'Email đã được sử dụng. Vui lòng dùng email khác.'];
        }
        if (stripos($e->getMessage(), 'uk_users_phone') !== false) {
            return ['success' => false, 'message' => 'Số điện thoại đã được sử dụng. Vui lòng dùng số khác.'];
        }
        return ['success' => false, 'message' => 'Đăng ký thất bại. Vui lòng thử lại.'];
    }
    $newUserId = (int)$db->insert_id;
    $stmt->close();

    if (!$ok || $newUserId <= 0) {
        return ['success' => false, 'message' => 'Đăng ký thất bại. Vui lòng thử lại.'];
    }

    auth_finalize_login_session(['id' => $newUserId, 'username' => $username, 'role' => $role, 'phone' => $phone]);
    if (function_exists('app_user_log')) {
        app_user_log($db, $newUserId, 'register', 'Đăng ký tài khoản', ['method' => 'password']);
    }

    return ['success' => true, 'message' => 'Đăng ký thành công. Đang chuyển hướng...', 'redirect' => '/'];
}

function auth_find_user_by_phone_for_otp(mysqli $db, string $normalizedPhone): ?array {
    [$p84, $p0] = auth_phone_variants($normalizedPhone);
    $stmt = $db->prepare('SELECT id, username, role, phone, email, full_name, avatar FROM users WHERE phone=? OR phone=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('ss', $p84, $p0);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function process_google_login(mysqli $db, array $config, array $input): array {
    $clientId = auth_str($config['client_id'] ?? '');
    $enabled = !empty($config['enabled']) && $clientId !== '' && stripos($clientId, 'YOUR_GOOGLE_CLIENT_ID') === false;
    if (!$enabled) return ['success' => false, 'message' => 'Đăng nhập Google chưa được bật.'];

    $credential = auth_str($input['credential'] ?? '');
    if ($credential === '') return ['success' => false, 'message' => 'Thiếu thông tin xác thực từ Google.'];

    $payload = auth_fetch_google_token_payload($credential, $config['verify_endpoint'] ?? 'https://oauth2.googleapis.com/tokeninfo');
    if (!$payload) return ['success' => false, 'message' => 'Không thể xác thực với Google.'];

    $aud = (string)($payload['aud'] ?? '');
    $iss = (string)($payload['iss'] ?? '');
    $allowedIssuers = $config['allowed_issuers'] ?? ['accounts.google.com', 'https://accounts.google.com'];
    $email = strtolower((string)($payload['email'] ?? ''));
    $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($aud !== $clientId || !in_array($iss, $allowedIssuers, true)) return ['success' => false, 'message' => 'Google client ID không khớp.'];
    if ($email === '' || !$emailVerified) return ['success' => false, 'message' => 'Email Google chưa được xác thực.'];

    $user = auth_get_user_by_email($db, $email);
    if (!$user && empty($config['auto_register'])) {
        return ['success' => false, 'message' => 'Email chưa tồn tại. Vui lòng đăng ký thủ công.'];
    }

    if (!$user) {
        $seed = (string)strstr($email, '@', true);
        $username = auth_generate_username($db, $seed);
        $fullName = preg_replace('/[<>\"\'=;()]/', '', auth_str($payload['name'] ?? ($payload['given_name'] ?? '')));
        $avatar = auth_str($payload['picture'] ?? '');
        // Mật khẩu ngẫu nhiên: tài khoản Google đăng nhập qua OAuth, không qua mật khẩu.
        $password = auth_random_password_hash();
        $phone = '';
        $role = 'user';
        $createdAt = date('Y-m-d H:i:s');
        $balance = 0;

        $stmt = $db->prepare('INSERT INTO users (username, full_name, password, phone, email, avatar, role, created_at, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) return ['success' => false, 'message' => 'Không thể tạo tài khoản từ Google.'];
        $stmt->bind_param('ssssssssi', $username, $fullName, $password, $phone, $email, $avatar, $role, $createdAt, $balance);
        $ok = $stmt->execute();
        $newUserId = (int)$db->insert_id;
        $stmt->close();
        if (!$ok || $newUserId <= 0) return ['success' => false, 'message' => 'Không thể tạo tài khoản từ Google.'];
        $user = ['id' => $newUserId, 'username' => $username, 'role' => $role, 'phone' => $phone];
        if (function_exists('app_user_log')) {
            app_user_log($db, $newUserId, 'register', 'Tạo tài khoản Google', ['method' => 'google']);
        }
    }

    $userId = (int)($user['id'] ?? 0);
    $lastLogin = auth_get_last_login_info($db, $userId);
    $ip = function_exists('get_client_ip') ? get_client_ip() : '';
    $ua = auth_get_client_ua();
    auth_finalize_login_session($user);
    if (function_exists('app_user_log')) {
        app_user_log($db, $userId, 'login', 'Đăng nhập Google thành công', ['method' => 'google']);
    }
    auth_notify_security_login_if_changed($db, $userId, $lastLogin, $ip, $ua);

    return ['success' => true, 'message' => 'Đăng nhập Google thành công.', 'redirect' => '/'];
}

/**
 * Kiểm tra trạng thái OTP đã xác thực trong session còn hiệu lực không.
 * Trả về '' nếu hợp lệ, hoặc chuỗi thông báo lỗi nếu không.
 * Cửa sổ tiêu thụ tối đa 5 phút kể từ lúc verify để tránh dùng lại OTP cũ.
 */
function auth_otp_verified_guard(): string {
    if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_verified_phone'])) {
        return 'Vui lòng xác thực OTP trước.';
    }
    $verifiedAt = (int)($_SESSION['otp_verified_at'] ?? 0);
    if ($verifiedAt <= 0 || (time() - $verifiedAt) > 300) {
        unset($_SESSION['otp_verified'], $_SESSION['otp_verified_phone'], $_SESSION['otp_verified_at']);
        return 'Phiên xác thực OTP đã hết hạn. Vui lòng xác thực lại.';
    }
    return '';
}

function process_login_phone_otp(mysqli $ithanhloc, array $input): array {
    $otpGuardErr = auth_otp_verified_guard();
    if ($otpGuardErr !== '') {
        return ['success' => false, 'message' => $otpGuardErr];
    }

    $verifiedTarget = (string)$_SESSION['otp_verified_phone'];
    $isEmail = filter_var($verifiedTarget, FILTER_VALIDATE_EMAIL) !== false;

    if ($isEmail) {
        $user = auth_get_user_by_email($ithanhloc, $verifiedTarget);
    } else {
        $normalizedPhone = auth_phone_normalize($verifiedTarget);
        $user = auth_find_user_by_phone_for_otp($ithanhloc, $normalizedPhone);
    }

    if (!$user) {
        return ['success' => false, 'message' => $isEmail ? 'Địa chỉ email này chưa được đăng ký tài khoản.' : 'Số điện thoại này chưa được đăng ký tài khoản.'];
    }

    $userId = (int)($user['id'] ?? 0);
    $lastLogin = auth_get_last_login_info($ithanhloc, $userId);
    $ip = function_exists('get_client_ip') ? get_client_ip() : '';
    $ua = auth_get_client_ua();

    auth_finalize_login_session($user);
    if (function_exists('app_user_log')) {
        app_user_log($ithanhloc, $userId, 'login', 'Đăng nhập bằng OTP', ['method' => $isEmail ? 'email_otp' : 'phone_otp']);
    }
    auth_notify_security_login_if_changed($ithanhloc, $userId, $lastLogin, $ip, $ua);

    unset($_SESSION['otp_data'], $_SESSION['otp_verified'], $_SESSION['otp_verified_phone'], $_SESSION['otp_verified_at']);

    return [
        'success' => true,
        'message' => 'Đăng nhập bằng OTP thành công. Đang chuyển hướng...',
        'redirect' => '/',
    ];
}

function process_register_phone_otp(mysqli $ithanhloc, array $input): array {
    $otpGuardErr = auth_otp_verified_guard();
    if ($otpGuardErr !== '') {
        return ['success' => false, 'message' => $otpGuardErr];
    }

    $verifiedTarget = (string)$_SESSION['otp_verified_phone'];
    $isEmail = filter_var($verifiedTarget, FILTER_VALIDATE_EMAIL) !== false;

    if ($isEmail) {
        $existing = auth_get_user_by_email($ithanhloc, $verifiedTarget);
    } else {
        $normalizedPhone = auth_phone_normalize($verifiedTarget);
        $existing = auth_find_user_by_phone_for_otp($ithanhloc, $normalizedPhone);
    }

    if ($existing) {
        $userId = (int)($existing['id'] ?? 0);
        auth_finalize_login_session($existing);
        if (function_exists('app_user_log')) {
            app_user_log($ithanhloc, $userId, 'login', 'Đăng nhập bằng OTP (tài khoản đã tồn tại)', ['method' => $isEmail ? 'email_otp' : 'phone_otp']);
        }

        unset($_SESSION['otp_data'], $_SESSION['otp_verified'], $_SESSION['otp_verified_phone'], $_SESSION['otp_verified_at']);

        return [
            'success' => true,
            'message' => $isEmail ? 'Email đã có tài khoản, bạn đã được đăng nhập.' : 'Số điện thoại đã có tài khoản, bạn đã được đăng nhập.',
            'redirect' => '/',
        ];
    }

    if ($isEmail) {
        $seed = (string)strstr($verifiedTarget, '@', true);
        $username = auth_generate_username($ithanhloc, $seed);
        $phone = '';
        $email = $verifiedTarget;
    } else {
        $normalizedPhone = auth_phone_normalize($verifiedTarget);
        $username = auth_generate_username($ithanhloc, 'p' . substr($normalizedPhone, -6));
        $phone = $normalizedPhone;
        $email = '';
    }

    // Mật khẩu ngẫu nhiên: tài khoản tạo qua OTP, người dùng đặt mật khẩu thật qua "quên mật khẩu" nếu cần.
    $hash = auth_random_password_hash();
    $fullName = '';
    $avatar = '';
    $role = 'user';
    $createdAt = date('Y-m-d H:i:s');
    $balance = 0;

    $stmt = $ithanhloc->prepare('INSERT INTO users (username, full_name, password, phone, email, avatar, role, created_at, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return ['success' => false, 'message' => 'Không thể đăng ký tài khoản mới. Vui lòng thử lại.'];
    $stmt->bind_param('ssssssssi', $username, $fullName, $hash, $phone, $email, $avatar, $role, $createdAt, $balance);
    try {
        $ok = $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        if (stripos($e->getMessage(), 'uk_users_phone') !== false) {
            return ['success' => false, 'message' => 'Số điện thoại đã được sử dụng. Vui lòng dùng số khác.'];
        }
        if (stripos($e->getMessage(), 'uk_users_email') !== false) {
            return ['success' => false, 'message' => 'Email đã được sử dụng. Vui lòng dùng email khác.'];
        }
        return ['success' => false, 'message' => 'Không thể đăng ký tài khoản mới. Vui lòng thử lại.'];
    }
    $newUserId = (int)$ithanhloc->insert_id;
    $stmt->close();

    if (!$ok || $newUserId <= 0) {
        return ['success' => false, 'message' => 'Không thể đăng ký tài khoản mới. Vui lòng thử lại.'];
    }

    $newUser = [
        'id' => $newUserId,
        'username' => $username,
        'role' => 'user',
        'phone' => $phone,
        'email' => $email,
    ];

    auth_finalize_login_session($newUser);
    if (function_exists('app_user_log')) {
        app_user_log($ithanhloc, $newUserId, 'register', 'Đăng ký tài khoản bằng OTP', ['method' => $isEmail ? 'email_otp' : 'phone_otp']);
    }

    unset($_SESSION['otp_data'], $_SESSION['otp_verified'], $_SESSION['otp_verified_phone'], $_SESSION['otp_verified_at']);

    return [
        'success' => true,
        'message' => 'Đăng ký tài khoản mới thành công. Đang chuyển hướng...',
        'redirect' => '/',
    ];
}

function process_forgot_password(mysqli $db, array $input): array {
    global $GOOGLE_RECAPTCHA;
    $email = clean_email($input['forgot_email'] ?? '', 254);
    if ($email === '') {
        return ['success' => false, 'message' => 'Email không hợp lệ.'];
    }

    // Verify reCAPTCHA
    $recaptchaToken = (string)($input['g-recaptcha-response'] ?? '');
    if (!auth_verify_recaptcha($recaptchaToken, $GOOGLE_RECAPTCHA['secret_key'] ?? '')) {
        return ['success' => false, 'message' => 'Vui lòng xác nhận bạn không phải là robot.'];
    }

    // Chống spam: Giới hạn tối đa 1 lần yêu cầu mỗi 60 giây
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $lastForgot = $_SESSION['last_forgot_request_time'] ?? 0;
    if (time() - $lastForgot < 60) {
        return ['success' => false, 'message' => 'Bạn thao tác quá nhanh. Vui lòng đợi ' . (60 - (time() - $lastForgot)) . ' giây trước khi gửi yêu cầu tiếp theo.'];
    }

    $_SESSION['last_forgot_request_time'] = time();

    // Thông báo trung tính để chống dò email tồn tại (user enumeration).
    $neutralMessage = 'Chúng tôi đã gửi liên kết khôi phục mật khẩu đến email của bạn. Vui lòng kiểm tra hộp thư.';

    // Email không tồn tại: trả về cùng thông báo, không tạo token / không gửi mail.
    $user = auth_get_user_by_email($db, $email);
    if (!$user) {
        return ['success' => true, 'message' => $neutralMessage];
    }

    // Tạo token ngẫu nhiên bảo mật
    $token = bin2hex(random_bytes(32));
    $resetTimeoutMinutes = 30;
    if (function_exists('app_get_config_value_by_path')) {
        $configMin = app_get_config_value_by_path('RESET_PASSWORD_TIMEOUT_MINUTES');
        if ($configMin !== null) {
            $resetTimeoutMinutes = (int)$configMin;
        }
    }
    $expires_at = date('Y-m-d H:i:s', time() + ($resetTimeoutMinutes * 60));

    // Lưu/cập nhật token vào bảng log_password_reset
    $stmt = $db->prepare('INSERT INTO log_password_reset (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.'];
    }
    $stmt->bind_param('sss', $email, $token, $expires_at);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.'];
    }

    // Tạo liên kết khôi phục mật khẩu
    global $baseUrl, $site_title, $SMTP_CONFIG;
    $siteUrl = isset($baseUrl) ? rtrim($baseUrl, '/') : 'https://sonxit.vn';
    $resetLink = $siteUrl . '/reset-password?email=' . urlencode($email) . '&token=' . $token;

    // Gửi email HTML khôi phục mật khẩu
    $smtpCfg = (isset($SMTP_CONFIG) && is_array($SMTP_CONFIG)) ? $SMTP_CONFIG : [];
    $sendRes = auth_send_reset_password_email_smtp($smtpCfg, $email, $resetLink);

    if (!$sendRes['ok']) {
        error_log('[ForgotPass][SMTP] Gửi email thất bại tới ' . $email . ': ' . $sendRes['error']);
        return ['success' => false, 'message' => 'Không thể gửi email khôi phục lúc này. Vui lòng thử lại sau.'];
    }

    return ['success' => true, 'message' => $neutralMessage];
}

function auth_send_reset_password_email_smtp(array $smtp, string $toEmail, string $resetLink): array {
    global $site_title, $baseUrl;

    $siteTitle = (isset($site_title) && $site_title !== '') ? $site_title : 'Paint & More';
    $siteUrl   = isset($baseUrl) ? rtrim($baseUrl, '/') : 'https://sonxit.vn';

    $resetTimeoutMinutes = 30;
    if (function_exists('app_get_config_value_by_path')) {
        $configMin = app_get_config_value_by_path('RESET_PASSWORD_TIMEOUT_MINUTES');
        if ($configMin !== null) {
            $resetTimeoutMinutes = (int)$configMin;
        }
    }

    // Dựng nội dung HTML từ template (nếu có); luôn kèm bản plain text.
    $html = '';
    $templatePath = __DIR__ . '/../core/zns/email_html/reset_password.html';
    if (is_file($templatePath)) {
        $html = (string)file_get_contents($templatePath);
        $html = str_replace(
            ['{{SITE_TITLE}}', '{{RESET_LINK}}', '{{YEAR}}', '{{SITE_URL}}', '{{EMAIL}}', '{{EXPIRE_MINUTES}}'],
            [$siteTitle, $resetLink, date('Y'), $siteUrl, htmlspecialchars($toEmail, ENT_QUOTES), (string)$resetTimeoutMinutes],
            $html
        );
    }

    $altBody =
        "Chào bạn,\r\n\r\n" .
        "Bạn đã yêu cầu khôi phục mật khẩu tài khoản tại {$siteTitle}.\r\n" .
        "Vui lòng nhấn vào liên kết dưới đây để thiết lập mật khẩu mới:\r\n" .
        "{$resetLink}\r\n\r\n" .
        "Liên kết này có hiệu lực trong vòng {$resetTimeoutMinutes} phút.\r\n" .
        "Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.";

    return app_send_smtp_mail($smtp, $toEmail, 'Khôi phục mật khẩu tài khoản - Paint & More', $html, $altBody);
}

$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (($_POST['is_ajax'] ?? '') === '1');

if ($isAjax && isset($ithanhloc) && isset($_POST['action'])) {
    // Whitelist action — chặn giá trị lạ ngay từ đầu
    $action = clean_input($_POST['action'] ?? '', 40);
    $allowedActions = ['register', 'login', 'login_phone_otp', 'register_phone_otp', 'google-login', 'forgot-password'];
    if (!in_array($action, $allowedActions, true)) {
        jOut(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    }

    if ($action === 'register') {
        jOut(process_registration($ithanhloc, $_POST));
    }

    if ($action === 'login') {
        jOut(process_login($ithanhloc, $_POST));
    }

    if ($action === 'login_phone_otp') {
        jOut(process_login_phone_otp($ithanhloc, $_POST));
    }

    if ($action === 'register_phone_otp') {
        jOut(process_register_phone_otp($ithanhloc, $_POST));
    }

    if ($action === 'google-login') {
        jOut(process_google_login($ithanhloc, $googleLoginConfig, $_POST));
    }

    if ($action === 'forgot-password') {
        jOut(process_forgot_password($ithanhloc, $_POST));
    }

    jOut(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
}

// Đăng ký tài khoản mới (role mặc định: user)
if (isset($_POST['do_register']) && isset($ithanhloc)) {
    $registerResult = process_registration($ithanhloc, $_POST);

    if ($registerResult['success']) {
        exit('<script type="text/javascript">window.location.href = "/";</script>');
    }

    $register_msg = $registerResult['message'];
}

// Đăng nhập
if (isset($_POST['do_login']) && isset($ithanhloc)) {
    $loginResult = process_login($ithanhloc, $_POST);

    if ($loginResult['success']) {
        exit('<script type="text/javascript">window.location.href = "/";</script>');
    }

    $login_error = $loginResult['message'];
}
?>
<?php
$isEmbeddedLayout = defined('APP_EMBED_LAYOUT') && APP_EMBED_LAYOUT;
if (!$isEmbeddedLayout):
    include __DIR__ . '/../head.php';
endif;
$__recaptchaSiteKey = trim((string)($GOOGLE_RECAPTCHA['site_key'] ?? ''));
if ($__recaptchaSiteKey !== '' && !$isEmbeddedLayout):
?>
<script>
if (!document.getElementById('qaRecaptcha') && !document.getElementById('recaptchaScript')) {
    var _s = document.createElement('script');
    _s.id = 'recaptchaScript';
    _s.src = 'https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($__recaptchaSiteKey, ENT_QUOTES) ?>';
    _s.async = true; _s.defer = true;
    document.head.appendChild(_s);
}
</script>
<?php endif; ?>
<script>
window.googleAuthConfig = <?=json_encode([
    'enabled' => $googleLoginEnabled,
    'clientId' => $googleLoginEnabled ? $googleClientId : '',
    // optional: redirect_uri & scope cho flow OAuth2 code (GIS)
    'redirectUri' => $googleLoginEnabled ? trim((string)($googleLoginConfig['redirect_uri'] ?? '')) : '',
    'scope' => trim((string)($googleLoginConfig['scope'] ?? 'openid email profile')),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);?>;
window.zaloAuthConfig = <?=json_encode([
    'enabled' => $zaloLoginEnabled,
    'authUrl' => $zaloLoginEnabled ? $zaloLoginAuthUrl : '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);?>;
window.authAjaxEndpoint = <?= json_encode($authAjaxEndpoint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.otpEndpoint = <?= json_encode($otpEndpoint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.recaptchaSiteKey = <?= json_encode($GOOGLE_RECAPTCHA['site_key'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<style>
        .auth-page {
            min-height: calc(60vh - 0px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }

        .auth-card-session {
            width: 100%;
            max-width: 960px;
            border-radius: 16px;
        }
        @media (max-width: 992px) {
            .auth-card-session {
                margin-top: 60px;
            }
            .auth-panel {
                padding: 0 !important;
                margin: 0 !important;
            }
        }
        @media (max-width: 560px) {
            .auth-page { padding: 0; }
            .panel-card { padding: 0; }
        }
        .auth-panel {
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 10px 18px;
        }

        .auth-landing {
            position: relative;
            background: #020617;
            min-height: 100%;
        }

        .auth-landing-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.6;
            display: block;
            pointer-events: none;
        }
        .auth-landing-logo {
            width: 65px;
            height: auto;
            position: absolute;
            display: block;
            opacity: 1;
            top: 24px;
            left: 24px;
            z-index: 1;
            background: #ffffff;
            padding: 3px;
            border-radius: 35px;
            box-shadow: 0px 0px 10px #ffffff;
        }

        .auth-landing-overlay {
            position: absolute;
            inset: 0;
           /* background: linear-gradient(292deg, rgb(11 75 40), rgb(169 189 178));*/
            color: #f9fafb;
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .auth-landing-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
            color: #fff;
        }

        .auth-landing-sub {
            font-size: 13px;
            opacity: 0.9;
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .panel-title {
            font-size: 20px;
            font-weight: 700;
        }

        .tab-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            padding: 4px;
        }

        .tab-switch button {
            border: none;
            background: transparent;
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
            color: var(--fb-text-sub);
        }

        .tab-switch button.active {
            background: #fff;
            color: var(--theme-primary);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
        }

        .login-mode-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            padding: 4px;
            margin-bottom: 12px;
        }

        .login-mode-switch button {
            border: none;
            background: transparent;
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
            color: var(--fb-text-sub);
        }

        .login-mode-switch button.active {
            background: #fff;
            color: var(--theme-primary);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
        }

        .qr-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--theme-primary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgba(var(--theme-primary-rgb), 0.35);
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(var(--theme-primary-rgb), 0.08);
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }

        .form-control {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 12px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--theme-primary);
            box-shadow: 0 0 0 3px rgba(var(--theme-primary-rgb), 0.15);
        }

        .btn-login {
            width: 100%;
            border: none;
            border-radius: 8px;
            padding: 12px 14px;
            font-weight: 700;
            font-size: 14px;
            background: var(--theme-primary);
            color: #fff;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-login:hover { background: var(--theme-primary-dark); }

        .helper-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            margin-top: 8px;
            color: var(--fb-text-sub);
        }

        .helper-row a { color: var(--theme-primary); text-decoration: none; font-weight: 600; }

        .divider {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--fb-text-sub);
            font-size: 12px;
            margin: 14px 0 12px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #eceff3;
        }

        .social-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        .social-row > * {
            flex: 0 0 auto;
        }

        .social-btn {
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #111827;
            padding: 0;
            border-radius: 999px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s, box-shadow 0.2s, transform 0.1s, border-color 0.2s;
        }
        .social-btn:disabled {
            cursor: not-allowed;
            opacity: 0.65;
        }
        .social-btn:hover:not(:disabled) {
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }

        .social-btn.zalo-login {
            /* Giữ cùng style với nút Google (social-btn mặc định) */
            min-width: 180px;
        }

        .social-btn.zalo-login i {
            font-size: 18px;
        }
        .google-signin-placeholder {
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .google-signin-placeholder > div {
            width: 100%;
        }
        .alert-message {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .alert-message.active { display: flex; }

        .alert-error {
            background: #fff4f4;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            color: #065f46;
        }

        .btn-login.is-loading {
            opacity: 0.85;
            pointer-events: none;
        }

        .panel-sub {
            color: var(--fb-text-sub);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .login-mode-panel { display: none; }
        .login-mode-panel.active { display: block; }

        .phone-otp-row-phone {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .phone-otp-row-phone .form-control {
            flex: 1 1 auto;
        }

        .link-plain {
            border: none;
            background: none;
            padding: 0;
            font-size: 13px;
            color: var(--theme-primary);
            cursor: pointer;
            text-decoration: underline;
        }

        /* OTP confirm modal + OTP step overlay */
        .otp-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 16px;
        }

        .otp-modal {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
            border: 1px solid #e5e7eb;
            padding: 16px 16px 14px;
        }

        .otp-modal-body {
            margin-bottom: 14px;
        }

        .otp-modal-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--fb-text);
        }

        .otp-modal-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .otp-step-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 16px;
        }

        .otp-step-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
            border: 1px solid #e5e7eb;
            padding: 18px 18px 16px;
        }

        .otp-step-header {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .otp-step-sub {
            font-size: 13px;
            color: var(--fb-text-sub);
        }

        .otp-resend-text {
            font-size: 13px;
            color: var(--fb-text-sub);
        }

        .main-container-slot
            {
                flex: 0 1 700px;
                width: min(100%, 700px);
                max-width: 700px;
                min-width: 0;
            }
    </style>
    <div class="auth-page">
        <div class="auth-card-session card shadow-sm border-0 overflow-hidden">
            <div class="row g-0">
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="auth-landing h-100">
                        <img src="<?= h($baseUrl) ?>/image/paintmore.svg" alt="Không gian sơn" class="auth-landing-logo" loading="lazy" decoding="async">
                        <img src="<?= h($baseUrl) ?>/image/login-bg.webp" alt="Không gian sơn" class="auth-landing-image" loading="lazy" decoding="async">
                        <div class="auth-landing-overlay">
                            <h2 class="auth-landing-title">
                                <span>Chào mừng bạn đến với</span> <?= h($_SiteTitle) ?>
                                <span style="color:#22c55e;vertical-align:middle;" title="Đã xác thực">
                                    <i class="bi bi-patch-check-fill" style="font-size:0.72em;"></i>
                                </span>
                            </h2>
                            <p class="auth-landing-sub">Nơi cung cấp giải pháp sơn với sản phẩm chính hãng 100% sơn nhập Mỹ, bền màu cho mọi không gian.</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="auth-panel">
                            <div class="text-center">
                                <div class="tab-switch" role="tablist">
                                    <button type="button" class="auth-tab active" data-tab="login"><i class="bi bi-person"></i> <span>Đăng nhập</span></button>
                                    <button type="button" class="auth-tab" data-tab="register"><span>Đăng ký</span></button>
                                </div>
                            </div>
                            <div class="divider"></div>

                            <div class="tab-panel active" data-panel="login">
                                <div class="panel-subs panel-title mb-3"><i class="bi bi-person"></i> <span>Đăng nhập tài khoản</span></div>

                                <?php $login_class = $login_error ? 'active alert-error' : ''; ?>
                                <div id="login-message" class="alert-message <?=$login_class?>">
                                    <i class="bi <?=$login_error ? 'bi-exclamation-circle' : 'bi-info-circle'?>"></i>
                                    <span class="alert-text"><?=h($login_error ?? '')?></span>
                                </div>

                                <form method="POST" id="login-form" data-auth-form="login">
                                    <div class="form-group">
                                        <input type="text" name="username" class="form-control" placeholder="Email/Số điện thoại/Tên đăng nhập" >
                                    </div>
                                    <div class="form-group">
                                        <input type="password" name="password" class="form-control" placeholder="Mật khẩu" >
                                    </div>
                                    <button class="btn-login" name="do_login">Đăng nhập</button>
                                </form>

                                <div class="helper-row">
                                    <span><span>Quên mật khẩu?</span> <button type="button" class="link-plain text-danger fw-bold" id="loginWithOtpLink">Đăng nhập bằng OTP Zalo</button></span>
                                    <div></div>
                                </div>
                                <div class="helper-row">
                                    <span><span>Bạn mới biết đến hệ thống?</span> <a href="#" class="tab-link" data-tab="register">Đăng ký</a></span>
                                    <div></div>
                                </div>
                                <div class="divider">hoặc</div>
                                <div class="text-center">
                                    <div class="social-row">
                                        <?php if ($zaloLoginEnabled): ?>
                                        <button class="social-btn zalo-login" type="button" id="zaloLoginBtn">
                                            <i class="bi bi-chat-dots"></i>
                                            <span>Tiếp tục với Zalo</span>
                                        </button>
                                        <?php endif; ?>
                                        <div id="googleSignInButton" class="google-signin-placeholder">
                                            <?php if ($googleLoginEnabled): ?>
                                            <button class="social-btn" type="button"><i class="bi bi-google"></i> Google</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-panel" data-panel="register">
                                <div class="panel-subs panel-title mb-3"><i class="bi bi-person-plus"></i> <span>Vui lòng nhập số điện thoại</span></div>

                                <div id="phone-register-main-message" class="alert-message">
                                    <i class="bi bi-info-circle"></i>
                                    <span class="alert-text"></span>
                                </div>

                                <div class="form-group">
                                    <input type="text" id="phoneRegisterPhoneInput" class="form-control" placeholder="Số điện thoại" autocomplete="tel">
                                </div>
                                <button type="button" id="phoneRegisterNextBtn" class="btn-login" disabled>Tiếp theo</button>

                                <div class="helper-row" style="margin-top: 16px;">
                                    <span><span>Bằng việc đăng ký, bạn đồng ý với</span> <a href="#">Điều khoản dịch vụ &amp; Chính sách bảo mật</a>.</span>
                                </div>
                                <div class="divider" style="margin-top:16px;"></div>

                                <div class="helper-row">
                                    <span><span>Đã có tài khoản?</span> <a href="#" class="tab-link" data-tab="login">Đăng nhập</a></span>
                                </div>

                                <!-- Popup xác nhận gửi OTP -->
                                <div id="phoneRegisterConfirmModal" class="otp-modal-backdrop" style="display:none;">
                                    <div class="otp-modal">
                                        <div class="otp-modal-body">
                                            <div class="otp-modal-title"><span>Chúng tôi sẽ gửi mã xác minh qua Zalo đến</span> <span id="phoneRegisterConfirmPhoneLabel"></span></div>
                                            
                                            <div id="phone-register-confirm-message" class="alert-message" style="margin-top:12px;">
                                                <i class="bi bi-exclamation-circle"></i>
                                                <span class="alert-text"></span>
                                            </div>
                                        </div>
                                        <div class="otp-modal-actions">
                                            <button type="button" id="phoneRegisterConfirmCancel" class="btn-login" style="background:#e5e7eb;color:#111827;">Hủy bỏ</button>
                                            <button type="button" id="phoneRegisterConfirmSend" class="btn-login">Gửi đến Zalo</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bước nhập OTP và xác minh -->
                                <div id="phoneRegisterOtpStep" class="otp-step-overlay" style="display:none;">
                                    <div class="otp-step-card">
                                        <div class="otp-step-header">Xác minh số điện thoại</div>
                                        <div class="otp-step-sub"><span>Mã xác thực sẽ được gửi qua Zalo đến</span> <span id="phoneRegisterOtpPhoneLabel"></span></div>

                                        <div id="phone-register-otp-message" class="alert-message" style="margin-top:12px;">
                                            <i class="bi bi-info-circle"></i>
                                            <span class="alert-text"></span>
                                        </div>

                                        <div class="form-group" style="margin-top:12px;">
                                            <input type="tel" id="phoneRegisterOtpInput" class="form-control" placeholder="Nhập mã OTP" maxlength="6" autocomplete="one-time-code">
                                        </div>

                                        <div class="helper-row">
                                            <span class="otp-resend-text" id="phoneRegisterResendText">Vui lòng chờ <span class="fw-bold text-dark">60</span> giây để gửi lại.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                   
                </div>
            </div>
        </div>
    </div>

<script>
(function(){
    function init() {
        const tabs = document.querySelectorAll('.auth-tab');
        const panels = document.querySelectorAll('.tab-panel');
        const links = document.querySelectorAll('.tab-link');
        const googleConfig = window.googleAuthConfig || {};
        const zaloConfig = window.zaloAuthConfig || {};
        const authEndpoint = window.authAjaxEndpoint || '/login/';
        const otpEndpoint = window.otpEndpoint || '/core/zns/otp.php';
        const zaloLoginBtn = document.getElementById('zaloLoginBtn');
        const loginWithOtpLink = document.getElementById('loginWithOtpLink');

        function setActive(tab){
            if (!tab) return;
            tabs.forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tab));
            panels.forEach(panel => panel.classList.toggle('active', panel.dataset.panel === tab));
        }

        tabs.forEach(btn => {
            btn.addEventListener('click', () => setActive(btn.dataset.tab));
        });

        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                setActive(link.dataset.tab);
            });
        });

        if (loginWithOtpLink) {
            loginWithOtpLink.addEventListener('click', (e) => {
                e.preventDefault();
                setActive('register');
                window.location.hash = '#register';
            });
        }

        if (window.location.hash === '#register') {
            setActive('register');
        }

        // Zalo PKCE Logic
        async function generatePkceVerifier(length) {
            const size = length || 64;
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
            let result = '';
            if (window.crypto && window.crypto.getRandomValues) {
                const randomValues = new Uint8Array(size);
                window.crypto.getRandomValues(randomValues);
                for (let i = 0; i < size; i++) {
                    result += chars[randomValues[i] % chars.length];
                }
            } else {
                for (let i = 0; i < size; i++) {
                    result += chars[Math.floor(Math.random() * chars.length)];
                }
            }
            return result;
        }

        async function sha256Base64Url(input) {
            if (!(window.crypto && window.crypto.subtle && window.TextEncoder)) return '';
            const encoder = new TextEncoder();
            const data = encoder.encode(input);
            const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const binary = hashArray.map(b => String.fromCharCode(b)).join('');
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
        }

        async function startZaloLoginWithPkce() {
            if (!zaloConfig.enabled || !zaloConfig.authUrl) {
                alert('Đăng nhập bằng Zalo chưa được bật.');
                return;
            }
            try {
                const verifier = await generatePkceVerifier(64);
                const challenge = await sha256Base64Url(verifier);
                if (!challenge) {
                    window.location.href = zaloConfig.authUrl;
                    return;
                }
                document.cookie = 'z_pkce_verifier=' + encodeURIComponent(verifier) + ';path=/;max-age=600;SameSite=Lax';
                let url = zaloConfig.authUrl;
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'code_challenge=' + encodeURIComponent(challenge) + '&code_challenge_method=S256';
                window.location.href = url;
            } catch (e) {
                window.location.href = zaloConfig.authUrl;
            }
        }

        if (zaloLoginBtn) {
            zaloLoginBtn.addEventListener('click', startZaloLoginWithPkce);
        }

        // Auth Forms Submission
        const authForms = document.querySelectorAll('[data-auth-form]');
        authForms.forEach(form => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                submitAuthForm(form);
            });
        });

        // OTP Flow
        function setupPhoneRegisterFlow() {
            const phoneInput = document.getElementById('phoneRegisterPhoneInput');
            const nextBtn = document.getElementById('phoneRegisterNextBtn');
            const mainMsg = document.getElementById('phone-register-main-message');
            const confirmModal = document.getElementById('phoneRegisterConfirmModal');
            const confirmMsg = document.getElementById('phone-register-confirm-message');
            const confirmPhoneLabel = document.getElementById('phoneRegisterConfirmPhoneLabel');
            const btnCancel = document.getElementById('phoneRegisterConfirmCancel');
            const btnSend = document.getElementById('phoneRegisterConfirmSend');
            const otpStep = document.getElementById('phoneRegisterOtpStep');
            const otpPhoneLabel = document.getElementById('phoneRegisterOtpPhoneLabel');
            const otpInput = document.getElementById('phoneRegisterOtpInput');
            const otpMsg = document.getElementById('phone-register-otp-message');
            const resendText = document.getElementById('phoneRegisterResendText');

            if (!phoneInput || !nextBtn || !confirmModal || !btnCancel || !btnSend || !otpStep || !otpInput || !otpMsg) return;

            let currentPhone = '';
            let isSending = false;
            let isVerifying = false;
            let resendTimer = null;

            function setResendCountdown(seconds) {
                if (!resendText) return;
                let remaining = seconds;
                resendText.innerHTML = `Vui lòng chờ <span class="fw-bold text-dark">${remaining}</span> giây để gửi lại.`;
                if (resendTimer) clearInterval(resendTimer);
                resendTimer = setInterval(() => {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(resendTimer);
                        resendText.innerHTML = '<button type="button" class="link-plain fw-bold" id="resendOtpBtn">Gửi lại mã OTP</button>';
                        const btn = document.getElementById('resendOtpBtn');
                        if (btn) btn.addEventListener('click', sendOtpAction);
                    } else {
                        resendText.innerHTML = `Vui lòng chờ <span class="fw-bold text-dark">${remaining}</span> giây để gửi lại.`;
                    }
                }, 1000);
            }

            function normalizeVietnamPhone(raw) {
                let digits = (raw || '').replace(/\D+/g, '');
                if (!digits) return '';
                if (digits.startsWith('00')) digits = digits.slice(2);
                if (digits.startsWith('84') && digits.length > 2) digits = '0' + digits.slice(2);
                if (!digits.startsWith('0')) digits = '0' + digits;
                return digits;
            }

            phoneInput.addEventListener('input', () => { nextBtn.disabled = !phoneInput.value.trim(); });
            phoneInput.addEventListener('blur', () => {
                const normalized = normalizeVietnamPhone(phoneInput.value);
                if (normalized) phoneInput.value = normalized;
            });

            nextBtn.addEventListener('click', () => {
                const val = normalizeVietnamPhone(phoneInput.value);
                if (!val) { renderMessage(mainMsg, 'error', 'Số điện thoại không hợp lệ.'); return; }
                currentPhone = val;
                if (confirmPhoneLabel) confirmPhoneLabel.textContent = val;
                renderMessage(confirmMsg, '', ''); // Clear old messages in modal
                confirmModal.style.display = 'flex';
            });

            btnCancel.addEventListener('click', () => { confirmModal.style.display = 'none'; });

            async function sendOtpAction() {
                if (isSending || !currentPhone) return;
                isSending = true;
                setButtonLoading(btnSend, true);
                renderMessage(mainMsg, '', '');
                renderMessage(confirmMsg, '', '');
                renderMessage(otpMsg, '', '');

                let token = '';
                try {
                    if (typeof grecaptcha !== 'undefined') {
                        token = await grecaptcha.execute(window.recaptchaSiteKey, { action: 'send_otp' });
                    }
                } catch (e) {}

                if (!token) {
                    renderMessage(confirmMsg, 'error', 'Không thể xác thực reCAPTCHA. Thử tải lại trang.');
                    isSending = false; setButtonLoading(btnSend, false); return;
                }

                const fd = new FormData();
                fd.append('action', 'send');
                fd.append('phone', currentPhone);
                fd.append('g-recaptcha-response', token);

                try {
                    const res = await fetch(otpEndpoint, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    if (data.success) {
                        confirmModal.style.display = 'none';
                        if (otpPhoneLabel) otpPhoneLabel.textContent = currentPhone;
                        otpStep.style.display = 'flex';
                        otpInput.value = ''; otpInput.focus();
                        renderMessage(otpMsg, 'success', data.message || 'Đã gửi mã OTP.');
                        setResendCountdown(60);
                    } else {
                        const msg = data.message || 'Lỗi gửi OTP.';
                        renderMessage(confirmMsg, 'error', msg);
                        renderMessage(otpMsg, 'error', msg);
                    }
                } catch (e) {
                    renderMessage(confirmMsg, 'error', 'Lỗi kết nối máy chủ.');
                    renderMessage(otpMsg, 'error', 'Lỗi kết nối máy chủ.');
                } finally { isSending = false; setButtonLoading(btnSend, false); }
            }

            btnSend.addEventListener('click', sendOtpAction);

            otpInput.addEventListener('input', () => {
                const code = otpInput.value.trim();
                if (/^[0-9]{6}$/.test(code)) verifyOtpAndRegister(code);
            });

            async function verifyOtpAndRegister(code) {
                if (isVerifying) return;
                isVerifying = true;
                otpInput.disabled = true;
                renderMessage(otpMsg, '', '');

                const fdV = new FormData();
                fdV.append('action', 'verify');
                fdV.append('otp', code);

                try {
                    const resV = await fetch(otpEndpoint, { method: 'POST', body: fdV, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const dataV = await resV.json();
                    if (!dataV.success) {
                        renderMessage(otpMsg, 'error', dataV.message || 'Mã OTP sai.');
                        isVerifying = false; otpInput.disabled = false; return;
                    }

                    const fdA = new FormData();
                    fdA.append('action', 'register_phone_otp');
                    fdA.append('is_ajax', '1');

                    const resA = await fetch(authEndpoint, { method: 'POST', body: fdA, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const dataA = await resA.json();
                    if (dataA.success) {
                        renderMessage(otpMsg, 'success', 'Thành công!');
                        if (dataA.redirect) setTimeout(() => { window.location.href = dataA.redirect; }, 600);
                    } else {
                        renderMessage(otpMsg, 'error', dataA.message || 'Lỗi xác thực.');
                        isVerifying = false; otpInput.disabled = false;
                    }
                } catch (e) {
                    renderMessage(otpMsg, 'error', 'Lỗi kết nối.');
                    isVerifying = false; otpInput.disabled = false;
                }
            }
        }

        setupPhoneRegisterFlow();

        async function submitAuthForm(form) {
            const action = form.dataset.authForm;
            const messageEl = document.getElementById(`${action}-message`);
            const submitBtn = form.querySelector('[type="submit"]');

            renderMessage(messageEl, '', '');
            setButtonLoading(submitBtn, true);

            let token = '';
            try {
                if (typeof grecaptcha !== 'undefined') {
                    token = await grecaptcha.execute(window.recaptchaSiteKey, { action: action });
                }
            } catch (e) {}

            if (!token) {
                renderMessage(messageEl, 'error', 'Lỗi reCAPTCHA.');
                setButtonLoading(submitBtn, false); return;
            }

            const fd = new FormData(form);
            fd.append('action', action);
            fd.append('is_ajax', '1');
            fd.append('g-recaptcha-response', token);

            try {
                const res = await fetch(authEndpoint, { 
                    method: 'POST', 
                    body: fd, 
                    headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                });
                const result = await res.json();
                if (result.success) {
                    renderMessage(messageEl, 'success', result.message || 'Thành công.');
                    if (result.redirect) setTimeout(() => { window.location.href = result.redirect; }, 600);
                } else {
                    renderMessage(messageEl, 'error', result.message || 'Lỗi xử lý.');
                }
            } catch (e) {
                renderMessage(messageEl, 'error', 'Lỗi kết nối.');
            } finally { setButtonLoading(submitBtn, false); }
        }

        function renderMessage(target, type, text) {
            if (!target) return;
            target.classList.remove('active', 'alert-error', 'alert-success');
            const textNode = target.querySelector('.alert-text');
            if (!text) { if (textNode) textNode.textContent = ''; return; }
            const icon = target.querySelector('i');
            if (icon) icon.className = type === 'success' ? 'bi bi-check-circle' : 'bi bi-exclamation-circle';
            if (textNode) textNode.textContent = text;
            target.classList.add('active', type === 'success' ? 'alert-success' : 'alert-error');
        }

        function setButtonLoading(button, isLoading) {
            if (!button) return;
            button.classList.toggle('is-loading', !!isLoading);
            if (isLoading) {
                if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
                button.textContent = 'Đang xử lý...';
                button.disabled = true;
            } else {
                button.textContent = button.dataset.originalText || button.textContent;
                button.disabled = false;
            }
        }

        // Google Initializer
        if (googleConfig.enabled) {
            (function loadGSI() {
                const s = document.createElement('script');
                s.src = 'https://accounts.google.com/gsi/client'; s.async = s.defer = true;
                s.onload = () => {
                    if (!googleConfig.clientId || !window.google) return;
                    const host = document.getElementById('googleSignInButton');
                    google.accounts.id.initialize({ client_id: googleConfig.clientId, callback: (r) => {
                        if (r.credential) submitGoogleCredential(r.credential);
                    }, ux_mode: 'popup' });
                    if (host) google.accounts.id.renderButton(host, { theme: 'outline', size: 'large', width: '100%', shape: 'pill' });
                };
                document.head.appendChild(s);
            })();
        }

        async function submitGoogleCredential(credential) {
            const messageEl = document.getElementById('login-message');
            renderMessage(messageEl, '', '');
            const fd = new FormData();
            fd.append('action', 'google-login');
            fd.append('credential', credential);
            fd.append('is_ajax', '1');
            try {
                const res = await fetch(authEndpoint, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const result = await res.json();
                if (result.success) {
                    renderMessage(messageEl, 'success', result.message || 'Thành công.');
                    if (result.redirect) setTimeout(() => { window.location.href = result.redirect; }, 600);
                } else {
                    renderMessage(messageEl, 'error', result.message || 'Lỗi Google.');
                }
            } catch (e) { renderMessage(messageEl, 'error', 'Lỗi kết nối.'); }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
<?php if (!$isEmbeddedLayout): ?>
<?php include __DIR__ . '/../foot.php'; ?>
</body>
</html>
<?php endif; ?>

