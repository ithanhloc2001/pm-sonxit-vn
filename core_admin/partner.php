<?php 
require_once __DIR__ . '/_admin_guard.php';
?>
<div class="partner-admin-shell">
    <!-- Header -->
    <div class="partner-admin-head">
        <div class="partner-head-left">
            <span class="partner-head-icon"><i class="bi bi-people-fill"></i></span>
            <div>
                <h1 class="partner-admin-title">Quản lý đối tác</h1>
                <p class="partner-admin-sub">Thiết lập danh sách các đơn vị đối tác hiển thị trên website</p>
            </div>
        </div>
        <button class="partner-btn-add" type="button" onclick="resetPartnerForm()" data-bs-toggle="modal" data-bs-target="#partnerModal">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm đối tác mới</span>
        </button>
    </div>

    <!-- Toolbar -->
    <div class="partner-toolbar">
        <div class="partner-toolbar-field">
            <label>Lọc theo trạng thái</label>
            <select id="filterStatus">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang hiển thị</option>
                <option value="0">Đang ẩn</option>
            </select>
        </div>
        <div class="partner-toolbar-meta">
            <span class="partner-count-pill"><i class="bi bi-collection"></i> Tổng số: <span id="pmTotal">0</span></span>
            <button class="partner-btn-refresh" type="button" onclick="loadAllPartners()">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Làm mới</span>
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="partner-table-wrap">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="pmTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 46px;"></th>
                        <th class="ps-3" style="width: 92px;">Logo</th>
                        <th>Thông tin đối tác</th>
                        <th class="text-center" style="width: 140px;">Trạng thái</th>
                        <th class="text-end pe-3" style="width: 140px;">Hành động</th>
                    </tr>
                </thead>
                <tbody id="partnerTableBody">
                    <!-- Dữ liệu được load bằng AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Partner Modal -->
<div class="modal fade" id="partnerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content partner-modal">
            <div class="partner-modal-header">
                <div class="partner-modal-titlewrap">
                    <span class="partner-modal-icon"><i class="bi bi-building-add"></i></span>
                    <h5 class="modal-title" id="modalTitle">Thêm đối tác mới</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="partnerForm">
                    <input type="hidden" id="pmId" value="0">
                    <input type="hidden" id="pmCurrentImage" value="">

                    <div class="row g-4">
                        <div class="col-12 col-md-7">
                            <div class="mb-3">
                                <label class="partner-label">Tên đối tác <span class="text-danger">*</span></label>
                                <input type="text" class="form-control partner-input" id="pmName" placeholder="Nhập tên đơn vị đối tác" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="partner-label">Thứ tự hiển thị</label>
                                    <input type="number" class="form-control partner-input" id="pmSortOrder" value="100">
                                </div>
                                <div class="col-6">
                                    <label class="partner-label">Trạng thái</label>
                                    <div class="partner-switch-wrap">
                                        <label class="pm-switch mb-0" for="pmActive">
                                            <input type="checkbox" id="pmActive" checked>
                                            <span class="pm-slider"></span>
                                        </label>
                                        <span class="pm-switch-text">Công khai</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="partner-label">Giới thiệu ngắn</label>
                                <textarea class="form-control partner-input" id="pmIntro" rows="4" placeholder="Mô tả về đối tác..."></textarea>
                            </div>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="partner-label">Logo đối tác</label>
                            <label class="partner-upload-box mb-2" id="pmPreview" for="pmImageFile">
                                <div class="text-center text-muted">
                                    <i class="bi bi-cloud-arrow-up fs-1 d-block mb-2 opacity-50"></i>
                                    <span class="small">Nhấn để chọn ảnh</span>
                                </div>
                            </label>
                            <input type="file" id="pmImageFile" class="d-none" accept="image/*">
                            <div class="partner-upload-hint">
                                <i class="bi bi-info-circle me-1"></i> Định dạng: JPG, PNG, WebP (Max 5MB)
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="partner-modal-footer">
                <button type="button" class="partner-btn-ghost" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="partner-btn-save" id="pmSaveBtn">
                    <i class="bi bi-save me-2"></i>Lưu thông tin
                </button>
            </div>
        </div>
    </div>
</div>

