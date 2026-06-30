<?php
// Trang xem chi tiết một mã voucher: /view-voucher?code=XXXX
// Public route (xem main_content.php). Các biến $baseUrl, $ithanhloc, h() đã sẵn sàng.

// Mã voucher cần xem
$voucherCode = strtoupper(trim((string)($_GET['code'] ?? '')));

// Map id -> tên danh mục để hiển thị badge ngành hàng áp dụng
$categoryMap = [];
$categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category', 'list_category']) : '';
if ($categoryTable !== '') {
	$catRes = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY id ASC");
	if ($catRes) {
		while ($row = $catRes->fetch_assoc()) {
			$id = (int)($row['id'] ?? 0);
			$name = trim((string)($row['name'] ?? ''));
			if ($id > 0 && $name !== '') $categoryMap[$id] = $name;
		}
	}
}
?>

<style>
	.vd-page { padding: 16px 12px 28px; }
	.vd-shell { display: grid; gap: 14px; max-width: 720px; margin: 0 auto; }
	.vd-back { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; color: #6b7280; text-decoration: none; width: fit-content; }
	.vd-back:hover { color: #ef4444; }

	.vd-card { position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; }
	.vd-hero { position: relative; display: grid; grid-template-columns: 96px 1fr; gap: 14px; align-items: center; padding: 18px 18px 16px; }
	.vd-hero::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; border-bottom: 1px dashed #e5e7eb; }
	.vd-logo { width: 96px; height: 96px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 2.2rem; background: #ee4d2d; }
	.vd-hero-main { display: grid; gap: 6px; min-width: 0; }
	.vd-badge { display: inline-flex; align-items: center; gap: 4px; font-size: .64rem; font-weight: 700; color: #ee4d2d; background: #fff1ee; border: 1px solid #ffd8cf; border-radius: 999px; padding: 3px 8px; width: fit-content; text-transform: uppercase; letter-spacing: .02em; }
	.vd-title { font-size: 1.18rem; font-weight: 800; color: #111827; line-height: 1.25; }
	.vd-sub { font-size: .82rem; color: #6b7280; }

	.vd-status { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; width: fit-content; }
	.vd-status.is-active { color: #047857; background: #ecfdf5; }
	.vd-status.is-scheduled { color: #b45309; background: #fffbeb; }
	.vd-status.is-expired, .vd-status.is-used_up, .vd-status.is-inactive { color: #b91c1c; background: #fef2f2; }

	.vd-codebox { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 14px 18px; background: #fafafa; }
	.vd-code-wrap { display: grid; gap: 2px; }
	.vd-code-label { font-size: .68rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; }
	.vd-code { font-size: 1.05rem; font-weight: 800; letter-spacing: .06em; color: #111827; font-family: ui-monospace, Menlo, Consolas, monospace; }
	.vd-actions { display: flex; gap: 8px; flex-wrap: wrap; }
	.vd-btn { border-radius: 999px; border: 1px solid transparent; padding: 8px 18px; font-size: .84rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
	.vd-btn-copy { background: #fff; border-color: #d1d5db; color: #374151; }
	.vd-btn-copy:hover { border-color: #9ca3af; }
	.vd-btn-save { background: #ee4d2d; color: #fff; }
	.vd-btn-save:hover { background: #d8431f; }
	.vd-btn-save:disabled { background: #fca5a5; cursor: default; }
	.vd-btn-saved { background: #ecfdf5; color: #047857; border-color: #a7f3d0; cursor: default; }

	.vd-section { padding: 16px 18px; border-top: 1px solid #f3f4f6; }
	.vd-section-title { font-size: .78rem; font-weight: 800; color: #374151; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 10px; }
	.vd-rows { display: grid; gap: 10px; }
	.vd-row { display: grid; grid-template-columns: 22px 1fr; gap: 10px; align-items: start; font-size: .86rem; color: #374151; }
	.vd-row i { color: #ee4d2d; font-size: .95rem; margin-top: 1px; }
	.vd-row b { color: #111827; }
	.vd-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
	.vd-chip { font-size: .72rem; font-weight: 600; color: #4b5563; background: #f3f4f6; border-radius: 999px; padding: 3px 9px; }
	.vd-desc { font-size: .86rem; color: #4b5563; line-height: 1.6; white-space: pre-wrap; }

	.vd-loading, .vd-error { text-align: center; padding: 40px 16px; color: #6b7280; font-size: .9rem; }
	.vd-error i { font-size: 2rem; color: #ef4444; display: block; margin-bottom: 8px; }

	/* Biến thể màu theo loại voucher */
	.vd-card.t-shipping .vd-logo, .vd-card.t-payment .vd-logo { background: #26aa99; }
	.vd-card.t-shipping .vd-badge { color: #26aa99; border-color: #b7ece6; background: #ecfdfb; }
	.vd-card.t-shipping .vd-row i { color: #26aa99; }
	.vd-card.t-category .vd-logo { background: #ea580c; }
	.vd-card.t-category .vd-badge { color: #ea580c; border-color: #fed7aa; background: #fff7ed; }
	.vd-card.t-category .vd-row i { color: #ea580c; }
	.vd-card.t-payment .vd-logo { background: #16a34a; }
	.vd-card.t-payment .vd-badge { color: #16a34a; border-color: #bbf7d0; background: #ecfdf5; }
	.vd-card.t-payment .vd-row i { color: #16a34a; }

	@media (max-width: 520px) {
		.vd-hero { grid-template-columns: 72px 1fr; }
		.vd-logo { width: 72px; height: 72px; font-size: 1.7rem; }
		.vd-title { font-size: 1.04rem; }
	}
</style>

<div class="vd-page">
	<div class="vd-shell">
		<a href="<?= h($baseUrl) ?>/voucher" class="vd-back"><i class="bi bi-arrow-left"></i> Kho voucher của bạn</a>
		<div id="vdContent">
			<div class="vd-loading"><i class="bi bi-arrow-repeat"></i> Đang tải chi tiết voucher...</div>
		</div>
	</div>
</div>

<script>
(function(){
	if (typeof jQuery === 'undefined') return;
	const $ = jQuery;
	const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
	const CATEGORY_MAP = <?= json_encode($categoryMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
	const CODE = <?= json_encode($voucherCode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
	const $content = $('#vdContent');

	const esc = (v) => $('<div>').text(String(v ?? '')).html();

	function fmtVND(n){
		const val = Number(n) || 0;
		try { return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + 'đ'; }
		catch(e){ return Math.round(val) + 'đ'; }
	}
	function fmtDate(raw){
		const txt = String(raw || '').trim();
		if (!txt) return '';
		const d = txt.replace('T', ' ').split(' ')[0];
		const p = d.split('-');
		if (p.length !== 3) return txt;
		return p[2] + '/' + p[1] + '/' + p[0];
	}

	function classify(row){
		const tpl = String(row.voucher_template || '').trim().toLowerCase();
		const target = String(row.discount_target || '').toLowerCase();
		if (tpl === 'shipping_discount' || target.indexOf('shipping') !== -1) return 'shipping';
		if (tpl === 'payment_discount') return 'payment';
		if (tpl === 'only_category_discount' || tpl === 'category_discount') return 'category';
		return 'order';
	}

	const TYPE_INFO = {
		shipping: { cls: 't-shipping', icon: 'bi-truck', label: 'Mã miễn phí vận chuyển', on: 'phí vận chuyển' },
		payment:  { cls: 't-payment',  icon: 'bi-credit-card-2-front', label: 'Ưu đãi thanh toán', on: 'đơn hàng' },
		category: { cls: 't-category', icon: 'bi-grid-3x3-gap', label: 'Mã giảm theo ngành hàng', on: 'đơn hàng' },
		order:    { cls: '',           icon: 'bi-percent', label: 'Mã giảm giá đơn hàng', on: 'đơn hàng' }
	};

	const STATUS_INFO = {
		active:    { cls: 'is-active',    icon: 'bi-check-circle', label: 'Đang áp dụng' },
		scheduled: { cls: 'is-scheduled', icon: 'bi-clock',        label: 'Sắp diễn ra' },
		expired:   { cls: 'is-expired',   icon: 'bi-x-circle',     label: 'Đã hết hạn' },
		used_up:   { cls: 'is-used_up',   icon: 'bi-x-circle',     label: 'Đã hết lượt sử dụng' },
		inactive:  { cls: 'is-inactive',  icon: 'bi-x-circle',     label: 'Ngừng áp dụng' }
	};

	function valueTitle(row, type){
		const unit = String(row.value_unit || '').toLowerCase();
		const on = TYPE_INFO[type].on;
		if (type === 'shipping' && unit !== 'percent' && Number(row.value || 0) <= 0) {
			return 'Miễn phí vận chuyển';
		}
		if (unit === 'percent') {
			const pct = String(row.value_label || ((Number(row.value) || 0) + '%'));
			return 'Giảm ' + pct + ' ' + on;
		}
		return 'Giảm ' + fmtVND(row.value || 0) + ' ' + on;
	}

	function categoryNames(row){
		const out = [];
		const raw = row.apply_category_ids || row.category_ids || '';
		String(raw).split(',').map(s => s.trim()).filter(Boolean).forEach(id => {
			const name = CATEGORY_MAP[id];
			if (name) out.push(name);
		});
		return out;
	}

	function paymentLabels(row){
		const out = [];
		String(row.payment_methods || '').split(',').map(s => s.trim().toLowerCase()).filter(Boolean).forEach(k => {
			if (k === 'cod') out.push('Thanh toán khi nhận hàng (COD)');
			else if (k === 'vnpay') out.push('VNPAY');
			else if (k === 'momo') out.push('MoMo');
			else out.push(k.toUpperCase());
		});
		return out;
	}

	function buildConditionRows(row, type){
		const rows = [];
		const min = Number(row.min_subtotal || 0);
		rows.push(min > 0
			? { icon: 'bi-bag-check', html: 'Đơn tối thiểu <b>' + esc(fmtVND(min)) + '</b>' }
			: { icon: 'bi-bag-check', html: 'Áp dụng cho <b>mọi đơn hàng</b>' });

		const max = Number(row.max_discount || 0);
		if (max > 0) {
			rows.push({ icon: 'bi-arrow-down-circle', html: 'Giảm tối đa <b>' + esc(fmtVND(max)) + '</b>' });
		}

		const cats = categoryNames(row);
		if (type === 'category' && cats.length) {
			rows.push({ icon: 'bi-grid-3x3-gap', html: 'Áp dụng cho ngành hàng:' + chips(cats) });
		}
		const pays = paymentLabels(row);
		if (pays.length) {
			rows.push({ icon: 'bi-credit-card', html: 'Phương thức thanh toán:' + chips(pays) });
		}

		const start = fmtDate(row.start_at);
		const end = fmtDate(row.end_at);
		let timeTxt = 'Không giới hạn thời gian';
		if (start && end) timeTxt = 'Từ <b>' + esc(start) + '</b> đến <b>' + esc(end) + '</b>';
		else if (end) timeTxt = 'Hạn sử dụng đến <b>' + esc(end) + '</b>';
		else if (start) timeTxt = 'Bắt đầu từ <b>' + esc(start) + '</b>';
		rows.push({ icon: 'bi-calendar-event', html: timeTxt });

		const maxUses = (row.max_uses !== null && row.max_uses !== '' && row.max_uses !== undefined) ? Number(row.max_uses) : null;
		if (maxUses !== null && maxUses > 0) {
			const used = Number(row.used_count || 0);
			const remain = Math.max(maxUses - used, 0);
			rows.push({ icon: 'bi-ticket-perforated', html: 'Còn lại <b>' + esc(remain) + '</b> lượt sử dụng' });
		}
		return rows;
	}

	function chips(list){
		return '<div class="vd-chips">' + list.map(t => '<span class="vd-chip">' + esc(t) + '</span>').join('') + '</div>';
	}

	function render(row){
		const type = classify(row);
		const ti = TYPE_INFO[type];
		const status = String(row.status || 'active');
		const si = STATUS_INFO[status] || STATUS_INFO.active;
		const saved = !!(row.is_saved || row.saved);
		const saveable = !!row.saveable;
		const loggedIn = !!row.logged_in;

		const condRows = buildConditionRows(row, type).map(r =>
			'<div class="vd-row"><i class="bi ' + r.icon + '"></i><div>' + r.html + '</div></div>'
		).join('');

		const desc = String(row.detail_text || row.promo_note || '').trim();
		const descSection = desc
			? '<div class="vd-section"><div class="vd-section-title">Mô tả</div><div class="vd-desc">' + esc(desc) + '</div></div>'
			: '';

		let actionBtn;
		if (saved) {
			actionBtn = '<button type="button" class="vd-btn vd-btn-saved" disabled><i class="bi bi-check-lg"></i> Đã lưu</button>';
		} else if (!saveable) {
			actionBtn = '<button type="button" class="vd-btn vd-btn-save" disabled>Không khả dụng</button>';
		} else if (!loggedIn) {
			actionBtn = '<a href="<?= h($baseUrl) ?>/page_login" class="vd-btn vd-btn-save"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập để lưu</a>';
		} else {
			actionBtn = '<button type="button" class="vd-btn vd-btn-save" id="vdSaveBtn"><i class="bi bi-bookmark-plus"></i> Lưu mã</button>';
		}

		const html = ''
			+ '<div class="vd-card ' + ti.cls + '">'
			+ '  <div class="vd-hero">'
			+ '    <span class="vd-logo"><i class="bi ' + ti.icon + '"></i></span>'
			+ '    <div class="vd-hero-main">'
			+ '      <span class="vd-badge"><i class="bi ' + ti.icon + '"></i> ' + esc(ti.label) + '</span>'
			+ '      <div class="vd-title">' + esc(valueTitle(row, type)) + '</div>'
			+ '      <span class="vd-status ' + si.cls + '"><i class="bi ' + si.icon + '"></i> ' + esc(si.label) + '</span>'
			+ '    </div>'
			+ '  </div>'
			+ '  <div class="vd-codebox">'
			+ '    <div class="vd-code-wrap">'
			+ '      <span class="vd-code-label">Mã voucher</span>'
			+ '      <span class="vd-code" id="vdCode">' + esc(row.code || CODE) + '</span>'
			+ '    </div>'
			+ '    <div class="vd-actions">'
			+ '      <button type="button" class="vd-btn vd-btn-copy" id="vdCopyBtn"><i class="bi bi-copy"></i> Sao chép</button>'
			+ '      ' + actionBtn
			+ '    </div>'
			+ '  </div>'
			+ '  <div class="vd-section">'
			+ '    <div class="vd-section-title">Điều kiện áp dụng</div>'
			+ '    <div class="vd-rows">' + condRows + '</div>'
			+ '  </div>'
			+ descSection
			+ '</div>';

		$content.html(html);
	}

	function renderError(msg){
		$content.html('<div class="vd-error"><i class="bi bi-exclamation-circle"></i>' + esc(msg || 'Không tải được voucher') + '</div>');
	}

	// Sự kiện (delegated vì nội dung render động)
	$content.on('click', '#vdCopyBtn', function(){
		const code = String($('#vdCode').text() || '').trim();
		if (!code) return;
		const done = () => { if (window.toastr && toastr.success) toastr.success('Đã sao chép mã: ' + code); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(code).then(done).catch(() => fallbackCopy(code, done));
		} else {
			fallbackCopy(code, done);
		}
	});

	function fallbackCopy(text, cb){
		const ta = document.createElement('textarea');
		ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
		document.body.appendChild(ta); ta.select();
		try { document.execCommand('copy'); cb && cb(); } catch(e) {}
		document.body.removeChild(ta);
	}

	$content.on('click', '#vdSaveBtn', function(){
		const $btn = $(this);
		const code = String($('#vdCode').text() || '').trim();
		if (!code) return;
		$btn.prop('disabled', true);
		$.post(API, { action: 'voucher_save', code })
			.done(res => {
				if (!res || !res.ok) {
					if (window.toastr && toastr.warning) toastr.warning((res && res.msg) || 'Không lưu được mã');
					$btn.prop('disabled', false);
					return;
				}
				if (window.toastr && toastr.success) toastr.success(res.msg || ('Đã lưu mã: ' + code));
				$btn.replaceWith('<button type="button" class="vd-btn vd-btn-saved" disabled><i class="bi bi-check-lg"></i> Đã lưu</button>');
			})
			.fail(() => {
				if (window.toastr && toastr.error) toastr.error('Lỗi kết nối server');
				$btn.prop('disabled', false);
			});
	});

	// Tải dữ liệu
	if (!CODE) {
		renderError('Thiếu mã voucher cần xem.');
		return;
	}
	$.get(API, { ajax: 'voucher_detail', code: CODE })
		.done(res => {
			if (!res || !res.ok || !res.data) {
				renderError((res && res.msg) || 'Mã voucher không tồn tại.');
				return;
			}
			render(res.data);
		})
		.fail(() => renderError('Không thể kết nối máy chủ voucher.'));
})();
</script>
