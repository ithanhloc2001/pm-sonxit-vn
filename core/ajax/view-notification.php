<?php
require_once __DIR__ . '/../../config.php';

$uid      = (int)($_SESSION['user_id'] ?? 0);
$noticeId = (int)($_REQUEST['id'] ?? 0);
$source   = strtolower(trim((string)($_REQUEST['source'] ?? '')));

if ($noticeId <= 0) {
    jOut(['ok' => false, 'msg' => 'Thiếu mã thông báo']);
}

// 1. Fetch notification (đã gộp về 1 bảng user_notification)
$stmt = $ithanhloc->prepare('SELECT id, user_id, title, body, type, meta_json, created_at, send_at FROM user_notification WHERE id = ? AND COALESCE(is_active, 1) = 1 LIMIT 1');
$stmt->bind_param('i', $noticeId);

if (!$stmt || !$stmt->execute()) {
    jOut(['ok' => false, 'msg' => 'Lỗi truy vấn cơ sở dữ liệu']);
}

$notice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notice) {
    jOut(['ok' => false, 'msg' => 'Thông báo không tồn tại hoặc đã bị ẩn']);
}

$sendAt = (string)($notice['send_at'] ?? '');
if ($sendAt !== '' && strtotime($sendAt) > time()) {
    jOut(['ok' => false, 'msg' => 'Thông báo chưa đến thời gian hiển thị']);
}

$targetUser = (int)($notice['user_id'] ?? 0);
if ($targetUser !== 0) {
    if ($uid <= 0) {
        jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập để xem thông báo này', 'need_login' => true]);
    }
    if ($targetUser !== $uid) {
        jOut(['ok' => false, 'msg' => 'Bạn không có quyền xem thông báo này']);
    }
}

// 2. Mark as read
if ($uid > 0) {
    if ($targetUser === 0) {
        $stmtR = $ithanhloc->prepare('INSERT INTO user_notification_read (user_id, notification_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()');
        if ($stmtR) {
            $stmtR->bind_param('ii', $uid, $noticeId);
            $stmtR->execute();
            $stmtR->close();
        }
    } else {
        $stmtR = $ithanhloc->prepare("UPDATE user_notification SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        if ($stmtR) {
            $stmtR->bind_param('ii', $noticeId, $uid);
            $stmtR->execute();
            $stmtR->close();
        }
    }
}

// 3. Helper Functions
function resolve_notice_type(string $type, string $orderId = ''): string {
    $key = strtolower(trim($type));
    $resolved = [
        'promo' => 'promotion', 'voucher' => 'promotion', 'coupon' => 'promotion',
        'profile' => 'account', 'bank' => 'account',
        'warning' => 'security', 'login' => 'security', 'security' => 'security',
        'alert' => 'system', 'support' => 'complaint', 'ticket' => 'complaint',
    ][$key] ?? $key;
    return ($resolved === '' && $orderId !== '') ? 'order' : ($resolved !== '' ? $resolved : 'info');
}

function notice_type_visual_preset(string $resolvedType): array {
    $map = [
        'order'     => ['title' => 'Đơn hàng của bạn', 'icon' => 'bi bi-bag-check-fill', 'filter' => 'order'],
        'payment'   => ['title' => 'Thanh toán', 'icon' => 'bi bi-credit-card-2-front-fill', 'filter' => 'payment'],
        'security'  => ['title' => 'Bảo mật tài khoản', 'icon' => 'bi bi-shield-lock-fill', 'filter' => 'account'],
        'account'   => ['title' => 'Tài khoản', 'icon' => 'bi bi-person-badge-fill', 'filter' => 'account'],
        'system'    => ['title' => 'Thông báo hệ thống', 'icon' => 'bi bi-megaphone-fill', 'filter' => 'system'],
        'promotion' => ['title' => 'Khuyến mãi', 'icon' => 'bi bi-gift-fill', 'filter' => 'promo'],
        'complaint' => ['title' => 'Hỗ trợ khách hàng', 'icon' => 'bi bi-life-preserver', 'filter' => 'complaint'],
        'info'      => ['title' => 'Thông báo', 'icon' => 'bi bi-bell-fill', 'filter' => 'info'],
    ];
    return $map[$resolvedType] ?? $map['info'];
}

function normalize_notice_money_text($val): string {
    $txt = trim((string)$val);
    $clean = str_replace([' ', ','], ['', '.'], $txt);
    return is_numeric($clean) ? number_format((float)$clean, 0, '.', '') . 'đ' : $txt;
}

