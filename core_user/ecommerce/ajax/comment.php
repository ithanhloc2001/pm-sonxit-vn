<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/review.php';

if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
    @$ithanhloc->set_charset('utf8mb4');
}

$ajax = $_GET['ajax'] ?? null;
$action = (string)($_REQUEST['action'] ?? '');
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : -1;
$guestKey = (function_exists('app_guest_key')) ? app_guest_key('pm_guest_key') : '';

/**
 * Helper to extract media files from $_FILES
 */
function getMediaInput() {
    if (isset($_FILES['review_media'])) return $_FILES['review_media'];
    foreach ($_FILES as $key => $fileGroup) {
        if (stripos((string)$key, 'review_media') === 0) return $fileGroup;
    }
    return null;
}

// === CSRF PROTECTION FOR STATE-CHANGING ACTIONS ===
if (in_array($action, ['product_review_add', 'product_review_like', 'product_review_edit', 'product_review_delete'], true)) {
    app_verify_csrf();

    // ── Rate Limiting ─────────────────────────────────────────────────────────
    // Gửi bình luận/đánh giá mới: tối đa 3 lần/5 phút/IP
    if ($action === 'product_review_add' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'review_add',
            3,   // tối đa 3 bình luận
            300, // trong 5 phút (300 giây)
            'Bạn đang gửi bình luận quá nhanh. Vui lòng chờ 5 phút rồi thử lại.'
        );
    }
    // Thả tim: tối đa 20 lần/phút/IP (chống spam tim hàng loạt)
    if ($action === 'product_review_like' && function_exists('app_rate_limit_response')) {
        app_rate_limit_response(
            'review_like',
            20, // tối đa 20 lần
            60, // trong 60 giây
            'Bạn đang tương tác quá nhanh. Vui lòng chờ vài giây rồi thử lại.'
        );
    }
    // ─────────────────────────────────────────────────────────────────────────
}


