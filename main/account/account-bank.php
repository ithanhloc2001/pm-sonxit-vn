<?php
require_once __DIR__ . '/../../config.php';

set_exception_handler(function(Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Lỗi server: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
}

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;

// Auto-create tables if missing (idempotent)
$ithanhloc->query("CREATE TABLE IF NOT EXISTS `user_bank_accounts` (
    `id`            INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`       INT(11) NOT NULL,
    `type`          VARCHAR(20) NOT NULL DEFAULT 'bank',
    `bank_code`     VARCHAR(50) DEFAULT NULL,
    `bank_name`     VARCHAR(120) DEFAULT NULL,
    `bank_branch`   VARCHAR(120) DEFAULT NULL,
    `account_no`    VARCHAR(100) DEFAULT NULL,
    `account_last4` VARCHAR(10) DEFAULT NULL,
    `account_owner` VARCHAR(120) DEFAULT NULL,
    `card_name`     VARCHAR(120) DEFAULT NULL,
    `card_last4`    VARCHAR(10) DEFAULT NULL,
    `card_exp`      VARCHAR(10) DEFAULT NULL,
    `is_default`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Patch: if the table already existed without AUTO_INCREMENT, fix it now
$_colCheck = $ithanhloc->query("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_bank_accounts' AND COLUMN_NAME = 'id'");
$_colRow = $_colCheck ? $_colCheck->fetch_assoc() : null;
if ($_colRow && strpos(strtolower($_colRow['EXTRA'] ?? ''), 'auto_increment') === false) {
    $ithanhloc->query('DELETE FROM user_bank_accounts WHERE id = 0');
    $ithanhloc->query('ALTER TABLE user_bank_accounts MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT');
}
unset($_colCheck, $_colRow);

$_colCheckLog = $ithanhloc->query("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_bank_log' AND COLUMN_NAME = 'id'");
$_colRowLog = $_colCheckLog ? $_colCheckLog->fetch_assoc() : null;
if ($_colRowLog && strpos(strtolower($_colRowLog['EXTRA'] ?? ''), 'auto_increment') === false) {
    $ithanhloc->query('DELETE FROM user_bank_log WHERE id = 0');
    $ithanhloc->query('ALTER TABLE user_bank_log MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT');
}
unset($_colCheckLog, $_colRowLog);


$ithanhloc->query("CREATE TABLE IF NOT EXISTS `user_bank_log` (
    `id`            INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`       INT(11) NOT NULL,
    `action`        VARCHAR(50) NOT NULL,
    `type`          VARCHAR(50) DEFAULT NULL,
    `bank_code`     VARCHAR(100) DEFAULT NULL,
    `account_last4` VARCHAR(10) DEFAULT NULL,
    `card_last4`    VARCHAR(10) DEFAULT NULL,
    `ip`            VARCHAR(100) DEFAULT NULL,
    `user_agent`    VARCHAR(300) DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Helper to log audit events into user_bank_log
function logBankAudit(
    mysqli $ithanhloc,
    int $userId,
    string $action,
    string $type,
    string $bankCode = '',
    string $accountLast4 = '',
    string $cardLast4 = ''
): void {
    $stmt = $ithanhloc->prepare('INSERT INTO `user_bank_log` (user_id, action, type, bank_code, account_last4, card_last4, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        $ip = get_client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        $stmt->bind_param('isssssss', $userId, $action, $type, $bankCode, $accountLast4, $cardLast4, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));

if ($action === 'save') {
    $type = strtolower(trim((string)($_POST['type'] ?? $_GET['type'] ?? 'bank')));
    if (!in_array($type, ['card', 'bank'], true)) {
        $type = 'bank';
    }

    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $existing = null;
    if ($id > 0) {
        $stmtEx = $ithanhloc->prepare('SELECT * FROM user_bank_accounts WHERE id = ? AND user_id = ? LIMIT 1');
        if ($stmtEx) {
            $stmtEx->bind_param('ii', $id, $uid);
            $stmtEx->execute();
            $existing = $stmtEx->get_result()->fetch_assoc();
            $stmtEx->close();
        }
        if (!$existing) {
            jOut(['ok' => false, 'msg' => 'Tài khoản/thẻ không tồn tại hoặc không thuộc sở hữu của bạn']);
        }
    }

    $makeDefault = (int)($_POST['is_default'] ?? 0) === 1;

    $bankCode     = trim((string)($_POST['bank_code'] ?? ''));
    $bankName     = trim((string)($_POST['bank_name'] ?? ''));
    $bankBranch   = trim((string)($_POST['bank_branch'] ?? ''));
    $accountNo    = trim((string)($_POST['account_no'] ?? ''));
    $accountOwner = trim((string)($_POST['account_owner'] ?? ''));
    
    // Credit card specific fields
    $cardName      = trim((string)($_POST['card_name'] ?? ''));
    $rawCardNumber = (string)($_POST['card_number'] ?? '');
    $cardExp       = trim((string)($_POST['card_exp'] ?? ''));

    $cardLast4 = '';
    $accountLast4 = '';
    $accountMasked = '';

    if ($type === 'card') {
        if ($id > 0 && (strpos($rawCardNumber, '*') !== false || trim($rawCardNumber) === '')) {
            $cardLast4 = $existing['card_last4'];
        } else {
            $cardNumber = preg_replace('/\D+/', '', $rawCardNumber);
            $cardLast4 = '';
            if ($cardNumber !== '') {
                $cardLast4 = substr($cardNumber, -4);
            }
        }
        if ($cardName === '' || $cardLast4 === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu thông tin thẻ']);
        }
    } else {
        if ($id > 0 && (strpos($accountNo, '*') !== false || $accountNo === '')) {
            $accountNo     = $existing['account_no'];
            $accountLast4  = $existing['account_last4'];
            $accountMasked = $existing['account_no'];
        } else {
            $accountDigits = preg_replace('/\D+/', '', $accountNo);
            $accountLast4  = $accountDigits !== '' ? substr($accountDigits, -4) : '';
            $accountMasked = $accountLast4 !== '' ? '**** ' . $accountLast4 : '';
        }
        if ($bankName === '' || $accountNo === '' || $accountOwner === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu thông tin tài khoản ngân hàng']);
        }
    }

    // Duplicate check
    if ($type === 'bank') {
        $stmtDup = $ithanhloc->prepare('SELECT id FROM user_bank_accounts WHERE user_id = ? AND type = ? AND bank_code = ? AND account_last4 = ? AND id != ? LIMIT 1');
        if ($stmtDup) {
            $stmtDup->bind_param('isssi', $uid, $type, $bankCode, $accountLast4, $id);
            $stmtDup->execute();
            $dupRow = $stmtDup->get_result()->fetch_assoc();
            $stmtDup->close();
            if ($dupRow) {
                jOut(['ok' => false, 'msg' => 'Tài khoản ngân hàng này đã được liên kết trước đó']);
            }
        }
    } else {
        $stmtDup = $ithanhloc->prepare('SELECT id FROM user_bank_accounts WHERE user_id = ? AND type = ? AND card_last4 = ? AND id != ? LIMIT 1');
        if ($stmtDup) {
            $stmtDup->bind_param('issi', $uid, $type, $cardLast4, $id);
            $stmtDup->execute();
            $dupRow = $stmtDup->get_result()->fetch_assoc();
            $stmtDup->close();
            if ($dupRow) {
                jOut(['ok' => false, 'msg' => 'Thẻ tín dụng này đã được liên kết trước đó']);
            }
        }
    }

    $shouldDefault = $makeDefault;
    if (!$makeDefault) {
        $stmtCheck = $ithanhloc->prepare('SELECT id FROM user_bank_accounts WHERE user_id = ? AND type = ? AND is_default = 1 AND id != ? LIMIT 1');
        if ($stmtCheck) {
            $stmtCheck->bind_param('isi', $uid, $type, $id);
            $stmtCheck->execute();
            $row = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();
            if (!$row) {
                $shouldDefault = true;
            }
        }
    }

    if ($shouldDefault) {
        $stmtClear = $ithanhloc->prepare('UPDATE user_bank_accounts SET is_default = 0 WHERE user_id = ? AND type = ?');
        if ($stmtClear) {
            $stmtClear->bind_param('is', $uid, $type);
            $stmtClear->execute();
            $stmtClear->close();
        }
    }

    if ($id > 0) {
        $stmt = $ithanhloc->prepare('UPDATE user_bank_accounts SET bank_code = ?, bank_name = ?, bank_branch = ?, account_no = ?, account_last4 = ?, account_owner = ?, card_name = ?, card_last4 = ?, card_exp = ?, is_default = ? WHERE id = ? AND user_id = ?');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không thể cập nhật thông tin']);
        }
        $isDefaultValue = $shouldDefault ? 1 : 0;
        $stmt->bind_param(
            'sssssssssiii',
            $bankCode,
            $bankName,
            $bankBranch,
            $accountMasked,
            $accountLast4,
            $accountOwner,
            $cardName,
            $cardLast4,
            $cardExp,
            $isDefaultValue,
            $id,
            $uid
        );
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
    } else {
        $stmt = $ithanhloc->prepare('INSERT INTO user_bank_accounts (user_id, type, bank_code, bank_name, bank_branch, account_no, account_last4, account_owner, card_name, card_last4, card_exp, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không thể lưu thông tin']);
        }
        $isDefaultValue = $shouldDefault ? 1 : 0;
        $stmt->bind_param(
            'issssssssssi',
            $uid,
            $type,
            $bankCode,
            $bankName,
            $bankBranch,
            $accountMasked,
            $accountLast4,
            $accountOwner,
            $cardName,
            $cardLast4,
            $cardExp,
            $isDefaultValue
        );
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
    }

    logBankAudit($ithanhloc, $uid, 'save', $type, $bankCode, $accountLast4, $cardLast4);

    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã lưu thông tin ngân hàng' : $err]);
}

if ($action === 'list') {
    $stmt = $ithanhloc->prepare('SELECT id, type, bank_code, bank_name, bank_branch, account_no, account_last4, account_owner, card_name, card_last4, card_exp, is_default, created_at FROM user_bank_accounts WHERE user_id = ? ORDER BY is_default DESC, id DESC');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể tải dữ liệu']);
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    jOut(['ok' => true, 'data' => $rows]);
}

if ($action === 'set_default') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu thông tin']);
    }
    
    $stmt = $ithanhloc->prepare('SELECT id, type, bank_code, account_last4, card_last4 FROM user_bank_accounts WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể đọc dữ liệu']);
    }
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy tài khoản']);
    }
    
    $type      = (string)($row['type'] ?? 'bank');
    $stmtClear = $ithanhloc->prepare('UPDATE user_bank_accounts SET is_default = 0 WHERE user_id = ? AND type = ?');
    if ($stmtClear) {
        $stmtClear->bind_param('is', $uid, $type);
        $stmtClear->execute();
        $stmtClear->close();
    }
    
    $stmtSet = $ithanhloc->prepare('UPDATE user_bank_accounts SET is_default = 1 WHERE id = ? AND user_id = ?');
    if ($stmtSet) {
        $stmtSet->bind_param('ii', $id, $uid);
        $stmtSet->execute();
        $stmtSet->close();
    }

    logBankAudit($ithanhloc, $uid, 'set_default', $type, (string)($row['bank_code'] ?? ''), (string)($row['account_last4'] ?? ''), (string)($row['card_last4'] ?? ''));

    jOut(['ok' => true, 'msg' => 'Đã đặt mặc định']);
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu thông tin']);
    }
    
    $stmt = $ithanhloc->prepare('SELECT id, type, bank_code, account_last4, card_last4, is_default FROM user_bank_accounts WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể đọc dữ liệu']);
    }
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy tài khoản']);
    }

    $stmtDel = $ithanhloc->prepare('DELETE FROM user_bank_accounts WHERE id = ? AND user_id = ?');
    if ($stmtDel) {
        $stmtDel->bind_param('ii', $id, $uid);
        $stmtDel->execute();
        $stmtDel->close();
    }

    if ((int)$row['is_default'] === 1) {
        $type     = (string)($row['type'] ?? 'bank');
        $stmtPick = $ithanhloc->prepare('SELECT id FROM user_bank_accounts WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT 1');
        if ($stmtPick) {
            $stmtPick->bind_param('is', $uid, $type);
            $stmtPick->execute();
            $next = $stmtPick->get_result()->fetch_assoc();
            $stmtPick->close();
            if ($next) {
                $stmtSet = $ithanhloc->prepare('UPDATE user_bank_accounts SET is_default = 1 WHERE id = ? AND user_id = ?');
                if ($stmtSet) {
                    $stmtSet->bind_param('ii', $next['id'], $uid);
                    $stmtSet->execute();
                    $stmtSet->close();
                }
            }
        }
    }

    logBankAudit($ithanhloc, $uid, 'delete', (string)($row['type'] ?? 'bank'), (string)($row['bank_code'] ?? ''), (string)($row['account_last4'] ?? ''), (string)($row['card_last4'] ?? ''));

    jOut(['ok' => true, 'msg' => 'Đã xoá tài khoản']);
}

