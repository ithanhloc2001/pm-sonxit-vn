<?php
// Nếu không phải là admin hoặc chưa đăng nhập thì chuyển hướng về trang chủ
if (!$isLoggedIn) {
    if (!headers_sent()) {
        header('Location: ' . $baseUrl);
    }
    exit('<script>window.location.href="' . $baseUrl . '";</script>');
}

// Map id -> tên danh mục để hiển thị badge danh mục (nếu có)
$categoryMap = [];
$categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category', 'list_category']) : '';
if ($categoryTable !== '') {
	$catRes = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY id ASC");
	if ($catRes) {
		while ($row = $catRes->fetch_assoc()) {
			$id = (int)($row['id'] ?? 0);
			$name = trim((string)($row['name'] ?? ''));
			if ($id > 0 && $name !== '') {
				$categoryMap[$id] = $name;
			}
		}
	}
}
?>

<style>
	.vcp-page { padding: 16px 12px 24px; }
	.vcp-shell { display: grid; gap: 12px; max-width: 960px; margin: 0 auto; }
	.vcp-head { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
	.vcp-title { font-size: 1.02rem; font-weight: 800; color: #111827; }
	.vcp-meta { font-size: .8rem; color: #6b7280; }

	.vcp-codebar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 10px 12px; }
	.vcp-code-label { font-size: .8rem; font-weight: 600; color: #4b5563; }
	.vcp-code-input { flex: 1 1 220px; min-width: 0; border-radius: 999px; border: 1px solid #d1d5db; padding: 6px 12px; font-size: .86rem; }
	.vcp-save-btn { border-radius: 999px; border: none; padding: 6px 14px; font-size: .82rem; font-weight: 600; background: #ef4444; color: #fff; opacity: .7; }
	.vcp-save-btn:not(:disabled) { cursor: pointer; opacity: 1; }
	.vcp-save-btn:disabled { cursor: default; }

	.vcp-tabs-wrap { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 6px; }
	.vcp-tabs { display: flex; flex-wrap: wrap; gap: 4px; }
	.vcp-tab { flex: 0 0 auto; border-radius: 999px; border: none; padding: 6px 12px; font-size: .82rem; font-weight: 600; background: transparent; color: #4b5563; cursor: pointer; }
	.vcp-tab.active { background: #fee2e2; color: #b91c1c; }

	.vcp-list { display: grid; gap: 10px; }
	.vcp-empty { border-radius: 12px; border: 1px dashed #d1d5db; padding: 18px 12px; text-align: center; font-size: .88rem; color: #6b7280; }

	/* Thẻ voucher sử dụng style tpl-voux-card giống trang quản lý voucher */
	.tpl-voux-card { position: relative; background: #fff; border: 1px solid #ffd8cf; border-radius: 10px; overflow: hidden; display: grid; grid-template-columns: 6px 64px 1fr auto; align-items: stretch; }
	.tpl-voux-card::before { content: ''; position: absolute; top: 0; bottom: 0; left: calc(6px + 64px); width: 1px; border-left: 1px dashed #e5e7eb; }
	.tpl-voux-qty { position: absolute; top: 6px; right: 8px; font-size: .62rem; font-weight: 700; color: #0f172a; background: rgba(15,23,42,.08); padding: 2px 6px; border-radius: 999px; }
	.tpl-voux-accent { background: #ee4d2d; }
	.tpl-voux-brand { display: grid; align-content: center; justify-items: center; gap: 6px; padding: 8px 6px; }
	.tpl-voux-logo-icon { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #ee4d2d; color: #fff; font-size: .85rem; }
	.tpl-voux-brand-name { font-size: .6rem; font-weight: 800; color: #4b5563; text-transform: uppercase; text-align: center; line-height: 1.1; }
	.tpl-voux-main { padding: 8px 10px; display: grid; gap: 4px; }
	.tpl-voux-badge { display: inline-flex; align-items: center; font-size: .6rem; font-weight: 700; color: #ee4d2d; background: #fff1ee; border: 1px solid #ffd8cf; border-radius: 999px; padding: 2px 6px; width: fit-content; }
	.tpl-voux-main-title { font-size: .8rem; font-weight: 800; color: #111827; line-height: 1.25; }
	.tpl-voux-sub { font-size: .68rem; color: #6b7280; }
	.tpl-voux-foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: 2px; flex-wrap: wrap; }
	.tpl-voux-time { font-size: .66rem; color: #ef4444; }
	.tpl-voux-side { display: grid; align-content: center; justify-items: center; gap: 6px; padding: 8px 8px; min-width: 84px; }
	.tpl-voux-btn { border: 1px solid #ee4d2d; border-radius: 6px; background: #fff; color: #ee4d2d; font-size: .66rem; font-weight: 700; padding: 4px 8px; }
	.tpl-voux-tag { font-size: .6rem; color: #6b7280; }
	.vcp-tnc { font-size: .75rem; color: #2563eb; text-decoration: none; }

	/* Màu theo loại voucher, giống admin */
	.tpl-voux-card.tpl-voux-ship { border-color: #b7ece6; }
	.tpl-voux-card.tpl-voux-ship .tpl-voux-accent { background: #26aa99; }
	.tpl-voux-card.tpl-voux-ship .tpl-voux-logo-icon { background: #26aa99; }
	.tpl-voux-card.tpl-voux-ship .tpl-voux-badge { color: #26aa99; border-color: #b7ece6; background: #ecfdfb; }
	.tpl-voux-card.tpl-voux-ship .tpl-voux-btn { color: #26aa99; border-color: #26aa99; }
	.tpl-voux-card.tpl-voux-order { border-color: #ffd8cf; }
	.tpl-voux-card.tpl-voux-order .tpl-voux-accent { background: #ee4d2d; }
	.tpl-voux-card.tpl-voux-order .tpl-voux-logo-icon { background: #ee4d2d; }
	.tpl-voux-card.tpl-voux-order .tpl-voux-badge { color: #ee4d2d; border-color: #ffd8cf; background: #fff1ee; }
	.tpl-voux-card.tpl-voux-order .tpl-voux-btn { color: #ee4d2d; border-color: #ee4d2d; }
	.tpl-voux-card.tpl-voux-category { border-color: #fed7aa; }
	.tpl-voux-card.tpl-voux-category .tpl-voux-accent { background: #ea580c; }
	.tpl-voux-card.tpl-voux-category .tpl-voux-logo-icon { background: #ea580c; }
	.tpl-voux-card.tpl-voux-category .tpl-voux-badge { color: #ea580c; border-color: #fed7aa; background: #fff7ed; }
	.tpl-voux-card.tpl-voux-category .tpl-voux-btn { color: #ea580c; border-color: #ea580c; }
	.tpl-voux-card.tpl-voux-all { border-color: #ddd6fe; }
	.tpl-voux-card.tpl-voux-all .tpl-voux-accent { background: #7c3aed; }
	.tpl-voux-card.tpl-voux-all .tpl-voux-logo-icon { background: #7c3aed; }
	.tpl-voux-card.tpl-voux-all .tpl-voux-badge { color: #7c3aed; border-color: #ddd6fe; background: #f5f3ff; }
	.tpl-voux-card.tpl-voux-all .tpl-voux-btn { color: #7c3aed; border-color: #7c3aed; }
	.tpl-voux-card.tpl-voux-payment { border-color: #bbf7d0; }
	.tpl-voux-card.tpl-voux-payment .tpl-voux-accent { background: #16a34a; }
	.tpl-voux-card.tpl-voux-payment .tpl-voux-logo-icon { background: #16a34a; }
	.tpl-voux-card.tpl-voux-payment .tpl-voux-badge { color: #16a34a; border-color: #bbf7d0; background: #ecfdf5; }
	.tpl-voux-card.tpl-voux-payment .tpl-voux-btn { color: #16a34a; border-color: #16a34a; }

	/* ===== Bố cục thẻ voucher trên mobile — đồng bộ y chang home_user.php =====
	   Giữ 4 cột 1 hàng (accent | brand | main | side), side là cột max-content xếp
	   dọc bên phải (KHÔNG xuống hàng 2). Prefix .vcp-page để thắng rule global. */
	@media (max-width: 640px) {
		.vcp-page .tpl-voux-card {
			grid-template-columns: 5px 44px minmax(0, 1fr) max-content;
			grid-template-rows: none;
			font-size: 0.92em;
		}
		/* Đường khía neo theo cạnh phải cột thương hiệu để luôn khớp dù cột co lại */
		.vcp-page .tpl-voux-card::before { display: none; }
		.vcp-page .tpl-voux-brand {
			padding: 6px 4px;
			gap: 4px;
			border-right: 1px dashed #e5e7eb;
		}
		.vcp-page .tpl-voux-logo-icon { width: 22px; height: 22px; font-size: 0.72rem; }
		.vcp-page .tpl-voux-brand-name { font-size: 0.52rem; }
		.vcp-page .tpl-voux-main { padding: 6px 8px; gap: 2px; min-width: 0; }
		.vcp-page .tpl-voux-main-title { font-size: 0.74rem; line-height: 1.2; overflow: hidden; }
		.vcp-page .tpl-voux-sub { font-size: 0.62rem; }
		.vcp-page .tpl-voux-badge { font-size: 0.54rem; padding: 1px 5px; }
		.vcp-page .tpl-voux-side {
			grid-column: auto;
			grid-row: auto;
			display: grid;
			align-content: center;
			justify-items: center;
			gap: 4px;
			padding: 6px 6px;
			min-width: 0;
			border-top: 0;
		}
		.vcp-page .tpl-voux-btn { font-size: 0.6rem; padding: 3px 7px; }
		.vcp-page .tpl-voux-tag { font-size: 0.56rem; }
		.vcp-page .tpl-voux-qty { font-size: 0.56rem; padding: 1px 5px; }
	}
</style>

<div class="vcp-page">
	<div class="vcp-shell" id="vcpRoot">
		<section class="vcp-head">
			<div>
				<div class="vcp-title">Kho voucher của bạn</div>
				<div class="vcp-meta" id="vcpMeta">Đang tải voucher...</div>
			</div>
		</section>

		<section class="vcp-codebar">
			<div class="vcp-code-label">Mã Voucher</div>
			<input type="text" id="vcpCodeInput" class="vcp-code-input" placeholder="Nhập mã voucher tại đây" maxlength="255" autocomplete="off">
			<button type="button" id="vcpSaveBtn" class="vcp-save-btn" disabled>Lưu mã</button>
		</section>

		<section class="vcp-tabs-wrap">
			<div class="vcp-tabs" role="tablist" aria-label="Bộ lọc voucher">
				<button type="button" class="vcp-tab active" data-vcp-tab="all">Tất cả</button>
				<button type="button" class="vcp-tab" data-vcp-tab="order">Mã giảm giá</button>
				<button type="button" class="vcp-tab" data-vcp-tab="shipping">Mã ship</button>
				<button type="button" class="vcp-tab" data-vcp-tab="saved">Đã lưu</button>
			</div>
		</section>

		<div class="vcp-list" id="vcpList"></div>
		<div class="vcp-empty d-none" id="vcpEmpty">Không có voucher phù hợp với bộ lọc hiện tại.</div>
	</div>
</div>

<script>
(function(){
	if (typeof jQuery === 'undefined') return;
	const $ = jQuery;
	const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
	const DETAIL_URL = '<?= h($baseUrl) ?>/view-voucher';
	const CATEGORY_MAP = <?= json_encode($categoryMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

	const state = {
		tab: 'all',
		coupons: [],
		savedCodes: []
	};

	const $tabs = $('.vcp-tab');
	const $list = $('#vcpList');
	const $empty = $('#vcpEmpty');
	const $meta = $('#vcpMeta');
	const $codeInput = $('#vcpCodeInput');
	const $saveBtn = $('#vcpSaveBtn');

	const esc = (v) => $('<div>').text(String(v ?? '')).html();

	function formatVND(n){
		const raw = Number(n) || 0;
		// Làm tròn về 1.000đ gần nhất cho hiển thị, chỉ làm tròn lên khi phần lẻ >= 500đ
		const val = raw > 0 ? Math.round(raw / 1000) * 1000 : 0;
		try {
			return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
		} catch(e) {
			return Math.round(val) + 'đ';
		}
	}

	function formatDate(raw){
		const txt = String(raw || '').trim();
		if (!txt) return '';
		const d = txt.split(' ')[0];
		const parts = d.split('-');
		if (parts.length !== 3) return txt;
		return parts[2] + '/' + parts[1] + '/' + parts[0];
	}

	function formatExpiry(raw){
		const txt = String(raw || '').trim();
		if (!txt) return { text: '', urgent: false, expired: false };
		const end = new Date(txt.replace(' ', 'T'));
		if (isNaN(end.getTime())) return { text: 'HSD: ' + formatDate(txt), urgent: false, expired: false };
		const now = new Date();
		const diffMs = end.getTime() - now.getTime();
		if (diffMs <= 0) return { text: 'Đã hết hạn', urgent: true, expired: true };
		const diffH = Math.floor(diffMs / 3600000);
		const diffM = Math.floor((diffMs % 3600000) / 60000);
		if (diffH < 24) {
			const parts = [];
			if (diffH > 0) parts.push(diffH + ' giờ');
			parts.push(diffM + ' phút');
			return { text: 'Còn lại ' + parts.join(' '), urgent: true, expired: false };
		}
		const diffD = Math.ceil(diffMs / 86400000);
		if (diffD <= 3) return { text: 'Còn ' + diffD + ' ngày', urgent: true, expired: false };
		return { text: 'HSD: ' + formatDate(txt), urgent: false, expired: false };
	}

	function computeMaxText(row){
		if (window.pmVoucher && typeof window.pmVoucher.maxText === 'function') {
			const r = window.pmVoucher.maxText(row || {});
			if (r) return r;
		}
		const max = Number(row.max_discount || 0);
		if (max > 0) return 'Giảm tối đa ' + formatVND(max);
		return '';
	}

	function classifyType(row){
		const tpl = String(row.voucher_template || '').trim().toLowerCase();
		if (tpl === 'shipping_discount') return 'shipping';
		if (tpl === 'payment_discount') return 'payment';
		if (tpl === 'only_category_discount') return 'only_category';
		if (tpl === 'category_discount') return 'category';
		return 'order';
	}

	function voucherTargets(row){
		const raw = String(row.discount_target || '').toLowerCase();
		const list = raw.split(',').map(t => t.trim()).filter(Boolean);
		if (list.includes('shipping')) return ['shipping'];
		return ['order'];
	}

	function isSaved(row){
		const code = String(row.code || '').trim().toUpperCase();
		if (!code) return false;
		if (row.is_saved || row.saved) return true;
		return state.savedCodes.includes(code);
	}

	function rowVisible(row){
		const targets = voucherTargets(row);
		if (state.tab === 'all') return true;
		if (state.tab === 'saved') return isSaved(row);
		if (state.tab === 'shipping') return targets.includes('shipping');
		if (state.tab === 'order') return targets.includes('order');
		return true;
	}

	function buildMeta(row){
		// Ưu tiên dùng helper chung pmVoucherCard nếu có để đồng bộ logic với các nơi khác
		if (window.pmVoucherCard && typeof window.pmVoucherCard.computeMeta === 'function') {
			const meta = window.pmVoucherCard.computeMeta(row || {}, { categoryMap: CATEGORY_MAP });
			// Map về cấu trúc cũ để phần renderCards không cần đổi nhiều
			let cardCls = 'tpl-voux-card tpl-voux-order';
			if (meta.variant === 'ship') cardCls = 'tpl-voux-card tpl-voux-ship';
			else if (meta.variant === 'category') cardCls = 'tpl-voux-card tpl-voux-category';
			else if (meta.variant === 'all') cardCls = 'tpl-voux-card tpl-voux-all';
			else if (meta.variant === 'payment') cardCls = 'tpl-voux-card tpl-voux-payment';
			return {
				cardCls,
				logoIconHtml: meta.iconHtml,
				brandName: meta.typeLabel,
				tagText: meta.tagLabel,
				titleText: meta.titleText,
				minText: meta.minText,
				maxText: meta.maxText || computeMaxText(row),
				endText: meta.endText,
				categoryNames: meta.categoryNames || [],
				paymentLabels: meta.paymentLabels || [],
				primaryTarget: meta.primaryTarget || 'order'
			};
		}

		// Fallback: logic cũ nếu helper chung chưa sẵn sàng
		const typeKey = classifyType(row);
		const targets = voucherTargets(row);
		const primaryTarget = targets[0] || 'order';

		let cardCls = 'tpl-voux-card tpl-voux-order';
		let logoIconHtml = '<i class="bi bi-percent"></i>';
		let brandName = 'Giảm giá';
		let tagText = 'Đơn hàng';

		if (typeKey === 'only_category') {
			cardCls = 'tpl-voux-card tpl-voux-category';
			logoIconHtml = '<i class="bi bi-grid-3x3-gap"></i>';
			brandName = 'Ngành hàng';
			tagText = 'Danh mục';
		} else if (typeKey === 'category') {
			cardCls = 'tpl-voux-card tpl-voux-all';
			logoIconHtml = '<i class="bi bi-collection"></i>';
			brandName = 'Toàn ngành';
			tagText = 'Toàn ngành';
		} else if (typeKey === 'shipping') {
			cardCls = 'tpl-voux-card tpl-voux-ship';
			logoIconHtml = '<i class="bi bi-truck"></i>';
			brandName = 'Vận chuyển';
			tagText = 'Vận chuyển';
		} else if (typeKey === 'payment') {
			cardCls = 'tpl-voux-card tpl-voux-payment';
			logoIconHtml = '<i class="bi bi-credit-card-2-front"></i>';
			brandName = 'Thanh toán';
			tagText = 'Thanh toán';
		}

		const unit = String(row.value_unit || '').toLowerCase();
		let titleText = '';
		if (unit === 'percent') {
			titleText = 'Mã giảm ' + (row.value || 0) + '% trên ' + (primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng');
		} else {
			titleText = 'Mã giảm ' + formatVND(row.value || 0) + ' trên ' + (primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng');
		}

		const min = Number(row.min_subtotal || 0);
		let minText = '';
		if (!min) {
			minText = 'Áp dụng mọi đơn';
		} else {
			minText = 'Đơn tối thiểu ' + formatVND(min);
		}

		const endDate = formatDate(row.end_at);
		const endText = endDate ? ('HSD: ' + endDate) : '';

		const categoryNames = [];
		if (typeKey === 'only_category' || typeKey === 'category') {
			if (row.apply_category_ids) {
				String(row.apply_category_ids).split(',').map(id => id.trim()).filter(Boolean).forEach(id => {
					const name = CATEGORY_MAP[id];
					if (name) categoryNames.push(name);
				});
			}
		}

		const paymentLabels = [];
		if (row.payment_methods) {
			String(row.payment_methods).split(',').map(k => k.trim().toLowerCase()).filter(Boolean).forEach(k => {
				if (k === 'cod') paymentLabels.push('COD');
				else if (k === 'vnpay') paymentLabels.push('VN PAY');
				else if (k === 'momo') paymentLabels.push('MOMO');
				else paymentLabels.push(k.toUpperCase());
			});
		}

		return { cardCls, logoIconHtml, brandName, tagText, titleText, minText, maxText: computeMaxText(row), endText, categoryNames, paymentLabels, primaryTarget };
	}

	function renderCards(){
		const rows = state.coupons.filter(rowVisible);
		if (!rows.length) {
			$list.empty();
			$empty.removeClass('d-none');
			$meta.text('0 voucher');
			return;
		}

		const html = rows.map(row => {
			const code = String(row.code || '').trim();
			const safeCode = esc(code);
			const saved = isSaved(row);
			const useLabel = saved ? 'Đã lưu' : 'Lưu mã';
			const meta = buildMeta(row);

			let categoryBadges = '';
			if (meta.categoryNames && meta.categoryNames.length) {
				const maxShow = 3;
				const total = meta.categoryNames.length;
				const visible = meta.categoryNames.slice(0, maxShow);
				categoryBadges = visible.map(name => '<span class="tpl-voux-badge">' + esc(name) + '</span>').join(' ');
				if (total > maxShow) {
					const moreCount = total - maxShow;
					categoryBadges += ' <span class="tpl-voux-badge">+' + esc(moreCount) + '</span>';
				}
			}
			const paymentBadges = meta.paymentLabels && meta.paymentLabels.length
				? meta.paymentLabels.map(l => '<span class="tpl-voux-badge">' + esc(l) + '</span>').join(' ')
				: '';

			const detailHref = DETAIL_URL + '?code=' + encodeURIComponent(code);

			// Fallback: luôn tính từ raw row data nếu meta trả về rỗng
			const minVal = Number(row.min_subtotal || 0);
			const minText = meta.minText || (minVal > 0 ? ('Đơn tối thiểu ' + formatVND(minVal)) : 'Áp dụng mọi đơn');
			const maxText = meta.maxText || computeMaxText(row);
			const expiry = formatExpiry(row.end_at);
			const termsTitle = esc(minText + (maxText ? (' • ' + maxText) : '') + (expiry.text ? (' • ' + expiry.text) : ''));

			let subLines = [];
			const subParts = [esc(minText)];
			if (maxText) subParts.push(esc(maxText));
			subLines.push('<div class="tpl-voux-sub">' + subParts.join(' · ') + '</div>');
			if (categoryBadges || paymentBadges) {
				subLines.push('<div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:2px">' + categoryBadges + paymentBadges + '</div>');
			}
			if (expiry.text) {
				const expCls = expiry.expired ? 'color:#991b1b;font-weight:700' : (expiry.urgent ? 'color:#ef4444;font-weight:600' : '');
				subLines.push('<div class="tpl-voux-foot"><span class="tpl-voux-time"' + (expCls ? ' style="' + expCls + '"' : '') + '>' + esc(expiry.text) + '</span></div>');
			}

			return ''
				+ '<article class="' + meta.cardCls + (expiry.expired ? ' opacity-50' : '') + '">' 
				+ '  <div class="tpl-voux-accent"></div>'
				+ '  <div class="tpl-voux-brand">'
				+ '    <span class="tpl-voux-logo-icon">' + meta.logoIconHtml + '</span>'
				+ '    <div class="tpl-voux-brand-name">' + esc(meta.brandName) + '</div>'
				+ '  </div>'
				+ '  <div class="tpl-voux-main">'
				+ '    <div class="tpl-voux-main-title">' + esc(meta.titleText) + '</div>'
				+ subLines.join('')
				+ '  </div>'
				+ '  <div class="tpl-voux-side">'
				+ '    <button type="button" class="tpl-voux-btn vcp-use-btn' + (saved ? ' active' : '') + '" data-vcp-use="' + safeCode + '" data-vcp-target="' + esc(meta.primaryTarget) + '">' + esc(useLabel) + '</button>'
				+ '    <span class="tpl-voux-tag"><a href="' + detailHref + '" class="voux-tnc" title="' + termsTitle + '" target="_blank" rel="noopener">Điều kiện</a></span>'
                //+ '    <span class="tpl-voux-tag">' + esc(meta.tagText) + '</span>'
				+ '  </div>'
				+ '</article>';
		}).join('');

		$list.html(html);
		$empty.addClass('d-none');
		$meta.text(rows.length + ' voucher');
	}

	function updateTabCount(){
		$tabs.each(function(){
			const key = String($(this).data('vcp-tab') || 'all');
			const baseLabel = String($(this).data('base-label') || $(this).text() || '').replace(/\s*\(\d+\)\s*$/, '');
			$(this).data('base-label', baseLabel);
			const count = state.coupons.filter(row => {
				if (key === 'all') return true;
				if (key === 'saved') return isSaved(row);
				const targets = voucherTargets(row);
				if (key === 'shipping') return targets.includes('shipping');
				if (key === 'order') return targets.includes('order');
				return true;
			}).length;
			$(this).text(baseLabel + ' (' + count + ')');
		});
	}

	function loadSavedCodes(){
		return $.get(API, { ajax: 'my_saved_vouchers' })
			.done(res => {
				if (res && res.ok && Array.isArray(res.codes)) {
					state.savedCodes = res.codes.map(c => String(c || '').trim().toUpperCase());
				} else {
					state.savedCodes = [];
				}
			})
			.fail(() => { state.savedCodes = []; });
	}

	function loadVouchers(){
		$meta.text('Đang tải voucher...');
		return $.get(API, { ajax: 'vouchers_public', target: 'all' })
			.done(res => {
				if (!res || !res.ok) {
					$meta.text('Không tải được voucher.');
					state.coupons = [];
					renderCards();
					return;
				}
				state.coupons = Array.isArray(res.data) ? res.data : [];
				updateTabCount();
				renderCards();
			})
			.fail(() => {
				$meta.text('Không thể kết nối máy chủ voucher.');
				state.coupons = [];
				renderCards();
			});
	}

	// Sự kiện
	$('.vcp-tabs').on('click', '.vcp-tab', function(){
		const key = String($(this).data('vcp-tab') || 'all');
		state.tab = key;
		$('.vcp-tab').removeClass('active');
		$(this).addClass('active');
		renderCards();
	});

	$codeInput.on('input', function(){
		const has = String($(this).val() || '').trim().length > 0;
		$saveBtn.prop('disabled', !has);
	});

	$saveBtn.on('click', function(){
		const code = String($codeInput.val() || '').trim();
		if (!code) return;
		$.post(API, { action: 'voucher_save', code })
			.done(res => {
				if (!res || !res.ok) {
					if (window.toastr && toastr.warning) toastr.warning((res && res.msg) || 'Không lưu được mã voucher');
					return;
				}
				if (window.toastr && toastr.success) toastr.success(res.msg || ('Đã lưu mã: ' + code));
				$codeInput.val('');
				$saveBtn.prop('disabled', true);
				$.when(loadSavedCodes(), loadVouchers()).done(() => {
					updateTabCount();
					renderCards();
				});
			})
			.fail(() => {
				if (window.toastr && toastr.error) toastr.error('Lỗi kết nối server');
			});
	});

	$list.on('click', '.vcp-use-btn', function(){
		const code = String($(this).data('vcp-use') || '').trim();
		if (!code) return;
		if (state.savedCodes.includes(code.toUpperCase())) {
			if (window.toastr && toastr.info) toastr.info('Mã đã được lưu trước đó.');
			return;
		}
		$.post(API, { action: 'voucher_save', code })
			.done(res => {
				if (!res || !res.ok) {
					if (window.toastr && toastr.warning) toastr.warning((res && res.msg) || 'Không lưu được mã');
					return;
				}
				if (window.toastr && toastr.success) toastr.success(res.msg || ('Đã lưu mã: ' + code));
				state.savedCodes.push(code.toUpperCase());
				updateTabCount();
				renderCards();
			})
			.fail(() => {
				if (window.toastr && toastr.error) toastr.error('Lỗi kết nối server');
			});
	});

	$.when(loadSavedCodes(), loadVouchers()).done(() => {
		updateTabCount();
		renderCards();
	});
})();
</script>
