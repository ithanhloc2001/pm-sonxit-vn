<div id="orderListPage" class="py-4">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon bg-primary-subtle text-primary border border-primary-subtle rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(12, 76, 41, 0.1) !important; border-color: rgba(12, 76, 41, 0.15) !important;">
                    <i class="bi bi-receipt fs-4" style="color: var(--theme-primary, #0c4c29);"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Đơn hàng của tôi</h1>
                        <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="ordersMeta" style="font-size: 0.72rem;">Đang tải...</span>
                    </div>
                    <!-- Description for Desktop / Tablet -->
                    <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                        Theo dõi, quản lý và kiểm tra lịch sử mua sắm trực tuyến của bạn.
                    </p>
                    <!-- Description for Mobile -->
                    <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                        Theo dõi, quản lý và kiểm tra lịch sử mua sắm.
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
            <div class="_card-body _p-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-0" id="searchOrder" placeholder="Tìm kiếm theo mã đơn hoặc tên sản phẩm...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filterStatus" class="form-select form-select-sm border shadow-sm rounded-3">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="processing">Chờ lấy hàng</option>
                            <option value="shipping">Chờ giao</option>
                            <option value="delivered">Đã giao</option>
                            <option value="return_requested">Trả hàng</option>
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
                </div>
            </div>
            <br>
            <!-- Order List Content -->
            <div id="ordersContainer">
                <!-- Desktop Layout -->
                <div id="desktopTableCard" class="desktop-only fb-table-responsive border-top">
                    <table class="table fb-table table-hover align-middle mb-0" id="ordersTable">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 15%;">Đơn hàng</th>
                                <th style="width: 35%;">Sản phẩm</th>
                                <th style="width: 15%;">Thanh toán</th>
                                <th style="width: 12%;">Tổng tiền</th>
                                <th style="width: 13%;">Trạng thái</th>
                                <th class="pe-4 text-end" style="width: 10%;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Layout -->
                <div id="ordersMobileList" class="mobile-only bg-white border-top shadow-sm overflow-hidden px-3">
                    <!-- Populated by JS -->
                </div>

                <!-- Loading & Empty States -->
                <div id="ordersLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Đang tải danh sách đơn hàng...</p>
                </div>
                
                <div id="ordersEmpty" class="text-center py-4 d-none">
                    <div class="mb-2">
                        <i class="bi bi-inbox text-muted opacity-25" style="font-size: 2.5rem;"></i>
                    </div>
                    <p class="text-muted small mb-3">Bạn chưa có đơn hàng nào phù hợp với điều kiện lọc.</p>
                    <a href="<?= h($baseUrl) ?>" class="btn btn-primary btn-sm px-3 rounded-pill">Mua sắm ngay</a>
                </div>
            </div>
            
            <div class="card-footer bg-white border-top-0 text-center py-3">
                <button id="loadMoreOrders" class="btn btn-light rounded-pill px-4 btn-sm fw-bold d-none">Xem thêm đơn hàng</button>
            </div>
        </div>
    </div>
