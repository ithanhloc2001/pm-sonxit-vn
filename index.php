<?php
require_once __DIR__ . '/config.php';
// Cấu hình và hằng số chung cho toàn bộ app, có thể truy cập trực tiếp từ các file include sau này
if (!defined('APP_EMBED_LAYOUT')) {
    define('APP_EMBED_LAYOUT', true);
}
// Định nghĩa các hằng số và biến toàn cục dùng chung cho toàn bộ app
$googleLoginConfig = $GOOGLE_LOGIN ?? [];
$googleClientId = trim((string)($googleLoginConfig['client_id'] ?? ''));
$googleLoginEnabled = !empty($googleLoginConfig['enabled']) && $googleClientId !== '' && stripos($googleClientId, 'YOUR_GOOGLE_CLIENT_ID') === false;
$authAjaxEndpoint = rtrim((string)($baseUrl ?? ''), '/') . '/login/';
$isAuthAjaxRequest =
    (($_POST['is_ajax'] ?? '') === '1')
    && isset($_POST['action'])
    && in_array((string)$_POST['action'], ['login', 'register', 'google-login', 'register_phone_otp', 'login_phone_otp', 'forgot-password'], true);

// Bổ sung: Nếu là AJAX xác thực, đảm bảo biến normal được thiết lập là login để các xử lý bên trong đồng bộ
if ($isAuthAjaxRequest && empty($_GET['normal'])) {
    $_GET['normal'] = 'login';
}

if ($isAuthAjaxRequest) {
    include 'main/login.php';
    exit;
}

// Search AJAX endpoint: bypass layout
if (($_GET['normal'] ?? '') === 'search' && (($_GET['ajax'] ?? '') === '1')) {
    include 'core/search.php';
    exit;
}

// Reset password (AJAX POST): xử lý TRƯỚC khi render layout để tránh HTML lẫn vào JSON
// (jOut sẽ trả JSON thuần và exit ngay trong reset-password.php)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array(($_POST['action'] ?? ''), ['update_password', 'resend_reset'], true)
) {
    $_rpPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    if (($_GET['normal'] ?? '') === 'reset-password' || strpos($_rpPath, '/reset-password') !== false) {
        include 'core_user/ecommerce/reset-password.php';
        exit;
    }
}

