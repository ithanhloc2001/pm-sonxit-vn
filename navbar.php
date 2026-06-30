<?php

/**
 * Navbar Component
 * Logic and UI for the system's header and navigation menu.
 */

// Vai trò người dùng hiện tại
$userRole = $_SESSION['role'] ?? 'guest';

// Hiển thị các hành động liên quan tới mua sắm (giỏ hàng, tìm kiếm...)
// Ưu tiên sử dụng trạng thái đăng nhập và quyền từ config.php
$showShopActions = (!$isLoggedIn) || $isAdmin || $isUser;

// URL gửi form tìm kiếm và giỏ hàng (Dựa trên $baseUrl từ config.php)
$searchAction = $baseUrl . '/search';
$cartHref     = $baseUrl . '/cart';
$searchHintBase = rtrim($baseUrl, '/') . '/search?q=';

// Biến điều hướng active
$currentCase     = $_GET['case'] ?? '';
$normalRoute     = $_GET['normal'] ?? '';
$userRouteActive = $_GET['user'] ?? '';

// Router hợp nhất: route hiện tại nằm ở ?normal (path chung). Hỗ trợ alias cũ
// ?ithanhloc / ?user / ?ghn để link active không vỡ trong giai đoạn chuyển tiếp.
$activeRoute = $normalRoute !== '' ? $normalRoute : (
    $currentCase !== '' ? $currentCase : (
        !empty($_GET['ithanhloc']) ? (string)$_GET['ithanhloc'] : (
            $userRouteActive !== '' ? $userRouteActive : (
                !empty($_GET['ghn']) ? 'ghn-' . (string)$_GET['ghn'] : ''
            )
        )
    )
);
// Chuẩn hoá vài alias admin trùng tên trang user về tên route mới
$activeRouteAliasMap = ['order' => 'orders', 'voucher' => 'vouchers'];
if (!empty($_GET['ithanhloc']) && isset($activeRouteAliasMap[(string)$_GET['ithanhloc']])) {
    $activeRoute = $activeRouteAliasMap[(string)$_GET['ithanhloc']];
}

// Trang đích cho các hành động liên quan đơn hàng
$orderPageUrl = $showShopActions ? $searchAction : $baseUrl . '/';

// Đảm bảo userId là kiểu int cho các hàm phụ trợ
$currentUserId = (int)($userId ?? 0);

// Biến đếm số lượng thông báo chưa đọc
$headerUnreadCount = function_exists('pm_header_get_unread_notification_count')
    ? pm_header_get_unread_notification_count((isset($ithanhloc) && $ithanhloc instanceof mysqli) ? $ithanhloc : null, $currentUserId)
    : 0;

// Lấy thông tin địa chỉ đang áp dụng
$appliedLocation = function_exists('ecommerce_get_active_location_fields')
    ? ecommerce_get_active_location_fields((isset($ithanhloc) && $ithanhloc instanceof mysqli) ? $ithanhloc : null, $currentUserId)
    : [];

$headerAppliedAddress = (string)($appliedLocation['applied_address'] ?? 'Chưa thiết lập địa chỉ');
$headerLocationHref   = $isLoggedIn ? ($baseUrl . '/account?tab=address') : ($baseUrl . '/login');

