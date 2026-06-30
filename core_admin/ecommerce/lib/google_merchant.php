<?php
/**
 * Google Merchant Center sync — đẩy sản phẩm lên Google Shopping
 * qua Content API for Shopping v2.1 endpoint /{merchantId}/products/batch.
 *
 * Xác thực: Service Account (JWT RS256 -> OAuth2 access token), KHÔNG cần SDK.
 *
 * Cấu hình lưu trong bảng `site_setting` (KHÔNG hardcode key trong source):
 *   - GOOGLE_MERCHANT_ID            : Merchant ID (vd 10678418082)
 *   - GOOGLE_MERCHANT_SA_JSON       : đường dẫn file service-account JSON, HOẶC dán
 *                                     nguyên nội dung JSON (đánh dấu is_secret=1)
 *   - GOOGLE_MERCHANT_AUTO          : '1' để tự sync khi save/delete sản phẩm
 *   - GOOGLE_MERCHANT_TARGET_COUNTRY: (tuỳ chọn) mặc định 'VN'
 *   - GOOGLE_MERCHANT_LANGUAGE      : (tuỳ chọn) mặc định 'vi'
 *   - GOOGLE_MERCHANT_CURRENCY      : (tuỳ chọn) mặc định 'VND'
 *
 * Mỗi hàm sync trả về mảng chuẩn:
 *   ['ok' => bool, 'msg' => string, 'response' => array]
 *
 * Tái dùng dữ liệu map field từ fb_catalog_build_item() (facebook_catalog.php)
 * để nhất quán giữa các kênh; chỉ khác cấu trúc payload theo chuẩn Google.
 */

require_once __DIR__ . '/facebook_catalog.php';

if (!function_exists('gmc_config')) {
    /**
     * Lấy cấu hình Google Merchant.
     * ['merchant_id','sa','target_country','language','currency','auto','ok','err']
     * 'sa' = mảng service-account đã decode (hoặc null).
     */
    function gmc_config(): array {
        $merchantId = trim((string)(app_get_config_value_by_path('GOOGLE_MERCHANT_ID') ?? ''));
        $saRaw      = trim((string)(app_get_config_value_by_path('GOOGLE_MERCHANT_SA_JSON') ?? ''));
        $auto       = trim((string)(app_get_config_value_by_path('GOOGLE_MERCHANT_AUTO') ?? ''));
        $country    = trim((string)(app_get_config_value_by_path('GOOGLE_MERCHANT_TARGET_COUNTRY') ?? '')) ?: 'VN';
        $language   = trim((string)(app_get_config_value_by_path('GOOGLE_MERCHANT_LANGUAGE') ?? '')) ?: 'vi';
        $currency   = trim((string)(app_get_config_value_by_path('GOOGLE_MERCHANT_CURRENCY') ?? '')) ?: 'VND';

        // GOOGLE_MERCHANT_SA_JSON có thể là đường dẫn file hoặc nội dung JSON trực tiếp.
        $sa = null;
        $err = '';
        if ($saRaw !== '') {
            $jsonText = '';
            if (strlen($saRaw) < 512 && @is_file($saRaw)) {
                $jsonText = (string)@file_get_contents($saRaw);
            } else {
                $jsonText = $saRaw;
            }
            $decoded = json_decode($jsonText, true);
            if (is_array($decoded) && !empty($decoded['client_email']) && !empty($decoded['private_key'])) {
                $sa = $decoded;
            } else {
                $err = 'GOOGLE_MERCHANT_SA_JSON không hợp lệ (thiếu client_email/private_key).';
            }
        } else {
            $err = 'Chưa cấu hình GOOGLE_MERCHANT_SA_JSON.';
        }
        if ($merchantId === '') {
            $err = $err ?: 'Chưa cấu hình GOOGLE_MERCHANT_ID.';
        }

        return [
            'merchant_id'    => $merchantId,
            'sa'             => $sa,
            'target_country' => strtoupper($country),
            'language'       => strtolower($language),
            'currency'       => strtoupper($currency),
            'auto'           => in_array(strtolower($auto), ['1', 'true', 'yes', 'on'], true),
            'ok'             => ($merchantId !== '' && $sa !== null),
            'err'            => $err,
        ];
    }
}

if (!function_exists('gmc_is_configured')) {
    function gmc_is_configured(): bool {
        return gmc_config()['ok'];
    }
}

