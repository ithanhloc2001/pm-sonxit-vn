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
		<div class="row mb-4 mb-md-5 justify-content-center">
			<div class="col-12 col-lg-10">
				<div class="bg-white rounded-3 shadow-sm p-3 p-md-4 p-lg-5" style="max-width:900px;margin:0 auto;">
					<h1 class="h4 text-uppercase fw-bold text-center mb-3">Quy định về hình thức thanh toán</h1>
					<p class="text-muted small text-center mb-4">
					Văn bản này quy định cụ thể các hình thức thanh toán được áp dụng khi khách hàng mua hàng hóa,
					sử dụng dịch vụ trên hệ thống Paint &amp; More, nhằm đảm bảo tính minh bạch, rõ ràng và tuân thủ quy
					định pháp luật hiện hành.
					</p>

					<div class="small">
					<h2 class="h6 fw-bold text-uppercase mb-2">I. Phạm vi và đối tượng áp dụng</h2>
					<p>
						1. Quy định này áp dụng cho tất cả khách hàng tổ chức, cá nhân thực hiện giao dịch mua bán hàng hóa,
						bao gồm nhưng không giới hạn ở sơn, vật tư hoàn thiện và các dịch vụ thi công liên quan, thông qua
						website và/hoặc các kênh bán hàng chính thức của Paint &amp; More.
					</p>
					<p>
						2. Các hình thức thanh toán được quy định dưới đây là cơ sở để hai bên thực hiện việc thanh toán,
						đối soát công nợ và giải quyết khiếu nại (nếu có).
					</p>

					<h2 class="h6 fw-bold text-uppercase mt-4 mb-2">II. Thanh toán tiền mặt khi nhận hàng (COD)</h2>
					<p>
						1. Khách hàng được phép thanh toán trực tiếp bằng tiền mặt cho nhân viên giao hàng của Paint &amp; More
						hoặc nhân viên của đơn vị vận chuyển do Paint &amp; More uỷ quyền, ngay tại thời điểm nhận hàng.
					</p>
					<p>
						2. Trước khi thanh toán, khách hàng có trách nhiệm kiểm tra tình trạng bên ngoài của hàng hóa
						(gồm: đúng chủng loại, mã sản phẩm, màu sắc, khối lượng, số lượng, vỏ bao bì nguyên vẹn).
					</p>
					<p class="mb-1">
						3. Hình thức thanh toán COD thường áp dụng cho các đơn hàng vật tư tiêu chuẩn sẵn có. Đối với các
						đơn hàng sơn pha màu theo yêu cầu hoặc đơn giá trị lớn, Paint &amp; More có thể yêu cầu khách hàng đặt cọc
						trước một phần giá trị đơn hàng theo thoả thuận.
					</p>

					<h2 class="h6 fw-bold text-uppercase mt-4 mb-2">III. Thanh toán bằng chuyển khoản ngân hàng</h2>
					<p>
						1. Khách hàng có thể thực hiện thanh toán toàn bộ hoặc một phần giá trị đơn hàng/hợp đồng qua hình
						thức chuyển khoản vào tài khoản ngân hàng chính thức của công ty.
					</p>
					<p class="mb-1 fw-semibold">2. Thông tin tài khoản nhận thanh toán (được sử dụng thống nhất trong các giao dịch):</p>
					<ul class="mb-2 ps-3">
						<li>Tên tài khoản: <strong>Công ty Cổ Phần Paint &amp; Moore</strong></li>
						<li>Số tài khoản: <strong>005704070016557</strong></li>
						<li>Ngân hàng: <strong>HD BANK - Chi nhánh VẠN HẠNH, TP.HCM</strong></li>
					</ul>
					<p class="mb-1 fw-semibold">3. Quy định về nội dung chuyển khoản:</p>
					<p class="mb-2">
						- Khách hàng ghi rõ <strong>[Mã đơn hàng]</strong> hoặc <strong>[Số điện thoại người đặt hàng]</strong> tại phần nội dung.
						Việc ghi đầy đủ, chính xác nội dung chuyển khoản là căn cứ để Paint &amp; More xác nhận thanh toán và
						đối soát đơn hàng.
					</p>
					<p>
						4. Thời điểm xác nhận thanh toán được tính từ khi hệ thống ngân hàng và/hoặc bộ phận kế toán của
						Paint &amp; More ghi nhận số tiền đã vào tài khoản nêu trên.
					</p>

					<h2 class="h6 fw-bold text-uppercase mt-4 mb-2">IV. Thanh toán trực tuyến qua ví điện tử / cổng thanh toán</h2>
					<p>
						1. Trong trường hợp hệ thống có tích hợp các ví điện tử và/hoặc cổng thanh toán (ví dụ: Momo,
						ZaloPay, VNPay, thẻ ATM nội địa, thẻ Visa/Mastercard...), khách hàng có thể lựa chọn hình thức thanh
						toán trực tuyến ngay tại bước thanh toán trên website.
					</p>
					<p class="mb-1 fw-semibold">2. Trình tự thực hiện thanh toán trực tuyến cơ bản như sau:</p>
					<ol class="mb-2 ps-3">
						<li>Khách hàng chọn hình thức "Thanh toán trực tuyến" tại bước Thanh toán trên hệ thống.</li>
						<li>Hệ thống chuyển hướng sang màn hình của ví điện tử/cổng thanh toán tương ứng.</li>
						<li>Khách hàng nhập thông tin thẻ/tài khoản, thực hiện các bước xác thực theo hướng dẫn của đơn vị cung cấp dịch vụ thanh toán.</li>
						<li>Sau khi giao dịch được chấp nhận thành công, hệ thống sẽ tự động ghi nhận trạng thái thanh toán của đơn hàng.</li>
					</ol>
					<p>
						3. Thông tin thẻ, tài khoản thanh toán của khách hàng được xử lý và bảo mật theo tiêu chuẩn của
						đơn vị cung cấp ví điện tử/cổng thanh toán; Paint &amp; More không trực tiếp lưu trữ dữ liệu thẻ thanh toán
						của khách hàng.
					</p>

					<h2 class="h6 fw-bold text-uppercase mt-4 mb-2">V. Thanh toán đối với dịch vụ thi công công trình</h2>
					<p>
						1. Đối với các hợp đồng thi công trọn gói hoặc cung cấp vật tư số lượng lớn, các bên có thể thống
						nhất lộ trình thanh toán theo từng đợt, được ghi nhận cụ thể trong hợp đồng/báo giá được hai bên xác nhận.
					</p>
					<p class="mb-1 fw-semibold">2. Lộ trình thanh toán tham khảo:</p>
					<ul class="mb-2 ps-3">
						<li>
							<strong>Đợt 1:</strong> Khách hàng tạm ứng khoảng 30% - 50% tổng giá trị hợp đồng ngay sau khi ký kết
								hợp đồng và trước khi Paint &amp; More tập kết vật tư, nhân công tới công trình.
						</li>
						<li>
							<strong>Đợt 2:</strong> Khách hàng thanh toán phần giá trị còn lại sau khi hai bên ký biên bản nghiệm thu,
								bàn giao công trình đưa vào sử dụng, trừ trường hợp hợp đồng có thoả thuận khác.
						</li>
					</ul>
					<p>
						3. Trường hợp cần thiết, hai bên có thể thống nhất bổ sung thêm các đợt thanh toán khác (ví dụ: theo
						tiến độ từng hạng mục) và sẽ được ghi nhận bằng phụ lục/hợp đồng cụ thể.
					</p>

					<h2 class="h6 fw-bold text-uppercase mt-4 mb-2">VI. Thời gian xác nhận thanh toán và hỗ trợ</h2>
					<p class="mb-2">
						1. Trong khung giờ làm việc hành chính, các khoản thanh toán hợp lệ thông thường sẽ được Paint &amp; More
						xác nhận trong khoảng từ <strong>10 - 30 phút</strong> kể từ thời điểm hệ thống ghi nhận tiền về tài khoản.
						Ngoài giờ làm việc, thời gian xác nhận có thể kéo dài hơn và sẽ được thông báo tới khách hàng khi cần thiết.
					</p>
						<p class="mb-0">
							2. Khi cần được giải đáp, hỗ trợ liên quan đến việc thanh toán, khách hàng có thể liên hệ bộ phận
							chăm sóc khách hàng của Paint &amp; More qua hotline, Zalo hoặc các kênh thông tin chính thức hiển thị
							trên website để được hướng dẫn chi tiết.
						</p>
					</div>
				</div>
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
document.querySelectorAll('.fb-sidebar-left .nav-link').forEach(link => {
	link.addEventListener('click', (e) => {
		if (link.classList.contains('nav-toggle-label')) {
			return;
		}
		if (window.innerWidth <= 768 || document.body.classList.contains('hide-left')) {
			toggleSidebar();
		}
	});
});

// Close sidebar when pressing Escape key
document.addEventListener('keydown', (e) => {
	if (e.key === 'Escape' && window.innerWidth <= 768) {
		const sidebar = document.querySelector('.fb-sidebar-left');
		if (sidebar.classList.contains('show')) {
			toggleSidebar();
		}
	}
});

// Handle window resize
window.addEventListener('resize', () => {
	const sidebar = document.querySelector('.fb-sidebar-left');
	const overlay = document.querySelector('.sidebar-overlay');

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