<?php
/**
 * AJAX Chat trực tuyến 24/7 (frontend — user & khách vãng lai).
 * Tái dùng hệ thống support: ticket category='chat'.
 * Actions: open | send | poll
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../core/support/support_common.php';

support_ensure_tables($ithanhloc);

$action    = strtolower(trim((string)($_REQUEST['action'] ?? '')));
$curUserId = (int)($_SESSION['user_id'] ?? 0);
$isLogged  = $curUserId > 0;
$base      = (string)($baseUrl ?? '');

// Chat phải được bật trong cấu hình
$chatEnabled = function_exists('app_get_config_value_by_path')
    ? app_get_config_value_by_path('LIVE_CHAT.enabled')
    : true;
$chatOn = ($chatEnabled === null) ? true
    : ($chatEnabled === true || $chatEnabled === 1 || $chatEnabled === '1' || strtolower((string)$chatEnabled) === 'true');
if (!$chatOn) {
    jOut(['ok' => false, 'msg' => 'Chat hỗ trợ hiện đang tạm tắt.'], 403);
}

// Action ghi BẮT BUỘC POST + CSRF
$writeActions = ['open', 'send'];
if (in_array($action, $writeActions, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ.'], 405);
    }
    if (function_exists('app_verify_csrf')) app_verify_csrf();

    // ── Rate Limiting ─────────────────────────────────────────────────────────
    // Mở phiên mới: tối đa 3 lần/phút/IP (chống tạo phiên rác hàng loạt)
    if ($action === 'open' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'chat_open',
            3,  // tối đa 3 lần mở phiên
            60, // trong 60 giây
            'Bạn đang mở quá nhiều phiên chat. Vui lòng chờ ít phút rồi thử lại.'
        );
    }
    // Gửi tin nhắn: tối đa 5 tin/30 giây/IP (chống spam tin nhắn liên tục)
    if ($action === 'send' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'chat_send',
            5,  // tối đa 5 tin nhắn
            30, // trong 30 giây
            'Bạn đang gửi tin nhắn quá nhanh. Vui lòng chờ vài giây rồi thử lại.'
        );
    }
    // ─────────────────────────────────────────────────────────────────────────
}

/**
 * Lấy phiên chat mà người gọi SỞ HỮU (user theo session, khách theo guest_key cookie).
 * Trả null nếu không có quyền — tránh lộ phiên của người khác.
 */
