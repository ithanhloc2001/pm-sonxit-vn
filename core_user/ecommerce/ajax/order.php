<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php 

if (!function_exists('listOrderColumns')) {
    function listOrderColumns(mysqli $ithanhloc, bool $forceRefresh = false): array {
        $cols = function_exists('listColumns') ? listColumns($ithanhloc, 'ecommerce_order', $forceRefresh) : [];
        $map = [];
        foreach ($cols as $c) {
            $key = trim((string)$c);
            if ($key !== '') {
                $map[$key] = true;
            }
        }
        return $map;
    }
}
// Các function xử lý logic liên quan đến đơn hàng sẽ được định nghĩa ở đây, bao gồm cả các function chung cho nhiều action khác nhau (như parsePaymentMeta, normalizeMomoMetaView, v.v.) để tránh trùng lặp code giữa các action.

// Xác định action trước để cho phép một số action dành cho khách vãng lai (user_id = 0)
$ajax = $_GET['ajax'] ?? '';
$action = strtolower(trim((string)($_REQUEST['action'] ?? ($ajax ?: 'list'))));

// Các action cho phép truy cập khi không đăng nhập (khách vãng lai)
$guestAllowedActions = ['list', 'summary', 'detail', 'sync_payment', 'sync_momo_payment', 'change_payment', 'cancel', 'cancel_request', 'confirm', 'return'];

// === CSRF PROTECTION FOR STATE-CHANGING ACTIONS ===
$stateChangingActions = ['change_payment', 'cancel', 'confirm', 'return', 'cancel_order', 'cancel_request', 'confirm_received', 'request_return', 'update_address'];
if (in_array($action, $stateChangingActions, true)) {
    app_verify_csrf();
}

$sessionUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : '';
$sessionRole = (string)($_SESSION['role'] ?? '');

// Treat user_id=0 as guest (some legacy flows might set it)
if ($sessionUserId === '' || $sessionUserId === '0') {
    // Khách vãng lai: chỉ cho phép xem chi tiết/sync/change thanh toán theo mã đơn
    if (!in_array($action, $guestAllowedActions, true)) {
        jOut(['ok' => false, 'msg' => 'Unauthorized'], 401);
    }

} else {
    /*
     Người dùng đã đăng nhập: nếu là admin thì cấm truy cập vào đây, vì admin sẽ có endpoint riêng ở core_admin/ajax/order.php
    if ($sessionRole === 'admin') {
        jOut(['ok' => false, 'msg' => 'Forbidden'], 403);
    }
    */
    // Chỉ chạy auto-cancel tối đa 1 lần mỗi 5 phút per-user để tránh blocking mỗi request.
    $__cancelCheckKey = '_order_cancel_check_' . (int)ecommerce_current_user_id();
    if (empty($_SESSION[$__cancelCheckKey]) || (time() - (int)$_SESSION[$__cancelCheckKey]) >= 300) {
        $_SESSION[$__cancelCheckKey] = time();
        session_write_close();
        autoCancelExpiredPendingOrders($ithanhloc, (int)ecommerce_current_user_id());
    } else {
        session_write_close();
    }
}

if ($ajax === 'orders_list') {
    handleListRequest($ithanhloc);
}
if ($ajax === '1') {
    handleLegacyDataTable($ithanhloc);
}
switch ($action) {
    case '':
    case 'orders_list':
    case 'list':
        handleListRequest($ithanhloc);
        break;
    case 'summary':
        handleSummaryRequest($ithanhloc);
        break;
    case 'detail':
        handleDetailRequest($ithanhloc);
        break;
    case 'sync_payment':
    case 'sync_momo_payment':
        handleSyncPaymentRequest($ithanhloc);
        break;
    case 'change_payment':
        try {
            handleChangePaymentRequest($ithanhloc);
        } catch (Throwable $e) {
            error_log('change_payment fatal: ' . $e->getMessage());
            jOut(['ok' => false, 'msg' => 'Không thể đổi phương thức thanh toán lúc này.'], 500);
        }
        break;
    case 'cancel':
    case 'cancel_order':
    case 'cancel_request':
        handleCancelRequest($ithanhloc);
        break;
    case 'confirm':
    case 'confirm_received':
        handleConfirmRequest($ithanhloc);
        break;
    case 'return':
    case 'request_return':
        handleReturnRequest($ithanhloc);
        break;
    case 'update_address':
        handleUpdateAddressRequest($ithanhloc);
        break;
    default:
        jOut(['ok' => false, 'msg' => 'Unsupported action'], 400);
}

function formatHcmDateTime(int $ts): string {
    if ($ts <= 0) return '';
    try {
        $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
        return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('H:i:s d/m/Y');
    } catch (Throwable $e) {
        return date('H:i:s d/m/Y', $ts);
    }
}

function tempEtaFromCreated(string $createdAt, int $days = 3): array {
    $createdTs = ecommerce_parse_hcm_datetime_to_ts($createdAt);
    if ($createdTs <= 0) return ['eta_text' => '', 'eta_latest' => ''];
    $days = max(1, $days);
    $etaTs = $createdTs + ($days * 86400);
    return [
        'eta_text' => formatHcmDateTime($etaTs),
        'eta_latest' => formatHcmDateTime($etaTs),
    ];
}

function momoConfigOrder(): array {
    $cfg = app_get_momo_config_by_env();
    return [
        'enabled' => !empty($cfg['enabled']),
        'partnerCode' => trim((string)($cfg['partnerCode'] ?? '')),
        'accessKey' => trim((string)($cfg['accessKey'] ?? '')),
        'secretKey' => trim((string)($cfg['secretKey'] ?? '')),
        'queryUrl' => trim((string)($cfg['queryUrl'] ?? '')),
    ];
}

function momoSignQuery(array $payload, string $secretKey): string {
    $raw = 'accessKey=' . ($payload['accessKey'] ?? '')
        . '&orderId=' . ($payload['orderId'] ?? '')
        . '&partnerCode=' . ($payload['partnerCode'] ?? '')
        . '&requestId=' . ($payload['requestId'] ?? '');
    return hash_hmac('sha256', $raw, $secretKey);
}

function momoPostJson(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'msg' => $err !== '' ? $err : 'Không thể gọi MoMo API'];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => 'MoMo API trả về dữ liệu không hợp lệ', 'http_code' => $http, 'raw' => $raw];
    }
    return ['ok' => true, 'http_code' => $http, 'data' => $json];
}

function momoQueryOrderStatus(string $momoOrderId, string $requestId): array {
    $cfg = momoConfigOrder();
    if (!$cfg['enabled']) return ['ok' => false, 'msg' => 'MoMo chưa bật'];
    if ($cfg['partnerCode'] === '' || $cfg['accessKey'] === '' || $cfg['secretKey'] === '') {
        return ['ok' => false, 'msg' => 'Thiếu cấu hình MoMo'];
    }
    if ($momoOrderId === '' || $requestId === '') {
        return ['ok' => false, 'msg' => 'Thiếu orderId/requestId để kiểm tra MoMo'];
    }

    $payload = [
        'partnerCode' => $cfg['partnerCode'],
        'requestId' => $requestId,
        'orderId' => $momoOrderId,
        'lang' => 'vi',
    ];
    $payload['signature'] = momoSignQuery([
        'accessKey' => $cfg['accessKey'],
        'orderId' => $momoOrderId,
        'partnerCode' => $cfg['partnerCode'],
        'requestId' => $requestId,
    ], $cfg['secretKey']);

    return momoPostJson($cfg['queryUrl'], $payload);
}

function buildVnpayUrl(array $params, string $hashSecret, string $baseUrl): string {
    ksort($params);
    $hashData = '';
    $query = '';
    $i = 0;
    foreach ($params as $key => $value) {
        $val = (string)$value;
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . '=' . urlencode($val);
        } else {
            $hashData .= urlencode($key) . '=' . urlencode($val);
            $i = 1;
        }
        $query .= urlencode($key) . '=' . urlencode($val) . '&';
    }
    $secureHash = hash_hmac('sha512', $hashData, $hashSecret);
    return $baseUrl . '?' . $query . 'vnp_SecureHash=' . $secureHash;
}

function momoConfigCreate(): array {
    $cfg = app_get_momo_config_by_env();
    return [
        'enabled' => !empty($cfg['enabled']),
        'partnerCode' => trim((string)($cfg['partnerCode'] ?? '')),
        'accessKey' => trim((string)($cfg['accessKey'] ?? '')),
        'secretKey' => trim((string)($cfg['secretKey'] ?? '')),
        'redirectUrl' => trim((string)($cfg['redirectUrl'] ?? '')),
        'ipnUrl' => trim((string)($cfg['ipnUrl'] ?? '')),
        'createUrl' => trim((string)($cfg['createUrl'] ?? '')),
        'requestType' => 'captureWallet',
    ];
}

function momoSignCreate(array $payload, string $secretKey): string {
    $raw = 'accessKey=' . ($payload['accessKey'] ?? '')
        . '&amount=' . ($payload['amount'] ?? '')
        . '&extraData=' . ($payload['extraData'] ?? '')
        . '&ipnUrl=' . ($payload['ipnUrl'] ?? '')
        . '&orderId=' . ($payload['orderId'] ?? '')
        . '&orderInfo=' . ($payload['orderInfo'] ?? '')
        . '&partnerCode=' . ($payload['partnerCode'] ?? '')
        . '&redirectUrl=' . ($payload['redirectUrl'] ?? '')
        . '&requestId=' . ($payload['requestId'] ?? '')
        . '&requestType=' . ($payload['requestType'] ?? 'captureWallet');
    return hash_hmac('sha256', $raw, $secretKey);
}

function momoHttpPostJson(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'msg' => $err !== '' ? $err : 'Không gọi được MoMo API'];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => 'MoMo API trả về dữ liệu không hợp lệ', 'http_code' => $httpCode, 'raw' => $raw];
    }
    return ['ok' => true, 'http_code' => $httpCode, 'data' => $json];
}

function createMomoQrPayment(array $args): array {
    $cfg = momoConfigCreate();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'msg' => 'MoMo chưa được bật trong cấu hình'];
    }
    if ($cfg['partnerCode'] === '' || $cfg['accessKey'] === '' || $cfg['secretKey'] === '' || $cfg['redirectUrl'] === '' || $cfg['ipnUrl'] === '') {
        return ['ok' => false, 'msg' => 'Thiếu cấu hình MoMo (partnerCode/accessKey/secretKey/redirectUrl/ipnUrl)'];
    }

    $orderId = trim((string)($args['order_id'] ?? ''));
    $amount = (int)round((float)($args['amount'] ?? 0));
    if ($orderId === '' || $amount <= 0) {
        return ['ok' => false, 'msg' => 'Thông tin đơn MoMo không hợp lệ'];
    }

    $forceNew = !empty($args['force_new_session']);
    $requestId = $orderId . '_' . time();
    $momoOrderId = $forceNew ? ($orderId . 'T' . time()) : $orderId;

    $orderInfo = trim((string)($args['order_info'] ?? ('Thanh toán đơn hàng ' . $orderId)));
    $extraData = trim((string)($args['extra_data'] ?? ''));

    $payload = [
        'partnerCode' => $cfg['partnerCode'],
        'partnerName' => 'PaintMore',
        'storeId' => 'PaintMoreStore',
        'requestId' => $requestId,
        'amount' => (string)$amount,
        'orderId' => $momoOrderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $cfg['redirectUrl'],
        'ipnUrl' => $cfg['ipnUrl'],
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => $cfg['requestType'],
        'autoCapture' => true,
    ];
    $payload['signature'] = momoSignCreate([
        'accessKey' => $cfg['accessKey'],
        'amount' => $payload['amount'],
        'extraData' => $payload['extraData'],
        'ipnUrl' => $payload['ipnUrl'],
        'orderId' => $payload['orderId'],
        'orderInfo' => $payload['orderInfo'],
        'partnerCode' => $payload['partnerCode'],
        'redirectUrl' => $payload['redirectUrl'],
        'requestId' => $payload['requestId'],
        'requestType' => $payload['requestType'],
    ], $cfg['secretKey']);

    $res = momoHttpPostJson($cfg['createUrl'], $payload);
    if (!($res['ok'] ?? false)) {
        return $res;
    }
    $data = is_array($res['data'] ?? null) ? $res['data'] : [];
    $code = (int)($data['resultCode'] ?? -1);
    if ($code !== 0) {
        return [
            'ok' => false,
            'msg' => (string)($data['message'] ?? 'MoMo create QR thất bại'),
            'result' => $data,
        ];
    }

    return [
        'ok' => true,
        'result' => $data,
        'request_id' => (string)($payload['requestId'] ?? ''),
        'momo_order_id' => (string)($payload['orderId'] ?? ''),
        'qr_url' => (string)($data['qrCodeUrl'] ?? ''),
        'pay_url' => (string)($data['payUrl'] ?? ''),
        'deeplink' => (string)($data['deeplink'] ?? ''),
    ];
}

function isMomoDuplicateOrderIdError(array $momoResult): bool {
    $result = is_array($momoResult['result'] ?? null) ? $momoResult['result'] : [];
    $code = (string)($result['resultCode'] ?? '');
    if ($code !== '' && in_array($code, ['41', '7000'], true)) {
        return true;
    }

    $msg = strtolower(trim((string)($momoResult['msg'] ?? ($result['message'] ?? ''))));
    if ($msg === '') return false;
    if (strpos($msg, 'orderid') === false) return false;
    return (
        strpos($msg, 'trung') !== false
        || strpos($msg, 'duplicate') !== false
        || strpos($msg, 'exists') !== false
        || strpos($msg, 'exist') !== false
        || strpos($msg, 'ton tai') !== false
    );
}


