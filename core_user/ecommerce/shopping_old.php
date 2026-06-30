<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config.php';
$ithanhloc->set_charset('utf8mb4');  
?>
<style>
/* CSS Grid Control via JS Variables */
.shopping.product-list-container {
    display: grid !important;
    grid-template-columns: repeat(var(--pc-cols, 6), minmax(0, 1fr)) !important;
    gap: 16px;
}

.filter-panel {
    max-height: calc(100vh - 100px) !important;
    overflow-y: auto !important;
    position: sticky !important;
    top: 80px !important;
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}

.filter-panel::-webkit-scrollbar {
    display: none !important; /* Chrome, Safari and Opera */
}

@media (max-width: 768px) {
    .shopping.product-list-container {
        grid-template-columns: repeat(var(--mobile-cols, 2), minmax(0, 1fr)) !important;
    }
    .filter-panel {
        max-height: 50vh !important;
        position: relative !important;
        top: 0 !important;
        margin-bottom: 20px;
    }
}
</style>
<div class="order-layout">
    <aside class="filter-panel">
        <div class="filter-head"><i class="bi bi-funnel"></i> Bộ lọc tìm kiếm</div>
        <div class="filter-tabs" role="tablist" aria-label="Bộ lọc">
            <button type="button" class="filter-tab is-active" data-section="cat">Danh mục</button>
            <button type="button" class="filter-tab" data-section="brand">Hãng</button>
            <button type="button" class="filter-tab" data-section="price">Giá</button>
            <button type="button" class="filter-tab" data-section="sort">Sắp xếp</button>
            <button type="button" class="filter-tab" data-section="rating">Đánh giá</button>
        </div>
        <div class="filter-section is-active" data-section="cat">
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
                <label class="filter-option"><input type="radio" name="priceFilter" data-min="0" data-max="1000000"> Dưới 1 triệu</label>
                <label class="filter-option"><input type="radio" name="priceFilter" data-min="1000000" data-max="3000000"> 1 - 3 triệu</label>
                <label class="filter-option"><input type="radio" name="priceFilter" data-min="3000000" data-max=""> Trên 3 triệu</label>
                <div class="mt-3">
                    <div class="filter-title mb-2" style="font-size: 0.6rem; opacity: 0.8;">Hoặc nhập khoảng giá</div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" id="priceMin" class="form-control form-control-sm" placeholder="Từ" style="border-radius: 4px;">
                        <span class="text-muted">-</span>
                        <input type="number" id="priceMax" class="form-control form-control-sm" placeholder="Đến" style="border-radius: 4px;">
                    </div>
                </div>
            </div>
        </div>
        <div class="filter-section" data-section="sort">
            <div class="filter-group mt-3">
                <div class="filter-title">Trạng thái</div>
                <label class="filter-option"><input type="checkbox" id="stockOnly" checked> Còn hàng</label>
                <label class="filter-option"><input type="checkbox" id="promoOnly"> Đang khuyến mãi</label>
            </div>
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
    <section class="scard sborder-0 sshadow-sm product-panel col-12">
        <div class="scard-body">
            <div class="mb-3">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input id="searchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
                </div>
            </div>
            <div id="activeBrandNotice" class="active-filter-note"><i class="bi bi-funnel-fill"></i><span></span></div>
            <div id="productGrid" class="shopping product-list-container"></div>
            <div id="emptyProducts" class="text-center text-muted py-4" style="display:none;">Không tìm thấy sản phẩm.</div>
            <div class="text-center mt-3" id="loadMoreWrap" style="display:none;">
                <button class="btn btn-outline-primary btn-sm px-4" id="loadMoreBtn">Xem thêm</button>
            </div>
            <div id="productSentinel" style="height: 10px;"></div>
        </div>
    </section>
</div>

