<?php
require_once __DIR__ . '/../../config.php';

if (!$isAdmin) {
    http_response_code(403);
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên']);
}

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'] ?? 0, $fatal, true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    if (ob_get_length()) {
        @ob_clean();
    }
    
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Lỗi hệ thống máy chủ'], JSON_UNESCAPED_UNICODE);
});

function ensureBlogTables(mysqli $ithanhloc): void {
    $res = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_blog_category'");
    if ($res && $res->num_rows === 0) {
        $sql = "CREATE TABLE IF NOT EXISTS `ecommerce_blog_category` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_blog_cat_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $ithanhloc->query($sql);
    }
    if ($res) {
        $res->close();
    }

    $res2 = $ithanhloc->query("SHOW TABLES LIKE 'ecommerce_blog'");
    if ($res2 && $res2->num_rows === 0) {
        $sql2 = "CREATE TABLE IF NOT EXISTS `ecommerce_blog` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            `excerpt` TEXT NULL,
            `content` LONGTEXT NULL,
            `thumbnail_url` VARCHAR(255) NULL,
            `author_name` VARCHAR(191) NULL,
            `tags` VARCHAR(255) NULL,
            `meta_title` VARCHAR(255) NULL,
            `meta_description` VARCHAR(255) NULL,
            `meta_keywords` VARCHAR(255) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `published_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_blog_slug` (`slug`),
            KEY `idx_blog_category` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $ithanhloc->query($sql2);
    }
    if ($res2) {
        $res2->close();
    }
}

function getUploadRootPath(): string {
    return dirname(__DIR__, 2);
}

function ensureBlogUploadDir(): array {
    global $uploadFolder;
    
    $root   = getUploadRootPath();
    $webDir = '/' . ($uploadFolder ?? 'uploads') . '/blog';
    $fsDir  = $root . str_replace('/', DIRECTORY_SEPARATOR, $webDir);
    
    if (!is_dir($fsDir)) {
        @mkdir($fsDir, 0777, true);
    }
    if (!is_dir($fsDir)) {
        return ['ok' => false, 'msg' => 'Không thể tạo thư mục ' . ($uploadFolder ?? 'uploads') . '/blog'];
    }
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $testFile = $fsDir . DIRECTORY_SEPARATOR . '.__writetest';
        $canWrite = @file_put_contents($testFile, 'test') !== false;
        if ($canWrite) {
            @unlink($testFile);
        }
        if (!$canWrite) {
            return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/blog'];
        }
    } else {
        if (!is_writable($fsDir)) {
            return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/blog'];
        }
    }
    return ['ok' => true, 'fs' => $fsDir, 'web' => $webDir];
}

function convertImageToWebpIfPossible(string $srcFsPath, string $webDir): string {
    if (!function_exists('imagewebp')) {
        return $webDir . '/' . basename($srcFsPath);
    }
    if (!is_file($srcFsPath)) {
        return $webDir . '/' . basename($srcFsPath);
    }
    
    $ext   = strtolower(pathinfo($srcFsPath, PATHINFO_EXTENSION));
    $image = null;

    // Ảnh lớn (PNG nhiều màu) cần nhiều RAM khi nạp vào GD. Tạm nâng memory_limit
    // để tránh imagecreatefrom* trả false → giữ nguyên PNG nặng (khó đẩy lên VPS).
    $info = @getimagesize($srcFsPath);
    if (is_array($info)) {
        $needBytes = (int)(($info[0] ?? 0) * ($info[1] ?? 0) * 5) + 16 * 1024 * 1024;
        $curLimit  = function_exists('ini_get') ? ini_get('memory_limit') : '';
        if ($curLimit !== '-1' && $needBytes > 0) {
            @ini_set('memory_limit', (string)max($needBytes, 256 * 1024 * 1024));
        }
    }

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            if (function_exists('imagecreatefromjpeg')) {
                $image = @imagecreatefromjpeg($srcFsPath);
            }
            break;
        case 'png':
            if (function_exists('imagecreatefrompng')) {
                $image = @imagecreatefrompng($srcFsPath);
                if ($image && function_exists('imagealphablending') && function_exists('imagesavealpha')) {
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
            }
            break;
        case 'gif':
            if (function_exists('imagecreatefromgif')) {
                $image = @imagecreatefromgif($srcFsPath);
            }
            break;
        case 'webp':
            return $webDir . '/' . basename($srcFsPath);
        default:
            return $webDir . '/' . basename($srcFsPath);
    }
    
    if (!$image) {
        return $webDir . '/' . basename($srcFsPath);
    }
    
    $dirFs      = dirname($srcFsPath);
    $baseName   = pathinfo($srcFsPath, PATHINFO_FILENAME);
    $webpName   = $baseName . '.webp';
    $destFsPath = $dirFs . DIRECTORY_SEPARATOR . $webpName;
    $ok         = @imagewebp($image, $destFsPath, 80);
    imagedestroy($image);
    
    if ($ok && is_file($destFsPath)) {
        @unlink($srcFsPath);
        return $webDir . '/' . $webpName;
    }
    
    return $webDir . '/' . basename($srcFsPath);
}

