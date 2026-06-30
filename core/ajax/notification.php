<?php
require_once __DIR__ . '/../../config.php';

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
$uid    = (int)($_SESSION['user_id'] ?? 0);

if ($uid <= 0) {
    jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
}

/**
 * Đếm số thông báo chưa đọc của user
 */
function unread_count(mysqli $ithanhloc, int $userId): int {
    $count = 0;
    // Gộp: tất cả thông báo (hệ thống + khuyến mãi) nằm chung bảng user_notification.
    // - Thông báo cá nhân (user_id<>0): chưa đọc = is_read=0.
    // - Thông báo chung (user_id=0): chưa đọc = chưa có bản ghi trong user_notification_read.
    $sql = "SELECT (
        (SELECT COUNT(*) FROM user_notification WHERE user_id = ? AND COALESCE(is_read, 0) = 0 AND COALESCE(is_active, 1) = 1)
        +
        (SELECT COUNT(*) FROM user_notification n
         LEFT JOIN user_notification_read r ON r.notification_id = n.id AND r.user_id = ?
         WHERE n.user_id = 0 AND COALESCE(n.is_active, 1) = 1 AND r.notification_id IS NULL)
    ) as c";

    $stmt = $ithanhloc->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            $count = (int)($row['c'] ?? 0);
            $res->close();
        }
        $stmt->close();
    }
    return $count;
}

