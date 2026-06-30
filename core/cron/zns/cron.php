<?php
/**
 * Cronjob: Tự động hóa Zalo ZNS (Làm mới Token, Xác nhận đơn hàng, Trạng thái giao hàng)
 * Tần suất khuyến nghị: chạy mỗi 5-15 phút.
 */

// 1. Cấu hình bật/tắt các tiến trình con trực tiếp trong file
if (!isset($zns_cron_config)) {
    $zns_cron_config = [
        'refresh_token'   => true, // Tự động làm mới Zalo ZNS Access Token / Refresh Token
        'order_confirm'   => true, // Tự động gửi thông báo xác nhận đơn hàng (ORDER_CONFIRM)
        'order_shipping'  => true, // Tự động gửi thông báo trạng thái vận chuyển (ORDER_SHIPPING)
    ];
}

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../zns/conf.php';

// Thiết lập header cho trình duyệt
header('Refresh: 300');
header('Content-Type: text/plain; charset=utf-8');

// 2. Các hàm bổ trợ
if (!function_exists('zns_api_call')) {
    function zns_api_call(string $url, string $token, array $data): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', "access_token: $token"]
        ]);
        $res = json_decode(curl_exec($ch), true);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => ($res['error'] ?? -1) === 0, 'res' => $res, 'http' => $http];
    }
}

if (!function_exists('normalize_phone')) {
    function normalize_phone(string $p): string {
        $p = preg_replace('/\D+/', '', $p);
        if (strpos($p, '0') === 0) $p = '84' . substr($p, 1);
        return (strlen($p) >= 11 && strlen($p) <= 12 && strpos($p, '84') === 0) ? $p : '';
    }
}

if (!function_exists('status_label')) {
    function status_label(string $s): string {
        $map = [
            'pending'          => 'Chờ xác nhận',
            'processing'       => 'Đang chuẩn bị hàng',
            'shipping'         => 'Đang giao hàng',
            'delivered'        => 'Đã giao thành công',
            'completed'        => 'Đã hoàn thành',
            'cancel_requested' => 'Đang chờ duyệt hủy',
            'return_requested' => 'Đang chờ duyệt trả hàng',
            'canceled'         => 'Đã hủy đơn',
            'returned'         => 'Đã trả hàng',
            'refunded'         => 'Đã hoàn tiền',
        ];
        return $map[strtolower($s)] ?? $s;
    }
}

// Danh sách (whitelist) các trạng thái ĐƯỢC PHÉP gửi ZNS cập nhật vận chuyển.
// Chỉ gửi cho các mốc quan trọng với khách; KHÔNG gửi cho yêu cầu hủy/trả, đã hủy/hoàn...
// Dùng whitelist (thay vì blacklist) để status mới thêm sau này không bị gửi nhầm.
if (!function_exists('zns_shipping_status_allowed')) {
    function zns_shipping_status_allowed(): array {
        return ['processing', 'shipping', 'delivered', 'completed'];
    }
}

