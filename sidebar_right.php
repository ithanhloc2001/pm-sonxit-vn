<?php
$today = date('Y-m-d');
$currentCase = $currentCase ?? ($_GET['case'] ?? '');
$normalRoute = $normalRoute ?? ($_GET['normal'] ?? '');
$userRouteActive = $userRouteActive ?? ($_GET['user'] ?? '');
$userOrderCount = 0;
$userOrderToday = 0;
$userNotifications = [];
$userUnreadCount = 0;
$userRecentLogs = [];

if (!function_exists('sidebar_parse_notice_payload')) {
    function sidebar_parse_notice_payload($rawBody): ?array {
        $txt = trim((string)$rawBody);
        if ($txt === '') {
            return null;
        }
        $decoded = json_decode($txt, true);
        if (!is_array($decoded) || (($decoded['schema'] ?? '') !== 'notx_v2')) {
            return null;
        }
        return $decoded;
    }
}

if (!function_exists('sidebar_notice_excerpt')) {
    function sidebar_notice_excerpt($rawBody, int $limit = 80): string {
        $text = trim((string)$rawBody);
        if ($text === '') {
            return '';
        }
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit, 'UTF-8') . '...';
    }
}

if (!function_exists('sidebar_notice_model')) {
    function sidebar_resolve_notice_type(string $type, string $orderId = ''): string {
        $key = strtolower(trim($type));
        $aliasMap = [
            'promo' => 'promotion',
            'voucher' => 'promotion',
            'coupon' => 'promotion',
            'profile' => 'account',
            'bank' => 'account',
            'warning' => 'system',
            'alert' => 'system',
            'support' => 'complaint',
            'ticket' => 'complaint',
        ];
        if ($key !== '' && isset($aliasMap[$key])) {
            $key = $aliasMap[$key];
        }
        if ($key === '' && trim($orderId) !== '') {
            return 'order';
        }
        return $key !== '' ? $key : 'info';
    }
    // Hàm này trả về cấu hình hiển thị (title, icon, banners) theo loại thông báo đã được chuẩn hóa
    function sidebar_type_visual_preset(string $resolvedType): array {
        $map = [
            'order' => ['title' => 'Đơn hàng của bạn', 'icon' => 'bi bi-bag-check-fill', 'banners' => []],
            'payment' => ['title' => 'Thanh toán', 'icon' => 'bi bi-credit-card-2-front-fill', 'banners' => []],
            'security' => ['title' => 'Bảo mật tài khoản', 'icon' => 'bi bi-shield-lock-fill', 'banners' => []],
            'account' => ['title' => 'Tài khoản', 'icon' => 'bi bi-person-badge-fill', 'banners' => []],
            'system' => ['title' => 'Thông báo hệ thống', 'icon' => 'bi bi-megaphone-fill', 'banners' => []],
            'promotion' => ['title' => 'Khuyến mãi', 'icon' => 'bi bi-gift-fill', 'banners' => []],
            'complaint' => ['title' => 'Hỗ trợ khách hàng', 'icon' => 'bi bi-life-preserver', 'banners' => []],
            'info' => ['title' => 'Thông báo', 'icon' => 'bi bi-bell-fill', 'banners' => []],
        ];
        return $map[$resolvedType] ?? $map['info'];
    }

    function sidebar_notice_model(array $notice, string $baseUrl = ''): array {
        $rawBody = (string)($notice['body'] ?? '');
        $meta = json_decode((string)($notice['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $payload = sidebar_parse_notice_payload($rawBody);
        $templateCode = '';
        $mainBanner = '';
        $orderId = trim((string)($meta['order_id'] ?? (($payload['order_id'] ?? ''))));
        $resolvedType = sidebar_resolve_notice_type((string)($notice['type'] ?? ''), $orderId);
        $preset = sidebar_type_visual_preset($resolvedType);

        $title = trim((string)($notice['title'] ?? $preset['title']));
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subtitle = '';
        $content = sidebar_notice_excerpt($rawBody, 90);
        $thumbImage = '';
        $thumbIcon = (string)($preset['icon'] ?? 'bi bi-megaphone-fill');
        $banners = [];

        if (is_array($payload)) {
            $templateCode = strtolower(trim((string)($payload['template'] ?? '')));
            $mainBanner = trim((string)($payload['main_banner'] ?? ''));
            $title = trim((string)($payload['title'] ?? $title));
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $subtitle = trim((string)($payload['subtitle'] ?? ''));
            $subtitle = html_entity_decode($subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = sidebar_notice_excerpt((string)($payload['content'] ?? ''), 90);
            $thumbImage = trim((string)($payload['thumb_image'] ?? ''));
            $thumbIcon = trim((string)($payload['thumb_icon'] ?? $thumbIcon));
            $rawBanners = $payload['banners'] ?? [];
            if (is_array($rawBanners)) {
                foreach ($rawBanners as $img) {
                    $img = trim((string)$img);
                    if ($img !== '') $banners[] = $img;
                }
            }
        }

        if ($thumbImage === '' && !empty($meta['thumb_image'])) {
            $thumbImage = trim((string)$meta['thumb_image']);
        }
        if (trim($thumbIcon) === '' && !empty($meta['thumb_icon'])) {
            $thumbIcon = trim((string)$meta['thumb_icon']);
        }

        if (empty($banners) && isset($meta['banners'])) {
            $metaBanners = $meta['banners'];
            if (is_string($metaBanners)) {
                $decodedBanners = json_decode($metaBanners, true);
                if (is_array($decodedBanners)) {
                    $metaBanners = $decodedBanners;
                } else {
                    $metaBanners = array_filter(array_map('trim', explode(',', $metaBanners)), static fn($v) => $v !== '');
                }
            }
            if (is_array($metaBanners)) {
                foreach ($metaBanners as $img) {
                    $img = trim((string)$img);
                    if ($img !== '') $banners[] = $img;
                }
            }
        }

        if (empty($banners) && !empty($meta['banner'])) {
            $bannerOne = trim((string)$meta['banner']);
            if ($bannerOne !== '') $banners[] = $bannerOne;
        }

        if ($title === '') $title = (string)($preset['title'] ?? 'Thông báo');
        if ($thumbIcon === '') $thumbIcon = (string)($preset['icon'] ?? 'bi bi-megaphone-fill');

        $toAbs = static function(string $url) use ($baseUrl): string {
            return app_get_media_url($url, (string)$baseUrl);
        };

        $thumbImage = $toAbs($thumbImage);
        $banners = array_values(array_filter(array_map($toAbs, $banners), static fn($v) => $v !== ''));

        // Điều chỉnh theo form mới: template 1 & 4 dùng main_banner làm banner chính nếu có
        if (in_array($templateCode, ['tpl1', 'tpl4'], true) && $mainBanner !== '') {
            $banners = [$toAbs($mainBanner)];
        }

        return [
            'title' => $title !== '' ? $title : 'Thông báo',
            'subtitle' => $subtitle,
            'content' => $content,
            'thumb_image' => $thumbImage,
            'thumb_icon' => $thumbIcon !== '' ? $thumbIcon : 'bi bi-megaphone-fill',
            'banners' => array_slice($banners, 0, 3),
        ];
    }
}

if (!function_exists('sidebar_status_label_vi')) {
    function sidebar_status_label_vi(string $status): string {
        $key = strtolower(trim($status));
        $map = [
            'pending' => 'Chờ  xử lý',
            'processing' => 'Đang xử lý',
            'shipping' => 'Đang giao',
            'delivered' => 'Đã giao',
            'canceled' => 'Đã hủy',
            'cancelled' => 'Đã hủy',
            'return_requested' => 'Yêu cầu trả hàng',
            'returned' => 'Đã trả hàng',
            'paid' => 'Đã thanh toán',
            'failed' => 'Thanh toán lỗi',
            'expired' => 'Hết hạn thanh toán',
            'cod' => 'Thanh toán khi nhận hàng',
            'success' => 'Thành công',
            'open' => 'Đang mở',
            'closed' => 'Đã đóng',
        ];
        return $map[$key] ?? trim($status);
    }
}

if (!function_exists('sidebar_normalize_money_text')) {
    function sidebar_normalize_money_text($value): string {
        if (is_numeric($value)) {
            return number_format((float)$value, 0, '.', '') . 'đ';
        }
        $txt = trim((string)$value);
        if ($txt === '') return '';
        if (preg_match('/^\d+(?:[\.,]\d+)?$/', str_replace(' ', '', $txt))) {
            $num = (float)str_replace(',', '.', str_replace(' ', '', $txt));
            return number_format($num, 0, '.', '') . 'đ';
        }
        return $txt;
    }
}

if (!function_exists('sidebar_present_notice')) {
    function sidebar_present_notice(array $notice, array $model): array {
        $type = strtolower(trim((string)($notice['type'] ?? '')));
        $meta = json_decode((string)($notice['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $payload = sidebar_parse_notice_payload((string)($notice['body'] ?? ''));
        if (!is_array($payload)) $payload = [];

        $statusRaw = trim((string)($meta['status'] ?? ($payload['status'] ?? '')));
        $statusLabel = $statusRaw !== '' ? sidebar_status_label_vi($statusRaw) : '';
        $amountRaw = $meta['amount'] ?? ($payload['amount'] ?? '');
        $amountText = sidebar_normalize_money_text($amountRaw);
        $orderId = trim((string)($meta['order_id'] ?? ($payload['order_id'] ?? '')));
        $resolvedType = sidebar_resolve_notice_type($type, $orderId);
        $preset = sidebar_type_visual_preset($resolvedType);

        $content = trim((string)($model['content'] ?? ''));

        $titleCandidate = trim((string)($model['title'] ?? ''));
        $title = $titleCandidate !== ''
            ? $titleCandidate
            : ((string)($preset['title'] ?? 'Thông báo'));

        $isOrder = ($resolvedType === 'order') || ($orderId !== '');
        $parts = [];

        if ($isOrder) {
           // if ($orderId !== '') $parts[] = 'Mã đơn: ' . $orderId;
            if ($content !== '') $parts[] = $content;
            //if ($statusLabel !== '') $parts[] = '• Trạng thái: ' . $statusLabel;
            //if ($amountText !== '') $parts[] = 'Số tiền: ' . number_format($amountRaw, 0, ',', '.') . 'đ';
           
            return [
                'is_order' => true,
                'title' => $title !== '' ? $title : 'Đơn hàng của bạn',
                'body' => implode(' • ', $parts),
                'status' => $statusLabel,
            ];
        }

        switch ($resolvedType) {
            case 'payment':
                if ($content !== '') $parts[] = $content;
                if ($orderId !== '') $parts[] = 'Mã đơn: ' . $orderId;
                if ($statusLabel !== '') $parts[] = 'Trạng thái: ' . $statusLabel;
                if ($amountText !== '') $parts[] = 'Số tiền: ' . $amountText;
                break;
            case 'security':
                if ($content !== '') $parts[] = $content;
                if ($statusLabel !== '') $parts[] = 'Trạng thái: ' . $statusLabel;
                break;
            case 'promotion':
                if ($content !== '') $parts[] = $content;
                if ($amountText !== '') $parts[] = 'Ưu đãi: ' . $amountText;
                break;
            case 'complaint':
                if ($content !== '') $parts[] = $content;
                if ($statusLabel !== '') $parts[] = 'Tình trạng: ' . $statusLabel;
                break;
            case 'account':
            case 'system':
            case 'info':
            default:
                if ($content !== '') $parts[] = $content;
                if ($statusLabel !== '') $parts[] = 'Trạng thái: ' . $statusLabel;
                break;
        }

        $body = implode(' • ', $parts);
        return [
            'is_order' => false,
            'title' => $title !== '' ? $title : 'Thông báo',
            'body' => $body,
            'status' => $statusLabel,
        ];
    }
}

// Nếu có user_id (đã đăng nhập frontend) thì dùng chung luồng lấy thông báo cho user
// bất kể role là user hay admin, để hiển thị đầy đủ cả thông báo cá nhân + hệ thống
if ($isLoggedIn && isset($_SESSION['user_id'])) {
    $userId = intval($_SESSION['user_id']);
    $orderCols = listColumns($ithanhloc, 'ecommerce_order');
    if (hasCol($orderCols, 'user_id')) {
        $resUserOrder = $ithanhloc->query("SELECT COUNT(*) c FROM ecommerce_order WHERE user_id = {$userId}");
        $userOrderCount = $resUserOrder ? intval($resUserOrder->fetch_assoc()['c']) : 0;

        $resUserOrderToday = $ithanhloc->query("SELECT COUNT(*) c FROM ecommerce_order WHERE user_id = {$userId} AND DATE(created_at) = '{$today}'");
        $userOrderToday = $resUserOrderToday ? intval($resUserOrderToday->fetch_assoc()['c']) : 0;
    }

    // Thông báo (hệ thống + khuyến mãi) đã gộp chung 1 bảng user_notification
    $sqlN = "SELECT n.id, n.title, n.body, n.type, n.meta_json, n.link, n.user_id, n.is_read, n.created_at,
                    CASE WHEN n.user_id=0 THEN (r.read_at IS NOT NULL) ELSE (COALESCE(NULLIF(TRIM(CAST(n.is_read AS CHAR)),''),'0')='1') END AS read_state,
                    CASE WHEN LOWER(TRIM(CAST(n.type AS CHAR))) IN ('promotion','promo','voucher','coupon') THEN 'promo' ELSE 'system' END AS table_source
             FROM user_notification n
             LEFT JOIN (
                 SELECT notification_id, MAX(read_at) AS read_at
                 FROM user_notification_read
                 WHERE user_id=?
                 GROUP BY notification_id
             ) r ON r.notification_id=n.id
             WHERE (n.user_id=? OR n.user_id=0)
                 AND COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'
                 AND (n.send_at IS NULL OR TRIM(CAST(n.send_at AS CHAR))='' OR n.send_at <= NOW())
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT 50";

    $stmtN = $ithanhloc->prepare($sqlN);
    if ($stmtN) {
        $stmtN->bind_param('ii', $userId, $userId);
        $stmtN->execute();
        $userNotifications = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtN->close();
    }

    // Đếm thông báo cá nhân chưa đọc
    $sqlU1 = "SELECT COUNT(*) as c FROM user_notification WHERE user_id=? AND COALESCE(NULLIF(TRIM(CAST(is_read AS CHAR)),''),'0')='0' AND COALESCE(NULLIF(TRIM(CAST(is_active AS CHAR)),''),'1')='1'";
    $stmtU1 = $ithanhloc->prepare($sqlU1);
    if ($stmtU1) {
        $stmtU1->bind_param('i', $userId);
        $stmtU1->execute();
        $rowU1 = $stmtU1->get_result()->fetch_assoc();
        $userUnreadCount += (int)($rowU1['c'] ?? 0);
        $stmtU1->close();
    }

    // Đếm thông báo chung chưa đọc
    $sqlU2 = "SELECT COUNT(*) as c FROM user_notification n WHERE n.user_id=0 AND COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1' AND NOT EXISTS (SELECT 1 FROM user_notification_read r WHERE r.user_id=? AND r.notification_id=n.id)";
    $stmtU2 = $ithanhloc->prepare($sqlU2);
    if ($stmtU2) {
        $stmtU2->bind_param('i', $userId);
        $stmtU2->execute();
        $rowU2 = $stmtU2->get_result()->fetch_assoc();
        $userUnreadCount += (int)($rowU2['c'] ?? 0);
        $stmtU2->close();
    }
}
// Lấy thông báo cho admin chỉ khi KHÔNG đăng nhập frontend (không có user_id)
// Trường hợp này chỉ hiển thị thông báo hệ thống (user_id=0)
if ($isAdmin && !$isLoggedIn) {
    $stmtN = $ithanhloc->prepare("SELECT n.id, n.title, n.body, n.type, n.meta_json, n.link, n.user_id, n.is_read, n.created_at,
        1 AS read_state
        FROM user_notification n
        WHERE n.user_id=0
            AND COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'
            AND (n.send_at IS NULL OR TRIM(CAST(n.send_at AS CHAR))='' OR n.send_at <= NOW())
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 50");
    if ($stmtN) {
        $stmtN->execute();
        $userNotifications = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtN->close();
    }
    // Đếm số thông báo chưa đọc (nếu muốn, có thể thêm logic riêng cho admin)
    $userUnreadCount = count($userNotifications);
}

// Map số bình luận cho mỗi thông báo trong sidebar (nếu có)
$sidebarCommentCounts = [];
if (!empty($userNotifications)) {
    $nidList = [];
    foreach ($userNotifications as $row) {
        $nid = (int)($row['id'] ?? 0);
        if ($nid > 0) {
            $nidList[$nid] = $nid;
        }
    }
    if (!empty($nidList)) {
        $idSql = implode(',', array_map('intval', array_values($nidList)));
        $sqlC = "SELECT notification_id, COUNT(*) AS c
                 FROM user_notification_comment
                 WHERE notification_id IN ({$idSql})
                   AND COALESCE(NULLIF(TRIM(CAST(is_active AS CHAR)) ,''),'1')='1'
                 GROUP BY notification_id";
        $resC = $ithanhloc->query($sqlC);
        if ($resC instanceof mysqli_result) {
            while ($rowC = $resC->fetch_assoc()) {
                $nid = (int)($rowC['notification_id'] ?? 0);
                if ($nid > 0) {
                    $sidebarCommentCounts[$nid] = (int)($rowC['c'] ?? 0);
                }
            }
        }
    }
}
?>
<div class="fb-sidebar-right">
    <?php if ($isAdmin): ?>
    <!-- thông báo riêng cho admin -->
    <?php endif; ?>

    <?php if ($isUser || $isAdmin): ?>
    <div class="sidebar-content">
        <div class="right-section">
            <?php
                $userNoticePreview = !empty($userNotifications) ? array_slice($userNotifications, 0, 5) : [];
                $userNoticeMoreLink = $baseUrl . '/account?tab=notifications';
            ?>

            <div class="d-flex align-items-center justify-content-between" style="gap:8px;">
                <h6 class="section-title" style="margin-top:0;margin-bottom:0;">
                    <i class="bi bi-bell-fill me-2"></i>
                    Thông báo của bạn
                    <?php if ($userUnreadCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= h($userUnreadCount) ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="notify-fb-shell" id="userNotifyPanelFb">
                    <div class="notify-fb-tools mb-2 mt-2">
                        <?php if ($userUnreadCount > 0): ?>
                            <button type="button" class="btn btn-light btn-sm" id="notifyMarkAllUserBtn">Đã đọc tất cả</button>
                        <?php endif; ?>
                        <a class="btn btn-light btn-sm" href="<?= h($userNoticeMoreLink); ?>">Xem tất cả</a>
                    </div>
                <div class="notify-fb-top">
                    <div class="notify-fb-filter" role="tablist" aria-label="Thông báo">
                        <button type="button" class="notify-fb-pill is-active" data-notify-filter="all" aria-pressed="true">Tất cả</button>
                        <button type="button" class="notify-fb-pill" data-notify-filter="unread" aria-pressed="false">Chưa đọc</button>
                    </div>
                </div>
                <div class="notify-fb-list" id="userNotifyFbList">
                <?php if (!empty($userNoticePreview)): ?>
                    <?php foreach ($userNoticePreview as $n): ?>
                        <?php
                        $model = sidebar_notice_model($n, (string)($baseUrl ?? ''));
                        $present = sidebar_present_notice($n, $model);
                        $titleText = $present['title'] ?? 'Thông báo';
                        $bodyText = $present['body'] ?? '';
                        $isOrderNotice = !empty($present['is_order']);
                        $thumbImage = $model['thumb_image'] ?? '';
                        $thumbIcon = $model['thumb_icon'] ?? 'bi bi-megaphone-fill';
                        $banners = is_array($model['banners'] ?? null) ? $model['banners'] : [];
                        $time = !empty($n['created_at']) ? date('H:i d/m/Y', strtotime($n['created_at'])) : '';
                        $isRead = !empty($n['read_state']);
                        $noticeId = (int)($n['id'] ?? 0);
                        $commentCount = $sidebarCommentCounts[$noticeId] ?? 0;
                        $metaRaw = json_decode((string)($n['meta_json'] ?? ''), true);
                        if (!is_array($metaRaw)) $metaRaw = [];
                        $payloadRaw = sidebar_parse_notice_payload((string)($n['body'] ?? ''));
                        if (!is_array($payloadRaw)) $payloadRaw = [];
                        $tplCode = strtolower(trim((string)($payloadRaw['template'] ?? '')));
                        $isBlogTpl = in_array($tplCode, ['tpl2','tpl3'], true);
                        $orderIdForType = trim((string)($metaRaw['order_id'] ?? ($payloadRaw['order_id'] ?? '')));
                        $resolvedType = sidebar_resolve_notice_type((string)($n['type'] ?? ''), $orderIdForType);
                        $typeClass = preg_replace('/[^a-z0-9_-]/', '', strtolower($resolvedType));
                        if ($typeClass === '') $typeClass = 'info';
                        $tableSource = $n['table_source'] ?? 'system';
                        $detailLink = '';
                        if ($tableSource === 'promo') {
                            $detailLink = nf_build_url((int)$noticeId, (string)($n['title'] ?? ''), $baseUrl);
                        }
                        
                        $eventKey = strtolower(trim((string)($metaRaw['event'] ?? ($payloadRaw['event'] ?? ''))));
                        $linkRaw = trim((string)($n['link'] ?? ($payloadRaw['link'] ?? '')));
                        $titleRaw = strtolower(trim((string)($n['title'] ?? '')));
                        
                        // Đối với thông báo hệ thống (system), nếu có link cụ thể (như link đơn hàng) thì vẫn giữ, 
                        // nhưng không dùng mặc định link tới view-notification nữa
                        if ($linkRaw !== '') {
                            if (preg_match('/^(https?:)?\/\//i', $linkRaw)) {
                                $detailLink = $linkRaw;
                            } elseif (strpos($linkRaw, '/') === 0) {
                                $detailLink = $baseUrl . $linkRaw;
                            } elseif (strpos($linkRaw, '?') === 0) {
                                $detailLink = $baseUrl . '/' . $linkRaw;
                            } else {
                                $detailLink = $baseUrl . '/' . ltrim($linkRaw, '/');
                            }
                        }
                        
                        // Nếu là thông báo hệ thống và không có link cụ thể, đảm bảo detailLink rỗng
                        if ($tableSource === 'system' && $linkRaw === '') {
                            $detailLink = '';
                        }
                        ?>
                        <!-- Hiển thị thẻ thông báo -->
                        <div class="notify-fb-card js-notify-item type-<?= h($typeClass) ?> <?= $isRead ? '' : 'is-unread'; ?>" data-id="<?= h($noticeId) ?>" data-is-read="<?= $isRead ? '1' : '0'; ?>" data-link="<?= h($detailLink) ?>">
                            <div class="notify-fb-main">
                                <div class="notify-fb-avatar">
                                    <?php if ($thumbImage !== ''): ?>
                                        <img src="<?= h($thumbImage) ?>" alt="thumb" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <i class="<?= h($thumbIcon) ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="notify-fb-body">
                                    <div class="notify-fb-title"><?= h($titleText) ?></div>
                                    <?php if (!$isOrderNotice && !empty($model['subtitle'])): ?>
                                        <div class="notify-fb-sub"><?= h($model['subtitle']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!$isBlogTpl): ?>
                                        <div class="notify-fb-text"><?= h($bodyText !== '' ? $bodyText : 'Thông báo mới.') ?></div>
                                    <?php endif; ?>
                                    <div class="notify-fb-meta">
                                        <span class="notify-fb-time"><?= h($time) ?></span>
                                        <!-- 
                                        <?php if ($commentCount > 0 && $isAdmin): ?>
                                            <span class="d-none notify-fb-comments"><i class="bi bi-chat-dots"></i> <?= h($commentCount) ?></span>
                                        <?php endif; ?>
                                        -->
                                        <span class="notify-fb-dot"></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Hiển thị banner nếu có và không phải template blog -->
                            <?php if (!empty($banners) && !$isBlogTpl): ?>
                                <?php
                                    $isProductTpl = in_array($tplCode, ['tpl2','tpl3'], true);
                                    $productIdsSidebar = [];
                                    if ($isProductTpl && isset($payloadRaw['product_ids']) && is_array($payloadRaw['product_ids'])) {
                                        foreach ($payloadRaw['product_ids'] as $pidRaw) {
                                            $pid = (int)$pidRaw;
                                            $productIdsSidebar[] = $pid > 0 ? $pid : 0;
                                        }
                                    }
                                ?>
                             <!-- Hiển thị gallery nếu có banner và không phải template blog -->
                                <!--div class="notify-fb-gallery <?php echo count($banners) === 1 ? 'is-cover' : ''; ?>">
                                    <?php foreach ($banners as $idx => $img): ?>
                                        <?php
                                            $pid = $productIdsSidebar[$idx] ?? 0;
                                            $href = ($isProductTpl && $pid > 0)
                                                ? ($baseUrl . '/view-product?pid=' . $pid)
                                                : '';
                                        ?>
                                         -- Hiển thị link điều hướng --
                                        <?php if ($href !== ''): ?>
                                            <a href="<?php echo h($href); ?>">
                                                <img src="<?php echo h($img); ?>" alt="banner" loading="lazy" decoding="async">
                                            </a>
                                        <?php else: ?>
                                            <img src="<?php echo h($img); ?>" alt="banner" loading="lazy" decoding="async">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div-->
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notify-fb-empty">Chưa có thông báo nào</div>
                <?php endif; ?>
                </div>

 
                <div class="notify-fb-skeleton d-none" id="userNotifyFbSkeleton">
                    <div class="notify-fb-skel-card">
                        <div class="notify-fb-skel-main">
                            <div class="notify-fb-skel-avatar"></div>
                            <div>
                                <div class="skeleton-line w-80" style="margin-bottom:8px;"></div>
                                <div class="skeleton-line w-60" style="margin-bottom:8px;"></div>
                                <div class="skeleton-line w-40"></div>
                            </div>
                        </div>
                    </div>
                    <div class="notify-fb-skel-card">
                        <div class="notify-fb-skel-main">
                            <div class="notify-fb-skel-avatar"></div>
                            <div>
                                <div class="skeleton-line w-80" style="margin-bottom:8px;"></div>
                                <div class="skeleton-line w-60" style="margin-bottom:8px;"></div>
                                <div class="skeleton-line w-40"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>
</div>

<!-- Overlay để đóng sidebar khi click ra ngoài (chỉ hiển thị trên mobile) -->
<script>
// Hàm toggle sidebar phải được gọi từ sự kiện click của nút toggle, ví dụ:
function toggleRightSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-right');
    if (!sidebar) return;

    if (sidebar.classList.contains('open')) {
        closeRightSidebar();
    } else {
        openRightSidebar();
    }
}

function openRightSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-right');
    const overlay = document.querySelector('.sidebar-overlay-right');
    if (!sidebar) return;
    document.body.classList.add('hide-right');
    
    sidebar.classList.add('open');
    overlay.classList.add('active');

    // Hàm ẩn overflow của body để tránh cuộn trang khi sidebar mở trên mobile
    document.body.style.overflow = 'hidden';
}

function closeRightSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-right');
    const overlay = document.querySelector('.sidebar-overlay-right');
    if (!sidebar) return;
    
    sidebar.classList.remove('open');
    overlay.classList.remove('active');

    // Hàm đóng sidebar cũng sẽ cho phép cuộn lại trang
    document.body.style.overflow = '';
}
</script>

<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core/ajax/notification.php';
    const $scrollBox = $('.fb-sidebar-right .sidebar-content');
    const $pillAll = $('[data-notify-filter="all"]');
    const $pillUnread = $('[data-notify-filter="unread"]');
    const $tools = $('.notify-fb-tools');
    const $listNotify = $('#userNotifyFbList');
    const state = {
        tab: 'all'
    };

    const updateNotifyBadge = (nextUnread) => {
        const count = Number(nextUnread || 0);
        const $badge = $('#headerNotifyBadge');
        if (!$badge.length) return;
        if (count > 0) {
            $badge.text(count > 99 ? '99+' : String(count)).removeClass('d-none');
        } else {
            $badge.addClass('d-none').text('0');
        }
    };
    const markRead = (id, cb) => {
        $.post(API, { action: 'mark_read', id }, (res) => {
            if (typeof cb === 'function') cb(res || null);
        }, 'json').fail(() => {
            if (typeof cb === 'function') cb(null);
        });
    };
    const markAll = (cb) => {
        $.post(API, { action: 'mark_all' }, (res) => {
            if (res && res.ok && typeof cb === 'function') cb(res);
        }, 'json');
    };

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

    const switchTab = (tab) => {
        state.tab = tab;
        // Cập nhật trạng thái active của các nút filter
        const $buttons = $('[data-notify-filter]');
        $buttons.removeClass('is-active').attr('aria-pressed', 'false');
        $(`[data-notify-filter="${tab}"]`).addClass('is-active').attr('aria-pressed', 'true');

        $tools.show();
        $listNotify.show();

        // Hàm filter thông báo: nếu là tab "chưa đọc" thì chỉ hiện các thẻ có data-is-read="0"
        const onlyUnread = tab === 'unread';
        $('.js-notify-item', $listNotify).each(function(){
            const isRead = String($(this).attr('data-is-read') || '0') === '1';
            const hide = (onlyUnread && isRead);
            $(this).toggle(!hide);
        });
    };


    $(document).on('click', '.js-notify-item', function(){
        const id = Number($(this).data('id') || 0);
        const link = String($(this).data('link') || '').trim();
        const $item = $(this);
        const markUiRead = () => {
            $item.removeClass('is-unread').attr('data-is-read', '1');
        };
        if (id) {
            markUiRead();
            markRead(id, (res) => {
                const unread = res && typeof res.unread !== 'undefined' ? res.unread : 0;
                updateNotifyBadge(unread);
                if (link) window.location.href = link;
            });
            return;
        } else if (link) {
            window.location.href = link;
        }
    });

    $('#notifyMarkAllUserBtn').on('click', function(){
        markAll((res) => {
            $('.js-notify-item').each(function(){
                $(this).removeClass('is-unread').attr('data-is-read', '1');
            });
            $('#notifyMarkAllUserBtn').remove();
            updateNotifyBadge(res?.unread || 0);
        });
    });

    $(document).on('click', '[data-notify-filter]', function(){
        const filter = String($(this).data('notify-filter') || 'all');
        switchTab(filter);
    });



    // default tab
    switchTab('all');
})();
</script>