// Hotline hiển thị (Ưu tiên site_hotline từ config)
$displayHotline = $site_hotline ?: ($company_hotline ?? '');
?>
<div class="fb-header">
    <!-- Header Topbar Announcement -->
    <div class="fb-header-topbar d-none d-lg-block">
        <div class="fb-header-topbar-inner d-flex justify-content-between align-items-center">
            <div class="topbar-left-marquee">
                <div class="topbar-left-track">
                    <!-- Set 1 -->
                    <span class="topbar-item"><i class="bi bi-patch-check"></i> Cam kết chất lượng - <strong>100% sơn nhập mỹ</strong></span>
                    <span class="topbar-dot">•</span>
                    <span class="topbar-item"><i class="bi bi-patch-check"></i> Sản phẩm <strong>Chính hãng - Xuất VAT đầy đủ</strong></span>
                    <span class="topbar-dot">•</span>
                    <span class="topbar-item"><i class="bi bi-truck"></i> Giao nhanh - <strong>Miễn phí cho đơn 500k</strong></span>
                    <span class="topbar-dot">•</span>
                    <!-- Set 2 (for seamless loop) -->
                    <span class="topbar-item"><i class="bi bi-patch-check"></i> Sản phẩm <strong>Chính hãng - Xuất VAT đầy đủ</strong></span>
                    <span class="topbar-dot">•</span>
                    <span class="topbar-item"><i class="bi bi-truck"></i> Giao nhanh - <strong>Miễn phí cho đơn 500k</strong></span>
                    <span class="topbar-dot">•</span>
                    <span class="topbar-item"><i class="bi bi-patch-check"></i> Cam kết chất lượng - <strong>100% sơn nhập mỹ</strong></span>
                    <span class="topbar-dot">•</span>
                </div>
            </div>
            <div class="topbar-right d-flex align-items-center gap-3">
                <a href="#" class="topbar-link" id="btnNearbyStores" data-bs-toggle="modal" data-bs-target="#nearbyStoreModal"><i class="bi bi-geo-alt"></i> Cửa hàng gần bạn</a>
                <span class="topbar-sep">|</span>
                <a href="<?= h($baseUrl) ?>/shopping" class="topbar-link"><i class="bi bi-file-earmark-text"></i> Đặt hàng</a>
                <span class="topbar-sep">|</span>
                <a href="tel:<?= $company_hotline ?>" class="topbar-link hotline"><i class="bi bi-telephone"></i> <?= $company_hotline ?></a>
            </div>
        </div>
    </div>
    <div class="fb-header-body">
        <div class="fb-header-shell">
            <div class="fb-header-top">
                <div class="fb-header-brand">
                    <!-- Nút toggle sidebar cho mobile -->
                    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Mở menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <!-- Logo -->
                    <a class="fb-header-logo" href="<?= h($baseUrl) ?>">
                        <img src="<?= h($_SiteLogo) ?>" alt="<?= h($_SiteTitle) ?>" loading="lazy" decoding="async" onerror="this.src='<?= h($baseUrl) ?>/image/paintmore.png';" />
                    </a>
                </div>

                <?php if ($showShopActions) { ?>
                    <!-- Thanh tìm kiếm -->
                    <div class="fb-header-search-cart is-hidden">
                        <div class="fb-header-search-wrap">
                            <form id="globalSearchForm" class="fb-header-search" action="<?= $searchAction ?>" method="get" autocomplete="off">
                                <input id="globalSearchInput" name="q" type="text" placeholder="Bạn cần tìm gì..." required>
                                <button class="fb-header-search-btn" type="submit" aria-label="Tìm kiếm">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                                <div id="searchSuggest" class="search-suggest d-none"></div>
                            </form>
                            <!-- <div class="search-hints fb-header-search-hints">
                            <a class="search-hint-item" href="<?= $searchHintBase . rawurlencode('Bán chạy') ?>"><i class="bi bi-lightning-charge"></i>Bán chạy</a>
                            <a class="search-hint-item" href="<?= $searchHintBase . rawurlencode('Sơn nội thất') ?>"><i class="bi bi-droplet"></i>Sơn nội thất</a>
                            <a class="search-hint-item" href="<?= $searchHintBase . rawurlencode('Sơn ngoại thất') ?>"><i class="bi bi-house"></i>Sơn ngoại thất</a>
                        </div> -->
                        </div>
                        <!-- /./ -->
                    </div>

                    <div class="fb-header-utility">
                        <!-- Tìm kiếm -->
                        <button id="headerSearchToggle" class="fb-header-icon-btn header-search-toggle" type="button" aria-label="Tìm kiếm">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <!-- Địa chỉ giao hàng -->
                        <a class="fb-header-action fb-header-action--location" href="<?= h($headerLocationHref) ?>" aria-label="Thiết lập địa chỉ giao hàng">
                            <span class="fb-header-action__icon"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="fb-header-action__text">
                                <span>Địa chỉ giao hàng</span>
                                <strong><?= h($headerAppliedAddress) ?></strong>
                            </span>
                        </a>
                        <!-- Giỏ hàng -->
                        <!-- Hỗ trợ / Ticket -->
                        <!-- <a class="fb-header-icon-btn" href="<?= h($baseUrl) ?>/support" aria-label="Hỗ trợ" title="Hỗ trợ / Ticket">
                        <i class="fa-solid fa-headset"></i>
                    </a> -->
                        <a class="fb-header-icon-btn fb-header-cart-btn" href="<?= $cartHref ?>" aria-label="Giỏ hàng">
                            <i class="fa-solid fa-bag-shopping"></i>
                            <span idd="cartBadgeHeader" id="cartBadge" class="fb-header-cart-count">0</span>
                        </a>
                        <!-- Hotline -->
                        <div class="d-none d-sm-block">
                            <a class="fb-header-action fb-header-action--hotline" href="tel:<?= h($displayHotline) ?>">
                                <span class="fb-header-action__icon"><i class="fa-solid fa-phone-volume"></i></span>
                                <span class="fb-header-action__text">
                                    <span>Hotline</span>
                                    <strong><?= h($displayHotline) ?></strong>
                                </span>
                            </a>
                        </div>
                        <!-- Ngôn ngữ 
                    <div class="dropdown d-inline-block pm-lang-dropdown">
                        <button class="fb-header-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Chuyển đổi ngôn ngữ">
                            <i class="bi bi-translate"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 p-2" style="min-width: 140px; border-radius: 12px;">
                            <li class="mb-1">
                                <button class="dropdown-item pm-lang-item rounded-3 d-flex align-items-center justify-content-between" data-lang="vi">
                                    <span>Tiếng Việt</span>
                                    <i class="bi bi-check2 active-check d-none"></i>
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item pm-lang-item rounded-3 d-flex align-items-center justify-content-between" data-lang="en">
                                    <span>English</span>
                                    <i class="bi bi-check2 active-check d-none"></i>
                                </button>
                            </li>
                        </ul>
                    </div>-->
                        <!-- Tài khoản -->
                        <?php if ($isLoggedIn) { ?>
                            <?php
                            $roleText = $userRole === 'admin' ? 'Quản trị viên' : 'Khách hàng';
                            $roleBadge = $userRole === 'admin' ? 'badge bg-danger-subtle text-danger border' : 'badge bg-success-subtle text-success border';
                            $userPhone = $_SESSION['user_phone'] ?? $_SESSION['phone'] ?? '';
                            ?>
                            <button class="sidebar-notify-toggle fb-header-icon-btn" onclick="toggleRightSidebar()" title="Thông báo" aria-label="Thông báo">
                                <i class="fa-solid fa-bell"></i>
                                <?php if (!empty($headerUnreadCount)): ?>
                                    <span class="fb-header-notify-badge" aria-label="<?= (int)$headerUnreadCount ?> thông báo chưa đọc">
                                        <?= ($headerUnreadCount > 99) ? '99+' : (int)$headerUnreadCount ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <!-- Tài khoản -->
                            <div class="dropdown fb-header-profile">
                                <button class="fb-header-action fb-header-action--user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="fb-header-action__icon"><i class="fa-regular fa-user"></i></span>
                                    <span class="fb-header-action__text">
                                        <span><?= h($_SESSION['user_name']) ?></span>
                                        <strong><?= !empty($userPhone) ? h($userPhone) : h($roleText) ?></strong>
                                    </span>
                                    <i class="fa-solid fa-chevron-down fb-header-action__chevron"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end header-user-menu">
                                    <!-- Thông tin tài khoản -->
                                    <li>
                                        <a class="dropdown-item" href="<?= h($baseUrl) ?>/account">
                                            <i class="bi bi-person-lines-fill me-2"></i>Thông tin tài khoản
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= h($baseUrl) ?>/support">
                                            <i class="bi bi-life-preserver me-2"></i>Hỗ trợ / Ticket
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= h($baseUrl) ?>/faq">
                                            <i class="bi bi-patch-question me-2"></i>Câu hỏi thường gặp
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <!--  Thoát đăng nhập -->
                                    <li>
                                        <a class="dropdown-item text-dark" href="<?= h($baseUrl) ?>/logout" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?');">
                                            <i class="bi bi-power me-2"></i> Đăng xuất
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        <?php } else { ?>
                            <!-- Chưa đăng nhập -->
                            <a href="<?= h($baseUrl) ?>/login" class="fb-header-action fb-header-action--user" aria-label="Đăng nhập">
                                <span class="fb-header-action__icon"><i class="fa-regular fa-user"></i></span>
                                <span class="fb-header-action__text">
                                    <span>Đăng nhập</span>
                                    <strong>Tài khoản</strong>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                    <!--  /./  -->

            </div>
        <?php } ?>

        <script>
            (function() {
                function setCookie(name, value, days, rawValue) {
                    try {
                        var d = new Date();
                        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
                        var expires = 'expires=' + d.toUTCString();
                        var v = rawValue ? String(value || '') : encodeURIComponent(String(value || ''));
                        document.cookie = name + '=' + v + ';' + expires + ';path=/;SameSite=Lax';
                    } catch (e) {}
                }

                function delCookie(name) {
                    try {
                        document.cookie = String(name || '') + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
                    } catch (e) {}
                }

                function getCookie(name) {
                    try {
                        var n = String(name || '').trim();
                        if (!n) return '';
                        var parts = ('; ' + document.cookie).split('; ' + n + '=');
                        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift() || '');
                    } catch (e) {}
                    return '';
                }

                function setLang(lang) {
                    var v = String(lang || '').toLowerCase();
                    if (v !== 'vi' && v !== 'en') return;
                    try {
                        localStorage.setItem('pm_lang', v);
                    } catch (e) {}
                    setCookie('pm_lang', v, 365);

                    // Auto-translate via Google Translate (hidden)
                    // Cookie format expected by Google: /<source>/<target>
                    if (v === 'en') {
                        setCookie('googtrans', '/vi/en', 365, true);
                    } else {
                        // reset translation
                        setCookie('googtrans', '/vi/vi', 365, true);
                        delCookie('googtrans');
                    }
                    location.reload();
                }

                function loadGoogleTranslateIfNeeded() {
                    // Only load when English is selected
                    var current = (getCookie('pm_lang') || (function() {
                        try {
                            return localStorage.getItem('pm_lang') || '';
                        } catch (e) {
                            return '';
                        }
                    })() || 'vi').toLowerCase();
                    if (current !== 'en') return;
                    if (window.__pmGoogleTranslateLoaded) return;
                    window.__pmGoogleTranslateLoaded = true;

                    // Ensure googtrans cookie is set (some browsers may clear it)
                    setCookie('googtrans', '/vi/en', 365, true);

                    window.googleTranslateElementInit = function() {
                        try {
                            if (!document.getElementById('google_translate_element')) return;
                            if (!window.google || !google.translate || !google.translate.TranslateElement) return;
                            new google.translate.TranslateElement({
                                pageLanguage: 'vi',
                                includedLanguages: 'vi,en',
                                autoDisplay: false
                            }, 'google_translate_element');
                        } catch (e) {}
                    };

                    var s = document.createElement('script');
                    s.type = 'text/javascript';
                    s.async = true;
                    s.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
                    document.head.appendChild(s);
                }

                function initLangMenu() {
                    var current = (getCookie('pm_lang') || (function() {
                        try {
                            return localStorage.getItem('pm_lang') || '';
                        } catch (e) {
                            return '';
                        }
                    })() || 'vi').toLowerCase();
                    document.querySelectorAll('.pm-lang-item').forEach(function(btn) {
                        var v = String(btn.getAttribute('data-lang') || '').toLowerCase();
                        if (v === current) {
                            btn.classList.add('active');
                            var check = btn.querySelector('.active-check');
                            if (check) check.classList.remove('d-none');
                        }
                        btn.addEventListener('click', function() {
                            setLang(v);
                        });
                    });
                }

                const $input = $('#globalSearchInput');
                const $suggest = $('#searchSuggest');
                const $form = $('#globalSearchForm');
                const $cartBadge = $('#cartBadge');
                const $searchCart = $('.fb-header-search-cart');
                const $searchToggle = $('#headerSearchToggle');
                const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
                const VOUCHER_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
                const ORDER_URL = '<?= $orderPageUrl ?>';
                const COUPON_URL = '<?= $baseUrl . '/voucher' ?>';
                if (!$input.length) {
                    return;
                }
                let timer;
                let catCache = [];
                let couponCache = [];
                const HISTORY_KEY = 'search_history';
                const currentQueryFromUrl = new URLSearchParams(window.location.search).get('q') || '';
                if (!$input.val() && currentQueryFromUrl) {
                    $input.val(currentQueryFromUrl);
                }

                function buildUrl(base, params = {}) {
                    try {
                        const url = new URL(base, window.location.origin);
                        Object.keys(params).forEach(key => {
                            const val = params[key];
                            if (val === undefined || val === null || val === '') return;
                            url.searchParams.set(key, val);
                        });
                        return url.pathname + url.search;
                    } catch (e) {
                        return base;
                    }
                }

                function fmtPrice(n) {
                    const raw = Number(n || 0);
                    if (raw <= 0) return 'Liên hệ';
                    if (window.pmFormatPrice && typeof window.pmFormatPrice === 'function') {
                        return window.pmFormatPrice(raw);
                    }
                    return raw.toLocaleString('vi-VN') + ' đ';
                }

                function loadHistory() {
                    try {
                        const raw = localStorage.getItem(HISTORY_KEY);
                        const list = raw ? JSON.parse(raw) : [];
                        return Array.isArray(list) ? list : [];
                    } catch (e) {
                        return [];
                    }
                }

                function saveHistory(term) {
                    const t = String(term || '').trim();
                    if (!t) return;
                    const list = loadHistory();
                    const lower = t.toLowerCase();
                    const next = [t].concat(list.filter(item => String(item).toLowerCase() !== lower)).slice(0, 8);
                    try {
                        localStorage.setItem(HISTORY_KEY, JSON.stringify(next));
                    } catch (e) {
                        return;
                    }
                }

                function removeHistory(term) {
                    const t = String(term || '').trim().toLowerCase();
                    if (!t) return;
                    const list = loadHistory().filter(item => String(item).toLowerCase() !== t);
                    try {
                        localStorage.setItem(HISTORY_KEY, JSON.stringify(list));
                    } catch (e) {
                        return;
                    }
                }

                function buildSearchUrl(q) {
                    const rawAction = $form.attr('action') || ORDER_URL;
                    const action = String(rawAction || '').replace(/\/$/, '');
                    const term = String(q || '').trim();
                    if (!term) {
                        return action || '/search';
                    }
                    if (action.toLowerCase().endsWith('/search')) {
                        return action + '?q=' + encodeURIComponent(term);
                    }
                    // Fallback: thêm q vào query string
                    return buildUrl(action, {
                        q: term
                    });
                }

                function setSearchOpen(nextOpen) {
                    if (!$searchCart.length) return;
                    const shouldOpen = Boolean(nextOpen);
                    $searchCart.toggleClass('is-open', shouldOpen);
                    $searchCart.toggleClass('is-hidden', !shouldOpen);
                    if (shouldOpen) {
                        setTimeout(() => $input.trigger('focus'), 0);
                    } else {
                        renderSuggestSections({});
                    }
                }

                function buildOrderUrl(params) {
                    return buildUrl(ORDER_URL, params);
                }

                function buildCouponUrl(code) {
                    return buildUrl(COUPON_URL, {
                        code
                    });
                }

                function renderHistory() {
                    const list = loadHistory();
                    if (!list.length) {
                        $suggest.addClass('d-none').empty();
                        return;
                    }
                    const items = list.map(term => {
                        const safe = $('<div>').text(term).text();
                        return `<a class="suggest-item history" data-q="${safe}" href="${buildSearchUrl(term)}">` +
                            `<div class="suggest-text"><div class="suggest-name">${safe}</div><div class="suggest-price text-muted">Lịch sử tìm kiếm</div></div>` +
                            `<button class="suggest-remove" type="button" data-q="${safe}" aria-label="Xóa">×</button>` +
                            `</a>`;
                    });
                    $suggest.html(`<div class="suggest-section-title">Lịch sử tìm kiếm</div>` + items.join('')).removeClass('d-none');
                }

                function renderSuggestSections({
                    keyword = '',
                    products = []
                }) {
                    const blocks = [];
                    const section = (title, items) => {
                        if (!items.length) return;
                        blocks.push(`<div class="suggest-section-title">${title}</div>` + items.join(''));
                    };
                    if (keyword) {
                        section('Tìm kiếm của bạn', [keyword]);
                    }
                    section('Sản phẩm', products);
                    if (!blocks.length) {
                        $suggest.addClass('d-none').empty();
                        return;
                    }
                    $suggest.html(blocks.join('')).removeClass('d-none');
                }

                const FALLBACK_THUMB = '<?= $site_fallback_logo ? h(to_abs_url((string)$site_fallback_logo, (string)$baseUrl), ENT_QUOTES, 'UTF-8') : '' ?>';

                function normalizeThumb(url) {
                    if (!url) return FALLBACK_THUMB;
                    const s = String(url).trim();
                    if (!s) return FALLBACK_THUMB;
                    if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(s);
                    if (/^https?:\/\//i.test(s) || s.startsWith('data:')) return s;
                    const cleaned = s.replace(/^\.?\//, '');
                    return '<?= h($baseUrl) ?>/' + cleaned;
                }

                function productItem(p, query = '') {
                    const thumb = normalizeThumb(p.thumb || p.image_url);
                    const name = $('<div>').text(p.product_name || 'Sản phẩm').text();
                    const price = fmtPrice(p.gia_min ?? 0);
                    let href;
                    if (window.pmBuildProductUrl) {
                        const extra = query ? {
                            q: query
                        } : null;
                        href = window.pmBuildProductUrl(p.id, p.product_name || name, extra);
                    } else {
                        href = buildUrl('<?= h($baseUrl) ?>/view-product', {
                            pid: p.id,
                            q: query
                        });
                    }
                    return `<a class="suggest-item" href="${href}">` +
                        `<img class="suggest-thumb" src="${thumb}" alt="${name}" loading="lazy" decoding="async" onerror="this.src='${FALLBACK_THUMB}'">` +
                        `<div class="suggest-text"><div class="suggest-name">${name}</div><div class="suggest-price">${price}</div></div>` +
                        `</a>`;
                }

                function keywordItem(q) {
                    const safe = $('<div>').text(q).text();
                    return `<a class="suggest-item" data-q="${safe}" href="${buildSearchUrl(q)}">` +
                        `<div class="suggest-text"><div class="suggest-name">Tìm kiếm: ${safe}</div><div class="suggest-price text-muted">Từ khoá</div></div>` +
                        `</a>`;
                }


                function fetchProducts(q, done) {
                    $.ajax({
                        url: API,
                        type: 'GET',
                        dataType: 'json',
                        data: {
                            ajax: 'products',
                            search: {
                                value: q
                            },
                            length: 6,
                            start: 0
                        },
                        success: function(res) {
                            try {
                                done(res && res.data ? res.data : []);
                            } catch (e) {
                                done([]);
                            }
                        },
                        error: function() {
                            done([]);
                        }
                    });
                }


                function handleInput(q) {
                    if (!q || q.trim().length < 2) {
                        $suggest.addClass('d-none').empty();
                        return;
                    }
                    fetchProducts(q, products => {
                        renderSuggestSections({
                            keyword: keywordItem(q),
                            products: products.map(p => productItem(p, q))
                        });
                    });
                }

                function refreshCartBadge() {
                    $.get(API, {
                        ajax: 'cart_get'
                    }, res => {
                        if (res && res.ok && typeof res.count !== 'undefined') {
                            $cartBadge.text(res.count);
                        }
                    });
                }


                initLangMenu();
                loadGoogleTranslateIfNeeded();

                $input.on('input', function() {
                    const val = this.value;
                    clearTimeout(timer);
                    timer = setTimeout(() => handleInput(val), 220);
                });

                $searchToggle.on('click', function() {
                    const isOpen = $searchCart.hasClass('is-open');
                    setSearchOpen(!isOpen);
                });

                $input.on('focus', function() {
                    if (!this.value.trim()) {
                        $suggest.addClass('d-none').empty();
                    }
                });

                $suggest.on('click', '[data-q]', function() {
                    const term = String($(this).data('q') || '').trim();
                    if (term) saveHistory(term);
                });

                $suggest.on('click', '.suggest-remove', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const term = String($(this).data('q') || '').trim();
                    if (term) {
                        removeHistory(term);
                        renderHistory();
                    }
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.fb-header-search').length && !$(e.target).closest('.search-suggest').length) {
                        renderSuggestSections({});
                    }
                    if (!$searchCart.length) return;
                    const inside = $(e.target).closest('.fb-header-search-cart, #headerSearchToggle').length > 0;
                    if (!inside) {
                        setSearchOpen(false);
                    }
                });

                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape') {
                        setSearchOpen(false);
                    }
                });

                $form.on('submit', function() {
                    saveHistory($input.val());
                    renderSuggestSections({});
                    setSearchOpen(false);
                });

                // --- Search Hints Rotation ---
                (function() {
                    const $hintsContainer = $('.fb-header-search-hints');
                    if (!$hintsContainer.length) return;

                    const baseSearchUrl = '<?= $searchHintBase ?>';
                    const hintsPool = [{
                            text: 'Bán chạy',
                            icon: 'bi bi-lightning-charge'
                        },
                        {
                            text: 'Sơn nội thất',
                            icon: 'bi bi-droplet'
                        },
                        {
                            text: 'Sơn ngoại thất',
                            icon: 'bi bi-house'
                        },
                        {
                            text: 'Sơn chống thấm',
                            icon: 'bi bi-shield-check'
                        },
                        {
                            text: 'Bột trét tường',
                            icon: 'bi bi-brush'
                        },
                        {
                            text: 'Sơn chống rỉ',
                            icon: 'bi bi-shield-fill-exclamation'
                        },
                        {
                            text: 'Sơn lót chống kiềm',
                            icon: 'bi bi-paint-bucket'
                        },
                        {
                            text: 'Sơn bóng cao cấp',
                            icon: 'bi bi-star'
                        },
                        {
                            text: 'Sơn chịu nhiệt',
                            icon: 'bi bi-fire'
                        },
                        {
                            text: 'Sơn DIY',
                            icon: 'bi bi-palette'
                        }
                    ];

                    let currentIndex = 0;
                    const showCount = 3;

                    function rotateHints() {
                        $hintsContainer.css('opacity', '0');
                        setTimeout(function() {
                            currentIndex = (currentIndex + showCount) % hintsPool.length;
                            let html = '';
                            for (let i = 0; i < showCount; i++) {
                                const item = hintsPool[(currentIndex + i) % hintsPool.length];
                                const href = baseSearchUrl + encodeURIComponent(item.text);
                                html += `<a class="search-hint-item" href="${href}"><i class="${item.icon}"></i>${item.text}</a>`;
                            }
                            $hintsContainer.html(html);
                            $hintsContainer.css('opacity', '1');
                        }, 400); // matches CSS transition time
                    }

                    // Rotate every 2.5 seconds
                    setInterval(rotateHints, 2500);
                })();

                // --- Placeholder Rotation Effect (Accessible & Clean) ---
                (function() {
                    if (!$input.length) return;

                    const texts = [
                        "Bạn cần tìm gì...",
                        "Sơn chống thấm...",
                        "Bột trét tường...",
                        "Sơn chống rỉ...",
                        "Sơn lót chống kiềm...",
                        "Sơn bóng cao cấp..."
                    ];

                    let textIndex = 0;

                    function rotatePlaceholder() {
                        if ($input.is(':focus') || $input.val().length > 0) {
                            $input.attr('placeholder', texts[0]);
                        } else {
                            $input.attr('placeholder', texts[textIndex]);
                            textIndex = (textIndex + 1) % texts.length;
                        }
                    }

                    // Delay startup then rotate every 3 seconds
                    rotatePlaceholder();
                    setInterval(rotatePlaceholder, 3000);
                })();

                refreshCartBadge();
            })();
        </script>

        </div>
        <!-- Menu điều hướng chính -->
        <div class="header-menu-row">
            <nav class="header-menu">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>" class="nav-link <?php echo (in_array($activeRoute, ['', 'home', 'dashboard'], true)) ? 'active' : ''; ?>">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Trang chủ</span>
                        </a>
                    </li>
                    <li class="nav-item d-none">
                        <a href="<?= h($baseUrl) ?>/account" class="nav-link <?php echo ($currentCase === 'account' || $currentCase === '/account') ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i>
                            <span>Tài khoản</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/blog" class="nav-link <?php echo ((($currentCase === 'blog') || ($normalRoute === 'blog') || ($userRouteActive === 'blog')) ? 'active' : ''); ?>">
                            <i class="bi bi-newspaper"></i>
                            <span>Bài viết</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/shopping" class="nav-link <?php echo ((($currentCase === 'shopping') || ($normalRoute === 'shopping') || ($userRouteActive === 'shopping')) ? 'active' : ''); ?>">
                            <i class="bi bi-cart-check"></i>
                            <span>Mua sắm</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/order" class="nav-link <?php echo ((($currentCase === 'order') || ($normalRoute === 'order') || ($userRouteActive === 'order')) ? 'active' : ''); ?>">
                            <i class="bi bi-receipt"></i>
                            <span>Đơn mua</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/cart" class="nav-link <?php echo ((($currentCase === 'cart') || ($normalRoute === 'cart') || ($userRouteActive === 'cart')) ? 'active' : ''); ?>">
                            <i class="bi bi-cart"></i>
                            <span>Xem giỏ hàng</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/voucher" class="nav-link <?php echo ((($currentCase === 'voucher') || ($normalRoute === 'voucher') || ($userRouteActive === 'voucher')) ? 'active' : ''); ?>">
                            <i class="bi bi-ticket-perforated"></i>
                            <span>Săn Voucher</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/support" class="nav-link <?php echo ((in_array(($currentCase ?? ''), ['support', 'support-detail'], true) || in_array(($normalRoute ?? ''), ['support', 'support-detail'], true) || in_array(($userRouteActive ?? ''), ['support'], true)) ? 'active' : ''); ?>">
                            <i class="bi bi-life-preserver"></i>
                            <span>Hỗ trợ</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= h($baseUrl) ?>/faq" class="nav-link <?php echo ((($currentCase === 'faq') || ($normalRoute === 'faq') || ($userRouteActive === 'faq')) ? 'active' : ''); ?>">
                            <i class="bi bi-patch-question"></i>
                            <span>Trung tâm trợ giúp</span>
                        </a>
                    </li>

                    <!-- Admin menu chỉ hiển thị nếu người dùng đã đăng nhập và có quyền admin -->
                    <?php if ($isLoggedIn && $isAdmin): ?>
                        <li class="nav-item">
                            <input type="checkbox" id="AdminToggleHeader" class="nav-toggle">
                            <label for="AdminToggleHeader" class="nav-link nav-toggle-label">
                                <i class="bi bi-sliders"></i>
                                <span>Hệ thống</span>
                                <i class="bi bi-chevron-down nav-chevron"></i>
                            </label>
                            <ul class="nav-sublist">
                                <!-- Sản phẩm -->
                                <li class="nav-item">
                                    <input type="checkbox" id="adminEcomToggleHeader" class="nav-toggle" <?php echo in_array($activeRoute, ['product', 'product-change', 'orders', 'order-change', 'rating', 'store', 'vouchers', 'promotion', 'shipping-rule'], true) ? 'checked' : ''; ?> />
                                    <label for="adminEcomToggleHeader" class="nav-link nav-toggle-label <?php echo in_array($activeRoute, ['product', 'product-change', 'orders', 'order-change', 'rating', 'store', 'vouchers', 'promotion', 'shipping-rule'], true) ? 'active' : ''; ?>">
                                        <i class="bi bi-box-seam"></i>
                                        <span>Ecommerce</span>
                                        <i class="bi bi-chevron-down nav-chevron"></i>
                                    </label>
                                    <ul class="nav-sublist">
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/product" class="nav-link <?php echo ($activeRoute === 'product' ? 'active' : ''); ?>"><i class="bi bi-box-seam"></i><span>Sản phẩm</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/orders" class="nav-link <?php echo ($activeRoute === 'orders' ? 'active' : ''); ?>"><i class="bi bi-receipt"></i><span>Đơn hàng</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/vouchers" class="nav-link <?php echo ($activeRoute === 'vouchers' ? 'active' : ''); ?>"><i class="bi bi-ticket-perforated"></i><span>Voucher</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/promotion" class="nav-link <?php echo ($activeRoute === 'promotion' ? 'active' : ''); ?>"><i class="bi bi-ticket-perforated"></i><span>Khuyến mãi</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/rating" class="nav-link <?php echo ($activeRoute === 'rating' ? 'active' : ''); ?>"><i class="bi bi-chat-square-dots"></i><span>Đánh giá </span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/store" class="nav-link <?php echo ($activeRoute === 'store' ? 'active' : ''); ?>"><i class="bi bi-geo-alt"></i><span>Chi nhánh</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/shipping-rule" class="nav-link <?php echo ($activeRoute === 'shipping-rule' ? 'active' : ''); ?>"><i class="bi bi-truck"></i><span>Phí vận chuyển</span></a></li>
                                    </ul>
                                </li>
                                <!-- Giao hàng -->
                                <li class="nav-item">
                                    <input type="checkbox" id="adminGHNToggleHeader" class="nav-toggle" <?php echo in_array($activeRoute, ['ghn-setup', 'ghn-store', 'ghn-create', 'ghn-order'], true) ? 'checked' : ''; ?> />
                                    <label for="adminGHNToggleHeader" class="nav-link nav-toggle-label">
                                        <i class="bi bi-truck"></i>
                                        <span>Giao hàng</span>
                                        <i class="bi bi-chevron-down nav-chevron"></i>
                                    </label>
                                    <ul class="nav-sublist">
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/ghn-setup" class="nav-link <?php echo ($activeRoute === 'ghn-setup' ? 'active' : ''); ?>"><i class="bi bi-gear"></i><span>Cấu hình API</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/ghn-store" class="nav-link <?php echo ($activeRoute === 'ghn-store' ? 'active' : ''); ?>"><i class="bi bi-shop"></i><span>Chọn Shop</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/ghn-create" class="nav-link <?php echo ($activeRoute === 'ghn-create' ? 'active' : ''); ?>"><i class="bi bi-plus-circle-fill"></i><span>Tạo Đơn Hàng</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/ghn-order" class="nav-link <?php echo ($activeRoute === 'ghn-order' ? 'active' : ''); ?>"><i class="bi bi-receipt"></i><span>Quản Lý Đơn</span></a></li>
                                    </ul>
                                </li>
                                <!-- Blog đăng bài --->
                                <li class="nav-item">
                                    <input type="checkbox" id="BlogToggleHeader" class="nav-toggle" <?php echo ($activeRoute === 'blog-manager' ? 'checked' : ''); ?>>
                                    <label for="BlogToggleHeader" class="nav-link nav-toggle-label">
                                        <i class="bi bi-journal-text"></i>
                                        <span>Blog</span>
                                        <i class="bi bi-chevron-down nav-chevron"></i>
                                    </label>
                                    <ul class="nav-sublist">
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/blog-manager" class="nav-link <?php echo ($activeRoute === 'blog-manager' ? 'active' : ''); ?>"><i class="bi bi-pencil-square"></i><span>Quản lý bài viết</span></a></li>
                                    </ul>
                                </li>
                                <?php $supActive = in_array($activeRoute, ['support-tickets', 'support-ticket', 'faq-manager'], true); ?>
                                <li class="nav-item">
                                    <input type="checkbox" id="SupportToggleHeader" class="nav-toggle" <?php echo ($supActive ? 'checked' : ''); ?>>
                                    <label for="SupportToggleHeader" class="nav-link nav-toggle-label">
                                        <i class="bi bi-life-preserver"></i>
                                        <span>Hỗ trợ</span>
                                        <i class="bi bi-chevron-down nav-chevron"></i>
                                    </label>
                                    <ul class="nav-sublist">
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/support-tickets" class="nav-link <?php echo (in_array($activeRoute, ['support-tickets', 'support-ticket'], true) ? 'active' : ''); ?>"><i class="bi bi-chat-left-dots"></i><span>Quản lý Ticket</span></a></li>
                                        <li class="nav-item"><a href="<?= h($baseUrl) ?>/faq-manager" class="nav-link <?php echo ($activeRoute === 'faq-manager' ? 'active' : ''); ?>"><i class="bi bi-patch-question"></i><span>Câu hỏi thường gặp</span></a></li>
                                    </ul>
                                </li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/setting" class="nav-link <?php echo ($activeRoute === 'setting' ? 'active' : ''); ?>"><i class="bi bi-gear"></i><span>Cài đặt chung</span></a></li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/zns" class="nav-link <?php echo ($activeRoute === 'zns' ? 'active' : ''); ?>"><i class="bi bi-gear"></i><span>Quản lí ZNS</span></a></li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/users" class="nav-link <?php echo ($activeRoute === 'users' ? 'active' : ''); ?>"><i class="bi bi-people-fill"></i><span>Quản lý Khách</span></a></li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/banner" class="nav-link <?php echo ($activeRoute === 'banner' ? 'active' : ''); ?>"><i class="bi bi-image"></i><span>Quản lý Banner</span></a></li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/partner" class="nav-link <?php echo ($activeRoute === 'partner' ? 'active' : ''); ?>"><i class="bi bi-image"></i><span>Quản lý Đối tác</span></a></li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/notification" class="nav-link <?php echo ($activeRoute === 'notification' ? 'active' : ''); ?>"><i class="bi bi-bell"></i><span>Quản lý Thông báo</span></a></li>
                                <li class="nav-item"><a href="<?= h($baseUrl) ?>/preview" class="nav-link <?php echo ($activeRoute === 'preview' ? 'active' : ''); ?>"><i class="bi bi-bell"></i><span>Quản lý Video Review</span></a></li>

                                <!--<li class="nav-item d-none"><a href="<?= h($baseUrl) ?>/color-manager" class="nav-link <?php echo ($activeRoute === 'color-manager' ? 'active' : ''); ?>"><i class="bi bi-palette2"></i><span>Quản lý Mã Màu</span></a></li>-->

                            </ul>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item d-none">
                        <a href="<?= h($baseUrl) ?>/contact" class="nav-link <?php echo ((($currentCase === 'contact') || ($normalRoute === 'contact') || ($userRouteActive === 'contact')) ? 'active' : ''); ?>">
                            <i class="bi bi-envelope"></i>
                            <span>Liên hệ</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>


    </div>