function notice_status_label_vi(string $status): string {
    $key = strtolower(trim($status));
    $map = [
        'pending'          => 'Chờ thanh toán', 'processing' => 'Đang xử lý', 'shipping' => 'Đang giao',
        'delivered'        => 'Đã giao', 'canceled' => 'Đã hủy', 'cancelled' => 'Đã hủy',
        'cancel_requested' => 'Yêu cầu hủy đơn', 'return_requested' => 'Yêu cầu trả hàng',
        'returned'         => 'Đã trả hàng', 'refunded' => 'Đã hoàn tiền',
        'paid'             => 'Đã thanh toán', 'failed' => 'Thanh toán lỗi',
        'expired'          => 'Hết hạn thanh toán', 'cod' => 'Thanh toán khi nhận hàng',
        'success'          => 'Thành công', 'open' => 'Đang mở', 'closed' => 'Đã đóng',
    ];
    return $map[$key] ?? trim($status);
}

function parse_notice_banners($val): array {
    if (is_string($val)) {
        $val = trim($val);
        if ($val === '') return [];
        $dec = json_decode($val, true);
        $val = is_array($dec) ? $dec : explode(',', $val);
    }
    return is_array($val) ? array_values(array_filter(array_map('trim', $val))) : [];
}

function build_notice_text_by_type(string $type, string $content, string $orderId, string $statusLabel, string $amountText): string {
    $parts = [];
    $hasOrder = ($orderId !== '');
    $hasStatus = ($statusLabel !== '');
    $hasAmount = ($amountText !== '');

    if ($type === 'order') {
        if ($hasOrder) $parts[] = 'Mã đơn: ' . $orderId;
        if ($content !== '') $parts[] = $content;
        if ($hasStatus) $parts[] = 'Trạng thái: ' . $statusLabel;
        if ($hasAmount) $parts[] = 'Số tiền: ' . $amountText;
    } else {
        if ($content !== '') $parts[] = $content;
        if ($type === 'payment') {
            if ($hasOrder) $parts[] = 'Mã đơn: ' . $orderId;
            if ($hasStatus) $parts[] = 'Trạng thái: ' . $statusLabel;
            if ($hasAmount) $parts[] = 'Số tiền: ' . $amountText;
        } elseif ($type === 'promotion') {
            if ($hasAmount) $parts[] = 'Ưu đãi: ' . $amountText;
        } elseif ($type === 'complaint') {
            if ($hasStatus) $parts[] = 'Tình trạng: ' . $statusLabel;
        } else {
            if ($hasStatus) $parts[] = 'Trạng thái: ' . $statusLabel;
        }
    }
    return implode(' • ', $parts);
}

function to_plain_text(string $val): string {
    $val = trim(strip_tags($val));
    return $val === '' ? '' : (preg_replace('/\s+/', ' ', $val) ?? '');
}

function sanitize_notice_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';
    $html    = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? '';
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><a><img><video><source><iframe><h2><h3><h4><span><div>';
    $clean   = strip_tags($html, $allowed);
    $clean   = preg_replace('/\s+on[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
    $clean   = preg_replace('/\sstyle\s*=\s*("[^"]*expression\([^\"]*\)[^"]*"|\'[^\']*expression\([^\']*\)[^\']*\')/i', '', $clean) ?? '';
    $clean   = preg_replace('/\s(href|src)\s*=\s*("|\')\s*javascript:[^\2]*\2/i', '', $clean) ?? '';
    return trim($clean);
}

function format_notice_time(string $raw): string {
    $raw = trim(str_replace('T', ' ', $raw));
    $ts = strtotime($raw);
    return ($raw === '' || $ts === false) ? $raw : date('H:i d-m-Y', $ts);
}

// 4. Extracting fields
$bodyRaw  = (string)($notice['body'] ?? '');
$payload  = json_decode($bodyRaw, true);
$isV2     = is_array($payload) && ($payload['schema'] ?? '') === 'notx_v2';
$payload  = $isV2 ? $payload : [];

$meta         = json_decode(trim((string)($notice['meta_json'] ?? '')), true) ?: [];
$orderId      = trim((string)($meta['order_id'] ?? ($payload['order_id'] ?? '')));
$resolvedType = resolve_notice_type((string)($notice['type'] ?? ''), $orderId);
$preset       = notice_type_visual_preset($resolvedType);
$statusRaw    = trim((string)($meta['status'] ?? ($payload['status'] ?? '')));
$statusLabel  = $statusRaw !== '' ? notice_status_label_vi($statusRaw) : '';
$amountText   = normalize_notice_money_text($meta['amount'] ?? ($payload['amount'] ?? ''));

