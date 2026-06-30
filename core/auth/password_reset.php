<?php
/**
 * Helper dùng chung cho luồng đặt lại mật khẩu.
 * Tách riêng để cả main/login.php (forgot-password) và
 * core_user/ecommerce/reset-password.php (gửi lại liên kết) cùng dùng,
 * KHÔNG cần include login.php (vốn có code top-level gây side-effect).
 */

if (!function_exists('pm_send_reset_email')) {
    /**
     * Gửi email chứa liên kết khôi phục mật khẩu qua SMTP.
     * @return array{ok:bool,error:string}
     */
    function pm_send_reset_email(array $smtp, string $toEmail, string $resetLink): array {
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        } else {
            $base = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
            foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $f) {
                if (is_file($base . $f)) require_once $base . $f;
            }
        }

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return ['ok' => false, 'error' => 'Thư viện PHPMailer chưa được cài đặt.'];
        }

        $host = trim((string)($smtp['host'] ?? ''));
        $user = trim((string)($smtp['username'] ?? ''));
        $pass = (string)($smtp['password'] ?? '');
        if ($host === '' || $user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'Cấu hình SMTP chưa đầy đủ (thiếu host/username/password).'];
        }

        $fromEmail = trim((string)($smtp['from_email'] ?? '')) ?: $user;
        $fromName  = trim((string)($smtp['from_name'] ?? '')) ?: 'Paint & More';
        $port      = (int)($smtp['port'] ?? 587);
        $enc       = strtolower((string)($smtp['encryption'] ?? 'tls'));

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host       = $host;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = $user;
            $mailer->Password   = $pass;
            $mailer->Port       = $port;
            $mailer->SMTPSecure = ($enc === 'ssl')
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->CharSet    = 'UTF-8';
            $mailer->Timeout    = 15;

            $mailer->setFrom($fromEmail, $fromName);
            $mailer->addAddress($toEmail);
            $mailer->Subject = 'Khôi phục mật khẩu tài khoản - Paint & More';

            $templatePath = __DIR__ . '/../zns/email_html/reset_password.html';

            global $site_title, $baseUrl;
            $siteTitle = (isset($site_title) && $site_title !== '') ? $site_title : 'Paint & More';
            $siteUrl = isset($baseUrl) ? rtrim($baseUrl, '/') : 'https://sonxit.vn';

            $resetTimeoutMinutes = 30;
            if (function_exists('app_get_config_value_by_path')) {
                $configMin = app_get_config_value_by_path('RESET_PASSWORD_TIMEOUT_MINUTES');
                if ($configMin !== null) {
                    $resetTimeoutMinutes = (int)$configMin;
                }
            }

            if (is_file($templatePath)) {
                $html = file_get_contents($templatePath);
                $html = str_replace(
                    ['{{SITE_TITLE}}', '{{RESET_LINK}}', '{{YEAR}}', '{{SITE_URL}}', '{{EMAIL}}', '{{EXPIRE_MINUTES}}'],
                    [$siteTitle, $resetLink, date('Y'), $siteUrl, htmlspecialchars($toEmail, ENT_QUOTES), (string)$resetTimeoutMinutes],
                    $html
                );
                $mailer->isHTML(true);
                $mailer->Body = $html;
                $mailer->AltBody =
                    "Chào bạn,\n\n" .
                    "Bạn đã yêu cầu khôi phục mật khẩu tài khoản tại {$siteTitle}.\n" .
                    "Vui lòng nhấn vào liên kết dưới đây để thiết lập mật khẩu mới:\n" .
                    "{$resetLink}\n\n" .
                    "Liên kết này có hiệu lực trong vòng {$resetTimeoutMinutes} phút.\n" .
                    "Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.";
            } else {
                $mailer->Body =
                    "Chào bạn,\r\n\r\n" .
                    "Bạn đã yêu cầu khôi phục mật khẩu tài khoản tại {$siteTitle}.\r\n" .
                    "Vui lòng nhấn vào liên kết dưới đây để thiết lập mật khẩu mới:\r\n" .
                    "{$resetLink}\r\n\r\n" .
                    "Liên kết này có hiệu lực trong vòng {$resetTimeoutMinutes} phút.\r\n" .
                    "Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.";
            }

            $mailer->send();
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $mailer->ErrorInfo ?: $e->getMessage()];
        }
    }
}

