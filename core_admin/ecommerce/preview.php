<?php
require_once __DIR__ . '/../_admin_guard.php';
?>
<?php
// Chuẩn bị dữ liệu danh mục & sản phẩm để chọn trong picker
$productOptions = [];
$categoryOptions = [];
try {
    $categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category', 'list_category']) : 'ecommerce_category';
} catch (Throwable $e) {
    $categoryTable = 'ecommerce_category';
}

if ($categoryTable) {
    $qCat = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY name ASC, id ASC");
    if ($qCat) {
        while ($c = $qCat->fetch_assoc()) {
            $categoryOptions[] = [
                'id' => (int)($c['id'] ?? 0),
                'name' => (string)($c['name'] ?? ''),
            ];
        }
    }
}

try {
    $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
} catch (Throwable $e) {
    $variantTable = 'ecommerce_product_variants';
}

$priceMinExpr = $variantTable
    ? "COALESCE((SELECT MIN(price) FROM `{$variantTable}` v WHERE v.product_id = p.id), 0) AS price_min"
    : '0 AS price_min';

$skuExpr = $variantTable
    ? "COALESCE((SELECT sku_variant FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.sku_variant <> '' ORDER BY v.id ASC LIMIT 1), '') AS sku"
    : "'' AS sku";

$qProducts = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, p.status, {$skuExpr}, p.category_id, {$priceMinExpr} FROM ecommerce_product p ORDER BY p.product_name ASC, p.id DESC");
if ($qProducts) {
    while ($p = $qProducts->fetch_assoc()) {
        $productOptions[] = [
            'id' => (int)($p['id'] ?? 0),
            'name' => (string)($p['product_name'] ?? ''),
            'sku' => (string)($p['sku'] ?? ''),
            'category_id' => (int)($p['category_id'] ?? 0),
            'price' => (int)($p['price_min'] ?? 0),
            'image_url' => (string)($p['image_url'] ?? ''),
            'status' => (int)($p['status'] ?? 0),
        ];
    }
}
?>

<div class="container-fluid py-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <a href="index.php" class="header-icon rounded-3 d-flex align-items-center justify-content-center text-decoration-none" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-camera-reels-fill fs-4"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Video review sản phẩm</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="rsMeta" style="font-size: 0.72rem;">Tổng: 0 video</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Quản lý video review hiển thị tại trang sản phẩm: link YouTube Short, video MP4 upload, thumbnail và trạng thái hiển thị.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Quản lý video review YouTube / MP4 cho sản phẩm.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2 px-3 py-2 shadow-sm" id="rsReload" style="font-size: 0.88rem; font-weight: 600; height: 40px; border-radius: 10px;">
                <i class="bi bi-arrow-clockwise"></i>
                <span class="d-none d-sm-inline">Làm mới</span>
            </button>
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm" id="rsNew" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-plus-lg fs-5"></i>
                <span class="d-none d-sm-inline">Thêm video</span>
                <span class="d-inline d-sm-none">Thêm mới</span>
            </button>
        </div>
    </div>

    <!-- KPI SUMMARY CARDS -->
    <div class="mb-4 grid-4" id="summaryGrid">
        <div class="summary-card active" data-rs-tab="all">
            <div class="d-flex flex-column">
                <span>Tổng số video</span>
                <strong class="mt-1" id="rsKpiAll">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-collection-play-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-rs-tab="active">
            <div class="d-flex flex-column">
                <span>Đang hiển thị</span>
                <strong class="mt-1" id="rsKpiActive">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-eye-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-rs-tab="youtube">
            <div class="d-flex flex-column">
                <span>YouTube Short</span>
                <strong class="mt-1" id="rsKpiYoutube">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-youtube fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-rs-tab="mp4">
            <div class="d-flex flex-column">
                <span>Video MP4</span>
                <strong class="mt-1" id="rsKpiMp4">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-film fs-5"></i>
            </div>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Tìm kiếm video</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" style="left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.88rem; pointer-events:none;"></i>
                        <input type="text" id="rsSearchBox" class="form-control" placeholder="Tìm theo tên sản phẩm, tiêu đề, creator..." style="padding-left:38px !important; border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Trạng thái</label>
                    <select id="rsFilterStatus" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="1">Đang hiển thị</option>
                        <option value="0">Đang ẩn</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Loại video</label>
                    <select id="rsFilterType" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="all">Tất cả loại</option>
                        <option value="youtube">YouTube Short</option>
                        <option value="mp4">Video MP4</option>
                        <option value="none">Chưa gắn video</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Sắp xếp</label>
                    <select id="rsSortOrder" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="id_desc">Mới nhất (Mặc định)</option>
                        <option value="id_asc">Cũ nhất</option>
                        <option value="price_asc">Giá sản phẩm: Thấp → Cao</option>
                        <option value="price_desc">Giá sản phẩm: Cao → Thấp</option>
                        <option value="name_asc">Tên sản phẩm (A-Z)</option>
                        <option value="name_desc">Tên sản phẩm (Z-A)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- DATA TABLE -->
    <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped" id="rsTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:90px;">Video</th>
                        <th style="width:240px;">Sản phẩm</th>
                        <th>Tiêu đề / Creator</th>
                        <th style="width:130px;">Loại</th>
                        <th class="text-center" style="width:90px;">Hiển thị</th>
                        <th class="text-end pe-4" style="width:120px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="rsRows"></tbody>
            </table>
            <div class="text-center text-muted py-5" id="rsEmpty" style="display:none;">
                <i class="bi bi-camera-video text-secondary" style="font-size: 2.5rem; opacity: .5;"></i>
                <div class="mt-2">Chưa có video review nào. Bấm <strong>Thêm video review</strong> để bắt đầu.</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD/EDIT VIDEO -->
