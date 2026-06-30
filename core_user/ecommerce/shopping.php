
<div class="row">
    <div class="col-12 col-md-2">
        <aside class="filter-panel">
            <!-- <div class="filter-head"><i class="bi bi-funnel"></i> Bộ lọc tìm kiếm</div> -->
            <div class="filter-tabs" role="tablist" aria-label="Bộ lọc">
                <button type="button" class="filter-tab is-active" data-section="status">Trạng thái</button>
                <button type="button" class="filter-tab" data-section="cat">Danh mục</button>
                <button type="button" class="filter-tab" data-section="brand">Hãng</button>
                <button type="button" class="filter-tab" data-section="price">Giá</button>
                <button type="button" class="filter-tab" data-section="sort">Sắp xếp</button>
                <button type="button" class="filter-tab" data-section="rating">Đánh giá</button>
            </div>
            <div class="filter-section is-active" data-section="status">
                <div class="filter-group">
                    <div class="filter-title">Ưu tiên cho bạn</div>
                    <label class="filter-option"><input type="checkbox" id="stockOnly" checked> Còn hàng</label>
                    <label class="filter-option"><input type="checkbox" id="promoOnly"> Khuyến mãi</label>
                </div>
            </div>
            <div class="filter-section" data-section="cat">
                <div class="filter-group">
                    <div class="filter-title">Danh mục</div>
                    <div id="filterCategories"></div>
                </div>
            </div>
            <div class="filter-section" data-section="brand">
                <div class="filter-group">
                    <div class="filter-title">Hãng đối tác</div>
                    <div id="filterBrands"></div>
                </div>
            </div>
            <div class="filter-section" data-section="price">
                <div class="filter-group">
                    <div class="filter-title">Khoảng giá</div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="priceMin" class="form-control form-control-sm" placeholder="Từ" style="border-radius: 4px;" inputmode="numeric">
                        <span class="text-muted">-</span>
                        <input type="text" id="priceMax" class="form-control form-control-sm" placeholder="Đến" style="border-radius: 4px;" inputmode="numeric">
                    </div>
                </div>
            </div>
            <div class="filter-section" data-section="sort">
                <div class="filter-group mt-3">
                    <div class="filter-title">Sắp xếp</div>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="newest" checked> Mới nhất</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="price_asc"> Giá thấp</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="price_desc"> Giá cao</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="name_asc"> Tên A-Z</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="name_desc"> Tên Z-A</label>
                </div>
            </div>
            <div class="filter-section" data-section="rating">
                <div class="filter-group">
                    <div class="filter-title">Theo đánh giá</div>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="5">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="4">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="3">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="2">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="1">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i></span>
                    </label>
                </div>
            </div>
            <div class="filter-actions">
                <button class="filter-btn primary" type="button" id="filterApplyBtn">Áp dụng</button>
                <button class="filter-btn" type="button" id="filterClearBtn">Xóa tất cả</button>
            </div>
        </aside>
    </div>
    <div class="col-12 col-md-10">
        <h1 class="fs-4 fw-bold mb-3"><?= h(isset($currentCategoryName) && $currentCategoryName !== '' ? $currentCategoryName : 'Mua sắm & Sản phẩm') ?></h1>
        <section class="_card _border-0 _shadow-sm product-panel">
        <div class="_card-body">
            <!-- Bộ lọc -->
            <div class="filter-h-wrapper p-2">
                <!-- Desktop Filter Bar -->
                <div class="desktop-filter-bar d-none d-md-block">
                    <div class="filter-h-section adv-filter-section">
                        <div class="adv-filter-header">
                            <div class="filter-h-title mb-0">Bộ lọc nâng cao</div>
                            <button type="button" class="adv-filter-toggle" id="advFilterToggle" aria-expanded="false" aria-controls="advFilterCollapsible">
                                <i class="bi bi-sliders2"></i>
                                <span class="adv-toggle-label">Hiện bộ lọc</span>
                                <i class="bi bi-chevron-down adv-toggle-caret"></i>
                            </button>
                        </div>
                        <div class="search-wrapper adv-search-wrapper mt-2">
                            <i class="bi bi-search search-icon"></i>
                            <input id="searchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
                        </div>
                        <!-- Vùng có thể thu gọn: ẩn mặc định trên PC, bung khi bấm nút "Bộ lọc nâng cao" -->
                        <div class="adv-filter-collapsible" id="advFilterCollapsible">
                        <div class="adv-filter-collapsible-inner">
                        <div class="filter-h-list" id="h-filter-criteria">
                            <!-- Main Multi-Column Filter Popup matching reference image -->
                            <div class="main-filter-popup-wrapper">
                                <div class="filter-h-item dropdown highlight" id="h-main-filter-btn"><i class="bi bi-funnel-fill"></i> Bộ lọc</div>
                                <div class="main-filter-popup" id="mainFilterPopup">
                                    <div class="main-filter-grid">
                                        <!-- Column 1: Không gian sử dụng -->
                                        <div class="main-filter-col">
                                            <div class="main-filter-col-title">Không gian sử dụng</div>
                                            <div class="main-filter-pills" id="popup-space-list">
                                                <span class="filter-pill selected" data-value="all">Tất cả</span>
                                                <span class="filter-pill" data-value="Nội thất">Nội thất</span>
                                                <span class="filter-pill" data-value="Ngoại thất">Ngoại thất</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Column 2: Vị trí thi công -->
                                        <div class="main-filter-col">
                                            <div class="main-filter-col-title">Vị trí thi công</div>
                                            <div class="main-filter-pills" id="popup-positions-list">
                                                <span class="filter-pill selected" data-value="all">Tất cả</span>
                                                <span class="filter-pill" data-value="Tường">Tường</span>
                                                <span class="filter-pill" data-value="Cửa">Cửa</span>
                                                <span class="filter-pill" data-value="Trần">Trần</span>
                                                <span class="filter-pill" data-value="Viền">Viền</span>
                                                <span class="filter-pill" data-value="Cửa sổ">Cửa sổ</span>
                                                <span class="filter-pill" data-value="Sàn">Sàn</span>
                                                <span class="filter-pill" data-value="Mái tôn">Mái tôn</span>
                                                <span class="filter-pill" data-value="Khác">Khác</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Column 3: Nhu cầu sử dụng -->
                                        <div class="main-filter-col">
                                            <div class="main-filter-col-title">Nhu cầu sử dụng</div>
                                            <div class="main-filter-pills" id="popup-needs-list">
                                                <span class="filter-pill selected" data-value="all">Tất cả</span>
                                                <span class="filter-pill" data-value="Phòng khách">Phòng khách</span>
                                                <span class="filter-pill" data-value="Phòng trẻ em">Phòng trẻ em</span>
                                                <span class="filter-pill" data-value="Phòng tắm / WC">Phòng tắm / WC</span>
                                                <span class="filter-pill" data-value="Phòng ngủ">Phòng ngủ</span>
                                                <span class="filter-pill" data-value="Phòng bếp">Phòng bếp</span>
                                                <span class="filter-pill" data-value="Mặt tiền / ngoài trời">Mặt tiền / ngoài trời</span>
                                                <span class="filter-pill" data-value="Chống thấm">Chống thấm</span>
                                                <span class="filter-pill" data-value="Chống ẩm mốc">Chống ẩm mốc</span>
                                                <span class="filter-pill" data-value="Chống bám bụi">Chống bám bụi</span>
                                                <span class="filter-pill" data-value="Chống kiềm">Chống kiềm</span>
                                                <span class="filter-pill" data-value="Chống UV">Chống UV</span>
                                                <span class="filter-pill" data-value="Chống rỉ sét">Chống rỉ sét</span>
                                                <span class="filter-pill" data-value="Chống nứt">Chống nứt</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="main-filter-footer">
                                        <button class="btn-popup-close" id="btn-popup-close" type="button">Đóng</button>
                                        <button class="btn-popup-apply" id="btn-popup-apply" type="button">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="filter-h-item" data-filter="default"><i class="bi bi-arrow-up-down"></i> Mặc định</div>
                            <div class="filter-h-item" data-filter="stock"><i class="bi bi-truck"></i> Sẵn hàng</div>
                            <div class="filter-h-item" data-filter="new"><i class="bi bi-cart-plus"></i> Hàng mới về</div>
                            <div class="category-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-category-filter" data-filter="category"><i class="bi bi-grid"></i> Danh mục</div>
                                <div class="category-dropdown-menu" id="categoryMenu">
                                    <div class="price-dropdown-title mb-3 fw-bold">Chọn danh mục sản phẩm</div>
                                    <div class="category-grid" id="h-category-grid">
                                        <!-- Categories will be injected here -->
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-category-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-category-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="price-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-price-filter"><i class="bi bi-coin"></i> Xem theo giá</div>
                                <div class="price-dropdown-menu" id="priceMenu">
                                    <div class="price-dropdown-title">Hãy chọn mức giá phù hợp với bạn</div>
                                    <div class="price-input-container">
                                        <div class="price-field">
                                            <input type="text" id="h-price-min" placeholder="0" inputmode="numeric">
                                        </div>
                                        <span class="text-muted">-</span>
                                        <div class="price-field">
                                            <input type="text" id="h-price-max" placeholder="50.000.000" inputmode="numeric">
                                        </div>
                                    </div>
                                    <div class="price-slider-group">
                                        <div class="price-slider-base"></div>
                                        <div class="price-slider-fill" id="h-price-fill"></div>
                                        <input type="range" class="price-slider-range" id="h-slider-min" min="0" max="50000000" step="100000" value="0">
                                        <input type="range" class="price-slider-range" id="h-slider-max" min="0" max="50000000" step="100000" value="50000000">
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-secondary" type="button" id="btn-price-close">Đóng</button>
                                        <button class="btn btn-primary" type="button" id="btn-price-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="brand-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-brand-filter">Hãng sản xuất</div>
                                <div class="brand-dropdown-menu" id="brandMenu">
                                    <div class="brand-grid" id="h-brand-grid">
                                        <!-- Brands will be injected here -->
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-brand-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-brand-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="space-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-space-filter" data-filter="paint_space">Không gian</div>
                                <div class="space-dropdown-menu" id="spaceMenu">
                                    <div class="space-dropdown-title mb-3 fw-bold">Chọn không gian sử dụng</div>
                                    <div class="space-option-list" id="h-space-list">
                                        <div class="space-option-item selected" data-value="all">
                                            Tất cả
                                        </div>
                                        <div class="space-option-item" data-value="Nội thất">
                                            Nội thất
                                        </div>
                                        <div class="space-option-item" data-value="Ngoại thất">
                                            Ngoại thất
                                        </div>
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-space-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-space-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="positions-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-positions-filter" data-filter="paint_positions">Vị trí</div>
                                <div class="positions-dropdown-menu" id="positionsMenu">
                                    <div class="positions-dropdown-title mb-3 fw-bold">Chọn vị trí thi công</div>
                                    <div class="positions-option-list" id="h-positions-list">
                                        <div class="positions-option-item selected" data-value="all">Tất cả</div>
                                        <div class="positions-option-item" data-value="Tường">Tường</div>
                                        <div class="positions-option-item" data-value="Cửa">Cửa</div>
                                        <div class="positions-option-item" data-value="Trần">Trần</div>
                                        <div class="positions-option-item" data-value="Viền">Viền</div>
                                        <div class="positions-option-item" data-value="Cửa sổ">Cửa sổ</div>
                                        <div class="positions-option-item" data-value="Sàn">Sàn</div>
                                        <div class="positions-option-item" data-value="Mái tôn">Mái tôn</div>
                                        <div class="positions-option-item" data-value="Khác">Khác</div>
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-positions-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-positions-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="needs-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-needs-filter" data-filter="paint_needs">Nhu cầu</div>
                                <div class="needs-dropdown-menu" id="needsMenu">
                                    <div class="needs-dropdown-title mb-3 fw-bold">Chọn nhu cầu sử dụng</div>
                                    <div class="needs-option-list" id="h-needs-list">
                                        <div class="needs-option-item selected" data-value="all">Tất cả</div>
                                        <div class="needs-option-item" data-value="Phòng khách">Phòng khách</div>
                                        <div class="needs-option-item" data-value="Phòng trẻ em">Phòng trẻ em</div>
                                        <div class="needs-option-item" data-value="Phòng tắm / WC">Phòng tắm / WC</div>
                                        <div class="needs-option-item" data-value="Phòng ngủ">Phòng ngủ</div>
                                        <div class="needs-option-item" data-value="Phòng bếp">Phòng bếp</div>
                                        <div class="needs-option-item" data-value="Mặt tiền / ngoài trời">Mặt tiền / ngoài trời</div>
                                        <div class="needs-option-item" data-value="Chống thấm">Chống thấm</div>
                                        <div class="needs-option-item" data-value="Chống ẩm mốc">Chống ẩm mốc</div>
                                        <div class="needs-option-item" data-value="Chống bám bụi">Chống bám bụi</div>
                                        <div class="needs-option-item" data-value="Chống kiềm">Chống kiềm</div>
                                        <div class="needs-option-item" data-value="Chống UV">Chống UV</div>
                                        <div class="needs-option-item" data-value="Chống rỉ sét">Chống rỉ sét</div>
                                        <div class="needs-option-item" data-value="Chống nứt">Chống nứt</div>
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-needs-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-needs-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="filter-h-title mt-3">Sắp xếp theo</div>
                        <div class="filter-h-list" id="h-sort-options">
                            <div class="filter-h-item active" data-sort="newest"><i class="bi bi-star"></i> Phổ biến</div>
                            <div class="filter-h-item" data-sort="rating"><i class="bi bi-star"></i> Đánh giá</div>
                            <div class="filter-h-item" data-sort="promo"><i class="bi bi-percent"></i> Khuyến mãi</div>
                            <div class="filter-h-item" data-sort="price_asc"><i class="bi bi-sort-numeric-down"></i> Giá Thấp - Cao</div>
                            <div class="filter-h-item" data-sort="price_desc"><i class="bi bi-sort-numeric-up-alt"></i> Giá Cao - Thấp</div>
                        </div>
                        </div><!-- /.adv-filter-collapsible-inner -->
                        </div><!-- /.adv-filter-collapsible -->
                    </div>
                </div>
                <!-- Mobile Filter Bar (Exactly matching user's reference image) -->
                <div class="mobile-filter-bar d-block d-md-none">
                    <!-- Mobile Search Box -->
                    <div class="search-wrapper mb-3">
                        <i class="bi bi-search search-icon"></i>
                        <input id="mobileSearchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
                    </div>

                    <!-- Chọn theo tiêu chí Section -->
                    <div class="mobile-criteria-section">
                        <div class="mobile-filter-title">Chọn theo tiêu chí</div>
                        <div class="mobile-criteria-list">
                            <div class="mobile-criteria-item" id="m-criteria-stock" data-filter="stock">
                                <span class="criteria-icon"><i class="bi bi-truck"></i></span>
                                <span class="criteria-label">Sẵn hàng</span>
                            </div>
                            <div class="mobile-criteria-item price-trigger" id="m-criteria-price">
                                <span class="criteria-icon"><i class="bi bi-coin"></i></span>
                                <span class="criteria-label">Xem theo giá</span>
                            </div>
                            <div class="mobile-criteria-item" id="m-criteria-new" data-filter="new">
                                <span class="criteria-icon"><i class="bi bi-cart-plus"></i></span>
                                <span class="criteria-label">Hàng mới</span>
                            </div>
                            <div class="mobile-criteria-item" id="m-criteria-cat" data-filter="category">
                                <span class="criteria-icon"><i class="bi bi-grid"></i></span>
                                <span class="criteria-label">Danh mục</span>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Tabs Row -->
                    <div class="mobile-tab-bar">
                        <div class="mobile-tab-item active" id="m-tab-popular" data-sort="newest">Phổ biến</div>
                        <div class="mobile-tab-item" id="m-tab-promo" data-sort="promo">Khuyến mãi HOT</div>
                        <div class="mobile-tab-item" id="m-tab-price" data-sort="price_asc">Giá <i class="bi bi-chevron-expand"></i></div>
                        <div class="mobile-tab-item" id="m-tab-filter">Bộ lọc <i class="bi bi-funnel-fill text-muted ms-1"></i></div>
                    </div>

                    <!-- Semi-transparent overlay for mobile drawer/panel -->
                    <div class="filter-overlay" id="mobileFilterOverlay"></div>
                </div>
            </div>
            <!-- /./ -->
            <div id="activeFiltersSection" class="active-filters-section">
                <div class="active-filters-label">Đang lọc theo</div>
                <div class="active-filters-list" id="activeFiltersList">
                    <!-- Tags will be injected here -->
                </div>
            </div>
            <br>
            <div id="productGrid" class="shopping product-list-container"></div>
            <div id="emptyProducts" class="text-center text-muted py-4" style="display:none;">Không tìm thấy sản phẩm.</div>
            <div class="text-center mt-3" id="loadMoreWrap" style="display:none;">
                <button class="btn btn-outline-primary btn-sm px-4" id="loadMoreBtn">Xem thêm</button>
            </div>
            <div id="productSentinel" style="height: 10px;"></div>
        </div>
    </section>
    </div>
</div>

<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
    const BASE_URL = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') ? '' : '<?= h($baseUrl) ?>';
    const FALLBACK_IMG = '<?= h($site_fallback_logo ? to_abs_url((string)$site_fallback_logo, (string)$baseUrl) : "") ?>';
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    $.ajaxSetup({ headers: { 'X-CSRF-Token': CSRF_TOKEN } });

    // Cấu hình số lượng sản phẩm trên mỗi hàng
    const PRODUCT_LIMIT_PC = 5;
    const PRODUCT_LIMIT_MOBILE = 2;
    const pageSize = 24;

    let page = 0; let hasMore = true; let loading = false;
    let miniCartState = [];
    const urlParams = new URLSearchParams(window.location.search);
    const initialSearch = String(urlParams.get('q') || '').trim();
    const initialCatId = Number(<?= json_encode($_GET['cat'] ?? 0) ?>) || Number(urlParams.get('cat') || 0);
    let initialCatSlug = String(<?= json_encode($_GET['cat_slug'] ?? '') ?> || urlParams.get('cat_slug') || '').trim();
    const initialBrand = String(urlParams.get('brand') || '').trim();
    const normalizeBrandKey = (value) => String(value || '')
        .toLowerCase()
        .replace(/[\u2010-\u2015]/g, '-')
        .replace(/\s+/g, '')
        .replace(/-+/g, '-')
        .trim();
    let searchTerm = initialSearch; let sortVal = 'newest';
    let initialFetchDone = false;
    let pendingResetFetch = false;
    let cats = [];
    let brands = [];
    const filterState = { catFilters: [], brandFilters: [], priceMin: null, priceMax: null, ratingMin: 0, stockOnly: true, promoOnly: false, paintSpaceFilters: [], paintPositionsFilters: [], paintNeedsFilters: [] };
    let initialBrandApplied = false;
    const FAVORITE_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/favorite.php';
    // Các phần tử DOM chính cần thao tác
    const $grid = $('#productGrid');
    const $search = $('#searchBox');
    const $loadMore = $('#loadMoreWrap');
    const $empty = $('#emptyProducts');
    const $filterCategories = $('#filterCategories');
    const $filterBrands = $('#filterBrands');
    const $activeBrandNotice = $('#activeBrandNotice');
    const $filterApply = $('#filterApplyBtn');
    const $filterClear = $('#filterClearBtn');
    const $filterTabs = $('.filter-tab');
    const $filterSections = $('.filter-section');


    // Áp dụng cấu hình grid từ JS
    if ($grid.length) {
        $grid[0].style.setProperty('--pc-cols', PRODUCT_LIMIT_PC);
        $grid[0].style.setProperty('--mobile-cols', PRODUCT_LIMIT_MOBILE);
    }

    const fmtPrice = (n) => {
        if (window.pmFormatPrice && typeof window.pmFormatPrice === 'function') {
            return window.pmFormatPrice(n);
        }
        const num = Number(n) || 0;
        return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
    };

    const formatPriceInput = (el) => {
        let val = el.value;
        let cleaned = val.replace(/\D/g, '');
        if (!cleaned) {
            el.value = '';
            return;
        }
        let formatted = cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        
        let selStart = el.selectionStart;
        let oldLen = val.length;
        
        el.value = formatted;
        
        if (selStart !== oldLen) {
            let newLen = formatted.length;
            let diff = newLen - oldLen;
            el.setSelectionRange(selStart + diff, selStart + diff);
        }
    };
    function esc(str){
        return $('<div>').text(String(str || '')).html();
    }
    // Hàm làm nổi bật các phần text liên quan đến khuyến mãi trong promo_highlights
    function highlightPromoText(text){
        const raw = String(text || '');
        if (!raw) return '';
        let safe = $('<div>').text(raw).html();
        safe = safe.replace(/(\d[\d\.]*\s*(?:đ|₫|VNĐ|VND|%))/i, '<strong>$1</strong>');
        safe = safe.replace(/(deal\s*sốc)/i, '<strong>$1</strong>');
        return safe;
    }
    // Hàm chuyển URL ảnh thành đường dẫn tuyệt đối, nếu đã là URL tuyệt đối thì giữ nguyên
    function toAbs(url){
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
        const raw = String(url || '').trim();
        if (!raw) return '';
        if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
        const base = String(BASE_URL || '').replace(/\/$/, '');
        if (!base) return raw;
        const path = raw.startsWith('/') ? raw : '/' + raw;
        return base + path;
    }




    function renderCategoryFilters(){
        if (!$filterCategories.length) return;
        const html = cats.map(c => {
            const name = $('<div>').text(c.name || 'Danh mục').html();
            const slug = String(c.slug || '').trim();
            return `<label class="filter-option"><input type="checkbox" data-cat="${c.id}" data-slug="${slug}"> ${name}</label>`;
        }).join('');
        $filterCategories.html(html || '<div class="text-muted small">Chưa có danh mục.</div>');
    }

    function renderBrandFilters(){
        if (!$filterBrands.length) return;
        const html = brands.map(b => {
            const safe = $('<div>').text(String(b || '').trim()).html();
            return `<label class="filter-option"><input type="checkbox" data-brand="${safe}"> ${safe}</label>`;
        }).join('');
        $filterBrands.html(html || '<div class="text-muted small">Chưa có hãng đối tác.</div>');
    }

    function runInitialFetch(){
        if (initialFetchDone) return;
        if (initialBrand && !initialBrandApplied) return;
        initialFetchDone = true;
        fetchProducts(true);
    }

    function renderCategoryDropdown(){
        if (!$('#h-category-grid').length) return;
        const html = cats.map(c => {
            const name = esc(c.name || 'Danh mục');
            const id = c.id;
            const isSelected = filterState.catFilters.includes(id);
            return `<div class="category-item-pill ${isSelected ? 'selected' : ''}" data-id="${id}" data-name="${name}">${name}</div>`;
        }).join('');
        $('#h-category-grid').html(html || '<div class="text-muted small">Chưa có danh mục.</div>');
    }

    function loadCats(){
        $.get(API, { ajax: 'categories' }, res => {
            cats = (res && res.ok) ? (res.data || []) : [];
            cats = cats.filter(c => {
                const id = Number(c.id || 0);
                const hasProducts = Number(c.product_count || 0) > 0;
                return hasProducts || id === 0 || id === initialCatId;
            });
            renderCategoryFilters();
            renderCategoryDropdown();
            if (initialCatId) {
                $filterCategories.find(`input[data-cat="${initialCatId}"]`).prop('checked', true);
                readFilters();
            } else if (initialCatSlug) {
                $filterCategories.find(`input[data-slug="${initialCatSlug}"]`).prop('checked', true);
                readFilters();
            }
            if (!initialBrand) {
                runInitialFetch();
            }
        }).fail(() => {
            if (!initialBrand) {
                runInitialFetch();
            }
        });
    }

    function loadBrands(){
        $.get(API, { ajax: 'brands' }, res => {
            brands = (res && res.ok) ? (res.data || []) : [];
            brands = [...new Set(brands.map(x => String(x || '').trim()).filter(Boolean))];
            renderBrandFilters();
            renderBrandDropdown();
            if (initialBrand) {
                const targetKey = normalizeBrandKey(initialBrand);
                let matched = false;
                $filterBrands.find('input[data-brand]').each(function(){
                    const val = String($(this).data('brand') || '').trim();
                    if (normalizeBrandKey(val) === targetKey) {
                        $(this).prop('checked', true);
                        matched = true;
                    }
                });
                readFilters();
                if (!matched) {
                    filterState.brandFilters = [initialBrand];
                    updateBrandNotice();
                }
                initialBrandApplied = true;
                fetchProducts(true);
            } else {
                runInitialFetch();
            }
        }).fail(() => {
            brands = [];
            renderBrandFilters();
            renderBrandDropdown();
            if (initialBrand && !initialBrandApplied) {
                filterState.brandFilters = [initialBrand];
                updateBrandNotice();
                initialBrandApplied = true;
                fetchProducts(true);
            } else {
                runInitialFetch();
            }
        });
    }

    function readFilters(manual = false){
        if (manual) initialCatSlug = '';
        // 1. Category
        const selectedCats = [];
        // From Sidebar (if exists)
        if ($filterCategories.length) {
            $filterCategories.find('input[data-cat]:checked').each(function(){
                const id = Number($(this).data('cat'));
                if (id) selectedCats.push(id);
            });
        }
        // From Horizontal Pills
        $('#h-category-grid .category-item-pill.selected').each(function(){
            const id = Number($(this).data('id'));
            if (id && !selectedCats.includes(id)) selectedCats.push(id);
        });
        filterState.catFilters = selectedCats;
        $('#h-category-filter').toggleClass('active', selectedCats.length > 0);
        $('#m-criteria-cat').toggleClass('active', selectedCats.length > 0);

        // 2. Brand
        const selectedBrands = [];
        // From Sidebar
        if ($filterBrands.length) {
            $filterBrands.find('input[data-brand]:checked').each(function(){
                const name = String($(this).data('brand') || '').trim();
                if (name) selectedBrands.push(name);
            });
        }
        // From Horizontal Pills
        $('#h-brand-grid .brand-item-pill.selected').each(function(){
            const name = String($(this).data('brand') || '').trim();
            if (name && !selectedBrands.includes(name)) selectedBrands.push(name);
        });
        filterState.brandFilters = selectedBrands;
        $('#h-brand-filter').toggleClass('active', selectedBrands.length > 0);
        updateBrandNotice();

        // 3. Price
        const pMin = $('#priceMin').val();
        const pMax = $('#priceMax').val();

        if (pMin !== '' || pMax !== '') {
            filterState.priceMin = pMin !== '' ? Number(pMin.replace(/\D/g, '')) : null;
            filterState.priceMax = pMax !== '' ? Number(pMax.replace(/\D/g, '')) : null;
        } else {
            filterState.priceMin = null;
            filterState.priceMax = null;
        }
        const hasPriceFilter = filterState.priceMin !== null || filterState.priceMax !== null;
        $('#h-price-filter').toggleClass('active', hasPriceFilter);
        $('#m-criteria-price').toggleClass('active', hasPriceFilter);

        // 4. Rating
        const $rating = $('input[name="ratingFilter"]:checked');
        filterState.ratingMin = $rating.length ? Number($rating.data('rating') || 0) : 0;

        // 5. Flags
        filterState.stockOnly = $('#stockOnly').is(':checked');
        filterState.promoOnly = $('#promoOnly').is(':checked');

        // 6. Paint Special Filters (Horizontal only)
        const selectedSpaces = [];
        $('#h-space-list .space-option-item.selected').each(function(){
            const val = $(this).data('value');
            if (val && val !== 'all') selectedSpaces.push(val);
        });
        filterState.paintSpaceFilters = selectedSpaces;
        $('#h-space-filter').toggleClass('active', selectedSpaces.length > 0);

        const selectedPositions = [];
        $('#h-positions-list .positions-option-item.selected').each(function(){
            const val = $(this).data('value');
            if (val && val !== 'all') selectedPositions.push(val);
        });
        filterState.paintPositionsFilters = selectedPositions;
        $('#h-positions-filter').toggleClass('active', selectedPositions.length > 0);

        const selectedNeeds = [];
        $('#h-needs-list .needs-option-item.selected').each(function(){
            const val = $(this).data('value');
            if (val && val !== 'all') selectedNeeds.push(val);
        });
        filterState.paintNeedsFilters = selectedNeeds;
        $('#h-needs-filter').toggleClass('active', selectedNeeds.length > 0);
    }

    function updateBrandNotice(){
        if (!$activeBrandNotice.length) return;
        const brands = Array.isArray(filterState.brandFilters) ? filterState.brandFilters.filter(Boolean) : [];
        if (!brands.length) {
            $activeBrandNotice.hide().find('span').text('');
            return;
        }
        $activeBrandNotice.find('span').text('Đang lọc theo hãng: ' + brands.join(', '));
        $activeBrandNotice.css('display', 'inline-flex');
    }

    function cardTemplate(p){
        const pid = Number(p.id || 0);
        const href = (window.pmBuildProductUrl
            ? window.pmBuildProductUrl(pid, p.product_name || p.name || '')
            : (BASE_URL + '/view-product/?pid=' + encodeURIComponent(pid)));

        const img = p.thumb
            ? toAbs(p.thumb)
            : <?= json_encode($site_fallback_logo ? rtrim($baseUrl, '/').'/'.ltrim($site_fallback_logo, '/') : '') ?>;

        let gallery = [];
        try {
            if (p.media_gallery) {
                gallery = typeof p.media_gallery === 'string' ? JSON.parse(p.media_gallery) : p.media_gallery;
            }
        } catch (e) {}
        if (!Array.isArray(gallery)) {
            gallery = [];
        }
        // media_gallery có thể là mảng chuỗi URL hoặc mảng object {type,url,caption}.
        // Chỉ lấy ảnh (bỏ video), trích URL, chuẩn hoá đường dẫn tuyệt đối.
        gallery = gallery
            .map(item => {
                if (typeof item === 'string') return item.trim();
                if (item && typeof item === 'object') {
                    const type = String(item.type || 'image').toLowerCase();
                    if (type === 'video') return '';
                    return String(item.url || '').trim();
                }
                return '';
            })
            .filter(url => url !== '')
            .map(url => toAbs(url));
        gallery = [...new Set(gallery)];

        const absImg = toAbs(img);
        // Số ảnh media (đã loại ảnh trùng với ảnh đại diện) — quyết định bật carousel
        const mediaCount = gallery.filter(u => u !== absImg).length;
        // gallery dùng để hiển thị: ảnh đại diện đứng đầu, kế đến các ảnh media
        if (absImg && !gallery.includes(absImg)) {
            gallery.unshift(absImg);
        }

        let galleryHtml = '';
        // Bật carousel khi có ÍT NHẤT 1 ảnh media (ngoài ảnh đại diện); không có media -> tắt
        if (mediaCount >= 1) {
            galleryHtml = `
                <div class="gallery-carousel-container">
                    <div class="gallery-carousel-slides">
                        ${gallery.map((gImg, idx) => `
                            <img class="gallery-slide-img ${idx === 0 ? 'active' : ''}" src="${$('<div>').text(gImg).html()}" loading="lazy" decoding="async" onerror="this.src='${esc(FALLBACK_IMG)}'">
                        `).join('')}
                    </div>
                    <button type="button" class="gallery-prev-btn" aria-label="Previous image"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="gallery-next-btn" aria-label="Next image"><i class="bi bi-chevron-right"></i></button>
                    <div class="gallery-dots">
                        ${gallery.map((_, idx) => `<span class="gdot ${idx === 0 ? 'active' : ''}"></span>`).join('')}
                    </div>
                </div>
            `;
        }

        const safeName = $('<div>').text(p.product_name || p.name || 'Sản phẩm').html();
        const priceMin = Number(p.gia_min ?? 0);
        let basePriceLabel = String(p.price_text || '').trim();
        if (!basePriceLabel) basePriceLabel = priceMin > 0 ? fmtPrice(priceMin) : 'Liên hệ';

        const safePrice = $('<div>').text(basePriceLabel).html();
        const oldPrice = p.old_price_text ? $('<div>').text(String(p.old_price_text)).html() : '';
        const newPrice = p.new_price_text ? $('<div>').text(String(p.new_price_text)).html() : '';

        const ratingCount = Number(p.rating_count || 0);
        const ratingVal = Number.isFinite(Number(p.rating_avg)) ? Number(p.rating_avg) : (Number(p.rating_value) || 0);

        const soldCount = Number(p.sold_count || p.sold || p.sold_qty || 0);
        const soldTextRaw = String(p.sold_text || '').trim();
        const fmtSoldCount = (n) => {
            const num = Number(n);
            if (!Number.isFinite(num) || num < 0) return '';
            if (num >= 1000) {
                const k = Math.floor(num / 100) / 10; // 30500 -> 30.5
                return String(k).replace(/\.0$/, '') + 'k+';
            }
            return String(num);
        };
        const soldText = soldTextRaw
            ? soldTextRaw
            : ('Đã bán ' + fmtSoldCount(soldCount));

        const promoSubtitle = p.promo_subtitle ? String(p.promo_subtitle) : '';
        const promoHighlights = Array.isArray(p.promo_highlights) ? p.promo_highlights : [];

        const discount = Number(p.discount_percent || 0);
        const voucherBadge = p.voucher_badge ? String(p.voucher_badge) : '';
        const hasShip = !!p.has_ship_demo;
        const shipLabel = p.ship_label ? String(p.ship_label) : '';
        const shipMinSubtotal = Number(p.ship_min_subtotal || 0);

        // Badge giảm giá: ưu tiên voucher text — định dạng "Giảm 11%"
        let discountText = '';
        if (voucherBadge) {
            let raw = voucherBadge.toString().trim();
            let label = raw;
            const m = raw.match(/^Giảm\s+(\d+)\s*%?$/i);
            if (m) label = 'Giảm ' + m[1] + '%';
            else if (/^\d+\s*[kK]$/.test(raw)) {
                const num = raw.replace(/[^0-9]/g, '');
                label = num ? 'Giảm ' + num + 'K' : raw;
            } else if (/^Giảm\s+\d+[kK]?/i.test(raw)) label = raw;
            else if (/^\d+$/.test(raw)) label = 'Giảm ' + raw + '%';
            else if (/^-/.test(raw)) label = 'Giảm ' + raw.replace(/^-\s*/, '');
            else label = raw;
            discountText = label;
        } else if (discount > 0) {
            discountText = (discount >= 100) ? 'Free' : ('Giảm ' + discount + '%');
        }

        const discountBadgeHtml = discountText
            ? `<div class="badge-discount badge-discount-v2"><i class="bi bi-tag-fill"></i><span>${$('<div>').text(discountText).html()}</span></div>`
            : '';

        // Badge voucher/freeship — luôn hiện nếu có voucher ship áp dụng, kèm điều kiện đơn tối thiểu
        let voucherHtml = '';
        if (hasShip) {
            const raw = (shipLabel || '').toString().trim();
            const isFree = (raw === '100%' || raw === '100' || raw === '');
            // raw đã kèm đơn vị từ server (vd "10%", "20K"); chỉ thêm dấu "-" cho rõ là giảm
            let label = isFree ? 'Freeship' : ('Ship -' + raw);
            // Ghi kèm đơn tối thiểu (rút gọn K) nếu có
            if (shipMinSubtotal > 0) {
                const minShort = shipMinSubtotal >= 1000
                    ? (shipMinSubtotal / 1000).toString().replace(/\.0$/, '') + 'K'
                    : String(shipMinSubtotal);
                label += ' đơn ' + minShort;
            }
            voucherHtml = `<div class="badge-voucher" title="${$('<div>').text(label).html()}"><i class="bi bi-truck"></i><span class="bv-text">${$('<div>').text(label).html()}</span></div>`;
        }

        // Danh mục (nếu có)
        const catName = String(p.category_name || p.category || '').trim();
        const catHtml = ''; //catName ? `<span class="shopping-product-category">${$('<div>').text(catName).html()}</span>` : '';

        // Promo line: lấy 1 dòng đầu tiên cho gọn (đúng layout mẫu). Vẫn ưu tiên promo_highlights.
        let promoLine = '';
        if (promoHighlights.length > 0) {
            promoLine = String(promoHighlights.find(t => String(t || '').trim()) || '').trim();
        }
        if (!promoLine && promoSubtitle) promoLine = String(promoSubtitle).trim();
        const promoHtml = promoLine ? `<div class="badge-promo">${highlightPromoText(promoLine)}</div>` : '';

        const priceHtml = (oldPrice && newPrice)
            ? `<span class="sp-price">${newPrice}</span><span class="sp-old-price">${oldPrice}</span>`
            : `<span class="sp-price">${safePrice}</span>`;

        const safeRating = Math.max(0, Math.min(5, ratingVal || 0));
        const starsHtml = `<i class="bi bi-star-fill is-on"></i>`;

        const ratingText = safeRating.toFixed(1) + ' (' + ratingCount + ')';

        const ratingHtml = `<div class="sp-rating"><span class="sp-stars">${starsHtml}</span><span>${$('<div>').text(ratingText).html()}</span></div>`;

        return `
            <a href="${$('<div>').text(href).html()}" class="shopping-product-card shadow-sm">
                <div class="shopping-img-wrapper">
                    <img class="main-prod-img" src="${$('<div>').text(img).html()}" alt="${esc(safeName)}" loading="lazy" decoding="async" onerror="this.src='${esc(FALLBACK_IMG)}'">
                    ${galleryHtml}
                    ${discountBadgeHtml}
                    ${voucherHtml}
                    <button type="button" class="btn-favorite-card ${p.liked ? 'active' : ''}" data-pid="${pid}" title="${p.liked ? 'Bỏ yêu thích' : 'Yêu thích'}">
                        <i class="bi bi-heart"></i>
                    </button>
                </div>

                <div class="shopping-product-content">
                    <div class="shopping-product-title">${safeName}</div>
                    ${catHtml ? `<div class="mb-1">${catHtml}</div>` : ''}
                    ${promoHtml}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">${priceHtml}</div>
                       
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-1">
                        ${ratingHtml}
                        <div class="product-card-add-cart-icon product-card-add-cart" data-pid="${pid}" data-name="${safeName}" title="Thêm vào giỏ hàng">
                            <i class="bi bi-cart-plus"></i>
                        </div>
                        <div class="sd-none sp-sold d-none">${$('<div>').text(soldText).html()}</div>
                    </div>
                </div>

                <div class="add-cart-btn">
                    <span type="button" class="product-card-add-cart" data-pid="${pid}" data-name="${safeName}">Thêm vào giỏ hàng</span>
                </div>
            </a>
        `;
    }

    function skeletonCardTemplate(){
        return `
            <article class="shopping-product-card is-skeleton" aria-hidden="true">
                <div class="shopping-img-wrapper">
                    <div class="shopping-skeleton" style="position:absolute;inset:0;"></div>
                </div>
                <div class="shopping-product-content">
                    <div class="shopping-skeleton" style="height:10px;width:92%;margin-bottom:8px;"></div>
                    <div class="shopping-skeleton" style="height:10px;width:72%;margin-bottom:10px;"></div>
                    <div class="shopping-skeleton" style="height:12px;width:60%;"></div>
                </div>
            </article>
        `;
    }

    function renderSkeleton(count = 8, replace = false){
        const safeCount = Math.max(1, Number(count) || 1);
        const html = new Array(safeCount).fill('').map(() => skeletonCardTemplate()).join('');
        if (replace) $grid.html(html);
        else $grid.append(html);
    }

    function clearSkeleton(){
        $grid.find('.shopping-product-card.is-skeleton, .product-card.is-skeleton, .pcard.is-skeleton').remove();
    }

    function fetchProducts(reset = false){
        if (loading) {
            if (reset) pendingResetFetch = true;
            return;
        }
        if (!hasMore && !reset) return;
        if (reset){
            page = 0;
            hasMore = true;
            renderSkeleton(Math.min(pageSize, 8), true);
        } else {
            renderSkeleton(4, false);
        }
        loading = true; $empty.hide();
        const start = page * pageSize;
        const params = {
            ajax: 'products',
            draw: 1,
            start,
            length: pageSize,
            'search[value]': searchTerm,
            custom_sort: sortVal,
            cat_slug: initialCatSlug
        };
        if (filterState.catFilters.length) {
            params.cat_filters = filterState.catFilters.join(',');
        }
        if (filterState.brandFilters.length) {
            params.brand_filters = filterState.brandFilters.join(',');
        }
        if (filterState.priceMin !== null) params.price_min = filterState.priceMin;
        if (filterState.priceMax !== null) params.price_max = filterState.priceMax;
        if (filterState.ratingMin) params.rating_min = filterState.ratingMin;
        if (filterState.stockOnly) params.stock_only = 1;
        if (filterState.promoOnly) params.promo_only = 1;
        if (filterState.paintSpaceFilters.length) {
            params.paint_space_filters = filterState.paintSpaceFilters.join(',');
        }
        if (filterState.paintPositionsFilters.length) {
            params.paint_positions_filters = filterState.paintPositionsFilters.join(',');
        }
        if (filterState.paintNeedsFilters.length) {
            params.paint_needs_filters = filterState.paintNeedsFilters.join(',');
        }

        updateActiveFiltersUI();

        $.get(API, params, res => {
            clearSkeleton();
            let list = [];
            let total = 0;

            if (Array.isArray(res)) {
                list = res;
                total = list.length;
            } else if (res && res.ok) {
                list = res.data || [];
                total = (typeof res.recordsFiltered !== 'undefined') ? (res.recordsFiltered || list.length) : list.length;
            } else if (res && Array.isArray(res.data)) {
                list = res.data;
                total = list.length;
            }

            if (reset && list.length === 0){ $empty.show(); $loadMore.hide(); }
            list.forEach(p => $grid.append(cardTemplate(p)));
            page += 1;
            hasMore = total ? (start + list.length < total) : false;
            $loadMore.toggle(hasMore);
        }).always(() => {
            clearSkeleton();
            loading = false;
            if (pendingResetFetch) {
                pendingResetFetch = false;
                fetchProducts(true);
            }
        });
    }

    if ($search.length && initialSearch) {
        $search.val(initialSearch);
    }

    $search.on('keyup', function(){
        searchTerm = $(this).val();
        fetchProducts(true);
    });

    $(document).on('change', '#filterCategories input, #filterBrands input, input[name="ratingFilter"], input[name="sortFilter"], #stockOnly, #promoOnly', function(){
        const $sort = $('input[name="sortFilter"]:checked');
        sortVal = $sort.length ? String($sort.data('sort') || 'newest') : 'newest';
        readFilters(true);
        fetchProducts(true);
    });

    $(document).on('input', '#priceMin, #priceMax', function(){
        formatPriceInput(this);
        readFilters(true);
        
        // Đồng bộ sang horizontal controls
        const valClean = parseInt($(this).val().replace(/\D/g, '')) || 0;
        if (this.id === 'priceMin') {
            $('#h-slider-min').val(valClean);
            $('#h-price-min').val($(this).val());
        } else {
            $('#h-slider-max').val(valClean);
            $('#h-price-max').val($(this).val());
        }
        
        fetchProducts(true);
    });

    $(document).on('input', '#h-price-min, #h-price-max', function(){
        formatPriceInput(this);
    });

    $filterApply.on('click', function(){
        const $sort = $('input[name="sortFilter"]:checked');
        sortVal = $sort.length ? String($sort.data('sort') || 'newest') : 'newest';
        readFilters(true);
        fetchProducts(true);
    });

    $filterClear.on('click', function(){
        $('#filterCategories input, #filterBrands input').prop('checked', false);
        $('input[name="ratingFilter"], input[name="sortFilter"]').prop('checked', false);
        $('#priceMin, #priceMax').val('');
        $('#h-slider-min').val(0);
        $('#h-slider-max').val(50000000);
        $('#h-price-min').val('');
        $('#h-price-max').val('');
        $('#stockOnly').prop('checked', true);   // Mặc định: Còn hàng
        $('#promoOnly').prop('checked', false);  // Khuyến mãi: chỉ lọc khi người dùng chủ động bật
        sortVal = 'newest';
        $('input[name="sortFilter"][data-sort="newest"]').prop('checked', true);
        
        // Reset horizontal UI & state
        filterState.paintSpaceFilters = [];
        filterState.paintPositionsFilters = [];
        $('.filter-h-item').removeClass('active');
        $('#h-brand-grid .brand-item-pill').removeClass('selected');
        $('#h-category-grid .category-item-pill').removeClass('selected');
        
        // Space
        $('#h-space-list .space-option-item').removeClass('selected');
        $('#h-space-list .space-option-item[data-value="all"]').addClass('selected');
        
        // Positions
        $('#h-positions-list .positions-option-item').removeClass('selected');
        $('#h-positions-list .positions-option-item[data-value="all"]').addClass('selected');
        
        // Needs
        $('#h-needs-list .needs-option-item').removeClass('selected');
        $('#h-needs-list .needs-option-item[data-value="all"]').addClass('selected');
        filterState.paintNeedsFilters = [];

        $('#h-sort-options .filter-h-item').removeClass('active');
        $('#h-sort-options .filter-h-item[data-sort="newest"]').addClass('active');

        // Trạng thái mặc định: làm nổi bật nút "Mặc định".
        $('#h-filter-criteria .filter-h-item[data-filter="default"]').addClass('active');

        readFilters(true);
        fetchProducts(true);
    });

    $filterTabs.on('click', function(){
        const key = String($(this).data('section') || '');
        if (!key) return;
        $filterTabs.removeClass('is-active');
        $(this).addClass('is-active');
        $filterSections.removeClass('is-active');
        $filterSections.filter(`[data-section="${key}"]`).addClass('is-active');
    });

    $('#loadMoreBtn').click(() => fetchProducts());

    // Infinite Scroll: Tự động load khi cuộn xuống
    if ('IntersectionObserver' in window) {
        const sentinel = document.getElementById('productSentinel');
        if (sentinel) {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !loading && hasMore) {
                    fetchProducts(false);
                }
            }, { rootMargin: '120px' });
            observer.observe(sentinel);
        }
    }

    // Nút thêm giỏ hàng trên từng product-card
    $grid.on('click', '.product-card-add-cart', function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        const $btn = $(this);
        const pid = Number($btn.data('pid') || 0);
        const name = String($btn.data('name') || '').trim();
        
        // Fly-to-cart animation source
        const $card = $btn.closest('.shopping-product-card');
        const $img = $card.find('.shopping-img-wrapper img');
        
        if (window.addToCartFromCard) {
            window.addToCartFromCard(pid, name, $img[0]);
        }
    });
    // Favorite toggle from card
    $grid.on('click', '.btn-fav-item', function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        const $btn = $(this);
        const pid = Number($btn.data('pid') || 0);
        if (!pid) return;
        
        $.post(FAVORITE_API, { action: 'toggle', pid: pid }, function(res){
            if (res && res.ok) {
                $btn.toggleClass('active', !!res.liked);
                if (window.toastr) {
                    if (res.liked) toastr.success('Đã thêm vào yêu thích');
                    else toastr.info('Đã bỏ yêu thích');
                }
            }
        });
    });

    // Event listeners cho bộ lọc ngang (Horizontal Filters)
    $('#h-sort-options .filter-h-item').on('click', function() {
        const $this = $(this);
        const newSort = $this.data('sort');
        
        if (newSort === 'promo') {
            // Khuyến mãi HOT: bật filter promoOnly và sắp xếp mới nhất
            $('#promoOnly').prop('checked', true).trigger('change');
            $('#h-sort-options .filter-h-item').removeClass('active');
            $this.addClass('active');
            return;
        }

        $('#h-sort-options .filter-h-item').removeClass('active');
        $this.addClass('active');
        
        // Cập nhật filter bên sidebar tương ứng (nếu có)
        if (newSort) {
            if (newSort !== 'promo') {
                // Nếu chọn sắp xếp khác, tắt promoOnly
                $('#promoOnly').prop('checked', false);
            }
            
            const $sidebarInput = $(`input[name="sortFilter"][data-sort="${newSort}"]`);
            if ($sidebarInput.length) {
                $sidebarInput.prop('checked', true).trigger('change');
            } else {
                sortVal = newSort;
                readFilters(true);
                fetchProducts(true);
            }
        }
    });

    $('#h-filter-criteria .filter-h-item').on('click', function() {
        const $this = $(this);
        const filterType = $this.data('filter');
        
        if (filterType === 'default') {
            $filterClear.click();
            return;
        }

        // Khi chọn bất kỳ tiêu chí nào khác, bỏ active nút "Mặc định".
        $('#h-filter-criteria .filter-h-item[data-filter="default"]').removeClass('active');

        if (filterType === 'stock') {
            $this.toggleClass('active');
            $('#stockOnly').prop('checked', $this.hasClass('active')).trigger('change');
        } else if (filterType === 'new') {
            // Sắp xếp theo hàng mới nhất; trạng thái active của nút được
            // đồng bộ qua handler 'change' của input[name="sortFilter"].
            $('#h-sort-options .filter-h-item[data-sort="newest"]').click();
        } else if (filterType === 'price') {
            // Cuộn đến phần lọc giá ở sidebar
            $filterTabs.filter('[data-section="price"]').click();
            const $panel = $('.filter-panel');
            if ($panel.length) {
                $panel.animate({ scrollTop: $('#priceMin').offset().top - 200 }, 500);
            }
        }
    });

    $('#btn-toggle-sidebar-filter').on('click', function() {
        // Trên mobile, có thể cần toggle class hiển thị sidebar
        if (window.innerWidth <= 768) {
            $('.filter-panel').toggleClass('show'); 
        } else {
            // Trên desktop, có thể chỉ cần scroll lên đầu filter
            $('.filter-panel').animate({ scrollTop: 0 }, 300);
        }
    });

    // Đồng bộ ngược lại khi sidebar thay đổi
    $(document).on('change', 'input[name="sortFilter"]', function() {
        const val = $(this).data('sort');
        $('#h-sort-options .filter-h-item').removeClass('active');
        $(`#h-sort-options .filter-h-item[data-sort="${val}"]`).addClass('active');
        // Đồng bộ nút "Hàng mới về": chỉ active khi đang sắp xếp theo hàng mới nhất.
        $('#h-filter-criteria .filter-h-item[data-filter="new"]').toggleClass('active', val === 'newest');
    });

    $(document).on('change', '#stockOnly', function() {
        $('#h-filter-criteria .filter-h-item[data-filter="stock"]').toggleClass('active', $(this).is(':checked'));
    });

    $(document).on('change', '#promoOnly', function() {
        $('#h-sort-options .filter-h-item[data-sort="promo"]').toggleClass('active', $(this).is(':checked'));
        if ($(this).is(':checked')) {
            // Nếu bật khuyến mãi, bỏ active các mục sắp xếp khác
            $('#h-sort-options .filter-h-item').not('[data-sort="promo"]').removeClass('active');
        } else {
            // Nếu tắt khuyến mãi, quay lại mặc định (newest) nếu không có cái nào active
            if ($('#h-sort-options .filter-h-item.active').length === 0) {
                $('#h-sort-options .filter-h-item[data-sort="newest"]').addClass('active');
            }
        }
    });

    // Category Dropdown
    $('#h-category-filter').on('click', function(e) {
        e.stopPropagation();
        $('#categoryMenu').toggleClass('show');
        renderCategoryDropdown();
    });

    $('#h-category-grid').on('click', '.category-item-pill', function() {
        const $this = $(this);
        $this.toggleClass('selected');
    });

    $('#btn-category-close').on('click', function() {
        $('#categoryMenu').removeClass('show');
    });

    $('#btn-category-apply').on('click', function() {
        const selected = [];
        $('#h-category-grid .category-item-pill.selected').each(function() {
            const id = Number($(this).data('id'));
            if (id) selected.push(id);
        });

        filterState.catFilters = selected;

        // Đồng bộ lên sidebar
        $('#filterCategories input[type="checkbox"]').prop('checked', false);
        selected.forEach(id => {
            $('#filterCategories').find(`input[data-cat="${id}"]`).prop('checked', true);
        });

        $('#categoryMenu').removeClass('show');
        $('#h-category-filter').toggleClass('active', selected.length > 0);
        readFilters(true);
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.category-dropdown-wrapper').length) {
            $('#categoryMenu').removeClass('show');
        }
    });

    // Dropdown Hãng sản xuất
    function renderBrandDropdown() {
        const $grid = $('#h-brand-grid');
        if (!$grid.length) return;
        
        let html = '';
        brands.forEach(brand => {
            const name = esc(brand);
            const isSelected = filterState.brandFilters.includes(brand);
            html += `<div class="brand-item-pill ${isSelected ? 'selected' : ''}" data-brand="${name}">${name}</div>`;
        });
        
        $grid.html(html || '<div class="text-muted small px-3">Chưa có hãng đối tác.</div>');
    }

    $('#h-brand-filter').on('click', function(e) {
        e.stopPropagation();
        $('#brandMenu').toggleClass('show');
        renderBrandDropdown(); // Refresh states
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.brand-dropdown-wrapper').length) {
            $('#brandMenu').removeClass('show');
        }
    });

    $('#brandMenu').on('click', function(e) {
        e.stopPropagation();
    });

    $('#h-brand-grid').on('click', '.brand-item-pill', function() {
        $(this).toggleClass('selected');
    });

    $('#btn-brand-close').on('click', function() {
        $('#brandMenu').removeClass('show');
    });

    $('#btn-brand-apply').on('click', function() {
        const selected = [];
        $('#h-brand-grid .brand-item-pill.selected').each(function() {
            selected.push($(this).data('brand'));
        });

        // Đồng bộ lên sidebar
        $('#filterBrands input[type="checkbox"]').prop('checked', false);
        selected.forEach(brand => {
            $('#filterBrands').find(`input[data-brand="${brand}"]`).prop('checked', true);
        });

        $('#h-brand-filter').toggleClass('active', selected.length > 0);
        $('#brandMenu').removeClass('show');
        readFilters(true);
        fetchProducts(true);
    });

    // Đồng bộ ngược từ sidebar sang dropdown nếu cần
    $(document).on('change', '#filterBrands input', function() {
        renderBrandDropdown();
    });

    // Dropdown Xem theo giá
    const $hPriceMin = $('#h-price-min');
    const $hPriceMax = $('#h-price-max');
    const $hSliderMin = $('#h-slider-min');
    const $hSliderMax = $('#h-slider-max');
    const $hPriceFill = $('#h-price-fill');

    function updatePriceSlider() {
        let min = parseInt($hSliderMin.val());
        let max = parseInt($hSliderMax.val());

        if (min > max) {
            let tmp = min;
            min = max;
            max = tmp;
        }

        $hPriceMin.val(fmtPrice(min).replace('đ', ''));
        $hPriceMax.val(fmtPrice(max).replace('đ', ''));

        const percent1 = (min / $hSliderMin.attr('max')) * 100;
        const percent2 = (max / $hSliderMax.attr('max')) * 100;
        $hPriceFill.css({
            left: percent1 + '%',
            width: (percent2 - percent1) + '%'
        });
    }

    $('#h-price-filter').on('click', function(e) {
        e.stopPropagation();
        $('#priceMenu').toggleClass('show');
        // Đồng bộ từ filterState hiện tại
        const min = filterState.priceMin || 0;
        const max = filterState.priceMax || 50000000;
        $hSliderMin.val(min);
        $hSliderMax.val(max);
        updatePriceSlider();
    });

    $hSliderMin.on('input', updatePriceSlider);
    $hSliderMax.on('input', updatePriceSlider);

    $hPriceMin.on('change', function() {
        let val = parseInt($(this).val().replace(/\D/g, '')) || 0;
        $hSliderMin.val(val);
        updatePriceSlider();
    });

    $hPriceMax.on('change', function() {
        let val = parseInt($(this).val().replace(/\D/g, '')) || 0;
        $hSliderMax.val(val);
        updatePriceSlider();
    });

    $('#btn-price-close').on('click', function() {
        $('#priceMenu').removeClass('show');
    });

    $('#btn-price-apply').on('click', function() {
        let min = parseInt($hSliderMin.val());
        let max = parseInt($hSliderMax.val());
        if (min > max) { let t = min; min = max; max = t; }
        
        filterState.priceMin = min;
        filterState.priceMax = max;

        // Đồng bộ sang sidebar
        const pMinEl = document.getElementById('priceMin');
        const pMaxEl = document.getElementById('priceMax');
        if (pMinEl) {
            pMinEl.value = min;
            formatPriceInput(pMinEl);
        }
        if (pMaxEl) {
            pMaxEl.value = max;
            formatPriceInput(pMaxEl);
        }
        $('#priceMenu').removeClass('show');
        $('#h-price-filter').addClass('active');
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.price-dropdown-wrapper').length) {
            $('#priceMenu').removeClass('show');
        }
    });

    // Dropdown Không gian
    $('#h-space-filter').on('click', function(e) {
        e.stopPropagation();
        $('#spaceMenu').toggleClass('show');
    });

    $('#h-space-list').on('click', '.space-option-item', function() {
        const $this = $(this);
        const val = $this.data('value');
        
        if (val === 'all') {
            $('#h-space-list .space-option-item').removeClass('selected');
            $this.addClass('selected');
        } else {
            $('#h-space-list .space-option-item[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            // Nếu không còn cái nào được chọn, tự động quay về "Tất cả"
            if ($('#h-space-list .space-option-item.selected').length === 0) {
                $('#h-space-list .space-option-item[data-value="all"]').addClass('selected');
            }
        }
    });

    $('#btn-space-close').on('click', function() {
        $('#spaceMenu').removeClass('show');
    });

    $('#btn-space-apply').on('click', function() {
        const selected = [];
        $('#h-space-list .space-option-item.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') {
                selected.push(val);
            }
        });

        filterState.paintSpaceFilters = selected;
        $('#spaceMenu').removeClass('show');
        
        // Cập nhật trạng thái active cho nút filter
        $('#h-space-filter').toggleClass('active', selected.length > 0);
        
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.space-dropdown-wrapper').length) {
            $('#spaceMenu').removeClass('show');
        }
    });

    // Dropdown Vị trí
    $('#h-positions-filter').on('click', function(e) {
        e.stopPropagation();
        $('#positionsMenu').toggleClass('show');
    });

    $('#h-positions-list').on('click', '.positions-option-item', function() {
        const $this = $(this);
        const val = $this.data('value');
        
        if (val === 'all') {
            $('#h-positions-list .positions-option-item').removeClass('selected');
            $this.addClass('selected');
        } else {
            $('#h-positions-list .positions-option-item[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            if ($('#h-positions-list .positions-option-item.selected').length === 0) {
                $('#h-positions-list .positions-option-item[data-value="all"]').addClass('selected');
            }
        }
    });

    $('#btn-positions-close').on('click', function() {
        $('#positionsMenu').removeClass('show');
    });

    $('#btn-positions-apply').on('click', function() {
        const selected = [];
        $('#h-positions-list .positions-option-item.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') {
                selected.push(val);
            }
        });

        filterState.paintPositionsFilters = selected;
        $('#positionsMenu').removeClass('show');
        
        $('#h-positions-filter').toggleClass('active', selected.length > 0);
        
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.positions-dropdown-wrapper').length) {
            $('#positionsMenu').removeClass('show');
        }
    });

    // Dropdown Nhu cầu
    $('#h-needs-filter').on('click', function(e) {
        e.stopPropagation();
        $('#needsMenu').toggleClass('show');
    });

    $('#h-needs-list').on('click', '.needs-option-item', function() {
        const $this = $(this);
        const val = $this.data('value');
        
        if (val === 'all') {
            $('#h-needs-list .needs-option-item').removeClass('selected');
            $this.addClass('selected');
        } else {
            $('#h-needs-list .needs-option-item[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            if ($('#h-needs-list .needs-option-item.selected').length === 0) {
                $('#h-needs-list .needs-option-item[data-value="all"]').addClass('selected');
            }
        }
    });

    $('#btn-needs-close').on('click', function() {
        $('#needsMenu').removeClass('show');
    });

    $('#btn-needs-apply').on('click', function() {
        const selected = [];
        $('#h-needs-list .needs-option-item.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') {
                selected.push(val);
            }
        });

        filterState.paintNeedsFilters = selected;
        $('#needsMenu').removeClass('show');
        
        $('#h-needs-filter').toggleClass('active', selected.length > 0);
        
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.needs-dropdown-wrapper').length) {
            $('#needsMenu').removeClass('show');
        }
    });

    // Thu gọn/bung "Bộ lọc nâng cao" (chỉ trên PC). Mặc định ẩn các filter,
    // chỉ chừa thanh tìm kiếm; bấm nút để hiện lần lượt các filter + phần sắp xếp.
    $('#advFilterToggle').on('click', function() {
        var $btn = $(this);
        var $panel = $('#advFilterCollapsible');
        var willOpen = !$panel.hasClass('is-open');
        $panel.toggleClass('is-open', willOpen);
        $btn.toggleClass('is-open', willOpen);
        $btn.attr('aria-expanded', willOpen ? 'true' : 'false');
        $btn.find('.adv-toggle-label').text(willOpen ? 'Ẩn bộ lọc' : 'Hiện bộ lọc');
        // Khởi động lại animation hiện-lần-lượt mỗi lần mở
        if (willOpen) {
            $panel.find('.filter-h-item, .filter-h-title').each(function() {
                this.style.animation = 'none';
                void this.offsetWidth; // ép reflow để reset animation
                this.style.animation = '';
            });
        }
    });

    // Nếu vào trang đã có bộ lọc sẵn (danh mục / hãng / từ khoá từ URL),
    // tự bung panel để người dùng thấy được bộ lọc đang áp dụng.
    if (initialCatId || initialBrand || initialSearch) {
        $('#advFilterCollapsible').addClass('is-open');
        $('#advFilterToggle').addClass('is-open').attr('aria-expanded', 'true')
            .find('.adv-toggle-label').text('Ẩn bộ lọc');
    }

    // Grand Multi-Column Filter Popup
    $('#h-main-filter-btn').on('click', function(e) {
        e.stopPropagation();
        $('#mainFilterPopup').toggleClass('show');

        // Sync active states from filterState when opening
        syncMainFilterPopupFromState();
    });

    // Handle pill click in the main filter popup
    $('.main-filter-pills').on('click', '.filter-pill', function() {
        const $this = $(this);
        const val = $this.data('value');
        const $parent = $this.parent();
        
        if (val === 'all') {
            $parent.find('.filter-pill').removeClass('selected');
            $this.addClass('selected');
        } else {
            $parent.find('.filter-pill[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            // If nothing is selected, default back to "All"
            if ($parent.find('.filter-pill.selected').length === 0) {
                $parent.find('.filter-pill[data-value="all"]').addClass('selected');
            }
        }
    });

    // Close button of popup
    $('#btn-popup-close').on('click', function() {
        $('#mainFilterPopup').removeClass('show');
    });

    // Close when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.main-filter-popup-wrapper').length) {
            $('#mainFilterPopup').removeClass('show');
        }
    });

    // Helper to sync popup selections from current filterState
    function syncMainFilterPopupFromState() {
        // Space
        const spaces = filterState.paintSpaceFilters || [];
        $('#popup-space-list .filter-pill').removeClass('selected');
        if (spaces.length === 0) {
            $('#popup-space-list .filter-pill[data-value="all"]').addClass('selected');
        } else {
            spaces.forEach(val => {
                $(`#popup-space-list .filter-pill[data-value="${val}"]`).addClass('selected');
            });
        }

        // Positions
        const positions = filterState.paintPositionsFilters || [];
        $('#popup-positions-list .filter-pill').removeClass('selected');
        if (positions.length === 0) {
            $('#popup-positions-list .filter-pill[data-value="all"]').addClass('selected');
        } else {
            positions.forEach(val => {
                $(`#popup-positions-list .filter-pill[data-value="${val}"]`).addClass('selected');
            });
        }

        // Needs
        const needs = filterState.paintNeedsFilters || [];
        $('#popup-needs-list .filter-pill').removeClass('selected');
        if (needs.length === 0) {
            $('#popup-needs-list .filter-pill[data-value="all"]').addClass('selected');
        } else {
            needs.forEach(val => {
                $(`#popup-needs-list .filter-pill[data-value="${val}"]`).addClass('selected');
            });
        }
    }

    // Apply button click
    $('#btn-popup-apply').on('click', function() {
        // 1. Read Space
        const spaces = [];
        $('#popup-space-list .filter-pill.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') spaces.push(val);
        });
        filterState.paintSpaceFilters = spaces;

        // 2. Read Positions
        const positions = [];
        $('#popup-positions-list .filter-pill.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') positions.push(val);
        });
        filterState.paintPositionsFilters = positions;

        // 3. Read Needs
        const needs = [];
        $('#popup-needs-list .filter-pill.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') needs.push(val);
        });
        filterState.paintNeedsFilters = needs;

        // Sync back to individual desktop dropdown list active states so UI is unified
        // Space
        $('#h-space-list .space-option-item').removeClass('selected');
        if (spaces.length === 0) {
            $('#h-space-list .space-option-item[data-value="all"]').addClass('selected');
            $('#h-space-filter').removeClass('active');
        } else {
            spaces.forEach(val => {
                $(`#h-space-list .space-option-item[data-value="${val}"]`).addClass('selected');
            });
            $('#h-space-filter').addClass('active');
        }

        // Positions
        $('#h-positions-list .positions-option-item').removeClass('selected');
        if (positions.length === 0) {
            $('#h-positions-list .positions-option-item[data-value="all"]').addClass('selected');
            $('#h-positions-filter').removeClass('active');
        } else {
            positions.forEach(val => {
                $(`#h-positions-list .positions-option-item[data-value="${val}"]`).addClass('selected');
            });
            $('#h-positions-filter').addClass('active');
        }

        // Needs
        $('#h-needs-list .needs-option-item').removeClass('selected');
        if (needs.length === 0) {
            $('#h-needs-list .needs-option-item[data-value="all"]').addClass('selected');
            $('#h-needs-filter').removeClass('active');
        } else {
            needs.forEach(val => {
                $(`#h-needs-list .needs-option-item[data-value="${val}"]`).addClass('selected');
            });
            $('#h-needs-filter').addClass('active');
        }

        // Highlight main filter button if any of these are active
        const hasActiveMainFilters = spaces.length > 0 || positions.length > 0 || needs.length > 0;
        $('#h-main-filter-btn').toggleClass('active', hasActiveMainFilters);

        // Close popup
        $('#mainFilterPopup').removeClass('show');

        // Fetch products
        fetchProducts(true);
    });

    function updateActiveFiltersUI() {
        const $list = $('#activeFiltersList');
        const $section = $('#activeFiltersSection');
        $list.empty();
        let count = 0;

        // Đồng bộ trạng thái active cho các nút "Chọn theo tiêu chí" (mobile) theo filter thực tế.
        $('#m-criteria-cat').toggleClass('active', filterState.catFilters.length > 0);
        $('#m-criteria-price').toggleClass('active', filterState.priceMin !== null || filterState.priceMax !== null);
        $('#m-criteria-stock').toggleClass('active', !!filterState.stockOnly);

        const addTag = (label, value, type, originalValue) => {
            count++;
            const $tag = $(`
                <div class="filter-tag">
                    <span class="tag-label">${label}:</span>
                    <span class="tag-value">${value}</span>
                    <span class="tag-remove" data-type="${type}" data-value="${originalValue || value}"><i class="bi bi-x"></i></span>
                </div>
            `);
            $list.append($tag);
        };

        // Category Filters
        if (filterState.catFilters.length) {
            filterState.catFilters.forEach(id => {
                const name = $(`.category-item-pill[data-id="${id}"]`).data('name') || id;
                addTag('Danh mục', name, 'category', id);
            });
        }

        // Brand Filters
        if (filterState.brandFilters.length) {
            filterState.brandFilters.forEach(id => {
                const name = $(`.brand-item-pill[data-id="${id}"]`).data('name') || id;
                addTag('Thương hiệu', name, 'brand', id);
            });
        }

        // Space Filters
        if (filterState.paintSpaceFilters.length) {
            filterState.paintSpaceFilters.forEach(val => {
                addTag('Không gian', val, 'space');
            });
        }

        // Positions Filters
        if (filterState.paintPositionsFilters.length) {
            filterState.paintPositionsFilters.forEach(val => {
                addTag('Vị trí', val, 'positions');
            });
        }

        // Needs Filters
        if (filterState.paintNeedsFilters.length) {
            filterState.paintNeedsFilters.forEach(val => {
                addTag('Nhu cầu', val, 'needs');
            });
        }

        // Price Filters
        if (filterState.priceMin !== null || filterState.priceMax !== null) {
            let priceLabel = '';
            if (filterState.priceMin && filterState.priceMax) priceLabel = `${Number(filterState.priceMin).toLocaleString()}đ - ${Number(filterState.priceMax).toLocaleString()}đ`;
            else if (filterState.priceMin) priceLabel = `Trên ${Number(filterState.priceMin).toLocaleString()}đ`;
            else if (filterState.priceMax) priceLabel = `Dưới ${Number(filterState.priceMax).toLocaleString()}đ`;
            
            if (priceLabel) addTag('Giá', priceLabel, 'price');
        }

        if (count > 0) {
            if (!$('#btn-clear-all-tags').length) {
                $list.append('<span class="clear-all-filters" id="btn-clear-all-tags">Bỏ chọn tất cả</span>');
            }
            $section.addClass('show');
        } else {
            $section.removeClass('show');
        }
    }

    // Handle tag removal
    $('#activeFiltersList').on('click', '.tag-remove', function() {
        const type = $(this).data('type');
        const value = $(this).data('value');

        if (type === 'category') {
            filterState.catFilters = filterState.catFilters.filter(id => Number(id) !== Number(value));
            $(`.category-item-pill[data-id="${value}"]`).removeClass('selected');
            $(`#filterCategories input[data-cat="${value}"]`).prop('checked', false);
        } else if (type === 'brand') {
            filterState.brandFilters = filterState.brandFilters.filter(id => String(id) !== String(value));
            $(`.brand-item-pill[data-id="${value}"]`).removeClass('selected');
            $(`#filterBrands input[value="${value}"]`).prop('checked', false);
        } else if (type === 'space') {
            filterState.paintSpaceFilters = filterState.paintSpaceFilters.filter(v => v !== value);
            $(`.space-option-item[data-value="${value}"]`).removeClass('selected');
            if (filterState.paintSpaceFilters.length === 0) $('.space-option-item[data-value="all"]').addClass('selected');
        } else if (type === 'positions') {
            filterState.paintPositionsFilters = filterState.paintPositionsFilters.filter(v => v !== value);
            $(`.positions-option-item[data-value="${value}"]`).removeClass('selected');
            if (filterState.paintPositionsFilters.length === 0) $('.positions-option-item[data-value="all"]').addClass('selected');
        } else if (type === 'needs') {
            filterState.paintNeedsFilters = filterState.paintNeedsFilters.filter(v => v !== value);
            $(`.needs-option-item[data-value="${value}"]`).removeClass('selected');
            if (filterState.paintNeedsFilters.length === 0) $('.needs-option-item[data-value="all"]').addClass('selected');
        } else if (type === 'price') {
            filterState.priceMin = null;
            filterState.priceMax = null;
            $('#priceMin, #priceMax').val('');
            $('#h-slider-min').val(0);
            $('#h-slider-max').val(50000000);
            $('#h-price-min').val('');
            $('#h-price-max').val('');
        }

        fetchProducts(true);
    });

    $('#activeFiltersList').on('click', '#btn-clear-all-tags', function() {
        $('#filterClearBtn').trigger('click');
    });

    // ---------------- MOBILE FILTER INTERACTION ---------------- //
    const $mobileSearch = $('#mobileSearchBox');
    
    // Move bottom sheet menu elements to body on mobile screen size to avoid being hidden by d-none parent
    if (window.innerWidth <= 768) {
        $('#categoryMenu, #priceMenu, #brandMenu, #spaceMenu, #positionsMenu, #needsMenu').appendTo('body');
    }

    // Sync search input
    if ($mobileSearch.length && initialSearch) {
        $mobileSearch.val(initialSearch);
    }
    
    $mobileSearch.on('keyup', function() {
        searchTerm = $(this).val();
        $search.val(searchTerm); // Sync to desktop input as well
        fetchProducts(true);
    });

    // 1. Criteria "Sẵn hàng" (stock)
    $('#m-criteria-stock').on('click', function() {
        const $this = $(this);
        $this.toggleClass('active');
        const isActive = $this.hasClass('active');
        $('#stockOnly').prop('checked', isActive).trigger('change');
        // Sync desktop element if visible
        $('#h-filter-criteria .filter-h-item[data-filter="stock"]').toggleClass('active', isActive);
    });

    // Sync back when stock changes from anywhere
    $(document).on('change', '#stockOnly', function() {
        $('#m-criteria-stock').toggleClass('active', $(this).is(':checked'));
    });

    // 2. Criteria "Xem theo giá" (price)
    $('#m-criteria-price').on('click', function(e) {
        e.stopPropagation();
        $('#priceMenu').toggleClass('show');
        // Synchronize range
        const min = filterState.priceMin || 0;
        const max = filterState.priceMax || 50000000;
        $hSliderMin.val(min);
        $hSliderMax.val(max);
        updatePriceSlider();
    });

    // 3. Criteria "Hàng mới" (new) — active khi đang sắp xếp theo hàng mới.
    $('#m-criteria-new').on('click', function() {
        // Chọn sort "newest" qua radio (kích hoạt change -> readFilters + fetchProducts).
        $('input[name="sortFilter"][data-sort="newest"]').prop('checked', true).trigger('change');
        $('#m-criteria-new').addClass('active');
    });

    // Khi đổi sort qua các tab khác thì bỏ active nút "Hàng mới".
    $('#m-tab-promo, #m-tab-price').on('click', function() {
        $('#m-criteria-new').removeClass('active');
    });

    // 4. Criteria "Danh mục" (category)
    $('#m-criteria-cat').on('click', function(e) {
        e.stopPropagation();
        $('#categoryMenu').toggleClass('show');
        renderCategoryDropdown();
    });

    // 5. Mobile Tab "Phổ biến"
    $('#m-tab-popular').on('click', function() {
        $('.mobile-tab-item').removeClass('active');
        $(this).addClass('active');
        $('#h-sort-options .filter-h-item[data-sort="newest"]').click();
    });

    // 6. Mobile Tab "Khuyến mãi HOT"
    $('#m-tab-promo').on('click', function() {
        $('.mobile-tab-item').removeClass('active');
        $(this).addClass('active');
        $('#h-sort-options .filter-h-item[data-sort="promo"]').click();
    });

    // 7. Mobile Tab "Giá" (Ascending/Descending toggle)
    $('#m-tab-price').on('click', function() {
        $('.mobile-tab-item').removeClass('active');
        $(this).addClass('active');
        
        let currentSort = $(this).attr('data-sort');
        let nextSort = currentSort === 'price_asc' ? 'price_desc' : 'price_asc';
        $(this).attr('data-sort', nextSort);
        
        // Show corresponding icon
        if (nextSort === 'price_asc') {
            $(this).html('Giá <i class="bi bi-sort-numeric-down text-primary"></i>');
        } else {
            $(this).html('Giá <i class="bi bi-sort-numeric-up-alt text-primary"></i>');
        }
        
        $(`#h-sort-options .filter-h-item[data-sort="${nextSort}"]`).click();
    });

    // Đồng bộ trạng thái các control trong sidebar/drawer theo filterState hiện tại,
    // để khi mở "Bộ lọc" các mục đã chọn ở nơi khác (chip, dropdown danh mục...) được tick sẵn.
    function syncSidebarFromState() {
        // Danh mục
        $('#filterCategories input[type="checkbox"]').prop('checked', false);
        filterState.catFilters.forEach(id => {
            $(`#filterCategories input[data-cat="${id}"]`).prop('checked', true);
        });
        // Hãng
        $('#filterBrands input[type="checkbox"]').prop('checked', false);
        filterState.brandFilters.forEach(b => {
            $(`#filterBrands input[data-brand="${b}"]`).prop('checked', true);
        });
        // Còn hàng / khuyến mãi
        $('#stockOnly').prop('checked', !!filterState.stockOnly);
        $('#promoOnly').prop('checked', !!filterState.promoOnly);
        // Giá
        $('#priceMin').val(filterState.priceMin ? Number(filterState.priceMin).toLocaleString('vi-VN') : '');
        $('#priceMax').val(filterState.priceMax ? Number(filterState.priceMax).toLocaleString('vi-VN') : '');
        // Đánh giá
        if (filterState.ratingMin > 0) {
            $(`input[name="ratingFilter"][data-rating="${filterState.ratingMin}"]`).prop('checked', true);
        } else {
            $('input[name="ratingFilter"]').prop('checked', false);
        }
        // Sắp xếp
        $(`input[name="sortFilter"][data-sort="${sortVal}"]`).prop('checked', true);
    }

    // 8. Mobile Tab "Bộ lọc" (slide drawer)
    $('#m-tab-filter').on('click', function(e) {
        e.stopPropagation();
        syncSidebarFromState();
        $('.filter-panel').addClass('show');
        $('#mobileFilterOverlay').addClass('show');
    });

    // Close drawers on overlay click
    $('#mobileFilterOverlay').on('click', function() {
        $('.filter-panel').removeClass('show');
        $('#mobileFilterOverlay').removeClass('show');
    });

    // Also close on sidebar apply/clear
    $filterApply.on('click', function() {
        $('.filter-panel').removeClass('show');
        $('#mobileFilterOverlay').removeClass('show');
    });
    $filterClear.on('click', function() {
        $('.filter-panel').removeClass('show');
        $('#mobileFilterOverlay').removeClass('show');
        $('#m-criteria-stock').removeClass('active');
    });

    // ===== Gallery carousel: hover hiện ảnh media + nút prev/next =====
    function goToSlide($container, idx) {
        const $slides = $container.find('.gallery-slide-img');
        const $dots   = $container.find('.gallery-dots .gdot');
        const n = $slides.length;
        if (n <= 1) return;
        idx = ((idx % n) + n) % n;
        $slides.removeClass('active').eq(idx).addClass('active');
        $dots.removeClass('active').eq(idx).addClass('active');
    }

    function currentIdx($container) {
        const i = $container.find('.gallery-slide-img.active').index();
        return i < 0 ? 0 : i;
    }

    // Nút trước
    $grid.on('click', '.gallery-prev-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $container = $(this).closest('.gallery-carousel-container');
        goToSlide($container, currentIdx($container) - 1);
    });

    // Nút sau
    $grid.on('click', '.gallery-next-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $container = $(this).closest('.gallery-carousel-container');
        goToSlide($container, currentIdx($container) + 1);
    });

    // Hover vào -> hiện ảnh media đầu tiên (slide 1); xem thêm bằng nút prev/next
    $grid.on('mouseenter', '.shopping-img-wrapper', function() {
        const $container = $(this).find('.gallery-carousel-container');
        const $slides = $container.find('.gallery-slide-img');
        if ($slides.length <= 1) return;
        goToSlide($container, 1);
    });

    // Rời chuột -> về ảnh đại diện (slide đầu)
    $grid.on('mouseleave', '.shopping-img-wrapper', function() {
        const $container = $(this).find('.gallery-carousel-container');
        if ($container.find('.gallery-slide-img').length > 1) {
            goToSlide($container, 0);
        }
    });

    // Click handler for favorite button on card
    $grid.on('click', '.btn-favorite-card', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(this);
        const pid = $btn.data('pid');
        if (!pid) return;
        
        $btn.prop('disabled', true);
        $.post(FAVORITE_API, {
            action: 'toggle',
            pid: pid
        }, function(res) {
            if (res && res.ok) {
                const liked = !!res.liked;
                $btn.toggleClass('active', liked);
                $btn.attr('title', liked ? 'Bỏ yêu thích' : 'Yêu thích');
                if (typeof toastr !== 'undefined') {
                    if (liked) toastr.success('Đã thêm vào danh sách yêu thích');
                    else toastr.info('Đã bỏ yêu thích');
                }
            } else {
                if (typeof toastr !== 'undefined') toastr.error(res.msg || 'Thao tác thất bại');
            }
        }).fail(function() {
            if (typeof toastr !== 'undefined') toastr.error('Không thể kết nối đến máy chủ');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    if (window.refreshCartBadge) window.refreshCartBadge();
    loadCats();
    loadBrands();
    // Đồng bộ UI thanh lọc ngang với trạng thái mặc định (Còn hàng)
    $('#h-filter-criteria .filter-h-item[data-filter="stock"]').toggleClass('active', $('#stockOnly').is(':checked'));
    $('#m-criteria-stock').toggleClass('active', $('#stockOnly').is(':checked'));
    readFilters();
    if (!initialCatId && !initialCatSlug && !initialBrand) runInitialFetch();
})();
</script>