function saveUploadedBlogImage(string $fileKey, bool $required): array {
    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh thumbnail'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }
    
    $file = $_FILES[$fileKey];
    $err  = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    
    if ($err === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh thumbnail'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Upload ảnh thất bại'];
    }
    
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'Ảnh tối đa 12MB'];
    }
    
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'Tệp upload không hợp lệ'];
    }
    
    $name = (string)($file['name'] ?? '');
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowExt, true)) {
        return ['ok' => false, 'msg' => 'Chỉ hỗ trợ jpg, jpeg, png, webp, gif'];
    }
    
    $dir = ensureBlogUploadDir();
    if (!$dir['ok']) {
        return ['ok' => false, 'msg' => $dir['msg']];
    }
    
    $fileName = 'blog_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs   = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
    if (!@move_uploaded_file($tmp, $destFs)) {
        return ['ok' => false, 'msg' => 'Không thể lưu ảnh tải lên'];
    }
    
    $finalWebPath = convertImageToWebpIfPossible($destFs, $dir['web']);
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file($finalWebPath);
    }
    return ['ok' => true, 'has_file' => true, 'path' => $finalWebPath];
}

function slugify(string $raw): string {
    $s = trim(mb_strtolower($raw, 'UTF-8'));
    $vn = [
        'à','á','ả','ã','ạ','â','ầ','ấ','ẩ','ẫ','ậ','ă','ằ','ắ','ẳ','ẵ','ặ',
        'è','é','ẻ','ẽ','ẹ','ê','ề','ế','ể','ễ','ệ',
        'ì','í','ỉ','ĩ','ị',
        'ò','ó','ỏ','õ','ọ','ô','ồ','ố','ổ','ỗ','ộ','ơ','ờ','ớ','ở','ỡ','ợ',
        'ù','ú','ủ','ũ','ụ','ư','ừ','ứ','ử','ữ','ự',
        'ỳ','ý','ỷ','ỹ','ỵ','đ',
    ];
    $en = [
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d',
    ];
    $s = str_replace($vn, $en, $s);
    $s = preg_replace('/[^a-z0-9\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/u', '-', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'post-' . date('Ymd-His');
    }
    return $s;
}

// ===== WORDPRESS IMPORT HELPERS =====

// Phân tích URL bài viết WordPress -> base site + slug.
// Hỗ trợ cả URL dạng /slug/ lẫn /chuyen-muc/slug/.
function wpParsePostUrl(string $url): array {
    $url = trim($url);
    if ($url === '') return ['ok' => false, 'msg' => 'Vui lòng nhập URL bài viết WordPress'];
    if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return ['ok' => false, 'msg' => 'URL không hợp lệ'];
    }
    $scheme = strtolower($parts['scheme'] ?? 'https');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'msg' => 'Chỉ hỗ trợ URL http/https'];
    }
    // Chặn SSRF: không cho host nội bộ / localhost / IP private.
    $host = strtolower($parts['host']);
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1'
        || preg_match('~^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|169\.254\.|0\.)~', $host)) {
        return ['ok' => false, 'msg' => 'Không cho phép import từ địa chỉ nội bộ'];
    }

    $base = $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    $path = trim((string)($parts['path'] ?? ''), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    // slug bài viết WordPress thường là segment cuối cùng (bỏ phần đuôi rỗng).
    $slug = '';
    while ($segments) {
        $cand = trim(array_pop($segments));
        if ($cand !== '' && !preg_match('~\.(html?|php)$~i', $cand)) { $slug = $cand; break; }
        if ($cand !== '') { $slug = preg_replace('~\.(html?|php)$~i', '', $cand); break; }
    }
    if ($slug === '') {
        return ['ok' => false, 'msg' => 'Không xác định được slug bài viết từ URL'];
    }
    return ['ok' => true, 'base' => $base, 'slug' => rawurldecode($slug)];
}

