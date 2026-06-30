<?php

/**
 * Trang danh sách "Chương trình ưu đãi" — liệt kê thông báo promotion
 * (user_notification, lọc theo type khuyến mãi). Bố cục tương tự trang Blog (hero + side + grid).
 *
 * Route: /notifications  (đăng ký trong main/main_content.php; catch-all .htaccess)
 * SEO meta (title/description/canonical) set trong index.php trước khi nạp head.
 */

if (!defined('APP_EMBED_LAYOUT')) {
    header('Location: ' . (isset($baseUrl) ? rtrim((string)$baseUrl, '/') : '') . '/');
    exit;
}

$nfBaseUrl = isset($baseUrl) ? rtrim((string)$baseUrl, '/') : '';
$nfSiteUrl = isset($_SiteUrl) ? rtrim((string)$_SiteUrl, '/') : $nfBaseUrl;

// ===== Helpers (fallback nếu chưa nạp từ home_user.php) =====
if (!function_exists('home_notice_excerpt')) {
    function home_notice_excerpt(string $body, int $limit = 90): string
    {
        $text = '';
        $decoded = json_decode(trim($body), true);
        if (is_array($decoded) && (($decoded['schema'] ?? '') === 'notx_v2')) {
            $text = trim((string)($decoded['subtitle'] ?? ''));
            if ($text === '') {
                $text = strip_tags((string)($decoded['content'] ?? ''));
            }
        } else {
            $text = strip_tags($body);
        }
        // Giải mã HTML entity (&sup2; &atilde; &amp;...) để hiển thị sạch
        $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim((string)$text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') > $limit) {
                return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '…';
            }
            return $text;
        }
        if (strlen($text) > $limit) {
            return rtrim(substr($text, 0, $limit)) . '…';
        }
        return $text;
    }
}

if (!function_exists('home_notice_image')) {
    function home_notice_image(string $body, string $baseUrl = ''): string
    {
        $decoded = json_decode(trim($body), true);
        if (!is_array($decoded) || (($decoded['schema'] ?? '') !== 'notx_v2')) {
            return '';
        }
        $template = strtolower(trim((string)($decoded['template'] ?? '')));
        $mainBanner = trim((string)($decoded['main_banner'] ?? ''));
        $thumbImage = trim((string)($decoded['thumb_image'] ?? ''));
        $banners = $decoded['banners'] ?? [];
        $bannerList = [];
        if (is_array($banners)) {
            foreach ($banners as $item) {
                $val = trim((string)$item);
                if ($val !== '') $bannerList[] = $val;
            }
        } elseif (is_string($banners)) {
            foreach (array_map('trim', explode(',', $banners)) as $val) {
                if ($val !== '') $bannerList[] = $val;
            }
        }
        $picked = '';
        if (in_array($template, ['tpl1', 'tpl4'], true)) {
            $picked = $mainBanner !== '' ? $mainBanner : ($bannerList[0] ?? '');
        } elseif (in_array($template, ['tpl2', 'tpl3'], true)) {
            $picked = $bannerList[0] ?? '';
        }
        if ($picked === '' && $thumbImage !== '') {
            $picked = $thumbImage;
        }
        if ($picked === '') return '';
        if (function_exists('to_abs_url')) {
            return to_abs_url($picked, $baseUrl);
        }
        if (preg_match('#^(https?:)?//#i', $picked)) return $picked;
        return rtrim((string)$baseUrl, '/') . '/' . ltrim($picked, '/');
    }
}

/** Loại nhãn hiển thị theo type của thông báo. */
if (!function_exists('nf_type_label')) {
    function nf_type_label(string $type): string
    {
        $map = [
            'promotion' => 'Khuyến mãi',
            'promo'     => 'Khuyến mãi',
            'event'     => 'Sự kiện',
            'news'      => 'Tin tức',
            'system'    => 'Hệ thống',
        ];
        $key = strtolower(trim($type));
        return $map[$key] ?? 'Ưu đãi';
    }
}

/** Build URL chi tiết thông báo (ưu tiên link trong DB, fallback nf_build_url). */
$nf_detail_url = function (array $notice) use ($nfBaseUrl): string {
    $linkRaw = trim((string)($notice['link'] ?? ''));
    if ($linkRaw !== '') {
        return preg_match('#^(https?:)?//#i', $linkRaw)
            ? $linkRaw
            : ($nfBaseUrl . '/' . ltrim($linkRaw, '/'));
    }
    $id = (int)($notice['id'] ?? 0);
    $title = trim((string)($notice['title'] ?? ''));
    return function_exists('nf_build_url')
        ? nf_build_url($id, $title, $nfBaseUrl)
        : ($nfBaseUrl . '/view-notification?id=' . $id);
};

// ===== Truy vấn thông báo promotion =====
$nfNotices = [];
$nfUserId = (int)($_SESSION['user_id'] ?? 0);

