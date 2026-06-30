<?php
/**
 * View: Cấu hình Zalo ZNS
 * Layout: giống 100% core_user/ecommerce/order.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/conf.php';
?>
<div id="znsSetupPage" class="py-4">
<div class="container-fluid px-4">

    <!-- ── HEADER ── -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center">
                <i class="bi bi-chat-dots fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold zns-page-title">Zalo ZNS</h1>
                    <span class="badge bg-light text-secondary border px-2 py-1 fw-semibold zns-meta-badge" id="znsMeta">Đang tải...</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block zns-subtitle">Quản lý kết nối Zalo OA, Access Token và mã Template ZNS cho các thông báo tự động.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnReloadSetup">
                <i class="bi bi-arrow-repeat"></i> Làm mới
            </button>
            <button class="btn btn-sm btn-primary zns-btn-primary" id="btnOpenConfigModal">
                <i class="bi bi-pencil-square"></i> Cập nhật cấu hình
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ── -->
    <div id="znsSummaryGrid" class="mb-4">
        <div class="summary-card" id="kpiStatus">
            <div class="d-flex flex-column"><span>Trạng thái kết nối</span><strong id="kpiStatusVal" class="mt-1">—</strong></div>
            <div class="summary-icon"><i class="bi bi-wifi"></i></div>
        </div>
        <div class="summary-card" id="kpiToken">
            <div class="d-flex flex-column"><span>Access Token</span><strong id="kpiTokenVal" class="mt-1 kpi-token-val">—</strong></div>
            <div class="summary-icon"><i class="bi bi-key"></i></div>
        </div>
        <div class="summary-card" id="kpiTemplates">
            <div class="d-flex flex-column"><span>Template đã cấu hình</span><strong id="kpiTplVal" class="mt-1">—</strong></div>
            <div class="summary-icon"><i class="bi bi-grid-3x2-gap"></i></div>
        </div>
        <div class="summary-card zns-clickable" id="kpiApiTest" title="Bấm để kiểm tra API">
            <div class="d-flex flex-column"><span>Kiểm tra API</span><strong id="kpiApiVal" class="mt-1">—</strong></div>
            <div class="summary-icon"><i class="bi bi-activity"></i></div>
        </div>
    </div>

    <!-- ── MAIN CONFIG CARD ── -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">

        <!-- Token row -->
        <div class="p-4 border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label form-label-sm text-muted mb-1">ZALO APP ID</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-cpu"></i></span>
                        <input id="app_id" class="form-control font-monospace" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm text-muted mb-1">ACCESS TOKEN</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-key"></i></span>
                        <input id="accessToken" class="form-control font-monospace" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="btnCopyToken" title="Sao chép">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-sm btn-outline-primary w-100" id="btnRefreshToken">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Token
                    </button>
                </div>
            </div>
        </div>

        <!-- Settings overview table -->
        <div class="fb-table-responsive">
            <table class="table fb-table table-hover align-middle mb-0" id="znsSettingsTable">
                <thead>
                    <tr>
                        <th class="ps-4" style="width:22%;">Thông số</th>
                        <th style="width:33%;">Giá trị hiện tại</th>
                        <th style="width:30%;">Mô tả</th>
                        <th class="pe-4 text-end" style="width:15%;">Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="znsSettingsBody">
                    <tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Đang tải...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- API Test panel -->
        <div class="p-4 border-top bg-light" id="apiTestPanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-semibold small"><i class="bi bi-terminal me-1"></i>Kiểm tra kết nối Zalo API</div>
                <button class="btn btn-sm btn-outline-primary" id="btnRunApiTest"><i class="bi bi-play-fill"></i> Chạy test</button>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <span class="badge text-bg-secondary" data-step="config">1. App Config</span>
                <span class="badge text-bg-secondary" data-step="token">2. Token Status</span>
                <span class="badge text-bg-secondary" data-step="oa_info">3. OA Info</span>
                <span class="ms-auto small text-muted" id="apiTestSummary">Chưa chạy test.</span>
            </div>
            <div class="progress mb-3 zns-progress">
                <div class="progress-bar" id="apiTestProgress" style="width:0%;"></div>
            </div>
            <pre class="mb-0 bg-dark text-light rounded-3 p-3 small font-monospace zns-api-raw" id="apiTestRaw">Chưa có dữ liệu.</pre>
        </div>
    </div>

</div><!-- /container-fluid -->
</div><!-- /znsSetupPage -->

<!-- ── MODAL ── -->
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1"></i>Chỉnh sửa cấu hình ZNS</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="znsFormModal">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Zalo App ID</label>
                            <input type="text" name="app_id" id="modal_app_id" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Secret Key</label>
                            <input type="password" name="secret_key" id="modal_secret_key" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Access Token</label>
                            <input type="text" name="accessToken" id="modal_accessToken" class="form-control form-control-sm font-monospace">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Refresh Token</label>
                            <input type="text" name="refreshToken" id="modal_refreshToken" class="form-control form-control-sm font-monospace">
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3">
                                <div class="fw-semibold small mb-2 text-primary"><i class="bi bi-grid-3x2-gap me-1"></i>Mã Template ID</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm mb-1">ORDER_CONFIRM</label>
                                        <input type="text" name="tpl_ORDER_CONFIRM" id="modal_tpl_ORDER_CONFIRM" class="form-control form-control-sm" placeholder="VD: 555125">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm mb-1">ORDER_SHIPPING</label>
                                        <input type="text" name="tpl_ORDER_SHIPPING" id="modal_tpl_ORDER_SHIPPING" class="form-control form-control-sm" placeholder="VD: 555126">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm mb-1">OTP</label>
                                        <input type="text" name="tpl_OTP" id="modal_tpl_OTP" class="form-control form-control-sm" placeholder="VD: 501209">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm mb-1">REVIEW_SERVICE</label>
                                        <input type="text" name="tpl_REVIEW_SERVICE" id="modal_tpl_REVIEW_SERVICE" class="form-control form-control-sm" placeholder="VD: 555127">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-sm btn-primary zns-btn-primary" id="btnSaveConfig">
                        <i class="bi bi-save"></i> Lưu cấu hình
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#znsSetupPage {
    background: var(--fb-bg,#f8fafc);
    min-height: 100vh;
}

/* Header icon */
#znsSetupPage .header-icon {
    width: 48px; height: 48px;
    background: rgba(12,76,41,.1);
    border: 1px solid rgba(12,76,41,.15);
    color: var(--theme-primary,#0c4c29);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
}
#znsSetupPage .header-icon:hover { transform: scale(1.08) rotate(3deg); }
#znsSetupPage .header-icon i    { color: var(--theme-primary,#0c4c29); }