if ($action === 'list') {
    $limit      = max(1, min(100, intval($_REQUEST['limit'] ?? 20)));
    $offset     = max(0, intval($_REQUEST['offset'] ?? 0));
    $typeFilter = strtolower(trim((string)($_REQUEST['type'] ?? '')));
    $onlyUnread = (int)($_REQUEST['unread'] ?? 0) === 1;

    $whereUnread = '';
    if ($onlyUnread) {
        $whereUnread = ' AND ((n.user_id = 0 AND r.notification_id IS NULL) OR (n.user_id <> 0 AND COALESCE(n.is_read, 0) = 0))';
    }

    $sourceFilter = strtolower(trim((string)($_REQUEST['source'] ?? '')));

    // Sau khi gộp: chỉ còn 1 bảng user_notification.
    // 'table_source' được suy ra từ type (promotion/promo/voucher/coupon = promo).
    $promoTypes = "('promotion', 'promo', 'voucher', 'coupon')";

    // Lọc theo type được người dùng chọn
    $whereType = "";
    if ($typeFilter !== '') {
        $safeType = $ithanhloc->real_escape_string($typeFilter);
        if ($typeFilter === 'order') {
            $whereType = " AND LOWER(n.type) = 'order'";
        } elseif ($typeFilter === 'system') {
            $whereType = " AND LOWER(n.type) IN ('system', 'warning', 'alert', 'info')";
        } elseif (in_array($typeFilter, ['promo', 'promotion', 'voucher', 'coupon'], true)) {
            $whereType = " AND LOWER(n.type) IN {$promoTypes}";
        } else {
            $whereType = " AND LOWER(n.type) = '{$safeType}'";
        }
    }

    // Lọc theo nguồn (system / promo) — thay cho việc tách 2 bảng trước đây
    $whereSource = "";
    if ($sourceFilter === 'system') {
        $whereSource = " AND LOWER(n.type) NOT IN {$promoTypes}";
    } elseif ($sourceFilter === 'promo') {
        $whereSource = " AND LOWER(n.type) IN {$promoTypes}";
    }

    $sql = "SELECT n.id, n.user_id, n.title, n.body, n.type, n.link, n.meta_json, n.created_at,
               CASE WHEN n.user_id = 0 THEN (r.read_at IS NOT NULL) ELSE (COALESCE(n.is_read, 0) = 1) END AS read_state,
               CASE WHEN LOWER(n.type) IN {$promoTypes} THEN 'promo' ELSE 'system' END AS table_source
        FROM user_notification n
        LEFT JOIN user_notification_read r ON r.notification_id = n.id AND r.user_id = ?
        WHERE (n.user_id = ? OR n.user_id = 0) AND COALESCE(n.is_active, 1) = 1
        AND (n.send_at IS NULL OR n.send_at <= NOW())
        {$whereType} {$whereSource} {$whereUnread}
        ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
    $stmt = $ithanhloc->prepare($sql);
    $stmt->bind_param('iiii', $uid, $uid, $limit, $offset);

    $rows = [];
    if ($stmt) {
        $stmt->execute();
        $resList = $stmt->get_result();
        $rows    = $resList ? $resList->fetch_all(MYSQLI_ASSOC) : [];
        if ($resList) {
            $resList->close();
        }
        $stmt->close();
    }

    foreach ($rows as &$row) {
        $row['read_state'] = (int)$row['read_state'];

        // Format time
        $ts                    = strtotime($row['created_at']);
        $row['created_at_fmt'] = $ts ? date('H:i d-m-Y', $ts) : $row['created_at'];

        // Type aliasing for UI
        $t     = strtolower(trim($row['type']));
        $alias = [
            'promo'    => 'promotion',
            'voucher'  => 'promotion',
            'coupon'   => 'promotion',
            'profile'  => 'account',
            'bank'     => 'account',
            'login'    => 'account',
            'security' => 'account',
            'warning'  => 'account',
            'alert'    => 'system',
            'support'  => 'complaint',
            'ticket'   => 'complaint',
        ];
        $row['type_resolved'] = $alias[$t] ?? ($t ?: 'info');

        // Parse notx_v2 payload
        $payload = json_decode($row['body'] ?? '', true);
        if (is_array($payload) && ($payload['schema'] ?? '') === 'notx_v2') {
            if (!empty($payload['title'])) {
                $row['title'] = html_entity_decode($payload['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            // Lấy nội dung hiển thị: ưu tiên subtitle, nếu không có lấy content
            $bodyTxt = trim((string)($payload['subtitle'] ?? ''));
            if ($bodyTxt === '') {
                $bodyTxt = trim((string)($payload['content'] ?? ''));
            }
            $row['body_text'] = html_entity_decode($bodyTxt, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (!empty($payload['thumb_image'])) {
                $row['thumb_image'] = $payload['thumb_image'];
            }
            if (!empty($payload['thumb_icon'])) {
                $row['thumb_icon'] = $payload['thumb_icon'];
            }
        } else {
            // Nếu không phải notx_v2, đảm bảo body_text chính là body (text thuần)
            $row['body_text'] = html_entity_decode($row['body'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    jOut([
        'ok'     => true,
        'data'   => $rows,
        'unread' => unread_count($ithanhloc, $uid),
    ]);
}

if ($action === 'mark_read') {
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $ithanhloc->prepare("SELECT user_id FROM user_notification WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $nUid = (int)$row['user_id'];
                if ($nUid === 0) {
                    // Thông báo chung: ghi nhận đã đọc theo từng user
                    $ithanhloc->query("INSERT INTO user_notification_read (user_id, notification_id, read_at)
                                       VALUES ({$uid}, {$id}, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()");
                } elseif ($nUid === $uid) {
                    // Thông báo cá nhân: đánh dấu trực tiếp trên bản ghi
                    $ithanhloc->query("UPDATE user_notification SET is_read = 1, read_at = NOW() WHERE id = {$id}");
                }
            }
            if ($res) { $res->close(); }
            $stmt->close();
        }
    }
    jOut(['ok' => true, 'unread' => unread_count($ithanhloc, $uid)]);
}

if ($action === 'mark_all') {
    // Đánh dấu đã đọc cho tất cả thông báo cá nhân (user_id<>0)
    $ithanhloc->query("UPDATE user_notification SET is_read = 1, read_at = NOW() WHERE user_id = {$uid} AND is_read = 0");

    // Đánh dấu đã đọc cho tất cả thông báo chung (user_id=0)
    $ithanhloc->query("
        INSERT IGNORE INTO user_notification_read (user_id, notification_id, read_at)
        SELECT {$uid}, id, NOW()
        FROM user_notification
        WHERE user_id = 0 AND COALESCE(is_active, 1) = 1
        AND id NOT IN (SELECT notification_id FROM user_notification_read WHERE user_id = {$uid})
    ");

    jOut(['ok' => true, 'unread' => unread_count($ithanhloc, $uid)]);
}

jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
