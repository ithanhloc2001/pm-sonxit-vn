<?php
/**
 * Facebook Catalog sync — đồng bộ sản phẩm lên Facebook Product Catalog
 * qua Graph API endpoint /{catalog_id}/items_batch.
 *
 * Cấu hình lưu trong bảng `site_setting` (KHÔNG hardcode token trong source):
 *   - FACEBOOK_CATALOG_ID       : id catalog
 *   - FACEBOOK_CATALOG_TOKEN    : access token (đánh dấu is_secret=1)
 *   - FACEBOOK_GRAPH_VERSION    : (tuỳ chọn) mặc định v25.0
 *   - FACEBOOK_CATALOG_AUTO     : '1' để tự động sync khi save/delete sản phẩm
 *
 * Mỗi hàm sync trả về mảng chuẩn:
 *   ['ok' => bool, 'msg' => string, 'handles' => array, 'response' => array]
 *
 * Liên hệ DB: dùng ecommerce_product + ecommerce_product_variants.
 * retailer_id (id phía Facebook) = sku sản phẩm nếu có, fallback 'pm-<product_id>'.
 */

if (!function_exists('fb_catalog_graph_version')) {
    function fb_catalog_graph_version(): string {
        $v = trim((string)(app_get_config_value_by_path('FACEBOOK_GRAPH_VERSION') ?? ''));
        return $v !== '' ? $v : 'v25.0';
    }
}

if (!function_exists('fb_catalog_config')) {
    /**
     * Lấy cấu hình catalog. ['catalog_id', 'token', 'version', 'auto', 'ok']
     */
    function fb_catalog_config(): array {
        $catalogId = trim((string)(app_get_config_value_by_path('FACEBOOK_CATALOG_ID') ?? ''));
        $token     = trim((string)(app_get_config_value_by_path('FACEBOOK_CATALOG_TOKEN') ?? ''));
        $auto      = trim((string)(app_get_config_value_by_path('FACEBOOK_CATALOG_AUTO') ?? ''));
        return [
            'catalog_id' => $catalogId,
            'token'      => $token,
            'version'    => fb_catalog_graph_version(),
            'auto'       => in_array(strtolower($auto), ['1', 'true', 'yes', 'on'], true),
            'ok'         => ($catalogId !== '' && $token !== ''),
        ];
    }
}

if (!function_exists('fb_catalog_is_configured')) {
    function fb_catalog_is_configured(): bool {
        return fb_catalog_config()['ok'];
    }
}

if (!function_exists('fb_catalog_clean_html')) {
    /**
     * Chuyển mô tả HTML (từ trình soạn thảo) thành plain text sạch cho Facebook:
     * - <br>, </p>, </div>, </li> -> xuống dòng
     * - <li> -> "- " (gạch đầu dòng)
     * - bỏ mọi thẻ còn lại, decode entity (&agrave; &nbsp; ...)
     * - gom khoảng trắng / dòng trống thừa
     */
    function fb_catalog_clean_html(string $html): string {
        $s = $html;
        // chuẩn hoá ngắt dòng theo block/break
        $s = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $s);
        $s = preg_replace('#<\s*/\s*(p|div|h[1-6]|tr)\s*>#i', "\n", $s);
        $s = preg_replace('#<\s*li[^>]*>#i', "- ", $s);
        $s = preg_replace('#<\s*/\s*li\s*>#i', "\n", $s);
        // bỏ thẻ còn lại
        $s = strip_tags($s);
        // decode &agrave; &nbsp; &amp; ...
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\xC2\xA0", ' ', $s); // nbsp -> space
        // gom khoảng trắng trong từng dòng, bỏ dòng trống thừa
        $lines = preg_split('/\r\n|\r|\n/', $s);
        $out = [];
        foreach ($lines as $ln) {
            $ln = trim(preg_replace('/[ \t]+/', ' ', $ln));
            if ($ln !== '') $out[] = $ln;
        }
        return trim(implode("\n", $out));
    }
}

if (!function_exists('fb_catalog_retailer_id')) {
    /**
     * Mã định danh sản phẩm phía Facebook (retailer_id). Ổn định theo sản phẩm.
     */
    function fb_catalog_retailer_id(array $product): string {
        $sku = trim((string)($product['sku'] ?? ''));
        if ($sku !== '') return $sku;
        return 'pm-' . (int)($product['id'] ?? 0);
    }
}

