<?php
/**
 * AJAX hỗ trợ (frontend - user & khách vãng lai).
 * Actions: create | reply | lookup | my_orders
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../core/support/support_common.php';

// Tái dùng tiện ích chuyển ảnh WebP của blog (nếu chưa nạp)
$__blogAjax = __DIR__ . '/../../../core_admin/blog/ajax.php';
// KHÔNG include blog ajax (nó tự exit theo guard admin); support_save_attachments
// đã có fallback nếu convertImageToWebpIfPossible() không tồn tại.

support_ensure_tables($ithanhloc);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));

$curUserId = (int)($_SESSION['user_id'] ?? 0);
$isLogged  = $curUserId > 0;

// Action ghi BẮT BUỘC qua POST — chặn gọi trực tiếp trên URL (GET).
$writeActions = ['create', 'reply', 'close'];
if (in_array($action, $writeActions, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ: hãy gửi qua nút trên giao diện.'], 405);
    }
    if (function_exists('app_verify_csrf')) app_verify_csrf();

    // ── Rate Limiting ─────────────────────────────────────────────────────────
    // Tạo ticket mới: tối đa 2 ticket/15 phút/IP (chống spam ticket rác)
    if ($action === 'create' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'ticket_create',
            2,   // tối đa 2 lần tạo ticket
            900, // trong 15 phút (900 giây)
            'Bạn đã gửi quá nhiều yêu cầu hỗ trợ. Vui lòng chờ 15 phút rồi thử lại.'
        );
    }
    // Trả lời ticket: tối đa 10 lần/phút/IP (chống spam reply)
    if ($action === 'reply' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'ticket_reply',
            10, // tối đa 10 lần reply
            60, // trong 60 giây
            'Bạn đang phản hồi quá nhanh. Vui lòng chờ vài giây rồi thử lại.'
        );
    }
    // ─────────────────────────────────────────────────────────────────────────
}

/**
 * Quyền truy cập 1 ticket: chủ sở hữu (user_id) hoặc khách có guest_key khớp,
 * hoặc khách cung cấp đúng code + số điện thoại.
 */