function autoCancelExpiredPendingOrders(mysqli $ithanhloc, int $userId): void {
    if ($userId <= 0) return;
    $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE user_id=? AND status="pending" ORDER BY created_at DESC LIMIT 100');
    if (!$stmt) return;
    $userIdStr = (string)$userId;
    $stmt->bind_param('s', $userIdStr);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $method = strtolower(trim((string)($row['payment_method'] ?? '')));
        if ($method === '' || $method === 'cod') continue;
        if (!isOrderPaymentExpired($row)) continue;
        markOrderExpiredCancel($ithanhloc, $row);
    }
}

function summaryKeys(): array {
    return ['all', 'pending', 'processing', 'shipping', 'delivered', 'return_requested', 'returned', 'refunded', 'canceled'];
}


function normalizeFilterStatus(?string $status): string {
    $value = strtolower(trim((string)$status));
    if ($value === '' || $value === 'all') {
        return 'all';
    }
    if ($value === 'return') {
        return 'return_requested';
    }
    return (string)(ecommerce_order_status_info($value)['key'] ?? 'pending');
}


function formatGhnEtaHuman(?string $raw): string {
    $ts = parseFlexibleTs($raw);
    return $ts > 0 ? date('H:i d/m/Y', $ts) : '';
}

function extractGhnEtaInfo(array $payload): array {
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $rawBlock = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
    $rawData = is_array($rawBlock['data'] ?? null) ? $rawBlock['data'] : [];

    $pickupRaw = pickFirstNonEmpty([
        $data['pickup_time'] ?? '',
        $data['pickup_shift']['from_time'] ?? '',
        $rawData['pickup_time'] ?? '',
        $rawData['pickup_shift']['from_time'] ?? '',
    ]);
    $pickupTs = parseFlexibleTs($pickupRaw);

    $eta = pickFirstNonEmpty([
        $data['leadtime_order']['to_estimate_date'] ?? '',
        $data['leadtime_order']['from_estimate_date'] ?? '',
        $data['leadtime'] ?? '',
        $rawData['leadtime_order']['to_estimate_date'] ?? '',
        $rawData['leadtime_order']['from_estimate_date'] ?? '',
        $rawData['leadtime'] ?? '',
    ]);

    $etaLatest = pickFirstNonEmpty([
        $data['leadtime_order']['to_estimate_date'] ?? '',
        $rawData['leadtime_order']['to_estimate_date'] ?? '',
        $data['leadtime'] ?? '',
        $rawData['leadtime'] ?? '',
    ]);

    $etaTs = parseFlexibleTs($eta);
    if ($pickupTs > 0 && $etaTs > 0 && $etaTs === $pickupTs) {
        $eta = pickFirstNonEmpty([
            $data['leadtime_order']['to_estimate_date'] ?? '',
            $data['leadtime_order']['from_estimate_date'] ?? '',
            $data['leadtime'] ?? '',
            $rawData['leadtime_order']['to_estimate_date'] ?? '',
            $rawData['leadtime_order']['from_estimate_date'] ?? '',
            $rawData['leadtime'] ?? '',
        ]);
        $etaLatest = pickFirstNonEmpty([
            $data['leadtime_order']['to_estimate_date'] ?? '',
            $rawData['leadtime_order']['to_estimate_date'] ?? '',
            $data['leadtime'] ?? '',
            $rawData['leadtime'] ?? '',
        ]);
    }

    if ($etaLatest === '') $etaLatest = $eta;
    if ($eta === '') $eta = $etaLatest;

    return [
        'eta_raw' => $eta,
        'eta_latest_raw' => $etaLatest,
        'eta_text' => formatGhnEtaHuman($eta),
        'eta_latest_text' => formatGhnEtaHuman($etaLatest),
    ];
}

function fetchCarrierMapBySystemOrders(mysqli $ithanhloc, array $orderIds): array {
    $ids = array_values(array_unique(array_filter(array_map('trim', $orderIds), static fn($v) => $v !== '')));
    if (!$ids) return [];
    if (!tableExists($ithanhloc, 'ghn_order') || !tableExists($ithanhloc, 'ghn_order_log')) return [];

    $safe = implode(',', array_map(static fn($id) => "'" . $ithanhloc->real_escape_string($id) . "'", $ids));
    $sql = "SELECT id, system_order_id, order_code, status, status_text, shipping_fee, updated_at, created_at, response_json
            FROM ghn_order
            WHERE system_order_id IN ({$safe})
            ORDER BY id DESC";
    $res = $ithanhloc->query($sql);
    if (!$res) return [];

    $map = [];
    $orderCodes = [];
    while ($row = $res->fetch_assoc()) {
        $systemOrderId = trim((string)($row['system_order_id'] ?? ''));
        if ($systemOrderId === '' || isset($map[$systemOrderId])) continue;
        $orderCode = trim((string)($row['order_code'] ?? ''));
        $status = trim((string)($row['status'] ?? ''));
        $statusText = trim((string)($row['status_text'] ?? ''));
        $updatedAt = trim((string)($row['updated_at'] ?? $row['created_at'] ?? ''));
        $etaInfo = extractGhnEtaInfo(decodeJsonArray($row['response_json'] ?? ''));
        $map[$systemOrderId] = [
            'carrier' => 'GHN',
            'order_code' => $orderCode,
            'status' => $status,
            'status_text' => ghnStatusLabel($status, $statusText),
            'mapped_status' => mapGhnStatusToOrderStatus($status),
            'shipping_fee' => rawToNumber($row['shipping_fee'] ?? 0),
            'updated_at' => $updatedAt,
            'eta_raw' => $etaInfo['eta_raw'] ?? '',
            'eta_latest_raw' => $etaInfo['eta_latest_raw'] ?? '',
            'eta_text' => $etaInfo['eta_text'] ?? '',
            'eta_latest_text' => $etaInfo['eta_latest_text'] ?? '',
            'timeline' => [],
        ];
        if ($orderCode !== '') $orderCodes[] = $orderCode;
    }

    $orderCodes = array_values(array_unique($orderCodes));
    if (!$orderCodes) return $map;

    $safeCodes = implode(',', array_map(static fn($code) => "'" . $ithanhloc->real_escape_string($code) . "'", $orderCodes));
    $logSql = "SELECT id, order_code, status, status_text, created_at, raw_json
               FROM ghn_order_log
               WHERE order_code IN ({$safeCodes})
               ORDER BY id DESC";
    $logRes = $ithanhloc->query($logSql);
    if (!$logRes) return $map;

    $logsByCode = [];
    $etaByCode = [];
    while ($log = $logRes->fetch_assoc()) {
        $code = trim((string)($log['order_code'] ?? ''));
        if ($code === '') continue;
        if (!isset($logsByCode[$code])) $logsByCode[$code] = [];
        if (count($logsByCode[$code]) >= 20) continue;
        $logStatus = trim((string)($log['status'] ?? ''));
        $label = ghnStatusLabel($logStatus, (string)($log['status_text'] ?? ''));
        $time = trim((string)($log['created_at'] ?? ''));
        $etaInfo = extractGhnEtaInfo(decodeJsonArray($log['raw_json'] ?? ''));
        if (!empty($etaInfo['eta_raw']) || !empty($etaInfo['eta_latest_raw'])) {
            if (!isset($etaByCode[$code])) {
                $etaByCode[$code] = [
                    'eta_raw' => $etaInfo['eta_raw'] ?? '',
                    'eta_latest_raw' => $etaInfo['eta_latest_raw'] ?? '',
                ];
            }
        }
        $logsByCode[$code][] = [
            'label' => $label !== '' ? $label : 'Cập nhật trạng thái',
            'status' => $logStatus,
            'time' => $time,
            'time_human' => $time !== '' ? date('H:i d/m/Y', strtotime($time)) : '',
        ];
    }

    foreach ($map as $oid => $carrier) {
        $code = $carrier['order_code'] ?? '';
        if ($code !== '' && !empty($logsByCode[$code])) {
            $logs = $logsByCode[$code];
            usort($logs, static function($a, $b){
                return strtotime((string)($a['time'] ?? '')) <=> strtotime((string)($b['time'] ?? ''));
            });
            $map[$oid]['timeline'] = $logs;
            $last = end($logs);
            if (is_array($last) && !empty($last['time'])) {
                $map[$oid]['updated_at'] = (string)$last['time'];
            }
            if (!empty($etaByCode[$code]['eta_raw'])) {
                $map[$oid]['eta_raw'] = (string)$etaByCode[$code]['eta_raw'];
                $map[$oid]['eta_text'] = formatGhnEtaHuman($map[$oid]['eta_raw']);
            }
            if (!empty($etaByCode[$code]['eta_latest_raw'])) {
                $map[$oid]['eta_latest_raw'] = (string)$etaByCode[$code]['eta_latest_raw'];
                $map[$oid]['eta_latest_text'] = formatGhnEtaHuman($map[$oid]['eta_latest_raw']);
            }
        }
    }

    return $map;
}

function statusLabel(string $status): string {
    $info = ecommerce_order_status_info($status);
    return (string)($info['label'] ?? 'Chờ xử lý');
}






function getXuConfigOrder(): array {
    return getXuConfig();
}








function pickNumber(array $src, array $keys): float {
    foreach ($keys as $k) {
        if (!array_key_exists($k, $src)) {
            continue;
        }
        $val = rawToNumber($src[$k]);
        if ($val > 0) {
            return $val;
        }
    }
    return 0.0;
}

function parseProductsFromRow(array $row): array {
    $items = [];
    if (!empty($row['products_json'])) {
        $decoded = json_decode($row['products_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string)($item['name'] ?? $item['product'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));

                $lineTotal = pickNumber($item, [
                    'line_total', 'line_total_raw', 'total', 'total_price', 'subtotal', 'amount', 'sum', 'thanh_tien', 'thanhtien', 'total_money', 'tong_tien', 'gia_tong'
                ]);
                $unitPrice = pickNumber($item, [
                    'price', 'price_raw', 'unit_price', 'unitPrice', 'price_value', 'price_num', 'price_vnd', 'sell_price', 'selling_price', 'final_price', 'product_price',
                    'gia', 'gia_tien', 'don_gia', 'gia_ban', 'giaBan', 'gia_sau_giam', 'giaban', 'priceSale', 'special_price'
                ]);
                if ($unitPrice <= 0 && $lineTotal > 0 && $qty > 0) {
                    $unitPrice = $lineTotal / $qty;
                }
                if ($lineTotal <= 0 && $unitPrice > 0) {
                    $lineTotal = $unitPrice * $qty;
                }

                $variant = trim((string)($item['variant'] ?? ''));
                if ($variant === '') {
                    $color = trim((string)($item['color'] ?? $item['color_name'] ?? $item['color_code'] ?? ''));
                    $size = trim((string)($item['size'] ?? $item['size_name'] ?? ''));
                    if ($color !== '' && $size !== '') {
                        $variant = $color . ' / ' . $size;
                    } elseif ($color !== '') {
                        $variant = $color;
                    } elseif ($size !== '') {
                        $variant = $size;
                    }
                }
                // Đánh dấu sản phẩm quà tặng / combo (nếu có) để hiển thị đúng ở chi tiết đơn.
                // Ưu tiên cờ is_gift đã lưu; chỉ suy luận theo giá khi đơn cũ KHÔNG có cờ này
                // (tránh nhầm dòng mua giá 0 tạm thời thành quà).
                if (array_key_exists('is_gift', $item)) {
                    $isGift = !empty($item['is_gift']);
                } else {
                    $isGift = ((float)($item['price'] ?? 0) <= 0.0 && $qty === 1);
                }
                $isCombo = !empty($item['is_combo']);

                // Lưu lại variant_id (nếu có) để có thể truy vấn đúng ảnh theo phân loại
                $variantId = null;
                if (isset($item['variant_id'])) {
                    $variantId = (int)$item['variant_id'];
                } elseif (isset($item['v_id'])) {
                    $variantId = (int)$item['v_id'];
                } elseif (isset($item['vid'])) {
                    $variantId = (int)$item['vid'];
                }

                $items[] = [
                    'id' => isset($item['id']) ? (int)$item['id'] : (isset($item['product_id']) ? (int)$item['product_id'] : null),
                    'variant_id' => $variantId,
                    'name' => $name,
                    'variant' => $variant,
                    'qty' => $qty,
                    'price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'is_gift' => $isGift ? 1 : 0,
                    'is_combo' => $isCombo ? 1 : 0,
                ];
            }
        }
    }

    if (!$items) {
        $productStr = (string)($row['product'] ?? '');
        $segments = preg_split('/\r\n|\r|\n|\||,/', $productStr);
        $segments = array_filter(array_map('trim', $segments), static fn($val) => $val !== '');
        foreach ($segments as $segment) {
            $items[] = [
                'id' => null,
                'name' => $segment,
                'variant' => '',
                'qty' => 1,
                'price' => 0,
            ];
        }
    }

    return $items;
}

function collectThumbRefs(array $orders): array {
    $ids = [];
    $names = [];
    $variantIds = [];
    foreach ($orders as $order) {
        foreach (($order['_items'] ?? []) as $item) {
            if (!empty($item['id'])) {
                $ids[] = (int)$item['id'];
            } elseif (!empty($item['name'])) {
                $names[] = strtolower($item['name']);
            }
            if (!empty($item['variant_id'])) {
                $variantIds[] = (int)$item['variant_id'];
            }
        }
    }
    return [
        'ids' => array_values(array_unique(array_filter($ids, static fn($v) => $v > 0))),
        'names' => array_values(array_unique(array_filter($names, static fn($v) => $v !== ''))),
        'variant_ids' => array_values(array_unique(array_filter($variantIds, static fn($v) => $v > 0))),
    ];
}

