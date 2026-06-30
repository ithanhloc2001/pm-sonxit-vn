<?php
// Hỗ trợ URL SEO: /view-notification/<slug>-<id>
// Lấy id từ slug (ưu tiên), fallback ?id=
$_nfSlug = trim((string)($_GET['slug'] ?? ''));
$_nfIdFromUrl = 0;
if ($_nfSlug !== '' && function_exists('nf_extract_id_from_slug')) {
    $_nfIdFromUrl = nf_extract_id_from_slug($_nfSlug);
}
if ($_nfIdFromUrl <= 0) {
    $_nfIdFromUrl = (int)($_GET['id'] ?? 0);
}

// JSON-LD NewsArticle + Breadcrumb (dùng helper chung)
$_nfSeo = $GLOBALS['_nfSeoData'] ?? null;
if (is_array($_nfSeo) && (int)($_nfSeo['id'] ?? 0) > 0 && file_exists(__DIR__ . '/../../core/seo_jsonld.php')):
    require_once __DIR__ . '/../../core/seo_jsonld.php';
    $_nfLogo = isset($_SiteLogo) ? (string)$_SiteLogo : '';
    if ($_nfLogo === '' && !empty($site_logo)) {
        $_nfLogo = seo_abs_url((string)$site_logo, (string)($baseUrl ?? ''));
    }
    $_nfPublisher = isset($_SiteTitle) ? (string)$_SiteTitle : (isset($site_title) ? (string)$site_title : 'Paint&More');
    echo seo_jsonld_article([
        'type'           => 'NewsArticle',
        'headline'       => $_nfSeo['title'] ?? '',
        'description'    => $_nfSeo['desc'] ?? '',
        'image'          => $_nfSeo['image'] ? [$_nfSeo['image']] : [],
        'datePublished'  => $_nfSeo['published'] ?? '',
        'dateModified'   => $_nfSeo['modified'] ?? ($_nfSeo['published'] ?? ''),
        'articleSection' => $_nfSeo['section'] ?? 'Thông báo',
        'url'            => $_nfSeo['url'] ?? '',
        'inLanguage'     => 'vi',
        'publisher'      => [
            'name' => $_nfPublisher,
            'logo' => $_nfLogo,
        ],
    ]);
    $_nfBcBase = isset($_SiteUrl) ? rtrim((string)$_SiteUrl, '/') : rtrim((string)($baseUrl ?? ''), '/');
    echo seo_jsonld_breadcrumb([
        ['name' => 'Trang chủ', 'url' => $_nfBcBase . '/'],
        ['name' => 'Thông báo', 'url' => $_nfBcBase . '/notifications'],
        ['name' => (string)($_nfSeo['title'] ?? 'Chi tiết'), 'url' => (string)($_nfSeo['url'] ?? '')],
    ]);
endif; ?>

<div class="container">
    <nav class="vn-breadcrumb" aria-label="breadcrumb">
        <a href="<?= h($baseUrl ?? '') ?>">Trang chủ</a>
        <span class="sep">›</span>
        <a id="js-breadcrumb-back" href="#">Thông báo</a>
        <span class="sep">›</span>
        <span class="current" id="js-breadcrumb-current">...</span>
    </nav>
</div>

<div class="container">
    <div id="notice-detail-loading" class="py-5 text-center">
        <div class="spinner-grow text-primary" role="status" style="width:2.5rem;height:2.5rem;"></div>
        <p class="mt-3 text-secondary small">Đang tải nội dung...</p>
    </div>

    <div id="notice-detail-content" class="opacity-0 transition-all">
        <!-- Hero banner -->
        <div class="vn-hero" id="js-notice-hero">
            <div class="vn-hero-overlay"></div>
            <div class="vn-hero-body">
                <div class="vn-hero-badge" id="js-notice-type">...</div>
                <h1 class="vn-hero-title" id="js-notice-title">...</h1>
                <div class="vn-hero-meta">
                    <span><i class="bi bi-clock"></i> <span id="js-notice-time">...</span></span>
                    <span><i class="bi bi-broadcast"></i> Hệ thống</span>
                </div>
            </div>
        </div>

        <!-- Article body -->
        <article class="vn-article">
            <p class="vn-subtitle" id="js-notice-subtitle" style="display:none;"></p>
            <div class="vn-content" id="js-notice-body"></div>
        </article>
    </div>
</div>

