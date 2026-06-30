<?php
require_once __DIR__ . '/../_admin_guard.php';

?>

<style>
#ghnTable_wrapper .dt-search { display:none; }
#ghnTable_wrapper .dt-length { display:none; }
#ghnTable thead th { white-space:nowrap; font-size:.82rem; }
#ghnTable tbody td { vertical-align:middle; font-size:.84rem; }
.ghn-status-badge { font-size:.76rem; padding:2px 8px; border-radius:999px; font-weight:600; white-space:nowrap; }
.ghn-status-badge.bg-warning { color:#000!important; }
.ghn-pay-badge { font-size:.74rem; padding:2px 7px; border-radius:6px; font-weight:600; white-space:nowrap; }
#orderDetailModal .detail-label { font-weight:600; color:#6c757d; font-size:.82rem; }
#orderDetailModal .detail-value { font-size:.88rem; }
#orderDetailModal .detail-section { border-left:3px solid var(--bs-primary); padding-left:12px; margin-bottom:1rem; }
</style>

<div class="container-fluid px-0">
    <!-- Header -->
    <div class="bg-white border rounded-3 p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-truck"></i> Quản lý đơn hàng</h5>
                <p class="mb-0 small text-muted">Theo dõi, lọc trạng thái và đồng bộ đơn Giao Hàng Nhanh.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a class="btn btn-sm btn-outline-secondary" href="<?= h($baseUrl); ?>/admin/giaohangnhanh/home">
                    <i class="bi bi-speedometer2"></i> Trang chủ
                </a>
                <a class="btn btn-sm btn-primary" href="<?= h($baseUrl); ?>/admin/giaohangnhanh/create">
                    <i class="bi bi-box-seam"></i> Tạo đơn mới
                </a>
                <button class="btn btn-sm btn-outline-success" id="btnSyncOrders"><i class="bi bi-arrow-repeat"></i> Đồng bộ</button>
                <div class="form-check form-switch ms-1">
                    <input class="form-check-input" type="checkbox" id="autoSyncSwitch">
                    <label class="form-check-label small" for="autoSyncSwitch">Tự đồng bộ</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border rounded-3 p-3 mb-3">
        <div class="d-flex gap-2 mb-3 flex-wrap" id="ghnStatusTabs">
            <button class="btn btn-sm btn-primary active" data-status-tab="1" data-status="">Tất cả</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="ready_to_pick">Chờ lấy</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="picking">Đang lấy</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="delivering">Đang giao</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="delivered">Đã giao</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="returned">Đơn hoàn</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="cancel">Đã huỷ</button>
            <button class="btn btn-sm btn-outline-primary" data-status-tab="1" data-status="draft">Nháp</button>
        </div>
        <div class="row g-2">
            <div class="col-12 col-md-3">
                <input id="ghn_search" class="form-control form-control-sm" placeholder="Tìm mã GHN / mã hệ thống...">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" id="ghn_date_from" class="form-control form-control-sm" title="Từ ngày">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" id="ghn_date_to" class="form-control form-control-sm" title="Đến ngày">
            </div>
            <div class="col-6 col-md-1">
                <input type="number" id="ghn_cod_min" class="form-control form-control-sm" min="0" placeholder="COD từ">
            </div>
            <div class="col-6 col-md-1">
                <input type="number" id="ghn_cod_max" class="form-control form-control-sm" min="0" placeholder="COD đến">
            </div>
            <div class="col-6 col-md-2">
                <select id="ghn_payment_filter" class="form-select form-select-sm">
                    <option value="">Tất cả thanh toán</option>
                    <option value="cod">COD</option>
                    <option value="momo">MoMo</option>
                    <option value="vnpay">VNPay</option>
                    <option value="zalopay">ZaloPay</option>
                </select>
            </div>
        </div>
    </div>

    <!-- DataTable -->
    <div class="bg-white border rounded-3 p-3 mb-3">
        <div class="table-responsive">
            <table id="ghnTable" class="table table-sm table-striped table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Mã GHN</th>
                        <th>MÃ ĐH</th>
                        <th>Trạng thái</th>
                        <th>Thanh toán</th>
                        <th>Loại</th>
                        <th>Yêu cầu xem hàng</th>
                        <th>Thu hộ</th>
                        <th>Phí ship</th>
                        <th>Tổng giá trị</th>
                        <th>Ngày giao dự kiến</th>
                        <th>Ngày tạo</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="small text-muted mt-2" id="syncHint">Tự đồng bộ: đang tắt.</div>
    </div>

    <!-- Debug -->
    <div class="bg-white border rounded-3 p-3">
        <h6 class="mb-2"><i class="bi bi-bug"></i> Debug JSON</h6>
        <pre class="bg-dark text-light rounded-3 p-3 small font-monospace mb-0" style="min-height:80px;max-height:260px;overflow:auto;white-space:pre-wrap;" id="debugJson">Chưa có dữ liệu.</pre>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="orderActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-gear"></i> Thao tác đơn hàng</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-3">Mã đơn: <b id="orderActionCode">—</b></div>
                <input type="hidden" id="orderActionCodeInput" value="">
                <input type="hidden" id="orderActionCodInput" value="0">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="small fw-semibold mb-2">In vận đơn</div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm js-popup-print" data-size="a5">In khổ A5</button>
                            <button type="button" class="btn btn-outline-primary btn-sm js-popup-print" data-size="80x80">In khổ 80x80</button>
                            <button type="button" class="btn btn-outline-primary btn-sm js-popup-print" data-size="52x70">In khổ 52x70</button>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="small fw-semibold mb-2">API đơn hàng</div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm js-popup-action" data-action="update_cod">Cập nhật COD</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm js-popup-action" data-action="update_note">Cập nhật ghi chú</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm js-popup-action" data-action="update_required_note">Cập nhật yêu cầu xem hàng</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm js-popup-action" data-action="delivery_again">Yêu cầu giao lại</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm js-popup-action" data-action="return">Yêu cầu hoàn đơn</button>
                            <button type="button" class="btn btn-outline-danger btn-sm js-popup-action" data-action="cancel">Huỷ đơn GHN</button>
                            <button type="button" class="btn btn-danger btn-sm js-popup-action" data-action="delete_local">Xoá đơn (trên hệ thống)</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-info-circle"></i> Chi tiết đơn hàng</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="mt-2 small text-muted">Đang tải...</div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?= h($basePath . '/core_admin/giaohangnhanh/ajax/api.php'); ?>';
    let autoSyncTimer = null;
    let currentGhnOrderStatus = '';
    let ghnDataTable = null;

    function parseJsonSafe(text){
        if (text && typeof text === 'object') return text;
        try { return JSON.parse(String(text || '')); } catch(e){ return null; }
    }

    function api(action, data = {}, method = 'POST'){
        return new Promise((resolve, reject) => {
            $.ajax({
                url: API, method, dataType: 'text',
                data: Object.assign({ action }, data),
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

    function esc(text){ return $('<div>').text(text == null ? '' : String(text)).html(); }

    function notify(msg, type){
        if (!window.toastr) return;
        toastr[type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'error' : 'info'](msg);
    }

    function setDebug(data){ $('#debugJson').text(JSON.stringify(data || {}, null, 2)); }

    function fmtMoney(v){ return Number(v || 0).toLocaleString('vi-VN') + 'đ'; }

    function toDateYmd(value){
        const raw = String(value || '').trim();
        if (!raw) return '';
        const n = raw.slice(0, 10).replace(/\//g, '-');
        if (/^\d{4}-\d{2}-\d{2}$/.test(n)) return n;
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return '';
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    }

    // Status
    const statusMap = {
        create:['Đơn mới tạo','bg-info text-white'], draft:['Nháp','bg-secondary text-white'],
        ready_to_pick:['Chờ lấy hàng','bg-warning'], picking:['Đang lấy hàng','bg-warning'],
        picked:['Đã lấy hàng','bg-info text-white'], delivering:['Đang giao','bg-primary text-white'],
        delivered:['Đã giao','bg-success text-white'], returned:['Đơn hoàn','bg-danger text-white'],
        cancel:['Đã huỷ','bg-dark text-white'], canceled:['Đã huỷ','bg-dark text-white'], cancelled:['Đã huỷ','bg-dark text-white']
    };
    function statusBadge(code){
        const c = String(code||'').toLowerCase();
        const [label, cls] = statusMap[c] || [code||'—','bg-light text-dark'];
        return '<span class="ghn-status-badge badge '+cls+'">'+esc(label)+'</span>';
    }

    // Payment method badge
    const payMap = { cod:['COD','bg-success'], momo:['MoMo','bg-danger'], vnpay:['VNPay','bg-primary'], zalopay:['ZaloPay','bg-info'] };
    function payBadge(method){
        const m = String(method||'').toLowerCase();
        const [label, cls] = payMap[m] || [method||'—','bg-secondary'];
        return '<span class="ghn-pay-badge badge '+cls+' text-white">'+esc(label)+'</span>';
    }

    // Payment type (ship fee payer)
    function payTypeTxt(id){
        const v = Number(id || 1);
        return v === 2 ? '<span class="text-danger fw-semibold">Người nhận trả</span>' : '<span class="text-success fw-semibold">Người gửi trả</span>';
    }

    // Required note
    const reqNoteMap = { CHOTHUHANG:'Cho thử hàng', CHOXEMHANGKHONGTHU:'Cho xem, không thử', KHONGCHOXEMHANG:'Không cho xem' };
    function reqNoteTxt(v){
        const s = String(v||'').toUpperCase();
        return reqNoteMap[s] || (v || '—');
    }

    // ETA
    function etaHtml(row){
        // Try response_json for leadtime/expected_delivery_time
        let resp = null;
        try { resp = typeof row.response_json === 'string' ? JSON.parse(row.response_json) : row.response_json; } catch(e){}
        const eta = resp?.data?.expected_delivery_time || resp?.expected_delivery_time || null;
        if (!eta) {
            const st = String(row.status||'').toLowerCase();
            if (st === 'delivered') return '<span class="badge bg-success-subtle text-success">Đã giao</span>';
            if (st === 'cancel' || st === 'canceled' || st === 'cancelled') return '<span class="badge bg-dark-subtle text-dark">Đã huỷ</span>';
            if (st === 'returned') return '<span class="badge bg-danger-subtle text-danger">Đã hoàn</span>';
            return '<span class="text-muted">—</span>';
        }
        const etaDate = new Date(eta);
        if (Number.isNaN(etaDate.getTime())) return '<span class="text-muted">—</span>';
        const now = new Date();
        const diffMs = etaDate - now;
        const diffH = Math.round(diffMs / 3600000);
        let etaLabel = '';
        if (diffMs <= 0) {
            etaLabel = '<span class="badge bg-warning-subtle text-warning">Quá hạn</span>';
        } else if (diffH < 24) {
            etaLabel = '<span class="badge bg-info-subtle text-info">Còn ~'+diffH+'h</span>';
        } else {
            const diffD = Math.ceil(diffH / 24);
            etaLabel = '<span class="badge bg-primary-subtle text-primary">Còn ~'+diffD+' ngày</span>';
        }
        const dateStr = etaDate.toLocaleDateString('vi-VN',{day:'2-digit',month:'2-digit',year:'numeric'});
        return '<div class="small">'+dateStr+'</div>'+etaLabel;
    }

    // Print
    function printOrder(orderCode, size){
        const code = String(orderCode||'').trim();
        if (!code) return notify('Thiếu mã GHN','warning');
        api('order_print_token',{order_code:code}).then(r=>{
            setDebug(r);
            if(!r.ok) return notify(r.msg||'Không lấy được token','warning');
            const url = String((r.print_urls||{})[size]||(r.print_urls||{}).a5||'');
            if(!url) return notify('Không tạo được link in','warning');
            window.open(url,'_blank','noopener');
        }).catch(e=>{ setDebug(e); notify(e.msg||'Lỗi in đơn','error'); });
    }

    function normalizeRequiredNote(v){
        const s = String(v||'').trim().toUpperCase();
        return ['CHOTHUHANG','CHOXEMHANGKHONGTHU','KHONGCHOXEMHANG'].includes(s)?s:'';
    }

    // Row actions
    function runRowAction(action, orderCode, meta){
        const code = String(orderCode||'').trim();
        if(!code) return notify('Thiếu mã GHN','warning');

        if(action==='cancel'){
            if(!confirm('Xác nhận huỷ đơn GHN '+code+'?'))return;
            api('order_cancel',{order_code:code}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Huỷ thất bại','warning');notify('Đã huỷ: '+code,'success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
        if(action==='delete_local'){
            if(!confirm('Xoá bản ghi GHN khỏi hệ thống?\nKHÔNG huỷ đơn trên GHN.'))return;
            api('order_delete_local',{order_code:code}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Xoá thất bại','warning');notify(r.msg||'Đã xoá','success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
        if(action==='return'){
            if(!confirm('Xác nhận hoàn đơn GHN '+code+'?'))return;
            api('order_return',{order_code:code}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Hoàn thất bại','warning');notify('Đã hoàn: '+code,'success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
        if(action==='delivery_again'){
            if(!confirm('Xác nhận giao lại đơn GHN '+code+'?'))return;
            api('order_delivery_again',{order_code:code}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Giao lại thất bại','warning');notify('Đã giao lại: '+code,'success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
        if(action==='update_cod'){
            const cur=Number(meta?.cod||0);
            const raw=prompt('Nhập COD mới cho đơn '+code,String(cur));
            if(raw==null)return;
            const next=Math.max(0,Number(String(raw).replace(/[^0-9.-]/g,'')||0));
            api('order_update_cod',{order_code:code,cod_amount:next}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Lỗi COD','warning');notify('COD: '+fmtMoney(next),'success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
        if(action==='update_note'){
            const note=prompt('Nhập ghi chú mới cho đơn '+code,'');
            if(note==null)return;
            api('order_update',{payload:{order_code:code,note:String(note).trim()}}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Lỗi','warning');notify('Đã cập nhật ghi chú','success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
        if(action==='update_required_note'){
            const raw=prompt('required_note (CHOTHUHANG | CHOXEMHANGKHONGTHU | KHONGCHOXEMHANG)','KHONGCHOXEMHANG');
            if(raw==null)return;
            const rn=normalizeRequiredNote(raw);
            if(!rn)return notify('required_note không hợp lệ','warning');
            api('order_update',{payload:{order_code:code,required_note:rn}}).then(r=>{setDebug(r);if(!r.ok)return notify(r.msg||'Lỗi','warning');notify('Đã cập nhật','success');loadGhnOrders();}).catch(e=>{setDebug(e);notify(e.msg||'Lỗi','error');});
            return;
        }
    }

    // Order Detail
    function showOrderDetail(orderCode){
        const code = String(orderCode||'').trim();
        if(!code) return;
        const body = $('#orderDetailBody');
        body.html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="mt-2 small text-muted">Đang tải chi tiết...</div></div>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('orderDetailModal')).show();

        // Find local row data
        const local = allOrders.find(r => String(r.order_code||'').trim() === code) || {};

        api('order_info',{order_code:code}).then(function(res){
            setDebug(res);
            const d = res.data || {};
            const items = Array.isArray(d.items) ? d.items : [];
            const logs = Array.isArray(d.log) ? d.log : [];

            let html = '';

            // Overview
            html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-box-seam"></i> Thông tin đơn</h6>';
            html += '<div class="row g-2">';
            html += detailCell('Mã GHN', d.order_code || code);
            html += detailCell('Mã hệ thống', local.system_order_id || '—');
            html += detailCell('Trạng thái', statusBadge(d.status || local.status), true);
            html += detailCell('Thanh toán', payBadge(local.ecom_payment_method), true);
            html += detailCell('Hình thức', payTypeTxt(d.payment_type_id || local.payment_type_id), true);
            html += detailCell('Yêu cầu xem hàng', reqNoteTxt(d.required_note || local.required_note));
            html += detailCell('Nội dung', d.content || local.content || '—');
            html += detailCell('Ghi chú', d.note || local.note || '—');
            html += '</div></div>';

            // Money
            html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-cash-stack"></i> Tài chính</h6>';
            html += '<div class="row g-2">';
            html += detailCell('COD', fmtMoney(d.cod_amount || local.cod_amount));
            html += detailCell('Phí ship', fmtMoney(d.total_fee || local.shipping_fee));
            html += detailCell('Giá trị hàng', fmtMoney(d.insurance_value || local.goods_value));
            html += detailCell('Coupon', d.coupon || local.coupon || '—');
            html += '</div></div>';

            // Sender
            html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-geo-alt"></i> Người gửi</h6>';
            html += '<div class="row g-2">';
            html += detailCell('Tên', d.from_name || local.from_name || '—');
            html += detailCell('SĐT', d.from_phone || local.from_phone || '—');
            html += detailCell('Địa chỉ', d.from_address || local.from_address || '—', false, 'col-12');
            html += '</div></div>';

            // Receiver
            html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-person-check"></i> Người nhận</h6>';
            html += '<div class="row g-2">';
            html += detailCell('Tên', d.to_name || local.to_name || '—');
            html += detailCell('SĐT', d.to_phone || local.to_phone || '—');
            html += detailCell('Địa chỉ', d.to_address || local.to_address || '—', false, 'col-12');
            html += '</div></div>';

            // Package
            html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-archive"></i> Kiện hàng</h6>';
            html += '<div class="row g-2">';
            html += detailCell('Cân nặng', (Number(d.weight || local.weight || 0)) + 'g');
            html += detailCell('Kích thước', (d.length||local.length||0)+'×'+(d.width||local.width||0)+'×'+(d.height||local.height||0)+' cm');
            html += '</div></div>';

            // ETA
            const eta = d.expected_delivery_time || d.leadtime || null;
            if (eta) {
                const etaD = new Date(eta);
                if (!Number.isNaN(etaD.getTime())) {
                    html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-clock-history"></i> Thời gian dự kiến giao</h6>';
                    html += '<div class="fs-6 fw-semibold text-primary">'+etaD.toLocaleString('vi-VN',{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})+'</div>';
                    const diffH = Math.round((etaD - new Date()) / 3600000);
                    if (diffH > 0) html += '<div class="small text-muted">Còn khoảng '+(diffH < 24 ? diffH+' giờ' : Math.ceil(diffH/24)+' ngày')+'</div>';
                    else html += '<div class="small text-danger">Đã quá hạn ETA</div>';
                    html += '</div>';
                }
            }

            // Items
            if (items.length) {
                html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-list-check"></i> Sản phẩm ('+items.length+')</h6>';
                html += '<div class="table-responsive"><table class="table table-sm table-bordered small mb-0"><thead class="table-light"><tr><th>Tên</th><th>SL</th><th>Trọng lượng</th></tr></thead><tbody>';
                items.forEach(function(it){
                    html += '<tr><td>'+esc(it.name||'—')+'</td><td class="text-center">'+Number(it.quantity||1)+'</td><td>'+Number(it.weight||0)+'g</td></tr>';
                });
                html += '</tbody></table></div></div>';
            }

            // Logs
            if (logs.length) {
                html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-journal-text"></i> Lịch sử trạng thái</h6>';
                html += '<div class="table-responsive"><table class="table table-sm small mb-0"><thead class="table-light"><tr><th>Thời gian</th><th>Trạng thái</th></tr></thead><tbody>';
                logs.forEach(function(l){
                    html += '<tr><td class="text-nowrap">'+esc(l.updated_date||l.created_at||'')+'</td><td>'+statusBadge(l.status)+'</td></tr>';
                });
                html += '</tbody></table></div></div>';
            }

            // Timestamps
            html += '<div class="detail-section"><h6 class="fw-bold mb-2"><i class="bi bi-calendar3"></i> Thời gian</h6>';
            html += '<div class="row g-2">';
            html += detailCell('Tạo lúc', local.created_at || d.created_date || '—');
            html += detailCell('Cập nhật', local.updated_at || d.updated_date || '—');
            html += detailCell('Sync lần cuối', local.last_sync_at || '—');
            html += '</div></div>';

            body.html(html);
        }).catch(function(err){
            setDebug(err);
            // Fallback: show local data only
            let html = '<div class="alert alert-warning small mb-3">Không lấy được chi tiết từ GHN API. Hiển thị dữ liệu local.</div>';
            html += '<div class="detail-section"><h6 class="fw-bold mb-2">Thông tin cơ bản</h6><div class="row g-2">';
            html += detailCell('Mã GHN', code);
            html += detailCell('Mã HT', local.system_order_id || '—');
            html += detailCell('Trạng thái', statusBadge(local.status), true);
            html += detailCell('COD', fmtMoney(local.cod_amount));
            html += detailCell('Phí ship', fmtMoney(local.shipping_fee));
            html += detailCell('Giá trị hàng', fmtMoney(local.goods_value));
            html += detailCell('Thanh toán', payBadge(local.ecom_payment_method), true);
            html += detailCell('TT phí ship', payTypeTxt(local.payment_type_id), true);
            html += detailCell('Người nhận', esc(local.to_name||'—')+' - '+esc(local.to_phone||''));
            html += detailCell('Địa chỉ', local.to_address || '—', false, 'col-12');
            html += '</div></div>';
            body.html(html);
        });
    }

    function detailCell(label, value, isHtml, colClass){
        const col = colClass || 'col-6 col-md-4';
        const val = isHtml ? value : esc(String(value ?? ''));
        return '<div class="'+col+'"><div class="detail-label">'+esc(label)+'</div><div class="detail-value">'+val+'</div></div>';
    }

    // DataTable
    function initDataTable(rows){
        if (ghnDataTable) {
            ghnDataTable.clear().rows.add(rows).draw();
            return;
        }
        ghnDataTable = $('#ghnTable').DataTable({
            data: rows,
            columns: [
                { data:'order_code', render: d => '<span class="fw-semibold user-select-all">'+esc(d||'—')+'</span>' },
                { data:'system_order_id', render: d => '<span class="small">'+esc(d||'—')+'</span>' },
                { data:'status', render: d => statusBadge(d) },
                { data:'ecom_payment_method', render: d => payBadge(d) },
                { data:'payment_type_id', render: d => payTypeTxt(d) },
                { data:'required_note', render: d => '<span class="small">'+esc(reqNoteTxt(d))+'</span>' },
                { data:'cod_amount', render: d => fmtMoney(d), className:'text-end' },
                { data:'shipping_fee', render: d => fmtMoney(d), className:'text-end' },
                { data:'goods_value', render: d => fmtMoney(d), className:'text-end' },
                { data:null, orderable:false, render: (d,t,r) => etaHtml(r) },
                { data:'created_at', render: d => '<span class="small">'+esc(d||'')+'</span>' },
                { data:null, orderable:false, className:'text-center text-nowrap', render:(d,t,r) => {
                    const code = String(r.order_code||'').trim();
                    if(!code) return '';
                    return '<div class="btn-group btn-group-sm">'
                        +'<button type="button" class="btn btn-outline-info js-view-detail" data-order-code="'+esc(code)+'" title="Xem chi tiết"><i class="bi bi-eye"></i></button>'
                        +'<button type="button" class="btn btn-outline-primary js-open-action-popup" data-order-code="'+esc(code)+'" data-cod="'+Number(r.cod_amount||0)+'" title="Thao tác"><i class="bi bi-gear"></i></button>'
                        +'</div>';
                }}
            ],
            order: [[10, 'desc']],
            pageLength: 20,
            lengthMenu: [10, 20, 50, 100],
            language: {
                emptyTable:'Chưa có đơn GHN', info:'Hiển thị _START_ - _END_ / _TOTAL_ đơn',
                infoEmpty:'0 đơn', infoFiltered:'(lọc từ _MAX_ đơn)',
                lengthMenu:'Hiện _MENU_ đơn/trang',
                paginate:{first:'«',previous:'‹',next:'›',last:'»'},
                zeroRecords:'Không tìm thấy đơn phù hợp'
            },
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
        });
    }

    function applyFilters(allRows){
        const q = String($('#ghn_search').val()||'').trim().toLowerCase();
        const fromDate = String($('#ghn_date_from').val()||'').trim();
        const toDate = String($('#ghn_date_to').val()||'').trim();
        const codMin = Number($('#ghn_cod_min').val()||0);
        const codMaxR = Number($('#ghn_cod_max').val()||0);
        const codMax = codMaxR > 0 ? codMaxR : Infinity;
        const payFilter = String($('#ghn_payment_filter').val()||'').toLowerCase();

        return allRows.filter(function(r){
            const status = String(r.status||'').toLowerCase();
            if (currentGhnOrderStatus && status !== currentGhnOrderStatus) return false;
            if (q) {
                const hay = [r.order_code, r.system_order_id, r.status].map(v=>String(v||'').toLowerCase()).join(' ');
                if (!hay.includes(q)) return false;
            }
            const dateYmd = toDateYmd(r.updated_at || r.created_at || '');
            if (fromDate && dateYmd && dateYmd < fromDate) return false;
            if (toDate && dateYmd && dateYmd > toDate) return false;
            const cod = Number(r.cod_amount||0);
            if (cod < codMin || cod > codMax) return false;
            if (payFilter && String(r.ecom_payment_method||'').toLowerCase() !== payFilter) return false;
            return true;
        });
    }

    let allOrders = [];

    function loadGhnOrders(){
        return api('ghn_orders_list',{limit:200},'GET').then(function(res){
            setDebug(res);
            if(!res.ok){notify(res.msg||'Không tải được đơn','warning');initDataTable([]);return;}
            allOrders = Array.isArray(res.rows)?res.rows:[];
            initDataTable(applyFilters(allOrders));
        }).catch(function(err){
            setDebug(err);notify(err.msg||'Lỗi kết nối','error');initDataTable([]);
        });
    }

    function syncOrders(){
        return api('ghn_orders_sync',{limit:30}).then(function(res){
            setDebug(res);
            if(!res.ok){$('#syncHint').text('Đồng bộ thất bại: '+(res.msg||''));return notify(res.msg||'Đồng bộ thất bại','warning');}
            const msg='Đã đồng bộ '+Number(res.updated||0)+' đơn - '+new Date().toLocaleString();
            $('#syncHint').text(msg);notify(msg,'success');loadGhnOrders();
        }).catch(function(err){
            setDebug(err);$('#syncHint').text('Đồng bộ thất bại');notify(err.msg||'Đồng bộ thất bại','error');
        });
    }

    function setAutoSync(on){
        if(autoSyncTimer){clearInterval(autoSyncTimer);autoSyncTimer=null;}
        if(on){autoSyncTimer=setInterval(syncOrders,120000);$('#syncHint').text('Tự đồng bộ: đang bật (120s/lần).');}
        else{$('#syncHint').text('Tự đồng bộ: đang tắt.');}
    }

    // Events: status tabs
    $(document).on('click','[data-status-tab]',function(){
        currentGhnOrderStatus = String($(this).data('status')||'').trim().toLowerCase();
        $('[data-status-tab]').removeClass('btn-primary active').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        initDataTable(applyFilters(allOrders));
    });

    // Events: filters
    $('#ghn_search,#ghn_date_from,#ghn_date_to,#ghn_cod_min,#ghn_cod_max,#ghn_payment_filter').on('input change',function(){
        initDataTable(applyFilters(allOrders));
    });

    // Events: view detail
    $(document).on('click','.js-view-detail',function(){
        showOrderDetail($(this).data('orderCode')||$(this).attr('data-order-code'));
    });

    // Events: action modal
    $(document).on('click','.js-open-action-popup',function(){
        const code=String($(this).data('orderCode')||$(this).attr('data-order-code')||'').trim();
        const cod=Number($(this).data('cod')||$(this).attr('data-cod')||0);
        $('#orderActionCode').text(code||'—');
        $('#orderActionCodeInput').val(code);
        $('#orderActionCodInput').val(String(cod));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('orderActionModal')).show();
    });
    $(document).on('click','.js-popup-print',function(){
        printOrder($('#orderActionCodeInput').val(),$(this).data('size')||'a5');
    });
    $(document).on('click','.js-popup-action',function(){
        const action=String($(this).data('action')||'').trim();
        const code=String($('#orderActionCodeInput').val()||'').trim();
        const cod=Number($('#orderActionCodInput').val()||0);
        runRowAction(action,code,{cod});
    });

    $('#btnSyncOrders').on('click',syncOrders);
    $('#autoSyncSwitch').on('change',function(){setAutoSync($(this).is(':checked'));});

    loadGhnOrders();
})();
</script>