if ($action === 'admin_list') {
    if ($role !== 'admin') {
        jOut(['ok' => false, 'msg' => 'Không có quyền truy cập']);
    }
    
    $sql = "SELECT b.id, b.user_id, b.type, b.bank_code, b.bank_name, b.bank_branch, b.account_no, b.account_last4, b.account_owner, b.card_name, b.card_last4, b.card_exp, b.is_default, b.created_at,
                   u.username, u.full_name, u.email
            FROM user_bank_accounts b
            LEFT JOIN users u ON u.id = b.user_id
            ORDER BY b.created_at DESC";
            
    $res  = $ithanhloc->query($sql);
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    jOut(['ok' => true, 'data' => $rows]);
}

// Default action: Save user profile main bank settings (direct update to users table)
function sanitizeText(string $value, int $maxLen): string {
    $value = trim($value);
    if ($value === '') return '';
    if (mb_strlen($value, 'UTF-8') > $maxLen) {
        $value = mb_substr($value, 0, $maxLen, 'UTF-8');
    }
    return $value;
}

$bankName          = sanitizeText((string)($_POST['bank_name'] ?? ''), 120);
$bankAccountName   = sanitizeText((string)($_POST['bank_account_name'] ?? ''), 120);
$bankAccountNumber = sanitizeText(preg_replace('/[^0-9A-Za-z]/', '', (string)($_POST['bank_account_number'] ?? '')), 64);
$walletMomo        = sanitizeText(preg_replace('/[^0-9]/', '', (string)($_POST['wallet_momo'] ?? '')), 32);
$walletZaloPay     = sanitizeText((string)($_POST['wallet_zalopay'] ?? ''), 64);
$walletVnpay       = sanitizeText((string)($_POST['wallet_vnpay'] ?? ''), 64);
$bankIsDefault     = isset($_POST['bank_is_default']) ? 1 : 0;