</div>
<style>
    /* CSS Variables synchronized with global style.css */
    #orderListPage {
        background-color: var(--fb-bg, #f8fafc);
        min-height: 100vh;
        color: var(--fb-text, #0f172a);
        font-family: var(--theme-font, inherit);
    }

    /* Modern Page Header Restyling */
    .header-icon {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .header-icon:hover {
        transform: scale(1.08) rotate(3deg);
        background-color: rgba(12, 76, 41, 0.12) !important;
        border-color: rgba(12, 76, 41, 0.25) !important;
    }

    /* Local Summary Cards Grid & Styles Modern Overrides */
    #summaryGrid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    @media (min-width: 576px) {
        #summaryGrid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (min-width: 992px) {
        #summaryGrid {
            grid-template-columns: repeat(6, 1fr);
        }
    }

    .summary-card {
        border: 1px solid var(--order-border, #e2e8f0) !important;
        border-radius: 12px !important;
        padding: 10px 14px !important;
        background: #fff !important;
        cursor: pointer;
        min-height: 68px !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    .summary-card:hover {
        border-color: rgba(12, 76, 41, 0.3) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05) !important;
    }
    .summary-card strong {
        display: block !important;
        font-size: 1.25rem !important;
        color: #0f172a !important;
        line-height: 1.1 !important;
        font-weight: 700 !important;
    }
    .summary-card span {
        font-size: 0.8rem !important;
        color: #64748b !important;
        font-weight: 500 !important;
        text-transform: none !important;
        letter-spacing: normal !important;
    }
    .summary-card .summary-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
    }
    .summary-card .summary-icon i {
        font-size: 0.95rem !important;
    }

    /* Active Tab Overrides */
    .summary-card.active {
        border-color: var(--theme-primary, #0c4c29) !important;
        background-color: rgba(12, 76, 41, 0.04) !important;
        box-shadow: 0 4px 12px rgba(12, 76, 41, 0.08) !important;
    }
    .summary-card.active strong {
        color: var(--theme-primary, #0c4c29) !important;
    }
    .summary-card.active span {
        color: var(--theme-primary, #0c4c29) !important;
    }

    /* Colors and states for specific active cards */
    .summary-card[data-status="all"] .summary-icon {
        background-color: #f1f5f9;
        color: #475569;
    }
    .summary-card.active[data-status="all"] .summary-icon {
        background-color: var(--theme-primary, #0c4c29) !important;
        color: #fff !important;
    }

    .summary-card[data-status="pending"] .summary-icon {
        background-color: #fff7ed;
        color: #ea580c;
    }
    .summary-card.active[data-status="pending"] .summary-icon {
        background-color: #ea580c !important;
        color: #fff !important;
    }

    .summary-card[data-status="processing"] .summary-icon {
        background-color: #eff6ff;
        color: #2563eb;
    }
    .summary-card.active[data-status="processing"] .summary-icon {
        background-color: #2563eb !important;
        color: #fff !important;
    }

    .summary-card[data-status="shipping"] .summary-icon {
        background-color: #faf5ff;
        color: #7e22ce;
    }
    .summary-card.active[data-status="shipping"] .summary-icon {
        background-color: #7e22ce !important;
        color: #fff !important;
    }

    .summary-card[data-status="delivered"] .summary-icon {
        background-color: #f0fdf4;
        color: #16a34a;
    }
    .summary-card.active[data-status="delivered"] .summary-icon {
        background-color: #16a34a !important;
        color: #fff !important;
    }

    .summary-card[data-status="return_requested"] .summary-icon {
        background-color: #fdf2f8;
        color: #db2777;
    }
    .summary-card.active[data-status="return_requested"] .summary-icon {
        background-color: #db2777 !important;
        color: #fff !important;
    }

    .summary-card[data-status="returned"] .summary-icon {
        background-color: #f1f5f9;
        color: #475569;
    }
    .summary-card.active[data-status="returned"] .summary-icon {
        background-color: #475569 !important;
        color: #fff !important;
    }

    .summary-card[data-status="canceled"] .summary-icon {
        background-color: #fef2f2;
        color: #dc2626;
    }
    .summary-card.active[data-status="canceled"] .summary-icon {
        background-color: #dc2626 !important;
        color: #fff !important;
    }

    /* Custom Responsive Layout Modes */
    @media (max-width: 768px) {
        .desktop-only { display: none !important; }
        .mobile-only { display: block !important; }
    }
    @media (min-width: 769px) {
        .desktop-only { display: block !important; }
        .mobile-only { display: none !important; }
    }

    /* Premium Overlapping Thumbnails */
    .order-thumbnails-group {
        display: flex;
        align-items: center;
    }
    .order-thumbnail-item {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid #fff;
        object-fit: cover;
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.08);
        margin-left: -10px;
        background: #f8fafc;
        transition: transform 0.2s ease, z-index 0.2s ease;
    }
    .order-thumbnail-item:first-child {
        margin-left: 0;
    }
    .order-thumbnail-item:hover {
        transform: translateY(-2px) scale(1.05);
        z-index: 10;
    }
    .order-thumbnail-more {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid #fff;
        background: var(--theme-primary-soft, #e6f0eb);
        color: var(--theme-primary, #0c4c29);
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.08);
        margin-left: -10px;
        z-index: 1;
    }

    /* Modern Table Card Layout */
    .fb-table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .fb-table { font-size: 0.875rem; }
    .fb-table thead th { 
        background: #f8fafc; 
        text-transform: uppercase; 
        font-size: 0.65rem; 
        font-weight: 800; 
        letter-spacing: 0.05em; 
        color: #64748b; 
        padding: 1rem 0.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .fb-table tbody td { padding: 1rem 0.5rem; border-bottom: 1px solid #f1f5f9; }
    .fb-table tbody tr:hover { background-color: #f8fafc; }

    /* Status Pill Styling */
    .status-pill {
        padding: 0.35rem 0.75rem;
        border-radius: 2rem;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .status-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .status-processing { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
    .status-shipping { background: #faf5ff; color: #7e22ce; border: 1px solid #f3e8ff; }
    .status-delivered { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
    .status-canceled { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
    .status-returned { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .status-refunded { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

    /* Mobile Compact Layout */
    .order-mobile-card {
        border-bottom: 1px solid #f1f5f9;
        transition: background-color 0.2s ease;
    }
    .order-mobile-card:last-child {
        border-bottom: none;
    }
    .order-mobile-card:hover {
        background-color: #f8fafc;
    }

    .order-id-link { color: var(--theme-primary, #0c4c29); font-weight: 700; text-decoration: none; }
    .order-id-link:hover { text-decoration: underline; }

    .btn-action-outline {
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 6px 12px;
        border: 1px solid #e2e8f0;
        color: #334155;
        background: white;
        transition: all 0.2s;
    }
    .btn-action-outline:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    
    .btn-action-primary {
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 6px 12px;
        background: var(--theme-primary, #0c4c29);
        color: white;
        border: none;
        transition: all 0.2s;
    }
    .btn-action-primary:hover {
        background: var(--theme-primary-dark, #08331c);
        transform: translateY(-1px);
        color: white;
    }
</style>
<script>
(function($){
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/order.php';
    const DETAIL_URL = '<?= h($baseUrl) ?>/view-order';
    const BASE_URL = '<?= h(rtrim((string)$baseUrl, '/')) ?>';

    // Ưu tiên dùng media domain (toMediaUrl từ head.php); fallback ghép BASE_URL.
    function toAbs(url){
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
        const raw = String(url || '').trim();
        if (!raw) return '';
        if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
        const base = String(BASE_URL || '').replace(/\/$/, '');
        if (!base) return raw;
        return base + (raw.startsWith('/') ? raw : '/' + raw);
    }

    const state = { 
        status: 'all', 
        search: '', 
        from: '', 
        to: '', 
        page: 1, 
        limit: 10, 
        loading: false, 
        hasMore: false, 
        total: 0 
    };

    const dom = {
        summary: $('#summaryGrid'),
        container: $('#ordersContainer'),
        loading: $('#ordersLoading'),
        empty: $('#ordersEmpty'),
        meta: $('#ordersMeta'),
        loadMore: $('#loadMoreOrders'),
        status: $('#filterStatus'),
        search: $('#searchOrder'),
        from: $('#filterFrom'),
        to: $('#filterTo')
    };

    function notify(type, msg){
        if (window.toastr) toastr[type](msg);
        else alert(msg);
    }

    function escapeHtml(str){
        return $('<div>').text(str ?? '').html();
    }

    function fmtMoney(num){
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num || 0);
    }

    function paymentStatusBadgeUser(row){
        const key = String(row?.payment_status || '').toLowerCase().trim();
        let label = String(row?.payment_status_label || row?.payment_status || '').trim();
        
        // If it is COD / cash payment AND payment status is pending or empty (Chưa thanh toán)
        const payMethodKey = String(row?.payment_method || row?.payment_gateway || '').toLowerCase();
        const isCod = !payMethodKey || payMethodKey === 'cod';
        if (isCod && (key === 'pending' || key === 'unpaid' || label === 'Chưa thanh toán' || !key)) {
            label = 'Thanh toán khi nhận';
        }
        
        if (!key && !label) return '<span class="text-muted small">—</span>';
        let cls = 'bg-light text-dark border';
        if (key === 'paid') cls = 'bg-success text-white';
        else if (key === 'pending') cls = 'bg-warning text-dark';
        else if (key === 'failed') cls = 'bg-danger text-white';
        else if (key === 'expired') cls = 'bg-secondary text-white';
        else if (key === 'refunded') cls = 'bg-info text-dark';
        return `<span class="badge ${cls}" style="font-size: 0.6rem;">${escapeHtml(label || key)}</span>`;
    }

    function statusPill(statusKey, label){
        const st = String(statusKey || 'pending').toLowerCase();
        const allow = ['pending','processing','shipping','delivered','return_requested','returned','refunded','canceled'];
        const cls = allow.includes(st) ? st : 'pending';
        return `<span class="status-pill status-${cls}">${escapeHtml(label || st)}</span>`;
    }

    function fetchSummary(){
        $.get(API, { action: 'summary' }, res => {
            if (res?.ok) renderSummary(res.list || []);
        });
    }

    function renderSummary(list){
        if (!Array.isArray(list)) return;
        
        const countMap = {};
        let allCount = 0;
        list.forEach(item => {
            if (item.key && item.key !== 'all') {
                countMap[item.key] = parseInt(item.count) || 0;
                allCount += countMap[item.key];
            }
        });
        countMap['all'] = allCount;
        
        const tabs = [
            { key: 'all', label: 'Tất cả', icon: 'bi bi-collection-fill', count: countMap['all'] },
            { key: 'processing', label: 'Chờ lấy hàng', icon: 'bi bi-box-seam', count: (countMap['pending'] || 0) + (countMap['processing'] || 0) },
            { key: 'shipping', label: 'Chờ giao', icon: 'bi bi-truck', count: countMap['shipping'] || 0 },
            { key: 'delivered', label: 'Đã giao', icon: 'bi bi-check-circle-fill', count: countMap['delivered'] || 0 },
            { key: 'return_requested', label: 'Trả hàng', icon: 'bi bi-arrow-counterclockwise', count: (countMap['return_requested'] || 0) + (countMap['returned'] || 0) + (countMap['refunded'] || 0) },
            { key: 'canceled', label: 'Đã huỷ', icon: 'bi bi-x-circle-fill', count: countMap['canceled'] || 0 }
        ];

        dom.summary.html(tabs.map(tab => {
            const isActive = tab.key === state.status;
            return `
                <div class="summary-card ${isActive ? 'active' : ''}" data-status="${escapeHtml(tab.key)}">
                    <div class="d-flex flex-column">
                        <span>${escapeHtml(tab.label)}</span>
                        <strong class="mt-1">${tab.count}</strong>
                    </div>
                    <div class="summary-icon">
                        <i class="${tab.icon}"></i>
                    </div>
                </div>
            `;
        }).join(''));
    }

    function fetchOrders(reset = false){
        if (state.loading) return;
        state.loading = true;
        
        if (reset) {
            state.page = 1;
            $('#ordersTableBody').empty();
            $('#ordersMobileList').empty();
            dom.loading.removeClass('d-none');
            dom.empty.addClass('d-none');
        }

        const params = {
            action: 'list',
            status: state.status === 'all' ? '' : state.status,
            search: state.search,
            from: state.from,
            to: state.to,
            page: state.page,
            limit: state.limit
        };

        $.get(API, params).done(res => {
            if (res?.ok) {
                const list = res.data || [];
                state.total = res.total || 0;
                renderOrders(list, reset);
                
                state.hasMore = (state.page * state.limit) < state.total;
                dom.loadMore.toggleClass('d-none', !state.hasMore);
                
                const renderedCount = $('#ordersTableBody tr').length;
                dom.meta.text(`Hiển thị ${renderedCount} / ${state.total} đơn hàng`);
            } else {
                notify('error', res?.msg || 'Không thể tải đơn hàng');
            }
        }).always(() => {
            state.loading = false;
            dom.loading.addClass('d-none');
        });
    }

    function renderOrders(list, reset){
        if (list.length === 0 && reset) {
            dom.empty.removeClass('d-none');
            $('#desktopTableCard').addClass('d-none');
            $('#ordersMobileList').addClass('d-none');
            return;
        }

        $('#desktopTableCard').removeClass('d-none');
        $('#ordersMobileList').removeClass('d-none');

        // Render Desktop Rows
        const desktopHtml = list.map(order => {
            const items = order.items || [];
            const isPending = order.status === 'pending';
            const statusKey = String(order.status || 'pending');
            const statusLabel = String(order.status_label || statusKey);
            const payMethodLabel = String(order.payment_method_label || order.payment_method || order.payment_gateway || '').trim();
            const payMethodKey = String(order.payment_method || order.payment_gateway || '').toLowerCase();
            const isCod = !payMethodKey || payMethodKey === 'cod';
            const displayPayMethod = isCod ? 'Tiền mặt' : payMethodLabel;
            const showPayButton = isPending && !isCod;
            const created = order.created_human;
            const totalFmt = order.totals?.grand_total || '0 đ';
            const itemsCount = items.length;
            
            // Map thumbnails
            const maxThumbs = 3;
            const thumbSlice = items.slice(0, maxThumbs);
            const moreCount = items.length - maxThumbs;
            
            const thumbsGroupHtml = `
                <div class="order-thumbnails-group flex-shrink-0">
                    ${thumbSlice.map(item => `
                        <img src="${item.thumb ? toAbs(item.thumb) : '<?= h($baseUrl) ?>/assets/img/no-image.png'}" class="order-thumbnail-item" title="${escapeHtml(item.name)}">
                    `).join('')}
                    ${moreCount > 0 ? `<div class="order-thumbnail-more">+${moreCount}</div>` : ''}
                </div>
            `;

            const productHtml = items[0] ? `
                <div class="d-flex align-items-center gap-2">
                    ${thumbsGroupHtml}
                    <div style="min-width: 0;">
                        <div class="fw-bold text-truncate_" style="max-width: 320px; font-size: 0.8rem;" title="${escapeHtml(items[0].name)}">${escapeHtml(items[0].name)}</div>
                        ${items.length > 1 ? `<div class="text-muted smaller" style="font-size: 0.7rem;">và ${items.length - 1} sản phẩm khác</div>` : ''}
                    </div>
                </div>
            ` : '<span class="text-muted italic smaller">Không có dữ liệu</span>';
            
            return `
                <tr>
                    <td class="ps-4">
                        <a href="${DETAIL_URL}?order_id=${order.order_id}" class="order-id-link">#${order.order_id}</a>
                        <div class="text-muted smaller mt-1" style="font-size: 0.7rem;">${created}</div>
                    </td>
                    <td>
                        ${productHtml}
                    </td>
                    <td>
                        <div class="small fw-medium mb-1">${displayPayMethod}</div>
                        ${paymentStatusBadgeUser(order)}
                    </td>
                    <td>
                        <div class="fw-bold text-danger">${totalFmt}</div>
                        <div class="text-muted smaller" style="font-size: 0.65rem;">${itemsCount} sản phẩm</div>
                    </td>
                    <td>${statusPill(statusKey, statusLabel)}</td>
                    <td class="pe-4 text-end">
                        <div class="d-inline-flex gap-2">
                            <a href="${DETAIL_URL}?order_id=${order.order_id}" class="btn-action-outline text-decoration-none">Chi tiết</a>
                            ${showPayButton ? `<a href="${DETAIL_URL}?order_id=${order.order_id}" class="btn-action-primary text-decoration-none">Thanh toán</a>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        // Render Mobile List
        const mobileHtml = list.map(order => {
            const items = order.items || [];
            const maxThumbs = 3;
            const thumbSlice = items.slice(0, maxThumbs);
            const moreCount = items.length - maxThumbs;
            
            const thumbsGroupHtml = `
                <div class="order-thumbnails-group flex-shrink-0">
                    ${thumbSlice.map(item => `
                        <img src="${item.thumb ? toAbs(item.thumb) : '<?= h($baseUrl) ?>/assets/img/no-image.png'}" class="order-thumbnail-item">
                    `).join('')}
                    ${moreCount > 0 ? `<div class="order-thumbnail-more">+${moreCount}</div>` : ''}
                </div>
            `;
            
            return `
                <a href="${DETAIL_URL}?order_id=${order.order_id}" class="order-mobile-card d-block text-decoration-none text-reset border-bottom py-3 px-2 transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">#${order.order_id}</span>
                        ${statusPill(order.status, order.status_label)}
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 mb-2">
                        ${thumbsGroupHtml}
                        <div class="min-w-0 flex-grow-1">
                            <div class="text-truncate_ text-dark fw-semibold" style="font-size: 0.62rem;" title="${escapeHtml(items[0]?.name || '')}">
                                ${escapeHtml(items[0]?.name || '')}
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">${items.length} SP</span>
                                ${paymentStatusBadgeUser(order)}
                            </div>
                        </div>
                        <div class="flex-shrink-0 text-muted ps-1">
                            <i class="bi bi-chevron-right fs-5"></i>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light-subtle" style="font-size: 0.75rem;">
                        <span class="text-muted">${order.created_human}</span>
                        <div>
                            <span class="text-muted me-1">Tổng tiền:</span>
                            <span class="fw-bold text-danger" style="font-size: 0.95rem;">${order.totals?.grand_total || '0 đ'}</span>
                        </div>
                    </div>
                </a>
            `;
        }).join('');

        if (reset) {
            $('#ordersTableBody').html(desktopHtml);
            $('#ordersMobileList').html(mobileHtml);
        } else {
            $('#ordersTableBody').append(desktopHtml);
            $('#ordersMobileList').append(mobileHtml);
        }
    }

    function bindEvents(){
        dom.summary.on('click', '.summary-card', function(){
            state.status = $(this).data('status');
            dom.status.val(state.status);
            fetchOrders(true);
            renderSummary([]); // Refresh UI immediately
            fetchSummary();
        });

        dom.status.on('change', function(){
            state.status = $(this).val();
            fetchOrders(true);
            fetchSummary();
        });

        dom.search.on('input', debounce(function(){
            state.search = $(this).val();
            fetchOrders(true);
        }, 500));

        dom.from.add(dom.to).on('change', () => fetchOrders(true));

        dom.loadMore.on('click', function(){
            state.page++;
            fetchOrders();
        });
    }

    function debounce(fn, delay){
        let timer = null;
        return function(...args){
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function init(){
        bindEvents();
        fetchSummary();
        fetchOrders(true);
    }

    $(init);
})(jQuery);
</script>
