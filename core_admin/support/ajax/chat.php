<?php
/**
 * AJAX Chat trực tuyến 24/7 (admin — hộp chat nổi).
 * Tái dùng hệ thống support: ticket category='chat'.
 * Actions: inbox | thread | poll | send | close
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../core/support/support_common.php';

if (!$isAdmin) {
    http_response_code(403);
    jOut(['ok' => false, 'msg' => 'Chỉ dành cho quản trị viên']);
}

support_ensure_tables($ithanhloc);

$action  = strtolower(trim((string)($_REQUEST['action'] ?? '')));
$adminId = (int)($_SESSION['user_id'] ?? 0);
$base    = (string)($baseUrl ?? '');

$writeActions = ['send', 'close', 'delete'];
if (in_array($action, $writeActions, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ.'], 405);
    }
    if (function_exists('app_verify_csrf')) app_verify_csrf();
}

function chat_admin_get_ticket(mysqli $db, int $id): ?array {
    $stmt = $db->prepare("SELECT * FROM support_ticket WHERE id=? AND category='chat' AND is_active=1 LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $t ?: null;
}

/** Tên hiển thị của 1 phiên chat: user.full_name / username, hoặc guest_name. */
function chat_display_name(array $t): string {
    $name = trim((string)($t['user_name'] ?? ''));
    if ($name === '') $name = trim((string)($t['username'] ?? ''));
    if ($name === '') $name = trim((string)($t['guest_name'] ?? ''));
    if ($name === '') $name = (int)($t['user_id'] ?? 0) > 0 ? 'Khách hàng' : 'Khách vãng lai';
    return $name;
}

// ===== Gợi ý SP/voucher (dùng helper chung trong support_common.php) ======
if ($action === 'suggest_categories') support_handle_suggest($ithanhloc, 'categories', $base);
if ($action === 'suggest_products')   support_handle_suggest($ithanhloc, 'products', $base);
if ($action === 'suggest_vouchers')   support_handle_suggest($ithanhloc, 'vouchers', $base);

// ===== Inbox: danh sách phiên chat đang mở ================================
if ($action === 'inbox') {
    $filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
    $where = "t.category='chat' AND t.is_active=1 AND t.status<>'closed'";
    if ($filter === 'unread') {
        $where .= " AND t.last_reply_by='user'";
    } elseif ($filter === 'read') {
        $where .= " AND (t.last_reply_by IS NULL OR t.last_reply_by <> 'user')";
    }

    $rows = [];
    $sql = "SELECT t.*, u.full_name AS user_name, u.username AS username, u.phone AS user_phone
            FROM support_ticket t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE $where
            ORDER BY (t.last_reply_by='user') DESC, t.last_reply_at DESC
            LIMIT 100";
    $res = $ithanhloc->query($sql);
    
    // Đếm tổng số chưa đọc thực tế trên mọi phiên đang hoạt động
    $unreadTotal = 0;
    $qUnread = $ithanhloc->query("SELECT COUNT(*) AS total FROM support_ticket WHERE category='chat' AND is_active=1 AND status<>'closed' AND last_reply_by='user'");
    if ($qUnread && ($ru = $qUnread->fetch_assoc())) {
        $unreadTotal = (int)$ru['total'];
    }

    if ($res) {
        while ($t = $res->fetch_assoc()) {
            // Tin nhắn cuối làm snippet
            $snippet = '';
            $sid = (int)$t['id'];
            $mr = $ithanhloc->prepare("SELECT content, media_json FROM support_ticket_message WHERE ticket_id=? AND is_active=1 ORDER BY id DESC LIMIT 1");
            if ($mr) {
                $mr->bind_param('i', $sid);
                $mr->execute();
                if ($last = $mr->get_result()->fetch_assoc()) {
                    $snippet = support_card_snippet(trim((string)$last['content']));
                    if ($snippet === '' && !empty($last['media_json'])) $snippet = '[Hình ảnh]';
                }
                $mr->close();
            }
            $unread = ((string)$t['last_reply_by'] === 'user');
            $rows[] = [
                'ticket_id' => $sid,
                'code'      => (string)$t['code'],
                'name'      => chat_display_name($t),
                'phone'     => (string)($t['guest_phone'] ?: $t['user_phone'] ?: ''),
                'is_guest'  => (int)$t['user_id'] === 0,
                'snippet'   => mb_substr($snippet, 0, 60),
                'unread'    => $unread,
                'status'    => (string)$t['status'],
                'time'      => $t['last_reply_at'] ? date('H:i d/m', strtotime((string)$t['last_reply_at'])) : '',
            ];
        }
    }
    jOut(['ok' => true, 'sessions' => $rows, 'unread_total' => $unreadTotal]);
}