function fetchThumbMap(mysqli $ithanhloc, array $orders): array {
    $refs = collectThumbRefs($orders);
    $map = ['byId' => [], 'byName' => [], 'byVariantId' => []];

    if (!empty($refs['ids'])) {
        $safe = implode(',', array_map('intval', $refs['ids']));
        $res = $ithanhloc->query("SELECT id, product_name, image_url FROM ecommerce_product WHERE id IN ($safe)");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map['byId'][(string)$row['id']] = $row['image_url'] ?? '';
            }
        }
    }

    if (!empty($refs['names'])) {
        $placeholders = implode(',', array_fill(0, count($refs['names']), '?'));
        $types = str_repeat('s', count($refs['names']));
        $stmt = $ithanhloc->prepare("SELECT product_name, image_url FROM ecommerce_product WHERE LOWER(product_name) IN ($placeholders)");
        if ($stmt) {
            $params = $refs['names'];
            if (bindParamsDynamic($stmt, $types, $params)) {
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $name = strtolower((string)($row['product_name'] ?? ''));
                    if ($name !== '') {
                        $map['byName'][$name] = $row['image_url'] ?? '';
                    }
                }
            }
            $stmt->close();
        }
    }

    // Nếu có variant_id và bảng biến thể tồn tại, ưu tiên lấy ảnh theo biến thể
    if (!empty($refs['variant_ids']) && tableExists($ithanhloc, 'ecommerce_product_variants')) {
        $safeVar = implode(',', array_map('intval', $refs['variant_ids']));
        $resVar = $ithanhloc->query("SELECT id, image_url FROM ecommerce_product_variants WHERE id IN ($safeVar)");
        if ($resVar) {
            while ($rowVar = $resVar->fetch_assoc()) {
                $vid = (int)($rowVar['id'] ?? 0);
                if ($vid <= 0) continue;
                $map['byVariantId'][(string)$vid] = (string)($rowVar['image_url'] ?? '');
            }
        }
    }

    return $map;
}

function loadInvoiceByOrderId(mysqli $ithanhloc, string $orderId): array {
    $orderId = trim($orderId);
    if ($orderId === '') {
        return [];
    }
    if (!tableExists($ithanhloc, 'ecommerce_order_invoice')) {
        return [];
    }

    $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order_invoice WHERE order_id=? LIMIT 1');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : [];
}

