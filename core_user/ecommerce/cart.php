
<div class="cart-body p-2">
    <div class="cart-layout">
        <div>
            <div class="items-head">
                <div>
                    <div class="items-title">Danh sách sản phẩm</div>
                    <div class="items-meta">Kiểm tra lại từng sản phẩm trước khi áp dụng ưu đãi.</div>
                </div>
            </div>
            <div id="cartWrap" class="____cart-items-list p-0 mt-2 m-0">
                <!-- Cart Skeleton Loading -->
                <div class="cart-loading-skeleton">
                    <?php for($i=0;$i<3;$i++): ?>
                    <div class="cart-item product-card-cart d-flex align-items-center mb-3 p-2" style="background:#fff;border-radius:12px;border:1px solid #f1f5f9;">
                        <div class="skeleton-line" style="width:70px;height:70px;border-radius:10px;flex-shrink:0;"></div>
                        <div class="ms-3 flex-grow-1">
                            <div class="skeleton-line w-75 mb-2" style="height:16px;"></div>
                            <div class="skeleton-line w-50 mb-3" style="height:12px;"></div>
                            <div class="d-flex justify-content-between">
                                <div class="skeleton-line w-25" style="height:14px;"></div>
                                <div class="skeleton-line" style="width:40px;height:14px;"></div>
                            </div>
                        </div>
                    </div>
                   <?php endfor; ?>
                </div>
            </div>
        </div>
        <aside class="summary-card">
            <div class="summary-row"><span>Tạm tính</span><span id="sumSubtotal">0 đ</span></div>
            <div class="summary-row"><span>Phí ship dự kiến</span><span id="sumShip">0 đ</span></div>
            <div class="summary-voucher">
                <div>Voucher:</div>
                <div class="summary-voucher-item"><span><i class="bi bi-gift"></i> <span>Đơn hàng</span></span><span id="sumVoucherOrder">0 đ</span></div>
                <div class="summary-voucher-item"><span><i class="bi bi-truck"></i> <span>Phí vận chuyển</span></span><span id="sumVoucherShip">0 đ</span></div>
                <div class="summary-voucher-item"><span><i class="bi bi-percent"></i> <span>Bạn tiết kiệm được</span></span><span class="text-danger" id="sumSaved">0 đ</span></div>
            </div>
            <div class="summary-divider"></div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Tổng thanh toán</span>
                <span class="summary-total" id="sumTotal">0 đ</span>
            </div>
            <div class="text-muted small h1" style="margin-top:2px;">*Giá chưa bao gồm VAT (<span id="vatPercentNote">8</span>%)*</div>
            
            <div class="d-grid gap-2 mt-2">
                <a class="btn btn-primary btn-rounded" href="<?= h($baseUrl) ?>/checkout">Mua ngay</a>
                <a class="btn btn-outline-secondary" href="<?= h($baseUrl) ?>/shopping"> Quay lại</a>
            </div>
            <div class="text-muted small">Ưu đãi được áp dụng ở bước thanh toán cuối cùng.</div>
        </aside>
    </div>
</div>
<div class="voucher-sticky" id="voucherSticky">
    <div class="voucher-sticky-inner">
        <div class="vs-row vs-row-voucher">
            <div class="vs-left">
                <span class="vs-icon-vip">VOUCHER</span>
            </div>
            <div class="vs-right">
                <div class="vs-voucher-summary" id="voucherStickyText">
                    <span class="vs-voucher-line vs-voucher-line-order" id="voucherStickyOrder"><i class="bi bi-gift me-1 text-danger"></i> <span>Đơn hàng</span></span>
                    <span class="vs-voucher-sep">|</span>
                    <span class="vs-voucher-line vs-voucher-line-ship" id="voucherStickyShip"><i class="bi bi-truck me-1 text-info"></i> <span>Phí vận chuyển</span></span>
                </div>
                <button type="button" class="vs-arrow-btn" id="voucherOpen" aria-label="Chọn voucher">
                    <span class="vs-arrow">&#10095;</span>
                </button>
            </div>
        </div>
        <div class="d-none vs-row vs-row-coin">
            <div class="vs-left">
                <span class="vs-icon-coin">S</span>
                <span class="vs-text-main">Dùng Xu để tiết kiệm thêm</span>
                <span class="vs-icon-help">?</span>
            </div>
            <div class="vs-right">
                <label class="vs-switch">
                    <input type="checkbox" id="vsUseCoin" disabled>
                    <span class="vs-slider"></span>
                </label>
            </div>
        </div>
    </div>
</div>

<div class="cart-sticky" id="cartSticky">
    <div class="cart-sticky-inner vs-row vs-row-checkout">
        <div class="vs-left">
            <label class="vs-checkbox-container">
                <input type="checkbox" id="selectAll" class="vs-checkbox">
                <span>Tất cả</span>
                <span class="vs-selected-count">(<span id="selectedCount">0</span>)</span>
            </label>
            <button class="btn btn-sm btn-primary" id="btnRemoveSelected" type="button"><i class="bi bi-trash"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="btnClear" type="button"><i class="bi bi-x-circle"></i> <span>Dọn dẹp</span></button>
            <button class="btn btn-sm btn-outline-secondary" id="btnClearVoucher" type="button"><i class="bi bi-x-circle"></i> <span>Voucher</span></button>
        </div>
        <div class="vs-right">
            <div class="vs-price-section">
                <div class="vs-price-top">
                    <span class="vs-icon-truck"></span>
                    <span class="vs-total-price" id="stickyTotal">0 đ</span>
                    <span class="vs-price-arrow">︾</span>
                </div>
                <div class="vs-savings-text" id="stickySaved">Tiết kiệm 0 đ</div>
            </div>
            <div class="vs-actions">
                <button class="btn btn-sm btn-primary" id="btnBuySelected" type="button"><span>Mua hàng</span> (<span id="selectedCountBtn">0</span>)</button>
            </div>
        </div>
    </div>
</div>

<div id="cartVariantPicker" class="variant-picker-overlay d-none">
    <div class="variant-picker-content">
        <div class="variant-picker-header">
            <div>
                <h6 class="m-0 fw-bold">Chọn phân loại</h6>
                <div class="text-muted small mt-1">Vui lòng chọn phân loại bạn muốn</div>
            </div>
            <button type="button" class="btn-close picker-close shadow-none" aria-label="Close"></button>
        </div>
        <div id="cartVariantPickerBody" class="variant-picker-body">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<div id="bxgyGiftDropdown" class="variant-dropdown d-none">
    <div id="bxgyGiftDropdownBody" class="variant-dropdown-body small text-muted px-3 py-2">
        <div class="skeleton-line w-75 mb-2" style="height:12px;"></div>
        <div class="skeleton-line w-50" style="height:12px;"></div>
    </div>
</div>