if (!function_exists('fb_catalog_build_item')) {
    /**
     * Map 1 bản ghi ecommerce_product (+ variant rẻ nhất) sang payload Facebook.
     * Trả về null nếu sản phẩm thiếu dữ liệu bắt buộc (giá / ảnh).
     */
    function fb_catalog_build_item(mysqli $ithanhloc, array $product): ?array {
        $pid = (int)($product['id'] ?? 0);
        if ($pid <= 0) return null;

        global $baseUrl;
        $base = (string)($baseUrl ?? '');

        // Giá + tồn kho: lấy từ biến thể (giá nhỏ nhất, tổng tồn kho)
        $price = 0.0;
        $totalStock = 0;
        $stmt = $ithanhloc->prepare('SELECT MIN(price) AS min_price, SUM(GREATEST(stock_quantity,0)) AS sqty FROM ecommerce_product_variants WHERE product_id=?');
        if ($stmt) {
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $price = (float)($row['min_price'] ?? 0);
            $totalStock = (int)($row['sqty'] ?? 0);
        }
        if ($price <= 0) return null; // Facebook yêu cầu giá > 0

        // Ảnh
        $imgRaw = trim((string)($product['image_url'] ?? ''));
        $imageLink = $imgRaw !== '' ? app_get_media_url($imgRaw) : '';
        if ($imageLink === '') return null; // bắt buộc có ảnh

        // availability: hàng đặt trước -> 'available for order'.
        // Ưu tiên cờ ngay trong $product (SELECT *) để phản ánh đúng dữ liệu vừa lưu;
        // fallback sang helper nếu cột không có trong $product.
        $isPreorder = array_key_exists('preorder_enabled', $product)
            ? ((int)$product['preorder_enabled'] === 1)
            : (function_exists('ecommerce_product_is_preorder') && ecommerce_product_is_preorder($ithanhloc, $pid));
        if ($isPreorder) {
            $availability = 'available for order';
        } else {
            $availability = $totalStock > 0 ? 'in stock' : 'out of stock';
        }

        $name = trim((string)($product['product_name'] ?? '')) ?: ('Sản phẩm #' . $pid);
        $descRaw = trim((string)($product['description'] ?? ''));
        $description = $descRaw !== '' ? fb_catalog_clean_html($descRaw) : $name;
        if ($description === '') $description = $name;
        $brand = trim((string)($product['manufacturer'] ?? '')) ?: 'Paint & More';
        $link = app_build_product_detail_link($pid, $base);

        return [
            'id'           => fb_catalog_retailer_id($product),
            'title'        => mb_substr($name, 0, 150),
            'description'  => mb_substr($description, 0, 5000),
            'price'        => round($price) . ' VND',
            'image_link'   => $imageLink,
            'link'         => $link,
            'availability' => $availability,
            'condition'    => 'new',
            'brand'        => mb_substr($brand, 0, 100),
        ];
    }
}