if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
    $nfActiveCond = "COALESCE(NULLIF(TRIM(CAST(n.is_active AS CHAR)),''),'1')='1'
        AND (n.send_at IS NULL OR TRIM(CAST(n.send_at AS CHAR))='' OR n.send_at <= NOW())";

    if ($nfUserId > 0) {
        $listSql = "SELECT DISTINCT n.id, n.title, n.body, n.type, n.link, n.created_at
                    FROM user_notification n
                    WHERE (n.user_id=0 OR n.user_id=?)
                      AND LOWER(TRIM(CAST(n.type AS CHAR))) IN ('promotion','promo','voucher','coupon')
                      AND {$nfActiveCond}
                    ORDER BY n.created_at DESC, n.id DESC
                    LIMIT 60";
        if ($ls = $ithanhloc->prepare($listSql)) {
            $ls->bind_param('i', $nfUserId);
            $ls->execute();
            $nfNotices = $ls->get_result()->fetch_all(MYSQLI_ASSOC);
            $ls->close();
        }
    } else {
        $listSql = "SELECT n.id, n.title, n.body, n.type, n.link, n.created_at
                    FROM user_notification n
                    WHERE n.user_id=0
                      AND LOWER(TRIM(CAST(n.type AS CHAR))) IN ('promotion','promo','voucher','coupon')
                      AND {$nfActiveCond}
                    ORDER BY n.created_at DESC, n.id DESC
                    LIMIT 60";
        if ($lr = $ithanhloc->query($listSql)) {
            $nfNotices = $lr->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Chuẩn hoá dữ liệu hiển thị 1 lần để tái dùng cho cả HTML lẫn JSON-LD
$nfItems = [];
foreach ($nfNotices as $row) {
    $body = (string)($row['body'] ?? '');
    $timeRaw = trim((string)($row['created_at'] ?? ''));
    $nfItems[] = [
        'id'      => (int)($row['id'] ?? 0),
        'title'   => trim((string)($row['title'] ?? 'Chương trình ưu đãi')) ?: 'Chương trình ưu đãi',
        'excerpt' => home_notice_excerpt($body, 110),
        'image'   => home_notice_image($body, $nfBaseUrl),
        'label'   => nf_type_label((string)($row['type'] ?? '')),
        'url'     => $nf_detail_url($row),
        'date'    => $timeRaw !== '' ? date('d/m/Y', strtotime($timeRaw)) : '',
        'iso'     => $timeRaw !== '' ? date('c', strtotime($timeRaw)) : '',
        'is_new'  => $timeRaw !== '' ? (strtotime($timeRaw) >= time() - 86400) : false,
    ];
}

$nfTotal = count($nfItems);

// ===== JSON-LD: Breadcrumb + ItemList (SEO) =====
if (!empty($nfItems) && file_exists(__DIR__ . '/../../core/seo_jsonld.php')) {
    require_once __DIR__ . '/../../core/seo_jsonld.php';
    if (function_exists('seo_jsonld_breadcrumb')) {
        echo seo_jsonld_breadcrumb([
            ['name' => 'Trang chủ', 'url' => $nfSiteUrl . '/'],
            ['name' => 'Chương trình ưu đãi', 'url' => $nfSiteUrl . '/notifications'],
        ]);
    }
    $absUrl = function (string $u) use ($nfSiteUrl): string {
        if ($u === '') return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        return function_exists('seo_abs_url') ? seo_abs_url($u, $nfSiteUrl) : ($nfSiteUrl . '/' . ltrim($u, '/'));
    };
    $ldElements = [];
    foreach (array_slice($nfItems, 0, 20) as $i => $it) {
        $ldElements[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'url'      => $absUrl((string)$it['url']),
            'name'     => (string)$it['title'],
        ];
    }
    $ld = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Chương trình ưu đãi & Khuyến mãi',
        'itemListElement' => $ldElements,
    ];
    echo '<script type="application/ld+json">'
        . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}
?>

<div class="nf-list-page">
    <h1 class="visually-hidden">Chương trình ưu đãi &amp; Khuyến mãi mới nhất | Paint&amp;More</h1>

    <!-- Breadcrumb + tiêu đề + tìm kiếm (đồng bộ phong cách trang Blog) -->
    <nav class="vn-breadcrumb mb-2" aria-label="breadcrumb">
        <a href="<?= h($nfBaseUrl) ?>">Trang chủ</a>
        <span class="sep">›</span>
        <span class="current">Chương trình ưu đãi</span>
    </nav>

    <div class="row g-3 mb-4 align-items-center">
        <div class="col-12 col-md-7 col-lg-8">
            <h2 class="nf-page-title mb-0">
                <i class="bi bi-gift"></i> Chương trình ưu đãi
                <span class="nf-count"><?= (int)$nfTotal ?></span>
            </h2>
        </div>
        <div class="col-12 col-md-5 col-lg-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 ps-1" id="nfSearchInput" placeholder="Tìm ưu đãi...">
            </div>
        </div>
    </div>

    <div id="nfContentArea">
        <?php if (empty($nfItems)): ?>
            <div class="text-center py-5">
                <div class="mb-3"><i class="bi bi-gift" style="font-size:3rem;color:#cbd5e1;"></i></div>
                <p class="text-secondary mb-0">Hiện chưa có chương trình ưu đãi nào.</p>
            </div>
        <?php else: ?>
            <!-- Lưới đồng nhất: mọi ưu đãi cùng 1 kiểu thẻ -->
            <div class="row g-3" id="nfGrid">
                <?php foreach ($nfItems as $it): ?>
                    <div class="col-6 col-md-4 col-lg-3 nf-item" data-title="<?= h(mb_strtolower($it['title'], 'UTF-8')) ?>">
                        <article class="news-card h-100 d-flex flex-column">
                            <a href="<?= h($it['url']) ?>" class="img-wrapper d-block">
                                <span class="badge-category"><?= h($it['label']) ?></span>
                                <?php if ($it['image'] !== ''): ?>
                                    <img src="<?= h($it['image']) ?>" alt="<?= h($it['title']) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="nf-noimg"><i class="bi bi-gift"></i></span>
                                <?php endif; ?>
                            </a>
                            <div class="p-2 d-flex flex-column flex-grow-1">
                                <a href="<?= h($it['url']) ?>" class="news-title mb-1"><?= h($it['title']) ?></a>
                                <?php if ($it['excerpt'] !== ''): ?>
                                    <p class="nf-excerpt mb-1"><?= h($it['excerpt']) ?></p>
                                <?php endif; ?>
                                <div class="news-meta mt-auto d-flex justify-content-between opacity-75">
                                    <?php if ($it['date'] !== ''): ?>
                                        <time datetime="<?= h($it['iso']) ?>"><i class="bi bi-clock"></i> <?= h($it['date']) ?></time>
                                    <?php else: ?><span></span><?php endif; ?>
                                    <span><i class="bi bi-chevron-right"></i></span>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Trạng thái rỗng khi lọc tìm kiếm -->
            <div id="nfEmptyState" class="text-center py-5 d-none">
                <div class="mb-3"><i class="bi bi-search" style="font-size:2.4rem;color:#cbd5e1;"></i></div>
                <p class="text-secondary mb-0">Không tìm thấy ưu đãi phù hợp.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* ===== Tái dùng phong cách thẻ giống trang Blog ===== */
    .nf-list-page .news-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        height: 100%;
        overflow: hidden;
    }
    .nf-list-page .news-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, .05);
        border-color: var(--theme-primary, #0c4c29);
    }
    /* Ảnh thẻ: tỉ lệ 16:10 đồng nhất cho mọi card */
    .nf-list-page .img-wrapper {
        position: relative;
        overflow: hidden;
        background: #f1f5f9;
        aspect-ratio: 16 / 10;
    }
    .nf-list-page .img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .nf-list-page .nf-noimg {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c4cad6;
        font-size: 2rem;
    }
    .nf-list-page .badge-category {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--theme-primary, #0c4c29);
        color: #fff;
        font-size: .65rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        z-index: 5;
        text-transform: uppercase;
    }

    /* Typography */
    .nf-list-page .news-title {
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.4;
        color: #1e293b;
        text-decoration: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .nf-list-page .news-card:hover .news-title { color: var(--theme-primary, #0c4c29); }
    .nf-list-page .nf-excerpt {
        font-size: .8rem;
        color: #6b7280;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .nf-list-page .news-meta { font-size: .75rem; color: #64748b; }

    /* Page title */
    .nf-list-page .nf-page-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--theme-primary, #0c4c29);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .nf-list-page .nf-count {
        font-size: .82rem;
        font-weight: 700;
        color: #64748b;
        background: #eef2f7;
        border-radius: 999px;
        padding: 2px 10px;
    }

    @media (max-width: 767px) {
        .nf-list-page .nf-page-title { font-size: 1.25rem; }
        .nf-list-page .news-title { font-size: .9rem; }
        .nf-list-page .nf-excerpt { display: none; }
    }
</style>

<script>
    // Tìm kiếm tức thời phía client (lọc các thẻ .nf-item theo data-title)
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('nfSearchInput');
        if (!input) return;
        var empty = document.getElementById('nfEmptyState');

        input.addEventListener('input', function () {
            var q = (input.value || '').trim().toLowerCase();
            var items = document.querySelectorAll('.nf-list-page .nf-item');
            var shown = 0;
            items.forEach(function (el) {
                var title = el.getAttribute('data-title') || '';
                var match = q === '' || title.indexOf(q) !== -1;
                el.classList.toggle('d-none', !match);
                if (match) shown++;
            });
            if (empty) empty.classList.toggle('d-none', shown !== 0);
        });
    });
</script>
