<?php
/**
 * Facebook Catalog — Product Feed (XML).
 *
 * Phương án KHÔNG cần app/token: Facebook Commerce Manager tự kéo URL này.
 *   Commerce Manager → Catalog → Data Sources → Add Items → Data Feed → dán URL:
 *     https://sonxit.vn/feed-facebook.php
 *
 * Bảo vệ tuỳ chọn: đặt site_setting 'FACEBOOK_FEED_SECRET'; nếu có thì phải gọi
 *   .../feed-facebook.php?key=<secret>
 * (Facebook cho phép gắn URL có tham số; nếu để trống thì feed mở công khai.)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core_admin/ecommerce/lib/facebook_catalog.php';

// Bảo vệ tuỳ chọn bằng secret key
$secret = trim((string)(app_get_config_value_by_path('FACEBOOK_FEED_SECRET') ?? ''));
if ($secret !== '') {
    $given = trim((string)($_GET['key'] ?? ''));
    if (!hash_equals($secret, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=1800'); // cho phép cache 30 phút
echo fb_catalog_feed_xml($ithanhloc);