// ===== THÊM BÌNH LUẬN SẢN PHẨM =====
if ($action === 'product_review_add') {
    $pid = intval($_REQUEST['pid'] ?? 0);
    $parentId = max(0, intval($_REQUEST['parent_id'] ?? 0));
    $rating = max(0, min(5, intval($_REQUEST['rating'] ?? 0)));
    $comment = trim((string)($_REQUEST['content'] ?? ''));

    if ($pid <= 0) jOut(['ok' => false, 'msg' => 'Thiếu sản phẩm']);
    if ($comment === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập nội dung']);
    if (mb_strlen($comment) > 2000) jOut(['ok' => false, 'msg' => 'Nội dung quá dài']);

    if ($parentId > 0) $rating = 0;
    $reviewType = $rating > 0 ? 'review' : 'question';

    $actorName = resolveReviewActorName($ithanhloc, $userId, ['name' => $_REQUEST['guest_name'] ?? '']);
    $phone = $userId >= 0 ? '' : trim((string)($_REQUEST['guest_phone'] ?? ''));

    if ($parentId > 0) {
        $stmt = $ithanhloc->prepare('SELECT parent_id FROM ecommerce_product_review WHERE id=? AND product_id=? AND status=1 LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $parentId, $pid); $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$p) $parentId = 0; else if ((int)($p['parent_id'] ?? 0) > 0) $parentId = (int)$p['parent_id'];
        }
    }

    $tags = trim((string)($_REQUEST['tags'] ?? ''));

    $mediaFiles = [];
    $mediaInput = getMediaInput();
    if (is_array($mediaInput)) {
        $upload = saveReviewMediaFiles($mediaInput, $userId);
        if (!($upload['ok'] ?? false)) jOut(['ok' => false, 'msg' => $upload['msg'] ?? 'Lỗi upload']);
        $mediaFiles = $upload['files'] ?? [];
    }
    $mediaJson = $mediaFiles ? json_encode($mediaFiles, JSON_UNESCAPED_UNICODE) : null;

    $stmt = $ithanhloc->prepare("INSERT INTO ecommerce_product_review (product_id, parent_id, user_id, rating, review_type, display_name, phone, comment, tags, media_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Lỗi hệ thống']);
    bindParamsDynamic($stmt, 'iiiissssss', [$pid, $parentId, $userId, $rating, $reviewType, $actorName, $phone, $comment, $tags, $mediaJson]);
    if (!$stmt->execute()) jOut(['ok' => false, 'msg' => 'Không thể gửi bình luận']);
    $newId = (int)$ithanhloc->insert_id;
    $stmt->close();

    logReviewActivity($ithanhloc, 'add', [
        'review_id' => $newId, 'pid' => $pid, 'user_id' => $userId, 'parent_id' => $parentId,
        'rating' => $rating, 'comment' => $comment, 'media_json' => $mediaJson, 'guest_key' => $guestKey
    ]);

    // Tự động trả lời (dưới tên Quản trị viên) cho bình luận/đánh giá GỐC của khách.
    // Chỉ áp dụng cho top-level (parent_id=0); reply của khách không kích hoạt.
    if ($parentId === 0 && function_exists('maybeAutoReplyReview')) {
        maybeAutoReplyReview($ithanhloc, $pid, $newId, $rating, $userId);
    }

    $bundle = fetchProductReviewsBundle($ithanhloc, $pid);
    jOut(['ok' => true, 'msg' => 'Đã gửi bình luận thành công', 'summary' => $bundle['summary'], 'reviews' => $bundle['reviews']]);
}

// ===== THẢ TIM / BỎ THẢ TIM BÌNH LUẬN =====
if ($action === 'product_review_like') {
    $reviewId = (int)($_REQUEST['review_id'] ?? 0);
    if ($reviewId <= 0) jOut(['ok' => false, 'msg' => 'Thiếu bình luận']);

    $stmt = $ithanhloc->prepare('SELECT id, product_id, user_id FROM ecommerce_product_review WHERE id=? AND status=1 LIMIT 1');
    $stmt->bind_param('i', $reviewId); $stmt->execute();
    $reviewRow = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$reviewRow) jOut(['ok' => false, 'msg' => 'Bình luận không tồn tại']);

    $liked = false;
    if ($userId >= 0) {
        $stmt = $ithanhloc->prepare('SELECT id FROM ecommerce_product_review_like WHERE review_id=? AND user_id=? LIMIT 1');
        $stmt->bind_param('ii', $reviewId, $userId); $stmt->execute();
        $liked = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
    } else {
        if (!$guestKey) jOut(['ok' => false, 'msg' => 'Không thể nhận diện khách']);
        $stmt = $ithanhloc->prepare('SELECT id FROM ecommerce_product_review_like WHERE review_id=? AND user_id=0 AND guest_key=? LIMIT 1');
        $stmt->bind_param('is', $reviewId, $guestKey); $stmt->execute();
        $liked = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
    }

    if ($liked) {
        if ($userId >= 0) {
            $stmtDel = $ithanhloc->prepare('DELETE FROM ecommerce_product_review_like WHERE review_id=? AND user_id=?');
            if ($stmtDel) { $stmtDel->bind_param('ii', $reviewId, $userId); $stmtDel->execute(); $stmtDel->close(); }
        } else {
            $stmtDel = $ithanhloc->prepare('DELETE FROM ecommerce_product_review_like WHERE review_id=? AND user_id=0 AND guest_key=?');
            if ($stmtDel) { $stmtDel->bind_param('is', $reviewId, $guestKey); $stmtDel->execute(); $stmtDel->close(); }
        }
        $stmtUpd = $ithanhloc->prepare('UPDATE ecommerce_product_review SET like_count = GREATEST(like_count - 1, 0) WHERE id=?');
        if ($stmtUpd) { $stmtUpd->bind_param('i', $reviewId); $stmtUpd->execute(); $stmtUpd->close(); }
        $liked = false;
    } else {
        if ($userId >= 0) {
            $stmtIns = $ithanhloc->prepare('INSERT IGNORE INTO ecommerce_product_review_like (review_id, user_id, guest_key) VALUES (?, ?, NULL)');
            if ($stmtIns) { $stmtIns->bind_param('ii', $reviewId, $userId); $stmtIns->execute(); $stmtIns->close(); }
        } else {
            $stmtIns = $ithanhloc->prepare('INSERT IGNORE INTO ecommerce_product_review_like (review_id, user_id, guest_key) VALUES (?, 0, ?)');
            if ($stmtIns) { $stmtIns->bind_param('is', $reviewId, $guestKey); $stmtIns->execute(); $stmtIns->close(); }
        }
        $stmtUpd = $ithanhloc->prepare('UPDATE ecommerce_product_review SET like_count = like_count + 1 WHERE id=?');
        if ($stmtUpd) { $stmtUpd->bind_param('i', $reviewId); $stmtUpd->execute(); $stmtUpd->close(); }
        $liked = true;
    }

    $stmtLC = $ithanhloc->prepare('SELECT like_count FROM ecommerce_product_review WHERE id=?');
    $likeCount = 0;
    if ($stmtLC) {
        $stmtLC->bind_param('i', $reviewId);
        $stmtLC->execute();
        $lcRow = $stmtLC->get_result()->fetch_assoc();
        $stmtLC->close();
        $likeCount = (int)($lcRow['like_count'] ?? 0);
    }

    if ($liked) {
        logReviewActivity($ithanhloc, 'like', [
            'review_id' => $reviewId, 'pid' => $reviewRow['product_id'], 'user_id' => $userId, 'guest_key' => $guestKey
        ]);
    }

    jOut(['ok' => true, 'liked' => $liked, 'like_count' => $likeCount]);
}

// ===== CHỈNH SỬA BÌNH LUẬN =====
if ($action === 'product_review_edit') {
    if ($userId < 0) jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
    $reviewId = (int)($_REQUEST['review_id'] ?? 0);
    $comment = trim((string)($_REQUEST['content'] ?? ''));
    if ($reviewId <= 0 || $comment === '') jOut(['ok' => false, 'msg' => 'Thiếu thông tin']);
    if (mb_strlen($comment) > 2000) jOut(['ok' => false, 'msg' => 'Nội dung quá dài']);

    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    if ($isAdmin) {
        $stmt = $ithanhloc->prepare('SELECT product_id, parent_id, review_type, rating, media_json FROM ecommerce_product_review WHERE id=? AND status=1 LIMIT 1');
        $stmt->bind_param('i', $reviewId);
    } else {
        $stmt = $ithanhloc->prepare('SELECT product_id, parent_id, review_type, rating, media_json FROM ecommerce_product_review WHERE id=? AND user_id=? AND status=1 LIMIT 1');
        $stmt->bind_param('ii', $reviewId, $userId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) jOut(['ok' => false, 'msg' => 'Không có quyền chỉnh sửa']);

    $mediaFiles = [];
    $hasExistingParam = isset($_REQUEST['existing_media']);
    if ($hasExistingParam) {
        $mediaFiles = normalizeReviewMedia(json_decode((string)$_REQUEST['existing_media'], true));
    }
    $newMedia = getMediaInput();
    if (is_array($newMedia)) {
        $upload = saveReviewMediaFiles($newMedia, $userId);
        if ($upload['ok']) $mediaFiles = array_merge($mediaFiles, $upload['files']);
    }
    $mediaJson = !empty($mediaFiles) ? json_encode($mediaFiles, JSON_UNESCAPED_UNICODE) : ($hasExistingParam ? null : $row['media_json']);

    $rating = array_key_exists('rating', $_REQUEST) ? max(0, min(5, (int)$_REQUEST['rating'])) : (int)$row['rating'];
    if ((int)$row['parent_id'] > 0) $rating = 0;
    $reviewType = $rating > 0 ? 'review' : 'question';

    $editTags = array_key_exists('tags', $_REQUEST) ? trim((string)$_REQUEST['tags']) : null;

    if ($editTags !== null) {
        $stmt = $ithanhloc->prepare('UPDATE ecommerce_product_review SET comment=?, rating=?, review_type=?, media_json=?, tags=? WHERE id=? LIMIT 1');
        $stmt->bind_param('sisssi', $comment, $rating, $reviewType, $mediaJson, $editTags, $reviewId);
    } else {
        $stmt = $ithanhloc->prepare('UPDATE ecommerce_product_review SET comment=?, rating=?, review_type=?, media_json=? WHERE id=? LIMIT 1');
        $stmt->bind_param('sissi', $comment, $rating, $reviewType, $mediaJson, $reviewId);
    }
    if (!$stmt->execute()) jOut(['ok' => false, 'msg' => 'Lỗi cập nhật']);
    $stmt->close();

    logReviewActivity($ithanhloc, 'edit', [
        'review_id' => $reviewId, 'pid' => $row['product_id'], 'user_id' => $userId, 'parent_id' => $row['parent_id'],
        'rating' => $rating, 'comment' => $comment, 'media_json' => $mediaJson
    ]);

    $bundle = fetchProductReviewsBundle($ithanhloc, (int)$row['product_id']);
    jOut(['ok' => true, 'msg' => 'Đã cập nhật', 'summary' => $bundle['summary'], 'reviews' => $bundle['reviews']]);
}

// ===== XÓA BÌNH LUẬN =====
if ($action === 'product_review_delete') {
    if ($userId < 0) jOut(['ok' => false, 'msg' => 'Vui lòng đăng nhập']);
    $reviewId = (int)($_REQUEST['review_id'] ?? 0);
    if ($reviewId <= 0) jOut(['ok' => false, 'msg' => 'Thiếu bình luận']);

    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    if ($isAdmin) {
        $stmt = $ithanhloc->prepare('SELECT product_id, parent_id FROM ecommerce_product_review WHERE id=? AND status=1 LIMIT 1');
        $stmt->bind_param('i', $reviewId);
    } else {
        $stmt = $ithanhloc->prepare('SELECT product_id, parent_id FROM ecommerce_product_review WHERE id=? AND user_id=? AND status=1 LIMIT 1');
        $stmt->bind_param('ii', $reviewId, $userId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) jOut(['ok' => false, 'msg' => 'Không có quyền xóa']);

    if ($isAdmin) {
        // Admin: xóa vĩnh viễn toàn bộ — replies, likes, log liên quan
        // 1. Xóa toàn bộ replies (con của review này) cùng likes của chúng
        $childIds = [];
        $resChild = $ithanhloc->query("SELECT id FROM ecommerce_product_review WHERE parent_id=$reviewId");
        if ($resChild) {
            while ($c = $resChild->fetch_assoc()) $childIds[] = (int)$c['id'];
            $resChild->close();
        }
        if (!empty($childIds)) {
            $childList = implode(',', $childIds);
            $ithanhloc->query("DELETE FROM ecommerce_product_review_like WHERE review_id IN ($childList)");
            $ithanhloc->query("DELETE FROM ecommerce_product_review WHERE id IN ($childList)");
        }
        // 2. Xóa likes của review chính
        $ithanhloc->query("DELETE FROM ecommerce_product_review_like WHERE review_id=$reviewId");
        // 3. Xóa review chính
        $ithanhloc->query("DELETE FROM ecommerce_product_review WHERE id=$reviewId");
    } else {
        // User thường: nếu có reply → soft-delete để giữ ngữ cảnh; không có reply → xóa thật
        $hasChild = $ithanhloc->query("SELECT id FROM ecommerce_product_review WHERE parent_id=$reviewId AND status=1 LIMIT 1")->fetch_assoc();
        if ($hasChild) {
            $ithanhloc->query("UPDATE ecommerce_product_review SET comment='', review_type='deleted', rating=0, media_json=NULL WHERE id=$reviewId");
        } else {
            $ithanhloc->query("DELETE FROM ecommerce_product_review_like WHERE review_id=$reviewId");
            $ithanhloc->query("DELETE FROM ecommerce_product_review WHERE id=$reviewId");
        }
    }

    logReviewActivity($ithanhloc, 'delete', [
        'review_id' => $reviewId, 'pid' => $row['product_id'], 'user_id' => $userId, 'parent_id' => $row['parent_id'],
        'is_active' => 0, 'new_content' => '[Deleted]', 'rating' => 0
    ]);

    $bundle = fetchProductReviewsBundle($ithanhloc, (int)$row['product_id']);
    jOut(['ok' => true, 'msg' => 'Đã xóa bình luận', 'summary' => $bundle['summary'], 'reviews' => $bundle['reviews']]);
}

jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
