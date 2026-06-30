<?php
require_once __DIR__ . '/../../config.php';

// Lọc theo slug chuyên mục (nếu có)
$categorySlug = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$categoryInfo = null;

if ($categorySlug !== '') {
    $stmtCat = $ithanhloc->prepare('SELECT id, name, slug FROM ecommerce_blog_category WHERE slug = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1');
    if ($stmtCat) {
        $stmtCat->bind_param('s', $categorySlug);
        $stmtCat->execute();
        $resCat = $stmtCat->get_result();
        $categoryInfo = $resCat ? $resCat->fetch_assoc() : null;
        if ($resCat) {
            $resCat->close();
        }
        $stmtCat->close();
    }
}

// Lấy danh sách tất cả chuyên mục để hiển thị tabs
$blogCategories = [];
$catListRes = $ithanhloc->query("SELECT id, name, slug FROM ecommerce_blog_category WHERE (is_active = 1 OR is_active IS NULL) ORDER BY name ASC");
if ($catListRes) {
    while ($catRow = $catListRes->fetch_assoc()) {
        $blogCategories[] = $catRow;
    }
    $catListRes->close();
}

// Lấy danh sách bài viết
$blogPosts = [];
if ($categoryInfo) {
    $sql = 'SELECT b.id, b.title, b.slug, b.excerpt, b.thumbnail_url, b.author_name, b.published_at,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug
            FROM ecommerce_blog b
            LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id
            WHERE b.is_active = 1 AND b.category_id = ?
            ORDER BY b.published_at DESC, b.id DESC';
    $stmt = $ithanhloc->prepare($sql);
    if ($stmt) {
        $catId = (int)($categoryInfo['id'] ?? 0);
        $stmt->bind_param('i', $catId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $blogPosts[] = $row;
            }
            $res->close();
        }
        $stmt->close();
    }
} else {
    $sql = 'SELECT b.id, b.title, b.slug, b.excerpt, b.thumbnail_url, b.author_name, b.published_at,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug
            FROM ecommerce_blog b
            LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id
            WHERE b.is_active = 1
            ORDER BY b.published_at DESC, b.id DESC';
    $res = $ithanhloc->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $blogPosts[] = $row;
        }
        $res->close();
    }
}

// Hàm chuẩn hóa URL ảnh (nếu có) và định dạng ngày tháng
function blog_list_normalize_image(?string $src, string $baseUrl): string {
    $raw = trim((string)$src);
    if ($raw === '') {
        return '';
    }
    if (function_exists('to_abs_url')) {
        return to_abs_url($raw, $baseUrl);
    }
    if (preg_match('~^https?://~i', $raw) || strpos($raw, 'data:image/') === 0) {
        return $raw;
    }
    return $baseUrl . '/' . ltrim($raw, '/\\');
}

// Hàm định dạng ngày tháng (nếu cần)
function blog_format_date(?string $dateStr): string {
    if (!$dateStr) {
        return '';
    }
    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        return '';
    }
    return date('d/m/Y', $timestamp);
}

// Tách bài viết hero (bài đầu) và các bài còn lại
$heroPost       = !empty($blogPosts) ? $blogPosts[0] : null;
$sidePosts      = array_slice($blogPosts, 1, 4); // 4 bài bên cạnh hero
$remainingPosts = array_slice($blogPosts, 5); // Các bài còn lại
?>