// Gọi HTTP GET trả JSON (dùng cho WordPress REST API).
function wpHttpGetJson(string $url, int $timeout = 25): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (PaintMore Blog Importer)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $err !== '') {
        return ['ok' => false, 'msg' => 'Không kết nối được tới website nguồn: ' . $err, 'http' => $code];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'msg' => 'Website nguồn trả về mã lỗi ' . $code, 'http' => $code];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => 'Website nguồn không phải WordPress REST API hợp lệ', 'http' => $code];
    }
    return ['ok' => true, 'http' => $code, 'data' => $json];
}

// Lấy bài viết WordPress theo slug qua REST API (kèm _embed để có ảnh + chuyên mục).
function wpFetchPostBySlug(string $base, string $slug): array {
    $endpoint = rtrim($base, '/') . '/wp-json/wp/v2/posts?_embed=1&slug=' . rawurlencode($slug);
    $res = wpHttpGetJson($endpoint);
    if (!($res['ok'] ?? false)) return $res;
    $list = $res['data'];
    if (!isset($list[0]) || !is_array($list[0])) {
        return ['ok' => false, 'msg' => 'Không tìm thấy bài viết tại URL này (website có thể không bật WordPress REST API)'];
    }
    return ['ok' => true, 'post' => $list[0]];
}

// Tải ảnh từ URL về thư mục blog, convert webp, đẩy lên VPS media.
// Trả ['path' => web path|'', 'published' => bool, 'reason' => string].
function wpDownloadImage(string $imageUrl): array {
    $imageUrl = trim($imageUrl);
    if ($imageUrl === '' || !preg_match('~^https?://~i', $imageUrl)) {
        return ['path' => '', 'published' => false, 'reason' => 'url ảnh không hợp lệ'];
    }

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (PaintMore Blog Importer)',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300 || $body === '') {
        return ['path' => '', 'published' => false, 'reason' => 'tải ảnh nguồn lỗi http ' . $code];
    }
    if (strlen($body) > 12 * 1024 * 1024) {
        return ['path' => '', 'published' => false, 'reason' => 'ảnh nguồn > 12MB'];
    }

    // Xác định đuôi từ content-type hoặc URL.
    $extMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $ext = $extMap[$ctype] ?? strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return ['path' => '', 'published' => false, 'reason' => 'định dạng ảnh không hỗ trợ'];
    }

    $dir = ensureBlogUploadDir();
    if (!$dir['ok']) {
        return ['path' => '', 'published' => false, 'reason' => 'không tạo được thư mục blog'];
    }

    $fileName = 'blog_wp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs   = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
    if (@file_put_contents($destFs, $body) === false) {
        return ['path' => '', 'published' => false, 'reason' => 'không ghi được file local'];
    }

    // Xác thực là ảnh thật trước khi giữ lại.
    $info = @getimagesize($destFs);
    if ($info === false) { @unlink($destFs); return ['path' => '', 'published' => false, 'reason' => 'file tải về không phải ảnh']; }

    $finalWebPath = convertImageToWebpIfPossible($destFs, $dir['web']);
    if ($finalWebPath === '') {
        return ['path' => '', 'published' => false, 'reason' => 'xử lý ảnh thất bại'];
    }

    // Đẩy ảnh đã hoàn thiện lên VPS media. Nếu remote TẮT → coi như đã "publish"
    // (ảnh phục vụ từ server gốc). Nếu remote BẬT mà đẩy lỗi → file kẹt local,
    // URL trỏ media domain ⇒ ảnh 404. Trả reason cụ thể để admin biết.
    global $mediaRemoteEnabled, $baseUrl;
    $published = true;
    $reason    = '';
    $storePath = $finalWebPath; // path lưu DB

    if (!empty($mediaRemoteEnabled) && function_exists('media_publish_local_file')) {
        $published = media_publish_local_file($finalWebPath);
        if (!$published) {
            // Đẩy VPS lỗi → file vẫn ở local. Nếu lưu path tương đối, to_abs_url sẽ
            // route sang media domain ⇒ 404. Thay vào đó lưu URL TUYỆT ĐỐI trỏ server
            // gốc để ảnh vẫn hiển thị (to_abs_url giữ nguyên URL tuyệt đối).
            $base = rtrim((string)($baseUrl ?? ''), '/');
            if ($base !== '') {
                $storePath = $base . '/' . ltrim($finalWebPath, '/');
            }
            $reason = 'đẩy ảnh lên media VPS thất bại — tạm phục vụ ảnh từ server gốc '
                . '(kiểm tra MEDIA_SECRET / MEDIA_RECEIVER_URL / giới hạn dung lượng upload trên VPS)';
            error_log('[blog import] không đẩy được ảnh lên media VPS: ' . $finalWebPath);
        }
    }

    return ['path' => $storePath, 'published' => $published, 'reason' => $reason];
}

