<?php
/**
 * fix_blog_wp_images.php — CHẠY TRÊN SERVER GỐC (có DB + thư mục uploads thật).
 *
 * Mục đích: vá các ảnh blog import từ WordPress đang 404 vì:
 *   - File còn nằm local (chưa đẩy lên VPS media) nhưng DB lưu path tương đối
 *     → to_abs_url route sang media.paintandmore.vn ⇒ 404.
 *
 * Với mỗi bài có thumbnail_url là path tương đối 'uploads/blog/...':
 *   1) Nếu file local còn → thử convert WebP (nếu là png/jpg) cho nhẹ.
 *   2) Thử đẩy lên VPS media (media_publish_local_file).
 *      - Đẩy OK  → giữ path tương đối (URL media domain sẽ hoạt động).
 *      - Đẩy lỗi → đổi thumbnail_url thành URL TUYỆT ĐỐI trỏ server gốc (hết 404).
 *   3) Nếu file local KHÔNG còn và DB là path tương đối → chuyển sang URL gốc.
 *
 * Chạy:  php scripts/fix_blog_wp_images.php          (dry-run, chỉ in)
 *        php scripts/fix_blog_wp_images.php --apply  (thực thi cập nhật DB)
 */

require_once __DIR__ . '/../config.php';

$apply = in_array('--apply', $argv, true);
echo $apply ? "=== CHẾ ĐỘ THỰC THI (--apply) ===\n" : "=== DRY-RUN (thêm --apply để cập nhật) ===\n";

global $ithanhloc, $baseUrl, $uploadFolder, $mediaRemoteEnabled;
if (!($ithanhloc instanceof mysqli)) {
    fwrite(STDERR, "Không kết nối được DB\n");
    exit(1);
}

$root   = function_exists('getUploadRootPath') ? getUploadRootPath() : dirname(__DIR__);
$folder = trim((string)($uploadFolder ?? 'uploads'), '/');
$base   = rtrim((string)($baseUrl ?? ''), '/');

$res = $ithanhloc->query("SELECT id, title, thumbnail_url FROM ecommerce_blog WHERE thumbnail_url LIKE '%blog_wp_%'");
if (!$res) { fwrite(STDERR, "Query lỗi: " . $ithanhloc->error . "\n"); exit(1); }

$stats = ['total' => 0, 'published' => 0, 'fallback' => 0, 'skip' => 0];

while ($row = $res->fetch_assoc()) {
    $stats['total']++;
    $id  = (int)$row['id'];
    $url = trim((string)$row['thumbnail_url']);

    // Bỏ qua nếu đã là URL tuyệt đối tới media domain hoặc đã ổn.
    if (preg_match('#^https?://#i', $url)) {
        // Nếu trỏ media domain mà file không lên VPS thì vẫn 404, nhưng ta không
        // chắc trạng thái VPS từ đây → chỉ xử lý path tương đối cho an toàn.
        echo "[#$id] bỏ qua (đã tuyệt đối): $url\n";
        $stats['skip']++;
        continue;
    }

    $rel    = ltrim(str_replace('\\', '/', $url), '/'); // uploads/blog/xxx
    $absFs  = rtrim(str_replace('\\', '/', $root), '/') . '/' . $rel;
    $exists = is_file($absFs);

    echo "[#$id] {$row['title']}\n    rel=$rel local=" . ($exists ? 'CÓ' : 'KHÔNG') . "\n";

    // File còn local → thử đẩy lên VPS.
    $newUrl = $url;
    $note   = '';
    if ($exists && !empty($mediaRemoteEnabled) && function_exists('media_publish_local_file')) {
        if (media_publish_local_file($rel)) {
            $note = 'đẩy VPS OK → giữ path tương đối';
            $stats['published']++;
        } else {
            $newUrl = $base . '/' . $rel;
            $note = 'đẩy VPS lỗi → URL server gốc';
            $stats['fallback']++;
        }
    } else {
        // Remote tắt hoặc file mất → trỏ thẳng server gốc để hết 404.
        if ($base !== '') {
            $newUrl = $base . '/' . $rel;
            $note = 'URL server gốc (remote tắt / file local)';
        } else {
            $note = 'không có baseUrl, bỏ qua';
        }
        $stats['fallback']++;
    }

    echo "    → $note\n    newUrl=$newUrl\n";

    if ($apply && $newUrl !== $url) {
        $st = $ithanhloc->prepare('UPDATE ecommerce_blog SET thumbnail_url = ? WHERE id = ?');
        $st->bind_param('si', $newUrl, $id);
        $st->execute();
        $st->close();
    }
}
$res->free();

echo "\n=== KẾT QUẢ ===\n";
echo "Tổng: {$stats['total']} | Đẩy VPS OK: {$stats['published']} | Fallback gốc: {$stats['fallback']} | Bỏ qua: {$stats['skip']}\n";
echo $apply ? "Đã cập nhật DB.\n" : "Dry-run xong. Thêm --apply để thực thi.\n";