<section id="js-related-section" class="vn-related vn-related-spotlight" style="display:none;">
    <div class="container">
        <div class="vn-spotlight-head">
            <div class="vn-spotlight-eyebrow"><i class="bi bi-stars"></i> ĐỀ XUẤT TỪ BÀI VIẾT</div>
            <h2 class="vn-spotlight-title" id="js-related-title">Sản phẩm liên quan</h2>
            <p class="vn-spotlight-sub">Những sản phẩm được nhắc đến trong bài viết — chọn ngay để khám phá ưu đãi.</p>
            <div class="vn-related-nav" id="vn-related-nav" style="display:none;">
                <button id="relatedPrev" aria-label="Trước"><i class="bi bi-chevron-left"></i></button>
                <button id="relatedNext" aria-label="Tiếp"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
        <div class="vn-spotlight-grid" id="js-related-content"></div>
    </div>
</section>

<script>
(function() {
    // URL gốc của site — dùng để tạo link TUYỆT ĐỐI. Trang này có thể đang ở
    // /view-notification/<slug>-<id>, nên link tương đối (account?..., view-product?...)
    // sẽ bị resolve thành /view-notification/account?... → sai. Luôn prefix BASE_URL.
    const BASE_URL = ('<?= h($baseUrl ?? '') ?>' || window.location.origin).replace(/\/+$/, '');
    const params = new URLSearchParams(window.location.search);
    // Ưu tiên id parse từ slug (do PHP truyền xuống), fallback ?id=
    const noticeId = '<?= (int)$_nfIdFromUrl ?>' !== '0' ? '<?= (int)$_nfIdFromUrl ?>' : params.get('id');
    const loadingEl = document.getElementById('notice-detail-loading');
    const containerEl = document.getElementById('notice-detail-content');

    // Mặc định cho link "Thông báo" trỏ về trang công khai /notifications
    // (kể cả khi fetch lỗi / chưa render) để khách vãng lai không bị đẩy
    // sang trang tài khoản yêu cầu đăng nhập.
    const backEl = document.getElementById('js-breadcrumb-back');
    if (backEl) backEl.href = BASE_URL + '/notifications';

    if (!noticeId) { showError('Thiếu mã thông báo.'); return; }

    fetch('<?= h($baseUrl ?? '') ?>/core/ajax/view-notification.php?id=' + noticeId)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { showError(res.msg || 'Không thể tải thông báo.'); return; }
            renderNotice(res.data);
        })
        .catch(() => showError('Lỗi kết nối. Vui lòng thử lại.'));

    function showError(msg) {
        loadingEl.innerHTML = `<div class="py-5 text-center text-secondary small">${msg}</div>`;
    }

    function renderNotice(data) {
        // Breadcrumb
        document.getElementById('js-breadcrumb-current').textContent = data.title || 'Thông báo';
        document.getElementById('js-breadcrumb-back').href = BASE_URL + '/notifications';

        // Badge + title + meta (inside hero overlay)
        document.getElementById('js-notice-type').textContent = data.typeBadgeLabel || 'Thông báo';
        document.getElementById('js-notice-title').textContent = data.title || 'Thông báo';
        document.getElementById('js-notice-time').textContent = data.createdAt || '';

        // Hero: inject image as background if available
        const heroEl = document.getElementById('js-notice-hero');
        const heroBanner = data.mainBanner || (data.banners && data.banners[0]) || '';
        if (heroBanner) {
            const img = document.createElement('img');
            img.src = heroBanner;
            img.alt = 'banner';
            img.loading = 'lazy';
            img.className = 'vn-hero-img';
            if (data.isProductTemplate && data.bannerProductIds && data.bannerProductIds[0]) {
                const a = document.createElement('a');
                a.href = BASE_URL + '/view-product?pid=' + data.bannerProductIds[0];
                a.appendChild(img);
                heroEl.insertBefore(a, heroEl.firstChild);
            } else {
                heroEl.insertBefore(img, heroEl.firstChild);
            }
        } else {
            const iconDiv = document.createElement('div');
            iconDiv.className = 'vn-hero-icon';
            iconDiv.innerHTML = `<i class="${data.thumbIcon || 'bi bi-megaphone-fill'}"></i>`;
            heroEl.insertBefore(iconDiv, heroEl.firstChild);
        }

        // Subtitle
        if (data.subtitle) {
            const sub = document.getElementById('js-notice-subtitle');
            sub.textContent = data.subtitle;
            sub.style.display = 'block';
        }

        // Body
        document.getElementById('js-notice-body').innerHTML = data.content || 'Không có nội dung.';

        // Related
        const relatedPanel = document.getElementById('js-related-section');
        const relatedContent = document.getElementById('js-related-content');
        const relatedTitle = document.getElementById('js-related-title');

        // Helpers
        const escAttr = (s) => String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const escText = (s) => String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const PLACEHOLDER = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 80 80'><rect width='80' height='80' fill='%23f1f5f9'/><path d='M20 56l14-18 10 12 6-8 10 14H20z' fill='%23cbd5e1'/><circle cx='28' cy='30' r='5' fill='%23cbd5e1'/></svg>";
        const safeImg = (u) => {
            const s = String(u || '').trim();
            if (!s) return PLACEHOLDER;
            // Ưu tiên media domain (toMediaUrl từ head.php).
            if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(s) || PLACEHOLDER;
            if (/^(https?:)?\/\//i.test(s) || /^data:/i.test(s)) return s;
            return BASE_URL + (s.charAt(0) === '/' ? s : '/' + s.replace(/^\/+/, ''));
        };

        const navWrap = document.getElementById('vn-related-nav');

        if (data.attachedProducts && data.attachedProducts.length > 0) {
            relatedPanel.style.display = 'block';
            relatedTitle.textContent = 'Sản phẩm nổi bật trong bài';
            const items = data.attachedProducts;
            const count = items.length;
            relatedContent.classList.toggle('is-single', count === 1);
            relatedContent.classList.toggle('is-double', count === 2);
            relatedContent.classList.toggle('is-triple', count >= 3);
            relatedContent.innerHTML = items.map((p, idx) => {
                const price = Number(p.price) > 0
                    ? new Intl.NumberFormat('vi-VN').format(p.price) + ' đ'
                    : 'Liên hệ';
                const hasPrice = Number(p.price) > 0;
                let url = p.url || (BASE_URL + '/view-product?pid=' + p.id);
                // Nếu backend trả url tương đối (không bắt đầu bằng http hoặc /), prefix BASE_URL
                if (url && !/^https?:\/\//i.test(url) && url.charAt(0) !== '/') {
                    url = BASE_URL + '/' + url.replace(/^\/+/, '');
                }
                const img = safeImg(p.image_url);
                const orderBadge = `<span class="vn-spot-rank">${idx + 1}</span>`;
                return `
                    <a class="vn-spot-card" href="${escAttr(url)}">
                        <div class="vn-spot-media">
                            ${orderBadge}
                            <img src="${escAttr(img)}" alt="${escAttr(p.name || 'Sản phẩm')}" loading="lazy" onerror="this.onerror=null;this.src='${PLACEHOLDER}'">
                        </div>
                        <div class="vn-spot-body">
                            <div class="vn-spot-eyebrow"><i class="bi bi-tags-fill"></i> Sản phẩm gợi ý</div>
                            <h3 class="vn-spot-name">${escText(p.name || '')}</h3>
                            <div class="vn-spot-meta">
                                <span class="vn-spot-price ${hasPrice ? '' : 'vn-spot-price--contact'}">${price}</span>
                                ${hasPrice ? '<span class="vn-spot-unit">/ sản phẩm</span>' : ''}
                            </div>
                            <span class="vn-spot-cta">
                                Xem chi tiết <i class="bi bi-arrow-right-short"></i>
                            </span>
                        </div>
                    </a>`;
            }).join('');
            if (navWrap) navWrap.style.display = (count > 3) ? 'flex' : 'none';
            if (count > 3) initSlider();
        } else if (data.banners && data.banners.length > 1) {
            relatedPanel.style.display = 'block';
            relatedTitle.textContent = 'Hình ảnh liên quan';
            relatedContent.classList.remove('is-single','is-double','is-triple');
            relatedContent.innerHTML = data.banners.slice(1, 12).map(img => {
                const src = safeImg(img);
                return `<a class="vn-spot-card vn-spot-card--banner" href="javascript:void(0)" onclick="window.open('${escAttr(src)}')"><div class="vn-spot-media"><img src="${escAttr(src)}" alt="banner" loading="lazy" onerror="this.onerror=null;this.src='${PLACEHOLDER}'"></div></a>`;
            }).join('');
            if (navWrap) navWrap.style.display = 'flex';
            initSlider();
        }

        loadingEl.style.display = 'none';
        containerEl.classList.add('loaded');
    }

    function initSlider() {
        const list = document.getElementById('js-related-content');
        const prev = document.getElementById('relatedPrev');
        const next = document.getElementById('relatedNext');
        if (!list || !prev || !next) return;
        const step = () => (list.querySelector('.vn-spot-card, .post-card')?.offsetWidth || 240) + 16;
        prev.onclick = () => list.scrollBy({ left: -step(), behavior: 'smooth' });
        next.onclick = () => list.scrollBy({ left: step(), behavior: 'smooth' });
    }
})();
</script>
