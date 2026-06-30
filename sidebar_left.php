<?php
// sidebar_left.php - Sidebar trái giống Facebook
$currentCase = $currentCase ?? '';
$normalRoute = $normalRoute ?? '';
$userRouteActive = $userRouteActive ?? '';

// Router hợp nhất: dùng chung biến $activeRoute (đã tính ở navbar nếu có,
// nếu không thì suy ra từ ?normal / alias cũ).
if (!isset($activeRoute) || $activeRoute === '') {
    $activeRoute = $normalRoute !== '' ? $normalRoute : (
        $currentCase !== '' ? $currentCase : (
            !empty($_GET['ithanhloc']) ? (string)$_GET['ithanhloc'] : (
                $userRouteActive !== '' ? $userRouteActive : (
                    !empty($_GET['ghn']) ? 'ghn-' . (string)$_GET['ghn'] : ''
                )
            )
        )
    );
    $sidebarAliasMap = ['order' => 'orders', 'voucher' => 'vouchers'];
    if (!empty($_GET['ithanhloc']) && isset($sidebarAliasMap[(string)$_GET['ithanhloc']])) {
        $activeRoute = $sidebarAliasMap[(string)$_GET['ithanhloc']];
    }
}
?>
<div class="fb-sidebar-left d-lg-none" >
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <style>
                .nav-toggle { position: absolute; opacity: 0; pointer-events: none; }
                .nav-toggle-label {
                    width: 100%;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    cursor: pointer;
                }
                .nav-sublist { list-style: none; margin: 6px 0 0; padding: 0 0 0 18px; display: none; }
                .nav-toggle:checked + .nav-toggle-label + .nav-sublist { display: block; }
                .nav-chevron { margin-left: auto; transition: transform 0.2s ease; }
                .nav-toggle:checked + .nav-toggle-label .nav-chevron { transform: rotate(180deg); }
                .sidebar-content { display: flex; flex-direction: column; height: 100%; }
                .hover-primary:hover { color: var(--theme-primary, #0d6efd) !important; text-decoration: underline !important; }
                .sidebar-footer { background: inherit; }
            </style>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/home" class="nav-link <?php echo (in_array($activeRoute, ['', 'home', 'dashboard'], true)) ? 'active' : ''; ?>">
                        <i class="bi bi-house-door-fill"></i>
                        <span>Trang chủ</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/blog" class="nav-link <?php echo ($activeRoute === 'blog' ? 'active' : ''); ?>">
                        <i class="bi bi-newspaper"></i>
                        <span>Trang tin tức</span>
                    </a>
                </li>
                <li class="nav-header">Tài khoản</li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/account" class="nav-link <?php echo ($activeRoute === 'account' ? 'active' : ''); ?>">
                        <i class="bi bi-person-circle"></i>
                        <span>Tài khoản</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/voucher" class="nav-link <?php echo ($activeRoute === 'voucher' ? 'active' : ''); ?>">
                        <i class="bi bi-ticket-perforated"></i>
                        <span>Kho Voucher</span>
                    </a>
                </li>
                <li class="nav-header">Mua sắm</li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/shopping" class="nav-link <?php echo ($activeRoute === 'shopping' ? 'active' : ''); ?>">
                        <i class="bi bi-cart-check"></i>
                        <span>Đặt hàng</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/cart" class="nav-link <?php echo ($activeRoute === 'cart' ? 'active' : ''); ?>">
                        <i class="bi bi-handbag"></i>
                        <span>Xem giỏ hàng</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/order" class="nav-link <?php echo ($activeRoute === 'order' ? 'active' : ''); ?>">
                        <i class="bi bi-receipt"></i>
                        <span>Đơn mua</span>
                    </a>
                </li>
                <li class="nav-header">Liên hệ</li>
                <li class="nav-item">
                    <a href="<?= $baseUrl ?>/contact" class="nav-link <?php echo ($activeRoute === 'contact' ? 'active' : ''); ?>">
                        <i class="bi bi-telephone-fill"></i>
                        <span>Liên hệ</span>
                    </a>
                <!-- Phần admin chỉ hiển thị nếu đã đăng nhập và có role là admin -->
                <?php if ($isLoggedIn && $isAdmin): ?>
                <li class="nav-header">Quản trị sản phẩm</li>
                <li class="nav-item">
                    <input type="checkbox" id="adminEcomToggleSidebar" class="nav-toggle" <?php echo in_array($activeRoute, ['product','product-change','promotion','orders','order-change','rating','store','shipping-rule','vouchers'], true) ? 'checked' : ''; ?> />
                    <label for="adminEcomToggleSidebar" class="nav-link nav-toggle-label <?php echo in_array($activeRoute, ['product','product-change','promotion','orders','order-change','rating','store','shipping-rule','vouchers'], true) ? 'active' : ''; ?>">
                        <i class="bi bi-bag"></i>
                        <span>Quản trị sản phẩm</span>
                        <i class="bi bi-chevron-down nav-chevron"></i>
                    </label>
                    <ul class="nav-sublist">
                        <li class="nav-item"><a href="<?= $baseUrl ?>/product" class="nav-link <?php echo ($activeRoute === 'product' ? 'active' : ''); ?>"><i class="bi bi-box-seam"></i><span>Sản phẩm</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/promotion" class="nav-link <?php echo ($activeRoute === 'promotion' ? 'active' : ''); ?>"><i class="bi bi-percent"></i><span>Khuyến mãi</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/orders" class="nav-link <?php echo ($activeRoute === 'orders' ? 'active' : ''); ?>"><i class="bi bi-receipt"></i><span>Đơn hàng</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/rating" class="nav-link <?php echo ($activeRoute === 'rating' ? 'active' : ''); ?>"><i class="bi bi-chat-square-dots"></i><span>Đánh giá</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/vouchers" class="nav-link <?php echo ($activeRoute === 'vouchers' ? 'active' : ''); ?>"><i class="bi bi-ticket-perforated"></i><span>Voucher</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/store" class="nav-link <?php echo ($activeRoute === 'store' ? 'active' : ''); ?>"><i class="bi bi-geo-alt"></i><span>Chi nhánh</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/shipping-rule" class="nav-link <?php echo ($activeRoute === 'shipping-rule' ? 'active' : ''); ?>"><i class="bi bi-truck"></i><span>Phí vận chuyển</span></a></li>
                    </ul>
                </li>
                <li class="nav-header">Hệ thống</li>
                <li class="nav-item">
                    <input type="checkbox" id="systemToggleSidebar" class="nav-toggle" />
                    <label for="systemToggleSidebar" class="nav-link nav-toggle-label">
                        <i class="bi bi-sliders"></i>
                        <span>Quản trị hệ thống</span>
                        <i class="bi bi-chevron-down nav-chevron"></i>
                    </label>
                    <ul class="nav-sublist">
                        <li class="nav-item"><a href="<?= $baseUrl ?>/setting" class="nav-link <?php echo ($activeRoute === 'setting' ? 'active' : ''); ?>"><i class="bi bi-gear"></i><span>Cấu Hình Website</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/zns" class="nav-link <?php echo ($activeRoute === 'zns' ? 'active' : ''); ?>"><i class="bi bi-chat-dots"></i><span>Cấu hình ZNS</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/users" class="nav-link <?php echo ($activeRoute === 'users' ? 'active' : ''); ?>"><i class="bi bi-people-fill"></i><span>Quản lý Users</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/banner" class="nav-link <?php echo ($activeRoute === 'banner' ? 'active' : ''); ?>"><i class="bi bi-image"></i><span>Quản lý Banner</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/notification" class="nav-link <?php echo ($activeRoute === 'notification' ? 'active' : ''); ?>"><i class="bi bi-bell"></i><span>Quản lý Thông báo</span></a></li>
                        <li class="nav-item"><a href="<?= $baseUrl ?>/color-manager" class="nav-link <?php echo ($activeRoute === 'color-manager' ? 'active' : ''); ?>"><i class="bi bi-palette2"></i><span>Quản lý Mã Màu</span></a></li>
                         </ul>
                </li>
                <?php endif; ?>
            </ul>
       
        </nav>
        <!-- Về chúng tôi -->
        <div class="sidebar-footer px-3 py-3 mt-auto">
            <div class="border-top pt-3">
                <h6 class="text-uppercase fw-bold mb-2" style="font-size: 0.7rem; color: #64748b; letter-spacing: 0.05em;">Chính sách & Bảo mật</h6>
                <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                    <li class="mb-1"><a href="<?= $baseUrl ?>/terms.html" class="text-decoration-none text-secondary hover-primary">Điều khoản chung</a></li>
                    <li class="mb-1"><a href="<?= $baseUrl ?>/chinh-sach-van-chuyen.html" class="text-decoration-none text-secondary hover-primary">Chính sách vận chuyển</a></li>
                    <li class="mb-1"><a href="<?= $baseUrl ?>/chinh-sach-doi-tra.html" class="text-decoration-none text-secondary hover-primary">Đổi trả & hoàn tiền</a></li>
                    <li><a href="<?= $baseUrl ?>/chinh-sach-bao-mat.html" class="text-decoration-none text-secondary hover-primary">Chính sách bảo mật</a></li>
                </ul>
                <div class="mt-3 text-muted" style="font-size: 0.65rem;">
                    © 2026 <?= h($company_name ?? 'Paint & More') ?>
                </div>
            </div>
        </div>
    </div>
</div>