<script>
(function(){
    // API endpoints và các hằng số khác
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
    // API để validate và áp dụng voucher, cũng như lưu trạng thái voucher đã chọn vào session
    const VOUCHER_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
    // API để tính phí vận chuyển và báo giá chi tiết
    const SHIPPING_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/shipping.php';
    // BASE_URL để chuyển URL tương đối thành tuyệt đối khi cần, ví dụ cho ảnh sản phẩm
    const BASE_URL = '<?= h($baseUrl) ?>';
    // Hàm tiện ích để hiển thị thông báo, ưu tiên sử dụng toastr nếu có, fallback về alert nếu không
    const notify = (msg, type = 'info') => {
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    };

    const formatWeight = (value, unit) => {
        let v = parseFloat(value) || 0;
        if (v <= 0) return '';
        let u = (unit || 'kg').toLowerCase().trim();
        if (v >= 1000) {
            if (u === 'gr' || u === 'gram' || u === 'g') { v /= 1000; u = 'kg'; }
            else if (u === 'ml') { v /= 1000; u = 'l'; }
        }
        const unitMap = { 'kg': 'kg', 'l': 'L', 'gr': 'gr', 'g': 'gr', 'gram': 'gr', 'ml': 'ml', 'oz': 'oz' };
        const displayUnit = unitMap[u] || u;
        return parseFloat(v.toFixed(3)) + (['kg', 'l'].includes(u) ? '' : ' ') + displayUnit;
    };

    // Trạng thái hiện tại của giỏ hàng và voucher
    let lastItems = [];
    let vouchers = [];
    let savedVoucherCodes = [];
    let selectedVoucherOrderCode = '';
    let selectedVoucherShipCode = '';
    let suggestedVoucherOrderCode = '';
    let suggestedVoucherShipCode = '';
    let voucherTab = 'order';
    let orderDiscount = 0;
    let shippingVoucherDiscount = 0;
    let shippingFee = 0;
    let shippingQuote = null;
    let cartVariantState = null;
    let $variantDropdown = null;
    // BXGY promos + mapping promos áp dụng theo từng dòng cart
    let bxgyPromos = [];
    let bxgyAppliedPromosByKey = {};
    const bxgyGiftVariantsCache = {};
    let bxgyDropdownState = null;
    const selectedKeys = new Set();
    const $cartSticky = $('#cartSticky');
    const $cartBody = $('.cart-body');
    const $voucherSticky = $('#voucherSticky');
    let stickyHover = false;
    let stickyHoverTimer = null;

    let $bxgyDropdown = null;

    // Đồng bộ dữ liệu BXGY (promo + mapping áp dụng) từ response API nếu có
    function syncBxgyFromResponse(res){
        if (!res) return;
        if (Array.isArray(res.bxgy_promos)) {
            bxgyPromos = res.bxgy_promos;
        }
        if (res.bxgy_applied_promos && typeof res.bxgy_applied_promos === 'object') {
            bxgyAppliedPromosByKey = res.bxgy_applied_promos;
        }
    }

    function hideVariantDropdown(){
        const $picker = $('#cartVariantPicker');
        if (!$picker.length) return;
        $picker.removeClass('is-visible');
        setTimeout(() => {
            $picker.addClass('d-none');
            document.body.style.overflow = '';
        }, 300);
        $('.variant-selector.is-open').removeClass('is-open');
        // Hiện lại nút giỏ nổi khi đóng picker
        document.getElementById('floatingCart')?.classList.remove('floating-cart-hidden');
        cartVariantState = null;
    }

    function hideBxgyDropdown(){
        if (!$bxgyDropdown) $bxgyDropdown = $('#bxgyGiftDropdown');
        if (!$bxgyDropdown.length) return;
        $bxgyDropdown.addClass('d-none').removeAttr('style');
        $('#bxgyGiftDropdownBody').empty();
        $('.bxgy-selector.is-open').removeClass('is-open');
        bxgyDropdownState = null;
    }

    // Bật/tắt trạng thái hiển thị của cart sticky dựa trên việc có sản phẩm nào được chọn hay không
    const toggleCartSticky = () => {
        if (!$cartSticky.length) return;
        const isHiddenBySelection = selectedKeys.size === 0;
        const shouldHide = isHiddenBySelection && !stickyHover;
        $cartSticky.toggleClass('is-hidden', shouldHide);
        $cartBody.toggleClass('has-cart-sticky', !shouldHide);
        document.body.classList.toggle('has-cart-sticky', !shouldHide);
    };
    // Chuyển URL tương đối thành tuyệt đối, giữ nguyên URL tuyệt đối hoặc data URI
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
    // Chuẩn hóa mã màu hex (#RRGGBB/#RGB), trả về chuỗi hợp lệ hoặc rỗng
    function normalizeColorHex(hex){
        const raw = String(hex || '').trim();
        if (!raw) return '';
        const m = raw.match(/^#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/);
        if (!m) return '';
        return '#' + m[1].toUpperCase();
    }

    function safeJsonObject(raw){
        if (!raw) return {};
        if (typeof raw === 'object') return raw;
        try {
            const tmp = JSON.parse(String(raw));
            return (tmp && typeof tmp === 'object') ? tmp : {};
        } catch (e) {
            return {};
        }
    }

    function findBxgyPromo(promoId){
        const id = Number(promoId || 0);
        return (Array.isArray(bxgyPromos) ? bxgyPromos : []).find(p => Number(p?.id || 0) === id) || null;
    }

    function promoGiftProducts(promo){
        if (!promo) return [];
        if (Array.isArray(promo.gift_products) && promo.gift_products.length) {
            return promo.gift_products;
        }
        const ids = Array.isArray(promo.gift_product_ids) ? promo.gift_product_ids : [];
        if (ids.length) {
            return ids.map(id => ({ product_id: Number(id || 0), name: '#' + String(id || '') }));
        }
        const gid = Number(promo.gift_product_id || 0);
        if (gid > 0) {
            return [{ product_id: gid, name: '#' + String(gid) }];
        }
        return [];
    }

    function getAllowedGiftVariantIds(promo, giftPid){
        if (!promo) return [];
        const map = promo.gift_variant_ids;
        if (!map || typeof map !== 'object') return [];
        const key1 = String(Number(giftPid || 0));
        const arr = Array.isArray(map[giftPid]) ? map[giftPid]
            : (Array.isArray(map[key1]) ? map[key1] : []);
        return (Array.isArray(arr) ? arr : []).map(v => Number(v || 0)).filter(v => v > 0);
    }

    function ensureGiftVariantsLoaded(pid){
        const productId = Number(pid || 0);
        if (!productId) return $.Deferred().resolve([]).promise();
        if (Array.isArray(bxgyGiftVariantsCache[productId])) {
            return $.Deferred().resolve(bxgyGiftVariantsCache[productId]).promise();
        }
        return $.get(API, { ajax: 'product_detail', pid: productId }).then(res => {
            const variants = (res && res.ok && res.data && Array.isArray(res.data.variants)) ? res.data.variants : [];
            bxgyGiftVariantsCache[productId] = variants;
            return variants;
        }, () => {
            bxgyGiftVariantsCache[productId] = [];
            return [];
        });
    }
    // Hiển thị các sản phẩm trong giỏ hàng
    function render(items){
        const $wrap = $('#cartWrap');
        lastItems = Array.isArray(items) ? items : [];
        if (!items || !items.length){
            $wrap.html(`
                <div class="cart-empty text-center py-5">
                    <div class="mb-3">
                        <!--i class="bi bi-box2" style="font-size: 5rem; color: #cbd5e1; opacity: 0.9;"></i-->
                        <img src="${BASE_URL}/image/character.png" alt="Giỏ hàng trống" style="width:200px;opacity:0.9;">
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Chưa có sản phẩm</h5>
                    <p class="text-muted mb-4">Bạn chưa có sản phẩm nào trong giỏ hàng.</p>
                    <a href="${BASE_URL}/" class="btn text-white px-4 rounded-pill fw-bold" style="background-color: #0c4c29; border-color: #0c4c29;">Mua sắm ngay</a>
                </div>
            `);
            $('#sumSubtotal,#sumShip,#sumTotal').text('0 đ');
            $('#sumVoucherOrder,#sumVoucherShip,#sumDiscount,#sumSaved').text('0 đ');
            $('#voucherStickyOrder').html('<i class="bi bi-gift me-1 text-danger"></i> ' + $('<div>').text('Đơn hàng').html());
            $('#voucherStickyShip').html('<i class="bi bi-truck me-1 text-info"></i> ' + $('<div>').text('Vận chuyển').html());
            $('#selectedCount').text('0');
            $('#selectedCountBtn').text('0');
            $('#stickyTotal').text('0 đ');
            $('#stickySaved').text('Tiết kiệm 0 đ');
            selectedKeys.clear();
            $('#selectAll').prop('checked', false);
            toggleCartSticky();
            return;
        }
        // Đảm bảo selectedKeys chỉ chứa các key hiện có trong items mới
        const activeKeys = new Set(items.map(it => String(it.key || '')));
        Array.from(selectedKeys).forEach(key => {
            if (!activeKeys.has(key)) selectedKeys.delete(key);
        });
        // Phân nhóm sản phẩm thành thường và tặng để hiển thị khác biệt
        const normalItems = [];
        const giftItems = [];
        items.forEach(it => {
            const isGift = !!it.is_gift || (Number(it.price || 0) === 0 && Number(it.qty || 0) === 1);
            (isGift ? giftItems : normalItems).push(it);
        });

        const colorToken = '| Màu:';
        const variantLabelOf = (it) => {
            const wVal = Number(it?.shipping_weight_value || it?.weight_value || 0);
            const wUnit = it?.shipping_weight_unit || it?.weight_unit || '';
            const wLabel = formatWeight(wVal, wUnit);
            let variantName = String(it?.variant || it?.variant_name || '').trim();

            if (!variantName.trim()) return '';
            const finalVariant = variantName.trim();
            if (!finalVariant.includes(colorToken)) return finalVariant;
            return String((finalVariant.split(colorToken)[0] || '')).trim();
        };
        const nameLabelOf = (it) => String(it?.name || it?.product_name || '').trim();
        const sortByVariantName = (a, b) => {
            const va = variantLabelOf(a).toLowerCase();
            const vb = variantLabelOf(b).toLowerCase();
            if (va !== vb) {
                if (!va) return 1;
                if (!vb) return -1;
                return va.localeCompare(vb, 'vi');
            }
            const na = nameLabelOf(a).toLowerCase();
            const nb = nameLabelOf(b).toLowerCase();
            return na.localeCompare(nb, 'vi');
        };
        normalItems.sort(sortByVariantName);
        giftItems.sort(sortByVariantName);

        let html = '';

        // Nhóm sản phẩm thường
        normalItems.forEach(it => {
            const rawThumb = String(it.variant_thumb || it.variant_image_url || it.thumb || it.image_url || '').trim();
            const img = rawThumb ? toAbs(rawThumb) : (BASE_URL + '/<?= ltrim(h($site_fallback_logo), '/') ?>');
            const safeName = $('<div>').text(it.name || '').html();
            const wVal = Number(it?.shipping_weight_value || it?.weight_value || 0);
            const wUnit = it?.shipping_weight_unit || it?.weight_unit || '';
            const wLabel = formatWeight(wVal, wUnit);
            let fullVariant = String(it.variant || it.variant_name || '').trim();
            let baseVariant = fullVariant;
            let colorFromVariant = '';
            if (fullVariant && fullVariant.includes(colorToken)){
                const parts = fullVariant.split(colorToken);
                baseVariant = (parts[0] || '').trim();
                colorFromVariant = (parts[1] || '').trim();
            }
            const finalVariant = baseVariant || 'Mặc định';
            const safeVariant = $('<div>').text(finalVariant).html();

            const rawColorCode = String(it.color_code || colorFromVariant || '').trim();
            const rawColorName = String(it.color_name || it.color || '').trim();
            let colorLabel = '';
            if (rawColorCode){
                colorLabel = rawColorName ? (rawColorCode + ' - ' + rawColorName) : rawColorCode;
            } else if (rawColorName){
                colorLabel = rawColorName;
            }
            const safeColorLabel = colorLabel ? $('<div>').text(colorLabel).html() : '';
            const colorHex = normalizeColorHex(it.color_hex);
            const colorStyle = colorHex ? ` style="background:${colorHex};"` : '';
            const isCombo = !!it.is_combo;
            const comboBadge = isCombo ? '<span class="badge bg-warning text-dark ms-1"><i class="bi bi-lightning-charge-fill me-1"></i>Deal sốc</span>' : '';
            const key = String(it.key || '');
            const isSelected = selectedKeys.has(key);
            const checked = isSelected ? 'checked' : '';
            html += `
                <div class="cart-item product-card-cart ${isSelected ? 'is-selected' : ''}">
                    <div class="checkbox-container">
                        <input type="checkbox" class="form-check-input cart-select" data-key="${key}" ${checked}>
                        
                    </div>
                    <div class="cart-product-img-wrap">
                        <img class="product-img" src="${$('<div>').text(img).html()}" alt="thumb" loading="lazy" decoding="async">
                        <div class="cart-item-check-icon"><i class="bi bi-check-lg"></i></div>
                    </div>
                    <div class="product-info">
                        <div class="cart-title">${comboBadge} ${safeName}</div>
                        <div class="cart-meta-row">
                            <div class="variant-selector" data-key="${key}">
                                <span>${safeVariant}</span>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                            ${wLabel ? `<span class="cart-meta-chip">${$('<div>').text(wLabel).html()}</span>` : ''}
                        </div>
                        ${safeColorLabel ? `<div class="cart-color-text">${safeColorLabel}</div>` : ''}
                        <div class="product-bottom-row">
                            <div class="cart-price">${window.pmFormatPrice(it.price)}</div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="cart-stepper" data-key="${key}">
                                    <button type="button" class="stepper-btn stepper-minus" data-step="-1">-</button>
                                    <input type="number" min="1" class="stepper-input cart-qty" data-key="${key}" value="${it.qty}">
                                    <button type="button" class="stepper-btn stepper-plus" data-step="1">+</button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1 lh-1" data-remove="${key}" title="Xóa">
                                    <i class="bi bi-trash3 fs-5"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                    
                </div>
            `;
        });
        // Nhóm sản phẩm tặng (quà tặng / deal miễn phí)
        if (giftItems.length){
            html += '<div class="m-3 fw-semibold text-success small"><i class="bi bi-gift-fill me-1"></i> Bạn nhận được quà tặng:</div>';
            giftItems.forEach(it => {
                const rawThumb = String(it.variant_thumb || it.variant_image_url || it.thumb || it.image_url || '').trim();
                const img = rawThumb ? toAbs(rawThumb) : (BASE_URL + '/<?= ltrim(h($site_fallback_logo), '/') ?>');
                const safeName = $('<div>').text(it.name || '').html();
                const wVal = Number(it?.shipping_weight_value || it?.weight_value || 0);
                const wUnit = it?.shipping_weight_unit || it?.weight_unit || '';
                const wLabel = formatWeight(wVal, wUnit);
                let fullVariant = String(it.variant || it.variant_name || '').trim();
                let baseVariant = fullVariant;
                let colorFromVariant = '';
                if (fullVariant && fullVariant.includes(colorToken)){
                    const parts = fullVariant.split(colorToken);
                    baseVariant = (parts[0] || '').trim();
                    colorFromVariant = (parts[1] || '').trim();
                }
                const finalVariant = baseVariant || 'Mặc định';
                const safeVariant = $('<div>').text(finalVariant).html();

                const rawColorCode = String(it.color_code || colorFromVariant || '').trim();
                const rawColorName = String(it.color_name || it.color || '').trim();
                let colorLabel = '';
                if (rawColorCode){
                    colorLabel = rawColorName ? (rawColorCode + ' - ' + rawColorName) : rawColorCode;
                } else if (rawColorName){
                    colorLabel = rawColorName;
                }
                const safeColorLabel = colorLabel ? $('<div>').text(colorLabel).html() : '';
                const colorHex = normalizeColorHex(it.color_hex);
                const colorStyle = colorHex ? ` style="background:${colorHex};"` : '';
                const key = String(it.key || '');
                const isSelected = selectedKeys.has(key);
                const checked = isSelected ? 'checked' : '';
                html += `
                    <div class="cart-item product-card-cart cart-item-gift ${isSelected ? 'is-selected' : ''}">
                        <div class="checkbox-container">
                            <input type="checkbox" class="form-check-input cart-select" data-key="${key}" ${checked}>
                        </div>
                        <div class="cart-product-img-wrap">
                            <img class="product-img__" style="height: 60px; width: 60px; object-fit: cover;" src="${img}" alt="thumb" loading="lazy" decoding="async">
                            <div class="cart-item-check-icon"><i class="bi bi-check-lg"></i></div>
                        </div>
                        <div class="product-info">
                            <div class="cart-title"><span class="badge bg-success text-white me-1">Quà tặng</span> ${safeName}</div>
                            <div class="cart-meta-row">
                                <div class="variant-selector" data-key="${key}">
                                    <span>${safeVariant}</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                ${wLabel ? `<span class="cart-meta-chip">${$('<div>').text(wLabel).html()}</span>` : ''}
                            </div>
                            ${safeColorLabel ? `<div class="cart-color-text">${safeColorLabel}</div>` : ''}
                            <div class="product-bottom-row">
                                <div class="cart-price text-success">Miễn phí</div>
                                <div class="text-muted small">x${Number(it.qty || 0) || 1}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $wrap.html(html);

        const selectedCount = selectedKeys.size;
        $('#selectedCount').text(selectedCount);
        $('#selectedCountBtn').text(selectedCount);
        $('#selectAll').prop('checked', selectedCount > 0 && selectedCount === items.length);
        toggleCartSticky();
        refreshSummary();
    }
    // Hàm tiện ích để chuẩn hóa mục tiêu của voucher thành 'order' hoặc 'shipping', giúp việc so sánh và áp dụng voucher trở nên nhất quán hơn
    function normalizeTarget(raw){
        return String(raw || 'order').trim().toLowerCase() === 'shipping' ? 'shipping' : 'order';
    }
    // Hàm tiện ích để tính tổng phụ của giỏ hàng dựa trên danh sách sản phẩm hiện tại, dùng đúng giá trị đã làm tròn như người dùng thấy
    function getSubtotal(){
        return lastItems.reduce((sum, it) => sum + (window.pmNormalizePrice(it.price) * Number(it.qty || 0)), 0);
    }
    // Hàm tiện ích để xây dựng payload danh sách sản phẩm theo định dạng mà API yêu cầu, bao gồm id, product_id, variant_id, color_code, price và qty của từng sản phẩm, đồng thời lọc bỏ những sản phẩm không hợp lệ (product_id <= 0 hoặc qty <= 0)
    function buildCartItemsPayload(){
        return lastItems.map(i => ({
            id: Number(i.product_id || i.id || 0),
            product_id: Number(i.product_id || i.id || 0),
            variant_id: Number(i.variant_id || i.v_id || i.vid || 0),
            color_code: i.color_code || '',
            price: Number(i.price || 0),
            qty: Number(i.qty || 0),
            // Giữ lại cờ quà tặng / combo để đơn hàng nhận biết
            is_gift: i.is_gift ? 1 : 0,
            is_combo: i.is_combo ? 1 : 0,
        })).filter(it => it.product_id > 0 && it.qty > 0);
    }
    // Hàm tiện ích để lấy danh sách product_id hiện có trong giỏ hàng, giúp việc kiểm tra xem voucher có áp dụng cho sản phẩm nào trong giỏ hay không trở nên dễ dàng hơn
    function currentProductIds(){
        const seen = {};
        const ids = [];
        lastItems.forEach(it => {
            const pid = Number(it.product_id || it.id || 0);
            if (pid > 0 && !seen[pid]) {
                seen[pid] = true;
                ids.push(pid);
            }
        });
        return ids;
    }
    // Hàm tiện ích để tìm voucher trong danh sách vouchers dựa trên mã voucher và mục tiêu của nó, giúp việc hiển thị thông tin chi tiết của voucher đã chọn trở nên chính xác hơn
    function findVoucherByCodeTarget(code, target){
        const normalizedCode = String(code || '').trim().toUpperCase();
        const normalizedTarget = normalizeTarget(target);
        if (!normalizedCode) return null;
        return vouchers.find(v => {
            const voucherCode = String(v.code || '').trim().toUpperCase();
            const voucherTarget = normalizeTarget(v.discount_target);
            return voucherCode === normalizedCode && voucherTarget === normalizedTarget;
        }) || null;
    }
    // Hàm tiện ích để tạo chuỗi tóm tắt các voucher đã chọn, tách riêng đơn hàng và vận chuyển
    function voucherSummaryTexts(){
        const orderCode = String(selectedVoucherOrderCode || suggestedVoucherOrderCode || '').trim();
        const shipCode = String(selectedVoucherShipCode || suggestedVoucherShipCode || '').trim();

        let orderText = '<i class="bi bi-gift me-1 text-danger"></i> Đơn hàng';
        let shipText = '<i class="bi bi-truck me-1 text-info"></i> Vận chuyển';

        if (orderCode) {
            const orderVoucher = findVoucherByCodeTarget(orderCode, 'order');
            let label = '';
            if (typeof baseDiscountTextCart === 'function' && orderVoucher) {
                label = String(baseDiscountTextCart(orderVoucher) || '').trim();
            }
            if (!label) {
                label = window.pmFormatPrice(Math.max(0, Number(orderDiscount || 0)));
            }
            orderText = '<span class="text-danger"><i class="bi bi-gift"></i> - ' + label + '</span>';
        }

        if (shipCode) {
            const shipVoucher = findVoucherByCodeTarget(shipCode, 'shipping');
            let label = '';
            if (typeof baseDiscountTextCart === 'function' && shipVoucher) {
                label = String(baseDiscountTextCart(shipVoucher) || '').trim();
            }
            if (!label) {
                label = window.pmFormatPrice(Math.max(0, Number(shippingVoucherDiscount || 0)));
            }
            shipText = '<span class="text-info"><i class="bi bi-truck"></i> - ' + label + '</span>';
        }

        return { order: orderText, ship: shipText };
    }
    // Hàm tiện ích để làm mới lại phần summary của giỏ hàng, bao gồm cập nhật tổng phụ, phí ship, tổng giảm giá, tổng thanh toán và phần tóm tắt voucher đã chọn, giúp người dùng luôn nắm được thông tin chi tiết về đơn hàng của mình
    function refreshSummary(){
        const sub = getSubtotal();
        const ship = Math.max(0, Number(shippingFee || 0));
        const orderVoucher = Math.max(0, Number(orderDiscount || 0));
        const shipVoucher = Math.max(0, Number(shippingVoucherDiscount || 0));
        const totalDiscount = Math.max(0, orderVoucher + shipVoucher);
        const total = Math.max(0, sub + ship - totalDiscount);

        $('#sumSubtotal').text(window.pmFormatPrice(sub));
        $('#sumShip').text(window.pmFormatPrice(ship));
        $('#sumVoucherOrder').text('-' + window.pmFormatPrice(orderVoucher));
        $('#sumVoucherShip').text('-' + window.pmFormatPrice(shipVoucher));
        $('#sumDiscount').text(window.pmFormatPrice(totalDiscount));
        $('#sumTotal').text(window.pmFormatPrice(total));
        $('#sumSaved').text(window.pmFormatPrice(totalDiscount));
        $('#stickyTotal').text(window.pmFormatPrice(total));
        $('#stickySaved').text('Tiết kiệm ' + window.pmFormatPrice(totalDiscount));
        const texts = voucherSummaryTexts();
        $('#voucherStickyOrder').html(texts.order);
        $('#voucherStickyShip').html(texts.ship);
        // Đếm số voucher đã lưu, dùng chung logic với modal voucher (dựa trên is_saved hoặc savedVoucherCodes)
        const savedCount = vouchers.filter(row => {
            const code = String(row.code || '').trim().toUpperCase();
            if (!code) return false;
            if (row.is_saved || row.saved) return true;
            return savedVoucherCodes.includes(code);
        }).length;
        $('#vouxMeta').text(String(savedCount) + ' voucher');
    }
    // Hàm tiện ích để lưu trạng thái voucher đã chọn vào session thông qua API, giúp giữ nguyên lựa chọn của người dùng khi làm mới trang hoặc quay lại sau
    function persistSessionVoucher(code, target){
        return window.pmVoucherAPI.persistSession(code, normalizeTarget(target));
    }
    // Hàm tiện ích để kiểm tra tính hợp lệ của voucher dựa trên mã voucher, mục tiêu và các điều kiện hiện tại của giỏ hàng như tổng phụ, phí ship và sản phẩm trong giỏ, giúp đảm bảo rằng chỉ những voucher phù hợp mới được áp dụng
    function validateVoucher(code, target){
        return window.pmVoucherAPI.validate({
            target: normalizeTarget(target),
            code: String(code || '').trim(),
            subtotal: getSubtotal(),
            shipping_fee: Number(shippingFee || 0),
            product_ids: currentProductIds().join(',')
        });
    }
    // Hàm tiện ích để làm mới lại phí ship dự kiến dựa trên tổng phụ hiện tại và danh sách sản phẩm trong giỏ, đồng thời nếu revalidateVoucher là true thì sau khi nhận được kết quả từ API sẽ tự động đồng bộ lại các voucher đã chọn để đảm bảo rằng chúng vẫn hợp lệ với phí ship mới
    function refreshShippingQuote(revalidateVoucher = false){
        if (!lastItems.length) {
            shippingFee = 0;
            shippingQuote = null;
            if (revalidateVoucher) syncSelectedVoucherDiscounts();
            refreshSummary();
            return;
        }
        $.get(SHIPPING_API, {
            ajax: 'shipping_quote',
            subtotal: getSubtotal(),
            products_json: JSON.stringify(buildCartItemsPayload())
        }).done(res => {
            if (res && res.ok) {
                shippingQuote = res.shipping_quote || null;
                shippingFee = Number(res.shipping_fee || 0);
            } else {
                shippingQuote = null;
                shippingFee = 0;
            }
            if (revalidateVoucher) {
                syncSelectedVoucherDiscounts();
                return;
            }
            refreshSummary();
        }).fail(() => {
            shippingQuote = null;
            shippingFee = 0;
            if (revalidateVoucher) {
                syncSelectedVoucherDiscounts();
                return;
            }
            refreshSummary();
        });
    }
    // Hàm tiện ích để xóa trạng thái voucher đã chọn dựa trên mục tiêu, giúp người dùng có thể dễ dàng bỏ chọn voucher nếu muốn
    function clearVoucherState(target){
        if (normalizeTarget(target) === 'shipping') {
            shippingVoucherDiscount = 0;
            selectedVoucherShipCode = '';
        } else {
            orderDiscount = 0;
            selectedVoucherOrderCode = '';
        }
    }

    // Hàm tiện ích để áp dụng voucher dựa trên mã voucher, mục tiêu và các tùy chọn như silent, closeAfter và persist, giúp người dùng có thể dễ dàng áp dụng voucher vào giỏ hàng
    function applyVoucher(code, target, options = {}){
        const normalizedTarget = normalizeTarget(target);
        const normalizedCode = String(code || '').trim();
        const silent = !!options.silent;
        const closeAfter = !!options.closeAfter;
        const persist = options.persist !== false;

        if (!normalizedCode) {
            clearVoucherState(normalizedTarget);
            if (persist) persistSessionVoucher('', normalizedTarget);
            refreshSummary();
            return;
        }
        // Gọi API để validate voucher, nếu hợp lệ thì cập nhật trạng thái voucher đã chọn và làm mới summary, nếu không hợp lệ thì xóa trạng thái voucher đã chọn và thông báo lỗi
        validateVoucher(normalizedCode, normalizedTarget).done(res => {
            if (res && res.ok) {
                if (normalizedTarget === 'shipping') {
                    shippingVoucherDiscount = Number(res.discount || 0);
                    selectedVoucherShipCode = normalizedCode;
                } else {
                    orderDiscount = Number(res.discount || 0);
                    selectedVoucherOrderCode = normalizedCode;
                }
                if (persist) persistSessionVoucher(normalizedCode, normalizedTarget);
                if (!silent) notify(res.msg || ('Đã chọn mã: ' + normalizedCode), 'success');
                if (closeAfter && window.pmVoucherModal && typeof window.pmVoucherModal.close === 'function') {
                    window.pmVoucherModal.close();
                }
            } else {
                clearVoucherState(normalizedTarget);
                if (persist) persistSessionVoucher('', normalizedTarget);
                if (!silent) notify(res?.msg || 'Mã không hợp lệ', 'warning');
            }
            refreshSummary();
        }).fail(() => {
            clearVoucherState(normalizedTarget);
            if (!silent) notify('Không thể kiểm tra voucher', 'warning');
            refreshSummary();
        });
    }

    function syncSelectedVoucherDiscounts(){
        if (selectedVoucherOrderCode) {
            applyVoucher(selectedVoucherOrderCode, 'order', { silent: true, persist: false });
        }
        if (selectedVoucherShipCode) {
            applyVoucher(selectedVoucherShipCode, 'shipping', { silent: true, persist: false });
        }
        if (!selectedVoucherOrderCode && !selectedVoucherShipCode) {
            refreshSummary();
        }
    }
    // Hàm tiện ích để tải danh sách mã voucher đã lưu của người dùng từ API, giúp việc xác định xem voucher nào là voucher đã lưu trở nên chính xác hơn
    function loadSavedCodes(){
        return window.pmVoucherAPI.loadSavedCodes()
            .done(codes => {
                savedVoucherCodes = codes || [];
            })
            .fail(() => {
                savedVoucherCodes = [];
            });
    }
    // Hàm tiện ích để tải danh sách sản phẩm trong giỏ hàng từ API
    function load(){
        $.get(API, { ajax: 'cart_get' }, res => {
            if (!res || !res.ok){
                notify(res?.msg || 'Không tải được giỏ hàng', 'error');
                return;
            }
            syncBxgyFromResponse(res);
            const orderList = Array.isArray(res.vouchers_order)
                ? res.vouchers_order.map(v => ({ ...v, discount_target: 'order' }))
                : [];
            const shipList = Array.isArray(res.vouchers_shipping)
                ? res.vouchers_shipping.map(v => ({ ...v, discount_target: 'shipping' }))
                : [];
            const legacyList = Array.isArray(res.saved_vouchers)
                ? res.saved_vouchers.map(v => ({ ...v, discount_target: normalizeTarget(v.discount_target) }))
                : [];
            const merged = {};
            [...orderList, ...shipList, ...legacyList].forEach(v => {
                const code = String(v.code || '').trim().toUpperCase();
                const target = normalizeTarget(v.discount_target);
                if (!code) return;
                merged[code + '__' + target] = { ...v, discount_target: target };
            });
            vouchers = Object.values(merged);
            suggestedVoucherOrderCode = String(res?.suggested_voucher_order?.code || res?.suggested_voucher?.code || '').trim();
            suggestedVoucherShipCode = String(res?.suggested_voucher_shipping?.code || '').trim();
            const sessionVoucherOrderCode = String(res?.selected_voucher_code_order || res?.selected_voucher_code || '').trim();
            const sessionVoucherShipCode = String(res?.selected_voucher_code_shipping || '').trim();

            const isSavedOrder = sessionVoucherOrderCode && savedVoucherCodes.includes(sessionVoucherOrderCode.toUpperCase());
            const isSavedShip = sessionVoucherShipCode && savedVoucherCodes.includes(sessionVoucherShipCode.toUpperCase());
            const isSavedSuggestedOrder = suggestedVoucherOrderCode && savedVoucherCodes.includes(suggestedVoucherOrderCode.toUpperCase());
            const isSavedSuggestedShip = suggestedVoucherShipCode && savedVoucherCodes.includes(suggestedVoucherShipCode.toUpperCase());

            if (sessionVoucherOrderCode && isSavedOrder) selectedVoucherOrderCode = sessionVoucherOrderCode;
            else if (!selectedVoucherOrderCode && suggestedVoucherOrderCode && isSavedSuggestedOrder) selectedVoucherOrderCode = suggestedVoucherOrderCode;
            if (sessionVoucherShipCode && isSavedShip) selectedVoucherShipCode = sessionVoucherShipCode;
            else if (!selectedVoucherShipCode && suggestedVoucherShipCode && isSavedSuggestedShip) selectedVoucherShipCode = suggestedVoucherShipCode;

            render(res.data || []);
            refreshShippingQuote(true);
        }).fail(() => notify('Lỗi kết nối server', 'error'));
    }

    function saveBxgyChoice(key, promoId, giftPid, giftVariantId){
        return $.post(API, {
            action: 'cart_set_bxgy_choice',
            key: String(key || ''),
            promo_id: Number(promoId || 0),
            gift_pid: Number(giftPid || 0),
            gift_variant_id: Number(giftVariantId || 0)
        });
    }

    // Mở dropdown chọn quà BXGY khi click pill bxgy-selector
    $('#cartWrap').on('click', '.bxgy-selector', function(e){
        e.stopPropagation();
        const $pill = $(this);
        const key = String($pill.data('key') || '');
        const promoId = Number($pill.data('promo_id') || 0);
        const giftPid = Number($pill.data('gift_pid') || 0);
        const giftVariantId = Number($pill.data('gift_vid') || 0);
        if (!key || !promoId) return;

        const promo = findBxgyPromo(promoId);
        if (!promo) return;

        bxgyDropdownState = { key, promoId };

        if (!$bxgyDropdown) $bxgyDropdown = $('#bxgyGiftDropdown');
        if (!$bxgyDropdown.length) return;
        const $body = $('#bxgyGiftDropdownBody');
        $body.html('<div class="py-2 text-center text-muted small">Đang tải lựa chọn quà...</div>');

        $('.bxgy-selector.is-open').removeClass('is-open');
        $pill.addClass('is-open');

        const offset = $pill.offset();
        const height = $pill.outerHeight() || 0;
        const width = $pill.outerWidth() || 0;
        const scrollTop = $(window).scrollTop() || 0;
        let left = offset.left;
        const top = offset.top + height + 6 - scrollTop;

        $bxgyDropdown.removeClass('d-none');
        $bxgyDropdown.css({ top: top + 'px', left: left + 'px', minWidth: width + 'px' });

        const dropdownWidth = $bxgyDropdown.outerWidth() || 0;
        const viewportWidth = $(window).width() || 0;
        if (left + dropdownWidth + 8 > viewportWidth){
            left = Math.max(8, viewportWidth - dropdownWidth - 8);
            $bxgyDropdown.css({ left: left + 'px' });
        }

        const gifts = promoGiftProducts(promo);
        if (!gifts.length) {
            $body.html('<div class="px-3 py-2 text-muted small">Không có quà tặng khả dụng.</div>');
            return;
        }

        $body.html('<div class="px-3 pt-2 pb-1 text-muted small">Chọn quà tặng và phân loại:</div>');

        gifts.forEach(g => {
            const gid = Number(g.product_id || g.id || 0);
            if (!gid) return;
            const giftName = String(g.name || g.product_name || ('Quà #' + gid)).trim();
            const baseThumb = String(g.thumb || g.image_url || '').trim();
            const allowedIds = getAllowedGiftVariantIds(promo, gid);

            ensureGiftVariantsLoaded(gid).then(variants => {
                const list = Array.isArray(variants) ? variants : [];
                let filtered = list;
                if (allowedIds.length) {
                    const allowedSet = new Set(allowedIds.map(x => Number(x || 0)));
                    filtered = list.filter(v => allowedSet.has(Number(v?.id || 0)));
                }

                if (!filtered.length) {
                    const isCurrent = (gid === giftPid) && (!giftVariantId || giftVariantId === 0);
                    const safeName = $('<div>').text(giftName).html();
                    const safeThumb = baseThumb ? $('<div>').text(toAbs(baseThumb)).html() : '';
                    const badge = isCurrent ? ' <span class="badge bg-success ms-1">Đang chọn</span>' : '';
                    const thumbHtml = safeThumb ? `<img class="me-2" src="${safeThumb}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;background:#f8fafc;" alt="Quà" loading="lazy" decoding="async">` : '<span class="me-2">??</span>';
                    $body.append(`
                        <button type="button" class="cart-variant-option bxgy-option" data-gift_pid="${gid}" data-gift_vid="0">
                            <span class="d-flex align-items-center flex-grow-1">
                                ${thumbHtml}
                                <span>${badge} ${safeName}</span>
                                <span class="fw-semibold ms-2 text-success">Miễn phí</span>
                            </span>
                        </button>
                    `);
                    return;
                }

                filtered.forEach(v => {
                    const vid = Number(v.id || 0);
                    let wVal = parseFloat(v.shipping_weight_value || v.weight_value || 0);
                    let wUnit = String(v.shipping_weight_unit || v.weight_unit || 'kg').toLowerCase().trim();
                    const unitMap = { 'kg': 'Kg', 'l': 'L', 'gr': 'g', 'gram': 'g', 'ml': 'ml' };
                    let wLabel = '';
                    if (wVal > 0) {
                        if (wVal >= 1000) {
                            if (wUnit === 'gr' || wUnit === 'gram' || wUnit === 'g') { wVal /= 1000; wUnit = 'kg'; }
                            else if (wUnit === 'ml') { wVal /= 1000; wUnit = 'l'; }
                        }
                        const displayUnit = unitMap[wUnit] || wUnit;
                        wLabel = parseFloat(wVal.toFixed(3)) + displayUnit;
                    }
                    const variantName = (v.variant_name || '').trim() || 'Mặc định';
                    const price = Number(v.price || 0);
                    const isCurrent = (gid === giftPid) && (giftVariantId > 0 ? (vid === giftVariantId) : false);
                    const safeName = $('<div>').text(giftName).html();
                    const safeVariant = $('<div>').text(variantName).html();
                    const variantThumb = String(v.image_url || baseThumb || '').trim();
                    const safeThumb = variantThumb ? $('<div>').text(toAbs(variantThumb)).html() : '';
                    const badge = isCurrent ? ' <span class="badge bg-success ms-1">Đang chọn</span>' : '';
                    const thumbHtml = safeThumb ? `<img class="me-2" src="${safeThumb}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;background:#f8fafc;" alt="Quà" loading="lazy" decoding="async">` : '<span class="me-2">??</span>';
                    $body.append(`
                        <button type="button" class="cart-variant-option bxgy-option" data-gift_pid="${gid}" data-gift_vid="${vid}">
                            <span class="d-flex align-items-center flex-grow-1 text-start">
                                ${thumbHtml}
                                <span>
                                    <div class="small fw-semibold text-dark">${badge} ${safeName}</div>
                                    <div class="small text-muted">${safeVariant}</div>
                                    <span class="fw-semibold text-success">${window.pmFormatPrice(price)}</span>
                                </span>
                            </span>
                        </button>
                    `);
                });
            });
        });
    });

    // Chọn một dòng quà trong dropdown BXGY
    $('#bxgyGiftDropdownBody').on('click', '.bxgy-option', function(e){
        e.stopPropagation();
        if (!bxgyDropdownState) return;
        const gid = Number($(this).data('gift_pid') || 0);
        const vid = Number($(this).data('gift_vid') || 0);
        if (!gid) return;
        const { key, promoId } = bxgyDropdownState;
        saveBxgyChoice(key, promoId, gid, vid).done(res => {
            if (!res || !res.ok) {
                notify(res?.msg || 'Không cập nhật được quà tặng', 'error');
                return;
            }
            hideBxgyDropdown();
            load();
        }).fail(() => notify('Không cập nhật được quà tặng', 'error'));
    });

    $('#cartWrap').on('change', '.cart-select', function(){
        const key = String($(this).data('key') || '');
        if (!key) return;
        const isChecked = this.checked;
        if (isChecked) selectedKeys.add(key);
        else selectedKeys.delete(key);
        
        $(this).closest('.cart-item').toggleClass('is-selected', isChecked);
        
        $('#selectedCount').text(selectedKeys.size);
        $('#selectedCountBtn').text(selectedKeys.size);
        $('#selectAll').prop('checked', selectedKeys.size > 0 && selectedKeys.size === lastItems.length);
        toggleCartSticky();
    });

    // Toggle chọn sản phẩm khi click vào toàn bộ item
    $('#cartWrap').on('click', '.cart-item', function(e){
        // Nếu click vào các vùng control đặc biệt thì không toggle chọn
        if ($(e.target).closest('.stepper-btn, .cart-qty, .variant-selector, [data-remove], a, button').length) return;
        
        const $cb = $(this).find('.cart-select');
        if (!$cb.length) return;
        
        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    });

    $('#selectAll').on('change', function(){
        if (!lastItems.length) return;
        selectedKeys.clear();
        if (this.checked) lastItems.forEach(it => selectedKeys.add(String(it.key || '')));
        render(lastItems);
    });

    // Mở popup chọn lại phân loại khi click vào pill phân loại trong giỏ
    $('#cartWrap').on('click', '.variant-selector', function(e){
        e.stopPropagation();
        const key = String($(this).data('key') || '');
        if (!key) return;
        const item = lastItems.find(it => String(it.key || '') === key);
        if (!item) return;
        const pid = Number(item.product_id || item.id || 0);
        if (!pid) return;

        const isGiftItem = !!item.is_gift || (Number(item.price || 0) === 0 && Number(item.qty || 0) === 1);
        const bxgyPromoId = Number(item.bxgy_promo_id || 0);

        cartVariantState = {
            key,
            pid,
            qty: Number(item.qty || 1),
            color_code: String(item.color_code || ''),
            is_gift: isGiftItem ? 1 : 0,
            bxgy_promo_id: bxgyPromoId,
            current_vid: Number(item.variant_id || item.v_id || item.vid || 0),
            thumb: String(item.variant_thumb || item.variant_image_url || item.thumb || item.image_url || '')
        };

        const $picker = $('#cartVariantPicker');
        const $body = $('#cartVariantPickerBody');
        $body.html(`
            <div class="py-5 text-center">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted small">Đang tải phân loại...</div>
            </div>
        `);

        $('.variant-selector.is-open').removeClass('is-open');
        $(this).addClass('is-open');

        $picker.removeClass('d-none');
        setTimeout(() => $picker.addClass('is-visible'), 10);
        document.body.style.overflow = 'hidden';
        // Ẩn nút giỏ nổi để không che variant cuối trong picker
        document.getElementById('floatingCart')?.classList.add('floating-cart-hidden');

        $.get(API, { ajax: 'product_detail', pid }, res => {
            if (!res || !res.ok || !res.data) {
                $body.html('<div class="px-3 py-5 text-center text-danger small"><i class="bi bi-exclamation-circle fs-2 mb-2 d-block"></i> Không tải được thông tin.</div>');
                return;
            }
            const variants = Array.isArray(res.data.variants) ? res.data.variants : [];
            if (!variants.length) {
                $body.html('<div class="px-3 py-5 text-center text-muted small"><i class="bi bi-info-circle fs-2 mb-2 d-block"></i> Không có phân loại khác.</div>');
                return;
            }

            let list = variants;
            if (cartVariantState && cartVariantState.is_gift && cartVariantState.bxgy_promo_id > 0) {
                const promo = findBxgyPromo(cartVariantState.bxgy_promo_id);
                const allowedIds = promo ? getAllowedGiftVariantIds(promo, pid) : [];
                if (allowedIds.length) {
                    const allowedSet = new Set(allowedIds.map(x => Number(x || 0)));
                    list = variants.filter(v => allowedSet.has(Number(v?.id || 0)));
                }
            }

            if (!list.length) {
                $body.html('<div class="px-3 py-5 text-center text-muted small">Không có phân loại quà tặng phù hợp.</div>');
                return;
            }

            let html = '';
            list.forEach(v => {
                const vid = Number(v.id || 0);
                let wVal = parseFloat(v.shipping_weight_value || v.weight_value || 0);
                let wUnit = String(v.shipping_weight_unit || v.weight_unit || 'kg').toLowerCase().trim();
                const unitMap = { 'kg': 'Kg', 'l': 'L', 'gr': 'g', 'gram': 'g', 'ml': 'ml' };
                let wLabel = '';
                if (wVal > 0) {
                    if (wVal >= 1000) {
                        if (wUnit === 'gr' || wUnit === 'gram' || wUnit === 'g') { wVal /= 1000; wUnit = 'kg'; }
                        else if (wUnit === 'ml') { wVal /= 1000; wUnit = 'l'; }
                    }
                    const displayUnit = unitMap[wUnit] || wUnit;
                    wLabel = parseFloat(wVal.toFixed(3)) + displayUnit;
                }
                const variantName = (v.variant_name || '').trim() || 'Mặc định';
                const price = Number(v.price || 0);
                const isSelected = vid === cartVariantState.current_vid;
                const vImg = v.image_url ? toAbs(v.image_url) : (cartVariantState.thumb ? toAbs(cartVariantState.thumb) : (BASE_URL + '/<?= ltrim(h($site_fallback_logo), '/') ?>'));
                
                html += `
                    <button type="button" class="picker-variant-item ${isSelected ? 'is-selected' : ''}" data-vid="${vid}">
                        <img src="${$('<div>').text(vImg).html()}" class="picker-variant-img" alt="thumb">
                        <div class="picker-variant-info">
                            <div class="picker-variant-name">${$('<div>').text(variantName).html()}</div>
                            ${wLabel ? `<div class="picker-variant-weight small text-secondary mb-1">${$('<div>').text(wLabel).html()}</div>` : ''}
                            <div class="picker-variant-price">${cartVariantState.is_gift
                                ? (price > 0
                                    ? `<span class="picker-variant-price-old">${window.pmFormatPrice(price)}</span> <span class="text-success fw-semibold">Miễn phí</span>`
                                    : '<span class="text-success fw-semibold">Miễn phí</span>')
                                : window.pmFormatPrice(price)}</div>
                        </div>
                        <div class="picker-variant-check"><i class="bi bi-check-lg"></i></div>
                    </button>
                `;
            });
            $body.html(html);
        }).fail(() => {
            $body.html('<div class="px-3 py-5 text-center text-danger small">Lỗi kết nối máy chủ.</div>');
        });
    });

    let isUpdatingQty = false;
    $('#cartWrap').on('change', '.cart-qty', function(){
        if (isUpdatingQty) return;
        const $input = $(this);
        const key = String($input.data('key') || '');
        if (!key) return;

        const qty = Math.max(1, parseInt($input.val(), 10) || 1);

        isUpdatingQty = true;
        $input.prop('disabled', true);

        $.post(API, { action: 'cart_update_qty', key, qty }, res => {
            if (res && res.ok) {
                if (res.msg) {
                    notify(String(res.msg), 'info');
                }
                syncBxgyFromResponse(res);
                render(res.data || []);
                refreshShippingQuote(true);
            } else {
                notify(res?.msg || 'Không thể cập nhật số lượng', 'error');
                render(lastItems || []);
            }
        }).fail(xhr => {
            const msg = xhr.responseJSON?.msg || 'Không thể cập nhật số lượng. Vui lòng thử lại.';
            notify(msg, 'error');
            render(lastItems || []);
        }).always(() => {
            isUpdatingQty = false;
        });
    });

    $('#cartWrap').on('keydown', '.cart-qty', function(e){
        if (e.key === 'Enter') {
            $(this).trigger('change');
            $(this).trigger('blur');
        }
    });

    // Chọn một phân loại mới trong picker
    $('#cartVariantPickerBody').on('click', '.picker-variant-item', function(e){
        e.stopPropagation();
        const vid = parseInt($(this).data('vid'), 10) || 0;
        if (!cartVariantState || !vid) return;
        
        // Nếu chọn đúng cái đang chọn thì đóng picker luôn
        if (vid === cartVariantState.current_vid) {
            hideVariantDropdown();
            return;
        }

        const isGift = !!cartVariantState.is_gift;
        const payload = isGift ? {
            action: 'cart_change_gift_variant',
            key: cartVariantState.key,
            variant_id: vid
        } : {
            action: 'cart_change_variant',
            key: cartVariantState.key,
            pid: cartVariantState.pid,
            variant_id: vid,
            qty: cartVariantState.qty,
            color_code: cartVariantState.color_code
        };

        $(this).addClass('is-loading').prop('disabled', true);

        $.post(API, payload, res => {
            if (!res || !res.ok) {
                notify(res?.msg || 'Không đổi được phân loại sản phẩm', 'error');
                $(this).removeClass('is-loading').prop('disabled', false);
                return;
            }
            syncBxgyFromResponse(res);
            hideVariantDropdown();
            notify(isGift ? 'Đã cập nhật phân loại quà tặng.' : 'Đã cập nhật phân loại sản phẩm trong giỏ.', 'success');
            render(res.data || []);
            refreshShippingQuote(true);
        }).fail(() => {
            notify('Không đổi được phân loại sản phẩm', 'error');
            $(this).removeClass('is-loading').prop('disabled', false);
        });
    });

    $('.picker-close, .variant-picker-overlay').on('click', function(e){
        if (e.target === this || $(this).hasClass('picker-close')) {
            hideVariantDropdown();
        }
    });

    // Nút tăng/giảm số lượng dạng stepper
    $('#cartWrap').on('click', '.stepper-btn', function(e){
        e.stopPropagation();
        e.preventDefault();
        if (isUpdatingQty) return;
        const $btn = $(this);
        const step = parseInt($btn.data('step'), 10) || 0;
        const $wrap = $btn.closest('.cart-stepper');
        const $input = $wrap.find('.cart-qty');
        if (!$input.length) return;
        let qty = parseInt($input.val(), 10) || 1;
        qty += step;
        if (qty < 1) qty = 1;
        $input.val(qty).trigger('change');
    });

    $('#cartWrap').on('click', '[data-remove]', function(e){
        e.stopPropagation();
        e.preventDefault();
        const key = String($(this).data('remove') || '');
        if (!key) return;

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.post(API, { action: 'cart_remove', key }, res => {
            if (res && res.ok) {
                notify('Đã xóa sản phẩm khỏi giỏ hàng', 'success');
                syncBxgyFromResponse(res);
                render(res.data || []);
                refreshShippingQuote(true);
            } else {
                notify(res?.msg || 'Không thể xóa sản phẩm', 'error');
                $btn.prop('disabled', false);
            }
        }).fail(xhr => {
            const msg = xhr.responseJSON?.msg || 'Không thể xóa sản phẩm. Vui lòng thử lại.';
            notify(msg, 'error');
            $btn.prop('disabled', false);
        });
    });

    $('#btnClear').click(function(){
        $.post(API, { action: 'cart_clear' }, res => {
            if (res && res.ok){
                notify('Đã xóa giỏ hàng', 'success');
                orderDiscount = 0;
                shippingVoucherDiscount = 0;
                selectedVoucherOrderCode = '';
                selectedVoucherShipCode = '';
                persistSessionVoucher('', 'order');
                persistSessionVoucher('', 'shipping');
                syncBxgyFromResponse(res);
                render([]);
                refreshShippingQuote(true);
            }
        });
    });

    $('#btnRemoveSelected').click(function(){
        if (!selectedKeys.size){
            notify('Chưa chọn sản phẩm nào', 'info');
            return;
        }
        $.post(API, { action: 'cart_remove_bulk', keys: Array.from(selectedKeys) }, res => {
            if (res && res.ok){
                notify('Đã xoá sản phẩm đã chọn', 'success');
                selectedKeys.clear();
                syncBxgyFromResponse(res);
                render(res.data || []);
                refreshShippingQuote(true);
            }
        });
    });

    // Bỏ toàn bộ voucher đang áp dụng (đơn hàng + vận chuyển)
    $('#btnClearVoucher').click(function(){
        orderDiscount = 0;
        shippingVoucherDiscount = 0;
        selectedVoucherOrderCode = '';
        selectedVoucherShipCode = '';
        persistSessionVoucher('', 'order');
        persistSessionVoucher('', 'shipping');
        refreshSummary();
        notify('Đã bỏ tất cả voucher đang áp dụng', 'success');
    });

    $('#btnBuySelected').click(function(){
        if (!selectedKeys.size){
            notify('Chưa chọn sản phẩm nào', 'info');
            return;
        }
        $.post(API, { action: 'cart_set_selected', keys: Array.from(selectedKeys) }, res => {
            if (!res || !res.ok){
                notify(res?.msg || 'Không thể tạo đơn hàng', 'error');
                return;
            }
            const picked = Array.isArray(res.data) ? res.data : [];
            if (!picked.length){
                notify(res?.msg || 'Không có sản phẩm hợp lệ để thanh toán', 'warning');
                return;
            }
            if (res.msg) {
                try {
                    sessionStorage.setItem('checkout_flash_msg', String(res.msg));
                } catch (e) {}
            }
            window.location.href = BASE_URL + '/checkout';
        });
    });

    // Đồng bộ trạng thái hover giữa voucherSticky và cartSticky
    function setStickyHover(on){
        stickyHover = !!on;
        if (stickyHover){
            if ($cartSticky.length){
                $cartSticky.removeClass('is-hidden');
                $cartBody.addClass('has-cart-sticky');
                document.body.classList.add('has-cart-sticky');
            }
        } else {
            toggleCartSticky();
        }
    }

    $('#voucherSticky, #cartSticky').on('mouseenter', function(){
        if (stickyHoverTimer){
            clearTimeout(stickyHoverTimer);
            stickyHoverTimer = null;
        }
        setStickyHover(true);
    }).on('mouseleave', function(e){
        const $rel = $(e.relatedTarget);
        if ($rel && $rel.closest('#voucherSticky, #cartSticky').length){
            return;
        }
        stickyHoverTimer = setTimeout(() => {
            setStickyHover(false);
        }, 120);
    });

    // Đóng dropdown khi click ra ngoài hoặc cuộn/resize
    $(document).on('click', function(){
        hideVariantDropdown();
        hideBxgyDropdown();
    });
    $('#cartVariantDropdown').on('click', function(e){
        e.stopPropagation();
    });
    $('#bxgyGiftDropdown').on('click', function(e){
        e.stopPropagation();
    });
    $(window).on('scroll resize', function(){
        hideVariantDropdown();
        hideBxgyDropdown();
    });
    // Hàm tiện ích để mở modal chọn voucher, truyền vào tab mặc định là 'order' hoặc 'shipping', đồng thời cung cấp các callback để xử lý khi người dùng áp dụng voucher hoặc lưu voucher mới, giúp việc tích hợp modal voucher trở nên dễ dàng và linh hoạt hơn
    function openSharedVoucherModal(initialTab){
        if (!window.pmVoucherModal || typeof window.pmVoucherModal.open !== 'function'){
            notify('Không mở được Kho Voucher lúc này', 'warning');
            return;
        }
        const opts = {
            vouchers: Array.isArray(vouchers) ? vouchers : [],
            savedVoucherCodes: Array.isArray(savedVoucherCodes) ? savedVoucherCodes : [],
            initialTab: initialTab === 'shipping' ? 'shipping' : 'order',
            selectedOrderCode: selectedVoucherOrderCode,
            selectedShipCode: selectedVoucherShipCode,
            onApply: ({ code, target }) => {
                applyVoucher(code, target, { closeAfter: true });
            },
            onSaved: (code) => {
                $.when(loadSavedCodes(), load()).done(() => {
                    refreshSummary();
                });
            }
        };
        window.pmVoucherModal.open(opts);
    }

    $('#voucherOpen').click(function(){
        openSharedVoucherModal('order');
    });
    $('#voucherSticky').on('click', function(e){
        // Chỉ mở kho voucher khi click vào vùng voucher, bỏ qua nút gạt Xu / các control khác
        if ($(e.target).closest('button,a,input,.vs-switch').length) return;
        openSharedVoucherModal(voucherTab);
    });

    $(function() {
        loadSavedCodes().always(() => {
            load();
            toggleCartSticky();
        });
    });
})();
</script>