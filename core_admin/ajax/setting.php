<?php
/**
 * AJAX Backend: Cấu hình hệ thống
 * Endpoint: /core_admin/ajax/setting.php
 */
require_once __DIR__ . '/../_admin_guard.php';

// ── Helper ────────────────────────────────────────────────────────────────────
function bot_json_out(array $payload): void {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bot_ensure_writable_dir(string $path): bool {
    if (!is_dir($path) && !@mkdir($path, 0775, true)) return false;
    if (is_writable($path)) return true;
    $tmp = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'writetest_' . uniqid() . '.tmp';
    if (@file_put_contents($tmp, 'test')) { @unlink($tmp); return true; }
    return false;
}

function bot_has_index(mysqli $db, string $indexName): bool {
    $safe = $db->real_escape_string($indexName);
    $res  = $db->query("SHOW INDEX FROM `site_setting` WHERE Key_name = '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

// ── DB: đồng bộ defaults ──────────────────────────────────────────────────────
// Chỉ seed khi site_setting còn THIẾU key (lần đầu hoặc vừa thêm key mới trong code).
// Khi đã đủ -> bỏ qua hoàn toàn (chỉ tốn 1 query COUNT nhẹ) để tránh ~70 lệnh INSERT mỗi request.
function bot_sync_defaults(mysqli $db): void {
    static $done = false;            // tránh chạy lại nhiều lần trong cùng 1 request
    if ($done) return;
    $done = true;

    $map = app_editable_config_map();
    $expected = count($map);
    if ($expected === 0) return;

    // Đếm số key (thuộc tập editable) đã có trong DB
    $keys     = array_keys($map);
    $safeKeys = array_map([$db, 'real_escape_string'], $keys);
    $in       = "'" . implode("','", $safeKeys) . "'";
    $existing = 0;
    if ($res = $db->query("SELECT COUNT(*) AS c FROM site_setting WHERE setting_key IN ({$in})")) {
        $row = $res->fetch_assoc();
        $existing = (int)($row['c'] ?? 0);
    }
    // Đã đủ -> không cần seed nữa
    if ($existing >= $expected) return;

    $settings = app_get_editable_config_values(false);
    if (empty($settings)) return;
    $sql  = "INSERT INTO site_setting (setting_key, setting_value, value_type, is_secret)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value_type = VALUES(value_type), is_secret = VALUES(is_secret)";
    $stmt = $db->prepare($sql);
    if (!$stmt) return;
    foreach ($settings as $item) {
        $key = (string)($item['key'] ?? '');
        if ($key === '') continue;
        $raw = $item['raw_value'] ?? '';
        if (is_bool($raw))                   $value = $raw ? '1' : '0';
        elseif (is_array($raw) || is_object($raw)) $value = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        else                                  $value = (string)$raw;
        $type   = (string)($item['type'] ?? 'string');
        $secret = !empty($item['secret']) ? 1 : 0;
        $stmt->bind_param('sssi', $key, $value, $type, $secret);
        $stmt->execute();
    }
    $stmt->close();
}

// ── UI: lấy config theo section ───────────────────────────────────────────────
function bot_get_config_for_ui(mysqli $db, array $sectionPrefixes = []): array {
    $settings = app_get_editable_config_values(false);
    if (empty($settings)) return [];
    if (!empty($sectionPrefixes)) {
        $settings = array_filter($settings, function($item) use ($sectionPrefixes) {
            $sec = (string)($item['section'] ?? '');
            foreach ($sectionPrefixes as $p) {
                if ($sec === $p || strpos($sec, $p) === 0) return true;
            }
            return false;
        });
        if (empty($settings)) return [];
    }
    $keys     = array_keys($settings);
    $safeKeys = array_map([$db, 'real_escape_string'], $keys);
    $in       = "'" . implode("','", $safeKeys) . "'";
    $override = [];
    $res = $db->query("SELECT setting_key, setting_value FROM site_setting WHERE setting_key IN ({$in})");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            if (($row['setting_key'] ?? '') !== '') $override[$row['setting_key']] = $row['setting_value'] ?? '';
        }
    }
    $rows = [];
    foreach ($settings as $key => $item) {
        $type     = (string)($item['type'] ?? 'string');
        $rawValue = $item['raw_value'];
        if ($key !== '' && array_key_exists($key, $override)) $rawValue = app_cast_config_value($override[$key], $type);
        $rows[] = ['key' => $key, 'label' => $item['label'], 'type' => $type, 'secret' => (bool)$item['secret'], 'section' => $item['section'], 'value' => $rawValue];
    }
    return $rows;
}

// ── Lưu config ────────────────────────────────────────────────────────────────
function bot_save_config(mysqli $db, array $incoming): array {
    $editableMap = app_editable_config_map();
    $sql  = "INSERT INTO site_setting (setting_key, setting_value, value_type, is_secret)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), is_secret = VALUES(is_secret)";
    $stmt = $db->prepare($sql);
    if (!$stmt) return ['ok' => false, 'msg' => 'Không thể chuẩn bị truy vấn lưu cấu hình.'];
    $saved = 0; $errors = [];
    foreach ($incoming as $key => $value) {
        if (!isset($editableMap[$key])) continue;
        $meta   = $editableMap[$key];
        $type   = (string)($meta['type'] ?? 'string');
        $cast   = app_cast_config_value($value, $type);
        if (!app_set_config_value_by_path($key, $cast)) { $errors[] = $key; continue; }
        if (is_bool($cast))                    $stored = $cast ? '1' : '0';
        elseif (is_array($cast)||is_object($cast)) $stored = json_encode($cast, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        else                                   $stored = (string)$cast;
        $secret = !empty($meta['secret']) ? 1 : 0;
        $stmt->bind_param('sssi', $key, $stored, $type, $secret);
        if ($stmt->execute()) $saved++; else $errors[] = $key;
    }
    $stmt->close();
    return ['ok' => empty($errors), 'saved' => $saved, 'errors' => $errors];
}

// ── MoMo helpers ─────────────────────────────────────────────────────────────
function momo_test_signature(array $p, string $secret): string {
    $raw = 'accessKey='.$p['accessKey'].'&amount='.$p['amount'].'&extraData='.$p['extraData']
         . '&ipnUrl='.$p['ipnUrl'].'&orderId='.$p['orderId'].'&orderInfo='.$p['orderInfo']
         . '&partnerCode='.$p['partnerCode'].'&redirectUrl='.$p['redirectUrl']
         . '&requestId='.$p['requestId'].'&requestType='.($p['requestType'] ?? 'captureWallet');
    return hash_hmac('sha256', $raw, $secret);
}

function momo_test_post_json(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return ['ok' => false, 'msg' => $err ?: 'MoMo API unreachable', 'http' => $http];
    $json = json_decode((string)$raw, true);
    return is_array($json) ? ['ok' => true, 'http' => $http, 'data' => $json]
                           : ['ok' => false, 'msg' => 'MoMo API invalid JSON', 'http' => $http, 'raw' => $raw];
}

// ── Upload helper ──────────────────────────────────────────────────────────────
function bot_handle_upload(string $field, string $subfolder, string $prefix, array $allowedExts): string {
    global $uploadFolder, $baseUrl;
    $file = $_FILES[$field] ?? null;
    if (!$file || !is_uploaded_file($file['tmp_name']))
        bot_json_out(['ok' => false, 'msg' => 'Không tìm thấy file upload']);
    if (!empty($file['error']))
        bot_json_out(['ok' => false, 'msg' => 'Lỗi upload (code ' . (int)$file['error'] . ')']);
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true))
        bot_json_out(['ok' => false, 'msg' => 'Định dạng file không hợp lệ (' . implode('/', array_map('strtoupper', $allowedExts)) . ')']);
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ($uploadFolder ?? 'uploads') . DIRECTORY_SEPARATOR . $subfolder;
    if (!bot_ensure_writable_dir($dir))
        bot_json_out(['ok' => false, 'msg' => 'Thư mục upload không ghi được']);
    $name = $prefix . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name))
        bot_json_out(['ok' => false, 'msg' => 'Không thể lưu file upload']);
    $settingRel = ($uploadFolder ?? 'uploads') . '/' . $subfolder . '/' . $name;
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file($settingRel);
    }
    return $settingRel;
}