</div>

<!-- MODAL: CỬA HÀNG GẦN BẠN -->
<div class="modal fade" id="nearbyStoreModal" tabindex="-1" aria-labelledby="nearbyStoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(12,76,41,.1);">
                        <i class="bi bi-geo-alt fs-5" style="color:#0c4c29;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold" id="nearbyStoreModalLabel" style="font-size:1.05rem;">Cửa hàng gần bạn</h5>
                        <small class="text-muted">Tìm chi nhánh và xem đường đi từ vị trí của bạn.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-0">
                <div id="nearbyGeoStatus" class="alert alert-light border d-flex align-items-center gap-2 py-2 small mb-3">
                    <i class="bi bi-cursor"></i>
                    <span id="nearbyGeoText">Bấm "Dùng vị trí của tôi" để xem đường đi nhanh tới cửa hàng.</span>
                    <button type="button" id="nearbyGeoBtn" class="btn btn-sm ms-auto" style="background:#0c4c29;color:#fff;white-space:nowrap;">
                        <i class="bi bi-crosshair me-1"></i>Dùng vị trí của tôi
                    </button>
                </div>
                <div id="nearbyStoreList">
                    <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Đang tải danh sách cửa hàng...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var modalEl = document.getElementById('nearbyStoreModal');
        if (!modalEl) return;
        var listEl = document.getElementById('nearbyStoreList');
        var geoBtn = document.getElementById('nearbyGeoBtn');
        var geoText = document.getElementById('nearbyGeoText');
        var BASE = '<?= h(rtrim($baseUrl, '/')) ?>';
        var stores = [];
        var userPos = null; // {lat, lng}
        var loaded = false;

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c];
            });
        }

        // Link Google Maps: nếu có vị trí khách thì chỉ đường, không thì search địa chỉ.
        function mapsLink(store) {
            var dest = encodeURIComponent(store.address_detail || store.branch_name || '');
            if (userPos) {
                return 'https://www.google.com/maps/dir/?api=1&origin=' + userPos.lat + ',' + userPos.lng + '&destination=' + dest;
            }
            if (store.map_url) return store.map_url;
            return 'https://www.google.com/maps/search/?api=1&query=' + dest;
        }

        function render() {
            if (!stores.length) {
                listEl.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-shop fs-3 d-block mb-2"></i>Chưa có cửa hàng nào.</div>';
                return;
            }
            var dirLabel = userPos ? 'Chỉ đường' : 'Xem bản đồ';
            listEl.innerHTML = stores.map(function(s) {
                var thumb = s.avatar_image ? (BASE + '/' + String(s.avatar_image).replace(/^\/+/, '')) : '';
                var thumbHtml = thumb ?
                    '<img src="' + esc(thumb) + '" alt="" style="width:54px;height:54px;object-fit:cover;border-radius:12px;flex:0 0 auto;">' :
                    '<div style="width:54px;height:54px;border-radius:12px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex:0 0 auto;"><i class="bi bi-shop" style="color:#0c4c29;"></i></div>';
                var hotline = s.hotline ? '<a href="tel:' + esc(s.hotline) + '" class="text-decoration-none"><i class="bi bi-telephone me-1"></i>' + esc(s.hotline) + '</a>' : '';
                return '<div class="d-flex gap-3 p-2 mb-2 rounded-3 border align-items-start">' +
                    thumbHtml +
                    '<div class="flex-grow-1 min-w-0">' +
                    '<div class="fw-semibold">' + esc(s.branch_name || 'Chi nhánh') + (s.region ? ' <span class="badge rounded-pill text-bg-light fw-normal">' + esc(s.region) + '</span>' : '') + '</div>' +
                    '<div class="small text-muted">' + esc(s.address_detail || '') + '</div>' +
                    '<div class="small mt-1 d-flex gap-3 flex-wrap">' + hotline + '</div>' +
                    '</div>' +
                    '<a href="' + esc(mapsLink(s)) + '" target="_blank" rel="noopener" class="btn btn-sm align-self-center" style="background:#0c4c29;color:#fff;white-space:nowrap;"><i class="bi bi-signpost-2 me-1"></i>' + dirLabel + '</a>' +
                    '</div>';
            }).join('');
        }

        function loadStores() {
            if (loaded) return;
            loaded = true;
            fetch(BASE + '/core_user/ecommerce/ajax/store.php?action=list_stores', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    stores = (d && d.ok && Array.isArray(d.stores)) ? d.stores : [];
                    render();
                })
                .catch(function() {
                    listEl.innerHTML = '<div class="text-center text-danger py-4">Không tải được danh sách cửa hàng.</div>';
                });
        }

        if (geoBtn) {
            geoBtn.addEventListener('click', function() {
                if (!('geolocation' in navigator)) {
                    geoText.textContent = 'Trình duyệt không hỗ trợ định vị. Bạn vẫn có thể xem bản đồ từng cửa hàng.';
                    return;
                }
                geoBtn.disabled = true;
                var old = geoBtn.innerHTML;
                geoBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang định vị...';
                navigator.geolocation.getCurrentPosition(function(pos) {
                    userPos = {
                        lat: pos.coords.latitude.toFixed(6),
                        lng: pos.coords.longitude.toFixed(6)
                    };
                    geoText.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Đã lấy vị trí — bấm "Chỉ đường" để mở đường đi tới cửa hàng.';
                    geoBtn.style.display = 'none';
                    render();
                }, function(err) {
                    geoBtn.disabled = false;
                    geoBtn.innerHTML = old;
                    geoText.textContent = (err && err.code === 1) ?
                        'Bạn đã từ chối chia sẻ vị trí. Vẫn có thể xem bản đồ từng cửa hàng.' :
                        'Không lấy được vị trí. Vẫn có thể xem bản đồ từng cửa hàng.';
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                });
            });
        }

        modalEl.addEventListener('show.bs.modal', loadStores);
    })();
</script>