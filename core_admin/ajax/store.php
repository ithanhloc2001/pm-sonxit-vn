<?php
require_once __DIR__ . '/../../config.php';
if (!$isAdmin){
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Chỉ dành cho quản trị viên'], JSON_UNESCAPED_UNICODE);
    exit;
}
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;
    while (ob_get_level()) { @ob_end_clean(); }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Server error',
        'error' => $err['message'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

function workspaceRootPath(): string {
    return dirname(__DIR__, 2);
}

function normalizePhone(string $phone): string {
    $digits = preg_replace('/[^0-9+]/', '', trim($phone));
    return substr($digits, 0, 32);
}

function getRegionOptions(): array {
    global $ECOMMERCE_REGIONS;
    $regions = (isset($ECOMMERCE_REGIONS) && is_array($ECOMMERCE_REGIONS) && !empty($ECOMMERCE_REGIONS))
        ? array_values($ECOMMERCE_REGIONS)
        : ['MIỀN BẮC', 'MIỀN TRUNG', 'MIỀN NAM'];
    $clean = [];
    foreach ($regions as $r) {
        $v = trim((string)$r);
        if ($v !== '') $clean[$v] = $v;
    }
    return array_values($clean);
}

function getOpenAiKey(mysqli $ithanhloc): string {
    global $API_OPENAI_KEY;
    return trim((string)($API_OPENAI_KEY ?? ''));
}

function buildGoogleMapUrl(string $query): string {
    $q = trim($query);
    if ($q === '') return '';
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

function aiSuggestBranch(string $apiKey, string $branchName, string $region, string $addressInput): array {
    $queryText = trim($addressInput !== '' ? $addressInput : ($branchName . ' ' . $region));
    if ($queryText === '') {
        return ['ok' => false, 'msg' => 'Thiếu dữ liệu để gợi ý'];
    }

    if ($apiKey === '') {
        return [
            'ok' => true,
            'ai_used' => false,
            'data' => [
                'address_detail' => $queryText,
                'map_url' => buildGoogleMapUrl($queryText),
                'hotline' => '',
            ]
        ];
    }

    $prompt = "Bạn là trợ lý chuẩn hoá dữ liệu chi nhánh tại Việt Nam.\n"
        . "Trả về DUY NHẤT JSON object với 3 key: address_detail, map_url, hotline.\n"
        . "- address_detail: địa chỉ chi tiết chuẩn hóa, có khu vực nếu phù hợp.\n"
        . "- map_url: URL Google Maps tìm kiếm địa chỉ đó (https://www.google.com/maps/search/?api=1&query=...).\n"
        . "- hotline: chỉ điền nếu chắc chắn có trong input, nếu không thì để rỗng.\n"
        . "Không thêm text ngoài JSON.";

    $payload = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => "Chi nhánh: {$branchName}\nKhu vực: {$region}\nInput: {$queryText}"],
        ],
        'temperature' => 0.2,
        'max_tokens' => 220,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode < 200 || $httpCode >= 300 || !$raw) {
        return [
            'ok' => true,
            'ai_used' => false,
            'msg' => 'AI không phản hồi, dùng gợi ý thường',
            'data' => [
                'address_detail' => $queryText,
                'map_url' => buildGoogleMapUrl($queryText),
                'hotline' => '',
            ]
        ];
    }

    $data = json_decode($raw, true);
    $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    if ($content === '') {
        return [
            'ok' => true,
            'ai_used' => false,
            'data' => [
                'address_detail' => $queryText,
                'map_url' => buildGoogleMapUrl($queryText),
                'hotline' => '',
            ]
        ];
    }

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $parsed = json_decode($m[0], true);
        }
    }
    if (!is_array($parsed)) {
        return [
            'ok' => true,
            'ai_used' => false,
            'data' => [
                'address_detail' => $queryText,
                'map_url' => buildGoogleMapUrl($queryText),
                'hotline' => '',
            ]
        ];
    }

    $address = trim((string)($parsed['address_detail'] ?? ''));
    $mapUrl = trim((string)($parsed['map_url'] ?? ''));
    $hotline = normalizePhone((string)($parsed['hotline'] ?? ''));

    if ($address === '') $address = $queryText;
    if ($mapUrl === '' || !preg_match('#^https?://#i', $mapUrl)) {
        $mapUrl = buildGoogleMapUrl($address);
    }

    return [
        'ok' => true,
        'ai_used' => true,
        'data' => [
            'address_detail' => $address,
            'map_url' => $mapUrl,
            'hotline' => $hotline,
        ]
    ];
}

