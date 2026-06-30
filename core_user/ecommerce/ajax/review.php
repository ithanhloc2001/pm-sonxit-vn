<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
// Hàm tiện ích để chuẩn hóa dữ liệu media của đánh giá, đảm bảo luôn trả về một mảng các item có cấu trúc ['type' => 'image'|'video', 'url' => '...']
function normalizeReviewMedia($raw): array {
    if ($raw === null) return [];
    $list = [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $list = $decoded;
    } elseif (is_array($raw)) {
        $list = $raw;
    }
    if (!$list) return [];

    $clean = [];
    foreach ($list as $item) {
        if (is_string($item)) {
            $url = trim($item);
            if ($url !== '') {
                if (preg_match('/^(javascript|data|vbscript|file|onclick|onload|onerror):/i', $url)) {
                    continue;
                }
                $clean[] = ['type' => 'image', 'url' => $url];
            }
            continue;
        }
        if (!is_array($item)) continue;
        $url = trim((string)($item['url'] ?? ''));
        if ($url === '') continue;
        if (preg_match('/^(javascript|data|vbscript|file|onclick|onload|onerror):/i', $url)) {
            continue;
        }
        $type = strtolower(trim((string)($item['type'] ?? 'image')));
        if (!in_array($type, ['image', 'video'], true)) {
            $type = 'image';
        }
        $clean[] = ['type' => $type, 'url' => $url];
    }
    return $clean;
}
// Hàm tiện ích để chuẩn hóa dữ liệu file upload từ $_FILES, hỗ trợ cả trường hợp upload đơn lẻ và nhiều file cùng lúc
function normalizeUploadedFiles(array $fileInput): array {
    $files = [];
    $names = $fileInput['name'] ?? null;
    if (!is_array($names)) {
        if (!empty($fileInput)) {
            $files[] = $fileInput;
        }
        return $files;
    }
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name' => $fileInput['name'][$i] ?? '',
            'type' => $fileInput['type'][$i] ?? '',
            'tmp_name' => $fileInput['tmp_name'][$i] ?? '',
            'error' => $fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'][$i] ?? 0,
        ];
    }
    return $files;
}
// Hàm tiện ích để đảm bảo thư mục lưu file đánh giá tồn tại và có quyền ghi, trả về đường dẫn hệ thống (fs) và đường dẫn web (web) nếu thành công, hoặc lỗi nếu không thể sử dụng thư mục
function ensureReviewUploadDir(): array {
    global $uploadFolder;
    $root = realpath(__DIR__ . '/../../../' . ($uploadFolder ?? 'uploads')) ?: (__DIR__ . '/../../../' . ($uploadFolder ?? 'uploads'));
    $fsDir = $root . DIRECTORY_SEPARATOR . 'reviews';
    $webDir = ($uploadFolder ?? 'uploads') . '/reviews';
    if (!is_dir($fsDir)) {
        @mkdir($fsDir, 0755, true);
    }
    if (!is_dir($fsDir) || !is_writable($fsDir)) {
        return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/reviews'];
    }
    return ['ok' => true, 'fs' => $fsDir, 'web' => $webDir];
}
// Hàm tiện ích để xử lý việc lưu file media của đánh giá, bao gồm kiểm tra loại file, kích thước, và lưu vào thư mục uploads/reviews, trả về danh sách các file đã lưu hoặc lỗi nếu có vấn đề
function saveReviewMediaFiles(array $fileInput, int $userId): array {
    $files = normalizeUploadedFiles($fileInput);
    if (!$files) return ['ok' => true, 'files' => []];

    $dir = ensureReviewUploadDir();
    if (!($dir['ok'] ?? false)) return $dir;

    $allowedImages = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $allowedVideos = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
    $maxImageSize = 5 * 1024 * 1024;
    $maxVideoSize = 30 * 1024 * 1024;
    $maxFiles = 6;

    $saved = [];
    foreach ($files as $file) {
        if (count($saved) >= $maxFiles) break;
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'msg' => 'Upload file thất bại'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'msg' => 'Tệp upload không hợp lệ'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) continue;

        $mime = '';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
        if ($mime === '') {
            $mime = (string)($file['type'] ?? '');
        }

        $type = '';
        $ext = '';
        if (isset($allowedImages[$mime])) {
            $type = 'image';
            $ext = $allowedImages[$mime];
            if ($size > $maxImageSize) {
                return ['ok' => false, 'msg' => 'Ảnh vượt quá dung lượng cho phép'];
            }
        } elseif (isset($allowedVideos[$mime])) {
            $type = 'video';
            $ext = $allowedVideos[$mime];
            if ($size > $maxVideoSize) {
                return ['ok' => false, 'msg' => 'Video vượt quá dung lượng cho phép'];
            }
        } else {
            return ['ok' => false, 'msg' => 'Chỉ hỗ trợ ảnh hoặc video mp4/webm/mov'];
        }

        $fileName = 'review_' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destFs = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
        if (!@move_uploaded_file($tmp, $destFs)) {
            return ['ok' => false, 'msg' => 'Không thể lưu file upload'];
        }
        $reviewRel = $dir['web'] . '/' . $fileName;
        if (function_exists('media_publish_local_file')) {
            media_publish_local_file($reviewRel);
        }
        $saved[] = ['type' => $type, 'url' => $reviewRel];
    }

    return ['ok' => true, 'files' => $saved];
}
// Hàm tiện ích để lấy danh sách review của một sản phẩm, bao gồm cả phần summary thống kê và phần chi tiết các đánh giá, trả về cấu trúc dữ liệu phù hợp để frontend có thể hiển thị
function fetchUserLikedReviewIds(mysqli $ithanhloc, int $userId): array {
    if ($userId < 0) return [];
    $stmt = $ithanhloc->prepare('SELECT review_id FROM ecommerce_product_review_like WHERE user_id=?');
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $ids = [];
    foreach ($rows as $row) {
        $rid = (int)($row['review_id'] ?? 0);
        if ($rid > 0) $ids[$rid] = true;
    }
    return array_keys($ids);
}