// Định tuyến thủ công cho các URL thân thiện
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Route to modernized landing page for root domain access
if ($requestPath === '/' && empty($_GET)) {
    //include 'page_home/index.php';
    //exit;
}
if (!isset($_GET['normal']) && !isset($_GET['user']) && !isset($_GET['ithanhloc']) && !isset($_GET['ghn'])) {
    $parts = array_values(array_filter(explode('/', strtolower(trim($requestPath, '/'))), static fn($part) => $part !== ''));
    $resolved = false;

 # Nếu chưa xác định được route nào, thử kiểm tra route chung (normal)
    if (!$resolved && !empty($parts)) {
        if ($parts[0] === 'product' && count($parts) >= 2) {
            $lastPart = $parts[count($parts) - 1];
            if (preg_match('/-(\d+)$/', $lastPart, $m)) {
                $pid = (int)$m[1];
                if ($pid > 0) {
                    $_GET['normal'] = 'view-product';
                    $_REQUEST['normal'] = 'view-product';
                    $_GET['pid'] = $pid;
                    $_REQUEST['pid'] = $pid;
                    $resolved = true;
                }
            }
        } elseif ($parts[0] === 'order') {
            $_GET['normal'] = 'order';
            $_REQUEST['normal'] = 'order';
            $resolved = true;
        } elseif ($parts[0] === 'view-order') {
            $_GET['normal'] = 'view-order';
            $_REQUEST['normal'] = 'view-order';
            $resolved = true;
        }
    }
}
try {
    $pageTitle = $pageTitle ?? null;
    $pageDescription = $pageDescription ?? null;
    $pageImageUrl = $pageImageUrl ?? null;
    $pageCanonicalUrl = $pageCanonicalUrl ?? null;
    $pageOgType = $pageOgType ?? null;

    // Xác định normal route cho SEO, ưu tiên tham số, fallback theo đường dẫn /blog/... nếu thiếu
    $normalRouteForSeo = $_GET['normal'] ?? null;
    if ($normalRouteForSeo === null && isset($_SERVER['REQUEST_URI'])) {
        $pathSeoDetect = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        $pathSeoDetect = trim((string)$pathSeoDetect, '/');
        $partsSeoDetect = $pathSeoDetect !== '' ? explode('/', $pathSeoDetect) : [];
        if (!empty($partsSeoDetect) && $partsSeoDetect[0] === 'blog') {
            $normalRouteForSeo = 'view-blog';
            if (!isset($_GET['slug']) && count($partsSeoDetect) >= 2) {
                $_GET['slug'] = $partsSeoDetect[count($partsSeoDetect) - 1];
            }
        }
    }

    if ($normalRouteForSeo === 'view-product' || $normalRouteForSeo === 'view-blog') {
        // Bật chế độ SEO-only cho lần include này
        $APP_SEO_ONLY = true;

        if ($normalRouteForSeo === 'view-product') {
            include __DIR__ . '/core_user/ecommerce/view-product.php';
        } elseif ($normalRouteForSeo === 'view-blog') {
            include __DIR__ . '/core_user/ecommerce/view-blog.php';
        }

        // Tắt lại để lần include sau render HTML bình thường
        $APP_SEO_ONLY = false;
    }

    // SEO Meta cho Category sản phẩm (shopping) và Category blog (blog)
    if ($normalRouteForSeo === 'shopping' && isset($_GET['cat_slug'])) {
        $_catSlug = trim((string)$_GET['cat_slug']);
        if ($_catSlug !== '' && isset($ithanhloc) && $ithanhloc instanceof mysqli) {
            $_catStmt = $ithanhloc->prepare("SELECT name, description FROM ecommerce_category WHERE slug = ? AND status = 1 LIMIT 1");
            if ($_catStmt) {
                $_catStmt->bind_param('s', $_catSlug);
                $_catStmt->execute();
                $_catRes = $_catStmt->get_result();
                $_catRow = $_catRes ? $_catRes->fetch_assoc() : null;
                $_catStmt->close();
                if ($_catRow) {
                    $_siteTitleRaw = (isset($site_title) && $site_title !== '') ? (string)$site_title : 'Paint&More';
                    $currentCategoryName = trim((string)$_catRow['name']);
                    $pageTitle = $currentCategoryName . ' | ' . $_siteTitleRaw;
                    $_catDesc = trim(strip_tags((string)$_catRow['description']));
                    if ($_catDesc !== '') {
                        if (function_exists('mb_strimwidth')) {
                            $pageDescription = mb_strimwidth($_catDesc, 0, 160, '…', 'UTF-8');
                        } else {
                            $pageDescription = substr($_catDesc, 0, 160);
                        }
                    }
                    $pageCanonicalUrl = rtrim($_SiteUrl, '/') . '/product-category/' . $_catSlug;
                }
            }
        }
    } elseif ($normalRouteForSeo === 'blog' && isset($_GET['category'])) {
        $_blogCatSlug = trim((string)$_GET['category']);
        if ($_blogCatSlug !== '' && isset($ithanhloc) && $ithanhloc instanceof mysqli) {
            $_bcatStmt = $ithanhloc->prepare("SELECT name FROM ecommerce_blog_category WHERE slug = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
            if ($_bcatStmt) {
                $_bcatStmt->bind_param('s', $_blogCatSlug);
                $_bcatStmt->execute();
                $_bcatRes = $_bcatStmt->get_result();
                $_bcatRow = $_bcatRes ? $_bcatRes->fetch_assoc() : null;
                $_bcatStmt->close();
                if ($_bcatRow) {
                    $_siteTitleRaw = (isset($site_title) && $site_title !== '') ? (string)$site_title : 'Paint&More';
                    $pageTitle = trim((string)$_bcatRow['name']) . ' | ' . $_siteTitleRaw;
                    $pageCanonicalUrl = rtrim($_SiteUrl, '/') . '/blog/category/' . $_blogCatSlug;
                }
            }
        }
    }
} catch (Throwable $e) {
    // Im lặng nếu có lỗi SEO meta, tránh ảnh hưởng render trang chính
}


// Kiểm tra trạng thái đăng nhập và điều hướng nếu cần
$isLoggedIn = (int)($_SESSION['user_id'] ?? 0) > 0;
// Kiểm tra nếu URL đã có route xác định nào đó (normal, user, ithanhloc, ghn)
$hasRoute = isset($_GET['normal']) || isset($_GET['user']) || isset($_GET['ithanhloc']) || isset($_GET['ghn']);
// Chuẩn bị đường dẫn yêu cầu và định tuyến thủ công nếu cần
$normalizedPath = rtrim($requestPath, '/');
if ($normalizedPath === '') {
    $normalizedPath = '/';
}
// Router hợp nhất: KHÔNG còn ép redirect về /admin/home hay /user/home.
// Trang chủ dùng path chung "/" (hoặc /home) và main_content.php tự chọn
// home_admin.php / home_user.php theo role. Không cần làm gì ở đây.

// Bỏ qua layout mặc định cho trang login để sử dụng giao diện landing page mới
/* 
if (isset($_GET['normal']) && $_GET['normal'] === 'login') {
    include __DIR__ . '/login/index.php';
    exit;
}
*/

// Tự động gán tiêu đề trang động theo URL/Route trước khi nạp head.php
if (!isset($pageTitle) || trim((string)$pageTitle) === '') {
    $routeTitleKey = $_GET['normal'] ?? null;
    if ($routeTitleKey !== null) {
        $pageTitleMap = [
            'shopping'      => 'Mua sắm',
            'voucher'       => 'Mã giảm giá',
            'cart'          => 'Giỏ hàng',
            'checkout'      => 'Thanh toán',
            'order'         => 'Đơn hàng của tôi',
            'blog'          => 'Tin tức & Cẩm nang',
            'notifications' => 'Chương trình ưu đãi',
            'account'       => 'Tài khoản của tôi',
            'search'        => 'Tìm kiếm sản phẩm',
            'order-confirm' => 'Xác nhận đơn hàng',
            'login'         => 'Đăng nhập',
        ];
        if (isset($pageTitleMap[$routeTitleKey])) {
            $siteTitleRaw = (isset($site_title) && $site_title !== '') ? (string)$site_title : 'Paint&More';
            $pageTitle = $pageTitleMap[$routeTitleKey] . ' | ' . $siteTitleRaw;

            // Tối ưu hóa SEO động cho trang tìm kiếm (search) và danh sách tin tức (blog)
            if ($routeTitleKey === 'search') {
                $q = trim((string)($_GET['q'] ?? ''));
                if ($q !== '') {
                    $pageTitle = "Kết quả tìm kiếm cho '" . h($q) . "' | " . $siteTitleRaw;
                    $pageDescription = "Tìm kiếm các sản phẩm và giải pháp sơn Mỹ chất lượng cao liên quan đến '" . h($q) . "' tại Paint&More.";
                    $pageCanonicalUrl = rtrim($_SiteUrl ?? $baseUrl ?? '', '/') . '/search?q=' . urlencode($q);
                }
                $pageRobots = "noindex, follow"; // Chặn index trang kết quả tìm kiếm tránh trùng lặp
            } elseif ($routeTitleKey === 'blog' && !isset($_GET['category'])) {
                $pageTitle = "Tin tức, Cẩm nang & Hướng dẫn thi công sơn | " . $siteTitleRaw;
                $pageDescription = "Chuyên mục chia sẻ cẩm nang phối màu sơn nhà đẹp, hướng dẫn thi công sơn tường, sơn xịt và các chương trình khuyến mãi mới nhất từ Paint&More.";
                $pageCanonicalUrl = rtrim($_SiteUrl ?? $baseUrl ?? '', '/') . '/blog';
            } elseif ($routeTitleKey === 'notifications') {
                $pageTitle = "Chương trình ưu đãi & Khuyến mãi mới nhất | " . $siteTitleRaw;
                $pageDescription = "Tổng hợp các chương trình ưu đãi, khuyến mãi và tin nổi bật mới nhất từ Paint&More — sơn Mỹ chính hãng, cập nhật liên tục.";
                $pageCanonicalUrl = rtrim($_SiteUrl ?? $baseUrl ?? '', '/') . '/notifications';
            }
        }
    }
}

// SEO meta cho trang xem chi tiết thông báo (view-notification)
if (($_GET['normal'] ?? '') === 'view-notification') {
    $_nfSeoSlug = trim((string)($_GET['slug'] ?? ''));
    $_nfSeoId = 0;
    if ($_nfSeoSlug !== '' && function_exists('nf_extract_id_from_slug')) {
        $_nfSeoId = nf_extract_id_from_slug($_nfSeoSlug);
    }
    if ($_nfSeoId <= 0) {
        $_nfSeoId = (int)($_GET['id'] ?? 0);
    }
    if ($_nfSeoId > 0 && isset($ithanhloc) && $ithanhloc instanceof mysqli) {
        $_nfStmt = $ithanhloc->prepare("SELECT id, title, body, type, created_at, meta_json FROM user_notification WHERE id = ? AND LOWER(TRIM(CAST(type AS CHAR))) IN ('promotion','promo','voucher','coupon') LIMIT 1");
        if ($_nfStmt) {
            $_nfStmt->bind_param('i', $_nfSeoId);
            $_nfStmt->execute();
            $_nfRes = $_nfStmt->get_result();
            $_nfRow = $_nfRes ? $_nfRes->fetch_assoc() : null;
            $_nfStmt->close();
            if ($_nfRow) {
                $siteTitleRaw = (isset($site_title) && $site_title !== '') ? (string)$site_title : 'Paint&More';
                $_nfTitle = trim((string)$_nfRow['title']);
                $_nfBody = (string)$_nfRow['body'];
                $_nfSubtitle = '';
                $_nfMainBanner = '';
                $_nfThumbImage = '';
                $_nfContentText = '';
                $_nfPayload = json_decode($_nfBody, true);
                if (is_array($_nfPayload) && (($_nfPayload['schema'] ?? '') === 'notx_v2')) {
                    if (!empty($_nfPayload['title'])) $_nfTitle = trim((string)$_nfPayload['title']);
                    $_nfSubtitle = trim((string)($_nfPayload['subtitle'] ?? ''));
                    $_nfMainBanner = trim((string)($_nfPayload['main_banner'] ?? ''));
                    $_nfThumbImage = trim((string)($_nfPayload['thumb_image'] ?? ''));
                    $_nfContentText = trim(strip_tags((string)($_nfPayload['content'] ?? '')));
                    // Fallback: lấy banner đầu tiên nếu chưa có main_banner
                    if ($_nfMainBanner === '' && is_array($_nfPayload['banners'] ?? null) && !empty($_nfPayload['banners'])) {
                        $_nfMainBanner = trim((string)$_nfPayload['banners'][0]);
                    }
                } else {
                    $_nfContentText = trim(strip_tags($_nfBody));
                }
                // Meta_json fallback cho thumb
                if ($_nfThumbImage === '') {
                    $_nfMeta = json_decode((string)$_nfRow['meta_json'], true);
                    if (is_array($_nfMeta)) {
                        $_nfThumbImage = trim((string)($_nfMeta['thumb_image'] ?? ''));
                    }
                }

                // ----- pageTitle -----
                if ($_nfTitle !== '') {
                    $pageTitle = $_nfTitle . ' | ' . $siteTitleRaw;
                }
                // ----- pageDescription: subtitle hoặc 160 ký tự đầu của content -----
                $_nfDesc = $_nfSubtitle !== '' ? $_nfSubtitle : $_nfContentText;
                if ($_nfDesc !== '') {
                    if (function_exists('mb_strimwidth')) {
                        $pageDescription = mb_strimwidth($_nfDesc, 0, 160, '…', 'UTF-8');
                    } else {
                        $pageDescription = substr($_nfDesc, 0, 160);
                    }
                }
                // ----- pageImageUrl: ưu tiên main_banner, fallback thumb_image -----
                $_nfImg = $_nfMainBanner !== '' ? $_nfMainBanner : $_nfThumbImage;
                if ($_nfImg !== '') {
                    if (preg_match('#^(https?:)?//#i', $_nfImg)) {
                        $pageImageUrl = $_nfImg;
                    } else {
                        $_nfBaseUrl = isset($baseUrl) ? rtrim((string)$baseUrl, '/') : '';
                        $pageImageUrl = $_nfBaseUrl . '/' . ltrim($_nfImg, '/');
                    }
                }
                // ----- canonical URL: dùng slug chuẩn hoá -----
                if (function_exists('nf_build_url')) {
                    $_nfBaseAbs = isset($_SiteUrl) ? rtrim((string)$_SiteUrl, '/') : (isset($baseUrl) ? rtrim((string)$baseUrl, '/') : '');
                    $pageCanonicalUrl = nf_build_url($_nfSeoId, $_nfTitle, $_nfBaseAbs);
                }
                // ----- og:type article cho thông báo -----
                $pageOgType = 'article';
                // ----- article:published_time / modified_time / section -----
                $_nfCreated = trim((string)$_nfRow['created_at']);
                if ($_nfCreated !== '') {
                    $_nfTs = strtotime($_nfCreated);
                    if ($_nfTs) {
                        $pageArticlePublished = date('c', $_nfTs);
                        $pageArticleModified  = $pageArticlePublished;
                    }
                }
                $_nfType = strtolower(trim((string)$_nfRow['type']));
                $_nfSectionMap = [
                    'promotion' => 'Khuyến mãi',
                    'promo'     => 'Khuyến mãi',
                    'order'     => 'Đơn hàng',
                    'security'  => 'Bảo mật',
                    'system'    => 'Hệ thống',
                ];
                $pageArticleSection = $_nfSectionMap[$_nfType] ?? 'Thông báo';

                // ----- Expose payload để view in JSON-LD -----
                $GLOBALS['_nfSeoData'] = [
                    'id'        => $_nfSeoId,
                    'title'     => $_nfTitle,
                    'desc'      => $_nfDesc ?? '',
                    'image'     => $pageImageUrl ?? '',
                    'url'       => $pageCanonicalUrl ?? '',
                    'published' => $pageArticlePublished ?? '',
                    'modified'  => $pageArticleModified ?? '',
                    'section'   => $pageArticleSection,
                ];
            }
        }
    }
}

// Early POST Request Router: Execute administrative controller before layout headers are sent.
// Router hợp nhất: chấp nhận cả tham số mới (?normal=) lẫn tham số cũ (?ithanhloc=, ?ghn=)
// để form POST cũ không bị vỡ. Tất cả quy về cùng một bảng route admin/ghn.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminPages = [
        'home'                 => 'main/home_admin.php',
        'dashboard'            => 'main/home_admin.php',
        'notification'         => 'core_admin/notification.php',
        'setting'              => 'core_admin/setting.php',
        'users'                => 'core_admin/users.php',
        'promotion'            => 'core_admin/ecommerce/promotion.php',
        'preview'              => 'core_admin/ecommerce/preview.php',
        'voucher'              => 'core_admin/ecommerce/voucher.php',
        'vouchers'             => 'core_admin/ecommerce/voucher.php',
        'rating'               => 'core_admin/ecommerce/rating.php',
        'product'              => 'core_admin/ecommerce/product.php',
        'product-change'       => 'core_admin/ecommerce/product-change.php',
        'order'                => 'core_admin/ecommerce/order.php',
        'orders'               => 'core_admin/ecommerce/order.php',
        'order-change'         => 'core_admin/ecommerce/order-change.php',
        'shipping-rule'        => 'core_admin/ecommerce/shipping-rule.php',
        'banner'               => 'core_admin/banner.php',
        'partner'              => 'core_admin/partner.php',
        'store'                => 'core_admin/store.php',
        'blog-manager'         => 'core_admin/blog/index.php',
        'zns'                  => 'core/zns/setup.php',
    ];
    $ghnPages = [
        'home'   => 'core_admin/giaohangnhanh/index.php',
        'setup'  => 'core_admin/giaohangnhanh/setup.php',
        'store'  => 'core_admin/giaohangnhanh/store.php',
        'create' => 'core_admin/giaohangnhanh/create.php',
        'order'  => 'core_admin/giaohangnhanh/order.php',
    ];

    $adminRoute = $_REQUEST['ithanhloc'] ?? null;
    $ghnRoute   = $_REQUEST['ghn'] ?? null;
    $normalRoute = $_REQUEST['normal'] ?? null;
    $targetFile = null;

    if ($ghnRoute) {
        $targetFile = $ghnPages[$ghnRoute] ?? null;
    } elseif ($adminRoute) {
        $targetFile = $adminPages[$adminRoute] ?? null;
    } elseif ($normalRoute) {
        // Tham số mới: /home POST -> ?normal=<route>. Hỗ trợ cả ghn-<action>.
        if (strpos($normalRoute, 'ghn-') === 0) {
            $targetFile = $ghnPages[substr($normalRoute, 4)] ?? null;
        } else {
            $targetFile = $adminPages[$normalRoute] ?? null;
        }
    }

    if ($targetFile && file_exists(__DIR__ . '/' . $targetFile)) {
        include __DIR__ . '/' . $targetFile;
        exit;
    }
}
?>
<!-- Header and layout setup -->
<?php include_once("head.php"); ?>