function ensureStoreUploadDir(): array {
    $root = workspaceRootPath();
    global $uploadFolder;
    $webDir = '/' . ($uploadFolder ?? 'uploads') . '/store';
    $fsDir = $root . str_replace('/', DIRECTORY_SEPARATOR, $webDir);
    
    if (!is_dir($fsDir)) {
        if (!@mkdir($fsDir, 0755, true)) {
            // Thử lại với quyền cao hơn nếu cần, nhưng thường là do directory cha bị ReadOnly
        }
    }

    $isWritable = is_writable($fsDir);
    if (!$isWritable) {
        // Thử ghi file thực tế (phòng hờ is_writable trên Windows trả về false sai)
        $testFile = $fsDir . DIRECTORY_SEPARATOR . 'wtest_' . uniqid() . '.tmp';
        if (@file_put_contents($testFile, '1')) {
            @unlink($testFile);
            $isWritable = true;
        }
    }

    if (!is_dir($fsDir) || !$isWritable) {
        return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . ($uploadFolder ?? 'uploads') . '/store'];
    }
    return ['ok' => true, 'fs' => $fsDir, 'web' => $webDir];
}

function saveUploadedStoreImage(array $file, bool $required = false): array {
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['ok' => false, 'msg' => 'Vui lòng chọn ảnh chi nhánh'];
        }
        return ['ok' => true, 'has_file' => false, 'path' => ''];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Upload ảnh thất bại'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'Ảnh tối đa 5MB'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'Tệp upload không hợp lệ'];
    }

    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowExt, true)) {
        return ['ok' => false, 'msg' => 'Chỉ hỗ trợ jpg, jpeg, png, webp, gif'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    $allowMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if ($mime !== '' && !in_array($mime, $allowMime, true)) {
        return ['ok' => false, 'msg' => 'Định dạng ảnh không hợp lệ'];
    }

    $dir = ensureStoreUploadDir();
    if (!$dir['ok']) {
        return ['ok' => false, 'msg' => $dir['msg']];
    }

    $fileName = 'store_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs = $dir['fs'] . DIRECTORY_SEPARATOR . $fileName;
    if (!@move_uploaded_file($tmp, $destFs)) {
        return ['ok' => false, 'msg' => 'Không thể lưu ảnh tải lên'];
    }

    $storeRel = $dir['web'] . '/' . $fileName;
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file($storeRel);
    }
    return ['ok' => true, 'has_file' => true, 'path' => $storeRel];
}

$regionOptions = getRegionOptions();
$action = trim((string)($_REQUEST['action'] ?? 'list'));

// SECURITY: Enforce CSRF token for all state-changing administrative actions
if (in_array($action, ['save', 'toggle_active', 'delete'], true)) {
    app_verify_csrf();
}

