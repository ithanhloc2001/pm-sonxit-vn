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
                <h1 class="h3 h2-md fw-bold mb-3">Thông tin về điều kiện giao dịch chung</h1>
                <p class="text-muted mb-0">
                    Điều kiện giao dịch chung này áp dụng cho các giao dịch mua bán hàng hóa, đặt dịch vụ
                    và sử dụng tiện ích trên website Paint &amp; More, nhằm đảm bảo tính minh bạch và quyền
                    lợi của khách hàng cũng như nghĩa vụ của các bên liên quan.
                </p>
            </div>

            <div class="small text-muted">
                <h2 class="h6 fw-bold mb-2">I. Phạm vi áp dụng và đối tượng</h2>
                <p>
                    1. Điều kiện giao dịch chung này được áp dụng đối với tất cả các giao dịch mua bán hàng hóa,
                    đặt dịch vụ và sử dụng các tiện ích khác trên website của Công ty Cổ Phần Paint &amp; More
                    (sau đây gọi tắt là "Website").
                </p>
                <p class="mb-3">
                    2. Khi truy cập, đặt hàng hoặc sử dụng dịch vụ trên Website, khách hàng được hiểu là đã tìm hiểu
                    và đồng ý tuân thủ các điều kiện giao dịch chung này cùng với các chính sách chuyên biệt khác
                    được công bố trên Website (chính sách bảo mật, chính sách giao hàng, chính sách đổi trả &amp; hoàn tiền,
                    chính sách thanh toán...).
                </p>

                <h2 class="h6 fw-bold mb-2">II. Điều kiện và hạn chế trong việc cung cấp hàng hóa, dịch vụ</h2>
                <p>
                    1. Việc cung cấp hàng hóa, dịch vụ phụ thuộc vào tình trạng tồn kho thực tế, năng lực cung ứng và tiến độ thi công
                    (đối với các gói dịch vụ thi công). Paint &amp; More có quyền từ chối hoặc đề nghị thay thế khi sản phẩm/dịch vụ
                    khách hàng yêu cầu không còn phù hợp hoặc không thể cung cấp.
                </p>
                <p>
                    2. Phạm vi cung cấp:
                </p>
                <ul class="mb-2 ps-3">
                    <li>Cung cấp hàng hóa trên phạm vi toàn quốc thông qua hệ thống vận chuyển/đối tác vận chuyển phù hợp.</li>
                    <li>Dịch vụ tư vấn, khảo sát và thi công được triển khai theo phạm vi địa lý, năng lực thực hiện được hai bên thống nhất.</li>
                </ul>
                <p class="mb-3">
                    3. Thời gian cung cấp hàng hóa, dịch vụ được ước tính theo khu vực, khối lượng đơn hàng và điều kiện khách quan
                    (thời tiết, giao thông, cao điểm Lễ/Tết...). Thời gian cụ thể sẽ được thông báo cho khách hàng trong quá trình
                    xác nhận đơn hàng/hợp đồng và có thể điều chỉnh khi phát sinh yếu tố bất khả kháng.
                </p>

                <h2 class="h6 fw-bold mb-2">III. Chính sách kiểm hàng, đổi trả và hoàn tiền</h2>
                <p class="mb-1 fw-semibold">1. Chính sách kiểm hàng:</p>
                <ul class="mb-2 ps-3">
                    <li>Khách hàng có trách nhiệm kiểm tra số lượng, chủng loại, mã màu, tình trạng vỏ thùng và niêm phong sản phẩm ngay khi nhận hàng.</li>
                    <li>Các dấu hiệu hư hỏng, sai mã màu, thiếu số lượng hoặc nhầm lẫn sản phẩm cần được phản ánh ngay cho nhân viên giao nhận
                        hoặc cho Paint &amp; More qua các kênh hỗ trợ được công bố trên Website.</li>
                </ul>
                <p class="mb-1 fw-semibold">2. Chính sách đổi trả, hoàn tiền:</p>
                <p>
                    Điều kiện, thời hạn đổi trả, các trường hợp được/không được đổi trả, hình thức hoàn tiền, chi phí vận chuyển khi đổi trả...
                    được quy định chi tiết tại trang "Chính sách đổi trả &amp; hoàn tiền" trên Website. Khách hàng vui lòng tham khảo kỹ
                    trước khi gửi yêu cầu hỗ trợ.
                </p>

                <h2 class="h6 fw-bold mb-2">IV. Chính sách bảo hành sản phẩm</h2>
                <p>
                    1. Các sản phẩm vật tư, thiết bị, phụ kiện do Paint &amp; More cung cấp có thể kèm theo chính sách bảo hành của hãng sản xuất
                    hoặc nhà phân phối. Thời hạn, phạm vi và điều kiện bảo hành sẽ được thể hiện trên bao bì, tem bảo hành, phiếu bảo hành
                    hoặc tài liệu kèm theo.
                </p>
                <p class="mb-3">
                    2. Đối với dịch vụ thi công, chế độ bảo hành (nếu có) sẽ được quy định rõ trong hợp đồng, phụ lục hợp đồng hoặc biên bản nghiệm thu,
                    bao gồm thời hạn bảo hành, phạm vi hạng mục bảo hành và điều kiện áp dụng. Khách hàng có trách nhiệm lưu giữ hóa đơn, hợp đồng,
                    phiếu bảo hành và các giấy tờ liên quan để làm căn cứ yêu cầu bảo hành.
                </p>

                <h2 class="h6 fw-bold mb-2">V. Tiêu chuẩn dịch vụ, quy trình cung cấp, biểu phí và các điều khoản liên quan</h2>
                <p class="mb-1 fw-semibold">1. Tiêu chuẩn dịch vụ:</p>
                <ul class="mb-2 ps-3">
                    <li>Paint &amp; More cam kết cung cấp hàng hóa, dịch vụ đúng mô tả, đúng chủng loại, chất lượng phù hợp với thông tin đã công bố trên Website
                        hoặc đã được hai bên thống nhất bằng văn bản.</li>
                    <li>Nhân viên tư vấn, kỹ thuật, thi công làm việc trên tinh thần chuyên nghiệp, trung thực, tôn trọng quyền lợi và sự an toàn của khách hàng.</li>
                </ul>
                <p class="mb-1 fw-semibold">2. Quy trình cung cấp dịch vụ (tư vấn, khảo sát, thi công...):</p>
                <ul class="mb-2 ps-3">
                    <li>Tiếp nhận yêu cầu, trao đổi nhu cầu và cung cấp giải pháp sơ bộ.</li>
                    <li>Khảo sát công trình (nếu cần), lập báo giá/đề xuất giải pháp chi tiết.</li>
                    <li>Hai bên thống nhất điều kiện, ký hợp đồng hoặc xác nhận đơn hàng.</li>
                    <li>Paint &amp; More tổ chức giao hàng, bố trí nhân sự và triển khai thi công theo kế hoạch.</li>
                    <li>Nghiệm thu, bàn giao, thanh lý hợp đồng và bảo hành (nếu có).</li>
                </ul>
                <p class="mb-1 fw-semibold">3. Biểu phí và chi phí phát sinh:</p>
                <p class="mb-3">
                    Giá bán sản phẩm, đơn giá dịch vụ, phí giao hàng, phí khảo sát (nếu có) và các loại phụ phí khác được niêm yết công khai trên Website
                    hoặc được thông báo cho khách hàng trước khi xác nhận đơn hàng/hợp đồng. Mọi chi phí phát sinh ngoài thỏa thuận ban đầu
                    (thay đổi khối lượng, mở rộng phạm vi, yêu cầu thêm hạng mục...) sẽ được báo giá, thống nhất bằng văn bản hoặc thông điệp điện tử
                    có giá trị xác nhận trước khi thực hiện.
                </p>

                <h2 class="h6 fw-bold mb-2">VI. Nghĩa vụ của người bán và nghĩa vụ của khách hàng</h2>
                <p class="mb-1 fw-semibold">1. Nghĩa vụ của người bán (Paint &amp; More):</p>
                <ul class="mb-2 ps-3">
                    <li>Cung cấp thông tin trung thực, rõ ràng về hàng hóa, dịch vụ; công bố đầy đủ các chính sách và điều kiện giao dịch liên quan trên Website.</li>
                    <li>Cung cấp hàng hóa, dịch vụ đúng thỏa thuận; thực hiện giao hàng, thi công, bảo hành theo hợp đồng và các chính sách đã công bố.</li>
                    <li>Bảo mật, bảo vệ dữ liệu cá nhân của khách hàng theo "Chính sách bảo mật thông tin khách hàng".</li>
                    <li>Hợp tác, hỗ trợ khách hàng giải quyết khiếu nại, tranh chấp liên quan đến giao dịch phát sinh trên Website trong phạm vi trách nhiệm của mình.</li>
                </ul>
                <p class="mb-1 fw-semibold">2. Nghĩa vụ của khách hàng:</p>
                <ul class="mb-3 ps-3">
                    <li>Cung cấp thông tin chính xác, đầy đủ khi đặt hàng, ký hợp đồng hoặc yêu cầu cung cấp dịch vụ; cập nhật kịp thời khi có thay đổi.</li>
                    <li>Đọc, hiểu và tuân thủ các điều kiện giao dịch chung, cùng với các chính sách chuyên biệt được công bố trên Website trước khi đặt hàng.</li>
                    <li>Thanh toán đầy đủ, đúng thời hạn theo phương thức đã lựa chọn; phối hợp tạo điều kiện để Paint &amp; More thực hiện giao hàng, thi công, bảo hành.</li>
                    <li>Sử dụng hàng hóa, dịch vụ đúng mục đích, tuân thủ các hướng dẫn kỹ thuật, an toàn; giữ gìn chứng từ giao dịch, chứng từ bảo hành để bảo vệ quyền lợi.</li>
                </ul>

                <h2 class="h6 fw-bold mb-2">VII. Hình thức công bố và cơ chế chấp nhận điều kiện giao dịch chung</h2>
                <p>
                    1. Toàn bộ nội dung điều kiện giao dịch chung được công bố bằng tiếng Việt, trình bày với màu chữ tương phản rõ ràng so với màu nền
                    của phần Website hiển thị nội dung này, nhằm đảm bảo khách hàng dễ đọc, dễ nhận biết.
                </p>
                <p>
                    2. Đối với các giao dịch đặt hàng trực tuyến, Website có cơ chế để khách hàng truy cập, xem trước đầy đủ nội dung điều kiện giao dịch chung
                    và thể hiện sự đồng ý riêng (ví dụ: đánh dấu chọn "Tôi đã đọc và đồng ý với Điều kiện giao dịch chung") trước khi gửi đề nghị giao kết hợp đồng.
                </p>
                <p class="mb-0">
                    3. Việc khách hàng đánh dấu đồng ý và/hoặc tiếp tục thao tác đặt hàng sau khi điều kiện giao dịch chung đã được cung cấp đầy đủ trên Website
                    được xem là khách hàng đã đọc, hiểu và chấp nhận ràng buộc bởi các điều kiện giao dịch chung này.
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