<?php
// Khởi tạo các biến với giá trị mặc định để tránh cảnh báo Undefined Variable
$adLeft = ['image_url' => '', 'link_url' => ''];
$adRight = ['image_url' => '', 'link_url' => ''];
$popupAd = ['image_url' => '', 'link_url' => ''];
$needsAddressSetup = false;
$isAddressSetupPage = false;
$isAdmin = false;

// Bật/tắt cấu hình tại trang chủ (Thiết lập địa chỉ, Banner quảng cáo)
$enableHomeBanners = true; // Đặt false nếu muốn tắt toàn bộ banner quảng cáo trên trang chủ
$enableAddressSetupBlocker = true; // Đặt false nếu muốn tắt thông báo bắt buộc thiết lập địa chỉ

// Xác định trang hiện tại có phải TRANG CHỦ hay không (cho cả khách lẫn đã đăng nhập).
// Trang chủ = KHÔNG ở route con (không có user/admin/ghn route; normal rỗng
// hoặc 'home'/'dashboard'). Banner dọc trái/phải hiển thị ở trang chủ bất kể role.
$__sessUserId = (int)($_SESSION['user_id'] ?? 0);
$__sessRole   = $_SESSION['role'] ?? null;
$__normalR    = $_REQUEST['normal'] ?? null;
$__userR      = $_REQUEST['user'] ?? null;
$__adminR     = $_REQUEST['ithanhloc'] ?? null;
$__ghnR       = $_REQUEST['ghn'] ?? null;
$isHomeUserPage = empty($__userR) && empty($__adminR) && empty($__ghnR)
    && (empty($__normalR) || in_array($__normalR, ['home', 'dashboard'], true));

