<?php
/**
 * receiver.php — ĐẶT TRÊN VPS MEDIA (gốc subdomain media.paintandmore.vn).
 * Nhận file upload từ server chính (HTTP POST), xác thực HMAC, lưu vào uploads/.
 *
 * CÀI ĐẶT:
 *   1) Upload file này lên gốc subdomain, đổi tên thành 'receiver.php'.
 *   2) Sửa $MEDIA_SECRET cho GIỐNG HỆT giá trị MEDIA_SECRET ở server chính.
 *   3) Sửa $UPLOAD_ROOT trỏ tới thư mục uploads/ thật trên VPS.
 *   4) Đảm bảo .htaccess KHÔNG chặn thực thi chính receiver.php (xem media-domain.htaccess).
 *
 * BẢO MẬT: chỉ chấp nhận request có chữ ký HMAC đúng + timestamp trong 300s.
 */

// ====== CẤU HÌNH (SỬA 2 DÒNG NÀY) ======
$MEDIA_SECRET = '4f20f5e37f9376e4f4f4bae5013cf41c4d9cd1c59ee031fd8e726427473d4138';
$UPLOAD_ROOT  = __DIR__ . '/uploads';   // thư mục uploads/ trên VPS (nếu subdomain map vào cha)
// Nếu subdomain map THẲNG vào uploads/ thì để: $UPLOAD_ROOT = __DIR__;

// ====== KHÔNG SỬA DƯỚI ĐÂY ======
// Luôn trả JSON, kể cả khi gặp fatal/warning — tránh body rỗng khiến server chính
// tưởng "đẩy lỗi" rồi giữ file local (ảnh hiển thị 404 ở media domain).
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }
ob_start();
header('Content-Type: application/json; charset=UTF-8');

function out($arr, int $code = 200) {
    if (!headers_sent()) http_response_code($code);
    while (ob_get_level() > 0) { @ob_end_clean(); } // bỏ mọi output rác trước đó
    echo json_encode($arr);
    exit;
}

// Bắt mọi lỗi nghiêm trọng (PHP 8.x: TypeError/upload quá lớn...) để vẫn trả JSON.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        echo json_encode(['ok' => false, 'msg' => 'receiver fatal: ' . $e['message']]);
    }
});
set_error_handler(function ($no, $str) {
    // Không cho warning/notice in ra body; ghi log thay vì vỡ JSON.
    error_log("[receiver] $str");
    return true;
});

// Đáp lại preflight OPTIONS ngay (một số CDN/proxy gửi OPTIONS trước POST).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    out(['ok' => true, 'preflight' => true]);
}

$folder = 'uploads';
$action = (string)($_POST['action'] ?? '');
$rel    = (string)($_POST['rel_path'] ?? '');
$ts     = (string)($_POST['ts'] ?? '');
$sign   = (string)($_POST['sign'] ?? '');
$fileHash = (string)($_POST['file_hash'] ?? '');

if ($MEDIA_SECRET === '' || $MEDIA_SECRET === 'ĐỔI_THÀNH_SECRET_GIỐNG_SERVER_CHÍNH') {
    out(['ok' => false, 'msg' => 'Server media chưa cấu hình secret'], 500);
}
if (!in_array($action, ['store', 'delete'], true)) out(['ok' => false, 'msg' => 'action sai'], 400);

// Chống replay: timestamp lệch tối đa 300s
if (!ctype_digit($ts) || abs(time() - (int)$ts) > 300) out(['ok' => false, 'msg' => 'ts hết hạn'], 403);

// Chuẩn hoá + chống path traversal
$rel = ltrim(str_replace('\\', '/', $rel), '/');
if ($rel === '' || strpos($rel, '..') !== false || !preg_match('#^' . preg_quote($folder, '#') . '/#', $rel)) {
    out(['ok' => false, 'msg' => 'rel_path không hợp lệ'], 400);
}
// chỉ cho phép ký tự an toàn
if (!preg_match('#^[A-Za-z0-9._/\-]+$#', $rel)) out(['ok' => false, 'msg' => 'rel_path ký tự lạ'], 400);

// Verify chữ ký
$expected = hash_hmac('sha256', $ts . '|' . $action . '|' . $rel . '|' . $fileHash, $MEDIA_SECRET);
if (!hash_equals($expected, $sign)) out(['ok' => false, 'msg' => 'sai chữ ký'], 403);

// rel bắt đầu bằng 'uploads/' → bỏ tiền tố để ghép vào $UPLOAD_ROOT (vốn đã là .../uploads)
$relInside = preg_replace('#^' . preg_quote($folder, '#') . '/#', '', $rel);
$destPath = rtrim($UPLOAD_ROOT, '/') . '/' . $relInside;

// Chặn ghi đè ra ngoài thư mục cho phép
$rootReal = rtrim(str_replace('\\', '/', realpath($UPLOAD_ROOT) ?: $UPLOAD_ROOT), '/');
$destDir  = dirname($destPath);

if ($action === 'delete') {
    if (is_file($destPath)) @unlink($destPath);
    out(['ok' => true, 'deleted' => $rel]);
}

// action = store
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    out(['ok' => false, 'msg' => 'thiếu file'], 400);
}
$tmp = $_FILES['file']['tmp_name'];

// Chặn đuôi thực thi
$ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
$banned = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp', 'htaccess'];
if (in_array($ext, $banned, true)) out(['ok' => false, 'msg' => 'đuôi file bị cấm'], 400);

// Kiểm tra hash khớp (toàn vẹn dữ liệu)
if ($fileHash !== '' && hash_file('sha256', $tmp) !== $fileHash) {
    out(['ok' => false, 'msg' => 'file_hash không khớp'], 400);
}

if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

// Xác nhận destDir nằm trong root cho phép
$destDirReal = rtrim(str_replace('\\', '/', realpath($destDir) ?: $destDir), '/');
if ($rootReal !== '' && strpos($destDirReal, $rootReal) !== 0) {
    out(['ok' => false, 'msg' => 'đường dẫn ngoài phạm vi'], 400);
}

if (!@move_uploaded_file($tmp, $destPath)) {
    // move_uploaded_file có thể fail nếu không phải HTTP upload thật; fallback copy
    if (!@copy($tmp, $destPath)) out(['ok' => false, 'msg' => 'không lưu được file'], 500);
}

out(['ok' => true, 'path' => $rel, 'size' => filesize($destPath)]);