<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
    const BASE_URL = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') ? '' : '<?= h($baseUrl) ?>';
    const FALLBACK_IMG = '<?= h($fallbackImage) ?>';

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
    const filterState = { catFilters: [], brandFilters: [], priceMin: null, priceMax: null, ratingMin: 0, stockOnly: true, promoOnly: false };
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

    function loadCats(){
        $.get(API, { ajax: 'categories' }, res => {
            cats = (res && res.ok) ? (res.data || []) : [];
            // Chỉ ẩn bớt danh mục không có sản phẩm, nhưng luôn giữ lại danh mục được chọn từ URL (?cat=...)
            cats = cats.filter(c => {
                const id = Number(c.id || 0);
                const hasProducts = Number(c.product_count || 0) > 0;
                return hasProducts || id === 0 || id === initialCatId;
            });
            renderCategoryFilters();
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
        const selectedCats = [];
        $filterCategories.find('input[data-cat]:checked').each(function(){
            const id = Number($(this).data('cat'));
            if (id) selectedCats.push(id);
        });
        filterState.catFilters = selectedCats;

        const selectedBrands = [];
        $filterBrands.find('input[data-brand]:checked').each(function(){
            const name = String($(this).data('brand') || '').trim();
            if (name) selectedBrands.push(name);
        });
        filterState.brandFilters = selectedBrands;
        updateBrandNotice();

        const $price = $('input[name="priceFilter"]:checked');
        const pMin = $('#priceMin').val();
        const pMax = $('#priceMax').val();

        if (pMin !== '' || pMax !== '') {
            filterState.priceMin = pMin !== '' ? Number(pMin) : null;
            filterState.priceMax = pMax !== '' ? Number(pMax) : null;
            // Nếu nhập thủ công thì bỏ chọn radio
            if (manual) $('input[name="priceFilter"]').prop('checked', false);
        } else {
            filterState.priceMin = $price.length ? Number($price.data('min') || 0) : null;
            const maxRaw = $price.length ? String($price.data('max') ?? '') : '';
            filterState.priceMax = maxRaw === '' ? null : Number(maxRaw || 0);
        }

        const $rating = $('input[name="ratingFilter"]:checked');
        filterState.ratingMin = $rating.length ? Number($rating.data('rating') || 0) : 0;

        filterState.stockOnly = $('#stockOnly').is(':checked');
        filterState.promoOnly = $('#promoOnly').is(':checked');
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

        // Badge giảm giá: ưu tiên voucher text
        let discountText = '';
        if (voucherBadge) {
            let raw = voucherBadge.toString().trim();
            let label = raw;
            const m = raw.match(/^Giảm\s+(\d+)\s*%?$/i);
            if (m) label = '-' + m[1] + '%';
            else if (/^\d+\s*[kK]$/.test(raw)) {
                const num = raw.replace(/[^0-9]/g, '');
                label = num ? '-' + num + 'K' : '-';
            } else if (/^Giảm\s+\d+[kK]?/i.test(raw)) label = '-' + raw.replace(/Giảm\s+/i, '');
            else if (/^\d+$/.test(raw)) label = '-' + raw + '%';
            else label = raw.replace(/^Giảm\s*/i, '-');
            discountText = label;
        } else if (discount > 0) {
            discountText = (discount >= 100) ? 'Free' : ('-' + discount + '%');
        }

        const discountBadgeHtml = discountText
            ? `<div class="badge-discount">${$('<div>').text(discountText).html()}</div>`
            : '';

        // Badge voucher/freeship
        let voucherHtml = '';
        if (hasShip) {
            const raw = (shipLabel || '').toString().trim();
            const line2 = (raw === '100%' || raw === '100') ? '100%' : ('Giảm ship ' + raw);
            voucherHtml = `<div class="badge-voucher">FREESHIP<br><span style="font-style:italic;">${$('<div>').text(line2).html()}</span></div>`;
        }

        // Danh mục (nếu có)
        const catName = String(p.category_name || p.category || '').trim();
        const catHtml = catName ? `<span class="shopping-product-category">${$('<div>').text(catName).html()}</span>` : '';

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
                    <img src="${$('<div>').text(img).html()}" alt="${esc(safeName)}" loading="lazy" decoding="async" onerror="this.src='${esc(FALLBACK_IMG)}'">
                    ${discountBadgeHtml}
                    ${voucherHtml}
                </div>

                <div class="shopping-product-content">
                    <div class="shopping-product-title">${catHtml}${safeName}</div>
                    ${promoHtml}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">${priceHtml}</div>
                       
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-1">
                        ${ratingHtml}
                        <div class="btn-fav-item" data-pid="${pid}">
                            <i class="bi bi-heart"></i>
                            <span class="fav-text">Yêu thích</span>
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

    $(document).on('change', '#filterCategories input, #filterBrands input, input[name="priceFilter"], input[name="ratingFilter"], input[name="sortFilter"], #stockOnly, #promoOnly', function(){
        if ($(this).attr('name') === 'priceFilter') {
            $('#priceMin, #priceMax').val('');
        }
        const $sort = $('input[name="sortFilter"]:checked');
        sortVal = $sort.length ? String($sort.data('sort') || 'newest') : 'newest';
        readFilters(true);
        fetchProducts(true);
    });

    $(document).on('input', '#priceMin, #priceMax', function(){
        // debounce if needed, but usually simple enough
        readFilters(true);
        fetchProducts(true);
    });

    $filterApply.on('click', function(){
        const $sort = $('input[name="sortFilter"]:checked');
        sortVal = $sort.length ? String($sort.data('sort') || 'newest') : 'newest';
        readFilters(true);
        fetchProducts(true);
    });

    $filterClear.on('click', function(){
        $('#filterCategories input, #filterBrands input').prop('checked', false);
        $('input[name="priceFilter"], input[name="ratingFilter"], input[name="sortFilter"]').prop('checked', false);
        $('#priceMin, #priceMax').val('');
        $('#stockOnly').prop('checked', true); // Ưu tiên Còn hàng
        $('#promoOnly').prop('checked', false);
        sortVal = 'newest';
        $('input[name="sortFilter"][data-sort="newest"]').prop('checked', true);
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

    if (window.refreshCartBadge) window.refreshCartBadge();
    loadCats();
    loadBrands();
    readFilters();
    if (!initialCatId && !initialCatSlug && !initialBrand) runInitialFetch();
})();
</script>
