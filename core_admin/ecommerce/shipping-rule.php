<?php
require_once __DIR__ . '/../_admin_guard.php';
?>
<?php
// Lấy danh sách vùng từ cấu hình ECOMMERCE_REGIONS trong site_setting (nếu có),
// nếu không thì fallback về 3 miền mặc định.
global $ECOMMERCE_REGIONS;
$shippingRegionOptions = (isset($ECOMMERCE_REGIONS) && is_array($ECOMMERCE_REGIONS) && !empty($ECOMMERCE_REGIONS))
    ? array_values($ECOMMERCE_REGIONS)
    : ['MIỀN BẮC', 'MIỀN TRUNG', 'MIỀN NAM'];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;min-width:48px;background-color:rgba(12,76,41,0.08)!important;color:var(--theme-primary,#0c4c29)!important;border:1px solid rgba(12,76,41,0.15);">
                <i class="bi bi-truck fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size:1.45rem;color:#1e293b!important;letter-spacing:-0.01em;">Phí vận chuyển</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="shippingRuleMeta" style="font-size:0.72rem;">Đang tải dữ liệu...</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size:0.82rem;line-height:1.45;max-width:600px;">
                    Quản lý quy tắc tính phí vận chuyển theo khu vực, khoảng giá trị đơn hàng và khối lượng.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size:0.78rem;line-height:1.4;">
                    Quản lý quy tắc tính phí vận chuyển theo khu vực.
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" id="btnAddShippingRule">
                <i class="bi bi-plus-lg me-1"></i> Thêm rule
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards (JS loaded) -->
    <div class="mb-4" id="summaryGrid">
        <!-- Rendered by JS -->
    </div>

    <!-- Filters & Table Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <!-- Search -->
                <div class="col-md-5">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-0" id="shippingRuleSearch" placeholder="Tìm kiếm theo vùng, ghi chú, phí...">
                    </div>
                </div>
                <!-- Filter Region -->
                <div class="col-md-3">
                    <select id="filterRegion" class="form-select form-select-sm border shadow-sm rounded-3">
                        <option value="">Tất cả khu vực</option>
                        <option value="ALL">Toàn quốc</option>
                        <?php foreach ($shippingRegionOptions as $r): ?>
                        <option value="<?= htmlspecialchars(trim($r), ENT_QUOTES) ?>"><?= htmlspecialchars(trim($r), ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filter Active -->
                <div class="col-md-2">
                    <select id="filterActive" class="form-select form-select-sm border shadow-sm rounded-3">
                        <option value="">Tất cả trạng thái</option>
                        <option value="1">Đang áp dụng</option>
                        <option value="0">Tạm tắt</option>
                    </select>
                </div>
                <!-- Actions -->
                <div class="col-md-2 d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold" type="button" id="btnResetFilter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="fb-table-responsive border-top">
            <table id="shippingRuleTable" class="table fb-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width:60px;">ID</th>
                        <th style="width:110px;">Trạng thái</th>
                        <th style="width:160px;">Khu vực</th>
                        <th>Khoảng đơn (VNĐ)</th>
                        <th>Khoảng KL (gram)</th>
                        <th style="width:130px;" class="text-end">Phí (VNĐ)</th>
                        <th>Ghi chú</th>
                        <th class="text-end pe-4" style="width:90px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="shippingRuleBody"></tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div id="shippingRuleEmpty" class="text-center py-5 text-muted" style="display:none;">
            <div class="py-4">
                <i class="bi bi-truck fs-1 d-block mb-3 opacity-25"></i>
                <p class="mb-0">Không tìm thấy quy tắc phí vận chuyển nào phù hợp.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal thêm / sửa rule -->
<div class="modal fade" id="shippingRuleModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="shippingRuleModalTitle">
                    <i class="bi bi-truck me-2" style="color:var(--theme-primary,#0c4c29)!important;"></i>Thêm rule vận chuyển
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shippingRuleForm" class="needs-validation" novalidate>
                <div class="modal-body py-3">
                    <input type="hidden" name="id" id="shippingRuleId" value="0">
                    <div class="card p-3 border-0 bg-light rounded-3">
                        <div class="row g-3">
                            <!-- Active toggle -->
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="shippingRuleIsActive" name="is_active" checked>
                                        <label class="form-check-label fw-semibold" for="shippingRuleIsActive">Đang áp dụng</label>
                                    </div>
                                    <span class="text-muted small">Phương thức: <span class="fw-semibold text-dark">Tiêu chuẩn</span></span>
                                </div>
                            </div>
                            <!-- Region -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">KHU VỰC (REGION)</label>
                                <select class="form-select" id="shippingRuleRegion" name="region">
                                    <!-- Options filled by JS -->
                                </select>
                                <div class="form-text">Lấy từ cấu hình ECOMMERCE_REGIONS hoặc 3 miền mặc định.</div>
                            </div>
                            <!-- Min Subtotal -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">ĐƠN TỐI THIỂU (VNĐ)</label>
                                <input type="number" class="form-control" id="shippingRuleMinSubtotal" name="min_subtotal" min="0" placeholder="0 = không giới hạn">
                            </div>
                            <!-- Max Subtotal -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">ĐƠN TỐI ĐA (VNĐ)</label>
                                <input type="number" class="form-control" id="shippingRuleMaxSubtotal" name="max_subtotal" min="0" placeholder="Để trống = không giới hạn">
                            </div>
                            <!-- Min Weight -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">KHỐI LƯỢNG TỐI THIỂU (gram)</label>
                                <input type="number" class="form-control" id="shippingRuleMinWeight" name="min_weight_gram" min="0" placeholder="0">
                            </div>
                            <!-- Max Weight -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">KHỐI LƯỢNG TỐI ĐA (gram)</label>
                                <input type="number" class="form-control" id="shippingRuleMaxWeight" name="max_weight_gram" min="0" placeholder="Để trống = không giới hạn">
                            </div>
                            <!-- Fee -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">PHÍ CỐ ĐỊNH (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="shippingRuleFee" name="fee" required min="0" placeholder="Nhập phí vận chuyển...">
                                <div class="invalid-feedback">Vui lòng nhập phí vận chuyển hợp lệ.</div>
                            </div>
                            <!-- Note -->
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">GHI CHÚ</label>
                                <textarea class="form-control bg-white" id="shippingRuleNote" name="note" rows="2" placeholder="VD: Miền Nam ≤ 500k ≤ 2kg"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4 d-none" id="btnDeleteShippingRule">
                        <i class="bi bi-trash me-1"></i> Xoá rule
                    </button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary px-4 rounded-pill">
                            <i class="bi bi-save me-1"></i> Lưu
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    // ── CẤU HÌNH ──────────────────────────────────────────────
    const ajaxUrl = '<?= htmlentities((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/core_admin/ecommerce/ajax/shipping-rule.php';
    const regionOptions = <?php echo json_encode(array_values(array_filter(array_map(static fn($v) => trim((string)$v), $shippingRegionOptions), static fn($v) => $v !== '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    // ── SELECTOR CACHE ─────────────────────────────────────────
    const $tableEl       = $('#shippingRuleTable');
    const $tableBody     = $('#shippingRuleBody');
    const $searchEl      = $('#shippingRuleSearch');
    const $filterRegion  = $('#filterRegion');
    const $filterActive  = $('#filterActive');
    const $summaryGrid   = $('#summaryGrid');
    const $metaBadge     = $('#shippingRuleMeta');
    const $emptyState    = $('#shippingRuleEmpty');
    const $btnResetFilter = $('#btnResetFilter');

    const modalEl    = document.getElementById('shippingRuleModal');
    const modal      = new bootstrap.Modal(modalEl);
    const form       = document.getElementById('shippingRuleForm');
    const btnAdd     = document.getElementById('btnAddShippingRule');
    const btnDelete  = document.getElementById('btnDeleteShippingRule');
    const idInput    = document.getElementById('shippingRuleId');
    const isActiveInput   = document.getElementById('shippingRuleIsActive');
    const regionInput     = document.getElementById('shippingRuleRegion');
    const minSubtotalInput = document.getElementById('shippingRuleMinSubtotal');
    const maxSubtotalInput = document.getElementById('shippingRuleMaxSubtotal');
    const minWeightInput  = document.getElementById('shippingRuleMinWeight');
    const maxWeightInput  = document.getElementById('shippingRuleMaxWeight');
    const feeInput   = document.getElementById('shippingRuleFee');
    const noteInput  = document.getElementById('shippingRuleNote');
    const titleEl    = document.getElementById('shippingRuleModalTitle');

    let dt = null;
    let ruleMap = {};
    let allItems = [];

    function esc(v) { return $('<div>').text(v ?? '').html(); }

    function formatMoney(v) {
        return Number(v || 0).toLocaleString('vi-VN');
    }

    function buildRangeLabel(minVal, maxVal, suffix) {
        if ((minVal === null || minVal === '' || isNaN(minVal)) && (maxVal === null || maxVal === '' || isNaN(maxVal))) {
            return 'Không giới hạn';
        }
        const parts = [];
        if (minVal !== null && minVal !== '' && !isNaN(minVal)) parts.push('≥ ' + formatMoney(minVal) + (suffix || ''));
        if (maxVal !== null && maxVal !== '' && !isNaN(maxVal)) parts.push('≤ ' + formatMoney(maxVal) + (suffix || ''));
        return parts.join(' & ');
    }

    // ── SUMMARY CARDS ──────────────────────────────────────────
    function renderSummary(items) {
        const total  = items.length;
        const active = items.filter(r => parseInt(r.is_active || 0, 10) === 1).length;
        const inactive = total - active;

        // Group by region
        const regionMap = {};
        items.forEach(r => {
            let reg = (r.region || 'ALL').trim().toUpperCase();
            if (!reg || reg === 'ALL') reg = 'Toàn quốc';
            regionMap[reg] = (regionMap[reg] || 0) + 1;
        });
        const regionCount = Object.keys(regionMap).length;

        const cards = [
            { label: 'Tổng rule', value: total, icon: 'bi bi-list-ul', status: 'processing' },
            { label: 'Đang áp dụng', value: active, icon: 'bi bi-check-circle-fill', status: 'delivered' },
            { label: 'Tạm tắt', value: inactive, icon: 'bi bi-pause-circle-fill', status: 'canceled' },
            { label: 'Khu vực', value: regionCount, icon: 'bi bi-geo-alt-fill', status: 'shipping' },
        ];

        const html = cards.map(c => `
            <div class="summary-card" data-status="${c.status}">
                <div class="d-flex flex-column">
                    <span>${esc(c.label)}</span>
                    <strong class="mt-1">${c.value}</strong>
                </div>
                <div class="summary-icon">
                    <i class="${c.icon}"></i>
                </div>
            </div>
        `).join('');
        $summaryGrid.html(html);
        $metaBadge.text(`${total} rule`);
    }

    // ── TABLE ROW BUILDER ──────────────────────────────────────
    function buildTableRows(items) {
        ruleMap = {};
        return items.map(function (row) {
            const id = parseInt(row.id || 0, 10);
            ruleMap[id] = row;
            const isActive = parseInt(row.is_active || 0, 10) === 1;

            let regionLabel = (row.region || '').trim();
            if (!regionLabel || regionLabel.toUpperCase() === 'ALL') regionLabel = 'Toàn quốc';

            const subtotalLabel = buildRangeLabel(row.min_subtotal, row.max_subtotal, '');
            const weightLabel   = buildRangeLabel(row.min_weight_gram, row.max_weight_gram, 'g');
            const fee = parseInt(row.fee || 0, 10);
            const note = (row.note || '').trim();

            const toggleSwitch = `<div class="form-check form-switch mb-0">
                <input class="form-check-input js-toggle-active" type="checkbox" data-id="${id}" ${isActive ? 'checked' : ''}>
            </div>`;

            const noteHtml = note ? `<span class="text-muted small">${esc(note)}</span>` : `<span class="text-muted small opacity-50">—</span>`;

            const actions = `
                <div class="d-flex justify-content-end gap-1">
                    <button class="quick-action-btn js-edit" type="button" data-id="${id}" title="Chỉnh sửa">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>`;

            return [
                id,
                toggleSwitch,
                `<span class="fw-semibold small">${esc(regionLabel)}</span>`,
                `<span class="small text-muted">${esc(subtotalLabel)}</span>`,
                `<span class="small text-muted">${esc(weightLabel)}</span>`,
                `<div class="text-end fw-bold text-primary">${formatMoney(fee)} <small class="text-muted fw-normal">đ</small></div>`,
                noteHtml,
                actions
            ];
        });
    }

    // ── RELOAD TABLE ───────────────────────────────────────────
    function reloadTable() {
        $metaBadge.text('Đang tải...');
        $.post(ajaxUrl, { action: 'list' }, function (resp) {
            if (!resp || !resp.ok) {
                toastr.error(resp && resp.msg ? resp.msg : 'Không thể tải danh sách rule.');
                return;
            }
            allItems = resp.items || [];
            renderSummary(allItems);

            const rows = buildTableRows(allItems);

            if (dt) {
                dt.clear();
                if (rows.length) dt.rows.add(rows);
                dt.draw();
            } else {
                dt = $tableEl.DataTable({
                    data: rows,
                    columns: [
                        { title: 'ID', width: '60px' },
                        { title: 'Trạng thái', orderable: false },
                        { title: 'Khu vực' },
                        { title: 'Khoảng đơn (VNĐ)' },
                        { title: 'Khoảng KL (gram)' },
                        { title: 'Phí (VNĐ)', orderable: true, searchable: false },
                        { title: 'Ghi chú' },
                        { title: 'Thao tác', orderable: false, searchable: false, width: '90px' }
                    ],
                    order: [[0, 'desc']],
                    paging: true,
                    searching: true,
                    lengthChange: false,
                    pageLength: 10,
                    dom: 'rt<"d-flex justify-content-between align-items-center p-4 border-top"ip>',
                    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json' }
                });

                // Search box
                $searchEl.on('keyup change', function () {
                    dt.search($(this).val() || '').draw();
                });

                // Filter Region
                $filterRegion.on('change', reloadAndFilter);

                // Filter Active
                $filterActive.on('change', reloadAndFilter);

                // Reset
                $btnResetFilter.on('click', function () {
                    $searchEl.val('');
                    $filterRegion.val('');
                    $filterActive.val('');
                    if (dt) dt.search('').draw();
                    reloadAndFilter();
                });
            }

            const emptyAfterLoad = allItems.length === 0;
            $emptyState.toggle(emptyAfterLoad);
        }, 'json').fail(function () {
            toastr.error('Lỗi kết nối server');
        });
    }



    function reloadAndFilter() {
        if (!dt) return;
        const regionVal = ($filterRegion.val() || '').trim().toUpperCase();
        const activeVal = $filterActive.val(); // '' | '1' | '0'

        const filtered = allItems.filter(row => {
            const rowRegion = ((row.region || 'ALL').trim().toUpperCase() === '' ? 'ALL' : (row.region || 'ALL').trim().toUpperCase());
            const rowActive = String(parseInt(row.is_active || 0, 10));
            if (regionVal && regionVal !== rowRegion) return false;
            if (activeVal !== '' && rowActive !== activeVal) return false;
            return true;
        });

        const rows = buildTableRows(filtered);
        dt.clear();
        if (rows.length) dt.rows.add(rows);
        dt.draw();

        $emptyState.toggle(filtered.length === 0);
        $metaBadge.text(`${filtered.length}/${allItems.length} rule`);
    }

    $filterRegion.on('change', reloadAndFilter);
    $filterActive.on('change', reloadAndFilter);
    $btnResetFilter.on('click', function () {
        $searchEl.val('');
        $filterRegion.val('');
        $filterActive.val('');
        if (dt) dt.search('').draw();
        reloadAndFilter();
    });

    // ── REGION OPTIONS (MODAL) ─────────────────────────────────
    function ensureRegionOptions(selectedValue) {
        while (regionInput.firstChild) regionInput.removeChild(regionInput.firstChild);

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Chọn vùng --';
        regionInput.appendChild(placeholder);

        const allOpt = document.createElement('option');
        allOpt.value = 'ALL';
        allOpt.textContent = 'Tất cả (Toàn quốc)';
        regionInput.appendChild(allOpt);

        const current = new Set(['ALL']);
        (regionOptions || []).forEach(function (val) {
            const clean = (val || '').trim();
            if (!clean) return;
            const opt = document.createElement('option');
            opt.value = clean;
            opt.textContent = clean;
            regionInput.appendChild(opt);
            current.add(clean);
        });

        const selected = (selectedValue || '').trim();
        if (selected && !current.has(selected)) {
            const extra = document.createElement('option');
            extra.value = selected;
            extra.textContent = selected + ' (khác cấu hình)';
            regionInput.appendChild(extra);
        }
        regionInput.value = selected || '';
    }

    // ── MODAL OPEN ─────────────────────────────────────────────
    function openModalForCreate() {
        titleEl.innerHTML = '<i class="bi bi-plus-lg me-2" style="color:var(--theme-primary,#0c4c29)!important;"></i>Thêm rule vận chuyển';
        idInput.value = '0';
        isActiveInput.checked = true;
        ensureRegionOptions('');
        minSubtotalInput.value = '';
        maxSubtotalInput.value = '';
        minWeightInput.value   = '';
        maxWeightInput.value   = '';
        feeInput.value  = '';
        noteInput.value = '';
        btnDelete.classList.add('d-none');
        form.classList.remove('was-validated');
        modal.show();
    }

    function openModalForEdit(id) {
        const row = ruleMap[id];
        if (!row) { toastr.error('Không tìm thấy rule để chỉnh sửa.'); return; }
        titleEl.innerHTML = `<i class="bi bi-pencil me-2" style="color:var(--theme-primary,#0c4c29)!important;"></i>Chỉnh sửa rule <span class="text-muted">#${id}</span>`;
        idInput.value = id;
        isActiveInput.checked = parseInt(row.is_active || 0, 10) === 1;
        ensureRegionOptions((row.region || '').trim());
        minSubtotalInput.value = row.min_subtotal || '';
        maxSubtotalInput.value = row.max_subtotal || '';
        minWeightInput.value   = row.min_weight_gram || '';
        maxWeightInput.value   = row.max_weight_gram || '';
        feeInput.value  = row.fee || '';
        noteInput.value = row.note || '';
        btnDelete.classList.remove('d-none');
        btnDelete.dataset.id = String(id);
        form.classList.remove('was-validated');
        modal.show();
    }

    // ── EVENT HANDLERS ─────────────────────────────────────────
    btnAdd.addEventListener('click', openModalForCreate);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        if (!form.checkValidity()) return;

        const formData = {
            action: 'save',
            id: idInput.value || '0',
            is_active: isActiveInput.checked ? 1 : 0,
            region: regionInput.value,
            min_subtotal: minSubtotalInput.value,
            max_subtotal: maxSubtotalInput.value,
            min_weight_gram: minWeightInput.value,
            max_weight_gram: maxWeightInput.value,
            fee: feeInput.value,
            note: noteInput.value
        };
        $.post(ajaxUrl, formData, function (resp) {
            if (!resp || !resp.ok) {
                toastr.error(resp && resp.msg ? resp.msg : 'Không thể lưu rule.');
                return;
            }
            toastr.success(resp.msg || 'Đã lưu rule vận chuyển');
            modal.hide();
            reloadTable();
        }, 'json').fail(() => toastr.error('Lỗi kết nối server'));
    });

    btnDelete.addEventListener('click', function () {
        const id = parseInt(idInput.value || '0', 10);
        if (!id) return;
        if (!confirm('Bạn có chắc muốn xoá rule #' + id + ' ?')) return;
        $.post(ajaxUrl, { action: 'delete', id }, function (resp) {
            if (!resp || !resp.ok) {
                toastr.error(resp && resp.msg ? resp.msg : 'Không thể xoá rule.');
                return;
            }
            toastr.success('Đã xoá rule #' + id);
            modal.hide();
            reloadTable();
        }, 'json').fail(() => toastr.error('Lỗi kết nối server'));
    });

    $tableEl.on('change', '.js-toggle-active', function () {
        const id = parseInt($(this).data('id') || 0, 10);
        if (!id) return;
        const isActive = $(this).is(':checked') ? 1 : 0;
        $.post(ajaxUrl, { action: 'toggle', id, is_active: isActive }, function (resp) {
            if (!resp || !resp.ok) {
                toastr.error(resp && resp.msg ? resp.msg : 'Không thể cập nhật trạng thái.');
                reloadTable();
            } else {
                if (ruleMap[id]) ruleMap[id].is_active = isActive;
                toastr.success(isActive ? 'Đã kích hoạt rule' : 'Đã tắt rule');
            }
        }, 'json').fail(() => toastr.error('Lỗi kết nối server'));
    });

    $tableEl.on('click', '.js-edit', function () {
        const id = parseInt($(this).data('id') || 0, 10);
        if (!id) return;
        openModalForEdit(id);
    });

    // ── INIT ───────────────────────────────────────────────────
    ensureRegionOptions('');
    reloadTable();
});
</script>


