<?php
require_once __DIR__ . '/../../config.php';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$action = trim((string)$action);
function zalopay_build_mac(array $params, string $key1): string {
    // Sử dụng cấu trúc mới: mac = HMAC_SHA256(app_id|app_trans_id|app_user|amount|app_time|embed_data|item)
    $data = implode('|', [
        $params['app_id'] ?? '',
        $params['app_trans_id'] ?? '',
        $params['app_user'] ?? '',
        $params['amount'] ?? '',
        $params['app_time'] ?? '',
        $params['embed_data'] ?? '',
        $params['item'] ?? '',
    ]);
    return hash_hmac('sha256', $data, $key1);
}

function zalopay_post_json(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'msg' => ($err !== '' ? $err : 'Lỗi kết nối ZaloPay (01)'), 'http' => $httpCode];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => 'Lỗi kết nối ZaloPay (02)', 'http' => $httpCode, 'raw' => $raw];
    }
    return ['ok' => true, 'http' => $httpCode, 'data' => $json];
}

function zalopay_json_out(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Lấy config từ setting.php / site_setting
function zalopay_get_config_from_settings(): array {
    $appId      = (int)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.app_id');
    $key1       = (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key1');
    $key2       = (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key2');
    $createUrl  = (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.createUrl');
    $callbackUrl= (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.callbackUrl');
    $redirectUrl= (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.redirectUrl');
    if ($createUrl === '') {
        $createUrl = 'https://sb-openapi.zalopay.vn/v2/create';
    }
    if ($callbackUrl === '' && $baseUrl !== '') {
        $callbackUrl = $baseUrl . '/core_admin/zalopay/api.php';
    }
    if ($redirectUrl === '' && $baseUrl !== '') {
        $redirectUrl = $baseUrl . '/order-confirm';
    }

    return [
        'app_id'      => $appId,
        'key1'        => $key1,
        'key2'        => $key2,
        'createUrl'   => $createUrl,
        'callbackUrl' => $callbackUrl,
        'redirectUrl' => $redirectUrl,
    ];
}

// Tạo order dùng chung  cho cả test và thực tế. Trả về mảng kết quả từ zalopay_post_json() với các key: ok, http, data, msg?, raw?
function zalopay_create_order(array $opts): array {
    global $company_bank_content_text;
    $cfg = zalopay_get_config_from_settings();
    $appId = (int)($cfg['app_id'] ?? 0);
    $key1 = (string)($cfg['key1'] ?? '');
    $endpoint = (string)($cfg['createUrl'] ?? '');
    $callbackUrl = (string)($cfg['callbackUrl'] ?? '');
    // Kiêm tra cấu hình bắt buộc: app_id, key1, createUrl
    if ($appId <= 0 || $key1 === '' || $endpoint === '') {
        return ['ok' => false, 'msg' => 'Cổng thanh toán đang gặp sự cố'];
    }
    // Hàm $amount sẽ nhận giá trị từ opts['amount'] (đơn vị VND), sẽ được validate ở đây. 
    // Nếu hợp lệ, sẽ nhân với 100 để chuyển sang đơn vị nhỏ nhất (đồng xu) trước khi gửi lên ZaloPay.
    $amount = (int)($opts['amount'] ?? 0);
    if ($amount <= 0) {
        return ['ok' => false, 'msg' => 'Số tiền không hợp lệ'];
    }
    // Các tham số khác: order_id (bắt buộc), app_user, bank_code, description, embed_data, items
    $orderId = trim((string)($opts['order_id'] ?? ''));
    if ($orderId === '') {
        return ['ok' => false, 'msg' => 'Không tìm thấy mã đơn hàng'];
    }
    $appUser = (string)($opts['app_user'] ?? 'guest');
    $bankCode = (string)($opts['bank_code'] ?? '');
    $description = ($company_bank_content_text .''. $orderId); //(string)($opts['description'] ?? 

    $nowMs = (int)round(microtime(true) * 1000);
    $today = date('ymd');
    // app_trans_id phải unique trong ngày
    // Format: yymmdd_ORDERID_suffix (order_id nằm ở segment thứ 2)
    $suffix = substr((string)$nowMs, -6);
    $appTransId = $today . '_' . $orderId . '_' . $suffix;

    $embed = $opts['embed_data'] ?? ['order_id' => $orderId];
    if (!is_array($embed)) $embed = ['order_id' => $orderId];

    $embedData = json_encode($embed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $itemJson = json_encode($opts['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $params = [
        'app_id' => $appId,
        'app_user' => $appUser,
        'app_time' => $nowMs,
        'amount' => $amount,
        'app_trans_id' => $appTransId,
        'bank_code' => $bankCode,
        'embed_data' => $embedData,
        'item' => $itemJson,
        'callback_url' => $callbackUrl,
        'description' => $description,
    ];
    $params['mac'] = zalopay_build_mac($params, $key1);

    return zalopay_post_json($endpoint, $params);
}

// ----------------------
// 1) TEST từ admin
// ----------------------
if ($action === 'test_create_order') {
    if (empty($isAdmin)) {
        zalopay_json_out(['ok' => false, 'msg' => 'Chỉ dành cho admin']);
    }
    $amount = (int)($_POST['amount'] ?? 10000);
    if ($amount <= 0) $amount = 10000;

    $res = zalopay_create_order([
        'order_id' => 'TEST-' . date('ymd-His'),
        'amount' => $amount,
        'app_user' => 'admin_test',
        'description' => 'Test thanh toán Zalopay',
    ]);
    zalopay_json_out($res);
}

// ----------------------
// 2) Tạo order thực tế (checkout gọi AJAX)
// ----------------------
if ($action === 'create_order') {
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    $amount = (int)($_POST['amount'] ?? 0);
    $appUser = (string)($_POST['app_user'] ?? 'guest');
    $bankCode = (string)($_POST['bank_code'] ?? '');

    $res = zalopay_create_order([
        'order_id' => $orderId,
        'amount' => $amount,
        'app_user' => $appUser,
        'bank_code' => $bankCode,
    ]);
    zalopay_json_out($res);
}

// ----------------------
// 3) Callback IPN từ ZaloPay (data + mac, dùng key2)
// ----------------------
function zalopay_ipn_response(int $code, string $msg): void {
    echo json_encode(['return_code' => $code, 'return_message' => $msg]);
    exit;
}

if ($action === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg = zalopay_get_config_from_settings();
    $key2 = (string)($cfg['key2'] ?? '');
    if ($key2 === '') {
        zalopay_ipn_response(-1, 'Thiếu cấu hình key2');
    }
    $raw = file_get_contents('php://input');
    $data = $_POST['data'] ?? null;
    $mac = $_POST['mac'] ?? null;

    if ($data === null && $raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = $json['data'] ?? null;
            $mac = $json['mac'] ?? null;
        }
    }

    if (!is_string($data) || !is_string($mac)) {
        zalopay_ipn_response(-1, 'Dữ liệu không hợp lệ (payload)');
    }

    $macLocal = hash_hmac('sha256', $data, $key2);
    if (!hash_equals($macLocal, $mac)) {
        zalopay_ipn_response(-1, 'Dữ liệu không hợp lệ (MAC mismatch)');
    }

    $payload = json_decode($data, true);
    if (!is_array($payload)) {
        zalopay_ipn_response(-1, 'Dữ liệu JSON không hợp lệ');
    }

    // Map dữ liệu từ ZaloPay
    $appTransId = (string)($payload['app_trans_id'] ?? '');
    $zpTransId = (string)($payload['zp_trans_id'] ?? '');
    $amount = (int)($payload['amount'] ?? 0);
    $status = (int)($payload['status'] ?? -1);

    $embedDataRaw = (string)($payload['embed_data'] ?? '');
    $embed = $embedDataRaw !== '' ? json_decode($embedDataRaw, true) : [];
    if (!is_array($embed)) $embed = [];
    $orderId = (string)($embed['order_id'] ?? '');

    if ($orderId === '') {
        // fallback: lấy phần sau dấu _ trong app_trans_id
        if (strpos($appTransId, '_') !== false) {
            $parts = explode('_', $appTransId);
            $orderId = (string)($parts[1] ?? '');
        }
    }

    if ($orderId === '') {
        zalopay_ipn_response(-1, 'Đơn hàng không xác định');
    }

    // Cập nhật ecommerce_order tương tự VNPAY
    global $ithanhloc;
    if (!$ithanhloc instanceof mysqli) {
        zalopay_ipn_response(-1, 'Cơ sở dữ liệu chưa sẵn sàng');
    }
    $ithanhloc->set_charset('utf8mb4');

    $cols = listColumns($ithanhloc, 'ecommerce_order');
    if (!$cols) {
        zalopay_ipn_response(-1, 'Bảng lưu không tồn tại');
    }

    $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE order_id=? LIMIT 1');
    if (!$stmt) {
        zalopay_ipn_response(-1, 'Đơn hàng không tồn tại');
    }
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        zalopay_ipn_response(-1, 'Đơn hàng không tồn tại');
    }

    if (hasCol($cols, 'total_amount') && isset($order['total_amount'])) {
        $expected = (float)($order['total_amount'] ?? 0);
        if ($expected > 0 && abs($expected - $amount) > 0.5) {
            zalopay_ipn_response(-1, 'Invalid amount');
        }
    }

    if (hasCol($cols, 'payment_status') && ($order['payment_status'] ?? '') === 'paid') {
        zalopay_ipn_response(1, 'Đơn hàng đã được thanh toán trước đó');
    }

    $success = ($status === 1);
    $set = [];
    // Chỉ cập nhật sang "paid" khi ZaloPay báo thành công.
    // Nếu status khác 1, giữ nguyên trạng thái hiện tại (pending/failed...) để tránh ghi sai "Lỗi thanh toán".
    if ($success) {
        if (hasCol($cols, 'payment_status')) { $set['payment_status'] = 'paid'; }
        if (hasCol($cols, 'payment_gateway')) { $set['payment_gateway'] = 'zalopay'; }
        if (hasCol($cols, 'payment_ref')) { $set['payment_ref'] = $zpTransId !== '' ? $zpTransId : $appTransId; }
        if (hasCol($cols, 'paid_at')) { $set['paid_at'] = date('Y-m-d H:i:s'); }
        if (hasCol($cols, 'status')) { $set['status'] = 'processing'; }
    }

    if ($set) {
        $fields = array_keys($set);
        $types = str_repeat('s', count($fields) + 1);
        $params = array_values($set);
        $params[] = $orderId;
        $sql = 'UPDATE ecommerce_order SET ' . implode(', ', array_map(fn($f) => "`$f`=?", $fields)) . ' WHERE order_id=?';
        $stmtU = $ithanhloc->prepare($sql);
        if ($stmtU) {
            $stmtU->bind_param($types, ...$params);
            $stmtU->execute();
            $stmtU->close();
        }
    }
    zalopay_ipn_response(1, 'Đã nhận callback');
}

// Nếu không trùng bất kỳ action nào
zalopay_json_out(['ok' => false, 'msg' => 'Unknown action']);