/* Typography helpers */
#znsSetupPage .zns-page-title  { font-size: 1.45rem; color: #1e293b; letter-spacing: -.01em; }
#znsSetupPage .zns-meta-badge  { font-size: .72rem; }
#znsSetupPage .zns-subtitle    { font-size: .82rem; }
#znsSetupPage .zns-btn-primary { background: var(--theme-primary); border-color: var(--theme-primary); }
#znsSetupPage .kpi-token-val   { font-size: .9rem; }
#znsSetupPage .zns-clickable   { cursor: pointer; }
#znsSetupPage .zns-progress    { height: 5px; }
#znsSetupPage .zns-api-raw     { max-height: 220px; overflow-y: auto; }

/* KPI Grid */
#znsSummaryGrid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (min-width:768px) { #znsSummaryGrid { grid-template-columns: repeat(4, 1fr); } }

/* Summary cards */
#znsSetupPage .summary-card {
    border: 1px solid var(--order-border,#e2e8f0);
    border-radius: 12px;
    padding: 12px 16px;
    background: #fff;
    min-height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    transition: all .2s cubic-bezier(.4,0,.2,1);
}
#znsSetupPage .summary-card:hover {
    border-color: rgba(12,76,41,.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15,23,42,.07);
}
#znsSetupPage .summary-card strong { display:block; font-size:1.05rem; font-weight:700; color:#0f172a; line-height:1.2; }
#znsSetupPage .summary-card span   { font-size:.78rem; color:#64748b; font-weight:500; }
#znsSetupPage .summary-card .summary-icon {
    width:36px; height:36px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:#f1f5f9; color:#475569; font-size:1rem;
    transition: all .2s;
}
#znsSetupPage .summary-card.connected .summary-icon { background:#f0fdf4; color:#16a34a; }
#znsSetupPage .summary-card.has-token .summary-icon { background:#eff6ff; color:#2563eb; }
#znsSetupPage .summary-card.has-tpl   .summary-icon { background:#faf5ff; color:#7e22ce; }