if (!function_exists('gmc_ensure_synced_column')) {
    /**
     * Đảm bảo có cột gmc_synced_at (DATETIME NULL) trên ecommerce_product để
     * đánh dấu thời điểm đồng bộ Google Merchant lần cuối. Tự tạo nếu chưa có.
     */
    function gmc_ensure_synced_column(mysqli $ithanhloc): bool {
        static $checked = null;
        if ($checked !== null) return $checked;
        $res = @$ithanhloc->query("SHOW COLUMNS FROM `ecommerce_product` LIKE 'gmc_synced_at'");
        if ($res && $res->num_rows > 0) {
            $checked = true;
            return true;
        }
        $checked = (bool)@$ithanhloc->query("ALTER TABLE `ecommerce_product` ADD COLUMN `gmc_synced_at` DATETIME NULL DEFAULT NULL");
        return $checked;
    }
}

if (!function_exists('gmc_mark_synced')) {
    /**
     * Đánh dấu (hoặc bỏ đánh dấu) trạng thái đã đồng bộ cho 1 sản phẩm.
     * $synced=true -> gmc_synced_at=NOW(); false -> NULL.
     */
    function gmc_mark_synced(mysqli $ithanhloc, int $pid, bool $synced): void {
        if ($pid <= 0) return;
        if (!gmc_ensure_synced_column($ithanhloc)) return;
        if ($synced) {
            $stmt = $ithanhloc->prepare("UPDATE `ecommerce_product` SET `gmc_synced_at` = NOW() WHERE id = ?");
        } else {
            $stmt = $ithanhloc->prepare("UPDATE `ecommerce_product` SET `gmc_synced_at` = NULL WHERE id = ?");
        }
        if ($stmt) {
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('gmc_b64url')) {
    function gmc_b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('gmc_access_token')) {
    /**
     * Lấy OAuth2 access token từ service account (JWT RS256, grant jwt-bearer).
     * Cache token trong static theo thời hạn để tránh ký lại mỗi request.
     * Trả về ['ok'=>bool, 'token'=>string, 'msg'=>string].
     */
    function gmc_access_token(array $sa): array {
        static $cache = ['token' => '', 'exp' => 0, 'email' => ''];

        $email = (string)($sa['client_email'] ?? '');
        $now = time();
        if ($cache['token'] !== '' && $cache['email'] === $email && $cache['exp'] - 60 > $now) {
            return ['ok' => true, 'token' => $cache['token'], 'msg' => ''];
        }

        $tokenUri = (string)($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        $scope = 'https://www.googleapis.com/auth/content';

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim = [
            'iss'   => $email,
            'scope' => $scope,
            'aud'   => $tokenUri,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];
        $signingInput = gmc_b64url(json_encode($header, JSON_UNESCAPED_SLASHES))
            . '.' . gmc_b64url(json_encode($claim, JSON_UNESCAPED_SLASHES));

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, (string)($sa['private_key'] ?? ''), OPENSSL_ALGO_SHA256);
        if (!$ok) {
            return ['ok' => false, 'token' => '', 'msg' => 'Ký JWT thất bại (private_key không hợp lệ).'];
        }
        $jwt = $signingInput . '.' . gmc_b64url($signature);

        $post = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);
        $ch = curl_init($tokenUri);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        unset($ch);
        if ($raw === false) {
            return ['ok' => false, 'token' => '', 'msg' => 'Lỗi kết nối lấy token: ' . $curlErr];
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json) || empty($json['access_token'])) {
            $em = is_array($json) ? (string)($json['error_description'] ?? $json['error'] ?? 'không rõ') : 'phản hồi không hợp lệ';
            return ['ok' => false, 'token' => '', 'msg' => 'Không lấy được access token: ' . $em];
        }

        $cache = [
            'token' => (string)$json['access_token'],
            'exp'   => $now + (int)($json['expires_in'] ?? 3600),
            'email' => $email,
        ];
        return ['ok' => true, 'token' => $cache['token'], 'msg' => ''];
    }
}

