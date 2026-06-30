<?php
if (!function_exists('jOut')) {
    /**
     * Xuất dữ liệu JSON và kết thúc request.
     */
    function jOut($data, int $status = 200) {
        while (ob_get_level()) { ob_end_clean(); }
        if (is_array($data) && isset($data['ok']) && !$data['ok']) {
            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'checkout_save') {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $newToken = bin2hex(random_bytes(16));
                $_SESSION['checkout_token'] = $newToken;
                $data['new_token'] = $newToken;
            }
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('app_verify_csrf')) {
    /**
     * Xác thực CSRF token từ header X-CSRF-Token hoặc $_POST['csrf_token'].
     * Nếu không khớp, trả về lỗi JSON và dừng request.
     */
    function app_verify_csrf() {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        $saved = $_SESSION['csrf_token'] ?? '';
        if ($token === '' || $saved === '' || !hash_equals($saved, $token)) {
            jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ (CSRF error). Vui lòng tải lại trang.'], 403);
        }
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * Lấy địa chỉ IP của client, hỗ trợ qua proxy.
     */
    function get_client_ip(): string {
        $ip = app_server_header_first('HTTP_CLIENT_IP');
        if ($ip === '') {
            $ip = app_server_header_first('HTTP_X_FORWARDED_FOR');
        }
        if ($ip === '') {
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        }
        return $ip;
    }
}

if (!function_exists('app_rate_limit')) {
    /**
     * Kiểm tra và ghi nhận rate limit dựa trên IP + session key.
     *
     * Cơ chế sliding window: mỗi key lưu danh sách timestamps trong session.
     * Mỗi request mới, xóa các timestamps cũ hơn $windowSeconds, sau đó
     * kiểm tra xem số lượng request trong cửa sổ có vượt $maxRequests không.
     *
     * @param  string $key          Tên định danh hành động (vd: 'chat_send', 'ticket_create')
     * @param  int    $maxRequests  Số request tối đa trong cửa sổ thời gian
     * @param  int    $windowSeconds Độ dài cửa sổ (giây)
     * @return bool  true = đang bị giới hạn (chặn), false = được phép tiếp tục
     */
    function app_rate_limit(string $key, int $maxRequests = 10, int $windowSeconds = 60): bool {
        // Kết hợp IP + session ID để tăng độ chính xác; ngay cả khi xóa cookie session
        // mới, IP vẫn bị theo dõi qua session server-side.
        $ip          = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $sessionKey  = 'rl_' . $key . '_' . md5($ip);
        $now         = time();

        // Đọc danh sách timestamps hiện có
        $timestamps = $_SESSION[$sessionKey] ?? [];
        if (!is_array($timestamps)) $timestamps = [];

        // Loại bỏ các timestamps ngoài cửa sổ
        $timestamps = array_values(array_filter($timestamps, static fn($t) => ($now - (int)$t) < $windowSeconds));

        // Kiểm tra vượt giới hạn
        if (count($timestamps) >= $maxRequests) {
            $_SESSION[$sessionKey] = $timestamps; // Lưu lại (không ghi thêm)
            return true; // BỊ CHẶN
        }

        // Ghi timestamp hiện tại và lưu lại
        $timestamps[] = $now;
        $_SESSION[$sessionKey] = $timestamps;
        return false; // CHO PHÉP
    }
}

if (!function_exists('app_rate_limit_response')) {
    /**
     * Kiểm tra rate limit và tự trả về JSON 429 nếu bị chặn.
     * Gọi hàm này thay thế cho việc gọi app_rate_limit() và tự xử lý.
     *
     * @param  string $key
     * @param  int    $maxRequests
     * @param  int    $windowSeconds
     * @param  string $msg  Thông báo lỗi hiển thị cho người dùng
     */
    function app_rate_limit_response(string $key, int $maxRequests = 10, int $windowSeconds = 60, string $msg = ''): void {
        if (app_rate_limit($key, $maxRequests, $windowSeconds)) {
            $retryAfter = $windowSeconds;
            if ($msg === '') {
                $msg = 'Bạn đang thực hiện quá nhiều yêu cầu. Vui lòng chờ ' . $retryAfter . ' giây rồi thử lại.';
            }
            while (ob_get_level()) ob_end_clean();
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $retryAfter);
            echo json_encode(['ok' => false, 'msg' => $msg, 'code_error' => 'RATE_LIMIT'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('rawToNumber')) {
    /**
     * Chuyển đổi một giá trị bất kỳ sang float, loại bỏ các ký tự không phải số.
     */
    function rawToNumber($raw): float {
        if (is_numeric($raw)) {
            return (float)$raw;
        }
        $clean = preg_replace('/[^0-9\.-]/', '', (string)$raw);
        return $clean === '' ? 0.0 : (float)$clean;
    }
}

if (!function_exists('formatMoney')) {
    /**
     * Định dạng số sang chuỗi tiền tệ (phân cách hàng nghìn bằng dấu chấm). Không kèm đơn vị.
     */
    function formatMoney($n): string {
        return number_format((float)$n, 0, ',', '.');
    }
}

if (!function_exists('fmtMoney')) {
    /**
     * Định dạng tiền VNĐ có kèm đơn vị 'đ' (mặc định làm tròn về 1.000đ).
     */
    function fmtMoney($raw, bool $roundToThousand = true): string {
        $val = rawToNumber($raw);
        if ($roundToThousand && $val > 0) {
            $val = round($val / 1000) * 1000;
        }
        return formatMoney($val) . ' đ';
    }
}

if (!function_exists('formatVND')) {
    /**
     * Định dạng tiền VNĐ (làm tròn về 1.000đ gần nhất, không kèm đơn vị).
     */
    function formatVND($n) {
        $val = rawToNumber($n);
        if ($val > 0) {
            $val = round($val / 1000) * 1000;
        }
        return formatMoney($val);
    }
}

if (!function_exists('nowStr')) {
    /**
     * Trả về thời gian hiện tại dạng chuỗi SQL (Y-m-d H:i:s).
     */
    function nowStr() {
        return date('Y-m-d H:i:s');
    }
}
if (!function_exists('h')) {
    function h(mixed $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('esc')) {
    /**
     * Alias của h() - Escape HTML cho output.
     */
    function esc(mixed $value): string {
        return h($value);
    }
}

if (!function_exists('clean_input')) {
    /**
     * Lớp bảo vệ input phía backend (chống XSS lưu trữ / control-char / oversize).
     * - Ép về chuỗi, loại bỏ null-byte và ký tự điều khiển (trừ tab/newline nếu cho phép).
     * - Chuẩn hoá unicode khoảng trắng, trim, và giới hạn độ dài tối đa.
     * Lưu ý: KHÔNG dùng để chống SQLi (đã có prepared statement) và KHÔNG escape HTML
     * (việc escape HTML phải làm khi OUTPUT bằng h()/esc()).
     */
    function clean_input($value, int $maxLen = 255, bool $allowNewline = false): string {
        $s = (string)($value ?? '');
        // Bỏ null-byte
        $s = str_replace("\0", '', $s);
        // Bỏ ký tự điều khiển nguy hiểm; giữ \t \n \r nếu cho phép xuống dòng
        if ($allowNewline) {
            $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        } else {
            $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
        }
        if ($s === null) $s = ''; // preg_replace trả null nếu input không phải UTF-8 hợp lệ
        $s = trim($s);
        // Giới hạn độ dài (theo ký tự, an toàn UTF-8)
        if ($maxLen > 0 && mb_strlen($s, 'UTF-8') > $maxLen) {
            $s = mb_substr($s, 0, $maxLen, 'UTF-8');
        }
        return $s;
    }
}

if (!function_exists('clean_email')) {
    /**
     * Làm sạch + xác thực email. Trả về email chữ thường hợp lệ, hoặc '' nếu không hợp lệ.
     */
    function clean_email($value, int $maxLen = 254): string {
        $s = clean_input($value, $maxLen);
        if ($s === '') return '';
        $s = strtolower($s);
        // Loại bỏ ký tự không thuộc tập email hợp lệ trước khi validate (chống header injection)
        $s = preg_replace('/[\s<>"\'\\\\,;]+/', '', $s);
        if ($s === null) return '';
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
    }
}

if (!function_exists('clean_phone_digits')) {
    /**
     * Trích chỉ chữ số từ input số điện thoại (loại mọi ký tự khác).
     * Giới hạn 15 chữ số theo chuẩn E.164 để tránh oversize.
     */
    function clean_phone_digits($value): string {
        $digits = preg_replace('/\D+/', '', (string)($value ?? ''));
        if ($digits === null) return '';
        return substr($digits, 0, 15);
    }
}
if (!function_exists('app_get_media_url')) {
    /**
     * Chuyển đổi đường dẫn media (avatar, ảnh sản phẩm...) thành URL tuyệt đối hoặc tương đối chuẩn.
     * Tự động phát hiện nếu đã là URL tuyệt đối (http/https).
     */
    function app_get_media_url(?string $url, string $basePath = ''): string {
        $url = trim((string)$url);
        if ($url === '') return '';
        if (preg_match('~^(https?:)?//~i', $url) || stripos($url, 'data:image/') === 0 || preg_match('~^[a-z0-9]+://~i', $url)) {
            return $url;
        }
        $base = rtrim($basePath, '/');
        if ($base === '') {
            global $baseUrl;
            $base = rtrim((string)($baseUrl ?? ''), '/');
        }
        // Route file media sang media domain (đồng bộ với to_abs_url).
        if (function_exists('to_abs_url')) {
            return to_abs_url($url, $base);
        }
        return ($base !== '' ? $base : '') . '/' . ltrim($url, '/\\');
    }
}
if (!function_exists('footer_region_key')) {
    function footer_region_key(string $region): string {
    $txt = trim($region);
    if ($txt === '') return 'south';

    $txt = function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);
    $map = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ];
    $txt = strtr($txt, $map);
    // Ưu tiên map đúng theo cột region trong bảng site_store
    // Hỗ trợ: "Miền Bắc", "Miền Trung", "Miền Nam" (không phân biệt hoa/thường, có/không dấu)
    if (strpos($txt, 'mien bac') !== false || $txt === 'bac') {
        return 'north';
    }
    if (strpos($txt, 'mien trung') !== false || $txt === 'trung') {
        return 'central';
    }
    if (strpos($txt, 'mien nam') !== false || $txt === 'nam') {
        return 'south';
    }
    // Nếu cột region trong DB đã lưu sẵn key chuẩn (north/central/south) thì dùng luôn
    if (in_array($txt, ['north', 'central', 'south'], true)) {
        return $txt;
    }
    // Mặc định: south để không làm vỡ giao diện
    return 'south';
    }
}

if (!function_exists('app_server_header_first')) {
    /**
     * Lấy giá trị header đầu tiên từ $_SERVER và xử lý trường hợp nhiều giá trị phân tách bằng dấu phẩy.
     * Dùng cho các header proxy như X-Forwarded-*.
     */
    function app_server_header_first(string $header): string {
        $raw = trim((string)($_SERVER[$header] ?? ''));
        if ($raw === '') {
            return '';
        }
        if (strpos($raw, ',') !== false) {
            $raw = trim((string)explode(',', $raw)[0]);
        }
        return $raw;
    }
}

if (!function_exists('app_release_session_lock')) {
    /**
     * Giải phóng session lock để request khác không bị chặn khi đang chạy tác vụ dài.
     */
    function app_release_session_lock(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

if (!function_exists('app_bot_setting_has_column')) {
    /**
     * Kiểm tra cột có tồn tại trong bảng bot_setting hay không.
     */
    function app_bot_setting_has_column(mysqli $ithanhloc, string $columnName): bool {
        static $columnCache = [];
        $colKey = strtolower($columnName);
        if (isset($columnCache[$colKey])) {
            return $columnCache[$colKey];
        }

        $safe = $ithanhloc->real_escape_string($columnName);
        $sql = "SHOW COLUMNS FROM site_setting LIKE '{$safe}'";
        $res = $ithanhloc->query($sql);
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        $columnCache[$colKey] = $exists;
        return $exists;
    }
}

// NOTE: Bảng site_setting hiện được giả định đã có từ migration, không còn auto-create runtime.

if (!function_exists('app_editable_config_map')) {
    /**
     * Danh mục cấu hình cho phép chỉnh trên giao diện admin.
     * Mỗi key gồm: label, type, secret, section.
     */
    function app_editable_config_map(): array {
        return [
            'TINYMCE_API_KEY' => ['label' => 'TinyMCE API Key', 'type' => 'string', 'secret' => true, 'section' => 'AI'],
            'API_OPENAI_KEY' => ['label' => 'OpenAI API Key', 'type' => 'string', 'secret' => true, 'section' => 'AI'],
            'API_GEMINI_KEY' => ['label' => 'Gemini API Key', 'type' => 'string', 'secret' => true, 'section' => 'AI'],
            'GOOGLE_MAPS_API_KEY' => ['label' => 'Google Maps API Key', 'type' => 'string', 'secret' => true, 'section' => 'AI'],
            'GOONG_API_KEY' => ['label' => 'Goong.io REST API Key (geocoding, autocomplete)', 'type' => 'string', 'secret' => true, 'section' => 'AI'],
            'GOONG_MAP_KEY' => ['label' => 'Goong.io Map Key (hiển thị bản đồ)', 'type' => 'string', 'secret' => true, 'section' => 'AI'],
            // Thông tin công ty (dùng cho thanh toán COD, hóa đơn...)
            'COMPANY_INFO.name' => ['label' => 'Tên công ty', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.tax_code' => ['label' => 'Mã số thuế', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.address' => ['label' => 'Địa chỉ công ty', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.hotline' => ['label' => 'Hotline công ty', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.email' => ['label' => 'Email công ty', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.bank_name' => ['label' => 'Ngân hàng - Tên', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.bank_account' => ['label' => 'Ngân hàng - Số tài khoản', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.bank_branch' => ['label' => 'Ngân hàng - Chi nhánh', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'COMPANY_INFO.bank_qr_image' => ['label' => 'Ngân hàng - Ảnh QR', 'type' => 'string', 'secret' => false, 'section' => 'Company'],
            'SITE_HOTLINE' => ['label' => 'Hotline hiển thị', 'type' => 'string', 'secret' => false, 'section' => 'Ecommerce'],
            'ECOMMERCE_VAT_DEFAULT' => ['label' => 'Thuế VAT mặc định (%)', 'type' => 'float', 'secret' => false, 'section' => 'Ecommerce'],
            'ECOMMERCE_SHIPPING.standard_fee' => ['label' => 'Phí ship Tiêu chuẩn (VNĐ)', 'type' => 'int', 'secret' => false, 'section' => 'Ecommerce - Shipping'],
            'ECOMMERCE_XU.review_reward' => ['label' => 'Xu thưởng đánh giá', 'type' => 'int', 'secret' => false, 'section' => 'Ecommerce'],
            // Chat hỗ trợ trực tuyến 24/7
            'LIVE_CHAT.enabled' => ['label' => 'Bật chat hỗ trợ trực tuyến 24/7', 'type' => 'bool', 'secret' => false, 'section' => 'Ecommerce'],
            // Tự động trả lời bình luận / đánh giá sản phẩm
            'AUTO_REPLY.enabled' => ['label' => 'Bật tự động trả lời bình luận/đánh giá', 'type' => 'bool', 'secret' => false, 'section' => 'Ecommerce'],
            'AUTO_REPLY.question_enabled' => ['label' => 'Tự động trả lời cho Câu hỏi', 'type' => 'bool', 'secret' => false, 'section' => 'Ecommerce'],
            'AUTO_REPLY.review_enabled' => ['label' => 'Tự động trả lời cho Đánh giá', 'type' => 'bool', 'secret' => false, 'section' => 'Ecommerce'],
            'AUTO_REPLY.question_text' => ['label' => 'Nội dung trả lời tự động cho câu hỏi', 'type' => 'string', 'secret' => false, 'section' => 'Ecommerce'],
            'AUTO_REPLY.review_text' => ['label' => 'Nội dung trả lời tự động cho đánh giá', 'type' => 'string', 'secret' => false, 'section' => 'Ecommerce'],
            'ECOMMERCE_XU.vnd_per_xu' => ['label' => 'Quy đổi VNĐ / 1 xu', 'type' => 'int', 'secret' => false, 'section' => 'Ecommerce'],
            'ECOMMERCE_XU.max_use_percent' => ['label' => 'Tỷ lệ dùng xu tối đa (%)', 'type' => 'int', 'secret' => false, 'section' => 'Ecommerce'],
            'ECOMMERCE_XU.earn_percent' => ['label' => 'Tỷ lệ hoàn xu sau giao (%)', 'type' => 'int', 'secret' => false, 'section' => 'Ecommerce'],
            'ECOMMERCE_REGIONS.north' => ['label' => 'Khu vực miền Bắc', 'type' => 'string', 'secret' => false, 'section' => 'Region'],
            'ECOMMERCE_REGIONS.central' => ['label' => 'Khu vực miền Trung', 'type' => 'string', 'secret' => false, 'section' => 'Region'],
            'ECOMMERCE_REGIONS.south' => ['label' => 'Khu vực miền Nam', 'type' => 'string', 'secret' => false, 'section' => 'Region'],
            // Social & Website
            'SITE_LOGO' => ['label' => 'Logo website', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SITE_FALLBACK_LOGO' => ['label' => 'Ảnh fallback (dùng khi thiếu ảnh)', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SITE_TITLE' => ['label' => 'Tiêu đề website', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SITE_DESCRIPTION' => ['label' => 'Mô tả website (meta description)', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SITE_URL' => ['label' => 'Địa chỉ website', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SITE_THEME_COLOR' => ['label' => 'Màu chủ đề website', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_FACEBOOK' => ['label' => 'Facebook Page/Link', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_ZALO' => ['label' => 'Zalo OA/Link', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_YOUTUBE' => ['label' => 'YouTube Channel', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_TIKTOK' => ['label' => 'TikTok', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_INSTAGRAM' => ['label' => 'Instagram', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_PHONE' => ['label' => 'Số điện thoại liên hệ', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'SOCIAL_EMAIL' => ['label' => 'Email liên hệ', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'RESET_PASSWORD_TIMEOUT_MINUTES' => ['label' => 'Hiệu lực link đặt lại mật khẩu (phút)', 'type' => 'int', 'secret' => false, 'section' => 'Social & Website'],
            'OTP_TIMEOUT_MINUTES' => ['label' => 'Hiệu lực mã OTP xác thực (phút)', 'type' => 'int', 'secret' => false, 'section' => 'Social & Website'],
            // Media domain / CDN — tách ảnh sang domain riêng
            'MEDIA_MODE' => ['label' => 'Chế độ media (vps/read_origin/local)', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'MEDIA_BASE_URL' => ['label' => 'Media domain (URL đọc ảnh)', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'MEDIA_KEEP_UPLOADS_PREFIX' => ['label' => 'Giữ tiền tố /uploads/ trong URL media', 'type' => 'bool', 'secret' => false, 'section' => 'Social & Website'],
            'MEDIA_REMOTE_ENABLED' => ['label' => 'Bật đẩy upload lên media VPS', 'type' => 'bool', 'secret' => false, 'section' => 'Social & Website'],
            'MEDIA_RECEIVER_URL' => ['label' => 'Media receiver URL (nhận upload)', 'type' => 'string', 'secret' => false, 'section' => 'Social & Website'],
            'MEDIA_SECRET' => ['label' => 'Media secret (HMAC, bí mật)', 'type' => 'string', 'secret' => true, 'section' => 'Social & Website'],
            'ECOMMERCE_PAYMENT_METHODS.cod.enabled' => ['label' => 'COD enabled', 'type' => 'bool', 'secret' => false, 'section' => 'Payment - COD'],
            'ECOMMERCE_PAYMENT_METHODS.momo_env' => ['label' => 'MoMo Environment', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Control'],
            'ECOMMERCE_PAYMENT_METHODS.momo.enabled' => ['label' => 'MoMo enabled', 'type' => 'bool', 'secret' => false, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.partnerCode' => ['label' => 'MoMo Partner Code', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.accessKey' => ['label' => 'MoMo Access Key', 'type' => 'string', 'secret' => true, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.secretKey' => ['label' => 'MoMo Secret Key', 'type' => 'string', 'secret' => true, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.redirectUrl' => ['label' => 'MoMo Redirect URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.ipnUrl' => ['label' => 'MoMo IPN URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.createUrl' => ['label' => 'MoMo Create URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo.queryUrl' => ['label' => 'MoMo Query URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Production'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.partnerCode' => ['label' => 'MoMo Test Partner Code', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.accessKey' => ['label' => 'MoMo Test Access Key', 'type' => 'string', 'secret' => true, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.secretKey' => ['label' => 'MoMo Test Secret Key', 'type' => 'string', 'secret' => true, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.redirectUrl' => ['label' => 'MoMo Test Redirect URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.ipnUrl' => ['label' => 'MoMo Test IPN URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.createUrl' => ['label' => 'MoMo Test Create URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.momo_test.queryUrl' => ['label' => 'MoMo Test Query URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - MoMo - Test'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.enabled' => ['label' => 'VNPAY enabled', 'type' => 'bool', 'secret' => false, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.tmnCode' => ['label' => 'VNPAY TMN Code', 'type' => 'string', 'secret' => false, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.hashSecret' => ['label' => 'VNPAY Hash Secret', 'type' => 'string', 'secret' => true, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.returnUrl' => ['label' => 'VNPAY Return URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.ipnUrl' => ['label' => 'VNPAY IPN URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.payUrl' => ['label' => 'VNPAY Payment URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.vnpay.apiUrl' => ['label' => 'VNPAY API URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - VNPAY'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.enabled' => ['label' => 'ZaloPay enabled', 'type' => 'bool', 'secret' => false, 'section' => 'Payment - ZaloPay'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.app_id' => ['label' => 'ZaloPay App ID', 'type' => 'int', 'secret' => false, 'section' => 'Payment - ZaloPay'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.key1' => ['label' => 'ZaloPay Key1 (mac)', 'type' => 'string', 'secret' => true, 'section' => 'Payment - ZaloPay'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.key2' => ['label' => 'ZaloPay Key2', 'type' => 'string', 'secret' => true, 'section' => 'Payment - ZaloPay'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.createUrl' => ['label' => 'ZaloPay Create Order URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - ZaloPay'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.callbackUrl' => ['label' => 'ZaloPay Callback URL', 'type' => 'string', 'secret' => false, 'section' => 'Payment - ZaloPay'],
            'ECOMMERCE_PAYMENT_METHODS.zalopay.redirectUrl' => ['label' => 'ZaloPay Redirect URL (Merchant)', 'type' => 'string', 'secret' => false, 'section' => 'Payment - ZaloPay'],
            'GOOGLE_LOGIN.enabled' => ['label' => 'Google Login enabled', 'type' => 'bool', 'secret' => false, 'section' => 'Login'],
            'GOOGLE_LOGIN.client_id' => ['label' => 'Google Client ID', 'type' => 'string', 'secret' => false, 'section' => 'Login'],
            'GOOGLE_LOGIN.auto_register' => ['label' => 'Google Auto Register', 'type' => 'bool', 'secret' => false, 'section' => 'Login'],
            'GOOGLE_LOGIN.redirect_uri' => ['label' => 'Google OAuth Redirect URI', 'type' => 'string', 'secret' => false, 'section' => 'Login'],
            'GOOGLE_LOGIN.scope' => ['label' => 'Google OAuth Scope', 'type' => 'string', 'secret' => false, 'section' => 'Login'],
            'ZALO_LOGIN.enabled' => ['label' => 'Zalo Login enabled', 'type' => 'bool', 'secret' => false, 'section' => 'Login'],
            'ZALO_LOGIN.auth_url' => ['label' => 'Zalo OAuth URL', 'type' => 'string', 'secret' => false, 'section' => 'Login'],
            'ZALO_LOGIN.app_id' => ['label' => 'Zalo App ID', 'type' => 'string', 'secret' => false, 'section' => 'Login'],
            'ZALO_LOGIN.secret' => ['label' => 'Zalo App Secret', 'type' => 'string', 'secret' => true, 'section' => 'Login'],
        ];
    }
}

if (!function_exists('app_cast_config_value')) {
    /**
     * Ép kiểu dữ liệu cấu hình theo type khai báo (int/float/bool/json/string).
     */
    function app_cast_config_value($value, string $type) {
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                if (is_bool($value)) return $value;
                $norm = strtolower(trim((string)$value));
                return in_array($norm, ['1', 'true', 'on', 'yes'], true);
            case 'json':
                $decoded = json_decode((string)$value, true);
                return is_array($decoded) ? $decoded : [];
            default:
                return (string)$value;
        }
    }
}

if (!function_exists('app_user_log')) {
    /**
     * Ghi một log hành động của người dùng vào user_logs.
     */
    function app_user_log(mysqli $ithanhloc, int $userId, string $action, string $message, array $meta = []): bool {
        if ($userId < 0) return false;
        $action = substr(trim($action), 0, 64);
        $message = substr(trim($message), 0, 255);
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $createdAt = date('Y-m-d H:i:s');

        // First attempt: Try without ID (in case it's auto_increment)
        $stmt = $ithanhloc->prepare('INSERT INTO user_logs (user_id, action, message, meta_json, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('issssss', $userId, $action, $message, $metaJson, $ip, $ua, $createdAt);
            try {
                if ($stmt->execute()) {
                    $stmt->close();
                    return true;
                }
            } catch (Throwable $e) {}
            $stmt->close();
        }

        // Fallback: Manual ID generation
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $nextId = 0;
            $res = $ithanhloc->query("SELECT COALESCE(MAX(id), 0) + 1 FROM user_logs");
            if ($res) {
                $nextId = (int)$res->fetch_row()[0];
                $res->close();
            }
            if ($nextId <= 0) $nextId = (int)(time() % 2000000000) + $attempt;

            $stmt = $ithanhloc->prepare('INSERT INTO user_logs (id, user_id, action, message, meta_json, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('iissssss', $nextId, $userId, $action, $message, $metaJson, $ip, $ua, $createdAt);
                try {
                    if ($stmt->execute()) {
                        $stmt->close();
                        return true;
                    }
                } catch (Throwable $e) {}
                $stmt->close();
            }
        }
        return false;
    }
}

if (!function_exists('ecommerce_order_log_ensure_table')) {
    function ecommerce_order_log_ensure_table(mysqli $ithanhloc): void {
        static $done = false;
        if ($done) return;
        $done = true;
        $ithanhloc->query("CREATE TABLE IF NOT EXISTS `ecommerce_order_log` (
            `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_id`    VARCHAR(64)  NOT NULL,
            `actor_type`  ENUM('admin','customer','system','carrier') NOT NULL DEFAULT 'system',
            `actor_id`    INT UNSIGNED NOT NULL DEFAULT 0,
            `event`       VARCHAR(64)  NOT NULL DEFAULT 'status_changed',
            `status_from` VARCHAR(32)  NOT NULL DEFAULT '',
            `status_to`   VARCHAR(32)  NOT NULL DEFAULT '',
            `note`        TEXT         NULL,
            `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_order_id`   (`order_id`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_order_created` (`order_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('ecommerce_order_log_insert')) {
    /**
     * Ghi một sự kiện vào ecommerce_order_log.
     *
     * @param string $actorType  'admin' | 'customer' | 'system' | 'carrier'
     * @param string $event      'status_changed' | 'note_added' | 'payment_updated' | ...
     */
    function ecommerce_order_log_insert(
        mysqli $ithanhloc,
        string $orderId,
        string $actorType,
        int    $actorId,
        string $event,
        string $statusFrom,
        string $statusTo,
        string $note = '',
        string $createdAt = ''
    ): bool {
        ecommerce_order_log_ensure_table($ithanhloc);
        $orderId    = substr(trim($orderId), 0, 64);
        $actorType  = in_array($actorType, ['admin','customer','system','carrier'], true) ? $actorType : 'system';
        $event      = substr(trim($event), 0, 64);
        $statusFrom = substr(trim($statusFrom), 0, 32);
        $statusTo   = substr(trim($statusTo), 0, 32);
        $note       = $note !== '' ? substr(trim($note), 0, 2000) : null;
        // Allow caller to supply a historical timestamp (e.g. carrier logs from GHN)
        $ts = ($createdAt !== '' && strtotime($createdAt) !== false)
            ? date('Y-m-d H:i:s', strtotime($createdAt))
            : date('Y-m-d H:i:s');
        $stmt = $ithanhloc->prepare(
            'INSERT INTO ecommerce_order_log (order_id, actor_type, actor_id, event, status_from, status_to, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) return false;
        $stmt->bind_param('ssisssss', $orderId, $actorType, $actorId, $event, $statusFrom, $statusTo, $note, $ts);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('ecommerce_order_log_fetch')) {
    /**
     * Lấy danh sách log của một đơn hàng, sắp xếp theo thời gian tăng dần.
     * Trả về mảng các bản ghi dùng cho timeline.
     */
    function ecommerce_order_log_fetch(mysqli $ithanhloc, string $orderId, int $limit = 200): array {
        ecommerce_order_log_ensure_table($ithanhloc);
        $stmt = $ithanhloc->prepare(
            'SELECT id, actor_type, actor_id, event, status_from, status_to, note, created_at
             FROM ecommerce_order_log
             WHERE order_id = ?
             ORDER BY created_at ASC, id ASC
             LIMIT ?'
        );
        if (!$stmt) return [];
        $stmt->bind_param('si', $orderId, $limit);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('ecommerce_order_log_last_id')) {
    /**
     * Trả về id lớn nhất hiện có trong log của một đơn hàng. Dùng cho SSE long-poll.
     */
    function ecommerce_order_log_last_id(mysqli $ithanhloc, string $orderId): int {
        ecommerce_order_log_ensure_table($ithanhloc);
        $stmt = $ithanhloc->prepare('SELECT COALESCE(MAX(id),0) FROM ecommerce_order_log WHERE order_id = ?');
        if (!$stmt) return 0;
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $id = (int)$stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $id;
    }
}

if (!function_exists('app_user_notify')) {
    if (!function_exists('app_is_notx_v2_payload')) {
        function app_is_notx_v2_payload(string $body): bool {
            $txt = trim($body);
            if ($txt === '') return false;
            $decoded = json_decode($txt, true);
            return is_array($decoded) && (($decoded['schema'] ?? '') === 'notx_v2');
        }
    }

    if (!function_exists('app_build_notx_v2_body')) {
        function app_build_notx_v2_body(string $title, string $content = '', array $options = []): string {
            $payload = [
                'schema' => 'notx_v2',
                'template' => trim((string)($options['template'] ?? 'tpl4')),
                'title' => trim($title),
                'subtitle' => trim((string)($options['subtitle'] ?? '')),
                'content' => trim($content),
                'thumb_type' => trim((string)($options['thumb_type'] ?? 'icon')),
                'thumb_icon' => trim((string)($options['thumb_icon'] ?? 'bi bi-megaphone-fill')),
            ];

            foreach (['status', 'amount', 'order_id', 'module', 'event', 'category'] as $key) {
                if (array_key_exists($key, $options)) {
                    $payload[$key] = $options[$key];
                }
            }

            foreach (['thumb_image', 'link'] as $key) {
                $value = trim((string)($options[$key] ?? ''));
                if ($value !== '') $payload[$key] = $value;
            }

            if (!empty($options['banners']) && is_array($options['banners'])) {
                $payload['banners'] = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $options['banners']), static fn($v) => $v !== ''));
            }

            return (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    if (!function_exists('app_user_notify_structured')) {
        function app_user_notify_structured(mysqli $ithanhloc, int $userId, array $data, ?int $createdBy = null, ?string $sendAt = null): bool {
            $title = trim((string)($data['title'] ?? 'Thông báo'));
            $content = trim((string)($data['content'] ?? ''));
            $type = trim((string)($data['type'] ?? 'system'));
            $link = trim((string)($data['link'] ?? ''));
            $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

            // Chuẩn hoá và bổ sung meta cho đơn hàng (order) để hiển thị đẹp hơn
            $isOrder = ($type === 'order') || (($meta['module'] ?? '') === 'order');
            $orderIdMeta = trim((string)($meta['order_id'] ?? ($data['order_id'] ?? '')));
            $amountMetaRaw = $meta['amount'] ?? ($data['amount'] ?? null);
            $eventMeta = trim((string)($meta['event'] ?? ($data['event'] ?? '')));
            $statusMeta = trim((string)($meta['status'] ?? ($data['status'] ?? '')));
            $amountFormatted = '';
            if ($amountMetaRaw !== null && $amountMetaRaw !== '') {
                $amountFloat = (float)$amountMetaRaw;
                if ($amountFloat > 0) {
                    // Định dạng tiền tệ VNĐ: 1.234.567đ
                    $amountFormatted = number_format($amountFloat, 0, ',', '.') . 'đ';
                }
            }

            if ($isOrder) {
                if ($orderIdMeta !== '' && !isset($meta['order_id'])) {
                    $meta['order_id'] = $orderIdMeta;
                }
                if ($amountMetaRaw !== null && !isset($meta['amount'])) {
                    $meta['amount'] = (float)$amountMetaRaw;
                }
                if ($statusMeta !== '' && !isset($meta['status'])) {
                    $meta['status'] = $statusMeta;
                }
                if ($eventMeta !== '' && !isset($meta['event'])) {
                    $meta['event'] = $eventMeta;
                }

                // Tự sinh subtitle chi tiết nếu caller chưa truyền
                if (empty($data['subtitle'])) {
                    $parts = [];
                    if ($orderIdMeta !== '') {
                        $parts[] = 'Mã đơn: ' . $orderIdMeta;
                    }
                    if ($amountFormatted !== '') {
                        $parts[] = 'Tổng tiền: ' . $amountFormatted;
                    }
                    $autoSubtitle = implode(' • ', $parts);
                    if ($autoSubtitle !== '') {
                        $data['subtitle'] = $autoSubtitle;
                    }
                }

                // Nếu chưa có content chi tiết thì sinh nội dung mô tả theo event
                if ($content === '') {
                    $msg = '';
                    switch ($eventMeta) {
                        case 'order_created':
                            $msg = 'Đơn hàng của bạn đã được tạo thành công.';
                            break;
                        case 'order_status_updated':
                            $msg = 'Trạng thái đơn hàng của bạn đã được cập nhật.';
                            break;
                        case 'order_canceled':
                            $msg = 'Đơn hàng của bạn đã được hủy.';
                            break;
                        case 'order_delivered_confirmed':
                            $msg = 'Bạn đã xác nhận đã nhận hàng.';
                            break;
                        case 'order_return_requested':
                            $msg = 'Bạn đã gửi yêu cầu trả hàng cho đơn hàng này.';
                            break;
                        default:
                            $msg = 'Đơn hàng của bạn vừa được cập nhật.';
                            break;
                    }
                    if ($orderIdMeta !== '') {
                        $msg .= ' (Mã đơn: ' . $orderIdMeta . ')';
                    }
                    if ($amountFormatted !== '') {
                        $msg .= ' Tổng tiền: ' . $amountFormatted . '.';
                    }
                    $content = $msg;
                }
            }

            foreach (['status', 'amount', 'order_id', 'module', 'event', 'category'] as $key) {
                if (array_key_exists($key, $data) && !array_key_exists($key, $meta)) {
                    $meta[$key] = $data[$key];
                }
            }

            $body = app_build_notx_v2_body($title, $content, [
                'template' => $data['template'] ?? (($type === 'order') ? 'tpl1' : 'tpl4'),
                'subtitle' => $data['subtitle'] ?? '',
                'thumb_type' => $data['thumb_type'] ?? 'icon',
                'thumb_icon' => $data['thumb_icon'] ?? (($type === 'order') ? 'bi bi-bag-check-fill' : 'bi bi-megaphone-fill'),
                'thumb_image' => $data['thumb_image'] ?? '',
                'banners' => $data['banners'] ?? [],
                'status' => $meta['status'] ?? '',
                'amount' => $meta['amount'] ?? '',
                'order_id' => $meta['order_id'] ?? '',
                'module' => $meta['module'] ?? (($type === 'order') ? 'order' : 'system'),
                'event' => $meta['event'] ?? '',
                'category' => $meta['category'] ?? '',
                'link' => $link,
            ]);

            return app_user_notify($ithanhloc, $userId, $title, $body, $type, $link, $meta, $createdBy, $sendAt);
        }
    }

    /**
     * Tạo thông báo trực tiếp cho một user hoặc thông báo chung (user_id=0).
     */
    function app_user_notify(mysqli $ithanhloc, int $userId, string $title, string $body = '', string $type = 'system', string $link = '', array $meta = [], ?int $createdBy = null, ?string $sendAt = null): bool {
        if ($userId < 0) return false;
        $title = substr(trim($title), 0, 255);
        $body = trim($body);
        $type = substr(trim($type), 0, 40);
        $link = substr(trim($link), 0, 255);
        if (!is_array($meta)) $meta = [];
        if (!isset($meta['module'])) {
            $meta['module'] = ($type === 'order') ? 'order' : 'system';
        }
        if (!app_is_notx_v2_payload($body)) {
            $body = app_build_notx_v2_body($title, $body, [
                'template' => ($type === 'order') ? 'tpl1' : 'tpl4',
                'subtitle' => (string)($meta['subtitle'] ?? ''),
                'thumb_icon' => (string)($meta['thumb_icon'] ?? (($type === 'order') ? 'bi bi-bag-check-fill' : 'bi bi-megaphone-fill')),
                'status' => $meta['status'] ?? '',
                'amount' => $meta['amount'] ?? '',
                'order_id' => $meta['order_id'] ?? '',
                'module' => $meta['module'] ?? '',
                'event' => $meta['event'] ?? '',
                'category' => $meta['category'] ?? '',
                'link' => $link,
            ]);
        }
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $createdAt = function_exists('nowStr') ? nowStr() : date('Y-m-d H:i:s');
        $createdByVal = (int)($createdBy ?? 0);
        $isRead = 0;
        $isActive = 1;

        // Try normal insert first (for DBs where id is AUTO_INCREMENT).
        // Some deployments have `user_notification.id` as NOT NULL and NOT AUTO_INCREMENT,
        // which makes inserts without `id` default to 0 and crash with duplicate PRIMARY.
        $stmt = $ithanhloc->prepare('INSERT INTO user_notification (user_id, title, body, type, link, meta_json, is_read, is_active, created_by, created_at, send_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) return false;
        $stmt->bind_param('isssssiiiss', $userId, $title, $body, $type, $link, $metaJson, $isRead, $isActive, $createdByVal, $createdAt, $sendAt);
        try {
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            $stmt->close();
        }

        // Fallback: generate id = MAX(id)+1 and retry a few times.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $nextId = 0;
            try {
                $res = @$ithanhloc->query('SELECT COALESCE(MAX(id),0)+1 AS next_id FROM user_notification');
                if ($res instanceof mysqli_result) {
                    $r = $res->fetch_assoc();
                    $res->close();
                    $nextId = (int)($r['next_id'] ?? 0);
                }
            } catch (Throwable $e) {
                $nextId = 0;
            }

            if ($nextId <= 0) {
                $nextId = (int)(time() % 2000000000);
                if ($nextId <= 0) $nextId = 1;
            }

            $stmt2 = $ithanhloc->prepare('INSERT INTO user_notification (id, user_id, title, body, type, link, meta_json, is_read, is_active, created_by, created_at, send_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if (!$stmt2) return false;
            $stmt2->bind_param('iisssssiiiss', $nextId, $userId, $title, $body, $type, $link, $metaJson, $isRead, $isActive, $createdByVal, $createdAt, $sendAt);
            try {
                $ok2 = $stmt2->execute();
                $stmt2->close();
                return (bool)$ok2;
            } catch (Throwable $e) {
                $stmt2->close();
                // retry
            }
        }

        return false;
    }
}

if (!function_exists('app_guest_key')) {
    function app_guest_key(string $cookieName = 'pm_guest_key'): string {
        if (!empty($_COOKIE[$cookieName]) && preg_match('/^[A-Za-z0-9]{16,64}$/', (string)$_COOKIE[$cookieName])) {
            return (string)$_COOKIE[$cookieName];
        }
        try {
            $key = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $key = bin2hex(openssl_random_pseudo_bytes(16));
        }
        if (!headers_sent()) {
            // Cookie là "token" định danh phiên chat của khách vãng lai → siết bảo mật:
            // HttpOnly (JS không đọc được, chống XSS đánh cắp), SameSite=Lax (chống CSRF),
            // Secure khi chạy HTTPS. Widget không cần đọc cookie này (gửi tự động kèm request).
            $secure = (
                (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') == 443)
                || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            );
            setcookie($cookieName, $key, [
                'expires'  => time() + 86400 * 365,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$cookieName] = $key;
        return $key;
    }
}

if (!function_exists('app_get_default_admin_user_id')) {
    function app_get_default_admin_user_id(mysqli $ithanhloc): int {
        static $cached = null;
        if ($cached !== null) return (int)$cached;
        $cached = 0;
        $stmt = @$ithanhloc->prepare("SELECT id FROM users WHERE LOWER(TRIM(role))='admin' ORDER BY id ASC LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $cached = (int)($row['id'] ?? 0);
        }
        return (int)$cached;
    }
}

if (!function_exists('app_get_table_columns')) {
    function app_get_table_columns(mysqli $ithanhloc, string $table): array {
        static $columnCache = [];
        $tableSafe = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($tableSafe === '') return [];
        if (isset($columnCache[$tableSafe])) return $columnCache[$tableSafe];
        $cols = [];
        $res = @$ithanhloc->query("SHOW COLUMNS FROM `{$tableSafe}`");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $f = (string)($row['Field'] ?? '');
                if ($f !== '') $cols[strtolower($f)] = true;
            }
            $res->close();
        }
        $columnCache[$tableSafe] = $cols;
        return $cols;
    }
}

if (!function_exists('app_table_has_col')) {
    function app_table_has_col(array $cols, string $col): bool {
        return isset($cols[strtolower($col)]);
    }
}

if (!function_exists('app_resolve_notification_owner_user_id')) {
    function app_resolve_notification_owner_user_id(array $noticeRow): int {
        $createdBy = (int)($noticeRow['created_by'] ?? 0);
        if ($createdBy > 0) return $createdBy;
        $uid = (int)($noticeRow['user_id'] ?? 0);
        return $uid > 0 ? $uid : 0;
    }
}

if (!function_exists('app_resolve_product_owner_user_id')) {
    function app_resolve_product_owner_user_id(mysqli $ithanhloc, int $productId): int {
        $productId = (int)$productId;
        if ($productId <= 0) return 0;
        $cols = app_get_table_columns($ithanhloc, 'ecommerce_product');
        $candidateCols = [];
        if (app_table_has_col($cols, 'created_by')) $candidateCols[] = 'created_by';
        if (app_table_has_col($cols, 'user_id')) $candidateCols[] = 'user_id';
        if (app_table_has_col($cols, 'owner_id')) $candidateCols[] = 'owner_id';
        if (app_table_has_col($cols, 'admin_id')) $candidateCols[] = 'admin_id';
        if (!$candidateCols) {
            return app_get_default_admin_user_id($ithanhloc);
        }
        $select = implode(', ', array_map(static fn($c) => "`{$c}`", $candidateCols));
        $stmt = @$ithanhloc->prepare("SELECT {$select} FROM ecommerce_product WHERE id=? LIMIT 1");
        if (!$stmt) return app_get_default_admin_user_id($ithanhloc);
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return app_get_default_admin_user_id($ithanhloc);
        foreach ($candidateCols as $c) {
            $v = (int)($row[$c] ?? 0);
            if ($v > 0) return $v;
        }
        return app_get_default_admin_user_id($ithanhloc);
    }
}

if (!function_exists('app_build_product_detail_link')) {
    function app_build_product_detail_link(int $productId, string $baseUrl = ''): string {
        $base = rtrim((string)$baseUrl, '/');
        $pid = (int)$productId;
        if ($pid <= 0) return $base !== '' ? ($base . '/view-product') : '/view-product';
        // Prefer existing SEO helper if available.
        if (function_exists('pm_product_url')) {
            return (string)pm_product_url($pid, '', $base);
        }
        return ($base !== '' ? $base : '') . '/view-product?pid=' . urlencode((string)$pid);
    }
}

if (!function_exists('app_find_post_thread_id')) {
    function app_find_post_thread_id(mysqli $ithanhloc, string $threadKey): int {
        $threadKey = trim($threadKey);
        if ($threadKey === '') return 0;
        $needle = '%"thread_key":"' . $ithanhloc->real_escape_string($threadKey) . '"%';
        // Dùng toán tử LIKE trên cột meta_json (cách làm đơn giản, không cần thay đổi schema)
        $stmt = @$ithanhloc->prepare("SELECT id FROM user_notification WHERE LOWER(TRIM(type))='post' AND meta_json LIKE ? AND COALESCE(NULLIF(TRIM(CAST(is_active AS CHAR)),''),'1')='1' ORDER BY id DESC LIMIT 1");
        if (!$stmt) return 0;
        $stmt->bind_param('s', $needle);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('app_get_or_create_post_thread')) {
    function app_get_or_create_post_thread(mysqli $ithanhloc, string $threadKey, int $ownerUserId, string $title, string $link, array $meta = []): int {
        $existing = app_find_post_thread_id($ithanhloc, $threadKey);
        if ($existing > 0) return $existing;

        $ownerUserId = max(0, (int)$ownerUserId);
        $meta = is_array($meta) ? $meta : [];
        $meta['module'] = $meta['module'] ?? 'post';
        $meta['event'] = $meta['event'] ?? 'thread';
        $meta['thread_key'] = $threadKey;
        $meta['is_thread'] = 1;

        // Create a lightweight thread notification (receiver = owner)
        $ok = app_user_notify($ithanhloc, $ownerUserId, $title, '', 'post', $link, $meta, null, null);
        if (!$ok) return 0;
        return (int)$ithanhloc->insert_id;
    }
}

if (!function_exists('app_log_post_event')) {
    function app_log_post_event(mysqli $ithanhloc, int $receiverUserId, string $title, string $body, string $link, array $meta = [], int $actorUserId = 0): bool {
        $receiverUserId = (int)$receiverUserId;
        if ($receiverUserId < 0) return false;
        $meta = is_array($meta) ? $meta : [];
        $meta['module'] = $meta['module'] ?? 'post';
        $meta['category'] = $meta['category'] ?? 'activity';
        return app_user_notify($ithanhloc, $receiverUserId, $title, $body, 'post', $link, $meta, $actorUserId > 0 ? $actorUserId : 0, null);
    }
}

// ==========================
// CẤU HÌNH PHƯƠNG THỨC VẬN CHUYỂN DÙNG CHUNG
// ==========================

if (!function_exists('defaultShippingMethodLabelMap')) {
    /**
     * Danh sách key phương thức vận chuyển chuẩn và nhãn hiển thị mặc định.
     * Dùng chung cho frontend (user) và admin để tránh lệch cấu hình.
     */
    function defaultShippingMethodLabelMap(): array {
        return [
            'ghn_nhanh' => 'Nhanh',
            'ghn_tiet_kiem' => 'Tiết kiệm',
            'ghn_hoa_toc' => 'Hỏa tốc',
            'tieu_chuan' => 'Tiêu chuẩn',
        ];
    }
}

if (!function_exists('getShippingMethodLabelMap')) {
    /**
     * Helper cho admin (có $ithanhloc) – hiện tại chỉ trả về map mặc định.
     * Nếu sau này có bảng cấu hình shipping riêng trong DB thì có thể mở rộng ở đây.
     */
    function ecommerce_get_shipping_method_label_map(mysqli $ithanhloc): array {
        return defaultShippingMethodLabelMap();
    }
}

// ==========================
// CẤU HÌNH GHN MẶC ĐỊNH TỪ BẢNG site_ghn_conf
// ==========================

if (!function_exists('app_get_default_ghn_env_config')) {
    /**
     * Lấy cấu hình môi trường GHN (env, base_url, enabled, token) từ bảng site_ghn_conf.
     * Trả về map dạng:
     *  [
     *      'env'      => 'test'|'prod',
     *      'base_url' => 'https://.../shiip/public-api',
     *      'enabled'  => bool,
     *      'token'    => string,
     *  ]
     */
    function app_get_default_ghn_env_config(mysqli $ithanhloc): array {
        $env = 'test';
        $baseUrl = '';
        $enabled = true;
        $token = '';

        try {
            $sql = "SELECT setting_key, setting_value FROM site_ghn_conf";
            $res = $ithanhloc->query($sql);
            $rows = [];
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $key = trim((string)($row['setting_key'] ?? ''));
                    if ($key === '') continue;
                    $rows[$key] = (string)($row['setting_value'] ?? '');
                }
                $res->close();
            }

            if ($rows) {
                $env = strtolower(trim((string)($rows['env'] ?? $env)));
                if (!in_array($env, ['test', 'prod'], true)) {
                    $env = 'test';
                }
                $baseUrl = trim((string)($rows['base_url'] ?? ''));
                $token = trim((string)($rows['token'] ?? ''));
                $enabledRaw = strtolower(trim((string)($rows['enabled'] ?? '1')));
                $enabled = in_array($enabledRaw, ['1', 'true', 'yes', 'on'], true);
            }
        } catch (Throwable $e) {
            // Nếu lỗi (chưa có bảng, lỗi kết nối...), fallback về cấu hình test mặc định
            $env = 'test';
            $baseUrl = '';
            $enabled = true;
            $token = '';
        }

        if ($baseUrl === '') {
            $baseUrl = $env === 'prod'
                ? 'https://online-gateway.ghn.vn/shiip/public-api'
                : 'https://dev-online-gateway.ghn.vn/shiip/public-api';
        }

        return [
            'env' => $env,
            'base_url' => rtrim($baseUrl, '/'),
            'enabled' => (bool)$enabled,
            'token' => $token,
        ];
    }
}

if (!function_exists('app_get_default_shipping_method_rate_map')) {
    /**
     * Hệ số mặc định áp dụng trên phí GHN cho từng phương thức shipping.
     * Có thể được dùng như multiplier trên baseFee GHN / fallback.
     */
    function app_get_default_shipping_method_rate_map(): array {
        return [
            'ghn_tiet_kiem' => 0.88,
            'ghn_nhanh' => 1.00,
            'ghn_hoa_toc' => 1.35,
            'tieu_chuan' => 1.0,
            'tu_den_lay' => 0.0,
        ];
    }
}

if (!function_exists('app_ensure_notification_template_table')) {
    /**
     * Đảm bảo bảng mẫu thông báo tồn tại.
     */
    function app_ensure_notification_template_table(mysqli $ithanhloc): void {
        @ $ithanhloc->query("CREATE TABLE IF NOT EXISTS `site_notification_template` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(64) NOT NULL,
            `title_tpl` VARCHAR(255) NOT NULL,
            `body_tpl` TEXT NULL,
            `type` VARCHAR(40) NOT NULL DEFAULT 'system',
            `link_tpl` VARCHAR(255) NULL,
            `meta_json` LONGTEXT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('app_upsert_notification_template')) {
    /**
     * Tạo mới/cập nhật template thông báo theo mã code.
     */
    function app_upsert_notification_template(mysqli $ithanhloc, string $code, string $titleTpl, string $bodyTpl, string $type = 'system', string $linkTpl = '', array $meta = [], bool $forceUpdate = false): bool {
        app_ensure_notification_template_table($ithanhloc);
        $code = substr(trim($code), 0, 64);
        if ($code === '') return false;

        $select = $ithanhloc->prepare('SELECT id FROM site_notification_template WHERE code=? LIMIT 1');
        if (!$select) return false;
        $select->bind_param('s', $code);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();

        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if ($row && isset($row['id'])) {
            if (!$forceUpdate) return true;
            $id = (int)$row['id'];
            $stmt = $ithanhloc->prepare('UPDATE site_notification_template SET title_tpl=?, body_tpl=?, type=?, link_tpl=?, meta_json=?, updated_at=NOW() WHERE id=?');
            if (!$stmt) return false;
            $stmt->bind_param('sssssi', $titleTpl, $bodyTpl, $type, $linkTpl, $metaJson, $id);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        }

        $stmt = $ithanhloc->prepare('INSERT INTO site_notification_template (code, title_tpl, body_tpl, type, link_tpl, meta_json) VALUES (?,?,?,?,?,?)');
        if (!$stmt) return false;
        $stmt->bind_param('ssssss', $code, $titleTpl, $bodyTpl, $type, $linkTpl, $metaJson);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('app_seed_notification_templates')) {
    /**
     * Seed các template thông báo mặc định cho hệ thống.
     */
    function app_seed_notification_templates(mysqli $ithanhloc): void {
		static $seeded = false;
		if ($seeded) return;
		$seeded = true;
        app_upsert_notification_template(
            $ithanhloc,
            'payment_success',
            'Thanh toán thành công',
            'Đơn {order_id} đã thanh toán thành công qua {gateway}. Số tiền: {amount}đ. Thời gian: {time}.',
            'success',
            '/view-order?order_id={order_id}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'promo_new',
            'Khuyến mãi mới',
            '{promo_title} - {promo_desc}',
            'info',
            '{link}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'coupon_new',
            'Mã giảm giá mới',
            'Mã {coupon_code}: {coupon_desc}',
            'info',
            '/voucher'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'security_login',
            'Cảnh báo đăng nhập',
            'Tài khoản của bạn vừa đăng nhập từ thiết bị {device} lúc {time}. IP: {ip}. Nếu không phải bạn, vui lòng đổi mật khẩu.',
            'warning',
            '/account'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'xu_change',
            'Xu của bạn vừa thay đổi',
            'Số xu thay đổi: {xu_delta} xu. Số dư hiện tại: {xu_balance} xu. Lý do: {note}.',
            'info',
            '/account'
        );

        // === Orders ===
        app_upsert_notification_template(
            $ithanhloc,
            'order_created',
            'Thông báo đơn hàng mới',
            'Đơn hàng của bạn đã được tạo thành công với mã đơn {order_id}. Tổng tiền: {amount}đ. Vui lòng kiểm tra chi tiết đơn hàng.',
            'order',
            '/view-order?order_id={order_id}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'order_status_updated',
            'Đơn hàng của bạn:',
            'Mã đơn: {order_id} - Cập nhật trạng thái: {status_label}',
            'order',
            '/view-order?order_id={order_id}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'order_canceled',
            'Đơn hàng đã hủy',
            'Mã đơn: {order_id} • Đơn hàng đã được hủy. Thời gian: {time}.',
            'order',
            '/view-order?order_id={order_id}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'order_delivered_confirmed',
            'Xác nhận đã nhận hàng',
            'Mã đơn: {order_id} • Bạn đã xác nhận đã nhận hàng. Thời gian: {time}.',
            'order',
            '/view-order?order_id={order_id}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'order_return_requested',
            'Yêu cầu trả hàng đã gửi',
            'Đã gửi yêu cầu trả hàng với mã đơn: {order_id}. Thời gian: {time}.',
            'order',
            '/view-order?order_id={order_id}'
        );

        // === Post / Comments (activity feed) ===
        app_upsert_notification_template(
            $ithanhloc,
            'post_product_comment',
            '{actor_name} vừa bình luận sản phẩm',
            '{product_name}',
            'post',
            '{link}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'post_product_review',
            '{actor_name} đã đánh giá sản phẩm',
            '{product_name}',
            'post',
            '{link}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'post_product_reply',
            '{actor_name} đã trả lời',
            '{product_name}',
            'post',
            '{link}'
        );
        app_upsert_notification_template(
            $ithanhloc,
            'post_product_reply_to_you',
            '{actor_name} đã trả lời bình luận/đánh giá của bạn',
            '{product_name}',
            'post',
            '{link}'
        );
    }
}

if (!function_exists('app_render_notification_template_text')) {
    /**
     * Render template strings (title/body/link/type) by code + vars.
     * Returns null if template missing/inactive.
     */
    function app_render_notification_template_text(mysqli $ithanhloc, string $code, array $vars = []): ?array {
        app_seed_notification_templates($ithanhloc);
        $tpl = app_get_notification_template($ithanhloc, $code);
        if (!$tpl || (int)($tpl['is_active'] ?? 1) !== 1) return null;

        $titleTpl = (string)($tpl['title_tpl'] ?? '');
        $bodyTpl = (string)($tpl['body_tpl'] ?? '');
        $linkTpl = (string)($tpl['link_tpl'] ?? '');
        $type = (string)($tpl['type'] ?? 'system');

        $title = app_render_template_vars($titleTpl, $vars);
        $body = app_render_template_vars($bodyTpl, $vars);
        $link = app_render_template_vars($linkTpl, $vars);
        return ['title' => $title, 'body' => $body, 'link' => $link, 'type' => $type];
    }
}

if (!function_exists('app_get_notification_template')) {
    /**
     * Lấy 1 template thông báo theo code.
     */
    function app_get_notification_template(mysqli $ithanhloc, string $code): ?array {
        app_ensure_notification_template_table($ithanhloc);
        $code = substr(trim($code), 0, 64);
        if ($code === '') return null;
        $stmt = $ithanhloc->prepare('SELECT * FROM site_notification_template WHERE code=? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('app_render_template_vars')) {
    /**
     * Thay thế placeholder dạng {key} trong template bằng dữ liệu thực tế.
     */
    function app_render_template_vars(string $tpl, array $vars): string {
        if ($tpl === '' || !$vars) return $tpl;
        $replacements = [];
        foreach ($vars as $key => $val) {
            $k = trim((string)$key);
            if ($k === '') continue;
            $replacements['{' . $k . '}'] = (string)$val;
        }
        return strtr($tpl, $replacements);
    }
}

if (!function_exists('app_user_notify_template')) {
    /**
     * Tạo thông báo cho user dựa trên template + biến động.
     */
    function app_user_notify_template(mysqli $ithanhloc, int $userId, string $code, array $vars = [], ?int $createdBy = null, ?string $sendAt = null): bool {
        if ($userId < 0) return false;
        $rendered = app_render_notification_template_text($ithanhloc, $code, $vars);
        if (!$rendered) return false;

        $title = (string)($rendered['title'] ?? '');
        $body = (string)($rendered['body'] ?? '');
        $link = (string)($rendered['link'] ?? '');
        $type = (string)($rendered['type'] ?? 'system');

        $meta = is_array($vars) ? $vars : [];
        $meta['event'] = $meta['event'] ?? $code;
        if (!isset($meta['module'])) {
            if ($type === 'order' || str_starts_with((string)$code, 'order_') || $code === 'payment_success') {
                $meta['module'] = 'order';
            } elseif ($type === 'post' || str_starts_with((string)$code, 'post_')) {
                $meta['module'] = 'post';
            } else {
                $meta['module'] = 'system';
            }
        }
        if ($code === 'payment_success' && !isset($meta['status'])) {
            $meta['status'] = 'paid';
        }
		$uiTemplate = ($meta['module'] ?? '') === 'order' ? 'tpl1' : 'tpl4';

        return app_user_notify_structured($ithanhloc, $userId, [
            'title' => $title,
            'subtitle' => (string)($vars['subtitle'] ?? ''),
            'content' => $body,
            'type' => $type,
            'link' => $link,
            'template' => ($code === 'payment_success') ? 'tpl1' : $uiTemplate,
            'meta' => $meta,
        ], $createdBy, $sendAt);
    }
}

if (!function_exists('app_upsert_bot_setting_value')) {
    /**
     * Upsert một key cấu hình vào bot_setting.
     * Nếu forceUpdate=false và key đã có giá trị thì giữ nguyên.
     */
    function app_upsert_bot_setting_value(mysqli $ithanhloc, string $key, string $value, bool $forceUpdate = false): bool {
        $select = $ithanhloc->prepare('SELECT id, setting_value FROM site_setting WHERE setting_key=? LIMIT 1');
        if (!$select) return false;
        $select->bind_param('s', $key);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();

        if ($row && isset($row['id'])) {
            $id = (int)$row['id'];
            $currentValue = trim((string)($row['setting_value'] ?? ''));
            if (!$forceUpdate && $currentValue !== '') {
                return true;
            }
            $update = $ithanhloc->prepare('UPDATE site_setting SET setting_value=? WHERE id=?');
            if (!$update) return false;
            $update->bind_param('si', $value, $id);
            $ok = $update->execute();
            $update->close();
            return $ok;
        }

        $insert = $ithanhloc->prepare('INSERT INTO site_setting (setting_key, setting_value) VALUES (?, ?)');
        if (!$insert) return false;
        $insert->bind_param('ss', $key, $value);
        $ok = $insert->execute();
        $insert->close();
        return $ok;
    }
}

if (!function_exists('app_get_config_value_by_path')) {
    /**
     * Lấy giá trị config theo path dạng dot-notation, ví dụ: ECOMMERCE_XU.vnd_per_xu.
     */
    function app_get_config_value_by_path(string $path) {
        global $API_OPENAI_KEY, $API_GEMINI_KEY, $TINYMCE_API_KEY, $GOOGLE_LOGIN, $ZALO_LOGIN, $ECOMMERCE_XU, $ECOMMERCE_GHN, $ECOMMERCE_SHIPPING, $ECOMMERCE_REGIONS, $ECOMMERCE_PAYMENT_METHODS, $hotline, $ECOMMERCE_VAT_DEFAULT, $COMPANY_INFO, $ithanhloc;
        static $settingsCache = null;

        $parts = explode('.', $path);
        if (!$parts) return null;

        $root = array_shift($parts);
        switch ($root) {
            case 'API_OPENAI_KEY':
                return $API_OPENAI_KEY;
            case 'API_GEMINI_KEY':
                return $API_GEMINI_KEY;
            case 'TINYMCE_API_KEY':
                return $TINYMCE_API_KEY;
            case 'ECOMMERCE_VAT_DEFAULT':
                return $ECOMMERCE_VAT_DEFAULT;
            case 'SITE_HOTLINE':
                return $hotline;
            case 'ECOMMERCE_XU':
                $ref = $ECOMMERCE_XU;
                break;
            case 'ECOMMERCE_GHN':
                $ref = $ECOMMERCE_GHN;
                break;
            case 'ECOMMERCE_SHIPPING':
                $ref = $ECOMMERCE_SHIPPING;
                break;
            case 'GOOGLE_LOGIN':
                $ref = $GOOGLE_LOGIN;
                break;
            case 'ZALO_LOGIN':
                $ref = $ZALO_LOGIN;
                break;
            case 'ECOMMERCE_REGIONS':
                $ref = $ECOMMERCE_REGIONS;
                break;
            case 'ECOMMERCE_PAYMENT_METHODS':
                $ref = $ECOMMERCE_PAYMENT_METHODS;
                break;
            case 'COMPANY_INFO':
                $ref = $COMPANY_INFO;
                break;
            default:
                // Fallback: đọc trực tiếp từ site_setting theo key đầy đủ (ví dụ: SOCIAL_EMAIL)
                if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
                    return null;
                }
                if ($settingsCache === null) {
                    $settingsCache = [];
                    $res = $ithanhloc->query('SELECT setting_key, setting_value, value_type FROM site_setting');
                    if ($res) {
                        while ($row = $res->fetch_assoc()) {
                            $k = (string)($row['setting_key'] ?? '');
                            if ($k !== '') {
                                $settingsCache[$k] = [
                                    'value' => $row['setting_value'] ?? '',
                                    'type' => (string)($row['value_type'] ?? 'string')
                                ];
                            }
                        }
                    }
                }
                if (isset($settingsCache[$path])) {
                    $rawValue = $settingsCache[$path]['value'];
                    $valueType = $settingsCache[$path]['type'];
                    if (function_exists('app_cast_config_value')) {
                        return app_cast_config_value($rawValue, $valueType);
                    }
                    return $rawValue;
                }
                if ($path === 'RESET_PASSWORD_TIMEOUT_MINUTES') {
                    return 30;
                }
                if ($path === 'OTP_TIMEOUT_MINUTES') {
                    return 5;
                }
                return null;
        }

        foreach ($parts as $part) {
            if (!is_array($ref) || !array_key_exists($part, $ref)) return null;
            $ref = $ref[$part];
        }
        return $ref;
    }
}

if (!function_exists('app_set_config_value_by_path')) {
    /**
     * Gán giá trị config theo path dạng dot-notation.
     */
    function app_set_config_value_by_path(string $path, $value): bool {
        global $API_OPENAI_KEY, $API_GEMINI_KEY, $TINYMCE_API_KEY, $GOOGLE_LOGIN, $ZALO_LOGIN, $ECOMMERCE_XU, $ECOMMERCE_GHN, $ECOMMERCE_SHIPPING, $ECOMMERCE_REGIONS, $ECOMMERCE_PAYMENT_METHODS, $hotline, $ECOMMERCE_VAT_DEFAULT, $COMPANY_INFO;
        // Các key cấu hình dạng đơn (không phân cấp) chỉ lưu trong bảng site_setting,
        // không cần đồng bộ vào biến global => luôn cho phép set để bot_save_config hoạt động.
        $simpleSiteKeys = [
            'GOOGLE_MAPS_API_KEY',
            'GOONG_API_KEY',
            'GOONG_MAP_KEY',
            'SITE_LOGO',
            'SITE_FALLBACK_LOGO',
            'SITE_TITLE',
            'SITE_DESCRIPTION',
            'SITE_URL',
            'SITE_THEME_COLOR',
            'SOCIAL_FACEBOOK',
            'SOCIAL_ZALO',
            'SOCIAL_YOUTUBE',
            'SOCIAL_TIKTOK',
            'SOCIAL_INSTAGRAM',
            'SOCIAL_PHONE',
            'SOCIAL_EMAIL',
            'RESET_PASSWORD_TIMEOUT_MINUTES',
            'OTP_TIMEOUT_MINUTES',
            'MEDIA_MODE',
            'MEDIA_BASE_URL',
            'MEDIA_KEEP_UPLOADS_PREFIX',
            'MEDIA_REMOTE_ENABLED',
            'MEDIA_RECEIVER_URL',
            'MEDIA_SECRET',
            'LIVE_CHAT.enabled',
            'AUTO_REPLY.enabled',
            'AUTO_REPLY.question_enabled',
            'AUTO_REPLY.review_enabled',
            'AUTO_REPLY.question_text',
            'AUTO_REPLY.review_text',
        ];
        if (in_array($path, $simpleSiteKeys, true)) {
            return true;
        }
        if ($path === 'API_OPENAI_KEY') {
            $API_OPENAI_KEY = (string)$value;
            return true;
        }
        if ($path === 'API_GEMINI_KEY') {
            $API_GEMINI_KEY = (string)$value;
            return true;
        }
        if ($path === 'TINYMCE_API_KEY') {
            $TINYMCE_API_KEY = (string)$value;
            return true;
        }
        if ($path === 'ECOMMERCE_VAT_DEFAULT') {
            $ECOMMERCE_VAT_DEFAULT = (float)$value;
            return true;
        }
        if ($path === 'SITE_HOTLINE') {
            $hotline = (string)$value;
            return true;
        }

        $parts = explode('.', $path);
        if (count($parts) < 2) return false;
        $root = array_shift($parts);

        switch ($root) {
            case 'ECOMMERCE_XU':
                $target = &$ECOMMERCE_XU;
                break;
            case 'ECOMMERCE_GHN':
                $target = &$ECOMMERCE_GHN;
                break;
            case 'ECOMMERCE_SHIPPING':
                $target = &$ECOMMERCE_SHIPPING;
                break;
            case 'GOOGLE_LOGIN':
                $target = &$GOOGLE_LOGIN;
                break;
            case 'ZALO_LOGIN':
                $target = &$ZALO_LOGIN;
                break;
            case 'ECOMMERCE_REGIONS':
                $target = &$ECOMMERCE_REGIONS;
                break;
            case 'ECOMMERCE_PAYMENT_METHODS':
                $target = &$ECOMMERCE_PAYMENT_METHODS;
                break;
            case 'COMPANY_INFO':
                $target = &$COMPANY_INFO;
                break;
            default:
                return false;
        }

        $cursor = &$target;
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = [];
            }
            $cursor = &$cursor[$part];
        }
        $cursor[$last] = $value;
        return true;
    }
}

if (!function_exists('ecommerce_order_status_info')) {
    /**
     * Chuẩn hoá key trạng thái đơn hàng và trả về label tiếng Việt thống nhất.
     * Sử dụng cho cả frontend (user) và backend (admin).
     */
    function ecommerce_order_status_info(?string $status): array {
        $raw = strtolower(trim((string)$status));

        // Chuẩn hoá một số alias cũ sang key chuẩn
        if ($raw === 'completed') {
            $raw = 'delivered';
        } elseif ($raw === 'return') {
            $raw = 'return_requested';
        }

        $labels = [
            'pending'          => 'Chờ xác nhận',
            'processing'       => 'Chuẩn bị hàng',
            'shipping'         => 'Đang giao',
            'delivered'        => 'Đã giao',
            'cancel_requested' => 'Chờ duyệt hủy',
            'return_requested' => 'Chờ duyệt trả hàng',
            'returned'         => 'Đã hoàn trả hàng',
            'refunded'         => 'Đã hoàn tiền',
            'canceled'         => 'Đơn đã hủy',
        ];

        $key = array_key_exists($raw, $labels) ? $raw : 'pending';

        return [
            'key' => $key,
            'label' => $labels[$key],
        ];
    }
}

if (!function_exists('ecommerce_payment_info')) {
    /**
     * Chuẩn hoá và gom toàn bộ thông tin thanh toán: trạng thái + phương thức.
     * Trả về cả key và label để dùng thống nhất trong API/admin.
     */
    function ecommerce_payment_info(?string $method, ?string $status, ?string $gateway = ''): array {
        $statusKey = strtolower(trim((string)$status));
        if ($statusKey === '') {
            $statusKey = 'pending';
        }

        $statusLabels = [
            'pending' => 'Chờ thanh toán',
            'unpaid' => 'Chưa thanh toán',
            'paid' => 'Đã thanh toán',
            'failed' => 'Không thể thanh toán',
            'expired' => 'Hết hạn thanh toán',
            'refund_pending' => 'Cần hoàn tiền',
            'refunded' => 'Đã hoàn tiền',
            // Some installs store COD as a payment_status; treat it as a status label (not method label)
            'cod' => 'Thanh toán khi nhận hàng',
        ];

        $statusLabel = $statusLabels[$statusKey] ?? 'Chưa cập nhật';

        $methodKey = strtolower(trim((string)$method));
        $gatewayKey = strtolower(trim((string)$gateway));

        $methodLabels = [
            'cod' => 'Tiền mặt',
            'bank' => 'Chuyển khoản',
            'card' => 'Thẻ',
            'vnpay' => 'VNPAY',
            'momo' => 'MoMo',
            'ewallet' => 'Ví điện tử',
            'installment' => 'Trả góp',
        ];

        $label = '';
        $effectiveKey = $methodKey !== '' ? $methodKey : $gatewayKey;

        if ($effectiveKey !== '' && isset($methodLabels[$effectiveKey])) {
            $label = $methodLabels[$effectiveKey];
        } elseif ($methodKey !== '') {
            $label = strtoupper($methodKey);
        } elseif ($gatewayKey !== '') {
            $label = strtoupper($gatewayKey);
        } else {
            $label = 'Chưa rõ';
        }

        return [
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
            'method_key' => $effectiveKey,
            'method_label' => $label,
        ];
    }
}

if (!function_exists('app_get_momo_config_by_env')) {
    /**
     * Trả về cấu hình MoMo theo môi trường (test/production) đã chọn.
     */
    function app_get_momo_config_by_env(?string $env = null): array {
        global $ECOMMERCE_PAYMENT_METHODS;
        $methods = is_array($ECOMMERCE_PAYMENT_METHODS) ? $ECOMMERCE_PAYMENT_METHODS : [];
        $envRaw = strtolower(trim((string)($env ?? ($methods['momo_env'] ?? 'production'))));
        $useTest = in_array($envRaw, ['test', 'testing', 'sandbox'], true);

        $prod = is_array($methods['momo'] ?? null) ? $methods['momo'] : [];
        $test = is_array($methods['momo_test'] ?? null) ? $methods['momo_test'] : [];
        $cfg = $useTest ? $test : $prod;

        $createUrl = trim((string)($cfg['createUrl'] ?? ''));
        if ($createUrl === '') {
            $createUrl = 'https://test-payment.momo.vn/v2/gateway/api/create';
        }

        $queryUrl = trim((string)($cfg['queryUrl'] ?? ''));
        if ($queryUrl === '') {
            $queryUrl = 'https://test-payment.momo.vn/v2/gateway/api/query';
        }

        return [
            'enabled' => !empty($prod['enabled']),
            'partnerCode' => trim((string)($cfg['partnerCode'] ?? '')),
            'accessKey' => trim((string)($cfg['accessKey'] ?? '')),
            'secretKey' => trim((string)($cfg['secretKey'] ?? '')),
            'redirectUrl' => trim((string)($cfg['redirectUrl'] ?? '')),
            'ipnUrl' => trim((string)($cfg['ipnUrl'] ?? '')),
            'createUrl' => $createUrl,
            'queryUrl' => $queryUrl,
            'requestType' => 'captureWallet',
            'env' => $useTest ? 'test' : 'production',
        ];
    }
}

if (!function_exists('app_get_default_vat_percent')) {
    /**
     * Lấy phần trăm VAT mặc định từ cấu hình, giới hạn 0-100.
     */
    function app_get_default_vat_percent(): float {
        $raw = app_get_config_value_by_path('ECOMMERCE_VAT_DEFAULT');
        if (!is_numeric($raw)) {
            $raw = 8.0;
        }
        $vat = (float)$raw;
        if ($vat < 0) $vat = 0.0;
        if ($vat > 100) $vat = 100.0;
        return $vat;
    }
}

if (!function_exists('app_get_editable_config_values')) {
    /**
     * Trả danh sách config editable kèm metadata; có thể mask giá trị secret khi hiển thị.
     */
    function app_get_editable_config_values(bool $maskSecrets = false): array {
        $map = app_editable_config_map();
        $result = [];
        foreach ($map as $key => $meta) {
            $value = app_get_config_value_by_path($key);
            $displayValue = $value;
            if ($maskSecrets && !empty($meta['secret']) && is_string($displayValue) && $displayValue !== '') {
                $displayValue = str_repeat('•', max(8, min(20, strlen($displayValue))));
            }
            $result[$key] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'type' => $meta['type'] ?? 'string',
                'secret' => !empty($meta['secret']),
                'section' => $meta['section'] ?? 'General',
                'value' => $displayValue,
                'raw_value' => $value,
            ];
        }
        return $result;
    }
}

if (!function_exists('app_apply_bot_setting_overrides')) {
    /**
     * Áp cấu hình override từ bảng bot_setting vào biến runtime hiện tại.
     */
    function app_apply_bot_setting_overrides(mysqli $ithanhloc): void {
        if (!app_bot_setting_has_column($ithanhloc, 'setting_key') || !app_bot_setting_has_column($ithanhloc, 'setting_value')) {
            return;
        }

        $editableMap = app_editable_config_map();
        if (empty($editableMap)) {
            return;
        }

        $keys = array_keys($editableMap);
        $safeKeys = array_map([$ithanhloc, 'real_escape_string'], $keys);
        $in = "'" . implode("','", $safeKeys) . "'";
        $overrideSql = "SELECT setting_key, setting_value FROM site_setting WHERE setting_key IN ({$in})";
        $overrideRes = $ithanhloc->query($overrideSql);
        if (!$overrideRes) {
            return;
        }

        while ($row = $overrideRes->fetch_assoc()) {
            $settingKey = (string)($row['setting_key'] ?? '');
            if ($settingKey === '' || !isset($editableMap[$settingKey])) {
                continue;
            }

            $type = $editableMap[$settingKey]['type'] ?? 'string';
            $castValue = app_cast_config_value($row['setting_value'] ?? '', $type);
            app_set_config_value_by_path($settingKey, $castValue);
        }
    }
}

if (!function_exists('app_load_user_info')) {
    /**
     * Nạp thông tin user hiện tại (không áp dụng admin) để dùng cho giao diện/front-end.
     */
    function app_load_user_info(mysqli $ithanhloc): ?array {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $userId = (int)$_SESSION['user_id'];
        $stmt = $ithanhloc->prepare('SELECT id, username, full_name, phone, email, address, avatar, role FROM users WHERE id=? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userRow || ($userRow['role'] ?? 'user') === 'admin') {
            return null;
        }

        $displayName = $userRow['full_name'] ?: $userRow['username'];
        return [
            'id' => $userRow['id'],
            'name' => $displayName ?? '',
            'role' => $userRow['role'] ?? 'user',
            'email' => $userRow['email'] ?? '',
            'phone' => $userRow['phone'] ?? '',
            'address' => $userRow['address'] ?? '',
            'avatar' => $userRow['avatar'] ?? '',
        ];
    }
}

if (!function_exists('to_abs_banner_url')) {
    // Giữ lại để tương thích nơi gọi cũ; chuyển hết logic sang to_abs_url để
    // banner cũng được route sang media domain như mọi file media khác.
    function to_abs_banner_url(string $url, string $baseUrl): string {
        if (function_exists('to_abs_url')) {
            return to_abs_url($url, $baseUrl);
        }
        $raw = trim($url);
        if ($raw === '') return '';
        if (preg_match('#^(https?:)?//#i', $raw) || preg_match('#^data:#i', $raw)) return $raw;
        if ($baseUrl === '') return $raw;
        $path = $raw[0] === '/' ? $raw : '/' . $raw;
        return $baseUrl . $path;
    }
}

if (!function_exists('get_home_ad_banner')) {
    function get_home_ad_banner(mysqli $ithanhloc, string $pageKey, string $slotKey, string $baseUrl): array {
        $stmt = $ithanhloc->prepare('SELECT image_url, link_url FROM site_banner WHERE page_key=? AND slot_key=? AND is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ss', $pageKey, $slotKey);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $img = to_abs_banner_url((string)($row['image_url'] ?? ''), $baseUrl);
                if ($img !== '') {
                    return [
                        'image_url' => $img,
                        'link_url' => trim((string)($row['link_url'] ?? '')),
                    ];
                }
            }
        }
        return [
            'image_url' => '',
            'link_url' => '',
        ];
    }
}

if (!function_exists('ensure_user_saved_locations_index')) {
    function ensure_user_saved_locations_index(mysqli $ithanhloc): void {
        if (function_exists('partner_table_exists') && partner_table_exists($ithanhloc, 'user_saved_locations')) {
            return;
        }
        $sql = "CREATE TABLE IF NOT EXISTS `user_saved_locations` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `address_id` VARCHAR(64) NOT NULL,
            `payload_json` TEXT NOT NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_user_address` (`user_id`, `address_id`),
            KEY `idx_user_default` (`user_id`, `is_default`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        @ $ithanhloc->query($sql);
    }
}

if (!function_exists('ensure_user_notification_comment_schema')) {
    /**
     * Đảm bảo schema cho bình luận thông báo có đủ cột cho: trả lời (parent_id), nhận diện khách (guest_key), media (media_json).
     * Best-effort: nếu DB user không có quyền ALTER thì bỏ qua (không throw).
     */
    function ensure_user_notification_comment_schema(mysqli $ithanhloc): void {
        static $didRun = false;
        if ($didRun) {
            return;
        }
        $didRun = true;

        $table = 'user_notification_comment';

        $hasColumn = static function(string $col) use ($ithanhloc, $table): bool {
            $col = $ithanhloc->real_escape_string($col);
            $res = @$ithanhloc->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
            return ($res instanceof mysqli_result) && $res->num_rows > 0;
        };

        // Đảm bảo id là AUTO_INCREMENT
        $resId = @$ithanhloc->query("SHOW COLUMNS FROM `{$table}` WHERE Field='id' AND Extra LIKE '%auto_increment%'");
        if ($resId instanceof mysqli_result) {
            if ($resId->num_rows === 0) {
                @$ithanhloc->query("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
            }
            $resId->close();
        }

        // parent_id: hỗ trợ trả lời bình luận
        if (!$hasColumn('parent_id')) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `parent_id` INT(11) NOT NULL DEFAULT 0 AFTER `notification_id`");
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD KEY `idx_parent` (`parent_id`)");
        }

        // guest_key: cho phép guest sửa/xóa bình luận của chính họ (dựa cookie)
        if (!$hasColumn('guest_key')) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `guest_key` VARCHAR(64) DEFAULT NULL AFTER `user_id`");
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD KEY `idx_guest_key` (`guest_key`)");
        }

        // media_json: lưu danh sách media [{url,type,size,name}] giống pattern review
        if (!$hasColumn('media_json')) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `media_json` TEXT DEFAULT NULL AFTER `content`");
        }

        // ref_type/ref_id: mapping tới đối tượng gốc (vd: product_review) để có thể dựng parent_id cho log theo đúng thread
        if (!$hasColumn('ref_type')) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `ref_type` VARCHAR(40) DEFAULT NULL AFTER `media_json`");
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD KEY `idx_ref_type` (`ref_type`)");
        }
        if (!$hasColumn('ref_id')) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `ref_id` INT(11) NOT NULL DEFAULT 0 AFTER `ref_type`");
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD KEY `idx_ref_id` (`ref_id`)");
        }
        // Composite index (best-effort) để lookup nhanh theo thread + ref
        $hasIdxNoticeRef = false;
        $resIdx = @$ithanhloc->query("SHOW INDEX FROM `{$table}` WHERE Key_name='idx_notice_ref'");
        if ($resIdx instanceof mysqli_result && $resIdx->num_rows > 0) {
            $hasIdxNoticeRef = true;
            $resIdx->close();
        }
        if (!$hasIdxNoticeRef) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD KEY `idx_notice_ref` (`notification_id`, `ref_type`, `ref_id`)");
        }

        // updated_at: lưu dấu thời gian chỉnh sửa
        if (!$hasColumn('updated_at')) {
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `updated_at` DATETIME DEFAULT NULL AFTER `created_at`");
            @ $ithanhloc->query("ALTER TABLE `{$table}` ADD KEY `idx_updated_at` (`updated_at`)");
        }
    }
}

/**
 * Đảm bảo cấu trúc bảng site_partner (đối tác)
 * Thêm auto_increment cho id nếu thiếu để tránh lỗi Duplicate entry 0.
 */
function ensure_site_partner_schema(mysqli $ithanhloc): void {
    static $didRun = false;
    if ($didRun) return;
    $didRun = true;

    $table = 'site_partner';
    
    // 1. Kiểm tra AUTO_INCREMENT cho id
    $resId = @$ithanhloc->query("SHOW COLUMNS FROM `{$table}` WHERE Field='id' AND Extra LIKE '%auto_increment%'");
    if ($resId instanceof mysqli_result) {
        if ($resId->num_rows === 0) {
            @$ithanhloc->query("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        }
        $resId->close();
    }

    // 2. Kiểm tra các cột cần thiết khác
    $hasColumn = static function(string $col) use ($ithanhloc, $table): bool {
        $col = $ithanhloc->real_escape_string($col);
        $res = @$ithanhloc->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        return ($res instanceof mysqli_result) && $res->num_rows > 0;
    };

    if (!$hasColumn('is_active')) {
        @$ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!$hasColumn('sort_order')) {
        @$ithanhloc->query("ALTER TABLE `{$table}` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 100");
    }
}

/**
 * Đảm bảo cấu trúc bảng user_saved_voucher (voucher đã lưu của user)
 * Thêm auto_increment cho id nếu thiếu.
 */
function ensure_user_saved_voucher_schema(mysqli $ithanhloc): void {
    static $didRun = false;
    if ($didRun) return;
    $didRun = true;

    $table = 'user_saved_voucher';
    
    $resId = @$ithanhloc->query("SHOW COLUMNS FROM `{$table}` WHERE Field='id' AND Extra LIKE '%auto_increment%'");
    if ($resId instanceof mysqli_result) {
        if ($resId->num_rows === 0) {
            @$ithanhloc->query("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        }
        $resId->close();
    }
}

if (!function_exists('format_vnd')) {
    function format_vnd($n): string {
        $raw = (float)$n;
        // Làm tròn về 1.000đ gần nhất, chỉ làm tròn lên khi phần lẻ >= 500đ
        if ($raw > 0) {
            $val = round($raw / 1000) * 1000;
        } else {
            $val = 0;
        }
        return number_format($val, 0, ',', '.') . ' đ';
    }
}

if (!function_exists('normalize_price')) {
    function normalize_price($value): float {
        if (is_numeric($value)) {
            return (float)$value;
        }
        $raw = preg_replace('/[^0-9]/', '', (string)$value);
        return $raw !== '' ? (float)$raw : 0.0;
    }
}

if (!function_exists('to_abs_url')) {
    function to_abs_url(string $url, string $baseUrl): string {
        global $mediaBaseUrl, $uploadFolder, $mediaKeepUploadsPrefix;

        $raw = trim($url);
        if ($raw === '') return '';
        // URL đã tuyệt đối (http/https/protocol-relative) hoặc data: → giữ nguyên.
        if (preg_match('#^(https?:)?//#i', $raw) || preg_match('#^data:#i', $raw)) return $raw;

        // Chuẩn hoá path tương đối: bỏ '/' đầu, đổi '\' thành '/'.
        $rel = str_replace('\\', '/', ltrim($raw, '/'));

        // Nếu là file MEDIA (nằm trong thư mục upload) và đã cấu hình media domain
        // → phục vụ từ $mediaBaseUrl.
        //   - $mediaKeepUploadsPrefix = true : giữ 'uploads/<path>' (subdomain map cấp cha)
        //   - false                          : bỏ 'uploads/' (subdomain map thẳng vào uploads/)
        $folder = isset($uploadFolder) && $uploadFolder !== '' ? trim((string)$uploadFolder, '/') : 'uploads';
        $mediaBase = isset($mediaBaseUrl) ? (string)$mediaBaseUrl : '';
        $keepPrefix = isset($mediaKeepUploadsPrefix) ? (bool)$mediaKeepUploadsPrefix : true;
        if ($mediaBase !== '' && ($rel === $folder || strpos($rel, $folder . '/') === 0)) {
            if ($keepPrefix) {
                return rtrim($mediaBase, '/') . '/' . $rel; // .../uploads/<path>
            }
            $mediaRel = ltrim(substr($rel, strlen($folder)), '/');
            return rtrim($mediaBase, '/') . '/' . $mediaRel; // .../<path>
        }

        // Tài nguyên tĩnh khác (vd /image/...) → vẫn ghép baseUrl như cũ.
        if ($baseUrl === '') return $raw;
        return rtrim($baseUrl, '/') . '/' . $rel;
    }
}

// ============================================================
// MEDIA REMOTE — đẩy / xoá file trên VPS media qua HTTP receiver (HMAC).
// ============================================================
if (!function_exists('media_remote_root_fs')) {
    /** Đường dẫn tuyệt đối tới gốc project (để map relPath 'uploads/...' ra file thật). */
    function media_remote_root_fs(): string {
        return rtrim(str_replace('\\', '/', dirname(__DIR__) . '/' . basename(__DIR__)), '/');
    }
}

if (!function_exists('media_remote_sign')) {
    /** Tạo chữ ký HMAC cho 1 request tới receiver. */
    function media_remote_sign(string $secret, string $action, string $relPath, string $ts, string $fileHash = ''): string {
        return hash_hmac('sha256', $ts . '|' . $action . '|' . $relPath . '|' . $fileHash, $secret);
    }
}

if (!function_exists('media_publish_local_file')) {
    /**
     * Đẩy 1 file ĐÃ hoàn thiện ở local (sau resize/WebP) lên VPS media, rồi XOÁ bản local.
     * $relPath: đường dẫn tương đối kiểu 'uploads/media/x.webp' (đúng giá trị lưu DB).
     * Trả về true nếu đã publish (hoặc remote tắt → coi như no-op thành công).
     * Khi remote BẬT mà đẩy lỗi → trả false (giữ nguyên file local để không mất dữ liệu).
     */
    function media_publish_local_file(string $relPath): bool {
        global $mediaRemoteEnabled, $mediaReceiverUrl, $mediaSecret, $uploadFolder;
        if (empty($mediaRemoteEnabled)) return true; // remote tắt → giữ local như cũ.

        $rel = ltrim(str_replace('\\', '/', $relPath), '/');
        $folder = trim((string)($uploadFolder ?? 'uploads'), '/');
        if ($rel === '' || ($rel !== $folder && strpos($rel, $folder . '/') !== 0)) {
            return true; // không phải file media → bỏ qua.
        }

        $absFs = media_remote_root_fs() . '/' . $rel;
        if (!is_file($absFs)) {
            error_log("[media_publish] file local không tồn tại: {$absFs}");
            return false;
        }

        $ts = (string)time();
        $fileHash = hash_file('sha256', $absFs);
        $sign = media_remote_sign($mediaSecret, 'store', $rel, $ts, $fileHash);

        $mime = function_exists('mime_content_type') ? (@mime_content_type($absFs) ?: 'application/octet-stream') : 'application/octet-stream';
        $cfile = new CURLFile($absFs, $mime, basename($rel));
        $post = [
            'action'   => 'store',
            'rel_path' => $rel,
            'ts'       => $ts,
            'file_hash'=> $fileHash,
            'sign'     => $sign,
            'file'     => $cfile,
        ];

        $ch = curl_init($mediaReceiverUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $json = json_decode((string)$resp, true);
        if ($code === 200 && is_array($json) && !empty($json['ok'])) {
            @unlink($absFs); // đã lên VPS → xoá bản local
            return true;
        }
        error_log("[media_publish] đẩy lỗi rel={$rel} http={$code} err={$err} resp=" . substr((string)$resp, 0, 300));
        return false;
    }
}

if (!function_exists('media_delete_remote')) {
    /** Xoá file trên VPS media theo relPath. No-op nếu remote tắt. */
    function media_delete_remote(string $relPath): bool {
        global $mediaRemoteEnabled, $mediaReceiverUrl, $mediaSecret, $uploadFolder;
        if (empty($mediaRemoteEnabled)) return true;

        $rel = ltrim(str_replace('\\', '/', $relPath), '/');
        $folder = trim((string)($uploadFolder ?? 'uploads'), '/');
        if ($rel === '' || ($rel !== $folder && strpos($rel, $folder . '/') !== 0)) return true;

        $ts = (string)time();
        $sign = media_remote_sign($mediaSecret, 'delete', $rel, $ts, '');
        $ch = curl_init($mediaReceiverUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['action' => 'delete', 'rel_path' => $rel, 'ts' => $ts, 'sign' => $sign],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        return $code === 200 && is_array($json) && !empty($json['ok']);
    }
}

if (!function_exists('parse_coupon_product_ids')) {
    function parse_coupon_product_ids($raw): array {
        $txt = trim((string)$raw);
        if ($txt === '') return [];
        $parts = preg_split('/[^0-9]+/', $txt);
        $ids = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id > 0) $ids[$id] = $id;
        }
        return array_values($ids);
    }
}

if (!function_exists('coupon_applies_to_product')) {
    function coupon_applies_to_product(array $coupon, int $productId): bool {
        // Hàm này chỉ dùng cho mục đích DEMO hiển thị badge giảm giá
        // (home_user, shopping, gợi ý sản phẩm), không dùng cho validate đơn hàng.
        // Vì vậy, để hiển thị nhất quán giữa home_user và shopping,
        // ta cố ý chỉ xét theo apply_scope/apply_product_ids giống logic cũ,
        // KHÔNG áp dụng filter ngành hàng chi tiết từ couponAppliesToProductIds.

        if ($productId <= 0) return false;

        $scope = strtolower(trim((string)($coupon['apply_scope'] ?? 'all')));
        if ($scope !== 'products') {
            // scope = all (hoặc giá trị khác) → hiểu là áp dụng cho mọi sản phẩm để hiển thị demo
            return true;
        }

        $targets = parse_coupon_product_ids($coupon['apply_product_ids'] ?? '');
        if (!$targets) return false;
        return in_array($productId, $targets, true);
    }
}

if (!function_exists('couponAppliesToProductIds')) {
    /**
     * Kiểm tra xem coupon có áp dụng cho bất kỳ sản phẩm nào trong danh sách hay không.
     * Dùng cho mục đích demo/hiển thị nhanh (badge, suggest).
     */
    function couponAppliesToProductIds(array $coupon, array $productIds): bool {
        if (empty($productIds)) return false;
        
        foreach ($productIds as $pid) {
            if (coupon_applies_to_product($coupon, (int)$pid)) {
                return true;
            }
        }
        return false;
    }
}

// Tính toán chiết khấu của coupon cho một giá tiền cụ thể, dùng để hiển thị badge giảm giá (nếu có)
if (!function_exists('coupon_discount_for_price')) {
    function coupon_discount_for_price(array $coupon, float $price): float {
        if ($price <= 0) return 0;

        $min = (float)($coupon['min_subtotal'] ?? 0);
        if ($price < $min) return 0;

        // Xác định đơn vị giá trị (percent/fixed) theo value_unit là nguồn sự thật,
        // fallback sang type nếu value_unit không rõ ràng.
        $valueUnit = strtolower(trim((string)($coupon['value_unit'] ?? '')));
        if ($valueUnit !== 'percent' && $valueUnit !== 'fixed') {
            $rawType = strtolower(trim((string)($coupon['type'] ?? 'fixed')));
            $valueUnit = $rawType === 'percent' ? 'percent' : 'fixed';
        }

        $disc = 0.0;
        $value = (float)($coupon['value'] ?? 0);

        if ($valueUnit === 'percent') {
            $disc = $price * $value / 100.0;
            if (($coupon['max_discount'] ?? null) !== null && (string)$coupon['max_discount'] !== '') {
                $disc = min($disc, (float)$coupon['max_discount']);
            }
        } else {
            // fixed amount
            $disc = $value;
        }

        return max(0, min($disc, $price));
    }
}

// Chuẩn hoá mệnh giá voucher theo type/value để hiển thị thống nhất
if (!function_exists('format_voucher_value_label')) {
    function format_voucher_value_label(string $type, float $value): string {
        $type = strtolower(trim($type));
        $v = max(0.0, (float)$value);
        if ($v <= 0) return '';

        if ($type === 'percent') {
            if ($v >= 100) {
                return 'Miễn phí';
            }
            // Giữ nguyên đơn vị % cho mệnh giá phần trăm
            // (không dùng đơn vị tiền cho type=percent)
            $txt = rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
            return $txt . '%';
        }

        // type fixed/amount: dùng đơn vị K, tuyệt đối không dùng % hoặc "đ" cho mệnh giá
        if ($v < 1000) {
            // Giá trị quá nhỏ: hiển thị số nguyên, không kèm đơn vị tiền
            return (string)round($v);
        }
        $k = $v / 1000.0;
        $txt = (abs($k - round($k)) < 0.01)
            ? (string)round($k)
            : number_format($k, 1, '.', '');
        $txt = rtrim(rtrim($txt, '0'), '.');
        return $txt . 'k';
    }
}
// Tìm coupon có chiết khấu tốt nhất cho sản phẩm này để hiển thị badge giảm giá (nếu có)
if (!function_exists('best_coupon_demo_for_product')) {
    function best_coupon_demo_for_product(array $coupons, int $productId, float $price): array {
        $bestDiscount = 0.0;
        $bestLabel = '';
        $bestType = '';
        $bestValue = 0.0;
        foreach ($coupons as $coupon) {
            if (!coupon_applies_to_product($coupon, $productId)) continue;
            $disc = coupon_discount_for_price($coupon, $price);
            if ($disc <= $bestDiscount) continue;
            $bestDiscount = $disc;
            $bestLabel = (string)($coupon['code'] ?? '');
            // Chuẩn hoá type cho demo theo value_unit (percent/fixed)
            $valueUnit = strtolower(trim((string)($coupon['value_unit'] ?? '')));
            if ($valueUnit !== 'percent' && $valueUnit !== 'fixed') {
                $rawType = strtolower(trim((string)($coupon['type'] ?? 'fixed')));
                $valueUnit = $rawType === 'percent' ? 'percent' : 'fixed';
            }
            $bestType = $valueUnit;
            $bestValue = (float)($coupon['value'] ?? 0);
        }
        return [
            'discount' => $bestDiscount,
            'label' => $bestLabel,
            'type' => $bestType,
            'value' => $bestValue,
        ];
    }
}

if (!function_exists('list_table_columns')) {
    function list_table_columns(mysqli $ithanhloc, string $table): array {
        static $columnCache = [];
        $cols = [];
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($safe === '') return $cols;
        if (isset($columnCache[$safe])) return $columnCache[$safe];
        $res = $ithanhloc->query("SHOW COLUMNS FROM `$safe`");
        if (!$res) return $cols;
        while ($row = $res->fetch_assoc()) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') $cols[] = $field;
        }
        $columnCache[$safe] = $cols;
        return $cols;
    }
}

if (!function_exists('get_home_carousel_banners')) {
    function get_home_carousel_banners(mysqli $ithanhloc, string $pageKey, string $baseUrl): array {
        $items = [];
        $keys = [$pageKey, 'home_user', 'home_guest', 'home'];
        $keys = array_values(array_unique(array_filter($keys, static fn($k) => trim((string)$k) !== '')));

        $stmt = $ithanhloc->prepare("SELECT image_url, link_url, title, content FROM site_banner WHERE page_key=? AND slot_key='carousel' AND is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 10");
        if ($stmt) {
            foreach ($keys as $key) {
                $rows = [];
                $stmt->bind_param('s', $key);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    $rows = $res->fetch_all(MYSQLI_ASSOC) ?: [];
                }
                if (!$rows) {
                    continue;
                }

                foreach ($rows as $row) {
                    $img = to_abs_url((string)($row['image_url'] ?? ''), $baseUrl);
                    if ($img === '') continue;
                    $items[] = [
                        'image_url' => $img,
                        'link_url' => trim((string)($row['link_url'] ?? '')),
                        'title' => trim((string)($row['title'] ?? '')),
                        'content' => trim((string)($row['content'] ?? '')),
                    ];
                }

                if ($items) {
                    break;
                }
            }
            $stmt->close();
        }
        if (!$items) {
            $fallbackPath = $site_fallback_logo ?: '';
            $fallback = to_abs_url($fallbackPath, $baseUrl);
            $items = [
                ['image_url' => $fallback, 'link_url' => '', 'title' => '', 'content' => ''],
                ['image_url' => $fallback, 'link_url' => '', 'title' => '', 'content' => ''],
                ['image_url' => $fallback, 'link_url' => '', 'title' => '', 'content' => ''],
            ];
        }
        return $items;
    }
}

if (!function_exists('partner_table_exists')) {
    function partner_table_exists(mysqli $ithanhloc, string $table): bool {
        static $tablesCache = null;
        if ($tablesCache === null) {
            $tablesCache = [];
            $res = $ithanhloc->query("SHOW TABLES");
            if ($res) {
                while ($row = $res->fetch_row()) {
                    $tablesCache[strtolower($row[0])] = true;
                }
            }
        }
        return isset($tablesCache[strtolower($table)]);
    }
}

if (!function_exists('first_existing_table')) {
    function first_existing_table(mysqli $ithanhloc, array $candidates): string {
        foreach ($candidates as $table) {
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
            if ($name === '') continue;
            if (partner_table_exists($ithanhloc, $name)) {
                return $name;
            }
        }
        return '';
    }
}

if (!function_exists('pm_slugify')) {
    function pm_slugify(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return 'product';
        }

        // Vietnamese character mapping
        $map = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
            'À' => 'a', 'Á' => 'a', 'Ạ' => 'a', 'Ả' => 'a', 'Ã' => 'a',
            'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ậ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a',
            'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ặ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a',
            'È' => 'e', 'É' => 'e', 'Ẹ' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e',
            'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ệ' => 'e', 'Ể' => 'e', 'Ễ' => 'e',
            'Ì' => 'i', 'Í' => 'i', 'Ị' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i',
            'Ò' => 'o', 'Ó' => 'o', 'Ọ' => 'o', 'Ỏ' => 'o', 'Õ' => 'o',
            'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ộ' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o',
            'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ợ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o',
            'Ù' => 'u', 'Ú' => 'u', 'Ụ' => 'u', 'Ủ' => 'u', 'Ũ' => 'u',
            'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ự' => 'u', 'Ử' => 'u', 'Ữ' => 'u',
            'Ỳ' => 'y', 'Ý' => 'y', 'Ỵ' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y',
            'Đ' => 'd'
        ];

        $text = strtr($text, $map);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');
        return $text !== '' ? $text : 'san-pham';
    }
}

if (!function_exists('pm_product_url')) {
    function pm_product_url(int $id, string $name, string $baseUrl): string {
        $base = rtrim((string)$baseUrl, '/');
        $id = (int)$id;
        if ($id <= 0) {
            return $base !== '' ? $base . '/order' : '/order';
        }

        $slug = '';

        // Ưu tiên slug lưu trong DB nếu có cột slug
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            static $slugColumnChecked = false;
            static $hasSlugColumn = false;
            static $slugCache = [];

            $ithanhloc = $GLOBALS['conn'];

            if (!$slugColumnChecked) {
                $slugColumnChecked = true;
                $res = @$ithanhloc->query("SHOW COLUMNS FROM ecommerce_product LIKE 'slug'");
                $hasSlugColumn = $res && $res->num_rows > 0;
            }

            if ($hasSlugColumn) {
                if (array_key_exists($id, $slugCache)) {
                    $slug = (string)$slugCache[$id];
                } else {
                    $value = '';
                    if ($stmt = @$ithanhloc->prepare("SELECT slug FROM ecommerce_product WHERE id=? LIMIT 1")) {
                        $stmt->bind_param('i', $id);
                        if ($stmt->execute()) {
                            $row = $stmt->get_result()->fetch_assoc();
                            if ($row) {
                                $value = trim((string)($row['slug'] ?? ''));
                            }
                        }
                        $stmt->close();
                    }
                    $slugCache[$id] = $value;
                    $slug = $value;
                }
            }
        }

        if ($slug !== '') {
            $slug = pm_slugify($slug);
        } else {
            $slug = pm_slugify($name);
        }

        $path = '/product/' . $slug . '-' . $id;
        return $base !== '' ? $base . $path : $path;
    }
}

if (!function_exists('get_home_partner_store')) {
    function get_home_partner_store(mysqli $ithanhloc, string $baseUrl): array {
        $items = [];
        $res = $ithanhloc->query("SELECT id, partner_name, intro, image_url FROM site_partner WHERE is_active=1 ORDER BY sort_order ASC, id DESC LIMIT 24");
        if (!$res) return $items;

        while ($row = $res->fetch_assoc()) {
            $imageUrl = to_abs_url((string)($row['image_url'] ?? ''), $baseUrl);
            if ($imageUrl === '') continue;
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'partner_name' => trim((string)($row['partner_name'] ?? '')),
                'intro' => trim((string)($row['intro'] ?? '')),
                'image_url' => $imageUrl,
            ];
        }
        return $items;
    }
}

if (!function_exists('normalize_search_text')) {
    function normalize_search_text(string $text): string {
        $txt = trim($text);
        if ($txt === '') return '';

        $txt = function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);
        $map = [
            'Ã ' => 'a', 'Ã¡' => 'a', 'áº¡' => 'a', 'áº£' => 'a', 'Ã£' => 'a',
            'Ã¢' => 'a', 'áº§' => 'a', 'áº¥' => 'a', 'áº­' => 'a', 'áº©' => 'a', 'áº«' => 'a',
            'Äƒ' => 'a', 'áº±' => 'a', 'áº¯' => 'a', 'áº·' => 'a', 'áº³' => 'a', 'áºµ' => 'a',
            'Ã¨' => 'e', 'Ã©' => 'e', 'áº¹' => 'e', 'áº»' => 'e', 'áº½' => 'e',
            'Ãª' => 'e', 'á»' => 'e', 'áº¿' => 'e', 'á»‡' => 'e', 'á»ƒ' => 'e', 'á»…' => 'e',
            'Ã¬' => 'i', 'Ã­' => 'i', 'á»‹' => 'i', 'á»‰' => 'i', 'Ä©' => 'i',
            'Ã²' => 'o', 'Ã³' => 'o', 'á»' => 'o', 'á»' => 'o', 'Ãµ' => 'o',
            'Ã´' => 'o', 'á»“' => 'o', 'á»‘' => 'o', 'á»™' => 'o', 'á»•' => 'o', 'á»—' => 'o',
            'Æ¡' => 'o', 'á»' => 'o', 'á»›' => 'o', 'á»£' => 'o', 'á»Ÿ' => 'o', 'á»¡' => 'o',
            'Ã¹' => 'u', 'Ãº' => 'u', 'á»¥' => 'u', 'á»§' => 'u', 'Å©' => 'u',
            'Æ°' => 'u', 'á»«' => 'u', 'á»©' => 'u', 'á»±' => 'u', 'á»­' => 'u', 'á»¯' => 'u',
            'á»³' => 'y', 'Ã½' => 'y', 'á»µ' => 'y', 'á»·' => 'y', 'á»¹' => 'y',
            'Ä‘' => 'd',
        ];
        $txt = strtr($txt, $map);
        $txt = preg_replace('/\s+/', ' ', $txt);
        return trim((string)$txt);
    }
}

if (!function_exists('text_has_any_keyword')) {
    function text_has_any_keyword(string $text, array $keywords): bool {
        foreach ($keywords as $keyword) {
            if ($keyword === '') continue;
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('detect_product_segment')) {
    function detect_product_segment(string $categoryName, string $productName): string {
        $text = normalize_search_text($categoryName . ' ' . $productName);
        if ($text === '') return 'other_tools';

        if (text_has_any_keyword($text, ['noi that', 'ngoai that', 'son noi that', 'son ngoai that', 'son tuong', 'chong tham'])) {
            return 'interior_exterior';
        }
        if (text_has_any_keyword($text, ['diy', 'son xit', 'xit son', 'spray', 'tu son'])) {
            return 'diy';
        }
        if (text_has_any_keyword($text, ['my thuat', 'nghe thuat', 've tranh', 'acrylic', 'canvas', 'tranh'])) {
            return 'art';
        }
        if (text_has_any_keyword($text, ['dung cu', 'con lan', 'co son', 'ru lo', 'ban cha', 'phu kien', 'dao', 'khay son'])) {
            return 'other_tools';
        }

        return 'other_tools';
    }
}

if (!function_exists('detect_brand_name')) {
    function detect_brand_name(string $productName): string {
        $text = normalize_search_text($productName);
        $brandMap = [
            'jotun' => 'Jotun',
            'dulux' => 'Dulux',
            'nippon' => 'Nippon',
            'kova' => 'Kova',
            'mykolor' => 'Mykolor',
            'spec' => 'Spec',
            'maxilite' => 'Maxilite',
            'toa' => 'TOA',
            'sika' => 'Sika',
            'boss' => 'Boss',
        ];
        foreach ($brandMap as $needle => $label) {
            if (strpos($text, $needle) !== false) {
                return $label;
            }
        }

        $parts = preg_split('/\s+/', trim($productName));
        $first = $parts && isset($parts[0]) ? preg_replace('/[^\p{L}\p{N}\-]/u', '', (string)$parts[0]) : '';
        if ($first !== '') {
            return $first;
        }
        return 'Hãng khác';
    }
}

if (!function_exists('map_partner_brand_name')) {
    function map_partner_brand_name(string $brandRaw, array $partnerNameMap): string {
        $brand = trim($brandRaw);
        if ($brand === '') return '';
        $key = normalize_search_text($brand);
        if ($key === '') return '';
        return $partnerNameMap[$key] ?? '';
    }
}

if (!function_exists('clamp_rating_0_5')) {
    function clamp_rating_0_5(float $rating): float {
        return max(0.0, min(5.0, $rating));
    }
}

if (!function_exists('render_star_rating_html')) {
    function render_star_rating_html(float $rating, int $reviewCount): string {
        $emptyStarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 14 14"><path fill="#D1D5DB" d="m3.597 8.977-.74 4.538a.44.44 0 0 0 .644.454l3.844-2.128 3.845 2.128a.44.44 0 0 0 .463-.026.44.44 0 0 0 .18-.428l-.74-4.538 3.128-3.21a.44.44 0 0 0-.247-.74L9.67 4.37 7.74.254c-.143-.307-.647-.307-.79 0L5.02 4.369l-4.303.659a.437.437 0 0 0-.248.739zM5.383 5.2a.44.44 0 0 0 .33-.246L7.345 1.47l1.632 3.482a.44.44 0 0 0 .33.247L13 5.765l-2.686 2.757a.44.44 0 0 0-.119.377l.63 3.866-3.268-1.809a.44.44 0 0 0-.423 0l-3.268 1.809.63-3.866a.44.44 0 0 0-.12-.377L1.69 5.765z"></path></svg>';
        if ($reviewCount === 0) {
            $starsHtml = str_repeat('<span style="cursor: inherit; display: inline-block; position: relative;"><span style="display: inline-block;">' . $emptyStarSvg . '</span></span>', 5);
            return '<div class="box-rating flex justify-center items-center gap-1 min-h-5">'
                . '<span style="display: inline-block; direction: ltr;">' . $starsHtml . '</span>'
                . '</div>';
        }

        $rating = clamp_rating_0_5($rating);
        $ratingText = number_format($rating, 1);
        $starsHtml = '';

        $fullStarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 14 14"><path fill="#F2994A" d="m3.252 8.952-.74 4.538a.438.438 0 0 0 .644.454L7 11.816l3.844 2.129a.438.438 0 0 0 .644-.454l-.74-4.538 3.127-3.21a.438.438 0 0 0-.246-.739l-4.304-.658L7.395.23c-.143-.307-.647-.307-.79 0l-1.93 4.115-4.303.659a.438.438 0 0 0-.247.739z"></path></svg>';

        for ($i = 1; $i <= 5; $i++) {
            $fillPercent = max(0, min(100, ($rating - ($i - 1)) * 100));
            $starsHtml .= '<span style="cursor: inherit; display: inline-block; position: relative;">'
                . '<span style="display: inline-block;">' . $emptyStarSvg . '</span>'
                . '<span style="display: inline-block; position: absolute; overflow: hidden; top: 0px; left: 0px; width: ' . $fillPercent . '%;">'
                . '<div class="mr-1">' . $fullStarSvg . '</div>'
                . '</span>'
                . '</span>';
        }
        return '<div class="box-rating flex justify-center items-center gap-1 min-h-5">'
            . '<span style="display: inline-block; direction: ltr;">' . $starsHtml . '</span>'
            . '<div class="rating-score text-14 mt-0.5 d-none">' . h($ratingText) . '</div>'
            . '</div>';
    }
}

if (!function_exists('app_get_current_user_id')) {
    /**
     * Lấy ID người dùng hiện tại từ session.
     */
    function app_get_current_user_id() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return intval($_SESSION['user_id'] ?? 0);
    }
}

// ==========================
// API FUNCTIONS FOR REFACTORING
// ==========================


if (!function_exists('GetProducts')) {
    /**
     * Lấy danh sách sản phẩm theo các tham số truyền vào.
     * Trả về mảng các sản phẩm đã được xử lý (giá, ảnh, voucher...).
     */
    function GetProducts(mysqli $ithanhloc, array $params = []) {
        global $baseUrl, $site_fallback_logo;

        $offset = max(0, intval($params['offset'] ?? 0));
        $limit = max(1, min(100, intval($params['limit'] ?? 10)));
        $categoryId = intval($params['category_id'] ?? 0);
        
        $productTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product']) : 'ecommerce_product';
        $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
        $categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category']) : 'ecommerce_category';
        $reviewTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_review']) : 'ecommerce_product_review';

        if ($productTable === '') return [];

        $productCols = function_exists('listColumns') ? listColumns($ithanhloc, $productTable) : [];
        $productActiveExpr = '';
        if (function_exists('hasCol') && hasCol($productCols, 'status')) {
            $productActiveExpr = "(p.status = TRUE OR p.status = 1 OR p.status = '1' OR LOWER(p.status) = 'true')";
        } elseif (function_exists('hasCol') && hasCol($productCols, 'is_active')) {
            $productActiveExpr = 'p.is_active = 1';
        }

        $hasVariantTable = false;
        try { $chk = $ithanhloc->query("SHOW TABLES LIKE '{$variantTable}'"); $hasVariantTable = ($chk && $chk->num_rows > 0); } catch (Throwable $e) {}
        $hasCategoryTable = false;
        try { $chk = $ithanhloc->query("SHOW TABLES LIKE '{$categoryTable}'"); $hasCategoryTable = ($chk && $chk->num_rows > 0); } catch (Throwable $e) {}
        $hasReviewTable = false;
        try { $chk = $ithanhloc->query("SHOW TABLES LIKE '{$reviewTable}'"); $hasReviewTable = ($chk && $chk->num_rows > 0); } catch (Throwable $e) {}

        $vatMultiplierExpr = '1.08';
        if (function_exists('hasCol') && hasCol($productCols, 'vat') && hasCol($productCols, 'vat_enabled')) {
            $vatMultiplierExpr = '(CASE WHEN COALESCE(p.vat_enabled, 1) = 1 THEN (1 + (COALESCE(p.vat, 8) / 100)) ELSE 1 END)';
        }

        $variantMinBaseExpr = $hasVariantTable ? "COALESCE((SELECT MIN(price) FROM `{$variantTable}` WHERE product_id = p.id), 0)" : '0';
        $giaMinExpr = "({$variantMinBaseExpr} * {$vatMultiplierExpr}) AS gia_min";
        $categoryJoin = $hasCategoryTable ? "LEFT JOIN `{$categoryTable}` c ON c.id = p.category_id" : '';
        $categoryNameExpr = $hasCategoryTable ? 'c.name AS category_name,' : "'' AS category_name,";
        $ratingSelect = $hasReviewTable ? "(SELECT AVG(rating) FROM `{$reviewTable}` WHERE product_id = p.id) AS rating_avg, (SELECT COUNT(*) FROM `{$reviewTable}` WHERE product_id = p.id) AS rating_count," : '0 AS rating_avg, 0 AS rating_count,';

        $where = 'WHERE 1=1';
        if ($productActiveExpr !== '') $where .= " AND {$productActiveExpr}";
        if ($categoryId > 0) $where .= " AND p.category_id = " . $categoryId;

        $sql = "SELECT p.id, p.product_name, p.image_url, p.sold_count, {$categoryNameExpr} {$ratingSelect} {$giaMinExpr}
                FROM `{$productTable}` p {$categoryJoin} {$where} ORDER BY p.id DESC LIMIT ?, ?";

        $items = [];
        if ($stmt = $ithanhloc->prepare($sql)) {
            $stmt->bind_param('ii', $offset, $limit);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $activeVouchers = [];
            if (function_exists('getActiveVouchersForDemo')) $activeVouchers = getActiveVouchersForDemo($ithanhloc);

            foreach ($rows as $row) {
                $pid = intval($row['id']);
                $priceValue = function_exists('normalize_price') ? normalize_price($row['gia_min'] ?? 0) : 0;
                $thumb = trim((string)($row['image_url'] ?? ''));
                $thumbUrl = $thumb !== '' ? to_abs_url($thumb, (string)($baseUrl ?? '')) : to_abs_url($site_fallback_logo ?? '', (string)($baseUrl ?? ''));

                $demo = function_exists('best_coupon_demo_for_product') ? best_coupon_demo_for_product($activeVouchers, $pid, $priceValue) : [];
                $hasDemo = $priceValue > 0 && (float)($demo['discount'] ?? 0) > 0;
                $newPriceValue = max(0, $priceValue - (float)($demo['discount'] ?? 0));
                
                $discountPercent = 0;
                $voucherBadge = '';
                if ($hasDemo) {
                    $discountPercent = (int)round(((float)($demo['discount'] ?? 0) / max($priceValue, 1)) * 100);
                    $demoType = strtolower((string)($demo['type'] ?? ''));
                    $demoValue = (float)($demo['value'] ?? 0);
                    $valueLabel = '';
                    if (function_exists('format_voucher_value_label')) {
                        $valueLabel = format_voucher_value_label($demoType === '' ? 'fixed' : $demoType, $demoValue);
                    } else {
                        if ($demoType === 'percent') {
                            $valueLabel = rtrim(rtrim(number_format($demoValue, 1, '.', ''), '0'), '.') . '%';
                        } else {
                            $valueLabel = $demoValue >= 1000 ? (rtrim(rtrim(number_format($demoValue/1000, 1, '.', ''), '0'), '.') . 'K') : (string)round($demoValue);
                        }
                    }
                    $voucherBadge = ($discountPercent >= 100 || strtolower($valueLabel) === 'miễn phí') ? 'Miễn phí đơn hàng' : ($valueLabel !== '' ? 'Giảm ' . $valueLabel : '');
                }

                $ratingAvg = (float)($row['rating_avg'] ?? 0);
                $ratingCount = intval($row['rating_count'] ?? 0);

                $items[] = [
                    'id' => $pid,
                    'name' => trim((string)$row['product_name']),
                    'thumb_url' => $thumbUrl,
                    'price' => $priceValue,
                    'price_text' => $priceValue > 0 ? (function_exists('format_vnd') ? format_vnd($priceValue) : number_format($priceValue)) : 'Liên hệ',
                    'old_price_text' => $hasDemo ? (function_exists('format_vnd') ? format_vnd($priceValue) : number_format($priceValue)) : '',
                    'new_price_text' => $hasDemo ? (function_exists('format_vnd') ? format_vnd($newPriceValue) : number_format($newPriceValue)) : '',
                    'discount_label' => $hasDemo ? $voucherBadge : '',
                    'voucher_badge' => $voucherBadge,
                    'badge_text' => $voucherBadge !== '' ? $voucherBadge : 'Mới nhất',
                    'discount_percent' => $discountPercent,
                    'category_name' => trim((string)$row['category_name']) ?: 'Sơn tường',
                    'sold_text' => number_format(intval($row['sold_count'] ?? 0)),
                    'rating_text' => $ratingCount > 0 ? number_format($ratingAvg, 1) : '0.0',
                    'rating' => round($ratingAvg, 1)
                ];

            }
        }
        return $items;
    }
}

if (!function_exists('GetCart')) {
    /**
     * Lấy giỏ hàng đầy đủ thông tin sản phẩm.
     */
    function GetCart(mysqli $ithanhloc) {
        $cart = $_SESSION['shop_cart'] ?? [];
        if (!is_array($cart)) return [];
        
        $items = [];
        foreach ($cart as $item) {
            $pid = intval($item['product_id'] ?? $item['id'] ?? 0);
            $variantId = intval($item['variant_id'] ?? 0);
            $qty = intval($item['qty'] ?? 1);
            $color = (string)($item['color_code'] ?? '');
            
            $fullItem = BuildCartItem($ithanhloc, $pid, $variantId, $qty, $color);
            if ($fullItem) $items[] = $fullItem;
        }
        return $items;
    }
}

if (!function_exists('BuildCartItem')) {
    /**
     * Tạo một item giỏ hàng đầy đủ thông tin từ DB.
     */
    function BuildCartItem(mysqli $ithanhloc, int $pid, int $variantId, int $qty, string $colorCode = '') {
        global $baseUrl;
        // Logic tối giản dựa trên ajax/cart.php
        $stmt = $ithanhloc->prepare("SELECT id, product_name, image_url FROM ecommerce_product WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) return null;

        $price = 0;
        $variantName = '';
        if ($variantId > 0) {
            $stmtV = $ithanhloc->prepare("SELECT price, variant_name FROM ecommerce_product_variants WHERE id = ? AND product_id = ? LIMIT 1");
            $stmtV->bind_param('ii', $variantId, $pid);
            $stmtV->execute();
            $v = $stmtV->get_result()->fetch_assoc();
            $stmtV->close();
            if ($v) {
                $price = $v['price'];
                $variantName = $v['variant_name'];
            }
        }

        return [
            'product_id' => $pid,
            'variant_id' => $variantId,
            'name' => $p['product_name'],
            'thumb' => to_abs_url($p['image_url'], (string)$baseUrl),
            'variant' => $variantName,
            'color' => $colorCode,
            'price' => $price,
            'qty' => $qty,
            'total' => $price * $qty
        ];
    }
}

if (!function_exists('AddToCart')) {
    /**
     * Thêm sản phẩm vào giỏ hàng session.
     */
    function AddToCart(mysqli $ithanhloc, int $pid, int $variantId, int $qty, string $colorCode = '') {
        $cart = $_SESSION['shop_cart'] ?? [];
        $key = $pid . ':' . $variantId . ':' . strtoupper($colorCode);
        
        $found = false;
        foreach ($cart as &$item) {
            $itemKey = ($item['product_id'] ?? $item['id']) . ':' . ($item['variant_id'] ?? 0) . ':' . strtoupper($item['color_code'] ?? '');
            if ($itemKey === $key) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $cart[] = [
                'product_id' => $pid,
                'variant_id' => $variantId,
                'qty' => $qty,
                'color_code' => $colorCode
            ];
        }
        
        $_SESSION['shop_cart'] = $cart;
        return ['ok' => true, 'count' => count($cart)];
    }
}

if (!function_exists('Login')) {
    /**
     * Xử lý đăng nhập.
     */
    function Login(mysqli $ithanhloc, string $username, string $password) {
        $stmt = $ithanhloc->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1");
        $stmt->bind_param('sss', $username, $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'];
            
            if (function_exists('app_user_log')) {
                app_user_log($ithanhloc, $user['id'], 'login', 'Đăng nhập thành công');
            }
            
            return ['ok' => true, 'user' => ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]];
        }
        return ['ok' => false, 'msg' => 'Sai tài khoản hoặc mật khẩu'];
    }
}

if (!function_exists('Register')) {
    /**
     * Đăng ký tài khoản mới.
     */
    function Register(mysqli $ithanhloc, array $data) {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $email = trim($data['email'] ?? '');
        
        if (!$username || !$password || !$email) return ['ok' => false, 'msg' => 'Vui lòng nhập đầy đủ thông tin'];
        
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $ithanhloc->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param('sss', $username, $hash, $email);
        $ok = $stmt->execute();
        $id = $ithanhloc->insert_id;
        $stmt->close();
        
        if ($ok) {
            return ['ok' => true, 'id' => $id];
        }
        return ['ok' => false, 'msg' => 'Đăng ký thất bại hoặc tên đăng nhập/email đã tồn tại'];
    }
}


if (!function_exists('RespondJSON')) {
    /**
     * Trả về JSON và kết thúc xử lý.
     */
    function RespondJSON(array $data, int $status = 200) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}


if (!function_exists('GetOrders')) {
    /**
     * Lấy danh sách đơn hàng của người dùng.
     */
    function GetOrders(mysqli $ithanhloc, int $userId, array $filters = []) {
        $status = $filters['status'] ?? 'all';
        $limit = max(1, min(100, intval($filters['limit'] ?? 10)));
        $offset = max(0, intval($filters['offset'] ?? 0));

        $where = "WHERE user_id = ?";
        $params = [$userId];
        $types = "i";

        if ($status !== 'all') {
            $where .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $sql = "SELECT * FROM ecommerce_order {$where} ORDER BY created_at DESC LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";

        $items = [];
        if ($stmt = $ithanhloc->prepare($sql)) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($rows as $row) {
                // Thêm các thông tin format sẵn
                $row['total_text'] = function_exists('format_vnd') ? format_vnd($row['total_amount'] ?? 0) : number_format($row['total_amount'] ?? 0);
                $items[] = $row;
            }
        }
        return $items;
    }
}

if (!function_exists('GetOrder')) {
    /**
     * Lấy chi tiết một đơn hàng.
     */
    function GetOrder(mysqli $ithanhloc, $orderId, int $userId = 0) {
        $where = "WHERE (order_id = ? OR id = ?)";
        if ($userId > 0) $where .= " AND user_id = ?";
        
        $sql = "SELECT * FROM ecommerce_order {$where} LIMIT 1";
        if ($stmt = $ithanhloc->prepare($sql)) {
            if ($userId > 0) $stmt->bind_param('sii', $orderId, $orderId, $userId);
            else $stmt->bind_param('si', $orderId, $orderId);
            
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($order) {
                // Lấy danh sách sản phẩm trong đơn
                $stmtItems = $ithanhloc->prepare("SELECT * FROM ecommerce_order_item WHERE order_id = ?");
                $oid = $order['order_id'];
                $stmtItems->bind_param('s', $oid);
                $stmtItems->execute();
                $order['items'] = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtItems->close();
                return $order;
            }
        }
        return null;
    }
}

if (!function_exists('GetNotifications')) {
    /**
     * Lấy danh sách thông báo.
     */
    function GetNotifications(mysqli $ithanhloc, int $userId, int $limit = 10, int $offset = 0) {
        $sql = "SELECT * FROM user_notification 
                WHERE (user_id = ? OR user_id = 0) 
                AND (is_active = 1 OR is_active IS NULL)
                ORDER BY created_at DESC LIMIT ?, ?";
        
        $items = [];
        if ($stmt = $ithanhloc->prepare($sql)) {
            $stmt->bind_param('iii', $userId, $offset, $limit);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($rows as $row) {
                $row['time_ago'] = function_exists('time_ago') ? time_ago($row['created_at']) : $row['created_at'];
                $items[] = $row;
            }
        }
        return $items;
    }
}

if (!function_exists('GetUnreadNotificationCount')) {
    /**
     * Đếm số thông báo chưa đọc.
     */
    function GetUnreadNotificationCount(mysqli $ithanhloc, int $userId) {
        $count = 0;
        // Thông báo riêng
        $stmt = $ithanhloc->prepare("SELECT COUNT(*) as c FROM user_notification WHERE user_id = ? AND is_read = 0 AND is_active = 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $count += (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }
        // Thông báo chung (user_id = 0) chưa có trong bảng user_notification_read cho user này
        $stmt2 = $ithanhloc->prepare("SELECT COUNT(*) as c FROM user_notification n 
                                 LEFT JOIN user_notification_read r ON r.notification_id = n.id AND r.user_id = ? 
                                 WHERE n.user_id = 0 AND n.is_active = 1 AND r.id IS NULL");
        if ($stmt2) {
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $count += (int)($stmt2->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt2->close();
        }
        return $count;
    }
}
if (!function_exists('listColumns')) {
    /**
     * Lấy danh sách tên các cột trong một bảng.
     */
    function listColumns(mysqli $ithanhloc, string $table, bool $forceRefresh = false): array {
        static $columnCache = [];
        $tableSafe = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($tableSafe === '') return [];
        if (!$forceRefresh && isset($columnCache[$tableSafe])) return $columnCache[$tableSafe];
        $cols = [];
        $res = @$ithanhloc->query("SHOW COLUMNS FROM `{$tableSafe}`");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $f = (string)($row['Field'] ?? '');
                if ($f !== '') $cols[] = $f;
            }
            $res->close();
        }
        $columnCache[$tableSafe] = $cols;
        return $cols;
    }
}

if (!function_exists('hasCol')) {
    /**
     * Kiểm tra một tên cột có tồn tại trong danh sách cột hay không.
     */
    function hasCol(array $cols, string $name): bool {
        return in_array($name, $cols, true);
    }
}

if (!function_exists('tableExists')) {
    /**
     * Kiểm tra một bảng có tồn tại trong database hay không.
     */
    function tableExists(mysqli $ithanhloc, string $table): bool {
        static $cache = [];
        $table = trim($table);
        if ($table === '') return false;
        if (isset($cache[$table])) return $cache[$table];
        $safe = $ithanhloc->real_escape_string($table);
        $res = $ithanhloc->query("SHOW TABLES LIKE '{$safe}'");
        return $cache[$table] = ($res instanceof mysqli_result) && $res->num_rows > 0;
    }
}
if (!function_exists('guest_location_ip_key')) {
    /**
     * Sinh khoá định danh khách vãng lai theo IP (hash để tránh lưu IP thô).
     */
    function guest_location_ip_key(): string {
        $ip = function_exists('get_client_ip') ? get_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = trim($ip);
        if ($ip === '') return '';
        return hash('sha256', 'guestloc|' . $ip);
    }
}

if (!function_exists('getColumnType')) {
    /**
     * Lấy kiểu dữ liệu SQL của một cột.
     */
    function getColumnType(mysqli $db, string $table, string $column): string {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $colSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $res = @$db->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'");
        if (!$res || !$res->num_rows) return '';
        $row = $res->fetch_assoc();
        return (string)($row['Type'] ?? '');
    }
}

if (!function_exists('isNumericSqlType')) {
    /**
     * Kiểm tra kiểu SQL có phải dạng số hay không.
     */
    function isNumericSqlType(string $type): bool {
        $t = strtolower($type);
        return strpos($t, 'int') !== false
            || strpos($t, 'decimal') !== false
            || strpos($t, 'float') !== false
            || strpos($t, 'double') !== false;
    }
}

if (!function_exists('bindParamsDynamic')) {
    /**
     * Bind tham số động cho mysqli_stmt.
     */
    function bindParamsDynamic(mysqli_stmt $stmt, string $types, array $params): bool {
        if ($types === '') return true;
        $bind_names = [];
        foreach ($params as $key => $value) {
            $bind_names[] = &$params[$key];
        }
        array_unshift($bind_names, $types);
        return (bool)call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
}


if (!function_exists('toAscii')) {
    /**
     * Chuyển text có dấu sang ASCII (Slug/Filename friendly).
     */
    function toAscii(string $text): string {
        $text = trim((string)$text);
        if ($text === '') return '';
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $text = preg_replace('/[^A-Za-z0-9\s\-\_\.]/', '', $text);
        return trim($text);
    }
}

if (!function_exists('fetchDefaultSavedLocation')) {
    /**
     * Lấy địa chỉ giao hàng mặc định từ user_saved_locations.
     */
    function fetchDefaultSavedLocation(mysqli $ithanhloc, int $userId): array {
        if (function_exists('ecommerce_user_get_default_location')) {
            return ecommerce_user_get_default_location($ithanhloc, $userId);
        }
        if ($userId <= 0) return [];
        $stmt = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id=? AND is_default=1 ORDER BY updated_at DESC, id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $payload = json_decode((string)($row['payload_json'] ?? ''), true);
                if (!is_array($payload)) $payload = [];
                $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
                $payload['is_default'] = (int)($row['is_default'] ?? 0);
                return $payload;
            }
        }
        $stmt2 = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id=? ORDER BY updated_at DESC, id DESC LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($row) {
                $payload = json_decode((string)($row['payload_json'] ?? ''), true);
                if (!is_array($payload)) $payload = [];
                $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
                $payload['is_default'] = (int)($row['is_default'] ?? 0);
                return $payload;
            }
        }
        return [];
    }
}

if (!function_exists('getWalletBalance')) {
    /**
     * Lấy số dư ví (balance) của người dùng.
     */
    function getWalletBalance(mysqli $ithanhloc, int $userId, bool $forUpdate = false): int {
        $sql = 'SELECT balance FROM users WHERE id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return max(0, (int)($row['balance'] ?? 0));
    }
}

if (!function_exists('getXuConfig')) {
    /**
     * Lấy config liên quan đến xu (vnd_per_xu, max_use_percent, earn_percent).
     */
    function getXuConfig(): array {
        global $ithanhloc;
        $vnd_per_xu = (int)app_get_config_value_by_path('ECOMMERCE_XU.vnd_per_xu', 1000);
        if ($vnd_per_xu <= 0) $vnd_per_xu = 1000;
        $max_use_percent = (int)app_get_config_value_by_path('ECOMMERCE_XU.max_use_percent', 50);
        if ($max_use_percent <= 0 || $max_use_percent > 100) $max_use_percent = 50;
        $earn_percent = (float)app_get_config_value_by_path('ECOMMERCE_XU.earn_percent', 2);
        if ($earn_percent < 0) $earn_percent = 0;
        return [
            'vnd_per_xu' => $vnd_per_xu,
            'max_use_percent' => $max_use_percent,
            'earn_percent' => $earn_percent,
        ];
    }
}

if (!function_exists('calcMaxXuRedeem')) {
    /**
     * Tính toán số xu tối đa có thể dùng.
     */
    function calcMaxXuRedeem(int $userXu, float $subtotal, int $vndPerXu, int $maxPercent): int {
        if ($userXu <= 0 || $subtotal <= 0 || $vndPerXu <= 0) return 0;
        $subtotalXu = (int)floor($subtotal / $vndPerXu);
        $maxVnd = $subtotal * ($maxPercent / 100.0);
        $maxAllowedByPercent = (int)floor($maxVnd / $vndPerXu);
        return max(0, min($userXu, $subtotalXu, $maxAllowedByPercent));
    }
}

if (!function_exists('getDefaultUserBankCode')) {
    /**
     * Lấy mã ngân hàng mặc định của người dùng.
     */
    function getDefaultUserBankCode(mysqli $ithanhloc, int $userId): string {
        $stmt = $ithanhloc->prepare("SELECT bank_code FROM user_bank_accounts WHERE user_id=? AND type='bank' AND bank_code <> '' ORDER BY is_default DESC, id DESC LIMIT 1");
        if (!$stmt) return '';
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (string)($row['bank_code'] ?? '');
    }
}

if (!function_exists('decodeJsonArray')) {
    /**
     * Decode JSON từ input có thể là chuỗi hoặc đã là mảng, trả về mảng.
     */
    function decodeJsonArray($input): array {
        if (is_array($input)) return $input;
        if ($input === null || $input === '') return [];
        $decoded = json_decode((string)$input, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('sanitizeHex')) {
    /**
     * Chuẩn hóa mã màu hex.
     */
    function sanitizeHex(string $hex): string {
        $hex = trim($hex);
        if ($hex === '') return '';
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $hex = strtoupper($hex);
        if (!preg_match('/^[0-9A-F]{6}$/', $hex)) return '';
        return '#' . $hex;
    }
}

if (!function_exists('sanitizeColorOptions')) {
    /**
     * Làm sạch và chuẩn hoá danh sách màu sắc.
     */
    function sanitizeColorOptions($raw): array {
        $items = decodeJsonArray($raw);
        $res = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $hex = sanitizeHex((string)($item['hex'] ?? ''));
            if ($hex === '') continue;
            if (isset($seen[$hex])) continue;
            $seen[$hex] = true;
            $res[] = [
                'name' => trim((string)($item['name'] ?? '')),
                'code' => trim((string)($item['code'] ?? '')),
                'hex' => $hex,
            ];
        }
        return $res;
    }
}

if (!function_exists('sanitizeMediaGallery')) {
    /**
     * Làm sạch gallery media.
     */
    function sanitizeMediaGallery($raw): array {
        $items = decodeJsonArray($raw);
        $res = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '') continue;
            $type = strtolower(trim((string)($item['type'] ?? 'image')));
            if ($type !== 'video') $type = 'image';
            $res[] = [
                'type' => $type,
                'url' => $url,
                'caption' => trim((string)($item['caption'] ?? '')),
            ];
        }
        return $res;
    }
}

if (!function_exists('normalizeWeightUnit')) {
    /**
     * Chuẩn hóa đơn vị trọng lượng.
     */
    function normalizeWeightUnit(string $unit): string {
        $u = strtolower(trim($unit));
        if ($u === '') return 'kg';
        if (in_array($u, ['g', 'gr', 'gram', 'grams'], true)) return 'g';
        if (in_array($u, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) return 'kg';
        if (in_array($u, ['ml', 'milliliter', 'millilitre', 'cc'], true)) return 'ml';
        if (in_array($u, ['l', 'liter', 'litre'], true)) return 'l';
        if (in_array($u, ['oz', 'ounce', 'ounces'], true)) return 'oz';
        return $u; // Return raw if unknown but not empty
    }
}

if (!function_exists('cleanVariantWeightLegacy')) {
    /**
     * Xoá chuỗi trọng lượng/dung tích cũ khỏi tên biến thể để tránh trùng lặp.
     */
    function cleanVariantWeightLegacy(string $text, $weight, string $unit): string {
        $t = trim($text);
        if ($t === '' || $t === 'Mặc định') return $t;
        $v = (float)$weight;
        if ($v <= 0) return $t;

        $u = strtolower(trim($unit));
        $normalized = normalizeWeightUnit($unit);
        
        // Pattern 1: Giá trị (có thể kèm .000) + đơn vị gốc
        $patterns = [];
        if ($u) {
            $patterns[] = '/\b' . preg_quote($v, '/') . '(\.0+)?\s*' . preg_quote($u, '/') . '\b/i';
            $patterns[] = '/\b' . preg_quote($v, '/') . '(\.0+)?\s*' . preg_quote($u . 's', '/') . '\b/i';
        }
        // Pattern 2: Giá trị + đơn vị chuẩn hóa
        if ($normalized && $normalized !== $u) {
            $patterns[] = '/\b' . preg_quote($v, '/') . '(\.0+)?\s*' . preg_quote($normalized, '/') . '\b/i';
        }
        // Pattern 3: Một số đơn vị phổ biến khác
        $patterns[] = '/\b' . preg_quote($v, '/') . '(\.0+)?\s*(gram|gr|kg|ml|l|oz)\b/i';

        foreach ($patterns as $p) {
            $t = preg_replace($p, '', $t);
        }

        return trim(preg_replace('/\s+/', ' ', $t));
    }
}

if (!function_exists('convertWeightToGram')) {
    /**
     * Chuyển trọng lượng về gram.
     */
    function convertWeightToGram(float $val, string $unit): int {
        $u = normalizeWeightUnit($unit);
        if ($val <= 0) return 0;
        if ($u === 'kg' || $u === 'l') return (int)round($val * 1000);
        return (int)round($val);
    }
}

if (!function_exists('normalizeProductIds')) {
    /**
     * Chuẩn hoá danh sách product_id.
     */
    function normalizeProductIds($raw): array {
        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/u', $raw) ?: [];
        }
        if (!is_array($raw)) return [];
        $res = [];
        foreach ($raw as $v) {
            $id = (int)$v;
            if ($id > 0) $res[] = $id;
        }
        return array_values(array_unique($res));
    }
}

if (!function_exists('productIdsFromItems')) {
    /**
     * Lấy danh sách product_id từ danh sách item.
     */
    function productIdsFromItems(array $items): array {
        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            if (!empty($item['is_gift'])) continue;
            $pid = (int)($item['product_id'] ?? $item['id'] ?? 0);
            if ($pid > 0) $ids[] = $pid;
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('ghnStatusLabel')) {
    /**
     * Chuyển mã trạng thái GHN sang nhãn tiếng Việt dễ hiểu.
     */
    function ghnStatusLabel(?string $ghnStatus, ?string $fallbackText = ''): string {
        $rawStatus = strtolower(trim((string)$ghnStatus));
        $rawFallback = trim((string)$fallbackText);
        $token = $rawStatus !== '' ? $rawStatus : strtolower(str_replace([' ', '-'], '_', $rawFallback));
        $map = [
            'create' => 'Đơn mới tạo',
            'ready_to_pick' => 'Sẵn sàng lấy hàng',
            'money_collect_ready_to_pick' => 'Sẵn sàng lấy hàng',
            'picking' => 'Đang lấy hàng',
            'picked' => 'Đã lấy hàng',
            'storing' => 'Đang luân chuyển kho',
            'sorting' => 'Đang phân loại',
            'delivering' => 'Đang giao hàng',
            'transporting' => 'Đang vận chuyển',
            'money_collect_delivering' => 'Đang giao hàng',
            'delivery_fail' => 'Giao hàng chưa thành công',
            'delivered' => 'Đã giao hàng',
            'delivery_success' => 'Đã giao hàng',
            'return' => 'Đang hoàn hàng',
            'returning' => 'Đang hoàn hàng',
            'returned' => 'Đã hoàn hàng',
            'return_sorting' => 'Đang hoàn hàng',
            'return_transporting' => 'Đang hoàn hàng',
            'returning_to_sender' => 'Đang hoàn về người gửi',
            'cancel' => 'Đã hủy',
            'canceled' => 'Đã hủy',
            'cancelled' => 'Đã hủy',
            'exception' => 'Sự cố vận chuyển',
            'damage' => 'Hàng hóa hư hỏng',
            'lost' => 'Thất lạc hàng hóa',
        ];

        if ($token !== '' && isset($map[$token])) {
            return $map[$token];
        }
        if ($rawFallback !== '' && !preg_match('/^[A-Z0-9_\-\s]+$/', $rawFallback)) {
            return $rawFallback;
        }
        return 'Đang cập nhật vận chuyển';
    }
}

if (!function_exists('mapGhnStatusToOrderStatus')) {
    /**
     * Map trạng thái GHN sang trạng thái đơn hàng của hệ thống.
     */
    function mapGhnStatusToOrderStatus(?string $ghnStatus): string {
        $s = strtolower(trim((string)$ghnStatus));
        if ($s === '') return 'pending';

        $pending = ['ready_to_pick', 'money_collect_ready_to_pick', 'create'];
        $processing = ['picking', 'picked', 'storing', 'sorting'];
        $shipping = ['delivering', 'transporting', 'money_collect_delivering', 'delivery_fail'];
        $delivered = ['delivered', 'delivery_success'];
        $canceled = ['cancel', 'canceled', 'cancelled', 'exception', 'damage', 'lost'];
        $returned = ['return', 'returning', 'returned', 'return_sorting', 'return_transporting', 'returning_to_sender'];

        if (in_array($s, $pending, true)) return 'pending';
        if (in_array($s, $processing, true)) return 'processing';
        if (in_array($s, $shipping, true)) return 'shipping';
        if (in_array($s, $delivered, true)) return 'delivered';
        if (in_array($s, $canceled, true)) return 'canceled';
        if (in_array($s, $returned, true)) return 'returned';
        return 'processing';
    }
}

if (!function_exists('parseFlexibleTs')) {
    /**
     * Parse timestamp hoặc chuỗi ngày tháng linh hoạt về integer.
     */
    function parseFlexibleTs($value): int {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)))) {
            $num = (int)$value;
            if ($num > 1000000000000) {
                $num = (int)floor($num / 1000);
            }
            return $num > 0 ? $num : 0;
        }
        $txt = trim((string)$value);
        if ($txt === '') return 0;
        $ts = strtotime($txt);
        return $ts !== false ? (int)$ts : 0;
    }
}

if (!function_exists('pickFirstNonEmpty')) {
    /**
     * Lấy giá trị đầu tiên không rỗng từ danh sách ứng viên.
     */
    function pickFirstNonEmpty(array $candidates): string {
        foreach ($candidates as $value) {
            $txt = trim((string)$value);
            if ($txt !== '') return $txt;
        }
        return '';
    }
}

if (!function_exists('isOrderPaymentExpired')) {
    /**
     * Kiểm tra đơn hàng đã hết hạn thanh toán chưa.
     */
    function isOrderPaymentExpired(array $order): bool {
        $status = strtolower(trim((string)($order['status'] ?? 'pending')));
        $paymentMethod = strtolower(trim((string)($order['payment_method'] ?? '')));
        if ($status !== 'pending' || $paymentMethod === '' || $paymentMethod === 'cod') {
            return false;
        }

        $expiresAt = trim((string)($order['payment_expires_at'] ?? ''));
        if ($expiresAt !== '') {
            $ts = strtotime($expiresAt);
            return $ts !== false && $ts <= time();
        }

        $createdAt = trim((string)($order['created_at'] ?? ''));
        if ($createdAt === '') return false;
        $createdTs = strtotime($createdAt);
        if ($createdTs === false) return false;
        return (time() - $createdTs) >= 24 * 3600;
    }
}

if (!function_exists('restoreStockForOrder')) {
    /**
     * Hoàn trả số lượng đã trừ về kho khi đơn hàng được huỷ / trả / hoàn tiền.
     * - Idempotent: chỉ hoàn 1 lần mỗi đơn (kiểm tra qua ecommerce_order_log với event 'stock_restored').
     * - Chỉ hoàn cho item có variant_id > 0 và không phải quà tặng (đúng với logic trừ kho lúc đặt).
     * - Bỏ qua nếu order không có sản phẩm hợp lệ.
     *
     * @param mysqli      $ithanhloc
     * @param array       $order Bản ghi ecommerce_order (cần ít nhất order_id, products_json).
     * @param string      $actorType 'admin' | 'system' | 'customer'
     * @param int         $actorId
     * @return bool true nếu thực hiện hoàn kho, false nếu đã hoàn trước đó hoặc không có gì để hoàn.
     */
    function restoreStockForOrder(mysqli $ithanhloc, array $order, string $actorType = 'system', int $actorId = 0): bool {
        $orderId = trim((string)($order['order_id'] ?? ''));
        if ($orderId === '') return false;

        // Đảm bảo bảng log tồn tại để check idempotent.
        if (function_exists('ecommerce_order_log_ensure_table')) {
            ecommerce_order_log_ensure_table($ithanhloc);
        }

        // Idempotent guard: nếu đã có log stock_restored cho order này thì bỏ qua.
        $stmt = $ithanhloc->prepare("SELECT id FROM ecommerce_order_log WHERE order_id=? AND event='stock_restored' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $orderId);
            $stmt->execute();
            $existed = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existed) return false;
        }

        // Parse products_json
        $raw = (string)($order['products_json'] ?? '');
        $items = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($items) || !$items) return false;

        // Gom theo variant_id để tránh nhiều UPDATE cho cùng 1 biến thể
        $aggregate = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (!empty($it['is_gift'])) continue;
            $variantId = (int)($it['variant_id'] ?? $it['vid'] ?? 0);
            $productId = (int)($it['product_id'] ?? $it['id'] ?? $it['pid'] ?? 0);
            $qty = (int)($it['qty'] ?? $it['quantity'] ?? 0);
            if ($variantId <= 0 || $productId <= 0 || $qty <= 0) continue;
            $key = $productId . ':' . $variantId;
            if (!isset($aggregate[$key])) {
                $aggregate[$key] = ['pid' => $productId, 'vid' => $variantId, 'qty' => 0];
            }
            $aggregate[$key]['qty'] += $qty;
        }
        if (!$aggregate) return false;

        $totalQty = 0;
        $variantCount = 0;
        $stmtUp = $ithanhloc->prepare('UPDATE ecommerce_product_variants SET stock_quantity = COALESCE(stock_quantity, 0) + ? WHERE id=? AND product_id=?');
        if (!$stmtUp) return false;
        foreach ($aggregate as $op) {
            $stmtUp->bind_param('iii', $op['qty'], $op['vid'], $op['pid']);
            $stmtUp->execute();
            if ($stmtUp->affected_rows > 0) {
                $totalQty += (int)$op['qty'];
                $variantCount++;
            }
        }
        $stmtUp->close();

        if ($variantCount === 0) return false;

        // Ghi log thân thiện với người xem (không phơi pid/vid kỹ thuật)
        $note = sprintf('Đã hoàn %d sản phẩm (%d biến thể) về kho.', $totalQty, $variantCount);
        $currentStatus = (string)($order['status'] ?? '');
        if (function_exists('ecommerce_order_log_insert')) {
            $actorType = in_array($actorType, ['admin','customer','system','carrier'], true) ? $actorType : 'system';
            ecommerce_order_log_insert(
                $ithanhloc, $orderId, $actorType, $actorId,
                'stock_restored', $currentStatus, $currentStatus, $note
            );
        }
        return true;
    }
}

if (!function_exists('restoreVoucherUsageForOrder')) {
    /**
     * Hoàn lại lượt sử dụng voucher (used_count -= 1) khi đơn bị huỷ/trả.
     * Idempotent qua log 'voucher_usage_restored'.
     */
    function restoreVoucherUsageForOrder(mysqli $ithanhloc, array $order, string $actorType = 'system', int $actorId = 0): bool {
        $orderId = trim((string)($order['order_id'] ?? ''));
        if ($orderId === '') return false;

        if (function_exists('ecommerce_order_log_ensure_table')) {
            ecommerce_order_log_ensure_table($ithanhloc);
        }

        $stmt = $ithanhloc->prepare("SELECT id FROM ecommerce_order_log WHERE order_id=? AND event='voucher_usage_restored' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $orderId);
            $stmt->execute();
            $existed = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existed) return false;
        }

        $codes = array_values(array_unique(array_filter([
            strtoupper(trim((string)($order['voucher_code'] ?? ''))),
            strtoupper(trim((string)($order['voucher_shipping_code'] ?? ''))),
            strtoupper(trim((string)($order['voucher_payment_code'] ?? ''))),
        ])));
        if (!$codes) return false;

        $restored = [];
        $stmtUp = $ithanhloc->prepare("UPDATE ecommerce_voucher
            SET used_count = GREATEST(0, CAST(COALESCE(used_count, '0') AS UNSIGNED) - 1)
            WHERE UPPER(code)=? AND CAST(COALESCE(used_count, '0') AS UNSIGNED) > 0");
        if (!$stmtUp) return false;
        foreach ($codes as $c) {
            $stmtUp->bind_param('s', $c);
            $stmtUp->execute();
            if ($stmtUp->affected_rows > 0) $restored[] = $c;
        }
        $stmtUp->close();

        if (!$restored) return false;

        if (function_exists('ecommerce_order_log_insert')) {
            $actorType = in_array($actorType, ['admin','customer','system','carrier'], true) ? $actorType : 'system';
            $note = 'Đã hoàn lượt sử dụng voucher: ' . implode(', ', $restored);
            $currentStatus = (string)($order['status'] ?? '');
            ecommerce_order_log_insert(
                $ithanhloc, $orderId, $actorType, $actorId,
                'voucher_usage_restored', $currentStatus, $currentStatus, $note
            );
        }
        return true;
    }
}

if (!function_exists('markRefundPendingForOrder')) {
    /**
     * Đánh dấu đơn cần refund thủ công khi huỷ/trả đơn đã thanh toán online.
     * KHÔNG tự động gọi gateway refund — chỉ set payment_status='refund_pending'
     * + log để admin xử lý refund qua MoMo/VNPay/ZaloPay portal.
     * Idempotent qua log 'refund_pending_marked'.
     */
    function markRefundPendingForOrder(mysqli $ithanhloc, array $order, string $actorType = 'system', int $actorId = 0): bool {
        $orderId = trim((string)($order['order_id'] ?? ''));
        if ($orderId === '') return false;

        $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
        if ($paymentStatus !== 'paid') return false;

        $gateway = strtolower(trim((string)($order['payment_gateway'] ?? $order['payment_method'] ?? '')));
        $onlineGateways = ['momo', 'vnpay', 'zalopay'];
        if (!in_array($gateway, $onlineGateways, true)) return false;

        if (function_exists('ecommerce_order_log_ensure_table')) {
            ecommerce_order_log_ensure_table($ithanhloc);
        }

        $stmt = $ithanhloc->prepare("SELECT id FROM ecommerce_order_log WHERE order_id=? AND event='refund_pending_marked' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $orderId);
            $stmt->execute();
            $existed = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existed) return false;
        }

        $cols = listColumns($ithanhloc, 'ecommerce_order');
        if (in_array('payment_status', $cols, true)) {
            $stmtU = $ithanhloc->prepare("UPDATE ecommerce_order SET payment_status='refund_pending', updated_at=NOW() WHERE order_id=? LIMIT 1");
            if ($stmtU) { $stmtU->bind_param('s', $orderId); $stmtU->execute(); $stmtU->close(); }
        }

        $amount = (float)($order['total_amount'] ?? 0);
        $note = sprintf('Cần hoàn tiền %s qua %s (số tiền: %s). Admin xử lý refund thủ công trên cổng thanh toán.',
            $orderId, strtoupper($gateway), number_format($amount, 0, ',', '.') . ' đ');
        if (function_exists('ecommerce_order_log_insert')) {
            $actorType = in_array($actorType, ['admin','customer','system','carrier'], true) ? $actorType : 'system';
            $currentStatus = (string)($order['status'] ?? '');
            ecommerce_order_log_insert(
                $ithanhloc, $orderId, $actorType, $actorId,
                'refund_pending_marked', $currentStatus, $currentStatus, $note
            );
        }
        return true;
    }
}

if (!function_exists('markOrderExpiredCancel')) {
    /**
     * Đánh dấu huỷ đơn hàng do hết hạn thanh toán.
     */
    function markOrderExpiredCancel(mysqli $ithanhloc, array $order): void {
        $orderId = trim((string)($order['order_id'] ?? ''));
        if ($orderId === '') return;

        $cols = listColumns($ithanhloc, 'ecommerce_order');
        $set = [];
        $vals = [];
        $types = '';

        $set[] = 'status=?'; $vals[] = 'canceled'; $types .= 's';
        if (in_array('payment_status', $cols, true)) { $set[] = 'payment_status=?'; $vals[] = 'expired'; $types .= 's'; }
        if (in_array('payment_response_code', $cols, true)) { $set[] = 'payment_response_code=?'; $vals[] = 'EXPIRED'; $types .= 's'; }
        if (in_array('canceled_at', $cols, true)) { $set[] = 'canceled_at=COALESCE(canceled_at, NOW())'; }
        if (in_array('updated_at', $cols, true)) { $set[] = 'updated_at=NOW()'; }
        if (!$set) return;

        $sql = 'UPDATE ecommerce_order SET ' . implode(', ', $set) . ' WHERE order_id=? LIMIT 1';
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return;
        $vals[] = $orderId;
        $types .= 's';
        if (!bindParamsDynamic($stmt, $types, $vals)) {
            $stmt->close();
            return;
        }
        $stmt->execute();
        $stmt->close();
        syncXuByOrderStatus($ithanhloc, $order, 'canceled');
        // Hoàn kho cho đơn huỷ do hết hạn thanh toán
        if (function_exists('restoreStockForOrder')) {
            try { restoreStockForOrder($ithanhloc, $order, 'system', 0); }
            catch (Throwable $e) { error_log('restoreStockForOrder (expired) failed: ' . $e->getMessage()); }
        }
    }
}

if (!function_exists('syncXuByOrderStatus')) {
    /**
     * Đồng bộ Xu (điểm thưởng) dựa trên trạng thái đơn hàng.
     */
    function syncXuByOrderStatus(mysqli $ithanhloc, $orderOrId, string $newStatus): void {
        $order = is_array($orderOrId) ? $orderOrId : [];
        $orderId = is_array($orderOrId) ? (string)($orderOrId['order_id'] ?? '') : (string)$orderOrId;
        if ($orderId === '') return;

        if (!$order) {
            $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE order_id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $orderId);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
        if (!$order) return;

        $userId = (int)($order['user_id'] ?? 0);
        if ($userId < 0) return;

        $ithanhloc->begin_transaction();
        try {
            $xuUsed = max(0, (int)($order['xu_used'] ?? 0));
            if (in_array($newStatus, ['canceled', 'returned'], true) && $xuUsed > 0) {
                $txType = 'order_refund';
                $txNote = 'Hoàn xu cho đơn ' . $orderId;
                $stmtTx = $ithanhloc->prepare('INSERT IGNORE INTO user_balance_log (user_id, ref_order_id, type, amount, note) VALUES (?,?,?,?,?)');
                if ($stmtTx) {
                    $stmtTx->bind_param('issis', $userId, $orderId, $txType, $xuUsed, $txNote);
                    $stmtTx->execute();
                    $inserted = $stmtTx->affected_rows > 0;
                    $stmtTx->close();
                    if ($inserted) {
                        $ithanhloc->query("UPDATE users SET balance = COALESCE(balance,0) + $xuUsed WHERE id=$userId");
                        $ithanhloc->query("UPDATE ecommerce_order SET xu_refunded = COALESCE(xu_refunded,0) + $xuUsed WHERE order_id='{$ithanhloc->real_escape_string($orderId)}'");
                    }
                }
            }

            if ($newStatus === 'delivered') {
                // Không thưởng xu earn cho đơn đã từng được hoàn xu (đã hủy/trả trước đó).
                // Tránh trường hợp admin set canceled -> delivered khiến đơn vừa được hoàn vừa được thưởng.
                $alreadyRefunded = false;
                $stmtChk = $ithanhloc->prepare("SELECT 1 FROM user_balance_log WHERE ref_order_id=? AND type='order_refund' LIMIT 1");
                if ($stmtChk) {
                    $stmtChk->bind_param('s', $orderId);
                    $stmtChk->execute();
                    $alreadyRefunded = (bool)$stmtChk->get_result()->fetch_assoc();
                    $stmtChk->close();
                }
                $cfg = getXuConfig();
                $vndPerXu = (int)$cfg['vnd_per_xu'];
                $earnPercent = (float)$cfg['earn_percent'];
                $baseVnd = max(0.0, (float)($order['total_amount'] ?? 0) - (float)($order['shipping_fee'] ?? 0));
                $earnXu = (!$alreadyRefunded && $vndPerXu > 0 && $earnPercent > 0) ? (int)floor(($baseVnd * $earnPercent / 100.0) / $vndPerXu) : 0;
                if ($earnXu > 0) {
                    $txType = 'order_earn';
                    $txNote = 'Hoàn xu cho đơn hoàn tất ' . $orderId;
                    $stmtTx = $ithanhloc->prepare('INSERT IGNORE INTO user_balance_log (user_id, ref_order_id, type, amount, note) VALUES (?,?,?,?,?)');
                    if ($stmtTx) {
                        $stmtTx->bind_param('issis', $userId, $orderId, $txType, $earnXu, $txNote);
                        $stmtTx->execute();
                        $inserted = $stmtTx->affected_rows > 0;
                        $stmtTx->close();
                        if ($inserted) {
                            $ithanhloc->query("UPDATE users SET balance = COALESCE(balance,0) + $earnXu WHERE id=$userId");
                            $ithanhloc->query("UPDATE ecommerce_order SET xu_earned = $earnXu WHERE order_id='{$ithanhloc->real_escape_string($orderId)}'");
                        }
                    }
                }
            }
            $ithanhloc->commit();
        } catch (Throwable $e) {
            $ithanhloc->rollback();
        }
    }
}

if (!function_exists('extractSearchTerm')) {
    /**
     * Trích xuất từ khoá tìm kiếm linh hoạt (hỗ trợ DataTables hoặc string).
     */
    function extractSearchTerm($raw): string {
        if (is_array($raw)) {
            return trim((string)($raw['value'] ?? ''));
        }
        return trim((string)$raw);
    }
}

if (!function_exists('canCustomerCancel')) {
    /**
     * Khách được gửi yêu cầu hủy khi đơn ở pending hoặc processing (trước khi bàn giao vận chuyển).
     * cancel_requested: đã gửi yêu cầu rồi, không gửi lại.
     */
    function canCustomerCancel(string $status): bool {
        if (strtolower(trim($status)) === 'completed') return false; // đơn đã hoàn thành: khóa hủy
        return in_array($status, ['pending', 'processing'], true);
    }
}

if (!function_exists('canCustomerConfirm')) {
    /**
     * Kiểm tra khách hàng có thể xác nhận nhận hàng không.
     * Cho phép cả 'delivered' vì GHN webhook có thể cập nhật trước khi khách xác nhận.
     */
    function canCustomerConfirm(string $status): bool {
        if (strtolower(trim($status)) === 'completed') return false; // đơn đã hoàn thành: không cần xác nhận lại
        return in_array($status, ['shipping', 'delivered'], true);
    }
}

if (!function_exists('canCustomerReturn')) {
    /**
     * Kiểm tra khách hàng có thể yêu cầu trả hàng không.
     */
    function canCustomerReturn(string $status): bool {
        if (strtolower(trim($status)) === 'completed') return false; // đơn đã hoàn thành: khóa trả hàng
        return $status === 'delivered';
    }
}

if (!function_exists('canCustomerEditAddress')) {
    /**
     * Khách được đổi địa chỉ khi đơn ở pending hoặc processing (trước khi bàn giao vận chuyển).
     */
    function canCustomerEditAddress(string $status): bool {
        return in_array($status, ['pending', 'processing'], true);
    }
}

if (!function_exists('ecommerce_cart_get')) {
    /**
     * Lấy giỏ hàng hiện tại từ session.
     */
    function ecommerce_cart_get(): array {
        $cart = $_SESSION['shop_cart'] ?? [];
        return is_array($cart) ? $cart : [];
    }
}

if (!function_exists('ecommerce_cart_set')) {
    /**
     * Cập nhật / lưu lại giỏ hàng vào session.
     */
    function ecommerce_cart_set(array $cart): void {
        $_SESSION['shop_cart'] = array_values($cart);
    }
}

if (!function_exists('ecommerce_cart_count')) {
    /**
     * Đếm tổng số lượng sản phẩm thực tế trong giỏ (không tính quà tặng).
     */
    function ecommerce_cart_count(): int {
        $cart = ecommerce_cart_get();
        $count = 0;
        foreach ($cart as $it) {
            $isGift = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0);
            if ($isGift) continue;
            $count += (int)($it['qty'] ?? 0);
        }
        return $count;
    }
}

if (!function_exists('ecommerce_build_cart_item')) {
    /**
     * Xây dựng dữ liệu chi tiết cho 1 item giỏ hàng từ product + variant.
     */
    /**
     * Kiểm tra một sản phẩm có bật "Hàng đặt trước" hay không (có cache theo request).
     */
    function ecommerce_product_is_preorder(mysqli $ithanhloc, int $pid): bool {
        static $cache = [];
        $pid = (int)$pid;
        if ($pid <= 0) return false;
        if (array_key_exists($pid, $cache)) return $cache[$pid];

        $pCols = listColumns($ithanhloc, 'ecommerce_product');
        if (!$pCols || !hasCol($pCols, 'preorder_enabled')) {
            return $cache[$pid] = false;
        }
        $stmt = $ithanhloc->prepare('SELECT preorder_enabled FROM ecommerce_product WHERE id=? LIMIT 1');
        if (!$stmt) return $cache[$pid] = false;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $cache[$pid] = (isset($row['preorder_enabled']) && (int)$row['preorder_enabled'] === 1);
    }

    function ecommerce_build_cart_item(mysqli $ithanhloc, int $pid, int $variantId, int $qty, string $colorCode = ''): ?array {
        $pid = max(1, $pid);
        $variantId = max(0, $variantId);
        $qty = max(1, $qty);
        $colorCode = trim($colorCode);

        // 1. Lấy thông tin sản phẩm chính
        $pCols = listColumns($ithanhloc, 'ecommerce_product');
        if (!$pCols) return null;

        $select = "id, product_name, image_url, payment_options";
        if (hasCol($pCols, 'vat')) $select .= ", vat";
        if (hasCol($pCols, 'vat_enabled')) $select .= ", vat_enabled";
        if (hasCol($pCols, 'preorder_enabled')) $select .= ", preorder_enabled";

        $stmt = $ithanhloc->prepare("SELECT $select FROM ecommerce_product WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) return null;

        $allowedPayments = ecommerce_sanitize_payment_keys($p['payment_options'] ?? null);
        if (!$allowedPayments) {
            $allowedPayments = ecommerce_all_enabled_payment_keys();
        }

        // Hàng đặt trước: bỏ qua chặn/giới hạn tồn kho khi bật
        $isPreorder = isset($p['preorder_enabled']) && (int)$p['preorder_enabled'] === 1;

        // 2. Mặc định
        $price = 0.0;
        $variantLabel = 'Mặc định';
        $variantSku = '';
        $stock = null;
        $variantImage = '';
        $groupId = 0;

        $shipWeightValue = 0.0;
        $shipWeightUnit = 'kg';
        $shipLengthCm = 0;
        $shipWidthCm = 0;
        $shipHeightCm = 0;

        // 3. Lấy thông tin biến thể (nếu có)
        if ($variantId > 0) {
            $vTable = 'ecommerce_product_variants';
            $vCols = listColumns($ithanhloc, $vTable);
            if ($vCols) {
                $vSelect = "id, group_id, variant_name, sku_variant, price, stock_quantity";
                if (hasCol($vCols, 'image_url')) $vSelect .= ", image_url";
                if (hasCol($vCols, 'shipping_weight_value')) $vSelect .= ", shipping_weight_value";
                if (hasCol($vCols, 'shipping_weight_unit')) $vSelect .= ", shipping_weight_unit";
                if (hasCol($vCols, 'shipping_length_cm')) $vSelect .= ", shipping_length_cm";
                if (hasCol($vCols, 'shipping_width_cm')) $vSelect .= ", shipping_width_cm";
                if (hasCol($vCols, 'shipping_height_cm')) $vSelect .= ", shipping_height_cm";

                $whereExtra = '';
                if (hasCol($vCols, 'status')) $whereExtra = " AND (status = 1 OR status = '1' OR LOWER(status) = 'true')";
                elseif (hasCol($vCols, 'is_active')) $whereExtra = " AND is_active = 1";

                $stmtV = $ithanhloc->prepare("SELECT $vSelect FROM `$vTable` WHERE id = ? AND product_id = ?$whereExtra LIMIT 1");
                if ($stmtV) {
                    $stmtV->bind_param('ii', $variantId, $pid);
                    $stmtV->execute();
                    $v = $stmtV->get_result()->fetch_assoc();
                    $stmtV->close();
                    if ($v) {
                        $variantLabel = trim($v['variant_name'] ?? 'Mặc định') ?: 'Mặc định';
                        if ($variantLabel !== 'Mặc định') {
                            $wV = (float)($v['shipping_weight_value'] ?? 0);
                            $wU = (string)($v['shipping_weight_unit'] ?? '');
                            $variantLabel = cleanVariantWeightLegacy($variantLabel, $wV, $wU);
                            if ($variantLabel === '') $variantLabel = 'Mặc định';
                        }
                        $variantSku = (string)($v['sku_variant'] ?? '');
                        $price = (float)($v['price'] ?? 0);
                        $stock = isset($v['stock_quantity']) ? (int)$v['stock_quantity'] : null;
                        $groupId = (int)($v['group_id'] ?? 0);
                        if (!empty($v['image_url'])) $variantImage = (string)$v['image_url'];

                        if (array_key_exists('shipping_weight_value', $v)) $shipWeightValue = (float)$v['shipping_weight_value'];
                        if (array_key_exists('shipping_weight_unit', $v)) $shipWeightUnit = normalizeWeightUnit((string)$v['shipping_weight_unit']);
                        if (array_key_exists('shipping_length_cm', $v)) $shipLengthCm = (int)$v['shipping_length_cm'];
                        if (array_key_exists('shipping_width_cm', $v)) $shipWidthCm = (int)$v['shipping_width_cm'];
                        if (array_key_exists('shipping_height_cm', $v)) $shipHeightCm = (int)$v['shipping_height_cm'];
                    }
                }
            }
        }

        // Fallback price
        if ($price <= 0) {
            $stmtMin = $ithanhloc->prepare('SELECT MIN(price) AS min_price FROM ecommerce_product_variants WHERE product_id = ?');
            if ($stmtMin) {
                $stmtMin->bind_param('i', $pid);
                $stmtMin->execute();
                $minRow = $stmtMin->get_result()->fetch_assoc();
                $stmtMin->close();
                if ($minRow && $minRow['min_price'] !== null) $price = (float)$minRow['min_price'];
            }
        }

        // 4. Tính toán VAT
        $vatEnabled = isset($p['vat_enabled']) ? (int)$p['vat_enabled'] : 1;
        $defaultVat = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
        $vatPercent = isset($p['vat']) ? (float)$p['vat'] : (float)$defaultVat;
        $vatPercent = max(0, min(100, $vatPercent));

        $priceBase = (float)round($price);
        $vatFactor = ($vatEnabled === 1) ? (1.0 + ($vatPercent / 100.0)) : 1.0;
        $priceFinal = (float)round($priceBase * $vatFactor);

        // 5. Logic KHOÁ CHẶT (Tight Logic Enforcement)
        $finalThumb = $variantImage !== '' ? $variantImage : (string)($p['image_url'] ?? '');
        
        // - Phải có giá > 0
        if ($priceFinal <= 0) return null;
        
        // - Phải có ảnh (ảnh biến thể hoặc ảnh sản phẩm gốc)
        if (trim($finalThumb) === '') return null;

        // - Phải còn hàng (stock check) — BỎ QUA nếu là hàng đặt trước
        if ($stock !== null && !$isPreorder) {
            $qty = max(0, min($qty, $stock)); // Giới hạn theo tồn kho, có thể về 0
        } elseif ($isPreorder) {
            $qty = max(1, $qty); // Đặt trước: luôn cho mua, không giới hạn theo kho
        }

        $key = $pid . ':' . $variantId . ':' . strtoupper($colorCode);
        $displayVariant = $variantLabel;
        if ($colorCode !== '') $displayVariant = trim($variantLabel . ' | Màu: ' . $colorCode);

        $productName = trim((string)($p['product_name'] ?? ''));
        if ($productName === '') $productName = 'Sản phẩm #' . $pid;

        return [
            'key' => $key,
            'product_id' => $pid,
            'variant_id' => $variantId,
            'group_id' => $groupId,
            'name' => $productName,
            'product_name' => $productName,
            'sku' => $variantSku,
            'thumb' => $variantImage !== '' ? $variantImage : (string)($p['image_url'] ?? ''),
            'variant' => $displayVariant,
            'variant_sku' => $variantSku,
            'color_code' => $colorCode,
            'price' => $priceFinal,
            'price_base' => $priceBase,
            'vat_percent' => $vatPercent,
            'vat_enabled' => $vatEnabled,
            'price_includes_vat' => ($vatEnabled === 1) ? 1 : 0,
            'qty' => $qty,
            'stock_quantity' => $stock,
            'is_preorder' => $isPreorder,
            'payment_options' => $allowedPayments,
            'weight_value' => $shipWeightValue,
            'weight_unit' => $shipWeightUnit,
            'length_cm' => $shipLengthCm,
            'width_cm' => $shipWidthCm,
            'height_cm' => $shipHeightCm,
        ];
    }
}

if (!function_exists('ecommerce_get_enabled_payment_methods')) {
    /**
     * Lấy danh sách các phương thức thanh toán đang bật.
     */
    function ecommerce_get_enabled_payment_methods(): array {
        global $ECOMMERCE_PAYMENT_METHODS;
        $methods = is_array($ECOMMERCE_PAYMENT_METHODS ?? null) ? $ECOMMERCE_PAYMENT_METHODS : [];
        $enabled = [];
        foreach ($methods as $key => $cfg) {
            if (!is_array($cfg)) continue;
            if (!empty($cfg['enabled'])) {
                $label = (string)($cfg['label'] ?? $key);
                if ((string)$key === 'cod') $label = 'Thanh toán khi nhận hàng';
                if ((string)$key === 'vnpay') $label = 'Ví VN PAY';
                if ((string)$key === 'zalopay') $label = 'Ví ZaloPay';
                if ((string)$key === 'momo') $label = 'Ví MoMo';
                $enabled[$key] = [
                    'key' => (string)$key,
                    'label' => $label,
                ];
            }
        }
        if (!isset($enabled['cod'])) {
            $enabled['cod'] = ['key' => 'cod', 'label' => 'COD - Nhận hàng trả tiền'];
        }
        return array_values($enabled);
    }
}

if (!function_exists('ecommerce_payment_label_map')) {
    /**
     * Trả về map key -> label của các phương thức thanh toán.
     */
    function ecommerce_payment_label_map(): array {
        static $map = null;
        if ($map !== null) return $map;
        $map = [];
        foreach (ecommerce_get_enabled_payment_methods() as $method) {
            $map[$method['key']] = $method['label'];
        }
        return $map;
    }
}

if (!function_exists('ecommerce_all_enabled_payment_keys')) {
    /**
     * Lấy danh sách các key thanh toán hợp lệ.
     */
    function ecommerce_all_enabled_payment_keys(): array {
        return array_keys(ecommerce_payment_label_map());
    }
}

if (!function_exists('ecommerce_sanitize_payment_keys')) {
    /**
     * Chuẩn hoá và lọc danh sách key thanh toán.
     */
    function ecommerce_sanitize_payment_keys($raw): array {
        // Hỗ trợ cả JSON (mảng/object) lẫn chuỗi CSV cho dữ liệu cũ (vd "cod,vnpay").
        // Lưu ý: KHÔNG dựa vào decodeJsonArray() toàn cục vì bản đó không fallback CSV.
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $s = trim((string)$raw);
            if ($s === '') {
                $list = [];
            } elseif ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
                $decoded = json_decode($s, true);
                $list = is_array($decoded) ? $decoded : [];
            } else {
                $list = array_filter(array_map('trim', explode(',', $s)));
            }
        }
        $keys = [];
        $aliasMap = [
            'bank' => ['vnpay', 'momo'],
            'transfer' => ['vnpay', 'momo'],
            'chuyenkhoan' => ['vnpay', 'momo'],
            'ewallet' => ['momo'],
            'wallet' => ['momo'],
            'momoqr' => ['momo'],
            'qr' => ['momo'],
        ];
        foreach ($list as $item) {
            $val = is_array($item) ? ($item['key'] ?? $item['value'] ?? '') : $item;
            $key = trim((string)$val);
            if ($key === '') continue;
            $key = preg_replace('/[^a-z0-9_\-]/i', '', $key);
            if ($key === '') continue;
            $normalized = strtolower($key);
            $expanded = $aliasMap[$normalized] ?? [$normalized];
            foreach ($expanded as $candidate) {
                $candidateNorm = strtolower(trim((string)$candidate));
                if ($candidateNorm === '') continue;
                if (!in_array($candidateNorm, $keys, true)) $keys[] = $candidateNorm;
            }
        }
        if (!$keys) return [];
        $allowed = ecommerce_payment_label_map();
        $filtered = [];
        foreach ($allowed as $allowedKey => $label) {
            $needle = strtolower($allowedKey);
            foreach ($keys as $inputKey) {
                if ($needle === $inputKey) {
                    $filtered[] = $allowedKey;
                    break;
                }
            }
        }
        return $filtered;
    }
}

if (!function_exists('ecommerce_cart_allowed_payment_keys')) {
    /**
     * Lấy giao điểm các phương thức thanh toán được phép cho cả giỏ hàng.
     */
    function ecommerce_cart_allowed_payment_keys(array $cart): array {
        $intersection = null;
        foreach ($cart as $item) {
            $options = $item['payment_options'] ?? null;
            if (!is_array($options) || !$options) continue;
            $options = array_values(array_filter(array_map('strval', $options)));
            if (!$options) continue;
            if ($intersection === null) $intersection = $options;
            else $intersection = array_values(array_intersect($intersection, $options));
        }
        return $intersection ?? ecommerce_all_enabled_payment_keys();
    }
}

if (!function_exists('ecommerce_hydrate_cart_payment_options')) {
    /**
     * Bổ sung thông tin payment, VAT cho các item trong giỏ.
     */
    function ecommerce_hydrate_cart_payment_options(mysqli $ithanhloc, array $cart): array {
        $needsMeta = [];
        foreach ($cart as $idx => $item) {
            $productId = (int)($item['product_id'] ?? 0);
            if ($productId <= 0) continue;
            $needsMeta[$idx] = $productId;
        }
        if (!$needsMeta) return $cart;
        $ids = array_values(array_unique(array_filter($needsMeta)));
        if (!$ids) return $cart;

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $map = [];

        $stmt = $ithanhloc->prepare("SELECT id, product_name, image_url, payment_options, vat, vat_enabled FROM ecommerce_product WHERE id IN ($ph)");
        if ($stmt) {
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $pid = (int)$row['id'];
                $keys = ecommerce_sanitize_payment_keys($row['payment_options'] ?? null);
                if (!$keys) $keys = ecommerce_all_enabled_payment_keys();
                $defaultVat = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
                $vatPercent = isset($row['vat']) ? (float)$row['vat'] : (float)$defaultVat;
                $vatEnabled = isset($row['vat_enabled']) ? (int)$row['vat_enabled'] : 1;
                $map[$pid] = [
                    'payment_options' => $keys,
                    'name' => trim((string)($row['product_name'] ?? '')),
                    'thumb' => trim((string)($row['image_url'] ?? '')),
                    'vat_percent' => $vatPercent,
                    'vat_enabled' => $vatEnabled,
                ];
            }
            $stmt->close();
        }

        foreach ($needsMeta as $idx => $pid) {
            $meta = $map[$pid] ?? null;
            if (!$meta) continue;

            if (!isset($cart[$idx]['payment_options']) || !$cart[$idx]['payment_options']) {
                $cart[$idx]['payment_options'] = $meta['payment_options'];
            }

            if (!isset($cart[$idx]['vat_percent'])) $cart[$idx]['vat_percent'] = (float)$meta['vat_percent'];
            if (!isset($cart[$idx]['vat_enabled'])) $cart[$idx]['vat_enabled'] = (int)$meta['vat_enabled'];
            if (!isset($cart[$idx]['price_includes_vat'])) $cart[$idx]['price_includes_vat'] = ($meta['vat_enabled'] === 1 ? 1 : 0);

            if (!isset($cart[$idx]['price_base'])) {
                $price = (float)($cart[$idx]['price'] ?? 0);
                $vatPct = (float)$cart[$idx]['vat_percent'];
                $includesVat = ((int)$cart[$idx]['price_includes_vat'] === 1);
                if ($price > 0 && $includesVat && $vatPct > 0) {
                    $cart[$idx]['price_base'] = (float)round($price / (1.0 + ($vatPct / 100.0)));
                } else {
                    $cart[$idx]['price_base'] = (float)round($price);
                }
            }

            $name = $meta['name'] !== '' ? $meta['name'] : ($cart[$idx]['name'] ?? $cart[$idx]['product_name'] ?? ('Sản phẩm #' . $pid));
            $cart[$idx]['name'] = $name;
            $cart[$idx]['product_name'] = $name;
            if (empty($cart[$idx]['thumb']) && $meta['thumb'] !== '') $cart[$idx]['thumb'] = $meta['thumb'];
        }
        return $cart;
    }
}

if (!function_exists('ecommerce_normalize_discount_target')) {
    /**
     * Chuẩn hoá mục tiêu giảm giá (order/shipping).
     */
    function ecommerce_normalize_discount_target($target): string {
        $key = strtolower(trim((string)$target));
        return $key === 'shipping' ? 'shipping' : 'order';
    }
}

if (!function_exists('ecommerce_normalize_csv_input')) {
    /**
     * Chuẩn hoá chuỗi nhập vào dạng CSV (tách bởi dấu phẩy, khoảng trắng, v.v.).
     */
    function ecommerce_normalize_csv_input($raw): string {
        $items = is_array($raw) ? $raw : preg_split('/[\s,;|]+/', (string)$raw);
        $list = [];
        foreach ($items as $item) {
            $val = strtolower(trim((string)$item));
            if ($val === '' || in_array($val, $list, true)) continue;
            $list[] = $val;
        }
        return implode(',', $list);
    }
}

if (!function_exists('ecommerce_voucher_format_label')) {
    /**
     * Định dạng nhãn giá trị voucher (VD: Giảm 10% (tối đa 50k)).
     */
    function ecommerce_voucher_format_label($type, $val, $max = null): string {
        $hasMax = ($max !== null && $max !== '');
        $maxVal = $hasMax ? (float)$max : 0.0;
        if ($type === 'percent') {
            $main = ($val >= 100) ? 'Miễn phí' : ('Giảm ' . rtrim(rtrim(number_format((float)$val, 1, '.', ''), '0'), '.') . '%');
            if ($hasMax && $maxVal > 0) {
                $main .= ' (tối đa ' . formatMoney($maxVal) . 'đ)';
            }
            return $main;
        }
        return ($type === 'fixed')
            ? (formatMoney($val) . 'đ')
            : (rtrim(rtrim(number_format((float)$val, 1, '.', ''), '0'), '.') . '%');
    }
}

if (!function_exists('ecommerce_voucher_build_detail_text')) {
    /**
     * Tự động sinh mô tả chi tiết cho voucher dựa trên dữ liệu cấu hình.
     */
    function ecommerce_voucher_build_detail_text(array $data): string {
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $value = (float)($data['value'] ?? 0);
        $valueUnit = strtolower((string)($data['value_unit'] ?? 'fixed'));
        if ($valueUnit !== 'percent' && $valueUnit !== 'fixed') {
            $valueUnit = 'fixed';
        }
        $max = $data['max_discount'] ?? null;
        $maxUnit = strtolower((string)($data['max_discount_unit'] ?? 'fixed'));
        if ($maxUnit !== 'percent' && $maxUnit !== 'fixed') {
            $maxUnit = 'fixed';
        }
        $min = (float)($data['min_subtotal'] ?? 0);
        $minUnit = strtolower((string)($data['min_subtotal_unit'] ?? 'fixed'));
        if ($minUnit !== 'percent' && $minUnit !== 'fixed') {
            $minUnit = 'fixed';
        }
        $endAt = trim((string)($data['end_at'] ?? ''));
        $maxUses = $data['max_uses'] ?? null;

        $valueLabel = ecommerce_voucher_format_label($valueUnit, $value);
        $maxLabel = ($max !== null && $max !== '') ? ecommerce_voucher_format_label($maxUnit, (float)$max) : '';

        if ($valueUnit === 'percent') {
            $discountText = ($valueLabel === 'Miễn phí') ? "Mã $code miễn phí đơn hàng" : "Mã $code giảm " . ($valueLabel !== '' ? $valueLabel : ($value . '%'));
        } else {
            $discountText = "Mã $code giảm " . ($valueLabel !== '' ? $valueLabel : formatMoney($value) . 'đ');
        }

        if ($valueUnit === 'percent' && $maxLabel !== '') {
            $discountText .= ' tối đa ' . $maxLabel;
        }

        $minLabel = $min > 0 ? ecommerce_voucher_format_label($minUnit, $min) : '';
        $minText = $min > 0 && $minLabel !== '' ? (" cho đơn hàng hợp lệ từ $minLabel") : ' cho đơn hàng hợp lệ';
        $endText = $endAt !== '' ? (" HSD: $endAt.") : '';
        $limitText = ' Số lượng có hạn.';

        return $discountText . $minText . ' ' . $endText . $limitText . ' Không áp dụng với đơn hàng từ Người bán không đủ điều kiện hưởng ưu đãi vận chuyển.';
    }
}

if (!function_exists('ecommerce_voucher_get_status_text')) {
    /**
     * Lấy trạng thái văn bản của voucher (Đang hoạt động, Hết hạn, Sắp diễn ra, Tạm tắt).
     */
    function ecommerce_voucher_get_status_text(array $c): string {
        if ((int)($c['is_active'] ?? 0) !== 1) return 'Tạm tắt';
        $now = time();
        $start = !empty($c['start_at']) ? strtotime($c['start_at']) : null;
        $end = !empty($c['end_at']) ? strtotime($c['end_at']) : null;
        if ($start && $now < $start) return 'Sắp diễn ra';
        if ($end && $now > $end) return 'Hết hạn';
        return 'Đang hoạt động';
    }
}

if (!function_exists('ecommerce_voucher_get_time_summary')) {
    /**
     * Tóm tắt thời gian hiệu lực của voucher.
     */
    function ecommerce_voucher_get_time_summary(array $c): string {
        $start = trim((string)($c['start_at'] ?? ''));
        $end = trim((string)($c['end_at'] ?? ''));

        if ($start === '' && $end === '') {
            return 'Không giới hạn';
        }

        $now = time();
        $tsStart = $start !== '' ? strtotime($start) : 0;
        $tsEnd = $end !== '' ? strtotime($end) : 0;

        if ($tsEnd > 0 && $now > $tsEnd) {
            return 'Đã hết hạn';
        }

        if ($tsStart > 0 && $now < $tsStart) {
            $diff = $tsStart - $now;
            $days = floor($diff / 86400);
            if ($days > 0) return "Sắp diễn ra ($days ngày nữa)";
            $hours = floor($diff / 3600);
            if ($hours > 0) return "Sắp diễn ra ($hours giờ nữa)";
            return 'Sắp diễn ra';
        }

        if ($tsEnd > 0) {
            $diff = $tsEnd - $now;
            $days = floor($diff / 86400);
            if ($days > 0) return "Còn $days ngày (đến " . date('d/m/Y', $tsEnd) . ")";
            $hours = floor($diff / 3600);
            if ($hours > 0) return "Còn $hours giờ (đến " . date('H:i d/m', $tsEnd) . ")";
            return 'Sắp hết hạn';
        }

        return 'Đang diễn ra (Vô thời hạn)';
    }
}


if (!function_exists('ecommerce_session_voucher_get')) {
    /**
     * Lấy mã voucher từ session.
     */
    function ecommerce_session_voucher_get(string $target = 'order'): string {
        $target = ecommerce_normalize_discount_target($target);
        $key = $target === 'shipping' ? 'selected_voucher_code_shipping' : 'selected_voucher_code_order';
        $code = strtoupper(trim((string)($_SESSION[$key] ?? '')));
        if ($target === 'order' && $code === '') {
            $code = strtoupper(trim((string)($_SESSION['selected_voucher_code'] ?? '')));
        }
        return $code;
    }
}

if (!function_exists('ecommerce_session_voucher_set')) {
    /**
     * Lưu mã voucher vào session.
     */
    function ecommerce_session_voucher_set(string $code, string $target = 'order'): void {
        $target = ecommerce_normalize_discount_target($target);
        $key = $target === 'shipping' ? 'selected_voucher_code_shipping' : 'selected_voucher_code_order';
        $code = strtoupper(trim($code));
        if ($code === '') {
            unset($_SESSION[$key]);
            if ($target === 'order') unset($_SESSION['selected_voucher_code']);
            return;
        }
        $_SESSION[$key] = $code;
        if ($target === 'order') $_SESSION['selected_voucher_code'] = $code;
    }
}

if (!function_exists('ecommerce_session_voucher_clear')) {
    /**
     * Xoá mã voucher khỏi session.
     */
    function ecommerce_session_voucher_clear(string $target = 'order'): void {
        ecommerce_session_voucher_set('', $target);
    }
}

if (!function_exists('ecommerce_get_user_saved_voucher_codes')) {
    /**
     * Lấy danh sách mã voucher đã lưu của user.
     */
    function ecommerce_get_user_saved_voucher_codes(mysqli $ithanhloc, int $userId): array {
        if ($userId < 0) return [];
        $stmt = $ithanhloc->prepare('SELECT voucher_code FROM user_saved_voucher WHERE user_id=? ORDER BY created_at DESC, id DESC');
        if (!$stmt) return [];
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $codes = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['voucher_code'] ?? '')));
            if ($code !== '' && !in_array($code, $codes, true)) $codes[] = $code;
        }
        return $codes;
    }
}

if (!function_exists('ecommerce_voucher_parse_product_ids')) {
    /**
     * Chuẩn hoá danh sách ID sản phẩm/biến thể từ chuỗi hoặc mảng.
     */
    function ecommerce_voucher_parse_product_ids($raw): array {
        if (is_array($raw)) return normalizeProductIds($raw);
        $txt = trim((string)$raw);
        if ($txt === '') return [];
        $decoded = json_decode($txt, true);
        return is_array($decoded) ? normalizeProductIds($decoded) : normalizeProductIds($txt);
    }
}

if (!function_exists('ecommerce_voucher_parse_csv_list')) {
    /**
     * Parse chuỗi CSV thành mảng các giá trị trim.
     */
    function ecommerce_voucher_parse_csv_list($raw): array {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/[\s,;|]+/', (string)$raw);
        }
        $list = [];
        foreach ($items as $item) {
            $val = strtolower(trim((string)$item));
            if ($val === '') continue;
            if (!in_array($val, $list, true)) $list[] = $val;
        }
        return $list;
    }
}

if (!function_exists('ecommerce_voucher_parse_category_ids')) {
    /**
     * Parse chuỗi/mảng ID ngành hàng thành mảng int duy nhất.
     */
    function ecommerce_voucher_parse_category_ids($raw): array {
        $parts = is_array($raw) ? $raw : preg_split('/[^0-9]+/', (string)$raw);
        $catIds = [];
        foreach ($parts as $p) {
            $v = (int)$p;
            if ($v > 0 && !in_array($v, $catIds, true)) {
                $catIds[] = $v;
            }
        }
        return $catIds;
    }
}

if (!function_exists('ecommerce_voucher_determine_template_key')) {
    /**
     * Xác định template key của voucher dựa trên cấu hình.
     */
    function ecommerce_voucher_determine_template_key(array $c): string {
        $rawTpl = strtolower(trim((string)($c['voucher_template'] ?? '')));
        $allowed = ['order_discount','shipping_discount','only_category_discount','category_discount','payment_discount'];
        if (in_array($rawTpl, $allowed, true)) {
            return $rawTpl;
        }

        $rawTarget = trim((string)($c['discount_target'] ?? 'order'));
        $targets = ecommerce_voucher_parse_csv_list($rawTarget);
        $hasShipping = in_array('shipping', array_map('ecommerce_normalize_discount_target', $targets), true);
        $hasPayment = trim((string)($c['payment_methods'] ?? '')) !== '';
        $hasCategories = trim((string)($c['apply_category_ids'] ?? '')) !== '';
        $applyScope = strtolower((string)($c['apply_scope'] ?? 'all'));

        // Thứ tự ưu tiên đồng nhất với admin JS (buildDetailHtml):
        // shipping > payment > category > order
        if ($hasShipping && !$hasPayment) return 'shipping_discount';
        if ($hasPayment) return 'payment_discount';
        if ($hasCategories) return $applyScope === 'products' ? 'only_category_discount' : 'category_discount';
        return 'order_discount';
    }
}

if (!function_exists('ecommerce_voucher_allowed_targets')) {
    /**
     * Lấy danh sách mục tiêu áp dụng (order/shipping) của voucher.
     */
    function ecommerce_voucher_allowed_targets(array $coupon): array {
        $templateKey = ecommerce_voucher_determine_template_key($coupon);
        if ($templateKey === 'shipping_discount') return ['shipping'];
        if (in_array($templateKey, ['payment_discount', 'category_discount', 'only_category_discount', 'order_discount'], true)) {
            $rawTpl = strtolower(trim((string)($coupon['voucher_template'] ?? '')));
            if ($rawTpl === '' || $rawTpl === 'order') {
                $raw = ecommerce_voucher_parse_csv_list($coupon['discount_target'] ?? '');
                if ($raw) {
                    $normalized = [];
                    foreach ($raw as $item) {
                        $key = ecommerce_normalize_discount_target($item);
                        if (!in_array($key, $normalized, true)) $normalized[] = $key;
                    }
                    if ($normalized) return $normalized;
                }
            }
            return ['order'];
        }
        return ['order'];
    }
}

if (!function_exists('ecommerce_voucher_fetch_product_categories')) {
    /**
     * Lấy map category_id cho danh sách product_id.
     */
    function ecommerce_voucher_fetch_product_categories(mysqli $ithanhloc, array $productIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$ids) return [];

        $productTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product']) : 'ecommerce_product';
        if ($productTable === '') return [];

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $ithanhloc->prepare("SELECT id, category_id FROM `{$productTable}` WHERE id IN ({$ph})");
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $pid = (int)($row['id'] ?? 0);
                $cid = (int)($row['category_id'] ?? 0);
                if ($pid > 0) $map[$pid] = $cid;
            }
        }
        $stmt->close();
        return $map;
    }
}

if (!function_exists('ecommerce_voucher_filter_eligible_keys')) {
    /**
     * Lọc danh sách item keys đủ điều kiện áp dụng voucher.
     */
    function ecommerce_voucher_filter_eligible_keys(mysqli $ithanhloc, array $coupon, array $itemContexts): array {
        if (!$itemContexts) return [];

        $scope = strtolower(trim((string)($coupon['apply_scope'] ?? 'all')));
        $rawCategoryIds = (string)($coupon['apply_category_ids'] ?? '');
        $hasCategoryFilter = trim($rawCategoryIds) !== '';
        $hasProductFilter = $scope === 'products';

        $allowedP = $hasProductFilter ? ecommerce_voucher_parse_product_ids($coupon['apply_product_ids'] ?? '') : [];
        $allowedG = $hasProductFilter ? ecommerce_voucher_parse_product_ids($coupon['apply_variant_group_ids'] ?? '') : [];
        $allowedV = $hasProductFilter ? ecommerce_voucher_parse_product_ids($coupon['apply_variant_ids'] ?? '') : [];
        $hasAnyProductLevelFilter = ($allowedP || $allowedG || $allowedV);

        $excluded = ecommerce_voucher_parse_product_ids($coupon['exclude_product_ids'] ?? '');
        $excludedSet = array_flip($excluded);
        $catIds = $hasCategoryFilter ? ecommerce_voucher_parse_category_ids($rawCategoryIds) : [];
        
        $items = [];
        foreach ($itemContexts as $idx => $it) {
            if (is_array($it)) {
                $items[$idx] = [
                    'key' => (string)($it['key'] ?? $idx),
                    'pid' => (int)($it['pid'] ?? $it['product_id'] ?? $it['id'] ?? 0),
                    'vid' => (int)($it['vid'] ?? $it['variant_id'] ?? 0),
                    'gid' => (int)($it['gid'] ?? $it['group_id'] ?? 0),
                ];
            } else {
                $items[$idx] = ['key' => (string)$it, 'pid' => (int)$it, 'vid' => 0, 'gid' => 0];
            }
        }

        $productIds = array_unique(array_column($items, 'pid'));
        $catMap = $catIds ? ecommerce_voucher_fetch_product_categories($ithanhloc, $productIds) : [];

        $eligibleKeys = [];
        foreach ($items as $it) {
            $pid = $it['pid'];
            if ($pid <= 0 || isset($excludedSet[$pid])) continue;

            if ($hasAnyProductLevelFilter) {
                $matched = false;
                if ($allowedP && in_array($pid, $allowedP, true)) $matched = true;
                if (!$matched && $allowedG && $it['gid'] > 0 && in_array($it['gid'], $allowedG, true)) $matched = true;
                if (!$matched && $allowedV && $it['vid'] > 0 && in_array($it['vid'], $allowedV, true)) $matched = true;
                if (!$matched) continue;
            }

            if ($catIds) {
                $cid = (int)($catMap[$pid] ?? 0);
                if (!$cid || !in_array($cid, $catIds, true)) continue;
            }

            $eligibleKeys[] = $it['key'];
        }
        return array_values(array_unique($eligibleKeys));
    }
}

if (!function_exists('ecommerce_voucher_compute_base_amount')) {
    /**
     * Tính toán giá trị đơn hàng được dùng làm căn cứ tính giảm giá.
     */
    function ecommerce_voucher_compute_base_amount(mysqli $ithanhloc, array $eligibleKeys, float $fallbackSubtotal): float {
        if (!$eligibleKeys) return 0.0;
        $cart = isset($_SESSION['shop_cart']) && is_array($_SESSION['shop_cart']) ? $_SESSION['shop_cart'] : [];
        if (!$cart) return $fallbackSubtotal;

        $eligibleSet = array_flip($eligibleKeys);
        $sum = 0.0;
        foreach ($cart as $it) {
            if (!is_array($it)) continue;
            $key = (string)($it['key'] ?? '');
            if ($key === '' || !isset($eligibleSet[$key])) continue;
            
            $qty = (int)($it['qty'] ?? $it['quantity'] ?? 0);
            $price = 0.0;
            if (array_key_exists('price_base', $it)) {
                $price = (float)$it['price_base'];
            } else {
                $pRaw = $it['price'] ?? ($it['unit_price'] ?? ($it['unitPrice'] ?? 0));
                $price = (float)preg_replace('/[^0-9.]/', '', (string)$pRaw);
            }
            if ($qty > 0 && $price > 0) $sum += $price * $qty;
        }

        if ($sum <= 0 && $fallbackSubtotal > 0) return $fallbackSubtotal;
        return max(0.0, $sum);
    }
}

if (!function_exists('ecommerce_voucher_normalize_value_unit')) {
    /**
     * Chuẩn hoá đơn vị giá trị voucher (percent/fixed).
     */
    function ecommerce_voucher_normalize_value_unit($rawValueUnit, $fallbackType = 'fixed'): string {
        $unit = strtolower(trim((string)$rawValueUnit));
        if ($unit === 'percent' || $unit === 'fixed') return $unit;
        $typeRaw = strtolower(trim((string)$fallbackType));
        return $typeRaw === 'percent' ? 'percent' : 'fixed';
    }
}

if (!function_exists('ecommerce_voucher_allows_channel')) {
    /**
     * Kiểm tra voucher có được phép áp dụng trên kênh bán hàng này không.
     */
    function ecommerce_voucher_allows_channel(array $coupon, string $channel): bool {
        $allowed = ecommerce_voucher_parse_csv_list($coupon['apply_channel'] ?? '');
        if (!$allowed) return true;

        $norm = static function (string $v): string {
            $v = strtolower(trim($v));
            if ($v === '') return '';
            // common aliases
            if ($v === 'website' || $v === 'site' || $v === 'browser' || $v === 'online') return 'web';
            if ($v === 'mobile' || $v === 'app' || $v === 'android' || $v === 'ios') return 'app';
            // keep alnum/_/- only
            $v = preg_replace('/[^a-z0-9_\-]/', '', $v);
            return $v;
        };

        $c = $norm($channel);
        if ($c === '') return true;

        foreach ($allowed as $raw) {
            $a = $norm((string)$raw);
            if ($a === '' ) continue;
            if ($a === 'all' || $a === '*' || $a === 'any') return true;
            if ($a === $c) return true;
            // allow web aliases in stored list
            if ($c === 'web' && ($a === 'website' || $a === 'site' || $a === 'online')) return true;
        }
        return false;
    }
}

if (!function_exists('ecommerce_voucher_normalize_max_discount_unit')) {
    /**
     * Chuẩn hoá đơn vị trần giảm giá.
     */
    function ecommerce_voucher_normalize_max_discount_unit($rawUnit): string {
        $u = strtolower(trim((string)$rawUnit));
        return ($u === 'percent' || $u === 'fixed') ? $u : 'fixed';
    }
}

if (!function_exists('ecommerce_voucher_exists')) {
    /**
     * Kiểm tra mã voucher có tồn tại trong hệ thống hay không.
     */
    function ecommerce_voucher_exists(mysqli $ithanhloc, string $code): bool {
        $code = strtoupper(trim($code));
        if ($code === '') return false;
        $stmt = $ithanhloc->prepare('SELECT id FROM ecommerce_voucher WHERE UPPER(code)=? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res && $res->num_rows > 0);
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('ecommerce_voucher_fetch_active_list')) {
    /**
     * Lấy tất cả voucher đang hoạt động tại thời điểm hiện tại.
     */
    function ecommerce_voucher_fetch_active_list(mysqli $ithanhloc, string $now): array {
        // NOTE: start_at/end_at/max_uses/used_count are stored as longtext in this DB.
        // Avoid brittle string comparisons in SQL; filter date range in PHP.
        $stmt = $ithanhloc->prepare("SELECT * FROM ecommerce_voucher
                    WHERE CAST(is_active AS UNSIGNED) = 1
                        AND (max_uses IS NULL OR max_uses = '' OR CAST(max_uses AS UNSIGNED) <= 0
                             OR CAST(COALESCE(used_count, '0') AS UNSIGNED) < CAST(max_uses AS UNSIGNED))
                    ORDER BY id DESC");
        if (!$stmt) return [];
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $rows = is_array($rows) ? $rows : [];

        if (!$rows) return [];

        $nowTs = @strtotime($now);
        if ($nowTs === false) $nowTs = time();

        $parseTs = static function (string $raw, bool $isEnd): ?int {
            $s = trim($raw);
            if ($s === '' || $s === '0000-00-00 00:00:00' || $s === '0000-00-00') return null;
            $s = str_replace('T', ' ', $s);
            $s = preg_replace('/\.(\d{1,6})(Z|[\+\-]\d{2}:?\d{2})?$/', '', $s);
            $s = preg_replace('/Z$/i', '', $s);
            $s = trim($s);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                $s .= $isEnd ? ' 23:59:59' : ' 00:00:00';
            }
            $ts = @strtotime($s);
            if ($ts === false) return null;
            return (int)$ts;
        };

        $rows = array_values(array_filter($rows, static function ($row) use ($nowTs, $parseTs) {
            if (!is_array($row)) return false;
            $startRaw = (string)($row['start_at'] ?? '');
            $endRaw = (string)($row['end_at'] ?? '');
            $startTs = $parseTs($startRaw, false);
            $endTs = $parseTs($endRaw, true);
            if ($startTs !== null && $nowTs < $startTs) return false;
            if ($endTs !== null && $nowTs > $endTs) return false;
            return true;
        }));

        return $rows;
    }
}

if (!function_exists('ecommerce_voucher_validate')) {
    /**
     * Kiểm tra tính hợp lệ và tính toán số tiền giảm giá của voucher.
     */
    function ecommerce_voucher_validate(mysqli $ithanhloc, string $code, float $subtotal, array $productIds = [], string $target = 'order', float $shippingFee = 0, string $paymentMethod = '', string $shippingMethod = '', string $channel = 'web', int $userId = 0, string $mode = 'apply'): array {
        $code = strtoupper(trim($code));
        $target = ecommerce_normalize_discount_target($target);
        if ($code === '') return ['ok' => true, 'code' => '', 'discount' => 0, 'target' => $target, 'msg' => ''];

        $stmt = $ithanhloc->prepare("SELECT * FROM ecommerce_voucher WHERE UPPER(code)=? LIMIT 1");
        if (!$stmt) return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Lỗi hệ thống kiểm tra mã'];
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã giảm giá không tồn tại'];
        if ((int)($row['is_active'] ?? 0) !== 1) return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã giảm giá đang tạm ngưng'];

        $allowedTargets = ecommerce_voucher_allowed_targets($row);
        if (!in_array($target, $allowedTargets, true)) {
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => $target === 'shipping' ? 'Mã không áp dụng cho phí vận chuyển' : 'Mã không áp dụng cho đơn hàng'];
        }

        // Kiểm tra user
        $allowedUsers = ecommerce_voucher_parse_product_ids($row['apply_user_ids'] ?? '');
        if ($allowedUsers && ($userId < 0 || !in_array($userId, $allowedUsers, true))) {
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã chỉ dành cho người dùng trong chương trình'];
        }

        // Lọc item contexts
        $itemContexts = [];
        if (isset($_SESSION['shop_cart']) && is_array($_SESSION['shop_cart'])) {
            foreach ($_SESSION['shop_cart'] as $it) {
                if (!is_array($it) || !empty($it['is_gift'])) continue;
                $itemContexts[] = [
                    'key' => (string)($it['key'] ?? ''),
                    'pid' => (int)($it['product_id'] ?? $it['id'] ?? 0),
                    'vid' => (int)($it['variant_id'] ?? 0),
                    'gid' => (int)($it['group_id'] ?? 0),
                ];
            }
        } else {
            foreach ($productIds as $pid) $itemContexts[] = ['pid' => (int)$pid, 'vid' => 0, 'gid' => 0];
        }

        $eligibleKeys = ecommerce_voucher_filter_eligible_keys($ithanhloc, $row, $itemContexts);
        if ($itemContexts && !$eligibleKeys) {
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã không áp dụng cho sản phẩm hiện tại'];
        }

        // Kiểm tra payment/shipping method
        $pmAllowed = ecommerce_voucher_parse_csv_list($row['payment_methods'] ?? '');
        if ($pmAllowed && $paymentMethod !== '' && !in_array(strtolower($paymentMethod), $pmAllowed, true)) {
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã không áp dụng cho phương thức thanh toán này'];
        }
        $smAllowed = ecommerce_voucher_parse_csv_list($row['shipping_methods'] ?? '');
        if ($smAllowed && $shippingMethod !== '' && !in_array(strtolower($shippingMethod), $smAllowed, true)) {
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã không áp dụng cho đơn vị vận chuyển này'];
        }

        // Kiểm tra thời gian
        $now = date('Y-m-d H:i:s');
        $startAt = trim((string)($row['start_at'] ?? ''));
        $endAt = trim((string)($row['end_at'] ?? ''));
        if ($startAt === '0000-00-00 00:00:00' || $startAt === '0000-00-00') $startAt = '';
        if ($endAt === '0000-00-00 00:00:00' || $endAt === '0000-00-00') $endAt = '';
        if ($startAt !== '' && $now < $startAt) return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã chưa đến thời gian áp dụng'];
        if ($endAt !== '' && $now > $endAt) return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã đã hết hạn'];

        // Kiểm tra lượt dùng
        // Lưu ý: một số hệ thống lưu max_uses/used_count dạng longtext (có thể chứa khoảng trắng/ký tự khác).
        // Chỉ chặn khi parse được max_uses > 0 và used_count >= max_uses.
        $parseIntFirst = static function ($raw): int {
            $s = trim((string)$raw);
            if ($s === '') return 0;
            if (preg_match('/-?\d+/', $s, $m)) return (int)$m[0];
            return 0;
        };
        $maxUses = max(0, $parseIntFirst($row['max_uses'] ?? ''));
        $usedCount = max(0, $parseIntFirst($row['used_count'] ?? 0));
        if ($maxUses > 0 && $usedCount >= $maxUses) {
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Mã đã hết lượt sử dụng'];
        }

        // Kiểm tra đơn tối thiểu
        $minVal = (float)($row['min_subtotal'] ?? 0);
        if ($minVal > 0) {
            $minUnit = strtolower(trim((string)($row['min_subtotal_unit'] ?? 'fixed')));
            $threshold = $minUnit === 'percent' ? ($subtotal * $minVal / 100.0) : $minVal;
            if ($subtotal < $threshold) return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Đơn tối thiểu ' . number_format($threshold, 0, ',', '.') . 'đ để áp dụng'];
        }

        // Tính discount
        $baseAmount = $target === 'shipping' ? (float)$shippingFee : (float)$subtotal;
        if ($target === 'order' && $eligibleKeys) {
            $baseAmount = ecommerce_voucher_compute_base_amount($ithanhloc, $eligibleKeys, $baseAmount);
        }
        
        if ($baseAmount <= 0) {
            if ($target === 'shipping' && $mode === 'list') return ['ok' => true, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => ''];
            return ['ok' => false, 'code' => $code, 'discount' => 0, 'target' => $target, 'msg' => 'Giá trị đơn không đủ để áp dụng'];
        }

        $val = (float)($row['value'] ?? 0);
        $unit = ecommerce_voucher_normalize_value_unit($row['value_unit'] ?? '', $row['type'] ?? 'fixed');
        $discount = $unit === 'percent' ? ($baseAmount * $val / 100.0) : $val;

        $maxD = $row['max_discount'] ?? null;
        if ($maxD !== null && $maxD !== '') {
            $maxDVal = (float)$maxD;
            $maxDUnit = ecommerce_voucher_normalize_max_discount_unit($row['max_discount_unit'] ?? 'fixed');
            $cap = $maxDUnit === 'percent' ? ($baseAmount * $maxDVal / 100.0) : $maxDVal;
            $discount = min($discount, $cap);
        }

        $discount = max(0, min($discount, $baseAmount));
        return ['ok' => true, 'code' => $code, 'discount' => $discount, 'target' => $target, 'msg' => 'Áp dụng thành công'];
    }
}

if (!function_exists('ecommerce_build_bxgy_meta')) {
    /**
     * Xây dựng phần meta liên quan đến promo mua X tặng Y (BXGY).
     */
    function ecommerce_build_bxgy_meta(mysqli $ithanhloc, array $cart): array {
        $baseItems = [];
        foreach ($cart as $it) {
            $isGiftItem = !empty($it['is_gift']) || ((float)($it['price'] ?? 0) <= 0.0);
            if ($isGiftItem) continue;
            $baseItems[] = $it;
        }

        if (!function_exists('bxgy_get_active_promos')) return ['bxgy_promos' => [], 'bxgy_applied_promos' => [], 'bxgy' => ['discount' => 0.0, 'gifts' => []]];

        $bxgyPromos = bxgy_get_active_promos($ithanhloc);
        $bxgyResult = bxgy_compute_discount_for_items($baseItems, $bxgyPromos);
        $bxgyGifts = isset($bxgyResult['gifts']) && is_array($bxgyResult['gifts']) ? array_values($bxgyResult['gifts']) : [];
        $bxgyAppliedPromos = isset($bxgyResult['applied_promos']) && is_array($bxgyResult['applied_promos']) ? $bxgyResult['applied_promos'] : [];

        $bxgyGiftAllIds = [];
        if (is_array($bxgyPromos)) {
            foreach ($bxgyPromos as $p) {
                $ids = (isset($p['gift_product_ids']) && is_array($p['gift_product_ids'])) ? $p['gift_product_ids'] : [];
                foreach ($ids as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0) $bxgyGiftAllIds[$gid] = $gid;
                }
            }
        }

        if ($bxgyGiftAllIds) {
            $idList = implode(',', array_map('intval', array_values($bxgyGiftAllIds)));
            $giftMap = [];
            $q = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) AS min_price FROM ecommerce_product p WHERE p.id IN ($idList)");
            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    $gid = (int)($r['id'] ?? 0);
                    if ($gid <= 0) continue;
                    $giftMap[$gid] = [
                        'product_id' => $gid,
                        'name' => (string)($r['product_name'] ?? ''),
                        'thumb' => (string)($r['image_url'] ?? ''),
                        'price' => (float)($r['min_price'] ?? 0),
                    ];
                }
            }

            foreach ($bxgyPromos as &$p) {
                $ids = (isset($p['gift_product_ids']) && is_array($p['gift_product_ids'])) ? $p['gift_product_ids'] : [];
                $p['gift_products'] = [];
                foreach ($ids as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0 && isset($giftMap[$gid])) {
                        $p['gift_products'][] = $giftMap[$gid];
                    }
                }
            }
            unset($p);
        }

        return [
            'bxgy_promos' => $bxgyPromos,
            'bxgy_applied_promos' => $bxgyAppliedPromos,
            'bxgy' => [
                'discount' => 0.0,
                'gifts' => $bxgyGifts,
            ],
        ];
    }
}

if (!function_exists('ecommerce_map_payment_labels')) {
    /**
     * Chuyển đổi danh sách key thành danh sách object {key, label}.
     */
    function ecommerce_map_payment_labels(array $keys): array {
        $map = ecommerce_payment_label_map();
        $list = [];
        foreach ($keys as $key) {
            if (isset($map[$key])) {
                $list[] = ['key' => $key, 'label' => $map[$key]];
            }
        }
        return $list;
    }
}

if (!function_exists('ecommerce_decode_json_array')) {
    /**
     * Decode JSON string to array, with fallback to CSV or raw array.
     */
    function ecommerce_decode_json_array($val): array {
        if (is_array($val)) return $val;
        $s = trim((string)$val);
        if ($s === '') return [];
        if ($s[0] === '[' || $s[0] === '{') {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) return $decoded;
        }
        return array_filter(array_map('trim', explode(',', $s)));
    }
}

if (!function_exists('ecommerce_current_user_id')) {
    /**
     * Lấy ID người dùng hiện tại từ session.
     */
    function ecommerce_current_user_id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('ecommerce_user_load')) {
    /**
     * Lấy hồ sơ tài khoản người dùng theo user_id.
     */
    function ecommerce_user_load(mysqli $ithanhloc, int $userId): ?array {
        if ($userId < 0) return null;
        $stmt = $ithanhloc->prepare('SELECT username, full_name, phone, email, address, avatar, gender, birthday, role, password, created_at FROM users WHERE id=? LIMIT 1');
        if (!$stmt) {
            error_log('ecommerce_user_load prepare failed: ' . $ithanhloc->error);
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $user;
    }
}

if (!function_exists('ecommerce_user_get_default_location')) {
    /**
     * Lấy địa chỉ giao hàng mặc định của user từ user_saved_locations.
     */
    function ecommerce_user_get_default_location(mysqli $ithanhloc, int $userId): array {
        if ($userId <= 0) return [];
        
        // Try default first
        $stmt = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id=? AND is_default=1 ORDER BY updated_at DESC, id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $payload = json_decode((string)($row['payload_json'] ?? ''), true);
                if (!is_array($payload)) $payload = [];
                $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
                $payload['is_default'] = (int)($row['is_default'] ?? 0);
                return $payload;
            }
        }

        // Fallback to latest
        $stmt2 = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id=? ORDER BY updated_at DESC, id DESC LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($row) {
                $payload = json_decode((string)($row['payload_json'] ?? ''), true);
                if (!is_array($payload)) $payload = [];
                $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
                $payload['is_default'] = (int)($row['is_default'] ?? 0);
                return $payload;
            }
        }

        return [];
    }
}

if (!function_exists('ecommerce_parse_payment_meta')) {
    /**
     * Parse payment metadata từ DB.
     */
    function ecommerce_parse_payment_meta($raw): array {
        return ecommerce_decode_json_array($raw);
    }
}

if (!function_exists('ecommerce_extract_momo_expire_ts')) {
    /**
     * Trích xuất thời gian hết hạn của đơn MoMo.
     */
    function ecommerce_extract_momo_expire_ts(array $paymentMeta): int {
        $raw = is_array($paymentMeta['raw'] ?? null) ? $paymentMeta['raw'] : [];
        $candidates = [
            $paymentMeta['expire_ts'] ?? null,
            $paymentMeta['expired_ts'] ?? null,
            $paymentMeta['expireTime'] ?? null,
            $paymentMeta['expiredTime'] ?? null,
            $paymentMeta['expiresAt'] ?? null,
            $paymentMeta['expire_at'] ?? null,
            $raw['expire_ts'] ?? null,
            $raw['expired_ts'] ?? null,
            $raw['expireTime'] ?? null,
            $raw['expiredTime'] ?? null,
            $raw['expiresAt'] ?? null,
            $raw['expire_at'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $ts = parseFlexibleTs($candidate);
            if ($ts > 0) return $ts;
        }

        $createdTs = ecommerce_parse_hcm_datetime_to_ts((string)($paymentMeta['created_at'] ?? ''));
        if ($createdTs <= 0) {
            $createdTs = ecommerce_parse_hcm_datetime_to_ts((string)($raw['created_at'] ?? ''));
        }
        if ($createdTs > 0) {
            return $createdTs + (15 * 60);
        }
        return 0;
    }
}

if (!function_exists('pm_header_get_unread_notification_count')) {
    function pm_header_get_unread_notification_count(?mysqli $ithanhloc, int $userId): int {
        if (!$ithanhloc || $userId <= 0) return 0;

        $count = 0;
        $stmtUnreadUser = $ithanhloc->prepare("SELECT COUNT(*) c FROM user_notification
            WHERE user_id=?
                AND COALESCE(NULLIF(TRIM(CAST(is_read AS CHAR)),''),'0')='0'
                AND COALESCE(NULLIF(TRIM(CAST(is_active AS CHAR)),''),'1')='1'");
        if ($stmtUnreadUser) {
            $stmtUnreadUser->bind_param('i', $userId);
            $stmtUnreadUser->execute();
            $rowUnreadUser = $stmtUnreadUser->get_result()->fetch_assoc();
            $count += (int)($rowUnreadUser['c'] ?? 0);
            $stmtUnreadUser->close();
        }

        $stmtUnreadBroadcast = $ithanhloc->prepare("SELECT COUNT(*) c
            FROM user_notification n
            WHERE n.user_id=0
                AND COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'
                AND NOT EXISTS (
                    SELECT 1
                    FROM user_notification_read r
                    WHERE r.user_id=? AND r.notification_id=n.id
                )");
        if ($stmtUnreadBroadcast) {
            $stmtUnreadBroadcast->bind_param('i', $userId);
            $stmtUnreadBroadcast->execute();
            $rowUnreadBroadcast = $stmtUnreadBroadcast->get_result()->fetch_assoc();
            $count += (int)($rowUnreadBroadcast['c'] ?? 0);
            $stmtUnreadBroadcast->close();
        }

        return max(0, $count);
    }
}

if (!function_exists('pm_header_get_selected_location')) {
    function pm_header_get_selected_location(?mysqli $ithanhloc, int $userId): array {
        $selectedLocation = [];

        // Logged-in user: read default location from DB
        if ($userId > 0 && $ithanhloc) {
            if (function_exists('ensure_user_saved_locations_index')) {
                ensure_user_saved_locations_index($ithanhloc);
            }
            if (function_exists('ecommerce_user_get_default_location')) {
                $selectedLocation = ecommerce_user_get_default_location($ithanhloc, $userId);
            } elseif (function_exists('fetchDefaultSavedLocation')) {
                $selectedLocation = fetchDefaultSavedLocation($ithanhloc, $userId);
            }
        }

        // Guest: read from session (set by main/account/region-session.php)
        if ($userId <= 0 && !$selectedLocation) {
            if (!empty($_SESSION['guest_location']) && is_array($_SESSION['guest_location'])) {
                $selectedLocation = $_SESSION['guest_location'];
            } elseif (!empty($_SESSION['guest_locations']) && is_array($_SESSION['guest_locations'])) {
                $guestList = array_values(array_filter($_SESSION['guest_locations'], static fn($x) => is_array($x)));
                if ($guestList) {
                    $default = null;
                    foreach ($guestList as $loc) {
                        if ((int)($loc['is_default'] ?? 0) === 1) {
                            $default = $loc;
                            break;
                        }
                    }
                    $selectedLocation = $default ?: ($guestList[0] ?? []);
                }
            }
        }

        return is_array($selectedLocation) ? $selectedLocation : [];
    }
}

if (!function_exists('pm_header_build_applied_address')) {
    function pm_header_build_applied_address(array $selectedLocation): string {
        $headerAppliedAddress = trim((string)($selectedLocation['customer_address'] ?? ''));
        if ($headerAppliedAddress === '') {
            $parts = array_filter([
                trim((string)($selectedLocation['street'] ?? '')),
                trim((string)($selectedLocation['ward'] ?? '')),
                trim((string)($selectedLocation['district'] ?? '')),
                trim((string)($selectedLocation['province'] ?? '')),
            ], static fn($v) => $v !== '');
            if ($parts) {
                $headerAppliedAddress = implode(', ', $parts);
            }
        }
        return ($headerAppliedAddress !== '') ? $headerAppliedAddress : 'Chưa thiết lập địa chỉ';
    }
}

if (!function_exists('ecommerce_location_extract_fields')) {
    /**
     * Chuẩn hoá các trường địa chỉ giao hàng để dùng chung giữa header/account/checkout.
     * Trả về mảng field đã trim/cast, có hỗ trợ fallback phone/recipient.
     */
    function ecommerce_location_extract_fields(array $selectedLocation, array $opts = []): array {
        $fallbackPhone = trim((string)($opts['fallback_phone'] ?? ''));
        $fallbackRecipientName = trim((string)($opts['fallback_recipient_name'] ?? ''));

        $addressType = trim((string)($selectedLocation['address_type'] ?? 'home'));
        if (!in_array($addressType, ['home', 'office'], true)) {
            $addressType = 'home';
        }

        $contactPhone = trim((string)($selectedLocation['contact_phone'] ?? ''));
        if ($contactPhone === '' && $fallbackPhone !== '') {
            $contactPhone = $fallbackPhone;
        }

        $recipientName = trim((string)($selectedLocation['recipient_name'] ?? ''));
        if ($recipientName === '' && $fallbackRecipientName !== '') {
            $recipientName = $fallbackRecipientName;
        }

        return [
            'branch_id' => (int)($selectedLocation['branch_id'] ?? 0),
            'address_id' => trim((string)($selectedLocation['address_id'] ?? '')),
            'street' => trim((string)($selectedLocation['street'] ?? '')),
            'ward' => trim((string)($selectedLocation['ward'] ?? '')),
            'ward_code' => trim((string)($selectedLocation['ward_code'] ?? '')),
            'district' => trim((string)($selectedLocation['district'] ?? '')),
            'district_id' => (int)($selectedLocation['district_id'] ?? 0),
            'province' => trim((string)($selectedLocation['province'] ?? '')),
            'province_id' => (int)($selectedLocation['province_id'] ?? 0),
            'contact_phone' => $contactPhone,
            'recipient_name' => $recipientName,
            'address_type' => $addressType,
            'delivery_note' => trim((string)($selectedLocation['delivery_note'] ?? '')),
        ];
    }
}

if (!function_exists('ecommerce_get_active_location_fields')) {
    /**
     * Helper tổng hợp để lấy toàn bộ thông tin địa chỉ đang áp dụng (cho cả user và guest).
     * Kết quả bao gồm các trường chi tiết và chuỗi địa chỉ đã build sẵn để hiển thị.
     */
    function ecommerce_get_active_location_fields(?mysqli $ithanhloc, int $userId, array $opts = []): array {
        // 1. Lấy địa chỉ đang chọn (DB hoặc Session)
        $selectedLocation = function_exists('pm_header_get_selected_location')
            ? pm_header_get_selected_location($ithanhloc, $userId)
            : [];

        // 2. Chuẩn bị các giá trị fallback
        $fallbackPhone = trim((string)($opts['fallback_phone'] ?? $_SESSION['user_phone'] ?? $_SESSION['phone'] ?? ''));
        $fallbackRecipientName = trim((string)($opts['fallback_recipient_name'] ?? ''));

        // 3. Trích xuất các trường chi tiết
        $fields = function_exists('ecommerce_location_extract_fields')
            ? ecommerce_location_extract_fields($selectedLocation, [
                'fallback_phone' => $fallbackPhone,
                'fallback_recipient_name' => $fallbackRecipientName,
            ])
            : [];

        // 4. Build chuỗi địa chỉ hiển thị
        $appliedAddress = function_exists('pm_header_build_applied_address')
            ? pm_header_build_applied_address($selectedLocation)
            : 'Chưa thiết lập địa chỉ';

        return array_merge($fields, [
            'applied_address' => $appliedAddress,
            'fallback_phone' => $fallbackPhone,
            'raw_location' => $selectedLocation, // Giữ lại raw nếu cần dùng thêm logic riêng
        ]);
    }
}

if (!function_exists('ecommerce_normalize_momo_meta_view')) {
    /**
     * Chuẩn hoá meta MoMo để hiển thị/xử lý.
     */
    function ecommerce_normalize_momo_meta_view(array $paymentMeta): array {
        $raw = is_array($paymentMeta['raw'] ?? null) ? $paymentMeta['raw'] : [];
        $requestId = pickFirstNonEmpty([
            $paymentMeta['request_id'] ?? '',
            $paymentMeta['requestId'] ?? '',
            $raw['requestId'] ?? '',
        ]);
        $qrUrl = pickFirstNonEmpty([
            $paymentMeta['qr_url'] ?? '',
            $paymentMeta['qrCodeUrl'] ?? '',
            $raw['qr_url'] ?? '',
            $raw['qrCodeUrl'] ?? '',
        ]);
        $payUrl = pickFirstNonEmpty([
            $paymentMeta['pay_url'] ?? '',
            $paymentMeta['payment_url'] ?? '',
            $paymentMeta['payUrl'] ?? '',
            $raw['pay_url'] ?? '',
            $raw['payment_url'] ?? '',
            $raw['payUrl'] ?? '',
            $raw['shortLink'] ?? '',
        ]);
        $deeplink = pickFirstNonEmpty([
            $paymentMeta['deeplink'] ?? '',
            $raw['deeplink'] ?? '',
            $raw['deeplinkMiniApp'] ?? '',
        ]);

        return [
            'request_id' => $requestId,
            'qr_url' => $qrUrl,
            'pay_url' => $payUrl,
            'deeplink' => $deeplink,
            'created_at' => pickFirstNonEmpty([
                $paymentMeta['created_at'] ?? '',
                $raw['created_at'] ?? '',
            ]),
            'expire_ts' => ecommerce_extract_momo_expire_ts($paymentMeta),
            'last_result_code' => (string)($paymentMeta['last_result_code'] ?? ''),
            'last_message' => (string)($paymentMeta['last_message'] ?? ''),
            'last_checked_at' => (string)($paymentMeta['last_checked_at'] ?? ''),
        ];
    }
}

if (!function_exists('ecommerce_parse_hcm_datetime_to_ts')) {
    /**
     * Chuyển chuỗi datetime (Asia/Ho_Chi_Minh) sang timestamp.
     */
    function ecommerce_parse_hcm_datetime_to_ts(string $raw): int {
        $txt = trim($raw);
        if ($txt === '') return 0;
        try {
            $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $txt, $tz);
            if ($dt instanceof DateTimeImmutable) {
                return (int)$dt->getTimestamp();
            }
            $dt = new DateTimeImmutable($txt, $tz);
            return (int)$dt->getTimestamp();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/**
 * Kiểm tra sản phẩm có phân loại (biến thể) hay không.
 */
if (!function_exists('ecommerce_product_has_variants')) {
    function ecommerce_product_has_variants(mysqli $ithanhloc, string $variantTable, int $productId): bool {
        if ($productId <= 0 || $variantTable === '') return false;
        try {
            $stmt = $ithanhloc->prepare("SELECT 1 FROM `{$variantTable}` WHERE product_id=? LIMIT 1");
            if (!$stmt) return false;
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            $has = ($res instanceof mysqli_result) ? ($res->num_rows > 0) : false;
            $stmt->close();
            return $has;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Đồng bộ lại trường ecommerce_product.coating_system dựa theo bảng hệ thống sơn chi tiết.
 */
if (!function_exists('ecommerce_product_rebuild_coating_summary')) {
    function ecommerce_product_rebuild_coating_summary(mysqli $ithanhloc, string $coatingTable, int $productId): void {
        if ($productId <= 0 || $coatingTable === '') return;
        try {
            $sql = "SELECT c.layer_type, c.layer_count, p.product_name AS suggest_product_name, cat.name AS category_name
                    FROM `{$coatingTable}` c
                    LEFT JOIN ecommerce_product p ON p.id = c.suggest_product_id
                    LEFT JOIN ecommerce_category cat ON cat.id = c.category_id
                    WHERE c.product_id = ?
                    ORDER BY c.sort_order ASC, c.id ASC";
            $stmt = $ithanhloc->prepare($sql);
            if (!$stmt) return;
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            $lines = [];
            while ($row = $res->fetch_assoc()) {
                $layerType = trim((string)($row['layer_type'] ?? ''));
                $layerCount = (int)($row['layer_count'] ?? 0);
                $prodName = trim((string)($row['suggest_product_name'] ?? ''));
                $catName = trim((string)($row['category_name'] ?? ''));

                $parts = [];
                if ($layerType !== '') $parts[] = $layerType;
                if ($prodName !== '') $parts[] = $prodName;
                elseif ($catName !== '') $parts[] = $catName;
                
                $main = trim(implode(' - ', $parts));
                if ($main === '') continue;
                $countLabel = $layerCount > 0 ? ($layerCount . ' lớp: ') : '';
                $lines[] = $countLabel . $main;
            }
            $stmt->close();

            $summary = trim(implode("\n", $lines));
            $stmtU = $ithanhloc->prepare("UPDATE ecommerce_product SET coating_system = ? WHERE id = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $summary, $productId);
                $stmtU->execute();
                $stmtU->close();
            }
        } catch (Throwable $e) {}
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY ALIASES
 * These aliases ensure that legacy code calling old function names continues to 
 * work correctly after the e-commerce library centralization.
 * ============================================================================
 */

if (!function_exists('getCart')) {
    /** @deprecated Use ecommerce_cart_get() */
    function getCart(): array { return ecommerce_cart_get(); }
}
if (!function_exists('setCart')) {
    /** @deprecated Use ecommerce_cart_set() */
    function setCart(array $cart): void { ecommerce_cart_set($cart); }
}
if (!function_exists('cartCount')) {
    /** @deprecated Use ecommerce_cart_count() */
    function cartCount(): int { return ecommerce_cart_count(); }
}
if (!function_exists('cartAllowedPaymentKeys')) {
    /** @deprecated Use ecommerce_cart_allowed_payment_keys() */
    function cartAllowedPaymentKeys(array $cart): array { return ecommerce_cart_allowed_payment_keys($cart); }
}
if (!function_exists('hydrateCartPaymentOptions')) {
    /** @deprecated Use ecommerce_hydrate_cart_payment_options() */
    function hydrateCartPaymentOptions(mysqli $db, array $cart): array { return ecommerce_hydrate_cart_payment_options($db, $cart); }
}
if (!function_exists('sanitizePaymentKeys')) {
    /** @deprecated Use ecommerce_sanitize_payment_keys() */
    function sanitizePaymentKeys($raw): array { return ecommerce_sanitize_payment_keys($raw); }
}
if (!function_exists('allEnabledPaymentKeys')) {
    /** @deprecated Use ecommerce_all_enabled_payment_keys() */
    function allEnabledPaymentKeys(): array { return ecommerce_all_enabled_payment_keys(); }
}
if (!function_exists('mapPaymentLabels')) {
    /** @deprecated Use ecommerce_map_payment_labels() */
    function mapPaymentLabels(array $keys): array { return ecommerce_map_payment_labels($keys); }
}
if (!function_exists('normalizeProductIdsInput')) {
    /** @deprecated Use normalizeProductIds() */
    function normalizeProductIdsInput($raw): array { return normalizeProductIds($raw); }
}
if (!function_exists('current_user_id')) {
    /** @deprecated Use ecommerce_current_user_id() */
    function current_user_id(): int { return ecommerce_current_user_id(); }
}
if (!function_exists('getEnabledPaymentMethods')) {
    /** @deprecated Use ecommerce_get_enabled_payment_methods() */
    function getEnabledPaymentMethods(): array { return ecommerce_get_enabled_payment_methods(); }
}
if (!function_exists('buildCartItem')) {
    /** @deprecated Use ecommerce_build_cart_item() */
    function buildCartItem(mysqli $db, int $pid, int $vid, int $qty, string $color = ''): ?array { return ecommerce_build_cart_item($db, $pid, $vid, $qty, $color); }
}
if (!function_exists('getShippingMethodLabelMap')) {
    /** @deprecated Use ecommerce_get_shipping_method_label_map() */
    function getShippingMethodLabelMap(mysqli $db): array { return ecommerce_get_shipping_method_label_map($db); }
}

if (!function_exists('product_has_variants')) {
    /** @deprecated Use ecommerce_product_has_variants() */
    function product_has_variants(mysqli $db, string $table, int $pid): bool { return ecommerce_product_has_variants($db, $table, $pid); }
}
if (!function_exists('rebuildProductCoatingSummary')) {
    /** @deprecated Use ecommerce_product_rebuild_coating_summary() */
    function rebuildProductCoatingSummary(mysqli $db, string $table, int $pid): void { ecommerce_product_rebuild_coating_summary($db, $table, $pid); }
}

if (!function_exists('loadUser')) {
    /** @deprecated Use ecommerce_user_load() */
    function loadUser(mysqli $db, int $userId): ?array { return ecommerce_user_load($db, $userId); }
}

if (!function_exists('loadDefaultSavedLocation')) {
    /** @deprecated Use ecommerce_user_get_default_location() */
    function loadDefaultSavedLocation(mysqli $db, int $userId): array { return ecommerce_user_get_default_location($db, $userId); }
}

if (!function_exists('validateVoucher')) {
    /** @deprecated Use ecommerce_voucher_validate() */
    function validateVoucher($ithanhloc, $code, $subtotal, array $productIds = [], string $target = 'order', float $shippingFee = 0, string $paymentMethod = '', string $shippingMethod = '', string $channel = 'web', int $userId = 0, string $mode = 'apply') {
        return ecommerce_voucher_validate($ithanhloc, $code, $subtotal, $productIds, $target, $shippingFee, $paymentMethod, $shippingMethod, $channel, $userId, $mode);
    }
}

if (!function_exists('voucherExists')) {
    /** @deprecated Use ecommerce_voucher_exists() */
    function voucherExists(mysqli $ithanhloc, string $code): bool { return ecommerce_voucher_exists($ithanhloc, $code); }
}

if (!function_exists('normalizeDiscountTarget')) {
    /** @deprecated Use ecommerce_normalize_discount_target() */
    function normalizeDiscountTarget($target): string { return ecommerce_normalize_discount_target($target); }
}

if (!function_exists('normalizeCsvInput')) {
    /** @deprecated Use ecommerce_normalize_csv_input() */
    function normalizeCsvInput($raw): string { return ecommerce_normalize_csv_input($raw); }
}

if (!function_exists('format_voucher_value_label')) {
    /** @deprecated Use ecommerce_voucher_format_label() */
    function format_voucher_value_label($type, $val, $max = null): string { return ecommerce_voucher_format_label($type, $val, $max); }
}

if (!function_exists('nf_build_url')) {
    /**
     * Tạo URL SEO-friendly cho trang xem thông báo.
     * Format: {baseUrl}/view-notification/{slug}-{id}
     * Trích id từ slug khi parse: regex /-(\d+)$/.
     */
    function nf_build_url(int $id, string $title = '', string $baseUrl = ''): string {
        $base = rtrim((string)$baseUrl, '/');
        if ($id <= 0) {
            return $base . '/view-notification';
        }
        $slug = $title !== '' ? pm_slugify($title) : 'thong-bao';
        // Giới hạn độ dài slug để URL không quá dài
        if (strlen($slug) > 80) {
            $slug = substr($slug, 0, 80);
            $slug = rtrim($slug, '-');
        }
        return $base . '/view-notification/' . $slug . '-' . $id;
    }
}

if (!function_exists('nf_extract_id_from_slug')) {
    /** Lấy ID từ slug dạng "bai-viet-a-b-c-4" → 4. */
    function nf_extract_id_from_slug(string $slug): int {
        if (preg_match('/-(\d+)$/', trim($slug), $m)) {
            return (int)$m[1];
        }
        // fallback: chỉ là số
        if (ctype_digit(trim($slug))) {
            return (int)trim($slug);
        }
        return 0;
    }
}

if (!function_exists('app_send_smtp_mail')) {
    /**
     * Gửi email qua SMTP bằng PHPMailer. Helper dùng chung cho OTP, khôi phục mật khẩu, v.v.
     *
     * @param array  $smtp     Cấu hình SMTP ($SMTP_CONFIG): host, username, password, from_email, from_name, port, encryption
     * @param string $toEmail  Email người nhận
     * @param string $subject  Tiêu đề
     * @param string $htmlBody Nội dung HTML (rỗng => gửi plain text dùng $altBody)
     * @param string $altBody  Nội dung text thay thế (bắt buộc khi muốn có bản plain)
     * @return array ['ok' => bool, 'error' => string]
     */
    function app_send_smtp_mail(array $smtp, string $toEmail, string $subject, string $htmlBody, string $altBody): array {
        // Nạp PHPMailer (composer autoload hoặc nạp lớp trực tiếp)
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        } else {
            $base = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
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
            $mailer->Subject = $subject;

            if ($htmlBody !== '') {
                $mailer->isHTML(true);
                $mailer->Body    = $htmlBody;
                $mailer->AltBody = $altBody;
            } else {
                $mailer->Body = $altBody;
            }

            $mailer->send();
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $mailer->ErrorInfo ?: $e->getMessage()];
        }
    }
}

