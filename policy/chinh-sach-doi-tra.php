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
                <h1 class="h3 h2-md fw-bold mb-3">Chính sách đổi trả &amp; hoàn tiền</h1>
                <p class="text-muted mb-0">
                    Chính sách này quy định điều kiện, phạm vi và quy trình đổi trả hàng hóa,
                    hoàn tiền khi khách hàng mua sản phẩm hoặc sử dụng dịch vụ của Paint &amp; More,
                    nhằm đảm bảo quyền lợi chính đáng và sự minh bạch cho các bên.
                </p>
            </div>

            <div class="small text-muted">
                <h2 class="h6 fw-bold mb-2">I. Phạm vi áp dụng</h2>
                <p class="mb-1">Chính sách áp dụng cho:</p>
                <ul class="mb-3 ps-3">
                    <li>Các đơn hàng vật tư (sơn, dụng cụ, phụ kiện) do Paint &amp; More cung cấp.</li>
                    <li>Các hợp đồng, hạng mục dịch vụ thi công do Paint &amp; More trực tiếp thực hiện hoặc phối hợp thực hiện.</li>
                </ul>

                <h2 class="h6 fw-bold mb-2">II. Điều kiện đổi trả hàng</h2>
                <p class="mb-1 fw-semibold">1. Đối với vật tư (sơn, dụng cụ):</p>
                <ul class="mb-2 ps-3">
                    <li>Sản phẩm còn nguyên vẹn, chưa khui nắp, tem mác đầy đủ.</li>
                    <li>Vỏ thùng, lon không bị biến dạng, trầy xước nghiêm trọng hoặc có dấu hiệu đã qua sử dụng.</li>
                </ul>
                <p class="mb-1 fw-semibold">2. Lưu ý đặc biệt về sơn pha màu:</p>
                <ul class="mb-2 ps-3">
                    <li>
                        Các sản phẩm sơn đã pha màu theo mã màu yêu cầu của khách hàng thông qua hệ thống máy pha màu tự động
                        <strong>không được hỗ trợ đổi trả</strong> dưới mọi hình thức.
                    </li>
                    <li>
                        Ngoại lệ duy nhất: nhân viên Paint &amp; More pha <strong>sai mã màu</strong> so với đơn đặt hàng
                        đã được khách hàng xác nhận.
                    </li>
                </ul>
                <p class="mb-1 fw-semibold">3. Đối với dịch vụ thi công:</p>
                <ul class="mb-3 ps-3">
                    <li>
                        Nếu phát hiện lỗi kỹ thuật thi công hoặc màu sắc thực tế không đúng cam kết,
                        khách hàng cần phản hồi ngay trong giai đoạn giám sát, nghiệm thu từng phần hoặc nghiệm thu toàn bộ công trình.
                    </li>
                </ul>

                <h2 class="h6 fw-bold mb-2">III. Thời gian áp dụng đổi trả</h2>
                <p>
                    Yêu cầu đổi, trả hàng hóa hoặc phản ánh về chất lượng thi công cần được gửi tới Paint &amp; More
                    trong vòng <strong>07 (bảy) ngày</strong> kể từ thời điểm:
                </p>
                <ul class="mb-2 ps-3">
                    <li>Khách hàng nhận bàn giao hàng hóa; hoặc</li>
                    <li>Khách hàng ký biên bản nghiệm thu hạng mục, công trình.</li>
                </ul>
                <p class="mb-3">
                    Sau thời hạn trên, Paint &amp; More sẽ xem xét hỗ trợ theo từng trường hợp cụ thể, tuy nhiên
                    không đảm bảo có thể áp dụng đầy đủ các quyền lợi đổi trả, hoàn tiền như quy định tiêu chuẩn.
                </p>

                <h2 class="h6 fw-bold mb-2">IV. Chi phí vận chuyển khi đổi trả</h2>
                <p class="mb-1 fw-semibold">1. Trường hợp lỗi thuộc về Paint &amp; More:</p>
                <ul class="mb-2 ps-3">
                    <li>
                        Giao sai dòng sơn, sai màu, hàng hóa lỗi từ nhà sản xuất hoặc lỗi phát sinh do khâu xử lý
                        của Paint &amp; More.
                    </li>
                    <li>
                        Paint &amp; More chịu <strong>100% chi phí vận chuyển</strong> để thu hồi và đổi, trả hàng mới cho khách hàng.
                    </li>
                </ul>
                <p class="mb-1 fw-semibold">2. Trường hợp phát sinh từ phía khách hàng:</p>
                <ul class="mb-3 ps-3">
                    <li>
                        Khách hàng đặt sai số lượng, thay đổi quyết định muốn đổi sang dòng sơn khác trong khi sản phẩm
                        vẫn đủ điều kiện (chưa pha màu, còn nguyên vẹn...).
                    </li>
                    <li>
                        Khách hàng chịu trách nhiệm thanh toán <strong>chi phí vận chuyển hai chiều</strong> cho việc gửi hàng
                        trả về kho và/hoặc nhận lại hàng mới.
                    </li>
                </ul>

                <h2 class="h6 fw-bold mb-2">V. Phương thức và thời gian hoàn tiền</h2>
                <p class="mb-1 fw-semibold">1. Phương thức hoàn tiền:</p>
                <ul class="mb-2 ps-3">
                    <li>
                        Khoản tiền hoàn lại được <strong>chuyển khoản</strong> vào tài khoản ngân hàng do khách hàng chỉ định.
                    </li>
                    <li>
                        Một số trường hợp có thể được quy đổi sang <strong>mã giảm giá/phiếu mua hàng</strong> nếu khách hàng đồng ý.
                    </li>
                </ul>
                <p class="mb-1 fw-semibold">2. Thời gian xử lý:</p>
                <p class="mb-0">
                    Paint &amp; More sẽ tiến hành thủ tục hoàn tiền trong vòng <strong>03 - 05 ngày làm việc</strong> kể từ khi:
                </p>
                <ul class="mb-3 ps-3 mt-1">
                    <li>Nhận lại hàng hóa và xác nhận hàng đạt đủ điều kiện đổi trả; hoặc</li>
                    <li>Hai bên thống nhất văn bản, biên bản về việc hoàn tiền đối với dịch vụ thi công.</li>
                </ul>

                <h2 class="h6 fw-bold mb-2">VI. Quy trình gửi yêu cầu đổi trả, hoàn tiền</h2>
                <p class="mb-1">Khách hàng thực hiện theo các bước cơ bản sau:</p>
                <ul class="mb-2 ps-3">
                    <li>Liên hệ Hotline hoặc email của Paint &amp; More, cung cấp thông tin đơn hàng/hợp đồng.</li>
                    <li>Mô tả lý do đổi trả, hoàn tiền kèm hình ảnh, video (nếu có) để đối chiếu.</li>
                    <li>Phối hợp với nhân viên Paint &amp; More để kiểm tra tình trạng hàng hóa, hiện trường thi công.</li>
                </ul>
                <p class="mb-0">
                    Sau khi tiếp nhận đầy đủ thông tin, Paint &amp; More sẽ thông báo phương án xử lý cụ thể cho khách hàng
                    trong thời gian sớm nhất, tuân thủ các quy định tại chính sách này và thỏa thuận hai bên.
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