function queryOrders(mysqli $ithanhloc, array $filters): array {
    $userId = ecommerce_current_user_id();
    $status = normalizeFilterStatus($filters['status'] ?? 'all');
    $search = extractSearchTerm($filters['search'] ?? '');
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));
    $page = max(1, (int)($filters['page'] ?? 1));
    $limit = min(20, max(5, (int)($filters['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $colsFlat = listColumns($ithanhloc, 'ecommerce_order');
    $columns = array_fill_keys($colsFlat, true);
    
    $guestOrders = isset($_SESSION['guest_orders']) ? (array)$_SESSION['guest_orders'] : [];
    if ($userId <= 0 && empty($guestOrders)) {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => 0,
            'total_all' => 0,
            'has_more' => false,
            'data' => [],
        ];
    }

    $where = ['user_id = ?'];
    $params = [$userId];
    $types = 's';
    if ($userId <= 0) {
        $placeholders = implode(',', array_fill(0, count($guestOrders), '?'));
        $where = ["user_id=0 AND order_id IN ($placeholders)"];
        $params = $guestOrders;
        $types = str_repeat('s', count($guestOrders));
    }

    if ($search !== '') {
        $like = "%{$search}%";
        $orParts = ['order_id LIKE ?', 'user_name LIKE ?'];
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
        foreach (['phone', 'email', 'note'] as $col) {
            if (isset($columns[$col])) {
                $orParts[] = "$col LIKE ?";
                $params[] = $like;
                $types .= 's';
            }
        }
        foreach (['products_json', 'product'] as $col) {
            if (isset($columns[$col])) {
                $orParts[] = "$col LIKE ?";
                $params[] = $like;
                $types .= 's';
            }
        }
        $where[] = '(' . implode(' OR ', $orParts) . ')';
    }

    if ($from !== '') {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $from;
        $types .= 's';
    }
    if ($to !== '') {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $to;
        $types .= 's';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Tổng tất cả đơn của user (không áp bộ lọc)
    $stmtAll = $ithanhloc->prepare('SELECT COUNT(*) AS c FROM ecommerce_order WHERE user_id = ?');
    $stmtAll->bind_param('s', $userId);
    $stmtAll->execute();
    $totalAll = (int)($stmtAll->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtAll->close();
    // Lấy toàn bộ đơn theo bộ lọc cơ bản (user + tìm kiếm + ngày),
    // sau đó lọc theo trạng thái bằng normalizeStatusKey để đồng bộ với summary.
    $sqlAll = "SELECT * FROM ecommerce_order $whereSql ORDER BY created_at DESC";
    $stmtAllRows = $ithanhloc->prepare($sqlAll);
    if (!bindParamsDynamic($stmtAllRows, $types, $params)) {
        jOut(['ok' => false, 'msg' => 'Bind failed'], 500);
    }
    $stmtAllRows->execute();
    $resAll = $stmtAllRows->get_result();

    $allRows = [];
    while ($row = $resAll->fetch_assoc()) {
        $row['_normalized_status'] = (string)(ecommerce_order_status_info($row['status'] ?? 'pending')['key'] ?? 'pending');
        $allRows[] = $row;
    }
    $stmtAllRows->close();

    // Áp dụng trạng thái giống logic summary
    $filteredRows = [];
    foreach ($allRows as $row) {
        $norm = (string)($row['_normalized_status'] ?? 'pending');
        $keep = false;
        if ($status === 'all') {
            $keep = true;
        } elseif ($status === 'processing') {
            // Tab "Chờ lấy hàng" gom cả pending và processing
            $keep = in_array($norm, ['pending', 'processing'], true);
        } elseif ($status === 'return_requested') {
            // Tab "Trả hàng" gom cả return_requested, returned và refunded
            $keep = in_array($norm, ['return_requested', 'returned', 'refunded'], true);
        } else {
            $keep = ($norm === $status);
        }
        if ($keep) {
            $filteredRows[] = $row;
        }
    }

    $filtered = count($filteredRows);

    // Phân trang trên mảng đã lọc
    $pageRows = array_slice($filteredRows, $offset, $limit);

    // Chuẩn bị dữ liệu phụ cho các đơn của trang hiện tại
    $orderIds = [];
    foreach ($pageRows as &$row) {
        $row['_items'] = parseProductsFromRow($row);
        $orderIds[] = (string)($row['order_id'] ?? '');
    }
    unset($row);

    $thumbMap = fetchThumbMap($ithanhloc, $pageRows);
    $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, $orderIds);

    $data = [];
    foreach ($pageRows as $row) {
        $data[] = buildOrderPayload($row, $thumbMap, $carrierMap);
    }

    return [
        'page' => $page,
        'limit' => $limit,
        'total' => $filtered,
        'total_all' => $totalAll,
        'has_more' => ($offset + count($data)) < $filtered,
        'data' => $data,
    ];
}

function fetchUserBankAccountsForReturn(?mysqli $ithanhloc, int $userId): array {
    if (!$ithanhloc || $userId <= 0) return [];
    $stmt = $ithanhloc->prepare(
        'SELECT id, type, bank_code, bank_name, bank_branch, account_no, account_last4, account_owner, is_default
         FROM user_bank_accounts WHERE user_id=? AND type="bank" ORDER BY is_default DESC, id DESC'
    );
    if (!$stmt) return [];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return is_array($rows) ? $rows : [];
}

function buildOrderPayload(array $row, array $thumbMap, array $carrierMap = []): array {
    $current = (int)ecommerce_current_user_id();
    $rowUserId = (int)($row['user_id'] ?? 0);
    $isOwner = false;
    if ($current > 0) {
        $isOwner = ($rowUserId === $current);
    } else {
        // Khách vãng lai: đơn guest (user_id=0) được nhận diện qua session guest_orders
        if ($rowUserId === 0) {
            $guestOrders = isset($_SESSION['guest_orders']) ? (array)$_SESSION['guest_orders'] : [];
            $isOwner = in_array($row['order_id'], $guestOrders, true);
        }
    }

    $orderId = (string)($row['order_id'] ?? '');
    $carrier = $carrierMap[$orderId] ?? [];
    $statusRaw = trim((string)($row['status'] ?? ''));
    $status = (string)(ecommerce_order_status_info($statusRaw !== '' ? $statusRaw : ($carrier['mapped_status'] ?? 'pending'))['key'] ?? 'pending');
    $items = [];
    $subtotalGuess = 0;
    $allQty = 0;
    // Tổng số lượng chỉ tính cho sản phẩm trả tiền (không tính quà tặng) để tránh dàn đều giá cho quà
    foreach (($row['_items'] ?? []) as $raw) {
        $isGiftRaw = !empty($raw['is_gift']) || (rawToNumber($raw['price'] ?? 0) <= 0 && (int)($raw['qty'] ?? 1) === 1);
        if ($isGiftRaw) {
            continue;
        }
        $allQty += max(1, (int)($raw['qty'] ?? 1));
    }
    foreach (($row['_items'] ?? []) as $item) {
        $isGift = !empty($item['is_gift']) || (rawToNumber($item['price'] ?? 0) <= 0 && (int)($item['qty'] ?? 1) === 1);
        $isCombo = !empty($item['is_combo']);
        $thumb = '';
        $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : 0;
        // Ưu tiên ảnh theo biến thể nếu có
        if ($variantId > 0 && !empty($thumbMap['byVariantId'][(string)$variantId] ?? '')) {
            $thumb = $thumbMap['byVariantId'][(string)$variantId];
        }
        // Fallback: ảnh theo sản phẩm
        if ($thumb === '' && !empty($item['id'])) {
            $thumb = $thumbMap['byId'][(string)$item['id']] ?? '';
        }
        if ($thumb === '' && !empty($item['name'])) {
            $thumb = $thumbMap['byName'][strtolower($item['name'])] ?? '';
        }
        $qty = max(1, (int)($item['qty'] ?? 1));
        $unitRaw = rawToNumber($item['price'] ?? $item['unit_price'] ?? 0);
        $lineRaw = rawToNumber($item['line_total'] ?? $item['line_total_raw'] ?? $item['total'] ?? $item['subtotal'] ?? $item['amount'] ?? 0);

        if ($isGift) {
            // Quà tặng: giữ giá 0đ, không dàn đều lại theo tổng đơn
            $unitRaw = 0.0;
            $lineRaw = 0.0;
        } else {
            if ($lineRaw <= 0 && $unitRaw > 0) {
                $lineRaw = $unitRaw * $qty;
            }
            if ($unitRaw <= 0 && $lineRaw > 0 && $qty > 0) {
                $unitRaw = $lineRaw / $qty;
            }
            if ($unitRaw <= 0 && $lineRaw <= 0 && $allQty > 0) {
                $totalRaw = rawToNumber($row['total_amount'] ?? ($row['subtotal'] ?? 0));
                if ($totalRaw > 0) {
                    $unitRaw = $totalRaw / $allQty;
                    $lineRaw = $unitRaw * $qty;
                }
            }
        }

        $lineForSubtotal = $isGift ? 0.0 : ($lineRaw > 0 ? $lineRaw : ($unitRaw * $qty));
        $subtotalGuess += $lineForSubtotal;
        $originalPrice = rawToNumber($item['price_original'] ?? $item['original_price'] ?? $item['compare_at_price'] ?? 0);
        if ($originalPrice <= 0 || $originalPrice <= $unitRaw) $originalPrice = 0;
        $items[] = [
            'id' => $item['id'],
            'variant_id' => $variantId,
            'name' => $item['name'],
            'variant' => $item['variant'],
            'qty' => $qty,
            'price' => $unitRaw,
            'price_fmt' => fmtMoney($unitRaw),
            'original_price' => $originalPrice,
            'original_price_fmt' => $originalPrice > 0 ? fmtMoney($originalPrice) : '',
            'line_total' => $lineRaw,
            'line_total_fmt' => fmtMoney($lineRaw),
            'thumb' => $thumb,
            'is_gift' => $isGift ? 1 : 0,
            'is_combo' => $isCombo ? 1 : 0,
            'slug' => $item['slug'] ?? $item['product_slug'] ?? '',
            'product_id' => (int)($item['id'] ?? $item['product_id'] ?? 0),
        ];
    }

    $subtotal = rawToNumber($row['subtotal'] ?? $subtotalGuess);
    if ($subtotal <= 0) {
        $subtotal = $subtotalGuess;
    }
    $shipping = rawToNumber($row['shipping_fee'] ?? 0);
    $carrierFee = rawToNumber($carrier['shipping_fee'] ?? 0);
    if ($carrierFee > 0) {
        $shipping = $carrierFee;
    }
    $discount = rawToNumber($row['discount_amount'] ?? 0);
    $grand = rawToNumber($row['total_amount'] ?? ($row['subtotal'] ?? 0));
    if ($grand <= 0) {
        $grand = max(0, $subtotal + $shipping - $discount);
    }

    $paymentMeta = ecommerce_parse_payment_meta($row['payment_meta_json'] ?? '');
    $paymentGateway = strtolower(trim((string)($row['payment_gateway'] ?? '')));
    $paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
    $isMomoPayment = ($paymentGateway === 'momo' || $paymentMethod === 'momo');
    $isVnpayPayment = ($paymentGateway === 'vnpay' || $paymentMethod === 'vnpay');
    $isZalopayPayment = ($paymentGateway === 'zalopay' || $paymentMethod === 'zalopay');
    $paymentExpiresAt = (string)($row['payment_expires_at'] ?? '');
    $paymentExpiresTs = ecommerce_parse_hcm_datetime_to_ts($paymentExpiresAt);
    $paymentViewMeta = [];
    if ($isMomoPayment || $isVnpayPayment) {
        $paymentViewMeta = ecommerce_normalize_momo_meta_view($paymentMeta);
    } elseif ($isZalopayPayment) {
        $raw = is_array($paymentMeta['raw'] ?? null) ? $paymentMeta['raw'] : [];
        $paymentViewMeta = [
            'order_url' => pickFirstNonEmpty([
                $paymentMeta['order_url'] ?? '',
                $paymentMeta['orderUrl'] ?? '',
                $paymentMeta['pay_url'] ?? '',
                $paymentMeta['payUrl'] ?? '',
                $raw['order_url'] ?? '',
                $raw['orderUrl'] ?? '',
                $raw['order_url'] ?? '',
            ]),
            'zp_trans_token' => pickFirstNonEmpty([
                $paymentMeta['zp_trans_token'] ?? '',
                $paymentMeta['zpTransToken'] ?? '',
                $raw['zp_trans_token'] ?? '',
                $raw['zpTransToken'] ?? '',
            ]),
            'created_at' => pickFirstNonEmpty([
                $paymentMeta['created_at'] ?? '',
                $raw['created_at'] ?? '',
            ]),
        ];
    }
    if ($isMomoPayment) {
        $momoExpireTs = (int)($paymentViewMeta['expire_ts'] ?? 0);
        if ($momoExpireTs > 0) {
            $paymentExpiresTs = $momoExpireTs;
        }
    }

    $etaText = (string)($carrier['eta_text'] ?? '');
    $etaLatest = (string)($carrier['eta_latest_text'] ?? '');
    if ($etaText === '' || $etaLatest === '') {
        $tmpEta = tempEtaFromCreated((string)($row['created_at'] ?? ''), 3);
        if ($etaText === '') $etaText = $tmpEta['eta_text'] ?? '';
        if ($etaLatest === '') $etaLatest = $tmpEta['eta_latest'] ?? '';
    }

    // Build display shipping address from order address + shipping snapshot (destination)
    $shippingAddressDisplay = (string)($row['address'] ?? '');
    $shippingSnapshot = [];
    if (!empty($row['shipping_snapshot_json'])) {
        $tmpSnap = json_decode((string)$row['shipping_snapshot_json'], true);
        if (is_array($tmpSnap)) {
            $shippingSnapshot = $tmpSnap;
            $dest = trim((string)($tmpSnap['destination'] ?? ''));
            if ($dest !== '') {
                $shippingAddressDisplay = $dest;
            }
        }
    }

        $createdTs = ecommerce_parse_hcm_datetime_to_ts($row['created_at'] ?? '');
        $isTooOld = (time() - $createdTs) > (3 * 3600);

        // Check if customer already confirmed receiving this order (idempotent flag)
        $customerConfirmed = false;
        $dbConnection = $GLOBALS['ithanhloc'] ?? null;
        if ($dbConnection && tableExists($dbConnection, 'ecommerce_order_log')) {
            $cfStmt = $dbConnection->prepare("SELECT id FROM ecommerce_order_log WHERE order_id=? AND event='customer_confirmed' LIMIT 1");
            if ($cfStmt) {
                $cfStmt->bind_param('s', $orderId);
                $cfStmt->execute();
                $customerConfirmed = (bool)$cfStmt->get_result()->fetch_assoc();
                $cfStmt->close();
            }
        }

        return [
            'order_id' => $orderId,
            'created_at' => $row['created_at'] ?? '',
            'created_human' => !empty($row['created_at']) ? date('H:i d/m/Y', strtotime($row['created_at'])) : '',
            'status' => $status,
            'status_raw' => $statusRaw, // trạng thái gốc (chưa alias) — dùng cho stepper phân biệt 'completed'
            'status_label' => statusLabel($status),
            'items' => $items,
            'items_count' => array_sum(array_map(static fn($it) => $it['qty'], $items)) ?: count($items),
            'customer' => [
                'name' => $row['user_name'] ?? '',
                'phone' => $row['phone'] ?? '',
                'email' => $row['email'] ?? '',
                'address' => $row['address'] ?? '',
            ],
            'shipping' => [
                'carrier' => $carrier['carrier'] ?? ($row['shipping_carrier'] ?? ''),
                'tracking' => $carrier['order_code'] ?? ($row['shipping_tracking'] ?? ''),
                'carrier_status' => $carrier['status'] ?? '',
                'carrier_status_label' => $carrier['status_text'] ?? '',
                'carrier_updated_at' => $carrier['updated_at'] ?? '',
                'carrier_updated_human' => !empty($carrier['updated_at']) ? date('H:i d/m/Y', strtotime((string)$carrier['updated_at'])) : '',
                'carrier_logs' => is_array($carrier['timeline'] ?? null) ? $carrier['timeline'] : [],
                'eta_raw' => $carrier['eta_raw'] ?? '',
                'eta_text' => $etaText,
                'eta_latest_raw' => $carrier['eta_latest_raw'] ?? '',
                'eta_latest' => $etaLatest,
                'fee' => fmtMoney($shipping),
                'destination' => $shippingSnapshot['destination'] ?? '',
                'method_key' => (string)($row['shipping_method'] ?? ($shippingSnapshot['shipping_method'] ?? '')),
                'method_label' => (string)($row['shipping_method_label'] ?? ($shippingSnapshot['shipping_method_text'] ?? '')),
                'required_note' => (string)($carrier['required_note'] ?? ($shippingSnapshot['required_note'] ?? '')),
            ],
            'eta_text' => $etaText,
            'eta_latest' => $etaLatest,
            'payment_method' => $row['payment_method'] ?? '',
            'payment_status' => $row['payment_status'] ?? '',
            'payment_status_label' => trim((string)(ecommerce_payment_info((string)($row['payment_method'] ?? ''), (string)($row['payment_status'] ?? ''), (string)($row['payment_gateway'] ?? ''))['status_label'] ?? '')),
            'payment_gateway' => $row['payment_gateway'] ?? '',
            'payment_expires_at' => $paymentExpiresAt,
            'payment_expires_ts' => $paymentExpiresTs > 0 ? (int)$paymentExpiresTs : 0,
            'payment_expires_human' => $paymentExpiresTs > 0 ? formatHcmDateTime((int)$paymentExpiresTs) : '',
            'payment_meta' => $paymentViewMeta,
            'totals' => [
                'subtotal' => fmtMoney($subtotal),
                'shipping' => fmtMoney($shipping),
                'shipping_discount' => fmtMoney(rawToNumber($row['shipping_discount_amount'] ?? 0)),
                'discount' => fmtMoney($discount),
                'grand_total' => fmtMoney($grand),
            ],
            'raw_totals' => [
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'shipping_discount' => rawToNumber($row['shipping_discount_amount'] ?? 0),
                'discount' => $discount,
                'grand_total' => $grand,
            ],
            'note' => $row['note'] ?? '',
            'shipping_address' => $shippingAddressDisplay,
            'paid_at' => $row['paid_at'] ?? '',
            'paid_at_human' => !empty($row['paid_at']) ? date('H:i d/m/Y', strtotime($row['paid_at'])) : '',
            'actions' => [
                'can_cancel'           => $isOwner && canCustomerCancel($statusRaw),
                'cancel_requested'     => $status === 'cancel_requested',
                'can_confirm'          => $isOwner && canCustomerConfirm($statusRaw) && !$customerConfirmed,
                'customer_confirmed'   => $customerConfirmed,
                'can_return'           => $isOwner && canCustomerReturn($statusRaw),
                'can_edit_address'     => $isOwner && canCustomerEditAddress($statusRaw),
                'can_change_payment'   => $isOwner && $status === 'pending'
                                          && strtolower(trim((string)($row['payment_status'] ?? 'pending'))) !== 'paid'
                                          && strtolower(trim((string)($row['payment_status'] ?? 'pending'))) !== 'expired'
                                          && !isOrderPaymentExpired($row),
            ],
            'enabled_payment_methods' => function_exists('ecommerce_get_enabled_payment_methods')
                ? ecommerce_get_enabled_payment_methods() : [['key' => 'cod', 'label' => 'Thanh toán khi nhận hàng']],
            'bank_accounts' => fetchUserBankAccountsForReturn($GLOBALS['ithanhloc'] ?? null, (int)($row['user_id'] ?? 0)),
            'return_reasons' => [
                'Thiếu hàng', 'Gửi sai hàng', 'Hàng bể vỡ', 'Hàng lỗi, không hoạt động',
                'Hàng hết hạn sử dụng', 'Khác với mô tả', 'Hàng giả/Hàng nhái',
            ],
            'shipping_address_detail' => [
                'recipient_name' => (string)($shippingSnapshot['recipient_name'] ?? $row['user_name'] ?? ''),
                'contact_phone'  => (string)($shippingSnapshot['contact_phone'] ?? $row['phone'] ?? ''),
                'street'         => (string)($shippingSnapshot['street'] ?? ''),
                'ward'           => (string)($shippingSnapshot['ward'] ?? ''),
                'ward_code'      => (string)($shippingSnapshot['ward_code'] ?? ''),
                'district'       => (string)($shippingSnapshot['district'] ?? ''),
                'district_id'    => (int)($shippingSnapshot['district_id'] ?? 0),
                'province'       => (string)($shippingSnapshot['province'] ?? ''),
                'province_id'    => (int)($shippingSnapshot['province_id'] ?? 0),
            ],
        ];
}



function requiredOrderId(): string {
    $id = trim((string)($_REQUEST['order_id'] ?? $_REQUEST['id'] ?? ''));
    if ($id === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn hàng'], 400);
    }
    return $id;
}

function ensureOwnership(mysqli $ithanhloc, string $orderId, bool $allowPublicView = false): array {
    $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE order_id=? LIMIT 1');
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng'], 404);
    }
    if ($allowPublicView) {
        return $row;
    }
    $current = (int)ecommerce_current_user_id();
    $rowUserId = (int)($row['user_id'] ?? 0);

    // Nếu người dùng đã đăng nhập: chỉ được thao tác trên đơn của chính mình
    if ($current > 0) {
        if ($rowUserId !== $current) {
            jOut(['ok' => false, 'msg' => 'Không có quyền thao tác'], 403);
        }
        return $row;
    }

    // Khách vãng lai (không có user_id trong session): chỉ được thao tác trên đơn guest thuộc session này
    if ($rowUserId > 0) {
        jOut(['ok' => false, 'msg' => 'Không có quyền thao tác'], 403);
    }
    $guestOrders = isset($_SESSION['guest_orders']) ? (array)$_SESSION['guest_orders'] : [];
    if (!in_array($orderId, $guestOrders, true)) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng trong phiên làm việc này'], 403);
    }
    return $row;
}

function handleListRequest(mysqli $ithanhloc): void {
    $filters = [
        'status' => $_GET['status'] ?? $_GET['tab'] ?? 'all',
        'search' => $_GET['search'] ?? '',
        'from' => $_GET['from'] ?? '',
        'to' => $_GET['to'] ?? '',
        'page' => intval($_GET['page'] ?? 1),
        'limit' => intval($_GET['limit'] ?? 10),
    ];
    $result = queryOrders($ithanhloc, $filters);
    $result['ok'] = true;
    jOut($result);
}


function handleSummaryRequest(mysqli $ithanhloc): void {
    $uid = intval(ecommerce_current_user_id());
    $keys = summaryKeys();
    $list = [];
    
    // Khởi tạo map
    $map = array_fill_keys($keys, 0);
    $map['all'] = 0;

    $guestOrders = isset($_SESSION['guest_orders']) ? (array)$_SESSION['guest_orders'] : [];
    if ($uid <= 0 && empty($guestOrders)) {
        jOut(['ok' => true, 'list' => $list, 'map' => $map]);
    }

    $where = 'user_id=?';
    $params = [$uid];
    $types = 'i';
    if ($uid <= 0) {
        $placeholders = implode(',', array_fill(0, count($guestOrders), '?'));
        $where = "user_id=0 AND order_id IN ($placeholders)";
        $params = $guestOrders;
        $types = str_repeat('s', count($guestOrders));
    }

    $sql = "SELECT status, COUNT(*) AS c FROM ecommerce_order WHERE $where GROUP BY status";
    if ($stmt = $ithanhloc->prepare($sql)) {
        if (!bindParamsDynamic($stmt, $types, $params)) {
             $stmt->close();
             jOut(['ok' => false, 'msg' => 'Lỗi bind tham số']);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rawStatus = (string)($row['status'] ?? 'pending');
            $norm = (string)(ecommerce_order_status_info($rawStatus)['key'] ?? 'pending');
            $count = (int)$row['c'];
            
            $map[$norm] = ($map[$norm] ?? 0) + $count;
            $map['all'] += $count;
        }
        $stmt->close();
    }

    foreach ($keys as $key) {
        $info = ecommerce_order_status_info($key);
        $list[] = [
            'key' => $key,
            'label' => $info['label'] ?? ucfirst($key),
            'count' => $map[$key] ?? 0
        ];
    }

    jOut(['ok' => true, 'list' => $list, 'map' => $map]);
}


function buildTimelineFromColumns(array $row): array {
    $actorLabels = [
        'admin'    => 'Hệ thống',
        'customer' => 'Khách hàng',
        'system'   => 'Hệ thống',
        'carrier'  => 'Vận chuyển',
    ];
    $steps = [
        ['label' => 'Tạo đơn hàng',                  'time' => $row['created_at'] ?? null],
        ['label' => 'Đã giao cho đơn vị vận chuyển',  'time' => $row['shipped_at'] ?? null],
        ['label' => 'Đã giao hàng',                   'time' => $row['delivered_at'] ?? null],
        ['label' => 'Yêu cầu trả hàng',               'time' => $row['return_requested_at'] ?? null],
        ['label' => 'Hoàn tất trả hàng',              'time' => $row['return_resolved_at'] ?? null],
        ['label' => 'Đã hủy',                         'time' => $row['canceled_at'] ?? null],
    ];
    $timeline = [];
    foreach ($steps as $step) {
        if (!$step['time']) continue;
        $timeline[] = [
            'label'      => $step['label'],
            'time'       => $step['time'],
            'time_human' => date('H:i d/m/Y', strtotime($step['time'])),
            'actor'      => '',
        ];
    }
    return $timeline;
}

function buildTimeline(array $row, ?mysqli $ithanhloc = null): array {
    $orderId = trim((string)($row['order_id'] ?? ''));

    // Đọc từ ecommerce_order_log nếu có kết nối DB
    if ($ithanhloc && $orderId !== '') {
        $logs = ecommerce_order_log_fetch($ithanhloc, $orderId);
        if (!empty($logs)) {
            $statusLabels = [
                'pending'          => 'Chờ xác nhận',
                'processing'       => 'Đã xác nhận — Đang chuẩn bị hàng',
                'shipping'         => 'Đang giao hàng',
                'delivered'        => 'Đã giao hàng thành công',
                'cancel_requested' => 'Yêu cầu hủy đơn — Chờ xét duyệt',
                'canceled'         => 'Đơn hàng đã hủy',
                'return_requested' => 'Yêu cầu trả hàng — Chờ xét duyệt',
                'returned'         => 'Đã hoàn trả hàng',
                'refunded'         => 'Đã hoàn tiền',
            ];
            $actorLabels = [
                'admin'    => 'Admin',
                'customer' => 'Khách hàng',
                'system'   => 'Hệ thống',
                'carrier'  => 'Đơn vị vận chuyển',
            ];
            $timeline = [];
            // Thêm bước "Tạo đơn" từ created_at của đơn hàng
            if (!empty($row['created_at'])) {
                $timeline[] = [
                    'label'      => 'Tạo đơn hàng',
                    'time'       => $row['created_at'],
                    'time_human' => date('H:i d/m/Y', strtotime($row['created_at'])),
                    'actor'      => 'Khách hàng',
                    'note'       => '',
                ];
            }
            // Ẩn các event nội bộ khỏi timeline của khách hàng
            $hiddenEvents = ['stock_restored'];
            $eventLabels = [
                'return_approved'      => 'Đã duyệt yêu cầu trả hàng',
                'return_rejected'      => 'Yêu cầu trả hàng bị từ chối',
                'return_received'      => 'Shop đã nhận lại hàng hoàn',
                'return_inspected'     => 'Shop đã kiểm tra hàng hoàn',
                'cancel_approved'      => 'Đã duyệt yêu cầu hủy',
                'cancel_rejected'      => 'Yêu cầu hủy bị từ chối',
                'address_updated'      => 'Cập nhật địa chỉ nhận hàng',
                'shipping_updated'     => 'Cập nhật vận chuyển',
                'carrier_updated'      => 'Cập nhật từ đơn vị vận chuyển',
                'payment_updated'      => 'Cập nhật thanh toán',
                'refund_completed'     => 'Đã hoàn tiền',
                'admin_note_updated'   => 'Ghi chú nội bộ',
            ];
            foreach ($logs as $log) {
                $event      = (string)($log['event'] ?? '');
                if (in_array($event, $hiddenEvents, true)) continue;
                $statusTo   = (string)($log['status_to'] ?? '');
                $statusFrom = (string)($log['status_from'] ?? '');
                $isStatusChange = ($event === 'status_changed') || ($statusTo !== '' && $statusTo !== $statusFrom);
                // Ưu tiên: nhãn sự kiện cụ thể → nhãn trạng thái → fallback
                if ($event !== '' && isset($eventLabels[$event])) {
                    $label = $eventLabels[$event];
                } elseif ($isStatusChange && isset($statusLabels[$statusTo])) {
                    $label = $statusLabels[$statusTo];
                } elseif ($statusTo !== '' && isset($statusLabels[$statusTo])) {
                    $label = $statusLabels[$statusTo];
                } else {
                    $label = 'Cập nhật đơn hàng';
                }
                $actorType  = (string)($log['actor_type'] ?? 'system');
                $actor      = $actorLabels[$actorType] ?? $actorType;
                $note       = (string)($log['note'] ?? '');
                $note = preg_replace('/^(Admin|Khách|Khách hàng)\s+(đổi địa chỉ:\s*)/iu', '', $note);
                $note = preg_replace('/^(Admin|Khách|Khách hàng)\s+(duyệt hủy|từ chối hủy|duyệt yêu cầu hủy|từ chối yêu cầu hủy|duyệt yêu cầu trả hàng|từ chối yêu cầu trả hàng|xác nhận đã hoàn tiền)/iu', '$2', $note);
                if ($note !== '') {
                    $note = mb_strtoupper(mb_substr($note, 0, 1)) . mb_substr($note, 1);
                }
                $timeline[] = [
                    'label'      => $label,
                    'time'       => $log['created_at'],
                    'time_human' => date('H:i d/m/Y', strtotime((string)$log['created_at'])),
                    'actor'      => $actor,
                    'note'       => $note,
                ];
            }
            return $timeline;
        }
    }

    // Fallback: đọc từ timestamp columns (đơn hàng cũ chưa có log)
    return buildTimelineFromColumns($row);
}

function handleDetailRequest(mysqli $ithanhloc): void {
    $orderId = requiredOrderId();
    $row = ensureOwnership($ithanhloc, $orderId, true);
    $row['_items'] = parseProductsFromRow($row);
    $thumbMap = fetchThumbMap($ithanhloc, [$row]);
    $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, [$orderId]);
    $payload = buildOrderPayload($row, $thumbMap, $carrierMap);

    // Thông tin hoá đơn (nếu có)
    $invoiceRow = loadInvoiceByOrderId($ithanhloc, $orderId);
    $payload['invoice'] = [
        'has_invoice' => !empty($invoiceRow),
        'invoice_type' => (string)($invoiceRow['invoice_type'] ?? ''),
        'buyer_name' => (string)($invoiceRow['buyer_name'] ?? ''),
        'company_name' => (string)($invoiceRow['company_name'] ?? ''),
        'tax_code' => (string)($invoiceRow['tax_code'] ?? ''),
        'address' => (string)($invoiceRow['address'] ?? ''),
        'email' => (string)($invoiceRow['email'] ?? ''),
        'created_at' => (string)($invoiceRow['created_at'] ?? ''),
    ];
    // Timeline đọc hoàn toàn từ ecommerce_order_log (nguồn sự thật duy nhất)
    // GHN carrier logs đã được ghi vào ecommerce_order_log bởi admin khi sync
    $payload['timeline'] = buildTimeline($row, $ithanhloc);
    jOut(['ok' => true, 'data' => $payload]);
}