<div class="modal fade" id="rsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rsModalTitle"><i class="bi bi-camera-reels me-2 text-primary"></i>Cấu hình video review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rsForm">
                    <input type="hidden" id="rsId" value="0">
                    <input type="hidden" id="rsProductId" value="0">
                    <input type="hidden" id="rsSort" value="0">
                    <input type="hidden" id="rsActive" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold d-block mb-2">Chọn sản phẩm</label>
                        <div class="product-picker">
                            <div class="product-picker-toolbar">
                                <select class="form-select form-select-sm" id="rsCategoryFilter" style="max-width:180px;">
                                    <option value="0">Tất cả danh mục</option>
                                </select>
                                <select class="form-select form-select-sm" id="rsPickerStatus" style="max-width:160px;">
                                    <option value="all">Tất cả trạng thái</option>
                                    <option value="1">Đang bật</option>
                                    <option value="0">Đang tắt</option>
                                </select>
                                <select class="form-select form-select-sm" id="rsPickerPrice" style="max-width:170px;">
                                    <option value="all">Tất cả mức giá</option>
                                    <option value="0-100000">Dưới 100k</option>
                                    <option value="100000-500000">100k – 500k</option>
                                    <option value="500000-1000000">500k – 1tr</option>
                                    <option value="1000000-5000000">1tr – 5tr</option>
                                    <option value="5000000-">Trên 5tr</option>
                                </select>
                                <select class="form-select form-select-sm" id="rsPickerSort" style="max-width:180px;">
                                    <option value="name_asc">Tên (A → Z)</option>
                                    <option value="name_desc">Tên (Z → A)</option>
                                    <option value="price_asc">Giá thấp → cao</option>
                                    <option value="price_desc">Giá cao → thấp</option>
                                    <option value="id_desc">Mới nhất</option>
                                    <option value="id_asc">Cũ nhất</option>
                                </select>
                                <input type="text" class="form-control form-control-sm flex-grow-1" id="rsProductFilterInput" placeholder="Lọc theo tên/SKU" style="min-width:180px;">
                            </div>
                            <div class="product-picker-list" id="rsProductPickerList"></div>
                            <div class="small text-muted mt-2" id="rsProductInfo">Chưa chọn sản phẩm.</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="rsTitle">Tiêu đề video</label>
                            <input type="text" class="form-control" id="rsTitle" placeholder="VD: Đánh giá sơn Rust-Oleum">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="rsCreator">Content Creator</label>
                            <input type="text" class="form-control" id="rsCreator" placeholder="VD: Cris Phan">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold" for="rsYoutube">
                                <i class="bi bi-youtube text-danger me-1"></i>Link YouTube Short <span class="text-muted">(ưu tiên hiển thị)</span>
                            </label>
                            <input type="text" class="form-control" id="rsYoutube" placeholder="https://www.youtube.com/shorts/... hoặc https://youtu.be/...">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">
                                <i class="bi bi-film text-primary me-1"></i>Hoặc upload video MP4 <span class="text-muted">(tối đa 30MB)</span>
                            </label>
                            <input type="file" id="rsVideoFile" accept="video/mp4" class="d-none">
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <button type="button" class="btn btn-outline-secondary" id="rsPickFileBtn"><i class="bi bi-folder2-open me-1"></i>Chọn file MP4</button>
                                <span class="small text-muted" id="rsFileName">Chưa chọn file.</span>
                            </div>
                            <div class="form-text small" id="rsVideoStatus">Hỗ trợ tối đa 30MB. File sẽ được upload tự động sau khi chọn.</div>
                            <input type="hidden" id="rsVideoUrl" value="">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold" for="rsThumb">
                                <i class="bi bi-image text-secondary me-1"></i>Ảnh thumbnail <span class="text-muted">(tùy chọn)</span>
                            </label>
                            <input type="text" class="form-control" id="rsThumb" placeholder="Đường dẫn ảnh hoặc để trống để dùng ảnh sản phẩm">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="rsSave"><i class="bi bi-save me-1"></i>Lưu video</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_admin/ecommerce/ajax/preview.php';
    const PRODUCT_OPTIONS = <?= json_encode($productOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const CATEGORY_OPTIONS = <?= json_encode($categoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const BASE_URL = '<?= h($baseUrl) ?>';
    const $rows = $('#rsRows');
    const $empty = $('#rsEmpty');
    const $meta = $('#rsMeta');
    const modalEl = document.getElementById('rsModal');
    const rsModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    let RS_ITEMS = [];
    let RS_TAB = 'all';

    function esc(str){ return $('<div>').text(String(str || '')).html(); }
    function escapeHtml(str){
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
    function notify(msg, type){
        if (window.toastr && toastr[type || 'info']) toastr[type || 'info'](msg);
        else alert(msg);
    }
    function formatMoney(v){
        const n = parseInt(String(v || '0').replace(/[^0-9]/g, ''), 10) || 0;
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function abs(url){
        if (!url) return '';
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
        if (/^https?:\/\//i.test(url) || url.indexOf('//') === 0) return url;
        return BASE_URL + '/' + String(url).replace(/^\/+/, '');
    }
    function productMap(){
        const m = {};
        (PRODUCT_OPTIONS || []).forEach(p => { m[Number(p.id || 0)] = p; });
        return m;
    }
    const P_MAP = productMap();

    function renderCategorySelect(selectId){
        const el = document.getElementById(selectId);
        if (!el) return;
        let html = '<option value="0">Tất cả danh mục</option>';
        (CATEGORY_OPTIONS || []).forEach(c => {
            const id = Number(c.id || 0);
            const name = escapeHtml(c.name || 'Danh mục');
            if (id > 0) html += `<option value="${id}">${name}</option>`;
        });
        el.innerHTML = html;
    }

    function getSelectedProductId(){
        const v = parseInt($('#rsProductId').val() || '0', 10);
        return Number.isFinite(v) && v > 0 ? v : 0;
    }
    function setSelectedProductId(pid){
        $('#rsProductId').val(pid > 0 ? pid : 0);
        updateProductInfo();
    }
    function updateProductInfo(){
        const pid = getSelectedProductId();
        const $info = $('#rsProductInfo');
        if (!pid){ $info.text('Chưa chọn sản phẩm.'); return; }
        const p = P_MAP[pid];
        $info.html(p
            ? `<i class="bi bi-check-circle-fill text-success me-1"></i>Đã chọn: <strong>${escapeHtml(p.name || 'Sản phẩm')}</strong> <span class="text-muted">(#${pid})</span>`
            : `<i class="bi bi-check-circle-fill text-success me-1"></i>Đã chọn: <strong>#${pid}</strong>`);
    }

    function renderProductPicker(){
        const listEl = document.getElementById('rsProductPickerList');
        if (!listEl) return;
        const selectedId = getSelectedProductId();
        const keyword = String(document.getElementById('rsProductFilterInput')?.value || '').trim().toLowerCase();
        const catId = parseInt(String(document.getElementById('rsCategoryFilter')?.value || '0'), 10) || 0;
        const statusF = String(document.getElementById('rsPickerStatus')?.value || 'all');
        const priceF = String(document.getElementById('rsPickerPrice')?.value || 'all');
        const sortF = String(document.getElementById('rsPickerSort')?.value || 'name_asc');

        let priceMin = 0, priceMax = Infinity;
        if (priceF !== 'all'){
            const parts = priceF.split('-');
            priceMin = Number(parts[0] || 0);
            priceMax = parts[1] === '' || parts[1] == null ? Infinity : Number(parts[1] || 0);
        }

        const list = (PRODUCT_OPTIONS || []).filter(p => {
            const pid = Number(p.id || 0);
            if (pid <= 0) return false;
            if (catId > 0 && Number(p.category_id || 0) !== catId) return false;
            if (statusF !== 'all' && Number(p.status || 0) !== parseInt(statusF, 10)) return false;
            const price = Number(p.price || 0);
            if (price < priceMin || price > priceMax) return false;
            if (!keyword) return true;
            const hay = `${p.name || ''} ${p.sku || ''} ${p.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });
        list.sort((a, b) => {
            switch (sortF){
                case 'name_desc': return String(b.name || '').localeCompare(String(a.name || ''), 'vi');
                case 'price_asc': return Number(a.price || 0) - Number(b.price || 0);
                case 'price_desc':return Number(b.price || 0) - Number(a.price || 0);
                case 'id_desc':   return Number(b.id || 0) - Number(a.id || 0);
                case 'id_asc':    return Number(a.id || 0) - Number(b.id || 0);
                case 'name_asc':
                default:          return String(a.name || '').localeCompare(String(b.name || ''), 'vi');
            }
        });

        if (!list.length){
            listEl.innerHTML = '<div class="product-picker-empty text-muted small text-center py-3">Không có sản phẩm phù hợp.</div>';
            updateProductInfo();
            return;
        }
        listEl.innerHTML = list.map(p => {
            const pid = Number(p.id || 0);
            const name = escapeHtml(p.name || 'Sản phẩm');
            const sku = escapeHtml(p.sku || '—');
            const basePrice = Number(p.price || 0);
            const basePriceText = basePrice > 0 ? formatMoney(basePrice) + 'đ' : '—';
            const isOn = Number(p.status || 0) === 1;
            const statusBadge = isOn
                ? '<span class="badge ms-1" style="font-size:.62rem; padding:2px 6px; border-radius:4px; background-color:rgba(12,76,41,.08); color:var(--theme-primary,#0c4c29); border:1px solid rgba(12,76,41,.18);"><i class="bi bi-eye-fill me-1"></i>Đang bật</span>'
                : '<span class="badge ms-1" style="font-size:.62rem; padding:2px 6px; border-radius:4px; background-color:#fef2f2; color:#dc2626; border:1px solid #fecaca;"><i class="bi bi-eye-slash-fill me-1"></i>Đang tắt</span>';
            return `
                <label class="tree-node-product d-flex align-items-center p-2 mb-2" style="cursor:pointer;">
                    <input type="radio" name="rsProductPick" class="form-check-input me-2 jsRsProductPick" value="${pid}" ${pid === selectedId ? 'checked' : ''}>
                    <div class="flex-grow-1">
                        <div class="fw-semibold d-flex align-items-center flex-wrap" style="font-size:.88rem; gap:4px;">
                            <span>${name}</span>
                            <span class="text-muted small">#${pid}</span>
                            ${statusBadge}
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">SKU: ${sku} · Giá: <span class="fw-semibold">${basePriceText}</span></div>
                    </div>
                </label>
            `;
        }).join('');
        updateProductInfo();
    }

    function resetForm(){
        $('#rsId').val('0');
        $('#rsProductId').val('0');
        $('#rsTitle').val('');
        $('#rsCreator').val('');
        $('#rsYoutube').val('');
        $('#rsVideoFile').val('');
        $('#rsVideoUrl').val('');
        $('#rsThumb').val('');
        $('#rsSort').val('0');
        $('#rsActive').val('1');
        $('#rsVideoStatus').text('Hỗ trợ tối đa 30MB. File sẽ được upload tự động sau khi chọn.');
        $('#rsFileName').text('Chưa chọn file.');
        $('#rsCategoryFilter').val('0');
        $('#rsPickerStatus').val('all');
        $('#rsPickerPrice').val('all');
        $('#rsPickerSort').val('name_asc');
        $('#rsProductFilterInput').val('');
        renderProductPicker();
    }

    function videoKind(item){
        if (item.youtube_url) return 'youtube';
        if (item.video_url) return 'mp4';
        return 'none';
    }

    function videoBadge(kind){
        if (kind === 'youtube') return '<span class="badge text-uppercase" style="font-size:.65rem; padding:2px 6px; border-radius:4px; background-color:#fef2f2; color:#dc2626; border:1px solid #fecaca;"><i class="bi bi-youtube me-1"></i>YouTube</span>';
        if (kind === 'mp4') return '<span class="badge text-uppercase" style="font-size:.65rem; padding:2px 6px; border-radius:4px; background-color:#eff6ff; color:#2563eb; border:1px solid #dbeafe;"><i class="bi bi-film me-1"></i>MP4</span>';
        return '<span class="badge text-uppercase" style="font-size:.65rem; padding:2px 6px; border-radius:4px; background-color:#f1f5f9; color:#64748b; border:1px solid #e2e8f0;">Chưa gắn</span>';
    }

    function itemPrice(it){
        const pid = Number(it.product_id || 0);
        const p = P_MAP[pid];
        return p ? Number(p.price || 0) : 0;
    }
    function itemName(it){
        const pid = Number(it.product_id || 0);
        return String(it.product_name || (P_MAP[pid] && P_MAP[pid].name) || '');
    }

    function getFiltered(){
        const kw = String($('#rsSearchBox').val() || '').trim().toLowerCase();
        const status = String($('#rsFilterStatus').val() || 'all');
        const type = String($('#rsFilterType').val() || 'all');
        const sort = String($('#rsSortOrder').val() || 'id_desc');
        const out = RS_ITEMS.filter(it => {
            const kind = videoKind(it);
            if (RS_TAB === 'active' && Number(it.is_active || 0) !== 1) return false;
            if (RS_TAB === 'youtube' && kind !== 'youtube') return false;
            if (RS_TAB === 'mp4' && kind !== 'mp4') return false;
            if (status !== 'all' && Number(it.is_active || 0) !== parseInt(status, 10)) return false;
            if (type !== 'all' && kind !== type) return false;
            if (kw){
                const hay = `${it.product_name || ''} ${it.title || ''} ${it.creator_name || ''} ${it.product_id || ''}`.toLowerCase();
                if (!hay.includes(kw)) return false;
            }
            return true;
        });
        out.sort((a, b) => {
            switch (sort){
                case 'id_asc':    return Number(a.id || 0) - Number(b.id || 0);
                case 'price_asc': return itemPrice(a) - itemPrice(b);
                case 'price_desc':return itemPrice(b) - itemPrice(a);
                case 'name_asc':  return itemName(a).localeCompare(itemName(b), 'vi');
                case 'name_desc': return itemName(b).localeCompare(itemName(a), 'vi');
                case 'id_desc':
                default:          return Number(b.id || 0) - Number(a.id || 0);
            }
        });
        return out;
    }

    function updateKpis(){
        let all = RS_ITEMS.length, active = 0, yt = 0, mp4 = 0;
        RS_ITEMS.forEach(it => {
            if (Number(it.is_active || 0) === 1) active++;
            const k = videoKind(it);
            if (k === 'youtube') yt++;
            else if (k === 'mp4') mp4++;
        });
        $('#rsKpiAll').text(all);
        $('#rsKpiActive').text(active);
        $('#rsKpiYoutube').text(yt);
        $('#rsKpiMp4').text(mp4);
        $meta.text('Tổng: ' + all + ' video');
    }

    function renderRows(){
        const list = getFiltered();
        if (!list.length){
            $rows.html('');
            $empty.show();
            return;
        }
        $empty.hide();
        const html = list.map(item => {
            const id = Number(item.id || 0);
            const pid = Number(item.product_id || 0);
            const pname = item.product_name || (P_MAP[pid] && P_MAP[pid].name) || ('Sản phẩm #' + pid);
            const pimg = abs(item.product_image || (P_MAP[pid] && P_MAP[pid].image_url) || '');
            const title = item.title || '';
            const creator = item.creator_name || '';
            const active = Number(item.is_active || 0) === 1;
            const thumb = abs(item.thumb_url || item.image_url || '');
            const kind = videoKind(item);
            const thumbHtml = thumb
                ? `<div class="position-relative rounded overflow-hidden border" style="width:64px; height:64px; background:#000;"><img src="${esc(thumb)}" alt="" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'"><div class="position-absolute top-50 start-50 translate-middle d-flex align-items-center justify-content-center rounded-circle" style="width:24px; height:24px; background:rgba(239,68,68,.95);"><i class="bi bi-play-fill text-white"></i></div></div>`
                : `<div class="rounded border d-flex align-items-center justify-content-center bg-light text-secondary" style="width:64px; height:64px;"><i class="bi bi-camera-video fs-4"></i></div>`;
            const productAvatar = pimg
                ? `<img src="${esc(pimg)}" class="rounded border object-fit-cover" style="width:32px; height:32px; flex-shrink:0;" onerror="this.outerHTML='<div class=\\'product-avatar bg-light text-secondary rounded border d-flex align-items-center justify-content-center fw-bold\\' style=\\'width:32px; height:32px; font-size:0.75rem; flex-shrink:0;\\'>SP</div>'">`
                : `<div class="product-avatar bg-light text-secondary rounded border d-flex align-items-center justify-content-center fw-bold" style="width:32px; height:32px; font-size:0.75rem; flex-shrink:0;">SP</div>`;
            return ''
                + '<tr data-id="' + id + '">'
                + '  <td class="ps-3">' + thumbHtml + '</td>'
                + '  <td>'
                + '      <div class="d-flex align-items-center gap-2">'
                + '          ' + productAvatar
                + '          <div class="min-width-0">'
                + '              <div class="fw-semibold text-truncate small" style="max-width:200px;" title="' + esc(pname) + '">' + esc(pname) + '</div>'
                + '              <div class="d-flex align-items-center gap-2 mt-1">'
                + '                  <span class="text-muted" style="font-size:0.72rem;">#' + pid + '</span>'
                + (active
                    ? '<span class="badge" style="font-size:.62rem; padding:2px 6px; border-radius:4px; background-color:rgba(12,76,41,.08); color:var(--theme-primary,#0c4c29); border:1px solid rgba(12,76,41,.18);"><i class="bi bi-eye-fill me-1"></i>Đang bật</span>'
                    : '<span class="badge" style="font-size:.62rem; padding:2px 6px; border-radius:4px; background-color:#fef2f2; color:#dc2626; border:1px solid #fecaca;"><i class="bi bi-eye-slash-fill me-1"></i>Đang tắt</span>')
                + '              </div>'
                + '          </div>'
                + '      </div>'
                + '  </td>'
                + '  <td>'
                + '      <div class="fw-semibold" style="font-size:.88rem;">' + esc(title || '(Chưa đặt tiêu đề)') + '</div>'
                + '      <div class="text-muted small"><i class="bi bi-person me-1"></i>' + esc(creator || 'Chưa rõ creator') + '</div>'
                + '  </td>'
                + '  <td>' + videoBadge(kind) + '</td>'
                + '  <td class="text-center">'
                + '      <div class="form-check form-switch d-inline-block m-0">'
                + '          <input class="form-check-input rs-toggle" type="checkbox" role="switch" data-id="' + id + '" ' + (active ? 'checked' : '') + '>'
                + '      </div>'
                + '  </td>'
                + '  <td class="text-end pe-4">'
                + '      <div class="voucher-actions">'
                + '          <button type="button" class="btn btn-outline-primary rs-edit" data-id="' + id + '" title="Sửa"><i class="bi bi-pencil"></i></button>'
                + '          <button type="button" class="btn btn-outline-danger rs-del" data-id="' + id + '" title="Xóa"><i class="bi bi-trash"></i></button>'
                + '      </div>'
                + '  </td>'
                + '</tr>';
        }).join('');
        $rows.html(html);
    }

    function loadList(){
        $.get(API, { action: 'list' }, function(res){
            if (!res || !res.ok){
                notify(res && res.msg ? res.msg : 'Không tải được danh sách', 'error');
                return;
            }
            RS_ITEMS = res.items || [];
            updateKpis();
            renderRows();
        }, 'json').fail(function(){
            notify('Lỗi tải danh sách', 'error');
        });
    }

    // SUMMARY TAB SWITCH
    $('#summaryGrid').on('click', '.summary-card', function(){
        $('#summaryGrid .summary-card').removeClass('active');
        $(this).addClass('active');
        RS_TAB = $(this).data('rs-tab') || 'all';
        renderRows();
    });

    // FILTERS
    $('#rsSearchBox, #rsFilterStatus, #rsFilterType, #rsSortOrder').on('input change', renderRows);

    // FILE PICKER → trigger hidden input
    $('#rsPickFileBtn').on('click', function(){
        document.getElementById('rsVideoFile').click();
    });
    // Auto-upload ngay khi user chọn file
    $('#rsVideoFile').on('change', function(){
        const f = this.files && this.files[0];
        if (!f){ $('#rsFileName').text('Chưa chọn file.'); return; }
        $('#rsFileName').text(f.name + ' (' + (f.size/1024/1024).toFixed(2) + ' MB)');

        const maxSize = 30 * 1024 * 1024;
        if (f.size > maxSize){ notify('Video tối đa 30MB', 'warning'); return; }
        if (!/\.mp4$/i.test(f.name || '') && String(f.type || '').toLowerCase().indexOf('mp4') === -1){
            notify('Chỉ hỗ trợ định dạng MP4', 'warning');
            return;
        }
        const fd = new FormData();
        fd.append('action', 'upload_video');
        fd.append('file', f);
        $('#rsVideoStatus').html('<i class="bi bi-arrow-clockwise me-1"></i>Đang upload video...');
        $('#rsPickFileBtn').prop('disabled', true);
        $.ajax({
            url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            success: function(res){
                if (res && res.ok && res.url){
                    $('#rsVideoUrl').val(res.url);
                    $('#rsVideoStatus').html('<i class="bi bi-check-circle-fill text-success me-1"></i>Đã upload: ' + esc(res.url));
                    notify('Đã upload video', 'success');
                } else {
                    $('#rsVideoStatus').text(res && res.msg ? res.msg : 'Upload thất bại');
                    notify((res && res.msg) ? res.msg : 'Không upload được video', 'error');
                }
            },
            error: function(){
                $('#rsVideoStatus').text('Lỗi upload video');
                notify('Lỗi kết nối', 'error');
            },
            complete: function(){
                $('#rsPickFileBtn').prop('disabled', false);
            }
        });
    });

    // SAVE
    $('#rsSave').on('click', function(){
        const pid = getSelectedProductId();
        if (!pid){ notify('Vui lòng chọn 1 sản phẩm', 'warning'); return; }
        const payload = {
            action: 'save',
            id: $('#rsId').val() || 0,
            product_id: pid,
            title: $('#rsTitle').val() || '',
            creator_name: $('#rsCreator').val() || '',
            sort_order: $('#rsSort').val() || 0,
            youtube_url: $('#rsYoutube').val() || '',
            video_url: $('#rsVideoUrl').val() || '',
            thumb_url: $('#rsThumb').val() || '',
            is_active: $('#rsActive').val() || 1
        };
        $.post(API, payload, function(res){
            if (!res || !res.ok){
                notify(res && res.msg ? res.msg : 'Không lưu được', 'error');
                return;
            }
            notify(res.msg || 'Đã lưu', 'success');
            if (rsModal) rsModal.hide();
            resetForm();
            loadList();
        }, 'json').fail(function(){ notify('Lỗi kết nối', 'error'); });
    });

    // RELOAD / NEW
    $('#rsReload').on('click', loadList);
    $('#rsNew').on('click', function(){
        $('#rsModalTitle').html('<i class="bi bi-camera-reels me-2 text-primary"></i>Cấu hình video review');
        resetForm();
        if (rsModal) rsModal.show();
    });

    // EDIT
    $rows.on('click', '.rs-edit', function(){
        const id = Number($(this).data('id') || 0);
        if (!id) return;
        const item = RS_ITEMS.find(it => Number(it.id || 0) === id);
        if (!item) return;
        $('#rsModalTitle').html('<i class="bi bi-pencil-square me-2 text-primary"></i>Cấu hình video review');
        resetForm();
        $('#rsId').val(id);
        $('#rsSort').val(item.sort_order != null ? item.sort_order : 0);
        $('#rsActive').val(item.is_active != null ? item.is_active : 1);
        setSelectedProductId(Number(item.product_id || 0));
        $('#rsTitle').val(item.title || '');
        $('#rsCreator').val(item.creator_name || '');
        $('#rsYoutube').val(item.youtube_url || '');
        $('#rsVideoUrl').val(item.video_url || '');
        $('#rsThumb').val(item.thumb_url || '');
        if (item.video_url){
            $('#rsVideoStatus').html('<i class="bi bi-check-circle-fill text-success me-1"></i>Đang dùng video: ' + esc(item.video_url));
        } else {
            $('#rsVideoStatus').text('Chưa chọn file.');
        }
        renderProductPicker();
        if (rsModal) rsModal.show();
    });

    // TOGGLE
    $rows.on('change', '.rs-toggle', function(){
        const id = $(this).data('id');
        $.post(API, { action: 'toggle', id: id }, function(res){
            if (!res || !res.ok){
                notify(res && res.msg ? res.msg : 'Không cập nhật được trạng thái', 'error');
                loadList();
                return;
            }
            notify(res.msg || 'Đã cập nhật', 'success');
            loadList();
        }, 'json').fail(function(){ notify('Lỗi kết nối', 'error'); loadList(); });
    });

    // DELETE
    $rows.on('click', '.rs-del', function(){
        const id = $(this).data('id');
        if (!confirm('Xóa video review này?')) return;
        $.post(API, { action: 'delete', id: id }, function(res){
            if (!res || !res.ok){
                notify(res && res.msg ? res.msg : 'Không xóa được', 'error');
                return;
            }
            notify(res.msg || 'Đã xóa', 'success');
            loadList();
        }, 'json').fail(function(){ notify('Lỗi kết nối', 'error'); });
    });

    // PICKER
    $('#rsCategoryFilter, #rsPickerStatus, #rsPickerPrice, #rsPickerSort').on('change', renderProductPicker);
    $('#rsProductFilterInput').on('input', renderProductPicker);
    $('#rsProductPickerList').on('change', '.jsRsProductPick', function(){
        const pid = parseInt($(this).val() || '0', 10) || 0;
        setSelectedProductId(pid);
    });

    renderCategorySelect('rsCategoryFilter');
    renderProductPicker();
    loadList();
})();
</script>