<style>
:root { --pm-green: var(--theme-primary, #0c4c29); --pm-green-dark: #08341c; }

/* ===== Shell ===== */
.partner-admin-shell{
    background:#fff;border:1px solid #e2e8f0;border-radius:18px;
    box-shadow:0 12px 30px rgba(15,23,42,.07);padding:22px;
    display:flex;flex-direction:column;gap:18px;
}

/* ===== Header ===== */
.partner-admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.partner-head-left{display:flex;align-items:center;gap:14px;}
.partner-head-icon{
    width:52px;height:52px;flex:0 0 52px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    background:rgba(12,76,41,.1);color:var(--pm-green);font-size:1.5rem;
    border:1px solid rgba(12,76,41,.15);transition:all .3s cubic-bezier(.4,0,.2,1);
}
.partner-head-icon:hover{transform:scale(1.06) rotate(3deg);background:rgba(12,76,41,.16);}
.partner-admin-title{font-size:1.4rem;font-weight:800;color:#0f172a;margin:0;line-height:1.2;}
.partner-admin-sub{font-size:.86rem;color:#64748b;margin:2px 0 0;}

.partner-btn-add{
    display:inline-flex;align-items:center;gap:8px;
    background:var(--pm-green);color:#fff;border:none;
    padding:11px 22px;border-radius:11px;font-weight:700;font-size:.9rem;
    box-shadow:0 4px 12px rgba(12,76,41,.18);transition:all .25s cubic-bezier(.4,0,.2,1);
}
.partner-btn-add:hover{background:var(--pm-green-dark);transform:translateY(-2px);box-shadow:0 8px 18px rgba(12,76,41,.28);}
.partner-btn-add:active{transform:translateY(0);}

/* ===== Toolbar ===== */
.partner-toolbar{
    display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;
    background:#f8fafc;border:1px solid #eef2f6;border-radius:14px;padding:14px 16px;
}
.partner-toolbar-field{display:flex;flex-direction:column;gap:6px;min-width:220px;}
.partner-toolbar-field label{font-size:.74rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#64748b;}
.partner-toolbar-field select{
    border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:9px 12px;
    font-size:.88rem;color:#0f172a;outline:none;transition:border-color .2s,box-shadow .2s;
}
.partner-toolbar-field select:focus{border-color:var(--pm-green);box-shadow:0 0 0 3px rgba(12,76,41,.12);}
.partner-toolbar-meta{display:flex;align-items:center;gap:10px;}
.partner-count-pill{
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(12,76,41,.1);color:var(--pm-green);
    padding:8px 14px;border-radius:999px;font-size:.82rem;font-weight:700;
}
.partner-btn-refresh{
    display:inline-flex;align-items:center;gap:7px;
    background:#fff;color:#475569;border:1px solid #e2e8f0;
    padding:8px 16px;border-radius:10px;font-size:.86rem;font-weight:600;transition:all .2s;
}
.partner-btn-refresh:hover{border-color:#cbd5e1;background:#f1f5f9;color:#0f172a;}

/* ===== Table ===== */
.partner-table-wrap{border:1px solid #eef2f6;border-radius:14px;overflow:hidden;}
#pmTable{border-collapse:separate!important;border-spacing:0!important;width:100%!important;}
#pmTable thead th{
    font-size:.72rem!important;font-weight:700!important;text-transform:uppercase!important;
    letter-spacing:.05em!important;color:#475569!important;
    padding:13px 16px!important;background:#f8fafc!important;border-bottom:1px solid #e5e7eb!important;
}
#pmTable tbody td{padding:12px 16px!important;border-bottom:1px solid #f1f5f9!important;vertical-align:middle!important;font-size:.88rem!important;}
#pmTable tbody tr{transition:background-color .15s ease;}
#pmTable tbody tr:hover{background:rgba(12,76,41,.02);}
#pmTable tbody tr:last-child td{border-bottom:none!important;}

.partner-logo-cell{
    width:58px;height:46px;display:flex;align-items:center;justify-content:center;
    background:#fff;border:1px solid #e5e7eb;border-radius:9px;overflow:hidden;
}
.partner-logo-cell img{max-width:100%;max-height:100%;object-fit:contain;}
.partner-logo-cell i{color:#cbd5e1;font-size:1.1rem;}

.drag-handle{cursor:grab;color:#cbd5e1;transition:all .2s;font-size:1.2rem;}
.drag-handle:hover{color:var(--pm-green);}
.drag-handle:active{cursor:grabbing;}
.sortable-ghost{opacity:.4;background:#f1f5f9!important;}
.sortable-chosen{background:#fff!important;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);}

.status-badge{
    display:inline-flex;align-items:center;padding:5px 13px;border-radius:999px;
    font-size:.72rem;font-weight:700;letter-spacing:.03em;border:1px solid transparent;transition:all .2s;
}
.status-badge.active{background:#f0fdf4;color:#166534;border-color:#bbf7d0;}
.status-badge.inactive{background:#f8fafc;color:#64748b;border-color:#e2e8f0;}

/* Row action buttons */
#pmTable .btn-group{gap:4px;}
#pmTable .btn-white{
    background:#fff;border:1px solid #e5e7eb!important;border-radius:9px!important;
    width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;
    padding:0;transition:all .2s;
}
#pmTable .btn-white:hover{background:#f8fafc;border-color:#cbd5e1!important;transform:translateY(-1px);}

/* ===== Modal ===== */
.partner-modal{border:none!important;border-radius:18px!important;box-shadow:0 24px 60px rgba(15,23,42,.25)!important;overflow:hidden;}
.partner-modal-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:20px 24px;border-bottom:1px solid #f1f5f9;
}
.partner-modal-titlewrap{display:flex;align-items:center;gap:12px;}
.partner-modal-icon{
    width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;
    background:rgba(12,76,41,.1);color:var(--pm-green);font-size:1.2rem;
}
.partner-modal .modal-title{font-size:1.1rem;font-weight:800;color:#0f172a;margin:0;}
.partner-label{display:block;font-size:.82rem;font-weight:700;color:#334155;margin-bottom:7px;}
.partner-input{
    border:1px solid #e2e8f0!important;border-radius:10px!important;padding:10px 13px!important;
    font-size:.9rem!important;color:#0f172a!important;background:#fff!important;
    box-shadow:none!important;transition:border-color .2s,box-shadow .2s!important;
}
.partner-input:focus{border-color:var(--pm-green)!important;box-shadow:0 0 0 3px rgba(12,76,41,.12)!important;}

.partner-switch-wrap{display:flex;align-items:center;gap:10px;margin-top:7px;}
.pm-switch{position:relative;display:inline-block;width:46px;height:26px;flex:0 0 46px;}
.pm-switch input{opacity:0;width:0;height:0;}
.pm-slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;transition:.2s;border-radius:999px;}
.pm-slider:before{content:'';position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;transition:.2s;border-radius:50%;box-shadow:0 1px 4px rgba(15,23,42,.2);}
.pm-switch input:checked + .pm-slider{background:var(--pm-green);}
.pm-switch input:checked + .pm-slider:before{transform:translateX(20px);}
.pm-switch-text{font-size:.86rem;font-weight:600;color:#475569;}

.partner-upload-box{
    width:100%;height:200px;background:#f8fafc;border:2px dashed #e2e8f0;border-radius:14px;
    display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;
    cursor:pointer;transition:all .3s;
}
.partner-upload-box:hover{border-color:var(--pm-green);background:#f1f5f9;}
.partner-upload-box img{max-width:100%;max-height:100%;object-fit:contain;}
.partner-upload-hint{font-size:.78rem;color:#94a3b8;margin-top:4px;}

.partner-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #f1f5f9;background:#fafbfc;}
.partner-btn-ghost{
    background:#fff;border:1px solid #e2e8f0;color:#475569;
    padding:10px 22px;border-radius:10px;font-weight:600;font-size:.88rem;transition:all .2s;
}
.partner-btn-ghost:hover{background:#f1f5f9;color:#0f172a;}
.partner-btn-save{
    background:var(--pm-green);border:none;color:#fff;
    padding:10px 24px;border-radius:10px;font-weight:700;font-size:.88rem;
    box-shadow:0 4px 12px rgba(12,76,41,.2);transition:all .25s cubic-bezier(.4,0,.2,1);
}
.partner-btn-save:hover{background:var(--pm-green-dark);transform:translateY(-2px);box-shadow:0 8px 18px rgba(12,76,41,.3);}
.partner-btn-save:disabled{opacity:.7;transform:none;cursor:not-allowed;}

/* DataTable overrides */
.dataTables_wrapper .dataTables_filter{padding:14px 16px 6px;}
.dataTables_wrapper .dataTables_filter input{border:1px solid #e2e8f0;border-radius:10px;padding:7px 12px;margin-left:8px;outline:none;}
.dataTables_wrapper .dataTables_filter input:focus{border-color:var(--pm-green);box-shadow:0 0 0 3px rgba(12,76,41,.12);}
.dataTables_wrapper .dataTables_info{padding:8px 16px 14px;color:#64748b;font-size:.82rem;}
.dataTables_wrapper .dataTables_paginate{padding:8px 16px 14px;}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:var(--pm-green)!important;border-color:var(--pm-green)!important;color:#fff!important;border-radius:8px;}
table.dataTable thead th{border-bottom:1px solid #f1f5f9!important;}

@media(max-width:768px){
    .partner-admin-shell{padding:16px;border-radius:14px;}
    .partner-admin-title{font-size:1.2rem;}
    .partner-toolbar-field{min-width:100%;}
    .partner-toolbar-meta{width:100%;justify-content:space-between;}
}
</style>

<!-- DataTables & SortableJS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
(function($){
    $(function(){
        const API = '<?= h($baseUrl . '/core_admin/ajax/partner.php') ?>';
        let partnerTable = null;
        let allPartners = [];
        
        // Initialize Modal safely
        const modalEl = document.getElementById('partnerModal');
        let pmModal = null;
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            pmModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        function notify(msg, type = 'info'){
            if (window.toastr && toastr[type]) toastr[type](msg);
            else alert(msg);
        }

        function esc(v){ return $('<div>').text(String(v || '')).html(); }

        function normalizeImage(src){
            const raw = String(src || '').trim();
            if (!raw) return '';
            if (/^https?:\/\//i.test(raw) || raw.indexOf('data:image/') === 0) return raw;
            // Ưu tiên media domain (toMediaUrl từ head.php) cho file trong thư mục upload.
            if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(raw);
            return raw.startsWith('/') ? raw : ('/' + raw.replace(/^\/+/, ''));
        }

        window.renderPartnerPreview = function(src){
            const image = normalizeImage(src);
            const $preview = $('#pmPreview');
            if (!image) {
                $preview.html('<div class="text-center text-muted"><i class="bi bi-cloud-arrow-up fs-1 d-block mb-2 opacity-50"></i><span class="small">Nhấn để chọn ảnh</span></div>');
                return;
            }
            $preview.html('<img src="' + image + '" class="img-fluid">');
        };

        window.resetPartnerForm = function(){
            $('#pmId').val('0');
            $('#pmCurrentImage').val('');
            $('#pmName').val('');
            $('#pmIntro').val('');
            $('#pmSortOrder').val('100');
            $('#pmActive').prop('checked', true);
            $('#pmImageFile').val('');
            $('#modalTitle').text('Thêm đối tác mới');
            renderPartnerPreview('');
        };

        function fillPartnerForm(item){
            $('#pmId').val(item.id);
            $('#pmCurrentImage').val(item.image_url);
            $('#pmName').val(item.partner_name);
            $('#pmIntro').val(item.intro);
            $('#pmSortOrder').val(item.sort_order);
            $('#pmActive').prop('checked', Number(item.is_active) === 1);
            $('#pmImageFile').val('');
            $('#modalTitle').text('Chỉnh sửa: ' + item.partner_name);
            renderPartnerPreview(item.image_url);
            if (pmModal) pmModal.show();
        }

        window.loadAllPartners = function(){
            const status = $('#filterStatus').val();
            $.get(API, { action: 'list' }, function(raw){
                try {
                    const res = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (res.ok) {
                        allPartners = res.rows || [];
                        renderPartnerTable(allPartners, status);
                    } else {
                        notify(res.msg || 'Không thể tải danh sách', 'error');
                    }
                } catch(e) { 
                    console.error('Parse error:', e);
                    notify('Lỗi xử lý dữ liệu từ server', 'error');
                }
            }).fail(function(){
                notify('Lỗi kết nối server', 'error');
            });
        };

        function renderPartnerTable(data, statusFilter = ''){
            if (partnerTable) {
                partnerTable.destroy();
            }

            let filtered = data;
            if (statusFilter !== '') {
                filtered = data.filter(r => String(r.is_active) === statusFilter);
            }

            $('#pmTotal').text(filtered.length);

            const html = filtered.map(item => {
                const id = item.id;
                const active = Number(item.is_active) === 1;
                const badge = active 
                    ? '<span class="status-badge active">Công khai</span>' 
                    : '<span class="status-badge inactive">Đang ẩn</span>';
                
                const img = normalizeImage(item.image_url);
                const imgHtml = img
                    ? '<div class="partner-logo-cell"><img src="' + esc(img) + '" alt="logo"></div>'
                    : '<div class="partner-logo-cell"><i class="bi bi-image text-muted"></i></div>';

                return `<tr data-id="${id}">
                    <td class="text-center">
                        <div class="drag-handle">
                            <i class="bi bi-grip-vertical"></i>
                        </div>
                    </td>
                    <td class="ps-4">${imgHtml}</td>
                    <td>
                        <div class="fw-bold text-dark">${esc(item.partner_name)}</div>
                        <div class="small text-muted text-truncate" style="max-width: 300px;">${esc(item.intro || 'Không có mô tả')}</div>
                    </td>
                    <td class="text-center">${badge}</td>
                    <td class="text-end pe-4">
                        <div class="btn-group shadow-sm">
                            <button class="btn btn-white btn-sm pm-edit" data-id="${id}" title="Sửa"><i class="bi bi-pencil-fill text-primary"></i></button>
                            <button class="btn btn-white btn-sm pm-toggle" data-id="${id}" title="Bật/Tắt"><i class="bi bi-power ${active ? 'text-success' : 'text-danger'}"></i></button>
                            <button class="btn btn-white btn-sm pm-del" data-id="${id}" title="Xóa"><i class="bi bi-trash-fill text-danger"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            $('#partnerTableBody').html(html);

            partnerTable = $('#pmTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json',
                    searchPlaceholder: "Tìm đối tác..."
                },
                pageLength: 25,
                order: [],
                columnDefs: [
                    { targets: [0, 1, 4], orderable: false }
                ],
                dom: '<"d-flex justify-content-between align-items-center"f>t<"d-flex justify-content-between align-items-center"ip>',
                drawCallback: function() {
                    initPartnerSortable();
                }
            });
        }

        function initPartnerSortable() {
            const el = document.getElementById('partnerTableBody');
            if (!el || typeof Sortable === 'undefined') return;

            if (el.sortable) el.sortable.destroy();

            const isFiltered = partnerTable && (partnerTable.search() !== '' || $('#filterStatus').val() !== '');
            
            el.sortable = new Sortable(el, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                disabled: isFiltered,
                onEnd: function() {
                    const ids = [];
                    $('#partnerTableBody tr').each(function() {
                        const rowId = $(this).data('id');
                        if (rowId) ids.push(rowId);
                    });
                    if (ids.length > 0) savePartnerOrder(ids);
                }
            });
            
            const $handles = $('.drag-handle');
            if (isFiltered) {
                $handles.css('opacity', '0.2').css('cursor', 'not-allowed').attr('title', 'Tắt lọc để sắp xếp');
            } else {
                $handles.css('opacity', '1').css('cursor', 'grab').removeAttr('title');
            }
        }

        function savePartnerOrder(ids) {
            $.post(API, { action: 'sort', ids: ids }, function(res) {
                if (res.ok) {
                    notify(res.msg, 'success');
                } else {
                    notify(res.msg, 'error');
                    loadAllPartners();
                }
            });
        }

        // Handlers
        $('#filterStatus').on('change', function(){
            renderPartnerTable(allPartners, $(this).val());
        });

        $('#pmImageFile').on('change', function(){
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => renderPartnerPreview(e.target.result);
                reader.readAsDataURL(file);
            }
        });

        $('#pmSaveBtn').on('click', function(){
            const name = $('#pmName').val().trim();
            if (!name) return notify('Vui lòng nhập tên đối tác', 'warning');

            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('id', $('#pmId').val());
            fd.append('partner_name', name);
            fd.append('intro', $('#pmIntro').val().trim());
            fd.append('sort_order', $('#pmSortOrder').val());
            fd.append('is_active', $('#pmActive').is(':checked') ? '1' : '0');
            fd.append('current_image', $('#pmCurrentImage').val());
            
            const file = $('#pmImageFile')[0].files[0];
            if (file) fd.append('partner_image', file);

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...');

            $.ajax({
                url: API, method: 'POST', data: fd, processData: false, contentType: false
            }).done(res => {
                if (res.ok) {
                    notify(res.msg, 'success');
                    if (pmModal) pmModal.hide();
                    loadAllPartners();
                } else notify(res.msg, 'error');
            }).fail(() => {
                notify('Lỗi kết nối khi lưu dữ liệu', 'error');
            }).always(() => {
                $btn.prop('disabled', false).html('<i class="bi bi-save me-2"></i>Lưu thông tin');
            });
        });

        $('#pmTable').on('click', '.pm-edit', function(){
            const id = $(this).data('id');
            const item = allPartners.find(r => r.id == id);
            if (item) fillPartnerForm(item);
        });

        $('#pmTable').on('click', '.pm-toggle', function(){
            const id = $(this).data('id');
            $.post(API, { action: 'toggle', id: id }, res => {
                if (res.ok) { notify(res.msg, 'success'); loadAllPartners(); }
                else notify(res.msg, 'error');
            });
        });

        $('#pmTable').on('click', '.pm-del', function(){
            const id = $(this).data('id');
            if (confirm('Bạn chắc chắn muốn xóa đối tác này?')) {
                $.post(API, { action: 'delete', id: id }, res => {
                    if (res.ok) { notify(res.msg, 'success'); loadAllPartners(); }
                    else notify(res.msg, 'error');
                });
            }
        });

        loadAllPartners();
    });
})(jQuery);
</script>