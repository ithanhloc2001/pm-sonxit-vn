<?php
if (!function_exists('ghn_admin_json_out')) {
    function ghn_admin_json_out(array $payload, int $statusCode = 200): void {
        while (ob_get_level()) { @ob_end_clean(); }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('ghn_admin_get_cfg')) {
    function ghn_admin_get_cfg(): array {
        global $ECOMMERCE_GHN, $ithanhloc;
        $cfg = is_array($ECOMMERCE_GHN ?? null) ? $ECOMMERCE_GHN : [];
        $dbCfg = [];
        if ($ithanhloc instanceof mysqli) {
            $res = $ithanhloc->query("SELECT setting_key, setting_value FROM giaohangnhanh_config");
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $key = trim((string)($row['setting_key'] ?? ''));
                    if ($key === '') continue;
                    $dbCfg[$key] = (string)($row['setting_value'] ?? '');
                }
            }
        }

        $sessionShop = [];
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['ghn_active_shop']) && is_array($_SESSION['ghn_active_shop'])) {
            $sessionShop = $_SESSION['ghn_active_shop'];
        }

        $dbShop = [];
        if ($ithanhloc instanceof mysqli && !$sessionShop) {
            $shopRes = $ithanhloc->query("SELECT shop_id, shop_name, phone, address, district_id, ward_code FROM giaohangnhanh_shop WHERE is_active=1 ORDER BY id DESC LIMIT 1");
            if ($shopRes instanceof mysqli_result && $shopRes->num_rows > 0) {
                $dbShop = (array)$shopRes->fetch_assoc();
            }
        }

        $activeShop = $sessionShop ?: $dbShop;

        $env = strtolower(trim((string)($cfg['env'] ?? 'test')));
        if (isset($dbCfg['env'])) {
            $env = strtolower(trim((string)$dbCfg['env']));
        }
        if (!in_array($env, ['test', 'prod'], true)) {
            $env = 'test';
        }
        $defaultBaseUrl = $env === 'prod'
            ? 'https://online-gateway.ghn.vn/shiip/public-api'
            : 'https://dev-online-gateway.ghn.vn/shiip/public-api';

        $enabled = !empty($cfg['enabled']);
        if (isset($dbCfg['enabled'])) {
            $enabled = in_array(strtolower(trim((string)$dbCfg['enabled'])), ['1', 'true', 'yes', 'on'], true);
        }

        $baseUrl = rtrim((string)($cfg['base_url'] ?? $defaultBaseUrl), '/');
        if (isset($dbCfg['base_url']) && trim((string)$dbCfg['base_url']) !== '') {
            $baseUrl = rtrim((string)$dbCfg['base_url'], '/');
        }

        $token = trim((string)($cfg['token'] ?? ''));
        if (isset($dbCfg['token'])) {
            $token = trim((string)$dbCfg['token']);
        }

        $shopId = (int)($cfg['shop_id'] ?? 0);
        if (!empty($activeShop['shop_id'])) {
            $shopId = (int)$activeShop['shop_id'];
        }

        $fromName = trim((string)($cfg['from_name'] ?? 'PaintMore'));
        if (!empty($activeShop['shop_name'])) {
            $fromName = trim((string)$activeShop['shop_name']);
        }

        $fromPhone = trim((string)($cfg['from_phone'] ?? ''));
        if (!empty($activeShop['phone'])) {
            $fromPhone = trim((string)$activeShop['phone']);
        }

        $fromAddress = trim((string)($cfg['from_address'] ?? ''));
        if (!empty($activeShop['address'])) {
            $fromAddress = trim((string)$activeShop['address']);
        }

        $fromDistrictId = (int)($cfg['from_district_id'] ?? 0);
        if (!empty($activeShop['district_id'])) {
            $fromDistrictId = (int)$activeShop['district_id'];
        }

        $fromWardCode = trim((string)($cfg['from_ward_code'] ?? ''));
        if (!empty($activeShop['ward_code'])) {
            $fromWardCode = trim((string)$activeShop['ward_code']);
        }

        return [
            'enabled' => $enabled,
            'env' => $env,
            'base_url' => $baseUrl,
            'token' => $token,
            'shop_id' => $shopId,
            'from_name' => $fromName,
            'from_phone' => $fromPhone,
            'from_address' => $fromAddress,
            'from_district_id' => $fromDistrictId,
            'from_ward_code' => $fromWardCode,
            'default_weight' => max(100, (int)($cfg['default_weight'] ?? 1200)),
            'default_length' => max(1, (int)($cfg['default_length'] ?? 20)),
            'default_width' => max(1, (int)($cfg['default_width'] ?? 20)),
            'default_height' => max(1, (int)($cfg['default_height'] ?? 20)),
            'insurance_value' => max(0, (int)($cfg['insurance_value'] ?? 0)),
            'active_shop' => [
                'shop_id' => (int)($activeShop['shop_id'] ?? 0),
                'shop_name' => (string)($activeShop['shop_name'] ?? $activeShop['name'] ?? ''),
                'phone' => (string)($activeShop['phone'] ?? ''),
                'address' => (string)($activeShop['address'] ?? ''),
                'district_id' => (int)($activeShop['district_id'] ?? 0),
                'ward_code' => (string)($activeShop['ward_code'] ?? ''),
            ],
        ];
    }
}