function support_fetch_owned_ticket(mysqli $db, int $userId): ?array {
    $code = strtoupper(trim((string)($_REQUEST['code'] ?? '')));
    $id   = (int)($_REQUEST['ticket_id'] ?? 0);
    if ($code === '' && $id <= 0) return null;

    if ($code !== '') {
        $stmt = $db->prepare('SELECT * FROM support_ticket WHERE code = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('s', $code);
    } else {
        $stmt = $db->prepare('SELECT * FROM support_ticket WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('i', $id);
    }
    if (!$stmt) return null;
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) return null;

    // Chủ sở hữu là user đăng nhập
    if ($userId > 0 && (int)$t['user_id'] === $userId) return $t;

    // Khách: khớp guest_key cookie
    $guestKey = function_exists('app_guest_key') ? app_guest_key() : '';
    if (!empty($t['guest_key']) && $guestKey !== '' && hash_equals((string)$t['guest_key'], $guestKey)) {
        return $t;
    }

    // Khách: khớp code + số điện thoại
    $phone = function_exists('clean_phone_digits') ? clean_phone_digits((string)($_REQUEST['phone'] ?? '')) : '';
    if ($phone !== '' && !empty($t['guest_phone'])) {
        $tp = function_exists('clean_phone_digits') ? clean_phone_digits((string)$t['guest_phone']) : (string)$t['guest_phone'];
        if ($tp !== '' && hash_equals($tp, $phone)) return $t;
    }
    return null;
}

// ===== Tạo ticket mới =====================================================
if ($action === 'create') {
    $subject  = function_exists('clean_input') ? clean_input($_POST['subject'] ?? '', 200) : trim((string)($_POST['subject'] ?? ''));
    $content  = function_exists('clean_input') ? clean_input($_POST['content'] ?? '', 5000, true) : trim((string)($_POST['content'] ?? ''));
    $category = support_norm_enum((string)($_POST['category'] ?? 'other'), support_categories(), 'other');
    $priority = support_norm_enum((string)($_POST['priority'] ?? 'normal'), support_priorities(), 'normal');
    $orderId  = trim((string)($_POST['order_id'] ?? ''));

    if ($subject === '' || $content === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập tiêu đề và nội dung yêu cầu.']);
    }
    if (mb_strlen($subject) < 5) {
        jOut(['ok' => false, 'msg' => 'Tiêu đề quá ngắn (tối thiểu 5 ký tự).']);
    }

    // Thông tin người gửi
    $guestName = $guestEmail = $guestPhone = null;
    $guestKey = null;
    if (!$isLogged) {
        $guestName  = function_exists('clean_input') ? clean_input($_POST['guest_name'] ?? '', 100) : trim((string)($_POST['guest_name'] ?? ''));
        $guestEmail = function_exists('clean_email') ? clean_email($_POST['guest_email'] ?? '') : trim((string)($_POST['guest_email'] ?? ''));
        $guestPhone = function_exists('clean_phone_digits') ? clean_phone_digits($_POST['guest_phone'] ?? '') : trim((string)($_POST['guest_phone'] ?? ''));
        if ($guestName === '' || $guestPhone === '') {
            jOut(['ok' => false, 'msg' => 'Khách vãng lai vui lòng nhập Họ tên và Số điện thoại để chúng tôi liên hệ.']);
        }
        $guestKey = function_exists('app_guest_key') ? app_guest_key() : bin2hex(random_bytes(16));
    }

    // Ảnh đính kèm
    $attach = support_save_attachments('attachments', 5);
    if (!$attach['ok']) {
        jOut(['ok' => false, 'msg' => $attach['msg'] ?? 'Lỗi tải ảnh.']);
    }
    $files = $attach['files'] ?? [];

    $code = support_generate_code($ithanhloc);
    $orderIdVal = $orderId !== '' ? $orderId : null;

    $stmt = $ithanhloc->prepare('INSERT INTO support_ticket
        (code, user_id, guest_key, guest_name, guest_email, guest_phone, order_id, category, priority, subject, status, last_reply_at, last_reply_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "open", NOW(), "user")');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể tạo yêu cầu (DB).']);
    }
    $uid = $isLogged ? $curUserId : 0;
    $stmt->bind_param(
        'sissssssss',
        $code, $uid, $guestKey, $guestName, $guestEmail, $guestPhone, $orderIdVal, $category, $priority, $subject
    );
    if (!$stmt->execute()) {
        $stmt->close();
        jOut(['ok' => false, 'msg' => 'Không thể tạo yêu cầu (execute).']);
    }
    $ticketId = (int)$stmt->insert_id;
    $stmt->close();

    // Tin nhắn đầu tiên = nội dung yêu cầu
    support_add_message($ithanhloc, $ticketId, 'user', $uid, $content, $files);

    $ticket = [
        'id' => $ticketId, 'code' => $code, 'user_id' => $uid,
        'guest_phone' => $guestPhone, 'subject' => $subject,
    ];
    support_notify_admin($ithanhloc, $ticket, $content, 'new');

    jOut([
        'ok' => true,
        'msg' => 'Đã gửi yêu cầu hỗ trợ. Mã tra cứu của bạn: ' . $code,
        'code' => $code,
        'redirect' => '/support-detail?code=' . rawurlencode($code),
    ]);
}

// ===== Trả lời ticket =====================================================
if ($action === 'reply') {
    $ticket = support_fetch_owned_ticket($ithanhloc, $curUserId);
    if (!$ticket) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy yêu cầu hoặc bạn không có quyền truy cập.']);
    }
    if (in_array((string)$ticket['status'], ['closed'], true)) {
        jOut(['ok' => false, 'msg' => 'Yêu cầu đã đóng, không thể trả lời thêm.']);
    }
    $content = function_exists('clean_input') ? clean_input($_POST['content'] ?? '', 5000, true) : trim((string)($_POST['content'] ?? ''));
    if ($content === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập nội dung.']);
    }
    $attach = support_save_attachments('attachments', 5);
    if (!$attach['ok']) {
        jOut(['ok' => false, 'msg' => $attach['msg'] ?? 'Lỗi tải ảnh.']);
    }
    $senderId = $curUserId > 0 ? $curUserId : 0;
    $msgId = support_add_message($ithanhloc, (int)$ticket['id'], 'user', $senderId, $content, $attach['files'] ?? []);

    // Khi khách trả lời, đưa ticket về trạng thái chờ xử lý (nếu đang resolved)
    $newStatus = (string)$ticket['status'];
    if ($newStatus === 'resolved') {
        $ithanhloc->query('UPDATE support_ticket SET status="open" WHERE id=' . (int)$ticket['id']);
        $newStatus = 'open';
    }
    support_notify_admin($ithanhloc, $ticket, $content, 'reply');

    jOut([
        'ok' => true,
        'msg' => 'Đã gửi phản hồi.',
        'message' => support_message_payload($ithanhloc, $msgId, (string)($baseUrl ?? '')),
        'status' => $newStatus,
    ]);
}

