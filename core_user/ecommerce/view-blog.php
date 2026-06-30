<?php
$_blogSeoOnly = !empty($APP_SEO_ONLY);
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$_blogEmptyCard = '
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 16px;">
    <div style="text-align:center;max-width:480px;">
        <div style="width:96px;height:96px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 28px;border:2px solid #e2e8f0;">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>
                <line x1="9" y1="9" x2="10" y2="9"/>
            </svg>
        </div>
        <h3 style="font-size:1.25rem;font-weight:800;color:#1e293b;margin-bottom:10px;">Bài viết không tìm thấy</h3>
        <p style="font-size:0.95rem;color:#64748b;line-height:1.7;margin-bottom:28px;">Bài viết bạn đang tìm có thể đã bị ẩn, xóa hoặc đường dẫn không chính xác.</p>
        <a href="/blog" style="display:inline-flex;align-items:center;gap:8px;background:#0c4c29;color:#fff;text-decoration:none;padding:11px 24px;border-radius:10px;font-weight:700;font-size:0.9rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Xem tất cả bài viết
        </a>
    </div>
</div>';

if ($slug === '') {
    if (!$_blogSeoOnly) echo $_blogEmptyCard;
    return;
}

$stmt = $ithanhloc->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM ecommerce_blog b LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id WHERE b.slug = ? AND b.is_active = 1 LIMIT 1');
if (!$stmt) {
    if (!$_blogSeoOnly) echo $_blogEmptyCard;
    return;
}
$stmt->bind_param('s', $slug);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    if (!$_blogSeoOnly) echo $_blogEmptyCard;
    return;
}

$title = trim((string)($post['meta_title'] ?? $post['title'] ?? 'Bài viết'));
if ($title === '') {
    $title = (string)($post['title'] ?? 'Bài viết');
}
$excerpt = trim((string)($post['excerpt'] ?? ''));
$catName = trim((string)($post['category_name'] ?? ''));
$catSlug = trim((string)($post['category_slug'] ?? ''));
$author = trim((string)($post['author_name'] ?? ''));
$publishedAtRaw = (string)($post['published_at'] ?? '');
$publishedAt = $publishedAtRaw !== '' ? date('H:i d-m-Y', strtotime($publishedAtRaw)) : '';
$tags = trim((string)($post['tags'] ?? ''));
$thumb = trim((string)($post['thumbnail_url'] ?? ''));

// SEO cho trang chi tiết blog
$siteTitleRaw = isset($site_title) && $site_title !== '' ? (string)$site_title : 'Paintmore';
$metaTitleRaw = trim((string)($post['meta_title'] ?? ''));
$seoTitle = $metaTitleRaw !== '' ? $metaTitleRaw : trim((string)($post['title'] ?? ''));
if ($seoTitle !== '') {
    $pageTitle = $seoTitle . ' | ' . $siteTitleRaw;
}

if (!isset($pageDescription) || $pageDescription === null) {
    $bExcerpt = $excerpt;
    $bContent = trim((string)($post['content'] ?? ''));
    $sourceDesc = $bExcerpt !== '' ? $bExcerpt : $bContent;
    if ($sourceDesc !== '') {
        $plain = strip_tags($sourceDesc);
        $plain = preg_replace('/\s+/', ' ', $plain);
        // Giải mã entity HTML về Unicode để tránh hiện &ocirc;, &agrave;... trong meta
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('mb_substr')) {
            $plain = mb_substr($plain, 0, 220, 'UTF-8');
        } else {
            $plain = substr($plain, 0, 220);
        }
        $pageDescription = trim($plain);
    }
}

$thumbSeo = $thumb;
if ($thumbSeo !== '') {
    $base = rtrim((string)($baseUrl ?? ''), '/');
    $isAbsolute = (bool)preg_match('~^https?://~i', $thumbSeo);
    $isProtocolRelative = substr($thumbSeo, 0, 2) === '//';
    $isDataUri = stripos($thumbSeo, 'data:') === 0;
    if ($isAbsolute || $isProtocolRelative || $isDataUri) {
        $pageImageUrl = $thumbSeo;
    } else {
        // Ảnh media → route qua media domain (og:image cần URL tuyệt đối).
        $pageImageUrl = to_abs_url($thumbSeo, (string)$baseUrl);
    }
}

$slugFinal = trim((string)($post['slug'] ?? $slug));
if ($slugFinal !== '') {
    $base = rtrim((string)($baseUrl ?? ''), '/');
    $pageCanonicalUrl = ($base !== '' ? $base : '') . '/blog/' . rawurlencode($slugFinal);
}