if (!function_exists('ghn_admin_ready')) {
    function ghn_admin_ready(array $cfg): bool {
        return !empty($cfg['enabled'])
            && (string)($cfg['token'] ?? '') !== ''
            && (int)($cfg['shop_id'] ?? 0) > 0;
    }
}

if (!function_exists('ghn_admin_mask_token')) {
    function ghn_admin_mask_token(string $token): string {
        $token = trim($token);
        if ($token === '') return '';
        if (strlen($token) <= 8) return str_repeat('*', strlen($token));
        return substr($token, 0, 4) . str_repeat('*', max(4, strlen($token) - 8)) . substr($token, -4);
    }
}

if (!function_exists('ghn_admin_candidates')) {
    function ghn_admin_candidates(array $cfg): array {
        $list = [];
        $env = strtolower(trim((string)($cfg['env'] ?? 'test')));
        if (!in_array($env, ['test', 'prod'], true)) {
            $env = 'test';
        }

        $configured = rtrim((string)($cfg['base_url'] ?? ''), '/');
        if ($configured !== '') $list[] = $configured;

        if ($env === 'prod') {
            $list[] = 'https://online-gateway.ghn.vn/shiip/public-api';
        } else {
            $list[] = 'https://dev-online-gateway.ghn.vn/shiip/public-api';
        }

        $uniq = [];
        foreach ($list as $url) {
            $key = strtolower($url);
            if (!isset($uniq[$key])) {
                $uniq[$key] = $url;
            }
        }
        return array_values($uniq);
    }
}

if (!function_exists('ghn_admin_norm_path')) {
    function ghn_admin_norm_path(string $path): string {
        $path = trim($path);
        if ($path === '') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return preg_replace('#/+#', '/', $path);
    }
}

