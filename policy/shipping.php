<?php
// Nếu trang này được gọi độc lập, tự include layout chung (head/foot)
// Nếu được nhúng trong layout khác và đã define APP_EMBED_LAYOUT, chỉ render phần nội dung bên dưới.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('APP_EMBED_LAYOUT')) {
    define('APP_EMBED_LAYOUT', false);
}

if (!APP_EMBED_LAYOUT) {
    require_once __DIR__ . '/../config.php';
    include __DIR__ . '/../head.php';
}
?>

<?php if (!APP_EMBED_LAYOUT): ?>
<div class="fb-layout">
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="sidebar-overlay-right d-block d-xl-none" onclick="closeRightSidebar()" aria-label="Close sidebar"></div>
    <?php include __DIR__ . '/../sidebar_left.php'; ?>
    <div class="fb-main">
        <div class="main-container main-boxed">
<?php endif; ?>

<section class="policy-landing py-4 py-md-5" style="background-color:#f3f4f6;">
    <div class="container">
        <div class="bg-white rounded-3 shadow-sm p-3 p-md-4 p-lg-5" style="max-width:900px;margin:0 auto;">
            <div class="text-center mb-4 mb-md-5">
                <h1 class="h3 h2-md fw-bold mb-3">Chính sách giao hàng</h1>
                <p class="text-muted mb-0">
                    Chính sách này quy định phương thức giao hàng, thời gian dự kiến, chi phí và trách nhiệm
                    của các bên nhằm đảm bảo hàng hóa được giao đến khách hàng đúng địa điểm, đúng thời gian
                    trong khả năng tốt nhất có thể.
                </p>
            </div>

            <div class="small text-muted">
                <h2 class="h6 fw-bold mb-2">I. Phạm vi và đối tượng áp dụng</h2>
                <p>
                    Chính sách giao hàng áp dụng cho tất cả đơn hàng vật tư, sản phẩm và các đơn hàng kèm dịch vụ
                    thi công được đặt thông qua hệ thống của Paint &amp; More trên phạm vi toàn quốc, trừ khi có thỏa thuận
                    riêng bằng văn bản giữa hai bên.
                </p>

                <h2 class="h6 fw-bold mb-2">II. Phương thức giao hàng</h2>
                <p class="mb-1 fw-semibold">1. Giao hàng trực tiếp:</p>
                <p class="mb-2">
                    Paint &amp; More sử dụng hệ thống xe tải hoặc nhân viên giao hàng của công ty để giao hàng
                    cho các đơn hàng trong khu vực nội thành và các vùng lân cận phù hợp.
                </p>
                <p class="mb-1 fw-semibold">2. Giao hàng qua đơn vị vận chuyển / chành xe:</p>
                <p class="mb-3">
                    Áp dụng cho các đơn hàng ở tỉnh xa hoặc theo yêu cầu, chỉ định riêng của khách hàng (ví dụ: chành xe quen).
                    Hàng hóa sẽ được bàn giao kèm chứng từ đầy đủ cho đơn vị vận chuyển và được ghi nhận trên phiếu giao nhận.
                </p>

                <h2 class="h6 fw-bold mb-2">III. Thời gian giao hàng ước tính</h2>
                <p class="mb-1 fw-semibold">1. Khu vực nội thành:</p>
                <p class="mb-2">
                    Dự kiến giao hàng trong vòng <strong>24 - 48 giờ</strong> kể từ khi đơn hàng được xác nhận
                    và hoàn tất thanh toán hoặc đặt cọc (nếu có).
                </p>
                <p class="mb-1 fw-semibold">2. Khu vực ngoại thành và các tỉnh thành khác:</p>
                <p class="mb-2">
                    Thời gian giao hàng dự kiến từ <strong>03 - 07 ngày làm việc</strong>, tùy khoảng cách địa lý,
                    điều kiện vận chuyển và đơn vị vận chuyển, chành xe.
                </p>
                <p class="mb-1 fw-semibold">3. Lưu ý chung:</p>
                <p class="mb-3">
                    Thời gian giao hàng có thể thay đổi trong các trường hợp bất khả kháng như thời tiết xấu, thiên tai,
                    dịch bệnh, sự cố giao thông hoặc các dịp cao điểm Lễ, Tết. Paint &amp; More sẽ chủ động liên hệ,
                    thông báo cho khách hàng nếu có dự kiến chậm trễ để hai bên thống nhất phương án xử lý phù hợp.
                </p>

                <h2 class="h6 fw-bold mb-2">IV. Chính sách phí vận chuyển</h2>
                <p class="mb-1 fw-semibold">1. Miễn phí vận chuyển:</p>
                <p class="mb-2">
                    Áp dụng cho các đơn hàng đạt giá trị từ <strong>5.000.000 VNĐ</strong> trở lên trong phạm vi bán kính
                    khoảng <strong>15km</strong> từ kho/cửa hàng của công ty (mức giá trị đơn hàng và bán kính có thể được
                    điều chỉnh và thông báo trên từng chương trình, thời kỳ).
                </p>
                <p class="mb-1 fw-semibold">2. Tính phí vận chuyển:</p>
                <p class="mb-3">
                    Đối với các đơn hàng không đạt điều kiện miễn phí hoặc giao đi các tỉnh xa, cước phí vận chuyển sẽ
                    được tính theo trọng lượng/thể tích đơn hàng, địa điểm giao nhận và bảng giá của đơn vị vận chuyển.
                    Nhân viên Paint &amp; More sẽ thông báo, thỏa thuận mức phí cụ thể với khách hàng trước khi tiến hành giao hàng.
                </p>

                <h2 class="h6 fw-bold mb-2">V. Giới hạn địa lý giao hàng</h2>
                <p class="mb-3">
                    Paint &amp; More nhận giao hàng trên phạm vi <strong>toàn quốc</strong>. Đối với dịch vụ thi công đi kèm,
                    phạm vi phục vụ, chi phí di chuyển và các điều kiện liên quan sẽ được quy định cụ thể trong hợp đồng
                    hoặc phụ lục hợp đồng, tùy thuộc vào quy mô, địa điểm và điều kiện thi công thực tế.
                </p>

                <h2 class="h6 fw-bold mb-2">VI. Trách nhiệm với hàng hóa trong quá trình vận chuyển</h2>
                <p class="mb-1 fw-semibold">1. Trường hợp Paint &amp; More vận chuyển hoặc chỉ định bên thứ ba:</p>
                <ul class="mb-2 ps-3">
                    <li>
                        Paint &amp; More chịu trách nhiệm đối với rủi ro mất mát, hư hỏng, móp méo thùng sơn trong suốt
                        quá trình vận chuyển từ kho đến địa chỉ khách hàng cung cấp, cho đến khi hoàn tất việc bàn giao hàng hóa.
                    </li>
                </ul>
                <p class="mb-1 fw-semibold">2. Trường hợp khách hàng tự chỉ định đơn vị vận chuyển (chành xe quen):</p>
                <ul class="mb-2 ps-3">
                    <li>
                        Sau khi Paint &amp; More bàn giao hàng hóa kèm chứng từ đầy đủ cho đơn vị vận chuyển do khách hàng chỉ định,
                        mọi rủi ro và chi phí phát sinh liên quan đến hàng hóa sẽ do khách hàng làm việc trực tiếp với đơn vị đó.
                    </li>
                </ul>
                <p class="mb-1 fw-semibold">3. Kiểm tra hàng hóa khi nhận:</p>
                <p class="mb-0">
                    Khi nhận hàng, khách hàng vui lòng kiểm tra kỹ số lượng, mã màu, tình trạng vỏ thùng. Nếu phát hiện hư hỏng,
                    trầy xước, bể vỡ hoặc giao sai hàng, cần ghi nhận ngay với nhân viên giao nhận hoặc đơn vị vận chuyển và liên hệ
                    Hotline của Paint &amp; More để được hỗ trợ xử lý kịp thời.
                </p>
            </div>
        </div>
    </div>