function handleSyncPaymentRequest(mysqli $ithanhloc): void {
    $orderId = requiredOrderId();
    $row = ensureOwnership($ithanhloc, $orderId);

    if (isOrderPaymentExpired($row)) {
        markOrderExpiredCancel($ithanhloc, $row);
        $row = ensureOwnership($ithanhloc, $orderId);
    }

    $method = strtolower(trim((string)($row['payment_method'] ?? '')));
    if ($method !== 'momo') {
        $row['_items'] = parseProductsFromRow($row);
        $thumbMap = fetchThumbMap($ithanhloc, [$row]);
        $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, [$orderId]);
        $payload = buildOrderPayload($row, $thumbMap, $carrierMap);
        jOut(['ok' => true, 'data' => $payload, 'sync' => ['skipped' => true, 'reason' => 'not_momo']]);
    }

    $status = (string)(ecommerce_order_status_info($row['status'] ?? 'pending')['key'] ?? 'pending');
    $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? 'pending')));
    if ($status !== 'pending' || $paymentStatus === 'paid') {
        $row['_items'] = parseProductsFromRow($row);
        $thumbMap = fetchThumbMap($ithanhloc, [$row]);
        $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, [$orderId]);
        $payload = buildOrderPayload($row, $thumbMap, $carrierMap);
        jOut(['ok' => true, 'data' => $payload, 'sync' => ['skipped' => true, 'reason' => 'already_final']]);
    }

    $meta = ecommerce_parse_payment_meta($row['payment_meta_json'] ?? '');
    $requestId = trim((string)($meta['requestId'] ?? ($row['gateway_tran_no'] ?? '')));
    if ($requestId === '') {
        $row['_items'] = parseProductsFromRow($row);
        $thumbMap = fetchThumbMap($ithanhloc, [$row]);
        $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, [$orderId]);
        $payload = buildOrderPayload($row, $thumbMap, $carrierMap);
        jOut(['ok' => true, 'data' => $payload, 'sync' => ['ok' => false, 'msg' => 'Thiáº¿u requestId MoMo']]);
    }

    $momoOrderId = trim((string)($meta['momo_order_id'] ?? ($meta['orderId'] ?? $orderId)));
    $query = momoQueryOrderStatus($momoOrderId, $requestId);
    if (!($query['ok'] ?? false)) {
        $row['_items'] = parseProductsFromRow($row);
        $thumbMap = fetchThumbMap($ithanhloc, [$row]);
        $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, [$orderId]);
        $payload = buildOrderPayload($row, $thumbMap, $carrierMap);
        jOut(['ok' => true, 'data' => $payload, 'sync' => ['ok' => false, 'msg' => (string)($query['msg'] ?? 'Không kiểm tra được MoMo')]]);
    }

    $result = is_array($query['data'] ?? null) ? $query['data'] : [];
    $resultCode = (int)($result['resultCode'] ?? -1);
    $message = trim((string)($result['message'] ?? ''));
    $transId = trim((string)($result['transId'] ?? ''));
    $expireTs = parseFlexibleTs(
        $result['expiredTime']
        ?? $result['expireTime']
        ?? $result['expiresAt']
        ?? $result['expire_at']
        ?? null
    );

    $meta['last_result_code'] = (string)$resultCode;
    $meta['last_message'] = $message;
    $meta['last_checked_at'] = date('Y-m-d H:i:s');
    $meta['last_raw'] = $result;
    if ($expireTs > 0) {
        $meta['expire_ts'] = $expireTs;
        $meta['expiredTime'] = $expireTs;
    }

    $cols = listOrderColumns($ithanhloc);
    $set = [];
    $vals = [];
    $types = '';

    if (isset($cols['payment_meta_json'])) {
        $set[] = 'payment_meta_json=?';
        $vals[] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $types .= 's';
    }
    if (isset($cols['payment_response_code'])) {
        $set[] = 'payment_response_code=?';
        $vals[] = (string)$resultCode;
        $types .= 's';
    }
    if ($expireTs > 0 && isset($cols['payment_expires_at'])) {
        try {
            $expAt = (new DateTimeImmutable('@' . $expireTs))
                ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $expAt = date('Y-m-d H:i:s', $expireTs);
        }
        $set[] = 'payment_expires_at=?';
        $vals[] = $expAt;
        $types .= 's';
    }

    if ($resultCode === 0) {
        if (isset($cols['payment_status'])) { $set[] = 'payment_status=?'; $vals[] = 'paid'; $types .= 's'; }
        if (isset($cols['status'])) { $set[] = 'status=?'; $vals[] = 'processing'; $types .= 's'; }
        if (isset($cols['paid_at'])) { $set[] = 'paid_at=COALESCE(paid_at, NOW())'; }
    } else {
        $newPayStatus = 'pending';
        // MoMo failure codes: 1006 (cancelled), 1001 (expired), 1002 (system maintenance), etc.
        // We treat these as 'failed' so the UI knows to refresh the session automatically.
        if (in_array($resultCode, [1006, 1001, 1002, 1007, 49])) {
            $newPayStatus = 'failed';
        }
        if (isset($cols['payment_status'])) { $set[] = 'payment_status=?'; $vals[] = $newPayStatus; $types .= 's'; }
        if (isset($cols['status'])) { $set[] = 'status=?'; $vals[] = 'pending'; $types .= 's'; }
    }

    if ($transId !== '' && isset($cols['bank_tran_no'])) {
        $set[] = 'bank_tran_no=?';
        $vals[] = $transId;
        $types .= 's';
    }
    if (isset($cols['updated_at'])) {
        $set[] = 'updated_at=NOW()';
    }

    if ($set) {
        $sql = 'UPDATE ecommerce_order SET ' . implode(', ', $set) . ' WHERE order_id=? LIMIT 1';
        $stmt = $ithanhloc->prepare($sql);
        if ($stmt) {
            $vals[] = $orderId;
            $types .= 's';
            if (bindParamsDynamic($stmt, $types, $vals)) {
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $row = ensureOwnership($ithanhloc, $orderId);
    if (isOrderPaymentExpired($row)) {
        markOrderExpiredCancel($ithanhloc, $row);
        $row = ensureOwnership($ithanhloc, $orderId);
    }

    $row['_items'] = parseProductsFromRow($row);
    $thumbMap = fetchThumbMap($ithanhloc, [$row]);
    $carrierMap = fetchCarrierMapBySystemOrders($ithanhloc, [$orderId]);
    $payload = buildOrderPayload($row, $thumbMap, $carrierMap);
    jOut([
        'ok' => true,
        'data' => $payload,
        'sync' => [
            'ok' => true,
            'result_code' => $resultCode,
            'message' => $message,
            'paid' => $resultCode === 0,
        ],
    ]);
}

function handleChangePaymentRequest(mysqli $ithanhloc): void {
    global $company_bank_content_text;
    $orderId = requiredOrderId();
    $method = strtolower(trim((string)($_REQUEST['payment_method'] ?? $_REQUEST['method'] ?? '')));
    if (!in_array($method, ['cod', 'momo', 'vnpay', 'zalopay'], true)) {
        jOut(['ok' => false, 'msg' => 'Phương thức thanh toán không hợp lệ'], 400);
    }

    $row = ensureOwnership($ithanhloc, $orderId);
    // Nếu đơn đã được đánh dấu hết hạn thanh toán thì khoá luôn mọi thao tác thanh toán/đổi PTTT
    if (strtolower(trim((string)($row['payment_status'] ?? ''))) === 'expired') {
        jOut(['ok' => false, 'msg' => 'Đơn đã hết hạn thanh toán'], 400);
    }
    if (isOrderPaymentExpired($row)) {
        markOrderExpiredCancel($ithanhloc, $row);
        jOut(['ok' => false, 'msg' => 'Đơn đã hết hạn thanh toán'], 400);
    }

    $status = (string)(ecommerce_order_status_info($row['status'] ?? 'pending')['key'] ?? 'pending');
    $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? 'pending')));
    if ($status !== 'pending' || $paymentStatus === 'paid') {
        jOut(['ok' => false, 'msg' => 'Đơn không còn ở trạng thái chờ thanh toán'], 400);
    }

    $amount = rawToNumber($row['total_amount'] ?? ($row['subtotal'] ?? 0));
    if ($amount <= 0) {
        jOut(['ok' => false, 'msg' => 'Số tiền đơn không hợp lệ'], 400);
    }

    $cols = listOrderColumns($ithanhloc);
    $set = [];
    $vals = [];
    $types = '';

    if (isset($cols['payment_method'])) { $set[] = 'payment_method=?'; $vals[] = $method; $types .= 's'; }
    if (isset($cols['payment_gateway'])) {
        $gateway = ($method === 'zalopay') ? 'zalopay' : (($method === 'cod') ? '' : $method);
        $set[] = 'payment_gateway=?';
        $vals[] = $gateway;
        $types .= 's';
    }
    if (isset($cols['payment_status'])) {
        $set[] = 'payment_status=?';
        // payment_status is a status (paid/pending/failed/expired/unpaid). COD is a method, so use unpaid.
        $vals[] = ($method === 'cod') ? 'unpaid' : 'pending';
        $types .= 's';
    }
    if (isset($cols['payment_ref'])) { $set[] = 'payment_ref=?'; $vals[] = $orderId; $types .= 's'; }
    if (isset($cols['updated_at'])) { $set[] = 'updated_at=NOW()'; }
    if (isset($cols['payment_expires_at']) && $method !== 'cod') {
        try {
            $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
            $nowExp = new DateTimeImmutable('now', $tz);
            if ($method === 'momo') {
                $exp = $nowExp->modify('+15 minutes')->format('Y-m-d H:i:s');
            } elseif ($method === 'vnpay') {
                $exp = $nowExp->modify('+30 minutes')->format('Y-m-d H:i:s');
            } else {
                $exp = $nowExp->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            }
        } catch (Throwable $e) {
            $exp = date('Y-m-d H:i:s', time() + (($method === 'vnpay') ? 1800 : 900));
        }
        $set[] = 'payment_expires_at=?';
        $vals[] = $exp;
        $types .= 's';
    }

    $response = ['ok' => true, 'order_id' => $orderId, 'payment_method' => $method];

    // Nếu khách chọn COD thì chỉ cần cập nhật lại phương thức,
    // không gọi cổng thanh toán bên ngoài.
    if ($method === 'cod') {
        if (isset($cols['payment_meta_json'])) {
            $set[] = 'payment_meta_json=?';
            $vals[] = json_encode(['gateway' => 'cod', 'created_at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
            $types .= 's';
        }
        if (isset($cols['gateway_tran_no'])) {
            $set[] = 'gateway_tran_no=?';
            $vals[] = '';
            $types .= 's';
        }
    }

    if ($method === 'vnpay') {
        global $ECOMMERCE_PAYMENT_METHODS, $baseUrl;
        $cfg = is_array($ECOMMERCE_PAYMENT_METHODS['vnpay'] ?? null) ? $ECOMMERCE_PAYMENT_METHODS['vnpay'] : [];
        if (empty($cfg['enabled'])) {
            jOut(['ok' => false, 'msg' => 'VNPAY chưa được bật cấu hình'], 400);
        }
        $tmnCode = (string)($cfg['tmnCode'] ?? '');
        $hashSecret = (string)($cfg['hashSecret'] ?? '');
        $returnUrl = (string)($cfg['returnUrl'] ?? '');
        if ($returnUrl === '' || stripos($returnUrl, 'order-done') !== false) {
            if ($baseUrl !== '') {
                $returnUrl = $baseUrl . '/order-confirm';
            }
        }
        $vnpUrl = (string)($cfg['payUrl'] ?? '');
        if ($vnpUrl === '') {
            $vnpUrl = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
        }
        if ($tmnCode === '' || $hashSecret === '' || $returnUrl === '') {
            jOut(['ok' => false, 'msg' => 'Thiếu cấu hình VNPAY'], 400);
        }

        $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $createDate = $now->format('YmdHis');
        $expireDate = $now->modify('+30 minutes')->format('YmdHis');
        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => (int)round($amount * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $orderId,
            'vnp_OrderInfo' => ($company_bank_content_text . ' ' . $orderId),
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => $ipAddr,
            'vnp_CreateDate' => $createDate,
            'vnp_ExpireDate' => $expireDate,
        ];
        $paymentUrl = buildVnpayUrl($inputData, $hashSecret, $vnpUrl);

        if (isset($cols['payment_meta_json'])) {
            $meta = [
                'gateway' => 'vnpay',
                'pay_url' => $paymentUrl,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $set[] = 'payment_meta_json=?';
            $vals[] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $types .= 's';
        }

        $response['payment_url'] = $paymentUrl;
    } elseif ($method === 'zalopay') {
        global $ECOMMERCE_PAYMENT_METHODS, $baseUrl;
        $cfg = is_array($ECOMMERCE_PAYMENT_METHODS['zalopay'] ?? null) ? $ECOMMERCE_PAYMENT_METHODS['zalopay'] : [];
        $appId = (int)($cfg['app_id'] ?? 0);
        $key1 = (string)($cfg['key1'] ?? '');
        $createUrl = (string)($cfg['createUrl'] ?? '');
        $callbackUrl = (string)($cfg['callbackUrl'] ?? '');
        $redirectUrl = (string)($cfg['redirectUrl'] ?? '');

        if ($appId <= 0 || $key1 === '' || $createUrl === '') {
            jOut(['ok' => false, 'msg' => 'Đang gặp sự cố với cổng thanh toán ZaloPay, vui lòng chọn phương thức khác hoặc liên hệ cửa hàng'], 400);
        }

        $uidInt = (int)($row['user_id'] ?? 0);
        $nowMs = (int)round(microtime(true) * 1000);
        $today = date('ymd');
        // Tạo app_trans_id duy nhất để mỗi lần đổi PTTT sẽ có 1 phiên thanh toán mới.
        // Format: yymmdd_ORDERID_suffix (order_id nằm ở segment thứ 2 để dễ suy ra lại)
        $suffix = substr((string)$nowMs, -6);
        $appTransId = $today . '_' . $orderId . '_' . $suffix;

        $baseUrlTrim = rtrim((string)($baseUrl ?? ''), '/');
        $defaultRedirect = $baseUrlTrim !== '' ? $baseUrlTrim . '/order-confirm?order_id=' . urlencode($orderId) : '/order-confirm?order_id=' . urlencode($orderId);
        // Nhúng thêm order_id vào embed_data để IPN có thể map chính xác đơn hàng,
        // đồng bộ với logic trong core_admin/zalopay/api.php
        $embedPayload = [
            'order_id' => $orderId,
            'redirecturl' => $redirectUrl !== '' ? $redirectUrl : $defaultRedirect,
        ];
        $embedData = json_encode($embedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $params = [
            'app_id' => $appId,
            'app_user' => (string)($uidInt > 0 ? ('user_' . $uidInt) : 'guest'),
            'app_time' => $nowMs,
            'amount' => (int)round($amount),
            'app_trans_id' => $appTransId,
            'bank_code' => '',
            'embed_data' => $embedData,
            'item' => $item,
            'callback_url' => $callbackUrl,
            'description' => ($company_bank_content_text . ' ' . $orderId),
        ];

        $macData = implode('|', [
            $params['app_id'],
            $params['app_trans_id'],
            $params['app_user'],
            $params['amount'],
            $params['app_time'],
            $params['embed_data'],
            $params['item'],
        ]);
        $params['mac'] = hash_hmac('sha256', $macData, $key1);

        $ch = curl_init($createUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            jOut([
                'ok' => false,
                'msg' => $err !== '' ? $err : 'Không thể kết nối đến ZaloPay',
                'http_code' => $httpCode,
            ], 400);
        }

        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            jOut(['ok' => false, 'msg' => 'Đang gặp sự cố với cổng thanh toán ZaloPay (lỗi phân tích phản hồi)'], 400);
        }
        if ((int)($json['return_code'] ?? 0) !== 1) {
            $msg = (string)($json['return_message'] ?? 'Đang gặp sự cố với cổng thanh toán ZaloPay');
            $subMsg = (string)($json['sub_return_message'] ?? '');
            if ($subMsg !== '' && stripos($msg, $subMsg) === false) {
                $msg .= ' - ' . $subMsg;
            }
            // Chuẩn hoá lại thông báo chung cho người dùng, tránh hiển thị "Giao dịch thất bại" quá chung chung
            if (stripos($msg, 'giao dich that bai') !== false || stripos($msg, 'giao dịch thất bại') !== false) {
                $msg = 'Thanh toán ZaloPay đang gặp lỗi, vui lòng chọn phương thức khác hoặc liên hệ cửa hàng.';
            }
            jOut([
                'ok' => false,
                'msg' => $msg,
                'code' => (int)($json['return_code'] ?? 0),
            ], 400);
        }

        $orderUrl = (string)($json['order_url'] ?? '');
        $zpToken = (string)($json['zp_trans_token'] ?? '');

        if (isset($cols['payment_meta_json'])) {
            $meta = [
                'gateway' => 'zalopay',
                'zp_trans_token' => $zpToken,
                'order_url' => $orderUrl,
                'raw' => $json,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $set[] = 'payment_meta_json=?';
            $vals[] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $types .= 's';
        }
        if (isset($cols['gateway_tran_no'])) {
            $set[] = 'gateway_tran_no=?';
            $vals[] = $zpToken;
            $types .= 's';
        }

        if ($orderUrl !== '') {
            $response['payment_url'] = $orderUrl;
        }
    } elseif ($method === 'momo') {
        $forceNew = !empty($_POST['force_new_session']);
        $momoResult = createMomoQrPayment([
            'order_id' => $orderId,
            'amount' => $amount,
            'order_info' => ($company_bank_content_text . '' . $orderId),
            'extra_data' => '',
            'force_new_session' => $forceNew,
        ]);
        if (!($momoResult['ok'] ?? false)) {
            if (isMomoDuplicateOrderIdError($momoResult)) {
                $oldMeta = ecommerce_normalize_momo_meta_view(ecommerce_parse_payment_meta((string)($row['payment_meta_json'] ?? '')));
                $oldPayUrl = pickFirstNonEmpty([
                    $oldMeta['pay_url'] ?? '',
                    $oldMeta['deeplink'] ?? '',
                ]);
                // Nếu chưa ép buộc phiên mới và có link cũ, thử trả về link cũ
                if (!$forceNew && $oldPayUrl !== '') {
                    jOut([
                        'ok' => true,
                        'order_id' => $orderId,
                        'payment_method' => 'momo',
                        'payment_url' => $oldPayUrl,
                        'reused_previous_payment' => true,
                        'msg' => 'Đơn này đã có yêu cầu MoMo trước đó. Hệ thống sẽ mở lại link thanh toán trước để bạn tiếp tục.',
                    ]);
                }
                
                // TỰ ĐỘNG TẠO PHIÊN MỚI: Nếu không có link cũ hoặc MoMo từ chối link cũ, tự động ép buộc phiên mới (unique orderId)
                $momoResult = createMomoQrPayment([
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'order_info' => ($company_bank_content_text . '' . $orderId),
                    'extra_data' => '',
                    'force_new_session' => true,
                ]);
                if (!($momoResult['ok'] ?? false)) {
                    jOut(['ok' => false, 'msg' => (string)($momoResult['msg'] ?? 'Không tạo được phiên MoMo mới sau lỗi trùng')], 400);
                }
            } else {
                jOut(['ok' => false, 'msg' => (string)($momoResult['msg'] ?? 'Không tạo được yêu cầu thanh toán bằng QR MoMo')], 400);
            }
        }

        // Lưu thông tin thanh toán mới vào đơn hàng
        $meta = ecommerce_parse_payment_meta((string)($row['payment_meta_json'] ?? ''));
        $meta['gateway'] = 'momo';
        $meta['requestId'] = (string)($momoResult['request_id'] ?? '');
        $meta['momo_order_id'] = (string)($momoResult['momo_order_id'] ?? '');
        $meta['payUrl'] = (string)($momoResult['pay_url'] ?? '');
        $meta['qrCodeUrl'] = (string)($momoResult['qr_url'] ?? '');
        $meta['deeplink'] = (string)($momoResult['deeplink'] ?? '');
        $meta['created_at'] = date('Y-m-d H:i:s');
        $meta['raw'] = $momoResult['result'] ?? [];

        $stmtU = $ithanhloc->prepare('UPDATE ecommerce_order SET payment_gateway=?, payment_meta_json=?, payment_status="pending" WHERE id=?');
        if ($stmtU) {
            $mj = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $stmtU->bind_param('ssi', $method, $mj, $row['id']);
            $stmtU->execute();
            $stmtU->close();
        }

        jOut([
            'ok' => true,
            'order_id' => $orderId,
            'payment_method' => 'momo',
            'payment_url' => $meta['payUrl'] ?: $meta['deeplink'],
            'msg' => 'Đã tạo yêu cầu thanh toán MoMo mới.',
        ]);
    }

    if (!$set) {
        jOut(['ok' => false, 'msg' => 'Không thể cập nhật phương thức thanh toán'], 500);
    }

    $sql = 'UPDATE ecommerce_order SET ' . implode(', ', $set) . ' WHERE order_id=? LIMIT 1';
    $stmt = $ithanhloc->prepare($sql);
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không thể cập nhật đơn hàng'], 500);
    }
    $vals[] = $orderId;
    $types .= 's';
    if (!bindParamsDynamic($stmt, $types, $vals)) {
        $stmt->close();
        jOut(['ok' => false, 'msg' => 'Không thể cập nhật đơn hàng'], 500);
    }
    $stmt->execute();
    $stmt->close();

    jOut($response);
}

function handleCancelRequest(mysqli $ithanhloc): void {
    $orderId = requiredOrderId();
    $row = ensureOwnership($ithanhloc, $orderId);
    $statusRaw = trim((string)($row['status'] ?? 'pending'));

    // Chỉ cho phép gửi yêu cầu hủy khi đơn chưa bàn giao vận chuyển (raw để bắt được 'completed')
    if (!canCustomerCancel($statusRaw)) {
        jOut(['ok' => false, 'msg' => 'Không thể yêu cầu hủy ở trạng thái hiện tại. Đơn hàng đã được bàn giao cho đơn vị vận chuyển.'], 400);
    }

    $reason = trim((string)($_REQUEST['reason'] ?? ''));
    if ($reason === '') {
        jOut(['ok' => false, 'msg' => 'Vui lòng cung cấp lý do hủy đơn'], 400);
    }

    $userId = ecommerce_current_user_id();
    $currentNote = trim((string)($row['note'] ?? ''));
    $cancelNote = 'Yêu cầu hủy: ' . $reason;
    $newNote = $currentNote !== '' ? $currentNote . "\n" . $cancelNote : $cancelNote;

    // Chuyển sang cancel_requested để admin duyệt
    $newStatus = 'cancel_requested';
    $stmt = $ithanhloc->prepare('UPDATE ecommerce_order SET status=?, note=?, updated_at=NOW() WHERE order_id=?');
    if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error], 500);
    $stmt->bind_param('sss', $newStatus, $newNote, $orderId);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if ($ok) {
        app_user_log($ithanhloc, (int)$userId, 'order_cancel_request', 'Yêu cầu hủy đơn hàng', [
            'order_id' => $orderId,
            'reason'   => $reason,
        ]);
        ecommerce_order_log_insert($ithanhloc, $orderId, 'customer', (int)$userId,
            'cancel_requested', $statusRaw, 'cancel_requested', $cancelNote);

        // Thông báo cho admin (nếu có hàm notify admin)
        if (function_exists('app_admin_notify')) {
            app_admin_notify($ithanhloc, 'order_cancel_request', [
                'order_id' => $orderId,
                'reason'   => $reason,
            ]);
        }
    }
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã gửi yêu cầu hủy đơn. Vui lòng chờ admin xác nhận.' : $err]);
}

