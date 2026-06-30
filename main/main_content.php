<?php require_once __DIR__ . '/../config.php'; ?>

<?php
$isLoggedIn = (int)($_SESSION['user_id'] ?? 0) > 0;
$role = $_SESSION['role'] ?? null;

// --- ROUTER HỢP NHẤT ---
// Mọi trang dùng path chung /<route>. Các tham số cũ (?ithanhloc, ?user, ?ghn)
// được giữ lại để tương thích ngược với link & form POST cũ, và được quy về
// cùng một tên route trong bảng $routes bên dưới.
$normalRoute = $_REQUEST['normal'] ?? null;

// Tương thích ngược: map param admin/user/ghn cũ -> tên route mới (nếu chưa có normal)
if ($normalRoute === null || $normalRoute === '') {
    if (!empty($_REQUEST['ithanhloc'])) {
        // Một số trang admin trùng tên trang user -> dùng tên riêng trong bảng route
        $legacyAdminAlias = [
            'voucher' => 'vouchers',
            'order'   => 'orders',
        ];
        $legacy = (string)$_REQUEST['ithanhloc'];
        $normalRoute = $legacyAdminAlias[$legacy] ?? $legacy;
    } elseif (!empty($_REQUEST['user'])) {
        $normalRoute = (string)$_REQUEST['user'];
    } elseif (!empty($_REQUEST['ghn'])) {
        $normalRoute = 'ghn-' . (string)$_REQUEST['ghn'];
    }
}

