<?php
require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../core/support/support_common.php';
support_ensure_tables($ithanhloc);
$cats = support_categories();
$pris = support_priorities();
$stats = support_statuses();
$csrf = (string)($_SESSION['csrf_token'] ?? '');
?>
<style>
/* Card không cắt dropdown theo chiều dọc; vẫn giữ cuộn ngang cho bảng trên mobile. */
.sup-table-card { overflow: visible !important; }
</style>
<div class="container-fluid py-4">
    <!-- Page Header (đồng bộ trang Đơn hàng) -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;min-width:48px;background-color:rgba(12,76,41,.08)!important;color:#0c4c29!important;border:1px solid rgba(12,76,41,.15);">
                <i class="bi bi-life-preserver fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size:1.45rem;color:#1e293b!important;letter-spacing:-0.01em;">Quản lý hỗ trợ (Ticket)</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="ticketsMeta" style="font-size:0.72rem;">Đang tải dữ liệu...</span>
                </div>
                <p class="text-muted mb-0 small" style="font-size:0.82rem;line-height:1.45;max-width:600px;">
                    Tiếp nhận và xử lý yêu cầu hỗ trợ theo độ ưu tiên.
                </p>
            </div>
        </div>
        <a href="/admin/faq-manager" class="btn btn-outline-secondary"><i class="bi bi-patch-question me-1"></i>Quản lý FAQ</a>
    </div>

    <!-- Thẻ tóm tắt theo trạng thái (bấm để lọc) -->
    <div class="mb-4" id="summaryGrid"></div>

    <!-- Filters & Actions -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 sup-table-card">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-0" id="fQ" placeholder="Tìm mã / đơn / SĐT / tên...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="fPriority" class="form-select form-select-sm border shadow-sm rounded-3">
                        <option value="">Tất cả ưu tiên</option>
                        <?php foreach ($pris as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="fCategory" class="form-select form-select-sm border shadow-sm rounded-3">
                        <option value="">Tất cả loại</option>
                        <?php foreach ($cats as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <!-- ô trạng thái ẩn để đồng bộ với thẻ tóm tắt (giống order: filterStatus) -->
                <select id="fStatus" class="d-none"><option value=""></option><?php foreach ($stats as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select>
                <div class="col-md-4 d-flex justify-content-end gap-2">
                    <div class="d-none" id="bulkActionsContainer">
                        <button class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm fw-bold" type="button" id="btnDeleteSelected">
                            <i class="bi bi-trash3-fill"></i> Xoá (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="fb-table-responsive border-top">
            <table id="ticketsTable" class="table fb-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width:40px;">
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="checkAllTickets"></div>
                        </th>
                        <th style="width:120px;">Mã</th>
                        <th>Tiêu đề</th>
                        <th>Khách</th>
                        <th>Loại</th>
                        <th>Ưu tiên</th>
                        <th>Trạng thái</th>
                        <th>Cập nhật</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="ticketsBody" class="border-top-0"></tbody>
            </table>
        </div>

        <div id="ticketsEmpty" class="text-center py-5 text-muted" style="display:none;">
            <div class="py-4">
                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                <p class="mb-0">Không có yêu cầu nào phù hợp.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var AJAX = '/core_admin/support/ajax/ticket.php';
    var CSRF = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;

    // Thẻ tóm tắt theo trạng thái (key khớp KPI + filter)
    var tabs = [
        { key: 'all', label: 'Tất cả', icon: 'bi bi-collection-fill' },
        { key: 'open', label: 'Đang mở', icon: 'bi bi-envelope-open' },
        { key: 'pending', label: 'Chờ phản hồi', icon: 'bi bi-hourglass-split' },
        { key: 'resolved', label: 'Đã xử lý', icon: 'bi bi-check-circle' },
        { key: 'closed', label: 'Đã đóng', icon: 'bi bi-archive' }
    ];
    var currentTab = 'all';
    var counts = { all: '—', total: '—', open: '—', pending: '—', resolved: '—', closed: '—' };

    var priMap = {
        high: '<span class="badge text-bg-danger">Cao</span>',
        normal: '<span class="badge text-bg-light border">Thường</span>',
        low: '<span class="badge text-bg-secondary">Thấp</span>'
    };
    var statMap = {
        open: '<span class="status-pill" style="background:#2563eb1a;color:#2563eb;">Đang mở</span>',
        pending: '<span class="status-pill" style="background:#b453091a;color:#b45309;">Chờ phản hồi</span>',
        resolved: '<span class="status-pill" style="background:#15803d1a;color:#15803d;">Đã xử lý</span>',
        closed: '<span class="status-pill" style="background:#64748b1a;color:#64748b;">Đã đóng</span>'
    };

    var $body = $('#ticketsBody');
    var $summary = $('#summaryGrid');
    var $empty = $('#ticketsEmpty');
    var $meta = $('#ticketsMeta');
    var $selCount = $('#selectedCount');
    var $btnDel = $('#btnDeleteSelected');
    var $bulk = $('#bulkActionsContainer');
    var $checkAll = $('#checkAllTickets');
    var selectedIds = new Set();
    var dataTable = null;

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function renderSummary() {
        var html = tabs.map(function (tab) {
            var active = tab.key === currentTab;
            var val = (tab.key === 'all') ? counts.total : counts[tab.key];
            return '<div class="summary-card ' + (active ? 'active' : '') + '" data-status="' + esc(tab.key) + '">' +
                '<div class="d-flex flex-column"><span>' + esc(tab.label) + '</span><strong class="mt-1">' + (val == null ? '—' : val) + '</strong></div>' +
                '<div class="summary-icon"><i class="' + tab.icon + '"></i></div>' +
                '</div>';
        }).join('');
        $summary.html(html);
    }

    function updateSelectedUi() {
        var n = selectedIds.size;
        $selCount.text(n);
        $btnDel.prop('disabled', n === 0);
        $bulk.toggleClass('d-none', n === 0);
    }

    function renderRows(list) {
        selectedIds.clear();
        $checkAll.prop('checked', false);
        updateSelectedUi();
        if (!list.length) {
            $empty.show();
            if (dataTable) { dataTable.clear().destroy(); dataTable = null; }
            $body.empty();
            return;
        }
        $empty.hide();
        if (dataTable) { dataTable.clear().destroy(); dataTable = null; }
        $body.html(list.map(function (t) {
            var who = esc(t.requester) + (t.is_guest ? ' <span class="badge text-bg-light border ms-1">Khách</span>' : '');
            if (t.phone) who += '<div class="small text-muted">' + esc(t.phone) + '</div>';
            return '<tr>' +
                '<td class="ps-4"><div class="form-check mb-0"><input class="form-check-input ticket-check" type="checkbox" data-id="' + t.id + '"></div></td>' +
                '<td class="fw-semibold">' + esc(t.code) + '</td>' +
                '<td>' + esc(t.subject) + (t.order_id ? '<div class="small text-muted">Đơn: ' + esc(t.order_id) + '</div>' : '') + '</td>' +
                '<td>' + who + '</td>' +
                '<td class="small">' + esc(t.category) + '</td>' +
                '<td>' + (priMap[t.priority] || esc(t.priority)) + '</td>' +
                '<td>' + (statMap[t.status] || esc(t.status)) + '</td>' +
                '<td class="small text-muted">' + esc(t.updated) + '</td>' +
                '<td class="text-end pe-4"><div class="d-flex justify-content-end gap-1">' +
                    '<a href="/admin/support-ticket?id=' + t.id + '" class="btn btn-sm" style="background:#0c4c29;color:#fff;">Xử lý</a>' +
                    '<div class="dropdown d-inline-block">' +
                        '<button class="quick-action-btn" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>' +
                        '<ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3" style="font-size:0.8rem;">' +
                            '<li><a class="dropdown-item py-2" href="/admin/support-ticket?id=' + t.id + '"><i class="bi bi-eye me-2"></i>Chi tiết</a></li>' +
                            '<li><hr class="dropdown-divider opacity-50"></li>' +
                            '<li><button class="dropdown-item py-2 text-danger" type="button" data-act="delete" data-id="' + t.id + '"><i class="bi bi-trash me-2"></i>Xoá yêu cầu</button></li>' +
                        '</ul>' +
                    '</div>' +
                '</div></td>' +
                '</tr>';
        }).join(''));
        dataTable = $('#ticketsTable').DataTable({
            paging: true, searching: false, ordering: false, lengthChange: false,
            pageLength: 10,
            dom: 'rt<"d-flex justify-content-between align-items-center p-4 border-top"ip>',
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json' }
        });
        // Khởi tạo dropdown với Popper "fixed" để menu thoát khỏi vùng cuộn của bảng,
        // không bị overflow của .fb-table-responsive cắt mất (kể cả khi bảng ít dòng).
        if (window.bootstrap && bootstrap.Dropdown) {
            $body.find('[data-bs-toggle="dropdown"]').each(function () {
                bootstrap.Dropdown.getOrCreateInstance(this, {
                    popperConfig: function (defaultConfig) {
                        return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                    }
                });
            });
        }
    }

    function load() {
        var p = new URLSearchParams({
            action: 'list',
            q: $('#fQ').val(),
            status: $('#fStatus').val(),
            priority: $('#fPriority').val(),
            category: $('#fCategory').val()
        });
        fetch(AJAX + '?' + p.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) return;
                if (d.kpi) {
                    counts.total = d.kpi.total || 0;
                    counts.open = d.kpi.open || 0;
                    counts.pending = d.kpi.pending || 0;
                    counts.resolved = d.kpi.resolved || 0;
                    counts.closed = d.kpi.closed || 0;
                    renderSummary();
                }
                var list = d.tickets || [];
                $meta.text(list.length + ' yêu cầu');
                renderRows(list);
            })
            .catch(function () { toastr && toastr.error('Lỗi tải danh sách'); });
    }

    // Thẻ tóm tắt: bấm để lọc theo trạng thái
    $summary.on('click', '.summary-card[data-status]', function () {
        var key = $(this).data('status');
        currentTab = key;
        $('#fStatus').val(key === 'all' ? '' : key);
        renderSummary();
        load();
    });

    // Bộ lọc
    ['fQ', 'fPriority', 'fCategory'].forEach(function (id) {
        var el = document.getElementById(id);
        el.addEventListener(id === 'fQ' ? 'input' : 'change', function () {
            clearTimeout(window.__supT); window.__supT = setTimeout(load, 250);
        });
    });

    // Chọn từng dòng
    $body.on('change', '.ticket-check', function () {
        var id = String($(this).attr('data-id') || '');
        if (!id) return;
        if ($(this).is(':checked')) selectedIds.add(id); else selectedIds.delete(id);
        var checks = $body.find('.ticket-check');
        $checkAll.prop('checked', checks.length && checks.filter(':checked').length === checks.length);
        updateSelectedUi();
    });
    $checkAll.on('change', function () {
        var c = $(this).is(':checked');
        $body.find('.ticket-check').each(function () { $(this).prop('checked', c).trigger('change'); });
    });

    // Xoá 1 yêu cầu (dropdown)
    $body.on('click', '[data-act="delete"]', function () {
        var id = $(this).data('id');
        confirmDelete('Xoá yêu cầu này?', 'Yêu cầu sẽ bị ẩn khỏi danh sách.', function () {
            $.post(AJAX, { action: 'delete_one', id: id, csrf_token: CSRF }, function (res) {
                if (res && res.ok) { toastr.success('Đã xoá yêu cầu'); load(); }
                else toastr.error((res && res.msg) || 'Không thể xoá');
            }, 'json').fail(function () { toastr.error('Lỗi kết nối server'); });
        });
    });

    // Xoá hàng loạt
    $btnDel.on('click', function () {
        if (selectedIds.size === 0) return;
        var ids = Array.from(selectedIds);
        confirmDelete('Xoá ' + ids.length + ' yêu cầu đã chọn?', 'Các yêu cầu sẽ bị ẩn khỏi danh sách.', function () {
            $.post(AJAX, { action: 'delete_multi', ids: ids, csrf_token: CSRF }, function (res) {
                if (res && res.ok) { toastr.success(res.msg || 'Đã xoá các yêu cầu đã chọn'); load(); }
                else toastr.error((res && res.msg) || 'Không thể xoá');
            }, 'json').fail(function () { toastr.error('Lỗi kết nối server'); });
        });
    });

    // Hộp xác nhận (SweetAlert2 nếu có, fallback confirm)
    function confirmDelete(title, text, onYes) {
        if (window.Swal) {
            Swal.fire({
                title: title, text: text, icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Xoá', cancelButtonText: 'Huỷ',
                confirmButtonColor: '#dc2626', cancelButtonColor: '#64748b', reverseButtons: true
            }).then(function (r) { if (r.isConfirmed) onYes(); });
        } else if (confirm(title)) { onYes(); }
    }

    renderSummary();
    load();
})();
</script>
