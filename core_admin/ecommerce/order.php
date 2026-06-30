<?php
require_once __DIR__ . '/../_admin_guard.php';
?>
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(37, 99, 235, 0.08) !important; color: var(--theme-primary, #2563eb) !important; border: 1px solid rgba(37, 99, 235, 0.15);">
                <i class="bi bi-receipt fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Quản lý đơn hàng</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="ordersMeta" style="font-size: 0.72rem;">Đang tải dữ liệu...</span>
                </div>
                <!-- Description for Desktop / Tablet -->
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Theo dõi, xử lý và cập nhật trạng thái đơn hàng thời gian thực.
                </p>
                <!-- Description for Mobile -->
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Theo dõi, xử lý và cập nhật trạng thái đơn hàng thời gian thực.
                </p>
            </div>
        </div>
    </div>

    <!-- Quick Stats & Status Navigation -->
    <div class="mb-4" id="summaryGrid">
    <!-- JS Loaded Status Cards -->
    </div>

    <!-- Filters & Actions Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-0" id="searchOrder" placeholder="Tìm mã đơn, tên khách, số điện thoại...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="filterStatus" class="form-select form-select-sm border shadow-sm rounded-3">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="pending">Chờ xác nhận</option>
                        <option value="processing">Chờ lấy hàng</option>
                        <option value="shipping">Chờ giao</option>
                        <option value="delivered">Đã giao</option>
                        <option value="return_requested">Chờ duyệt trả</option>
                        <option value="returned">Đã trả</option>
                        <option value="canceled">Đã huỷ</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0 small text-muted">Từ</span>
                        <input type="date" id="filterFrom" class="form-control border-0">
                        <span class="input-group-text bg-white border-0 small text-muted">Đến</span>
                        <input type="date" id="filterTo" class="form-control border-0 border-start">
                    </div>
                </div>
                <div class="col-md-2 d-flex justify-content-end gap-2">
                    <div class="d-none" id="bulkActionsContainer">
                        <button class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm fw-bold" type="button" id="btnDeleteSelected">
                            <i class="bi bi-trash3-fill"></i> (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="fb-table-responsive border-top">
            <table id="ordersTable" class="table fb-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width:40px;">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="checkAllOrders">
                            </div>
                        </th>
                        <th style="width: 140px;">Đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Sản phẩm</th>
                        <th>Thanh toán</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="ordersBody" class="border-top-0"></tbody>
            </table>
        </div>

        <div id="ordersEmpty" class="text-center py-5 text-muted" style="display:none;">
            <div class="py-4">
                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                <p class="mb-0">Không tìm thấy đơn hàng nào phù hợp.</p>
            </div>
        </div>

        <div class="card-footer bg-white border-top-0 text-center py-3">
            <button id="loadMoreOrders" class="btn btn-light rounded-pill px-4 btn-sm fw-bold d-none">Xem thêm</button>
        </div>
    </div>
</div>



<div class="modal fade" id="formModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="dataForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Thông Tin Đơn Hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="card p-3 border-0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">MÃ ĐƠN HÀNG</label>
                            <input name="order_id" id="e_order_id" class="form-control fw-bold" placeholder="(Tự động tạo)">
                            
                            <label class="form-label fw-bold small text-muted mt-3">KHÁCH HÀNG</label>
                            <input name="user_name" id="e_user_name" class="form-control" required>

                            <label class="form-label fw-bold small text-muted mt-3">SỐ ĐIỆN THOẠI</label>
                            <input name="phone" id="e_phone" class="form-control">

                            <label class="form-label fw-bold small text-muted mt-3">EMAIL</label>
                            <input type="email" name="email" id="e_email" class="form-control">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">TRẠNG THÁI GIAO HÀNG</label>
                            <select name="status" id="e_status" class="form-select mb-3">
                                <option value="pending">Chờ xác nhận</option>
                                <option value="processing">Chờ lấy hàng</option>
                                <option value="shipping">Chờ giao</option>
                                <option value="delivered">Đã giao</option>
                                <!--option value="return_requested">Trả hàng</option>
                                <option value="returned">Đã trả</option-->
                                <option value="canceled">Huỷ</option>
                            </select>

                            <label class="form-label fw-bold small text-muted mt-2">ĐỊA CHỈ</label>
                            <textarea name="address" id="e_address" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">CHI TIẾT SẢN PHẨM</label>
                            <div class="border rounded p-2 bg-white">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-md-7">
                                        <label class="form-label small text-muted mb-1">Chọn sản phẩm</label>
                                        <select id="productSelect" class="form-select">
                                            <option value="">-- Chọn sản phẩm --</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">Số lượng</label>
                                        <input id="productQty" type="number" min="1" value="1" class="form-control">
                                    </div>
                                    <div class="col-6 col-md-2 d-grid">
                                        <button type="button" class="btn btn-outline-primary" id="btnAddProduct"><i class="bi bi-plus-lg"></i></button>
                                    </div>
                                </div>

                                <div class="mt-2" id="productList" style="max-height: 220px; overflow:auto;"></div>

                                <!-- keep legacy field for backend compatibility -->
                                <textarea name="product" id="e_product" class="form-control d-none" rows="3"></textarea>
                                <input type="hidden" name="products_json" id="e_products_json" value="[]">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">GHI CHÚ</label>
                            <input name="note" id="e_note" class="form-control bg-white text-danger">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="ipnModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="fw-bold">Lịch sử IPN</div>
                    <div class="text-muted small" id="ipnOrderTitle">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="ipn-table" id="ipnTable">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Mã phản hồi</th>
                                <th>Trạng thái</th>
                                <th>Số tiền</th>
                                <th>Ngân hàng</th>
                                <th>Hợp lệ</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div class="ipn-empty" id="ipnEmpty" style="display:none;">Không có log IPN.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Trigger ẩn để open modal đổi địa chỉ admin -->
<button class="btn-admin-edit-address d-none" type="button"></button>

<!-- Modal Admin Đổi Địa Chỉ -->
<div class="modal fade" id="adminEditAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Đổi địa chỉ nhận hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="hidden" id="adminEaOrderId">
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small">Họ tên người nhận <span class="text-danger">*</span></label>
                        <input type="text" id="adminEaName" class="form-control" maxlength="120">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="tel" id="adminEaPhone" class="form-control" maxlength="20">
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label fw-semibold small">Tỉnh / Thành phố <span class="text-danger">*</span></label>
                        <select id="adminEaProvince" class="form-select">
                            <option value="">-- Chọn tỉnh/thành --</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label fw-semibold small">Quận / Huyện <span class="text-danger">*</span></label>
                        <select id="adminEaDistrict" class="form-select" disabled>
                            <option value="">-- Chọn quận/huyện --</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label fw-semibold small">Phường / Xã <span class="text-danger">*</span></label>
                        <select id="adminEaWard" class="form-select" disabled>
                            <option value="">-- Chọn phường/xã --</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Địa chỉ chi tiết (số nhà, tên đường) <span class="text-danger">*</span></label>
                        <input type="text" id="adminEaStreet" class="form-control" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary px-4 rounded-pill fw-semibold" id="btnAdminSaveAddress">
                    <i class="bi bi-check2 me-1"></i>Lưu địa chỉ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    // --- CẤU HÌNH ĐƯỜNG DẪN API ---
    const API_URL = '<?= h($baseUrl) ?>/core_admin/ecommerce/ajax/order.php'; 
    const BASE_URL = '<?= h($baseUrl) ?>';
    const FALLBACK_IMG = <?= json_encode(to_abs_url(((string)($site_fallback_logo ?? '') !== '' ? (string)$site_fallback_logo : 'image/paintmore.svg'), (string)$baseUrl), JSON_UNESCAPED_UNICODE) ?>;
    // --- Table-based admin list view ---
    let currentTab = 'all';
    let page = 1;
    const limit = 200;
    let total = 0;
    let loaded = 0;
    let loading = false;

    const tabs = [
        { key: 'all', label: 'Tất cả', icon: 'bi bi-collection-fill' },
        { key: 'pending', label: 'Chờ xác nhận', icon: 'bi bi-clock-history' },
        { key: 'processing', label: 'Chờ lấy hàng', icon: 'bi bi-box-seam' },
        { key: 'shipping', label: 'Chờ giao', icon: 'bi bi-truck' },
        { key: 'delivered', label: 'Đã giao', icon: 'bi bi-check-circle-fill' },
        { key: 'return_requested', label: 'Chờ duyệt trả', icon: 'bi bi-arrow-counterclockwise' },
        { key: 'returned', label: 'Đã trả', icon: 'bi bi-arrow-left-circle-fill' },
        { key: 'canceled', label: 'Đã huỷ', icon: 'bi bi-x-circle-fill' },
        { key: 'preorder', label: 'Đặt trước', icon: 'bi bi-clock-history' }
    ];

    const numberFormatter = new Intl.NumberFormat('vi-VN');
    const summaryCounts = tabs.reduce((acc, tab) => { acc[tab.key] = '—'; return acc; }, { all: '—' });

    const tableBody = $('#ordersBody');
    const summaryGrid = $('#summaryGrid');
    const searchInput = $('#searchOrder');
    const statusFilter = $('#filterStatus');
    const startDateInput = $('#filterFrom');
    const endDateInput = $('#filterTo');
    const emptyState = $('#ordersEmpty');
    const loadMoreBtn = $('#loadMoreOrders');
    const ordersMeta = $('#ordersMeta');
    const selectedCount = $('#selectedCount');
    const deleteSelectedBtn = $('#btnDeleteSelected');
    const bulkActionsContainer = $('#bulkActionsContainer');
    const checkAllOrders = $('#checkAllOrders');
    const selectedIds = new Set();
    let dataTable = null;

    function esc(v){ return $('<div>').text(v ?? '').html(); }
    function formatCount(val){
        if(typeof val === 'number' && Number.isFinite(val)) return numberFormatter.format(val);
        if(typeof val === 'string' && val.trim() !== '') return val;
        return '—';
    }

    function updateSummaryCounts(obj){
        if(!obj) return;
        Object.entries(obj).forEach(([key, val]) => {
            if(summaryCounts.hasOwnProperty(key)) {
                summaryCounts[key] = formatCount(val);
            }
        });
    }
    // Tổng số lượng đơn hàng theo từng trạng thái, sẽ được gọi khi load trang và khi thay đổi bộ lọc để cập nhật lại số lượng tương ứng
    function renderSummary(){
        const html = tabs.map(tab => {
            const isActive = tab.key === currentTab;
            const val = summaryCounts[tab.key] ?? '—';
            const icon = tab.icon || 'bi bi-collection-fill';
            return `
                <div class="summary-card ${isActive ? 'active' : ''}" data-status="${esc(tab.key)}">
                    <div class="d-flex flex-column">
                        <span>${esc(tab.label)}</span>
                        <strong class="mt-1">${val}</strong>
                    </div>
                    <div class="summary-icon">
                        <i class="${icon}"></i>
                    </div>
                </div>
            `;
        }).join('');
        summaryGrid.html(html);
    }
    // Hàm này sẽ được gọi khi người dùng chọn một tab trạng thái để xem các đơn hàng tương ứng
    function setTab(key){
        currentTab = key;
        statusFilter.val(key);
        page = 1;
        total = 0;
        loaded = 0;
        emptyState.hide();
        tableBody.empty();
        loadMoreBtn.removeClass('show').prop('disabled', false).text('Xem thêm');
        renderSummary();
        fetchOrders(true);
    }
    // Cập nhật trạng thái hiển thị và nội dung của nút "Xem thêm"
    function setLoadMoreVisible(){
        const canMore = loaded < total;
        loadMoreBtn.toggleClass('show', canMore);
        if(!canMore) loadMoreBtn.prop('disabled', false).text('Xem thêm');
    }
    //  Trạng thái đơn hàng
    function statusPill(statusKey, label){
        const st = String(statusKey || 'pending').toLowerCase();
        const allow = ['pending','processing','shipping','delivered','return_requested','returned','canceled'];
        const cls = allow.includes(st) ? st : 'pending';
        return `<span class="status-pill status-${cls}">${esc(label || st)}</span>`;
    }
    // Trạng thái thanh toán
    function paymentStatusBadgeAdmin(row){
        const key = String(row?.payment_status || '').toLowerCase().trim();
        const label = String(row?.payment_status_label || row?.payment_status || '').trim();
        if (!key && !label) return '<span class="text-muted small">—</span>';
        let cls = 'bg-light text-dark border';
        if (key === 'paid') cls = 'bg-success';
        else if (key === 'pending') cls = 'bg-warning text-dark';
        else if (key === 'failed') cls = 'bg-danger';
        else if (key === 'expired') cls = 'bg-secondary';
        else if (key === 'refunded') cls = 'bg-info text-dark';
        else if (key === 'refund_pending') cls = 'bg-warning text-dark border border-danger';
        const displayLabel = key === 'refund_pending' ? (label || 'Cần hoàn tiền') : (label || key);
        return `<span class="badge ${cls}" style="font-size: 0.6rem;" title="${key === 'refund_pending' ? 'Đơn cần hoàn tiền — admin xử lý trên cổng thanh toán' : ''}">${esc(displayLabel)}</span>`;
    }

    function renderOrderRow(r){
        const oid = esc(r.order_id);
        const statusKey = String(r.status || 'pending');
        const statusLabel = String(r.status_label || statusKey);
        const payMethodLabel = String(r.payment_method_label || r.payment_method || r.payment_gateway || '').trim();
        const payMethodKey = String(r.payment_method || r.payment_gateway || '').toLowerCase();
        const created = esc(r.created_fmt || '');
        const totalFmt = esc(r.total_amount_fmt || '0 đ');
        const customer = esc(r.user_name || 'Khách hàng');
        const phone = esc(r.phone || '');
        const email = esc(r.email || '');
        const address = esc(r.address || '');
        const isGuest = !r.user_id || String(r.user_id) === '0';
        
        let itemsArr = parseProductsJson(r.products_json);
        if (!itemsArr.length) itemsArr = parseProductString(r.product);
        const totalItems = itemsArr.length;
        // Đơn có hàng đặt trước?
        const hasPreorder = itemsArr.some(it => Number(it.is_preorder) === 1 || it.is_preorder === true);
        // Tổng SỐ LƯỢNG sản phẩm (cộng qty mọi dòng, gồm cả quà), không phải số dòng
        const totalQty = itemsArr.reduce((s, it) => s + (Math.max(1, Number(it.qty) || 1)), 0);
        const itemsCount = totalQty > 0 ? totalQty : Number(r.items_count || totalItems || 0);
        
        // Chỉ hiển thị 1 sản phẩm đầu tiên (click chi tiết để xem đầy đủ)
        const firstItem = itemsArr[0];
        const moreCount = Math.max(0, itemsArr.length - 1);
        const productHtml = firstItem ? `
            <div class="d-flex flex-column gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-light rounded p-1 flex-shrink-0" style="width: 36px; height: 36px;">
                        <img src="${normalizeImgUrl(firstItem.image_url)}" class="w-100 h-100 object-fit-contain" onerror="this.src=FALLBACK_IMG">
                    </div>
                    <div style="min-width: 0;">
                        <div class="fw-semibold text-truncate" style="max-width: 190px; font-size: 0.72rem; line-height: 1.25;" title="${esc(firstItem.name)}">${esc(firstItem.name)}${(Number(firstItem.is_preorder) === 1 || firstItem.is_preorder === true) ? ' <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle align-middle" style="font-size:0.55rem; padding:1px 4px;">Đặt trước</span>' : ''}</div>
                        <div class="text-muted text-truncate" style="max-width: 190px; font-size: 0.64rem;">${firstItem.variant ? esc(firstItem.variant) + ' · ' : ''}x${Math.max(1, Number(firstItem.qty) || 1)}</div>
                    </div>
                </div>
                ${moreCount > 0 ? `<div class="text-muted" style="font-size: 0.64rem;">+${moreCount} sản phẩm khác</div>` : ''}
            </div>
        ` : '<span class="text-muted italic smaller">Không có dữ liệu</span>';

        const quickActions = [];
        if (statusKey === 'pending') {
            quickActions.push(`<button class="quick-action-btn text-success" title="Xác nhận" onclick="updateOrderStatus('${oid}', 'processing')"><i class="bi bi-check-lg"></i></button>`);
        } else if (statusKey === 'processing') {
            quickActions.push(`<button class="quick-action-btn text-primary" title="Giao hàng" onclick="updateOrderStatus('${oid}', 'shipping')"><i class="bi bi-truck"></i></button>`);
        } else if (statusKey === 'shipping') {
            quickActions.push(`<button class="quick-action-btn text-info" title="Hoàn tất" onclick="updateOrderStatus('${oid}', 'delivered')"><i class="bi bi-box-seam"></i></button>`);
        }

        const checked = selectedIds.has(oid) ? 'checked' : '';
        return `
            <tr>
                <td class="ps-4">
                    <div class="form-check mb-0">
                        <input class="form-check-input order-check" type="checkbox" data-id="${oid}" ${checked}>
                    </div>
                </td>
                <td>
                    <a href="<?= h($baseUrl) ?>/admin/order-change?order_id=${oid}" class="order-id-link">#${oid || '---'}</a>
                    ${hasPreorder ? '<div class="mt-1"><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:0.6rem;"><i class="bi bi-clock-history me-1"></i>Đặt trước</span></div>' : ''}
                    <div class="text-muted smaller mt-1" style="font-size: 0.7rem;">${created}</div>
                </td>
                <td>
                    <div class="fw-bold text-dark mb-0">${customer}</div>
                    <div class="text-muted smaller" style="font-size: 0.7rem;">${phone || 'N/A'}</div>
                </td>
                <td>${productHtml}</td>
                <td>
                    <div class="small fw-medium mb-1">${payMethodLabel || 'COD'}</div>
                    ${paymentStatusBadgeAdmin(r)}
                </td>
                <td>
                    <div class="fw-bold text-primary">${totalFmt}</div>
                    <div class="text-muted smaller" style="font-size: 0.65rem;">${itemsCount} sản phẩm</div>
                </td>
                <td>${statusPill(statusKey, statusLabel)}</td>
                <td class="text-end pe-4">
                    <div class="d-flex justify-content-end gap-1">
                        ${quickActions.join('')}
                        <div class="dropdown d-inline-block">
                            <button class="quick-action-btn" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3" style="font-size: 0.8rem;">
                                <li><a class="dropdown-item py-2" href="<?= h($baseUrl) ?>/admin/order-change?order_id=${oid}"><i class="bi bi-eye me-2"></i>Chi tiết</a></li>
                                <li><button class="dropdown-item py-2" type="button" data-act="print" data-id="${oid}"><i class="bi bi-printer me-2"></i>In hóa đơn</button></li>
                                ${payMethodKey === 'vnpay' ? `<li><button class="dropdown-item py-2" type="button" data-act="ipn" data-id="${oid}"><i class="bi bi-clock-history me-2"></i>Lịch sử IPN</button></li>` : ''}
                                ${String(r.payment_status || '').toLowerCase() === 'refund_pending' && ['momo','zalopay'].includes(payMethodKey) ? `<li><button class="dropdown-item py-2 text-success fw-semibold" type="button" data-act="refund_auto" data-id="${oid}" data-gateway="${payMethodKey}"><i class="bi bi-arrow-counterclockwise me-2"></i>Hoàn tiền tự động (${payMethodKey.toUpperCase()})</button></li>` : ''}
                                ${String(r.payment_status || '').toLowerCase() === 'refund_pending' ? `<li><button class="dropdown-item py-2 text-warning fw-semibold" type="button" data-act="mark_refunded" data-id="${oid}"><i class="bi bi-cash-coin me-2"></i>Đã hoàn tiền tay</button></li>` : ''}
                                <li><button class="dropdown-item py-2" type="button" data-act="edit_address" data-id="${oid}"><i class="bi bi-geo-alt me-2"></i>Đổi địa chỉ</button></li>
                                <li><hr class="dropdown-divider opacity-50"></li>
                                <li><button class="dropdown-item py-2 text-danger" type="button" data-act="delete" data-id="${oid}"><i class="bi bi-trash me-2"></i>Xóa đơn</button></li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }

    window.updateOrderStatus = function(orderId, status) {
        let payload = { action: 'update_status', id: orderId, status: status };
        
        if (status === 'shipping') {
            const carrier = prompt('Nhập tên đơn vị vận chuyển (ví dụ: GHN, Viettel Post, ...):', 'GHN');
            if (carrier === null) return; // Cancelled
            const tracking = prompt('Nhập mã vận đơn (tracking number):');
            if (tracking === null) return; // Cancelled
            payload.shipping_carrier = carrier;
            payload.shipping_tracking = tracking;
        }

        if (!confirm(`Cập nhật đơn #${orderId} sang trạng thái mới?`)) return;
        
        $.post(API_URL, payload, function(res){
            if (res && res.ok) {
                toastr.success('Đã cập nhật trạng thái');
                fetchOrders(false);
            } else {
                toastr.error(res.msg || 'Không thể cập nhật');
            }
        }, 'json');
    };

    function renderRows(rows, reset){
        const list = Array.isArray(rows) ? rows : [];
        if (reset) {
            selectedIds.clear();
            checkAllOrders && checkAllOrders.prop('checked', false);
            updateSelectedUi();
        }
        if (!list.length) {
            emptyState.show();
            if (dataTable) dataTable.clear().draw();
            else tableBody.empty();
            return;
        }
        emptyState.hide();
        const html = list.map(renderOrderRow).join('');
        if (dataTable) {
            dataTable.clear().destroy();
            dataTable = null;
        }
        tableBody.html(html);
        dataTable = $('#ordersTable').DataTable({
            paging: true,
            searching: false,
            ordering: false,
            lengthChange: false,
            pageLength: 10,
            dom: 'rt<"d-flex justify-content-between align-items-center p-4 border-top"ip>',
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json'
            }
        });
    }

    function updateSelectedUi(){
        const count = selectedIds.size;
        selectedCount.text(`${count} đã chọn`);
        deleteSelectedBtn.prop('disabled', count === 0);
        bulkActionsContainer.toggleClass('d-none', count === 0);
    }

    const ipnModal = new bootstrap.Modal(document.getElementById('ipnModal'));
    const ipnTableBody = $('#ipnTable tbody');
    const ipnEmpty = $('#ipnEmpty');

    function fetchOrderDetailAdmin(orderId){
        if (!orderId) return $.Deferred().resolve(null).promise();
        return $.get(API_URL, { ajax: 'order_detail', order_id: orderId })
            .then(res => {
                if (!res || !res.ok || !res.order) return null;
                return res;
            })
            .catch(() => null);
    }

    function parseProductsForPrint(order){
        const items = [];
        const rawJson = String(order?.products_json || '').trim();
        if (rawJson) {
            try {
                const decoded = JSON.parse(rawJson);
                if (Array.isArray(decoded)) {
                    decoded.forEach((it) => {
                        if (!it) return;
                        const name = String(it.name || it.product || '').trim();
                        if (!name) return;
                        const qty = parseInt(it.qty ?? it.quantity ?? 1, 10) || 1;
                        const price = String(it.price_fmt || it.price || '');
                        const total = String(it.line_total_fmt || it.line_total || it.total || '');
                        items.push({ name, qty, price, total, variant: String(it.variant || '') });
                    });
                }
            } catch (e) {}
        }
        if (!items.length) {
            const raw = String(order?.product || '').trim();
            if (!raw) return items;
            const parts = raw.split(/\r\n|\r|\n|\||,/).map(s => s.trim()).filter(Boolean);
            parts.forEach((p) => items.push({ name: p, qty: 1, price: '', total: '', variant: '' }));
        }
        return items;
    }

    function buildPrintHtmlAdmin(payload){
        const order = payload?.order || {};
        const shippingLive = payload?.shipping_live || {};
        const items = parseProductsForPrint(order);
        const rows = items.map((it) => {
            const variant = it.variant ? ` <span style="color:#6b7280">(${esc(it.variant)})</span>` : '';
            return `<tr>
                <td>${esc(it.name)}${variant}</td>
                <td class="col-qty">${esc(it.qty)}</td>
                <td class="col-price">${esc(it.price)}</td>
                <td class="col-total">${esc(it.total)}</td>
            </tr>`;
        }).join('');

        const orderId = esc(order.order_id || '');
        const created = esc(order.created_fmt || '');
        const payment = esc(order.payment_method_label || '');
        const payStatus = esc(order.payment_status_label || '');
        const total = esc(order.total_amount_fmt || '');
        const shippingCarrier = esc(shippingLive?.carrier_name || order.shipping_carrier || '');
        const shippingTracking = esc(shippingLive?.tracking_code || order.shipping_tracking || '');

        return `<!doctype html>
            <html lang="vi">
            <head>
                <meta charset="utf-8">
                <title>Hóa đơn bán hàng #${orderId}</title>
                <style>
                    *{box-sizing:border-box;}
                    body{font-family:Arial,Helvetica,sans-serif;color:#111827;margin:26px;font-size:12px;line-height:1.55;}
                    h1{font-size:18px;margin:0;letter-spacing:.02em;}
                    .subtitle{font-size:12px;color:#374151;margin-top:2px;}
                    .meta{color:#6b7280;font-size:12px;margin-top:6px;}
                    .header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:12px;}
                    .company{font-weight:700;font-size:13px;}
                    .company small{display:block;font-weight:400;color:#6b7280;}
                    .info{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px;}
                    .box{border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;}
                    .box h3{font-size:12px;margin:0 0 6px;color:#111827;text-transform:uppercase;letter-spacing:.04em;}
                    .box p{margin:0;color:#374151;}
                    .box p + p{margin-top:3px;}
                    table{width:100%;border-collapse:collapse;margin-top:6px;table-layout:fixed;}
                    th,td{border:1px solid #e5e7eb;padding:6px 8px;vertical-align:top;word-break:break-word;}
                    th{text-align:left;background:#f8fafc;color:#374151;font-weight:700;text-transform:uppercase;font-size:11px;}
                    .col-qty{width:56px;text-align:center;}
                    .col-price{width:110px;text-align:right;}
                    .col-total{width:120px;text-align:right;}
                    .totals{margin-top:10px;display:flex;justify-content:flex-end;}
                    .totals table{width:260px;}
                    .totals td{border:none;padding:3px 0;}
                    .totals tr td:last-child{text-align:right;font-weight:700;}
                    .note{margin-top:10px;font-size:11px;color:#6b7280;}
                </style>
            </head>
            <body>
                <div class="header">
                    <div>
                        <div class="company">PAINTMORE</div>
                        <small>Hóa đơn bán hàng</small>
                    </div>
                    <div style="text-align:right;">
                        <h1>HÓA ĐƠN</h1>
                        <div class="subtitle">Mã đơn: ${orderId}</div>
                        <div class="meta">Ngày lập: ${created}</div>
                    </div>
                </div>
                <div class="info">
                    <div class="box">
                        <h3>Thông tin khách hàng</h3>
                        <p>Họ tên: ${esc(order.user_name || '')}</p>
                        <p>Điện thoại: ${esc(order.phone || '')}</p>
                        <p>Email: ${esc(order.email || '')}</p>
                        <p>Địa chỉ: ${esc(order.address || '')}</p>
                    </div>
                    <div class="box">
                        <h3>Thanh toán & vận chuyển</h3>
                        <p>Phương thức: ${payment}</p>
                        <p>Trạng thái: ${payStatus}</p>
                        <p>Đơn vị vận chuyển: ${shippingCarrier}</p>
                        <p>Mã vận đơn: ${shippingTracking}</p>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Tên hàng hóa</th>
                            <th class="col-qty">Số lượng</th>
                            <th class="col-price">Đơn giá</th>
                            <th class="col-total">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows || '<tr><td colspan="4">Không có sản phẩm</td></tr>'}
                    </tbody>
                </table>
                <div class="totals">
                    <table>
                        <tr><td>Tổng thanh toán</td><td>${total}</td></tr>
                    </table>
                </div>
                <div class="note">Lưu ý: Hóa đơn điện tử này có giá trị đối chiếu khi nhận hàng.</div>
            </body>
            </html>`;
    }

    function openPrintWindow(html, title){
        const w = window.open('', '_blank');
        if (!w) return;
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.document.title = title || 'Print';
        w.focus();
        w.print();
    }


    function loadIpnLogs(orderId){
        $('#ipnOrderTitle').text('#' + orderId);
        ipnTableBody.empty();
        ipnEmpty.hide();
        $.get(API_URL, { ajax: 'ipn_logs', order_id: orderId }, function(res){
            if (!res || !res.ok) {
                ipnEmpty.text('Không thể tải log.').show();
                return;
            }
            const list = Array.isArray(res.data) ? res.data : [];
            if (!list.length) {
                ipnEmpty.show();
                return;
            }
            const rows = list.map(item => {
                const valid = Number(item.is_valid) === 1 ? 'Có' : 'Không';
                return `
                    <tr>
                        <td>${esc(item.created_at || '')}</td>
                        <td>${esc(item.response_code || '')}</td>
                        <td>${esc(item.transaction_status || '')}</td>
                        <td>${esc(item.amount || '')}</td>
                        <td>${esc(item.bank_code || '')}</td>
                        <td>${esc(valid)}</td>
                    </tr>
                `;
            }).join('');
            ipnTableBody.html(rows);
        });
    }

    function fetchOrders(reset){
        if(loading) return;
        loading = true;
        ordersMeta.text('Đang tải dữ liệu...');
        loadMoreBtn.prop('disabled', true).text('Đang tải...');

        $.get(API_URL, {
            ajax: 'orders_list',
            tab: currentTab,
            q: searchInput.val(),
            startDate: startDateInput.val(),
            endDate: endDateInput.val(),
            page: 1,
            limit
        }, function(res){
            if(!res || !res.ok){
                toastr.error(res?.msg || 'Không tải được dữ liệu');
                return;
            }
            total = parseInt(res.total || 0, 10) || 0;
            const summaryCandidate = res.summary || res.summary_counts || res.count_by_status || res.by_status || null;
            updateSummaryCounts(summaryCandidate);
            summaryCounts[currentTab] = formatCount(total);
            renderSummary();

            const data = Array.isArray(res.data) ? res.data : [];
            renderRows(data, reset);
            const shown = data.length;
            ordersMeta.text(total ? `Đang hiển thị ${shown}/${total} đơn` : 'Không có dữ liệu cho bộ lọc hiện tại');
        }, 'json').fail(function(){
            toastr.error('Lỗi kết nối server');
        }).always(function(){
            loading = false;
            loadMoreBtn.prop('disabled', false).text('Xem thêm');
        });
    }

    function reloadOrders(){
        setTab(currentTab);
    }

    let productsCache = [];
    function loadProductsForPicker() {
        $.get(API_URL + '?get_products=1', function(res){
            if(!res || !res.ok) return;
            productsCache = res.data || [];
            const $sel = $('#productSelect');
            const current = $sel.val();
            $sel.empty().append('<option value="">-- Chọn sản phẩm --</option>');
            productsCache.forEach(p => {
                const id = Number(p?.id || 0);
                const name = (p && p.name) ? String(p.name) : '';
                if(!id || !name) return;
                $sel.append(`<option value="${esc(id)}">${esc(name)}</option>`);
            });
            if(current) $sel.val(current);
        }, 'json');
    }

    const productMetaCache = {};

    function normalizeImgUrl(raw){
        const url = String(raw || '').trim();
        if (!url) return FALLBACK_IMG;
        if (/^https?:\/\//i.test(url) || url.startsWith('//')) return url;
        // Ưu tiên media domain (toMediaUrl từ head.php) cho file trong thư mục upload.
        if (typeof window.toMediaUrl === 'function') {
            const mapped = window.toMediaUrl(url.replace(/^\.\//, ''));
            if (mapped) return mapped;
        }
        const base = String(BASE_URL || '').replace(/\/$/, '');
        if (url.startsWith('/')) return base + url;
        return base + '/' + url.replace(/^\.\//, '').replace(/^\//, '');
    }

    function fetchProductMeta(ids){
        const uniq = Array.from(new Set((ids || []).map(v => Number(v || 0)).filter(v => v > 0)));
        const missing = uniq.filter(id => !productMetaCache[String(id)]);
        if (!missing.length) return $.Deferred().resolve().promise();
        return $.get(API_URL, { ajax: 'product_meta', ids: missing.join(',') }, function(res){
            if (res && res.ok && res.data) {
                Object.keys(res.data).forEach(k => {
                    productMetaCache[String(k)] = res.data[k] || {};
                });
            }
        }, 'json');
    }

    const orderItems = [];
    function parseProductsJson(raw) {
        try {
            const arr = (typeof raw === 'string') ? JSON.parse(raw) : raw;
            if (!Array.isArray(arr)) return [];
            return arr
                .map(it => {
                    if (!it) return null;
                    const name = String(it.name || it.product || '').trim();
                    if (!name) return null;
                    const pid = Number(it.product_id || it.id || 0);
                    const qtyNum = parseInt(it.qty ?? it.quantity ?? 1, 10);
                    const qty = (Number.isFinite(qtyNum) && qtyNum > 0) ? qtyNum : 1;
                    const variant = String(it.variant || '').trim();
                    const imageUrl = String(it.image_url || it.thumb || it.image || '').trim();
                    return {
                        product_id: pid,
                        name,
                        qty,
                        variant,
                        image_url: imageUrl,
                        is_preorder: (Number(it.is_preorder) === 1 || it.is_preorder === true) ? 1 : 0,
                    };
                })
                .filter(Boolean);
        } catch (e) {
            return [];
        }
    }
    function parseProductString(str) {
        const items = [];
        const parts = String(str || '').split(/\r\n|\r|\n|\||,/).map(s => s.trim()).filter(Boolean);
        parts.forEach(p => {
            const m = p.match(/^(.*?)(?:\s*[xX]\s*(\d+))?$/);
            const name = (m && m[1]) ? m[1].trim() : p;
            const qty = (m && m[2]) ? parseInt(m[2], 10) : 1;
            if(name) items.push({name, qty: (Number.isFinite(qty) && qty>0) ? qty : 1});
        });
        return items;
    }

    function syncLegacyFieldsFromItems() {
        const productJoined = orderItems.map(it => `${it.name} x${it.qty}`).join(' | ');
        $('#e_product').val(productJoined);
        try {
            $('#e_products_json').val(JSON.stringify(orderItems));
        } catch (e) {
            $('#e_products_json').val('[]');
        }

    }

    function renderProductList() {
        const $list = $('#productList');
        if(!orderItems.length) {
            $list.html('<div class="text-muted small">Chưa chọn sản phẩm nào.</div>');
            syncLegacyFieldsFromItems();
            return;
        }
        const display = orderItems.map((it, idx) => ({...it, _idx: idx}));
        display.sort((a, b) => {
            const va = String(a.variant || '').trim().toLowerCase();
            const vb = String(b.variant || '').trim().toLowerCase();
            if (va !== vb) {
                if (!va) return 1;
                if (!vb) return -1;
                return va.localeCompare(vb, 'vi');
            }
            const na = String(a.name || '').trim().toLowerCase();
            const nb = String(b.name || '').trim().toLowerCase();
            return na.localeCompare(nb, 'vi');
        });

        let currentGroup = null;
        const rows = display.map((it) => {
            const pid = Number(it.product_id || 0);
            const meta = pid > 0 ? (productMetaCache[String(pid)] || {}) : {};
            const img = normalizeImgUrl(it.image_url || meta.image_url || '');
            const name = String(it.name || meta.product_name || 'Sản phẩm').trim();
            const variant = String(it.variant || '').trim();
            const groupLabel = variant ? variant : 'Mặc định';
            const groupHtml = (currentGroup !== groupLabel)
                ? (() => { currentGroup = groupLabel; return `<div class="small text-muted fw-semibold mt-2 mb-1">Phân loại: ${esc(groupLabel)}</div>`; })()
                : '';
            return `
                ${groupHtml}
                <div class="d-flex align-items-start justify-content-between border rounded px-2 py-2 mb-1 bg-white">
                    <div class="d-flex align-items-start gap-2 min-w-0">
                        <img src="${esc(img)}" alt="thumb" class="rounded border bg-light flex-shrink-0" style="width:42px;height:42px;object-fit:cover;">
                        <div class="small min-w-0" style="word-break:break-word;">
                            <div class="fw-semibold text-primary">${esc(name)}</div>
                            <div class="text-muted">x${esc(it.qty)}</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-rm="${esc(it._idx)}"><i class="bi bi-x-lg"></i></button>
                </div>
            `;
        }).join('');
        $list.html(rows);
        syncLegacyFieldsFromItems();
    }

    function renderProductListWithMeta(){
        const ids = orderItems.map(it => Number(it.product_id || 0)).filter(v => v > 0);
        fetchProductMeta(ids).always(function(){
            renderProductList();
        });
    }

    $('#btnAddProduct').on('click', function(){
        const selVal = String($('#productSelect').val() || '').trim();
        const qty = parseInt($('#productQty').val() || '1', 10);
        if(!selVal) return toastr.warning('Vui lòng chọn sản phẩm');
        const q = (Number.isFinite(qty) && qty > 0) ? qty : 1;
        const pid = Number(selVal || 0);
        const p = pid > 0 ? (productsCache.find(x => Number(x?.id || 0) === pid) || null) : null;
        const name = String(p?.name || '').trim() || selVal;
        const imageUrl = String(p?.image_url || '').trim();
        const existing = pid > 0
            ? orderItems.find(it => Number(it.product_id || 0) === pid && String(it.variant || '') === '')
            : orderItems.find(it => it.name === name && String(it.variant || '') === '');
        if(existing) existing.qty += q;
        else orderItems.push({ product_id: pid, name, qty: q, variant: '', image_url: imageUrl });
        renderProductListWithMeta();
    });

    $('#productList').on('click', 'button[data-rm]', function(){
        const idx = parseInt($(this).attr('data-rm'), 10);
        if(Number.isFinite(idx) && idx >= 0) {
            orderItems.splice(idx, 1);
            renderProductListWithMeta();
        }
    });

    let searchTimer = null;
    searchInput.on('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => setTab(currentTab), 350);
    });
    startDateInput.add(endDateInput).on('change', function(){ setTab(currentTab); });
    statusFilter.on('change', function(){ setTab(String($(this).val() || 'all')); });
    summaryGrid.on('click', '.summary-card[data-status]', function(){
        const key = String($(this).data('status') || 'all');
        setTab(key);
    });
    loadMoreBtn.on('click', function(){
        if(loaded >= total) return;
        page += 1;
        fetchOrders(false);
    });

    $('#btnCreateOrder').on('click', () => openModal());
    $('#btnScrollTop').on('click', function(){
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    tableBody.on('click', 'button[data-act]', function(){
        const act = String($(this).attr('data-act') || '');
        const oid = String($(this).attr('data-id') || '');
        if(!oid) return;
        if(act === 'print') {
            fetchOrderDetailAdmin(oid).then(payload => {
                if (!payload) {
                    toastr.error('Không tải được chi tiết đơn');
                    return;
                }
                const html = buildPrintHtmlAdmin(payload);
                openPrintWindow(html, 'Đơn hàng #' + oid);
            });
            return;
        }
        if(act === 'ipn') {
            loadIpnLogs(oid);
            ipnModal.show();
            return;
        }
        if(act === 'delete') {
            if(!confirm('Bạn có chắc chắn muốn xóa đơn #' + oid + '?')) return;
            $.post(API_URL, { action: 'delete_one', id: oid }, function(res){
                if(res && res.ok){ toastr.success('Đã xóa đơn'); reloadOrders(); }
                else toastr.error(res?.msg || 'Không thể xóa');
            }, 'json').fail(function(){ toastr.error('Lỗi kết nối server'); });
            return;
        }
        if(act === 'refund_auto') {
            const gateway = String($(this).attr('data-gateway') || '').toUpperCase();
            if (!confirm(`Gọi API ${gateway} hoàn tiền tự động cho đơn #${oid}?\nTiền sẽ được hoàn về ví/tài khoản khách trong 1-3 ngày làm việc.`)) return;
            const $btn = $(this).prop('disabled', true);
            toastr.info('Đang gọi ' + gateway + '...');
            $.post(API_URL, { action: 'admin_refund_via_gateway', order_id: oid }, function(res){
                if (res && res.ok) {
                    toastr.success(res.msg || 'Refund thành công qua ' + gateway);
                    reloadOrders();
                } else if (res && res.status === 'pending') {
                    toastr.warning(res.msg + ' — đơn ở trạng thái pending, cần query lại sau.');
                } else {
                    toastr.error(res?.msg || 'Refund thất bại');
                    $btn.prop('disabled', false);
                }
            }, 'json').fail(function(){
                toastr.error('Lỗi kết nối server');
                $btn.prop('disabled', false);
            });
            return;
        }
        if(act === 'mark_refunded') {
            const note = prompt('Xác nhận đã hoàn tiền cho đơn #' + oid + ' qua cổng thanh toán?\nGhi chú (tuỳ chọn, ví dụ: mã giao dịch refund):', '');
            if (note === null) return;
            $.post(API_URL, { action: 'admin_mark_refunded', order_id: oid, note: note }, function(res){
                if(res && res.ok){ toastr.success(res.msg || 'Đã ghi nhận hoàn tiền'); reloadOrders(); }
                else toastr.error(res?.msg || 'Không thể ghi nhận hoàn tiền');
            }, 'json').fail(function(){ toastr.error('Lỗi kết nối server'); });
            return;
        }
        if(act === 'edit_address') {
            fetchOrderDetailAdmin(oid).then(payload => {
                if (!payload) { toastr.error('Không tải được chi tiết đơn'); return; }
                const order = payload.order || payload;
                // Parse shipping_snapshot_json để lấy province_id, district_id, ward_code
                let snap = {};
                try { snap = JSON.parse(order.shipping_snapshot_json || '{}') || {}; } catch(e) {}
                const merged = {
                    recipient_name: snap.recipient_name || order.user_name || '',
                    contact_phone:  snap.contact_phone  || order.phone     || '',
                    street:         snap.street      || '',
                    ward:           snap.ward        || '',
                    ward_code:      snap.ward_code   || '',
                    district:       snap.district    || '',
                    district_id:    snap.district_id || 0,
                    province:       snap.province    || '',
                    province_id:    snap.province_id || 0,
                };
                $('.btn-admin-edit-address').data('order-id', oid).data('snap', merged).trigger('click');
            });
            return;
        }
    });
    // Sao chép mã đơn hàng (giống giao diện user)
    tableBody.on('click', '.copy-order-id-btn', function(e){
        e.preventDefault();
        const $btn = $(this);
        const $orderId = $btn.closest('.fw-semibold').find('.order-id-text');
        const val = $orderId.text().trim();
        if (!val || val === '---') return;
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            window.prompt('Sao chép mã đơn:', val);
            return;
        }
        navigator.clipboard.writeText(val).then(function(){
            $btn.html('<i class="bi bi-clipboard-check text-success"></i>');
            setTimeout(function(){ $btn.html('<i class="bi bi-clipboard"></i>'); }, 1200);
            toastr.success('Đã sao chép mã đơn: ' + val);
        });
    });

    $('#refreshOrders').on('click', function(){ reloadOrders(); });

    summaryGrid.on('click', '.status-card', function(){
        const status = $(this).data('status');
        if(status) setTab(status);
    });

    tableBody.on('change', '.order-check', function(){
        const oid = String($(this).attr('data-id') || '');
        if(!oid) return;
        if($(this).is(':checked')) selectedIds.add(oid);
        else selectedIds.delete(oid);
        const visibleChecks = tableBody.find('.order-check');
        const allChecked = visibleChecks.length && visibleChecks.filter(':checked').length === visibleChecks.length;
        checkAllOrders.prop('checked', allChecked);
        updateSelectedUi();
    });

    checkAllOrders.on('change', function(){
        const checked = $(this).is(':checked');
        tableBody.find('.order-check').each(function(){
            $(this).prop('checked', checked).trigger('change');
        });
    });

    deleteSelectedBtn.on('click', function(){
        if(selectedIds.size === 0) return;
        if(!confirm('Bạn có chắc chắn muốn xóa các đơn đã chọn?')) return;
        const ids = Array.from(selectedIds);
        $.post(API_URL, { action: 'delete_multi', ids: ids }, function(res){
            if(res && res.ok){ toastr.success('Đã xóa các đơn đã chọn'); reloadOrders(); }
            else toastr.error(res?.msg || 'Không thể xóa');
        }, 'json').fail(function(){ toastr.error('Lỗi kết nối server'); });
    });

    renderSummary();
    setTab('all');
    loadProductsForPicker();

    window.openModal = (r = null) => {
        $('#dataForm')[0].reset();

        // reset product picker
        orderItems.splice(0, orderItems.length);
        $('#productQty').val(1);
        $('#productSelect').val('');
        $('#e_product').val('');
        $('#e_products_json').val('[]');
        $('#productList').empty();

        if(r){
            $('#modalTitle').text('Cập Nhật: ' + r.order_id);
            $('#e_order_id').val(r.order_id).prop('readonly', true).addClass('bg-light'); 
            $('#e_user_name').val(r.user_name); $('#e_phone').val(r.phone); $('#e_email').val(r.email);
            $('#e_address').val(r.address); $('#e_product').val(r.product);
            $('#e_note').val(r.note); $('#e_status').val(r.status);

            // populate picker list from SQL (prefer products_json, fallback to legacy product string)
            const itemsFromJson = parseProductsJson(r.products_json);
            const itemsFromLegacy = itemsFromJson.length ? [] : parseProductString(r.product);
            itemsFromJson.concat(itemsFromLegacy).forEach(it => orderItems.push(it));
            renderProductListWithMeta();
        } else {
            $('#modalTitle').text('Tạo Đơn Mới');
            $('#e_order_id').val('').prop('readonly', false).removeClass('bg-light');
            renderProductListWithMeta();
        }
        new bootstrap.Modal('#formModal').show();
    }

    $('#dataForm').submit(function(e){
        e.preventDefault();
        $.post(API_URL, $(this).serialize() + '&action=save', function(res){
            if(res.ok){ toastr.success(res.msg); $('#formModal').modal('hide'); reloadOrders(); } 
            else { toastr.error(res.msg); }
        }, 'json').fail(function(){ toastr.error('Lỗi kết nối server'); });
    });


    // ===== ADMIN: ĐỔI ĐỊA CHỈ NHẬN HÀNG =====
    const REGION_API = '<?= h($baseUrl) ?>/main/account/region-session.php';

    $(document).on('click', '.btn-admin-edit-address', function() {
        const orderId = $(this).data('order-id');
        const snap    = $(this).data('snap') || {};
        $('#adminEaOrderId').val(orderId);
        $('#adminEaName').val(snap.recipient_name || snap.user_name || '');
        $('#adminEaPhone').val(snap.contact_phone || snap.phone || '');
        $('#adminEaStreet').val(snap.street || '');
        $('#adminEaDistrict').prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
        $('#adminEaWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');

        const savedProvinceId = String(snap.province_id || '');
        const savedDistrictId = String(snap.district_id || '');
        const savedWardCode   = String(snap.ward_code || '');

        $.get(REGION_API, { action: 'region_provinces' }, function(res) {
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            let opts = '<option value="">-- Chọn tỉnh/thành --</option>';
            rows.forEach(r => {
                const sel = String(r.ProvinceID) === savedProvinceId ? ' selected' : '';
                opts += `<option value="${r.ProvinceID}" data-name="${r.ProvinceName}"${sel}>${r.ProvinceName}</option>`;
            });
            $('#adminEaProvince').html(opts);

            if (!savedProvinceId || !savedDistrictId) return;
            $.get(REGION_API, { action: 'region_districts', province_id: savedProvinceId }, function(res2) {
                const rows2 = Array.isArray(res2?.rows) ? res2.rows : [];
                let opts2 = '<option value="">-- Chọn quận/huyện --</option>';
                rows2.forEach(r => {
                    const sel = String(r.DistrictID) === savedDistrictId ? ' selected' : '';
                    opts2 += `<option value="${r.DistrictID}" data-name="${r.DistrictName}"${sel}>${r.DistrictName}</option>`;
                });
                $('#adminEaDistrict').html(opts2).prop('disabled', false);

                if (!savedWardCode) return;
                $.get(REGION_API, { action: 'region_wards', district_id: savedDistrictId }, function(res3) {
                    const rows3 = Array.isArray(res3?.rows) ? res3.rows : [];
                    let opts3 = '<option value="">-- Chọn phường/xã --</option>';
                    rows3.forEach(r => {
                        const sel = String(r.WardCode) === savedWardCode ? ' selected' : '';
                        opts3 += `<option value="${r.WardCode}" data-name="${r.WardName}"${sel}>${r.WardName}</option>`;
                    });
                    $('#adminEaWard').html(opts3).prop('disabled', false);
                });
            });
        });

        $('#adminEditAddressModal').modal('show');
    });

    $('#adminEaProvince').on('change', function() {
        const pid = $(this).val();
        $('#adminEaDistrict').prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
        $('#adminEaWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
        if (!pid) return;
        $.get(REGION_API, { action: 'region_districts', province_id: pid }, function(res) {
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            let opts = '<option value="">-- Chọn quận/huyện --</option>';
            rows.forEach(r => { opts += `<option value="${r.DistrictID}" data-name="${r.DistrictName}">${r.DistrictName}</option>`; });
            $('#adminEaDistrict').html(opts).prop('disabled', false);
        });
    });

    $('#adminEaDistrict').on('change', function() {
        const did = $(this).val();
        $('#adminEaWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
        if (!did) return;
        $.get(REGION_API, { action: 'region_wards', district_id: did }, function(res) {
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            let opts = '<option value="">-- Chọn phường/xã --</option>';
            rows.forEach(r => { opts += `<option value="${r.WardCode}" data-name="${r.WardName}">${r.WardName}</option>`; });
            $('#adminEaWard').html(opts).prop('disabled', false);
        });
    });

    $('#btnAdminSaveAddress').on('click', function() {
        const orderId    = $('#adminEaOrderId').val();
        const name       = $('#adminEaName').val().trim();
        const phone      = $('#adminEaPhone').val().trim();
        const provinceId = $('#adminEaProvince').val();
        const province   = $('#adminEaProvince').find(':selected').data('name') || '';
        const districtId = $('#adminEaDistrict').val();
        const district   = $('#adminEaDistrict').find(':selected').data('name') || '';
        const wardCode   = $('#adminEaWard').val();
        const ward       = $('#adminEaWard').find(':selected').data('name') || '';
        const street     = $('#adminEaStreet').val().trim();

        if (!name)       { toastr.warning('Vui lòng nhập họ tên người nhận'); return; }
        if (!phone || phone.replace(/[^0-9]/g,'').length < 9) { toastr.warning('Số điện thoại không hợp lệ'); return; }
        if (!provinceId) { toastr.warning('Vui lòng chọn Tỉnh/Thành phố'); return; }
        if (!districtId) { toastr.warning('Vui lòng chọn Quận/Huyện'); return; }
        if (!wardCode)   { toastr.warning('Vui lòng chọn Phường/Xã'); return; }
        if (!street)     { toastr.warning('Vui lòng nhập địa chỉ chi tiết'); return; }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...');

        $.post(API_URL, {
            action: 'admin_update_address',
            order_id: orderId,
            recipient_name: name,
            contact_phone: phone,
            province_id: provinceId,
            province: province,
            district_id: districtId,
            district: district,
            ward_code: wardCode,
            ward: ward,
            street: street
        }, 'json').done(function(res) {
            if (res?.ok) {
                toastr.success('Đã cập nhật địa chỉ giao hàng');
                $('#adminEditAddressModal').modal('hide');
                reloadOrders();
            } else {
                toastr.error(res?.msg || 'Không thể cập nhật địa chỉ');
            }
        }).fail(function() {
            toastr.error('Lỗi kết nối, vui lòng thử lại');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i>Lưu địa chỉ');
        });
    });
});
</script>