if ($enableHomeBanners) {
    $bannerPageKey = $isLoggedIn ? 'home_user' : 'home_guest';
    // ad-banner-rail (banner dọc trái/phải) CHỈ hiển thị ở trang chủ home_user.
    if ($isHomeUserPage) {
        $adLeft = get_home_ad_banner($ithanhloc, $bannerPageKey, 'ad_left', $baseUrl);
        $adRight = get_home_ad_banner($ithanhloc, $bannerPageKey, 'ad_right', $baseUrl);
    }
    // Popup khuyến mãi giữ nguyên hành vi (không giới hạn theo trang).
    $popupAd = get_home_ad_banner($ithanhloc, $bannerPageKey, 'popup', $baseUrl);
}

if ($enableAddressSetupBlocker) {
    $isAddressSetupPage = (isset($_GET['normal']) && $_GET['normal'] === 'account' && isset($_GET['tab']) && $_GET['tab'] === 'address') 
        || (stripos($_SERVER['REQUEST_URI'] ?? '', '/account') !== false && stripos($_SERVER['REQUEST_URI'] ?? '', 'tab=address') !== false);
    $isAdmin = isset($_SESSION['role']) && (string)$_SESSION['role'] === 'admin';

    // Kiểm tra nếu người dùng đã đăng nhập và chưa có địa chỉ giao hàng nào, yêu cầu họ thiết lập địa chỉ
    if ($isLoggedIn && !$isAdmin) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            ensure_user_saved_locations_index($ithanhloc);
            $stmt = $ithanhloc->prepare('SELECT COUNT(*) AS cnt FROM user_saved_locations WHERE user_id=?');
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $needsAddressSetup = (int)($row['cnt'] ?? 0) <= 0 && !$isAddressSetupPage;
            }
        }
    }
}
?>
<div class="fb-layout">
    <!-- Main -->
    <div class="fb-main">
        <div class="main-container-ad-wrap">
            <!-- Left Ad Banner -->
            <?php if (!empty($adLeft['image_url'])): ?>
            <div class="ad-banner-rail left" aria-hidden="true">
                <?php if (!empty($adLeft['link_url'])): ?>
                    <a href="<?= h($adLeft['link_url']) ?>" target="_blank" rel="noopener">
                        <img src="<?= h($adLeft['image_url']) ?>" alt="Banner quảng cáo trái" loading="lazy" decoding="async">
                    </a>
                <?php else: ?>
                    <img src="<?= h($adLeft['image_url']) ?>" alt="Banner quảng cáo trái" loading="lazy" decoding="async">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- end Left Ad Banner -->

            <!-- Main Container <div class="main-container-slot___"></div>-->
            
                <div id="mainContainer" class="main-container main-fluid">
                    <?php require_once("main/main_content.php"); ?>
                </div>
           
            <!-- end Main Container -->

            <!-- Right Ad Banner -->
            <?php if (!empty($adRight['image_url'])): ?>
            <div class="ad-banner-rail right" aria-hidden="true">
                <?php if (!empty($adRight['link_url'])): ?>
                    <a href="<?= h($adRight['link_url']) ?>" target="_blank" rel="noopener">
                        <img src="<?= h($adRight['image_url']) ?>" alt="Banner quảng cáo phải" loading="lazy" decoding="async">
                    </a>
                <?php else: ?>
                    <img src="<?= h($adRight['image_url']) ?>" alt="Banner quảng cáo phải" loading="lazy" decoding="async">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- end Right Ad Banner -->
        </div>
    </div>
    <!-- end Main -->
