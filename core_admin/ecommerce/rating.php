<?php
require_once __DIR__ . '/../_admin_guard.php';
?>
<div class="container-fluid py-4">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-chat-left-heart-fill fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Quản lý đánh giá</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="tableMeta" style="font-size: 0.72rem;">Đang tải...</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Kiểm duyệt nội dung đánh giá sản phẩm, quản lý câu hỏi phản hồi khách hàng và thống kê chỉ số hài lòng.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Duyệt đánh giá, câu hỏi phản hồi khách hàng & thống kê sao.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-white border shadow-sm" id="refreshBtn" title="Làm mới">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Quick Stats & Status Navigation -->
    <div class="mb-4 grid-4" id="summaryGrid">
        <!-- JS renders .summary-card elements directly into this CSS grid -->
    </div>

    <!-- STANDALONE SEARCH & FILTERS -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Tìm kiếm</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" style="left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.88rem; pointer-events:none;"></i>
                        <input type="text" id="rvSearchInput" class="form-control" placeholder="Tìm tên sản phẩm, khách hàng..." style="padding-left:38px !important; border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Loại hình</label>
                    <select id="rvTypeFilter" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="all">Tất cả loại hình</option>
                        <option value="review">Đánh giá</option>
                        <option value="question">Hỏi đáp</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Mức sao</label>
                    <select id="rvRatingFilter" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="all">Tất cả mức sao</option>
                        <option value="5">5 Sao</option>
                        <option value="4">4 Sao</option>
                        <option value="3">3 Sao</option>
                        <option value="2">2 Sao</option>
                        <option value="1">1 Sao</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Trạng thái</label>
                    <select id="rvStatusFilter" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="show">Hiển thị</option>
                        <option value="hide">Đã ẩn</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Hiển thị</label>
                    <select id="rvLimitFilter" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="15">15 dòng</option>
                        <option value="30">30 dòng</option>
                        <option value="50">50 dòng</option>
                        <option value="100">100 dòng</option>
                        <option value="-1">Tất cả</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex justify-content-start align-items-center pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="rvUnrepliedToggle" style="width: 2.2em; height: 1.1em;">
                        <label class="form-check-label small fw-bold text-muted ms-2" for="rvUnrepliedToggle">Chưa trả lời</label>
                    </div>
                </div>
            </div>

            <!-- BULK ACTION TOOLBAR (hiện khi có chọn) -->
            <div id="bulkToolbar" class="d-none" style="overflow: hidden; max-height: 0; transition: max-height 0.25s ease, opacity 0.2s ease; opacity: 0;">
                <div class="border-top mt-3 pt-3 d-flex align-items-center gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-2 me-2">
                        <span class="badge rounded-pill fw-bold px-3 py-2" id="selectedItemCount"
                            style="background: rgba(12,76,41,0.1); color: var(--theme-primary,#0c4c29); font-size: 0.82rem; border: 1px solid rgba(12,76,41,0.2);">
                            0 đã chọn
                        </span>
                        <span class="text-muted small">Thao tác hàng loạt:</span>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="applyBulkAction('show')" style="border-radius:8px; height:34px; font-size:0.82rem; font-weight:600;">
                        <i class="bi bi-eye me-1"></i>Hiện
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="applyBulkAction('hide')" style="border-radius:8px; height:34px; font-size:0.82rem; font-weight:600;">
                        <i class="bi bi-eye-slash me-1"></i>Ẩn
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="applyBulkAction('delete')" style="border-radius:8px; height:34px; font-size:0.82rem; font-weight:600;">
                        <i class="bi bi-trash3 me-1"></i>Xóa
                    </button>
                    <button class="btn btn-sm btn-link text-muted text-decoration-none p-0 ms-1" onclick="clearBulkSelection()" title="Bỏ chọn">
                        <i class="bi bi-x-circle"></i> Bỏ chọn
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAIL LIST TABLE -->
    <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table id="ratingDataTable" class="table table-hover align-middle mb-0 table-striped" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:40px;" class="ps-4">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="selectAllItems" style="width: 1.1em; height: 1.1em;">
                            </div>
                        </th>
                        <th style="width: 250px;">Sản phẩm</th>
                        <th>Người đánh giá</th>
                        <th style="width: 100px;">Đánh giá</th>
                        <th>Nội dung</th>
                        <th style="width: 140px;">Thời gian</th>
                        <th style="width: 100px;">Trạng thái</th>
                        <th class="text-end pe-4" style="width: 100px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Interaction Modal -->
