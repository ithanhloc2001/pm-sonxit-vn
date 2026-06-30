<?php
require_once __DIR__ . '/../../../config.php'; // DB, session, jOut(), helpers
require_once __DIR__ . '/../conf.php';          // $zalo_config, $templateId

// Bắt mọi lỗi/exception và trả về JSON thay vì HTML 500 (để frontend hiển thị được)
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    jOut(['ok' => false, 'msg' => 'Lỗi máy chủ: ' . $e->getMessage()], 200);
});
// mysqli ném exception thay vì warning để bắt được lỗi SQL
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Chỉ admin mới được truy cập
if (!$isAdmin) {
    jOut(['ok' => false, 'msg' => 'Chỉ admin mới được cấu hình Zalo ZNS.'], 403);
}

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

// ============================================================
// Helper functions
// ============================================================

/**
 * Lấy toàn bộ cài đặt ZNS từ bảng site_zns_conf.
 */
function zns_get_all_settings(mysqli $ithanhloc): array {
    $rows = [];
    $res  = $ithanhloc->query("SELECT setting_key, setting_value FROM site_zns_conf");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $k = (string)($row['setting_key'] ?? '');
            if ($k !== '') {
                $rows[$k] = (string)($row['setting_value'] ?? '');
            }
        }
        $res->close();
    }
    return $rows;
}

/**
 * Lưu hoặc cập nhật một cài đặt ZNS (UPSERT).
 */
function zns_save_setting_item(mysqli $ithanhloc, string $key, string $value): void {
    $stmt = $ithanhloc->prepare(
        "INSERT INTO site_zns_conf (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

// ============================================================
// Action: load — trả về cấu hình hiện tại
// ============================================================
if ($action === 'load') {
    $settings = zns_get_all_settings($ithanhloc);
    jOut([
        'ok'   => true,
        'data' => [
            'app_id'         => $settings['app_id']         ?? ($zalo_config['app_id']      ?? ''),
            'secret_key'     => $settings['secret_key']     ?? ($zalo_config['secret_key']  ?? ''),
            'accessToken'    => $settings['accessToken']    ?? ($zalo_config['accessToken']  ?? ''),
            'refreshToken'   => $settings['refreshToken']   ?? ($zalo_config['refreshToken'] ?? ''),
            'ORDER_CONFIRM'  => $settings['ORDER_CONFIRM']  ?? ($templateId['ORDER_CONFIRM']  ?? ''),
            'ORDER_SHIPPING' => $settings['ORDER_SHIPPING'] ?? ($templateId['ORDER_SHIPPING'] ?? ''),
            'OTP'            => $settings['OTP']            ?? ($templateId['OTP']            ?? ''),
            'REVIEW_SERVICE' => $settings['REVIEW_SERVICE'] ?? ($templateId['REVIEW_SERVICE'] ?? ''),
        ],
    ]);
}

// ============================================================
// Action: save — lưu cấu hình
// ============================================================
if ($action === 'save') {
    $appId     = trim((string)($_POST['app_id']     ?? ''));
    $secretKey = trim((string)($_POST['secret_key'] ?? ''));

    if ($appId === '' || $secretKey === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng nhập đầy đủ App ID và Secret Key.'], 400);
    }

    $data = [
        'app_id'         => $appId,
        'secret_key'     => $secretKey,
        'accessToken'    => trim((string)($_POST['accessToken']         ?? '')),
        'refreshToken'   => trim((string)($_POST['refreshToken']        ?? '')),
        'ORDER_CONFIRM'  => trim((string)($_POST['tpl_ORDER_CONFIRM']   ?? '')),
        'ORDER_SHIPPING' => trim((string)($_POST['tpl_ORDER_SHIPPING']  ?? '')),
        'OTP'            => trim((string)($_POST['tpl_OTP']             ?? '')),
        'REVIEW_SERVICE' => trim((string)($_POST['tpl_REVIEW_SERVICE']  ?? '')),
    ];

    foreach ($data as $k => $v) {
        zns_save_setting_item($ithanhloc, $k, $v);
    }

    jOut(['ok' => true, 'msg' => 'Đã lưu cấu hình Zalo ZNS thành công.']);
}

// ============================================================
// Action: refresh_token — làm mới Access Token qua Zalo OAuth
// ============================================================
if ($action === 'refresh_token') {
    $settings  = zns_get_all_settings($ithanhloc);
    $appId     = $settings['app_id']      ?? '';
    $secretKey = $settings['secret_key']  ?? '';
    $refresh   = $settings['refreshToken'] ?? '';

    if ($appId === '' || $secretKey === '' || $refresh === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu cấu hình App ID/Secret/Refresh Token để làm mới.'], 200);
    }

    $ch = curl_init('https://oauth.zaloapp.com/v4/oa/access_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'refresh_token' => $refresh,
            'app_id'        => $appId,
            'grant_type'    => 'refresh_token',
        ]),
        CURLOPT_HTTPHEADER     => ["secret_key: $secretKey"],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    // Lỗi tầng vận chuyển (SSL/CA/timeout) — curl không trả về dữ liệu
    if ($raw === false || $raw === '') {
        jOut(['ok' => false, 'msg' => 'Không kết nối được tới Zalo OAuth: ' . ($err ?: 'không có phản hồi')], 200);
    }

    $res = json_decode((string)$raw, true);

    if (empty($res['access_token'])) {
        // Trả 200 để frontend đọc được thông báo lỗi cụ thể từ Zalo thay vì rơi vào .catch()
        $zaloMsg = $res['error_description'] ?? $res['message'] ?? ($err ?: 'Không xác định');
        $zaloCode = isset($res['error']) ? ' (mã ' . $res['error'] . ')' : '';
        jOut(['ok' => false, 'msg' => 'Lỗi từ Zalo: ' . $zaloMsg . $zaloCode], 200);
    }

    $update = ['accessToken' => $res['access_token']];
    if (!empty($res['refresh_token'])) {
        $update['refreshToken'] = $res['refresh_token'];
    }

    foreach ($update as $k => $v) {
        zns_save_setting_item($ithanhloc, $k, $v);
    }

    jOut(['ok' => true, 'msg' => 'Làm mới Access Token thành công.']);
}

jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ.'], 400);