</div>
<?php if (!empty($popupAd['image_url'])): ?>
<div id="homePopupBanner" class="home-popup-banner" aria-modal="true" role="dialog" aria-label="Thông báo khuyến mãi" style="display:none;">
    <div class="home-popup-banner-inner">
        <button type="button" class="home-popup-close" aria-label="Đóng">
            <span>&times;</span>
        </button>
        <div class="home-popup-banner-img-wrap">
            <?php if (!empty($popupAd['link_url'])): ?>
                <a href="<?= h($popupAd['link_url']) ?>" target="_blank"  class="home-popup-banner-close" rel="noopener">
                    <img src="<?= h($popupAd['image_url']) ?>" alt="Banner khuyến mãi" loading="lazy" decoding="async">
                </a>
            <?php else: ?>
                <img src="<?= h($popupAd['image_url']) ?>" alt="Banner khuyến mãi" loading="lazy" decoding="async">
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- BLOCK CẤU HÌNH ĐỊA CHỈ -->
<?php if (!empty($needsAddressSetup)): ?>
<style>
.addr-blocker{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1990;display:flex;align-items:center;justify-content:center;padding:16px;}
.addr-blocker-card{width:min(420px,92vw);background:#fff;border-radius:14px;box-shadow:0 20px 50px rgba(15,23,42,.25);padding:16px;display:flex;flex-direction:column;gap:10px;}
.addr-blocker-icon{font-size:2.6rem;color:#94a3b8;line-height:1;text-align:center;}
.addr-blocker-title{font-size:1rem;font-weight:800;color:#0f172a;}
.addr-blocker-text{font-size:.88rem;color:#475569;line-height:1.5;}
.addr-blocker-actions{display:flex;gap:8px;flex-wrap:wrap;}
.addr-blocker-actions .btn{border-radius:10px;font-weight:700;}
</style>
<div class="addr-blocker" id="addrBlocker" aria-modal="true" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="addr-blocker-card text-center">
        <div class="addr-blocker-icon mb-3" aria-hidden="true" style="font-size: 5rem; color:#f87171;"><img src="<?= h($baseUrl) ?>/image/shipper.png" alt="Shipper Icon" loading="lazy" decoding="async"></div>
        <div class="addr-blocker-title">Thiết lập địa chỉ giao hàng</div>
        <div class="addr-blocker-text">Bạn cần thêm địa chỉ giao hàng để tiếp tục mua sắm.</div>
        <div class="addr-blocker-actions justify-content-center">
            <a class="btn btn-primary" href="<?= h($baseUrl) ?>/account?tab=address">Thiết lập ngay</a>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
// Close right sidebar when clicking outside or pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRightSidebar();
    }
});

// Auto-close right sidebar on window resize to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth >= 1200) {
        closeRightSidebar();
    }
});