<div class="modal fade" id="interactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Hội thoại tương tác</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-3 pb-4 bg-white">
                <div class="bg-light p-3 rounded-4 mb-4 small border" id="modalProductContext"></div>
                
                <div class="d-flex flex-column gap-3 overflow-auto mb-4 p-2 custom-scrollbar" id="modalChatHistory" style="max-height: 400px; scroll-behavior: smooth;">
                    <!-- Chat content -->
                </div>
                
                <div class="border-top pt-4">
                    <label class="small fw-semibold text-muted text-uppercase mb-2 d-block" style="font-size: 0.7rem; letter-spacing: 1px;">Gửi phản hồi chính thức</label>
                    <textarea class="form-control border-light-subtle bg-light rounded-4 p-3 shadow-sm mb-3" id="replyTextArea" rows="3" placeholder="Nhập nội dung phản hồi khách hàng..." style="font-size: 0.9rem;"></textarea>
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-sm btn-outline-danger" id="deleteRootBtn" style="border-radius: 8px; font-size: 0.82rem; font-weight: 600;">
                            <i class="bi bi-trash3 me-1"></i>Xóa bình luận gốc
                        </button>
                        <button class="btn btn-success px-5 fw-semibold text-white shadow" id="submitReplyBtn" style="height: 40px; border-radius: 8px; font-size: 0.88rem; background-color: var(--theme-primary, #0c4c29); border: none;">
                            <i class="bi bi-send-fill me-2 text-white"></i>Gửi phản hồi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const API_ENDPOINT = '<?= $baseUrl ?>/core_admin/ecommerce/ajax/rating-by-product.php';
    let currentThreadId = 0;
    let selectedIds = [];
    let currentTab = 'all';

    const tabs = [
        { key: 'all', label: 'Tất cả' },
        { key: 'unreplied', label: 'Chưa phản hồi' },
        { key: 'low', label: 'Đánh giá thấp' },
        { key: 'question', label: 'Hỏi đáp' }
    ];

    const dt = $('#ratingDataTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        pageLength: 15,
        dom: 'rt<"d-flex justify-content-between align-items-center p-4 border-top"ip>',
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
        ajax: {
            url: API_ENDPOINT,
            type: 'GET',
            data: function(d) {
                d.action = 'list';
                d.status_filter = $('#rvStatusFilter').val();
                d.type_filter = $('#rvTypeFilter').val();
                d.rating = $('#rvRatingFilter').val();
                d.unreplied = $('#rvUnrepliedToggle').prop('checked') ? 1 : 0;
                d.search_val = $('#rvSearchInput').val();
            },
            error: function (xhr) {
                let msg = 'Không thể tải dữ liệu. Vui lòng kiểm tra đăng nhập.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res && res.msg) msg = res.msg;
                } catch(e) {}
                if (window.toastr) toastr.error(msg);
            }
        },
        columns: [
            { 
                data: 'id', 
                orderable: false,
                className: 'ps-4',
                render: function(v) { return `<div class="form-check mb-0"><input type="checkbox" class="form-check-input item-selector" data-id="${v}" style="width: 1.1em; height: 1.1em;"></div>`; }
            },
            { 
                data: 'product_name',
                render: function(v, t, r) {
                    const thumb = r.image_url
                        ? (typeof window.toMediaUrl === 'function' ? window.toMediaUrl(r.image_url) : '<?= $baseUrl ?>/' + r.image_url)
                        : '<?= $baseUrl ?>/assets/img/no-image.png';
                    return `
                        <div class="d-flex align-items-center gap-2">
                            <img src="${thumb}" class="rounded-3 shadow-sm border border-light-subtle" style="width: 42px; height: 42px; object-fit: cover;" onerror="this.src='<?= $baseUrl ?>/assets/img/no-image.png'">
                            <div style="min-width: 0;">
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 180px; font-size: 0.85rem;" title="${esc(v)}">${esc(v)}</div>
                                <div class="text-muted smaller fw-semibold" style="font-size: 0.65rem; margin-top: 1px;">ID: #${r.product_id}</div>
                            </div>
                        </div>
                    `;
                }
            },
            { 
                data: 'author_name',
                render: function(v) { return `<div class="small fw-bold text-dark">${esc(v)}</div>`; }
            },
            { 
                data: 'rating',
                render: function(v, t, r) {
                    if (String(r.review_type).toLowerCase() === 'question') {
                        return '<span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-bold" style="font-size: 0.68rem;">HỎI ĐÁP</span>';
                    }
                    const n = parseInt(v) || 0;
                    return `
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle px-2 py-1 fw-bold" style="font-size: 0.68rem;">
                            <i class="bi bi-star-fill me-1"></i>${n}.0
                        </span>
                    `;
                }
            },
            { 
                data: 'comment',
                render: function(v, t, r) {
                    return `
                        <div class="small text-muted text-truncate-2 mb-1" style="max-width: 320px; line-height: 1.4; font-size: 0.8rem;">${esc(v)}</div>
                        <a href="javascript:void(0)" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 text-decoration-none view-conversation px-2 py-1" data-id="${r.id}" data-author="${esc(r.author_name)}" data-product="${esc(r.product_name)}" style="font-size: 0.65rem; border-radius: 6px;">
                            <i class="bi bi-chat-dots-fill me-1"></i>${r.reply_count} PHẢN HỒI
                        </a>
                    `;
                }
            },
            { 
                data: 'created_at',
                render: function(v) { return `<div class="small text-muted fw-bold" style="font-size: 0.75rem;">${fmtDate(v)}</div>`; }
            },
            { 
                data: 'status',
                render: function(v) {
                    const active = parseInt(v) === 1;
                    return `
                        <span class="badge ${active ? 'bg-success bg-opacity-10 text-success border-success-subtle' : 'bg-danger bg-opacity-10 text-danger border-danger-subtle'} border px-2 py-1 fw-bold" style="font-size: 0.68rem;">
                            ${active ? 'HIỂN THỊ' : 'ĐÃ ẨN'}
                        </span>
                    `;
                }
            },
            { 
                data: 'id',
                orderable: false,
                className: 'text-end pe-4',
                render: function(v, t, r) {
                    const active = parseInt(r.status) === 1;
                    return `
                        <div class="rating-actions d-flex gap-1 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary toggle-status" data-id="${v}" data-current="${r.status}" title="${active ? 'Ẩn đánh giá' : 'Hiện đánh giá'}">
                                <i class="bi bi-${active ? 'eye-slash' : 'eye'}"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-single" data-id="${v}" data-name="${esc(r.comment ? r.comment.substring(0,40) : 'đánh giá này')}" title="Xóa vĩnh viễn">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[5, 'desc']],
        drawCallback: function() {
            updateKPIs();
            resetCheckboxes();
            const info = dt.page.info();
            $('#tableMeta').text('Tổng: ' + (info ? info.recordsTotal : 0) + ' mục');
        }
    });

    function esc(s) { return $('<div>').text(String(s || '')).html(); }
    function fmtDate(s) {
        if (!s) return '—';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'});
    }

    function renderSummary(stats) {
        const icons = {
            all: 'bi-collection-fill',
            unreplied: 'bi-chat-left-dots-fill',
            low: 'bi-star-half',
            question: 'bi-question-circle-fill'
        };
        const html = tabs.map(tab => {
            const isActive = tab.key === currentTab;
            let val = '0';
            if (stats) {
                if (tab.key === 'all') val = stats.total;
                else if (tab.key === 'unreplied') val = stats.unreplied;
                else if (tab.key === 'low') val = stats.low_rating;
                else if (tab.key === 'question') val = stats.question || 0;
            }
            const iconClass = icons[tab.key] || 'bi-collection-fill';
            return `
                <div class="summary-card ${isActive ? 'active' : ''}" data-status="${tab.key}">
                    <div class="d-flex flex-column">
                        <span>${tab.label}</span>
                        <strong class="mt-1">${val.toLocaleString('vi-VN')}</strong>
                    </div>
                    <div class="summary-icon">
                        <i class="${iconClass} fs-5"></i>
                    </div>
                </div>
            `;
        }).join('');
        $('#summaryGrid').html(html);
    }

    function updateKPIs() {
        $.get(API_ENDPOINT, { action: 'stats' }, function(res) {
            if (res.ok) {
                renderSummary(res.stats);
            }
        });
    }

    $(document).on('click', '.summary-card', function() {
        const status = $(this).data('status');
        quickFilter(status);
    });

    window.quickFilter = function(type) {
        currentTab = type;
        
        // Reset filters
        $('#rvStatusFilter, #rvTypeFilter, #rvRatingFilter').val('all');
        $('#rvUnrepliedToggle').prop('checked', false);

        if (type === 'unreplied') $('#rvUnrepliedToggle').prop('checked', true);
        else if (type === 'low') $('#rvRatingFilter').val('2');
        else if (type === 'question') $('#rvTypeFilter').val('question');

        // Update active state visually before AJAX returns
        $('.summary-card').removeClass('active');
        $(`.summary-card[data-status="${type}"]`).addClass('active');

        dt.draw();
    };

    function resetCheckboxes() {
        $('#selectAllItems').prop('checked', false);
        selectedIds = [];
        updateBulkBar();
    }

    $('#selectAllItems').on('change', function() {
        const checked = $(this).prop('checked');
        $('.item-selector').prop('checked', checked);
        updateSelectionState();
    });

    $('#ratingDataTable').on('change', '.item-selector', updateSelectionState);

    function updateSelectionState() {
        selectedIds = [];
        $('.item-selector:checked').each(function() {
            selectedIds.push($(this).data('id'));
        });
        updateBulkBar();
    }

    function updateBulkBar() {
        const $toolbar = $('#bulkToolbar');
        const $count = $('#selectedItemCount');
        if (selectedIds.length > 0) {
            $count.text(selectedIds.length + ' đã chọn');
            if ($toolbar.hasClass('d-none')) {
                $toolbar.removeClass('d-none').css({ 'max-height': '0', 'opacity': '0' });
                // trigger reflow then animate
                setTimeout(function() {
                    $toolbar.css({ 'max-height': '80px', 'opacity': '1' });
                }, 10);
            }
        } else {
            $toolbar.css({ 'max-height': '0', 'opacity': '0' });
            setTimeout(function() { $toolbar.addClass('d-none'); }, 250);
        }
    }

    window.clearBulkSelection = function() {
        $('.item-selector, #selectAllItems').prop('checked', false);
        selectedIds = [];
        updateBulkBar();
    };

    window.applyBulkAction = function(act) {
        if (selectedIds.length === 0) return;
        
        const count = selectedIds.length;
        let confirmMsg = '';
        if (act === 'delete') {
            confirmMsg = `⚠️ XÓA VĨNH VIỄN ${count} đánh giá?\n\nThao tác này sẽ xóa sạch:\n• Tất cả replies/phản hồi\n• Lượt thích\n• Thông báo liên quan\n• Log hệ thống\n\nKHÔNG THỂ HOÀN TÁC!`;
        } else {
            confirmMsg = `Xác nhận ${act === 'show' ? 'hiện' : 'ẩn'} ${count} đánh giá đã chọn?`;
        }
        if (!confirm(confirmMsg)) return;

        const action = act === 'delete' ? 'bulk_delete' : 'bulk_status';
        const data = { action: action, ids: selectedIds };
        if (act !== 'delete') data.status = (act === 'show' ? 1 : 0);

        // Loading state on bulk toolbar buttons
        const $toolbar = $('#bulkToolbar');
        $toolbar.find('button').prop('disabled', true);

        $.post(API_ENDPOINT, data)
            .done(function(res) {
                if (res.ok) {
                    toastr.success(res.msg || 'Thành công');
                    clearBulkSelection();
                    dt.draw();
                } else {
                    toastr.error(res.msg || 'Có lỗi xảy ra');
                }
            })
            .fail(function() {
                toastr.error('Lỗi kết nối. Vui lòng thử lại.');
            })
            .always(function() {
                $toolbar.find('button').prop('disabled', false);
            });
    };

    $('#ratingDataTable').on('click', '.toggle-status', function() {
        const id = $(this).data('id');
        const next = parseInt($(this).data('current')) === 1 ? 0 : 1;
        $.post(API_ENDPOINT, { action: 'toggle_status', review_id: id, status: next }, function(res) {
            if (res.ok) dt.draw();
            else toastr.error(res.msg || 'Lỗi cập nhật trạng thái');
        });
    });

    $('#ratingDataTable').on('click', '.delete-single', function() {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'đánh giá này';
        if (!confirm(`Xóa vĩnh viễn đánh giá:\n"${name}"\n\nThao tác sẽ xóa sạch replies, lượt thích và thông báo liên quan. KHÔNG THỂ HOÀN TÁC!`)) return;

        const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post(API_ENDPOINT, { action: 'delete_single', review_id: id })
            .done(function(res) {
                if (res.ok) {
                    toastr.success(res.msg || 'Đã xóa thành công');
                    dt.draw();
                } else {
                    toastr.error(res.msg || 'Lỗi khi xóa');
                    $btn.prop('disabled', false).html('<i class="bi bi-trash3"></i>');
                }
            })
            .fail(function() {
                toastr.error('Lỗi kết nối. Vui lòng thử lại.');
                $btn.prop('disabled', false).html('<i class="bi bi-trash3"></i>');
            });
    });

    const interactionModal = new bootstrap.Modal(document.getElementById('interactionModal'));
    $('#ratingDataTable').on('click', '.view-conversation', function() {
        currentThreadId = $(this).data('id');
        const author = $(this).data('author');
        const product = $(this).data('product');
        
        $('#modalProductContext').html(`<i class="bi bi-box-seam me-1 text-primary"></i> <span class="fw-bold">${esc(product)}</span> <span class="mx-2 text-muted">•</span> <strong>${esc(author)}</strong>`);
        $('#modalChatHistory').html('<div class="text-center py-5"><div class="spinner-border text-primary border-width-3"></div><div class="mt-2 text-muted small">Đang tải hội thoại...</div></div>');
        $('#replyTextArea').val('');
        
        interactionModal.show();
        loadConversation(currentThreadId);
    });

    function loadConversation(rid) {
        $.get(API_ENDPOINT, { action: 'thread', review_id: rid }, function(res) {
            if (res.ok) {
                const history = res.items.map(it => {
                    const isAdmin = it.author_role === 'admin';
                    const msgId   = it.id;
                    const deleteBtnHtml = `
                        <button type="button" class="btn-delete-msg btn btn-sm btn-link text-danger p-0 ms-2"
                            data-msg-id="${msgId}"
                            title="Xóa bình luận này"
                            style="font-size: 0.65rem; opacity: 0.7; text-decoration: none;"
                        ><i class="bi bi-trash3"></i></button>
                    `;
                    return `
                        <div class="d-flex flex-column ${isAdmin ? 'align-items-end' : 'align-items-start'}">
                            <div class="p-3 rounded-4 shadow-sm border ${isAdmin ? 'bg-primary text-white border-primary' : 'bg-white text-dark'}" style="max-width: 85%; ${isAdmin ? 'background-color: var(--theme-primary, #0c4c29) !important; border-color: var(--theme-primary, #0c4c29) !important; color: #fff; border-bottom-right-radius: 4px !important;' : 'background-color: #f8fafc !important; border-color: #e2e8f0 !important; color: #1e293b; border-bottom-left-radius: 4px !important;'}">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <div class="fw-bold x-small ${isAdmin ? 'text-white-50' : 'text-primary'}" style="font-size: 0.65rem; letter-spacing: 0.5px;">${isAdmin ? 'QUẢN TRỊ VIÊN' : esc(it.author_name).toUpperCase()}</div>
                                    ${deleteBtnHtml}
                                </div>
                                <div style="font-size: 0.85rem; line-height: 1.5;">${esc(it.comment)}</div>
                                <div class="x-small mt-2 opacity-50 text-end" style="font-size: 0.6rem;">${fmtDate(it.created_at)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                $('#modalChatHistory').html(history || '<div class="text-center py-5 text-muted small italic">Chưa có tương tác nào cho đánh giá này.</div>');
                setTimeout(() => {
                    const el = document.getElementById('modalChatHistory');
                    if (el) el.scrollTop = el.scrollHeight;
                }, 100);
            }
        });
    }

    $('#submitReplyBtn').on('click', function() {
        const content = $('#replyTextArea').val().trim();
        if (!content) { toastr.warning('Vui lòng nhập nội dung phản hồi'); return; }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> ĐANG GỬi...');
        
        $.post(API_ENDPOINT, { action: 'reply', review_id: currentThreadId, content: content }, function(res) {
            $btn.prop('disabled', false).html('<i class="bi bi-send-fill me-2 text-white"></i>GỬi phản hồi');
            if (res.ok) {
                $('#replyTextArea').val('');
                loadConversation(currentThreadId);
                dt.draw(false);
                toastr.success('Phản hồi thành công');
            } else {
                toastr.error(res.msg || 'Lỗi gửi phản hồi');
            }
        });
    });

    // --- XÓA TỪ̀NG REPLY TRONG MODAL ---
    $('#modalChatHistory').on('click', '.btn-delete-msg', function() {
        const msgId = $(this).data('msg-id');
        if (!msgId) return;
        if (!confirm('Ðưa bình luận này ra khỏi hội thoại?\n\nNếu đây là bình luận gốc, toàn bộ replies sẽ bị xóa theo.\nKHÔNG THỂ HOÀN TÁC!')) return;

        const $btn = $(this).prop('disabled', true);
        $.post(API_ENDPOINT, { action: 'delete_reply', review_id: msgId })
            .done(function(res) {
                if (res.ok) {
                    toastr.success(res.msg || 'Đã xóa');
                    loadConversation(currentThreadId);
                    dt.draw(false);
                } else {
                    toastr.error(res.msg || 'Lỗi khi xóa');
                    $btn.prop('disabled', false);
                }
            })
            .fail(function() {
                toastr.error('Lỗi kết nối. Vui lòng thử lại.');
                $btn.prop('disabled', false);
            });
    });

    // --- XÓA BÌNH LUẬN GỐC TỪ MODAL ---
    $('#deleteRootBtn').on('click', function() {
        if (!currentThreadId) return;
        if (!confirm('Xóa Bình luận GỐC #' + currentThreadId + '?\n\nToàn bộ replies, lượt thích và dữ liệu liên quan sẽ bị xóa. KHÔNG THỂ HOÀN TÁC!')) return;

        const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang xóa...');
        $.post(API_ENDPOINT, { action: 'delete_single', review_id: currentThreadId })
            .done(function(res) {
                if (res.ok) {
                    toastr.success(res.msg || 'Đã xóa bình luận gốc');
                    bootstrap.Modal.getInstance(document.getElementById('interactionModal')).hide();
                    dt.draw();
                } else {
                    toastr.error(res.msg || 'Lỗi khi xóa');
                    $btn.prop('disabled', false).html('<i class="bi bi-trash3 me-1"></i>Xóa bình luận gốc');
                }
            })
            .fail(function() {
                toastr.error('Lỗi kết nối. Vui lòng thử lại.');
                $btn.prop('disabled', false).html('<i class="bi bi-trash3 me-1"></i>Xóa bình luận gốc');
            });
    });

    // Reset deleteRootBtn khi đóng modal
    document.getElementById('interactionModal').addEventListener('hidden.bs.modal', function() {
        $('#deleteRootBtn').prop('disabled', false).html('<i class="bi bi-trash3 me-1"></i>Xóa bình luận gốc');
        currentThreadId = 0;
    });

    // --- BỘ LỌC TƯƠNG TÁC ---
    $('#rvStatusFilter, #rvTypeFilter, #rvRatingFilter, #rvUnrepliedToggle').on('change', () => dt.draw());
    
    $('#rvLimitFilter').on('change', function() {
        dt.page.len($(this).val()).draw();
    });

    $('#rvSearchInput').on('keyup', function(e) { 
        if (e.key === 'Enter' || this.value === '') dt.draw(); 
    });

    $('#refreshBtn').on('click', () => dt.draw());
});
</script>