function handleConfirmRequest(mysqli $ithanhloc): void {
    $orderId = requiredOrderId();
    $row = ensureOwnership($ithanhloc, $orderId);
    $statusRaw = trim((string)($row['status'] ?? 'pending'));
    if (!canCustomerConfirm($statusRaw)) {
        jOut(['ok' => false, 'msg' => 'Chỉ xác nhận khi đơn đang giao'], 400);
    }

    // === IDEMPOTENT GUARD: chỉ cho phép xác nhận nhận hàng 1 lần duy nhất ===
    if (function_exists('ecommerce_order_log_ensure_table')) {
        ecommerce_order_log_ensure_table($ithanhloc);
    }
    $chkStmt = $ithanhloc->prepare("SELECT id FROM ecommerce_order_log WHERE order_id=? AND event='customer_confirmed' LIMIT 1");
    if ($chkStmt) {
        $chkStmt->bind_param('s', $orderId);
        $chkStmt->execute();
        $alreadyConfirmed = (bool)$chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();
        if ($alreadyConfirmed) {
            jOut(['ok' => true, 'msg' => 'Bạn đã xác nhận nhận hàng trước đó rồi']);
        }
    }

    $userId = ecommerce_current_user_id();
    $stmt = $ithanhloc->prepare('UPDATE ecommerce_order SET status="delivered", delivered_at=COALESCE(delivered_at,NOW()), updated_at=NOW() WHERE order_id=? AND user_id=?');
    $stmt->bind_param('ss', $orderId, $userId);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if ($ok) {
        syncXuByOrderStatus($ithanhloc, $row, 'delivered');
        // Log trạng thái thay đổi
        ecommerce_order_log_insert($ithanhloc, $orderId, 'customer', (int)$userId,
            'status_changed', $statusRaw, 'delivered', 'Khách xác nhận đã nhận hàng');
        // Log idempotent marker (event='customer_confirmed') → ngăn xác nhận lần 2
        ecommerce_order_log_insert($ithanhloc, $orderId, 'customer', (int)$userId,
            'customer_confirmed', 'delivered', 'delivered', 'Khách xác nhận đã nhận hàng thành công');
        foreach (parseProductsFromRow($row) as $item) {
            $pid = (int)($item['id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                @$ithanhloc->query('UPDATE ecommerce_product SET sold_count = sold_count + ' . $qty . ' WHERE id=' . $pid);
            }
        }
        $link = '/view-order?order_id=' . urlencode($orderId);
        app_user_log($ithanhloc, (int)$userId, 'order_confirm', 'Đã xác nhận nhận hàng', [
            'order_id' => $orderId
        ]);
		app_user_notify_template($ithanhloc, (int)$userId, 'order_delivered_confirmed', [
			'order_id' => $orderId,
			'status' => 'delivered',
			'time' => date('Y-m-d H:i:s'),
			'link' => $link,
			'event' => 'order_delivered_confirmed',
		]);
    }

    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xác nhận nhận hàng' : $err]);
}


function ecommerceOrderReturnEnsureTable(mysqli $ithanhloc): void {
    static $done = false;
    if ($done) return;
    $ithanhloc->query(
        'CREATE TABLE IF NOT EXISTS `ecommerce_order_return` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` VARCHAR(64) NOT NULL,
            `user_id` INT(11) DEFAULT NULL,
            `reason` VARCHAR(120) DEFAULT NULL,
            `bank_account_id` INT(11) DEFAULT NULL,
            `bank_snapshot` TEXT DEFAULT NULL,
            `refund_amount` BIGINT DEFAULT 0,
            `description` TEXT DEFAULT NULL,
            `media_json` TEXT DEFAULT NULL,
            `contact_email` VARCHAR(190) DEFAULT NULL,
            `status` VARCHAR(32) DEFAULT "pending",
            `created_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_order` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

function saveReturnMediaFiles(array $fileInput, int $userId): array {
    global $uploadFolder;
    $folder = $uploadFolder ?? 'uploads';

    // Chuẩn hoá danh sách file (hỗ trợ cả single và multiple)
    $files = [];
    $names = $fileInput['name'] ?? null;
    if (!is_array($names)) {
        if (!empty($fileInput) && (int)($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $files[] = $fileInput;
        }
    } else {
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
    }
    if (!$files) return ['ok' => true, 'files' => []];

    $root = realpath(__DIR__ . '/../../../' . $folder) ?: (__DIR__ . '/../../../' . $folder);
    $fsDir = $root . DIRECTORY_SEPARATOR . 'returns';
    $webDir = $folder . '/returns';
    if (!is_dir($fsDir)) {
        @mkdir($fsDir, 0755, true);
    }
    if (!is_dir($fsDir) || !is_writable($fsDir)) {
        return ['ok' => false, 'msg' => 'Không thể ghi vào thư mục ' . $folder . '/returns'];
    }

    $allowedImages = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $allowedVideos = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    $maxSingle = 20 * 1024 * 1024; // 20MB mỗi file
    $maxTotal  = 20 * 1024 * 1024; // 20MB tổng cộng theo yêu cầu
    $maxFiles  = 8;

    $saved = [];
    $totalSize = 0;
    foreach ($files as $file) {
        if (count($saved) >= $maxFiles) break;
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'msg' => 'Tệp tải lên vượt quá 20MB'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'msg' => 'Tải tệp lên thất bại'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'msg' => 'Tệp tải lên không hợp lệ'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) continue;
        if ($size > $maxSingle) {
            return ['ok' => false, 'msg' => 'Mỗi tệp tối đa 20MB'];
        }
        $totalSize += $size;
        if ($totalSize > $maxTotal) {
            return ['ok' => false, 'msg' => 'Tổng dung lượng tệp đính kèm tối đa 20MB'];
        }

        $mime = '';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
        if ($mime === '') $mime = (string)($file['type'] ?? '');

        if (isset($allowedImages[$mime])) {
            $type = 'image';
            $ext = $allowedImages[$mime];
        } elseif (isset($allowedVideos[$mime])) {
            $type = 'video';
            $ext = $allowedVideos[$mime];
        } else {
            return ['ok' => false, 'msg' => 'Chỉ hỗ trợ ảnh (jpg/png/webp/gif) hoặc video (mp4/webm/mov)'];
        }

        $fileName = 'return_' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destFs = $fsDir . DIRECTORY_SEPARATOR . $fileName;
        if (!@move_uploaded_file($tmp, $destFs)) {
            return ['ok' => false, 'msg' => 'Không thể lưu tệp tải lên'];
        }
        $orderRel = $webDir . '/' . $fileName;
        if (function_exists('media_publish_local_file')) {
            media_publish_local_file($orderRel);
        }
        $saved[] = ['type' => $type, 'url' => $orderRel];
    }

    return ['ok' => true, 'files' => $saved];
}

function handleReturnRequest(mysqli $ithanhloc): void {
    $orderId = requiredOrderId();
    $row = ensureOwnership($ithanhloc, $orderId);
    $statusRaw = trim((string)($row['status'] ?? 'pending'));
    if (!canCustomerReturn($statusRaw)) {
        jOut(['ok' => false, 'msg' => 'Chỉ trả hàng khi đơn đã giao. Đơn đã hoàn thành không thể trả hàng.'], 400);
    }
    $userId = (int)ecommerce_current_user_id();

    // ===== Validate dữ liệu form trả hàng =====
    $allowedReasons = [
        'Thiếu hàng',
        'Gửi sai hàng',
        'Hàng bể vỡ',
        'Hàng lỗi, không hoạt động',
        'Hàng hết hạn sử dụng',
        'Khác với mô tả',
        'Hàng giả/Hàng nhái',
    ];
    $reason = trim((string)($_POST['reason'] ?? $_REQUEST['reason'] ?? ''));
    if ($reason === '' || !in_array($reason, $allowedReasons, true)) {
        jOut(['ok' => false, 'msg' => 'Vui lòng chọn lý do trả hàng hợp lệ'], 400);
    }

    $bankAccountId = (int)($_POST['bank_account_id'] ?? 0);
    $bankSnapshot = '';

    if ($bankAccountId > 0) {
        // Khách đã đăng nhập → chọn tài khoản từ user_bank_accounts
        $stmtB = $ithanhloc->prepare('SELECT id, type, bank_code, bank_name, bank_branch, account_no, account_last4, account_owner FROM user_bank_accounts WHERE id=? AND user_id=? LIMIT 1');
        if ($stmtB) {
            $stmtB->bind_param('ii', $bankAccountId, $userId);
            $stmtB->execute();
            $bankRow = $stmtB->get_result()->fetch_assoc();
            $stmtB->close();
            if (!$bankRow) {
                jOut(['ok' => false, 'msg' => 'Tài khoản ngân hàng không hợp lệ'], 400);
            }
            $bankSnapshot = json_encode($bankRow, JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Khách vãng lai → nhập thông tin ngân hàng thủ công
        $manualBankName  = trim((string)($_POST['manual_bank_name'] ?? ''));
        $manualBankCode  = trim((string)($_POST['manual_bank_code'] ?? ''));
        $manualAccountNo = preg_replace('/\s+/', '', (string)($_POST['manual_account_no'] ?? ''));
        $manualOwner     = trim((string)($_POST['manual_account_owner'] ?? ''));
        $manualBranch    = trim((string)($_POST['manual_bank_branch'] ?? ''));

        if ($manualBankName === '' && $manualBankCode === '') {
            jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên ngân hàng nhận tiền hoàn'], 400);
        }
        if ($manualAccountNo === '' || !preg_match('/^[0-9]{6,20}$/', $manualAccountNo)) {
            jOut(['ok' => false, 'msg' => 'Số tài khoản không hợp lệ (chỉ chứa 6-20 chữ số)'], 400);
        }
        if ($manualOwner === '' || mb_strlen($manualOwner, 'UTF-8') < 3) {
            jOut(['ok' => false, 'msg' => 'Vui lòng nhập chủ tài khoản'], 400);
        }

        $bankRow = [
            'id'             => 0,
            'type'           => 'bank',
            'bank_code'      => mb_substr($manualBankCode, 0, 32, 'UTF-8'),
            'bank_name'      => mb_substr($manualBankName, 0, 120, 'UTF-8'),
            'bank_branch'    => mb_substr($manualBranch, 0, 190, 'UTF-8'),
            'account_no'     => $manualAccountNo,
            'account_last4'  => substr($manualAccountNo, -4),
            'account_owner'  => mb_strtoupper(mb_substr($manualOwner, 0, 120, 'UTF-8'), 'UTF-8'),
            'source'         => 'manual_guest',
        ];
        $bankSnapshot = json_encode($bankRow, JSON_UNESCAPED_UNICODE);
    }

    $refundAmount = (int)round((float)preg_replace('/[^0-9.]/', '', (string)($_POST['refund_amount'] ?? '0')));
    $orderGrand = (int)round((float)($row['total_amount'] ?? 0));
    if ($refundAmount < 0) $refundAmount = 0;
    if ($orderGrand > 0 && $refundAmount > $orderGrand) {
        jOut(['ok' => false, 'msg' => 'Số tiền hoàn không được vượt quá tổng giá trị đơn hàng'], 400);
    }

    $description = trim((string)($_POST['description'] ?? ''));
    if (mb_strlen($description, 'UTF-8') > 2000) {
        $description = mb_substr($description, 0, 2000, 'UTF-8');
    }

    $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        jOut(['ok' => false, 'msg' => 'Email liên hệ không hợp lệ'], 400);
    }
    if ($contactEmail === '') {
        $contactEmail = trim((string)($row['email'] ?? ''));
    }

    // ===== Lưu media (nếu có) =====
    $mediaJson = '[]';
    if (!empty($_FILES['media'])) {
        $mediaRes = saveReturnMediaFiles($_FILES['media'], $userId);
        if (!($mediaRes['ok'] ?? false)) {
            jOut(['ok' => false, 'msg' => (string)($mediaRes['msg'] ?? 'Không thể lưu tệp đính kèm')], 400);
        }
        $mediaJson = json_encode($mediaRes['files'] ?? [], JSON_UNESCAPED_UNICODE);
    }

    // ===== Ghi bản ghi yêu cầu trả hàng =====
    ecommerceOrderReturnEnsureTable($ithanhloc);
    $now = date('Y-m-d H:i:s');
    $stmtR = $ithanhloc->prepare(
        'INSERT INTO ecommerce_order_return
            (order_id, user_id, reason, bank_account_id, bank_snapshot, refund_amount, description, media_json, contact_email, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)'
    );
    if (!$stmtR) {
        jOut(['ok' => false, 'msg' => 'Không thể tạo yêu cầu trả hàng'], 500);
    }
    $stmtR->bind_param(
        'sisisissss',
        $orderId, $userId, $reason, $bankAccountId, $bankSnapshot,
        $refundAmount, $description, $mediaJson, $contactEmail, $now
    );
    $okR = $stmtR->execute();
    $stmtR->close();
    if (!$okR) {
        jOut(['ok' => false, 'msg' => 'Không thể lưu yêu cầu trả hàng'], 500);
    }

    // ===== Cập nhật trạng thái đơn =====
    // Note dùng dấu xuống dòng để FE render từng dòng riêng biệt (white-space: pre-line)
    $noteLines = [];
    $noteLines[] = 'Lý do: ' . $reason;
    $noteLines[] = 'Số tiền hoàn: ' . number_format($refundAmount, 0, ',', '.') . 'đ';
    if ($description !== '') {
        $noteLines[] = 'Mô tả: ' . $description;
    }
    $returnNote = implode("\n", $noteLines);
    $stmt = $ithanhloc->prepare('UPDATE ecommerce_order SET status="return_requested", return_reason=?, return_requested_at=COALESCE(return_requested_at,NOW()), updated_at=NOW() WHERE order_id=? AND user_id=?');
    $stmt->bind_param('ssi', $returnNote, $orderId, $userId);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if ($ok) {
        $link = '/view-order?order_id=' . urlencode($orderId);
        app_user_log($ithanhloc, $userId, 'order_return', 'Đã gửi yêu cầu trả hàng', [
            'order_id' => $orderId,
            'reason'   => $reason,
            'refund_amount' => $refundAmount,
        ]);
        ecommerce_order_log_insert($ithanhloc, $orderId, 'customer', $userId,
            'status_changed', $statusRaw, 'return_requested', $returnNote);
        app_user_notify_template($ithanhloc, $userId, 'order_return_requested', [
            'order_id' => $orderId,
            'status' => 'return_requested',
            'time' => $now,
            'link' => $link,
            'event' => 'order_return_requested',
        ]);
        if (function_exists('app_admin_notify')) {
            app_admin_notify($ithanhloc, 'order_return_request', [
                'order_id' => $orderId,
                'reason'   => $reason,
            ]);
        }
    }
    jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã gửi yêu cầu trả hàng. Vui lòng chờ admin xác nhận.' : $err]);
}


function handleUpdateAddressRequest(mysqli $ithanhloc): void {
    $orderId = requiredOrderId();
    $row = ensureOwnership($ithanhloc, $orderId);
    $status = (string)(ecommerce_order_status_info($row['status'] ?? 'pending')['key'] ?? 'pending');

    if (!canCustomerEditAddress($status)) {
        jOut(['ok' => false, 'msg' => 'Không thể đổi địa chỉ khi đơn đã bàn giao cho đơn vị vận chuyển.'], 400);
    }

    // Đọc và validate các field địa chỉ mới
    $recipientName  = trim((string)($_POST['recipient_name'] ?? ''));
    $contactPhone   = trim((string)($_POST['contact_phone'] ?? ''));
    $street         = trim((string)($_POST['street'] ?? ''));
    $ward           = trim((string)($_POST['ward'] ?? ''));
    $wardCode       = trim((string)($_POST['ward_code'] ?? ''));
    $district       = trim((string)($_POST['district'] ?? ''));
    $districtId     = (int)($_POST['district_id'] ?? 0);
    $province       = trim((string)($_POST['province'] ?? ''));
    $provinceId     = (int)($_POST['province_id'] ?? 0);

    $errors = [];
    if ($recipientName === '') $errors[] = 'Vui lòng nhập họ tên người nhận.';
    if ($contactPhone === '' || strlen(preg_replace('/[^0-9]/', '', $contactPhone)) < 9)
        $errors[] = 'Số điện thoại không hợp lệ.';
    if ($provinceId <= 0) $errors[] = 'Vui lòng chọn Tỉnh/Thành phố.';
    if ($districtId <= 0) $errors[] = 'Vui lòng chọn Quận/Huyện.';
    if ($wardCode === '') $errors[] = 'Vui lòng chọn Phường/Xã.';
    if ($street === '') $errors[] = 'Vui lòng nhập địa chỉ chi tiết.';

    foreach ([$recipientName, $contactPhone, $street, $ward, $district, $province] as $val) {
        if ($val !== '' && preg_match('/[<>\"\';()]/', $val)) {
            $errors[] = 'Nội dung nhập chứa ký tự không hợp lệ.';
            break;
        }
    }

    if ($errors) {
        jOut(['ok' => false, 'msg' => implode(' ', $errors)], 422);
    }

    // Lookup tên tỉnh/quận/phường từ ghn_region nếu chưa có
    if ($province === '' && $provinceId > 0) {
        $stmtP = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level='province' AND region_id=? LIMIT 1");
        if ($stmtP) { $stmtP->bind_param('i', $provinceId); $stmtP->execute(); $province = (string)($stmtP->get_result()->fetch_assoc()['name'] ?? ''); $stmtP->close(); }
    }
    if ($district === '' && $districtId > 0) {
        $stmtD = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level='district' AND region_id=? LIMIT 1");
        if ($stmtD) { $stmtD->bind_param('i', $districtId); $stmtD->execute(); $district = (string)($stmtD->get_result()->fetch_assoc()['name'] ?? ''); $stmtD->close(); }
    }
    if ($ward === '' && $wardCode !== '') {
        $stmtW = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level='ward' AND code=? LIMIT 1");
        if ($stmtW) { $stmtW->bind_param('s', $wardCode); $stmtW->execute(); $ward = (string)($stmtW->get_result()->fetch_assoc()['name'] ?? ''); $stmtW->close(); }
    }

    // Build địa chỉ đầy đủ dạng string
    $fullAddress = implode(', ', array_filter([$street, $ward, $district, $province], static fn($v) => $v !== ''));

    // Cập nhật snapshot JSON để lưu đầy đủ thông tin GHN (district_id, ward_code cần tính phí ship)
    $snapshot = [];
    if (!empty($row['shipping_snapshot_json'])) {
        $decoded = json_decode((string)$row['shipping_snapshot_json'], true);
        if (is_array($decoded)) $snapshot = $decoded;
    }
    $snapshot['destination']   = $fullAddress;
    $snapshot['recipient_name'] = $recipientName;
    $snapshot['contact_phone']  = $contactPhone;
    $snapshot['province']       = $province;
    $snapshot['province_id']    = $provinceId;
    $snapshot['district']       = $district;
    $snapshot['district_id']    = $districtId;
    $snapshot['ward']           = $ward;
    $snapshot['ward_code']      = $wardCode;
    $snapshot['street']         = $street;
    $snapshot['address_updated_at'] = date('Y-m-d H:i:s');
    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

    $userId = ecommerce_current_user_id();
    $stmt = $ithanhloc->prepare(
        'UPDATE ecommerce_order SET address=?, user_name=?, phone=?, shipping_snapshot_json=?, updated_at=NOW()
         WHERE order_id=? AND (user_id=? OR user_id=0 OR user_id IS NULL) LIMIT 1'
    );
    if (!$stmt) jOut(['ok' => false, 'msg' => 'Lỗi hệ thống'], 500);
    $stmt->bind_param('sssssi', $fullAddress, $recipientName, $contactPhone, $snapshotJson, $orderId, $userId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $err = $stmt->error;
    $stmt->close();

    if (!$ok || $affected < 1) {
        jOut(['ok' => false, 'msg' => $err ?: 'Không thể cập nhật địa chỉ'], 500);
    }

    ecommerce_order_log_insert($ithanhloc, $orderId, 'customer', $userId,
        'address_updated', $status, $status,
        'Khách đổi địa chỉ: ' . $fullAddress . ' | SDT: ' . $contactPhone . ' | Người nhận: ' . $recipientName);

    jOut(['ok' => true, 'msg' => 'Đã cập nhật địa chỉ giao hàng']);
}

function handleLegacyDataTable(mysqli $ithanhloc): void {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = max(10, (int)($_GET['length'] ?? 10));
    $page = intdiv($start, $length) + 1;
    $filters = [
        'status' => $_GET['status_filter'] ?? 'all',
        'search' => $_GET['search'] ?? '',
        'from' => $_GET['startDate'] ?? '',
        'to' => $_GET['endDate'] ?? '',
        'page' => $page,
        'limit' => $length,
    ];
    $result = queryOrders($ithanhloc, $filters);
    $rows = [];
    foreach ($result['data'] as $order) {
        $rows[] = [
            'order_id' => $order['order_id'],
            'user_name' => $order['customer']['name'],
            'phone' => $order['customer']['phone'],
            'created_at' => $order['created_at'],
            'created_fmt' => $order['created_human'],
            'status' => $order['status'],
            'status_label' => $order['status_label'],
            'cart_html' => buildLegacyCartHtml($order['items']),
            'total_amount_fmt' => $order['totals']['grand_total'],
            'note' => $order['note'],
        ];
    }
    jOut([
        'draw' => $draw,
        'recordsTotal' => $result['total_all'],
        'recordsFiltered' => $result['total'],
        'data' => $rows,
    ]);
}

function buildLegacyCartHtml(array $items): string {
    if (!$items) {
        return '<span class="text-muted small">Không có sản phẩm</span>';
    }
    $html = [];
    foreach ($items as $item) {
        $variant = $item['variant'] ? ' <span class="text-muted">(' . htmlspecialchars($item['variant'], ENT_QUOTES, 'UTF-8') . ')</span>' : '';
        $html[] = '<div class="cart-line"><span class="fw-semibold text-primary">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</span>' . $variant . ' <span class="badge bg-light text-dark">x' . (int)$item['qty'] . '</span></div>';
    }
    return implode('', $html);
}

jOut(['ok' => false, 'msg' => 'Bad Request'], 400);

