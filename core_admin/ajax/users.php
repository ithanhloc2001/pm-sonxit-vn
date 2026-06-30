<?php
/**
 * AJAX Backend: Quản lý tài khoản người dùng
 * Endpoint: /core_admin/ajax/users.php
 */
require_once __DIR__ . '/../_admin_guard.php';

// ─── Helper: clean JSON output ───────────────────────────────────────────────
function jOut(array $data): void {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Diễn giải lỗi UNIQUE index (email/phone) thành thông báo thân thiện cho admin
function users_dup_message(string $dbError, string $fallback): string {
    if (stripos($dbError, 'uk_users_email') !== false) return 'Email đã được sử dụng. Vui lòng dùng email khác.';
    if (stripos($dbError, 'uk_users_phone') !== false) return 'Số điện thoại đã được sử dụng. Vui lòng dùng số khác.';
    return $fallback;
}

// ─── Helper: wallet update (transaction-safe) ─────────────────────────────────
function applyWalletUpdate(mysqli $db, int $userId, string $mode, int $amount, string $note): array {
    $mode = trim($mode);
    $note = trim($note);

    if ($userId <= 0)  return ['ok' => false, 'msg' => 'Người dùng không hợp lệ'];
    if ($note === '')  return ['ok' => false, 'msg' => 'Vui lòng nhập lý do'];
    if (!in_array($mode, ['gift', 'refund', 'deduct', 'set_balance'], true))
        return ['ok' => false, 'msg' => 'Hành động không hợp lệ'];

    if ($mode === 'set_balance') {
        if ($amount < 0) return ['ok' => false, 'msg' => 'Số dư mới không hợp lệ'];
    } else {
        if ($amount <= 0) return ['ok' => false, 'msg' => 'Số xu không hợp lệ'];
    }

    $db->begin_transaction();
    try {
        $stmtCur = $db->prepare('SELECT COALESCE(balance,0) AS balance FROM users WHERE id=? LIMIT 1 FOR UPDATE');
        if (!$stmtCur) throw new Exception('Không thể đọc số dư hiện tại');
        $stmtCur->bind_param('i', $userId);
        $stmtCur->execute();
        $row = $stmtCur->get_result()->fetch_assoc();
        $stmtCur->close();

        if (!$row) throw new Exception('Không tìm thấy tài khoản');

        $current    = (int)($row['balance'] ?? 0);
        $delta      = 0;
        $newBalance = $current;
        $txType     = 'admin_adjust';

        if ($mode === 'gift') {
            $delta = $amount; $newBalance = $current + $amount; $txType = 'admin_gift';
        } elseif ($mode === 'refund') {
            $delta = $amount; $newBalance = $current + $amount; $txType = 'admin_refund';
        } elseif ($mode === 'deduct') {
            if ($current < $amount) throw new Exception('Số dư hiện tại không đủ để giảm');
            $delta = -$amount; $newBalance = $current - $amount; $txType = 'admin_deduct';
        } else { // set_balance
            $newBalance = $amount; $delta = $newBalance - $current; $txType = 'admin_set_balance';
        }

        $stmtUp = $db->prepare('UPDATE users SET balance=? WHERE id=?');
        if (!$stmtUp) throw new Exception('Không thể cập nhật số dư');
        $stmtUp->bind_param('ii', $newBalance, $userId);
        if (!$stmtUp->execute()) {
            $err = $stmtUp->error; $stmtUp->close();
            throw new Exception('Không thể cập nhật số dư: ' . $err);
        }
        $stmtUp->close();

        $stmtTx = $db->prepare('INSERT INTO user_balance_log (user_id, ref_order_id, type, amount, note) VALUES (?, NULL, ?, ?, ?)');
        if ($stmtTx) {
            $stmtTx->bind_param('isis', $userId, $txType, $delta, $note);
            $stmtTx->execute();
            $stmtTx->close();
        }

        $db->commit();

        $deltaLabel = ($delta >= 0 ? '+' : '') . (string)$delta;
        app_user_notify_template($db, $userId, 'xu_change', [
            'xu_delta'   => $deltaLabel,
            'xu_balance' => (string)$newBalance,
            'note'       => $note,
            'time'       => date('Y-m-d H:i:s')
        ]);

        return ['ok' => true, 'new_balance' => $newBalance];
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
}

// ─── ROUTE: GET ?ajax=list → danh sách users ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    $res = $ithanhloc->query(
        'SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role, u.created_at, u.avatar,
                COALESCE(u.balance,0) AS xu_balance,
                (SELECT meta_json FROM user_logs WHERE user_id = u.id AND action = "register" ORDER BY id ASC LIMIT 1) AS reg_meta
         FROM users u
         ORDER BY u.id DESC'
    );
    if (!$res) jOut(['ok' => false, 'msg' => 'Lỗi truy vấn: ' . $ithanhloc->error]);
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    jOut(['ok' => true, 'data' => $data]);
}

// ─── ROUTE: POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    jOut(['ok' => false, 'msg' => 'Bad request']);
}

app_verify_csrf();