<style>
    /* Category Filters */
    .category-nav {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        padding: 0 0 10px;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        width: 100%;
    }
    .category-nav::-webkit-scrollbar {
        display: none;
    }

    .btn-category {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        white-space: nowrap;
        text-decoration: none;
    }

    .btn-category:hover,
    .btn-category.active {
        background: var(--theme-primary);
        border-color: var(--theme-primary);
        color: #fff;
    }

    /* News Cards */
    .news-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
        overflow: hidden;
    }

    .news-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }

    .img-wrapper {
        position: relative;
        overflow: hidden;
        background: #f1f5f9;
    }

    .img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .badge-category {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--theme-primary);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        z-index: 5;
        text-transform: uppercase;
    }

    /* Hero */
    .hero-article {
        height: 320px;
    }
    .hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, transparent 100%);
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }
    .hero-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
        line-height: 1.3;
    }

    /* Typography */
    .news-title {
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.4;
        color: #1e293b;
        text-decoration: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .news-card:hover .news-title {
        color: var(--theme-primary);
    }

    .news-meta {
        font-size: 0.75rem;
        color: #64748b;
    }

    /* Horizontal Card */
    .horizontal-card {
        display: flex;
        gap: 15px;
        align-items: center;
    }
    .horizontal-card .img-wrapper {
        width: 100px;
        height: 70px;
        flex-shrink: 0;
        border-radius: 8px;
    }
    .horizontal-card .news-title {
        -webkit-line-clamp: 2;
        font-size: 0.9rem;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .hero-article,
        .hero-article .img-wrapper {
            height: 400px;
            min-height: 400px;
        }
        .hero-article .news-title {
            font-size: 1.6rem;
        }
        .blog-main-title {
            font-size: 1.8rem;
        }
    }

    @media (max-width: 767px) {
        .hero-article,
        .hero-article .img-wrapper {
            height: 300px;
            min-height: 300px;
        }
        .hero-overlay {
            padding: 20px;
        }
        .hero-article .news-title {
            font-size: 1.3rem;
        }
        .news-title {
            font-size: 1.1rem;
        }
    }
</style>

<div class="blog-container py-0">
    <h1 class="visually-hidden">Tin tức, Cẩm nang & Hướng dẫn sơn nhà | Paint&More</h1>
    <!-- Compact Search & Categories -->
    <div class="row g-3 mb-4 align-items-center">
        <div class="col-12 col-md-5 col-lg-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 ps-1" id="blogSearchInput" placeholder="Tìm tin tức...">
            </div>
        </div>
        <div class="col-12 col-md-7 col-lg-8">
            <?php if (!empty($blogCategories)): ?>
                <div class="category-nav" id="categoryNav">
                    <a href="<?= h($baseUrl . '/blog') ?>" class="btn-category<?= $categorySlug === '' ? ' active' : '' ?>" data-slug="">Tất cả</a>
                    <?php foreach ($blogCategories as $cat):
                        $cSlug = trim((string)($cat['slug'] ?? ''));
                        if ($cSlug === '') continue;
                        $cName = (string)($cat['name'] ?? 'Chuyên mục');
                        $isActive = ($cSlug === $categorySlug);
                    ?>
                        <a href="<?= h($baseUrl . '/blog/category/' . urlencode($cSlug)) ?>" class="btn-category<?= $isActive ? ' active' : '' ?>" data-slug="<?= h($cSlug) ?>"><?= h($cName) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Content Area -->
    <div id="blogContentArea">
        <?php if (empty($blogPosts)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-journal-x" style="font-size: 3rem; color: #cbd5e1;"></i>
                </div>
                <p class="text-secondary">Chưa có bài viết nào được tìm thấy.</p>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-4">
                <!-- Hero Post -->
                <?php if ($heroPost): 
                    $heroUrl = $baseUrl . '/blog/' . urlencode((string)($heroPost['slug'] ?? ''));
                    $heroThumb = blog_list_normalize_image($heroPost['thumbnail_url'] ?? '', $baseUrl);
                ?>
                <div class="col-lg-7">
                    <article class="news-card hero-article position-relative">
                        <a href="<?= h($heroUrl) ?>" class="img-wrapper d-block h-100">
                            <?php if ($heroThumb !== ''): ?>
                                <img src="<?= h($heroThumb) ?>" alt="<?= h($heroPost['title'] ?? '') ?>" loading="lazy">
                            <?php endif; ?>
                            <div class="hero-overlay">
                                <?php if (!empty($heroPost['category_name'])): ?>
                                    <span class="badge-category mb-2 d-inline-block"><?= h($heroPost['category_name']) ?></span>
                                <?php endif; ?>
                                <h3 class="hero-title"><?= h($heroPost['title'] ?? '') ?></h3>
                            </div>
                        </a>
                    </article>
                </div>
                <?php endif; ?>

                <!-- Side Posts: Horizontal -->
                <div class="col-lg-5 d-flex flex-column gap-3">
                    <?php foreach ($sidePosts as $sidePost): 
                        $sideUrl = $baseUrl . '/blog/' . urlencode((string)($sidePost['slug'] ?? ''));
                        $sideThumb = blog_list_normalize_image($sidePost['thumbnail_url'] ?? '', $baseUrl);
                    ?>
                    <div class="horizontal-card">
                        <a href="<?= h($sideUrl) ?>" class="img-wrapper shadow-sm">
                            <?php if ($sideThumb !== ''): ?>
                                <img src="<?= h($sideThumb) ?>" alt="<?= h($sidePost['title'] ?? '') ?>" loading="lazy">
                            <?php endif; ?>
                        </a>
                        <div class="flex-grow-1">
                            <a href="<?= h($sideUrl) ?>" class="news-title mb-1"><?= h($sidePost['title'] ?? '') ?></a>
                            <div class="news-meta">
                                <span><?= blog_format_date($sidePost['published_at'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-2 border-top">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Bài viết khác</h5>
                </div>
                
                <div class="row g-3" id="remainingPostsGrid">
                    <?php foreach ($remainingPosts as $post): 
                        $postUrl = $baseUrl . '/blog/' . urlencode((string)($post['slug'] ?? ''));
                        $postThumb = blog_list_normalize_image($post['thumbnail_url'] ?? '', $baseUrl);
                    ?>
                    <div class="col-sm-6 col-lg-3">
                        <article class="news-card">
                            <a href="<?= h($postUrl) ?>" class="img-wrapper d-block" style="height: 140px;">
                                <?php if (!empty($post['category_name'])): ?>
                                    <span class="badge-category"><?= h($post['category_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($postThumb !== ''): ?>
                                    <img src="<?= h($postThumb) ?>" alt="<?= h($post['title'] ?? '') ?>" loading="lazy">
                                <?php endif; ?>
                            </a>
                            <div class="p-2 d-flex flex-column flex-grow-1">
                                <a href="<?= h($postUrl) ?>" class="news-title mb-1"><?= h($post['title'] ?? '') ?></a>
                                <div class="news-meta mt-auto d-flex justify-content-between opacity-75">
                                    <span><?= blog_format_date($post['published_at'] ?? '') ?></span>
                                    <span><i class="bi bi-chevron-right"></i></span>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var BLOG_API = '<?= h($baseUrl) ?>/core/blog/ajax.php';
    var BLOG_BASE = '<?= h($baseUrl) ?>';
    var currentSlug = '<?= h($categorySlug) ?>';
    var searchTimeout = null;

    var contentEl = document.getElementById('blogContentArea');
    var searchInput = document.getElementById('blogSearchInput');
    var categoryNav = document.getElementById('categoryNav');

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showSkeleton() {
        if (!contentEl) return;
        var html = '<div class="row g-3 mb-4">';
        html += '<div class="col-lg-7"><div class="news-card hero-article skeleton-line" style="height:320px;border-radius:12px;"></div></div>';
        html += '<div class="col-lg-5 d-flex flex-column gap-3">';
        for (var i = 0; i < 2; i++) {
            html += '<div class="horizontal-card"><div class="skeleton-line" style="width:100px;height:70px;border-radius:8px;flex-shrink:0;"></div>';
            html += '<div class="flex-grow-1"><div class="skeleton-line w-75 mb-2" style="height:16px;"></div><div class="skeleton-line w-25" style="height:12px;"></div></div></div>';
        }
        html += '</div></div>';
        html += '<div class="pt-2 border-top"><div class="row g-3">';
        for (var i = 0; i < 4; i++) {
            html += '<div class="col-sm-6 col-lg-3"><div class="news-card"><div class="skeleton-line" style="height:140px;border-radius:8px 8px 0 0;"></div>';
            html += '<div class="p-2"><div class="skeleton-line w-100 mb-2" style="height:14px;"></div><div class="skeleton-line w-50" style="height:12px;"></div></div></div></div>';
        }
        html += '</div></div>';
        contentEl.innerHTML = html;
    }

    function loadBlog(slug, search, pushState) {
        showSkeleton();
        slug = slug || '';
        search = search || '';
        
        var params = [];
        params.push('category=' + encodeURIComponent(slug));
        if (search) {
            params.push('q=' + encodeURIComponent(search));
        }
        
        var url = BLOG_API + '?' + params.join('&');
        fetch(url, { credentials: 'same-origin' })
            .then(function(res){ return res.json(); })
            .then(function(json){
                if (!json || !json.ok) {
                    contentEl.innerHTML = '<div class="text-center py-5"><p class="text-secondary">' + escHtml((json && json.msg) || 'Không tải được dữ liệu') + '</p></div>';
                    return;
                }
                renderBlogContent(json);
                currentSlug = slug;
                
                if (pushState) {
                    var baseUrl = slug ? (BLOG_BASE + '/blog/category/' + encodeURIComponent(slug)) : (BLOG_BASE + '/blog');
                    var newUrl = baseUrl;
                    if (search) {
                        newUrl += (newUrl.indexOf('?') === -1 ? '?' : '&') + 'search=' + encodeURIComponent(search);
                    }
                    window.history.pushState({ slug: slug, search: search }, '', newUrl);
                }
            })
            .catch(function(){
                contentEl.innerHTML = '<div class="text-center py-5"><p class="text-secondary">Lỗi kết nối máy chủ.</p></div>';
            });
    }

    function renderBlogContent(data) {
        if (categoryNav && Array.isArray(data.categories)) {
            var slug = data.categorySlug || '';
            var tabsHtml = '<a href="' + escHtml(BLOG_BASE + '/blog') + '" class="btn-category' + (slug === '' ? ' active' : '') + '" data-slug="">Tất cả</a>';
            data.categories.forEach(function(cat){
                var cSlug = String(cat.slug || '');
                if (!cSlug) return;
                var cName = cat.name || 'Chuyên mục';
                var cUrl = BLOG_BASE + '/blog/category/' + encodeURIComponent(cSlug);
                var cls = 'btn-category';
                if (cSlug === slug) cls += ' active';
                tabsHtml += '<a href="' + escHtml(cUrl) + '" class="' + cls + '" data-slug="' + escHtml(cSlug) + '">' + escHtml(cName) + '</a>';
            });
            categoryNav.innerHTML = tabsHtml;
        }

        var posts = [];
        var groups = data.groups || {};
        Object.keys(groups).forEach(function(key){
            var g = groups[key] || {};
            (g.posts || []).forEach(function(p){ posts.push(p); });
        });

        if (posts.length === 0) {
            contentEl.innerHTML = '<div class="text-center py-5"><div class="mb-3"><i class="bi bi-journal-x" style="font-size: 3rem; color: #cbd5e1;"></i></div><p class="text-secondary">Chưa có bài viết nào được tìm thấy.</p></div>';
            return;
        }

        var html = '';
        var heroPost = posts[0] || null;
        var sidePosts = posts.slice(1, 3);
        var remainingPosts = posts.slice(3);

        html += '<div class="row g-3 mb-4">';
        
        if (heroPost) {
            var heroUrl = BLOG_BASE + '/blog/' + encodeURIComponent(heroPost.slug);
            html += '<div class="col-lg-7">';
            html += '<article class="news-card hero-article position-relative">';
            html += '<a href="' + escHtml(heroUrl) + '" class="img-wrapper d-block h-100">';
            if (heroPost.thumbnail_url) {
                html += '<img src="' + escHtml(heroPost.thumbnail_url) + '" alt="" loading="lazy">';
            }
            html += '<div class="hero-overlay">';
            if (heroPost.category_name) {
                html += '<span class="badge-category mb-2 d-inline-block">' + escHtml(heroPost.category_name) + '</span>';
            }
            html += '<h3 class="hero-title">' + escHtml(heroPost.title) + '</h3>';
            html += '</div></a></article></div>';
        }

        html += '<div class="col-lg-5 d-flex flex-column gap-3">';
        sidePosts.forEach(function(post){
            if (!post.slug) return;
            var postUrl = BLOG_BASE + '/blog/' + encodeURIComponent(post.slug);
            html += '<div class="horizontal-card">';
            html += '<a href="' + escHtml(postUrl) + '" class="img-wrapper shadow-sm">';
            if (post.thumbnail_url) {
                html += '<img src="' + escHtml(post.thumbnail_url) + '" alt="" loading="lazy">';
            }
            html += '</a>';
            html += '<div class="flex-grow-1">';
            html += '<a href="' + escHtml(postUrl) + '" class="news-title mb-1">' + escHtml(post.title) + '</a>';
            html += '<div class="news-meta"><span>' + escHtml(post.published_at_fmt || '') + '</span></div>';
            html += '</div></div>';
        });
        html += '</div></div>';

        if (remainingPosts.length > 0) {
            html += '<div class="pt-2 border-top">';
            html += '<div class="d-flex align-items-center mb-3"><h5 class="fw-bold mb-0">Bài viết khác</h5></div>';
            html += '<div class="row g-3">';
            remainingPosts.forEach(function(post){
                if (!post.slug) return;
                var postUrl = BLOG_BASE + '/blog/' + encodeURIComponent(post.slug);
                html += '<div class="col-sm-6 col-lg-3">';
                html += '<article class="news-card">';
                html += '<a href="' + escHtml(postUrl) + '" class="img-wrapper d-block" style="height: 140px;">';
                if (post.category_name) {
                    html += '<span class="badge-category">' + escHtml(post.category_name) + '</span>';
                }
                if (post.thumbnail_url) {
                    html += '<img src="' + escHtml(post.thumbnail_url) + '" alt="" loading="lazy">';
                }
                html += '</a>';
                html += '<div class="p-2 d-flex flex-column flex-grow-1">';
                html += '<a href="' + escHtml(postUrl) + '" class="news-title mb-1">' + escHtml(post.title) + '</a>';
                html += '<div class="news-meta mt-auto d-flex justify-content-between opacity-75">';
                html += '<span>' + escHtml(post.published_at_fmt || '') + '</span>';
                html += '<span><i class="bi bi-chevron-right"></i></span>';
                html += '</div></div></article></div>';
            });
            html += '</div></div>';
        }

        contentEl.innerHTML = html;
    }

    // Category navigation
    document.addEventListener('click', function(e){
        var target = e.target.closest('.btn-category');
        if (!target) return;
        e.preventDefault();
        var slug = target.getAttribute('data-slug') || '';
        if (searchInput) searchInput.value = '';
        loadBlog(slug, '', true);
    });

    // Search
    if (searchInput) {
        searchInput.addEventListener('input', function(){
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function(){
                var q = (searchInput.value || '').trim();
                loadBlog(currentSlug, q, true);
            }, 500);
        });
    }

    // Popstate
    window.addEventListener('popstate', function(e){
        var state = e.state || {};
        var slug = typeof state.slug === 'string' ? state.slug : '';
        var search = typeof state.search === 'string' ? state.search : '';
        if (searchInput) searchInput.value = search;
        loadBlog(slug, search, false);
    });
});
</script>
