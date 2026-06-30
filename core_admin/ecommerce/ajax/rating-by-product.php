<?php
require_once __DIR__ . '/../../../config.php';
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list')));
$draw = intval($_GET['draw'] ?? 1);
// Kiểm tra quyền truy cập một cách linh hoạt hơn
if (!$isLoggedIn) {
    jOut([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'ok' => false,
        'msg' => 'Phiên làm việc đã kết thúc. Vui lòng đăng nhập lại.'
    ]);
}

if (!$isAdmin) {
    jOut([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'ok' => false,
        'msg' => 'Tài khoản không có quyền quản trị. Vui lòng kiểm tra lại.'
    ]);
}

if ($action === 'list') {
    // DataTable parameters
    $start = max(0, intval($_GET['start'] ?? 0));
    $pageSize = intval($_GET['length'] ?? 15);
    if ($pageSize <= 0) $pageSize = 15;
    if ($pageSize > 200) $pageSize = 200;

    // Filters
    $search = trim($_GET['search']['value'] ?? $_GET['search_val'] ?? '');
    $statusFilter = strtolower(trim((string)($_GET['status_filter'] ?? 'all')));
    $typeFilter = strtolower(trim((string)($_GET['type_filter'] ?? 'all')));
    $ratingFilter = (int)($_GET['rating'] ?? 0);
    $unrepliedOnly = (int)($_GET['unreplied'] ?? 0);

    // Sorting
    $orderColIndex = intval($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtolower(trim((string)($_GET['order'][0]['dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
    $orderCols = ['r.id', 'p.product_name', 'author_name', 'r.rating', 'r.comment', 'r.created_at', 'r.status'];
    $orderBy = $orderCols[$orderColIndex] ?? 'r.created_at';
    if ($orderBy === '') $orderBy = 'r.created_at';

    // Query Building
    $where = ['r.parent_id = 0'];
    $params = [];
    $types = '';

    if ($statusFilter === 'show') {
        $where[] = 'r.status = 1';
    } elseif ($statusFilter === 'hide') {
        $where[] = 'r.status = 0';
    }

    if (in_array($typeFilter, ['review', 'question'], true)) {
        $where[] = 'LOWER(r.review_type) = ?';
        $params[] = $typeFilter;
        $types .= 's';
    }

    if ($ratingFilter > 0) {
        $where[] = 'r.rating = ?';
        $params[] = $ratingFilter;
        $types .= 'i';
    }

    if ($unrepliedOnly === 1) {
        $where[] = 'NOT EXISTS (SELECT 1 FROM ecommerce_product_review sub WHERE sub.parent_id = r.id)';
    }

    if ($search !== '') {
        $where[] = '(p.product_name LIKE ? OR r.comment LIKE ? OR COALESCE(r.display_name, u.full_name, u.username, "") LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'sss';
    }

    $whereSql = implode(' AND ', $where);

    // Get Total records filtered
    $countSql = "SELECT COUNT(*) as c FROM ecommerce_product_review r 
                 LEFT JOIN ecommerce_product p ON p.id = r.product_id
                 LEFT JOIN users u ON u.id = r.user_id
                 WHERE $whereSql";
    $stmtCount = $ithanhloc->prepare($countSql);
    if (!$stmtCount) {
        jOut(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $ithanhloc->error]);
    }
    if ($types !== '') bindParamsDynamic($stmtCount, $types, $params);
    $stmtCount->execute();
    $recordsFiltered = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCount->close();

    // Get Total records absolute
    $recordsTotal = (int)($ithanhloc->query("SELECT COUNT(*) as c FROM ecommerce_product_review WHERE parent_id = 0")->fetch_assoc()['c'] ?? 0);

    // Get Data
    $sql = "SELECT r.*, p.product_name, p.image_url,
            COALESCE(r.display_name, u.full_name, u.username, 'Khách hàng') as author_name,
            (SELECT COUNT(*) FROM ecommerce_product_review sub WHERE sub.parent_id = r.id) as reply_count
            FROM ecommerce_product_review r
            LEFT JOIN ecommerce_product p ON p.id = r.product_id
            LEFT JOIN users u ON u.id = r.user_id
            WHERE $whereSql
            ORDER BY $orderBy $orderDir
            LIMIT ?, ?";
    
    $stmt = $ithanhloc->prepare($sql);
    if (!$stmt) {
        jOut(['draw' => $draw, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $recordsFiltered, 'data' => [], 'error' => $ithanhloc->error]);
    }
    $pList = $params;
    $tList = $types . 'ii';
    $pList[] = $start;
    $pList[] = $pageSize;
    bindParamsDynamic($stmt, $tList, $pList);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jOut([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ]);
}

if ($action === 'stats') {
    $totalRes = $ithanhloc->query("SELECT COUNT(*) FROM ecommerce_product_review WHERE parent_id = 0");
    $total = $totalRes ? $totalRes->fetch_row()[0] : 0;

    $unrepliedRes = $ithanhloc->query("SELECT COUNT(*) FROM ecommerce_product_review r WHERE parent_id = 0 AND NOT EXISTS (SELECT 1 FROM ecommerce_product_review sub WHERE sub.parent_id = r.id)");
    $unreplied = $unrepliedRes ? $unrepliedRes->fetch_row()[0] : 0;

    $lowRes = $ithanhloc->query("SELECT COUNT(*) FROM ecommerce_product_review WHERE parent_id = 0 AND rating > 0 AND rating <= 2");
    $low = $lowRes ? $lowRes->fetch_row()[0] : 0;

    $questionRes = $ithanhloc->query("SELECT COUNT(*) FROM ecommerce_product_review WHERE parent_id = 0 AND (review_type = 'question' OR rating = 0)");
    $question = $questionRes ? $questionRes->fetch_row()[0] : 0;

    jOut([
        'ok' => true,
        'stats' => [
            'total'     => (int)$total,
            'unreplied' => (int)$unreplied,
            'low_rating'=> (int)$low,
            'question'  => (int)$question,
        ]
    ]);
}

if ($action === 'toggle_status') {
    $id = (int)($_POST['review_id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);
    $stmt = $ithanhloc->prepare("UPDATE ecommerce_product_review SET status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => $ok]);
    }
    jOut(['ok' => false]);
}

if ($action === 'bulk_status') {
    $ids = $_POST['ids'] ?? [];
    $status = (int)($_POST['status'] ?? 0);
    if (empty($ids)) jOut(['ok' => false, 'msg' => 'Chưa chọn mục nào']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $ithanhloc->prepare("UPDATE ecommerce_product_review SET status = ? WHERE id IN ($placeholders)");
    if ($stmt) {
        $types = 'i' . str_repeat('i', count($ids));
        $params = array_merge([$status], $ids);
        bindParamsDynamic($stmt, $types, $params);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => $ok, 'msg' => $ok ? 'Cập nhật thành công' : 'Lỗi cập nhật']);
    }
    jOut(['ok' => false]);
}

if ($action === 'bulk_delete' || $action === 'delete_single') {
    // Normalize IDs: single delete passes review_id, bulk passes ids[]
    if ($action === 'delete_single') {
        $rid = (int)($_POST['review_id'] ?? 0);
        $ids = $rid > 0 ? [$rid] : [];
    } else {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_filter($ids, fn($x) => $x > 0);
    }

    if (empty($ids)) {
        jOut(['ok' => false, 'msg' => 'Chưa chọn mục nào']);
    }


    // ── BƯỚC 1: Thu thập tất cả ID liên quan (gốc + replies) ──────────────
    $allIds = [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $ithanhloc->prepare(
        "SELECT id FROM ecommerce_product_review WHERE id IN ($placeholders) OR parent_id IN ($placeholders)"
    );
    if ($stmt) {
        $types = str_repeat('i', count($ids) * 2);
        $params = array_merge(array_values($ids), array_values($ids));
        bindParamsDynamic($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $allIds[] = (int)$r['id'];
        $stmt->close();
    }

    if (empty($allIds)) {
        jOut(['ok' => true, 'msg' => 'Dữ liệu đã được xóa hoặc không tồn tại']);
    }

    $idList = implode(',', $allIds);

    // ── BƯỚC 2: Xóa lượt thích của đánh giá ──────────────────────────────
    $ithanhloc->query("DELETE FROM ecommerce_product_review_like WHERE review_id IN ($idList)");

    // ── BƯỚC 3: Xóa comment trên thông báo liên quan ─────────────────────
    $ithanhloc->query(
        "DELETE FROM user_notification_comment WHERE ref_type = 'product_review' AND ref_id IN ($idList)"
    );

    // ── BƯỚC 4: Tìm và xóa toàn bộ thông báo chứa review_id ──────────────
    $notifIds = [];

    // 4a. Tìm qua meta_json LIKE (dạng "review_id":123)
    foreach ($allIds as $rid) {
        $likeStr = '%"review_id":' . $rid . '%';
        $stmtN = $ithanhloc->prepare("SELECT id FROM user_notification WHERE meta_json LIKE ?");
        if ($stmtN) {
            $stmtN->bind_param('s', $likeStr);
            $stmtN->execute();
            $resN = $stmtN->get_result();
            while ($n = $resN->fetch_assoc()) $notifIds[] = (int)$n['id'];
            $stmtN->close();
        }
    }

    // 4b. Tìm qua ref_type = 'product_review' trong notification_comment
    $resNc = $ithanhloc->query(
        "SELECT DISTINCT notification_id FROM user_notification_comment
         WHERE ref_type = 'product_review' AND ref_id IN ($idList)"
    );
    if ($resNc) {
        while ($nc = $resNc->fetch_assoc()) $notifIds[] = (int)$nc['notification_id'];
    }

    if (!empty($notifIds)) {
        $notifIds = array_unique(array_filter($notifIds));
        $notifList = implode(',', $notifIds);

        // Xóa comment trong notification
        $ithanhloc->query("DELETE FROM user_notification_comment WHERE notification_id IN ($notifList)");
        // Xóa like trên notification
        $ithanhloc->query("DELETE FROM user_notification_like WHERE notification_id IN ($notifList)");
        // Xóa trạng thái đã đọc
        $ithanhloc->query("DELETE FROM user_notification_read WHERE notification_id IN ($notifList)");
        // Xóa chính thông báo
        $ithanhloc->query("DELETE FROM user_notification WHERE id IN ($notifList)");
    }

    // ── BƯỚC 5: Xóa user_logs liên quan đến review ───────────────────────
    foreach ($allIds as $rid) {
        $logLike = '%"review_id":' . $rid . '%';
        $stmtL = $ithanhloc->prepare("DELETE FROM user_logs WHERE meta_json LIKE ?");
        if ($stmtL) {
            $stmtL->bind_param('s', $logLike);
            $stmtL->execute();
            $stmtL->close();
        }
    }

    // ── BƯỚC 6: Xóa chính các đánh giá + replies ─────────────────────────
    $ok = $ithanhloc->query("DELETE FROM ecommerce_product_review WHERE id IN ($idList)");

    $count = count(array_unique($ids));
    jOut([
        'ok'  => (bool)$ok,
        'msg' => $ok
            ? "Đã xóa {$count} đánh giá và toàn bộ dữ liệu liên quan"
            : 'Lỗi khi xóa dữ liệu'
    ]);
}


if ($action === 'thread') {
    $rid = (int)($_GET['review_id'] ?? 0);
    // Lấy comment gốc và tất cả reply
    $stmt = $ithanhloc->prepare("SELECT r.*, COALESCE(r.display_name, u.full_name, u.username, 'Khách hàng') as author_name, u.role as author_role
                                FROM ecommerce_product_review r
                                LEFT JOIN users u ON u.id = r.user_id
                                WHERE r.id = ? OR r.parent_id = ?
                                ORDER BY r.created_at ASC");
    if ($stmt) {
        $stmt->bind_param('ii', $rid, $rid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        jOut(['ok' => true, 'items' => $rows]);
    }
    jOut(['ok' => false, 'msg' => 'Lỗi tải hội thoại']);
}

if ($action === 'reply') {
    $rid = (int)($_POST['review_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if (!$content) jOut(['ok' => false, 'msg' => 'Nội dung không được để trống']);
    
    // Lấy product_id từ comment gốc
    $origRes = $ithanhloc->query("SELECT product_id FROM ecommerce_product_review WHERE id = $rid");
    $orig = $origRes ? $origRes->fetch_assoc() : null;
    $pid = $orig['product_id'] ?? 0;
    
    $stmt = $ithanhloc->prepare("INSERT INTO ecommerce_product_review (product_id, user_id, comment, parent_id, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    if ($stmt) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt->bind_param('iisi', $pid, $uid, $content, $rid);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => $ok, 'msg' => $ok ? 'Đã gửi phản hồi' : 'Lỗi khi gửi']);
    }
    jOut(['ok' => false, 'msg' => 'Lỗi kết nối database']);
}

// ── XÓA MỘT REPLY CỤ THỂ (admin) ─────────────────────────────────────────
if ($action === 'delete_reply') {
    $rid = (int)($_POST['review_id'] ?? 0);
    if ($rid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID bình luận']);

    // Thu thập tất cả ID liên quan (review đó + mọi reply con)
    $allIds = [];
    $stmt = $ithanhloc->prepare(
        "SELECT id FROM ecommerce_product_review WHERE id = ? OR parent_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $rid, $rid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $allIds[] = (int)$r['id'];
        $stmt->close();
    }

    if (empty($allIds)) {
        jOut(['ok' => true, 'msg' => 'Đã xóa hoặc không tồn tại']);
    }

    $idList = implode(',', $allIds);

    // Xóa likes
    $ithanhloc->query("DELETE FROM ecommerce_product_review_like WHERE review_id IN ($idList)");
    // Xóa thông báo liên quan (nếu bảng tồn tại)
    $ithanhloc->query("DELETE FROM user_notification_comment WHERE ref_type = 'product_review' AND ref_id IN ($idList)");
    // Xóa chính các review
    $ok = $ithanhloc->query("DELETE FROM ecommerce_product_review WHERE id IN ($idList)");

    jOut([
        'ok'  => (bool)$ok,
        'msg' => $ok ? 'Đã xóa bình luận' : 'Lỗi khi xóa',
    ]);
}