$act = trim((string)($_POST['action'] ?? ''));

// ── action: wallet_update / adjust_xu ────────────────────────────────────────
if ($act === 'wallet_update' || $act === 'adjust_xu') {
    $id = intval($_POST['id'] ?? 0);

    if ($act === 'adjust_xu') {
        $delta = intval($_POST['delta'] ?? 0);
        $note  = trim((string)($_POST['note'] ?? 'Điều chỉnh xu bởi admin'));
        if ($delta === 0) jOut(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ']);
        $mode   = $delta > 0 ? 'gift' : 'deduct';
        $amount = abs($delta);
        jOut(applyWalletUpdate($ithanhloc, $id, $mode, $amount, $note));
    }

    $mode   = trim((string)($_POST['mode'] ?? 'gift'));
    $amount = intval($_POST['amount'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));
    jOut(applyWalletUpdate($ithanhloc, $id, $mode, $amount, $note));
}

// ── action: save (thêm mới / cập nhật user) ──────────────────────────────────
if ($act === 'save') {
    $id       = intval($_POST['id'] ?? 0);
    $u        = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $avatar   = trim($_POST['avatar'] ?? '');
    $role     = trim($_POST['role'] ?? 'user');
    $p        = $_POST['password'] ?? '';

    // Upload avatar
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar_file']['error'] !== UPLOAD_ERR_OK) {
            $maxSize = ini_get('upload_max_filesize');
            jOut(['ok' => false, 'msg' => "Lỗi tải lên (Mã: {$_FILES['avatar_file']['error']}). Giới hạn: $maxSize"]);
        }
        $file    = $_FILES['avatar_file'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) jOut(['ok' => false, 'msg' => 'Định dạng ảnh không hỗ trợ (JPG, PNG, GIF, WEBP).']);
        $uploadDir = __DIR__ . '/../../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext         = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = 'avatar_' . time() . '_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName))
            jOut(['ok' => false, 'msg' => 'Không thể lưu file ảnh. Kiểm tra quyền thư mục uploads.']);
        $avatar = 'uploads/avatars/' . $newFileName;
        if (function_exists('media_publish_local_file')) {
            media_publish_local_file($avatar);
        }
    }

    // Validation
    if ($u === '') jOut(['ok' => false, 'msg' => 'Thiếu username']);
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $u))
        jOut(['ok' => false, 'msg' => 'Username không hợp lệ (3-30 ký tự, chữ/số/gạch dưới).']);
    if ($fullName !== '' && (preg_match('/[<>"\'=;()]/', $fullName) || stripos($fullName, 'script') !== false))
        jOut(['ok' => false, 'msg' => 'Họ tên chứa ký tự không hợp lệ.']);
    if (!in_array($role, ['user', 'admin'], true)) $role = 'user';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        jOut(['ok' => false, 'msg' => 'Email không hợp lệ.']);

    // ===== Chặn trùng username / email / số điện thoại =====
    // ($id > 0 -> loại trừ chính tài khoản đang sửa; $id = 0 -> tạo mới, không loại trừ)
    $dupUser = $ithanhloc->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    $dupUser->bind_param('si', $u, $id);
    $dupUser->execute();
    if ($dupUser->get_result()->fetch_assoc()) { $dupUser->close(); jOut(['ok' => false, 'msg' => 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.']); }
    $dupUser->close();

    if ($email !== '') {
        $dupEmail = $ithanhloc->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $dupEmail->bind_param('si', $email, $id);
        $dupEmail->execute();
        if ($dupEmail->get_result()->fetch_assoc()) { $dupEmail->close(); jOut(['ok' => false, 'msg' => 'Email đã được sử dụng. Vui lòng dùng email khác.']); }
        $dupEmail->close();
    }

    if ($phone !== '') {
        $digits = preg_replace('/\D+/', '', $phone);
        $p84 = $digits; $p0 = $digits;
        if (strpos($digits, '84') === 0) { if (strlen($digits) > 2) $p0 = '0' . substr($digits, 2); }
        elseif (strpos($digits, '0') === 0) { if (strlen($digits) > 1) $p84 = '84' . substr($digits, 1); }
        $dupPhone = $ithanhloc->prepare('SELECT id FROM users WHERE id <> ? AND phone IS NOT NULL AND phone <> "" AND (phone = ? OR phone = ? OR phone = ?) LIMIT 1');
        $dupPhone->bind_param('isss', $id, $phone, $p84, $p0);
        $dupPhone->execute();
        if ($dupPhone->get_result()->fetch_assoc()) { $dupPhone->close(); jOut(['ok' => false, 'msg' => 'Số điện thoại đã được sử dụng. Vui lòng dùng số khác.']); }
        $dupPhone->close();
    }

    if ($id > 0) {
        // Update
        if ($p !== '') {
            $hash = password_hash($p, PASSWORD_BCRYPT);
            $stmt = $ithanhloc->prepare('UPDATE users SET username=?, password=?, full_name=?, email=?, phone=?, avatar=?, role=? WHERE id=?');
            $stmt->bind_param('sssssssi', $u, $hash, $fullName, $email, $phone, $avatar, $role, $id);
        } else {
            $stmt = $ithanhloc->prepare('UPDATE users SET username=?, full_name=?, email=?, phone=?, avatar=?, role=? WHERE id=?');
            $stmt->bind_param('ssssssi', $u, $fullName, $email, $phone, $avatar, $role, $id);
        }
        try { $ok = $stmt->execute(); $err = ''; }
        catch (mysqli_sql_exception $e) { $ok = false; $err = users_dup_message($e->getMessage(), 'Lỗi cập nhật: ' . $e->getMessage()); }
        $stmt->close();
        jOut($ok ? ['ok' => true] : ['ok' => false, 'msg' => $err]);
    } else {
        // Insert
        if ($p === '') jOut(['ok' => false, 'msg' => 'Mật khẩu không được để trống khi tạo mới.']);
        $hash = password_hash($p, PASSWORD_BCRYPT);
        $stmt = $ithanhloc->prepare('INSERT INTO users (username, password, full_name, email, phone, avatar, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssss', $u, $hash, $fullName, $email, $phone, $avatar, $role);
        try { $ok = $stmt->execute(); $err = ''; }
        catch (mysqli_sql_exception $e) { $ok = false; $err = users_dup_message($e->getMessage(), 'Lỗi tạo tài khoản: ' . $e->getMessage()); }
        $newUserId = (int)$ithanhloc->insert_id; $stmt->close();
        if (!$ok) jOut(['ok' => false, 'msg' => $err]);
        if (function_exists('app_user_log')) {
            app_user_log($ithanhloc, $newUserId, 'register', 'Đăng ký tài khoản (Admin tạo)', ['method' => 'password']);
        }
        jOut(['ok' => true, 'new_id' => $newUserId]);
    }
}

// ── action: delete ────────────────────────────────────────────────────────────
if ($act === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === 1) jOut(['ok' => false, 'msg' => 'Không thể xóa tài khoản admin mặc định.']);
    $stmt = $ithanhloc->prepare('DELETE FROM users WHERE id=? AND id<>1');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute(); $err = $stmt->error; $stmt->close();
    jOut($ok ? ['ok' => true] : ['ok' => false, 'msg' => 'Lỗi xóa: ' . $err]);
}

// ── action: delete_multi ──────────────────────────────────────────────────────
if ($act === 'delete_multi') {
    $ids     = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $safeIds = array_filter(array_map('intval', $ids), fn($i) => $i > 1);
    if (!$safeIds) jOut(['ok' => false, 'msg' => 'Không có tài khoản hợp lệ để xóa.']);
    $idList = implode(',', $safeIds);
    $ok     = $ithanhloc->query("DELETE FROM users WHERE id IN ({$idList}) AND id<>1") === true;
    jOut(['ok' => $ok]);
}

// ── action: gift_xu_multi ─────────────────────────────────────────────────────
if ($act === 'gift_xu_multi') {
    $ids    = $_POST['ids'] ?? [];
    $amount = intval($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? 'Quà tặng hàng loạt từ admin');
    if (!is_array($ids) || !$ids || $amount <= 0)
        jOut(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ']);
    $safeIds = array_map('intval', $ids);
    $idList  = implode(',', $safeIds);

    $ithanhloc->begin_transaction();
    try {
        $ithanhloc->query("UPDATE users SET balance = COALESCE(balance,0) + $amount WHERE id IN ($idList)");
        foreach ($safeIds as $uid) {
            $stmt = $ithanhloc->prepare('INSERT INTO user_balance_log (user_id, type, amount, note) VALUES (?, "admin_gift", ?, ?)');
            $stmt->bind_param('iis', $uid, $amount, $note);
            $stmt->execute(); $stmt->close();
            app_user_notify_template($ithanhloc, $uid, 'xu_change', [
                'xu_delta' => '+' . $amount, 'xu_balance' => 'Đã cập nhật',
                'note' => $note, 'time' => date('Y-m-d H:i:s')
            ]);
        }
        $ithanhloc->commit();
        jOut(['ok' => true]);
    } catch (Exception $e) {
        $ithanhloc->rollback();
        jOut(['ok' => false, 'msg' => $e->getMessage()]);
    }
}

// ── action: set_role_multi ────────────────────────────────────────────────────
if ($act === 'set_role_multi') {
    $ids  = $_POST['ids'] ?? [];
    $role = trim($_POST['role'] ?? 'user');
    if (!is_array($ids) || !$ids || !in_array($role, ['user', 'admin'], true))
        jOut(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ']);
    $safeIds = array_filter(array_map('intval', $ids), fn($i) => $i > 1);
    if (!$safeIds) jOut(['ok' => false, 'msg' => 'Không thể thay đổi quyền tài khoản hệ thống.']);
    $idList = implode(',', $safeIds);
    $ok     = $ithanhloc->query("UPDATE users SET role = '$role' WHERE id IN ($idList) AND id<>1") === true;
    jOut(['ok' => $ok]);
}

jOut(['ok' => false, 'msg' => 'Action không xác định.']);
