<?php
require_once __DIR__ . '/../../config.php';

function normalizePhone(string $phone): string {
    return substr(preg_replace('/[^0-9+]/', '', trim($phone)), 0, 32);
}

function normalizeKey(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false && $converted !== null) {
        $text = $converted;
    }
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    $text = trim((string)preg_replace('/\s+/', ' ', $text));
    return $text;
}

function inferRegionFromProvince(mysqli $ithanhloc, int $provinceId, string $provinceName, array $regionConfig): string {
    $province = trim($provinceName);
    if ($province === '' && $provinceId > 0) {
        $province = ghnRegionNameById($ithanhloc, 'province', $provinceId);
    }
    if ($province === '') {
        return '';
    }

    $normProvince = normalizeKey($province);
    if ($normProvince === '') {
        return '';
    }

    // Chuẩn hoá label vùng cấu hình để khớp với Miền Bắc/Trung/Nam
    $northLabel   = '';
    $centralLabel = '';
    $southLabel   = '';
    
    foreach ($regionConfig as $label) {
        $norm = normalizeKey($label);
        if ($northLabel === '' && strpos($norm, 'mien bac') !== false) {
            $northLabel = $label;
        } elseif ($centralLabel === '' && strpos($norm, 'mien trung') !== false) {
            $centralLabel = $label;
        } elseif ($southLabel === '' && (strpos($norm, 'mien nam') !== false || strpos($norm, 'nam bo') !== false)) {
            $southLabel = $label;
        }
    }
    
    if ($northLabel === '') {
        $northLabel = $regionConfig[0] ?? '';
    }
    if ($centralLabel === '') {
        $centralLabel = $regionConfig[1] ?? $northLabel;
    }
    if ($southLabel === '') {
        $southLabel = $regionConfig[2] ?? $centralLabel;
    }

    // Danh sách tỉnh/thành theo vùng (dạng không dấu, thường)
    $north = [
        'ha giang', 'cao bang', 'lao cai', 'lai chau', 'dien bien', 'son la', 'yen bai', 'tuyen quang', 'lang son', 'bac kan', 'thai nguyen', 'phu tho', 'vinh phuc',
        'quang ninh', 'bac giang', 'bac ninh', 'hai duong', 'hung yen', 'hai phong', 'ha noi', 'thai binh', 'ha nam', 'nam dinh', 'ninh binh', 'hoa binh',
    ];
    $central = [
        'thanh hoa', 'nghe an', 'ha tinh', 'quang binh', 'quang tri', 'thua thien hue', 'hue', 'da nang', 'quang nam', 'quang ngai', 'binh dinh', 'phu yen',
        'khanh hoa', 'ninh thuan', 'binh thuan', 'kon tum', 'gia lai', 'dak lak', 'dak nong', 'lam dong',
    ];
    $south = [
        'binh phuoc', 'binh duong', 'dong nai', 'tay ninh', 'ba ria vung tau', 'vung tau', 'ho chi minh', 'tp ho chi minh', 'thanh pho ho chi minh',
        'long an', 'tien giang', 'ben tre', 'tra vinh', 'vinh long', 'dong thap', 'an giang', 'kien giang', 'can tho', 'hau giang', 'soc trang', 'bac lieu', 'ca mau',
    ];

    if (in_array($normProvince, $north, true)) {
        return $northLabel;
    }
    if (in_array($normProvince, $central, true)) {
        return $centralLabel;
    }
    if (in_array($normProvince, $south, true)) {
        return $southLabel;
    }

    return '';
}

function locationFingerprint(array $loc): string {
    $region     = normalizeKey((string)($loc['region'] ?? ''));
    $addr       = normalizeKey((string)($loc['customer_address'] ?? ''));
    $provinceId = (int)($loc['province_id'] ?? 0);
    $districtId = (int)($loc['district_id'] ?? 0);
    $wardCode   = strtolower(trim((string)($loc['ward_code'] ?? '')));
    $branchId   = (int)($loc['branch_id'] ?? 0);
    
    if ($addr === '' && $provinceId === 0 && $districtId === 0 && $wardCode === '' && $branchId === 0) {
        return '';
    }
    
    return implode('|', [
        $region,
        (string)$branchId,
        (string)$provinceId,
        (string)$districtId,
        $wardCode,
        $addr,
    ]);
}

function regionOptions(): array {
    global $ECOMMERCE_REGIONS;
    $regions = (isset($ECOMMERCE_REGIONS) && is_array($ECOMMERCE_REGIONS) && !empty($ECOMMERCE_REGIONS))
        ? array_values($ECOMMERCE_REGIONS)
        : ['MIỀN BẮC', 'MIỀN TRUNG', 'MIỀN NAM'];
    $clean = [];
    foreach ($regions as $region) {
        $v = trim((string)$region);
        if ($v !== '') {
            $clean[$v] = $v;
        }
    }
    return array_values($clean);
}

function clearLegacyLocationCookies(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (isset($_COOKIE['selected_location'])) {
        setcookie('selected_location', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['selected_location']);
    }
    if (isset($_COOKIE['selected_locations'])) {
        setcookie('selected_locations', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['selected_locations']);
    }
}

function fetchSavedLocationsFromDb(mysqli $ithanhloc, int $userId): array {
    if ($userId <= 0) {
        return [];
    }
    $rows = [];
    $stmt = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id = ? ORDER BY is_default DESC, updated_at DESC, id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
        $payload['is_default'] = (int)($row['is_default'] ?? 0);
        $rows[]                = $payload;
    }
    $stmt->close();
    return $rows;
}

