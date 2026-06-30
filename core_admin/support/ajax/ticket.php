<?php
/**
 * AJAX hỗ trợ (admin).
 * Actions: list | reply | update_status | update_priority | assign
 *          | faq_save | faq_delete
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../core/support/support_common.php';

if (!$isAdmin) {
    http_response_code(403);
    jOut(['ok' => false, 'msg' => 'Chỉ dành cho quản trị viên']);
}

support_ensure_tables($ithanhloc);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
$adminId = (int)($_SESSION['user_id'] ?? 0);

// Action ghi BẮT BUỘC qua POST — chặn gọi trực tiếp trên URL (GET).
$writeActions = ['reply', 'update_status', 'update_priority', 'assign', 'faq_save', 'faq_delete', 'delete_one', 'delete_multi'];
if (in_array($action, $writeActions, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ: hãy thao tác qua giao diện.'], 405);
    }
    if (function_exists('app_verify_csrf')) app_verify_csrf();
}

function support_admin_get_ticket(mysqli $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM support_ticket WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $t ?: null;
}

// ===== Gợi ý SP/voucher (dùng helper chung trong support_common.php) ======
if ($action === 'suggest_categories') support_handle_suggest($ithanhloc, 'categories', (string)($baseUrl ?? ''));
if ($action === 'suggest_products')   support_handle_suggest($ithanhloc, 'products', (string)($baseUrl ?? ''));
if ($action === 'suggest_vouchers')   support_handle_suggest($ithanhloc, 'vouchers', (string)($baseUrl ?? ''));

// ===== Danh sách + lọc ====================================================
if ($action === 'list') {
    $status   = support_norm_enum((string)($_GET['status'] ?? ''), support_statuses(), '');
    $priority = support_norm_enum((string)($_GET['priority'] ?? ''), support_priorities(), '');
    $category = support_norm_enum((string)($_GET['category'] ?? ''), support_categories(), '');
    $q        = trim((string)($_GET['q'] ?? ''));

    $where = ['t.is_active = 1'];
    $params = []; $types = '';
    // status/priority/category được validate enum -> an toàn để nội suy
    if ($status !== '')   { $where[] = "t.status = '" . $ithanhloc->real_escape_string($status) . "'"; }
    if ($priority !== '') { $where[] = "t.priority = '" . $ithanhloc->real_escape_string($priority) . "'"; }
    if ($category !== '') { $where[] = "t.category = '" . $ithanhloc->real_escape_string($category) . "'"; }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(t.code LIKE ? OR t.subject LIKE ? OR t.order_id LIKE ? OR t.guest_phone LIKE ? OR t.guest_name LIKE ?)";
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT t.*, u.full_name AS user_name, u.phone AS user_phone
            FROM support_ticket t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FIELD(t.priority,'high','normal','low'),
                     (t.status='open') DESC, (t.status='pending') DESC,
                     t.last_reply_at DESC, t.id DESC
            LIMIT 300";

    $rows = [];
    if ($params) {
        $stmt = $ithanhloc->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    } else {
        $res = $ithanhloc->query($sql);
        while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    }

    // KPI (gồm cả 'closed' cho thẻ tóm tắt giống order)
    $kpi = ['total' => 0, 'open' => 0, 'pending' => 0, 'resolved' => 0, 'closed' => 0];
    $kres = $ithanhloc->query("SELECT status, COUNT(*) c FROM support_ticket WHERE is_active=1 GROUP BY status");
    while ($kres && ($kr = $kres->fetch_assoc())) {
        $kpi['total'] += (int)$kr['c'];
        if (isset($kpi[$kr['status']])) $kpi[$kr['status']] = (int)$kr['c'];
    }

    $cats = support_categories();
    $out = [];
    foreach ($rows as $t) {
        $name = $t['user_id'] > 0 ? ($t['user_name'] ?: '#' . $t['user_id']) : ($t['guest_name'] ?: 'Khách');
        $phone = $t['user_id'] > 0 ? ($t['user_phone'] ?: '') : ($t['guest_phone'] ?: '');
        $out[] = [
            'id' => (int)$t['id'],
            'code' => $t['code'],
            'subject' => $t['subject'],
            'category' => $cats[$t['category']] ?? $t['category'],
            'priority' => $t['priority'],
            'status' => $t['status'],
            'order_id' => $t['order_id'],
            'requester' => $name,
            'phone' => $phone,
            'is_guest' => ((int)$t['user_id'] === 0),
            'updated' => date('H:i d/m/Y', strtotime((string)$t['updated_at'])),
        ];
    }
    jOut(['ok' => true, 'tickets' => $out, 'kpi' => $kpi]);
}

// ===== Admin trả lời ======================================================
if ($action === 'reply') {
    $id = (int)($_POST['ticket_id'] ?? 0);
    $ticket = support_admin_get_ticket($ithanhloc, $id);
    if (!$ticket) jOut(['ok' => false, 'msg' => 'Không tìm thấy yêu cầu.']);
    $content = function_exists('clean_input') ? clean_input($_POST['content'] ?? '', 5000, true) : trim((string)($_POST['content'] ?? ''));

    // Gợi ý SP/voucher → build marker [[PMCARD:type]]{json} từ DB (helper chung).
    list($content, $hasCard, $cardType) = support_apply_card_to_content($ithanhloc, $content, (string)($baseUrl ?? ''));

    $attach = support_save_attachments('attachments', 5);
    if (!$attach['ok']) jOut(['ok' => false, 'msg' => $attach['msg'] ?? 'Lỗi tải ảnh.']);
    $files = $attach['files'] ?? [];

    if ($content === '' && empty($files) && !$hasCard) {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập nội dung phản hồi.']);
    }

    $msgId = support_add_message($ithanhloc, $id, 'admin', $adminId, $content, $files);

    // Phản hồi admin -> chuyển sang "pending" (chờ khách) nếu đang open
    $newStatus = (string)$ticket['status'];
    if ($newStatus === 'open') {
        $ithanhloc->query('UPDATE support_ticket SET status="pending" WHERE id=' . $id);
        $newStatus = 'pending';
    }
    // Gán người phụ trách nếu chưa có
    $assigned = (int)$ticket['assignee_id'];
    if ($assigned === 0) {
        $ithanhloc->query('UPDATE support_ticket SET assignee_id=' . $adminId . ' WHERE id=' . $id);
        $assigned = $adminId;
    }

    $notifySnippet = $hasCard
        ? ($cardType === 'voucher' ? '[Đã gửi mã ưu đãi]' : '[Đã gợi ý sản phẩm]')
        : ($content !== '' ? $content : '[Đã gửi ảnh]');
    support_notify_user($ithanhloc, $ticket, $notifySnippet);
    jOut([
        'ok' => true,
        'msg' => 'Đã gửi phản hồi tới khách hàng.',
        'message' => support_message_payload($ithanhloc, $msgId, (string)($baseUrl ?? '')),
        'status' => $newStatus,
        'assigned' => $assigned,
    ]);
}

// ===== Xoá 1 ticket (soft-delete: is_active=0) ===========================
if ($action === 'delete_one') {
    $id = (int)($_POST['id'] ?? $_POST['ticket_id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu mã yêu cầu.']);
    $stmt = $ithanhloc->prepare('UPDATE support_ticket SET is_active = 0 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();
    if ($ok && $aff > 0) support_cleanup_deleted_tickets($ithanhloc, [$id]);
    jOut(['ok' => (bool)$ok, 'deleted' => $ok ? (int)$aff : 0, 'msg' => $ok ? 'Đã xoá yêu cầu.' : 'Xoá thất bại.']);
}

// ===== Xoá hàng loạt ticket ==============================================
if ($action === 'delete_multi') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || !$ids) jOut(['ok' => false, 'msg' => 'Thiếu danh sách yêu cầu.']);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
    if (!$ids) jOut(['ok' => false, 'msg' => 'Danh sách không hợp lệ.']);
    $place = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $ithanhloc->prepare("UPDATE support_ticket SET is_active = 0 WHERE id IN ($place)");
    $stmt->bind_param($types, ...$ids);
    $ok = $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();
    if ($ok && $aff > 0) support_cleanup_deleted_tickets($ithanhloc, $ids);
    jOut(['ok' => (bool)$ok, 'deleted' => $ok ? (int)$aff : 0, 'msg' => $ok ? "Đã xoá {$aff} yêu cầu." : 'Xoá thất bại.']);
}

// ===== Đổi trạng thái =====================================================
if ($action === 'update_status') {
    $id = (int)($_POST['ticket_id'] ?? 0);
    $status = support_norm_enum((string)($_POST['status'] ?? ''), support_statuses(), '');
    if ($id <= 0 || $status === '') jOut(['ok' => false, 'msg' => 'Tham số không hợp lệ.']);
    $stmt = $ithanhloc->prepare('UPDATE support_ticket SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => $ok, 'msg' => $ok ? 'Đã cập nhật trạng thái.' : 'Cập nhật thất bại.']);
}

// ===== Đổi ưu tiên ========================================================
if ($action === 'update_priority') {
    $id = (int)($_POST['ticket_id'] ?? 0);
    $priority = support_norm_enum((string)($_POST['priority'] ?? ''), support_priorities(), '');
    if ($id <= 0 || $priority === '') jOut(['ok' => false, 'msg' => 'Tham số không hợp lệ.']);
    $stmt = $ithanhloc->prepare('UPDATE support_ticket SET priority = ? WHERE id = ?');
    $stmt->bind_param('si', $priority, $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => $ok, 'msg' => $ok ? 'Đã cập nhật ưu tiên.' : 'Cập nhật thất bại.']);
}

// ===== Gán phụ trách (cho admin hiện tại) =================================
if ($action === 'assign') {
    $id = (int)($_POST['ticket_id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Tham số không hợp lệ.']);
    $stmt = $ithanhloc->prepare('UPDATE support_ticket SET assignee_id = ? WHERE id = ?');
    $stmt->bind_param('ii', $adminId, $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => $ok, 'msg' => $ok ? 'Đã nhận xử lý yêu cầu.' : 'Thất bại.']);
}

// ===== FAQ: danh sách =====================================================
if ($action === 'faq_list') {
    $faqs = [];
    $res = $ithanhloc->query("SELECT * FROM support_faq ORDER BY order_index ASC, id ASC");
    while ($res && ($r = $res->fetch_assoc())) {
        $faqs[] = [
            'id' => (int)$r['id'],
            'question' => (string)$r['question'],
            'answer' => (string)$r['answer'],
            'category' => (string)$r['category'],
            'order_index' => (int)$r['order_index'],
            'is_active' => (int)$r['is_active'],
        ];
    }
    jOut(['ok' => true, 'faqs' => $faqs]);
}

// ===== FAQ: lưu (thêm/sửa) ================================================
if ($action === 'faq_save') {
    $id = (int)($_POST['id'] ?? 0);
    $question = function_exists('clean_input') ? clean_input($_POST['question'] ?? '', 250) : trim((string)($_POST['question'] ?? ''));
    $answer   = trim((string)($_POST['answer'] ?? ''));
    $category = function_exists('clean_input') ? clean_input($_POST['category'] ?? 'general', 40) : 'general';
    $order    = (int)($_POST['order_index'] ?? 0);
    $active   = (int)(!empty($_POST['is_active']));
    if ($question === '' || $answer === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập câu hỏi và câu trả lời.']);

    if ($id > 0) {
        $stmt = $ithanhloc->prepare('UPDATE support_faq SET question=?, answer=?, category=?, order_index=?, is_active=? WHERE id=?');
        $stmt->bind_param('sssiii', $question, $answer, $category, $order, $active, $id);
    } else {
        $stmt = $ithanhloc->prepare('INSERT INTO support_faq (question, answer, category, order_index, is_active) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssii', $question, $answer, $category, $order, $active);
    }
    $ok = $stmt->execute();
    $newId = $id > 0 ? $id : (int)$stmt->insert_id;
    $stmt->close();
    jOut([
        'ok' => $ok,
        'msg' => $ok ? 'Đã lưu câu hỏi.' : 'Lưu thất bại.',
        'faq' => [
            'id' => $newId,
            'question' => $question,
            'answer' => $answer,
            'category' => $category,
            'order_index' => $order,
            'is_active' => $active,
        ],
        'is_new' => ($id <= 0),
    ]);
}

// ===== FAQ: xóa ===========================================================
if ($action === 'faq_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Tham số không hợp lệ.']);
    $stmt = $ithanhloc->prepare('DELETE FROM support_faq WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => $ok, 'msg' => $ok ? 'Đã xóa câu hỏi.' : 'Xóa thất bại.']);
}

jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
