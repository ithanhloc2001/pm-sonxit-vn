<?php
require_once __DIR__ . '/../_admin_guard.php';
if (isset($_GET['ajax']) || (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    require_once __DIR__ . '/ajax/product.php';
    exit;
}

// Facebook Catalog: nạp lib + đọc cấu hình hiện tại để prefill modal
require_once __DIR__ . '/lib/facebook_catalog.php';
$fbCfg = fb_catalog_config();

// Google Merchant: nạp lib + đọc cấu hình hiện tại để prefill modal
require_once __DIR__ . '/lib/google_merchant.php';
$gmcCfg = gmc_config();

// Bảng phân loại sản phẩm (biến thể)
$variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
$productCols = listColumns($ithanhloc, 'ecommerce_product');
$productHasSkuColumn = hasCol($productCols, 'sku');
$catCols = listColumns($ithanhloc, 'ecommerce_category');
$categoryThumbColumn = '';
if (hasCol($catCols, 'thumb_image')) {
    $categoryThumbColumn = 'thumb_image';
} elseif (hasCol($catCols, 'image_url')) {
    $categoryThumbColumn = 'image_url';
}

// VAT mặc định từ cấu hình hệ thống (ECOMMERCE_VAT_DEFAULT)
$VAT_DEFAULT = function_exists('app_get_default_vat_percent') ? app_get_default_vat_percent() : 8.0;
?>

<style>
    #table {
        border-collapse: collapse;
        width: 100% !important;
    }
    #table thead th {
        vertical-align: middle;
        white-space: nowrap;
        text-align: left;
        padding-top: 10px;
        padding-bottom: 10px;
        font-size: 0.85rem;
    }
    /* Cột tên sản phẩm cho phép xuống dòng nhưng không bị xoay chữ */
    #table thead th:nth-child(4) {
        white-space: normal;
        min-width: 180px;
    }
    /* Đảm bảo không có transform / writing-mode lạ áp vào header */
    #table thead th,
    #table thead th span {
        writing-mode: horizontal-tb !important;
        transform: none !important;
    }

    #table tbody td {
        vertical-align: middle;
        font-size: 0.86rem;
    }
    #table .thumb-img {
        width: 80px;
        height: 80px;
        object-fit: contain;
        background: #f8fafc;
        border-radius: 8px;
    }

    /* Category drag & drop sorting */
    #catModal #catTbody tr.cat-row { cursor: default; }
    #catModal #catTbody .cat-drag-handle { cursor: grab; }
    #catModal #catTbody .cat-drag-handle:active { cursor: grabbing; }
    #catModal #catTbody tr.cat-dragging { opacity: .5; background: #eef4ff; }
    #catModal #catTbody tr.cat-drop-above td { box-shadow: inset 0 2px 0 0 #0d6efd; }
    #catModal #catTbody tr.cat-drop-below td { box-shadow: inset 0 -2px 0 0 #0d6efd; }

    /* Category modal: prevent oversized thumbs in category list/edit UI */
    #catModal #catTbody .thumb-img {
        width: 44px;
        height: 44px;
        object-fit: cover;
        background: #f8fafc;
        border-radius: 10px;
        flex: 0 0 auto;
    }

    #catModal #c_thumb_preview.thumb-img {
        width: 120px;
        height: 120px;
        object-fit: contain;
        background: #f8fafc;
        border-radius: 12px;
        flex: 0 0 auto;
    }

    #table thead th:first-child,
    #table tbody td:first-child {
        width: 36px;
        text-align: center;
    }

    #table thead th:last-child,
    #table tbody td:last-child {
        white-space: nowrap;
        text-align: center;
    }

    .search-wrapper {
        position: relative;
    }

    .search-input {
        width: 100%;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 8px 40px 8px 36px;
        font-size: .9rem;
        box-shadow: 0 4px 10px rgba(15, 23, 42, .06);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--theme-primary, #0c4c29);
        box-shadow: 0 0 0 1px rgba(12, 76, 41, .25);
    }

    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: .9rem;
    }

    .filter-btn {
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        padding: 6px 12px;
        font-size: .82rem;
        background: #fff;
    }

    /* Tabs danh mục (cat-tab-item) – style giống pill Bootstrap */
    #catTabsContainer {
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    #catTabsContainer::-webkit-scrollbar {
        display: none;
    }

    .cat-tab-item {
        border-radius: 999px;
        padding: 6px 16px;
        font-weight: 500;
        font-size: 0.88rem;
        border: 1px solid #dee2e6;
        background: #fff;
        color: #212529;
        cursor: pointer;
        transition: all 0.18s ease;
        white-space: nowrap;
        flex: 0 0 auto;
    }

    .cat-tab-item:hover {
        background: #f8fafc;
        color: #111827;
        border-color: #cbd5e1;
    }

    .cat-tab-item.active {
        background: var(--theme-primary, #0c4c29);
        color: #fff;
        border-color: var(--theme-primary, #0c4c29);
        box-shadow: 0 2px 6px rgba(12, 76, 41, 0.35);
    }

    /* Standard Design System adjustments */
    .btn {
        border-radius: 12px;
    }

    .form-control,
    .form-select {
        border-radius: 12px;
    }

    .modal-content {
        border-radius: 16px !important;
    }
</style>

<div class="card border-0 shadow-sm rounded-4 p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= h($baseUrl) ?>" class="text-decoration-none text-dark">
                <i class="bi bi-arrow-left-circle-fill text-secondary fs-4"></i>
            </a>
            <span class="fw-bold">Danh sách sản phẩm</span>
            <span id="bulkActions" class="d-none badge bg-primary text-white ms-sm-2 mr-2">
                Đã chọn: <b style="margin-left:4px;color:red" id="bulkCount">0</b>
            </span>
        </div>
        <div class="d-flex gap-2 text-right align-items-center ms-auto flex-wrap">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fbCatalogModal" title="Đồng bộ Facebook Catalog">
                <i class="bi bi-facebook me-1"></i> Facebook
            </button>
            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#gmcModal" title="Đồng bộ Google Merchant Center">
                <i class="bi bi-google me-1"></i> Google
            </button>
            <button class="btn btn-primary btn-sm me-1" onclick="openProd(0)">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </div>
</div>

<!-- ===== Facebook Catalog Modal ===== -->
<div class="modal fade" id="fbCatalogModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-bold"><i class="bi bi-facebook text-primary me-2"></i>Đồng bộ Facebook Catalog</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body pt-0">
        <div id="fbCatalogAlert" class="small mb-2"></div>
        <div class="mb-2">
          <label class="form-label small fw-semibold mb-1">Catalog ID</label>
          <input id="fbCatalogId" class="form-control form-control-sm" placeholder="VD: 2344847722668789" value="<?= h($fbCfg['catalog_id']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold mb-1">Access Token <?= $fbCfg['token'] !== '' ? '<span class="badge bg-success-subtle text-success ms-1">đã lưu</span>' : '' ?></label>
          <input id="fbCatalogToken" type="password" class="form-control form-control-sm" placeholder="<?= $fbCfg['token'] !== '' ? 'Để trống nếu giữ token cũ' : 'Dán access token...' ?>" autocomplete="off">
          <div class="form-text" style="font-size:.72rem;">Token được lưu bảo mật trong site_setting, không hiển thị lại.</div>
        </div>
        <div class="row g-2">
          <div class="col-7">
            <label class="form-label small fw-semibold mb-1">Graph version</label>
            <input id="fbGraphVersion" class="form-control form-control-sm" placeholder="v25.0" value="<?= h($fbCfg['version']) ?>">
          </div>
          <div class="col-5 d-flex align-items-end">
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" id="fbAutoSync" <?= $fbCfg['auto'] ? 'checked' : '' ?>>
              <label class="form-check-label small" for="fbAutoSync">Tự động sync</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-between">
        <button type="button" class="btn btn-outline-success btn-sm" id="fbSyncAllBtn">
          <i class="bi bi-cloud-upload me-1"></i> Đồng bộ tất cả SP
        </button>
        <button type="button" class="btn btn-primary btn-sm" id="fbSaveConfigBtn">
          <i class="bi bi-save me-1"></i> Lưu cấu hình
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Google Merchant Center Modal ===== -->
<div class="modal fade" id="gmcModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-bold"><i class="bi bi-google text-danger me-2"></i>Đồng bộ Google Merchant Center</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body pt-0">
        <div id="gmcAlert" class="small mb-2"></div>

        <!-- Cấu hình (thu gọn) -->
        <div class="mb-3">
          <a class="small fw-semibold text-decoration-none" data-bs-toggle="collapse" href="#gmcConfigBox" role="button">
            <i class="bi bi-gear me-1"></i>Cấu hình kết nối <i class="bi bi-chevron-down"></i>
          </a>
          <div class="collapse <?= $gmcCfg['ok'] ? '' : 'show' ?> mt-2" id="gmcConfigBox">
            <div class="border rounded-3 p-2">
              <div class="mb-2">
                <label class="form-label small fw-semibold mb-1">Merchant ID</label>
                <input id="gmcMerchantId" class="form-control form-control-sm" placeholder="VD: 10678418082" value="<?= h($gmcCfg['merchant_id']) ?>">
              </div>
              <div class="mb-2">
                <label class="form-label small fw-semibold mb-1">Service Account JSON <?= $gmcCfg['sa'] !== null ? '<span class="badge bg-success-subtle text-success ms-1">đã lưu</span>' : '' ?></label>
                <textarea id="gmcSaJson" class="form-control form-control-sm" rows="3" placeholder="<?= $gmcCfg['sa'] !== null ? 'Để trống nếu giữ JSON cũ' : 'Dán nội dung file service-account JSON, hoặc đường dẫn tới file...' ?>" autocomplete="off" style="font-family:monospace;font-size:.72rem;"></textarea>
                <div class="form-text" style="font-size:.72rem;">JSON được lưu bảo mật trong site_setting, không hiển thị lại. Nhớ thêm email service account vào quyền truy cập của tài khoản Merchant.</div>
              </div>
              <div class="row g-2 align-items-end">
                <div class="col-5">
                  <label class="form-label small fw-semibold mb-1">Quốc gia (target)</label>
                  <input id="gmcCountry" class="form-control form-control-sm" placeholder="VN" value="<?= h($gmcCfg['target_country']) ?>">
                </div>
                <div class="col-4">
                  <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="gmcAutoSync" <?= $gmcCfg['auto'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="gmcAutoSync">Tự động sync</label>
                  </div>
                </div>
                <div class="col-3 text-end">
                  <button type="button" class="btn btn-primary btn-sm w-100" id="gmcSaveConfigBtn">
                    <i class="bi bi-save me-1"></i> Lưu
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Chọn danh mục + sản phẩm -->
        <div class="row g-2 align-items-end mb-2">
          <div class="col-sm-7">
            <label class="form-label small fw-semibold mb-1">Danh mục</label>
            <select id="gmcCatSelect" class="form-select form-select-sm">
              <option value="0">— Tất cả danh mục —</option>
            </select>
          </div>
          <div class="col-sm-5 text-sm-end">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gmcReloadBtn">
              <i class="bi bi-arrow-clockwise me-1"></i> Tải sản phẩm
            </button>
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-1">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="gmcSelectAll">
            <label class="form-check-label small fw-semibold" for="gmcSelectAll">Chọn tất cả</label>
          </div>
          <span class="small text-muted">Đã chọn: <b id="gmcSelCount">0</b></span>
        </div>

        <div id="gmcProductList" class="border rounded-3" style="max-height:320px;overflow-y:auto;">
          <div class="text-muted small text-center py-4">Chọn danh mục rồi bấm “Tải sản phẩm”.</div>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-between flex-wrap gap-2">
        <button type="button" class="btn btn-outline-danger btn-sm" id="gmcDeleteSelBtn">
          <i class="bi bi-trash me-1"></i> Xoá khỏi Google (đã chọn)
        </button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-success btn-sm" id="gmcSyncAllBtn">
            <i class="bi bi-cloud-upload me-1"></i> Đồng bộ tất cả
          </button>
          <button type="button" class="btn btn-success btn-sm" id="gmcSyncSelBtn">
            <i class="bi bi-cloud-check me-1"></i> Đồng bộ đã chọn
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="table-responsive d-flex align-items-center mt-2 mb-3" id="catTabsContainer"></div>

<div class="card card-body border-0 shadow-sm rounded-4 d-flex flex-wrap gap-2 mb-3 p-3">
    <div class="d-flex flex-grow-1 me-2">
        <div class="search-wrapper w-100">
            <i class="bi bi-search search-icon"></i>
            <input id="searchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select id="sortBox" class="filter-btn">
            <option value="newest">ID mới nhất</option>
            <option value="oldest">ID cũ nhất</option>
            <option value="id_asc">ID tăng dần</option>
            <option value="id_desc">ID giảm dần</option>
            <option value="name_asc">Tên A-Z</option>
            <option value="name_desc">Tên Z-A</option>
            <option value="price_asc">Giá tăng</option>
            <option value="price_desc">Giá giảm</option>
            <option value="stock_asc">Tồn kho tăng</option>
            <option value="stock_desc">Tồn kho giảm</option>
        </select>
        <select id="statusFilter" class="filter-btn">
            <option value="">Trạng thái: Tất cả</option>
            <option value="true">Trạng thái: ON</option>
            <option value="false">Trạng thái: OFF</option>
        </select>
        <select id="stockFilter" class="filter-btn">
            <option value="">Tồn kho: Tất cả</option>
            <option value="in_stock">Còn hàng</option>
            <option value="out_of_stock">Hết hàng</option>
        </select>
        <label class="filter-btn d-inline-flex align-items-center gap-1 m-0">
            <input type="checkbox" id="filterNoPrice" class="form-check-input m-0">
            Chưa có giá
        </label>
        <label class="filter-btn d-inline-flex align-items-center gap-1 m-0">
            <input type="checkbox" id="filterNoImage" class="form-check-input m-0">
            Chưa có ảnh
        </label>
        <select id="pageLength" class="filter-btn">
            <option value="-1">Tất cả</option>
            <option value="5">Hiển thị: 5</option>
            <option value="10">Hiển thị: 10</option>
            <option value="25">Hiển thị: 25</option>
            <option value="50">Hiển thị: 50</option>
            <option value="100">Hiển thị: 100</option>
            <option value="1000">Hiển thị: 1000</option>
        </select>
    </div>
</div>

<div class="card card-body border-0 shadow-sm rounded-4 table-responsive p-3">
    <div class="d-flex gap-2 flex-wrap mb-2">
        <button class="btn btn-outline-dark btn-sm ms-2" onclick="openCatList()">
            <i class="bi bi-list-ul"></i> Danh mục
        </button>
        <button class="btn btn-primary btn-sm ms-2" onclick="openBulkEdit()">
            <i class="bi bi-pencil"></i> Chỉnh sửa
        </button>
        <button class="btn btn-danger btn-sm ms-2" onclick="delItems()">
            <i class="bi bi-trash"></i>
        </button>
        <button class="btn btn-success btn-sm me-2 d-none" onclick="convertAllWebP()">
            <i class="bi bi-filetype-webp"></i> Tối ưu ảnh
        </button>
    </div>
    <table id="table" class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th><input type="checkbox" id="chkAll"></th>
                <th>ID</th>
                <th style="min-width: 100px; max-width: 200px">THÔNG TIN SẢN PHẨM</th>
                <th style="min-width: 50px; max-width: 100px">TRẠNG THÁI</th>
                <th style="min-width: 50px; max-width: 100px">THAO TÁC</th>
            </tr>
        </thead>
    </table>
</div>

<!-- Modal quản lý danh mục -->
<div class="modal fade" id="catModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="catForm" class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <div>
                    <h5 class="fw-bold mb-0">Danh mục sản phẩm</h5>
                    <small class="text-muted">Quản lý danh sách danh mục, thêm mới và chỉnh sửa</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-success" onclick="openNewCategoryForm()">
                        <i class="bi bi-plus-lg"></i> Thêm danh mục
                    </button>
                </div>

                <div class="border rounded-3 bg-light-subtle p-2 mb-3">
                    <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0">
                            <tbody id="catTbody"></tbody>
                        </table>
                    </div>
                </div>

                <div id="catFormWrapper" class="border rounded-3 p-3 d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div id="catFormTitle" class="fw-semibold">Thêm danh mục</div>
                            <small id="catFormSubtitle" class="text-muted">Điền thông tin danh mục rồi bấm Lưu</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeCategoryForm()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <label class="form-label mb-1">Ảnh thumb danh mục</label>
                    <div class="form-group mt-2 mb-2">
                        <input type="file" id="c_thumb_file" accept="image/*" class="d-none">
                        <?php
                        $catThumbFallbackPath = $site_fallback_logo ?: '';
                        // Ảnh fallback nằm trong uploads/ nên phải route qua media domain (to_abs_url),
                        // dùng baseUrl trực tiếp sẽ 404 ở chế độ vps.
                        $catThumbFallbackUrl = $catThumbFallbackPath !== ''
                            ? (function_exists('to_abs_url') ? to_abs_url((string)$catThumbFallbackPath, (string)$baseUrl) : rtrim((string)$baseUrl, '/') . '/' . ltrim((string)$catThumbFallbackPath, '/'))
                            : '';
                        ?>
                        <img id="c_thumb_preview" src="<?= h($catThumbFallbackUrl) ?>" alt="thumb" style="height:35px" class="xthumb-img bg-light">
                        <span type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#c_thumb_file').click()">
                            <i class="bi bi-image"></i> Chọn ảnh
                        </span>
                    </div>

                    <input type="hidden" name="action" value="save_cat">
                    <input type="hidden" name="id" id="c_id">

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label for="c_name" class="form-label mb-1">Tên danh mục</label>
                            <input name="name" id="c_name" class="form-control form-control-sm" placeholder="Ví dụ: Sơn ngoại thất cao cấp" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="c_slug" class="form-label mb-1">Slug / Đường dẫn</label>
                            <input name="slug" id="c_slug" class="form-control form-control-sm" placeholder="vd: son-ngoai-that-cao-cap">
                            <div class="form-text small">Để trống sẽ tự tạo từ tên.</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <label for="c_desc" class="form-label mb-1">Mô tả</label>
                            <textarea name="description" id="c_desc" rows="3" class="form-control form-control-sm" placeholder="Mô tả ngắn giúp người dùng hiểu danh mục"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-success w-100" type="submit">Lưu danh mục</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal chỉnh sửa hàng loạt -->
<div class="modal fade" id="bulkEditModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="fw-bold">Sửa hàng loạt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">Áp dụng cho <strong id="bulk_count">0</strong> sản phẩm.</div>

                <label class="form-label">Đổi danh mục</label>
                <select id="bulk_cat" class="form-select form-select-sm mb-3">
                    <option value="">-- Giữ nguyên danh mục --</option>
                </select>

                <label class="form-label">Hãng sản xuất</label>
                <select id="bulk_brand" class="form-select form-select-sm mb-3">
                    <option value="">-- Giữ nguyên hãng sản xuất --</option>
                </select>

                <label class="form-label">SKU</label>
                <input id="bulk_sku" class="form-control form-control-sm mb-3" placeholder="Để trống = giữ nguyên SKU">

                <label class="form-label">Trạng thái</label>
                <div class="d-flex flex-wrap gap-2 mb-3" id="bulk_status_quick">
                    <input type="radio" class="btn-check" name="bulk_status" id="bulk_status_keep" value="" checked>
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_status_keep">Giữ nguyên</label>
                    <input type="radio" class="btn-check" name="bulk_status" id="bulk_status_on" value="true">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_status_on">ON</label>
                    <input type="radio" class="btn-check" name="bulk_status" id="bulk_status_off" value="false">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_status_off">OFF</label>
                </div>

                <label class="form-label">Thuế VAT</label>
                <div class="d-flex flex-wrap gap-2" id="bulk_vat_quick">
                    <input type="radio" class="btn-check" name="bulk_vat_enabled" id="bulk_vat_keep" value="" checked>
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_vat_keep">Giữ nguyên</label>
                    <input type="radio" class="btn-check" name="bulk_vat_enabled" id="bulk_vat_on" value="1">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_vat_on">Bật VAT</label>
                    <input type="radio" class="btn-check" name="bulk_vat_enabled" id="bulk_vat_off" value="0">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_vat_off">Tắt VAT</label>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="bulk_vat_set_default">
                    <label class="form-check-label small" for="bulk_vat_set_default">
                        Đặt VAT sản phẩm = VAT mặc định (<?= h($VAT_DEFAULT) ?>%)
                    </label>
                </div>

                <label class="form-label mt-3">Phương thức giao hàng</label>
                <?php $bulkShippingLabelMap = ecommerce_get_shipping_method_label_map($ithanhloc); ?>
                <div class="d-flex flex-wrap gap-2 mb-3" id="bulk_shipping_methods">
                    <input type="checkbox" class="btn-check" id="bulk_ship_all" value="all">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_ship_all">Tất cả</label>
                    <?php foreach ($bulkShippingLabelMap as $shipKey => $shipLabel):
                        $shipKeySafe = h($shipKey);
                        $shipId = 'bulk_ship_' . preg_replace('/[^a-z0-9_\-]/i', '_', (string)$shipKey);
                        $shipIdSafe = h($shipId);
                        $shipLabelSafe = h($shipLabel);
                    ?>
                        <input type="checkbox" class="btn-check bulk-shipping-item" id="<?= $shipIdSafe ?>" value="<?= $shipKeySafe ?>">
                        <label class="btn btn-outline-secondary btn-sm" for="<?= $shipIdSafe ?>"><?= $shipLabelSafe ?></label>
                    <?php endforeach; ?>
                </div>

                <label class="form-label">Hàng đặt trước</label>
                <div class="d-flex flex-wrap gap-2" id="bulk_preorder_quick">
                    <input type="radio" class="btn-check" name="bulk_preorder" id="bulk_preorder_keep" value="" checked>
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_preorder_keep">Giữ nguyên</label>
                    <input type="radio" class="btn-check" name="bulk_preorder" id="bulk_preorder_yes" value="1">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_preorder_yes">Có</label>
                    <input type="radio" class="btn-check" name="bulk_preorder" id="bulk_preorder_no" value="0">
                    <label class="btn btn-outline-secondary btn-sm" for="bulk_preorder_no">Không</label>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="applyBulkEdit()">Áp dụng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="fw-bold">Sửa nhanh sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="qe_id">

                <div class="mb-3">
                    <label for="qe_name" class="form-label">Tiêu đề</label>
                    <input id="qe_name" class="form-control form-control-sm" placeholder="Tên sản phẩm">
                </div>

                <div class="mb-3">
                    <label for="qe_slug" class="form-label">Slug / Đường dẫn</label>
                    <div class="input-group input-group-sm">
                        <input id="qe_slug" class="form-control" placeholder="vd: son-ngoai-that-cao-cap">
                        <button class="btn btn-outline-secondary" type="button" onclick="$('#qe_slug').val(slugifyClient($('#qe_name').val()))" title="Tạo lại slug từ tên">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="form-text small">Để trống sẽ tự tạo từ tên.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="qe_sku" class="form-label">SKU</label>
                        <input id="qe_sku" class="form-control form-control-sm" placeholder="Mã sản phẩm (tuỳ chọn)">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="qe_status" class="form-label">Trạng thái</label>
                        <select id="qe_status" class="form-select form-select-sm">
                            <option value="true">ON - Hiển thị</option>
                            <option value="false">OFF - Ẩn</option>
                        </select>
                    </div>
                </div>

                <div class="mb-0">
                    <label for="qe_cat" class="form-label">Danh mục</label>
                    <select id="qe_cat" class="form-select form-select-sm">
                        <option value="0">-- Giữ nguyên / chưa gán --</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveQuickEdit()">Lưu nhanh</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Ảnh upload được đẩy lên media domain (chế độ vps) nên đường dẫn tương đối
    // phải route qua mediaBaseUrl, không phải baseUrl, nếu không sẽ 404 / ảnh vỡ.
    const MEDIA_BASE_URL = '<?= h(rtrim((string)($mediaBaseUrl ?? $baseUrl), '/')) ?>';
    const resolveUrl = (url) => {
        if (!url) return '';
        url = String(url).trim();
        if (url.indexOf('://') !== -1 || url.startsWith('//')) return url;
        return MEDIA_BASE_URL + '/' + url.replace(/^\/+/, '');
    };

    const fmtNum = (e) => {
        let v = e.value.replace(/\D/g, '');
        e.value = v ? new Intl.NumberFormat('vi-VN').format(v) : '';
    };

    const API_URL = '<?= h($baseUrl) ?>/core_admin/ecommerce/product.php';
    const CAT_THUMB_FIELD = '<?= h($categoryThumbColumn ?? '') ?>';

    // ===== Facebook Catalog modal =====
    (function(){
        const $alert = () => document.getElementById('fbCatalogAlert');
        function fbAlert(msg, ok){
            const el = $alert(); if(!el) return;
            el.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
            el.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle' : 'bi-exclamation-circle') + ' me-1"></i>' + msg;
        }
        const saveBtn = document.getElementById('fbSaveConfigBtn');
        const syncAllBtn = document.getElementById('fbSyncAllBtn');

        if (saveBtn) saveBtn.addEventListener('click', function(){
            saveBtn.disabled = true;
            $.post(API_URL, {
                action: 'fb_save_config',
                catalog_id: document.getElementById('fbCatalogId').value.trim(),
                token: document.getElementById('fbCatalogToken').value.trim(),
                version: document.getElementById('fbGraphVersion').value.trim(),
                auto: document.getElementById('fbAutoSync').checked ? 1 : 0
            }, null, 'json').done(r => {
                fbAlert(r.msg || (r.ok ? 'Đã lưu.' : 'Lỗi'), !!r.ok);
                if (r.ok && window.toastr) toastr.success(r.msg || 'Đã lưu cấu hình');
            }).fail(() => fbAlert('Lỗi kết nối server', false))
              .always(() => saveBtn.disabled = false);
        });

        if (syncAllBtn) syncAllBtn.addEventListener('click', function(){
            if (!confirm('Đồng bộ TẤT CẢ sản phẩm đang bật lên Facebook Catalog?')) return;
            const old = syncAllBtn.innerHTML;
            syncAllBtn.disabled = true;
            syncAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang đồng bộ...';
            $.post(API_URL, { action: 'fb_sync_all' }, null, 'json').done(r => {
                fbAlert(r.msg || (r.ok ? 'Xong' : 'Lỗi'), !!r.ok);
                if (window.toastr) (r.ok ? toastr.success : toastr.warning)(r.msg || 'Hoàn tất');
            }).fail(() => fbAlert('Lỗi kết nối server', false))
              .always(() => { syncAllBtn.disabled = false; syncAllBtn.innerHTML = old; });
        });
    })();

    // ===== Google Merchant modal =====
    (function(){
        function gmcAlert(msg, ok){
            const el = document.getElementById('gmcAlert'); if(!el) return;
            el.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
            el.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle' : 'bi-exclamation-circle') + ' me-1"></i>' + msg;
        }
        const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

        const saveBtn    = document.getElementById('gmcSaveConfigBtn');
        const syncAllBtn = document.getElementById('gmcSyncAllBtn');
        const syncSelBtn = document.getElementById('gmcSyncSelBtn');
        const delSelBtn  = document.getElementById('gmcDeleteSelBtn');
        const catSelect  = document.getElementById('gmcCatSelect');
        const reloadBtn  = document.getElementById('gmcReloadBtn');
        const listEl     = document.getElementById('gmcProductList');
        const selectAll  = document.getElementById('gmcSelectAll');
        const selCountEl = document.getElementById('gmcSelCount');
        const gmcModalEl = document.getElementById('gmcModal');

        // ---- Lưu cấu hình ----
        if (saveBtn) saveBtn.addEventListener('click', function(){
            saveBtn.disabled = true;
            $.post(API_URL, {
                action: 'gmc_save_config',
                merchant_id: document.getElementById('gmcMerchantId').value.trim(),
                sa_json: document.getElementById('gmcSaJson').value.trim(),
                target_country: document.getElementById('gmcCountry').value.trim(),
                auto: document.getElementById('gmcAutoSync').checked ? 1 : 0
            }, null, 'json').done(r => {
                gmcAlert(r.msg || (r.ok ? 'Đã lưu.' : 'Lỗi'), !!r.ok);
                if (r.ok && window.toastr) toastr.success(r.msg || 'Đã lưu cấu hình');
            }).fail(() => gmcAlert('Lỗi kết nối server', false))
              .always(() => saveBtn.disabled = false);
        });

        // ---- Nạp danh mục vào dropdown (1 lần khi mở modal) ----
        let catsLoaded = false;
        function loadCats(){
            if (catsLoaded) return;
            $.get(API_URL, { ajax: 'categories' }, null, 'json').done(r => {
                (r.data || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name || ('Danh mục #' + c.id);
                    catSelect.appendChild(opt);
                });
                catsLoaded = true;
            });
        }
        if (gmcModalEl) gmcModalEl.addEventListener('shown.bs.modal', loadCats);

        // ---- Nạp danh sách sản phẩm + trạng thái sync ----
        function updateSelCount(){
            const n = listEl.querySelectorAll('.gmc-pick:checked').length;
            selCountEl.textContent = n;
        }
        function loadProducts(){
            const catId = catSelect.value || 0;
            listEl.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';
            if (selectAll) selectAll.checked = false;
            $.post(API_URL, { action: 'gmc_products', category_id: catId }, null, 'json').done(r => {
                const list = (r && r.products) || [];
                if (!list.length) { listEl.innerHTML = '<div class="text-muted small text-center py-4">Không có sản phẩm.</div>'; updateSelCount(); return; }
                listEl.innerHTML = list.map(p => {
                    const badge = p.synced
                        ? '<span class="badge bg-success-subtle text-success ms-1" title="' + esc(p.synced_at) + '"><i class="bi bi-check-circle me-1"></i>Đã đồng bộ</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary ms-1">Chưa đồng bộ</span>';
                    const off = p.active ? '' : '<span class="badge bg-warning-subtle text-warning ms-1">Đang ẩn</span>';
                    const img = p.image_url ? '<img src="' + esc(resolveUrl(p.image_url)) + '" style="width:34px;height:34px;object-fit:contain;border-radius:6px;background:#f8fafc;" onerror="this.style.display=\'none\'">' : '';
                    return '<label class="d-flex align-items-center gap-2 px-2 py-1 border-bottom" style="cursor:pointer;">' +
                        '<input type="checkbox" class="form-check-input gmc-pick m-0" value="' + p.id + '">' +
                        img +
                        '<span class="flex-grow-1 small">' + esc(p.product_name) + (p.sku ? ' <span class="text-muted">[' + esc(p.sku) + ']</span>' : '') + '</span>' +
                        off + badge +
                        '</label>';
                }).join('');
                listEl.querySelectorAll('.gmc-pick').forEach(cb => cb.addEventListener('change', () => {
                    updateSelCount();
                    if (!cb.checked && selectAll) selectAll.checked = false;
                }));
                updateSelCount();
            }).fail(() => { listEl.innerHTML = '<div class="text-danger small text-center py-4">Lỗi tải sản phẩm.</div>'; });
        }
        if (reloadBtn) reloadBtn.addEventListener('click', loadProducts);
        if (catSelect) catSelect.addEventListener('change', loadProducts);
        if (selectAll) selectAll.addEventListener('change', function(){
            listEl.querySelectorAll('.gmc-pick').forEach(cb => { cb.checked = selectAll.checked; });
            updateSelCount();
        });

        function selectedIds(){
            return Array.from(listEl.querySelectorAll('.gmc-pick:checked')).map(cb => cb.value);
        }
        function runBatch(btn, action, ids, confirmMsg){
            if (!ids.length) { gmcAlert('Chưa chọn sản phẩm nào.', false); return; }
            if (confirmMsg && !confirm(confirmMsg)) return;
            const old = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...';
            $.post(API_URL, { action: action, ids: ids }, null, 'json').done(r => {
                gmcAlert(r.msg || (r.ok ? 'Xong' : 'Lỗi'), !!r.ok);
                if (window.toastr) (r.ok ? toastr.success : toastr.warning)(r.msg || 'Hoàn tất');
                loadProducts(); // refresh trạng thái sync
            }).fail(() => gmcAlert('Lỗi kết nối server', false))
              .always(() => { btn.disabled = false; btn.innerHTML = old; });
        }

        if (syncSelBtn) syncSelBtn.addEventListener('click', function(){
            runBatch(syncSelBtn, 'gmc_sync_selected', selectedIds(), null);
        });
        if (delSelBtn) delSelBtn.addEventListener('click', function(){
            runBatch(delSelBtn, 'gmc_delete_selected', selectedIds(),
                'Xoá các sản phẩm đã chọn KHỎI Google Merchant? (sản phẩm vẫn giữ trên website)');
        });

        if (syncAllBtn) syncAllBtn.addEventListener('click', function(){
            if (!confirm('Đồng bộ TẤT CẢ sản phẩm thuộc danh mục feed lên Google Merchant Center?')) return;
            const old = syncAllBtn.innerHTML;
            syncAllBtn.disabled = true;
            syncAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang đồng bộ...';
            $.post(API_URL, { action: 'gmc_sync_all' }, null, 'json').done(r => {
                gmcAlert(r.msg || (r.ok ? 'Xong' : 'Lỗi'), !!r.ok);
                if (window.toastr) (r.ok ? toastr.success : toastr.warning)(r.msg || 'Hoàn tất');
                loadProducts();
            }).fail(() => gmcAlert('Lỗi kết nối server', false))
              .always(() => { syncAllBtn.disabled = false; syncAllBtn.innerHTML = old; });
        });
    })();

    let catsData = [];
    let currentCat = 0;
    let colorOptions = [];
    let paymentOptions = [];
    let mediaGallery = [];
    let partnerList = [];

    const PAYMENT_LIBRARY = [
        { key: 'cod', label: 'Thanh toán khi nhận hàng' },
        { key: 'zalopay', label: 'ZaloPay' },
        { key: 'vnpay', label: 'VNPAY' },
        { key: 'momo', label: 'MOMO' }
    ];

    const DEFAULT_PAYMENT_OPTIONS = ['cod', 'vnpay', 'momo'];

    const safeParseArray = (value) => {
        if (Array.isArray(value)) return value;
        if (!value) return [];
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (err) {
            return [];
        }
    };

    const normalizeHex = (value) => {
        if (!value) return '';
        let hex = value.trim();
        if (!hex) return '';
        if (hex[0] !== '#') hex = '#' + hex;
        if (hex.length === 4) {
            hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
        }
        return /^#([0-9A-Fa-f]{6})$/.test(hex) ? hex.toUpperCase() : '';
    };

    const ESCAPE_MAP = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#39;"
    };

    const escapeHtml = (str = '') => str.replace(/[&<>"']/g, ch => ESCAPE_MAP[ch] || ch);

    const sanitizeColorOptions = (list) => list
        .filter(item => item && item.hex)
        .map(item => ({
            name: item.name ? String(item.name) : '',
            code: item.code ? String(item.code) : '',
            hex: normalizeHex(item.hex)
        }))
        .filter(item => item.hex);

    const sanitizePaymentOptions = (list) => {
        const allowed = PAYMENT_LIBRARY.map(o => o.key);
        const aliasMap = {
            bank: 'vnpay',
            transfer: 'vnpay',
            chuyenkhoan: 'vnpay',
            card: 'vnpay',
            installment: 'vnpay',
            zalo: 'zalopay',
            zlp: 'zalopay',
            ewallet: 'momo',
            wallet: 'momo',
            momoqr: 'momo',
            qr: 'momo'
        };
        const unique = [];
        list.forEach(item => {
            const raw = String(item || '').trim().toLowerCase();
            const key = aliasMap[raw] || raw;
            if (allowed.includes(key) && !unique.includes(key)) unique.push(key);
        });
        return unique;
    };

    const sanitizeMediaGallery = (list) => {
        const sanitized = [];
        list.forEach(item => {
            if (!item || !item.url) return;
            const url = String(item.url).trim();
            if (!url) return;
            sanitized.push({
                type: item.type === 'video' ? 'video' : 'image',
                url,
                caption: item.caption ? String(item.caption).trim() : ''
            });
        });
        return sanitized;
    };

    const renderColorOptions = () => {
        if (!colorOptions.length) {
            $('#colorList').html('<div class="color-empty">Chưa có màu nào cho sản phẩm này.</div>');
            return;
        }
        let html = '<div class="row g-2">';
        colorOptions.forEach((c, idx) => {
            html += `
                <div class="col-12 col-lg-6">
                    <div class="color-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="color-chip" style="background:${c.hex}"></div>
                            <div>
                                <div class="fw-bold">${escapeHtml(c.name || 'Chưa đặt tên')}</div>
                                <small>${escapeHtml(c.hex)}${c.code ? ' • ' + escapeHtml(c.code) : ''}</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeColorOption(${idx}, event)"><i class="bi bi-x"></i></button>
                    </div>
                </div>`;
        });
        html += '</div>';
        $('#colorList').html(html);
    };

    const renderPaymentOptions = () => {
        const markup = PAYMENT_LIBRARY.map(opt => {
            const active = paymentOptions.includes(opt.key);
            return `<button type="button" class="payment-pill ${active ? 'active' : ''}" onclick="togglePaymentOption('${opt.key}')">${opt.label}</button>`;
        }).join('');
        $('#paymentOptionChips').html(markup || '<div class="text-muted small">Chưa có phương thức.</div>');
    };

    const loadPartners = () => {
        $.get(API_URL + '?ajax=partners', res => {
            partnerList = Array.isArray(res?.data) ? res.data : [];
            let options = '<option value="">-- Giữ nguyên hãng sản xuất --</option>';
            partnerList.forEach(item => {
                let name = '';
                if (typeof item === 'string') {
                    name = item;
                } else if (item && typeof item === 'object') {
                    name = item.partner_name || item.name || item.title || item.store_name || '';
                }
                const safe = escapeHtml(String(name || '').trim());
                if (!safe) return;
                options += `<option value="${safe}">${safe}</option>`;
            });
            $('#bulk_brand').html(options);
        }, 'json');
    };

    const renderMediaGallery = () => {
        if (!mediaGallery.length) {
            $('#mediaList').html('<div class="color-empty">Chưa thêm ảnh/video mô tả.</div>');
            return;
        }
        let rows = '';
        mediaGallery.forEach((item, idx) => {
            const label = item.type === 'video' ? 'Video' : 'Ảnh';
            rows += `<tr>
                <td><span class="badge bg-${item.type === 'video' ? 'danger' : 'secondary'}">${label}</span></td>
                <td><a href="${escapeHtml(item.url)}" target="_blank" rel="noopener">${escapeHtml(item.url)}</a></td>
                <td>${escapeHtml(item.caption || '')}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMediaItem(${idx})"><i class="bi bi-trash"></i></button></td>
            </tr>`;
        });
        $('#mediaList').html(`<table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Loại</th><th>Đường dẫn</th><th>Ghi chú</th><th class="text-end">Xóa</th></tr></thead><tbody>${rows}</tbody></table>`);
    };

    const hydrateProductMeta = (product) => {
        colorOptions = sanitizeColorOptions(safeParseArray(product ? product.color_options : []));
        paymentOptions = sanitizePaymentOptions(safeParseArray(product ? product.payment_options : []));
        mediaGallery = sanitizeMediaGallery(safeParseArray(product ? product.media_gallery : []));
        if (paymentOptions.length === 0) paymentOptions = [...DEFAULT_PAYMENT_OPTIONS];
        renderColorOptions();
        renderPaymentOptions();
        renderMediaGallery();
        $('#color_options').val(JSON.stringify(colorOptions));
        $('#payment_options').val(JSON.stringify(paymentOptions));
        $('#media_gallery').val(JSON.stringify(mediaGallery));
    };

    window.addColorOption = () => {
        const name = ($('#color_name_input').val() || '').trim();
        const code = ($('#color_code_input').val() || '').trim();
        const hex = normalizeHex($('#color_hex_input').val() || $('#color_picker').val());
        if (!hex) {
            toastr.warning('Nhập mã màu HEX hợp lệ');
            return;
        }
        colorOptions.push({
            name,
            code,
            hex
        });
        renderColorOptions();
        $('#color_name_input, #color_code_input, #color_hex_input').val('');
        $('#color_picker').val(hex);
    };

    window.removeColorOption = (idx, evt) => {
        if (typeof idx === 'undefined' || idx < 0 || idx >= colorOptions.length) return;
        if (!(evt && evt.shiftKey) && !confirm('Xóa màu này khỏi sản phẩm?')) return;
        colorOptions.splice(idx, 1);
        renderColorOptions();
    };

    window.togglePaymentOption = (key) => {
        const allowed = PAYMENT_LIBRARY.find(opt => opt.key === key);
        if (!allowed) return;
        const idx = paymentOptions.indexOf(key);
        if (idx === -1) paymentOptions.push(key);
        else paymentOptions.splice(idx, 1);
        renderPaymentOptions();
    };

    window.addMediaItem = () => {
        const type = $('#media_type').val();
        const url = ($('#media_url').val() || '').trim();
        const caption = ($('#media_caption').val() || '').trim();
        if (!url) {
            toastr.warning('Nhập đường dẫn media trước');
            return;
        }
        if (!/^https?:\/\//i.test(url)) {
            toastr.warning('Đường dẫn cần bắt đầu bằng http hoặc https');
            return;
        }
        mediaGallery.push({
            type: type === 'video' ? 'video' : 'image',
            url,
            caption
        });
        renderMediaGallery();
        $('#media_url, #media_caption').val('');
    };

    window.removeMediaItem = (idx) => {
        if (typeof idx === 'undefined' || idx < 0 || idx >= mediaGallery.length) return;
        if (!confirm('Xóa media này?')) return;
        mediaGallery.splice(idx, 1);
        renderMediaGallery();
    };

    $(function() {
        $('#color_picker').on('input', function() {
            $('#color_hex_input').val($(this).val().toUpperCase());
        });

        const renderBulkCatSelect = () => {
            let options = '<option value="">-- Giữ nguyên danh mục --</option>';
            catsData.forEach(c => {
                options += `<option value="${c.id}">${c.name}</option>`;
            });
            $('#bulk_cat').html(options);
        };

        const loadCats = (cb) => {
            $.get(API_URL + '?ajax=categories', res => {
                catsData = Array.isArray(res.data) ? res.data : [];
                let tabs = `<button class="cat-tab-item active" onclick="filterCat(0, this)">Tất cả</button>`;
                let select = '<option value="0">-- Chọn danh mục --</option>';
                let list = '';
                catsData.forEach(c => {
                    const nameSafe = $('<div>').text(c.name || 'Danh mục').html();
                    let thumbRel = '';
                    if (CAT_THUMB_FIELD && c && typeof c[CAT_THUMB_FIELD] !== 'undefined' && c[CAT_THUMB_FIELD]) {
                        thumbRel = String(c[CAT_THUMB_FIELD] || '');
                    }
                    const thumbHtml = thumbRel ?
                        `<img src="${resolveUrl(thumbRel)}" class="thumb-img me-2" alt="thumb">` :
                        '';
                    const isActive = !(String(c.status) === '0' || String(c.status) === 'false');
                    const toggleHtml = `
                        <div class="form-check form-switch d-inline-flex align-items-center ms-2">
                            <input class="form-check-input" type="checkbox" ${isActive ? 'checked' : ''} onclick="toggleCatStatus(${c.id}, this)">
                        </div>`;
                    tabs += `<button class="cat-tab-item m-2" onclick="filterCat(${c.id}, this)">${nameSafe}</button>`;
                    select += `<option value="${c.id}">${nameSafe}</option>`;
                    list += `<tr data-cat-id="${c.id}" draggable="true" class="cat-row">` +
                        `<td style="width:34px" class="text-center text-muted cat-drag-handle" title="Kéo để sắp xếp"><i class="bi bi-grip-vertical"></i></td>` +
                        `<td>${thumbHtml}${toggleHtml}${nameSafe}</td>` +
                        `<td class="text-end">` +
                            `<button type="button" class="btn btn-sm btn-primary" onclick='editCat(${JSON.stringify(c)})' title="Sửa"><i class="bi bi-pencil"></i></button> ` +
                            `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="dupCat(${c.id})" title="Nhân bản"><i class="bi bi-files"></i></button> ` +
                            `<button type="button" class="btn btn-sm btn-danger" onclick="delCat(${c.id})" title="Xóa"><i class="bi bi-trash"></i></button>` +
                        `</td></tr>`;
                });
                $('#catTabsContainer').html(tabs);
                $('#p_cat').html(select);
                $('#catTbody').html(list);
                initCatSortable();
                renderBulkCatSelect();
                if (cb) cb();
            }, 'json');
        }
        loadCats();
        loadPartners();

        window.table = $('#table').DataTable({
            serverSide: true,
            processing: true,
            autoWidth: false,
            dom: 't<"d-flex justify-content-between align-items-center p-3 flex-column flex-md-row"ip>',
            ajax: {
                url: API_URL + '?ajax=products',
                data: d => {
                    d.search.value = $('#searchBox').val();
                    d.cat_filter = currentCat;
                    d.custom_sort = $('#sortBox').val();
                    d.filter_no_price = $('#filterNoPrice').is(':checked') ? 1 : 0;
                    d.filter_no_image = $('#filterNoImage').is(':checked') ? 1 : 0;
                    d.stock_filter = $('#stockFilter').val();
                    d.status_filter = $('#statusFilter').val();
                }
            },
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
            columns: [{
                    data: null,
                    orderable: false,
                    width: '40px',
                    className: 'text-center',
                    render: r => `<input type="checkbox" class="chk form-check-input" value="${r.id}">`
                },
                {
                    data: null,
                    width: '50px',
                    className: 'text-center text-muted small',
                    render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1
                },
                {
                    data: 'product_name',
                    width: '450px',
                    render: (d, t, r) => {
                        const thumb = r.thumb ? `<img src="${resolveUrl(r.thumb)}" class="thumb-img">` : `<div class="thumb-img bg-light"></div>`;
                        const name = String(d || '');
                        const safeName = $('<div>').text(name).html();
                        const warn = r && (r.shipping_config_missing === true || String(r.shipping_config_missing) === '1' || String(r.shipping_config_missing) === 'true') ?
                            ' <span class="badge bg-danger ms-1" style="font-size:10px">Thiếu ship</span>' :
                            '';

                        let catHtml = '';
                        if (r.category_id) {
                            let c = catsData.find(x => x.id == r.category_id);
                            if (c) catHtml = `<span class="badge bg-light text-dark border small" style="font-size:11px; font-weight:400;">${c.name}</span>`;
                        }

                        const stockHtml = r.kho_text ? `<span class="text-muted small ms-2" style="font-size:11px;">| Kho: ${r.kho_text}</span>` : '';

                        return `
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center justify-content-center">${thumb}</div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold product-name text-truncate" title="${safeName}">${safeName}${warn}</div>
                                    <div class="text-success fw-bold small" style="font-size: 12px;">${r.gia_text || ''}</div>
                                    <div class="mt-1 d-flex align-items-center flex-wrap gap-1">
                                        <span class="badge bg-secondary-subtle text-secondary border-0 small px-2" style="font-size:10px; font-weight:500;">${r.sku || 'N/A'}</span>
                                        ${catHtml}
                                        ${stockHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                },
                {
                    data: 'status',
                    width: '100px',
                    className: 'text-center',
                    render: (d, t, r) => `
                        <div class="form-check form-switch d-inline-flex justify-content-center align-items-center p-0 m-0" style="min-height: auto;">
                            <input class="form-check-input" type="checkbox" role="switch" ${d=='true'?'checked':''} onclick="toggle(${r.id},'${d}', this)" style="width: 2.8rem; height: 1.5rem; cursor: pointer; margin: 0;">
                        </div>
                    `
                },
                {
                    data: null,
                    width: '130px',
                    className: 'text-center',
                    render: r => `
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-danger" title="Sửa nhanh" data-id="${r.id}"><i class="bi bi-lightning-charge"></i> Sửa nhanh </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Nhân bản" onclick="cloneProduct(${r.id})"><i class="bi bi-files"></i> Nhân bản</button>
                            <a class="btn btn-sm btn-light border" title="Sửa đầy đủ" href="<?= h($baseUrl) ?>/admin/product-change?id=${r.id}"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-primary" title="Xem ngoài trang" href="<?= h($baseUrl) ?>/view-product?pid=${r.id}" target="_blank" rel="noopener">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>`
                }
            ],
            pageLength: 10,
            order: [
                [2, 'asc']
            ]
        });

        const reload = () => table.ajax.reload(null, false);
        const updateBulkActions = () => {
            const count = $('.chk:checked').length;
            $('#bulkCount').text(count);
            $('#bulk_count').text(count);
            if (count > 0) {
                $('#bulkActions').removeClass('d-none').addClass('d-flex');
            } else {
                $('#bulkActions').addClass('d-none').removeClass('d-flex');
            }
        };

        window.filterCat = (id, el) => {
            currentCat = id;
            $('.cat-tab-item').removeClass('active');
            $(el).addClass('active');
            reload();
        }

        $('#searchBox').keyup(() => setTimeout(reload, 400));
        $('#sortBox').change(reload);
        $('#statusFilter').change(reload);
        $('#stockFilter').change(reload);
        $('#filterNoPrice').change(reload);
        $('#filterNoImage').change(reload);
        $('#chkAll').change(function() {
            $('.chk').prop('checked', this.checked);
            updateBulkActions();
        });
        $('#pageLength').change(function() {
            table.page.len($(this).val()).draw();
        });
        $(document).on('change', '.chk', function() {
            if (!this.checked) $('#chkAll').prop('checked', false);
            updateBulkActions();
        });
        table.on('draw', function() {
            $('#chkAll').prop('checked', false);
            updateBulkActions();
        });

        const fillQuickEditCats = () => {
            const $sel = $('#qe_cat');
            if (!$sel.length) return;
            let options = '<option value="0">-- Giữ nguyên / chưa gán --</option>';
            catsData.forEach(c => {
                options += `<option value="${c.id}">${c.name}</option>`;
            });
            $sel.html(options);
        };

        let prevQeName = '';
        const openQuickEditInternal = (product) => {
            if (!product) return;
            $('#qe_id').val(product.id || '');
            const name = product.product_name || '';
            $('#qe_name').val(name);
            $('#qe_slug').val(product.slug || '');
            $('#qe_sku').val(product.sku || '');
            $('#qe_status').val((product.status === 'false' || product.status === '0') ? 'false' : 'true');
            const catId = parseInt(product.category_id || 0, 10) || 0;
            fillQuickEditCats();
            $('#qe_cat').val(String(catId));

            prevQeName = name;

            const modalEl = document.getElementById('quickEditModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        };

        $('#qe_name').on('input', function() {
            const name = $(this).val().trim();
            const slugInput = $('#qe_slug');
            const currentSlug = slugInput.val().trim();
            // Tự động cập nhật slug nếu slug đang trống hoặc khớp với slug của tên cũ (người dùng chưa sửa slug thủ công)
            if (!currentSlug || currentSlug === slugifyClient(prevQeName)) {
                slugInput.val(slugifyClient(name));
            }
            prevQeName = name;
        });

        $(document).on('click', 'button[data-id][title="Sửa nhanh"]', function() {
            const id = parseInt($(this).data('id') || 0, 10);
            if (!id) return;
            $.get(API_URL + '?ajax=product_detail&pid=' + id, res => {
                if (res && res.ok && res.product) {
                    openQuickEditInternal(res.product);
                } else {
                    toastr.error(res && res.msg ? res.msg : 'Không tải được sản phẩm');
                }
            }, 'json').fail(() => {
                toastr.error('Lỗi kết nối');
            });
        });

        window.countLines = (el) => {
            let val = $(el).val();
            $('#desc_count').text((val ? val.split(/\r\n|\r|\n/).length : 0) + ' dòng');
        }

        window.openProd = (r) => {
            if (r && r.id) {
                window.location.href = '<?= h($baseUrl) ?>/admin/product-change?id=' + r.id;
            } else {
                window.location.href = '<?= h($baseUrl) ?>/admin/product-change';
            }
        }

        window.cloneProduct = (id) => {
            id = parseInt(id || 0, 10);
            if (!id) {
                toastr.warning('Thiếu ID sản phẩm để nhân bản');
                return;
            }
            if (!confirm('Nhân bản sản phẩm này thành một sản phẩm mới?')) return;
            $.post(API_URL, {
                action: 'clone_product',
                id: id
            }, function(res) {
                if (res && res.ok) {
                    toastr.success('Đã nhân bản sản phẩm');
                    if (res.new_id) {
                        window.location.href = '<?= h($baseUrl) ?>/admin/product-change?id=' + res.new_id;
                    } else {
                        table.ajax.reload(null, false);
                    }
                } else {
                    toastr.error(res && res.msg ? res.msg : 'Không nhân bản được sản phẩm');
                }
            }, 'json').fail(function() {
                toastr.error('Lỗi kết nối khi nhân bản sản phẩm');
            });
        }

        window.openCatList = () => {
            loadCats(() => {
                closeCategoryForm();
                new bootstrap.Modal('#catModal').show();
            });
        }

        window.editCat = (c) => {
            $('#c_id').val(c.id);
            $('#c_name').val(c.name || '');
            $('#c_slug').val(c.slug || '');
            $('#c_desc').val(c.description || '');
            const isActive = !(String(c.status) === '0' || String(c.status) === 'false');
            $('#c_status').prop('checked', isActive);
            let thumbRel = '';
            if (CAT_THUMB_FIELD && c && typeof c[CAT_THUMB_FIELD] !== 'undefined' && c[CAT_THUMB_FIELD]) {
                thumbRel = String(c[CAT_THUMB_FIELD] || '');
            }
            const thumbUrl = thumbRel ? resolveUrl(thumbRel) : '<?= h($catThumbFallbackUrl) ?>';
            $('#c_thumb_preview').attr('src', thumbUrl);
            $('#catFormTitle').text('Chỉnh sửa danh mục');
            $('#catFormSubtitle').text('Cập nhật thông tin rồi bấm Lưu');
            $('#catFormWrapper').removeClass('d-none');
        }

        $('#catForm').submit(function(e) {
            e.preventDefault();
            const name = ($('#c_name').val() || '').trim();
            if (!name) {
                toastr.warning('Vui lòng nhập tên danh mục');
                return;
            }
            let slug = ($('#c_slug').val() || '').trim();
            if (!slug && typeof slugifyClient === 'function') {
                slug = slugifyClient(name);
                $('#c_slug').val(slug);
            }
            const formData = $(this).serializeArray();
            const payload = {};
            formData.forEach(it => {
                payload[it.name] = it.value;
            });
            payload.status = $('#c_status').is(':checked') ? '1' : '0';
            $.ajax({
                url: API_URL,
                type: 'POST',
                data: payload,
                dataType: 'json'
            }).done(function(res) {
                if (res && res.ok) {
                    toastr.success('Đã lưu danh mục thành công');
                    $('#c_id').val('');
                    $('#catForm')[0].reset();
                    $('#c_status').prop('checked', true);
                    loadCats(reload);
                } else {
                    toastr.error(res && res.msg ? res.msg : 'Không lưu được danh mục');
                }
            }).fail(function(xhr, status, err) {
                let msg = 'Lỗi kết nối khi lưu danh mục';
                if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed && parsed.msg) {
                            msg = parsed.msg;
                        }
                    } catch (e) {
                        console.error('save_cat raw response:', xhr.responseText);
                    }
                }
                toastr.error(msg);
            });
        });

        window.delCat = (id) => {
            if (confirm('Xóa?')) $.post(API_URL, {
                action: 'del_cat',
                id: id
            }, res => {
                if (res.ok) loadCats(reload);
                else toastr.error(res.msg);
            }, 'json');
        }

        // Nhân bản danh mục
        window.dupCat = (id) => {
            if (!confirm('Nhân bản danh mục này? Bản sao sẽ ở trạng thái Ẩn để bạn kiểm tra trước.')) return;
            $.post(API_URL, { action: 'dup_cat', id: id }, res => {
                if (res && res.ok) {
                    if (window.toastr) toastr.success(res.msg || 'Đã nhân bản danh mục');
                    loadCats(reload);
                } else {
                    if (window.toastr) toastr.error(res && res.msg ? res.msg : 'Không nhân bản được danh mục');
                }
            }, 'json').fail(() => { if (window.toastr) toastr.error('Lỗi kết nối khi nhân bản danh mục'); });
        }

        // Kéo-thả sắp xếp danh mục (HTML5 native drag & drop)
        let _catDragEl = null;
        window.initCatSortable = () => {
            const tbody = document.getElementById('catTbody');
            if (!tbody) return;

            tbody.querySelectorAll('tr.cat-row').forEach(row => {
                row.addEventListener('dragstart', e => {
                    _catDragEl = row;
                    row.classList.add('cat-dragging');
                    if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', row.dataset.catId || ''); } catch (_) {} }
                });
                row.addEventListener('dragend', () => {
                    row.classList.remove('cat-dragging');
                    _catDragEl = null;
                    tbody.querySelectorAll('.cat-drop-above,.cat-drop-below').forEach(r => r.classList.remove('cat-drop-above', 'cat-drop-below'));
                });
                row.addEventListener('dragover', e => {
                    e.preventDefault();
                    if (!_catDragEl || _catDragEl === row) return;
                    const rect = row.getBoundingClientRect();
                    const after = (e.clientY - rect.top) > rect.height / 2;
                    row.classList.toggle('cat-drop-below', after);
                    row.classList.toggle('cat-drop-above', !after);
                });
                row.addEventListener('dragleave', () => row.classList.remove('cat-drop-above', 'cat-drop-below'));
                row.addEventListener('drop', e => {
                    e.preventDefault();
                    if (!_catDragEl || _catDragEl === row) return;
                    const rect = row.getBoundingClientRect();
                    const after = (e.clientY - rect.top) > rect.height / 2;
                    row.classList.remove('cat-drop-above', 'cat-drop-below');
                    if (after) row.after(_catDragEl);
                    else row.before(_catDragEl);
                    persistCatOrder();
                });
            });
        };

        function persistCatOrder() {
            const ids = Array.from(document.querySelectorAll('#catTbody tr.cat-row'))
                .map(r => parseInt(r.dataset.catId || '0', 10))
                .filter(v => v > 0);
            if (!ids.length) return;
            $.post(API_URL, { action: 'reorder_cats', ids: ids }, res => {
                if (res && res.ok) { if (window.toastr) toastr.success(res.msg || 'Đã lưu thứ tự'); }
                else { if (window.toastr) toastr.error(res && res.msg ? res.msg : 'Không lưu được thứ tự'); loadCats(); }
            }, 'json').fail(() => { if (window.toastr) toastr.error('Lỗi kết nối khi lưu thứ tự'); loadCats(); });
        }

        window.toggle = (id, s, el) => {
            $.post(API_URL, {
                action: 'toggle',
                id: id,
                s: s
            }, function(res) {
                if (res && res.ok) {
                    toastr.success('Cập nhật trạng thái thành công');
                    // Cập nhật lại thuộc tính onclick để lần nhấn sau gửi trạng thái mới nhất
                    const nextS = (s === 'true' ? 'false' : 'true');
                    if (el) $(el).attr('onclick', `toggle(${id}, '${nextS}', this)`);
                } else {
                    toastr.error(res.msg || 'Lỗi cập nhật');
                    if (el) el.checked = (s === 'true'); // Revert
                }
            }, 'json').fail(function() {
                toastr.error('Lỗi kết nối máy chủ');
                if (el) el.checked = (s === 'true'); // Revert
            });
        };

        window.delItems = () => {
            let ids = $('.chk:checked').map((i, e) => e.value).get();
            if (!ids.length) {
                toastr.warning('Chọn sản phẩm trước');
                return;
            }
            if (confirm(`Xóa ${ids.length} sản phẩm đã chọn?`)) $.post(API_URL, {
                action: 'del_items',
                ids: ids
            }, res => {
                reload();
            }, 'json');
        }

        window.openBulkEdit = () => {
            const ids = $('.chk:checked').map((i, e) => e.value).get();
            if (!ids.length) {
                toastr.warning('Chọn sản phẩm trước');
                return;
            }
            $('#bulk_count').text(ids.length);
            $('#bulk_cat').val('');
            $('#bulk_brand').val('');
            $('#bulk_sku').val('');
            $('#bulk_status_keep').prop('checked', true);
            $('#bulk_vat_keep').prop('checked', true);
            $('#bulk_preorder_keep').prop('checked', true);
            $('#bulk_ship_all').prop('checked', false);
            $('.bulk-shipping-item').prop('checked', false).prop('disabled', false);
            $('#bulk_vat_set_default').prop('checked', false);
            new bootstrap.Modal('#bulkEditModal').show();
        };

        const syncBulkShippingAll = () => {
            const isAll = $('#bulk_ship_all').is(':checked');
            if (isAll) {
                $('.bulk-shipping-item').prop('checked', true).prop('disabled', true);
            } else {
                $('.bulk-shipping-item').prop('disabled', false);
            }
        };
        $(document).on('change', '#bulk_ship_all', syncBulkShippingAll);

        window.applyBulkEdit = () => {
            const ids = $('.chk:checked').map((i, e) => e.value).get();
            if (!ids.length) {
                toastr.warning('Chọn sản phẩm trước');
                return;
            }
            const catId = $('#bulk_cat').val();
            const brand = ($('#bulk_brand').val() || '').trim();
            const sku = ($('#bulk_sku').val() || '').trim();
            const status = $('input[name="bulk_status"]:checked').val() || '';
            const vatEnabled = $('input[name="bulk_vat_enabled"]:checked').val() || '';
            const vatSetDefault = $('#bulk_vat_set_default').is(':checked') ? '1' : '';

            let shippingKeys = [];
            if ($('#bulk_ship_all').is(':checked')) {
                shippingKeys = $('.bulk-shipping-item').map((i, e) => String(e.value || '').trim()).get().filter(Boolean);
            } else {
                shippingKeys = $('.bulk-shipping-item:checked').map((i, e) => String(e.value || '').trim()).get().filter(Boolean);
            }
            const shippingMethods = shippingKeys.length ? JSON.stringify(shippingKeys) : '';

            const preorderEnabled = $('input[name="bulk_preorder"]:checked').val() || '';

            if (!catId && !brand && !sku && !status && vatEnabled === '' && !vatSetDefault && !shippingMethods && preorderEnabled === '') {
                toastr.warning('Chọn ít nhất một thay đổi');
                return;
            }
            $.post(API_URL, {
                action: 'bulk_update',
                ids: ids,
                category_id: catId,
                manufacturer: brand,
                sku: sku,
                status: status,
                vat_enabled: vatEnabled,
                vat_set_default: vatSetDefault,
                shipping_methods: shippingMethods,
                preorder_enabled: preorderEnabled
            }, res => {
                if (res.ok) {
                    toastr.success('Đã cập nhật');
                    reload();
                    const modalEl = document.getElementById('bulkEditModal');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                } else toastr.error(res.msg || 'Không cập nhật được');
            }, 'json').fail(() => {
                toastr.error('Lỗi kết nối');
            });
        };

        // [1] WEBP
        window.convertAllWebP = () => {
            if (confirm('Tối ưu hóa toàn bộ ảnh về WebP?')) {
                $.post(API_URL, {
                    action: 'convert_all_webp'
                }, res => {
                    if (res.ok) {
                        toastr.success(res.msg);
                        reload();
                    }
                }, 'json');
            }
        }

        // Helper JS: tạo slug tiếng Việt không dấu (client, tương thích pm_slugify)
        function slugifyClient(text) {
            if (!text) return '';
            let slug = text.toString().toLowerCase().trim();
            // Handle special case for 'đ' which NFD doesn't split
            slug = slug.replace(/đ/g, 'd');
            // Remove diacritics
            slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            // Replace non-alphanumeric with dashes
            slug = slug.replace(/[^a-z0-9]+/g, '-');
            // Clean up dashes
            return slug.replace(/-+/g, '-').replace(/^-+|-+$/g, '') || 'san-pham';
        }

        window.saveQuickEdit = () => {
            const id = parseInt($('#qe_id').val() || 0, 10);
            const name = ($('#qe_name').val() || '').trim();
            let slug = ($('#qe_slug').val() || '').trim();
            let sku = ($('#qe_sku').val() || '').trim();
            const status = $('#qe_status').val() || 'true';
            const catId = parseInt($('#qe_cat').val() || 0, 10) || 0;
            if (!id || !name) {
                toastr.warning('Thiếu ID hoặc tên sản phẩm');
                return;
            }
            // Nếu chưa nhập slug thì tự tạo từ tên (tiếng Việt không dấu)
            if (!slug) {
                slug = slugifyClient(name);
                $('#qe_slug').val(slug);
            }
            // SKU luôn được chuẩn hoá viết hoa
            if (sku) {
                sku = sku.toUpperCase();
                $('#qe_sku').val(sku);
            }
            $.post(API_URL, {
                action: 'save_product_quick',
                id: id,
                product_name: name,
                slug: slug,
                sku: sku,
                status: status,
                category_id: catId
            }, res => {
                if (res && res.ok) {
                    toastr.success('Đã lưu nhanh sản phẩm');
                    const modalEl = document.getElementById('quickEditModal');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                    reload();
                } else {
                    toastr.error(res && res.msg ? res.msg : 'Không lưu được sản phẩm');
                }
            }, 'json').fail(() => {
                toastr.error('Lỗi kết nối');
            });
        };

    });

    // Bật / tắt nhanh trạng thái danh mục từ modal Danh mục
    function toggleCatStatus(id, el) {
        const $cb = $(el);
        const newStatus = $cb.is(':checked') ? '1' : '0';
        if (!id) {
            return;
        }
        $.post(API_URL, {
            action: 'toggle_cat',
            id: id,
            status: newStatus
        }, function(res) {
            if (!res || !res.ok) {
                toastr.error(res && res.msg ? res.msg : 'Không cập nhật được trạng thái danh mục');
                $cb.prop('checked', !$cb.is(':checked'));
            } else {
                $.get(API_URL + '?ajax=categories', function(r) {
                    if (r && Array.isArray(r.data)) {
                        catsData = r.data;
                    }
                }, 'json');
            }
        }, 'json').fail(function() {
            toastr.error('Lỗi kết nối khi cập nhật trạng thái danh mục');
            $cb.prop('checked', !$cb.is(':checked'));
        });
    }

    // Mở form thêm danh mục mới trong modal Danh mục
    function openNewCategoryForm() {
        $('#c_id').val('0');
        $('#c_name').val('');
        $('#c_slug').val('');
        $('#c_desc').val('');
        $('#c_status').prop('checked', true);
        $('#c_thumb_preview').attr('src', '<?= h($catThumbFallbackUrl) ?>');
        $('#catFormTitle').text('Thêm danh mục');
        $('#catFormSubtitle').text('Điền thông tin danh mục rồi bấm Lưu');
        $('#catFormWrapper').removeClass('d-none');
    }

    // Ẩn form danh mục (khi đóng hoặc mở lại modal)
    function closeCategoryForm() {
        $('#catFormWrapper').addClass('d-none');
    }

    // Upload ảnh thumb danh mục khi chọn file, chỉ áp dụng cho danh mục đã lưu (id > 0)
    $(document).on('change', '#c_thumb_file', function() {
        const $file = $(this);
        const id = parseInt($('#c_id').val() || '0', 10);
        if (!id) {
            toastr.warning('Vui lòng lưu danh mục trước, sau đó mới upload ảnh.');
            $file.val('');
            return;
        }
        if (!this.files || !this.files.length) return;
        const fd = new FormData();
        fd.append('action', 'up_cat_thumb');
        fd.append('id', id);
        fd.append('img', this.files[0]);
        $.ajax({
            url: API_URL,
            type: 'POST',
            data: fd,
            contentType: false,
            processData: false,
            dataType: 'json'
        }).done(function(res) {
            $file.val('');
            if (res && res.ok && res.url) {
                $('#c_thumb_preview').attr('src', resolveUrl(res.url));
                toastr.success('Đã cập nhật ảnh danh mục');
            } else {
                toastr.error(res && res.msg ? res.msg : 'Không cập nhật được ảnh danh mục');
            }
        }).fail(function() {
            $file.val('');
            toastr.error('Lỗi kết nối khi upload ảnh danh mục');
        });
    });
</script>