function fetchDefaultLocationFromDb(mysqli $ithanhloc, int $userId): array {
    if ($userId <= 0) {
        return [];
    }
    
    $stmt = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id = ? AND is_default = 1 ORDER BY updated_at DESC, id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
            $payload['is_default'] = (int)($row['is_default'] ?? 0);
            return $payload;
        }
    }

    $stmt2 = $ithanhloc->prepare("SELECT address_id, payload_json, is_default FROM user_saved_locations WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('i', $userId);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($row) {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $payload['address_id'] = $payload['address_id'] ?? (string)($row['address_id'] ?? '');
            $payload['is_default'] = (int)($row['is_default'] ?? 0);
            return $payload;
        }
    }

    return [];
}

function persistLocationsToDb(mysqli $ithanhloc, int $userId, array $locations): void {
    if ($userId <= 0) {
        return;
    }
    $ithanhloc->query("DELETE FROM user_saved_locations WHERE user_id = " . (int)$userId);
    if (!$locations) {
        return;
    }
    $stmt = $ithanhloc->prepare("INSERT INTO user_saved_locations (user_id, address_id, payload_json, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        return;
    }
    foreach ($locations as $loc) {
        if (!is_array($loc)) {
            continue;
        }
        $addressId = (string)($loc['address_id'] ?? '');
        if ($addressId === '') {
            $addressId         = 'loc_' . substr(sha1(uniqid('', true)), 0, 12);
            $loc['address_id'] = $addressId;
        }
        $payload   = json_encode($loc, JSON_UNESCAPED_UNICODE);
        $isDefault = (int)($loc['is_default'] ?? 0);
        $stmt->bind_param('issi', $userId, $addressId, $payload, $isDefault);
        $stmt->execute();
    }
    $stmt->close();
}

function persistDefaultLocationToDb(mysqli $ithanhloc, int $userId, array $location): void {
    if ($userId <= 0) {
        return;
    }
    $addressId = (string)($location['address_id'] ?? '');
    if ($addressId === '') {
        return;
    }

    $payload = json_encode($location, JSON_UNESCAPED_UNICODE);

    // Bỏ cờ mặc định hiện tại
    $ithanhloc->query("UPDATE user_saved_locations SET is_default = 0 WHERE user_id = " . (int)$userId);

    // Cập nhật bản ghi địa chỉ tương ứng thành mặc định, đồng thời đồng bộ payload
    $stmt = $ithanhloc->prepare("UPDATE user_saved_locations SET payload_json = ?, is_default = 1, updated_at = NOW() WHERE user_id = ? AND address_id = ?");
    if ($stmt) {
        $stmt->bind_param('sis', $payload, $userId, $addressId);
        $stmt->execute();
        $stmt->close();
    }
}

function ghnRegionNameById(mysqli $ithanhloc, string $level, int $regionId): string {
    if ($regionId <= 0) {
        return '';
    }
    $stmt = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level = ? AND region_id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('si', $level, $regionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['name'] ?? ''));
}

function saveAddressToProfile(mysqli $ithanhloc, int $userId, array $location): void {
    if ($userId <= 0) {
        return;
    }
    $address    = trim((string)($location['customer_address'] ?? ''));
    $province   = trim((string)($location['province'] ?? ''));
    $district   = trim((string)($location['district'] ?? ''));
    $ward       = trim((string)($location['ward'] ?? ''));
    $phone      = normalizePhone((string)($location['contact_phone'] ?? ''));
    $provinceId = (int)($location['province_id'] ?? 0);
    $districtId = (int)($location['district_id'] ?? 0);
    $wardCode   = trim((string)($location['ward_code'] ?? ''));

    if ($provinceId > 0 && $province === '') {
        $province = ghnRegionNameById($ithanhloc, 'province', $provinceId);
    }
    if ($districtId > 0 && $district === '') {
        $district = ghnRegionNameById($ithanhloc, 'district', $districtId);
    }
    if ($wardCode !== '' && $ward === '') {
        $stmtWard = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level = 'ward' AND code = ? LIMIT 1");
        if ($stmtWard) {
            $stmtWard->bind_param('s', $wardCode);
            $stmtWard->execute();
            $wardRow = $stmtWard->get_result()->fetch_assoc();
            $stmtWard->close();
            $ward = trim((string)($wardRow['name'] ?? ''));
        }
    }

    $stmt = $ithanhloc->prepare("INSERT INTO user_address (user_id, phone, address, province, district, ward, province_id, district_id, ward_code, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            phone = VALUES(phone),
            address = VALUES(address),
            province = VALUES(province),
            district = VALUES(district),
            ward = VALUES(ward),
            province_id = VALUES(province_id),
            district_id = VALUES(district_id),
            ward_code = VALUES(ward_code),
            updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param('isssssiss', $userId, $phone, $address, $province, $district, $ward, $provinceId, $districtId, $wardCode);
        $stmt->execute();
        $stmt->close();
    }
}

function mapUrlToCoords(string $url): array {
    $txt = trim($url);
    if ($txt === '') {
        return ['lat' => null, 'lng' => null];
    }

    if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $txt, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    if (preg_match('/[?&](?:q|query|ll)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/i', $txt, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $txt, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    return ['lat' => null, 'lng' => null];
}

function geocodeAddress(string $address): array {
    $query = trim($address);
    if ($query === '') {
        return ['lat' => null, 'lng' => null, 'display_name' => ''];
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' . rawurlencode($query);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: PaintMore-Location/1.0',
        ],
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $http >= 400) {
        return ['lat' => null, 'lng' => null, 'display_name' => ''];
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json[0])) {
        return ['lat' => null, 'lng' => null, 'display_name' => ''];
    }
    $first = $json[0];
    $lat   = isset($first['lat']) ? (float)$first['lat'] : null;
    $lng   = isset($first['lon']) ? (float)$first['lon'] : null;
    return [
        'lat'          => $lat,
        'lng'          => $lng,
        'display_name' => trim((string)($first['display_name'] ?? '')),
    ];
}

function haversineKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float {
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return null;
    }
    $earth = 6371.0;
    $dLat  = deg2rad($lat2 - $lat1);
    $dLng  = deg2rad($lng2 - $lng1);
    $a     = sin($dLat / 2) * sin($dLat / 2)
             + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
    $c     = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earth * $c, 2);
}

function fetchActiveBranches(mysqli $ithanhloc, string $region = ''): array {
    $sql  = "SELECT id, branch_name, region, address_detail, hotline, map_url, sort_order FROM site_store WHERE is_active = 1";
    $rows = [];

    if ($region !== '') {
        $sql .= " AND (region = '' OR region IS NULL OR region = ?) ORDER BY sort_order ASC, id DESC";
        $stmt = $ithanhloc->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $region);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $rows[] = $r;
            }
            $stmt->close();
        }
    } else {
        $sql .= " ORDER BY sort_order ASC, id DESC";
        $res = $ithanhloc->query($sql);
        while ($res && ($r = $res->fetch_assoc())) {
            $rows[] = $r;
        }
    }

    return $rows;
}

function currentSelectedLocation(): array {
    global $ithanhloc;
    clearLegacyLocationCookies();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return fetchDefaultLocationFromDb($ithanhloc, $userId);
    }
    if (!empty($_SESSION['guest_location']) && is_array($_SESSION['guest_location'])) {
        return $_SESSION['guest_location'];
    }
    return [];
}

function currentSavedLocations(): array {
    global $ithanhloc;
    clearLegacyLocationCookies();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return fetchSavedLocationsFromDb($ithanhloc, $userId);
    }
    if (!empty($_SESSION['guest_locations']) && is_array($_SESSION['guest_locations'])) {
        return array_values(array_filter($_SESSION['guest_locations'], static fn($x) => is_array($x)));
    }
    if (!empty($_SESSION['guest_location']) && is_array($_SESSION['guest_location'])) {
        return [$_SESSION['guest_location']];
    }
    return [];
}

function persistLocations(array $locations): void {
    global $ithanhloc;
    $locations = array_values(array_filter($locations, static fn($x) => is_array($x)));
    $userId    = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        persistLocationsToDb($ithanhloc, $userId, $locations);
        return;
    }
    $_SESSION['guest_locations'] = $locations;
}