// ===== User/khách tự đóng yêu cầu ========================================
if ($action === 'close') {
    $ticket = support_fetch_owned_ticket($ithanhloc, $curUserId);
    if (!$ticket) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy yêu cầu hoặc bạn không có quyền truy cập.']);
    }
    if ((string)$ticket['status'] === 'closed') {
        jOut(['ok' => false, 'msg' => 'Yêu cầu đã được đóng trước đó.']);
    }

    $tid = (int)$ticket['id'];
    $stmt = $ithanhloc->prepare('UPDATE support_ticket SET status="closed", updated_at=NOW() WHERE id=? AND status<>"closed"');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể đóng yêu cầu.']);
    }
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$changed) {
        jOut(['ok' => false, 'msg' => 'Yêu cầu đã được đóng trước đó.']);
    }

    // Ghi log hệ thống vào hội thoại để admin/khách thấy mốc đóng.
    support_add_message($ithanhloc, $tid, 'system', $curUserId, 'Yêu cầu đã được khách hàng đóng.');

    // Báo admin biết ticket đã đóng (best-effort).
    if (function_exists('support_notify_admin')) {
        support_notify_admin($ithanhloc, $ticket, 'Khách đã đóng yêu cầu.', 'reply');
    }

    jOut(['ok' => true, 'msg' => 'Đã đóng yêu cầu hỗ trợ.', 'status' => 'closed']);
}

// ===== Khách tra cứu ticket theo code + phone =============================
if ($action === 'lookup') {
    $ticket = support_fetch_owned_ticket($ithanhloc, $curUserId);
    if (!$ticket) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy yêu cầu với mã/số điện thoại đã nhập.']);
    }
    jOut(['ok' => true, 'redirect' => '/support-detail?code=' . rawurlencode((string)$ticket['code'])]);
}

// ===== Lấy ticket của user (cho tab tài khoản) ===========================
if ($action === 'my_tickets') {
    if (!$isLogged) jOut(['ok' => true, 'tickets' => []]);
    $tickets = [];
    $stmt = $ithanhloc->prepare('SELECT code, subject, status, category, priority, updated_at FROM support_ticket WHERE user_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 20');
    if ($stmt) {
        $stmt->bind_param('i', $curUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tickets[] = [
                'code'     => (string)$row['code'],
                'subject'  => (string)$row['subject'],
                'status'   => (string)$row['status'],
                'category' => (string)$row['category'],
                'priority' => (string)$row['priority'],
                'updated'  => date('H:i d/m/Y', strtotime((string)$row['updated_at'])),
            ];
        }
        $stmt->close();
    }
    jOut(['ok' => true, 'tickets' => $tickets]);
}

// ===== Lấy đơn hàng của user (cho dropdown) ===============================
if ($action === 'my_orders') {
    if (!$isLogged || $curUserId <= 0) jOut(['ok' => true, 'orders' => []]);
    $orders = [];
    $stmt = $ithanhloc->prepare('SELECT order_id, status, total_amount, created_at FROM ecommerce_order WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    if ($stmt) {
        $stmt->bind_param('i', $curUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $statusLabel = function_exists('ecommerce_order_status_info')
                ? (ecommerce_order_status_info((string)$row['status'])['label'] ?? (string)$row['status'])
                : (string)$row['status'];
            $orders[] = [
                'order_id' => (string)$row['order_id'],
                'status'   => $statusLabel,
                'created'  => date('d/m/Y', strtotime((string)$row['created_at'])),
            ];
        }
        $stmt->close();
    }
    jOut(['ok' => true, 'orders' => $orders]);
}

jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