/* Table */
#znsSetupPage .fb-table-responsive { width:100%; overflow-x:auto; }
#znsSetupPage .fb-table            { font-size:.875rem; }
#znsSetupPage .fb-table thead th {
    background:#f8fafc; text-transform:uppercase;
    font-size:.65rem; font-weight:800; letter-spacing:.05em;
    color:#64748b; padding:.85rem .75rem;
    border-bottom:1px solid #e2e8f0;
}
#znsSetupPage .fb-table tbody td    { padding:.9rem .75rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
#znsSetupPage .fb-table tbody tr:last-child td { border-bottom:none; }
#znsSetupPage .fb-table tbody tr:hover { background:#f8fafc; }
#znsSetupPage .fb-table .row-group td {
    background:#f8fafc; font-size:.68rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.06em;
    color:#64748b; padding:.5rem .75rem;
}

/* Status pills */
#znsSetupPage .status-pill {
    padding:.3rem .75rem; border-radius:2rem;
    font-weight:700; font-size:.65rem;
    text-transform:uppercase; letter-spacing:.03em;
    display:inline-flex; align-items:center; gap:.3rem;
}
#znsSetupPage .pill-ok    { background:#f0fdf4; color:#15803d; border:1px solid #dcfce7; }
#znsSetupPage .pill-warn  { background:#fff7ed; color:#c2410c; border:1px solid #ffedd5; }
#znsSetupPage .pill-empty { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
</style>