$title      = trim((string)($payload['title'] ?? ($notice['title'] ?? ($preset['title'] ?? 'Thông báo'))));
$subtitle   = trim((string)($payload['subtitle'] ?? ''));
$thumbImage = trim((string)($payload['thumb_image'] ?? ($meta['thumb_image'] ?? '')));
$thumbIcon  = trim((string)($payload['thumb_icon'] ?? ($meta['thumb_icon'] ?? ($preset['icon'] ?? 'bi bi-megaphone-fill'))));

$rawContent            = $payload['content'] ?? ($notice['body'] ?? '');
$normalizedTextContent = to_plain_text((string)$rawContent);
$computedTextByType    = build_notice_text_by_type($resolvedType, $normalizedTextContent, $orderId, $statusLabel, $amountText);
$contentHtml           = sanitize_notice_html((string)$rawContent);
$content               = $contentHtml !== '' ? $contentHtml : nl2br(htmlspecialchars($computedTextByType !== '' ? $computedTextByType : $normalizedTextContent, ENT_QUOTES, 'UTF-8'));

$templateCode      = strtolower(trim((string)($payload['template'] ?? 'tpl1')));
$isCoverTemplate   = in_array($templateCode, ['tpl1', 'tpl4'], true);
$isProductTemplate = $templateCode === 'tpl2';

$banners = parse_notice_banners($payload['banners'] ?? []);
if (empty($banners)) {
    $banners = parse_notice_banners($meta['banners'] ?? ($meta['banner'] ?? []));
}
$banners = array_slice($banners, 0, $isCoverTemplate ? 1 : 3);

$bannerProductIds = [];
if ($isProductTemplate && isset($payload['product_ids']) && is_array($payload['product_ids'])) {
    foreach ($payload['product_ids'] as $pidRaw) {
        $pid = (int)$pidRaw;
        if ($pid > 0) $bannerProductIds[] = $pid;
    }
}

$attachedProducts = [];
if (!empty($bannerProductIds)) {
    $idList = implode(',', $bannerProductIds);
    $resP   = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id) AS min_price FROM ecommerce_product p WHERE p.id IN ($idList)");
    if ($resP) {
        while ($rowP = $resP->fetch_assoc()) {
            $pid   = (int)$rowP['id'];
            $pName = trim((string)$rowP['product_name']);
            // Chuẩn hoá ảnh: có thể là JSON array, CSV hoặc 1 path đơn lẻ; luôn trả về absolute URL
            $imgRaw = trim((string)$rowP['image_url']);
            $imgFirst = '';
            if ($imgRaw !== '') {
                $decoded = json_decode($imgRaw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $imgFirst = trim((string)$decoded[0]);
                } else {
                    $parts = preg_split('/[\r\n,|]+/', $imgRaw);
                    foreach ((array)$parts as $candidate) {
                        $candidate = trim((string)$candidate);
                        if ($candidate !== '') { $imgFirst = $candidate; break; }
                    }
                }
            }
            if ($imgFirst !== '') {
                $imgFirst = to_abs_url($imgFirst, (string)$baseUrl);
            }
            $attachedProducts[$pid] = [
                'id'        => $pid,
                'name'      => $pName,
                'image_url' => $imgFirst,
                'price'     => (float)$rowP['min_price'],
                'url'       => function_exists('pm_product_url') ? pm_product_url($pid, $pName, (string)$baseUrl) : 'view-product?pid=' . $pid
            ];
        }
        $resP->close();
    }
}

$createdAt  = format_notice_time((string)($notice['created_at'] ?? ''));
$backFilter = trim((string)($preset['filter'] ?? 'system'));

$typeBadgeLabelMap = [
    'order'     => 'Đơn hàng', 'payment' => 'Thanh toán', 'security' => 'Bảo mật',
    'account'   => 'Tài khoản', 'promotion' => 'Khuyến mãi', 'system' => 'Hệ thống',
    'complaint' => 'Hỗ trợ', 'post' => 'Tương tác',
];
$typeBadgeLabel = $typeBadgeLabelMap[$resolvedType] ?? 'Thông báo';

jOut([
    'ok'   => true,
    'data' => [
        'title'             => $title,
        'subtitle'          => $subtitle,
        'content'           => $content,
        'createdAt'         => $createdAt,
        'typeBadgeLabel'    => $typeBadgeLabel,
        'thumbIcon'         => $thumbIcon,
        'thumbImage'        => $thumbImage,
        'banners'           => $banners,
        'attachedProducts'  => array_values($attachedProducts),
        'orderId'           => $orderId,
        'backFilter'        => $backFilter,
        'isProductTemplate' => $isProductTemplate,
        'bannerProductIds'  => $bannerProductIds,
        'mainBanner'        => trim((string)($payload['main_banner'] ?? '')),
    ]
]);
