<?php
/**
 * Hệ thống Ticket Hỗ trợ — hàm dùng chung cho cả frontend & admin.
 *
 * Tái dùng các helper sẵn có của hệ thống (functions.php):
 *   jOut(), h(), clean_input(), clean_email(), clean_phone_digits(),
 *   to_abs_url(), nowStr(), app_user_notify(), app_guest_key(),
 *   app_get_default_admin_user_id().
 *
 * Module tự bootstrap bảng (giống core_admin/blog/ajax.php) để không phụ thuộc
 * vào việc chạy migration thủ công.
 */

if (!defined('SUPPORT_COMMON_LOADED')) {
    define('SUPPORT_COMMON_LOADED', 1);

    // Danh mục & độ ưu tiên & trạng thái hợp lệ ------------------------------
    if (!function_exists('support_categories')) {
        function support_categories(): array {
            return [
                'order'   => 'Sự cố đơn hàng',
                'payment' => 'Lỗi thanh toán',
                'faq'     => 'Câu hỏi thường gặp',
                'chat'    => 'Chat trực tuyến',
                'other'   => 'Khác',
            ];
        }
    }
    if (!function_exists('support_priorities')) {
        function support_priorities(): array {
            return ['high' => 'Cao', 'normal' => 'Thường', 'low' => 'Thấp'];
        }
    }
    if (!function_exists('support_statuses')) {
        function support_statuses(): array {
            return [
                'open'     => 'Đang mở',
                'pending'  => 'Chờ phản hồi',
                'resolved' => 'Đã xử lý',
                'closed'   => 'Đã đóng',
            ];
        }
    }
    if (!function_exists('support_norm_enum')) {
        function support_norm_enum(string $val, array $map, string $default): string {
            $val = strtolower(trim($val));
            return isset($map[$val]) ? $val : $default;
        }
    }

    // Bootstrap bảng nếu chưa có ---------------------------------------------
    if (!function_exists('support_ensure_tables')) {
        function support_ensure_tables(mysqli $db): void {
            static $done = false;
            if ($done) return;
            $done = true;

            $db->query("CREATE TABLE IF NOT EXISTS `support_ticket` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(20) NOT NULL,
                `user_id` INT NOT NULL DEFAULT 0,
                `guest_key` VARCHAR(64) NULL,
                `guest_name` VARCHAR(100) NULL,
                `guest_email` VARCHAR(150) NULL,
                `guest_phone` VARCHAR(30) NULL,
                `order_id` VARCHAR(255) NULL,
                `category` VARCHAR(40) NOT NULL DEFAULT 'other',
                `priority` VARCHAR(10) NOT NULL DEFAULT 'normal',
                `subject` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'open',
                `assignee_id` INT NOT NULL DEFAULT 0,
                `last_reply_at` DATETIME NULL,
                `last_reply_by` VARCHAR(10) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_ticket_code` (`code`),
                KEY `idx_ticket_user` (`user_id`),
                KEY `idx_ticket_status` (`status`),
                KEY `idx_ticket_priority` (`priority`),
                KEY `idx_ticket_guest` (`guest_key`),
                KEY `idx_ticket_order` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->query("CREATE TABLE IF NOT EXISTS `support_ticket_message` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ticket_id` INT UNSIGNED NOT NULL,
                `sender_type` VARCHAR(10) NOT NULL,
                `sender_id` INT NOT NULL DEFAULT 0,
                `content` TEXT NOT NULL,
                `media_json` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_msg_ticket` (`ticket_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->query("CREATE TABLE IF NOT EXISTS `support_faq` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `category` VARCHAR(40) NOT NULL DEFAULT 'general',
                `question` VARCHAR(255) NOT NULL,
                `answer` LONGTEXT NOT NULL,
                `order_index` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_faq_active_order` (`is_active`, `order_index`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    // Sinh mã ticket duy nhất (TK-XXXXXX) ------------------------------------
    if (!function_exists('support_generate_code')) {
        function support_generate_code(mysqli $db): string {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // bỏ ký tự dễ nhầm
            for ($attempt = 0; $attempt < 8; $attempt++) {
                $rand = '';
                for ($i = 0; $i < 6; $i++) {
                    $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $code = 'TK-' . $rand;
                $stmt = $db->prepare('SELECT id FROM support_ticket WHERE code = ? LIMIT 1');
                if (!$stmt) return $code;
                $stmt->bind_param('s', $code);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$exists) return $code;
            }
            return 'TK-' . strtoupper(bin2hex(random_bytes(3)));
        }
    }

    // Thư mục upload đính kèm ------------------------------------------------
    if (!function_exists('support_upload_dir')) {
        function support_upload_dir(): array {
            global $uploadFolder;
            $root   = dirname(__DIR__, 2); // .../htdocs
            $webDir = '/' . ($uploadFolder ?? 'uploads') . '/support';
            $fsDir  = $root . str_replace('/', DIRECTORY_SEPARATOR, $webDir);
            if (!is_dir($fsDir)) {
                @mkdir($fsDir, 0777, true);
            }
            if (!is_dir($fsDir)) {
                return ['ok' => false, 'msg' => 'Không thể tạo thư mục upload hỗ trợ'];
            }
            return ['ok' => true, 'fs' => $fsDir, 'web' => $webDir];
        }
    }

    /**
     * Lưu các file đính kèm từ $_FILES[$fileKey] (hỗ trợ nhiều file).
     * Trả về ['ok'=>bool, 'files'=>['/uploads/support/xxx.jpg', ...], 'msg'=>...].
     */
    if (!function_exists('support_save_attachments')) {
        function support_save_attachments(string $fileKey, int $maxFiles = 5): array {
            if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
                return ['ok' => true, 'files' => []];
            }
            $dir = support_upload_dir();
            if (!$dir['ok']) {
                return ['ok' => false, 'msg' => $dir['msg']];
            }

            $names = (array)($_FILES[$fileKey]['name'] ?? []);
            $tmps  = (array)($_FILES[$fileKey]['tmp_name'] ?? []);
            $errs  = (array)($_FILES[$fileKey]['error'] ?? []);
            $sizes = (array)($_FILES[$fileKey]['size'] ?? []);

            // Chuẩn hóa cho trường hợp 1 file (không phải mảng)
            if (!is_array($_FILES[$fileKey]['name'])) {
                $names = [$_FILES[$fileKey]['name']];
                $tmps  = [$_FILES[$fileKey]['tmp_name']];
                $errs  = [$_FILES[$fileKey]['error']];
                $sizes = [$_FILES[$fileKey]['size']];
            }

            $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $saved = [];
            $count = 0;

            foreach ($names as $idx => $origName) {
                if ($count >= $maxFiles) break;
                $err = (int)($errs[$idx] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) {
                    return ['ok' => false, 'msg' => 'Tải ảnh thất bại'];
                }
                $size = (int)($sizes[$idx] ?? 0);
                if ($size <= 0 || $size > 12 * 1024 * 1024) {
                    return ['ok' => false, 'msg' => 'Mỗi ảnh tối đa 12MB'];
                }
                $tmp = (string)($tmps[$idx] ?? '');
                if ($tmp === '' || !is_uploaded_file($tmp)) {
                    return ['ok' => false, 'msg' => 'Tệp upload không hợp lệ'];
                }
                $ext = strtolower(pathinfo((string)$origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowExt, true)) {
                    return ['ok' => false, 'msg' => 'Chỉ hỗ trợ ảnh jpg, jpeg, png, webp, gif'];
                }
                // BẢO MẬT: kiểm tra NỘI DUNG thực sự là ảnh (không chỉ tin đuôi file) để
                // chặn upload file thực thi (php/html) đội lốt ảnh.
                $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
                $imgInfo = @getimagesize($tmp);
                if ($imgInfo === false || !in_array((int)($imgInfo[2] ?? 0), $allowedImageTypes, true)) {
                    return ['ok' => false, 'msg' => 'Tệp tải lên không phải ảnh hợp lệ.'];
                }
                $fileName = 'support_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destFs   = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
                if (!@move_uploaded_file($tmp, $destFs)) {
                    return ['ok' => false, 'msg' => 'Không thể lưu ảnh tải lên'];
                }
                // Tái dùng chuyển WebP của blog nếu đã nạp; nếu không thì giữ nguyên.
                if (function_exists('convertImageToWebpIfPossible')) {
                    $supportRel = convertImageToWebpIfPossible($destFs, $dir['web']);
                } else {
                    $supportRel = $dir['web'] . '/' . $fileName;
                }
                if (function_exists('media_publish_local_file')) {
                    media_publish_local_file($supportRel);
                }
                $saved[] = $supportRel;
                $count++;
            }
            return ['ok' => true, 'files' => $saved];
        }
    }

    // Chuẩn hóa media_json thành mảng URL tuyệt đối -------------------------
    if (!function_exists('support_media_urls')) {
        function support_media_urls(?string $mediaJson, string $baseUrl): array {
            $raw = trim((string)$mediaJson);
            if ($raw === '') return [];
            $arr = json_decode($raw, true);
            if (!is_array($arr)) return [];
            $out = [];
            foreach ($arr as $p) {
                $p = trim((string)$p);
                if ($p === '') continue;
                $out[] = function_exists('to_abs_url') ? to_abs_url($p, $baseUrl) : $p;
            }
            return $out;
        }
    }

    /**
     * Render nội dung 1 tin nhắn (xử lý marker [[PMCARD:type]]{json} thành card HTML gọn).
     * Nếu không có marker → trả text đã escape (giữ xuống dòng).
     * Dùng inline style để không phụ thuộc CSS của trang hiển thị.
     */
    if (!function_exists('support_render_message_content')) {
        function support_render_message_content(string $content): string {
            $content = (string)$content;
            if (strpos($content, '[[PMCARD:') !== 0
                || !preg_match('/^\[\[PMCARD:(product|voucher)\]\](\{.*?\})(?:\n(.*))?$/s', $content, $mt)) {
                return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
            }
            $type = $mt[1];
            $data = json_decode($mt[2], true);
            $extra = isset($mt[3]) ? trim($mt[3]) : '';
            if (!is_array($data)) {
                return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
            }
            $esc = static function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

            $html = '';
            if ($type === 'product') {
                $img = trim((string)($data['img'] ?? ''));
                $html = '<div style="display:flex;gap:10px;max-width:300px;border:1px solid #e2e8f0;border-radius:12px;padding:8px;background:#fff;">'
                    . ($img !== '' ? '<div style="width:60px;height:60px;flex:0 0 60px;border-radius:8px;overflow:hidden;background:#f1f5f9;"><img src="' . $esc($img) . '" alt="" style="width:100%;height:100%;object-fit:cover;"></div>' : '')
                    . '<div style="min-width:0;">'
                    . (!empty($data['cat']) ? '<div style="font-size:.68rem;color:#94a3b8;">' . $esc($data['cat']) . '</div>' : '')
                    . '<div style="font-size:.85rem;font-weight:700;color:#0f172a;">' . $esc($data['name'] ?? '') . '</div>'
                    . '<div style="font-size:.85rem;font-weight:700;color:#dc2626;">' . $esc($data['price'] ?? '') . '</div>'
                    . '</div></div>';
            } else { // voucher
                $accent = '#ee4d2d';
                switch ((string)($data['variant'] ?? 'order')) {
                    case 'ship': $accent = '#26aa99'; break;
                    case 'payment': $accent = '#16a34a'; break;
                    case 'category': $accent = '#ea580c'; break;
                    case 'all': $accent = '#7c3aed'; break;
                }
                $iconClass = $esc($data['icon'] ?? 'bi-percent');
                $html = '<div style="display:flex;align-items:center;gap:10px;max-width:300px;background:#fff;border:1px solid #e5e7eb;border-left:4px solid ' . $accent . ';border-radius:10px;padding:8px 10px;">'
                    . '<span style="flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:' . $accent . ';color:#fff;font-size:.85rem;"><i class="bi ' . $iconClass . '"></i></span>'
                    . '<div style="min-width:0;">'
                    . '<div style="font-size:.8rem;font-weight:800;color:#111827;line-height:1.25;">' . $esc($data['title'] ?? $data['label'] ?? '') . '</div>'
                    . '<div style="font-size:.72rem;color:#374151;">Mã: <b>' . $esc($data['code'] ?? '') . '</b></div>'
                    . '<div style="font-size:.68rem;color:#6b7280;">' . $esc($data['min'] ?? 'Áp dụng mọi đơn') . '</div>'
                    . (!empty($data['exp']) ? '<div style="font-size:.66rem;color:#ef4444;">' . $esc($data['exp']) . '</div>' : '')
                    . '</div></div>';
            }
            if ($extra !== '') {
                $html .= '<div style="margin-top:6px;">' . nl2br($esc($extra)) . '</div>';
            }
            return $html;
        }
    }

    // ===== Helper card gợi ý SP/voucher (dùng chung cho chat.php & ticket.php) =====

    /** Đổi đường dẫn ảnh sản phẩm sang URL tuyệt đối (qua media domain nếu có). */
    if (!function_exists('support_card_img')) {
        function support_card_img(string $img, string $baseUrl): string {
            $img = trim($img);
            if ($img === '') return '';
            return function_exists('to_abs_url') ? to_abs_url($img, $baseUrl) : $img;
        }
    }

    /** Lấy dữ liệu 1 sản phẩm cho card (id, tên, ảnh, giá tối thiểu, danh mục). Null nếu không có. */
    if (!function_exists('support_fetch_product_card')) {
        function support_fetch_product_card(mysqli $db, int $pid, string $baseUrl): ?array {
            if ($pid <= 0) return null;
            $sql = "SELECT p.id, p.product_name, p.image_url,
                           (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) AS min_price,
                           (SELECT c.name FROM ecommerce_category c WHERE c.id = p.category_id LIMIT 1) AS cat_name
                    FROM ecommerce_product p WHERE p.id = ? LIMIT 1";
            $stmt = $db->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$r) return null;

            $price = (float)($r['min_price'] ?? 0);
            $priceText = $price > 0
                ? (function_exists('formatMoney') ? formatMoney($price) . 'đ' : number_format($price, 0, ',', '.') . 'đ')
                : 'Liên hệ';
            return [
                'id'    => (int)$r['id'],
                'name'  => (string)($r['product_name'] ?? ''),
                'price' => $priceText,
                'img'   => support_card_img(trim((string)($r['image_url'] ?? '')), $baseUrl),
                'cat'   => (string)($r['cat_name'] ?? ''),
            ];
        }
    }

    /** Lấy dữ liệu 1 voucher (theo code) cho card. Null nếu không tồn tại / không active. */
    if (!function_exists('support_fetch_voucher_card')) {
        function support_fetch_voucher_card(mysqli $db, string $code): ?array {
            $code = strtoupper(trim($code));
            if ($code === '') return null;
            $stmt = $db->prepare("SELECT * FROM ecommerce_voucher WHERE UPPER(code) = ? AND CAST(is_active AS UNSIGNED) = 1 LIMIT 1");
            if (!$stmt) return null;
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $v = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$v) return null;
            return support_voucher_to_card($v);
        }
    }

    /** Chuẩn hoá 1 row voucher thành payload card (variant màu, brand, icon, tiêu đề, hạn dùng). */
    if (!function_exists('support_voucher_to_card')) {
        function support_voucher_to_card(array $v): array {
            $code = strtoupper(trim((string)($v['code'] ?? '')));
            $unit = strtolower((string)($v['value_unit'] ?? 'fixed'));
            $value = (float)($v['value'] ?? 0);

            $tplKey = function_exists('ecommerce_voucher_determine_template_key')
                ? ecommerce_voucher_determine_template_key($v) : 'order_discount';
            $map = [
                'order_discount'         => ['variant' => 'order',    'brand' => 'Giảm giá',    'icon' => 'bi-percent'],
                'shipping_discount'      => ['variant' => 'ship',     'brand' => 'Vận chuyển',  'icon' => 'bi-truck'],
                'payment_discount'       => ['variant' => 'payment',  'brand' => 'Thanh toán',  'icon' => 'bi-credit-card-2-front'],
                'category_discount'      => ['variant' => 'all',      'brand' => 'Toàn ngành',  'icon' => 'bi-collection'],
                'only_category_discount' => ['variant' => 'category', 'brand' => 'Ngành hàng',  'icon' => 'bi-grid-3x3-gap'],
            ];
            $meta = $map[$tplKey] ?? $map['order_discount'];
            $primaryTarget = ($meta['variant'] === 'ship') ? 'shipping' : 'order';

            $onText = $primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng';
            if ($unit === 'percent') {
                $title = 'Mã giảm ' . rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '% trên ' . $onText;
            } else {
                $title = 'Mã giảm ' . (function_exists('formatMoney') ? formatMoney($value) . 'đ' : number_format($value, 0, ',', '.') . 'đ') . ' trên ' . $onText;
            }

            $label = function_exists('ecommerce_voucher_format_label')
                ? ecommerce_voucher_format_label($unit, $value, $v['max_discount'] ?? null)
                : ((string)$value . ($unit === 'percent' ? '%' : 'đ'));

            $minRaw = (float)($v['min_subtotal'] ?? 0);
            $minUnit = strtolower((string)($v['min_subtotal_unit'] ?? 'fixed'));
            $minText = $minRaw <= 0 ? 'Áp dụng mọi đơn'
                : ($minUnit === 'percent'
                    ? ('Đơn tối thiểu ' . rtrim(rtrim(number_format($minRaw, 1, '.', ''), '0'), '.') . '%')
                    : ('Đơn tối thiểu ' . (function_exists('formatMoney') ? formatMoney($minRaw) . 'đ' : number_format($minRaw, 0, ',', '.') . 'đ')));

            $exp = '';
            $endRaw = trim((string)($v['end_at'] ?? ''));
            if ($endRaw !== '' && $endRaw !== '0000-00-00 00:00:00' && $endRaw !== '0000-00-00') {
                $tsEnd = strtotime($endRaw);
                if ($tsEnd) {
                    $exp = (time() > $tsEnd) ? 'Đã hết hạn' : ('Hạn sử dụng đến: ' . date('d/m/Y', $tsEnd));
                }
            }

            return [
                'code' => $code, 'label' => $label, 'title' => $title,
                'brand' => $meta['brand'], 'icon' => $meta['icon'], 'variant' => $meta['variant'],
                'min' => $minText, 'target' => $primaryTarget, 'exp' => $exp,
            ];
        }
    }

    /**
     * Build content có marker card từ input admin (card_type + card_id/voucher_code) + text kèm theo.
     * Trả [content, hasCard, cardType] hoặc ném lỗi qua jOut nếu SP/voucher không tồn tại.
     * $content: text đã clean_input. Marker được build SAU clean (không bị strip).
     */
    if (!function_exists('support_apply_card_to_content')) {
        function support_apply_card_to_content(mysqli $db, string $content, string $baseUrl): array {
            $cardType = strtolower(trim((string)($_POST['card_type'] ?? '')));
            if ($cardType !== 'product' && $cardType !== 'voucher') {
                return [$content, false, ''];
            }
            if ($cardType === 'product') {
                $card = support_fetch_product_card($db, (int)($_POST['card_id'] ?? 0), $baseUrl);
                if (!$card) jOut(['ok' => false, 'msg' => 'Sản phẩm không tồn tại.']);
                $marker = '[[PMCARD:product]]' . json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $card = support_fetch_voucher_card($db, (string)($_POST['voucher_code'] ?? ''));
                if (!$card) jOut(['ok' => false, 'msg' => 'Mã ưu đãi không tồn tại hoặc đã tắt.']);
                $marker = '[[PMCARD:voucher]]' . json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $newContent = $content !== '' ? ($marker . "\n" . $content) : $marker;
            return [$newContent, true, $cardType];
        }
    }

    /** Đổi content có marker thành snippet ngắn cho danh sách/thông báo. */
    if (!function_exists('support_card_snippet')) {
        function support_card_snippet(string $content): string {
            if (strpos($content, '[[PMCARD:') !== 0) return $content;
            if (preg_match('/^\[\[PMCARD:(product|voucher)\]\](\{.*?\})(?:\n(.*))?$/s', $content, $m)) {
                $data = json_decode($m[2], true);
                $extra = isset($m[3]) ? trim($m[3]) : '';
                if ($m[1] === 'product') {
                    $name = is_array($data) ? (string)($data['name'] ?? '') : '';
                    $label = '🛍️ Gợi ý: ' . ($name !== '' ? $name : 'sản phẩm');
                } else {
                    $code = is_array($data) ? (string)($data['code'] ?? '') : '';
                    $label = '🎟️ Mã ưu đãi' . ($code !== '' ? ': ' . $code : '');
                }
                return $extra !== '' ? ($label . ' — ' . $extra) : $label;
            }
            return $content;
        }
    }

    /** Output JSON cho 3 action suggest dùng chung. $kind: products|categories|vouchers. */
    if (!function_exists('support_handle_suggest')) {
        function support_handle_suggest(mysqli $db, string $kind, string $baseUrl): void {
            if ($kind === 'categories') {
                $res = $db->query("SELECT c.id, c.name FROM ecommerce_category c
                    WHERE EXISTS (SELECT 1 FROM ecommerce_product p WHERE p.category_id = c.id) ORDER BY c.id ASC");
                $data = [];
                if ($res) { while ($r = $res->fetch_assoc()) { $data[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']]; } }
                jOut(['ok' => true, 'data' => $data]);
            }
            if ($kind === 'vouchers') {
                $now = function_exists('nowStr') ? nowStr() : date('Y-m-d H:i:s');
                $rows = function_exists('ecommerce_voucher_fetch_active_list') ? ecommerce_voucher_fetch_active_list($db, $now) : [];
                $data = [];
                foreach ($rows as $v) { if (is_array($v)) $data[] = support_voucher_to_card($v); }
                jOut(['ok' => true, 'data' => $data]);
            }
            // products
            $q = trim((string)($_GET['q'] ?? ''));
            $catId = (int)($_GET['cat_id'] ?? 0);
            $limit = (int)($_GET['limit'] ?? 12);
            if ($limit <= 0 || $limit > 30) $limit = 12;
            $conds = []; $types = ''; $params = [];
            if ($q !== '') { $conds[] = "(p.product_name LIKE ? OR p.sku LIKE ?)"; $like = '%' . $q . '%'; $types .= 'ss'; $params[] = $like; $params[] = $like; }
            if ($catId > 0) { $conds[] = "p.category_id = ?"; $types .= 'i'; $params[] = $catId; }
            $whereSQL = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
            $sql = "SELECT p.id, p.product_name, p.image_url,
                       (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) AS min_price,
                       (SELECT c.name FROM ecommerce_category c WHERE c.id = p.category_id LIMIT 1) AS cat_name
                    FROM ecommerce_product p {$whereSQL} ORDER BY p.id DESC LIMIT {$limit}";
            $stmt = $db->prepare($sql);
            if (!$stmt) jOut(['ok' => false, 'msg' => 'Lỗi truy vấn sản phẩm.']);
            if ($types !== '') { $stmt->bind_param($types, ...$params); }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $data = [];
            foreach ($rows as $r) {
                $price = (float)($r['min_price'] ?? 0);
                $priceText = $price > 0
                    ? (function_exists('formatMoney') ? formatMoney($price) . 'đ' : number_format($price, 0, ',', '.') . 'đ')
                    : 'Liên hệ';
                $data[] = [
                    'id' => (int)$r['id'], 'name' => (string)($r['product_name'] ?? ''),
                    'price' => $priceText, 'img' => support_card_img(trim((string)($r['image_url'] ?? '')), $baseUrl),
                    'cat' => (string)($r['cat_name'] ?? ''),
                ];
            }
            jOut(['ok' => true, 'data' => $data]);
        }
    }

    /**
     * Dọn dẹp dữ liệu liên quan khi xoá (soft-delete) một/nhiều ticket.
     * - Ẩn (is_active=0) các tin nhắn của ticket.
     * - Ẩn (is_active=0) các thông báo chuông (user_notification) trỏ tới ticket
     *   — nhận diện qua `link` chứa mã ticket (TK-XXXXXX), khớp cả link user
     *     (/support-detail?code=...) lẫn admin (/admin/support-ticket?code=...).
     *
     * $ticketIds: danh sách id ticket đã bị xoá.
     * Trả về số ticket đã xử lý lookup được mã.
     */
    if (!function_exists('support_cleanup_deleted_tickets')) {
        function support_cleanup_deleted_tickets(mysqli $db, array $ticketIds): int {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ticketIds), static fn($v) => $v > 0)));
            if (!$ids) return 0;
            $place = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            // 1. Ẩn tin nhắn của các ticket này
            $stmt = $db->prepare("UPDATE support_ticket_message SET is_active = 0 WHERE ticket_id IN ($place)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
            }

            // 2. Lấy mã các ticket để khớp thông báo qua link
            $codes = [];
            $stmt = $db->prepare("SELECT code FROM support_ticket WHERE id IN ($place)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $c = trim((string)($r['code'] ?? ''));
                    if ($c !== '') $codes[] = $c;
                }
                $stmt->close();
            }
            if (!$codes) return 0;

            // 3. Ẩn các thông báo có link chứa "code=<mã>"
            foreach ($codes as $code) {
                $stmt = $db->prepare("UPDATE user_notification SET is_active = 0 WHERE link LIKE ?");
                if (!$stmt) continue;
                $like = '%code=' . $code . '%';
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $stmt->close();
            }
            return count($codes);
        }
    }

    // Thêm 1 tin nhắn vào ticket + cập nhật last_reply ----------------------
    if (!function_exists('support_add_message')) {
        function support_add_message(mysqli $db, int $ticketId, string $senderType, int $senderId, string $content, array $mediaFiles = []): int {
            $senderType = in_array($senderType, ['user', 'admin', 'system'], true) ? $senderType : 'user';
            $mediaJson = $mediaFiles ? json_encode(array_values($mediaFiles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt = $db->prepare('INSERT INTO support_ticket_message (ticket_id, sender_type, sender_id, content, media_json) VALUES (?, ?, ?, ?, ?)');
            if (!$stmt) return 0;
            $stmt->bind_param('isiss', $ticketId, $senderType, $senderId, $content, $mediaJson);
            $stmt->execute();
            $msgId = (int)$stmt->insert_id;
            $stmt->close();

            $now = function_exists('nowStr') ? nowStr() : date('Y-m-d H:i:s');
            $replyBy = ($senderType === 'admin') ? 'admin' : 'user';
            $upd = $db->prepare('UPDATE support_ticket SET last_reply_at = ?, last_reply_by = ?, updated_at = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('sssi', $now, $replyBy, $now, $ticketId);
                $upd->execute();
                $upd->close();
            }
            return $msgId;
        }
    }

    /**
     * Trả về payload 1 tin nhắn (dùng cho AJAX chèn vào luồng chat, không reload).
     */
    if (!function_exists('support_message_payload')) {
        function support_message_payload(mysqli $db, int $msgId, string $baseUrl): array {
            $stmt = $db->prepare('SELECT * FROM support_ticket_message WHERE id = ? LIMIT 1');
            if (!$stmt) return [];
            $stmt->bind_param('i', $msgId);
            $stmt->execute();
            $m = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$m) return [];
            return [
                'id'          => (int)$m['id'],
                'sender_type' => (string)$m['sender_type'],
                'content'     => (string)$m['content'],
                'media'       => support_media_urls($m['media_json'] ?? '', $baseUrl),
                'time'        => date('H:i d/m/Y', strtotime((string)$m['created_at'])),
            ];
        }
    }

    /**
     * Thông báo cho người tạo ticket khi admin phản hồi.
     * - Nếu là user đăng nhập: tạo bản ghi user_notification (chuông/sidebar).
     * - Gửi ZNS Zalo nếu bật (stub an toàn, xem support_send_zns).
     */
    if (!function_exists('support_notify_user')) {
        function support_notify_user(mysqli $db, array $ticket, string $snippet): void {
            $userId = (int)($ticket['user_id'] ?? 0);
            $code   = (string)($ticket['code'] ?? '');
            $subject = (string)($ticket['subject'] ?? '');
            $link = '/support-detail?code=' . rawurlencode($code);

            if ($userId > 0 && function_exists('app_user_notify')) {
                $title = 'Phản hồi hỗ trợ • ' . $code;
                $body  = 'Yêu cầu "' . $subject . '" vừa được nhân viên phản hồi: ' . mb_substr($snippet, 0, 140);
                app_user_notify($db, $userId, $title, $body, 'complaint', $link, [
                    'module'     => 'support',
                    'event'      => 'reply',
                    'category'   => 'support',
                    'thumb_icon' => 'bi bi-life-preserver',
                ]);
            }

            // Zalo ZNS (tùy chọn — chỉ chạy khi đã bật & có template)
            $phone = trim((string)($ticket['guest_phone'] ?? ''));
            if ($phone === '' && function_exists('ecommerce_user_load') && $userId > 0) {
                $u = ecommerce_user_load($db, $userId);
                $phone = trim((string)($u['phone'] ?? ''));
            }
            if ($phone !== '') {
                support_send_zns($db, $phone, $code, $subject, $snippet);
            }
        }
    }

    /**
     * Gửi thông báo ticket qua Zalo ZNS.
     *
     * LƯU Ý: cần một ZNS template riêng cho hỗ trợ được Zalo duyệt và token OA
     * hợp lệ (cấu hình trong module core/zns). Hiện để dạng tùy chọn, mặc định
     * TẮT để không gây lỗi luồng chính. Bật bằng biến môi trường:
     *   SUPPORT_ZNS_ENABLED=1
     *   SUPPORT_ZNS_TEMPLATE_ID=<id template đã duyệt>
     */
    if (!function_exists('support_send_zns')) {
        function support_send_zns(mysqli $db, string $phone, string $code, string $subject, string $snippet): bool {
            $enabled = in_array(strtolower((string)(getenv('SUPPORT_ZNS_ENABLED') ?: '0')), ['1', 'true', 'yes', 'on'], true);
            if (!$enabled) {
                return false; // no-op khi chưa bật
            }
            $templateId = trim((string)(getenv('SUPPORT_ZNS_TEMPLATE_ID') ?: ''));
            if ($templateId === '' || !function_exists('zalo_call_api_otp')) {
                @error_log('[support-zns] Bỏ qua: thiếu template hoặc hàm gửi ZNS.');
                return false;
            }
            // Lấy access token OA từ cấu hình ZNS sẵn có (nếu module cung cấp).
            $token = function_exists('zns_get_access_token') ? (string)zns_get_access_token($db) : '';
            if ($token === '') {
                @error_log('[support-zns] Bỏ qua: chưa có access token Zalo OA.');
                return false;
            }
            $phoneDigits = function_exists('clean_phone_digits') ? clean_phone_digits($phone) : preg_replace('/\D+/', '', $phone);
            if (strpos($phoneDigits, '0') === 0) {
                $phoneDigits = '84' . substr($phoneDigits, 1);
            }
            $payload = [
                'phone'       => $phoneDigits,
                'template_id' => $templateId,
                'template_data' => [
                    'code'    => $code,
                    'subject' => mb_substr($subject, 0, 60),
                    'snippet' => mb_substr($snippet, 0, 100),
                ],
            ];
            try {
                $resp = zalo_call_api_otp('https://business.openapi.zalo.me/message/template', $token, $payload);
                @error_log('[support-zns] Gửi ' . $code . ' tới ' . $phoneDigits . ': ' . json_encode($resp, JSON_UNESCAPED_UNICODE));
                return true;
            } catch (Throwable $e) {
                @error_log('[support-zns] Lỗi gửi: ' . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Thông báo cho admin khi có ticket / phản hồi mới từ khách.
     */
    if (!function_exists('support_notify_admin')) {
        function support_notify_admin(mysqli $db, array $ticket, string $snippet, string $event = 'new'): void {
            if (!function_exists('app_user_notify')) return;
            $adminId = function_exists('app_get_default_admin_user_id') ? app_get_default_admin_user_id($db) : 0;
            if ($adminId <= 0) return;
            $code = (string)($ticket['code'] ?? '');
            $subject = (string)($ticket['subject'] ?? '');
            $title = ($event === 'new' ? 'Ticket mới • ' : 'Khách phản hồi • ') . $code;
            $body  = '"' . $subject . '" — ' . mb_substr($snippet, 0, 140);
            app_user_notify($db, $adminId, $title, $body, 'complaint', '/admin/support-ticket?code=' . rawurlencode($code), [
                'module'     => 'support',
                'event'      => $event,
                'category'   => 'support',
                'thumb_icon' => 'bi bi-life-preserver',
            ]);
        }
    }

    // ====================================================================
    // CHAT TRỰC TUYẾN 24/7 — tái dùng support_ticket với category='chat'.
    // Mỗi khách/user chỉ có 1 phiên chat ĐANG MỞ (status != 'closed').
    // ====================================================================

    /**
     * Lấy phiên chat đang mở của user/khách; nếu chưa có thì tạo mới.
     * $guest: ['name'=>..., 'phone'=>..., 'email'=>..., 'key'=>...] (cho khách vãng lai).
     * Trả về bản ghi ticket (array) hoặc [] nếu lỗi.
     */
    if (!function_exists('support_get_or_create_chat')) {
        function support_get_or_create_chat(mysqli $db, int $userId, array $guest = []): array {
            support_ensure_tables($db);

            // 1. Tìm phiên đang mở
            if ($userId > 0) {
                $stmt = $db->prepare("SELECT * FROM support_ticket WHERE category='chat' AND user_id=? AND status<>'closed' AND is_active=1 ORDER BY id DESC LIMIT 1");
                $stmt->bind_param('i', $userId);
            } else {
                $gKey = (string)($guest['key'] ?? '');
                if ($gKey === '') return [];
                $stmt = $db->prepare("SELECT * FROM support_ticket WHERE category='chat' AND user_id=0 AND guest_key=? AND status<>'closed' AND is_active=1 ORDER BY id DESC LIMIT 1");
                $stmt->bind_param('s', $gKey);
            }
            if ($stmt) {
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($existing) return $existing;
            }

            // 2. Chưa có → tạo phiên mới
            $code = support_generate_code($db);
            $subject = 'Chat trực tuyến';
            $guestName  = $userId > 0 ? null : (trim((string)($guest['name'] ?? '')) ?: null);
            $guestPhone = $userId > 0 ? null : (trim((string)($guest['phone'] ?? '')) ?: null);
            $guestEmail = $userId > 0 ? null : (trim((string)($guest['email'] ?? '')) ?: null);
            $guestKey   = $userId > 0 ? null : (trim((string)($guest['key'] ?? '')) ?: null);

            // bind: code(s) user_id(i) guest_key(s) guest_name(s) guest_email(s) guest_phone(s) subject(s)
            $stmt = $db->prepare("INSERT INTO support_ticket
                (code, user_id, guest_key, guest_name, guest_email, guest_phone, category, priority, subject, status, last_reply_at, last_reply_by)
                VALUES (?, ?, ?, ?, ?, ?, 'chat', 'high', ?, 'open', NOW(), 'user')");
            if (!$stmt) return [];
            $stmt->bind_param('sisssss', $code, $userId, $guestKey, $guestName, $guestEmail, $guestPhone, $subject);
            if (!$stmt->execute()) { $stmt->close(); return []; }
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            // Luôn thêm tin nhắn chào hỏi từ admin khi bắt đầu cuộc trò chuyện
            support_add_message($db, $newId, 'admin', 0, 'Paint&More xin chào! Quý khách cần chúng tôi hỗ trợ gì ạ?');

            $stmt = $db->prepare('SELECT * FROM support_ticket WHERE id=? LIMIT 1');
            if (!$stmt) return [];
            $stmt->bind_param('i', $newId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: [];
        }
    }

    /**
     * Lấy danh sách message của 1 phiên có id > $afterId (phục vụ polling delta).
     * Trả mảng payload [{id, sender_type, content, media[], time}, ...] theo thứ tự tăng dần.
     */
    if (!function_exists('support_fetch_messages')) {
        function support_fetch_messages(mysqli $db, int $ticketId, int $afterId, string $baseUrl): array {
            $out = [];
            $stmt = $db->prepare('SELECT id, sender_type, content, media_json, created_at
                FROM support_ticket_message
                WHERE ticket_id = ? AND id > ? AND is_active = 1
                ORDER BY id ASC LIMIT 200');
            if (!$stmt) return $out;
            $stmt->bind_param('ii', $ticketId, $afterId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($m = $res->fetch_assoc()) {
                $out[] = [
                    'id'          => (int)$m['id'],
                    'sender_type' => (string)$m['sender_type'],
                    'content'     => (string)$m['content'],
                    'media'       => support_media_urls($m['media_json'] ?? '', $baseUrl),
                    'time'        => date('H:i d/m/Y', strtotime((string)$m['created_at'])),
                ];
            }
            $stmt->close();
            return $out;
        }
    }
}
