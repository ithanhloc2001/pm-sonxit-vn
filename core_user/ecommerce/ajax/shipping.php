<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/util.php';
?>
<?php
//Lấy thư viện ghnAdminLib
$ghnAdminLib = __DIR__ . '/../../../core_admin/giaohangnhanh/lib/ghn_admin.php';
if (is_file($ghnAdminLib)) {
    require_once $ghnAdminLib;
}
// Đảm bảo có hàm fetchDefaultSavedLocation khi file này được gọi trực tiếp (không thông qua location.php)
if (!function_exists('fetchDefaultSavedLocation')) {
    function fetchDefaultSavedLocation(mysqli $ithanhloc, int $userId): array {
        return is_callable('fetch_default_saved_location') ? fetch_default_saved_location($ithanhloc, $userId) : [];
    }
}
// Đây là file AJAX chuyên về các thao tác liên quan đến shipping, đặc biệt là tích hợp với GHN để tính phí và tạo đơn vận chuyển
function ghnConfig(): array {
    global $ECOMMERCE_GHN, $ithanhloc;
    // Cấu hình từ biến toàn cục (config.php)
    $cfg = is_array($ECOMMERCE_GHN ?? null) ? $ECOMMERCE_GHN : [];

    // Ghi đè bằng cấu hình lưu trong DB giống panel admin nếu có
    $dbCfg = [];
    $activeShop = [];
    if ($ithanhloc instanceof mysqli && function_exists('ghn_get_cfg')) {
        $dbCfg = ghn_get_cfg($ithanhloc);
        $activeShop = (array)($dbCfg['shop'] ?? []);

        // Đồng bộ các trường cơ bản
        if (array_key_exists('enabled', $dbCfg)) {
            $cfg['enabled'] = $dbCfg['enabled'];
        }
        if (!empty($dbCfg['base_url'])) {
            $cfg['base_url'] = $dbCfg['base_url'];
        }
        if (!empty($dbCfg['token'])) {
            $cfg['token'] = $dbCfg['token'];
        }
        if (!empty($activeShop['shop_id'])) {
            $cfg['shop_id'] = (int)$activeShop['shop_id'];
        }
        // Ưu tiên địa chỉ/điện thoại từ shop đang hoạt động
        if (!empty($activeShop['shop_name']) || !empty($activeShop['name'])) {
            $cfg['from_name'] = $activeShop['shop_name'] ?? $activeShop['name'];
        }
        if (!empty($activeShop['phone'])) {
            $cfg['from_phone'] = $activeShop['phone'];
        }
        if (!empty($activeShop['address'])) {
            $cfg['from_address'] = $activeShop['address'];
        }
        if (!empty($activeShop['district_id'])) {
            $cfg['from_district_id'] = (int)$activeShop['district_id'];
        }
        if (!empty($activeShop['ward_code'])) {
            $cfg['from_ward_code'] = $activeShop['ward_code'];
        }
    }

    // Lấy cấu hình env/base_url/token/enabled mặc định từ bảng site_ghn_conf
    if (function_exists('app_get_default_ghn_env_config') && $ithanhloc instanceof mysqli) {
        $envCfg = app_get_default_ghn_env_config($ithanhloc);
        // Chỉ ghi đè nếu cấu hình hiện tại chưa có
        if (!isset($cfg['env'])) {
            $cfg['env'] = $envCfg['env'] ?? 'test';
        }
        if (empty($cfg['base_url'])) {
            $cfg['base_url'] = $envCfg['base_url'] ?? '';
        }
        if (!isset($cfg['enabled'])) {
            $cfg['enabled'] = $envCfg['enabled'] ?? true;
        }
        if (empty($cfg['token']) && !empty($envCfg['token'])) {
            $cfg['token'] = $envCfg['token'];
        }
    }

    $baseUrl = trim((string)($cfg['base_url'] ?? ''));
    $enabled = !empty($cfg['enabled']);
    $token = trim((string)($cfg['token'] ?? ''));
    $shopId = (int)($cfg['shop_id'] ?? 0);

    $fromName = trim((string)($cfg['from_name'] ?? ($activeShop['shop_name'] ?? $activeShop['name'] ?? 'PaintMore')));
    $fromPhone = trim((string)($cfg['from_phone'] ?? ($activeShop['phone'] ?? '')));
    $fromAddress = trim((string)($cfg['from_address'] ?? ($activeShop['address'] ?? '')));
    $fromDistrictId = (int)($cfg['from_district_id'] ?? ($activeShop['district_id'] ?? 0));
    $fromWardCode = trim((string)($cfg['from_ward_code'] ?? ($activeShop['ward_code'] ?? '')));

    return [
        'enabled' => $enabled,
        'base_url' => rtrim($baseUrl, '/'),
        'token' => $token,
        'shop_id' => $shopId,
        'from_name' => $fromName !== '' ? $fromName : 'PaintMore',
        'from_phone' => $fromPhone,
        'from_address' => $fromAddress,
        'from_district_id' => $fromDistrictId,
        'from_ward_code' => $fromWardCode,
        'default_weight' => max(100, (int)($cfg['default_weight'] ?? 1200)),
        'default_length' => max(1, (int)($cfg['default_length'] ?? 20)),
        'default_width' => max(1, (int)($cfg['default_width'] ?? 20)),
        'default_height' => max(1, (int)($cfg['default_height'] ?? 20)),
        'insurance_value' => max(0, (int)($cfg['insurance_value'] ?? 0)),
    ];
}
// Danh sách các trang GHN trong admin, chỉ admin mới có quyền truy cập
function ghnIsReady(array $cfg): bool {
    return !empty($cfg['enabled'])
        && (string)($cfg['token'] ?? '') !== ''
    && (int)($cfg['shop_id'] ?? 0) > 0;
}
// Hàm tiện ích để đảm bảo bảng lưu trữ thông tin các đơn hàng GHN đã tạo tồn tại trong database, nếu chưa có sẽ tự động tạo
function ghnCandidateBaseUrls(array $cfg): array {
    $configured = rtrim((string)($cfg['base_url'] ?? ''), '/');
    $list = [];
    if ($configured !== '') $list[] = $configured;
    $list[] = 'https://dev-online-gateway.ghn.vn/shiip/public-api';
    $list[] = 'https://online-gateway.ghn.vn/shiip/public-api';
    $uniq = [];
    foreach ($list as $url) {
        $k = strtolower($url);
        if ($url !== '' && !isset($uniq[$k])) $uniq[$k] = $url;
    }
    return array_values($uniq);
}
// Hàm này dùng để đảm bảo bảng lưu trữ thông tin các đơn hàng GHN đã tạo tồn tại trong database, nếu chưa có sẽ tự động tạo
function ghnRequest(array $cfg, string $method, string $path, ?array $payload = null, bool $withShopId = true): array {
    $methodUpper = strtoupper($method);
    $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
    $attempts = [];

    foreach (ghnCandidateBaseUrls($cfg) as $baseUrl) {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if (!$ch) {
            $attempts[] = ['base_url' => $baseUrl, 'ok' => false, 'msg' => 'GHN curl init failed'];
            continue;
        }

        $headers = [
            'Content-Type: application/json',
            'Token: ' . (string)$cfg['token'],
        ];
        if ($withShopId) {
            $headers[] = 'ShopId: ' . (string)((int)$cfg['shop_id']);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $methodUpper,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if ($json !== null && in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $attempts[] = ['base_url' => $baseUrl, 'ok' => false, 'http' => $http, 'msg' => 'GHN request failed: ' . $err];
            continue;
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            $attempts[] = ['base_url' => $baseUrl, 'ok' => false, 'http' => $http, 'msg' => 'GHN response invalid'];
            continue;
        }
        $code = (int)($decoded['code'] ?? $http);
        $isOk = ($code >= 200 && $code < 300) || !empty($decoded['data']);
        $result = [
            'ok' => $isOk,
            'http' => $http,
            'code' => $code,
            'message' => (string)($decoded['message'] ?? ''),
            'data' => $decoded['data'] ?? null,
            'raw' => $decoded,
            'base_url' => $baseUrl,
        ];
        if ($isOk) {
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
// Tiện ích để lấy danh sách quận/huyện từ GHN, ưu tiên lấy theo từng tỉnh để giảm tải, nếu không được mới lấy toàn bộ
function ghnFetchDistricts(array $cfg): array {
    $districtMap = [];

    $provinceRes = ghnRequest($cfg, 'GET', '/master-data/province', null, false);
    if (($provinceRes['ok'] ?? false) && is_array($provinceRes['data'] ?? null)) {
        foreach ($provinceRes['data'] as $province) {
            if (!is_array($province)) continue;
            $provinceId = (int)($province['ProvinceID'] ?? 0);
            if ($provinceId <= 0) continue;
            $districtRes = ghnRequest($cfg, 'POST', '/master-data/district', ['province_id' => $provinceId], false);
            if (!($districtRes['ok'] ?? false) || !is_array($districtRes['data'] ?? null)) continue;
            foreach ($districtRes['data'] as $district) {
                if (!is_array($district)) continue;
                $id = (int)($district['DistrictID'] ?? 0);
                if ($id > 0) {
                    $districtMap[$id] = $district;
                }
            }
        }
    }

    if (!$districtMap) {
        $allDistrictRes = ghnRequest($cfg, 'GET', '/master-data/district', null, false);
        if (($allDistrictRes['ok'] ?? false) && is_array($allDistrictRes['data'] ?? null)) {
            foreach ($allDistrictRes['data'] as $district) {
                if (!is_array($district)) continue;
                $id = (int)($district['DistrictID'] ?? 0);
                if ($id > 0) {
                    $districtMap[$id] = $district;
                }
            }
        }
    }

    if (!$districtMap) {
        return ['ok' => false, 'msg' => 'Không tải được danh sách quận/huyện GHN'];
    }

    return ['ok' => true, 'data' => array_values($districtMap)];
}
// Tiện ích này dùng để lấy thông tin chi tiết của một đơn hàng GHN đã tạo, bao gồm cả trạng thái hiện tại, lịch sử trạng thái, v.v., giúp admin có thể theo dõi và quản lý đơn hàng hiệu quả hơn
function ghnHydrateSenderFromShop(array $cfg): array {
    if ((int)($cfg['from_district_id'] ?? 0) > 0 && trim((string)($cfg['from_ward_code'] ?? '')) !== '') {
        return $cfg;
    }
    if ((string)($cfg['token'] ?? '') === '' || (int)($cfg['shop_id'] ?? 0) <= 0) {
        return $cfg;
    }

    static $cache = [];
    $cacheKey = md5((string)($cfg['token'] ?? '') . '|' . (string)($cfg['shop_id'] ?? 0) . '|' . (string)($cfg['base_url'] ?? ''));
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $res = ghnRequest($cfg, 'POST', '/v2/shop/all', [
        'offset' => 0,
        'limit' => 200,
        'client_phone' => '',
    ], false);
    if (!($res['ok'] ?? false)) {
        $cache[$cacheKey] = $cfg;
        return $cfg;
    }

    $data = $res['data'] ?? [];
    $shops = is_array($data['shops'] ?? null) ? $data['shops'] : (is_array($data) ? $data : []);
    if (!$shops) {
        $cache[$cacheKey] = $cfg;
        return $cfg;
    }

    $shopId = (int)($cfg['shop_id'] ?? 0);
    $picked = null;
    foreach ($shops as $shop) {
        $id = (int)($shop['_id'] ?? $shop['shop_id'] ?? $shop['id'] ?? 0);
        if ($id === $shopId) {
            $picked = $shop;
            break;
        }
    }
    if ($picked === null && isset($shops[0]) && is_array($shops[0])) {
        $picked = $shops[0];
    }
    if (!is_array($picked)) {
        $cache[$cacheKey] = $cfg;
        return $cfg;
    }

    if ((int)($cfg['from_district_id'] ?? 0) <= 0) {
        $cfg['from_district_id'] = (int)($picked['district_id'] ?? 0);
    }
    if (trim((string)($cfg['from_ward_code'] ?? '')) === '') {
        $cfg['from_ward_code'] = trim((string)($picked['ward_code'] ?? ''));
    }
    if (trim((string)($cfg['from_phone'] ?? '')) === '') {
        $cfg['from_phone'] = trim((string)($picked['phone'] ?? ''));
    }
    if (trim((string)($cfg['from_address'] ?? '')) === '') {
        $cfg['from_address'] = trim((string)($picked['address'] ?? ''));
    }
    if (trim((string)($cfg['from_name'] ?? '')) === '') {
        $cfg['from_name'] = trim((string)($picked['name'] ?? ''));
    }

    $cache[$cacheKey] = $cfg;
    return $cfg;
}
// Tiện ích để lấy mã quận/huyện GHN dựa trên tên quận/huyện mà người dùng nhập vào, giúp tăng khả năng khớp chính xác khi tạo đơn hàng GHN nếu người dùng không biết mã quận/huyện
function ghnResolveDistrictId(array $cfg, string $districtName): int {
    $districtName = trim($districtName);
    if ($districtName === '') return 0;

    static $cache = null;
    if ($cache === null) {
        $res = ghnFetchDistricts($cfg);
        $cache = ($res['ok'] ?? false) && is_array($res['data'] ?? null) ? $res['data'] : [];
    }
    if (!$cache) return 0;

    foreach ($cache as $row) {
        $name = trim((string)($row['DistrictName'] ?? ''));
        if ($name !== '' && normalizeLooseText($name) === normalizeLooseText($districtName)) {
            return (int)($row['DistrictID'] ?? 0);
        }
    }
    foreach ($cache as $row) {
        $name = trim((string)($row['DistrictName'] ?? ''));
        if ($name !== '' && textContainsLoose($name, $districtName)) {
            return (int)($row['DistrictID'] ?? 0);
        }
    }
    return 0;
}
// Tiện ích để lấy mã phường/xã GHN dựa trên tên phường/xã và mã quận/huyện, giúp tăng khả năng khớp chính xác khi tạo đơn hàng GHN nếu người dùng không biết mã phường/xã
function ghnResolveWardCode(array $cfg, int $districtId, string $wardName): string {
    $wardName = trim($wardName);
    if ($districtId <= 0 || $wardName === '') return '';

    static $cache = [];
    if (!isset($cache[$districtId])) {
        $res = ghnRequest($cfg, 'POST', '/master-data/ward', ['district_id' => $districtId], false);
        $cache[$districtId] = ($res['ok'] ?? false) && is_array($res['data'] ?? null) ? $res['data'] : [];
    }
    $wards = $cache[$districtId];
    if (!$wards) return '';

    foreach ($wards as $row) {
        $name = trim((string)($row['WardName'] ?? ''));
        if ($name !== '' && normalizeLooseText($name) === normalizeLooseText($wardName)) {
            return trim((string)($row['WardCode'] ?? ''));
        }
    }
    foreach ($wards as $row) {
        $name = trim((string)($row['WardName'] ?? ''));
        if ($name !== '' && textContainsLoose($name, $wardName)) {
            return trim((string)($row['WardCode'] ?? ''));
        }
    }
    return '';
}
// Hàm tiện ích để xây dựng thông tin địa chỉ nhận hàng cho GHN dựa trên dữ liệu người dùng nhập vào, bao gồm cả việc cố gắng resolve mã quận/huyện và phường/xã nếu người dùng chỉ nhập tên
function ghnBuildToAddress(array $cfg, array $location): array {
    $districtId = (int)($location['district_id'] ?? 0);
    $wardCode = trim((string)($location['ward_code'] ?? ''));

    if ($districtId <= 0) {
        $districtId = ghnResolveDistrictId($cfg, (string)($location['district'] ?? ''));
    }
    if ($wardCode === '') {
        $wardCode = ghnResolveWardCode($cfg, $districtId, (string)($location['ward'] ?? ''));
    }
    return ['district_id' => $districtId, 'ward_code' => $wardCode];
}
// Hàm tiện ích để tính phí vận chuyển GHN cho một đơn hàng dựa trên thông tin địa chỉ nhận hàng và danh sách sản phẩm, sẽ tự động chọn dịch vụ phù hợp và trả về kết quả chi tiết
function ghnEstimateFee(array $cfg, array $location, array $items): array {
    $cfg = ghnHydrateSenderFromShop($cfg);
    if ((int)($cfg['from_district_id'] ?? 0) <= 0) {
        return ['ok' => false, 'msg' => 'Thiếu from_district_id GHN (không lấy được từ Shop)'];
    }

    $to = ghnBuildToAddress($cfg, $location);
    if ((int)$to['district_id'] <= 0 || (string)$to['ward_code'] === '') {
        return ['ok' => false, 'msg' => 'Không xác định được quận/huyện hoặc phường/xã GHN'];
    }

    $serviceRes = ghnRequest($cfg, 'POST', '/v2/shipping-order/available-services', [
        'shop_id' => (int)$cfg['shop_id'],
        'from_district' => (int)$cfg['from_district_id'],
        'to_district' => (int)$to['district_id'],
    ]);
    if (!($serviceRes['ok'] ?? false) || empty($serviceRes['data'][0])) {
        return ['ok' => false, 'msg' => 'Không lấy được dịch vụ GHN', 'debug' => $serviceRes];
    }
    $service = $serviceRes['data'][0];
    $serviceId = (int)($service['service_id'] ?? 0);
    $serviceTypeId = (int)($service['service_type_id'] ?? 0);

    $totalWeight = 0;
    $maxLength = 0;
    $maxWidth = 0;
    $maxHeight = 0;
    foreach ($items as $it) {
        $qty = max(1, (int)($it['qty'] ?? 1));
        $weightEach = max(1, (int)($it['weight'] ?? $cfg['default_weight']));
        $totalWeight += $qty * $weightEach;
        $maxLength = max($maxLength, max(1, (int)($it['length'] ?? $cfg['default_length'])));
        $maxWidth = max($maxWidth, max(1, (int)($it['width'] ?? $cfg['default_width'])));
        $maxHeight = max($maxHeight, max(1, (int)($it['height'] ?? $cfg['default_height'])));
    }
    if ($totalWeight <= 0) $totalWeight = (int)$cfg['default_weight'];
    if ($maxLength <= 0) $maxLength = (int)$cfg['default_length'];
    if ($maxWidth <= 0) $maxWidth = (int)$cfg['default_width'];
    if ($maxHeight <= 0) $maxHeight = (int)$cfg['default_height'];

    $payload = [
        'from_district_id' => (int)$cfg['from_district_id'],
        'from_ward_code' => (string)$cfg['from_ward_code'],
        'service_id' => $serviceId,
        'service_type_id' => $serviceTypeId,
        'to_district_id' => (int)$to['district_id'],
        'to_ward_code' => (string)$to['ward_code'],
        'weight' => $totalWeight,
        'length' => $maxLength,
        'width' => $maxWidth,
        'height' => $maxHeight,
        'insurance_value' => (int)$cfg['insurance_value'],
    ];

    $feeRes = ghnRequest($cfg, 'POST', '/v2/shipping-order/fee', $payload);
    if (!($feeRes['ok'] ?? false)) {
        return ['ok' => false, 'msg' => 'Không tính được phí GHN', 'debug' => $feeRes];
    }
    $feeData = is_array($feeRes['data'] ?? null) ? $feeRes['data'] : [];
    $totalFee = (float)($feeData['total'] ?? 0);

    return [
        'ok' => true,
        'fee' => max(0, $totalFee),
        'service_id' => $serviceId,
        'service_type_id' => $serviceTypeId,
        'to_district_id' => (int)$to['district_id'],
        'to_ward_code' => (string)$to['ward_code'],
        'raw' => $feeRes,
    ];
}
// ghnCreateShippingOrder tạo một đơn hàng vận chuyển GHN dựa trên dữ liệu đã chuẩn bị sẵn, bao gồm thông tin người gửi, người nhận, danh sách sản phẩm, và dịch vụ vận chuyển đã chọn, trả về kết quả chi tiết nếu thành công hoặc lỗi nếu có vấn đề
function ghnCreateShippingOrder(array $cfg, array $orderData): array {
    $res = ghnRequest($cfg, 'POST', '/v2/shipping-order/create', $orderData);
    if (!($res['ok'] ?? false)) {
        return ['ok' => false, 'msg' => 'Tạo đơn GHN thất bại', 'debug' => $res];
    }
    $data = is_array($res['data'] ?? null) ? $res['data'] : [];
    return [
        'ok' => true,
        'order_code' => (string)($data['order_code'] ?? ''),
        'sort_code' => (string)($data['sort_code'] ?? ''),
        'trans_type' => (string)($data['trans_type'] ?? ''),
        'raw' => $res,
    ];
}
// tryCreateGhnForCheckout thử tạo đơn GHN cho quá trình thanh toán, bao gồm việc kiểm tra cấu hình GHN, xây dựng địa chỉ nhận hàng, danh sách sản phẩm, và gọi API GHN để tạo đơn, trả về kết quả chi tiết nếu thành công hoặc lỗi nếu có vấn đề
function tryCreateGhnForCheckout(mysqli $ithanhloc, array $args): array {
    $cfg = ghnConfig();
    $cfg = ghnHydrateSenderFromShop($cfg);
    if (!ghnIsReady($cfg)) {
        return ['ok' => false, 'msg' => 'GHN chưa cấu hình'];
    }
    if ((int)($cfg['from_district_id'] ?? 0) <= 0) {
        return ['ok' => false, 'msg' => 'GHN thiếu from_district_id và không tự lấy được từ Shop'];
    }

    $shippingMethod = strtolower(trim((string)($args['shipping_method'] ?? '')));
    if ($shippingMethod !== '' && strpos($shippingMethod, 'ghn_') !== 0 && $shippingMethod !== 'tu_den_lay') {
        return ['ok' => false, 'msg' => 'Không tạo vận đơn GHN cho phương thức vận chuyển này'];
    }
    if ($shippingMethod === 'tu_den_lay') {
        return ['ok' => false, 'msg' => 'Không tạo vận đơn GHN cho đơn tự đến lấy'];
    }

    $location = is_array($args['location'] ?? null) ? $args['location'] : [];
    $to = ghnBuildToAddress($cfg, $location);
    if ((int)$to['district_id'] <= 0 || (string)$to['ward_code'] === '') {
        return ['ok' => false, 'msg' => 'Thiếu district/ward để tạo đơn GHN'];
    }

    $items = is_array($args['items'] ?? null) ? $args['items'] : [];
    if (!$items) {
        return ['ok' => false, 'msg' => 'Không có sản phẩm để tạo đơn GHN'];
    }

    $serviceRes = ghnRequest($cfg, 'POST', '/v2/shipping-order/available-services', [
        'shop_id' => (int)$cfg['shop_id'],
        'from_district' => (int)$cfg['from_district_id'],
        'to_district' => (int)$to['district_id'],
    ]);
    if (!($serviceRes['ok'] ?? false) || empty($serviceRes['data'][0])) {
        return ['ok' => false, 'msg' => 'GHN không có dịch vụ phù hợp', 'debug' => $serviceRes];
    }
    $service = $serviceRes['data'][0];

    $ghnItems = [];
    $totalWeight = 0;
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $qty = max(1, (int)($it['qty'] ?? 1));
        $name = trim((string)($it['name'] ?? $it['product_name'] ?? 'Sản phẩm'));
        $price = max(0, (int)($it['price'] ?? 0));
        $itemWeight = max(100, (int)$cfg['default_weight']);
        $totalWeight += $itemWeight * $qty;
        $ghnItems[] = [
            'name' => mb_substr($name, 0, 120),
            'code' => (string)($it['sku'] ?? ''),
            'quantity' => $qty,
            'price' => $price,
            'length' => (int)$cfg['default_length'],
            'width' => (int)$cfg['default_width'],
            'height' => (int)$cfg['default_height'],
            'weight' => $itemWeight,
            'category' => ['level1' => 'Paint'],
        ];
    }
    if (!$ghnItems) {
        return ['ok' => false, 'msg' => 'Không tạo được danh sách item GHN'];
    }

    $toName = trim((string)($args['user_name'] ?? ''));
    if ($toName === '') $toName = 'Khách hàng';
    $toPhone = preg_replace('/\D+/', '', (string)($args['phone'] ?? $location['contact_phone'] ?? ''));
    $toAddress = trim((string)($args['address'] ?? $location['customer_address'] ?? ''));
    if ($toAddress === '') {
        return ['ok' => false, 'msg' => 'Thiếu địa chỉ giao để tạo đơn GHN'];
    }
    if (strlen($toPhone) < 9) {
        return ['ok' => false, 'msg' => 'SĐT nhận hàng chưa hợp lệ cho GHN'];
    }

    $orderPayload = [
        'payment_type_id' => 2,
        'required_note' => 'KHONGCHOXEMHANG',
        'from_name' => (string)$cfg['from_name'],
        'from_phone' => preg_replace('/\D+/', '', (string)$cfg['from_phone']),
        'from_address' => (string)$cfg['from_address'],
        'from_ward_code' => (string)$cfg['from_ward_code'],
        'from_district_id' => (int)$cfg['from_district_id'],
        'to_name' => $toName,
        'to_phone' => $toPhone,
        'to_address' => $toAddress,
        'to_ward_code' => (string)$to['ward_code'],
        'to_district_id' => (int)$to['district_id'],
        'service_id' => (int)($service['service_id'] ?? 0),
        'service_type_id' => (int)($service['service_type_id'] ?? 0),
        'weight' => max((int)$cfg['default_weight'], $totalWeight),
        'length' => (int)$cfg['default_length'],
        'width' => (int)$cfg['default_width'],
        'height' => (int)$cfg['default_height'],
        'insurance_value' => max(0, (int)($args['total_amount'] ?? 0)),
        'client_order_code' => (string)($args['order_id'] ?? ''),
        'note' => trim((string)($args['note'] ?? '')),
        'items' => $ghnItems,
    ];

    $created = ghnCreateShippingOrder($cfg, $orderPayload);
    if (!($created['ok'] ?? false)) {
        return $created;
    }

    $tracking = trim((string)($created['order_code'] ?? ''));
    if ($tracking !== '') {
        $stmt = $ithanhloc->prepare('UPDATE ecommerce_order SET shipping_tracking=?, shipping_carrier=? WHERE order_id=? LIMIT 1');
        if ($stmt) {
            $carrier = 'Nhanh';
            $oid = (string)($args['order_id'] ?? '');
            $stmt->bind_param('sss', $tracking, $carrier, $oid);
            $stmt->execute();
            $stmt->close();
        }
    }

    return [
        'ok' => true,
        'order_code' => $tracking,
        'raw' => $created['raw'] ?? null,
    ];
}
// computeShippingFeeFromMethodOverrides là một hàm tiện ích để tính toán phí vận chuyển dựa trên các giá trị ghi đè có thể có cho từng phương thức vận chuyển, giúp linh hoạt trong việc áp dụng các mức phí khác nhau tùy theo phương thức được chọn
function computeShippingFeeFromMethodOverrides(array $feeOverrides, string $methodKey = ''): float {
    $methodKey = strtolower(trim($methodKey));
    if ($methodKey !== '' && array_key_exists($methodKey, $feeOverrides)) {
        return max(0, (float)$feeOverrides[$methodKey]);
    }
    foreach ($feeOverrides as $fee) {
        $value = max(0, (float)$fee);
        if ($value > 0) return $value;
    }
    return 0;
}

// normalizeShippingThresholds là một hàm tiện ích để chuẩn hóa và sắp xếp các ngưỡng vận chuyển được nhập vào dưới dạng chuỗi hoặc mảng, giúp dễ dàng quản lý và áp dụng các ngưỡng này trong quá trình tính phí vận chuyển
function normalizeShippingThresholds($raw): array {
    $list = [];
    if (is_array($raw)) {
        foreach ($raw as $item) {
            $num = is_numeric($item) ? (float)$item : (float)preg_replace('/[^\d]/', '', (string)$item);
            if ($num > 0) $list[] = $num;
        }
    } else {
        $txt = trim((string)$raw);
        if ($txt !== '') {
            $parts = preg_split('/[;,\|\n\r]+/u', $txt) ?: [];
            foreach ($parts as $part) {
                $num = (float)preg_replace('/[^\d]/', '', (string)$part);
                if ($num > 0) $list[] = $num;
            }
        }
    }
    $uniq = [];
    foreach ($list as $v) $uniq[$v] = $v;
    $vals = array_values($uniq);
    sort($vals, SORT_NUMERIC);
    return $vals;
}
// getRegionConfigValues là một hàm tiện ích để lấy danh sách các giá trị vùng miền được cấu hình, giúp quản lý và áp dụng các quy tắc vận chuyển theo vùng miền một cách linh hoạt
function getRegionConfigValues(): array {
    global $ECOMMERCE_REGIONS;
    $regions = (isset($ECOMMERCE_REGIONS) && is_array($ECOMMERCE_REGIONS) && !empty($ECOMMERCE_REGIONS))
        ? array_values($ECOMMERCE_REGIONS)
        : ['MIỀN BẮC', 'MIỀN TRUNG', 'MIỀN NAM'];
    return array_values(array_filter(array_map(static fn($v) => trim((string)$v), $regions), static fn($v) => $v !== ''));
}
// getCurrentSessionRegion là một hàm tiện ích để xác định vùng miền hiện tại của phiên làm việc dựa trên giá trị được lưu trong session, giúp áp dụng các quy tắc vận chuyển theo vùng miền một cách chính xác
function getCurrentSessionRegion(): string {
    $regions = getRegionConfigValues();
    $fallback = $regions[0] ?? 'MIỀN BẮC';
    $sessionVal = trim((string)($_SESSION['selected_region'] ?? ''));
    if ($sessionVal !== '' && in_array($sessionVal, $regions, true)) {
        return $sessionVal;
    }
    return $fallback;
}

function matchRuleRegion(string $sessionRegion, array $regions): bool {
    if (!$regions) return true;
    if (in_array('ALL', $regions, true)) return true;
    return in_array($sessionRegion, $regions, true);
}
// getSelectedLocationContext là một hàm tiện ích để lấy thông tin địa điểm đã chọn của người dùng, bao gồm cả việc ưu tiên lấy thông tin đã lưu trong cơ sở dữ liệu nếu người dùng đã đăng nhập, hoặc lấy thông tin từ session nếu là khách vãng lai, giúp áp dụng các quy tắc vận chuyển dựa trên địa điểm một cách chính xác
function getSelectedLocationContext(): array {
    global $ithanhloc;
    $location = [];
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        $location = fetchDefaultSavedLocation($ithanhloc, $userId);
    } else {
        // Khách vãng lai: lấy location đã lưu trong session thông qua region-session
        if (!empty($_SESSION['guest_location']) && is_array($_SESSION['guest_location'])) {
            $location = $_SESSION['guest_location'];
        }
    }

    $region = trim((string)($location['region'] ?? ''));
    if ($region === '') $region = getCurrentSessionRegion();

    $distance = $location['distance_km'] ?? null;
    if ($distance === '' || $distance === null || !is_numeric($distance)) {
        $distance = null;
    } else {
        $distance = (float)$distance;
    }

    return [
        'region' => $region,
        'branch_id' => (int)($location['branch_id'] ?? 0),
        'branch_name' => trim((string)($location['branch_name'] ?? '')),
        'branch_region' => trim((string)($location['branch_region'] ?? '')),
        'street' => trim((string)($location['street'] ?? '')),
        'ward' => trim((string)($location['ward'] ?? '')),
        'ward_code' => trim((string)($location['ward_code'] ?? '')),
        'district' => trim((string)($location['district'] ?? '')),
        'district_id' => (int)($location['district_id'] ?? 0),
        'province' => trim((string)($location['province'] ?? '')),
        'province_id' => (int)($location['province_id'] ?? 0),
        'contact_phone' => trim((string)($location['contact_phone'] ?? '')),
        'customer_address' => trim((string)($location['customer_address'] ?? '')),
        'distance_km' => $distance,
    ];
}
// normalizeLooseText là một hàm tiện ích để chuẩn hóa chuỗi văn bản một cách lỏng lẻo, bao gồm việc loại bỏ dấu câu, chuyển đổi sang chữ thường, và loại bỏ các ký tự không phải là chữ cái hoặc số, giúp tăng khả năng khớp khi so sánh các chuỗi văn bản liên quan đến địa điểm
function normalizeLooseText(string $txt): string {
    $txt = trim($txt);
    if ($txt === '') return '';
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        if ($converted !== false && $converted !== '') {
            $txt = $converted;
        }
    }
    $txt = mb_strtolower($txt, 'UTF-8');
    $txt = preg_replace('/[^a-z0-9\s]/u', ' ', $txt);
    $txt = preg_replace('/\s+/u', ' ', $txt);
    return trim((string)$txt);
}

function textContainsLoose(string $haystack, string $needle): bool {
    $h = normalizeLooseText($haystack);
    $n = normalizeLooseText($needle);
    if ($h === '' || $n === '') return false;
    return mb_strpos($h, $n, 0, 'UTF-8') !== false;
}

function matchRuleScopeByLocation(string $scopeType, string $scopeValue, array $location): bool {
    if ($scopeType === 'all') return true;
    if ($scopeValue === '') return true;

    $province = trim((string)($location['province'] ?? ''));
    $district = trim((string)($location['district'] ?? ''));
    $street = trim((string)($location['street'] ?? ''));
    $fullAddress = trim((string)($location['customer_address'] ?? ''));
    $region = trim((string)($location['region'] ?? ''));
    $branchRegion = trim((string)($location['branch_region'] ?? ''));

    if ($scopeType === 'province') {
        return textContainsLoose($province, $scopeValue)
            || textContainsLoose($fullAddress, $scopeValue)
            || textContainsLoose($region, $scopeValue)
            || textContainsLoose($branchRegion, $scopeValue);
    }
    if ($scopeType === 'district' || $scopeType === 'ward') {
        return textContainsLoose($district, $scopeValue)
            || textContainsLoose($street, $scopeValue)
            || textContainsLoose($fullAddress, $scopeValue);
    }

    return textContainsLoose($fullAddress, $scopeValue)
        || textContainsLoose($street, $scopeValue)
        || textContainsLoose($district, $scopeValue)
        || textContainsLoose($province, $scopeValue)
        || textContainsLoose($region, $scopeValue);
}

function matchRuleDistance(?float $fromKm, ?float $toKm, ?float $distanceKm): bool {
    if ($fromKm === null && $toKm === null) return true;
    if ($distanceKm === null) return false;
    if ($fromKm !== null && $distanceKm < $fromKm) return false;
    if ($toKm !== null && $distanceKm > $toKm) return false;
    return true;
}

function matchRuleOrder(?float $orderMin, ?float $orderMax, float $subtotal): bool {
    if ($orderMin !== null && $subtotal < $orderMin) return false;
    if ($orderMax !== null && $subtotal > $orderMax) return false;
    return true;
}

function resolveShippingByLocation(mysqli $ithanhloc, string $sessionRegion, array $location, float $subtotal = 0, string $preferredMethod = ''): array {
    return [
        'fallback' => false,
        'rules' => [],
        'selected_rule' => null,
        'calculated_fee' => 0,
    ];
}
// buildShippingPreviewForRegion là một hàm tiện ích để xây dựng bản xem trước thông tin vận chuyển dựa trên vùng miền và địa điểm của người dùng, bao gồm việc giải quyết quy tắc vận chuyển phù hợp và tính toán phí vận chuyển dựa trên các yếu tố như khoảng cách và tổng giá trị đơn hàng
function buildShippingPreviewForRegion(mysqli $ithanhloc, string $sessionRegion, array $location = [], float $subtotal = 0): array {
    $resolved = resolveShippingByLocation($ithanhloc, $sessionRegion, $location, $subtotal);
    return [
        'region' => $sessionRegion,
        'fallback' => (bool)($resolved['fallback'] ?? false),
        'rules' => $resolved['rules'] ?? [],
        'selected_rule' => $resolved['selected_rule'] ?? null,
        'calculated_fee' => $resolved['calculated_fee'],
        'distance_km' => $location['distance_km'] ?? null,
        'branch_name' => $location['branch_name'] ?? '',
        'customer_address' => $location['customer_address'] ?? '',
    ];
}

// CHUẨN HÓA DANH SÁCH SẢN PHẨM TỪ REQUEST ĐỂ TÍNH PHÍ SHIP (products_json)
function shippingItemsFromRequest(): ?array {
    $raw = $_REQUEST['products_json'] ?? null;
    if ($raw === null) {
        return null;
    }
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $decoded = json_decode((string)$raw, true);
        $items = is_array($decoded) ? $decoded : [];
    }
    if (!$items) {
        return [];
    }
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $pid = (int)($item['product_id'] ?? $item['id'] ?? 0);
        $qty = max(0, (int)($item['qty'] ?? 0));
        if ($pid <= 0 || $qty <= 0) continue;
        $normalized[] = [
            'id' => $pid,
            'product_id' => $pid,
            'variant_id' => max(0, (int)($item['variant_id'] ?? $item['v_id'] ?? $item['vid'] ?? 0)),
            'qty' => $qty,
            'price' => (float)($item['price'] ?? 0),
            'weight_value' => (float)($item['weight_value'] ?? $item['weight'] ?? 0),
            'weight_unit' => (string)($item['weight_unit'] ?? 'kg'),
            'length_cm' => (int)($item['length_cm'] ?? $item['length'] ?? 0),
            'width_cm' => (int)($item['width_cm'] ?? $item['width'] ?? 0),
            'height_cm' => (int)($item['height_cm'] ?? $item['height'] ?? 0),
        ];
    }
    return $normalized;
}