if ($normalRoute === 'login') {
    $loginUrl = rtrim((string)($baseUrl ?? ''), '/') . '/page_login';
    echo '<script type="text/javascript">window.location.href = ' . json_encode($loginUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($loginUrl) . '"></noscript>';
    return;
}

// --- BẢNG ROUTE HỢP NHẤT ---
// Mỗi route: 'file' (đường dẫn include), 'auth' (cần đăng nhập?), 'role'
//   role: null = mọi role | 'admin' = chỉ admin | 'user' = chỉ user
// Route 'home'/'dashboard' xử lý riêng theo role (admin -> home_admin, user -> home_user).
$routes = [
    // ----- Trang chung / công khai -----
    'login'               => ['file' => 'main/login.php',                       'auth' => false, 'role' => null],
    'logout'              => ['file' => 'main/logout.php',                      'auth' => false, 'role' => null],
    'account'             => ['file' => 'main/account.php',                     'auth' => true,  'role' => null],
    'search'              => ['file' => 'core/search.php',                      'auth' => false, 'role' => null],
    'blog'                => ['file' => 'core/blog/index.php',                  'auth' => false, 'role' => null],
    'job-report'          => ['file' => 'core/job/index.php',                   'auth' => false, 'role' => null],
    'job-report-preview'  => ['file' => 'core/job/week_panel.php',              'auth' => false, 'role' => null],
    'reset-password'      => ['file' => 'core_user/ecommerce/reset-password.php','auth' => false, 'role' => null],

    // ----- Trang người dùng / e-commerce (công khai) -----
    'support'             => ['file' => 'core_user/support/list.php',           'auth' => false, 'role' => null],
    'support-detail'      => ['file' => 'core_user/support/detail.php',         'auth' => false, 'role' => null],
    'faq'                 => ['file' => 'core_user/support/faq.php',            'auth' => false, 'role' => null],
    'shopping'            => ['file' => 'core_user/ecommerce/shopping.php',     'auth' => false, 'role' => null],
    'notifications'       => ['file' => 'core_user/ecommerce/notifications.php', 'auth' => false, 'role' => null],
    'view-notification'   => ['file' => 'core_user/ecommerce/view-notification.php', 'auth' => false, 'role' => null],
    'view-blog'           => ['file' => 'core_user/ecommerce/view-blog.php',    'auth' => false, 'role' => null],
    'view-voucher'        => ['file' => 'core_user/ecommerce/view-voucher.php', 'auth' => false, 'role' => null],
    'view-product'        => ['file' => 'core_user/ecommerce/view-product.php', 'auth' => false, 'role' => null],
    'view-order'          => ['file' => 'core_user/ecommerce/view-order.php',   'auth' => false, 'role' => null],
    'cart'                => ['file' => 'core_user/ecommerce/cart.php',         'auth' => false, 'role' => null],
    'checkout'            => ['file' => 'core_user/ecommerce/checkout.php',     'auth' => false, 'role' => null],
    'order-confirm'       => ['file' => 'core_user/ecommerce/order-confirm.php','auth' => false, 'role' => null],
    'order'               => ['file' => 'core_user/ecommerce/order.php',        'auth' => false, 'role' => null],
    'voucher'             => ['file' => 'core_user/ecommerce/voucher.php',      'auth' => false, 'role' => null],

    // ----- Trang quản trị (chỉ admin) -----
    'notification'        => ['file' => 'core_admin/notification.php',          'auth' => true, 'role' => 'admin'],
    'setting'             => ['file' => 'core_admin/setting.php',               'auth' => true, 'role' => 'admin'],
    'users'               => ['file' => 'core_admin/users.php',                 'auth' => true, 'role' => 'admin'],
    'promotion'           => ['file' => 'core_admin/ecommerce/promotion.php',   'auth' => true, 'role' => 'admin'],
    'preview'             => ['file' => 'core_admin/ecommerce/preview.php',     'auth' => true, 'role' => 'admin'],
    'vouchers'            => ['file' => 'core_admin/ecommerce/voucher.php',      'auth' => true, 'role' => 'admin'],
    'rating'              => ['file' => 'core_admin/ecommerce/rating.php',       'auth' => true, 'role' => 'admin'],
    'product'             => ['file' => 'core_admin/ecommerce/product.php',      'auth' => true, 'role' => 'admin'],
    'product-change'      => ['file' => 'core_admin/ecommerce/product-change.php','auth' => true, 'role' => 'admin'],
    'orders'              => ['file' => 'core_admin/ecommerce/order.php',        'auth' => true, 'role' => 'admin'],
    'order-change'        => ['file' => 'core_admin/ecommerce/order-change.php', 'auth' => true, 'role' => 'admin'],
    'shipping-rule'       => ['file' => 'core_admin/ecommerce/shipping-rule.php','auth' => true, 'role' => 'admin'],
    'banner'              => ['file' => 'core_admin/banner.php',                 'auth' => true, 'role' => 'admin'],
    'partner'             => ['file' => 'core_admin/partner.php',                'auth' => true, 'role' => 'admin'],
    'store'               => ['file' => 'core_admin/store.php',                  'auth' => true, 'role' => 'admin'],
    'blog-manager'        => ['file' => 'core_admin/blog/index.php',             'auth' => true, 'role' => 'admin'],
    'support-tickets'     => ['file' => 'core_admin/support/list.php',           'auth' => true, 'role' => 'admin'],
    'support-ticket'      => ['file' => 'core_admin/support/detail.php',         'auth' => true, 'role' => 'admin'],
    'faq-manager'         => ['file' => 'core_admin/support/faq.php',            'auth' => true, 'role' => 'admin'],
    'zns'                 => ['file' => 'core/zns/setup.php',                    'auth' => true, 'role' => 'admin'],
    'color-manager'       => ['file' => 'core_admin/ecommerce/color-manager.php','auth' => true, 'role' => 'admin'],

    // ----- Giao hàng nhanh (GHN) - chỉ admin. Tên route: ghn-<action> -----
    'ghn-home'            => ['file' => 'core_admin/giaohangnhanh/index.php',    'auth' => true, 'role' => 'admin'],
    'ghn-setup'           => ['file' => 'core_admin/giaohangnhanh/setup.php',    'auth' => true, 'role' => 'admin'],
    'ghn-store'           => ['file' => 'core_admin/giaohangnhanh/store.php',    'auth' => true, 'role' => 'admin'],
    'ghn-create'          => ['file' => 'core_admin/giaohangnhanh/create.php',   'auth' => true, 'role' => 'admin'],
    'ghn-order'           => ['file' => 'core_admin/giaohangnhanh/order.php',    'auth' => true, 'role' => 'admin'],
];

// Các trang lỗi
$render404 = function () {
    http_response_code(404);
    echo "<div style='text-align:center; margin-top:50px;'><h1>404</h1><p>Không tìm thấy trang yêu cầu.</p><a href='/'>Quay lại</a></div>";
};

$render403 = function () {
    http_response_code(403);
    echo "<div style='text-align:center; margin-top:50px;'><h1>403 Forbidden</h1><p>Bạn không có quyền truy cập khu vực này.</p><a href='/'>Quay lại</a></div>";
};

// ----- Trang chủ theo role (route rỗng / 'home' / 'dashboard') -----
// LƯU Ý: include phải chạy ở scope file (không bọc trong closure) để các trang
// con thấy được biến toàn cục như $ithanhloc, $baseUrl, $site_fallback_logo...
if ($normalRoute === null || $normalRoute === '' || $normalRoute === 'home' || $normalRoute === 'dashboard') {
    if (!$isLoggedIn) {
        include 'main/home_guest.php';
    } else {
        include $role === 'admin' ? 'main/home_admin.php' : 'main/home_user.php';
    }
    return;
}

// ----- Tra route trong bảng hợp nhất -----
$route = $routes[$normalRoute] ?? null;

// Route không tồn tại
if ($route === null) {
    // Khách chưa đăng nhập gõ route lạ -> đưa về trang đăng nhập
    if (!$isLoggedIn) {
        $loginUrl = rtrim((string)($baseUrl ?? ''), '/') . '/page_login';
        echo '<script type="text/javascript">window.location.href = ' . json_encode($loginUrl) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($loginUrl) . '"></noscript>';
        return;
    }
    $render404();
    return;
}

// Yêu cầu đăng nhập
if (!empty($route['auth']) && !$isLoggedIn) {
    $loginUrl = rtrim((string)($baseUrl ?? ''), '/') . '/page_login';
    echo '<script type="text/javascript">window.location.href = ' . json_encode($loginUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($loginUrl) . '"></noscript>';
    return;
}

// Phân quyền theo role
if ($route['role'] !== null && $role !== $route['role']) {
    $render403();
    return;
}

include $route['file'];
?>