// Tìm hoặc tạo chuyên mục blog theo tên, trả category_id.
function wpResolveCategoryId(mysqli $ithanhloc, string $name): int {
    $name = trim($name);
    if ($name === '') $name = 'Blog';
    $slug = slugify($name);

    $st = $ithanhloc->prepare('SELECT id FROM ecommerce_blog_category WHERE slug = ? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $slug);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row && (int)$row['id'] > 0) return (int)$row['id'];
    }
    $st = $ithanhloc->prepare('INSERT INTO ecommerce_blog_category (name, slug, is_active) VALUES (?, ?, 1)');
    if ($st) {
        $st->bind_param('ss', $name, $slug);
        if ($st->execute()) {
            $newId = (int)$st->insert_id;
            $st->close();
            return $newId;
        }
        $st->close();
    }
    return 0;
}

ensureBlogTables($ithanhloc);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));

// ===== IMPORT BÀI VIẾT TỪ URL WORDPRESS =====
if ($action === 'import_wp') {
    $url = trim((string)($_POST['url'] ?? $_REQUEST['url'] ?? ''));
    $overrideCategoryId = (int)($_POST['category_id'] ?? 0);
    $publish = (int)($_POST['is_active'] ?? 1) ? 1 : 0;

    $parsed = wpParsePostUrl($url);
    if (!($parsed['ok'] ?? false)) {
        jOut(['ok' => false, 'msg' => $parsed['msg'] ?? 'URL không hợp lệ']);
    }

    $fetched = wpFetchPostBySlug($parsed['base'], $parsed['slug']);
    if (!($fetched['ok'] ?? false)) {
        jOut(['ok' => false, 'msg' => $fetched['msg'] ?? 'Không lấy được bài viết']);
    }
    $post = $fetched['post'];

    $title = trim(html_entity_decode((string)($post['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($title === '') {
        jOut(['ok' => false, 'msg' => 'Bài viết nguồn không có tiêu đề']);
    }
    $slug    = slugify((string)($post['slug'] ?? '') !== '' ? (string)$post['slug'] : $title);
    $content = (string)($post['content']['rendered'] ?? '');
    $excerptHtml = (string)($post['excerpt']['rendered'] ?? '');
    $excerpt = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($excerptHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($excerpt) > 480) $excerpt = mb_substr($excerpt, 0, 477) . '...';

    // Ngày đăng từ WordPress (nếu có), nếu không thì để NOW khi publish.
    $wpDate = trim((string)($post['date'] ?? ''));
    $publishedAt = '';
    if ($wpDate !== '') {
        $ts = strtotime($wpDate);
        if ($ts) $publishedAt = date('Y-m-d H:i:s', $ts);
    }

    // Lấy chuyên mục + tag từ _embedded.
    $wpCategoryName = '';
    $tagNames = [];
    if (isset($post['_embedded']['wp:term']) && is_array($post['_embedded']['wp:term'])) {
        foreach ($post['_embedded']['wp:term'] as $group) {
            if (!is_array($group)) continue;
            foreach ($group as $term) {
                $tax = (string)($term['taxonomy'] ?? '');
                $name = trim((string)($term['name'] ?? ''));
                if ($name === '') continue;
                if ($tax === 'category' && $wpCategoryName === '') $wpCategoryName = $name;
                elseif ($tax === 'post_tag') $tagNames[] = $name;
            }
        }
    }
    $tags = implode(', ', array_slice(array_values(array_unique($tagNames)), 0, 20));

    // Chuyên mục: ưu tiên admin chọn, nếu không thì map theo WordPress.
    $categoryId = $overrideCategoryId > 0
        ? $overrideCategoryId
        : wpResolveCategoryId($ithanhloc, $wpCategoryName !== '' ? $wpCategoryName : 'Blog');
    if ($categoryId <= 0) {
        jOut(['ok' => false, 'msg' => 'Không xác định được chuyên mục để lưu bài viết']);
    }

    // Ảnh đại diện từ _embedded:featuredmedia.
    $thumbUrl   = '';
    $thumbWarn  = '';
    $featImg = (string)($post['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '');
    if ($featImg !== '') {
        $imgRes   = wpDownloadImage($featImg);
        $thumbUrl = (string)($imgRes['path'] ?? '');
        if ($thumbUrl === '') {
            $thumbWarn = 'Không tải được ảnh đại diện: ' . ($imgRes['reason'] ?? '');
        } elseif (empty($imgRes['published'])) {
            // Ảnh đã lưu local + URL fallback về server gốc nên VẪN hiển thị,
            // chỉ chưa lên media VPS. Báo để admin xử lý cấu hình media.
            $thumbWarn = 'Ảnh đại diện tạm phục vụ từ server gốc: ' . ($imgRes['reason'] ?? '');
        }
    }

    // Tránh trùng slug: nếu đã có thì thêm hậu tố.
    $finalSlug = $slug;
    $suffix = 1;
    while (true) {
        $stChk = $ithanhloc->prepare('SELECT id FROM ecommerce_blog WHERE slug = ? LIMIT 1');
        if (!$stChk) break;
        $stChk->bind_param('s', $finalSlug);
        $stChk->execute();
        $stChk->store_result();
        $exists = $stChk->num_rows > 0;
        $stChk->close();
        if (!$exists) break;
        $suffix++;
        $finalSlug = $slug . '-' . $suffix;
        if ($suffix > 50) { $finalSlug = $slug . '-' . date('YmdHis'); break; }
    }

    $author = '';
    if (isset($post['_embedded']['author'][0]['name'])) {
        $author = trim((string)$post['_embedded']['author'][0]['name']);
    }
    $metaTitle = mb_substr($title, 0, 70);
    $metaDesc  = mb_substr($excerpt, 0, 180);

    $stmt = $ithanhloc->prepare('INSERT INTO ecommerce_blog (category_id, title, slug, excerpt, content, thumbnail_url, author_name, tags, meta_title, meta_description, meta_keywords, is_active, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 1 THEN ? ELSE NULL END)');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể tạo bài viết import']);
    }
    $publishedAtParam = $publishedAt !== '' ? $publishedAt : date('Y-m-d H:i:s');
    $stmt->bind_param(
        'issssssssssiis',
        $categoryId, $title, $finalSlug, $excerpt, $content, $thumbUrl,
        $author, $tags, $metaTitle, $metaDesc, $tags, $publish, $publish, $publishedAtParam
    );
    $ok    = $stmt->execute();
    $newId = $ok ? (int)$stmt->insert_id : 0;
    $err   = $stmt->error;
    $stmt->close();

    if (!$ok) {
        jOut(['ok' => false, 'msg' => 'Lưu bài viết thất bại: ' . $err]);
    }

    jOut([
        'ok'  => true,
        'msg' => 'Đã import bài viết từ WordPress' . ($thumbWarn !== '' ? ' — ' . $thumbWarn : ''),
        'id'  => $newId,
        'data' => [
            'title'      => $title,
            'slug'       => $finalSlug,
            'category'   => $wpCategoryName,
            'thumbnail'  => $thumbUrl,
            'tags'       => $tags,
        ],
    ]);
}

// ===== Gợi ý SEO + keyword bằng AI (fallback client nếu không có key/lỗi) =====
if ($action === 'seo_suggest') {
    $title    = trim((string)($_REQUEST['title'] ?? ''));
    $excerpt  = trim((string)($_REQUEST['excerpt'] ?? ''));
    $category = trim((string)($_REQUEST['category'] ?? ''));
    $content  = trim((string)($_REQUEST['content'] ?? ''));
    // Bỏ HTML khỏi nội dung, cắt gọn để tiết kiệm token
    $contentText = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));
    if (mb_strlen($contentText) > 1500) $contentText = mb_substr($contentText, 0, 1500);

    if ($title === '' && $excerpt === '' && $contentText === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập tiêu đề hoặc nội dung trước khi tạo SEO.']);
    }

    // Lấy key từ env ($API_OPENAI_KEY) hoặc trực tiếp từ site_setting (tùy môi trường)
    global $API_OPENAI_KEY;
    $apiKey = trim((string)($API_OPENAI_KEY ?? ''));
    if ($apiKey === '') {
        $st = $ithanhloc->prepare("SELECT setting_value FROM site_setting WHERE setting_key='API_OPENAI_KEY' LIMIT 1");
        if ($st) { $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close(); $apiKey = trim((string)($row['setting_value'] ?? '')); }
    }
    if ($apiKey === '') {
        // Không có key -> để client tự sinh
        jOut(['ok' => true, 'ai_used' => false, 'msg' => 'Chưa cấu hình API_OPENAI_KEY, dùng gợi ý cơ bản.']);
    }

    $prompt = "Bạn là chuyên gia SEO tiếng Việt cho blog ngành sơn/xây dựng.\n"
        . "Dựa trên thông tin bài viết, trả về DUY NHẤT 1 JSON object với 3 key:\n"
        . "- meta_title: thẻ title hấp dẫn, <= 60 ký tự, có từ khóa chính.\n"
        . "- meta_description: mô tả <= 160 ký tự, tự nhiên, có CTA nhẹ.\n"
        . "- keywords: mảng 5-10 từ khóa tiếng Việt liên quan nhất (cụm 1-3 từ), không trùng lặp.\n"
        . "Chỉ trả JSON, không thêm chữ nào khác.";

    $userMsg = "Tiêu đề: {$title}\nChuyên mục: {$category}\nMô tả ngắn: {$excerpt}\nNội dung: {$contentText}";

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $userMsg],
        ],
        'temperature' => 0.5,
        'max_tokens' => 400,
        'response_format' => ['type' => 'json_object'],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode < 200 || $httpCode >= 300 || !$raw) {
        jOut(['ok' => true, 'ai_used' => false, 'msg' => 'AI không phản hồi, dùng gợi ý cơ bản.']);
    }

    $data = json_decode((string)$raw, true);
    $cnt = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    $parsed = json_decode($cnt, true);
    if (!is_array($parsed) && preg_match('/\{[\s\S]*\}/', $cnt, $m)) {
        $parsed = json_decode($m[0], true);
    }
    if (!is_array($parsed)) {
        jOut(['ok' => true, 'ai_used' => false, 'msg' => 'AI trả về không hợp lệ, dùng gợi ý cơ bản.']);
    }

    $kw = $parsed['keywords'] ?? [];
    if (is_string($kw)) $kw = array_map('trim', explode(',', $kw));
    $kw = is_array($kw) ? array_values(array_filter(array_map(fn($x) => trim((string)$x), $kw))) : [];

    jOut([
        'ok' => true,
        'ai_used' => true,
        'data' => [
            'meta_title'       => mb_substr(trim((string)($parsed['meta_title'] ?? '')), 0, 70),
            'meta_description' => mb_substr(trim((string)($parsed['meta_description'] ?? '')), 0, 180),
            'keywords'         => array_slice($kw, 0, 10),
        ],
    ]);
}

