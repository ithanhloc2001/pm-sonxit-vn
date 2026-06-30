<?php
include_once '../config.php';

$_SiteUrl = $_SiteUrl ?? ($site_url ?: ($baseUrl ?? '/'));
$_SiteTitle = $_SiteTitle ?? ($site_title ?: 'Paint&More');
$_SiteLogoPath = $site_logo ?: ($site_fallback_logo ?? '');
if (!preg_match('~^https?://~i', (string)$_SiteLogoPath)) {
    $_SiteLogo = rtrim((string)($baseUrl ?? ''), '/') . '/' . ltrim((string)$_SiteLogoPath, '/');
} else {
    $_SiteLogo = $_SiteLogoPath;
}

$metaTitle = isset($pageTitle) && trim((string)$pageTitle) !== '' ? (string)$pageTitle : $_SiteTitle;
$canonicalUrl = isset($pageCanonicalUrl) && trim((string)$pageCanonicalUrl) !== '' ? (string)$pageCanonicalUrl : (rtrim($_SiteUrl, '/') . '/diy');
$metaDescription = isset($pageDescription) ? trim((string)$pageDescription) : 'Cẩm nang và giải pháp sơn tự làm (DIY) từ Mỹ tại Paint&More.';
$metaImageUrl = $_SiteLogo;
if (isset($pageImageUrl) && trim((string)$pageImageUrl) !== '') {
    $metaImageUrl = (string)$pageImageUrl;
}
$ogType = isset($pageOgType) && trim((string)$pageOgType) !== '' ? (string)$pageOgType : 'website';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= h($metaTitle) ?></title>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>" />
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= h($metaDescription) ?>" />
    <?php endif; ?>
    <meta property="og:title" content="<?= h($metaTitle) ?>" />
    <meta property="og:description" content="<?= h($metaDescription) ?>" />
    <meta property="og:url" content="<?= h($canonicalUrl) ?>" />
    <meta property="og:image" content="<?= h($metaImageUrl) ?>" />
    <meta property="og:type" content="<?= h($ogType) ?>" />
    <meta property="og:site_name" content="<?= h($_SiteTitle) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= h($metaTitle) ?>" />
    <meta name="twitter:description" content="<?= h($metaDescription) ?>" />
    <meta name="twitter:image" content="<?= h($metaImageUrl) ?>" />
    <meta name="robots" content="<?= h($pageRobots ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1') ?>" />
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="../style.css" rel="stylesheet"/>
    <link href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: '1.0.0' ?>" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src="<?= h($baseUrl) ?>/assets/js/jquery-3.7.1.min.js"></script>
    <script>
        if (typeof jQuery === 'undefined') {
            document.write('<script src="../assets/js/jquery-3.7.1.min.js"><\/script>');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <meta name="csrf-token" content="<?= h($csrfToken ?? '') ?>">
    <style>
        :root {
            --landing-bg: #f8fafc;
            --landing-primary: #0c4c29;
            --landing-accent: #FFA827;
        }
    </style>

</head>