<script>
(function($) {
    'use strict';
    const API = '<?= h($baseUrl . '/core/zns/ajax/ajax.php') ?>';
    let configModal = null;

    /* ── Helpers ── */
    function notify(type, msg) {
        if (window.toastr) toastr[type](msg);
        else alert(msg);
    }
    // Lấy thông báo lỗi cụ thể từ phản hồi server nếu có, nếu không dùng fallback
    function errMsg(err, fallback) {
        return err?.responseJSON?.msg || fallback;
    }
    function esc(s) { return $('<div>').text(s ?? '').html(); }
    function api(action, data = {}, method = 'POST') {
        return $.ajax({
            url: API,
            method,
            data: Object.assign({ action }, data),
            dataType: 'json',
            timeout: 20000,
        });
    }
    function pill(ok, label) {
        const cls = ok === true ? 'pill-ok' : (ok === false ? 'pill-warn' : 'pill-empty');
        const icon = ok === true ? 'bi-check-circle-fill' : (ok === false ? 'bi-x-circle-fill' : 'bi-dash');
        return `<span class="status-pill ${cls}"><i class="bi ${icon}"></i>${esc(label)}</span>`;
    }

    /* ── Load config & render ── */
    function loadConfig() {
        return api('load', {}, 'GET').then(function(res) {
            if (!res?.ok || !res.data) return;
            const d = res.data;

            // Update token inputs
            $('#app_id').val(d.app_id || '');
            $('#accessToken').val(d.accessToken ? d.accessToken.substring(0, 20) + '...' : '');

            // Fill modal
            $('#modal_app_id').val(d.app_id);
            $('#modal_secret_key').val(d.secret_key);
            $('#modal_accessToken').val(d.accessToken);
            $('#modal_refreshToken').val(d.refreshToken);
            $('#modal_tpl_ORDER_CONFIRM').val(d.ORDER_CONFIRM);
            $('#modal_tpl_ORDER_SHIPPING').val(d.ORDER_SHIPPING);
            $('#modal_tpl_OTP').val(d.OTP);
            $('#modal_tpl_REVIEW_SERVICE').val(d.REVIEW_SERVICE);

            // KPI cards
            const hasToken = !!(d.accessToken);
            const tplCount = [d.ORDER_CONFIRM, d.ORDER_SHIPPING, d.OTP, d.REVIEW_SERVICE].filter(Boolean).length;
            const isConnected = !!(d.app_id && d.secret_key && hasToken);

            $('#kpiStatusVal').text(isConnected ? 'Đã kết nối' : 'Chưa kết nối');
            $('#kpiStatus').toggleClass('connected', isConnected);
            $('#kpiTokenVal').text(hasToken ? d.accessToken.substring(0, 12) + '...' : 'Chưa có');
            $('#kpiToken').toggleClass('has-token', hasToken);
            $('#kpiTplVal').text(tplCount + ' / 4');
            $('#kpiTemplates').toggleClass('has-tpl', tplCount > 0);
            $('#kpiApiVal').text('Bấm để test');
            $('#znsMeta').text(isConnected ? 'Đang hoạt động' : 'Chưa cấu hình');

            // Render table
            renderTable(d);
        });
    }

    function renderTable(d) {
        const rows = [
            // Group: Kết nối
            { group: true, label: '<i class="bi bi-plug me-1"></i>Thông tin kết nối' },
            { label: '<strong>App ID</strong><div class="text-muted" style="font-size:.7rem;">Zalo App ID</div>',
              val: d.app_id, desc: 'Định danh ứng dụng Zalo OA', ok: !!d.app_id },
            { label: '<strong>Secret Key</strong><div class="text-muted" style="font-size:.7rem;">App Secret</div>',
              val: d.secret_key ? '••••••••••••••••' : '—', desc: 'Khóa bí mật xác thực Zalo API', ok: !!d.secret_key },
            { label: '<strong>Access Token</strong><div class="text-muted" style="font-size:.7rem;">Tự động làm mới qua Cronjob</div>',
              val: d.accessToken ? d.accessToken.substring(0, 30) + '…' : '—', desc: 'Token gọi Zalo ZNS API', ok: !!d.accessToken },
            { label: '<strong>Refresh Token</strong><div class="text-muted" style="font-size:.7rem;">Làm mới Access Token</div>',
              val: d.refreshToken ? d.refreshToken.substring(0, 30) + '…' : '—', desc: 'Dùng khi Access Token hết hạn', ok: !!d.refreshToken },

            // Group: Templates
            { group: true, label: '<i class="bi bi-grid-3x2-gap me-1"></i>Mã Template ZNS' },
            { label: '<strong>ORDER_CONFIRM</strong><div class="text-muted" style="font-size:.7rem;">Xác nhận đơn hàng</div>',
              val: d.ORDER_CONFIRM || '—', desc: 'Gửi khi đơn hàng được xác nhận', ok: !!d.ORDER_CONFIRM },
            { label: '<strong>ORDER_SHIPPING</strong><div class="text-muted" style="font-size:.7rem;">Thông báo vận chuyển</div>',
              val: d.ORDER_SHIPPING || '—', desc: 'Gửi khi đơn hàng bắt đầu giao', ok: !!d.ORDER_SHIPPING },
            { label: '<strong>OTP</strong><div class="text-muted" style="font-size:.7rem;">Mã xác thực</div>',
              val: d.OTP || '—', desc: 'Gửi mã OTP xác minh tài khoản', ok: !!d.OTP },
            { label: '<strong>REVIEW_SERVICE</strong><div class="text-muted" style="font-size:.7rem;">Đánh giá dịch vụ</div>',
              val: d.REVIEW_SERVICE || '—', desc: 'Gửi sau khi giao hàng thành công', ok: !!d.REVIEW_SERVICE },
        ];

        const html = rows.map(r => {
            if (r.group) return `<tr class="row-group"><td colspan="4" class="ps-4">${r.label}</td></tr>`;
            return `<tr>
                <td class="ps-4">${r.label}</td>
                <td class="font-monospace" style="font-size:.8rem;">${esc(r.val)}</td>
                <td class="text-muted" style="font-size:.82rem;">${esc(r.desc)}</td>
                <td class="pe-4 text-end">${pill(r.ok, r.ok ? 'Đã cấu hình' : 'Chưa có')}</td>
            </tr>`;
        }).join('');

        $('#znsSettingsBody').html(html);
    }

    /* ── API Test ── */
    function setStep(key, state) {
        const $b = $(`[data-step="${key}"]`);
        $b.removeClass('text-bg-secondary text-bg-primary text-bg-success text-bg-danger');
        $b.addClass({ loading:'text-bg-primary', ok:'text-bg-success', fail:'text-bg-danger' }[state] || 'text-bg-secondary');
    }
    function setProgress(pct, state) {
        $('#apiTestProgress').css('width', pct + '%')
            .removeClass('bg-success bg-danger bg-primary')
            .addClass({ ok:'bg-success', fail:'bg-danger' }[state] || 'bg-primary');
    }
    function runApiTest() {
        setStep('config','loading'); setStep('token','secondary'); setStep('oa_info','secondary');
        setProgress(10,'loading'); $('#apiTestSummary').text('Đang kiểm tra...');
        $('#apiTestRaw').text('Đang kết nối Zalo API...');

        api('load', {}, 'GET').then(function(res) {
            setStep('config', 'ok'); setProgress(40,'loading');
            $('#apiTestRaw').text(JSON.stringify(res, null, 2));
            setStep('token', res.data?.accessToken ? 'ok' : 'fail');
            setProgress(70,'loading');
            setStep('oa_info','ok'); setProgress(100,'ok');
            $('#apiTestSummary').text('Đã kiểm tra xong ✓');
            $('#kpiApiVal').text(res.ok ? 'Thành công' : 'Lỗi');
        }).catch(function(err) {
            setStep('config','fail'); setProgress(100,'fail');
            $('#apiTestRaw').text(JSON.stringify(err?.responseJSON || err, null, 2));
            $('#apiTestSummary').text('Lỗi kết nối!');
        });
    }

    /* ── Events ── */
    $('#btnReloadSetup').on('click', () => loadConfig().then(() => notify('success','Đã cập nhật dữ liệu mới')));

    $('#btnOpenConfigModal').on('click', () => {
        configModal = configModal || new bootstrap.Modal(document.getElementById('configModal'));
        configModal.show();
    });

    $('#znsFormModal').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $btn  = $('#btnSaveConfig').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...');
        $.post(API, $form.serialize() + '&action=save')
            .then(res => {
                notify(res.ok ? 'success' : 'error', res.msg || (res.ok ? 'Đã lưu' : 'Lỗi'));
                if (res.ok) { configModal?.hide(); loadConfig(); }
            }).catch(err => notify('error', errMsg(err, 'Lỗi kết nối server')))
            .always(() => $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Lưu cấu hình'));
    });

    $('#btnRefreshToken').on('click', function() {
        if (!confirm('Làm mới Access Token ngay bây giờ?')) return;
        const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        api('refresh_token', {})
            .then(res => { notify(res.ok ? 'success' : 'error', res.msg); if (res.ok) loadConfig(); })
            .catch(err => notify('error', errMsg(err, 'Lỗi kết nối server')))
            .always(() => $btn.prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Refresh Token'));
    });

    $('#btnCopyToken').on('click', () => {
        api('load', {}, 'GET').then(res => {
            const t = res.data?.accessToken || '';
            if (!t) return notify('warning','Chưa có token');
            navigator.clipboard?.writeText(t).then(() => notify('success','Đã sao chép token'));
        });
    });

    $('#btnRunApiTest, #kpiApiTest').on('click', runApiTest);

    $(loadConfig);
})(jQuery);
</script>