if ($action === 'list_categories') {
    $rows = [];
    $res  = $ithanhloc->query('SELECT id, name, slug, is_active, created_at FROM ecommerce_blog_category ORDER BY name ASC, id ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->close();
    }
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'save_category') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim((string)($_POST['name'] ?? ''));
    $slugRaw = trim((string)($_POST['slug'] ?? ''));
    $active  = (int)($_POST['is_active'] ?? 1) ? 1 : 0;
    
    if ($name === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên chuyên mục']);
    }
    
    $slug = $slugRaw !== '' ? slugify($slugRaw) : slugify($name);
    
    if ($id > 0) {
        $stmt = $ithanhloc->prepare('SELECT id FROM ecommerce_blog_category WHERE slug = ? AND id <> ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('si', $slug, $id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->close();
                jOut(['ok' => false, 'msg' => 'Slug chuyên mục đã tồn tại']);
            }
            $stmt->close();
        }
        
        $stmtU = $ithanhloc->prepare('UPDATE ecommerce_blog_category SET name = ?, slug = ?, is_active = ? WHERE id = ?');
        if (!$stmtU) {
            jOut(['ok' => false, 'msg' => 'Không thể cập nhật chuyên mục']);
        }
        $stmtU->bind_param('ssii', $name, $slug, $active, $id);
        $ok = $stmtU->execute();
        $stmtU->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật chuyên mục' : 'Không thể cập nhật chuyên mục']);
    } else {
        $stmt = $ithanhloc->prepare('SELECT id FROM ecommerce_blog_category WHERE slug = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->close();
                jOut(['ok' => false, 'msg' => 'Slug chuyên mục đã tồn tại']);
            }
            $stmt->close();
        }
        
        $stmtI = $ithanhloc->prepare('INSERT INTO ecommerce_blog_category (name, slug, is_active) VALUES (?, ?, ?)');
        if (!$stmtI) {
            jOut(['ok' => false, 'msg' => 'Không thể tạo chuyên mục']);
        }
        $stmtI->bind_param('ssi', $name, $slug, $active);
        $ok = $stmtI->execute();
        $stmtI->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã tạo chuyên mục' : 'Không thể tạo chuyên mục']);
    }
}