// Chỉ UPDATE những cột thực sự tồn tại trong bảng users
$_walletFields = ['bank_name', 'bank_account_name', 'bank_account_number', 'bank_is_default', 'wallet_momo', 'wallet_zalopay', 'wallet_vnpay'];
$_existRes = $ithanhloc->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('bank_name','bank_account_name','bank_account_number','bank_is_default','wallet_momo','wallet_zalopay','wallet_vnpay')");
$_existCols = [];
if ($_existRes) { while ($r = $_existRes->fetch_assoc()) { $_existCols[] = $r['COLUMN_NAME']; } }

if (empty($_existCols)) {
    jOut(['ok' => false, 'msg' => 'Bảng người dùng chưa hỗ trợ thông tin ngân hàng/ví.']);
}

$_allValues = [
    'bank_name'           => $bankName,
    'bank_account_name'   => $bankAccountName,
    'bank_account_number' => $bankAccountNumber,
    'bank_is_default'     => $bankIsDefault,
    'wallet_momo'         => $walletMomo,
    'wallet_zalopay'      => $walletZaloPay,
    'wallet_vnpay'        => $walletVnpay,
];

$_sets   = [];
$_params = [];
$_types  = '';
foreach ($_existCols as $_col) {
    $_sets[]   = "`$_col` = ?";
    $_params[] = $_allValues[$_col];
    $_types   .= ($_col === 'bank_is_default') ? 'i' : 's';
}
$_params[] = $uid;
$_types   .= 'i';

$stmt = $ithanhloc->prepare('UPDATE users SET ' . implode(', ', $_sets) . ' WHERE id = ? LIMIT 1');
if (!$stmt) {
    jOut(['ok' => false, 'msg' => 'Không thể lưu thông tin ngân hàng/ví']);
}
$stmt->bind_param($_types, ...$_params);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    jOut(['ok' => false, 'msg' => 'Không thể lưu thông tin ngân hàng/ví']);
}

app_user_log($ithanhloc, $uid, 'bank_update', 'Cập nhật ngân hàng/ví');
app_user_notify_structured($ithanhloc, $uid, [
    'title'    => 'Cập nhật thanh toán',
    'content'  => 'Bạn vừa cập nhật thông tin ngân hàng/ví thanh toán.',
    'type'     => 'system',
    'link'     => '#',
    'template' => 'tpl4',
    'meta'     => ['module' => 'system', 'event' => 'bank_wallet_updated', 'status' => 'success'],
]);

jOut([
    'ok'  => true,
    'msg' => 'Đã lưu thiết lập ngân hàng / ví',
    'data' => array_intersect_key($_allValues, array_flip($_existCols)),
]);
