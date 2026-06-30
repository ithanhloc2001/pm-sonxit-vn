<?php include_once '../config.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo $_SiteTitle; ?></title>
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