if (!function_exists('ghn_region_cache_get_provinces')) {
    function ghn_region_cache_get_provinces(mysqli $ithanhloc): array {
        $rows = [];
        $res = $ithanhloc->query("SELECT region_id AS ProvinceID, name AS ProvinceName FROM giaohangnhanh_region WHERE level='province' ORDER BY name ASC");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = [
                    'ProvinceID' => (int)($row['ProvinceID'] ?? 0),
                    'ProvinceName' => (string)($row['ProvinceName'] ?? ''),
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('ghn_region_cache_get_districts')) {
    function ghn_region_cache_get_districts(mysqli $ithanhloc, int $provinceId): array {
        $provinceId = max(0, $provinceId);
        if ($provinceId <= 0) return [];

        $rows = [];
        $stmt = $ithanhloc->prepare("SELECT region_id AS DistrictID, name AS DistrictName, parent_id AS ProvinceID FROM giaohangnhanh_region WHERE level='district' AND parent_id=? ORDER BY name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $provinceId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'DistrictID' => (int)($row['DistrictID'] ?? 0),
                'DistrictName' => (string)($row['DistrictName'] ?? ''),
                'ProvinceID' => (int)($row['ProvinceID'] ?? 0),
            ];
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('ghn_region_cache_get_wards')) {
    function ghn_region_cache_get_wards(mysqli $ithanhloc, int $districtId): array {
        $districtId = max(0, $districtId);
        if ($districtId <= 0) return [];

        $rows = [];
        $stmt = $ithanhloc->prepare("SELECT code AS WardCode, name AS WardName, parent_id AS DistrictID FROM giaohangnhanh_region WHERE level='ward' AND parent_id=? ORDER BY name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $districtId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'WardCode' => (string)($row['WardCode'] ?? ''),
                'WardName' => (string)($row['WardName'] ?? ''),
                'DistrictID' => (int)($row['DistrictID'] ?? 0),
            ];
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('ghn_region_cache_save_provinces')) {
    function ghn_region_cache_save_provinces(mysqli $ithanhloc, array $rows): void {
        $sql = "INSERT INTO giaohangnhanh_region (level, region_id, parent_id, code, name, raw_json)
                VALUES ('province', ?, NULL, NULL, ?, ?)
                ON DUPLICATE KEY UPDATE name=VALUES(name), raw_json=VALUES(raw_json), updated_at=CURRENT_TIMESTAMP";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['ProvinceID'] ?? 0);
            if ($id <= 0) continue;
            $name = trim((string)($row['ProvinceName'] ?? ''));
            if ($name === '') continue;
            $raw = json_encode($row, JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('iss', $id, $name, $raw);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('ghn_region_cache_save_districts')) {
    function ghn_region_cache_save_districts(mysqli $ithanhloc, int $provinceId, array $rows): void {
        $provinceId = max(0, $provinceId);
        if ($provinceId <= 0) return;

        $sql = "INSERT INTO giaohangnhanh_region (level, region_id, parent_id, code, name, raw_json)
                VALUES ('district', ?, ?, NULL, ?, ?)
                ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), name=VALUES(name), raw_json=VALUES(raw_json), updated_at=CURRENT_TIMESTAMP";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['DistrictID'] ?? 0);
            if ($id <= 0) continue;
            $name = trim((string)($row['DistrictName'] ?? ''));
            if ($name === '') continue;
            $raw = json_encode($row, JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('iiss', $id, $provinceId, $name, $raw);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('ghn_region_cache_save_wards')) {
    function ghn_region_cache_save_wards(mysqli $ithanhloc, int $districtId, array $rows): void {
        $districtId = max(0, $districtId);
        if ($districtId <= 0) return;

        $sql = "INSERT INTO giaohangnhanh_region (level, region_id, parent_id, code, name, raw_json)
                VALUES ('ward', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), code=VALUES(code), name=VALUES(name), raw_json=VALUES(raw_json), updated_at=CURRENT_TIMESTAMP";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $code = trim((string)($row['WardCode'] ?? ''));
            if ($code === '') continue;
            $id = (int)preg_replace('/\D+/', '', $code);
            if ($id <= 0) {
                $id = abs(crc32($districtId . '-' . $code));
            }
            $name = trim((string)($row['WardName'] ?? ''));
            if ($name === '') continue;
            $raw = json_encode($row, JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('iisss', $id, $districtId, $code, $name, $raw);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('ghn_admin_set_config')) {
    function ghn_admin_set_config(mysqli $ithanhloc, string $key, string $value): bool {
        $stmt = $ithanhloc->prepare("INSERT INTO giaohangnhanh_config (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        if (!$stmt) return false;
        $stmt->bind_param('ss', $key, $value);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('ghn_admin_set_active_shop')) {
    function ghn_admin_set_active_shop(mysqli $ithanhloc, array $shop, int $adminUserId = 0): bool {
        $shopId = (int)($shop['shop_id'] ?? 0);
        if ($shopId <= 0) return false;

        $ithanhloc->query("UPDATE giaohangnhanh_shop SET is_active=0 WHERE is_active=1");

        $name = trim((string)($shop['shop_name'] ?? $shop['name'] ?? ('Shop ' . $shopId)));
        $phone = trim((string)($shop['phone'] ?? ''));
        $address = trim((string)($shop['address'] ?? ''));
        $districtId = (int)($shop['district_id'] ?? 0);
        $wardCode = trim((string)($shop['ward_code'] ?? ''));
        $rawJson = json_encode($shop, JSON_UNESCAPED_UNICODE);

        $sql = "INSERT INTO giaohangnhanh_shop (shop_id, shop_name, phone, address, district_id, ward_code, raw_json, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE
                    shop_name=VALUES(shop_name),
                    phone=VALUES(phone),
                    address=VALUES(address),
                    district_id=VALUES(district_id),
                    ward_code=VALUES(ward_code),
                    raw_json=VALUES(raw_json),
                    is_active=1,
                    created_by=VALUES(created_by),
                    updated_at=CURRENT_TIMESTAMP";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('isssissi', $shopId, $name, $phone, $address, $districtId, $wardCode, $rawJson, $adminUserId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ghn_active_shop'] = [
                'shop_id' => $shopId,
                'shop_name' => $name,
                'phone' => $phone,
                'address' => $address,
                'district_id' => $districtId,
                'ward_code' => $wardCode,
            ];
        }

        return $ok;
    }
}

if (!function_exists('ghn_admin_log_timeline')) {
    function ghn_admin_log_timeline(mysqli $ithanhloc, string $eventKey, string $status, string $title, array $detail = [], int $adminUserId = 0): void {
        $detailJson = $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $ithanhloc->prepare("INSERT INTO giaohangnhanh_timeline (admin_user_id, event_key, status, title, detail_json) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) return;
        $stmt->bind_param('issss', $adminUserId, $eventKey, $status, $title, $detailJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ghn_admin_get_timeline_rows')) {
    function ghn_admin_get_timeline_rows(mysqli $ithanhloc, int $limit = 100): array {
        $limit = max(10, min(500, $limit));
        $rows = [];
        $res = $ithanhloc->query("SELECT id, created_at, event_key, status, title, detail_json FROM giaohangnhanh_timeline ORDER BY id DESC LIMIT {$limit}");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $row['detail'] = json_decode((string)($row['detail_json'] ?? ''), true) ?: [];
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('ghn_admin_log_api')) {
    function ghn_admin_log_api(mysqli $ithanhloc, array $log): void {
        $stmt = $ithanhloc->prepare("INSERT INTO ghn_api_logs
            (admin_user_id, action, method, path, base_url, http_code, response_code, ok, duration_ms, error_message, request_json, response_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        if (!$stmt) return;

        $adminUserId = isset($log['admin_user_id']) ? (int)$log['admin_user_id'] : 0;
        $action = (string)($log['action'] ?? '');
        $method = (string)($log['method'] ?? 'GET');
        $path = (string)($log['path'] ?? '/');
        $baseUrl = (string)($log['base_url'] ?? '');
        $httpCode = (int)($log['http_code'] ?? 0);
        $responseCode = (int)($log['response_code'] ?? 0);
        $ok = !empty($log['ok']) ? 1 : 0;
        $durationMs = (int)($log['duration_ms'] ?? 0);
        $errorMessage = (string)($log['error_message'] ?? '');
        $requestJson = (string)($log['request_json'] ?? '');
        $responseJson = (string)($log['response_json'] ?? '');

        $stmt->bind_param(
            'issssiiiisss',
            $adminUserId,
            $action,
            $method,
            $path,
            $baseUrl,
            $httpCode,
            $responseCode,
            $ok,
            $durationMs,
            $errorMessage,
            $requestJson,
            $responseJson
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ghn_admin_request')) {
    function ghn_admin_request(mysqli $ithanhloc, array $cfg, string $method, string $path, ?array $payload = null, bool $withShopId = true, array $meta = []): array {
        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $method = 'GET';
        }

        $path = ghn_admin_norm_path($path);
        $adminUserId = (int)($meta['admin_user_id'] ?? 0);
        $action = (string)($meta['action'] ?? 'proxy');
        $jsonPayload = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

        $attempts = [];
        foreach (ghn_admin_candidates($cfg) as $baseUrl) {
            $url = rtrim($baseUrl, '/') . $path;
            $ch = curl_init($url);
            if (!$ch) {
                $attempts[] = ['ok' => false, 'msg' => 'curl init failed', 'base_url' => $baseUrl];
                continue;
            }

            $headers = [
                'Content-Type: application/json',
                'Token: ' . (string)$cfg['token'],
            ];
            if ($withShopId) {
                $headers[] = 'ShopId: ' . (string)((int)$cfg['shop_id']);
            }

            $start = microtime(true);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);

            if ($jsonPayload !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            }

            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            $durationMs = (int)round((microtime(true) - $start) * 1000);

            if ($raw === false) {
                $msg = $curlErr !== '' ? $curlErr : 'curl_exec failed';
                ghn_admin_log_api($ithanhloc, [
                    'admin_user_id' => $adminUserId,
                    'action' => $action,
                    'method' => $method,
                    'path' => $path,
                    'base_url' => $baseUrl,
                    'http_code' => $httpCode,
                    'response_code' => 0,
                    'ok' => false,
                    'duration_ms' => $durationMs,
                    'error_message' => $msg,
                    'request_json' => $jsonPayload,
                    'response_json' => '',
                ]);
                $attempts[] = ['ok' => false, 'msg' => $msg, 'http' => $httpCode, 'base_url' => $baseUrl];
                continue;
            }

            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                $decoded = ['raw_text' => (string)$raw];
            }

            $responseCode = (int)($decoded['code'] ?? $httpCode);
            $ok = ($responseCode >= 200 && $responseCode < 300) || !empty($decoded['data']);
            $errorMessage = '';
            if (!$ok) {
                $errorMessage = (string)($decoded['message'] ?? ('HTTP ' . $httpCode));
            }

            ghn_admin_log_api($ithanhloc, [
                'admin_user_id' => $adminUserId,
                'action' => $action,
                'method' => $method,
                'path' => $path,
                'base_url' => $baseUrl,
                'http_code' => $httpCode,
                'response_code' => $responseCode,
                'ok' => $ok,
                'duration_ms' => $durationMs,
                'error_message' => $errorMessage,
                'request_json' => $jsonPayload,
                'response_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            ]);

            $result = [
                'ok' => $ok,
                'http' => $httpCode,
                'code' => $responseCode,
                'message' => (string)($decoded['message'] ?? ''),
                'data' => $decoded['data'] ?? null,
                'raw' => $decoded,
                'base_url' => $baseUrl,
                'duration_ms' => $durationMs,
            ];

            if ($ok) {
                return $result;
            }
            $attempts[] = $result;
        }

        $last = end($attempts);
        if (!is_array($last)) {
            return ['ok' => false, 'msg' => 'GHN request failed'];
        }
        $last['attempts'] = $attempts;
        return $last;
    }
}

if (!function_exists('ghn_admin_save_order_snapshot')) {
    function ghn_admin_save_order_snapshot(mysqli $ithanhloc, array $orderData, int $adminUserId = 0): void {
        $orderCode = trim((string)($orderData['order_code'] ?? $orderData['OrderCode'] ?? ''));
        if ($orderCode === '') return;

        $clientOrderCode = trim((string)($orderData['client_order_code'] ?? ''));
        $statusText = trim((string)($orderData['status'] ?? $orderData['status_text'] ?? $orderData['Status'] ?? ''));
        $codAmount = (float)($orderData['cod_amount'] ?? $orderData['CODAmount'] ?? 0);
        $shippingFee = (float)($orderData['total_fee'] ?? $orderData['shipping_fee'] ?? $orderData['TotalFee'] ?? 0);
        $rawJson = json_encode($orderData, JSON_UNESCAPED_UNICODE);

        $stmt = $ithanhloc->prepare("INSERT INTO ghn_order_snapshots
            (admin_user_id, order_code, client_order_code, status_text, cod_amount, shipping_fee, raw_json)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                admin_user_id=VALUES(admin_user_id),
                client_order_code=VALUES(client_order_code),
                status_text=VALUES(status_text),
                cod_amount=VALUES(cod_amount),
                shipping_fee=VALUES(shipping_fee),
                raw_json=VALUES(raw_json),
                created_at=CURRENT_TIMESTAMP");
        if (!$stmt) return;

        $stmt->bind_param('isssdds', $adminUserId, $orderCode, $clientOrderCode, $statusText, $codAmount, $shippingFee, $rawJson);
        $stmt->execute();
        $stmt->close();
    }
}