if (!function_exists('zns_update_settings')) {
    function zns_update_settings(mysqli $db, array $data): void {
        $stmt = $db->prepare("INSERT INTO site_zns_conf (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($data as $k => $v) {
            $key = (string)$k; $val = (string)$v;
            $stmt->bind_param('ss', $key, $val);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// 3. Đọc cấu hình Zalo ZNS từ database/global
$appId       = trim($zalo_config['app_id'] ?? '');
$secretKey   = trim($zalo_config['secret_key'] ?? '');
$accessToken = trim($zalo_config['accessToken'] ?? '');
$refreshToken = trim($zalo_config['refreshToken'] ?? '');

$confirmTplId  = $templateId['ORDER_CONFIRM'] ?? '555125';
$shippingTplId = $templateId['ORDER_SHIPPING'] ?? '547474';

if (!$appId || !$secretKey) {
    exit("[ZNS CRON] Thiếu cấu hình App ID hoặc Secret Key của Zalo OA.\n");
}

// ===== TIẾN TRÌNH 1: LÀM MỚI ACCESS TOKEN / REFRESH TOKEN =====================
if ($zns_cron_config['refresh_token']) {
    echo "[ZNS TOKEN] Đang kiểm tra làm mới token...\n";
    if (!$refreshToken) {
        echo "[ZNS TOKEN] Thất bại: Thiếu Refresh Token.\n";
    } else {
        $ch = curl_init('https://oauth.zaloapp.com/v4/oa/access_token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['refresh_token' => $refreshToken, 'app_id' => $appId, 'grant_type' => 'refresh_token']),
            CURLOPT_HTTPHEADER => ["secret_key: $secretKey"]
        ]);

        $res = json_decode(curl_exec($ch), true);
        $err = curl_error($ch);
        curl_close($ch);

        if (isset($res['access_token'])) {
            $accessToken = $res['access_token']; // Cập nhật token đang dùng trong bộ nhớ để các tiến trình tiếp theo sử dụng
            $update = ['accessToken' => $res['access_token']];
            if (!empty($res['refresh_token'])) {
                $refreshToken = $res['refresh_token'];
                $update['refreshToken'] = $res['refresh_token'];
            }
            zns_update_settings($ithanhloc, $update);
            echo "[ZNS TOKEN] Thành công! Cập nhật lúc " . date('H:i:s') . "\n";
        } else {
            echo "[ZNS TOKEN] Thất bại: " . ($res['message'] ?? $err ?? 'Lỗi không xác định') . "\n";
        }
    }
} else {
    echo "[ZNS TOKEN] Tiến trình làm mới token bị TẮT.\n";
}

// Kiểm tra Access Token khả dụng cho các tiến trình tiếp theo
if (!$accessToken) {
    exit("[ZNS CRON] Dừng tiến trình gửi tin: Thiếu Access Token.\n");
}

// ===== TIẾN TRÌNH 2: TỰ ĐỘNG GỬI XÁC NHẬN ĐƠN HÀNG (ORDER_CONFIRM) =============
if ($zns_cron_config['order_confirm']) {
    echo "[ZNS CONFIRM] Đang xử lý...\n";
    $sql = "SELECT o.order_id, o.phone, o.user_name, o.total_amount, o.created_at, o.status 
            FROM ecommerce_order o 
            LEFT JOIN log_zns l ON o.order_id = l.order_id AND l.notification_type = 'ORDER_CONFIRM'
            WHERE o.status = 'pending' AND o.phone IS NOT NULL AND l.id IS NULL 
            ORDER BY o.id DESC LIMIT 20";

    $res = $ithanhloc->query($sql);
    if (!$res || $res->num_rows === 0) {
        echo "[ZNS CONFIRM] Không có đơn hàng mới cần gửi.\n";
    } else {
        $sent = 0; $failed = 0;
        while ($order = $res->fetch_assoc()) {
            $phone = normalize_phone($order['phone']);
            if (!$phone) { $failed++; continue; }

            $tplData = [
                'phone'      => $order['phone'],
                'price'      => (float)$order['total_amount'],
                'name'       => $order['user_name'] ?: 'Khách hàng',
                'created_at' => date('H:i d/m/Y', strtotime($order['created_at'])),
                'order_id'   => 'DH-' . $order['order_id'],
                'status'     => 'Chờ xác nhận'
            ];

            $api = zns_api_call('https://business.openapi.zalo.me/message/template', $accessToken, [
                'phone' => $phone,
                'template_id' => $confirmTplId,
                'template_data' => $tplData
            ]);

            $status = $api['ok'] ? 'sent' : 'failed';
            $err = $api['ok'] ? '' : ($api['res']['message'] ?? 'Unknown error');
            $stmt = $ithanhloc->prepare("INSERT INTO log_zns (order_id, phone, payload_json, response_json, status, error_message, notification_type, order_status) VALUES (?, ?, ?, ?, ?, ?, 'ORDER_CONFIRM', ?)");
            $pJson = json_encode($tplData, JSON_UNESCAPED_UNICODE);
            $rJson = json_encode($api['res'], JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('sssssss', $order['order_id'], $phone, $pJson, $rJson, $status, $err, $order['status']);
            $stmt->execute();
            $stmt->close();

            if ($api['ok']) $sent++; else $failed++;
        }
        echo "[ZNS CONFIRM] Hoàn tất. Thành công: $sent, Thất bại: $failed\n";
    }
} else {
    echo "[ZNS CONFIRM] Tiến trình gửi xác nhận đơn hàng bị TẮT.\n";
}

// ===== TIẾN TRÌNH 3: TỰ ĐỘNG GỬI CẬP NHẬT TRẠNG THÁI VẬN CHUYỂN (ORDER_SHIPPING)
if ($zns_cron_config['order_shipping']) {
    echo "[ZNS SHIPPING] Đang xử lý...\n";
    // Chỉ gửi cho các trạng thái quan trọng (whitelist), bỏ qua yêu cầu hủy/trả, đã hủy/hoàn...
    $allowed = zns_shipping_status_allowed();
    $allowedIn = "'" . implode("','", array_map([$ithanhloc, 'real_escape_string'], $allowed)) . "'";
    $sql = "SELECT o.order_id, o.phone, o.user_name, o.created_at, o.status
            FROM ecommerce_order o
            LEFT JOIN log_zns l ON o.order_id = l.order_id AND l.notification_type = 'ORDER_SHIPPING' AND l.order_status = o.status
            WHERE o.status IN ($allowedIn) AND o.phone IS NOT NULL AND l.id IS NULL
            ORDER BY o.id DESC LIMIT 50";

    $res = $ithanhloc->query($sql);
    if (!$res || $res->num_rows === 0) {
        echo "[ZNS SHIPPING] Không có cập nhật trạng thái đơn hàng mới.\n";
    } else {
        $sent = 0; $failed = 0;
        while ($order = $res->fetch_assoc()) {
            $phone = normalize_phone($order['phone']);
            if (!$phone) { $failed++; continue; }

            $tplData = [
                'order_code'   => (string)$order['order_id'],
                'order_status' => status_label($order['status']),
                'user_name'    => $order['user_name'] ?: 'Khách hàng',
                'created_at'   => date('H:i d/m/Y', strtotime($order['created_at']))
            ];

            $api = zns_api_call('https://business.openapi.zalo.me/message/template', $accessToken, [
                'phone' => $phone,
                'template_id' => $shippingTplId,
                'template_data' => $tplData,
                'tracking_id' => $order['order_id']
            ]);

            $status = $api['ok'] ? 'sent' : 'failed';
            $err = $api['ok'] ? '' : ($api['res']['message'] ?? 'Unknown error');
            $stmt = $ithanhloc->prepare("INSERT INTO log_zns (order_id, phone, payload_json, response_json, status, error_message, notification_type, order_status) VALUES (?, ?, ?, ?, ?, ?, 'ORDER_SHIPPING', ?)");
            $pJson = json_encode($tplData, JSON_UNESCAPED_UNICODE);
            $rJson = json_encode($api['res'], JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('sssssss', $order['order_id'], $phone, $pJson, $rJson, $status, $err, $order['status']);
            $stmt->execute();
            $stmt->close();

            if ($api['ok']) $sent++; else $failed++;
        }
        echo "[ZNS SHIPPING] Hoàn tất. Thành công: $sent, Thất bại: $failed\n";
    }
} else {
    echo "[ZNS SHIPPING] Tiến trình gửi cập nhật trạng thái vận chuyển bị TẮT.\n";
}
