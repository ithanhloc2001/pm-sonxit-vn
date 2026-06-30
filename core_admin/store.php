<?php 
require_once __DIR__ . '/_admin_guard.php';
?>
<style>
    /* Modern Page Header Restyling */
    .header-icon {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .header-icon:hover {
        transform: scale(1.08) rotate(3deg);
        background-color: rgba(12, 76, 41, 0.12) !important;
        border-color: rgba(12, 76, 41, 0.25) !important;
    }
    #bmAddBtn {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        border-radius: 10px !important;
        background-color: var(--theme-primary, #0c4c29) !important;
        border: none !important;
    }
    #bmAddBtn:hover {
        background-color: #08341c !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(12, 76, 41, 0.25) !important;
    }
    #bmAddBtn:active {
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(12, 76, 41, 0.2) !important;
    }



    /* Modern status badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #cbd5e1;
        transition: all 0.2s ease;
    }
    .status-badge.active { border-color:#bbf7d0; background:#f0fdf4; color:#166534; }
    .status-badge.paused { border-color:#e2e8f0; background:#f8fafc; color:#64748b; }
    .status-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    /* Streamlined Table CSS Overrides */
    #bmTable {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100% !important;
    }
    #bmTable thead th {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        color: #475569 !important;
        padding: 12px 16px !important;
        border-bottom: 2px solid var(--order-border, #e5e7eb) !important;
        background-color: #f8fafc !important;
    }
    #bmTable tbody td {
        padding: 12px 16px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle !important;
        font-size: 0.88rem !important;
    }
    #bmTable tbody tr {
        transition: background-color 0.15s ease !important;
    }
    #bmTable tbody tr:hover {
        background-color: rgba(12, 76, 41, 0.015) !important;
    }
    .avatar-circle {
        width: 44px;
        height: 44px;
        object-fit: cover;
        border-radius: 12px;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }

    /* Action buttons styles */
    .branch-actions .btn {
        width: 32px !important;
        height: 32px !important;
        border-radius: 8px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.88rem !important;
        transition: all 0.2s ease !important;
        margin-right: 4px;
        border: 1px solid #e2e8f0 !important;
        background: #fff;
    }
    .branch-actions .btn-outline-primary {
        color: var(--order-primary, #2563eb) !important;
    }
    .branch-actions .btn-outline-primary:hover {
        background-color: rgba(37, 99, 235, 0.05) !important;
        border-color: var(--order-primary, #2563eb) !important;
    }
    .branch-actions .btn-outline-danger {
        color: #ef4444 !important;
    }
    .branch-actions .btn-outline-danger:hover {
        background-color: rgba(239, 68, 68, 0.05) !important;
        border-color: #ef4444 !important;
    }
</style>
<div class="container-fluid py-4">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-shop fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Quản lý chi nhánh</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="tableMeta" style="font-size: 0.72rem;">Đang tải...</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Hệ thống quản lý thông tin các địa điểm đại lý, hotline chăm sóc khách hàng và giờ hoạt động trên toàn quốc.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Cấu hình địa điểm đại lý, hotline và giờ hoạt động.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm text-white" id="bmAddBtn" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-plus-lg fs-5 text-white"></i>
                <span class="d-none d-sm-inline">Thêm chi nhánh</span>
                <span class="d-inline d-sm-none">Thêm mới</span>
            </button>
        </div>
    </div>

    <!-- STANDALONE SEARCH & FILTERS -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Tìm kiếm chi nhánh</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" style="left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.88rem; pointer-events:none;"></i>
                        <input type="text" id="searchBranch" class="form-control" placeholder="Tên chi nhánh, hotline, địa chỉ..." style="padding-left:38px !important; border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Khu vực</label>
                    <select id="filterRegion" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="">Tất cả khu vực</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Trạng thái</label>
                    <select id="filterStatus" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active">Đang hoạt động</option>
                        <option value="paused">Đang tạm dừng</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- SUMMARY KPI CARDS GRID -->
    <div class="mb-4 grid-3" id="summaryGrid">
        <div class="summary-card active" data-filter="">
            <div class="d-flex flex-column">
                <span>Tất cả chi nhánh</span>
                <strong class="mt-1" id="countTotal">—</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-collection-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-filter="active">
            <div class="d-flex flex-column">
                <span>Đang hoạt động</span>
                <strong class="mt-1" id="countActive">—</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-check-circle-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-filter="paused">
            <div class="d-flex flex-column">
                <span>Đang tạm dừng</span>
                <strong class="mt-1" id="countPaused">—</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-pause-circle-fill fs-5"></i>
            </div>
        </div>
    </div>

    <!-- DETAIL LIST TABLE -->
    <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table id="bmTable" class="table table-hover align-middle mb-0 table-striped">
                <thead>
                    <tr>
                        <th class="ps-4">Chi nhánh</th>
                        <th>Khu vực</th>
                        <th>Hotline</th>
                        <th>Địa chỉ & Bản đồ</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4" style="width: 150px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JS renders data here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa Chi Nhánh -->
<div class="modal fade" id="bmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-light py-3 px-4">
                <h5 class="modal-title fw-bold text-dark" id="bmModalLabel">Thông tin chi nhánh</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <input type="hidden" id="bmId" value="0">
                <input type="hidden" id="bmAvatarCurrent" value="">
                <input type="hidden" id="bmGalleryExisting" value="[]">
                <input type="hidden" id="bmIsActive" value="1">

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary d-flex align-items-center" style="letter-spacing: 0.02em; font-size: 0.92rem;"><i class="bi bi-info-circle-fill me-2"></i>THÔNG TIN CƠ BẢN</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem;">Tên chi nhánh</label>
                                    <input type="text" class="form-control fw-bold border-light-subtle" id="bmBranchName" placeholder="VD: Chi nhánh Quận 1" style="border-radius: 8px; height: 40px; font-size: 0.9rem;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem;">Khu vực</label>
                                    <select class="form-select border-light-subtle" id="bmRegion" style="border-radius: 8px; height: 40px; font-size: 0.9rem;"></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem;">Hotline</label>
                                    <input type="text" class="form-control border-light-subtle" id="bmHotline" placeholder="09xxxxxxx" style="border-radius: 8px; height: 40px; font-size: 0.9rem;">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem;">Địa chỉ chi tiết</label>
                                    <textarea class="form-control border-light-subtle" id="bmAddress" rows="2" placeholder="Số nhà, tên đường..." style="border-radius: 8px; font-size: 0.9rem;"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem;">URL bản đồ (Google Maps)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control border-light-subtle" id="bmMapUrl" placeholder="https://maps.google.com/..." style="border-top-left-radius: 8px; border-bottom-left-radius: 8px; height: 40px; font-size: 0.9rem;">
                                        <button class="btn btn-sm btn-primary px-4 fw-bold text-white d-flex align-items-center justify-content-center" type="button" id="bmAiSuggestBtn" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px; background-color: var(--theme-primary, #0c4c29); border: none;">
                                            <i class="bi bi-stars me-2 text-white"></i>AI Gợi ý
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <h6 class="fw-bold mb-3 text-primary d-flex align-items-center" style="letter-spacing: 0.02em; font-size: 0.92rem;"><i class="bi bi-clock-fill me-2"></i>GIỜ HOẠT ĐỘNG</h6>
                            <div id="bmHoursWrap"></div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary d-flex align-items-center" style="letter-spacing: 0.02em; font-size: 0.92rem;"><i class="bi bi-image-fill me-2"></i>ẢNH ĐẠI DIỆN</h6>
                            <div class="mb-3 rounded-4 d-flex align-items-center justify-content-center bg-light border border-dashed" style="min-height: 180px;">
                                <img id="bmAvatarPreview" src="" alt="Avatar" class="img-fluid rounded-4 shadow-sm d-none" style="max-height: 160px; object-fit: contain;">
                                <div id="bmAvatarEmpty" class="text-muted small text-center"><i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>Chưa có ảnh</div>
                            </div>
                            <label for="bmAvatarFile" class="btn btn-sm btn-dark w-100 fw-bold py-2" style="border-radius: 8px; font-size: 0.88rem; height: 38px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="bi bi-upload me-2"></i>Tải lên ảnh mới
                            </label>
                            <input type="file" id="bmAvatarFile" accept="image/*" class="d-none">
                        </div>

                        <div class="mb-0">
                            <h6 class="fw-bold mb-3 text-primary d-flex align-items-center" style="letter-spacing: 0.02em; font-size: 0.92rem;"><i class="bi bi-images me-2"></i>BỘ SƯU TẬP</h6>
                            <div id="bmGalleryWrap" class="d-flex flex-wrap gap-2 mb-3"></div>
                            <label for="bmGalleryFiles" class="btn btn-sm btn-outline-primary w-100 fw-bold py-2 border-2" style="border-radius: 8px; font-size: 0.88rem; height: 38px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="bi bi-plus-lg me-2"></i>Thêm ảnh
                            </label>
                            <input type="file" id="bmGalleryFiles" accept="image/*" multiple class="d-none">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 bg-light">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="height: 40px; border-radius: 8px; font-size: 0.88rem;">Đóng</button>
                <button type="button" class="btn btn-success px-5 fw-semibold text-white shadow" id="bmSaveBtn" style="height: 40px; border-radius: 8px; font-size: 0.88rem; background-color: var(--theme-primary, #0c4c29); border: none;">
                    <i class="bi bi-check-lg me-2 text-white"></i>Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?= htmlspecialchars($basePath . '/core_admin/ajax/store.php', ENT_QUOTES, 'UTF-8') ?>';
    const modalEl = document.getElementById('bmModal');
    const bmModal = new bootstrap.Modal(modalEl);
    let rows = [];
    let regionOptions = [];
    let dt = null;
    const dayLabels = {
        mon: 'Thứ 2', tue: 'Thứ 3', wed: 'Thứ 4', thu: 'Thứ 5', fri: 'Thứ 6', sat: 'Thứ 7', sun: 'Chủ nhật'
    };

    function initDataTable(){
        if(dt) return;
        if (!$.fn.DataTable) return;
        dt = $('#bmTable').DataTable({
            language: {
                    info: "Hiển thị _START_ - _END_ / _TOTAL_",
                    infoEmpty: "Không có dữ liệu",
                    infoFiltered: "(lọc từ _MAX_ mục)",
                    lengthMenu: "Hiển thị _MENU_ mục",
                    zeroRecords: "Không tìm thấy dữ liệu",
                    search: "Tìm kiếm:",
                    paginate: {
                        first: "Đầu",
                        last: "Cuối",
                        next: ">",
                        previous: "<"
                    },
                    processing: "Đang xử lý...",
                    loadingRecords: "Đang tải...",
                    emptyTable: "Không có dữ liệu trong bảng"
            },
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [3, 5] }],
            dom: 'rt<"d-flex justify-content-between align-items-center p-3"ip>'
        });
    }

    function notify(msg, type = 'info'){
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    }

    function parseJsonLoose(payload){
        if (payload && typeof payload === 'object') return payload;
        const text = String(payload || '').trim();
        if (!text) return null;
        try { return JSON.parse(text); } catch (e) {}
        return null;
    }

    function requestJson(opts, onSuccess){
        const ajaxOpts = Object.assign({ dataType: 'text' }, opts || {});
        $.ajax(ajaxOpts).done(function(raw){
            const res = parseJsonLoose(raw);
            if (!res) return;
            onSuccess(res);
        }).fail(function(){ notify('Lỗi kết nối server', 'error'); });
    }

    function esc(v){ return $('<div>').text(String(v || '')).html(); }

    function renderRegionSelect(){
        const opts = ['<option value="">-- Chọn khu vực --</option>'];
        const filterOpts = ['<option value="">Tất cả khu vực</option>'];
        regionOptions.forEach(function(r){
            opts.push('<option value="' + esc(r) + '">' + esc(r) + '</option>');
            filterOpts.push('<option value="' + esc(r) + '">' + esc(r) + '</option>');
        });
        $('#bmRegion').html(opts.join(''));
        $('#filterRegion').html(filterOpts.join(''));
    }

    function resetForm(){
        $('#bmId').val('0');
        $('#bmBranchName').val('');
        $('#bmRegion').val('');
        $('#bmHotline').val('');
        $('#bmAddress').val('');
        $('#bmMapUrl').val('');
        $('#bmIsActive').val('1');
        $('#bmAvatarCurrent').val('');
        $('#bmGalleryExisting').val('[]');
        $('#bmAvatarPreview').attr('src','').addClass('d-none');
        $('#bmAvatarEmpty').removeClass('d-none');
        $('#bmAvatarFile').val('');
        $('#bmGalleryFiles').val('');
        renderGallery([]);
        buildHoursUi(null);
    }

    function fillForm(item){
        $('#bmAvatarFile').val('');
        $('#bmGalleryFiles').val('');
        $('#bmId').val(Number(item.id || 0));
        $('#bmBranchName').val(String(item.branch_name || ''));
        $('#bmRegion').val(String(item.region || ''));
        $('#bmHotline').val(String(item.hotline || ''));
        $('#bmAddress').val(String(item.address_detail || ''));
        $('#bmMapUrl').val(String(item.map_url || ''));
        $('#bmIsActive').val(Number(item.is_active || 0) === 1 ? '1' : '0');

        const avatar = String(item.avatar_image || '').trim();
        $('#bmAvatarCurrent').val(avatar);
        if (avatar) {
            $('#bmAvatarPreview').attr('src', avatar).removeClass('d-none');
            $('#bmAvatarEmpty').addClass('d-none');
        } else {
            $('#bmAvatarPreview').attr('src','').addClass('d-none');
            $('#bmAvatarEmpty').removeClass('d-none');
        }

        let gallery = [];
        if (item.gallery_images_json) {
            try { const g = JSON.parse(item.gallery_images_json); if (Array.isArray(g)) gallery = g.filter(x => x); } catch(e) {}
        }
        $('#bmGalleryExisting').val(JSON.stringify(gallery));
        renderGallery(gallery);

        let hoursData = null;
        if (item.opening_hours_json) {
            try { const h = JSON.parse(item.opening_hours_json); if (h && typeof h === 'object') hoursData = h; } catch(e) {}
        }
        buildHoursUi(hoursData);
    }

    function renderGallery(list){
        const $wrap = $('#bmGalleryWrap');
        if (!list || !list.length) { $wrap.html('<div class="text-muted small fst-italic w-100">Chưa có ảnh nào.</div>'); return; }
        const html = list.map(function(url, idx){
            return '<div class="position-relative border rounded-3 overflow-hidden shadow-sm" style="width:70px; height:70px;">'
                + '<img src="'+esc(url)+'" class="w-100 h-100 object-fit-cover">'
                + '<button type="button" class="btn btn-dark btn-sm position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center branch-gallery-remove" data-idx="'+idx+'" style="width:20px; height:20px; font-size:12px; opacity:0.8;">&times;</button>'
                + '</div>';
        }).join('');
        $wrap.html(html);
    }

    function buildHoursUi(data){
        const $wrap = $('#bmHoursWrap');
        const dKeys = ['mon','tue','wed','thu','fri','sat','sun'];
        let html = '<div class="table-responsive"><table class="table table-sm table-borderless align-middle mb-0">';
        dKeys.forEach(function(k){
            const dayCfg = (data && data[k]) ? data[k] : null;
            const enabled = dayCfg && dayCfg.enabled !== false;
            const open = dayCfg && dayCfg.open ? dayCfg.open : '';
            const close = dayCfg && dayCfg.close ? dayCfg.close : '';
            html += '<tr class="bm-hour-item" data-day="'+k+'">'
                + '<td style="width: 40px;"><div class="form-check m-0"><input class="form-check-input bm-day-enabled" type="checkbox" '+(enabled ? 'checked' : '')+' style="width: 1.1em; height: 1.1em;"></div></td>'
                + '<td style="width: 100px;"><span class="small fw-bold text-dark">'+esc(dayLabels[k] || k)+'</span></td>'
                + '<td><div class="d-flex align-items-center gap-2">'
                + '<input type="time" class="form-control form-control-sm border-light-subtle" value="'+esc(open)+'" style="max-width: 110px;">'
                + '<span class="small text-muted">đến</span>'
                + '<input type="time" class="form-control form-control-sm border-light-subtle" value="'+esc(close)+'" style="max-width: 110px;">'
                + '</div></td></tr>';
        });
        $wrap.html(html + '</table></div>');
    }

    function collectHoursJson(){
        const dKeys = ['mon','tue','wed','thu','fri','sat','sun'];
        const out = {};
        dKeys.forEach(function(k){
            const $row = $('#bmHoursWrap .bm-hour-item[data-day="'+k+'"]').first();
            if (!$row.length) return;
            const enabled = $row.find('.bm-day-enabled').is(':checked');
            const open = ($row.find('input[type="time"]').first().val() || '').toString();
            const close = ($row.find('input[type="time"]').last().val() || '').toString();
            if (!enabled) { out[k] = { enabled:false }; return; }
            if (!open || !close) return;
            out[k] = { enabled:true, open:open, close:close };
        });
        return JSON.stringify(out);
    }

    function renderTable(){
        if(!dt) initDataTable();
        if(!dt) return;
        dt.clear();
        
        let activeCount = 0;
        let pausedCount = 0;

        const dataForDt = rows.map(function(item){
            const id = Number(item.id || 0);
            const active = Number(item.is_active || 0) === 1;
            if(active) activeCount++; else pausedCount++;
            
            const avatar = String(item.avatar_image || '').trim();
            const avatarHtml = avatar
                ? '<img src="' + esc(avatar) + '" class="avatar-circle me-3">'
                : '<div class="bg-light rounded-circle border me-3 d-inline-flex align-items-center justify-content-center shadow-sm" style="width:40px;height:40px;"><i class="bi bi-shop text-muted"></i></div>';
            
            const mapLink = item.map_url
                ? '<a href="' + esc(item.map_url) + '" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" target="_blank" rel="noopener"><i class="bi bi-map me-1"></i>Bản đồ</a>'
                : '<span class="badge bg-light text-muted fw-normal border">Chưa có link</span>';
            
            const statusBadge = active 
                ? '<span class="status-badge active bm-toggle cursor-pointer" data-id="'+id+'"><i class="bi bi-check-circle-fill me-1"></i>Hoạt động</span>'
                : '<span class="status-badge paused bm-toggle cursor-pointer" data-id="'+id+'"><i class="bi bi-pause-circle-fill me-1"></i>Tạm dừng</span>';

            return [
                '<div class="d-flex align-items-center ps-2">' + avatarHtml + '<div><div class="fw-bold text-dark">' + esc(item.branch_name || '—') + '</div><div class="small text-muted">ID: #' + id + '</div></div></div>',
                '<span class="badge bg-light text-dark border fw-normal px-2 py-1">' + esc(item.region || '—') + '</span>',
                '<div class="fw-bold text-primary">' + esc(item.hotline || '—') + '</div>',
                '<div class="small text-muted mb-2 text-truncate" style="max-width:200px;">' + esc(item.address_detail || '—') + '</div>' + mapLink,
                statusBadge,
                '<div class="text-end ps-3"><div class="branch-actions"><button type="button" class="btn btn-outline-primary bm-edit" data-id="' + id + '" title="Sửa"><i class="bi bi-pencil-square"></i></button><button type="button" class="btn btn-outline-danger bm-del" data-id="' + id + '" title="Xóa"><i class="bi bi-trash"></i></button></div></div>'
            ];
        });

        dt.rows.add(dataForDt).draw();
        
        $('#countTotal').text(rows.length);
        $('#countActive').text(activeCount);
        $('#countPaused').text(pausedCount);
        $('#tableMeta').text('Tổng: ' + rows.length + ' chi nhánh');
    }

    function loadAll(){
        requestJson({ url: API, method: 'GET', data: { action: 'list' } }, function(res){
            if (!res || !res.ok) return;
            regionOptions = Array.isArray(res.regions) ? res.regions : [];
            rows = Array.isArray(res.rows) ? res.rows : [];
            renderRegionSelect();
            renderTable();
        });
    }

    $('#bmAddBtn').on('click', function(){ resetForm(); $('#bmModalLabel').text('Thêm chi nhánh mới'); bmModal.show(); });
    $('#searchBranch').on('input', function(){ dt.search($(this).val()).draw(); });
    $('#filterRegion').on('change', function(){ dt.column(1).search($(this).val()).draw(); });
    $('#filterStatus').on('change', function(){
        const val = $(this).val();
        if(val === 'active') dt.column(4).search('Hoạt động').draw();
        else if(val === 'paused') dt.column(4).search('Tạm dừng').draw();
        else dt.column(4).search('').draw();
    });

    $(document).on('click', '.summary-card', function(){
        $('.summary-card').removeClass('active');
        $(this).addClass('active');
        const f = $(this).data('filter');
        $('#filterStatus').val(f).trigger('change');
    });

    $('#bmSaveBtn').on('click', function(){
        const id = Number($('#bmId').val() || 0);
        const branchName = $('#bmBranchName').val().trim();
        if (!branchName) { notify('Nhập tên chi nhánh', 'warning'); return; }
        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('id', id);
        formData.append('branch_name', branchName);
        formData.append('region', $('#bmRegion').val());
        formData.append('address_detail', $('#bmAddress').val().trim());
        formData.append('hotline', $('#bmHotline').val().trim());
        formData.append('map_url', $('#bmMapUrl').val().trim());
        formData.append('is_active', $('#bmIsActive').val() === '1' ? 1 : 0);
        formData.append('avatar_current', $('#bmAvatarCurrent').val());
        formData.append('gallery_existing', $('#bmGalleryExisting').val());
        formData.append('opening_hours_json', collectHoursJson());
        const avatarFile = $('#bmAvatarFile')[0]?.files?.[0];
        if (avatarFile) formData.append('avatar_image', avatarFile);
        const galleryFiles = $('#bmGalleryFiles')[0]?.files || [];
        for (let i = 0; i < galleryFiles.length; i++) formData.append('gallery_images[]', galleryFiles[i]);

        const $btn = $(this); $btn.prop('disabled', true);
        $.ajax({ url: API, method: 'POST', data: formData, processData: false, contentType: false, dataType: 'text' }).done(function(raw){
            $btn.prop('disabled', false);
            const res = parseJsonLoose(raw);
            if (res && res.ok) { notify('Lưu thành công', 'success'); bmModal.hide(); loadAll(); }
            else notify(res?.msg || 'Lỗi', 'error');
        }).fail(function(){ $btn.prop('disabled', false); notify('Lỗi kết nối', 'error'); });
    });

    $('#bmAiSuggestBtn').on('click', function(){
        const branchName = $('#bmBranchName').val().trim(), region = $('#bmRegion').val(), addressInput = $('#bmAddress').val().trim();
        if (!branchName && !addressInput) return;
        const $btn = $(this); $btn.prop('disabled', true);
        requestJson({ url: API, method: 'POST', data: { action: 'ai_suggest', branch_name: branchName, region: region, address_input: addressInput } }, function(res){
            $btn.prop('disabled', false);
            if (res && res.ok) {
                const d = res.data || {};
                if (d.address_detail) $('#bmAddress').val(d.address_detail);
                if (d.map_url) $('#bmMapUrl').val(d.map_url);
                if (d.hotline && !$('#bmHotline').val().trim()) $('#bmHotline').val(d.hotline);
            }
        });
    });

    $('#bmAvatarFile').on('change', function(){
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e){
                $('#bmAvatarPreview').attr('src', e.target.result).removeClass('d-none');
                $('#bmAvatarEmpty').addClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    });

    $('#bmGalleryFiles').on('change', function(){
        const files = this.files;
        const $wrap = $('#bmGalleryWrap');
        $wrap.find('.new-gallery-item').remove();
        if (!files.length) {
            if (!$wrap.children().length) $wrap.html('<div class="text-muted small fst-italic w-100">Chưa có ảnh nào.</div>');
            return;
        }
        if ($wrap.find('.text-muted').length) $wrap.empty();

        for (let i = 0; i < files.length; i++) {
            const file = files[i], reader = new FileReader(), fIdx = i; 
            reader.onload = function(e){
                const html = '<div class="position-relative border rounded-3 overflow-hidden shadow-sm new-gallery-item" style="width:70px; height:70px;">'
                    + '<img src="'+e.target.result+'" class="w-100 h-100 object-fit-cover">'
                    + '<div class="position-absolute bottom-0 start-0 w-100 bg-primary text-white text-center" style="font-size:9px; font-weight:bold; opacity:0.9;">MỚI</div>'
                    + '<button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center new-gallery-remove" data-idx="'+fIdx+'" style="width:18px; height:18px; font-size:10px; opacity:0.9; z-index:10;">&times;</button>'
                    + '</div>';
                $wrap.append(html);
            };
            reader.readAsDataURL(file);
        }
    });

    $('#bmGalleryWrap').on('click', '.new-gallery-remove', function(e){
        e.preventDefault(); e.stopPropagation();
        const idx = Number($(this).data('idx')), input = $('#bmGalleryFiles')[0], dt = new DataTransfer(), { files } = input;
        for (let i = 0; i < files.length; i++) if (i !== idx) dt.items.add(files[i]);
        input.files = dt.files; $(input).trigger('change');
    });

    $('#bmGalleryWrap').on('click', '.branch-gallery-remove', function(){
        const idx = Number($(this).data('idx'));
        let current = JSON.parse($('#bmGalleryExisting').val() || '[]');
        if (idx >= 0 && idx < current.length) { current.splice(idx, 1); $('#bmGalleryExisting').val(JSON.stringify(current)); renderGallery(current); }
    });

    $('#bmTable').on('click', '.bm-edit', function(){
        const id = Number($(this).data('id') || 0);
        const row = rows.find(r => Number(r.id || 0) === id);
        if (row) { fillForm(row); bmModal.show(); }
    });

    $('#bmTable').on('click', '.bm-del', function(){
        const id = Number($(this).data('id') || 0);
        if (id && confirm('Xóa chi nhánh này?')) {
            requestJson({ url: API, method: 'POST', data: { action: 'delete', id: id } }, function(res){
                if (res && res.ok) loadAll();
            });
        }
    });

    $('#bmTable').on('click', '.bm-toggle', function(){
        const id = Number($(this).data('id') || 0);
        const row = rows.find(r => Number(r.id || 0) === id);
        if (!row) return;
        const newStatus = (Number(row.is_active || 0) === 1) ? 0 : 1;
        requestJson({ url: API, method: 'POST', data: { action: 'toggle_active', id: id, is_active: newStatus } }, function(res){
            if (res && res.ok) loadAll();
        });
    });

    loadAll();
})();
</script>