if (!function_exists('gmc_build_product')) {
    /**
     * Map 1 bản ghi ecommerce_product sang resource product của Content API.
     * Tái dùng fb_catalog_build_item() cho giá/ảnh/mô tả/link/brand.
     * Trả về null nếu thiếu dữ liệu bắt buộc.
     */
    function gmc_build_product(mysqli $ithanhloc, array $product, array $cfg): ?array {
        $item = fb_catalog_build_item($ithanhloc, $product);
        if (!$item) return null;

        // offerId ổn định theo sản phẩm (giống retailer_id của Facebook).
        $offerId = fb_catalog_retailer_id($product);

        // Giá: fb_catalog trả "277000 VND" -> tách lấy số.
        $priceValue = preg_replace('/[^\d.]/', '', (string)$item['price']);
        if ($priceValue === '' || (float)$priceValue <= 0) return null;

        return [
            'offerId'         => $offerId,
            'title'           => $item['title'],
            'description'     => $item['description'],
            'link'            => $item['link'],
            'imageLink'       => $item['image_link'],
            'contentLanguage' => $cfg['language'],
            'targetCountry'   => $cfg['target_country'],
            'channel'         => 'online',
            'availability'    => google_merchant_availability($item['availability']),
            'condition'       => 'new',
            'brand'           => $item['brand'],
            'mpn'             => $offerId,
            'identifierExists' => false, // không có GTIN/MPN chuẩn -> báo Google bỏ qua
            'price'           => [
                'value'    => $priceValue,
                'currency' => $cfg['currency'],
            ],
        ];
    }
}

if (!function_exists('gmc_products_batch')) {
    /**
     * Gửi 1 batch entries tới products/batch.
     * $entries: [['batchId'=>int,'merchantId'=>..,'method'=>'insert|delete', ...], ...]
     */
    function gmc_products_batch(array $entries): array {
        $cfg = gmc_config();
        if (!$cfg['ok']) {
            return ['ok' => false, 'msg' => $cfg['err'] ?: 'Chưa cấu hình Google Merchant.', 'response' => []];
        }
        if (!$entries) {
            return ['ok' => false, 'msg' => 'Không có sản phẩm để đồng bộ.', 'response' => []];
        }

        $tok = gmc_access_token($cfg['sa']);
        if (!$tok['ok']) {
            return ['ok' => false, 'msg' => $tok['msg'], 'response' => []];
        }

        $url = 'https://shoppingcontent.googleapis.com/content/v2.1/products/batch';
        $payload = json_encode(['entries' => array_values($entries)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $tok['token'],
            ],
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        unset($ch);

        if ($raw === false) {
            return ['ok' => false, 'msg' => 'Lỗi kết nối Google: ' . $curlErr, 'response' => []];
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'msg' => 'Phản hồi Google không hợp lệ (HTTP ' . $httpCode . ').', 'response' => ['raw' => $raw]];
        }
        if (isset($json['error'])) {
            $em = (string)($json['error']['message'] ?? 'Lỗi không xác định');
            return ['ok' => false, 'msg' => 'Google báo lỗi: ' . $em, 'response' => $json];
        }

        // Mỗi entry có thể có 'errors' riêng dù HTTP 200.
        $entryErrors = [];
        foreach (($json['entries'] ?? []) as $e) {
            if (!empty($e['errors']['errors'])) {
                foreach ($e['errors']['errors'] as $er) {
                    $entryErrors[] = (string)($er['message'] ?? 'lỗi');
                }
            }
        }
        $okHttp = ($httpCode >= 200 && $httpCode < 300);

        return [
            'ok'       => $okHttp && empty($entryErrors),
            'msg'      => $okHttp
                ? (empty($entryErrors) ? 'Đồng bộ thành công.' : ('Một số sản phẩm lỗi: ' . implode('; ', array_slice($entryErrors, 0, 3))))
                : ('HTTP ' . $httpCode),
            'response' => $json,
        ];
    }
}