if ($action === 'toggle_category') {
    $id    = (int)($_POST['id'] ?? 0);
    $state = (int)($_POST['is_active'] ?? 1) ? 1 : 0;
    
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu ID chuyên mục']);
    }
    
    $stmt = $ithanhloc->prepare('UPDATE ecommerce_blog_category SET is_active = ? WHERE id = ?');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể cập nhật chuyên mục']);
    }
    $stmt->bind_param('ii', $state, $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật chuyên mục' : 'Không thể cập nhật chuyên mục']);
}

if ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu ID chuyên mục']);
    }
    
    $stmtCnt = $ithanhloc->prepare('SELECT COUNT(*) FROM ecommerce_blog WHERE category_id = ?');
    if ($stmtCnt) {
        $stmtCnt->bind_param('i', $id);
        $stmtCnt->execute();
        $stmtCnt->bind_result($c);
        $stmtCnt->fetch();
        $stmtCnt->close();
        if ((int)$c > 0) {
            jOut(['ok' => false, 'msg' => 'Không thể xoá chuyên mục đang có bài viết']);
        }
    }
    
    $stmt = $ithanhloc->prepare('DELETE FROM ecommerce_blog_category WHERE id = ?');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể xoá chuyên mục']);
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xoá chuyên mục' : 'Không thể xoá chuyên mục']);
}