$pageOgType = 'article';
// article:* meta
if ($publishedAtRaw !== '') {
    $_blogTs = strtotime($publishedAtRaw);
    if ($_blogTs) {
        $pageArticlePublished = date('c', $_blogTs);
    }
}
if (!empty($post['updated_at'])) {
    $_blogUpd = strtotime((string)$post['updated_at']);
    if ($_blogUpd) {
        $pageArticleModified = date('c', $_blogUpd);
    }
} elseif (isset($pageArticlePublished)) {
    $pageArticleModified = $pageArticlePublished;
}
if ($catName !== '') {
    $pageArticleSection = $catName;
}
if ($author !== '') {
    $pageAuthor = $author;
}
if ($tags !== '') {
    $pageArticleTags = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $tags))));
}

// Nếu chỉ chạy để set SEO (từ index.php) thì không render HTML
if (!empty($APP_SEO_ONLY)) {
    return;
}

// ===== JSON-LD: BlogPosting + Breadcrumb =====
if (file_exists(__DIR__ . '/../../core/seo_jsonld.php')) {
    require_once __DIR__ . '/../../core/seo_jsonld.php';
    $_blogLogo = isset($_SiteLogo) ? (string)$_SiteLogo : '';
    if ($_blogLogo === '' && !empty($site_logo)) {
        $_blogLogo = seo_abs_url((string)$site_logo, (string)($baseUrl ?? ''));
    }
    echo seo_jsonld_article([
        'type'           => 'BlogPosting',
        'headline'       => $seoTitle,
        'description'    => $pageDescription ?? '',
        'image'          => $pageImageUrl ? [$pageImageUrl] : [],
        'datePublished'  => $pageArticlePublished ?? '',
        'dateModified'   => $pageArticleModified ?? '',
        'articleSection' => $catName,
        'url'            => $pageCanonicalUrl ?? '',
        'author'         => $author,
        'inLanguage'     => 'vi',
        'keywords'       => $pageArticleTags ?? [],
        'publisher'      => [
            'name' => $siteTitleRaw,
            'logo' => $_blogLogo,
        ],
    ]);
    $_blogBcBase = rtrim((string)($baseUrl ?? ''), '/');
    $_blogBcItems = [
        ['name' => 'Trang chủ', 'url' => $_blogBcBase . '/'],
        ['name' => 'Tin tức',   'url' => $_blogBcBase . '/blog'],
    ];
    if ($catName !== '' && $catSlug !== '') {
        $_blogBcItems[] = ['name' => $catName, 'url' => $_blogBcBase . '/blog/category/' . rawurlencode($catSlug)];
    }
    $_blogBcItems[] = ['name' => $seoTitle, 'url' => $pageCanonicalUrl ?? ''];
    echo seo_jsonld_breadcrumb($_blogBcItems);
}

$backUrl = $baseUrl . '/blog';

$catUrl = '';
if ($catSlug !== '') {
    $baseTmp = rtrim((string)$baseUrl, '/');
    $catUrl = $baseTmp . '/blog/category/' . rawurlencode($catSlug);
}

$rel = [];
$stmtRel = $ithanhloc->prepare('SELECT id, title, slug, thumbnail_url, published_at FROM ecommerce_blog WHERE is_active = 1 AND slug <> ? ORDER BY published_at DESC, id DESC LIMIT 6');
if ($stmtRel) {
    $stmtRel->bind_param('s', $slug);
    $stmtRel->execute();
    $resRel = $stmtRel->get_result();
    if ($resRel) {
        $rel = $resRel->fetch_all(MYSQLI_ASSOC);
    }
    $stmtRel->close();
}

if (!function_exists('blog_detail_normalize_image')) {
    function blog_detail_normalize_image(?string $src): string {
        global $baseUrl;
        $raw = trim((string)$src);
        if ($raw === '') return '';
        if (preg_match('~^https?://~i', $raw) || strpos($raw, 'data:image/') === 0) return $raw;
        // Ảnh media → route qua media domain.
        return function_exists('to_abs_url') ? to_abs_url($raw, (string)($baseUrl ?? '')) : ('/' . ltrim($raw, '/\\'));
    }
}