// Popup banner hiển thị mỗi 30 phút một lần
(function(){
    const popupEl = document.getElementById('homePopupBanner');
    if (!popupEl) return;

    const STORAGE_KEY = 'pmPopupBannerLastShown';
    const INTERVAL_MS = 30 * 60 * 1000; // 30 phút

    function shouldShow(){
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return true;
            const last = parseInt(raw, 10) || 0;
            if (!last) return true;
            return (Date.now() - last) >= INTERVAL_MS;
        } catch (e) {
            return true;
        }
    }

    function markShown(){
        try {
            localStorage.setItem(STORAGE_KEY, String(Date.now()));
        } catch (e) {}
    }

    function openPopup(){
        popupEl.style.display = 'flex';
        popupEl.classList.add('show');
        document.body.classList.add('home-popup-open');
    }

    function closePopup(){
        popupEl.classList.remove('show');
        popupEl.style.display = 'none';
        document.body.classList.remove('home-popup-open');
        markShown();
    }

    document.addEventListener('DOMContentLoaded', function(){
        if (!shouldShow()) return;
        setTimeout(openPopup, 800);
    });

    popupEl.addEventListener('click', function(e){
        if (e.target && e.target.closest('.home-popup-banner-close')) {
            closePopup();
        } else if (e.target && e.target.closest('.home-popup-close')) {
            closePopup();
        }
    });
    })();

    <?php if (!empty($needsAddressSetup)): ?>
        // Chặn cuộn trang khi yêu cầu thiết lập địa chỉ giao hàng
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.overflow = 'hidden';
        });
    <?php endif; ?>