if (!function_exists('gmc_sync_product')) {
    /**
     * Đồng bộ (insert) một sản phẩm. SP tắt/ẩn -> xoá khỏi Merchant.
     */
    function gmc_sync_product(mysqli $ithanhloc, int $pid): array {
        $pid = (int)$pid;
        if ($pid <= 0) return ['ok' => false, 'msg' => 'Thiếu product_id', 'response' => []];

        $cfg = gmc_config();
        if (!$cfg['ok']) return ['ok' => false, 'msg' => $cfg['err'] ?: 'Chưa cấu hình Google Merchant.', 'response' => []];

        $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_product WHERE id=? LIMIT 1');
        if (!$stmt) return ['ok' => false, 'msg' => 'Lỗi DB', 'response' => []];
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$product) return ['ok' => false, 'msg' => 'Không tìm thấy sản phẩm', 'response' => []];

        $status = strtolower(trim((string)($product['status'] ?? '')));
        $isActive = in_array($status, ['1', 'true', 'on', 'yes'], true);
        if (!$isActive) {
            return gmc_delete_product($ithanhloc, $pid, $product);
        }

        $resource = gmc_build_product($ithanhloc, $product, $cfg);
        if (!$resource) {
            return ['ok' => false, 'msg' => 'Sản phẩm chưa đủ dữ liệu để đồng bộ (cần giá > 0 và ảnh).', 'response' => []];
        }

        $r = gmc_products_batch([[
            'batchId'    => 1,
            'merchantId' => $cfg['merchant_id'],
            'method'     => 'insert',
            'product'    => $resource,
        ]]);
        if ($r['ok']) gmc_mark_synced($ithanhloc, $pid, true);
        return $r;
    }
}

if (!function_exists('gmc_product_rest_id')) {
    /**
     * REST id của product trên Content API: online:{lang}:{country}:{offerId}
     */
    function gmc_product_rest_id(string $offerId, array $cfg): string {
        return 'online:' . $cfg['language'] . ':' . $cfg['target_country'] . ':' . $offerId;
    }
}

if (!function_exists('gmc_delete_product')) {
    /**
     * Xoá một sản phẩm khỏi Merchant. $product (tuỳ chọn) để lấy offerId.
     */
    function gmc_delete_product(mysqli $ithanhloc, int $pid, ?array $product = null): array {
        $pid = (int)$pid;
        if ($pid <= 0) return ['ok' => false, 'msg' => 'Thiếu product_id', 'response' => []];

        $cfg = gmc_config();
        if (!$cfg['ok']) return ['ok' => false, 'msg' => $cfg['err'] ?: 'Chưa cấu hình Google Merchant.', 'response' => []];

        if ($product === null) {
            $stmt = $ithanhloc->prepare('SELECT id, sku FROM ecommerce_product WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $pid);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc() ?: ['id' => $pid];
                $stmt->close();
            } else {
                $product = ['id' => $pid];
            }
        }
        $offerId = fb_catalog_retailer_id($product);

        $r = gmc_products_batch([[
            'batchId'    => 1,
            'merchantId' => $cfg['merchant_id'],
            'method'     => 'delete',
            'productId'  => gmc_product_rest_id($offerId, $cfg),
        ]]);
        if ($r['ok']) gmc_mark_synced($ithanhloc, $pid, false);
        return $r;
    }
}