if ($action === 'list') {
    $rows = [];
    $res = $ithanhloc->query("SELECT * FROM site_store ORDER BY sort_order ASC, id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $r['is_active'] = (int)($r['is_active'] ?? 0);
            $rows[] = $r;
        }
    }
    jOut(['ok' => true, 'regions' => $regionOptions, 'rows' => $rows]);
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $branchName = trim((string)($_POST['branch_name'] ?? ''));
    if ($branchName === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên chi nhánh']);

    $region = trim((string)($_POST['region'] ?? ''));
    if ($region !== '' && !in_array($region, $regionOptions, true)) {
        $region = '';
    }

    $address = trim((string)($_POST['address_detail'] ?? ''));
    $hotline = normalizePhone((string)($_POST['hotline'] ?? ''));
    $mapUrl = trim((string)($_POST['map_url'] ?? ''));
    if ($mapUrl !== '' && !preg_match('#^https?://#i', $mapUrl)) {
        $mapUrl = 'https://' . ltrim($mapUrl, '/');
    }
    $sortOrder = (int)($_POST['sort_order'] ?? 100);
    $isActive = ((int)($_POST['is_active'] ?? 0) === 1) ? 1 : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    $openingHoursJson = trim((string)($_POST['opening_hours_json'] ?? ''));
    if ($openingHoursJson !== '') {
        $decoded = json_decode($openingHoursJson, true);
        if (!is_array($decoded)) {
            $openingHoursJson = '';
        } else {
            $openingHoursJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
    }

    $avatarCurrent = trim((string)($_POST['avatar_current'] ?? ''));
    $avatarPath = $avatarCurrent;
    if (isset($_FILES['avatar_image']) && is_array($_FILES['avatar_image'])) {
        $upload = saveUploadedStoreImage($_FILES['avatar_image'], false);
        if (!$upload['ok']) {
            jOut(['ok' => false, 'msg' => $upload['msg']]);
        }
        if ($upload['has_file']) {
            $avatarPath = (string)$upload['path'];
        }
    }

    $galleryExisting = [];
    $galleryRaw = (string)($_POST['gallery_existing'] ?? '');
    if ($galleryRaw !== '') {
        $tmp = json_decode($galleryRaw, true);
        if (is_array($tmp)) {
            foreach ($tmp as $g) {
                $s = trim((string)$g);
                if ($s !== '') $galleryExisting[] = $s;
            }
        }
    }

    $galleryAll = $galleryExisting;
    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'] ?? null)) {
        $names = $_FILES['gallery_images']['name'];
        $tmpNames = $_FILES['gallery_images']['tmp_name'];
        $sizes = $_FILES['gallery_images']['size'];
        $errors = $_FILES['gallery_images']['error'];
        $count = count($names);
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $names[$i] ?? '',
                'tmp_name' => $tmpNames[$i] ?? '',
                'size' => $sizes[$i] ?? 0,
                'error' => $errors[$i] ?? UPLOAD_ERR_NO_FILE,
            ];
            $up = saveUploadedStoreImage($file, false);
            if (!$up['ok']) {
                jOut(['ok' => false, 'msg' => $up['msg']]);
            }
            if ($up['has_file']) {
                $galleryAll[] = (string)$up['path'];
            }
        }
    }
    $galleryJson = $galleryAll ? json_encode(array_values(array_unique($galleryAll)), JSON_UNESCAPED_UNICODE) : '';

    if ($id > 0) {
        $stmt = $ithanhloc->prepare("UPDATE site_store SET branch_name=?, region=?, address_detail=?, hotline=?, map_url=?, sort_order=?, is_active=?, notes=?, avatar_image=?, gallery_images_json=?, opening_hours_json=? WHERE id=?");
        if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể cập nhật chi nhánh']);
        $stmt->bind_param('sssssiissssi', $branchName, $region, $address, $hotline, $mapUrl, $sortOrder, $isActive, $notes, $avatarPath, $galleryJson, $openingHoursJson, $id);
        $ok = $stmt->execute();
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật chi nhánh' : 'Không thể cập nhật chi nhánh']);
    }

    $stmt = $ithanhloc->prepare("INSERT INTO site_store (branch_name, region, address_detail, hotline, map_url, sort_order, is_active, notes, avatar_image, gallery_images_json, opening_hours_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể tạo chi nhánh']);
    $stmt->bind_param('sssssiissss', $branchName, $region, $address, $hotline, $mapUrl, $sortOrder, $isActive, $notes, $avatarPath, $galleryJson, $openingHoursJson);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã thêm chi nhánh' : 'Không thể tạo chi nhánh']);
}

if ($action === 'toggle_active') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID chi nhánh']);
    $isActive = ((int)($_POST['is_active'] ?? 0) === 1) ? 1 : 0;
    $stmt = $ithanhloc->prepare('UPDATE site_store SET is_active=? WHERE id=?');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể cập nhật trạng thái']);
    $stmt->bind_param('ii', $isActive, $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut([
        'ok' => (bool)$ok,
        'msg' => $ok
            ? ($isActive ? 'Đã bật chi nhánh' : 'Đã tắt chi nhánh')
            : 'Không thể cập nhật trạng thái'
    ]);
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jOut(['ok' => false, 'msg' => 'Thiếu ID chi nhánh']);
    $stmt = $ithanhloc->prepare('DELETE FROM site_store WHERE id=?');
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể xóa chi nhánh']);
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xóa chi nhánh' : 'Không thể xóa chi nhánh']);
}

if ($action === 'ai_suggest') {
    $branchName = trim((string)($_POST['branch_name'] ?? ''));
    $region = trim((string)($_POST['region'] ?? ''));
    $addressInput = trim((string)($_POST['address_input'] ?? ''));
    $apiKey = getOpenAiKey($ithanhloc);
    $result = aiSuggestBranch($apiKey, $branchName, $region, $addressInput);
    jOut($result);
}

jOut(['ok' => false, 'msg' => 'Action không hợp lệ']);