if ($action === 'list_posts') {
    $rows = [];
    $sql  = 'SELECT b.id, b.title, b.slug, b.category_id, b.author_name, b.is_active, b.published_at, b.created_at, c.name AS category_name
            FROM ecommerce_blog b
            LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id
            ORDER BY b.created_at DESC, b.id DESC
            LIMIT 200';
    $res  = $ithanhloc->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->close();
    }
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'get_post') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu ID bài viết']);
    }
    
    $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_blog WHERE id = ? LIMIT 1');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể tải bài viết']);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$row) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy bài viết']);
    }
    jOut(['ok' => true, 'data' => $row]);
}

if ($action === 'save_post') {
    $id           = (int)($_POST['id'] ?? 0);
    $categoryId   = (int)($_POST['category_id'] ?? 0);
    $title        = trim((string)($_POST['title'] ?? ''));
    $slugRaw      = trim((string)($_POST['slug'] ?? ''));
    $excerpt      = trim((string)($_POST['excerpt'] ?? ''));
    $content      = trim((string)($_POST['content'] ?? ''));
    $author       = trim((string)($_POST['author_name'] ?? ''));
    $tags         = trim((string)($_POST['tags'] ?? ''));
    $metaTitle    = trim((string)($_POST['meta_title'] ?? ''));
    $metaDesc     = trim((string)($_POST['meta_description'] ?? ''));
    $metaKeywords = trim((string)($_POST['meta_keywords'] ?? ''));
    $active       = (int)($_POST['is_active'] ?? 1) ? 1 : 0;
    $currentThumb = trim((string)($_POST['current_thumb'] ?? ''));

    if ($categoryId <= 0) {
        jOut(['ok' => false, 'msg' => 'Vui lòng chọn chuyên mục']);
    }
    if ($title === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập tiêu đề bài viết']);
    }

    $slug   = $slugRaw !== '' ? slugify($slugRaw) : slugify($title);
    $upload = saveUploadedBlogImage('thumb_file', false);
    
    if (!$upload['ok']) {
        jOut(['ok' => false, 'msg' => $upload['msg']]);
    }
    $thumbUrl = $upload['has_file'] ? (string)$upload['path'] : $currentThumb;

    if ($id > 0) {
        $stmtSlug = $ithanhloc->prepare('SELECT id FROM ecommerce_blog WHERE slug = ? AND id <> ? LIMIT 1');
        if ($stmtSlug) {
            $stmtSlug->bind_param('si', $slug, $id);
            $stmtSlug->execute();
            $stmtSlug->store_result();
            if ($stmtSlug->num_rows > 0) {
                $stmtSlug->close();
                jOut(['ok' => false, 'msg' => 'Slug bài viết đã tồn tại']);
            }
            $stmtSlug->close();
        }
        
        $stmt = $ithanhloc->prepare('UPDATE ecommerce_blog SET category_id = ?, title = ?, slug = ?, excerpt = ?, content = ?, thumbnail_url = ?, author_name = ?, tags = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, is_active = ?, published_at = CASE WHEN ? = 1 AND published_at IS NULL THEN NOW() ELSE published_at END WHERE id = ?');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không thể cập nhật bài viết']);
        }
        $stmt->bind_param('issssssssssiii', $categoryId, $title, $slug, $excerpt, $content, $thumbUrl, $author, $tags, $metaTitle, $metaDesc, $metaKeywords, $active, $active, $id);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật bài viết' : 'Không thể cập nhật bài viết', 'id' => $id]);
    } else {
        $stmtSlug = $ithanhloc->prepare('SELECT id FROM ecommerce_blog WHERE slug = ? LIMIT 1');
        if ($stmtSlug) {
            $stmtSlug->bind_param('s', $slug);
            $stmtSlug->execute();
            $stmtSlug->store_result();
            if ($stmtSlug->num_rows > 0) {
                $stmtSlug->close();
                jOut(['ok' => false, 'msg' => 'Slug bài viết đã tồn tại']);
            }
            $stmtSlug->close();
        }
        
        $stmt = $ithanhloc->prepare('INSERT INTO ecommerce_blog (category_id, title, slug, excerpt, content, thumbnail_url, author_name, tags, meta_title, meta_description, meta_keywords, is_active, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 1 THEN NOW() ELSE NULL END)');
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không thể tạo bài viết']);
        }
        $stmt->bind_param('issssssssssii', $categoryId, $title, $slug, $excerpt, $content, $thumbUrl, $author, $tags, $metaTitle, $metaDesc, $metaKeywords, $active, $active);
        $ok    = $stmt->execute();
        $newId = $ok ? (int)$stmt->insert_id : 0;
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã tạo bài viết' : 'Không thể tạo bài viết', 'id' => $newId]);
    }
}

if ($action === 'toggle_post') {
    $id    = (int)($_POST['id'] ?? 0);
    $state = (int)($_POST['is_active'] ?? 1) ? 1 : 0;
    
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu ID bài viết']);
    }
    
    $stmt = $ithanhloc->prepare('UPDATE ecommerce_blog SET is_active = ?, published_at = CASE WHEN ? = 1 AND published_at IS NULL THEN NOW() ELSE published_at END WHERE id = ?');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể cập nhật bài viết']);
    }
    $stmt->bind_param('iii', $state, $state, $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật trạng thái bài viết' : 'Không thể cập nhật bài viết']);
}

if ($action === 'delete_post') {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu ID bài viết']);
    }
    
    $stmt = $ithanhloc->prepare('DELETE FROM ecommerce_blog WHERE id = ?');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể xoá bài viết']);
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xoá bài viết' : 'Không thể xoá bài viết']);
}

jOut(['ok' => false, 'msg' => 'Action không hợp lệ']);