if (!function_exists('fb_catalog_send_batch')) {
    /**
     * Gửi 1 batch requests tới items_batch.
     * $requests: mảng [['method'=>'CREATE|UPDATE|DELETE','data'=>[...]], ...]
     */
    function fb_catalog_send_batch(array $requests): array {
        $cfg = fb_catalog_config();
        if (!$cfg['ok']) {
            return ['ok' => false, 'msg' => 'Chưa cấu hình Facebook Catalog (thiếu FACEBOOK_CATALOG_ID / FACEBOOK_CATALOG_TOKEN).', 'handles' => [], 'response' => []];
        }
        if (!$requests) {
            return ['ok' => false, 'msg' => 'Không có sản phẩm để đồng bộ.', 'handles' => [], 'response' => []];
        }

        $url = 'https://graph.facebook.com/' . $cfg['version'] . '/' . rawurlencode($cfg['catalog_id']) . '/items_batch';
        $postFields = [
            'item_type'    => 'PRODUCT_ITEM',
            'requests'     => json_encode(array_values($requests), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'access_token' => $cfg['token'],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        unset($ch); // PHP 8: CurlHandle tự giải phóng, không cần curl_close()

        if ($raw === false) {
            return ['ok' => false, 'msg' => 'Lỗi kết nối Facebook: ' . $curlErr, 'handles' => [], 'response' => []];
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'msg' => 'Phản hồi Facebook không hợp lệ (HTTP ' . $httpCode . ').', 'handles' => [], 'response' => ['raw' => $raw]];
        }
        if (isset($json['error'])) {
            $em = (string)($json['error']['message'] ?? 'Lỗi không xác định');
            return ['ok' => false, 'msg' => 'Facebook báo lỗi: ' . $em, 'handles' => [], 'response' => $json];
        }

        return [
            'ok'       => ($httpCode >= 200 && $httpCode < 300),
            'msg'      => ($httpCode >= 200 && $httpCode < 300) ? 'Đồng bộ thành công.' : ('HTTP ' . $httpCode),
            'handles'  => $json['handles'] ?? [],
            'response' => $json,
        ];
    }
}

if (!function_exists('fb_catalog_sync_product')) {
    /**
     * Đồng bộ (CREATE/UPDATE) một sản phẩm. Sản phẩm tắt/ẩn -> bỏ qua (không lỗi).
     */
    function fb_catalog_sync_product(mysqli $ithanhloc, int $pid): array {
        $pid = (int)$pid;
        if ($pid <= 0) return ['ok' => false, 'msg' => 'Thiếu product_id', 'handles' => [], 'response' => []];

        $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_product WHERE id=? LIMIT 1');
        if (!$stmt) return ['ok' => false, 'msg' => 'Lỗi DB', 'handles' => [], 'response' => []];
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$product) return ['ok' => false, 'msg' => 'Không tìm thấy sản phẩm', 'handles' => [], 'response' => []];

        // Sản phẩm không bật (status != true) -> xoá khỏi catalog để không hiển thị
        $status = strtolower(trim((string)($product['status'] ?? '')));
        $isActive = in_array($status, ['1', 'true', 'on', 'yes'], true);
        if (!$isActive) {
            return fb_catalog_delete_product($ithanhloc, $pid, $product);
        }

        $item = fb_catalog_build_item($ithanhloc, $product);
        if (!$item) {
            return ['ok' => false, 'msg' => 'Sản phẩm chưa đủ dữ liệu để đồng bộ (cần giá > 0 và ảnh).', 'handles' => [], 'response' => []];
        }

        return fb_catalog_send_batch([['method' => 'UPDATE', 'data' => $item]]);
    }
}

if (!function_exists('fb_catalog_delete_product')) {
    /**
     * Xoá một sản phẩm khỏi catalog. $product (tuỳ chọn) để lấy retailer_id; nếu
     * không truyền sẽ tự đọc DB.
     */
    function fb_catalog_delete_product(mysqli $ithanhloc, int $pid, ?array $product = null): array {
        $pid = (int)$pid;
        if ($pid <= 0) return ['ok' => false, 'msg' => 'Thiếu product_id', 'handles' => [], 'response' => []];

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
        $retailerId = fb_catalog_retailer_id($product);
        return fb_catalog_send_batch([['method' => 'DELETE', 'data' => ['id' => $retailerId]]]);
    }
}