function chat_fetch_owned(mysqli $db, int $userId): ?array {
    $ticketId = (int)($_REQUEST['ticket_id'] ?? 0);
    if ($ticketId <= 0) return null;
    $stmt = $db->prepare("SELECT * FROM support_ticket WHERE id=? AND category='chat' AND is_active=1 LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) return null;

    if ($userId > 0 && (int)$t['user_id'] === $userId) return $t;

    $guestKey = function_exists('app_guest_key') ? app_guest_key() : '';
    if ((int)$t['user_id'] === 0 && !empty($t['guest_key']) && $guestKey !== '' && hash_equals((string)$t['guest_key'], $guestKey)) {
        return $t;
    }
    return null;
}

function chat_get_assignee_name(mysqli $db, int $ticketId): ?string {
    $stmt = $db->prepare("SELECT a.full_name, a.username FROM support_ticket t LEFT JOIN users a ON a.id = t.assignee_id WHERE t.id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        return $r['full_name'] ? trim((string)$r['full_name']) : ($r['username'] ? trim((string)$r['username']) : null);
    }
    return null;
}

// ===== Khôi phục phiên đang mở (KHÔNG tạo mới) — dùng khi F5/tải lại trang =====
// User: theo session. Khách: theo cookie guest_key. Không cần name/phone.
if ($action === 'resume') {
    $ticket = null;
    if ($isLogged) {
        $stmt = $ithanhloc->prepare("SELECT * FROM support_ticket WHERE category='chat' AND user_id=? AND status<>'closed' AND is_active=1 ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $curUserId);
    } else {
        $gKey = function_exists('app_guest_key') ? app_guest_key() : '';
        if ($gKey === '') {
            jOut(['ok' => false, 'need_guest' => true]);
        }
        $stmt = $ithanhloc->prepare("SELECT * FROM support_ticket WHERE category='chat' AND user_id=0 AND guest_key=? AND status<>'closed' AND is_active=1 ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('s', $gKey);
    }
    if ($stmt) {
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$ticket) {
        jOut(['ok' => false, 'need_guest' => !$isLogged]);
    }
    jOut([
        'ok'        => true,
        'ticket_id' => (int)$ticket['id'],
        'code'      => (string)$ticket['code'],
        'status'    => (string)$ticket['status'],
        'assignee_name' => chat_get_assignee_name($ithanhloc, (int)$ticket['id']),
        'messages'  => support_fetch_messages($ithanhloc, (int)$ticket['id'], 0, $base),
    ]);
}

// ===== Mở / tiếp tục phiên chat ==========================================
if ($action === 'open') {
    $guest = [];
    if (!$isLogged) {
        $nameInput  = trim((string)($_POST['guest_name'] ?? ''));
        $phoneInput = trim((string)($_POST['guest_phone'] ?? ''));

        if ($nameInput === '' || $phoneInput === '') {
            jOut(['ok' => false, 'msg' => 'Vui lòng nhập Họ tên và Số điện thoại để bắt đầu chat.', 'need_guest' => true]);
        }

        // Sanitize name: remove HTML/PHP tags, special chars (XSS/PHP inject filter)
        $name = strip_tags($nameInput);
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $name = preg_replace('/[<>\/\{\}\[\]\(\)\\\]/', '', $name);
        $name = trim($name);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            jOut(['ok' => false, 'msg' => 'Họ tên phải từ 2 đến 50 ký tự và không chứa các ký tự lạ.', 'need_guest' => true]);
        }

        // Sanitize & Normalize Phone
        $phone = preg_replace('/[^\d\+]/', '', $phoneInput);
        if (strpos($phone, '+84') === 0) {
            $phone = '0' . substr($phone, 3);
        } elseif (strpos($phone, '84') === 0) {
            $phone = '0' . substr($phone, 2);
        }

        // Validate Vietnamese phone number: 03, 05, 07, 08, 09 followed by exactly 8 digits
        if (!preg_match('/^0(3|5|7|8|9)\d{8}$/', $phone)) {
            jOut(['ok' => false, 'msg' => 'Số điện thoại không hợp lệ. Vui lòng nhập số điện thoại Việt Nam hợp lệ (ví dụ: 0987654321).', 'need_guest' => true]);
        }

        $guest = [
            'name'  => $name,
            'phone' => $phone,
            'email' => function_exists('clean_email') ? clean_email($_POST['guest_email'] ?? '') : '',
            'key'   => function_exists('app_guest_key') ? app_guest_key() : bin2hex(random_bytes(16)),
        ];
    }

    $ticket = support_get_or_create_chat($ithanhloc, $curUserId, $guest);
    if (!$ticket) {
        jOut(['ok' => false, 'msg' => 'Không thể mở phiên chat. Vui lòng thử lại.']);
    }

    $messages = support_fetch_messages($ithanhloc, (int)$ticket['id'], 0, $base);
    jOut([
        'ok'        => true,
        'ticket_id' => (int)$ticket['id'],
        'code'      => (string)$ticket['code'],
        'status'    => (string)$ticket['status'],
        'assignee_name' => chat_get_assignee_name($ithanhloc, (int)$ticket['id']),
        'messages'  => $messages,
    ]);
}

// ===== Gửi tin nhắn ======================================================
if ($action === 'send') {
    $ticket = chat_fetch_owned($ithanhloc, $curUserId);
    if (!$ticket) {
        jOut(['ok' => false, 'session_gone' => true, 'msg' => 'Phiên chat không tồn tại hoặc bạn không có quyền.']);
    }
    if ((string)$ticket['status'] === 'closed') {
        jOut(['ok' => false, 'msg' => 'Phiên chat đã kết thúc. Hãy mở phiên mới.', 'status' => 'closed']);
    }
    $content = function_exists('clean_input') ? clean_input($_POST['content'] ?? '', 5000, true) : trim((string)($_POST['content'] ?? ''));

    $attach = support_save_attachments('attachments', 5);
    if (!$attach['ok']) {
        jOut(['ok' => false, 'msg' => $attach['msg'] ?? 'Lỗi tải ảnh.']);
    }
    $files = $attach['files'] ?? [];

    if ($content === '' && empty($files)) {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập nội dung.']);
    }

    $senderId = $curUserId > 0 ? $curUserId : 0;
    $msgId = support_add_message($ithanhloc, (int)$ticket['id'], 'user', $senderId, $content, $files);

    // Nếu admin đã đánh dấu resolved mà khách nhắn tiếp → mở lại
    if ((string)$ticket['status'] === 'resolved') {
        $tidOpen = (int)$ticket['id'];
        $up = $ithanhloc->prepare('UPDATE support_ticket SET status="open" WHERE id=?');
        if ($up) { $up->bind_param('i', $tidOpen); $up->execute(); $up->close(); }
    }

    support_notify_admin($ithanhloc, $ticket, $content !== '' ? $content : '[Đã gửi ảnh]', 'reply');

    jOut([
        'ok'      => true,
        'message' => support_message_payload($ithanhloc, $msgId, $base),
    ]);
}

// ===== Polling lấy tin mới ===============================================
if ($action === 'poll') {
    $ticket = chat_fetch_owned($ithanhloc, $curUserId);
    if (!$ticket) {
        jOut(['ok' => false, 'msg' => 'Phiên chat không tồn tại.']);
    }
    $afterId  = (int)($_GET['after_id'] ?? 0);
    $messages = support_fetch_messages($ithanhloc, (int)$ticket['id'], $afterId, $base);
    jOut([
        'ok'       => true,
        'status'   => (string)$ticket['status'],
        'assignee_name' => chat_get_assignee_name($ithanhloc, (int)$ticket['id']),
        'messages' => $messages,
    ]);
}

jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