if (!function_exists('pm_resend_reset_link')) {
    /**
     * Tạo token mới + lưu DB + gửi lại email khôi phục mật khẩu.
     * Giới hạn tối đa $maxPerDay lần / 1 email / 24h (lưu trong bảng log_password_reset).
     * @return array{success:bool,message:string}
     */
    function pm_resend_reset_link(mysqli $db, string $email, int $maxPerDay = 5): array {
        $email = function_exists('clean_email') ? clean_email($email, 254) : trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ.'];
        }

        // Email phải tồn tại trong hệ thống
        $exists = false;
        if ($stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1')) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
        }
        if (!$exists) {
            return ['success' => false, 'message' => 'Địa chỉ email này không tồn tại trong hệ thống.'];
        }

        // Đảm bảo có cột đếm số lần gửi trong ngày
        pm_ensure_resend_columns($db);

        // Đọc bộ đếm hiện tại
        $cnt = 0; $windowStart = null;
        if ($stmt = $db->prepare('SELECT resend_count, resend_window_start FROM log_password_reset WHERE email = ? LIMIT 1')) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($cnt, $windowStart);
            $stmt->fetch();
            $stmt->close();
        }

        $now = time();
        $windowTs = $windowStart ? strtotime((string)$windowStart) : 0;
        // Reset bộ đếm nếu đã quá 24h kể từ lần đầu của chu kỳ
        if (!$windowTs || ($now - $windowTs) >= 86400) {
            $cnt = 0;
            $windowTs = $now;
        }

        if ((int)$cnt >= $maxPerDay) {
            return ['success' => false, 'message' => 'Bạn đã yêu cầu gửi lại quá nhiều lần. Vui lòng thử lại sau.'];
        }

        // Tạo token mới + thời hạn
        $token = bin2hex(random_bytes(32));
        $resetTimeoutMinutes = 30;
        if (function_exists('app_get_config_value_by_path')) {
            $configMin = app_get_config_value_by_path('RESET_PASSWORD_TIMEOUT_MINUTES');
            if ($configMin !== null) $resetTimeoutMinutes = (int)$configMin;
        }
        $expiresAt = date('Y-m-d H:i:s', $now + ($resetTimeoutMinutes * 60));
        $newCount = (int)$cnt + 1;
        $windowStartStr = date('Y-m-d H:i:s', $windowTs);

        $stmt = $db->prepare(
            'INSERT INTO log_password_reset (email, token, expires_at, resend_count, resend_window_start)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at),
                                     resend_count = VALUES(resend_count), resend_window_start = VALUES(resend_window_start)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.'];
        }
        $stmt->bind_param('sssis', $email, $token, $expiresAt, $newCount, $windowStartStr);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.'];
        }

        // Gửi email
        global $baseUrl, $SMTP_CONFIG;
        $siteUrl = isset($baseUrl) ? rtrim($baseUrl, '/') : 'https://sonxit.vn';
        $resetLink = $siteUrl . '/reset-password?email=' . urlencode($email) . '&token=' . $token;
        $smtpCfg = (isset($SMTP_CONFIG) && is_array($SMTP_CONFIG)) ? $SMTP_CONFIG : [];
        $sendRes = pm_send_reset_email($smtpCfg, $email, $resetLink);
        if (!$sendRes['ok']) {
            error_log('[ResendReset][SMTP] Gửi email thất bại tới ' . $email . ': ' . $sendRes['error']);
            return ['success' => false, 'message' => 'Không thể gửi email khôi phục lúc này. Vui lòng thử lại sau.'];
        }

        return [
            'success' => true,
            'message' => 'Đã gửi lại liên kết khôi phục tới email của bạn. Vui lòng kiểm tra hộp thư.',
        ];
    }
}

if (!function_exists('pm_ensure_resend_columns')) {
    /**
     * Bổ sung cột đếm số lần gửi lại vào bảng log_password_reset nếu chưa có.
     */
    function pm_ensure_resend_columns(mysqli $db): void {
        $cols = [];
        if ($rc = $db->query("SHOW COLUMNS FROM log_password_reset")) {
            while ($c = $rc->fetch_assoc()) { $cols[$c['Field']] = true; }
        }
        if (!isset($cols['resend_count'])) {
            @$db->query("ALTER TABLE log_password_reset ADD COLUMN resend_count INT NOT NULL DEFAULT 0");
        }
        if (!isset($cols['resend_window_start'])) {
            @$db->query("ALTER TABLE log_password_reset ADD COLUMN resend_window_start DATETIME NULL");
        }
    }
}