function extractProductIdsFromShippingItems($items): array {
    if (!is_array($items) || !$items) return [];
    return productIdsFromItems($items);
}

function buildProductShippingContext(mysqli $ithanhloc, array $productIds): array {
    if (!$productIds) {
        return [
            'blocked' => true,
            'block_reason' => 'Không có sản phẩm để tính vận chuyển',
            'missing_product_ids' => [],
            'available_methods' => [],
            'fee_overrides' => [],
            'method_labels' => [],
            'eta_windows' => [],
            'eta_text_overrides' => [],
        ];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $ithanhloc->prepare("SELECT id, shipping_methods FROM ecommerce_product WHERE id IN ($placeholders)");
    if (!$stmt) {
        return [
            'blocked' => true,
            'block_reason' => 'Không tải được cấu hình vận chuyển của sản phẩm',
            'missing_product_ids' => $productIds,
            'available_methods' => [],
            'fee_overrides' => [],
            'method_labels' => [],
            'eta_windows' => [],
            'eta_text_overrides' => [],
        ];
    }
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $res = $stmt->get_result();

    $methodIntersection = null;
    $missingProductIds = [];
    $feeOverrides = [];
    $methodLabels = [];
    $etaWindows = [];
    $etaTextOverrides = [];

    while ($row = $res->fetch_assoc()) {
        $pid = (int)($row['id'] ?? 0);
        $methods = sanitizeProductShippingMethods($row['shipping_methods'] ?? null);
        $activeMap = [];
        foreach ($methods as $method) {
            if (empty($method['active'])) continue;
            $key = strtolower(trim((string)($method['key'] ?? '')));
            if ($key === '') continue;
            $fee = (float)($method['fee'] ?? 0);
            $label = trim((string)($method['label'] ?? strtoupper(str_replace('_', ' ', $key))));
            $activeMap[$key] = $fee;
            if (!isset($methodLabels[$key])) {
                $methodLabels[$key] = $label;
            }
            if (!isset($feeOverrides[$key]) || $fee > (float)$feeOverrides[$key]) {
                $feeOverrides[$key] = $fee;
            }

            $etaMin = max(0, (int)($method['eta_days_min'] ?? 0));
            $etaMax = max($etaMin, (int)($method['eta_days_max'] ?? $etaMin));
            if (!isset($etaWindows[$key])) {
                $etaWindows[$key] = ['min' => $etaMin, 'max' => $etaMax];
            } else {
                $etaWindows[$key]['min'] = max((int)$etaWindows[$key]['min'], $etaMin);
                $etaWindows[$key]['max'] = max((int)$etaWindows[$key]['max'], $etaMax);
            }

            $etaText = trim((string)($method['eta_text'] ?? ''));
            if ($etaText !== '' && !isset($etaTextOverrides[$key])) {
                $etaTextOverrides[$key] = $etaText;
            }
        }
        $keys = array_keys($activeMap);
        if (!$keys && $pid > 0) {
            $missingProductIds[] = $pid;
        }
        if ($methodIntersection === null) {
            $methodIntersection = $keys;
        } else {
            $methodIntersection = array_values(array_intersect($methodIntersection, $keys));
        }
    }
    $stmt->close();

    $available = $methodIntersection ?? [];
    $blocked = false;
    $reason = '';
    if ($missingProductIds) {
        $blocked = true;
        $reason = 'Một số sản phẩm chưa cấu hình phương thức vận chuyển';
    } elseif (!$available) {
        $blocked = true;
        $reason = 'Không có phương thức vận chuyển chung cho các sản phẩm trong giỏ';
    }

    return [
        'blocked' => $blocked,
        'block_reason' => $reason,
        'missing_product_ids' => array_values(array_unique(array_map('intval', $missingProductIds))),
        'available_methods' => $available,
        'fee_overrides' => $feeOverrides,
        'method_labels' => $methodLabels,
        'eta_windows' => $etaWindows,
        'eta_text_overrides' => $etaTextOverrides,
    ];
}

function computeShippingFeeByLocation(mysqli $ithanhloc, float $subtotal, ?array $profile = null, string $preferredMethod = '', $shippingItems = null): float {
    $quote = buildShippingQuoteByLocation($ithanhloc, $subtotal, $profile, $preferredMethod, $shippingItems);
    return (float)($quote['shipping_fee'] ?? 0);
}

function ensureEcommerceShippingRuleTable(mysqli $ithanhloc): void {
    $exists = (function_exists('tableExists') && tableExists($ithanhloc, 'ecommerce_shipping_rule'));

    // Best-effort create/migrate: if permission is missing, silently keep rule feature disabled.
    if (!$exists) {
        try {
            $ithanhloc->query("CREATE TABLE IF NOT EXISTS `ecommerce_shipping_rule` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `method_key` varchar(50) NOT NULL DEFAULT 'tieu_chuan',
                `region` varchar(100) DEFAULT NULL,
                `district_id` int(11) DEFAULT NULL,
                `min_subtotal` double DEFAULT NULL,
                `max_subtotal` double DEFAULT NULL,
                `min_weight_gram` int(11) DEFAULT NULL,
                `max_weight_gram` int(11) DEFAULT NULL,
                `fee` int(11) NOT NULL DEFAULT 0,
                `priority` int(11) NOT NULL DEFAULT 0,
                `note` varchar(255) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_active_method` (`is_active`, `method_key`),
                KEY `idx_region` (`region`),
                KEY `idx_district_id` (`district_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // ignore
        }
    }

    // Migrate existing table: add district_id column + index if missing.
    try {
        if (function_exists('tableExists') && tableExists($ithanhloc, 'ecommerce_shipping_rule')) {
            $rulesCols = listColumns($ithanhloc, 'ecommerce_shipping_rule');
            $hasDistrictId = hasCol($rulesCols, 'district_id');
            if (!$hasDistrictId) {
                $ithanhloc->query("ALTER TABLE `ecommerce_shipping_rule` ADD COLUMN `district_id` int(11) DEFAULT NULL AFTER `region`");
                $ithanhloc->query("ALTER TABLE `ecommerce_shipping_rule` ADD KEY `idx_district_id` (`district_id`)");
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function pickShippingRuleForStandard(mysqli $ithanhloc, string $region, int $districtId, float $subtotal, int $weightGram): array {
    if (!function_exists('tableExists') || !tableExists($ithanhloc, 'ecommerce_shipping_rule')) {
        return ['id' => 0, 'fee' => null];
    }
        $region = trim($region);
    $method = 'tieu_chuan';
    $districtId = (int)$districtId;
    if ($districtId < 0) $districtId = 0;

                // Prefer district-specific rules, then exact-region rules, sau đó tới rule toàn quốc (ALL / NULL).
    $sql = "SELECT id, fee
            FROM ecommerce_shipping_rule
            WHERE is_active = 1
              AND method_key = ?
                            AND (
                                district_id IS NULL OR district_id = 0 OR district_id = ?
                            )
              AND (
                                region IS NULL OR region = '' OR UPPER(region) = 'ALL' OR region = ?
              )
              AND (min_subtotal IS NULL OR min_subtotal <= ?)
              AND (max_subtotal IS NULL OR max_subtotal >= ?)
              AND (min_weight_gram IS NULL OR min_weight_gram <= ?)
              AND (max_weight_gram IS NULL OR max_weight_gram >= ?)
            ORDER BY
                            (district_id = ?) DESC,
                            (region = ?) DESC,
              priority DESC,
              id DESC
            LIMIT 1";

    $stmt = $ithanhloc->prepare($sql);
    if (!$stmt) {
        return ['id' => 0, 'fee' => null];
    }
    $w = max(0, (int)$weightGram);
    $sub = max(0.0, (float)$subtotal);
    bindParamsDynamic($stmt, 'sisddiiis', [$method, $districtId, $region, $sub, $sub, $w, $w, $districtId, $region]);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['id' => 0, 'fee' => null];
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return ['id' => 0, 'fee' => null];
    }
    $fee = isset($row['fee']) ? (float)$row['fee'] : null;
    return [
        'id' => (int)($row['id'] ?? 0),
        'fee' => (is_numeric($fee) ? max(0.0, $fee) : null),
    ];
}
// buildShippingQuoteByLocation là một hàm tiện ích để xây dựng báo giá vận chuyển chi tiết dựa trên địa điểm, tổng giá trị đơn hàng, và các yếu tố liên quan đến sản phẩm, giúp cung cấp thông tin đầy đủ về các phương thức vận chuyển khả dụng, phí vận chuyển, và thời gian giao hàng dự kiến
function buildShippingQuoteByLocation(mysqli $ithanhloc, float $subtotal, ?array $profile = null, string $preferredMethod = '', $shippingItems = null): array {
    $location = getSelectedLocationContext();
    $region = trim((string)($location['region'] ?? ''));
    if ($region === '') $region = getCurrentSessionRegion();
    $districtId = (int)($location['district_id'] ?? 0);

    $contextItems = is_array($shippingItems) ? $shippingItems : ecommerce_cart_get();
    $productShippingCtx = buildProductShippingContextFromItems($ithanhloc, $contextItems);
    $productIds = array_values(array_map('intval', (array)($productShippingCtx['product_ids'] ?? [])));
    $requestedMethods = array_values(array_filter(array_map('strval', (array)($productShippingCtx['requested_methods'] ?? []))));
    $globalMethodLabels = defaultShippingMethodLabelMap();
    $methodLabels = (array)($productShippingCtx['method_labels'] ?? $globalMethodLabels);
    $package = (array)($productShippingCtx['package'] ?? []);
    $packageWeight = max(1, (int)($package['weight'] ?? 1000));
    $packageLength = max(1, (int)($package['length'] ?? 20));
    $packageWidth = max(1, (int)($package['width'] ?? 20));
    $packageHeight = max(1, (int)($package['height'] ?? 20));
    $feeOverrides = [];
    $etaWindows = [];
    $etaTextOverrides = [];
    $standardFeeCfg = app_get_config_value_by_path('ECOMMERCE_SHIPPING.standard_fee');
    $standardFee = is_numeric($standardFeeCfg) ? (float)$standardFeeCfg : 30000.0;
    $standardFee = (float)max(0, round($standardFee / 1000) * 1000);

    if (!empty($productShippingCtx['blocked'])) {
        $reason = trim((string)($productShippingCtx['block_reason'] ?? ''));
        if ($reason === '') $reason = 'Không thể đặt hàng do cấu hình vận chuyển sản phẩm';
        return [
            'blocked' => true,
            'block_reason' => $reason,
            'missing_product_ids' => array_values(array_map('intval', (array)($productShippingCtx['missing_product_ids'] ?? []))),
            'product_ids' => $productIds,
            'destination' => (string)($location['customer_address'] ?? ''),
            'shipping_fee' => 0,
            'shipping_discount' => 0,
            'shipping_rule_id' => 0,
            'shipping_method' => '',
            'shipping_method_text' => '',
            'carrier_name' => '',
            'eta_text' => '',
            'methods' => [],
            'requested_methods' => $requestedMethods,
            'method_labels' => $methodLabels,
        ];
    }
    // Lấy thông tin ghi đè phí và ETA từ sản phẩm, ưu tiên phương thức được yêu cầu nếu có
    // Hệ số phí vận chuyển theo phương thức lấy từ helper chung
    $methodRate = function_exists('app_get_default_shipping_method_rate_map')
        ? app_get_default_shipping_method_rate_map()
        : [
            'ghn_tiet_kiem' => 0.88,
            'ghn_nhanh' => 1.00,
            'ghn_hoa_toc' => 1.35,
            'tieu_chuan' => 1.0,
            'tu_den_lay' => 0.0,
        ];
    // Hàm tính phí cơ bản dự phòng nếu không lấy được phí từ GHN, dựa trên trọng lượng và kích thước gói hàng
    $fallbackBaseFee = static function(int $weight, int $length, int $width, int $height): float {
        $kg = max(1, (int)ceil($weight / 1000));
        $maxDim = max($length, $width, $height);
        $base = 15000 + ($kg * 7000);
        if ($maxDim >= 40) $base += 6000;
        if ($maxDim >= 60) $base += 8000;
        return (float)$base;
    };
    $roundFee = static function(float $fee): float {
        return (float)(round(max(0, $fee) / 1000) * 1000);
    };
    // Chỉ cho phép phương thức vận chuyển được cấu hình theo sản phẩm (intersection trên giỏ).
    // Nếu không có phương thức nào => khóa đặt hàng (được xử lý ở productShippingCtx['blocked']).
    $methodKeys = array_values(array_unique(array_filter(array_map('strval', $requestedMethods))));
    $buildEtaText = static function(int $minDays, int $maxDays): string {
        $from = date('d/m', strtotime('+' . $minDays . ' days'));
        $to = date('d/m', strtotime('+' . $maxDays . ' days'));
        return 'Dự kiến giao lúc: ' . $from . ' - ' . $to;
    };
    // Hàm cung cấp ETA mặc định cho từng phương thức vận chuyển, có thể được ghi đè bởi thông tin từ sản phẩm
    $defaultEtaByMethod = static function(string $methodKey): array {
        switch ($methodKey) {
            case 'ghn_hoa_toc': return ['min' => 0, 'max' => 1, 'text' => 'Nhận dự kiến: hôm nay - ngày mai'];
            case 'ghn_tiet_kiem': return ['min' => 2, 'max' => 5, 'text' => 'Nhận dự kiến sau: 2 - 5 ngày'];
            case 'tieu_chuan': return ['min' => 2, 'max' => 5, 'text' => 'Nhận dự kiến sau: 2 - 5 ngày'];
            case 'tu_den_lay': return ['min' => 0, 'max' => 0, 'text' => 'Sẵn sàng để nhận tại chi nhánh'];
            case 'ghn_nhanh':
            default:
                return ['min' => 1, 'max' => 3, 'text' => 'Nhận dự kiến sau: 1 - 3 ngày'];
        }
    };
    // Chính sách bồi thường nếu giao hàng quá hạn, có thể được tùy chỉnh theo từng phương thức hoặc chung cho tất cả
    $compensationText = 'Chọn phí vận chuyển phù hợp cho đơn hàng này'; //'Giao quá hạn được bù mã voucher 20.000đ vào tài khoản';
    $ghnCfg = ghnConfig();
    $hasDestination = (
        ((int)($location['district_id'] ?? 0) > 0 && trim((string)($location['ward_code'] ?? '')) !== '')
        || (trim((string)($location['district'] ?? '')) !== '' && trim((string)($location['ward'] ?? '')) !== '')
    );
    $canUseGhn = ghnIsReady($ghnCfg) && $hasDestination;
    $ghnFeeResult = null;
    $ghnFeeValue = null;
    if ($canUseGhn) {
        $ghnEstimateItems = [[
            'qty' => 1,
            'weight' => $packageWeight,
            'length' => $packageLength,
            'width' => $packageWidth,
            'height' => $packageHeight,
        ]];
        $ghnFeeResult = ghnEstimateFee($ghnCfg, $location, $ghnEstimateItems);
        if (!empty($ghnFeeResult['ok'])) {
            $ghnFeeValue = max(0, (float)($ghnFeeResult['fee'] ?? 0));
        }
    }

    $baseFee = $ghnFeeValue !== null
        ? (float)$ghnFeeValue
        : $fallbackBaseFee($packageWeight, $packageLength, $packageWidth, $packageHeight);

    // Shipping rules (DB) for the "Tiêu chuẩn" method
    $rulePicked = ['id' => 0, 'fee' => null];
    try {
        ensureEcommerceShippingRuleTable($ithanhloc);
        $rulePicked = pickShippingRuleForStandard($ithanhloc, $region, $districtId, $subtotal, (int)$packageWeight);
    } catch (Throwable $e) {
        $rulePicked = ['id' => 0, 'fee' => null];
    }

    $methodMap = [];
    foreach ($methodKeys as $methodKey) {
        $key = strtolower(trim((string)$methodKey));
        if ($key === '') continue;
        if ($key === 'tu_den_lay') {
            $fee = 0;
        } elseif ($key === 'tieu_chuan') {
            $ruleFee = $rulePicked['fee'] ?? null;
            $fee = is_numeric($ruleFee) ? (float)$ruleFee : $standardFee;
        } elseif (array_key_exists($key, $feeOverrides)) {
            $fee = max(0, (float)$feeOverrides[$key]);
        } else {
            $rate = (float)($methodRate[$key] ?? 1.0);
            $fee = $roundFee($baseFee * $rate);
            if ($fee <= 0) {
                $fee = $roundFee($fallbackBaseFee($packageWeight, $packageLength, $packageWidth, $packageHeight));
            }
        }

        $defaultEta = $defaultEtaByMethod($key);
        $window = is_array($etaWindows[$key] ?? null) ? $etaWindows[$key] : [];
        $etaMin = max(0, (int)($window['min'] ?? $defaultEta['min']));
        $etaMax = max($etaMin, (int)($window['max'] ?? $defaultEta['max']));
        $etaText = trim((string)($etaTextOverrides[$key] ?? ''));
        if ($etaText === '') {
            if ($key === 'tu_den_lay') {
                $etaText = (string)$defaultEta['text'];
            } else {
                $etaText = $buildEtaText($etaMin, $etaMax);
            }
        }

        $methodMap[$key] = [
            'key' => $key,
            'label' => (string)($methodLabels[$key] ?? $globalMethodLabels[$key] ?? strtoupper(str_replace('_', ' ', $key))),
            // carrier_name = ĐƠN VỊ vận chuyển (không phải tên gói). GHN giao gói "Nhanh" ⇒ carrier là "GHN".
            // Trước đây gán nhầm = 'Nhanh' (trùng tên gói) gây hiển thị lặp "NHANH - Nhanh".
            'carrier_name' => ($key === 'tieu_chuan')
                ? 'Tiêu chuẩn'
                : (($key !== 'tu_den_lay' && $ghnFeeValue !== null) ? 'GHN' : ''),
            'eta_text' => $etaText,
            'policy_text' => $compensationText,
            'active' => true,
            'fee' => $fee,
            'fee_text' => formatVND($fee) . ' đ',
        ];
    }

    if (!$methodMap) {
        return [
            'blocked' => true,
            'block_reason' => 'Chưa có phương thức vận chuyển phù hợp cho giỏ hàng hiện tại',
            'missing_product_ids' => [],
            'product_ids' => $productIds,
            'destination' => $location['customer_address'] ?? '',
            'shipping_fee' => 0,
            'shipping_fee_text' => formatVND(0) . ' đ',
            'shipping_rule_id' => 0,
            'carrier_name' => '',
            'shipping_method' => '',
            'shipping_method_text' => '',
            'eta_text' => '',
            'policy_text' => $compensationText,
            'extra_condition' => '',
            'methods' => [],
            'snapshot' => [
                'fallback' => false,
                'preferred_method' => $preferredMethod,
                'selected_rule' => null,
                'product_shipping' => [
                    'product_ids' => $productIds,
                    'requested_methods' => $requestedMethods,
                    'fee_overrides' => $feeOverrides,
                    'method_labels' => $methodLabels,
                    'package' => [
                        'weight' => $packageWeight,
                        'length' => $packageLength,
                        'width' => $packageWidth,
                        'height' => $packageHeight,
                    ],
                ],
                'ghn' => null,
            ],
        ];
    }

    $selectedMethod = strtolower(trim($preferredMethod));
    if ($selectedMethod === '' || !isset($methodMap[$selectedMethod])) {
        if ($ghnFeeValue !== null) {
            foreach (array_keys($methodMap) as $candidate) {
                if ($candidate !== 'tu_den_lay') {
                    $selectedMethod = $candidate;
                    break;
                }
            }
        }
        if ($selectedMethod === '' || !isset($methodMap[$selectedMethod])) {
            $selectedMethod = array_key_first($methodMap) ?: 'ghn_nhanh';
        }
    }
    $shippingFee = (float)($methodMap[$selectedMethod]['fee'] ?? 0);
    $carrierName = (string)($methodMap[$selectedMethod]['carrier_name'] ?? '');
    $shippingRuleId = 0;
    if ($selectedMethod === 'tieu_chuan') {
        $shippingRuleId = (int)($rulePicked['id'] ?? 0);
    }
    $ghnSnapshot = null;
    if ($canUseGhn && is_array($ghnFeeResult)) {
        if (!empty($ghnFeeResult['ok'])) {
            $ghnSnapshot = [
                'used' => true,
                'service_id' => (int)($ghnFeeResult['service_id'] ?? 0),
                'service_type_id' => (int)($ghnFeeResult['service_type_id'] ?? 0),
                'to_district_id' => (int)($ghnFeeResult['to_district_id'] ?? 0),
                'to_ward_code' => (string)($ghnFeeResult['to_ward_code'] ?? ''),
            ];
        } else {
            $ghnSnapshot = [
                'used' => false,
                'error' => (string)($ghnFeeResult['msg'] ?? 'GHN fee unavailable'),
            ];
        }
    }
    // Trả về thông tin vận chuyển chi tiết, bao gồm cả các phương thức khả dụng, phí vận chuyển, và các thông tin liên quan đến địa điểm và sản phẩm, giúp người dùng có cái nhìn tổng quan về lựa chọn vận chuyển của họ
    return [
        'blocked' => false,
        'block_reason' => '',
        'missing_product_ids' => [],
        'region' => $region,
        'distance_km' => $location['distance_km'] ?? null,
        'destination' => $location['customer_address'] ?? '',
        'shipping_fee' => $shippingFee,
        'shipping_fee_text' => formatVND($shippingFee) . ' đ',
        'shipping_rule_id' => $shippingRuleId,
        'carrier_name' => $carrierName,
        'shipping_method' => $selectedMethod,
        'shipping_method_text' => (string)($methodMap[$selectedMethod]['label'] ?? 'NHANH'),
        'eta_text' => (string)($methodMap[$selectedMethod]['eta_text'] ?? ''),
        'policy_text' => $compensationText,
        'extra_condition' => '',
        'methods' => array_values($methodMap),
        'snapshot' => [
            'fallback' => false,
            'preferred_method' => $preferredMethod,
            'selected_rule' => null,
            'product_shipping' => [
                'product_ids' => $productIds,
                'requested_methods' => $requestedMethods,
                'fee_overrides' => $feeOverrides,
                'method_labels' => $methodLabels,
                'package' => [
                    'weight' => $packageWeight,
                    'length' => $packageLength,
                    'width' => $packageWidth,
                    'height' => $packageHeight,
                ],
            ],
            'ghn' => $ghnSnapshot,
        ],
    ];
}
// buildProductShippingContextFromItems là một hàm tiện ích để xây dựng ngữ cảnh vận chuyển sản phẩm dựa trên các mặt hàng được cung cấp, bao gồm việc trích xuất ID sản phẩm, xác định các phương thức vận chuyển được yêu cầu, và thu thập thông tin về trọng lượng và kích thước của gói hàng, giúp chuẩn bị dữ liệu cần thiết cho việc tính toán phí vận chuyển và lựa chọn phương thức vận chuyển phù hợp
function buildProductShippingContextFromItems(mysqli $ithanhloc, array $shippingItems): array {
    $nonGiftItems = [];
    foreach ($shippingItems as $item) {
        if (!is_array($item)) continue;
        // Chỉ loại quà tặng theo cờ is_gift. KHÔNG suy luận gift từ price <= 0:
        // checkout cố ý không gửi price (chống spoofing) và shippingItemsFromRequest()
        // luôn gán price=0.0, nên dựa vào price sẽ coi nhầm mọi sản phẩm là quà tặng.
        if (!empty($item['is_gift'])) continue;
        $nonGiftItems[] = $item;
    }
    $productIds = extractProductIdsFromShippingItems($nonGiftItems);
    if (!$productIds) {
        return [
            'product_ids' => [],
            'blocked' => true,
            'block_reason' => 'Giỏ hàng trống hoặc chỉ có quà tặng',
            'missing_product_ids' => [],
            'requested_methods' => [],
            'method_labels' => defaultShippingMethodLabelMap(),
            'package' => ['weight' => 1000, 'length' => 20, 'width' => 20, 'height' => 20],
        ];
    }

    $lineItems = [];
    foreach ($shippingItems as $item) {
        if (!is_array($item)) continue;
        $pid = (int)($item['product_id'] ?? $item['id'] ?? 0);
        $variantId = max(0, (int)($item['variant_id'] ?? $item['v_id'] ?? $item['vid'] ?? 0));
        $qty = max(0, (int)($item['qty'] ?? 0));
        if ($pid <= 0 || $qty <= 0) continue;
        $lineItems[] = [
            'product_id' => $pid,
            'variant_id' => $variantId,
            'qty' => $qty,
            'weight_value' => (float)($item['weight_value'] ?? 0),
            'weight_unit' => (string)($item['weight_unit'] ?? 'kg'),
            'length_cm' => (int)($item['length_cm'] ?? 0),
            'width_cm' => (int)($item['width_cm'] ?? 0),
            'height_cm' => (int)($item['height_cm'] ?? 0),
        ];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    // Lấy thông tin phương thức vận chuyển được hỗ trợ của từng sản phẩm, đồng thời xác định giao điểm của các phương thức này để tìm ra những phương thức chung có thể áp dụng cho toàn bộ đơn hàng
    $stmt = $ithanhloc->prepare("SELECT id, shipping_methods FROM ecommerce_product WHERE id IN ($placeholders)");
    if (!$stmt) {
        return [
            'product_ids' => $productIds,
            'blocked' => true,
            'block_reason' => 'Không tải được cấu hình vận chuyển của sản phẩm',
            'missing_product_ids' => $productIds,
            'requested_methods' => [],
            'method_labels' => defaultShippingMethodLabelMap(),
            'package' => ['weight' => 1000, 'length' => 20, 'width' => 20, 'height' => 20],
        ];
    }
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $res = $stmt->get_result();

    $methodIntersection = null;
    $methodLabels = defaultShippingMethodLabelMap();
    $productMap = [];
    $missingProductIds = [];

    while ($row = $res->fetch_assoc()) {
        $pid = (int)($row['id'] ?? 0);
        if ($pid <= 0) continue;
        $productMap[$pid] = $row;

        $methods = sanitizeProductShippingMethods($row['shipping_methods'] ?? null);
        $activeKeys = [];
        foreach ($methods as $method) {
            if (empty($method['active'])) continue;
            $key = strtolower(trim((string)($method['key'] ?? '')));
            if ($key === '') continue;
            $activeKeys[] = $key;
            if (!empty($method['label']) && !isset($methodLabels[$key])) {
                $methodLabels[$key] = trim((string)$method['label']);
            }
        }
        $activeKeys = array_values(array_unique($activeKeys));
        if (!$activeKeys) {
            $missingProductIds[] = $pid;
        }
        if ($methodIntersection === null) $methodIntersection = $activeKeys;
        else $methodIntersection = array_values(array_intersect($methodIntersection, $activeKeys));
    }
    $stmt->close();

    $variantIds = [];
    foreach ($lineItems as $item) {
        $vid = (int)($item['variant_id'] ?? 0);
        if ($vid > 0) $variantIds[$vid] = $vid;
    }

    $variantMap = [];
    if ($variantIds) {
        $variantIdList = array_values($variantIds);
        $variantPlaceholders = implode(',', array_fill(0, count($variantIdList), '?'));
        $variantTypes = str_repeat('i', count($variantIdList));
        $stmtVariant = $ithanhloc->prepare("SELECT id, product_id, shipping_weight_value, shipping_weight_unit, shipping_length_cm, shipping_width_cm, shipping_height_cm FROM ecommerce_product_variants WHERE id IN ($variantPlaceholders)");
        if ($stmtVariant) {
            $stmtVariant->bind_param($variantTypes, ...$variantIdList);
            $stmtVariant->execute();
            $resVariant = $stmtVariant->get_result();
            while ($v = $resVariant->fetch_assoc()) {
                $vid = (int)($v['id'] ?? 0);
                if ($vid <= 0) continue;
                $variantMap[$vid] = $v;
            }
            $stmtVariant->close();
        }
    }

    $totalWeight = 0;
    $maxLength = 20;
    $maxWidth = 20;
    $maxHeight = 20;

    foreach ($lineItems as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $vid = (int)($item['variant_id'] ?? 0);
        $qty = max(1, (int)($item['qty'] ?? 1));
        if ($pid <= 0) continue;

        $requestWeightValue = (float)($item['weight_value'] ?? 0);
        $requestWeightUnit = (string)($item['weight_unit'] ?? 'kg');
        $requestLength = max(0, (int)($item['length_cm'] ?? 0));
        $requestWidth = max(0, (int)($item['width_cm'] ?? 0));
        $requestHeight = max(0, (int)($item['height_cm'] ?? 0));

        if ($requestWeightValue > 0) {
            $weightGram = convertWeightToGram($requestWeightValue, $requestWeightUnit);
            $lengthCm = max(1, $requestLength > 0 ? $requestLength : 20);
            $widthCm = max(1, $requestWidth > 0 ? $requestWidth : 20);
            $heightCm = max(1, $requestHeight > 0 ? $requestHeight : 20);

            if ($weightGram <= 0) $weightGram = 1000;
            $totalWeight += $weightGram * $qty;
            $maxLength = max($maxLength, $lengthCm);
            $maxWidth = max($maxWidth, $widthCm);
            $maxHeight = max($maxHeight, $heightCm);
            continue;
        }

        $productRow = $productMap[$pid] ?? null;
        $variantRow = ($vid > 0 && isset($variantMap[$vid])) ? $variantMap[$vid] : null;
        if ($variantRow && (int)($variantRow['product_id'] ?? 0) !== $pid) {
            $variantRow = null;
        }
        // Ưu tiên lấy thông tin trọng lượng và kích thước từ biến thể nếu có, sau đó mới đến sản phẩm, và cuối cùng là giá trị mặc định nếu không có thông tin nào được cung cấp, đảm bảo rằng chúng ta có dữ liệu cần thiết để tính toán phí vận chuyển một cách chính xác
        if ($variantRow) {
            $weightValue = (float)($variantRow['shipping_weight_value'] ?? 1);
            if ($weightValue < 0) $weightValue = 0;
            $weightGram = convertWeightToGram($weightValue, (string)($variantRow['shipping_weight_unit'] ?? 'kg'));
            $lengthCm = max(1, (int)($variantRow['shipping_length_cm'] ?? 20));
            $widthCm = max(1, (int)($variantRow['shipping_width_cm'] ?? 20));
            $heightCm = max(1, (int)($variantRow['shipping_height_cm'] ?? 20));
        } else {

            $weightGram = 1000;
            $lengthCm = 20;
            $widthCm = 20;
            $heightCm = 20;
        }
           
        if ($weightGram <= 0) $weightGram = 1000;
        $totalWeight += $weightGram * $qty;
        $maxLength = max($maxLength, $lengthCm);
        $maxWidth = max($maxWidth, $widthCm);
        $maxHeight = max($maxHeight, $heightCm);
    }
    // Nếu không có trọng lượng nào được cung cấp, sử dụng trọng lượng mặc định để đảm bảo rằng quá trình tính toán phí vận chuyển vẫn có thể tiếp tục mà không gặp lỗi do thiếu dữ liệu, đồng thời giúp tránh việc khóa đặt hàng do không xác định được trọng lượng của gói hàng
    if ($totalWeight <= 0) $totalWeight = 1000;
    $requestedMethods = $methodIntersection ?? [];
    $blocked = false;
    $reason = '';
    // Nếu có sản phẩm nào không có cấu hình phương thức vận chuyển, hoặc nếu không có phương thức chung nào cho tất cả sản phẩm trong giỏ, thì khóa đặt hàng và cung cấp lý do cụ thể để người dùng hiểu vấn đề, đồng thời giúp họ có cơ sở để điều chỉnh giỏ hàng hoặc liên hệ hỗ trợ nếu cần thiết
    if ($missingProductIds) {
        $blocked = true;
        $reason = 'Một số sản phẩm chưa cấu hình phương thức vận chuyển';
    } elseif (!$requestedMethods) {
        $blocked = true;
        $reason = 'Không có phương thức vận chuyển chung cho các sản phẩm trong giỏ';
    }
         
    return [
        'product_ids' => $productIds,
        'blocked' => $blocked,
        'block_reason' => $reason,
        'missing_product_ids' => array_values(array_unique(array_map('intval', $missingProductIds))),
        'requested_methods' => array_values(array_unique(array_map('strval', $requestedMethods))),
        'method_labels' => $methodLabels,
        'package' => ['weight' => $totalWeight, 'length' => $maxLength, 'width' => $maxWidth, 'height' => $maxHeight],
    ];
}

function sanitizeProductShippingMethods($raw): array {
    $list = decodeJsonArray($raw);
    $allowed = defaultShippingMethodLabelMap();
    $legacyMap = [
        'nhanh' => 'ghn_nhanh',
        'cong_kenh' => 'ghn_tiet_kiem',
        'hoa_toc' => 'ghn_hoa_toc',
    ];

    $cleanMap = [];
    foreach ($list as $item) {
        $key = '';
        $isActive = true;
        $label = '';
        if (is_string($item)) {
            $key = strtolower(trim($item));
        } elseif (is_array($item)) {
            $key = strtolower(trim((string)($item['key'] ?? '')));
            $label = trim((string)($item['label'] ?? ''));
            if (array_key_exists('active', $item)) {
                $isActive = !empty($item['active']);
            }
        }
        if (isset($legacyMap[$key])) $key = $legacyMap[$key];
        if (!isset($allowed[$key])) continue;
        $cleanMap[$key] = [
            'key' => $key,
            'label' => $label !== '' ? $label : $allowed[$key],
            'active' => $isActive,
        ];
    }

    if (!$cleanMap) return [];

    $result = [];
    foreach ($allowed as $key => $label) {
        if (isset($cleanMap[$key])) $result[] = $cleanMap[$key];
    }
    return $result;
}

// Nếu file được gọi trực tiếp (không phải include từ file khác), xử lý AJAX shipping tại đây
if (basename(__FILE__) === basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    $jOut = function ($data) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
        $jOut(['ok' => false, 'msg' => 'Không thể kết nối cơ sở dữ liệu']);
    }

    $ajax = (string)($_GET['ajax'] ?? '');

    if ($ajax === 'shipping_quote') {
        $profile = null;
        if (isset($_SESSION['user_id'])) {
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $stmt = $ithanhloc->prepare('SELECT * FROM user_address WHERE user_id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $profile = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
        $subtotalIn = (float)($_GET['subtotal'] ?? 0);
        $preferredMethod = trim((string)($_GET['shipping_method'] ?? ''));
        $shippingItems = shippingItemsFromRequest();
        $quote = buildShippingQuoteByLocation($ithanhloc, $subtotalIn, $profile, $preferredMethod, $shippingItems);
        $jOut([
            'ok' => true,
            'shipping_fee' => (float)($quote['shipping_fee'] ?? 0),
            'shipping_quote' => $quote,
        ]);
    }

    $jOut(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);
}