</section>

<?php if (!APP_EMBED_LAYOUT): ?>
        </div>
    </div>
    <?php include __DIR__ . '/../sidebar_right.php'; ?>
</div>

<script>
// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-left');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;

    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');

    // Prevent body scroll when sidebar is open on mobile
    if (window.innerWidth <= 768) {
        if (sidebar.classList.contains('show')) {
            body.style.overflow = 'hidden';
        } else {
            body.style.overflow = '';
        }
    }
}

// Close sidebar when clicking a left-sidebar menu item (mobile or hidden layout)
if (document.querySelector('.fb-sidebar-left')) {
    document.querySelectorAll('.fb-sidebar-left .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (link.classList.contains('nav-toggle-label')) {
                return;
            }
            if (window.innerWidth <= 768 || document.body.classList.contains('hide-left')) {
                toggleSidebar();
            }
        });
    });
}

// Close sidebar when pressing Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        const sidebar = document.querySelector('.fb-sidebar-left');
        if (sidebar && sidebar.classList.contains('show')) {
            toggleSidebar();
        }
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    const sidebar = document.querySelector('.fb-sidebar-left');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!sidebar || !overlay) return;

    if (window.innerWidth > 768) {
        // Reset mobile state on desktop
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
});

function openRightSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-right');
    const overlay = document.querySelector('.sidebar-overlay-right');
    if (!sidebar || !overlay) return;
    sidebar.classList.add('open');
    overlay.classList.add('active');
}

function closeRightSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-right');
    const overlay = document.querySelector('.sidebar-overlay-right');
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
}

function toggleRightSidebar() {
    const sidebar = document.querySelector('.fb-sidebar-right');
    if (!sidebar) return;
    if (sidebar.classList.contains('open')) {
        closeRightSidebar();
    } else {
        openRightSidebar();
    }
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>
</body>
</html>
<?php endif; ?>