if (!function_exists('gmc_sync_all')) {
    /**
     * Đồng bộ hàng loạt SP thuộc danh mục feed (giống phạm vi feed Facebook/Google XML),
     * chia batch để tránh payload quá lớn.
     * Trả về ['ok','msg','synced','skipped','batches'].
     */
    function gmc_sync_all(mysqli $ithanhloc, int $batchSize = 100): array {
        $cfg = gmc_config();
        if (!$cfg['ok']) {
            return ['ok' => false, 'msg' => $cfg['err'] ?: 'Chưa cấu hình Google Merchant.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];
        }

        // Cùng phạm vi danh mục với feed (mặc định hang-cu, hang-moi).
        $catSlugs = fb_catalog_feed_category_slugs();
        $catIds = [];
        if ($catSlugs) {
            $inSlug = "'" . implode("','", array_map([$ithanhloc, 'real_escape_string'], $catSlugs)) . "'";
            $rc = $ithanhloc->query("SELECT id FROM ecommerce_category WHERE LOWER(slug) IN ($inSlug)");
            if ($rc) { while ($r = $rc->fetch_assoc()) { $catIds[] = (int)$r['id']; } }
        }
        if (!$catIds) {
            return ['ok' => false, 'msg' => 'Không tìm thấy danh mục feed để đồng bộ.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];
        }
        $inIds = implode(',', $catIds);
        $res = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE category_id IN ($inIds) AND LOWER(TRIM(CAST(status AS CHAR))) IN ('1','true','on','yes') ORDER BY id ASC");
        if (!$res) return ['ok' => false, 'msg' => 'Lỗi DB khi đọc sản phẩm.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];

        gmc_ensure_synced_column($ithanhloc);
        $entries = [];
        $batchMap = [];
        $synced = 0; $skipped = 0; $batches = 0; $errors = []; $okPids = [];
        $batchId = 0;
        $flush = function () use (&$entries, &$batchMap, &$batches, &$errors, &$okPids) {
            if (!$entries) return;
            $r = gmc_products_batch($entries);
            $batches++;
            if ($r['ok']) { foreach ($batchMap as $pid) { $okPids[] = $pid; } }
            else { $errors[] = $r['msg']; }
            $entries = [];
            $batchMap = [];
        };

        while ($product = $res->fetch_assoc()) {
            $resource = gmc_build_product($ithanhloc, $product, $cfg);
            if (!$resource) { $skipped++; continue; }
            $bid = ++$batchId;
            $entries[] = [
                'batchId'    => $bid,
                'merchantId' => $cfg['merchant_id'],
                'method'     => 'insert',
                'product'    => $resource,
            ];
            $batchMap[$bid] = (int)$product['id'];
            $synced++;
            if (count($entries) >= $batchSize) $flush();
        }
        $flush();

        foreach ($okPids as $pid) { gmc_mark_synced($ithanhloc, $pid, true); }

        return [
            'ok'      => empty($errors),
            'msg'     => empty($errors)
                ? ("Đã đồng bộ $synced sản phẩm (bỏ qua $skipped chưa đủ dữ liệu).")
                : ('Có lỗi ở 1 số batch: ' . implode('; ', array_slice($errors, 0, 3))),
            'synced'  => $synced,
            'skipped' => $skipped,
            'batches' => $batches,
        ];
    }
}

if (!function_exists('gmc_list_products_for_picker')) {
    /**
     * Danh sách sản phẩm để chọn đồng bộ (kèm trạng thái đã sync).
     * $catId = 0 -> tất cả danh mục. Trả ['ok','products'=>[{id,product_name,sku,image_url,status,synced,synced_at}]].
     */
    function gmc_list_products_for_picker(mysqli $ithanhloc, int $catId = 0): array {
        gmc_ensure_synced_column($ithanhloc);
        $where = '1=1';
        $params = [];
        $types = '';
        if ($catId > 0) {
            $where .= ' AND p.category_id = ?';
            $params[] = $catId;
            $types .= 'i';
        }
        $sql = "SELECT p.id, p.product_name, p.image_url, p.status, p.gmc_synced_at,
                       (SELECT v.sku_variant FROM ecommerce_product_variants v
                          WHERE v.product_id = p.id AND TRIM(COALESCE(v.sku_variant,'')) <> ''
                          ORDER BY v.price ASC, v.id ASC LIMIT 1) AS sku
                FROM ecommerce_product p
                WHERE $where
                ORDER BY p.product_name ASC
                LIMIT 500";
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) return ['ok' => false, 'products' => [], 'msg' => 'Lỗi DB'];
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            $rows[] = [
                'id'           => (int)$row['id'],
                'product_name' => (string)$row['product_name'],
                'sku'          => (string)($row['sku'] ?? ''),
                'image_url'    => (string)($row['image_url'] ?? ''),
                'active'       => in_array($status, ['1', 'true', 'on', 'yes'], true),
                'synced'       => !empty($row['gmc_synced_at']),
                'synced_at'    => (string)($row['gmc_synced_at'] ?? ''),
            ];
        }
        $stmt->close();
        return ['ok' => true, 'products' => $rows];
    }
}

if (!function_exists('gmc_sync_ids')) {
    /**
     * Đồng bộ (insert) danh sách sản phẩm theo ID, chia batch.
     * Đánh dấu gmc_synced_at cho từng SP thành công.
     * Trả về ['ok','msg','synced','skipped','batches'].
     */
    function gmc_sync_ids(mysqli $ithanhloc, array $ids, int $batchSize = 100): array {
        $cfg = gmc_config();
        if (!$cfg['ok']) {
            return ['ok' => false, 'msg' => $cfg['err'] ?: 'Chưa cấu hình Google Merchant.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) return ['ok' => false, 'msg' => 'Chưa chọn sản phẩm nào.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];

        gmc_ensure_synced_column($ithanhloc);
        $inIds = implode(',', $ids);
        $res = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE id IN ($inIds) ORDER BY id ASC");
        if (!$res) return ['ok' => false, 'msg' => 'Lỗi DB khi đọc sản phẩm.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];

        $entries = [];        // [batchId => pid] để đánh dấu sau khi batch ok
        $batchMap = [];
        $synced = 0; $skipped = 0; $batches = 0; $errors = [];
        $batchId = 0;
        $okPids = [];

        $flush = function () use (&$entries, &$batchMap, &$batches, &$errors, &$okPids, $ithanhloc) {
            if (!$entries) return;
            $r = gmc_products_batch($entries);
            $batches++;
            if ($r['ok']) {
                foreach ($batchMap as $pid) { $okPids[] = $pid; }
            } else {
                $errors[] = $r['msg'];
            }
            $entries = [];
            $batchMap = [];
        };

        while ($product = $res->fetch_assoc()) {
            $pid = (int)$product['id'];
            // SP tắt -> xoá khỏi Merchant thay vì insert (đồng nhất với gmc_sync_product)
            $status = strtolower(trim((string)($product['status'] ?? '')));
            if (!in_array($status, ['1', 'true', 'on', 'yes'], true)) {
                $dr = gmc_delete_product($ithanhloc, $pid, $product);
                if (!$dr['ok']) { $skipped++; }
                continue;
            }
            $resource = gmc_build_product($ithanhloc, $product, $cfg);
            if (!$resource) { $skipped++; continue; }
            $bid = ++$batchId;
            $entries[] = [
                'batchId'    => $bid,
                'merchantId' => $cfg['merchant_id'],
                'method'     => 'insert',
                'product'    => $resource,
            ];
            $batchMap[$bid] = $pid;
            $synced++;
            if (count($entries) >= $batchSize) $flush();
        }
        $flush();

        foreach ($okPids as $pid) { gmc_mark_synced($ithanhloc, $pid, true); }

        return [
            'ok'      => empty($errors),
            'msg'     => empty($errors)
                ? ("Đã đồng bộ $synced sản phẩm (bỏ qua $skipped chưa đủ dữ liệu).")
                : ('Có lỗi ở 1 số batch: ' . implode('; ', array_slice($errors, 0, 3))),
            'synced'  => $synced,
            'skipped' => $skipped,
            'batches' => $batches,
        ];
    }
}

if (!function_exists('gmc_delete_ids')) {
    /**
     * Xoá danh sách sản phẩm theo ID khỏi Google Merchant (GIỮ nguyên trong DB),
     * bỏ đánh dấu gmc_synced_at. Trả về ['ok','msg','deleted','failed'].
     */
    function gmc_delete_ids(mysqli $ithanhloc, array $ids): array {
        $cfg = gmc_config();
        if (!$cfg['ok']) {
            return ['ok' => false, 'msg' => $cfg['err'] ?: 'Chưa cấu hình Google Merchant.', 'deleted' => 0, 'failed' => 0];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) return ['ok' => false, 'msg' => 'Chưa chọn sản phẩm nào.', 'deleted' => 0, 'failed' => 0];

        $deleted = 0; $failed = 0; $errors = [];
        foreach ($ids as $pid) {
            $r = gmc_delete_product($ithanhloc, $pid); // tự bỏ đánh dấu khi ok
            if ($r['ok']) { $deleted++; }
            else { $failed++; if (count($errors) < 3) $errors[] = $r['msg']; }
        }

        return [
            'ok'      => ($failed === 0),
            'msg'     => ($failed === 0)
                ? ("Đã xoá $deleted sản phẩm khỏi Google Merchant.")
                : ("Xoá được $deleted, lỗi $failed: " . implode('; ', $errors)),
            'deleted' => $deleted,
            'failed'  => $failed,
        ];
    }
}

if (!function_exists('gmc_auto_sync')) {
    /**
     * Gọi sau khi save/delete sản phẩm. Chỉ chạy nếu bật GOOGLE_MERCHANT_AUTO.
     * Nuốt mọi lỗi để KHÔNG làm hỏng luồng lưu sản phẩm chính.
     * $event: 'save' | 'delete'
     */
    function gmc_auto_sync(mysqli $ithanhloc, int $pid, string $event = 'save'): void {
        try {
            $cfg = gmc_config();
            if (!$cfg['ok'] || !$cfg['auto']) return;
            if ($event === 'delete') {
                gmc_delete_product($ithanhloc, $pid);
            } else {
                gmc_sync_product($ithanhloc, $pid);
            }
        } catch (Throwable $ignore) {
            // im lặng — auto sync không được phép chặn nghiệp vụ chính
        }
    }
}
