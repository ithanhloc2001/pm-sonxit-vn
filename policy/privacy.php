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
                <h1 class="h3 h2-md fw-bold mb-3">Chính sách bảo mật thông tin khách hàng</h1>
                <p class="text-muted mb-0">
                    Chính sách này quy định nguyên tắc, phạm vi và cách thức Paint &amp; More
                    thu thập, sử dụng, lưu trữ và bảo vệ thông tin cá nhân của khách hàng
                    trong quá trình truy cập, sử dụng website/dịch vụ của chúng tôi.
                </p>
            </div>

            <div class="small text-muted">
                <h2 class="h6 fw-bold mb-2">I. Mục đích và phạm vi thu thập thông tin</h2>
                <p>
                    Paint &amp; More có thể thu thập các thông tin cá nhân cơ bản như: Họ tên, số điện thoại,
                    địa chỉ email, địa chỉ giao hàng/thi công và các thông tin liên quan đến nhu cầu sử dụng
                    sản phẩm, dịch vụ của khách hàng.
                </p>
                <p class="mb-1 fw-semibold">Mục đích thu thập bao gồm nhưng không giới hạn:</p>
                <ul class="mb-3 ps-3">
                    <li>Xử lý và xác nhận đơn hàng, báo giá, lịch khảo sát và thi công.</li>
                    <li>Cung cấp thông tin về sản phẩm, chương trình khuyến mãi, dịch vụ hậu mãi.</li>
                    <li>Hỗ trợ chăm sóc khách hàng, giải quyết khiếu nại, bảo hành, đổi trả.</li>
                </ul>

                <h2 class="h6 fw-bold mb-2">II. Phạm vi sử dụng thông tin</h2>
                <p>
                    Thông tin cá nhân do khách hàng cung cấp chỉ được Paint &amp; More sử dụng để phục vụ
                    các hoạt động liên quan trực tiếp đến giao dịch và chăm sóc khách hàng, cụ thể:
                </p>
                <ul class="mb-3 ps-3">
                    <li>Cung cấp sản phẩm/dịch vụ đúng theo nhu cầu đã đăng ký hoặc đặt mua.</li>
                    <li>Liên hệ, gửi thông báo liên quan đến giao dịch, lịch giao hàng, lịch thi công.</li>
                    <li>Gửi thông tin khuyến mãi, tri ân khách hàng khi được khách hàng đồng ý.</li>
                </ul>
                <p class="mb-3">
                    Chúng tôi <strong>không</strong> sử dụng hoặc chia sẻ thông tin cá nhân ngoài các mục đích
                    nêu trên, trừ khi có yêu cầu của cơ quan nhà nước có thẩm quyền hoặc được khách hàng chấp thuận.
                </p>

                <h2 class="h6 fw-bold mb-2">III. Thời gian lưu trữ thông tin</h2>
                <p>
                    Thông tin cá nhân của khách hàng được lưu trữ trên hệ thống trong thời gian cần thiết để
                    thực hiện các mục đích nêu tại chính sách này hoặc cho đến khi:
                </p>
                <ul class="mb-3 ps-3">
                    <li>Khách hàng có yêu cầu huỷ bỏ, xoá dữ liệu; hoặc</li>
                    <li>Khách hàng chủ động chỉnh sửa, cập nhật qua các kênh hỗ trợ; hoặc</li>
                    <li>Paint &amp; More thực hiện xoá hoặc ẩn danh dữ liệu theo chính sách nội bộ hoặc yêu cầu pháp lý.</li>
                </ul>
                <p class="mb-3">
                    Trong suốt thời gian lưu trữ, chúng tôi áp dụng các biện pháp kỹ thuật và tổ chức hợp lý
                    để bảo mật dữ liệu trên hệ thống.
                </p>

                <h2 class="h6 fw-bold mb-2">IV. Các cá nhân, tổ chức được tiếp cận thông tin</h2>
                <p class="mb-1">Thông tin cá nhân của khách hàng có thể được tiếp cận bởi:</p>
                <ul class="mb-3 ps-3">
                    <li>Ban quản trị website và bộ phận xử lý đơn hàng, chăm sóc khách hàng của Paint &amp; More.</li>
                    <li>
                        Các đối tác cung cấp dịch vụ liên quan như đơn vị vận chuyển, đối tác thanh toán,
                        cơ sở lưu trữ dữ liệu (chỉ trong phạm vi thông tin cần thiết cho nghiệp vụ).
                    </li>
                    <li>Cơ quan nhà nước có thẩm quyền theo quy định pháp luật hiện hành.</li>
                </ul>
                <p class="mb-3">
                    Các bên thứ ba có liên quan phải tuân thủ cam kết bảo mật và chỉ sử dụng thông tin đúng mục đích
                    mà Paint &amp; More đã thông báo với khách hàng.
                </p>

                <h2 class="h6 fw-bold mb-2">V. Đơn vị thu thập và quản lý thông tin</h2>
                <p class="mb-1 fw-semibold">Đơn vị phụ trách:</p>
                <ul class="mb-3 ps-3">
                    <li>Tên đơn vị: <strong>Công ty Cổ Phần Paint &amp; More</strong></li>
                    <li>Địa chỉ: <strong>135/37/71 Nguyễn Hữu Cảnh, Phường 22, Quận Bình Thạnh, Thành phố Hồ Chí Minh, Việt Nam</strong></li>
                    <li>Hotline: <strong>0909.143.900</strong></li>
                    <li>Email: <strong>info.pmkm@gmail.com</strong></li>
                </ul>
                <p class="mb-3">
                    Thông tin chi tiết khác (nếu có thay đổi) sẽ được cập nhật theo giấy phép kinh doanh và công bố
                    trên website/ứng dụng khi triển khai chính thức.
                </p>

                <h2 class="h6 fw-bold mb-2">VI. Quyền của khách hàng và cơ chế khiếu nại</h2>
                <p class="mb-1 fw-semibold">Quyền của khách hàng:</p>
                <ul class="mb-3 ps-3">
                    <li>Yêu cầu kiểm tra, cập nhật, chỉnh sửa thông tin cá nhân đã cung cấp.</li>
                    <li>Yêu cầu tạm ngưng hoặc xoá bỏ thông tin trong một số trường hợp hợp lý.</li>
                    <li>Được giải thích về cách thức Paint &amp; More thu thập, sử dụng và bảo vệ dữ liệu.</li>
                </ul>
                <p class="mb-1 fw-semibold">Cách thức liên hệ hỗ trợ, khiếu nại:</p>
                <p class="mb-2">
                    Khách hàng có thể liên hệ qua <strong>Hotline 0909.143.900</strong> hoặc email
                    <strong>info.pmkm@gmail.com</strong> được hiển thị trên website để yêu cầu chỉnh sửa, xoá dữ liệu
                    hoặc gửi khiếu nại khi nghi ngờ thông tin bị sử dụng sai mục đích.
                </p>
                <p class="mb-0">
                    Paint &amp; More cam kết tiếp nhận và phản hồi các yêu cầu liên quan đến dữ liệu cá nhân trong
                    thời gian sớm nhất có thể, dự kiến trong vòng <strong>24 giờ làm việc</strong> kể từ khi nhận được yêu cầu hợp lệ.
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