// ===== Thread: toàn bộ tin của 1 phiên ===================================
if ($action === 'thread') {
    $tid = (int)($_GET['ticket_id'] ?? 0);
    $t = chat_admin_get_ticket($ithanhloc, $tid);
    if (!$t) jOut(['ok' => false, 'msg' => 'Không tìm thấy phiên chat.']);

    // join thông tin user để hiện tên và assignee
    $stmt = $ithanhloc->prepare("SELECT t.*, u.full_name AS user_name, u.username AS username, u.phone AS user_phone,
        a.full_name AS assignee_name, a.username AS assignee_username
        FROM support_ticket t 
        LEFT JOIN users u ON u.id=t.user_id 
        LEFT JOIN users a ON a.id=t.assignee_id
        WHERE t.id=? LIMIT 1");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $full = $stmt->get_result()->fetch_assoc() ?: $t;
    $stmt->close();

    jOut([
        'ok'        => true,
        'ticket_id' => $tid,
        'code'      => (string)$t['code'],
        'name'      => chat_display_name($full),
        'phone'     => (string)($full['guest_phone'] ?: $full['user_phone'] ?: ''),
        'status'    => (string)$t['status'],
        'assignee_name' => $full['assignee_name'] ? trim((string)$full['assignee_name']) : ($full['assignee_username'] ? trim((string)$full['assignee_username']) : null),
        'messages'  => support_fetch_messages($ithanhloc, $tid, 0, $base),
    ]);
}

// ===== Poll: tin mới của 1 phiên (+ trạng thái) ==========================
if ($action === 'poll') {
    $tid = (int)($_GET['ticket_id'] ?? 0);
    $t = chat_admin_get_ticket($ithanhloc, $tid);
    if (!$t) jOut(['ok' => false, 'msg' => 'Không tìm thấy phiên chat.']);

    // join thông tin user để lấy tên assignee
    $stmt = $ithanhloc->prepare("SELECT t.*, a.full_name AS assignee_name, a.username AS assignee_username 
        FROM support_ticket t LEFT JOIN users a ON a.id=t.assignee_id WHERE t.id=? LIMIT 1");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $full = $stmt->get_result()->fetch_assoc() ?: $t;
    $stmt->close();

    $afterId = (int)($_GET['after_id'] ?? 0);
    jOut([
        'ok'       => true,
        'status'   => (string)$t['status'],
        'assignee_name' => $full['assignee_name'] ? trim((string)$full['assignee_name']) : ($full['assignee_username'] ? trim((string)$full['assignee_username']) : null),
        'messages' => support_fetch_messages($ithanhloc, $tid, $afterId, $base),
    ]);
}

// ===== Gửi tin (admin) ===================================================
if ($action === 'send') {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    $t = chat_admin_get_ticket($ithanhloc, $tid);
    if (!$t) jOut(['ok' => false, 'msg' => 'Không tìm thấy phiên chat.']);
    if ((string)$t['status'] === 'closed') {
        jOut(['ok' => false, 'msg' => 'Phiên chat đã đóng.', 'status' => 'closed']);
    }
    $content = function_exists('clean_input') ? clean_input($_POST['content'] ?? '', 5000, true) : trim((string)($_POST['content'] ?? ''));

    // Gợi ý SP/voucher → build marker [[PMCARD:type]]{json} từ DB (helper chung).
    list($content, $hasCard, $cardType) = support_apply_card_to_content($ithanhloc, $content, $base);

    $attach = support_save_attachments('attachments', 5);
    if (!$attach['ok']) jOut(['ok' => false, 'msg' => $attach['msg'] ?? 'Lỗi tải ảnh.']);
    $files = $attach['files'] ?? [];

    if ($content === '' && empty($files) && !$hasCard) {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập nội dung.']);
    }

    $msgId = support_add_message($ithanhloc, $tid, 'admin', $adminId, $content, $files);

    // Gán phụ trách nếu chưa có
    if ((int)($t['assignee_id'] ?? 0) === 0 && $adminId > 0) {
        $up = $ithanhloc->prepare('UPDATE support_ticket SET assignee_id=? WHERE id=?');
        if ($up) { $up->bind_param('ii', $adminId, $tid); $up->execute(); $up->close(); }
    }

    $notifySnippet = $hasCard
        ? ($cardType === 'voucher' ? '[Đã gửi mã ưu đãi]' : '[Đã gợi ý sản phẩm]')
        : ($content !== '' ? $content : '[Đã gửi ảnh]');
    support_notify_user($ithanhloc, $t, $notifySnippet);

    jOut([
        'ok'      => true,
        'message' => support_message_payload($ithanhloc, $msgId, $base),
    ]);
}

// ===== Đóng phiên ========================================================
if ($action === 'close') {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    $t = chat_admin_get_ticket($ithanhloc, $tid);
    if (!$t) jOut(['ok' => false, 'msg' => 'Không tìm thấy phiên chat.']);
    if ((string)$t['status'] === 'closed') {
        jOut(['ok' => true, 'msg' => 'Phiên đã đóng trước đó.', 'status' => 'closed']);
    }
    $up = $ithanhloc->prepare('UPDATE support_ticket SET status="closed", updated_at=NOW() WHERE id=?');
    if ($up) { $up->bind_param('i', $tid); $up->execute(); $up->close(); }
    support_add_message($ithanhloc, $tid, 'system', $adminId, 'Phiên chat đã được nhân viên kết thúc.');
    jOut(['ok' => true, 'msg' => 'Đã đóng phiên chat.', 'status' => 'closed']);
}

// ===== Xoá cuộc trò chuyện ===============================================
if ($action === 'delete') {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    $t = chat_admin_get_ticket($ithanhloc, $tid);
    if (!$t) jOut(['ok' => false, 'msg' => 'Không tìm thấy phiên chat.']);

    // Soft delete cuộc trò chuyện và tin nhắn liên quan.
    // LƯU Ý: support_ticket_message KHÔNG có cột updated_at → chỉ set is_active.
    $upT = $ithanhloc->prepare('UPDATE support_ticket SET is_active=0, updated_at=NOW() WHERE id=?');
    if ($upT) { $upT->bind_param('i', $tid); $upT->execute(); $upT->close(); }
    $upM = $ithanhloc->prepare('UPDATE support_ticket_message SET is_active=0 WHERE ticket_id=?');
    if ($upM) { $upM->bind_param('i', $tid); $upM->execute(); $upM->close(); }

    jOut(['ok' => true, 'msg' => 'Đã xoá cuộc trò chuyện.']);
}

jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