// ═════════════════════════════════════════════════════════════════════════════
bot_sync_defaults($ithanhloc);

$act = $_POST['action'] ?? '';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$isGet  = $_SERVER['REQUEST_METHOD'] === 'GET';

// ── GET: bootstrap (lazy-load tab) ───────────────────────────────────────────
if ($isGet && isset($_GET['ajax']) && $_GET['ajax'] === 'bootstrap') {
    $map = ['ai-login' => ['AI','Login'], 'payment' => ['Payment'], 'company' => ['Company'],
            'ecommerce' => ['Ecommerce'], 'region' => ['Region'], 'social' => ['Social']];
    $group   = (string)($_GET['group'] ?? '');
    $prefixes = $map[$group] ?? [];
    bot_json_out(['ok' => true, 'settings' => bot_get_config_for_ui($ithanhloc, $prefixes)]);
}

// ── POST: yêu cầu CSRF ────────────────────────────────────────────────────────
if ($isPost) app_verify_csrf();

// ── POST: save_config ─────────────────────────────────────────────────────────
if ($isPost && $act === 'save_config') {
    $payload = $_POST['settings'] ?? [];
    if (is_string($payload)) $payload = json_decode($payload, true) ?? [];
    if (!is_array($payload)) bot_json_out(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ']);
    $result = bot_save_config($ithanhloc, $payload);
    bot_json_out($result['ok'] ? ['ok' => true, 'saved' => $result['saved']]
                               : ['ok' => false, 'msg' => 'Lưu cấu hình chưa hoàn tất', 'detail' => $result]);
}

// ── POST: set_media_mode (nút switch khẩn cấp media domain) ──────────────────
// Chỉ đổi cờ MEDIA_MODE, GIỮ NGUYÊN MEDIA_BASE_URL/SECRET/RECEIVER để bật lại nhanh.
//   'vps'         → đọc + ghi đều ở media VPS (bình thường)
//   'read_origin' → ĐỌC từ server gốc, TẮT ghi VPS (upload về local) — dùng khi VPS lỗi
//   'local'       → tắt hoàn toàn, về như trước khi tách domain
if ($isPost && $act === 'set_media_mode') {
    $mode = strtolower(trim((string)($_POST['mode'] ?? '')));
    if (!in_array($mode, ['vps', 'read_origin', 'local'], true)) {
        bot_json_out(['ok' => false, 'msg' => 'Chế độ không hợp lệ']);
    }
    $savedMediaUrl = trim((string)app_get_config_value_by_path('MEDIA_BASE_URL'));
    if ($mode === 'vps' && $savedMediaUrl === '') {
        bot_json_out(['ok' => false, 'msg' => 'Chưa có Media domain (URL đọc ảnh). Nhập URL rồi Lưu trước khi bật chế độ VPS.']);
    }
    // MEDIA_REMOTE_ENABLED chỉ bật ở chế độ vps.
    $remote = ($mode === 'vps');
    $result = bot_save_config($ithanhloc, [
        'MEDIA_MODE' => $mode,
        'MEDIA_REMOTE_ENABLED' => $remote,
    ]);
    bot_json_out($result['ok']
        ? ['ok' => true, 'mode' => $mode]
        : ['ok' => false, 'msg' => 'Không thể đổi chế độ media', 'detail' => $result]);
}

// ── POST: set_momo_env ────────────────────────────────────────────────────────
if ($isPost && $act === 'set_momo_env') {
    $raw = strtolower(trim((string)($_POST['env'] ?? 'production')));
    $env = in_array($raw, ['test','testing','sandbox'], true) ? 'test' : 'production';
    $r   = bot_save_config($ithanhloc, ['ECOMMERCE_PAYMENT_METHODS.momo_env' => $env]);
    bot_json_out($r['ok'] ? ['ok' => true, 'env' => $env]
                          : ['ok' => false, 'msg' => 'Không thể cập nhật chế độ MoMo', 'detail' => $r]);
}

// ── POST: test_momo ───────────────────────────────────────────────────────────
if ($isPost && $act === 'test_momo') {
    $cfg = app_get_momo_config_by_env((string)($_POST['env'] ?? 'production'));
    if (empty($cfg['enabled'])) bot_json_out(['ok' => false, 'msg' => 'MoMo chưa được bật']);
    $partnerCode = trim((string)($cfg['partnerCode'] ?? ''));
    $accessKey   = trim((string)($cfg['accessKey'] ?? ''));
    $secretKey   = trim((string)($cfg['secretKey'] ?? ''));
    $redirectUrl = trim((string)($cfg['redirectUrl'] ?? ''));
    $ipnUrl      = trim((string)($cfg['ipnUrl'] ?? ''));
    $createUrl   = trim((string)($cfg['createUrl'] ?? '')) ?: 'https://test-payment.momo.vn/v2/gateway/api/create';
    if (!$partnerCode || !$accessKey || !$secretKey || !$redirectUrl || !$ipnUrl)
        bot_json_out(['ok' => false, 'msg' => 'Thiếu cấu hình MoMo (partnerCode/accessKey/secretKey/redirectUrl/ipnUrl)']);
    $orderId  = 'TEST-' . date('ymd-His');
    $reqId    = $orderId . '-' . mt_rand(1000, 9999);
    $payload  = ['partnerCode' => $partnerCode, 'partnerName' => 'PaintMore', 'storeId' => 'PaintMoreStore',
                 'requestId' => $reqId, 'amount' => '1000', 'orderId' => $orderId,
                 'orderInfo' => 'Test MoMo config', 'redirectUrl' => $redirectUrl, 'ipnUrl' => $ipnUrl,
                 'lang' => 'vi', 'extraData' => '', 'requestType' => 'captureWallet', 'autoCapture' => true];
    $payload['signature'] = momo_test_signature(
        array_merge($payload, ['accessKey' => $accessKey]), $secretKey
    );
    $res  = momo_test_post_json($createUrl, $payload);
    if (!($res['ok'] ?? false)) bot_json_out(['ok' => false, 'msg' => $res['msg'] ?? 'MoMo test failed', 'http' => $res['http'] ?? 0]);
    $data = is_array($res['data'] ?? null) ? $res['data'] : [];
    bot_json_out(['ok' => ((int)($data['resultCode'] ?? -1)) === 0,
                  'resultCode' => (int)($data['resultCode'] ?? -1),
                  'message' => (string)($data['message'] ?? 'MoMo test response'), 'data' => $data]);
}

// ── POST: upload_cod_qr ───────────────────────────────────────────────────────
if ($isPost && $act === 'upload_cod_qr') {
    $relPath = bot_handle_upload('qr_image', 'bank', 'cod_qr_', ['png','jpg','jpeg','webp']);
    $save    = bot_save_config($ithanhloc, ['COMPANY_INFO.bank_qr_image' => $relPath]);
    if (empty($save['ok'])) bot_json_out(['ok' => false, 'msg' => 'Không thể lưu cấu hình ảnh QR']);
    bot_json_out(['ok' => true, 'path' => $relPath, 'url' => to_abs_url($relPath, (string)($baseUrl ?? ''))]);
}

// ── POST: upload_site_logo ────────────────────────────────────────────────────
if ($isPost && $act === 'upload_site_logo') {
    $relPath = bot_handle_upload('logo_image', 'logo', 'site_logo_', ['png','jpg','jpeg','webp','svg']);
    $save    = bot_save_config($ithanhloc, ['SITE_LOGO' => $relPath]);
    if (empty($save['ok'])) bot_json_out(['ok' => false, 'msg' => 'Không thể lưu cấu hình logo']);
    bot_json_out(['ok' => true, 'path' => $relPath, 'url' => to_abs_url($relPath, (string)($baseUrl ?? ''))]);
}

// ── POST: upload_site_fallback_logo ──────────────────────────────────────────
if ($isPost && $act === 'upload_site_fallback_logo') {
    $relPath = bot_handle_upload('fallback_image', 'logo', 'site_fallback_', ['png','jpg','jpeg','webp','svg','gif']);
    $save    = bot_save_config($ithanhloc, ['SITE_FALLBACK_LOGO' => $relPath]);
    if (empty($save['ok'])) bot_json_out(['ok' => false, 'msg' => 'Không thể lưu cấu hình ảnh fallback']);
    bot_json_out(['ok' => true, 'path' => $relPath, 'url' => to_abs_url($relPath, (string)($baseUrl ?? ''))]);
}

bot_json_out(['ok' => false, 'msg' => 'Action không xác định.']);