// Google Login One Short
<?php if (!$isLoggedIn && $googleLoginEnabled): ?>
/*(function() {
    const googleConfig = {
        enabled: true,
        clientId: <?= json_encode($googleClientId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        scope: <?= json_encode((string)($googleLoginConfig['scope'] ?? 'openid email profile'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    };
    const authEndpoint = <?= json_encode($authAjaxEndpoint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function loadGoogleIdentityScriptHome() {
        return new Promise((resolve, reject) => {
            if (window.google && window.google.accounts && window.google.accounts.id) {
                resolve();
                return;
            }
            const existing = document.querySelector('script[data-google-identity]');
            if (existing) {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', () => reject(new Error('load-error')), { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            script.defer = true;
            script.dataset.googleIdentity = '1';
            script.onload = resolve;
            script.onerror = () => reject(new Error('load-error'));
            document.head.appendChild(script);
        });
    }

    function handleGoogleCredentialResponseHome(response) {
        if (!response || !response.credential) return;
        submitGoogleCredentialHome(response.credential);
    }

    function submitGoogleCredentialHome(credential) {
        const formData = new FormData();
        formData.append('action', 'google-login');
        formData.append('credential', credential);
        formData.append('is_ajax', '1');

        fetch(authEndpoint, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(result => {
                if (result && result.success) {
                    const redirect = result.redirect || '/';
                    window.location.href = redirect;
                }
            })
            .catch(() => {
                // im lặng nếu lỗi, tránh làm phiền người dùng trên trang chủ
            });
    }

    if (googleConfig.enabled && googleConfig.clientId) {
        loadGoogleIdentityScriptHome()
            .then(() => {
                if (!window.google || !window.google.accounts || !window.google.accounts.id) return;
                window.google.accounts.id.initialize({
                    client_id: googleConfig.clientId,
                    callback: handleGoogleCredentialResponseHome,
                    auto_select: false,
                    cancel_on_tap_outside: true,
                });
                window.google.accounts.id.prompt();
            })
            .catch(() => {
                // bỏ qua nếu không load được script
            });
    }
})();*/
<?php endif; ?>
</script>

<?php include_once("foot.php"); ?>
</body>
</html>