$thumb = blog_detail_normalize_image($thumb);
foreach ($rel as &$r) {
    $r['thumbnail_url'] = blog_detail_normalize_image($r['thumbnail_url'] ?? '');
}
unset($r);
?>
<div class="container">
    <nav class="vn-breadcrumb" aria-label="breadcrumb">
        <a href="<?= h($baseUrl) ?>">Trang chủ</a>
        <span class="sep">›</span>
        <a href="<?= h($backUrl) ?>">Bài viết</a>
        <?php if ($catName !== '' && $catUrl !== ''): ?>
            <span class="sep">›</span>
            <a href="<?= h($catUrl) ?>"><?= h($catName) ?></a>
        <?php endif; ?>
        <span class="sep">›</span>
        <span class="current" title="<?= h($post['title'] ?? '') ?>"><?= h($post['title'] ?? '') ?></span>
    </nav>
</div>

<div class="container">
    <!-- Hero banner -->
    <div class="vn-hero">
        <?php if ($thumb !== ''): ?>
            <img src="<?= h($thumb) ?>" alt="<?= h($post['title'] ?? '') ?>" class="vn-hero-img" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="vn-hero-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
        <?php endif; ?>
        <div class="vn-hero-overlay"></div>
        <div class="vn-hero-body">
            <?php if ($catName !== ''): ?>
                <div class="vn-hero-badge"><?= h($catName) ?></div>
            <?php endif; ?>
            <h1 class="vn-hero-title"><?= h($post['title'] ?? '') ?></h1>
            <div class="vn-hero-meta">
                <?php if ($publishedAt !== ''): ?>
                    <span><i class="bi bi-clock"></i> <?= h($publishedAt) ?></span>
                <?php endif; ?>
                <?php if ($author !== ''): ?>
                    <span><i class="bi bi-person"></i> <?= h($author) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Article body -->
    <article class="vn-article">
        <?php
        // BUG-006 FIX: Chỉ hiển thị excerpt nếu nó không trùng với đoạn đầu của content.
        // So sánh plaintext để tránh bị lệch do HTML tags trong content.
        $contentPlain  = trim(preg_replace('/\s+/', ' ', strip_tags((string)($post['content'] ?? ''))));
        $excerptPlain  = trim(preg_replace('/\s+/', ' ', strip_tags($excerpt)));
        $showSubtitle  = $excerpt !== '' && (
            $excerptPlain === '' ||
            mb_stripos($contentPlain, $excerptPlain, 0, 'UTF-8') !== 0
        );
        ?>
        <?php if ($showSubtitle): ?>
            <p class="vn-subtitle"><?= h($excerpt) ?></p>
        <?php endif; ?>

        <div class="vn-content">
            <?= (string)($post['content'] ?? '') ?>

            <?php if ($tags !== ''):
                $parts = array_filter(array_map('trim', explode(',', $tags)));
                if ($parts): ?>
                    <div class="article-tags mt-3">
                        <div class="article-tags-label">Từ khóa:</div>
                        <?php foreach ($parts as $tag): ?>
                            <span class="badge bg-light text-secondary border"><?= h($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </article>
</div>

<?php if (!empty($rel)): $cards = array_slice($rel, 0, 12); ?>
<section class="vn-related">
    <div class="container">
        <div class="vn-related-head">
            <h2 class="vn-related-title">Bài viết khác</h2>
            <div class="vn-related-nav">
                <button id="relatedPrev" aria-label="Trước"><i class="bi bi-chevron-left"></i></button>
                <button id="relatedNext" aria-label="Tiếp"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
        <div class="post-grid" id="relatedPostList">
            <?php foreach ($cards as $r):
                $url = $baseUrl . '/blog/' . urlencode((string)($r['slug'] ?? ''));
                $tThumb = $r['thumbnail_url'] ?? '';
                $dateText = !empty($r['published_at']) ? date('d.m.Y', strtotime((string)$r['published_at'])) : '';
            ?>
            <div class="post-card">
                <a href="<?= h($url) ?>">
                    <?php if ($tThumb !== ''): ?>
                        <img src="<?= h($tThumb) ?>" alt="<?= h($r['title'] ?? '') ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <div class="post-card-body">
                        <h4 class="post-card-title"><?= h($r['title'] ?? '') ?></h4>
                        <?php if ($dateText): ?><div class="post-card-date"><?= h($dateText) ?></div><?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
(function(){
    var list = document.getElementById('relatedPostList');
    var prev = document.getElementById('relatedPrev');
    var next = document.getElementById('relatedNext');
    if (!list || !prev || !next) return;
    var step = function(){ return (list.querySelector('.post-card')?.offsetWidth || 240) + 16; };
    prev.onclick = function(){ list.scrollBy({left: -step(), behavior: 'smooth'}); };
    next.onclick = function(){ list.scrollBy({left: step(), behavior: 'smooth'}); };
})();
</script>
<?php endif; ?>