if (!function_exists('fb_catalog_sync_all')) {
    /**
     * Đồng bộ hàng loạt mọi sản phẩm đang bật (chia batch để tránh payload quá lớn).
     * Trả về ['ok', 'msg', 'synced', 'skipped', 'batches'].
     */
    function fb_catalog_sync_all(mysqli $ithanhloc, int $batchSize = 100): array {
        if (!fb_catalog_is_configured()) {
            return ['ok' => false, 'msg' => 'Chưa cấu hình Facebook Catalog.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];
        }
        $res = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE LOWER(TRIM(CAST(status AS CHAR))) IN ('1','true','on','yes') ORDER BY id ASC");
        if (!$res) return ['ok' => false, 'msg' => 'Lỗi DB khi đọc sản phẩm.', 'synced' => 0, 'skipped' => 0, 'batches' => 0];

        $requests = [];
        $synced = 0; $skipped = 0; $batches = 0; $errors = [];
        $flush = function () use (&$requests, &$batches, &$errors) {
            if (!$requests) return;
            $r = fb_catalog_send_batch($requests);
            $batches++;
            if (!$r['ok']) $errors[] = $r['msg'];
            $requests = [];
        };

        while ($product = $res->fetch_assoc()) {
            $item = fb_catalog_build_item($ithanhloc, $product);
            if (!$item) { $skipped++; continue; }
            $requests[] = ['method' => 'UPDATE', 'data' => $item];
            $synced++;
            if (count($requests) >= $batchSize) $flush();
        }
        $flush();

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

if (!function_exists('fb_catalog_auto_sync')) {
    /**
     * Gọi sau khi save/delete sản phẩm. Chỉ chạy nếu bật FACEBOOK_CATALOG_AUTO.
     * Nuốt mọi lỗi để KHÔNG làm hỏng luồng lưu sản phẩm chính.
     * $event: 'save' | 'delete'
     */
    function fb_catalog_auto_sync(mysqli $ithanhloc, int $pid, string $event = 'save'): void {
        try {
            $cfg = fb_catalog_config();
            if (!$cfg['ok'] || !$cfg['auto']) return;
            if ($event === 'delete') {
                fb_catalog_delete_product($ithanhloc, $pid);
            } else {
                fb_catalog_sync_product($ithanhloc, $pid);
            }
        } catch (Throwable $ignore) {
            // im lặng — auto sync không được phép chặn nghiệp vụ chính
        }
    }
}

if (!function_exists('fb_catalog_feed_category_slugs')) {
    /**
     * Danh sách slug danh mục được đưa vào feed Facebook.
     * Mặc định: "Hàng Cũ" (hang-cu) + "Hàng mới" (hang-moi).
     * Có thể override qua site_setting 'FACEBOOK_FEED_CATEGORY_SLUGS' (phân tách bằng dấu phẩy).
     */
    function fb_catalog_feed_category_slugs(): array {
        $raw = trim((string)(app_get_config_value_by_path('FACEBOOK_FEED_CATEGORY_SLUGS') ?? ''));
        if ($raw !== '') {
            $slugs = array_filter(array_map(fn($s) => strtolower(trim($s)), explode(',', $raw)));
            if ($slugs) return array_values($slugs);
        }
        return ['hang-cu', 'hang-moi'];
    }
}

if (!function_exists('fb_catalog_feed_xml')) {
    /**
     * Xuất feed XML (RSS 2.0 + namespace Google Merchant) cho Facebook Catalog.
     * Dùng cho phương án "Data Feed": Facebook tự kéo từ URL, KHÔNG cần app/token.
     * Tái dùng fb_catalog_build_item() để map field nhất quán với cách sync API.
     *
     * Trả về chuỗi XML hoàn chỉnh.
     */
    function fb_catalog_feed_xml(mysqli $ithanhloc): string {
        global $baseUrl;
        $shopTitle = 'Paint & More';
        $shopLink = rtrim((string)($baseUrl ?? ''), '/');

        // Chỉ lấy sản phẩm đang bán thuộc danh mục "Hàng Cũ" / "Hàng mới".
        // Lọc theo slug (bền hơn id); nếu DB chưa có 2 danh mục này thì feed rỗng.
        $catSlugs = fb_catalog_feed_category_slugs();
        $catIds = [];
        if ($catSlugs) {
            $inSlug = "'" . implode("','", array_map([$ithanhloc, 'real_escape_string'], $catSlugs)) . "'";
            $rc = $ithanhloc->query("SELECT id FROM ecommerce_category WHERE LOWER(slug) IN ($inSlug)");
            if ($rc) { while ($r = $rc->fetch_assoc()) { $catIds[] = (int)$r['id']; } }
        }

        $items = '';
        if (!$catIds) {
            // Không tìm thấy danh mục cấu hình -> không xuất sản phẩm nào (an toàn)
            $res = false;
        } else {
            $inIds = implode(',', $catIds);
            $res = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE category_id IN ($inIds) AND LOWER(TRIM(CAST(status AS CHAR))) IN ('1','true','on','yes') ORDER BY id ASC");
        }
        if ($res) {
            while ($product = $res->fetch_assoc()) {
                $item = fb_catalog_build_item($ithanhloc, $product);
                if (!$item) continue; // thiếu giá/ảnh -> bỏ qua (Facebook bắt buộc)

                // Google Merchant feed dùng giá kèm tiền tệ: "277000 VND"
                $items .= "    <item>\n"
                    . '      <g:id>' . fb_xml_esc($item['id']) . "</g:id>\n"
                    . '      <g:title>' . fb_xml_esc($item['title']) . "</g:title>\n"
                    . '      <g:description>' . fb_xml_esc($item['description']) . "</g:description>\n"
                    . '      <g:link>' . fb_xml_esc($item['link']) . "</g:link>\n"
                    . '      <g:image_link>' . fb_xml_esc($item['image_link']) . "</g:image_link>\n"
                    . '      <g:availability>' . fb_xml_esc($item['availability']) . "</g:availability>\n"
                    . '      <g:price>' . fb_xml_esc($item['price']) . "</g:price>\n"
                    . '      <g:condition>' . fb_xml_esc($item['condition']) . "</g:condition>\n"
                    . '      <g:brand>' . fb_xml_esc($item['brand']) . "</g:brand>\n"
                    . "    </item>\n";
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n"
            . "  <channel>\n"
            . '    <title>' . fb_xml_esc($shopTitle) . "</title>\n"
            . '    <link>' . fb_xml_esc($shopLink) . "</link>\n"
            . "    <description>Product feed for Facebook Catalog</description>\n"
            . $items
            . "  </channel>\n"
            . "</rss>\n";
    }
}

if (!function_exists('fb_xml_esc')) {
    function fb_xml_esc(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('google_merchant_availability')) {
    /**
     * Map availability kiểu Facebook (có khoảng trắng) sang chuẩn Google Merchant
     * (gạch dưới). Google chỉ chấp nhận: in_stock | out_of_stock | preorder | backorder.
     */
    function google_merchant_availability(string $fbAvailability): string {
        switch (strtolower(trim($fbAvailability))) {
            case 'in stock':
                return 'in_stock';
            case 'available for order':
                return 'preorder';
            case 'out of stock':
            default:
                return 'out_of_stock';
        }
    }
}

if (!function_exists('google_merchant_feed_xml')) {
    /**
     * Xuất feed XML chuẩn Google Merchant Center (RSS 2.0 + namespace g:).
     * Dùng cho Google Merchant → Products → Feeds (Google tự kéo URL).
     *
     * Tái dùng fb_catalog_build_item() để map field nhất quán với feed Facebook,
     * chỉ khác: availability dạng gạch dưới, có g:mpn + g:identifier_exists (vì DB
     * không có GTIN/barcode thật).
     *
     * Trả về chuỗi XML hoàn chỉnh.
     */
    function google_merchant_feed_xml(mysqli $ithanhloc): string {
        global $baseUrl;
        $shopTitle = 'Paint & More';
        $shopLink = rtrim((string)($baseUrl ?? ''), '/');

        // Cùng phạm vi danh mục với feed Facebook (mặc định hang-cu, hang-moi).
        $catSlugs = fb_catalog_feed_category_slugs();
        $catIds = [];
        if ($catSlugs) {
            $inSlug = "'" . implode("','", array_map([$ithanhloc, 'real_escape_string'], $catSlugs)) . "'";
            $rc = $ithanhloc->query("SELECT id FROM ecommerce_category WHERE LOWER(slug) IN ($inSlug)");
            if ($rc) { while ($r = $rc->fetch_assoc()) { $catIds[] = (int)$r['id']; } }
        }

        $items = '';
        if (!$catIds) {
            $res = false; // không có danh mục cấu hình -> feed rỗng (an toàn)
        } else {
            $inIds = implode(',', $catIds);
            $res = $ithanhloc->query("SELECT * FROM ecommerce_product WHERE category_id IN ($inIds) AND LOWER(TRIM(CAST(status AS CHAR))) IN ('1','true','on','yes') ORDER BY id ASC");
        }
        if ($res) {
            while ($product = $res->fetch_assoc()) {
                $item = fb_catalog_build_item($ithanhloc, $product);
                if (!$item) continue; // thiếu giá/ảnh -> bỏ qua

                $mpn = fb_catalog_retailer_id($product); // SKU hoặc 'pm-<id>'

                $items .= "    <item>\n"
                    . '      <g:id>' . fb_xml_esc($item['id']) . "</g:id>\n"
                    . '      <g:title>' . fb_xml_esc($item['title']) . "</g:title>\n"
                    . '      <g:description>' . fb_xml_esc($item['description']) . "</g:description>\n"
                    . '      <g:link>' . fb_xml_esc($item['link']) . "</g:link>\n"
                    . '      <g:image_link>' . fb_xml_esc($item['image_link']) . "</g:image_link>\n"
                    . '      <g:condition>' . fb_xml_esc($item['condition']) . "</g:condition>\n"
                    . '      <g:availability>' . fb_xml_esc(google_merchant_availability($item['availability'])) . "</g:availability>\n"
                    . '      <g:price>' . fb_xml_esc($item['price']) . "</g:price>\n"
                    . '      <g:brand>' . fb_xml_esc($item['brand']) . "</g:brand>\n"
                    . '      <g:mpn>' . fb_xml_esc($mpn) . "</g:mpn>\n"
                    . "      <g:identifier_exists>no</g:identifier_exists>\n"
                    . "    </item>\n";
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n"
            . "  <channel>\n"
            . '    <title>' . fb_xml_esc($shopTitle) . "</title>\n"
            . '    <link>' . fb_xml_esc($shopLink) . "</link>\n"
            . "    <description>Product feed for Google Merchant Center</description>\n"
            . $items
            . "  </channel>\n"
            . "</rss>\n";
    }
}
