<?php
$apiUrl = $basePath . '/core_admin/giaohangnhanh/ajax/api.php';
require_once __DIR__ . '/../_admin_guard.php';

?>
<style>
.form-control:read-only {
background-color: var(--bs-secondary-bg);
cursor: not-allowed;
}

#apiTestSteps .badge {
    font-weight: 600;
}

#apiTestRaw {
    min-height: 140px;
    max-height: 420px;
    overflow: auto;
    white-space: pre-wrap;
}
</style>
<div class="container-fluid py-4">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08); color: var(--theme-primary, #0c4c29); border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-truck fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b; letter-spacing: -0.01em;">Cấu hình API Giao Hàng Nhanh</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="apiStatusBadge" style="font-size: 0.72rem;">Đang tải...</span>
                </div>
                <p class="text-muted mb-0 small" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Quản lý token kết nối GHN, kiểm tra API và đồng bộ dữ liệu vùng miền cho toàn hệ thống.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3 py-2 shadow-sm" href="<?= h($basePath) ?>/admin/giaohangnhanh/home" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline">Quay lại</span>
            </a>
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm text-white" id="btnReloadSetup" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-arrow-repeat fs-5 text-white"></i>
                <span class="d-none d-sm-inline">Làm mới</span>
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body">
        <div class="fw-semibold mb-3 text-uppercase text-secondary" style="font-size:.8rem; letter-spacing:.03em;">Thông tin API Giao Hàng Nhanh</div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold small mb-1">Base URL <span class="text-muted fw-normal">(cố định)</span></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                    <input id="api_base_url" class="form-control" value="https://online-gateway.ghn.vn/shiip/public-api" readonly>
                </div>
                <div class="form-text">Base URL của GHN luôn mặc định Production.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold small mb-1">API Key (Token) hiện tại</label>
                <div class="row g-2">
                    <div class="col-12 col-lg-8">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-key text-muted"></i></span>
                            <input id="api_token_current" class="form-control font-monospace" value="" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="btnCopyToken"><i class="bi bi-clipboard"></i><span class="d-none d-sm-inline ms-1">Sao chép</span></button>
                        </div>
                        <div class="form-text">Token bị khoá trên UI; dùng nút sao chép hoặc cập nhật token.</div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-primary" id="btnOpenTokenModal"><i class="bi bi-pencil-square me-1"></i> Cập nhật key</button>
                            <button class="btn btn-sm btn-outline-secondary" id="btnImportRegion"><i class="bi bi-database me-1"></i> Import vùng miền</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded-4 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <div class="fw-semibold"><i class="bi bi-activity me-1 text-primary"></i>Test nhanh GHN API</div>
                        <button class="btn btn-sm btn-outline-primary" id="btnRunApiTest"><i class="bi bi-play-fill"></i> Chạy test</button>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2" id="apiTestSteps">
                        <span class="badge text-bg-secondary" data-step="config">1. Config</span>
                        <span class="badge text-bg-secondary" data-step="province">2. Province</span>
                        <span class="badge text-bg-secondary" data-step="shop_all">3. Shop all</span>
                        <span class="badge text-bg-secondary" data-step="shop_detail">4. Shop detail</span>
                        <span class="ms-auto small text-muted" id="apiTestSummary">Chưa chạy test.</span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar" role="progressbar" style="width:0%" id="apiTestProgress"></div>
                    </div>

                    <div class="mt-2">
                        <pre class="mb-0 bg-dark text-light rounded-3 p-3 small font-monospace" id="apiTestRaw">Chưa có dữ liệu.</pre>
                    </div>
                </div>
            </div>

        </div>
        </div>
    </div>
</div>

<!-- Update Token Modal -->
<div class="modal fade" id="tokenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-key text-primary me-2"></i>Cập nhật API Key (Token)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="small text-muted mb-2">Nhập token mới để thay thế token hiện tại. Token sẽ được lưu trên server.</div>
                <label class="form-label fw-semibold small mb-1">Token mới</label>
                <div class="input-group input-group-sm">
                    <input type="password" class="form-control font-monospace" id="api_token_new" placeholder="Nhập token GHN...">
                    <button class="btn btn-outline-secondary" type="button" id="btnToggleNewToken"><i class="bi bi-eye" id="iconToggleNewToken"></i></button>
                </div>
                <div class="form-text">Không chia sẻ token ra ngoài. Chỉ admin truy cập trang này.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnSaveNewToken"><i class="bi bi-save me-1"></i> Lưu token</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?= h($apiUrl) ?>';
    let tokenModal = null;

    function parseJsonSafe(text){
        if (text && typeof text === 'object') return text;
        try { return JSON.parse(String(text || '')); } catch(e){ return null; }
    }

    function api(action, data = {}, method = 'POST'){
        return new Promise((resolve, reject) => {
            $.ajax({
                url: API + '?action=' + encodeURIComponent(action),
                method,
                dataType: 'text',
                data: data,
                timeout: 120000,
            }).done(function(raw){
                const parsed = parseJsonSafe(raw);
                if (!parsed) return reject({ ok:false, msg:'Phản hồi JSON không hợp lệ', raw });
                resolve(parsed);
            }).fail(function(xhr, textStatus){
                reject(parseJsonSafe(xhr?.responseText || '') || { ok:false, msg:textStatus || 'Request failed' });
            });
        });
    }

    function esc(text){
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function notify(message, type){
        if (window.toastr) {
            if (type === 'success') toastr.success(message);
            else if (type === 'warning') toastr.warning(message);
            else if (type === 'error') toastr.error(message);
            else toastr.info(message);
        } else {
            console.log((type || 'info').toUpperCase() + ': ' + message);
        }
    }

    function setStepState(key, state){
        const $b = $('#apiTestSteps [data-step="'+key+'"]');
        if (!$b.length) return;
        $b.removeClass('text-bg-secondary text-bg-primary text-bg-success text-bg-danger text-bg-warning');
        if (state === 'loading') $b.addClass('text-bg-primary');
        else if (state === 'ok') $b.addClass('text-bg-success');
        else if (state === 'skip') $b.addClass('text-bg-warning');
        else if (state === 'fail') $b.addClass('text-bg-danger');
        else $b.addClass('text-bg-secondary');
    }

    function setProgress(percent, state){
        const p = Math.max(0, Math.min(100, Number(percent || 0)));
        const $bar = $('#apiTestProgress');
        $bar.css('width', p + '%');
        $bar.removeClass('bg-success bg-danger bg-warning bg-primary');
        if (state === 'ok') $bar.addClass('bg-success');
        else if (state === 'fail') $bar.addClass('bg-danger');
        else if (state === 'skip') $bar.addClass('bg-warning');
        else $bar.addClass('bg-primary');
    }

    function loadConfig(){
        return api('setting_get', {}, 'GET').then(function(res){
            if (!res.ok) {
                $('#api_token_current').val('');
                notify('Không đọc được cấu hình GHN', 'warning');
                return null;
            }
            const cfg = res.config || {};
            const shop = res.active_shop || {};
            const shopId = Number(shop.shop_id || 0);
            $('#api_base_url').val('https://online-gateway.ghn.vn/shiip/public-api');
            $('#api_token_current').val(String(cfg.token_masked || 'chưa cấu hình'));
            const ready = !!cfg.enabled && !!cfg.has_token && shopId > 0;
            const label = (ready ? 'READY' : 'NOT READY') + ' | Shop ID: ' + shopId + ' | Token: ' + String(cfg.token_masked || '—');
            $('#apiTestSummary').text(label);
            // Badge trạng thái trên header
            $('#apiStatusBadge')
                .removeClass('bg-light text-secondary border-secondary-subtle bg-success-subtle text-success-emphasis border-success-subtle bg-danger-subtle text-danger-emphasis border-danger-subtle')
                .addClass(ready ? 'bg-success-subtle text-success-emphasis border-success-subtle' : 'bg-danger-subtle text-danger-emphasis border-danger-subtle')
                .html('<i class="bi ' + (ready ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill') + ' me-1"></i>' + (ready ? 'Đã kết nối' : 'Chưa sẵn sàng'));
            return { ready };
        }).catch(function(err){
            notify(err.msg || 'Không tải được cấu hình', 'error');
            return null;
        });
    }

    function runApiTest(){
        setStepState('config', 'loading');
        setStepState('province', 'idle');
        setStepState('shop_all', 'idle');
        setStepState('shop_detail', 'idle');
        setProgress(10, 'loading');
        $('#apiTestSummary').text('Đang chạy test...');
        $('#apiTestRaw').text('Đang chạy test...');

        return api('api_test', {}, 'GET').then(function(res){
            // Always show raw response
            $('#apiTestRaw').text(JSON.stringify(res || {}, null, 2));

            // Config step: always ok if response is JSON
            setStepState('config', 'ok');
            setProgress(40, 'loading');

            const tests = Array.isArray(res.tests) ? res.tests : [];
            const byKey = {};
            tests.forEach(function(t){
                if (t && t.key) byKey[String(t.key)] = t;
            });

            ['province','shop_all','shop_detail'].forEach(function(k){
                const t = byKey[k];
                if (!t) return setStepState(k, 'fail');
                if (t.skipped) return setStepState(k, 'skip');
                setStepState(k, t.ok ? 'ok' : 'fail');
            });

            const ok = !!res.ok;
            setProgress(100, ok ? 'ok' : 'fail');
            $('#apiTestSummary').text(String(res.msg || (ok ? 'Đã quét thành công' : 'Có lỗi khi quét API')));
            if (ok) notify('Đã quét thành công', 'success');
            else notify(String(res.msg || 'Có lỗi khi quét API hoặc cấu hình chưa đủ'), 'warning');
        }).catch(function(err){
            setStepState('config', 'fail');
            setProgress(100, 'fail');
            $('#apiTestSummary').text('Lỗi chạy test');
            $('#apiTestRaw').text(JSON.stringify(err || {}, null, 2));
            notify(err.msg || 'Không chạy được test GHN API', 'error');
        });
    }

    // Import nặng → chạy theo từng bước (chunk) để tránh timeout. Vòng lặp: provinces → districts (mỗi tỉnh) → wards (batch quận).
    let regionImporting = false; // guard: chặn chạy chồng (double-click) → tránh 2 alert mâu thuẫn.
    async function importRegions(){
        if (regionImporting) return; // đã có 1 lần import đang chạy → bỏ qua click thừa.
        regionImporting = true;
        const $btn = $('#btnImportRegion');
        const originalHtml = $btn.html();
        const setLabel = (t) => $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>' + t);
        $btn.prop('disabled', true);
        setLabel('Đang import tỉnh...');

        const totals = { provinces: 0, districts: 0, wards: 0 };
        try {
            // B1: tỉnh → nhận hàng đợi province_id.
            const pRes = await api('region_sync_all', { step: 'provinces' }, 'POST');
            if (!pRes.ok) { notify(pRes.msg || 'Import vùng miền thất bại', 'warning'); return; }
            totals.provinces = Number((pRes.counts || {}).provinces || 0);
            const provinceIds = Array.isArray(pRes.queue) ? pRes.queue : [];

            // B2: với mỗi tỉnh → sync quận, gom district_id vào hàng đợi phường.
            const wardQueue = [];
            for (let i = 0; i < provinceIds.length; i++) {
                setLabel('Đang import quận ('+(i+1)+'/'+provinceIds.length+')...');
                const dRes = await api('region_sync_all', { step: 'districts', province_id: provinceIds[i] }, 'POST');
                if (!dRes.ok) { notify(dRes.msg || 'Lỗi import quận/huyện', 'warning'); return; }
                totals.districts += Number((dRes.counts || {}).districts || 0);
                (Array.isArray(dRes.queue) ? dRes.queue : []).forEach((id) => wardQueue.push(id));
            }

            // B3: sync phường theo batch district_id (10 quận/lần) để giảm round-trip nhưng vẫn dưới timeout.
            const BATCH = 10;
            for (let i = 0; i < wardQueue.length; i += BATCH) {
                const batch = wardQueue.slice(i, i + BATCH);
                setLabel('Đang import phường ('+Math.min(i+BATCH, wardQueue.length)+'/'+wardQueue.length+')...');
                const wRes = await api('region_sync_all', { step: 'wards', district_ids: JSON.stringify(batch) }, 'POST');
                if (!wRes.ok) { notify(wRes.msg || 'Lỗi import phường/xã', 'warning'); return; }
                totals.wards += Number((wRes.counts || {}).wards || 0);
            }

            notify('Đã import: '+totals.provinces+' tỉnh, '+totals.districts+' quận, '+totals.wards+' phường', 'success');
            reloadAll();
        } catch (err) {
            notify((err && err.msg) || 'Import vùng miền thất bại', 'error');
        } finally {
            regionImporting = false;
            $btn.prop('disabled', false).html(originalHtml);
        }
    }

    function copyToken(){
        return api('setting_token_get', {}, 'GET').then(function(res){
            if (!res.ok) {
                notify(res.msg || 'Không lấy được token', 'warning');
                return;
            }
            const token = String(res.token || '').trim();
            if (!token) {
                notify('Chưa có token để sao chép', 'warning');
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(token).then(function(){
                    notify('Đã sao chép token', 'success');
                }).catch(function(){
                    notify('Không thể sao chép (trình duyệt chặn)', 'warning');
                });
            }
            // Fallback
            const $tmp = $('<textarea>').val(token).css({ position:'fixed', left:'-9999px', top:'-9999px' });
            $('body').append($tmp);
            $tmp[0].select();
            try {
                document.execCommand('copy');
                notify('Đã sao chép token', 'success');
            } catch(e) {
                notify('Không thể sao chép token', 'warning');
            }
            $tmp.remove();
        }).catch(function(err){
            notify(err.msg || 'Không lấy được token', 'error');
        });
    }

    function openTokenModal(){
        if (!tokenModal) {
            const el = document.getElementById('tokenModal');
            if (el && window.bootstrap && window.bootstrap.Modal) tokenModal = new bootstrap.Modal(el);
        }
        $('#api_token_new').val('');
        $('#api_token_new').attr('type', 'password');
        $('#iconToggleNewToken').removeClass('bi-eye-slash').addClass('bi-eye');
        if (tokenModal) tokenModal.show();
    }

    function saveNewToken(){
        const token = String($('#api_token_new').val() || '').trim();
        if (!token) {
            notify('Vui lòng nhập token mới', 'warning');
            return;
        }
        const $btn = $('#btnSaveNewToken');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...');

        return api('setting_save', { token: token, enabled: 1 }, 'POST').then(function(res){
            if (res.ok) {
                notify('Đã cập nhật token GHN', 'success');
                if (tokenModal) tokenModal.hide();
                reloadAll();
            } else {
                notify(res.msg || 'Cập nhật token thất bại', 'warning');
            }
        }).catch(function(err){
            notify(err.msg || 'Cập nhật token thất bại', 'error');
        }).finally(function(){
            $btn.prop('disabled', false).html(originalHtml);
        });
    }

    function reloadAll(){
        return loadConfig().then(function(){
            return runApiTest();
        });
    }

    $('#btnReloadSetup').on('click', reloadAll);
    $('#btnImportRegion').off('click', importRegions).on('click', importRegions);

    $('#btnRunApiTest').on('click', runApiTest);
    $('#btnCopyToken').on('click', copyToken);
    $('#btnOpenTokenModal').on('click', openTokenModal);
    $('#btnSaveNewToken').on('click', saveNewToken);
    $('#btnToggleNewToken').on('click', function(){
        const $input = $('#api_token_new');
        const $icon = $('#iconToggleNewToken');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    reloadAll();
})();
</script>