function fetchGuestLikedReviewIds(mysqli $ithanhloc, string $guestKey): array {
    $guestKey = trim($guestKey);
    if ($guestKey === '') return [];
    $likeCols = listColumns($ithanhloc, 'ecommerce_product_review_like');
    if (!hasCol($likeCols, 'guest_key')) return [];
    $stmt = $ithanhloc->prepare('SELECT review_id FROM ecommerce_product_review_like WHERE user_id=0 AND guest_key=?');
    if (!$stmt) return [];
    $stmt->bind_param('s', $guestKey);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $ids = [];
    foreach ($rows as $row) {
        $rid = (int)($row['review_id'] ?? 0);
        if ($rid > 0) $ids[$rid] = true;
    }
    return array_keys($ids);
}
// Hàm tiện ích để lấy danh sách review của một sản phẩm, bao gồm cả phần summary thống kê và phần chi tiết các đánh giá, trả về cấu trúc dữ liệu phù hợp để frontend có thể hiển thị
function maskPhoneMiddle(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') return '';
    $len = strlen($digits);
    if ($len <= 4) return $digits;
    if ($len <= 7) {
        return substr($digits, 0, 2) . str_repeat('x', max(0, $len - 4)) . substr($digits, -2);
    }
    return substr($digits, 0, 3) . str_repeat('x', max(0, $len - 7)) . substr($digits, -4);
}
// Hàm tiện ích để lấy danh sách review của một sản phẩm, bao gồm cả phần summary thống kê và phần chi tiết các đánh giá, trả về cấu trúc dữ liệu phù hợp để frontend có thể hiển thị
function fetchProductReviewsBundle(mysqli $ithanhloc, int $pid): array {
    $viewerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
    $likedIds = [];
    if ($viewerId >= 0) {
        $likedIds = fetchUserLikedReviewIds($ithanhloc, $viewerId);
    } else {
        $guestKey = '';
        if (!empty($_COOKIE['pm_guest_key']) && preg_match('/^[A-Za-z0-9]{16,64}$/', (string)$_COOKIE['pm_guest_key'])) {
            $guestKey = (string)$_COOKIE['pm_guest_key'];
        }
        $likedIds = $guestKey !== '' ? fetchGuestLikedReviewIds($ithanhloc, $guestKey) : [];
    }
    $likedMap = [];
    foreach ($likedIds as $id) { $likedMap[(int)$id] = true; }

    $summary = [
        'rating_avg' => 0,
        'rating_count' => 0,
        'total_comments' => 0,
        'has_media_count' => 0,
        'distribution' => ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0],
    ];

    $stmtSum = $ithanhloc->prepare("SELECT
        AVG(CASE WHEN rating > 0 THEN rating END) AS avg_r,
        SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) AS cnt_r,
        COUNT(*) AS cnt_all,
        SUM(CASE WHEN media_json IS NOT NULL AND media_json != '' AND media_json != '[]' THEN 1 ELSE 0 END) AS cnt_media
        FROM ecommerce_product_review
        WHERE product_id = ? AND status = 1");
    if ($stmtSum) {
        $stmtSum->bind_param('i', $pid);
        $stmtSum->execute();
        $row = $stmtSum->get_result()->fetch_assoc();
        $stmtSum->close();
        $summary['rating_avg'] = (float)($row['avg_r'] ?? 0);
        $summary['rating_count'] = (int)($row['cnt_r'] ?? 0);
        $summary['total_comments'] = (int)($row['cnt_all'] ?? 0);
        $summary['has_media_count'] = (int)($row['cnt_media'] ?? 0);
    }

    $stmtDist = $ithanhloc->prepare("SELECT rating, COUNT(*) AS cnt
        FROM ecommerce_product_review
        WHERE product_id = ? AND status = 1 AND rating BETWEEN 1 AND 5
        GROUP BY rating");
    if ($stmtDist) {
        $stmtDist->bind_param('i', $pid);
        $stmtDist->execute();
        $rows = $stmtDist->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDist->close();
        foreach ($rows as $r) {
            $rk = (string)intval($r['rating'] ?? 0);
            if (isset($summary['distribution'][$rk])) {
                $summary['distribution'][$rk] = (int)($r['cnt'] ?? 0);
            }
        }
    }

    $filterRating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
    $filterMedia = isset($_GET['has_media']) && $_GET['has_media'] === '1';

    $where = "WHERE r.product_id = ? AND r.status = 1";
    if ($filterRating >= 1 && $filterRating <= 5) {
        $where .= " AND r.rating = $filterRating";
    }
    if ($filterMedia) {
        $where .= " AND r.media_json IS NOT NULL AND r.media_json != '' AND r.media_json != '[]'";
    }

    $reviews = [];
    $stmtRev = $ithanhloc->prepare("SELECT
        r.id, r.parent_id, r.user_id, r.rating, r.review_type, r.comment, r.tags, r.media_json, r.like_count, r.created_at,
        r.display_name, r.phone,
        u.full_name, u.username, u.role AS user_role
        FROM ecommerce_product_review r
        LEFT JOIN users u ON u.id = r.user_id
        $where
        ORDER BY r.parent_id ASC, r.created_at DESC, r.id DESC
        LIMIT 300");
    if ($stmtRev) {
        $stmtRev->bind_param('i', $pid);
        $stmtRev->execute();
        $rows = $stmtRev->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtRev->close();

        // ===== Verified-buyer: tập user_ids đã thực sự MUA + ĐÃ NHẬN sản phẩm này =====
        // Tính 1 lần cho cả batch để tránh N+1 query.
        // Quy tắc: status = 'delivered' (đã giao thành công). KHÔNG nhận pending/processing/canceled/returned.
        $verifiedUserIds = [];
        $candidateUserIds = [];
        foreach ($rows as $row) {
            $u = (int)($row['user_id'] ?? 0);
            if ($u > 0) $candidateUserIds[$u] = true;
        }
        if (!empty($candidateUserIds)) {
            $userIdsList = implode(',', array_map('intval', array_keys($candidateUserIds)));
            // Dùng JSON_CONTAINS (MySQL 5.7+); fallback LIKE nếu hệ thống không hỗ trợ JSON
            $sqlVerify = "SELECT DISTINCT user_id FROM ecommerce_order
                WHERE user_id IN ($userIdsList)
                  AND status = 'delivered'
                  AND products_json IS NOT NULL
                  AND products_json != ''
                  AND JSON_CONTAINS(products_json, JSON_OBJECT('product_id', CAST(? AS UNSIGNED)), '$[*]') = 1";
            $stmtV = @$ithanhloc->prepare($sqlVerify);
            if ($stmtV) {
                $stmtV->bind_param('i', $pid);
                @$stmtV->execute();
                $resV = @$stmtV->get_result();
                if ($resV) {
                    while ($rV = $resV->fetch_assoc()) {
                        $uid = (int)$rV['user_id'];
                        if ($uid > 0) $verifiedUserIds[$uid] = true;
                    }
                }
                $stmtV->close();
            } else {
                // Fallback LIKE: không cần JSON_CONTAINS
                $stmtVL = $ithanhloc->prepare("SELECT DISTINCT user_id FROM ecommerce_order
                    WHERE user_id IN ($userIdsList)
                      AND status = 'delivered'
                      AND products_json LIKE ?");
                if ($stmtVL) {
                    $pattern = '%"product_id":' . (int)$pid . '%';
                    $stmtVL->bind_param('s', $pattern);
                    $stmtVL->execute();
                    $resVL = $stmtVL->get_result();
                    if ($resVL) {
                        while ($rVL = $resVL->fetch_assoc()) {
                            $uid = (int)$rVL['user_id'];
                            if ($uid > 0) $verifiedUserIds[$uid] = true;
                        }
                    }
                    $stmtVL->close();
                }
            }
        }

        $indexed = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $name = trim((string)($row['display_name'] ?? ''));
            if ($name === '') $name = trim((string)($row['full_name'] ?? ''));
            if ($name === '') $name = trim((string)($row['username'] ?? ''));
            if ($name === '') $name = 'Khách hàng';
            $uid = (int)($row['user_id'] ?? 0);
            $reviewType = (string)($row['review_type'] ?? 'review');
            $rating = (int)($row['rating'] ?? 0);
            $userRole = (string)($row['user_role'] ?? '');
            // Verified buyer chỉ áp dụng cho review TOP-LEVEL (parent_id=0) của user thường,
            // có rating > 0 (tức là review thật), và user_id đã có order delivered chứa product này
            $isVerified = (
                $uid > 0
                && (int)($row['parent_id'] ?? 0) === 0
                && $rating > 0
                && $reviewType !== 'deleted'
                && $userRole !== 'admin'
                && isset($verifiedUserIds[$uid])
            );
            $indexed[$id] = [
                'id' => $id,
                'parent_id' => (int)($row['parent_id'] ?? 0),
                'user_id' => $uid,
                'name' => $name,
                'user_role' => $userRole,
                'phone_mask' => maskPhoneMiddle((string)($row['phone'] ?? '')),
                'rating' => $rating,
                'review_type' => $reviewType,
                'comment' => (string)($row['comment'] ?? ''),
                'tags' => (string)($row['tags'] ?? ''),
                'media' => normalizeReviewMedia($row['media_json'] ?? null),
                'like_count' => (int)($row['like_count'] ?? 0),
                'liked_by_me' => isset($likedMap[$id]),
                'created_at' => (string)($row['created_at'] ?? ''),
                'is_verified_buyer' => $isVerified,
                'replies' => [],
            ];
        }

        foreach ($indexed as $id => $item) {
            $parent = (int)($item['parent_id'] ?? 0);
            if ($parent > 0 && isset($indexed[$parent])) {
                $indexed[$parent]['replies'][] = $item;
            }
        }

        foreach ($indexed as $id => $item) {
            if ((int)($item['parent_id'] ?? 0) === 0) {
                $reviews[] = $item;
            }
        }
    }

    return ['summary' => $summary, 'reviews' => $reviews];
}

/**
 * Lấy thông tin cơ bản của sản phẩm (tên, chủ sở hữu) để phục vụ logging/notification
 */
function fetchMinimalProductInfo(mysqli $ithanhloc, int $pid): array {
    $name = '';
    $ownerId = 0;
    $stmt = $ithanhloc->prepare('SELECT product_name FROM ecommerce_product WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) $name = trim((string)($row['product_name'] ?? ''));
        $stmt->close();
    }
    if ($name === '') $name = 'Sản phẩm #' . $pid;
    if (function_exists('app_resolve_product_owner_user_id')) {
        $ownerId = (int)app_resolve_product_owner_user_id($ithanhloc, $pid);
    }
    return ['name' => $name, 'owner_id' => $ownerId];
}

/**
 * Xác định tên hiển thị của người thực hiện hành động (User hoặc Guest)
 */
function resolveReviewActorName(mysqli $ithanhloc, int $userId, array $guestData = []): string {
    if ($userId >= 0) {
        $stmt = $ithanhloc->prepare('SELECT full_name, username FROM users WHERE id=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $name = trim((string)($row['full_name'] ?? ''));
            if ($name === '') $name = trim((string)($row['username'] ?? ''));
            return $name !== '' ? $name : 'Khách hàng';
        }
    }
    $gName = trim((string)($guestData['name'] ?? ''));
    return $gName !== '' ? $gName : 'Khách';
}

/**
 * Ghi log activity và gửi thông báo hệ thống cho các tương tác review (add, edit, like, delete)
 */
function logReviewActivity(mysqli $ithanhloc, string $event, array $data): void {
    $reviewId = (int)($data['review_id'] ?? 0);
    $pid = (int)($data['pid'] ?? 0);
    $userId = (int)($data['user_id'] ?? -1);
    $parentId = (int)($data['parent_id'] ?? 0);
    $rating = (int)($data['rating'] ?? 0);
    $comment = (string)($data['comment'] ?? '');
    $mediaJson = $data['media_json'] ?? null;
    $guestKey = (string)($data['guest_key'] ?? '');
    
    $pInfo = fetchMinimalProductInfo($ithanhloc, $pid);
    $productName = $pInfo['name'];
    $ownerId = $pInfo['owner_id'];
    $actorName = resolveReviewActorName($ithanhloc, $userId, ['name' => $data['actor_name'] ?? '']);
    
    global $baseUrl;
    $base = rtrim((string)($baseUrl ?? ''), '/');
    $link = function_exists('app_build_product_detail_link') ? app_build_product_detail_link($pid, $base) : ($base . '/view-product?pid=' . $pid);
    $threadKey = 'product:' . $pid;

    // 1. Thread & Notification Comment Log
    if (function_exists('app_get_or_create_post_thread')) {
        $threadTitle = 'Bình luận & đánh giá sản phẩm: ' . $productName;
        $threadId = (int)app_get_or_create_post_thread($ithanhloc, $threadKey, $ownerId, $threadTitle, $link, [
            'module' => 'post', 'event' => 'product_thread', 'object_type' => 'product', 'object_id' => $pid
        ]);
        if ($threadId > 0 && in_array($event, ['add', 'edit', 'delete'], true)) {
            if (function_exists('ensure_user_notification_comment_schema')) ensure_user_notification_comment_schema($ithanhloc);
            
            if ($event === 'add') {
                $logParentId = 0;
                if ($parentId > 0) {
                    $stmtLP = $ithanhloc->prepare("SELECT id FROM user_notification_comment WHERE notification_id=? AND ref_type='product_review' AND ref_id=? ORDER BY id DESC LIMIT 1");
                    if ($stmtLP) {
                        $stmtLP->bind_param('ii', $threadId, $parentId);
                        $stmtLP->execute();
                        $logParentId = (int)($stmtLP->get_result()->fetch_assoc()['id'] ?? 0);
                        $stmtLP->close();
                    }
                }
                $content = ($rating > 0 ? "[Đánh giá $rating/5] " : "") . $comment;
                $stmt = $ithanhloc->prepare("INSERT INTO user_notification_comment (notification_id, parent_id, user_id, guest_key, guest_name, content, media_json, ref_type, ref_id, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),NOW())");
                if ($stmt) {
                    $gk = $userId >= 0 ? null : $guestKey;
                    $gn = $userId >= 0 ? null : $actorName;
                    bindParamsDynamic($stmt, 'iiisssssi', [$threadId, $logParentId, max(0, $userId), $gk, $gn, $content, $mediaJson, 'product_review', $reviewId]);
                    $stmt->execute(); $stmt->close();
                }
            } elseif ($event === 'edit') {
                $content = ($rating > 0 ? "[Đánh giá $rating/5] " : "") . $comment;
                $stmt = $ithanhloc->prepare("UPDATE user_notification_comment SET content=?, media_json=?, updated_at=NOW() WHERE notification_id=? AND ref_type='product_review' AND ref_id=? ORDER BY id DESC LIMIT 1");
                if ($stmt) {
                    bindParamsDynamic($stmt, 'ssii', [$content, $mediaJson, $threadId, $reviewId]);
                    $stmt->execute(); $stmt->close();
                }
            } elseif ($event === 'delete') {
                $isActive = (int)($data['is_active'] ?? 0);
                if ($isActive === 0) {
                    $ithanhloc->query("UPDATE user_notification_comment SET is_active=0, updated_at=NOW() WHERE notification_id=$threadId AND ref_type='product_review' AND ref_id=$reviewId");
                } else {
                    $stmt = $ithanhloc->prepare("UPDATE user_notification_comment SET content=?, is_active=1, updated_at=NOW() WHERE notification_id=? AND ref_type='product_review' AND ref_id=? ORDER BY id DESC LIMIT 1");
                    if ($stmt) {
                        $c = $data['new_content'] ?? '[Bình luận đã được xóa]';
                        $stmt->bind_param('sii', $c, $threadId, $reviewId);
                        $stmt->execute(); $stmt->close();
                    }
                }
            }
        }
    }

    // 2. Event Log & Realtime Notification
    if (function_exists('app_log_post_event')) {
        $evtCode = $rating > 0 ? 'product_review' : 'product_comment';
        if ($parentId > 0) $evtCode = 'product_reply';
        if (strpos($event, 'edit') !== false) $evtCode .= '_edit';
        if (strpos($event, 'delete') !== false) $evtCode .= '_delete';
        if ($event === 'like') $evtCode = 'product_review_like';

        $title = $actorName . ' ' . ($parentId > 0 ? 'đã trả lời' : ($rating > 0 ? 'đã đánh giá' : 'vừa bình luận')) . ' sản phẩm';
        if ($event === 'like') $title = $actorName . ' đã thích một đánh giá';
        
        $meta = array_merge($data['meta'] ?? [], [
            'module' => 'post', 'event' => $evtCode, 'object_type' => 'product', 'object_id' => $pid,
            'review_id' => $reviewId, 'actor_user_id' => $userId, 'thread_key' => $threadKey
        ]);

        if ($ownerId >= 0 && $ownerId !== $userId) {
            @app_log_post_event($ithanhloc, $ownerId, $title, $productName, $link, $meta, $userId);
        }
        
        // Notify Parent Owner on Reply
        if ($parentId > 0 && ($event === 'add' || $event === 'edit')) {
            $stmt = $ithanhloc->prepare('SELECT user_id FROM ecommerce_product_review WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $parentId); $stmt->execute();
                $pOwner = (int)($stmt->get_result()->fetch_assoc()['user_id'] ?? -1);
                $stmt->close();
                if ($pOwner >= 0 && $pOwner !== $userId && $pOwner !== $ownerId) {
                    $meta['event'] = 'product_reply_to_you';
                    @app_log_post_event($ithanhloc, $pOwner, $actorName . ' đã trả lời bình luận của bạn', $productName, $link, $meta, $userId);
                }
            }
        }
    }
}

/**
 * Tự động trả lời (dưới tên Quản trị viên) một bình luận/đánh giá gốc của khách.
 * Đọc cấu hình mặc định từ setting.php (AUTO_REPLY.*).
 *
 * @param int $pid        ID sản phẩm
 * @param int $parentId   ID bình luận/đánh giá gốc vừa tạo (phải là top-level, parent_id=0)
 * @param int $rating     Số sao của bình luận gốc (>0 => đánh giá; =0 => câu hỏi)
 * @param int $actorUserId user_id của khách vừa gửi (để tránh tự trả lời chính admin)
 * @return int            ID bản ghi reply vừa tạo, hoặc 0 nếu không tạo.
 */
function maybeAutoReplyReview(mysqli $ithanhloc, int $pid, int $parentId, int $rating, int $actorUserId): int {
    if ($pid <= 0 || $parentId <= 0) return 0;

    // 1. Kiểm tra bật/tắt
    if (!function_exists('app_get_config_value_by_path')) return 0;
    // Helper cục bộ: ép giá trị cấu hình về bool
    $cfgOn = function($v) {
        return ($v === true || $v === 1 || $v === '1' || strtolower((string)$v) === 'true');
    };
    // Công tắc tổng
    if (!$cfgOn(app_get_config_value_by_path('AUTO_REPLY.enabled'))) return 0;
    // Công tắc riêng theo loại (mặc định BẬT nếu key chưa được cấu hình)
    if ($rating > 0) {
        $perTypeRaw = app_get_config_value_by_path('AUTO_REPLY.review_enabled');
        if ($perTypeRaw !== null && !$cfgOn($perTypeRaw)) return 0;
    } else {
        $perTypeRaw = app_get_config_value_by_path('AUTO_REPLY.question_enabled');
        if ($perTypeRaw !== null && !$cfgOn($perTypeRaw)) return 0;
    }

    // 2. Không tự trả lời nếu chính người gửi đã là admin (tránh bot trả lời admin)
    if ($actorUserId > 0) {
        $stmtR = $ithanhloc->prepare('SELECT role FROM users WHERE id=? LIMIT 1');
        if ($stmtR) {
            $stmtR->bind_param('i', $actorUserId);
            $stmtR->execute();
            $actorRole = (string)($stmtR->get_result()->fetch_assoc()['role'] ?? '');
            $stmtR->close();
            if ($actorRole === 'admin') return 0;
        }
    }

    // 3. Tránh trả lời trùng: nếu đã có reply của admin cho bình luận gốc này thì bỏ qua
    $stmtDup = $ithanhloc->prepare(
        "SELECT r.id FROM ecommerce_product_review r
         JOIN users u ON u.id = r.user_id
         WHERE r.parent_id=? AND u.role='admin' AND r.status=1 LIMIT 1"
    );
    if ($stmtDup) {
        $stmtDup->bind_param('i', $parentId);
        $stmtDup->execute();
        $hasAdminReply = (bool)$stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();
        if ($hasAdminReply) return 0;
    }

    // 4. Chọn nội dung theo loại (đánh giá có sao vs câu hỏi)
    if ($rating > 0) {
        $content = (string)app_get_config_value_by_path('AUTO_REPLY.review_text');
        if (trim($content) === '') {
            $content = 'Cảm ơn bạn đã đánh giá sản phẩm! Shop rất trân trọng phản hồi của bạn và sẽ tiếp tục cố gắng để phục vụ bạn tốt hơn.';
        }
    } else {
        $content = (string)app_get_config_value_by_path('AUTO_REPLY.question_text');
        if (trim($content) === '') {
            $content = 'Cảm ơn bạn đã đặt câu hỏi! Shop đã ghi nhận và sẽ phản hồi chi tiết trong thời gian sớm nhất. Bạn có thể liên hệ hotline để được hỗ trợ nhanh hơn nhé.';
        }
    }

    // 5. Xác định admin để gán user_id (để frontend hiển thị badge "Quản trị viên")
    $adminId = 0;
    $adminName = '';
    $resAdmin = $ithanhloc->query("SELECT id, full_name, username FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1");
    if ($resAdmin) {
        $adminRow = $resAdmin->fetch_assoc();
        $resAdmin->close();
        if ($adminRow) {
            $adminId = (int)($adminRow['id'] ?? 0);
            $adminName = trim((string)($adminRow['full_name'] ?? ''));
            if ($adminName === '') $adminName = trim((string)($adminRow['username'] ?? ''));
        }
    }
    if ($adminName === '') $adminName = 'Quản trị viên';

    // 6. Insert reply (parent_id = bình luận gốc, rating=0, review_type='question')
    $stmt = $ithanhloc->prepare(
        "INSERT INTO ecommerce_product_review
            (product_id, parent_id, user_id, rating, review_type, display_name, phone, comment, tags, media_json, status, created_at)
         VALUES (?, ?, ?, 0, 'question', ?, '', ?, '', NULL, 1, NOW())"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('iiiss', $pid, $parentId, $adminId, $adminName, $content);
    if (!$stmt->execute()) { $stmt->close(); return 0; }
    $replyId = (int)$ithanhloc->insert_id;
    $stmt->close();

    // 7. Ghi log như một reply bình thường (giữ luồng thông báo nhất quán)
    logReviewActivity($ithanhloc, 'add', [
        'review_id' => $replyId, 'pid' => $pid, 'user_id' => $adminId, 'parent_id' => $parentId,
        'rating' => 0, 'comment' => $content, 'media_json' => null, 'actor_name' => $adminName,
    ]);

    return $replyId;
}

// Khi truy cập trực tiếp review.php từ trình duyệt (không phải include như thư viện),
// cho phép dùng như một endpoint read-only để lấy danh sách đánh giá sản phẩm.
if (basename(__FILE__) === basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once __DIR__ . '/../../../config.php';
    if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
        @$ithanhloc->set_charset('utf8mb4');
    }

    $ajax = $_GET['ajax'] ?? null;



    if ($ajax === 'product_reviews') {
        $pid = intval($_GET['pid'] ?? 0);
        if ($pid <= 0) {
            jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
        }
        /** @var mysqli $ithanhloc */
        $bundle = fetchProductReviewsBundle($ithanhloc, $pid);
        jOut([
            'ok' => true,
            'summary' => $bundle['summary'],
            'reviews' => $bundle['reviews'],
        ]);
    }

    jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
}