function persistSelectedLocation(array $location): void {
    global $ithanhloc;
    $userId = (int)($_SESSION['user_id'] ?? 0);
    
    if (!empty($location['region'])) {
        $_SESSION['selected_region'] = (string)$location['region'];
        $secure                      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('selected_region', (string)$location['region'], [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    
    if ($userId <= 0) {
        $loc       = $location;
        $loc['is_default'] = 1;
        $guestList = [];
        
        if (!empty($_SESSION['guest_locations']) && is_array($_SESSION['guest_locations'])) {
            $guestList = array_values(array_filter($_SESSION['guest_locations'], static fn($x) => is_array($x)));
        } elseif (!empty($_SESSION['guest_location']) && is_array($_SESSION['guest_location'])) {
            $guestList = [$_SESSION['guest_location']];
        }

        foreach ($guestList as $i => $item) {
            if (!is_array($item)) {
                unset($guestList[$i]);
                continue;
            }
            $guestList[$i]['is_default'] = 0;
        }
        $guestList = array_values($guestList);

        $locId = (string)($loc['address_id'] ?? '');
        $found = false;
        if ($locId !== '') {
            foreach ($guestList as $i => $item) {
                if ((string)($item['address_id'] ?? '') === $locId) {
                    $guestList[$i] = $loc;
                    $found         = true;
                    break;
                }
            }
        }
        
        if (!$found) {
            array_unshift($guestList, $loc);
        } else {
            usort($guestList, static function ($a, $b) use ($locId) {
                $aId = (string)($a['address_id'] ?? '');
                $bId = (string)($b['address_id'] ?? '');
                if ($aId === $locId) {
                    return -1;
                }
                if ($bId === $locId) {
                    return 1;
                }
                return 0;
            });
        }

        if (count($guestList) > 5) {
            $guestList = array_slice($guestList, 0, 5);
        }

        $_SESSION['guest_location']  = $loc;
        $_SESSION['guest_locations'] = $guestList;
        return;
    }

    persistDefaultLocationToDb($ithanhloc, $userId, $location);
}

function findBranchById(mysqli $ithanhloc, int $branchId): ?array {
    if ($branchId <= 0) {
        return null;
    }
    $stmt = $ithanhloc->prepare("SELECT id, branch_name, region, address_detail, hotline, map_url FROM site_store WHERE id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $branchId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function buildLocationPayload(array $input, ?array $branch): array {
    $region      = trim((string)($input['region'] ?? ''));
    $street      = trim((string)($input['street'] ?? ''));
    $district    = trim((string)($input['district'] ?? ''));
    $ward        = trim((string)($input['ward'] ?? ''));
    $province    = trim((string)($input['province'] ?? ''));
    $phone       = normalizePhone((string)($input['contact_phone'] ?? ''));
    $recipientName = trim((string)($input['recipient_name'] ?? ''));
    $addressType = strtolower(trim((string)($input['address_type'] ?? 'home')));
    if (!in_array($addressType, ['home', 'office'], true)) {
        $addressType = 'home';
    }
    $deliveryNote  = trim((string)($input['delivery_note'] ?? ''));
    $addressDetail = trim((string)($input['address_detail'] ?? ''));
    $addressId     = trim((string)($input['address_id'] ?? ''));
    $provinceId    = (int)($input['province_id'] ?? 0);
    $districtId    = (int)($input['district_id'] ?? 0);
    $wardCode      = trim((string)($input['ward_code'] ?? ''));

    $customerAddress = $addressDetail;
    if ($customerAddress === '') {
        $parts           = array_filter([$street, $ward, $district, $province], static fn($v) => trim((string)$v) !== '');
        $customerAddress = implode(', ', $parts);
    }

    $branchCoords = ['lat' => null, 'lng' => null];
    if ($branch) {
        $branchCoords = mapUrlToCoords((string)($branch['map_url'] ?? ''));
        if ($branchCoords['lat'] === null || $branchCoords['lng'] === null) {
            $geoBranch    = geocodeAddress((string)($branch['address_detail'] ?? ''));
            $branchCoords = ['lat' => $geoBranch['lat'], 'lng' => $geoBranch['lng']];
        }
    }

    // Ưu tiên toạ độ do người dùng GHIM trên bản đồ (chính xác hơn geocode text).
    $customerCoords = ['lat' => null, 'lng' => null];
    $inLat = isset($input['customer_lat']) ? trim((string)$input['customer_lat']) : '';
    $inLng = isset($input['customer_lng']) ? trim((string)$input['customer_lng']) : '';
    if ($inLat !== '' && $inLng !== '' && is_numeric($inLat) && is_numeric($inLng)) {
        $customerCoords = ['lat' => (float)$inLat, 'lng' => (float)$inLng];
    } elseif ($customerAddress !== '') {
        $geoCustomer    = geocodeAddress($customerAddress);
        $customerCoords = ['lat' => $geoCustomer['lat'], 'lng' => $geoCustomer['lng']];
    }

    $distanceKm = haversineKm(
        $branchCoords['lat'],
        $branchCoords['lng'],
        $customerCoords['lat'],
        $customerCoords['lng']
    );

    return [
        'address_id'       => $addressId !== '' ? $addressId : ('loc_' . substr(sha1(uniqid('', true)), 0, 12)),
        'region'           => $region,
        'branch_id'        => $branch ? (int)$branch['id'] : 0,
        'branch_name'      => $branch ? (string)($branch['branch_name'] ?? '') : '',
        'branch_region'    => $branch ? (string)($branch['region'] ?? '') : '',
        'branch_address'   => $branch ? (string)($branch['address_detail'] ?? '') : '',
        'branch_map_url'   => $branch ? (string)($branch['map_url'] ?? '') : '',
        'street'           => $street,
        'ward'             => $ward,
        'ward_code'        => $wardCode,
        'district'         => $district,
        'district_id'      => $districtId,
        'province'         => $province,
        'province_id'      => $provinceId,
        'contact_phone'    => $phone,
        'recipient_name'   => $recipientName,
        'address_type'     => $addressType,
        'delivery_note'    => $deliveryNote,
        'customer_address' => $customerAddress,
        'branch_lat'       => $branchCoords['lat'],
        'branch_lng'       => $branchCoords['lng'],
        'customer_lat'     => $customerCoords['lat'],
        'customer_lng'     => $customerCoords['lng'],
        'distance_km'      => $distanceKm,
        'updated_at'       => nowStr(),
    ];
}

function upsertLocationWithLimit(array $saved, array $payload, int $limit = 5): array {
    $id      = (string)($payload['address_id'] ?? '');
    $updated = false;

    // 1) Ưu tiên cập nhật theo address_id nếu trùng
    foreach ($saved as $idx => $loc) {
        if ((string)($loc['address_id'] ?? '') === $id && $id !== '') {
            $saved[$idx] = $payload;
            $updated     = true;
            break;
        }
    }

    // 2) Nếu là địa chỉ mới, kiểm tra trùng nội dung (fingerprint)
    if (!$updated) {
        $newFp = locationFingerprint($payload);
        if ($newFp !== '') {
            foreach ($saved as $idx => $loc) {
                $fp = locationFingerprint($loc);
                if ($fp !== '' && $fp === $newFp) {
                    // Trùng địa chỉ → dùng lại address_id cũ và cập nhật
                    $payload['address_id'] = (string)($loc['address_id'] ?? $id);
                    $saved[$idx]           = $payload;
                    $updated               = true;
                    break;
                }
            }
        }
    }

    // 3) Nếu vẫn chưa cập nhật, thêm mới (giới hạn tối đa)
    if (!$updated) {
        if (count($saved) >= $limit) {
            return ['ok' => false, 'msg' => 'Chỉ được lưu tối đa 5 địa chỉ giao hàng'];
        }
        array_unshift($saved, $payload);
    }

    return ['ok' => true, 'saved' => array_values($saved)];
}

$regionConfig  = regionOptions();
$defaultRegion = $regionConfig[0] ?? 'MIỀN BẮC';
$method        = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Trích mảng NameExtension (các biến thể tên GHN) từ raw_json để frontend match tên OSM chính xác hơn.
if (!function_exists('ghnNameAlts')) {
    function ghnNameAlts(?string $rawJson): array {
        if (!$rawJson) return [];
        $j = json_decode($rawJson, true);
        if (!is_array($j) || empty($j['NameExtension']) || !is_array($j['NameExtension'])) return [];
        $alts = [];
        foreach ($j['NameExtension'] as $alt) {
            $alt = trim((string)$alt);
            if ($alt !== '') $alts[] = $alt;
        }
        return array_values(array_unique($alts));
    }
}

if (!function_exists('normalizeDbLocationName')) {
    function normalizeDbLocationName(string $name): string {
        $s = normalizeKey($name);
        $s = preg_replace('/^(thanh pho|tinh|quan|huyen|thi xa|thi tran|phuong|xa)\s+/i', '', $s);
        return trim($s);
    }
}

if (!function_exists('extractDbNumberToken')) {
    function extractDbNumberToken(string $name): string {
        if (preg_match('/\d+/', $name, $matches)) {
            return $matches[0];
        }
        return '';
    }
}

if (!function_exists('matchDbRegionName')) {
    // $mode: 'exact' chỉ khớp tuyệt đối; 'loose' cho phép substring (dễ dính NameExtension lỗi).
    function matchDbRegionName(string $keyword, string $mainName, array $alts, bool $numAware, string $mode = 'loose'): bool {
        $kwNum = $numAware ? extractDbNumberToken($keyword) : '';
        $candidates = array_merge([$mainName], $alts);
        foreach ($candidates as $candidate) {
            $txt = normalizeDbLocationName($candidate);
            if ($txt === '') continue;
            if ($numAware) {
                $txtNum = extractDbNumberToken($txt);
                if ($kwNum !== '' && $txtNum !== '' && $kwNum !== $txtNum) {
                    continue;
                }
            }
            if ($txt === $keyword) {
                return true;
            }
            if ($mode === 'loose' && (strpos($txt, $keyword) !== false || strpos($keyword, $txt) !== false)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('resolveProvinceIdFromDb')) {
    function resolveProvinceIdFromDb(mysqli $db, string $provinceName): int {
        $keyword = normalizeDbLocationName($provinceName);
        if ($keyword === '') return 0;
        $res = $db->query("SELECT region_id, name, raw_json FROM ghn_region WHERE level = 'province'");
        $rows = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $row['_alts'] = ghnNameAlts($row['raw_json'] ?? null);
            $rows[] = $row;
        }
        // Ưu tiên khớp tuyệt đối trước, rồi mới rơi xuống substring.
        foreach (['exact', 'loose'] as $mode) {
            foreach ($rows as $row) {
                if (matchDbRegionName($keyword, $row['name'], $row['_alts'], false, $mode)) {
                    return (int)$row['region_id'];
                }
            }
        }
        return 0;
    }
}

if (!function_exists('resolveDistrictIdFromDb')) {
    function resolveDistrictIdFromDb(mysqli $db, int $provinceId, string $districtName): int {
        $keyword = normalizeDbLocationName($districtName);
        if ($keyword === '') return 0;
        $stmt = $db->prepare("SELECT region_id, name, raw_json FROM ghn_region WHERE level = 'district' AND parent_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $provinceId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($res && ($row = $res->fetch_assoc())) {
                $row['_alts'] = ghnNameAlts($row['raw_json'] ?? null);
                $rows[] = $row;
            }
            $stmt->close();
            // Ưu tiên khớp tuyệt đối trước, rồi mới rơi xuống substring.
            foreach (['exact', 'loose'] as $mode) {
                foreach ($rows as $row) {
                    if (matchDbRegionName($keyword, $row['name'], $row['_alts'], true, $mode)) {
                        return (int)$row['region_id'];
                    }
                }
            }
        }
        return 0;
    }
}

if (!function_exists('resolveWardCodeFromDb')) {
    function resolveWardCodeFromDb(mysqli $db, int $districtId, string $wardName): string {
        $keyword = normalizeDbLocationName($wardName);
        if ($keyword === '') return '';
        $stmt = $db->prepare("SELECT code, name, raw_json FROM ghn_region WHERE level = 'ward' AND parent_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $districtId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($res && ($row = $res->fetch_assoc())) {
                $row['_alts'] = ghnNameAlts($row['raw_json'] ?? null);
                $rows[] = $row;
            }
            $stmt->close();
            // Ưu tiên khớp tuyệt đối trước, rồi mới rơi xuống substring.
            foreach (['exact', 'loose'] as $mode) {
                foreach ($rows as $row) {
                    if (matchDbRegionName($keyword, $row['name'], $row['_alts'], true, $mode)) {
                        return trim((string)$row['code']);
                    }
                }
            }
        }
        return '';
    }
}

if ($method === 'GET') {
    $actionGet = trim((string)($_GET['action'] ?? ''));
    if ($actionGet === 'region_provinces') {
        $rows = [];
        $res  = $ithanhloc->query("SELECT region_id AS ProvinceID, name AS ProvinceName, raw_json FROM ghn_region WHERE level = 'province' ORDER BY name ASC");
        while ($res && ($r = $res->fetch_assoc())) {
            $r['alts'] = ghnNameAlts($r['raw_json'] ?? null);
            unset($r['raw_json']);
            $rows[] = $r;
        }
        jOut(['ok' => true, 'rows' => $rows]);
    }

    if ($actionGet === 'region_districts') {
        $provinceId = (int)($_GET['province_id'] ?? 0);
        if ($provinceId <= 0) {
            jOut(['ok' => false, 'message' => 'Thiếu province_id'], 422);
        }
        $rows = [];
        $stmt = $ithanhloc->prepare("SELECT region_id AS DistrictID, name AS DistrictName, raw_json FROM ghn_region WHERE level = 'district' AND parent_id = ? ORDER BY name ASC");
        if ($stmt) {
            $stmt->bind_param('i', $provinceId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $r['alts'] = ghnNameAlts($r['raw_json'] ?? null);
                unset($r['raw_json']);
                $rows[] = $r;
            }
            $stmt->close();
        }
        jOut(['ok' => true, 'rows' => $rows]);
    }

    if ($actionGet === 'region_wards') {
        $districtId = (int)($_GET['district_id'] ?? 0);
        if ($districtId <= 0) {
            jOut(['ok' => false, 'message' => 'Thiếu district_id'], 422);
        }
        $rows = [];
        $stmt = $ithanhloc->prepare("SELECT code AS WardCode, name AS WardName, raw_json FROM ghn_region WHERE level = 'ward' AND parent_id = ? ORDER BY name ASC");
        if ($stmt) {
            $stmt->bind_param('i', $districtId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $r['alts'] = ghnNameAlts($r['raw_json'] ?? null);
                unset($r['raw_json']);
                $rows[] = $r;
            }
            $stmt->close();
        }
        jOut(['ok' => true, 'rows' => $rows]);
    }

    $requestRegion  = trim((string)($_GET['region'] ?? ''));
    $sessionRegion  = trim((string)($_SESSION['selected_region'] ?? ''));
    $selectedRegion = in_array($requestRegion, $regionConfig, true)
        ? $requestRegion
        : (in_array($sessionRegion, $regionConfig, true) ? $sessionRegion : $defaultRegion);

    $branches = fetchActiveBranches($ithanhloc, $selectedRegion);
    jOut([
        'ok'              => true,
        'regions'         => $regionConfig,
        'selected_region' => $selectedRegion,
        'branches'        => $branches,
        'location'        => currentSelectedLocation(),
        'saved_locations' => currentSavedLocations(),
    ]);
}

if ($method !== 'POST') {
    jOut(['ok' => false, 'message' => 'Method not allowed'], 405);
}

// ── Rate Limiting cho save_address ──────────────────────────────────────────
// Chặn spam thêm/sửa địa chỉ hàng loạt: tối đa 10 lần/phút/session
(function() {
    $actionCheck = trim((string)($_POST['action'] ?? (json_decode(file_get_contents('php://input') ?: '', true)['action'] ?? 'save_address')));
    if (in_array($actionCheck, ['set_active', 'delete_address'], true)) {
        return; // Chỉ rate-limit thao tác lưu/cập nhật địa chỉ
    }

    $now    = time();
    $window = 60;   // Cửa sổ 60 giây
    $limit  = 10;   // Tối đa 10 request/phút

    $bucket = $_SESSION['_addr_rl'] ?? ['count' => 0, 'start' => $now];

    // Reset bucket nếu đã qua cửa sổ thời gian
    if (($now - (int)$bucket['start']) >= $window) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    $bucket['count']++;
    $_SESSION['_addr_rl'] = $bucket;

    if ($bucket['count'] > $limit) {
        jOut([
            'ok'      => false,
            'message' => 'Bạn đang thực hiện quá nhiều thao tác. Vui lòng chờ 1 phút trước khi thử lại.',
        ], 429);
    }
})();

$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
$region = '';

if (is_array($data) && isset($data['region'])) {
    $region = trim((string) $data['region']);
} elseif (isset($_POST['region'])) {
    $region = trim((string) $_POST['region']);
}

$regionConfig = (isset($ECOMMERCE_REGIONS) && is_array($ECOMMERCE_REGIONS) && !empty($ECOMMERCE_REGIONS))
    ? array_values($ECOMMERCE_REGIONS)
    : ['MIỀN BẮC', 'MIỀN TRUNG', 'MIỀN NAM'];

$branchId      = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : (int)($data['branch_id'] ?? 0);
$street        = trim((string)($_POST['street'] ?? ($data['street'] ?? '')));
$ward          = trim((string)($_POST['ward'] ?? ($data['ward'] ?? '')));
$district      = trim((string)($_POST['district'] ?? ($data['district'] ?? '')));
$province      = trim((string)($_POST['province'] ?? ($data['province'] ?? '')));
$provinceId    = (int)($_POST['province_id'] ?? ($data['province_id'] ?? 0));
$addressDetail = trim((string)($_POST['address_detail'] ?? ($data['address_detail'] ?? '')));
$districtId    = (int)($_POST['district_id'] ?? ($data['district_id'] ?? 0));
$wardCode      = trim((string)($_POST['ward_code'] ?? ($data['ward_code'] ?? '')));
$contactPhone  = trim((string)($_POST['contact_phone'] ?? ($data['contact_phone'] ?? '')));
$recipientName = trim((string)($_POST['recipient_name'] ?? ($data['recipient_name'] ?? '')));
$addressType   = trim((string)($_POST['address_type'] ?? ($data['address_type'] ?? 'home')));
$deliveryNote  = trim((string)($_POST['delivery_note'] ?? ($data['delivery_note'] ?? '')));
$addressId     = trim((string)($_POST['address_id'] ?? ($data['address_id'] ?? '')));
$customerLat   = trim((string)($_POST['customer_lat'] ?? ($data['customer_lat'] ?? '')));
$customerLng   = trim((string)($_POST['customer_lng'] ?? ($data['customer_lng'] ?? '')));
$action        = trim((string)($_POST['action'] ?? ($data['action'] ?? 'save_address')));

// Tự động phân giải địa chỉ GHN từ Database nếu thông tin ID bị trống hoặc để chuẩn hóa tên
if ($provinceId <= 0 && $province !== '') {
    $provinceId = resolveProvinceIdFromDb($ithanhloc, $province);
}
if ($provinceId > 0) {
    $dbProvinceName = ghnRegionNameById($ithanhloc, 'province', $provinceId);
    if ($dbProvinceName !== '') {
        $province = $dbProvinceName;
    }
}

if ($provinceId > 0 && $districtId <= 0 && $district !== '') {
    $districtId = resolveDistrictIdFromDb($ithanhloc, $provinceId, $district);
}
if ($districtId > 0) {
    $dbDistrictName = ghnRegionNameById($ithanhloc, 'district', $districtId);
    if ($dbDistrictName !== '') {
        $district = $dbDistrictName;
    }
}

if ($districtId > 0 && $wardCode === '' && $ward !== '') {
    $wardCode = resolveWardCodeFromDb($ithanhloc, $districtId, $ward);
}

// Smart fallback: If wardCode is still empty, look up the ward across all districts of the province.
// This resolves issues where Goong lists a post-merger administrative unit (like "Thành phố Thủ Đức")
// but GHN lists it under a legacy district (like "Quận 2", "Quận 9", "Quận Thủ Đức").
if ($provinceId > 0 && $wardCode === '' && $ward !== '') {
    $wardKeyword = normalizeDbLocationName($ward);
    if ($wardKeyword !== '') {
        $stmtWardMatch = $ithanhloc->prepare("
            SELECT w.code, w.parent_id, w.name, w.raw_json 
            FROM ghn_region w
            JOIN ghn_region d ON w.parent_id = d.region_id
            WHERE w.level = 'ward' 
              AND d.level = 'district' 
              AND d.parent_id = ?
        ");
        if ($stmtWardMatch) {
            $stmtWardMatch->bind_param('i', $provinceId);
            $stmtWardMatch->execute();
            $resWards = $stmtWardMatch->get_result();
            $possibleMatches = [];
            while ($resWards && ($rowWard = $resWards->fetch_assoc())) {
                $alts = ghnNameAlts($rowWard['raw_json'] ?? null);
                if (matchDbRegionName($wardKeyword, $rowWard['name'], $alts, true)) {
                    $possibleMatches[] = $rowWard;
                }
            }
            $stmtWardMatch->close();

            if (!empty($possibleMatches)) {
                // If there are multiple matches, prefer the one matching the current districtId (if any)
                $matchedWard = null;
                if ($districtId > 0) {
                    foreach ($possibleMatches as $pm) {
                        if ((int)$pm['parent_id'] === $districtId) {
                            $matchedWard = $pm;
                            break;
                        }
                    }
                }
                if (!$matchedWard) {
                    $matchedWard = $possibleMatches[0];
                }

                $wardCode = trim((string)$matchedWard['code']);
                $districtId = (int)$matchedWard['parent_id'];
                
                // Update district name and ward name to match DB exactly
                $dbDistrictName = ghnRegionNameById($ithanhloc, 'district', $districtId);
                if ($dbDistrictName !== '') {
                    $district = $dbDistrictName;
                }
                $ward = trim((string)$matchedWard['name']);
            }
        }
    }
}

if ($wardCode !== '') {
    $stmtWardName = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level = 'ward' AND code = ? LIMIT 1");
    if ($stmtWardName) {
        $stmtWardName->bind_param('s', $wardCode);
        $stmtWardName->execute();
        $wardRow = $stmtWardName->get_result()->fetch_assoc();
        $stmtWardName->close();
        $dbWardName = trim((string)($wardRow['name'] ?? ''));
        if ($dbWardName !== '') {
            $ward = $dbWardName;
        }
    }
}


// Nếu chưa có region nhưng đã có tỉnh/thành, tự map theo tỉnh → Miền Bắc/Trung/Nam
if ($region === '' && ($provinceId > 0 || $province !== '')) {
    $inferredRegion = inferRegionFromProvince($ithanhloc, $provinceId, $province, $regionConfig);
    if ($inferredRegion !== '' && in_array($inferredRegion, $regionConfig, true)) {
        $region = $inferredRegion;
    }
}

if ($region !== '' && !in_array($region, $regionConfig, true)) {
    $region = '';
}
if ($region === '') {
    $sessionRegion = trim((string)($_SESSION['selected_region'] ?? ''));
    if ($sessionRegion !== '' && in_array($sessionRegion, $regionConfig, true)) {
        $region = $sessionRegion;
    }
}

$savedLocations = currentSavedLocations();

if ($action === 'set_active') {
    $targetId = $addressId;
    if ($targetId === '') {
        jOut(['ok' => false, 'message' => 'Thiếu địa chỉ cần chọn'], 422);
    }
    $selected = null;
    foreach ($savedLocations as $loc) {
        if ((string)($loc['address_id'] ?? '') === $targetId) {
            $selected = $loc;
            break;
        }
    }
    if (!$selected) {
        jOut(['ok' => false, 'message' => 'Không tìm thấy địa chỉ đã lưu'], 404);
    }
    persistSelectedLocation($selected);
    jOut([
        'ok'              => true,
        'region'          => (string)($selected['region'] ?? $region),
        'location'        => $selected,
        'saved_locations' => $savedLocations,
    ]);
}

if ($action === 'delete_address') {
    $targetId = $addressId;
    if ($targetId === '') {
        jOut(['ok' => false, 'message' => 'Thiếu địa chỉ cần xoá'], 422);
    }

    $next = array_values(array_filter($savedLocations, static function ($loc) use ($targetId) {
        return (string)($loc['address_id'] ?? '') !== $targetId;
    }));
    persistLocations($next);

    $current = currentSelectedLocation();
    if ((string)($current['address_id'] ?? '') === $targetId) {
        $fallback = $next[0] ?? [
            'region'           => $region,
            'address_id'       => '',
            'branch_id'        => 0,
            'branch_name'      => '',
            'branch_region'    => '',
            'branch_address'   => '',
            'branch_map_url'   => '',
            'street'           => '',
            'ward'             => '',
            'district'         => '',
            'province'         => '',
            'contact_phone'    => '',
            'recipient_name'   => '',
            'address_type'     => 'home',
            'delivery_note'    => '',
            'customer_address' => '',
            'distance_km'      => null,
            'updated_at'       => nowStr(),
        ];
        persistSelectedLocation($fallback);
    }

    jOut([
        'ok'              => true,
        'saved_locations' => $next,
        'location'        => currentSelectedLocation(),
    ]);
}

$branch = findBranchById($ithanhloc, $branchId);
if ($branchId > 0 && !$branch) {
    jOut(['ok' => false, 'message' => 'Chi nhánh không hợp lệ hoặc đã bị tắt'], 422);
}
if ($branch) {
    $branchRegion = trim((string)($branch['region'] ?? ''));
    if ($region === '' && $branchRegion !== '') {
        $region = $branchRegion;
    }
    if ($branchRegion !== '' && $region !== '' && $branchRegion !== $region) {
        jOut(['ok' => false, 'message' => 'Chi nhánh không thuộc khu vực đã chọn'], 422);
    }
}

// Validate đầy đủ thông tin địa chỉ trước khi lưu
if (!in_array($action, ['set_active', 'delete_address'], true)) {
    $errors          = [];
    $normalizedPhone = normalizePhone($contactPhone);

    $checkXss = [$street, $ward, $district, $province, $addressDetail, $recipientName, $deliveryNote];
    foreach ($checkXss as $val) {
        if ($val !== '' && (preg_match('/[<>\"\'=;()]/', $val) || stripos($val, 'script') !== false)) {
            $errors[] = 'Nội dung nhập chứa ký tự không hợp lệ (không hỗ trợ các ký tự đặc biệt).';
            break;
        }
    }

    if ($region === '') {
        $errors[] = 'Vui lòng chọn khu vực (Miền Bắc/Trung/Nam).';
    }
    if ($provinceId <= 0 && $province === '') {
        $errors[] = 'Vui lòng chọn Tỉnh/Thành phố.';
    }
    if ($districtId <= 0 && $district === '') {
        $errors[] = 'Vui lòng chọn Quận/Huyện.';
    }
    if ($wardCode === '' && $ward === '') {
        $errors[] = 'Vui lòng chọn Phường/Xã.';
    }
    if ($addressDetail === '' && $street === '') {
        $errors[] = 'Vui lòng nhập địa chỉ chi tiết (số nhà, tên đường...).';
    }
    if ($recipientName === '') {
        $errors[] = 'Vui lòng nhập Họ tên người nhận.';
    }
    if ($normalizedPhone === '') {
        $errors[] = 'Vui lòng nhập Số điện thoại liên hệ.';
    } elseif (strlen($normalizedPhone) < 9) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }

    if (!empty($errors)) {
        jOut([
            'ok'      => false,
            'message' => 'Vui lòng điền đầy đủ và chính xác thông tin giao hàng.',
            'errors'  => $errors,
        ], 422);
    }
}

$payload = buildLocationPayload([
    'address_id'     => $addressId,
    'region'         => $region,
    'street'         => $street,
    'ward'           => $ward,
    'ward_code'      => $wardCode,
    'district'       => $district,
    'district_id'    => $districtId,
    'province'       => $province,
    'province_id'    => $provinceId,
    'address_detail' => $addressDetail,
    'contact_phone'  => $contactPhone,
    'recipient_name' => $recipientName,
    'address_type'   => $addressType,
    'delivery_note'  => $deliveryNote,
    'customer_lat'   => $customerLat,
    'customer_lng'   => $customerLng,
], $branch);

$upsert = upsertLocationWithLimit($savedLocations, $payload, 5);
if (!($upsert['ok'] ?? false)) {
    jOut(['ok' => false, 'message' => $upsert['msg'] ?? 'Không thể lưu địa chỉ'], 422);
}

$savedLocations = $upsert['saved'];
persistLocations($savedLocations);
persistSelectedLocation($payload);

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    // DB-level guard: ngăn chặn bypass session để thêm địa chỉ vô hạn
    $stCount = $ithanhloc->prepare("SELECT COUNT(*) AS cnt FROM user_saved_locations WHERE user_id = ?");
    if ($stCount) {
        $stCount->bind_param('i', $userId);
        $stCount->execute();
        $rowCount = $stCount->get_result()->fetch_assoc();
        $stCount->close();
        if ((int)($rowCount['cnt'] ?? 0) > 10) {
            jOut([
                'ok'      => false,
                'message' => 'Số địa chỉ lưu đã đạt giới hạn tối đa. Vui lòng xoá bớt địa chỉ cũ trước khi thêm mới.',
            ], 422);
        }
    }
    saveAddressToProfile($ithanhloc, $userId, $payload);
}

jOut([
    'ok'              => true,
    'region'          => $region,
    'location'        => $payload,
    'saved_locations' => $savedLocations,
]);
