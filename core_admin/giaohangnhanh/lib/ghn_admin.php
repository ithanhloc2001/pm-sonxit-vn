<?php


if (!function_exists('ghn_setting_get_all')) {
    function ghn_setting_get_all(mysqli $ithanhloc): array {
        // Ưu tiên đọc một bản ghi duy nhất với key = 'config' để tránh nhiều dòng cấu hình
        $stmt = $ithanhloc->prepare("SELECT setting_value FROM site_ghn_conf WHERE setting_key='config' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $stmt->bind_result($val);
            if ($stmt->fetch()) {
                $stmt->close();
                $decoded = json_decode((string)$val, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } else {
                $stmt->close();
            }
        }

        // Fallback: đọc theo dạng key/value cũ (env, base_url, token, enabled ...)
        $rows = [];
        $res = $ithanhloc->query("SELECT setting_key, setting_value FROM site_ghn_conf");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $key = trim((string)($row['setting_key'] ?? ''));
                if ($key === '') continue;
                $rows[$key] = (string)($row['setting_value'] ?? '');
            }
        }
        return $rows;
    }
}

if (!function_exists('ghn_setting_save_config')) {
    function ghn_setting_save_config(mysqli $ithanhloc, array $config): bool {
        $key = 'config';
        $value = json_encode($config, JSON_UNESCAPED_UNICODE);
        $stmt = $ithanhloc->prepare("INSERT INTO site_ghn_conf (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        if (!$stmt) return false;
        $stmt->bind_param('ss', $key, $value);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('ghn_mask_token')) {
    function ghn_mask_token(string $token): string {
        $token = trim($token);
        if ($token === '') return '';
        if (strlen($token) <= 8) return str_repeat('*', strlen($token));
        return substr($token, 0, 4) . str_repeat('*', max(4, strlen($token) - 8)) . substr($token, -4);
    }
}

if (!function_exists('ghn_get_cfg')) {
    function ghn_get_cfg(mysqli $ithanhloc): array {
        $db = ghn_setting_get_all($ithanhloc);
        // Luôn dùng môi trường production với Base URL cố định
        $env = 'prod';
        $baseUrl = 'https://online-gateway.ghn.vn/shiip/public-api';
        $token = trim((string)($db['token'] ?? ''));
        $enabled = in_array(strtolower((string)($db['enabled'] ?? '1')), ['1','true','yes','on'], true);

        $activeShop = ghn_shop_get_active($ithanhloc);

        return [
            'enabled' => $enabled,
            'env' => $env,
            'base_url' => rtrim($baseUrl, '/'),
            'token' => $token,
            'shop' => $activeShop,
        ];
    }
}

if (!function_exists('ghn_ready')) {
    function ghn_ready(array $cfg): bool {
        return !empty($cfg['enabled'])
            && (string)($cfg['token'] ?? '') !== ''
            && !empty($cfg['shop']['shop_id']);
    }
}

if (!function_exists('ghn_norm_path')) {
    function ghn_norm_path(string $path): string {
        $path = trim($path);
        if ($path === '') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return preg_replace('#/+#', '/', $path);
    }
}

if (!function_exists('ghn_candidates')) {
    function ghn_candidates(array $cfg): array {
        $list = [];
        $env = strtolower(trim((string)($cfg['env'] ?? 'test')));
        if (!in_array($env, ['test', 'prod'], true)) $env = 'test';
        $configured = rtrim((string)($cfg['base_url'] ?? ''), '/');
        if ($configured !== '') $list[] = $configured;
        $list[] = $env === 'prod'
            ? 'https://online-gateway.ghn.vn/shiip/public-api'
            : 'https://dev-online-gateway.ghn.vn/shiip/public-api';

        $uniq = [];
        foreach ($list as $url) {
            $key = strtolower($url);
            if (!isset($uniq[$key])) $uniq[$key] = $url;
        }
        return array_values($uniq);
    }
}

if (!function_exists('ghn_request')) {
    function ghn_request(array $cfg, string $method, string $path, ?array $payload = null, bool $withShopId = true): array {
        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE'], true)) $method = 'GET';
        $path = ghn_norm_path($path);
        $jsonPayload = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $attempts = [];

        foreach (ghn_candidates($cfg) as $baseUrl) {
            $url = rtrim($baseUrl, '/') . $path;
            $ch = curl_init($url);
            if (!$ch) {
                $attempts[] = ['ok' => false, 'msg' => 'curl init failed', 'base_url' => $baseUrl];
                continue;
            }

            $headers = [
                'Content-Type: application/json',
                'Token: ' . (string)($cfg['token'] ?? ''),
            ];
            if ($withShopId && !empty($cfg['shop']['shop_id'])) {
                $headers[] = 'ShopId: ' . (string)((int)$cfg['shop']['shop_id']);
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
            if ($jsonPayload !== null && in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            }

            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            $durationMs = (int)round((microtime(true) - $start) * 1000);

            if ($raw === false) {
                $attempts[] = ['ok' => false, 'msg' => ($curlErr ?: 'curl_exec failed'), 'http' => $httpCode, 'base_url' => $baseUrl, 'duration_ms' => $durationMs];
                continue;
            }

            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) $decoded = ['raw_text' => (string)$raw];
            $respCode = (int)($decoded['code'] ?? $httpCode);
            $ok = ($respCode >= 200 && $respCode < 300) || !empty($decoded['data']);
            $message = trim((string)($decoded['message'] ?? ''));
            if ($message === '' || strtolower($message) === 'error') {
                $message = trim((string)($decoded['code_message_value'] ?? $decoded['code_message'] ?? $decoded['message_display'] ?? $decoded['msg'] ?? $decoded['error'] ?? ''));
            }
            if ($message === '' && isset($decoded['raw_text'])) {
                $message = trim((string)$decoded['raw_text']);
            }
            if ($message === '') {
                $message = $ok ? 'OK' : ('HTTP ' . $httpCode);
            }
            $result = [
                'ok' => $ok,
                'http' => $httpCode,
                'code' => $respCode,
                'message' => $message,
                'data' => $decoded['data'] ?? null,
                'raw' => $decoded,
                'base_url' => $baseUrl,
                'duration_ms' => $durationMs,
            ];
            if ($ok) return $result;
            $attempts[] = $result;
        }

        $last = end($attempts);
        if (!is_array($last)) return ['ok' => false, 'msg' => 'GHN request failed'];
        $last['attempts'] = $attempts;
        return $last;
    }
}

if (!function_exists('ghn_shop_list')) {
    function ghn_shop_extract_real_name(array $row): string {
        $name = trim((string)($row['name'] ?? ''));
        $shopId = (int)($row['shop_id'] ?? 0);
        $isFallbackName = ($name === '' || ($shopId > 0 && preg_match('/^shop\s*' . preg_quote((string)$shopId, '/') . '$/i', $name) === 1));
        if (!$isFallbackName) return $name;

        $rawJson = (string)($row['raw_json'] ?? '');
        if ($rawJson === '') return $name;
        $raw = json_decode($rawJson, true);
        if (!is_array($raw)) return $name;
        $candidates = [
            $raw['name'] ?? null,
            $raw['shop_name'] ?? null,
            $raw['ShopName'] ?? null,
            (is_array($raw['raw'] ?? null) ? ($raw['raw']['name'] ?? null) : null),
            (is_array($raw['raw'] ?? null) ? ($raw['raw']['shop_name'] ?? null) : null),
            (is_array($raw['raw'] ?? null) ? ($raw['raw']['ShopName'] ?? null) : null),
        ];
        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '' && !($shopId > 0 && preg_match('/^shop\s*' . preg_quote((string)$shopId, '/') . '$/i', $candidate) === 1)) {
                return $candidate;
            }
        }
        return $name;
    }

    function ghn_shop_list(mysqli $ithanhloc): array {
        $rows = [];
        $res = $ithanhloc->query("SELECT shop_id, name, phone, address, district_id, ward_code, is_active, synced_at, raw_json FROM ghn_shop ORDER BY is_active DESC, updated_at DESC");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $displayName = ghn_shop_extract_real_name($row);
                if ($displayName !== '' && $displayName !== (string)($row['name'] ?? '')) {
                    $row['name'] = $displayName;
                }
                $row['display_name'] = $displayName;
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('ghn_shop_upsert')) {
    function ghn_shop_upsert(mysqli $ithanhloc, array $shop, bool $setActive = false): bool {
        
        $shopId = (int)($shop['shop_id'] ?? 0);
        if ($shopId <= 0) return false;

        if ($setActive) {
            $ithanhloc->query("UPDATE ghn_shop SET is_active=0 WHERE is_active=1");
        }

        $name = trim((string)($shop['name'] ?? $shop['shop_name'] ?? ('Shop ' . $shopId)));
        $phone = trim((string)($shop['phone'] ?? ''));
        $address = trim((string)($shop['address'] ?? ''));
        $districtId = (int)($shop['district_id'] ?? 0);
        $wardCode = trim((string)($shop['ward_code'] ?? ''));
        $rawJson = json_encode($shop, JSON_UNESCAPED_UNICODE);
        $active = $setActive ? 1 : (int)($shop['is_active'] ?? 0);

        $sql = "INSERT INTO ghn_shop (shop_id, name, phone, address, district_id, ward_code, raw_json, is_active, synced_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    name=VALUES(name),
                    phone=VALUES(phone),
                    address=VALUES(address),
                    district_id=VALUES(district_id),
                    ward_code=VALUES(ward_code),
                    raw_json=VALUES(raw_json),
                    is_active=VALUES(is_active),
                    synced_at=VALUES(synced_at),
                    updated_at=CURRENT_TIMESTAMP";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('isssissi', $shopId, $name, $phone, $address, $districtId, $wardCode, $rawJson, $active);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('ghn_shop_set_active')) {
    function ghn_shop_set_active(mysqli $ithanhloc, int $shopId): bool {
        
        if ($shopId <= 0) return false;
        $ithanhloc->query("UPDATE ghn_shop SET is_active=0 WHERE is_active=1");
        $stmt = $ithanhloc->prepare("UPDATE ghn_shop SET is_active=1 WHERE shop_id=?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $shopId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('ghn_shop_get_active')) {
    function ghn_shop_get_active(mysqli $ithanhloc): array {
        
        $res = $ithanhloc->query("SELECT shop_id, name, phone, address, district_id, ward_code, raw_json FROM ghn_shop WHERE is_active=1 ORDER BY updated_at DESC LIMIT 1");
        if ($res instanceof mysqli_result && $res->num_rows > 0) {
            $row = (array)$res->fetch_assoc();
            $displayName = ghn_shop_extract_real_name($row);
            if ($displayName !== '' && $displayName !== (string)($row['name'] ?? '')) {
                $row['name'] = $displayName;
            }
            $row['display_name'] = $displayName;
            return $row;
        }
        return [];
    }
}

if (!function_exists('ghn_region_cache_get_provinces')) {
    function ghn_region_cache_get_provinces(mysqli $ithanhloc): array {
        
        $rows = [];
        $res = $ithanhloc->query("SELECT region_id AS ProvinceID, name AS ProvinceName FROM ghn_region WHERE level='province' ORDER BY name ASC");
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
        $stmt = $ithanhloc->prepare("SELECT region_id AS DistrictID, name AS DistrictName, parent_id AS ProvinceID FROM ghn_region WHERE level='district' AND parent_id=? ORDER BY name ASC");
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
        $stmt = $ithanhloc->prepare("SELECT code AS WardCode, name AS WardName, parent_id AS DistrictID FROM ghn_region WHERE level='ward' AND parent_id=? ORDER BY name ASC");
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
        
        $sql = "INSERT INTO ghn_region (level, region_id, parent_id, code, name, raw_json)
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

        $sql = "INSERT INTO ghn_region (level, region_id, parent_id, code, name, raw_json)
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

        $sql = "INSERT INTO ghn_region (level, region_id, parent_id, code, name, raw_json)
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

if (!function_exists('ghn_order_insert')) {
    function ghn_order_insert(mysqli $ithanhloc, array $order, array $items): int {
        
        $sql = "INSERT INTO ghn_order
            (system_order_id, order_code, status, status_text, shipping_fee, cod_amount, goods_value, content,
             weight, length, width, height,
             from_name, from_phone, from_address, from_district_id, from_ward_code,
             to_name, to_phone, to_address, to_province_id, to_district_id, to_ward_code,
             pickup_type, pick_shift_json, station_id, service_type_id, payment_type_id, payment_method, coupon,
             required_note, note, payload_json, response_json, created_by, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";

        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return 0;
        $pickShiftJson = json_encode($order['pick_shift'] ?? [], JSON_UNESCAPED_UNICODE);
        $payloadJson = json_encode($order['payload'] ?? [], JSON_UNESCAPED_UNICODE);
        $responseJson = json_encode($order['response'] ?? [], JSON_UNESCAPED_UNICODE);

        $stmt->bind_param(
            'ssssdddsiiiisssissssiisssiiissssssi',
            $order['system_order_id'],
            $order['order_code'],
            $order['status'],
            $order['status_text'],
            $order['shipping_fee'],
            $order['cod_amount'],
            $order['goods_value'],
            $order['content'],
            $order['weight'],
            $order['length'],
            $order['width'],
            $order['height'],
            $order['from_name'],
            $order['from_phone'],
            $order['from_address'],
            $order['from_district_id'],
            $order['from_ward_code'],
            $order['to_name'],
            $order['to_phone'],
            $order['to_address'],
            $order['to_province_id'],
            $order['to_district_id'],
            $order['to_ward_code'],
            $order['pickup_type'],
            $pickShiftJson,
            $order['station_id'],
            $order['service_type_id'],
            $order['payment_type_id'],
            $order['payment_method'],
            $order['coupon'],
            $order['required_note'],
            $order['note'],
            $payloadJson,
            $responseJson,
            $order['created_by']
        );
        $ok = $stmt->execute();
        $orderId = $ok ? (int)$stmt->insert_id : 0;
        $stmt->close();

        if ($orderId > 0) {
            $itemStmt = $ithanhloc->prepare("INSERT INTO ghn_order_item (ghn_order_id, product_id, name, sku, image, quantity, weight, length, width, height)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            if ($itemStmt) {
                foreach ($items as $it) {
                    $itemStmt->bind_param(
                        'iisssiiiii',
                        $orderId,
                        $it['product_id'],
                        $it['name'],
                        $it['sku'],
                        $it['image'],
                        $it['quantity'],
                        $it['weight'],
                        $it['length'],
                        $it['width'],
                        $it['height']
                    );
                    $itemStmt->execute();
                }
                $itemStmt->close();
            }
        }
        return $orderId;
    }
}

if (!function_exists('ghn_order_update_status')) {
    function ghn_order_update_status(mysqli $ithanhloc, string $orderCode, string $status, string $statusText, array $raw = []): void {
        // Cập nhật trạng thái đơn
        $stmt = $ithanhloc->prepare("UPDATE ghn_order SET status=?, status_text=?, last_sync_at=NOW(), updated_at=NOW() WHERE order_code=?");
        if ($stmt) {
            $stmt->bind_param('sss', $status, $statusText, $orderCode);
            $stmt->execute();
            $stmt->close();
        }
        $rawJson = $raw ? json_encode($raw, JSON_UNESCAPED_UNICODE) : null;

        // Lấy ghn_order_id từ bảng ghn_order
        $ghnOrderId = null;
        $stmtId = $ithanhloc->prepare("SELECT id FROM ghn_order WHERE order_code=? LIMIT 1");
        if ($stmtId) {
            $stmtId->bind_param('s', $orderCode);
            $stmtId->execute();
            $stmtId->bind_result($idVal);
            if ($stmtId->fetch()) {
                $ghnOrderId = $idVal;
            }
            $stmtId->close();
        }

        $stmt2 = $ithanhloc->prepare("INSERT INTO ghn_order_log (order_code, status, status_text, raw_json, ghn_order_id, created_at) VALUES (?,?,?,?,?,NOW())");
        if ($stmt2) {
            $stmt2->bind_param('sssss', $orderCode, $status, $statusText, $rawJson, $ghnOrderId);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

    function ghn_ecommerce_order_columns(mysqli $ithanhloc): array {
        return listColumns($ithanhloc, 'ecommerce_order');
    }

if (!function_exists('ghn_map_to_ecommerce_order_status')) {
    function ghn_map_to_ecommerce_order_status(string $ghnStatus): string {
        $s = strtolower(trim($ghnStatus));
        if ($s === '') return '';

        $pending = ['ready_to_pick','money_collect_ready_to_pick','create'];
        $processing = ['picking','picked','storing','sorting'];
        $shipping = ['delivering','transporting','money_collect_delivering','delivery_fail'];
        $delivered = ['delivered','delivery_success'];
        $canceled = ['cancel','canceled','cancelled','canceling','canceling_by_sender','exception','damage','lost'];
        $returned = ['return','returning','returned','return_sorting','return_transporting','returning_to_sender','waiting_to_return','return_fail'];

        if (in_array($s, $pending, true)) return 'pending';
        if (in_array($s, $processing, true)) return 'processing';
        if (in_array($s, $shipping, true)) return 'shipping';
        if (in_array($s, $delivered, true)) return 'delivered';
        if (in_array($s, $canceled, true)) return 'cancel';
        if (in_array($s, $returned, true)) return 'returned';
        return '';
    }
}

if (!function_exists('ghn_sync_ecommerce_order_status')) {
    function ghn_sync_ecommerce_order_status(mysqli $ithanhloc, string $systemOrderId, string $ghnStatus, string $orderCode = '', string $statusText = ''): bool {
        $systemOrderId = trim($systemOrderId);
        if ($systemOrderId === '') return false;

        $mappedStatus = ghn_map_to_ecommerce_order_status($ghnStatus);
        if ($mappedStatus === '') return false;

        $cols = ghn_ecommerce_order_columns($ithanhloc);
        if (!$cols) return false;

        // Đọc status hiện tại để áp guard rails:
        //  - KHÔNG đè trạng thái "đang chờ duyệt" của khách (cancel_requested/return_requested)
        //  - KHÔNG downgrade từ trạng thái terminal hoặc trễ hơn (canceled/returned/refunded/delivered)
        $currentStatus = '';
        $stCur = $ithanhloc->prepare('SELECT status FROM ecommerce_order WHERE order_id=? LIMIT 1');
        if ($stCur) {
            $stCur->bind_param('s', $systemOrderId);
            $stCur->execute();
            $rowCur = $stCur->get_result()->fetch_assoc();
            $stCur->close();
            $currentStatus = strtolower(trim((string)($rowCur['status'] ?? '')));
        }

        $pendingDecision = in_array($currentStatus, ['cancel_requested', 'return_requested'], true);
        $isTerminal = in_array($currentStatus, ['canceled', 'cancelled', 'cancel', 'returned', 'refunded'], true);
        $ghnLower = strtolower($mappedStatus);

        // Nếu khách đang chờ admin duyệt huỷ/trả mà GHN báo trạng thái khác → KHÔNG đè status,
        // chỉ cập nhật shipping_tracking/carrier + ghi log để admin xem xét.
        $skipStatusUpdate = false;
        if ($pendingDecision) {
            $skipStatusUpdate = true;
        }
        // Nếu đã ở trạng thái terminal cũ hơn (delivered/canceled/returned/refunded) thì không downgrade
        if ($currentStatus === 'delivered' && in_array($ghnLower, ['shipping', 'processing', 'pending'], true)) {
            $skipStatusUpdate = true;
        }
        if ($isTerminal && $ghnLower !== $currentStatus) {
            $skipStatusUpdate = true;
        }

        $set = [];
        $types = '';
        $vals = [];

        if (!empty($cols['status']) && !$skipStatusUpdate) {
            $set[] = 'status=?';
            $types .= 's';
            $vals[] = $mappedStatus;
        }
        if (!empty($cols['updated_at'])) {
            $set[] = 'updated_at=NOW()';
        }
        if ($orderCode !== '' && !empty($cols['shipping_tracking'])) {
            $set[] = 'shipping_tracking=?';
            $types .= 's';
            $vals[] = $orderCode;
        }
        if (!empty($cols['shipping_carrier'])) {
            $set[] = 'shipping_carrier=?';
            $types .= 's';
            $vals[] = 'GHN';
        }

        $timeNow = date('Y-m-d H:i:s');
        if ($mappedStatus === 'shipping' && !empty($cols['shipped_at'])) {
            $set[] = 'shipped_at=COALESCE(shipped_at, ?)';
            $types .= 's';
            $vals[] = $timeNow;
        }
        if ($mappedStatus === 'delivered' && !empty($cols['delivered_at'])) {
            $set[] = 'delivered_at=COALESCE(delivered_at, ?)';
            $types .= 's';
            $vals[] = $timeNow;
        }
        if (($mappedStatus === 'cancel' || $mappedStatus === 'canceled') && !empty($cols['canceled_at'])) {
            $set[] = 'canceled_at=COALESCE(canceled_at, ?)';
            $types .= 's';
            $vals[] = $timeNow;
        }
        if ($mappedStatus === 'returned') {
            if (!empty($cols['return_requested_at'])) {
                $set[] = 'return_requested_at=COALESCE(return_requested_at, ?)';
                $types .= 's';
                $vals[] = $timeNow;
            }
            if (!empty($cols['return_resolved_at'])) {
                $set[] = 'return_resolved_at=COALESCE(return_resolved_at, ?)';
                $types .= 's';
                $vals[] = $timeNow;
            }
        }

        if (!$set) {
            // Vẫn ghi log để admin biết có sync từ GHN nhưng bị skip
            if ($skipStatusUpdate && function_exists('ecommerce_order_log_insert')) {
                $reason = $pendingDecision
                    ? 'GHN báo "' . $mappedStatus . '" nhưng đơn đang chờ admin duyệt (' . $currentStatus . '). Bỏ qua, không tự đổi status.'
                    : 'GHN báo "' . $mappedStatus . '" nhưng đơn đã ở trạng thái cuối (' . $currentStatus . '). Bỏ qua để tránh downgrade.';
                ecommerce_order_log_insert(
                    $ithanhloc, $systemOrderId, 'carrier', 0,
                    'ghn_sync_skipped', $currentStatus, $currentStatus, $reason
                );
            }
            return false;
        }

        $sql = 'UPDATE ecommerce_order SET ' . implode(', ', $set) . ' WHERE order_id=? LIMIT 1';
        $types .= 's';
        $vals[] = $systemOrderId;
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return false;
        bindParamsDynamic($stmt, $types, $vals);
        $ok = $stmt->execute();
        $stmt->close();

        // Audit log mọi thay đổi status từ GHN
        if ($ok && !$skipStatusUpdate && $currentStatus !== $mappedStatus && function_exists('ecommerce_order_log_insert')) {
            $note = 'GHN sync: ' . $ghnStatus . ($statusText !== '' ? ' (' . $statusText . ')' : '')
                . ($orderCode !== '' ? ' [vận đơn: ' . $orderCode . ']' : '');
            ecommerce_order_log_insert(
                $ithanhloc, $systemOrderId, 'carrier', 0,
                'ghn_status_synced', $currentStatus, $mappedStatus, $note
            );

            // Khi GHN báo delivered → đồng bộ kích hoạt syncXuByOrderStatus để cộng xu / restore quy trình
            if ($mappedStatus === 'delivered' && function_exists('syncXuByOrderStatus')) {
                try { syncXuByOrderStatus($ithanhloc, $systemOrderId, 'delivered'); }
                catch (Throwable $e) { error_log('syncXuByOrderStatus (ghn delivered) failed: ' . $e->getMessage()); }
            }
            // Khi GHN báo returned/canceled từ trạng thái không pending → hoàn kho/voucher/refund
            if (in_array($mappedStatus, ['returned', 'canceled', 'cancel'], true)) {
                $stmtFull = $ithanhloc->prepare('SELECT order_id, status, products_json, voucher_code, voucher_shipping_code, voucher_payment_code, payment_status, payment_method, payment_gateway, total_amount FROM ecommerce_order WHERE order_id=? LIMIT 1');
                if ($stmtFull) {
                    $stmtFull->bind_param('s', $systemOrderId);
                    $stmtFull->execute();
                    $fullRow = $stmtFull->get_result()->fetch_assoc();
                    $stmtFull->close();
                    if ($fullRow) {
                        if (function_exists('restoreStockForOrder')) {
                            try { restoreStockForOrder($ithanhloc, $fullRow, 'carrier', 0); }
                            catch (Throwable $e) { error_log('restoreStockForOrder (ghn sync) failed: ' . $e->getMessage()); }
                        }
                        if (function_exists('restoreVoucherUsageForOrder')) {
                            try { restoreVoucherUsageForOrder($ithanhloc, $fullRow, 'carrier', 0); }
                            catch (Throwable $e) { error_log('restoreVoucherUsageForOrder (ghn sync) failed: ' . $e->getMessage()); }
                        }
                        if (function_exists('markRefundPendingForOrder')) {
                            try { markRefundPendingForOrder($ithanhloc, $fullRow, 'carrier', 0); }
                            catch (Throwable $e) { error_log('markRefundPendingForOrder (ghn sync) failed: ' . $e->getMessage()); }
                        }
                    }
                }
            }
        }
        return (bool)$ok;
    }
}

