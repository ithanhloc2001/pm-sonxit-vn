<!DOCTYPE html>
<?php
$pmLang = strtolower(trim((string)($_COOKIE['pm_lang'] ?? 'vi')));
if (!in_array($pmLang, ['vi', 'en'], true)) {
    $pmLang = 'vi';
}
?>
<html lang="<?= h($pmLang) ?>">

<head>
    <meta charset="UTF-8">
    <?php
    // Dùng cấu hình website từ site_setting, có fallback về giá trị cũ để an toàn
    // Cấu hình website từ config.php
    $_SiteUrl = $_SiteUrl ?? ($site_url ?: ($baseUrl ?? '/'));
    $_SiteTitle = $_SiteTitle ?? ($site_title ?: 'Paint&More');
    // Xử lý logo: route file media sang media domain (qua to_abs_url), URL tuyệt đối giữ nguyên.
    $_SiteLogoPath = $site_logo ?: ($site_fallback_logo ?? '');
    if (function_exists('to_abs_url')) {
        $_SiteLogo = to_abs_url((string)$_SiteLogoPath, (string)($baseUrl ?? ''));
    } elseif (!preg_match('~^https?://~i', (string)$_SiteLogoPath)) {
        $_SiteLogo = rtrim((string)($baseUrl ?? ''), '/') . '/' . ltrim((string)$_SiteLogoPath, '/');
    } else {
        $_SiteLogo = $_SiteLogoPath;
    }
    // Meta title: ưu tiên pageTitle nếu được thiết lập, fallback về site_title
    $metaTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? (string)$pageTitle : $_SiteTitle;
    // Canonical URL: ưu tiên pageCanonicalUrl nếu được thiết lập, fallback về site_url
    $canonicalUrl = isset($pageCanonicalUrl) && trim((string)$pageCanonicalUrl) !== '' ? (string)$pageCanonicalUrl : $_SiteUrl;
    // Meta description: ưu tiên pageDescription (theo từng trang), fallback về SITE_DESCRIPTION trong cấu hình.
    // Decode entity trước (phòng dữ liệu đã bị encode khi lưu) để h() bên dưới không double-encode (&amp;amp;).
    $metaDescription = (isset($pageDescription) && trim((string)$pageDescription) !== '')
        ? trim((string)$pageDescription)
        : (string)($site_description ?? '');
    $metaDescription = trim(html_entity_decode($metaDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $metaImageUrl = $_SiteLogo;
    if (isset($pageImageUrl) && trim((string)$pageImageUrl) !== '') {
        $metaImageUrl = (string)$pageImageUrl;
    }
    // ogType: ưu tiên pageOgType nếu được thiết lập, fallback về 'website'
    $ogType = isset($pageOgType) && trim((string)$pageOgType) !== '' ? (string)$pageOgType : 'website';
    ?>
    <!-- /./ -->
    <title><?= h($metaTitle) ?></title>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>" />
    <link rel="alternate" href="<?= h($canonicalUrl) ?>" hreflang="vi" />
    <link href="<?= h($_SiteLogo) ?>" type="image/x-icon" rel="shortcut icon" />
    <?php if ($metaDescription !== ''): ?>
    
    <meta name="description" content="<?= h($metaDescription) ?>" /> <?php endif; ?>
    <meta property="og:title" content="<?= h($metaTitle) ?>" />
    <meta property="og:description" content="<?= h($metaDescription) ?>" />
    <meta property="og:url" content="<?= h($canonicalUrl) ?>" />
    <meta property="og:image" content="<?= h($metaImageUrl) ?>" />
    <meta property="og:type" content="<?= h($ogType) ?>" />
    <meta property="og:site_name" content="<?= h($_SiteTitle) ?>" />
    <meta property="og:locale" content="<?= h($pmLang === 'en' ? 'en_US' : 'vi_VN') ?>" />
    <?php if (isset($pageAuthor) && trim((string)$pageAuthor) !== ''): ?>
    
    <meta name="author" content="<?= h((string)$pageAuthor) ?>" /><?php endif; ?>
    <?php if ($ogType === 'article'): ?>
        <?php if (isset($pageArticlePublished) && trim((string)$pageArticlePublished) !== ''): ?>
            <meta property="article:published_time" content="<?= h((string)$pageArticlePublished) ?>" />
        <?php endif; ?>
        <?php if (isset($pageArticleModified) && trim((string)$pageArticleModified) !== ''): ?>
            <meta property="article:modified_time" content="<?= h((string)$pageArticleModified) ?>" />
        <?php endif; ?>
        <?php if (isset($pageArticleSection) && trim((string)$pageArticleSection) !== ''): ?>
            <meta property="article:section" content="<?= h((string)$pageArticleSection) ?>" />
        <?php endif; ?>
        <?php if (isset($pageAuthor) && trim((string)$pageAuthor) !== ''): ?>
            <meta property="article:author" content="<?= h((string)$pageAuthor) ?>" />
        <?php endif; ?>
        <?php if (isset($pageArticleTags) && is_array($pageArticleTags)):
            foreach ($pageArticleTags as $_tag): $_tag = trim((string)$_tag);
                if ($_tag === '') continue; ?>
                <meta property="article:tag" content="<?= h($_tag) ?>" />
        <?php endforeach;
        endif; ?>
    <?php endif; ?>

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= h($metaTitle) ?>" />
    <meta name="twitter:description" content="<?= h($metaDescription) ?>" />
    <meta name="twitter:image" content="<?= h($metaImageUrl) ?>" />
    <meta name="robots" content="<?= h($pageRobots ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1') ?>" />
    <?php if (isset($pageRobots) && strpos($pageRobots, 'noindex') !== false): ?>
    
    <meta name="googlebot" content="noindex,follow" />
    <meta name="bingbot" content="noindex,follow" />

    <?php else: ?>
    
    <meta name="googlebot" content="index,follow" />
    <meta name="bingbot" content="index,follow" />

    <?php endif; ?>
    
    <meta charset="utf-8" />
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
    <meta name="viewport" id="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    
    <script>
        (function() {
            var vp = document.getElementById('viewport');
            if (vp) {
                var sw = window.screen.width;
                if (sw >= 768 && sw < 1240) {
                    // Force desktop layout for tablets and small laptops
                    vp.setAttribute('content', 'width=1240');
                } else {
                    vp.setAttribute('content', 'width=device-width, initial-scale=1, viewport-fit=cover');
                }
            }
        })();
    </script>
    
    <!--- /./ -->
    <meta name="theme-color" content="<?= ($site_theme_color ?: '#3b82f6') ?>">
    <meta name="format-detection" content="telephone=no">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <meta name="csrf-token" content="<?= h($csrfToken) ?>">
    <script>
        // Global Sidebar Toggle Functions
        window.toggleSidebar = function() {
            const sidebar = document.querySelector('.fb-sidebar-left');
            const overlay = document.querySelector('.sidebar-overlay');
            const body = document.body;
            if (!sidebar || !overlay) return;

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');

            if (window.innerWidth <= 768) {
                body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
        };

        window.closeLeftSidebar = function() {
            const sidebar = document.querySelector('.fb-sidebar-left');
            const overlay = document.querySelector('.sidebar-overlay');
            const body = document.body;
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
            body.style.overflow = '';
        };

        window.openRightSidebar = function() {
            const sidebar = document.querySelector('.fb-sidebar-right');
            const overlay = document.querySelector('.sidebar-overlay-right');
            if (!sidebar || !overlay) return;
            sidebar.classList.add('open');
            overlay.classList.add('active');
        };

        window.closeRightSidebar = function() {
            const sidebar = document.querySelector('.fb-sidebar-right');
            const overlay = document.querySelector('.sidebar-overlay-right');
            if (!sidebar || !overlay) return;
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        };

        window.toggleRightSidebar = function() {
            const sidebar = document.querySelector('.fb-sidebar-right');
            if (!sidebar) return;
            if (sidebar.classList.contains('open')) {
                closeRightSidebar();
            } else {
                openRightSidebar();
            }
        };

        window.closeSearchCart = function() {
            const searchCart = document.querySelector('.fb-header-search-cart');
            if (searchCart) {
                searchCart.classList.remove('is-open');
                searchCart.classList.add('is-hidden');
            }
        };

        // Global Event Listeners for Sidebars
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.fb-sidebar-left .nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (link.classList.contains('nav-toggle-label')) return;
                    if (window.innerWidth <= 768 || document.body.classList.contains('hide-left')) {
                        toggleSidebar();
                    }
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeLeftSidebar();
                    closeRightSidebar();
                    closeSearchCart();
                }
            });

            // Tự động tắt sidebar/dropdown khi bấm nút bất kỳ hoặc thao tác các icon button trong head
            document.addEventListener('click', function(e) {
                const target = e.target;
                if (!target) return;

                const isInteractiveClick = target.closest('button') || target.closest('a') || target.closest('.btn') || target.closest('[role="button"]');

                if (isInteractiveClick) {
                    const isLeftToggle = target.closest('.mobile-menu-toggle');
                    const isRightToggle = target.closest('.sidebar-notify-toggle');
                    const isSearchToggle = target.closest('#headerSearchToggle') || target.closest('.fb-header-search-cart');
                    const isProfileToggle = target.closest('.fb-header-profile');

                    if (!isLeftToggle) {
                        closeLeftSidebar();
                    }
                    if (!isRightToggle) {
                        closeRightSidebar();
                    }
                    if (!isSearchToggle) {
                        closeSearchCart();
                    }
                    if (!isProfileToggle) {
                        const openDropdowns = document.querySelectorAll('.fb-header-profile .dropdown-menu.show');
                        openDropdowns.forEach(menu => {
                            menu.classList.remove('show');
                            const toggleBtn = menu.parentElement.querySelector('[data-bs-toggle="dropdown"]');
                            if (toggleBtn) {
                                toggleBtn.classList.remove('show');
                                toggleBtn.setAttribute('aria-expanded', 'false');
                            }
                        });
                    }
                } else {
                    if (!target.closest('.fb-sidebar-left') && !target.closest('.mobile-menu-toggle') && !target.closest('.sidebar-overlay')) {
                        closeLeftSidebar();
                    }
                    if (!target.closest('.fb-sidebar-right') && !target.closest('.sidebar-notify-toggle') && !target.closest('.sidebar-overlay-right')) {
                        closeRightSidebar();
                    }
                }
            });

            // Hiệu ứng Accordion: Khi mở 1 submenu, đóng các submenu khác ở cùng cấp
            document.addEventListener('click', function(e) {
                const label = e.target.closest('.nav-toggle-label');
                if (!label) return;
                const forId = label.getAttribute('for');
                if (!forId) return;
                const checkbox = document.getElementById(forId);
                if (!checkbox) return;

                // Nếu đang đóng (sắp được mở)
                if (!checkbox.checked) {
                    const parentLi = label.closest('li');
                    if (parentLi) {
                        const siblingLis = Array.from(parentLi.parentElement.children).filter(el => el !== parentLi && el.tagName === 'LI');
                        siblingLis.forEach(sibling => {
                            sibling.querySelectorAll('input.nav-toggle:checked').forEach(cb => {
                                cb.checked = false;
                            });
                        });
                    }
                }
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    closeLeftSidebar();
                }
            });
        });
    </script>
    <link href="<?= h($baseUrl) ?>/assets/css/montserrat.css" rel="stylesheet">
    <link href="<?= h($baseUrl) ?>/assets/bootstrap/bootstrap.min.css?v=5.3.0" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Places Autocomplete dropdown phải nổi trên Bootstrap modal -->
    <style>.pac-container{z-index:20000!important;}</style>
    <link rel="stylesheet" href="<?= h($baseUrl) ?>/style.css?v=<?= time() ?>"> <!--?v=<?= time() ?>-->
    <link rel="stylesheet" href="<?= h($baseUrl) ?>/assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= h($baseUrl) ?>/assets/css/toastr.min.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= h($baseUrl) ?>/assets/css/toastr-theme.css?v=<?= @filemtime(__DIR__ . '/assets/css/toastr-theme.css') ?: time() ?>">
    <script src="<?= h($baseUrl) ?>/assets/js/jquery-3.7.1.min.js?v=3.7.1"></script>
    <script src="<?= h($baseUrl) ?>/assets/bootstrap/bootstrap.bundle.min.js?v=5.3.0"></script>
    <script src="<?= h($baseUrl) ?>/assets/js/toastr.min.js?v=2.1.4"></script>
    <style>
        /* Keep layout stable when Google Translate is enabled (pm_lang=en) */
        .goog-te-banner-frame,
        .goog-te-banner-frame.skiptranslate,
        #goog-gt-tt,
        .goog-tooltip,
        .goog-tooltip:hover,
        .goog-te-balloon-frame,
        .goog-te-gadget-icon {
            display: none !important;
        }

        body {
            top: 0 !important;
        }

        .skiptranslate {
            display: none !important;
        }

        @media (max-width: 1600px) {
            .fb-sidebar-left {
                display: none;
            }
        }

        @media (max-width: 1800px) {
            .fb-sidebar-left {
                display: none;
            }
        }
    </style>

    <script>
        // Helper build slug from product name (client-side, Unicode-safe)
        (function() {
            function pmSlugify(str) {
                var s = String(str || '').toLowerCase().trim();
                if (!s) return 'product';
                // Handle Vietnamese 'đ' specifically as NFD doesn't split it
                s = s.replace(/đ/g, 'd');
                if (s.normalize) {
                    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                return s || 'product';
            }

            function pmBuildProductUrl(id, name, extraParams) {
                var base = '<?= h($baseUrl) ?>'.replace(/\/$/, '');
                var pid = parseInt(id || 0, 10);
                var url = base || '';
                if (pid > 0) {
                    var slug = pmSlugify(name || '');
                    url += '/product/' + slug + '-' + pid;
                } else {
                    url += '/shopping';
                }
                if (extraParams && typeof extraParams === 'object') {
                    var usp = new URLSearchParams();
                    Object.keys(extraParams).forEach(function(k) {
                        var v = extraParams[k];
                        if (v !== undefined && v !== null && String(v) !== '') {
                            usp.append(k, String(v));
                        }
                    });
                    var qs = usp.toString();
                    if (qs) {
                        url += (url.indexOf('?') === -1 ? '?' : '&') + qs;
                    }
                }
                return url;
            }
            window.pmSlugify = pmSlugify;
            window.pmBuildProductUrl = pmBuildProductUrl;
        })();

        // ====== MEDIA DOMAIN (JS) ======
        // Mirror logic của PHP to_abs_url(): file trong thư mục upload sẽ phục vụ
        // từ media domain; asset tĩnh/URL tuyệt đối/data: giữ nguyên.
        window.MEDIA_BASE_URL = <?= json_encode((string)($mediaBaseUrl ?? ''), JSON_UNESCAPED_SLASHES) ?>;
        window.MEDIA_KEEP_PREFIX = <?= !empty($mediaKeepUploadsPrefix) ? 'true' : 'false' ?>;
        window.MEDIA_UPLOAD_FOLDER = <?= json_encode(trim((string)($uploadFolder ?? 'uploads'), '/'), JSON_UNESCAPED_SLASHES) ?>;
        window.BASE_URL = <?= json_encode(rtrim((string)($baseUrl ?? ''), '/'), JSON_UNESCAPED_SLASHES) ?>;
        window.toMediaUrl = function(url) {
            var raw = String(url == null ? '' : url).trim();
            if (!raw) return '';
            if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
            var rel = raw.replace(/\\/g, '/').replace(/^\/+/, '');
            var folder = (window.MEDIA_UPLOAD_FOLDER || 'uploads');
            var mediaBase = (window.MEDIA_BASE_URL || '').replace(/\/+$/, '');
            var isMedia = (rel === folder || rel.indexOf(folder + '/') === 0);
            if (mediaBase && isMedia) {
                if (window.MEDIA_KEEP_PREFIX) return mediaBase + '/' + rel; // .../uploads/<path>
                return mediaBase + '/' + rel.substring(folder.length).replace(/^\/+/, ''); // .../<path>
            }
            var base = (window.BASE_URL || '').replace(/\/+$/, '');
            return base ? base + '/' + rel : ('/' + rel);
        };

        // CSRF Setup for jQuery
        $.ajaxSetup({
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>

    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-5TRRBS2F');
    </script>
    <!-- End Google Tag Manager -->

    <?php
    // ===== Sitewide JSON-LD: Organization + WebSite =====
    if (file_exists(__DIR__ . '/core/seo_jsonld.php')) {
        require_once __DIR__ . '/core/seo_jsonld.php';
        $_siteUrlAbs = rtrim((string)$_SiteUrl, '/');
        $_logoAbs = $_SiteLogo;
        $_sameAs = [];
        foreach ([$site_facebook ?? '', $site_youtube ?? '', $site_zalo ?? '', $site_instagram ?? ''] as $_s) {
            $_s = trim((string)$_s);
            if ($_s !== '' && preg_match('#^https?://#i', $_s)) $_sameAs[] = $_s;
        }
        echo seo_jsonld_organization([
            'name'   => (string)$_SiteTitle,
            'url'    => $_siteUrlAbs,
            'logo'   => $_logoAbs,
            'sameAs' => $_sameAs,
            'phone'  => trim((string)($site_phone ?? '')),
        ]);
        echo seo_jsonld_website((string)$_SiteTitle, $_siteUrlAbs);
    }
    ?>


</head>

<body class="hide-right">

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5TRRBS2F" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <!-- /./  -->
    <?php if (!isset($no_page_loader) || !$no_page_loader): ?>
        <div id="pageLoader" class="page-loader" aria-hidden="true">
            <div class="loader-shell" role="status" aria-label="Đang tải trang">
                <div class="loader-logo">
                    <div class="loader-orbit"></div>
                    <div class="loader-logo-inner">
                        <img src="<?= h($baseUrl) ?>/image/shopping-icon.svg" alt="Logo" height="135" width="135" loading="lazy" decoding="async" />
                    </div>
                </div>
                <div class="loader-progress-track">
                    <div class="loader-progress-bar"></div>
                </div>
                <div class="loader-text text-primary fw-bold">Sơn Mỹ Nhà Việt</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Thẻ title, SEO Meta tags sẽ được config từ config.php và nạp sau -->
    <div class="sidebar-overlay d-lg-none" onclick="toggleSidebar()"></div>
    <div class="sidebar-overlay-right d-block d-xl-none" onclick="closeRightSidebar()" aria-label="Close sidebar"></div>
    <?php require_once __DIR__ . '/sidebar_left.php'; ?>
    <?php require_once __DIR__ . '/sidebar_right.php'; ?>
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- /./ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function applyOrderTableStyles() {
                document.querySelectorAll('.fb-main table.table').forEach(function(table) {
                    if (table.closest('.modal') || table.closest('.offcanvas') || table.closest('.dropdown-menu')) return;

                    var wrapper = table.closest('.dataTables_wrapper');
                    if (!wrapper) return;

                    table.classList.add('order-table');
                    wrapper.classList.add('order-table-card', 'table_wrapper');
                });
            }

            applyOrderTableStyles();

            var observer = new MutationObserver(function() {
                applyOrderTableStyles();
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>
    <!-- /./ -->
    <script>
        (function() {
            const MODAL_BASE_Z = 1080;
            const BACKDROP_BASE_Z = 1070;

            function getShownModals() {
                return Array.from(document.querySelectorAll('.modal.show'));
            }

            function getBackdrops() {
                return Array.from(document.querySelectorAll('.modal-backdrop'));
            }

            function moveModalToBody(modalEl) {
                if (!modalEl || modalEl.parentElement === document.body) return;
                document.body.appendChild(modalEl);
            }

            function cleanupOrphanBackdrops() {
                const shownCount = getShownModals().length;
                const backdrops = getBackdrops();
                if (backdrops.length <= shownCount) return;
                for (let i = 0; i < backdrops.length - shownCount; i++) {
                    backdrops[i].remove();
                }
            }

            function restackModalLayers() {
                const shownModals = getShownModals();
                const backdrops = getBackdrops();

                shownModals.forEach(function(modalEl, idx) {
                    modalEl.style.zIndex = String(MODAL_BASE_Z + idx * 2);
                });

                backdrops.forEach(function(backdropEl, idx) {
                    backdropEl.style.zIndex = String(BACKDROP_BASE_Z + idx * 2);
                    backdropEl.classList.add('show');
                });
            }

            function ensureBackdropForOpenModal() {
                const shownModals = getShownModals();
                if (!shownModals.length) return;
                if (getBackdrops().length > 0) return;

                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.setAttribute('data-modal-guard', '1');
                document.body.appendChild(backdrop);
                document.body.classList.add('modal-open');
            }

            function syncModalDomState() {
                cleanupOrphanBackdrops();
                const shownModals = getShownModals();

                if (!shownModals.length) {
                    getBackdrops().forEach(function(el) {
                        el.remove();
                    });
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                    return;
                }

                ensureBackdropForOpenModal();
                restackModalLayers();
            }

            document.addEventListener('show.bs.modal', function(event) {
                moveModalToBody(event.target);
            });

            document.addEventListener('shown.bs.modal', function() {
                syncModalDomState();
            });

            document.addEventListener('hidden.bs.modal', function() {
                setTimeout(syncModalDomState, 30);
            });

            window.addEventListener('pageshow', function() {
                setTimeout(syncModalDomState, 0);
            });

            document.addEventListener('DOMContentLoaded', function() {
                syncModalDomState();
            });
        })();
    </script>
    <!-- /./ -->
    <script>
        /*Ẩn pageLoader chỉ sau khi trang (bao gồm asset) đã load xong*/
        window.addEventListener('load', function() {
            var loader = document.getElementById('pageLoader');
            if (!loader) return;
            // Nhỏ delay để tránh giật khi load rất nhanh
            //setTimeout(function(){
            loader.classList.add('is-hidden');
            loader.setAttribute('aria-hidden', 'true');
            //}, 100);
        });
    </script>