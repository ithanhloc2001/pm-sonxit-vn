<?php
require_once __DIR__ . '/../config.php';
$pageTitle = "DIY Paint & More | Sơn Mỹ Tự Làm Cao Cấp & Tiết Kiệm";
$pageDescription = "Giải pháp tự sơn nhà, cải tạo không gian sống với sơn xịt Rust-Oleum nhập khẩu Mỹ chính hãng. Dễ thi công, khô nhanh, bền màu đến 10 năm.";
$pageCanonicalUrl = rtrim($baseUrl, '/') . '/diy';
$no_page_loader = true;
include_once __DIR__ . '/../head.php';

/* ===== Gợi ý sản phẩm: random SP đang bán thuộc danh mục "Hàng mới", tối đa 6 ===== */
$lpSuggestProducts = [];
if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
    // Tìm id danh mục theo slug (bền hơn hardcode id)
    $lpSuggestCatId = 0;
    if ($rcSug = $ithanhloc->query("SELECT id FROM ecommerce_category WHERE LOWER(slug)='hang-moi' LIMIT 1")) {
        if ($rowSug = $rcSug->fetch_assoc()) $lpSuggestCatId = (int)$rowSug['id'];
    }
    if ($lpSuggestCatId > 0) {
        $sqlSug = "SELECT p.id, p.product_name, p.image_url,
                          (SELECT MIN(price) FROM ecommerce_product_variants v WHERE v.product_id = p.id AND v.price > 0) AS min_price
                   FROM ecommerce_product p
                   WHERE p.category_id = " . $lpSuggestCatId . "
                     AND LOWER(TRIM(CAST(p.status AS CHAR))) IN ('1','true','on','yes')
                     AND TRIM(COALESCE(p.image_url,'')) <> ''
                   HAVING min_price IS NOT NULL
                   ORDER BY RAND() LIMIT 6";
        if ($resSug = $ithanhloc->query($sqlSug)) {
            while ($rowP = $resSug->fetch_assoc()) $lpSuggestProducts[] = $rowP;
        }
    }
}
?>
<link href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: '1' ?>" rel="stylesheet" />

<style>
    /* ============================================================
   BASE
   ============================================================ */
    .lp-page {
        font-family: 'Montserrat', sans-serif;
        color: #1a1a1a;
        overflow-x: hidden;
        margin-top: -45px;
    }

    /* Chỉ Hero cao full màn hình; các section khác cao theo nội dung. */
    .lp-hero {
        min-height: 100vh;
        min-height: 100svh;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* ============================================================
   SECTION 1 — HERO
   ============================================================ */
    .lp-hero {
        position: relative;
        overflow: hidden;
        background: #fafafa;
    }

    .lp-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 60%;
        height: 100%;
        background: linear-gradient(to right, rgba(250, 250, 250, 0.98) 0%, rgba(250, 250, 250, 0.8) 60%, rgba(250, 250, 250, 0) 100%);
        z-index: 2;
        pointer-events: none;
    }

    .lp-hero img.hero-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: right center;
        z-index: 1;
    }

    .lp-hero-inner {
        display: grid;
        grid-template-columns: 1.15fr 0.85fr;
        min-height: 520px;
        flex: 1;
        /* lấp đầy chiều cao section (100vh) */
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        position: relative;
        z-index: 3;
    }

    .lp-hero-content {
        padding: 48px 32px 48px 24px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        z-index: 4;
    }

    .lp-section-badge {
        display: inline-block;
        background: #0c4c29;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 5px 12px;
        border-radius: 4px;
        margin-bottom: 24px;
        letter-spacing: .5px;
        align-self: flex-start;
    }

    .lp-hero-content h1 {
        font-size: clamp(24px, 3.2vw, 38px);
        font-weight: 900;
        line-height: 1.2;
        color: #1a1a1a;
        text-transform: uppercase;
        margin: 0 0 20px;
    }

    .lp-hero-content h1 .red {
        color: #0c4c29;
    }

    .lp-hero-desc {
        font-size: 14.5px;
        color: #444;
        line-height: 1.65;
        margin-bottom: 32px;
        max-width: 440px;
    }

    .lp-hero-btns {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }

    .lp-btn-red {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: #0c4c29;
        color: #fff;
        font-weight: 800;
        font-size: 13.5px;
        text-transform: uppercase;
        padding: 14px 28px;
        border-radius: 6px;
        text-decoration: none;
        border: 2px solid #0c4c29;
        transition: background .2s, border-color .2s;
    }

    .lp-btn-red:hover {
        background: #0c4c29;
        border-color: #0c4c29;
        color: #fff;
    }

    .lp-btn-outline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: transparent;
        color: #1a1a1a;
        font-weight: 800;
        font-size: 13.5px;
        text-transform: uppercase;
        padding: 14px 28px;
        border-radius: 6px;
        text-decoration: none;
        border: 2px solid #1a1a1a;
        transition: background .2s, color .2s, border-color .2s;
    }

    .lp-btn-outline:hover {
        background: #1a1a1a;
        color: #fff;
        border-color: #1a1a1a;
    }

    .lp-hero-right {
        min-height: 420px;
    }

    /* Trust bar */
    .lp-trust {
        background: #0c4c29;
        position: relative;
        z-index: 5;
    }

    .lp-trust-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
    }

    .lp-trust-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 22px 24px;
        border-right: 1px solid rgba(255, 255, 255, .12);
        color: #fff;
    }

    .lp-trust-item:last-child {
        border-right: none;
    }

    .lp-trust-item i {
        font-size: 28px;
        opacity: .8;
        flex-shrink: 0;
    }

    .lp-trust-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        line-height: 1.4;
    }

    /* ── Hero Intro Animations ── */
    @keyframes heroFadeUp {
        from {
            opacity: 0;
            transform: translateY(28px);
            filter: blur(4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
            filter: blur(0);
        }
    }

    @keyframes heroBgReveal {
        from {
            opacity: 0;
            transform: scale(1.04);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes heroLineSlide {
        from { transform: scaleX(0); opacity: 0; }
        to   { transform: scaleX(1); opacity: 1; }
    }

    /* Applied to hero children — each gets a delay */
    .hero-anim {
        animation: heroFadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .hero-anim-bg {
        animation: heroBgReveal 1.1s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .hero-anim-d1 { animation-delay: 0.05s; }
    .hero-anim-d2 { animation-delay: 0.18s; }
    .hero-anim-d3 { animation-delay: 0.30s; }
    .hero-anim-d4 { animation-delay: 0.44s; }
    .hero-anim-d5 { animation-delay: 0.56s; }
    .hero-anim-d6 { animation-delay: 0.68s; }
    .hero-anim-d7 { animation-delay: 0.80s; }

    /* Trust bar slides up as a whole */
    .hero-trust-anim {
        animation: heroFadeUp 0.65s cubic-bezier(0.22, 1, 0.36, 1) 0.85s both;
    }

    /* Respect reduced-motion preference */
    @media (prefers-reduced-motion: reduce) {
        .hero-anim, .hero-anim-bg, .hero-trust-anim {
            animation: none;
        }
    }


    /* ============================================================
   SCROLL REVEAL — reusable across sections
   ============================================================ */
    .sr {
        opacity: 0;
        transition: opacity 0.65s cubic-bezier(0.22, 1, 0.36, 1),
                    transform 0.65s cubic-bezier(0.22, 1, 0.36, 1),
                    filter 0.65s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .sr.from-left  { transform: translateX(-40px); }
    .sr.from-right { transform: translateX(40px); }
    .sr.from-bottom{ transform: translateY(36px); }
    .sr.scale-in   { transform: scale(0.80); filter: blur(3px); }

    .sr.revealed {
        opacity: 1;
        transform: none;
        filter: none;
    }
    .sr.delay-1 { transition-delay: 0.10s; }
    .sr.delay-2 { transition-delay: 0.22s; }
    .sr.delay-3 { transition-delay: 0.34s; }
    .sr.delay-4 { transition-delay: 0.46s; }

    @media (prefers-reduced-motion: reduce) {
        .sr { opacity: 1 !important; transform: none !important; filter: none !important; transition: none !important; }
    }

    /* ============================================================
   SECTION 2 — SO SÁNH VS
   ============================================================ */
    .lp-compare {
        padding: 80px 24px;
        background: #fff;
    }

    .lp-compare-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: 64px;
        align-items: center;
    }

    .lp-compare-left h2 {
        font-size: clamp(22px, 3vw, 38px);
        font-weight: 900;
        line-height: 1.12;
        text-transform: uppercase;
        margin: 0 0 18px;
    }

    .lp-compare-left h2 .green {
        color: #2d6a2d;
    }

    .lp-compare-sub {
        font-size: 14px;
        color: #666;
        line-height: 1.65;
        margin: 0;
    }

    .lp-vs-wrap {
        display: grid;
        grid-template-columns: 1fr 48px 1fr;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 6px 30px rgba(0, 0, 0, .1);
    }

    .lp-vs-col {
        position: relative;
        min-height: 440px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        overflow: hidden;
    }

    .lp-vs-col-img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
        overflow: hidden;
    }

    .lp-vs-col-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .lp-vs-col:hover .lp-vs-col-img img {
        transform: scale(1.05);
    }

    .lp-vs-col-body {
        position: relative;
        z-index: 2;
        padding: 32px 24px;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }

    .lp-vs-col.bad .lp-vs-col-body {
        background: linear-gradient(to top, rgba(15, 15, 15, 0.92) 0%, rgba(15, 15, 15, 0.55) 60%, rgba(15, 15, 15, 0.1) 100%);
    }

    .lp-vs-col.good .lp-vs-col-body {
        background: linear-gradient(to top, rgba(12, 76, 41, 0.95) 0%, rgba(12, 76, 41, 0.65) 60%, rgba(12, 76, 41, 0.1) 100%);
        color: #fff;
    }

    .lp-vs-title {
        font-size: 15px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 2px solid rgba(255, 255, 255, .2);
        color: #fff;
    }

    .lp-vs-col.good .lp-vs-title {
        color: #fff;
        border-bottom-color: rgba(255, 255, 255, .25);
    }

    .lp-vs-col ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .lp-vs-col ul li {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 13.5px;
        line-height: 1.55;
        margin-bottom: 10px;
        color: rgba(255, 255, 255, 0.9);
    }

    .lp-vs-col.good ul li {
        color: rgba(255, 255, 255, .95);
    }

    .lp-vs-col ul li .xi {
        color: #ff6b6b;
        flex-shrink: 0;
        margin-top: 1px;
        font-size: 14px;
    }

    .lp-vs-col ul li .ok {
        color: #a3ffd6;
        flex-shrink: 0;
        margin-top: 1px;
        font-size: 14px;
    }

    .lp-vs-mid {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        font-size: 18px;
        font-weight: 900;
        color: #0c4c29;
        position: relative;
        z-index: 3;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }

    /* ============================================================
   SECTION 3 — BẢNG SO SÁNH CHI TIẾT
   ============================================================ */
    .lp-table-sec {
        padding: 80px 24px;
        background: #f5f0e8;
    }

    .lp-table-sec-inner {
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Header — centered */
    .lp-table-head {
        max-width: 760px;
        margin: 0 auto 40px;
        text-align: center;
    }

    .lp-table-eyebrow {
        display: inline-block;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #0c4c29;
        background: #e6f0ea;
        padding: 6px 16px;
        border-radius: 100px;
        margin-bottom: 16px;
    }

    .lp-table-head h2 {
        font-size: clamp(24px, 3.2vw, 40px);
        font-weight: 900;
        line-height: 1.15;
        text-transform: uppercase;
        margin: 0 0 14px;
    }

    .lp-table-head h2 .red {
        color: #0c4c29;
    }

    .lp-table-head p {
        font-size: 14px;
        color: #555;
        line-height: 1.7;
        margin: 0;
    }

    /* Before/After trio */
    .lp-ba-trio-wrap {
        max-width: 1000px;
        margin: 0 auto 48px;
    }

    .lp-ba-trio {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    .lp-ba-item {}

    .lp-ba-item-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #555;
        text-align: center;
        margin-bottom: 6px;
    }

    .lp-ba-pair {
        position: relative;
        width: 100%;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        user-select: none;
    }

    .lp-ba-pair img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        pointer-events: none;
    }

    .lp-ba-pair .slider-after {
        width: 100%;
        height: 100%;
    }

    .lp-ba-pair .slider-before {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
        clip-path: polygon(0 0, var(--percent, 50%) 0, var(--percent, 50%) 100%, 0 100%);
        pointer-events: none;
    }

    .lp-ba-pair .slider-handle {
        position: absolute;
        top: 0;
        bottom: 0;
        left: var(--percent, 50%);
        width: 2px;
        background: #fff;
        z-index: 3;
        pointer-events: none;
        transform: translateX(-50%);
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }

    .lp-ba-pair .slider-handle-button {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 32px;
        height: 32px;
        background: #0c4c29;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
        border: 2px solid #fff;
        font-size: 12px;
        transition: transform 0.2s ease, background-color 0.2s ease;
    }

    .lp-ba-pair:hover .slider-handle-button {
        transform: translate(-50%, -50%) scale(1.1);
        background-color: #08331b;
    }

    .lp-ba-pair .slider-handle-button i {
        font-size: 10px;
    }

    .lp-ba-pair .slider-handle-button i:first-child {
        margin-right: -2px;
    }

    .lp-ba-pair .slider-handle-button i:last-child {
        margin-left: -2px;
    }

    .lp-ba-pair .slider-range {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: ew-resize;
        z-index: 4;
        margin: 0;
        padding: 0;
        -webkit-appearance: none;
        appearance: none;
    }

    .lp-ba-tag {
        position: absolute;
        bottom: 10px;
        font-size: 9px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        z-index: 3;
        text-transform: uppercase;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .lp-ba-tag.b {
        left: 10px;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(2px);
        color: #fff;
    }

    .lp-ba-tag.a {
        right: 10px;
        background: rgba(12, 76, 41, 0.85);
        backdrop-filter: blur(2px);
        color: #fff;
    }

    /* ── Comparison grid (CSS grid, không dùng <table>) ── */
    .lp-cmp {
        max-width: 1100px;
        margin: 0 auto;
        background: #fff;
        overflow: hidden;
        /* border-radius: 16px;
        box-shadow: 0 6px 30px rgba(12, 76, 41, .08);
        border: 1px solid #eae6db; */
    }

    /* Each row & header = 3-column grid */
    .lp-cmp-header,
    .lp-cmp-row {
        display: grid;
        grid-template-columns: 1.1fr 1fr 1.2fr;
        align-items: stretch;
    }

    .lp-cmp-header {
        background: #0c4c29;
        color: #fff;
    }

    .lp-cmp-header>div {
        padding: 16px 22px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .lp-cmp-h-cheap {
        color: rgba(255, 255, 255, .65);
        background: rgba(0, 0, 0, .12);
    }

    .lp-cmp-h-cheap i {
        color: #ff8a8a;
        font-size: 13px;
    }

    .lp-cmp-h-ro {
        background: #ffc107;
        color: #0c4c29 !important;
        justify-content: center;
    }

    .lp-cmp-h-ro .lp-cmp-h-logo {
        height: 22px;
        object-fit: contain;
    }

    .lp-cmp-h-ro i {
        font-size: 13px;
    }

    .lp-cmp-row {
        border-top: 1px solid #f0ece6;
        transition: background .15s;
    }

    .lp-cmp-row:hover {
        background: #fdfbf7;
    }

    .lp-cmp-row>div {
        padding: 15px 22px;
        font-size: 13.5px;
        line-height: 1.5;
        display: flex;
        flex-direction: column;
        gap: 5px;
        justify-content: center;
    }

    .lp-cmp-crit {
        flex-direction: row !important;
        align-items: center;
        gap: 10px !important;
        font-weight: 700;
        font-size: 12.5px;
        text-transform: uppercase;
        color: #1a1a1a;
        background: #faf8f4;
    }

    .lp-cmp-icon {
        font-size: 18px;
        line-height: 1;
        flex-shrink: 0;
    }

    .lp-cmp-cheap {
        color: #888;
    }

    .lp-cmp-ro {
        color: #1d5c33;
        font-weight: 600;
        background: rgba(255, 193, 7, .06);
    }

    /* Inline tag — ẩn trên PC, hiện trên mobile (stacked) */
    .lp-cmp-tag {
        display: none;
    }

    /* Last row — highlight */
    .lp-cmp-row.is-last {
        background: #0c4c29;
    }

    .lp-cmp-row.is-last:hover {
        background: #0c4c29;
    }

    .lp-cmp-row.is-last .lp-cmp-crit {
        background: #08331b;
        color: #fff;
    }

    .lp-cmp-row.is-last .lp-cmp-cheap {
        color: rgba(255, 255, 255, .55);
    }

    .lp-cmp-row.is-last .lp-cmp-ro {
        color: #ffc107;
        font-weight: 800;
        background: transparent;
    }

    /* Bottom bar */
    .lp-bottom-bar {
        background: linear-gradient(135deg, #09331b 0%, #0c4c29 100%);
        padding: 32px 24px;
        border-top: 3px solid #ffc107;
        position: relative;
        overflow: hidden;
    }

    .lp-bottom-bar::before {
        content: '';
        position: absolute;
        width: 250px;
        height: 250px;
        background: radial-gradient(circle, rgba(255, 193, 7, 0.05) 0%, transparent 70%);
        top: -125px;
        right: -50px;
        pointer-events: none;
    }

    .lp-bottom-bar-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 30px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .lp-bottom-bar-quote {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        max-width: 680px;
    }

    .lp-bottom-bar-quote .quote-icon {
        font-size: 28px;
        color: #ffc107;
        opacity: 0.85;
        line-height: 1;
        margin-top: -2px;
    }

    .lp-bottom-bar-text {
        color: #f8fafc;
        font-size: 15px;
        font-weight: 500;
        line-height: 1.45;
    }

    .lp-bottom-bar-text span {
        display: block;
        color: #ffc107;
        font-size: 17.5px;
        font-weight: 800;
        margin-top: 4px;
        letter-spacing: -0.2px;
    }

    .lp-bottom-bar-badges {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .lp-bottom-bar-badge-card {
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 10px 18px;
        border-radius: 12px;
        backdrop-filter: blur(4px);
        transition: transform 0.2s, border-color 0.2s;
    }

    .lp-bottom-bar-badge-card:hover {
        transform: translateY(-2px);
        border-color: rgba(255, 255, 255, 0.18);
    }

    .lp-made-usa {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #fff;
    }

    .lp-made-usa-text {
        font-size: 13.5px;
        font-weight: 800;
        text-transform: uppercase;
        line-height: 1.2;
    }

    .lp-made-usa-text small {
        display: block;
        font-size: 10px;
        font-weight: 400;
        opacity: .6;
        text-transform: none;
    }

    /* ============================================================
   RESPONSIVE
   ============================================================ */

    /* ============================================================
   SECTION 4 — DÀNH CHO AI
   ============================================================ */
    .lp-audience {
        padding: 80px 24px;
        background: #fff;
    }

    .lp-audience-inner {
        max-width: 1200px;
        margin: 0 auto;
    }

    .lp-audience-top {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: center;
        margin-bottom: 48px;
    }

    .lp-audience-top-left h2 {
        font-size: clamp(26px, 3.6vw, 46px);
        font-weight: 900;
        line-height: 1.08;
        text-transform: uppercase;
        margin: 0 0 16px;
    }

    .lp-audience-top-left h2 .red {
        color: #0c4c29;
    }

    .lp-audience-top-left p {
        font-size: 14px;
        color: #555;
        line-height: 1.65;
        margin: 0;
    }

    .lp-audience-top-right {
        text-align: right;
    }

    .lp-audience-top-right img {
        max-height: 240px;
        width: 100%;
        object-fit: cover;
        border-radius: 12px;
    }

    /* 4-col cards */
    .lp-aud-cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }

    .lp-aud-card {
        border: 1px solid #eae6db;
        border-radius: 16px;
        overflow: hidden;
        transition: box-shadow .3s ease, transform .3s ease;
        background: #fff;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .lp-aud-card:hover {
        box-shadow: 0 16px 32px rgba(12, 76, 41, 0.08);
        transform: translateY(-5px);
    }

    .lp-aud-card-img {
        height: 180px;
        overflow: hidden;
        position: relative;
        width: 100%;
        background: #f7f5f0;
    }

    .lp-aud-card-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .lp-aud-card:hover .lp-aud-card-img img {
        transform: scale(1.08);
    }

    .lp-aud-icon {
        position: absolute;
        top: 160px;
        left: 20px;
        width: 40px;
        height: 40px;
        background: #ffffff;
        border: 2px solid #0c4c29;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        box-shadow: 0 4px 10px rgba(12, 76, 41, 0.15);
        z-index: 2;
    }

    .lp-aud-card-top {
        padding: 32px 18px 24px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .lp-aud-card h4 {
        font-size: 14px;
        font-weight: 800;
        text-transform: uppercase;
        margin: 0 0 10px;
        color: #0c4c29;
        letter-spacing: 0.3px;
        line-height: 1.3;
    }

    .lp-aud-card p {
        font-size: 12.5px;
        color: #555;
        line-height: 1.6;
        margin: 0;
    }

    /* Feature strip */
    .lp-features {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        padding-top: 32px;
        border-top: 1px solid #eee;
        margin-top: 20px;
    }

    .lp-feat-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .lp-feat-icon {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
        border: 1.5px solid #ddd;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #2d6a2d;
    }

    .lp-feat-item h5 {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        margin: 0 0 4px;
    }

    .lp-feat-item p {
        font-size: 11px;
        color: #777;
        margin: 0;
        line-height: 1.5;
    }

    /* ============================================================
   SECTION 5 — BEFORE / AFTER
   ============================================================ */
    .lp-ba-sec {
        padding: 80px 24px;
        background: #f5f0e8;
    }

    .lp-ba-sec-inner {
        max-width: 1200px;
        margin: 0 auto;
    }

    .lp-ba-header {
        margin-bottom: 36px;
    }

    .lp-ba-header h2 {
        font-size: clamp(24px, 3.2vw, 42px);
        font-weight: 900;
        line-height: 1.1;
        text-transform: uppercase;
        margin: 0 0 10px;
    }

    .lp-ba-header h2 .red {
        color: #0c4c29;
    }

    .lp-ba-header p {
        font-size: 13px;
        color: #666;
        margin: 0;
    }

    .lp-ba-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .lp-ba5-card {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
    }

    .lp-ba5-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #1a1a1a;
        padding: 10px 14px 8px;
        border-bottom: 1px solid #f0f0f0;
    }

    .lp-ba5-pair {
        display: grid;
        grid-template-columns: 1fr 36px 1fr;
        height: 150px;
        align-items: stretch;
    }

    .lp-ba5-side {
        position: relative;
        overflow: hidden;
    }

    .lp-ba5-side img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .lp-ba5-tag {
        position: absolute;
        top: 6px;
        left: 6px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 2px 6px;
        border-radius: 3px;
        z-index: 2;
    }

    .lp-ba5-tag.before {
        background: #555;
        color: #fff;
    }

    .lp-ba5-tag.after {
        background: #2d6a2d;
        color: #fff;
    }

    .lp-ba5-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0ece5;
        color: #999;
        font-size: 14px;
    }

    /* CTA row */
    .lp-ba5-cta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fff;
        border-radius: 10px;
        padding: 18px 24px;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
    }

    .lp-ba5-cta-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .lp-ba5-cta-icon {
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        background: #1a1a1a;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
    }

    .lp-ba5-cta-left p {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #1a1a1a;
    }

    .lp-ba5-cta-left span {
        color: #0c4c29;
        font-weight: 700;
        font-size: 14px;
    }

    /* ============================================================
   SECTION 6 — YÊN TÂM
   ============================================================ */
    .lp-safety {
        padding: 80px 24px;
        background: #fff;
    }

    .lp-safety-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: 60px;
        align-items: start;
    }

    .lp-safety-left h2 {
        font-size: clamp(26px, 3.5vw, 46px);
        font-weight: 900;
        line-height: 1.08;
        text-transform: uppercase;
        margin: 0 0 40px;
    }

    .lp-safety-left h2 em {
        font-style: normal;
        color: #2d6a2d;
    }

    .lp-safety-list {
        display: flex;
        flex-direction: column;
        gap: 26px;
    }

    .lp-safety-item {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .lp-safety-icon {
        width: 50px;
        height: 50px;
        flex-shrink: 0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .lp-safety-icon.leaf {
        background: #e8f5e8;
        color: #2d6a2d;
    }

    .lp-safety-icon.voc {
        background: #0c4c29;
        color: #fff;
        font-size: 12px;
        font-weight: 800;
    }

    .lp-safety-icon.house {
        background: #e8f5e8;
        color: #2d6a2d;
    }

    .lp-safety-item h4 {
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        margin: 0 0 5px;
    }

    .lp-safety-item p {
        font-size: 13px;
        color: #666;
        margin: 0;
        line-height: 1.55;
    }

    /* Right images */
    .lp-safety-right {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .lp-safety-img-main {
        border-radius: 12px;
        overflow: hidden;
        height: 270px;
        position: relative;
    }

    .lp-safety-img-main img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .lp-safety-img-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .lp-safety-img-sm {
        border-radius: 10px;
        overflow: hidden;
        height: 160px;
        position: relative;
    }

    .lp-safety-img-sm img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .lp-safety-cap {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 0, 0, .68));
        padding: 28px 12px 12px;
        color: #fff;
    }

    .lp-safety-cap strong {
        display: block;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .lp-safety-cap span {
        font-size: 11px;
        opacity: .85;
        line-height: 1.4;
    }

    /* ============================================================
   UTILITY — ẩn/hiện theo breakpoint (không phụ thuộc Bootstrap)
   ============================================================ */
    .lp-mobile-only {
        display: none !important;
    }

    @media (max-width: 600px) {
        .lp-mobile-only {
            display: block !important;
        }

        .lp-pc-only {
            display: none !important;
        }
    }

    /* ============================================================
   PC FIX — tỷ lệ cột tốt hơn
   ============================================================ */
    .lp-video-inner {
        grid-template-columns: 240px 1fr;
        gap: 48px;
    }

    .lp-products-inner {
        grid-template-columns: 260px 1fr;
        gap: 48px;
    }

    .lp-timeline-inner {
        grid-template-columns: 220px 1fr;
        gap: 48px;
    }

    /* Fix why img-card overflow */
    .lp-why-img-card {
        width: 100%;
    }

    /* ============================================================
   SECTION 7 — VIDEO
   ============================================================ */
    .lp-video-sec {
        padding: 80px 24px;
        background: #f5f0e8;
    }

    .lp-video-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 56px;
        align-items: start;
    }

    .lp-video-left {
        display: flex;
        flex-direction: column;
    }

    .lp-video-left h2 {
        font-size: clamp(24px, 3vw, 40px);
        font-weight: 900;
        line-height: 1.1;
        text-transform: uppercase;
        margin: 0 0 16px;
    }

    .lp-video-left h2 .red {
        color: #0c4c29;
    }

    .lp-video-left p {
        font-size: 14px;
        color: #555;
        line-height: 1.65;
        margin: 0;
    }

    .lp-video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .lp-video-card {
        cursor: pointer;
    }

    .lp-video-thumb {
        position: relative;
        aspect-ratio: 16 / 9;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
        background: #1a1a1a;
    }

    .lp-video-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        opacity: .85;
        transition: opacity .2s;
    }

    .lp-video-card:hover .lp-video-thumb img {
        opacity: 1;
    }

    .lp-video-play {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lp-video-play i {
        width: 44px;
        height: 44px;
        background: rgba(192, 57, 43, .9);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        padding-left: 3px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .4);
        transition: transform .2s;
    }

    .lp-video-card:hover .lp-video-play i {
        transform: scale(1.1);
    }

    .lp-video-dur {
        position: absolute;
        bottom: 7px;
        right: 8px;
        background: rgba(0, 0, 0, .75);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .lp-video-info strong {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 3px;
    }

    .lp-video-info span {
        font-size: 12px;
        color: #777;
        line-height: 1.4;
    }

    .lp-video-prodlink {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 7px;
        font-size: 12px;
        font-weight: 700;
        color: #c0392b;
        text-decoration: none;
    }

    .lp-video-prodlink:hover {
        text-decoration: underline;
    }

    /* YouTube lightbox */
    .lp-yt-overlay {
        position: fixed;
        inset: 0;
        z-index: 4000;
        background: rgba(0, 0, 0, .82);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .lp-yt-overlay.show {
        display: flex;
    }

    .lp-yt-box {
        position: relative;
        width: 100%;
        max-width: 960px;
        aspect-ratio: 16 / 9;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
    }

    .lp-yt-box iframe {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }

    .lp-yt-close {
        position: absolute;
        top: -44px;
        right: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 0;
        background: rgba(255, 255, 255, .15);
        color: #fff;
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lp-yt-close:hover {
        background: rgba(255, 255, 255, .3);
    }

    /* ============================================================
   SECTION — GỢI Ý SẢN PHẨM
   ============================================================ */
    .lp-suggest-sec {
        background: #f7f7f5;
        padding: 64px 24px;
    }
    .lp-suggest-inner {
        max-width: 1200px;
        margin: 0 auto;
    }
    .lp-suggest-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px;
        margin-bottom: 32px;
    }
    .lp-suggest-head h2 {
        font-size: clamp(22px, 2.6vw, 32px);
        font-weight: 900;
        line-height: 1.2;
        text-transform: uppercase;
        margin: 8px 0 0;
        color: #1a1a1a;
    }
    .lp-suggest-head h2 .red { color: #c0392b; }

    .lp-suggest-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 18px;
    }
    .lp-suggest-card {
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 14px;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .lp-suggest-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, .1);
        border-color: #d8d8d8;
    }
    .lp-suggest-thumb {
        position: relative;
        aspect-ratio: 1 / 1;
        background: #f4f4f2;
        overflow: hidden;
    }
    .lp-suggest-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 10px;
        transition: transform .3s ease;
    }
    .lp-suggest-card:hover .lp-suggest-thumb img { transform: scale(1.06); }
    .lp-suggest-tag {
        position: absolute;
        top: 8px;
        left: 8px;
        background: #0c4c29;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 999px;
        letter-spacing: .3px;
    }
    .lp-suggest-body {
        padding: 12px 12px 14px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .lp-suggest-name {
        font-size: 13px;
        font-weight: 600;
        color: #1a1a1a;
        line-height: 1.4;
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 36px;
    }
    .lp-suggest-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: auto;
    }
    .lp-suggest-price {
        font-size: 15px;
        font-weight: 900;
        color: #c0392b;
    }
    .lp-suggest-cart {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #0c4c29;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
        transition: background .2s ease;
    }
    .lp-suggest-card:hover .lp-suggest-cart { background: #c0392b; }

    @media (max-width: 600px) {
        .lp-suggest-sec { padding: 40px 16px; }
        .lp-suggest-head { flex-direction: column; align-items: flex-start; margin-bottom: 20px; }

        /* Danh sách sản phẩm: cuộn ngang (swipe) trên mobile */
        .lp-suggest-grid {
            display: flex;
            grid-template-columns: none;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            gap: 12px;
            padding-bottom: 6px;
            margin-right: -16px;     /* card cuối lộ sát mép -> gợi ý vuốt */
            scrollbar-width: none;
        }
        .lp-suggest-grid::-webkit-scrollbar { display: none; }
        .lp-suggest-card {
            flex: 0 0 46%;           /* ~2 card/màn hình, lộ một phần card kế tiếp */
            scroll-snap-align: start;
        }
    }

    .lp-video-stats {
        display: flex;
        gap: 0;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
        overflow: hidden;
    }

    .lp-vstat {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-right: 1px solid #f0ece5;
    }

    .lp-vstat:last-child {
        border-right: none;
    }

    .lp-vstat>i {
        font-size: 22px;
        color: #0c4c29;
        flex-shrink: 0;
    }

    .lp-vstat strong {
        display: block;
        font-size: 18px;
        font-weight: 900;
        color: #1a1a1a;
        line-height: 1;
    }

    .lp-vstat span {
        font-size: 11px;
        color: #777;
    }

    /* ============================================================
   SECTION 8 — DÒNG SẢN PHẨM
   ============================================================ */
    .lp-products-sec {
        padding: 80px 24px;
        background: #0c4c29;
    }

    .lp-products-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 56px;
        align-items: start;
    }

    .lp-products-left h2 {
        font-size: clamp(24px, 3vw, 40px);
        font-weight: 900;
        line-height: 1.1;
        text-transform: uppercase;
        color: #fff;
        margin: 0 0 18px;
    }

    .lp-products-left p {
        font-size: 14px;
        color: rgba(255, 255, 255, .7);
        line-height: 1.65;
        margin: 0;
    }

    .lp-products-right {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .lp-prod-card {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        cursor: pointer;
        transition: transform .2s;
    }

    .lp-prod-card:hover {
        transform: scale(1.03);
    }

    .lp-prod-img {
        height: 200px;
    }

    .lp-prod-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .lp-prod-label {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 0, 0, .75));
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .4px;
        padding: 20px 10px 9px;
        text-align: center;
    }

    /* ============================================================
   SECTION 9 — TIMELINE
   ============================================================ */
    .lp-timeline-sec {
        padding: 80px 24px;
        background: #1a1a1a;
        color: #fff;
    }

    .lp-timeline-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 56px;
        align-items: start;
    }

    .lp-timeline-left h2 {
        font-size: clamp(24px, 3vw, 40px);
        font-weight: 900;
        line-height: 1.1;
        text-transform: uppercase;
        margin: 0 0 18px;
    }

    .lp-timeline-left p {
        font-size: 14px;
        color: rgba(255, 255, 255, .65);
        line-height: 1.65;
        margin: 0;
    }

    .lp-timeline-imgs {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        margin-bottom: 28px;
    }

    .lp-timeline-imgs img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-radius: 8px;
        display: block;
        opacity: .85;
    }

    .lp-timeline-track {
        display: flex;
        align-items: flex-start;
        position: relative;
        padding-bottom: 8px;
    }

    .lp-timeline-track::before {
        content: '';
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        height: 2px;
        background: rgba(255, 255, 255, .15);
    }

    .lp-milestone {
        flex: 1;
        position: relative;
        padding: 0 6px;
    }

    .lp-milestone-dot {
        width: 18px;
        height: 18px;
        background: #0c4c29;
        border-radius: 50%;
        border: 3px solid #1a1a1a;
        margin-bottom: 12px;
        position: relative;
        z-index: 2;
    }

    .lp-milestone-year {
        font-size: 22px;
        font-weight: 900;
        color: #f5c842;
        line-height: 1;
        margin-bottom: 6px;
    }

    .lp-milestone-desc {
        font-size: 11px;
        color: rgba(255, 255, 255, .6);
        line-height: 1.5;
    }

    .lp-made-usa-badge {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: 28px;
    }

    .lp-made-usa-badge strong {
        display: block;
        font-size: 15px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .lp-made-usa-badge span {
        font-size: 11px;
        color: rgba(255, 255, 255, .6);
    }

    /* ============================================================
   SECTION 10 — VÌ SAO MUA TẠI PAINT & MORE
   ============================================================ */
    .lp-why-sec {
        padding: 90px 24px 70px;
        background: #ffffff;
    }

    .lp-why-inner {
        max-width: 1200px;
        margin: 0 auto 48px;
        display: grid;
        grid-template-columns: 1fr 1.2fr;
        gap: 56px;
        align-items: center;
    }

    .lp-why-left h2 {
        font-size: clamp(26px, 3.2vw, 44px);
        font-weight: 900;
        line-height: 1.1;
        text-transform: uppercase;
        margin: 0 0 36px;
        color: #050505;
        letter-spacing: -0.5px;
    }

    .lp-why-left h2 .red {
        color: #0c4c29;
    }

    .lp-why-badges {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .lp-why-badge {
        display: flex;
        align-items: center;
        gap: 20px;
        background: #fdfdfd;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        padding: 18px 24px;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .lp-why-badge:hover {
        transform: translateX(8px);
        background: #ffffff;
        border-color: #0c4c29;
        box-shadow: 0 10px 25px rgba(12, 76, 41, 0.06);
    }

    .lp-why-icon {
        width: 48px;
        height: 48px;
        flex-shrink: 0;
        background: #e6f0eb;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #0c4c29;
        transition: all 0.3s ease;
    }

    .lp-why-badge:hover .lp-why-icon {
        background: #0c4c29;
        color: #ffffff;
        transform: scale(1.05);
    }

    .lp-why-badge strong {
        display: block;
        font-size: 13.5px;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 4px;
        color: #050505;
    }

    .lp-why-badge span {
        font-size: 12.5px;
        color: #65676b;
        line-height: 1.5;
    }

    .lp-why-right {
        display: grid;
        grid-template-columns: 1.3fr 1fr;
        gap: 16px;
        width: 100%;
    }

    .lp-why-img-card {
        position: relative;
        border-radius: 14px;
        overflow: hidden;
        width: 100%;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .lp-why-img-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    }

    .lp-why-img-showroom {
        grid-row: span 2;
        height: 400px;
    }

    .lp-why-img-storage,
    .lp-why-img-staff {
        height: 192px;
    }

    .lp-why-img-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .lp-why-img-card:hover img {
        transform: scale(1.05);
    }

    .lp-why-img-label {
        position: absolute;
        bottom: 16px;
        left: 16px;
        background: rgba(12, 76, 41, 0.85);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        color: #ffffff;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 30px;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .lp-why-img-card:hover .lp-why-img-label {
        background: #0c4c29;
        transform: translateY(-2px);
    }

    /* Final CTA */
    .lp-final-cta {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
        padding-top: 8px;
    }

    /* ============================================================
   TABLET (≤960px) — stack nội dung, vẫn rộng rãi
   ============================================================ */
    @media (max-width: 960px) {

        .lp-hero-inner,
        .lp-compare-inner,
        .lp-safety-inner,
        .lp-video-inner,
        .lp-products-inner,
        .lp-timeline-inner,
        .lp-why-inner {
            grid-template-columns: 1fr;
            gap: 32px;
        }

        .lp-audience-top {
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .lp-trust-inner {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-hero {
            min-height: 100vh !important;
            min-height: 100svh !important;
            display: flex !important;
            align-items: center !important;
        }

        .lp-hero-inner {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            min-height: auto !important;
            width: 100% !important;
        }

        .lp-hero-content {
            background: rgba(255, 255, 255, 0.88) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border: 1px solid rgba(255, 255, 255, 0.6) !important;
            border-radius: 18px !important;
            margin: 32px !important;
            padding: 36px 30px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
            max-width: 500px !important;
            z-index: 4 !important;
        }

        .lp-hero::before {
            width: 100% !important;
            background: rgba(0, 0, 0, 0.03) !important;
        }

        .lp-hero-right {
            display: none !important;
        }

        .lp-hero-content h1 {
            font-size: 28px !important;
            margin-bottom: 14px !important;
            line-height: 1.25 !important;
            text-shadow: none !important;
            color: #0c4c29 !important;
        }

        .lp-hero-desc {
            font-size: 14px !important;
            margin-bottom: 24px !important;
            color: #4b5563 !important;
            font-weight: 500 !important;
            text-shadow: none !important;
            line-height: 1.6 !important;
        }

        /* VS section */
        .lp-vs-wrap {
            grid-template-columns: 1fr;
            position: relative;
            gap: 0;
        }

        .lp-vs-col {
            min-height: 310px;
        }

        .lp-vs-col-body {
            padding: 24px 18px;
        }

        .lp-vs-title {
            font-size: 13.5px;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }

        .lp-vs-col ul li {
            font-size: 13px;
            margin-bottom: 8px;
            gap: 6px;
        }

        .lp-vs-mid {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #fff;
            color: #0c4c29;
            font-size: 14px;
            font-weight: 900;
            z-index: 5;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            border: 2px solid #0c4c29;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Comparison grid — tablet/mobile: side-by-side cards ── */
        .lp-cmp {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
        }

        .lp-cmp-header {
            display: none !important;
        }

        .lp-cmp-row {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            grid-template-rows: auto auto !important;
            background: #fff !important;
            border: 1px solid #eae6db !important;
            border-radius: 14px !important;
            box-shadow: 0 4px 12px rgba(12, 76, 41, 0.03) !important;
            overflow: hidden !important;
            position: relative !important;
            padding: 0 !important;
        }

        /* Tiêu chí trở thành tiêu đề của card row, trải ngang phía trên */
        .lp-cmp-crit {
            grid-column: 1 / span 2 !important;
            grid-row: 1 !important;
            background: #fdfbf7 !important;
            padding: 10px 16px !important;
            font-size: 12.5px !important;
            font-weight: 700 !important;
            color: #0c4c29 !important;
            border-bottom: 1px solid #f0ece6 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
        }

        .lp-cmp-cheap {
            grid-column: 1 !important;
            grid-row: 2 !important;
            padding: 14px 12px !important;
            background: #fff !important;
            text-align: center !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 6px !important;
            justify-content: flex-start !important;
            border-right: 1px solid #f0ece6 !important;
        }

        .lp-cmp-ro {
            grid-column: 2 !important;
            grid-row: 2 !important;
            padding: 14px 12px !important;
            background: rgba(12, 76, 41, 0.03) !important;
            text-align: center !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 6px !important;
            justify-content: flex-start !important;
        }

        /* Hiện tag trên mobile để phân biệt 2 cột */
        .lp-cmp-tag {
            display: inline-flex !important;
            align-items: center;
            gap: 4px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 3px 8px;
            border-radius: 6px;
            width: max-content;
        }

        .lp-cmp-tag i {
            font-size: 10px;
        }

        .tag-cheap {
            background: #fff5f5;
            color: #e53e3e;
            border: 1px solid #fed7d7;
        }

        .tag-ro {
            background: #ecfdf5;
            color: #10b981;
            border: 1px solid #dcfce7;
        }

        .lp-cmp-cheap .lp-cmp-txt {
            font-size: 11.5px !important;
            color: #64748b !important;
            line-height: 1.45 !important;
            font-weight: 400 !important;
        }

        .lp-cmp-ro .lp-cmp-txt {
            font-size: 11.5px !important;
            color: #0c4c29 !important;
            line-height: 1.45 !important;
            font-weight: 600 !important;
        }

        /* Last row - Highlight */
        .lp-cmp-row.is-last {
            border-color: #f5c842 !important;
            box-shadow: 0 4px 15px rgba(245, 200, 66, 0.15) !important;
        }

        .lp-cmp-row.is-last .lp-cmp-crit {
            background: #0c4c29 !important;
            color: #fff !important;
            border-bottom: 1px solid #08331b !important;
        }

        .lp-cmp-row.is-last .lp-cmp-cheap {
            background: #fafafa !important;
        }

        .lp-cmp-row.is-last .tag-cheap {
            background: #f3f4f6 !important;
            color: #6b7280 !important;
            border-color: #e5e7eb !important;
        }

        .lp-cmp-row.is-last .lp-cmp-cheap .lp-cmp-txt {
            color: #9ca3af !important;
        }

        .lp-cmp-row.is-last .lp-cmp-ro {
            background: #fffbeb !important;
        }

        .lp-cmp-row.is-last .tag-ro {
            background: #f5c842 !important;
            color: #0c4c29 !important;
            border-color: #f5c842 !important;
        }

        .lp-cmp-row.is-last .lp-cmp-ro .lp-cmp-txt {
            color: #b45309 !important;
            font-weight: 800 !important;
            font-size: 12px !important;
        }

        .lp-bottom-bar-inner {
            flex-direction: column;
            text-align: center;
        }

        /* Audience */
        .lp-aud-cards {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-audience-top-right {
            display: none !important;
        }

        /* Features */
        .lp-features {
            grid-template-columns: repeat(2, 1fr);
        }

        /* BA */
        .lp-ba-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-ba-trio {
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .lp-ba-trio .lp-ba-item:nth-child(3) {
            grid-column: span 2;
            max-width: 380px;
            margin: 0 auto;
            width: 100%;
        }

        .lp-ba5-cta {
            flex-direction: column;
            align-items: flex-start;
        }

        /* Video */
        .lp-video-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-video-stats {
            flex-direction: column;
        }

        .lp-vstat {
            border-right: none;
            border-bottom: 1px solid #f0ece5;
        }

        .lp-vstat:last-child {
            border-bottom: none;
        }

        /* Products */
        .lp-products-right {
            grid-template-columns: repeat(4, 1fr);
        }

        /* Timeline */
        .lp-timeline-imgs {
            grid-template-columns: repeat(5, 1fr);
        }

        .lp-timeline-track {
            flex-wrap: wrap;
            gap: 20px;
        }

        .lp-timeline-track::before {
            display: none;
        }

        .lp-milestone {
            flex: 0 0 calc(50% - 10px);
        }

        /* Why */
        .lp-why-right {
            grid-template-columns: repeat(3, 1fr);
        }

        .lp-why-img-card {
            width: 100%;
            height: 170px;
        }

        .lp-why-img-showroom {
            grid-row: auto;
            height: 170px;
        }
    }

    /* ============================================================
   MOBILE (≤600px) — layout gọn, swiper ngang
   ============================================================ */
    @media (max-width: 600px) {

        /* Hero - Split Layout for Mobile (Image at top, text at bottom) */
        .lp-hero {
            min-height: auto !important;
            display: flex !important;
            flex-direction: column !important;
            background: #fff !important;
        }

        .lp-hero::before {
            display: none !important;
        }

        .lp-hero img.hero-bg {
            position: relative !important;
            width: 100% !important;
            height: 250px !important;
            object-fit: cover !important;
            object-position: right center !important;
            z-index: 1 !important;
            display: block !important;
        }

        .lp-hero-inner {
            display: block !important;
            width: 100% !important;
            min-height: auto !important;
            z-index: 2 !important;
        }

        .lp-hero-content {
            background: #fff !important;
            margin: 0 !important;
            padding: 24px 16px 20px !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }

        .lp-section-badge {
            display: none !important;
        }

        .lp-hero-content h1 {
            font-size: 24px !important;
            margin-bottom: 12px !important;
            line-height: 1.3 !important;
            text-shadow: none !important;
            color: #1a1a1a !important;
        }

        .lp-hero-content h1 .red {
            color: #0c4c29 !important;
        }

        .lp-hero-desc {
            font-size: 13.5px !important;
            margin-bottom: 20px !important;
            color: #4b5563 !important;
            font-weight: 400 !important;
            text-shadow: none !important;
            line-height: 1.55 !important;
        }

        .lp-hero-btns {
            flex-direction: column;
            width: 100%;
            gap: 10px;
        }

        .lp-hero-btns a {
            width: 100%;
            text-align: center;
            justify-content: center;
            padding: 13px 20px;
            font-size: 13px;
        }

        /* Section padding */
        .lp-compare,
        .lp-table-sec,
        .lp-audience,
        .lp-ba-sec,
        .lp-video-sec,
        .lp-products-sec {
            padding: 40px 16px;
            overflow: hidden;
        }

        /* Sections có swiper ngang — KHÔNG overflow: hidden, padding xử lý qua inner */
        .lp-safety,
        .lp-timeline-sec,
        .lp-why-sec {
            padding: 40px 0;
            overflow: visible;
        }

        .lp-safety-inner,
        .lp-timeline-inner,
        .lp-why-inner {
            padding: 0 16px;
            grid-template-columns: 1fr !important;
            gap: 24px !important;
        }

        .lp-made-usa-badge {
            padding: 0 0px;
        }

        /* Trust bar — 4 cột nhỏ */
        .lp-trust-inner {
            grid-template-columns: repeat(4, 1fr);
        }

        .lp-trust-item {
            flex-direction: column;
            gap: 6px;
            padding: 14px 8px;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, .12);
        }

        .lp-trust-item:last-child {
            border-right: none;
        }

        .lp-trust-item i {
            font-size: 20px;
        }

        .lp-trust-label {
            font-size: 9px;
            line-height: 1.35;
        }

        /* Section headings */
        .lp-compare-left h2,
        .lp-table-sec h2,
        .lp-audience-top-left h2,
        .lp-safety-left h2,
        .lp-video-left h2,
        .lp-products-left h2,
        .lp-timeline-left h2,
        .lp-why-left h2,
        .lp-ba-header h2 {
            font-size: 22px !important;
        }

        /* VS section */
        .lp-vs-col ul li {
            font-size: 12.5px !important;
        }

        /* Table section BA trio — swiper ngang */
        .lp-ba-trio {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            scroll-snap-type: x mandatory !important;
            gap: 12px !important;
            padding: 4px 16px 12px !important;
            margin: 0 -16px !important;
            scrollbar-width: none !important;
            max-width: unset !important;
        }

        .lp-ba-trio::-webkit-scrollbar {
            display: none !important;
        }

        .lp-ba-trio .lp-ba-item {
            flex: 0 0 82% !important;
            scroll-snap-align: center;
            max-width: unset !important;
        }

        .lp-ba-trio .lp-ba-item:nth-child(3) {
            grid-column: auto;
            max-width: unset !important;
        }

        .lp-ba-pair {
            aspect-ratio: 1 !important;
        }

        /* BA trio wrapper + nav */
        .lp-ba-trio-wrap {
            position: relative;
        }

        .lp-ba-trio-nav {
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 14px;
        }

        .lp-ba-trio-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #0c4c29;
            background: #fff;
            color: #0c4c29;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .2s, color .2s;
        }

        .lp-ba-trio-btn:hover {
            background: #0c4c29;
            color: #fff;
        }

        .lp-ba-trio-dots {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .lp-ba-trio-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ccc;
            transition: background .2s, transform .2s;
        }

        .lp-ba-trio-dot.active {
            background: #0c4c29;
            transform: scale(1.3);
        }

        /* Header + BA trio spacing trên mobile */
        .lp-table-head {
            margin-bottom: 28px !important;
        }

        .lp-ba-trio-wrap {
            margin-bottom: 32px !important;
        }



        /* Ẩn bớt hàng so sánh trên mobile mặc định */
        .lp-cmp:not(.lp-cmp-expanded) .lp-cmp-row-collapsible {
            display: none !important;
        }

        /* BA grid */
        .lp-ba-grid {
            grid-template-columns: 1fr;
        }

        /* Audience */
        .lp-audience-top {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }

        /* Features — 2 cột */
        .lp-features {
            grid-template-columns: 1fr 1fr !important;
            gap: 16px !important;
        }

        /* AUD-CARDS CAROUSEL */
        .lp-aud-cards-wrap {
            position: relative;
        }

        .lp-aud-cards {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            scroll-snap-type: x mandatory !important;
            gap: 12px !important;
            padding: 4px 16px 8px !important;
            margin: 0 -16px !important;
            scrollbar-width: none !important;
        }

        .lp-aud-cards::-webkit-scrollbar {
            display: none !important;
        }

        .lp-aud-card {
            flex: 0 0 82% !important;
            scroll-snap-align: center;
            margin: 0 !important;
            border-radius: 14px !important;
        }

        .lp-aud-card-img {
            height: 160px !important;
        }

        .lp-aud-icon {
            top: 140px !important;
            left: 14px !important;
            width: 36px !important;
            height: 36px !important;
            font-size: 16px !important;
            border-radius: 8px !important;
        }

        .lp-aud-card-top {
            padding: 28px 14px 18px !important;
        }

        .lp-aud-card h4 {
            font-size: 13px !important;
        }

        .lp-aud-card p {
            font-size: 12px !important;
            line-height: 1.55 !important;
        }

        /* Nav */
        .lp-aud-nav {
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 14px;
        }

        .lp-aud-nav-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #0c4c29;
            background: #fff;
            color: #0c4c29;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .2s, color .2s;
        }

        .lp-aud-nav-btn:hover {
            background: #0c4c29;
            color: #fff;
        }

        .lp-aud-dots {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .lp-aud-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ccc;
            transition: background .2s, transform .2s;
        }

        .lp-aud-dot.active {
            background: #0c4c29;
            transform: scale(1.3);
        }

        /* Safety — swiper ngang */
        .lp-safety-right {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            scroll-snap-type: x mandatory !important;
            gap: 12px !important;
            padding: 4px 16px 12px !important;
            scrollbar-width: none !important;
        }

        .lp-safety-right::-webkit-scrollbar {
            display: none !important;
        }

        .lp-safety-img-main,
        .lp-safety-img-sm {
            flex: 0 0 240px !important;
            scroll-snap-align: start;
            margin: 0 !important;
            height: 200px !important;
        }

        .lp-safety-img-row {
            display: contents !important;
        }

        .lp-safety-left h2 {
            margin-bottom: 20px;
        }

        .lp-safety-item {
            gap: 12px !important;
        }

        .lp-safety-icon {
            width: 40px !important;
            height: 40px !important;
            font-size: 18px !important;
        }

        /* Video — cuộn ngang (swipe) trên mobile */
        .lp-video-grid {
            display: flex !important;
            grid-template-columns: none !important;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            gap: 12px !important;
            padding-bottom: 6px;
            margin-right: -16px;     /* cho card cuối lộ ra sát mép, gợi ý vuốt */
            scrollbar-width: none;
        }
        .lp-video-grid::-webkit-scrollbar { display: none; }

        .lp-video-card {
            flex: 0 0 78%;           /* card chiếm 78% -> lộ một phần card kế tiếp */
            scroll-snap-align: start;
        }

        .lp-video-thumb {
            height: auto !important;
            aspect-ratio: 16 / 9;
        }

        .lp-video-stats {
            display: none !important;
        }

        /* Products — 2x4 grid */
        .lp-products-right {
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 8px !important;
        }

        .lp-prod-img {
            height: 90px !important;
        }

        .lp-prod-label {
            font-size: 9px !important;
            padding: 12px 6px 7px !important;
        }

        .lp-products-left p {
            font-size: 13px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .lp-timeline-right {
            min-width: 0;
            width: 100%;
        }

        /* Timeline mobile — carousel */
        .lp-tl-carousel-wrap {
            position: relative;
            margin-bottom: 8px;
        }

        .lp-tl-carousel {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            scroll-snap-type: x mandatory !important;
            gap: 12px !important;
            padding: 4px 16px 8px !important;
            margin: 0 -16px !important;
            scrollbar-width: none !important;
        }

        .lp-tl-carousel::-webkit-scrollbar {
            display: none !important;
        }

        .lp-tl-slide {
            flex: 0 0 88% !important;
            scroll-snap-align: center;
            border-radius: 14px;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: row;
            min-height: 160px;
            background: #111;
        }

        /* Ảnh nền full slide, mờ dần sang phải */
        .lp-tl-slide-img {
            position: absolute;
            inset: 0;
            z-index: 0;
        }

        .lp-tl-slide-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            opacity: .35;
        }

        /* Overlay gradient trái sang phải */
        .lp-tl-slide-img::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right,
                    rgba(12, 76, 41, .92) 0%,
                    rgba(12, 76, 41, .70) 50%,
                    rgba(0, 0, 0, .2) 100%);
        }

        .lp-tl-slide-body {
            position: relative;
            z-index: 1;
            padding: 22px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
            border-left: 4px solid #f5c842;
            margin: 16px 0 16px 16px;
            padding-left: 16px;
        }

        .lp-tl-slide-body .lp-milestone-dot {
            display: none;
        }

        .lp-tl-slide-body .lp-milestone-year {
            font-size: 38px;
            font-weight: 900;
            color: #f5c842;
            line-height: 1;
            margin: 0;
            letter-spacing: -1px;
        }

        .lp-tl-slide-body .lp-milestone-desc {
            font-size: 13px;
            color: rgba(255, 255, 255, .88);
            line-height: 1.5;
            margin: 0;
            max-width: 220px;
        }

        /* Nav */
        .lp-tl-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 14px;
        }

        .lp-tl-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, .5);
            background: transparent;
            color: #fff;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .2s, border-color .2s;
        }

        .lp-tl-btn:hover {
            background: rgba(255, 255, 255, .15);
            border-color: #fff;
        }

        .lp-tl-dots {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .lp-tl-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .3);
            transition: background .2s, transform .2s;
        }

        .lp-tl-dot.active {
            background: #f5c842;
            transform: scale(1.3);
        }

        /* Why badges — danh sách dọc */
        .lp-why-badges {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
        }

        .lp-why-badge {
            flex-direction: row;
            align-items: flex-start;
            gap: 12px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .lp-why-icon {
            width: 38px;
            height: 38px;
            font-size: 17px;
            flex-shrink: 0;
        }

        .lp-why-left h2 {
            margin-bottom: 20px;
        }

        /* Why right — swiper ngang */
        .lp-why-right {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            scroll-snap-type: x mandatory !important;
            gap: 12px !important;
            padding: 4px 16px 12px !important;
            scrollbar-width: none !important;
        }

        .lp-why-right::-webkit-scrollbar {
            display: none !important;
        }

        .lp-why-img-card {
            flex: 0 0 200px !important;
            width: 200px !important;
            scroll-snap-align: start;
            margin: 0 !important;
            height: 160px !important;
        }

        .lp-why-img-showroom {
            grid-row: auto !important;
            height: 160px !important;
        }

        /* Bottom bar */
        .lp-bottom-bar {
            padding: 24px 16px;
        }

        .lp-bottom-bar-inner {
            flex-direction: column;
            align-items: center;
            gap: 16px !important;
            text-align: center;
        }

        .lp-bottom-bar-quote {
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .lp-bottom-bar-quote .quote-icon {
            font-size: 22px;
            margin-top: 0;
        }

        .lp-bottom-bar-text {
            font-size: 13.5px !important;
        }

        .lp-bottom-bar-text span {
            font-size: 15px !important;
            margin-top: 6px;
        }

        .lp-bottom-bar-badges {
            flex-direction: row;
            justify-content: center;
            width: 100%;
            gap: 10px;
        }

        .lp-bottom-bar-badge-card {
            flex: 1;
            justify-content: center;
        }
    }

    /* ── STICKY BOTTOM CTA BAR (Mobile only) ── */
    @media (max-width: 600px) {
        .lp-sticky-cta {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -4px 16px rgba(0, 0, 0, .1);
            display: flex;
            gap: 10px;
            padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
        }

        .lp-sticky-cta a {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 13px 12px;
            border-radius: 8px;
            text-decoration: none;
            letter-spacing: .3px;
        }

        .lp-sticky-cta .lp-sticky-buy {
            background: #0c4c29;
            color: #fff;
        }

        .lp-sticky-cta .lp-sticky-contact {
            background: #f0f4f0;
            color: #0c4c29;
            border: 1.5px solid #0c4c29;
        }
    }
</style>

<div class="lp-page">

    <!-- ================================================================
     SECTION 1 — HERO
     ================================================================ -->
    <section class="lp-hero">
        <img class="hero-bg hero-anim-bg" src="image/hero_banner.png" alt="Rust-Oleum sơn xịt số 1 Hoa Kỳ"
            onerror="this.style.background='#d4c9b8';this.removeAttribute('src')">
        <div class="lp-hero-inner">
            <div class="lp-hero-content">
                <div class="lp-section-badge hero-anim hero-anim-d1">SECTION 1 - HERO</div>
                <h1 class="hero-anim hero-anim-d2">
                    Rust-Oleum –<br>
                    <span class="red">Thương hiệu sơn xịt<br>
                        số 1 tại <span style="white-space: nowrap;">Hoa Kỳ
                            <svg class="us-flag" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 7410 3900" style="height: 24px; vertical-align: middle; margin-left: 8px; margin-top: -4px; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); display: inline-block;">
                                <rect width="7410" height="3900" fill="#b22234" />
                                <path d="M0,450H7410M0,1050H7410M0,1650H7410M0,2250H7410M0,2850H7410M0,3450H7410" stroke="#fff" stroke-width="300" />
                                <rect width="2964" height="2100" fill="#3c3b6e" />
                                <g fill="#fff">
                                    <g id="s18">
                                        <g id="s9">
                                            <g id="s5">
                                                <path id="s" d="M247,90 317,307 134,174 360,174 177,307z" />
                                                <use href="#s" x="494" />
                                                <use href="#s" x="988" />
                                                <use href="#s" x="1482" />
                                                <use href="#s" x="1976" />
                                            </g>
                                            <use href="#s" x="247" y="150" />
                                            <use href="#s" x="741" y="150" />
                                            <use href="#s" x="1235" y="150" />
                                            <use href="#s" x="1729" y="150" />
                                        </g>
                                        <use href="#s9" y="300" />
                                        <use href="#s9" y="600" />
                                        <use href="#s9" y="900" />
                                        <use href="#s9" y="1200" />
                                    </g>
                                    <use href="#s5" x="247" y="1500" />
                                    <use href="#s18" y="1500" />
                                </g>
                            </svg>
                        </span></span>
                </h1>
                <p class="lp-hero-desc hero-anim hero-anim-d3">Từ ghế sắt, xe đạp đến đồ decor, Rust-Oleum giúp tạo nên lớp hoàn thiện đẹp, bền và đáng công sức bạn bỏ ra.</p>
                <div class="lp-hero-btns hero-anim hero-anim-d4">
                    <a href="<?= $baseUrl ?>/shoppingping" class="lp-btn-red">
                        <i class="bi bi-cart-fill"></i> MUA NGAY
                    </a>
                    <!--a href="/gallery" class="lp-btn-outline">
                        <i class="bi bi-play-circle"></i> XEM THÀNH QUẢ THỰC TẾ
                    </a-->
                </div>
            </div>
            <div class="lp-hero-right">
            </div>
        </div>

        <div class="lp-trust hero-trust-anim">
            <div class="lp-trust-inner">
                <div class="lp-trust-item">
                    <i class="bi bi-shield"></i>
                    <span class="lp-trust-label">Thương hiệu<br>hơn 100 năm<br>từ Hoa Kỳ</span>
                </div>
                <div class="lp-trust-item">
                    <i class="bi bi-trophy"></i>
                    <span class="lp-trust-label">Số 1<br>sơn xịt DIY<br>tại Hoa Kỳ</span>
                </div>
                <div class="lp-trust-item">
                    <i class="bi bi-check-circle"></i>
                    <span class="lp-trust-label">Chất lượng<br>cao cấp<br>đáng tin cậy</span>
                </div>
                <div class="lp-trust-item">
                    <i class="bi bi-wind"></i>
                    <span class="lp-trust-label">An toàn hơn<br>ít mùi, dễ sử dụng<br>nhanh khô</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================
     SECTION 2 — SO SÁNH VS
     ================================================================ -->
    <section class="lp-compare">
        <div class="lp-compare-inner">
            <div class="lp-compare-left sr from-left">
                <h2>Đổi màu là việc của sơn.<br><span class="green">Nâng cấp món đồ,</span><br>mới là việc của Rust-Oleum.</h2>
                <p class="lp-compare-sub">Không phải mọi lon sơn đều tạo ra cùng một thành phẩm.</p>
            </div>
            <div class="lp-vs-wrap">
                <div class="lp-vs-col bad sr from-bottom delay-1">
                    <div class="lp-vs-col-img">
                        <img src="image/ghe_before.jpeg" alt="Sơn xịt thông thường"
                            onerror="this.style.background='#bbb';this.removeAttribute('src')">
                    </div>
                    <div class="lp-vs-col-body">
                        <div class="lp-vs-title">Sơn xịt thông thường</div>
                        <ul>
                            <li><i class="bi bi-x-circle-fill xi"></i> Dễ bong tróc</li>
                            <li><i class="bi bi-x-circle-fill xi"></i> Màu không đều</li>
                            <li><i class="bi bi-x-circle-fill xi"></i> Mùi nồng khó chịu</li>
                            <li><i class="bi bi-x-circle-fill xi"></i> Độ bền thấp</li>
                            <li><i class="bi bi-x-circle-fill xi"></i> Khó lâu</li>
                        </ul>
                    </div>
                </div>
                <div class="lp-vs-mid sr scale-in delay-2">VS</div>
                <div class="lp-vs-col good sr from-bottom delay-3">
                    <div class="lp-vs-col-img">
                        <img src="image/ghe_after.jpeg" alt="Rust-Oleum"
                            onerror="this.style.background='#2d6a2d';this.removeAttribute('src')">
                    </div>
                    <div class="lp-vs-col-body">
                        <div class="lp-vs-title">Rust-Oleum</div>
                        <ul>
                            <li><i class="bi bi-check-circle-fill ok"></i> Nâng cấp món đồ</li>
                            <li><i class="bi bi-check-circle-fill ok"></i> Thành phẩm đẹp hơn</li>
                            <li><i class="bi bi-check-circle-fill ok"></i> Độ hoàn thiện cao hơn</li>
                            <li><i class="bi bi-check-circle-fill ok"></i> Đáng công sức hơn</li>
                            <li><i class="bi bi-check-circle-fill ok"></i> Ít mùi, khô nhanh</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================
     SECTION 3 — BẢNG SO SÁNH CHI TIẾT (REDESIGN)
     ================================================================ -->
    <section class="lp-table-sec">
        <div class="lp-table-sec-inner">

            <!-- Header -->
            <div class="lp-table-head sr from-bottom">
                <span class="lp-table-eyebrow">So sánh chi tiết</span>
                <h2>Tại sao cùng là sơn xịt<br>nhưng thành phẩm lại <span class="red">khác nhau?</span></h2>
                <p>Giá của một lon sơn chỉ là một phần của câu chuyện. Điều quan trọng hơn là thời gian, công sức và chất lượng thành phẩm sau cùng.</p>
            </div>

            <!-- Before / After trio -->
            <div class="lp-ba-trio-wrap sr from-bottom delay-2">
                <div class="lp-ba-trio" id="baTrio">
                    <?php
                    $baItems = [
                        ['GHẾ SẮT', 'image/ghe_mini_before.jpeg', 'image/ghe_mini_after.jpeg'],
                        ['XE ĐẠP',  'image/xe_mini_before.jpeg', 'image/xe_mini_after.jpeg'],
                        ['ĐÈN DECOR', 'image/den_mini_before.jpeg', 'image/den_mini_after.jpeg'],
                    ];
                    foreach ($baItems as [$lbl, $imgB, $imgA]): ?>
                        <div class="lp-ba-item">
                            <div class="lp-ba-item-label"><?= htmlspecialchars($lbl) ?></div>
                            <div class="lp-ba-pair" style="--percent: 50%;">
                                <img class="slider-after" src="<?= htmlspecialchars($imgA) ?>" alt="After <?= htmlspecialchars($lbl) ?>"
                                    onerror="this.style.background='#5a8a5a';this.removeAttribute('src')">
                                <div class="slider-before">
                                    <img src="<?= htmlspecialchars($imgB) ?>" alt="Before <?= htmlspecialchars($lbl) ?>"
                                        onerror="this.style.background='#aaa';this.removeAttribute('src')">
                                </div>
                                <div class="slider-handle">
                                    <div class="slider-handle-button">
                                        <i class="bi bi-chevron-left"></i>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </div>
                                <input type="range" min="0" max="100" value="50" class="slider-range" aria-label="So sánh Trước và Sau">
                                <span class="lp-ba-tag b">Before</span>
                                <span class="lp-ba-tag a">After</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Nav chỉ hiện trên mobile -->
                <div class="lp-ba-trio-nav d-flex d-md-none">
                    <button class="lp-ba-trio-btn" id="baTrioPrev" aria-label="Trước">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="lp-ba-trio-dots" id="baTrioDots">
                        <?php foreach ($baItems as $i => $_): ?>
                            <span class="lp-ba-trio-dot<?= $i === 0 ? ' active' : '' ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <button class="lp-ba-trio-btn" id="baTrioNext" aria-label="Tiếp">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <?php
            $rows = [
                ['', 'Độ hoàn thiện',       'Dễ loang màu, không đều',         'Lớp phủ mịn và đồng đều hơn',                    false, false],
                ['', 'Độ che phủ',          'Cần nhiều lớp hơn',               'Độ che phủ cao hơn',                             false, false],
                ['', 'Thời gian thi công',  'Mất nhiều thời gian hơn',         'Hoàn thiện nhanh hơn',                           false, false],
                ['', 'Mùi sơn',             'Thường nồng hơn',                 'Dễ chịu hơn trong quá trình thi công',           false, true],
                ['', 'Cảm giác khi xịt',   'Dễ mỏi tay khi sử dụng lâu',      'Đầu xịt thiết kế tối ưu, thao tác nhẹ hơn',      false, true],
                ['', 'Xịt góc khó',         'Khó thao tác',                    'Vòi xịt đa hướng, linh hoạt',                    false, true],
                ['', 'Độ bền thành phẩm',   'Dễ trầy xước, bong tróc hơn',     'Bám dính và bảo vệ bề mặt tốt hơn',              false, false],
                ['', 'Nguy cơ làm lại',     'Cao hơn',                         'Thấp hơn',                                       false, true],
                ['', 'An toàn cho gia đình', 'Khác nhau tùy sản phẩm',          'Tiêu chuẩn sản xuất, kiểm soát chất lượng Mỹ',   false, true],
                ['', 'Chi phí dài hạn',     'Có thể phát sinh làm lại',        'Tối ưu hơn nếu xét toàn bộ vòng đời sử dụng',    false, true],
                ['', 'Giá trị thành phẩm',  'Đổi màu món đồ',                  'Nâng cấp món đồ',                                true, false],
            ];
            ?>

            <!-- Comparison grid (CSS grid — robust on PC & mobile) -->
            <div class="lp-cmp">
                <div class="lp-cmp-header">
                    <div class="lp-cmp-h-crit">Tiêu chí</div>
                    <div class="lp-cmp-h-cheap"><i class="bi bi-x-circle-fill"></i> Sơn xịt giá rẻ</div>
                    <div class="lp-cmp-h-ro">
                        <i class="bi bi-x-circle"></i> RUST-OLEUM
                        <!-- <img src="img/rustoleum-logo.png" alt="Rust-Oleum" class="lp-cmp-h-logo"
                            onerror="this.outerHTML='<span><i class=&quot;bi bi-check-circle-fill&quot;></i> Rust-Oleum</span>'"> -->
                    </div>
                </div>

                <?php foreach ($rows as [$ico, $label, $cheap, $ro, $isLast, $isCollapsible]): ?>
                    <div class="lp-cmp-row<?= $isLast ? ' is-last' : '' ?><?= $isCollapsible ? ' lp-cmp-row-collapsible' : '' ?>">
                        <div class="lp-cmp-crit">
                            <span class="lp-cmp-icon"><?= $ico ?></span>
                            <span><?= htmlspecialchars($label) ?></span>
                        </div>
                        <div class="lp-cmp-cheap">
                            <span class="lp-cmp-tag tag-cheap"><i class="bi bi-x-lg"></i> Giá rẻ</span>
                            <span class="lp-cmp-txt"><?= htmlspecialchars($cheap) ?></span>
                        </div>
                        <div class="lp-cmp-ro">
                            <span class="lp-cmp-tag tag-ro"><i class="bi bi-check-lg"></i> Rust-Oleum</span>
                            <span class="lp-cmp-txt"><?= htmlspecialchars($ro) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Nút Xem thêm/Thu gọn chỉ hiện trên mobile -->
            <div class="lp-cmp-toggle-wrap text-center d-block d-md-none mt-4">
                <button type="button" class="btn btn-sm px-4 rounded-pill" id="btnCmpToggle" style="border: 2px solid #0c4c29; color: #0c4c29; font-weight: 700; background: #fff;">
                    <span>Xem thêm tiêu chí so sánh</span> <i class="bi bi-chevron-down ms-1"></i>
                </button>
            </div>

        </div>
    </section>

    <div class="lp-bottom-bar">
        <div class="lp-bottom-bar-inner">
            <div class="lp-bottom-bar-quote">
                <i class="bi bi-quote quote-icon"></i>
                <div class="lp-bottom-bar-text">
                    Đôi khi thứ đắt nhất không phải lon sơn.
                    <span>Mà là công sức phải làm lại từ đầu.</span>
                </div>
            </div>
            <div class="lp-bottom-bar-badges">
                <div class="lp-bottom-bar-badge-card">
                    <img src="img/rustoleum-logo.png" alt="Rust-Oleum" style="height:32px;object-fit:contain;"
                        onerror="this.style.display='none'">
                </div>
                <div class="lp-bottom-bar-badge-card lp-made-usa">
                    <img src="img/usa.webp" alt="USA" class="flag" style="height:28px;object-fit:contain;"
                        onerror="this.innerHTML='🇺🇸'">
                    <div class="lp-made-usa-text">
                        Made in USA
                        <small>Quality since 1921</small>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ================================================================
     SECTION 4 — DÀNH CHO AI
     ================================================================ -->
    <section class="lp-audience">
        <div class="lp-audience-inner">
            <div class="lp-audience-top">
                <div class="lp-audience-top-left sr from-left">
                    <h2>Dành cho những người<br>muốn làm một lần<br><span class="red">cho đáng công</span></h2>
                    <p>Rust-Oleum đồng hành cùng bạn tạo nên những thành phẩm bền đẹp và tự hào.</p>
                </div>
                <div class="lp-audience-top-right sr from-right">
                    <img src="image/diy_banner.png" alt="Rust-Oleum 2X spray"
                        onerror="this.style.background='#d4c9b8';this.removeAttribute('src')">
                </div>
            </div>

            <?php
            $audiences = [
                ['🏠', 'Chủ nhà',                   'Muốn làm mới đồ dùng trong nhà, tiết kiệm và đẹp hơn.',                        'image/chunha.jpeg'],
                ['❤️', 'Người yêu DIY',             'Muốn tạo ra thành phẩm đẹp và cá tính cho không gian sống của mình.',          'image/khachhang.jpeg'],
                ['☕', 'Chủ quán Cafe,Homestay,..', 'Muốn nâng cấp không gian với chi phí hợp lý, ấn tượng hơn.',                   'image/cafe.jpeg'],
                ['🔧', 'Nghệ nhân, Thợ thủ công',   'Cần độ hoàn thiện cao cho sản phẩm, bền đẹp theo thời gian.',                  'image/tho.jpeg'],
            ];
            ?>
            <div class="lp-aud-cards-wrap sr from-bottom delay-2">
                <div class="lp-aud-cards" id="audCards">
                    <?php foreach ($audiences as [$ico, $title, $desc, $img]): ?>
                        <div class="lp-aud-card">
                            <div class="lp-aud-card-img">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= strip_tags($title) ?>"
                                    onerror="this.style.background='#c8c0b0';this.removeAttribute('src')">
                            </div>
                            <div class="lp-aud-icon"><?= $ico ?></div>
                            <div class="lp-aud-card-top">
                                <h4><?= $title ?></h4>
                                <p><?= $desc ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Nav chỉ hiện trên mobile -->
                <div class="lp-aud-nav d-flex d-md-none">
                    <button class="lp-aud-nav-btn" id="audPrev" aria-label="Trước"><i class="bi bi-chevron-left"></i></button>
                    <div class="lp-aud-dots" id="audDots">
                        <?php foreach ($audiences as $i => $_): ?>
                            <span class="lp-aud-dot<?= $i === 0 ? ' active' : '' ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <button class="lp-aud-nav-btn" id="audNext" aria-label="Tiếp"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>

            <div class="lp-features">
                <div class="lp-feat-item sr from-bottom delay-1">
                    <div class="lp-feat-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <h5>Bám dính vượt trội</h5>
                        <p>Bám trên nhiều bề mặt: kim loại, gỗ, nhựa, gốm sứ...</p>
                    </div>
                </div>
                <div class="lp-feat-item sr from-bottom delay-2">
                    <div class="lp-feat-icon"><i class="bi bi-clock"></i></div>
                    <div>
                        <h5>Khô nhanh</h5>
                        <p>Tiết kiệm thời gian, làm xong là dùng ngay.</p>
                    </div>
                </div>
                <div class="lp-feat-item sr from-bottom delay-3">
                    <div class="lp-feat-icon"><i class="bi bi-cloud-rain"></i></div>
                    <div>
                        <h5>Bền màu, chống rỉ</h5>
                        <p>Chịu nắng mưa, không bong tróc, không phai màu.</p>
                    </div>
                </div>
                <div class="lp-feat-item sr from-bottom delay-4">
                    <div class="lp-feat-icon"><i class="bi bi-hand-thumbs-up"></i></div>
                    <div>
                        <h5>Dễ sử dụng</h5>
                        <p>Xịt đều, mịn, không chảy sơn, dễ thao tác.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================
     SECTION 5 — BEFORE / AFTER
     ================================================================ -->
    <section class="lp-ba-sec d-none">
        <div class="lp-ba-sec-inner">
            <div class="lp-ba-header">
                <div>
                    <h2>Mỗi món đồ đều có thể<br>có một <span class="red">cuộc đời mới</span></h2>
                    <p>Thay đổi màu sơn – Thay đổi cảm xúc – Thay đổi không gian sống.</p>
                </div>
            </div>

            <?php
            $baItems5 = [
                ['Ghế sắt',  'img/img2.png', 'img/img1.png'],
                ['Xe đạp',   'img/img3.png', 'img/img4.png'],
                ['Kệ sắt',   'img/img5.png', 'img/img6.png'],
                ['Đèn dầu',  'img/img2.png', 'img/img1.png'],
                ['Mailbox',  'img/img3.png', 'img/img4.png'],
                ['Chậu cây', 'img/img5.png', 'img/img6.png'],
            ];
            $chunks = array_chunk($baItems5, 3);
            foreach ($chunks as $chunk): ?>
                <div class="lp-ba-grid">
                    <?php foreach ($chunk as [$lbl, $imgB, $imgA]): ?>
                        <div class="lp-ba5-card">
                            <div class="lp-ba5-label"><?= htmlspecialchars($lbl) ?></div>
                            <div class="lp-ba5-pair">
                                <div class="lp-ba5-side">
                                    <span class="lp-ba5-tag before">Before</span>
                                    <img src="<?= htmlspecialchars($imgB) ?>" alt="Before"
                                        onerror="this.style.background='#aaa';this.removeAttribute('src')">
                                </div>
                                <div class="lp-ba5-arrow"><i class="bi bi-arrow-left-right"></i></div>
                                <div class="lp-ba5-side">
                                    <span class="lp-ba5-tag after">After</span>
                                    <img src="<?= htmlspecialchars($imgA) ?>" alt="After"
                                        onerror="this.style.background='#5a8a5a';this.removeAttribute('src')">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="lp-ba5-cta">
                <div class="lp-ba5-cta-left">
                    <div class="lp-ba5-cta-icon"><i class="bi bi-camera"></i></div>
                    <div>
                        <p>Hàng triệu người yêu DIY trên thế giới đã chọn Rust-Oleum.</p>
                        <span>Còn bạn thì sao?</span>
                    </div>
                </div>
                <a href="/gallery" class="lp-btn-red">
                    Xem thêm cảm hứng DIY <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- ================================================================
     SECTION 6 — KHÔNG CHỈ ĐẸP. CÒN PHẢI YÊN TÂM.
     ================================================================ -->
    <section class="lp-safety">
        <div class="lp-safety-inner">
            <div class="lp-safety-left sr from-left">
                <h2>Không chỉ đẹp.<br>Còn phải <em>yên tâm.</em></h2>
                <div class="lp-safety-list">
                    <div class="lp-safety-item">
                        <div class="lp-safety-icon leaf"><i class="bi bi-tree"></i></div>
                        <div>
                            <h4>Không chứa chì</h4>
                            <p>An toàn cho sức khỏe bản thân và gia đình.</p>
                        </div>
                    </div>
                    <div class="lp-safety-item">
                        <div class="lp-safety-icon voc">VOC</div>
                        <div>
                            <h4>VOC được kiểm soát</h4>
                            <p>Theo tiêu chuẩn Mỹ, giảm thiểu mùi khó chịu.</p>
                        </div>
                    </div>
                    <div class="lp-safety-item">
                        <div class="lp-safety-icon house"><i class="bi bi-house-heart"></i></div>
                        <div>
                            <h4>Phù hợp nhiều dự án</h4>
                            <p>Nội thất, decor, ngoài trời, đồ dùng và nhiều hơn nữa.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lp-safety-right sr from-right">
                <div class="lp-safety-img-main">
                    <img src="image/room.jpeg" alt="Góc làm việc"
                        onerror="this.style.background='#2d3a2d';this.removeAttribute('src')">
                    <div class="lp-safety-cap">
                        <strong>Góc làm việc</strong>
                        <span>Sáng tạo và truyền cảm hứng.</span>
                    </div>
                </div>
                <div class="lp-safety-img-row">
                    <div class="lp-safety-img-sm">
                        <img src="image/roomkid.jpeg" alt="Phòng trẻ em"
                            onerror="this.style.background='#3a5a7a';this.removeAttribute('src')">
                        <div class="lp-safety-cap">
                            <strong>Phòng trẻ em</strong>
                            <span>An toàn cho bé, màu sắc nuôi dưỡng trí tưởng tượng.</span>
                        </div>
                    </div>
                    <div class="lp-safety-img-sm">
                        <img src="image/studio.jpeg" alt="Quán cafe / Studio"
                            onerror="this.style.background='#4a3020';this.removeAttribute('src')">
                        <div class="lp-safety-cap">
                            <strong>Quán cafe / Studio</strong>
                            <span>Không gian ấn tượng, bền đẹp theo thời gian.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- ================================================================
     SECTION 7 — VIDEO THỰC TẾ
     ================================================================ -->
    <section class="lp-video-sec">
        <div class="lp-video-inner">
            <div class="lp-video-left sr from-left">
                <h2>Xem Rust-Oleum<br><span class="red">hoạt động thực tế</span></h2>
                <p>Hàng triệu người yêu DIY trên thế giới đã chọn Rust-Oleum.<br>Hãy xem lý do vì sao.</p>
                <a href="https://www.youtube.com/@RustOleumBrands/videos" class="lp-btn-red d-none d-md-inline-flex" style="margin-top:24px;align-self:flex-start;">
                    <i class="bi bi-play-circle-fill"></i> Xem tất cả video
                </a>
            </div>
            <div class="lp-video-right">
                <div class="lp-video-grid">
                    <?php
                    // Video YouTube thực tế của Rust-Oleum. Thêm tối đa 6 clip.
                    // Mỗi item: youtube_id, tiêu đề, mô tả, link sản phẩm (có thể để '').
                    $videos = [
                        [
                            'yt'      => 'kIpUdGlzExQ',
                            'title'   => 'Countertop Coating - Hiệu ứng vân đá',
                            'desc'    => 'Tạo vân đá cẩm thạch đẹp mắt ngay tại nhà với Rust-Oleum HOME Countertop Coating.',
                            'product' => 'https://sonxit.vn/shopping',
                        ],
                        [
                            'yt'      => 'wcDv7W0sYb4',
                            'title'   => 'Watco Tung Oil Spray',
                            'desc'    => 'Dầu Tung Oil bảo vệ và làm đẹp bề mặt gỗ, chống thấm tự nhiên.',
                            'product' => 'https://sonxit.vn/shopping',
                        ],
                        [
                            'yt'      => 'VKQA_nP1y1M',
                            'title'   => 'Watco Teak Oil Finish',
                            'desc'    => 'Hoàn thiện bề mặt gỗ teak với dầu dưỡng chuyên dụng Watco Teak Oil.',
                            'product' => 'https://sonxit.vn/shopping',
                        ],
                        [
                            'yt'      => '0Jk9kow8DzE',
                            'title'   => 'Watco Danish Oil Natural',
                            'desc'    => 'Dầu Danish Oil tự nhiên thấm sâu, bảo vệ gỗ bền lâu, tôn màu vân gỗ.',
                            'product' => 'https://sonxit.vn/shopping',
                        ],
                        [
                            'yt'      => '5MEFuJskHdU',
                            'title'   => 'HOME Designer Countertop Kit',
                            'desc'    => 'Hướng dẫn sơn mặt bàn bếp theo phong cách thiết kế cao cấp từng bước.',
                            'product' => 'https://sonxit.vn/shopping',
                        ],
                        [
                            'yt'      => 'Qx39_ZPY-h4',
                            'title'   => 'Spray Paint — Màu năm 2025',
                            'desc'    => 'Sơn xịt Rust-Oleum màu Satin Lagoon — Màu của năm 2025, thanh lịch và hiện đại.',
                            'product' => 'https://sonxit.vn/shopping',
                        ],
                        // Thêm clip mới ở đây (tối đa 6):
                        // [ 'yt' => 'VIDEO_ID', 'title' => '...', 'desc' => '...', 'product' => 'https://...' ],
                    ];
                    $videos = array_slice($videos, 0, 6);
                    foreach ($videos as $v):
                        $ytId   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($v['yt'] ?? ''));
                        $title  = (string)($v['title'] ?? '');
                        $desc   = (string)($v['desc'] ?? '');
                        $prod   = trim((string)($v['product'] ?? ''));
                        if ($ytId === '') continue;
                        $thumb  = 'https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg';
                    ?>
                        <div class="lp-video-card" data-yt="<?= htmlspecialchars($ytId) ?>" data-title="<?= htmlspecialchars($title) ?>" role="button" tabindex="0">
                            <div class="lp-video-thumb">
                                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy"
                                    onerror="this.onerror=null;this.src='https://i.ytimg.com/vi/<?= htmlspecialchars($ytId) ?>/mqdefault.jpg'">
                                <div class="lp-video-play"><i class="bi bi-play-fill"></i></div>
                                <span class="lp-video-dur"><i class="bi bi-youtube"></i></span>
                            </div>
                            <div class="lp-video-info">
                                <strong><?= htmlspecialchars($title) ?></strong>
                                <span><?= htmlspecialchars($desc) ?></span>
                                <?php if ($prod !== ''): ?>
                                    <a href="<?= htmlspecialchars($prod) ?>" class="lp-video-prodlink" onclick="event.stopPropagation();">
                                        <i class="bi bi-bag-check"></i> Xem sản phẩm
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Mobile CTA button -->
                <a href="https://www.youtube.com/@RustOleumBrands/videos" class="lp-btn-red d-flex d-md-none justify-content-center text-center mt-3" style="width: 100%;">
                    <i class="bi bi-play-circle-fill"></i> Xem tất cả video
                </a>
                <!-- <div class="lp-video-stats">
                    <div class="lp-vstat">
                        <i class="bi bi-play-circle-fill"></i>
                        <div><strong>10.000+</strong><span>Video DIY trên toàn cầu</span></div>
                    </div>
                    <div class="lp-vstat">
                        <i class="bi bi-people-fill"></i>
                        <div><strong>5M+</strong><span>Người xem mỗi tháng</span></div>
                    </div>
                    <div class="lp-vstat">
                        <i class="bi bi-heart-fill"></i>
                        <div><strong>97%</strong><span>Người dùng hài lòng</span></div>
                    </div>
                </div> -->
            </div>
        </div>
    </section>

    <!-- YouTube Lightbox (dùng chung cho section video) -->
    <div class="lp-yt-overlay" id="lpYtOverlay" aria-hidden="true">
        <div class="lp-yt-box">
            <button type="button" class="lp-yt-close" id="lpYtClose" aria-label="Đóng">&times;</button>
            <div id="lpYtFrame"></div>
        </div>
    </div>
    <script>
        (function() {
            var overlay = document.getElementById('lpYtOverlay');
            var frame = document.getElementById('lpYtFrame');
            var closeBtn = document.getElementById('lpYtClose');
            if (!overlay || !frame) return;

            function openVideo(id) {
                if (!id) return;
                frame.innerHTML = '<iframe src="https://www.youtube.com/embed/' + encodeURIComponent(id) +
                    '?autoplay=1&rel=0" title="YouTube video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
                overlay.classList.add('show');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeVideo() {
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
                frame.innerHTML = ''; // dừng phát
                document.body.style.overflow = '';
            }

            document.querySelectorAll('.lp-video-card[data-yt]').forEach(function(card) {
                card.addEventListener('click', function() {
                    openVideo(card.getAttribute('data-yt'));
                });
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openVideo(card.getAttribute('data-yt'));
                    }
                });
            });
            closeBtn.addEventListener('click', closeVideo);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeVideo();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && overlay.classList.contains('show')) closeVideo();
            });
        })();
    </script>

    <!-- ================================================================
     SECTION — GỢI Ý SẢN PHẨM (sản phẩm thật từ hệ thống)
     ================================================================ -->
    <?php if (!empty($lpSuggestProducts)): ?>
    <section class="lp-suggest-sec">
        <div class="lp-suggest-inner">
            <div class="lp-suggest-head">
                <div>
                    <div class="lp-section-badge">SẢN PHẨM NỔI BẬT</div>
                    <h2>Gợi ý cho bạn<br><span class="red">từ bộ sưu tập Hàng mới</span></h2>
                </div>
                <a href="<?= h($baseUrl) ?>/shopping" class="lp-btn-red d-none d-md-inline-flex" style="align-self:center;">
                    Xem tất cả <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div class="lp-suggest-grid">
                <?php foreach ($lpSuggestProducts as $sp):
                    $spId    = (int)($sp['id'] ?? 0);
                    $spName  = (string)($sp['product_name'] ?? '');
                    $spImg   = function_exists('app_get_media_url') ? app_get_media_url((string)($sp['image_url'] ?? '')) : (string)($sp['image_url'] ?? '');
                    $spPrice = (float)($sp['min_price'] ?? 0);
                    $spUrl   = function_exists('app_build_product_detail_link') ? app_build_product_detail_link($spId, (string)$baseUrl) : ((string)$baseUrl . '/view-product?pid=' . $spId);
                    $spPriceText = $spPrice > 0 ? number_format($spPrice, 0, ',', '.') . 'đ' : 'Liên hệ';
                ?>
                    <a href="<?= h($spUrl) ?>" class="lp-suggest-card">
                        <div class="lp-suggest-thumb">
                            <img src="<?= h($spImg) ?>" alt="<?= h($spName) ?>" loading="lazy"
                                 onerror="this.style.opacity='.4';this.src='<?= h($baseUrl) ?>/image/no-image.png'">
                            <span class="lp-suggest-tag">Hàng mới</span>
                        </div>
                        <div class="lp-suggest-body">
                            <div class="lp-suggest-name"><?= h($spName) ?></div>
                            <div class="lp-suggest-foot">
                                <span class="lp-suggest-price"><?= h($spPriceText) ?></span>
                                <span class="lp-suggest-cart"><i class="bi bi-bag-plus"></i></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <a href="<?= h($baseUrl) ?>/shopping" class="lp-btn-red d-flex d-md-none justify-content-center text-center mt-3" style="width:100%;">
                Xem tất cả sản phẩm <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- ================================================================
     SECTION 8 — DÒNG SẢN PHẨM
     ================================================================ -->
    <!-- <section class="lp-products-sec">
        <div class="lp-products-inner">
            <div class="lp-products-left">
                <h2>Không chỉ là sơn xịt.<br>Là giải pháp cho<br>từng loại bề mặt.</h2>
                <p>Từ kim loại, gỗ, nhựa, gạch men đến các hiệu ứng trang trí đặc biệt, Rust-Oleum luôn có dòng sản phẩm phù hợp cho mọi dự án DIY của bạn.</p>
                <a href="<?= $baseUrl ?>/shopping" class="lp-btn-red d-none d-md-inline-flex" style="margin-top:28px;align-self:flex-start;">
                    Xem tất cả sản phẩm <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <div class="lp-products-right">
                <?php
                $products = [
                    ['image/chongriset.webp',      'Chống gỉ sét'],
                    ['image/sonkimloai.webp',        'Sơn kim loại'],
                    ['image/songo.webp', 'Sơn gỗ'],
                    ['image/sonnhieubemat.avif',        'Sơn đa bề mặt'],
                    ['image/songachmen.webp',      'Sơn gạch men'],
                    ['image/sonhieuung.webp',    'Sơn hiệu ứng'],
                    ['image/sonbangden.png',  'Chalkboard'],
                    ['image/sonnoithat.webp',        'DIY nội thất'],
                ];
                foreach ($products as [$img, $label]): ?>
                    <div class="lp-prod-card">
                        <div class="lp-prod-img">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($label) ?>"
                                onerror="this.style.background='#2a2a2a';this.removeAttribute('src')">
                        </div>
                        <div class="lp-prod-label"><?= htmlspecialchars($label) ?></div>
                    </div>
                <?php endforeach; ?>

                <S-- Mobile CTA button --
                <a href="<?= $baseUrl ?>/shopping" class="lp-btn-red d-flex d-md-none justify-content-center text-center mt-3" style="width: 100%; grid-column: span 4;">
                    Xem tất cả sản phẩm <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </section> -->

    <!-- ================================================================
     SECTION 9 — HƠN 100 NĂM TỪ HOA KỲ (TIMELINE)
     ================================================================ -->
    <section class="lp-timeline-sec">
        <div class="lp-timeline-inner">
            <div class="lp-timeline-left sr from-left">
                <h2>Hơn 100 năm<br>từ Hoa Kỳ</h2>
                <p>Một hành trình đổi mới không ngừng để bảo vệ và làm đẹp cho mọi bề mặt.</p>
            </div>
            <?php
            $milestones = [
                ['1921',  'Thành lập từ năm 1921',                   'image/history_1.jpeg', '#3a3020'],
                ['1949',  'Đưa sơn xịt vào thị trường',              'image/history_2.jpeg', '#2a3020'],
                ['1970s', 'Tiên phong trong dòng sơn chống gỉ',      'image/history_3.jpeg', '#203030'],
                ['2000s', 'Không ngừng đổi mới và mở rộng toàn cầu', 'image/history_4.jpeg', '#202040'],
                ['2026',  'Tin dùng tại hơn 100 quốc gia',           'image/history_4.jpeg', '#1a2030'],
            ];
            ?>
            <div class="lp-timeline-right">
                <!-- PC: grid ảnh + timeline track ngang -->
                <div class="lp-timeline-imgs lp-pc-only sr from-bottom delay-1">
                    <?php foreach ($milestones as [$y, $d, $img, $fb]): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($y) ?>"
                            onerror="this.style.background='<?= $fb ?>';this.removeAttribute('src')">
                    <?php endforeach; ?>
                </div>
                <div class="lp-timeline-track-wrap lp-pc-only sr from-bottom delay-2">
                    <div class="lp-timeline-track">
                        <?php foreach ($milestones as [$year, $desc]): ?>
                            <div class="lp-milestone">
                                <div class="lp-milestone-dot"></div>
                                <div class="lp-milestone-year"><?= htmlspecialchars($year) ?></div>
                                <div class="lp-milestone-desc"><?= htmlspecialchars($desc) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Mobile: carousel gộp ảnh + milestone -->
                <div class="lp-tl-carousel-wrap lp-mobile-only">
                    <div class="lp-tl-carousel" id="tlCarousel">
                        <?php foreach ($milestones as [$year, $desc, $img, $fb]): ?>
                            <div class="lp-tl-slide">
                                <div class="lp-tl-slide-img">
                                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($year) ?>"
                                        onerror="this.style.background='<?= $fb ?>';this.removeAttribute('src')">
                                </div>
                                <div class="lp-tl-slide-body">
                                    <div class="lp-milestone-dot"></div>
                                    <div class="lp-milestone-year"><?= htmlspecialchars($year) ?></div>
                                    <div class="lp-milestone-desc"><?= htmlspecialchars($desc) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="lp-tl-nav">
                        <button class="lp-tl-btn" id="tlPrev" aria-label="Trước"><i class="bi bi-chevron-left"></i></button>
                        <div class="lp-tl-dots" id="tlDots">
                            <?php foreach ($milestones as $i => $_): ?>
                                <span class="lp-tl-dot<?= $i === 0 ? ' active' : '' ?>"></span>
                            <?php endforeach; ?>
                        </div>
                        <button class="lp-tl-btn" id="tlNext" aria-label="Tiếp"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>

                <div class="lp-made-usa-badge">
                    <img src="img/usa.webp" alt="USA" style="height:36px;object-fit:contain;"
                        onerror="this.style.display='none'">
                    <div>
                        <strong>Made in USA</strong>
                        <span>Quality since 1921</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================
     SECTION 10 — VÌ SAO MUA TẠI PAINT & MORE
     ================================================================ -->
    <section class="lp-why-sec">
        <div class="lp-why-inner">
            <div class="lp-why-left sr from-left">
                <h2>Vì sao mua tại<br><span class="red">Paint &amp; More?</span></h2>
                <div class="lp-why-badges">
                    <div class="lp-why-badge">
                        <div class="lp-why-icon"><i class="bi bi-patch-check-fill"></i></div>
                        <div>
                            <strong>Hàng chính hãng</strong>
                            <span>Cam kết 100% sản phẩm chính hãng từ Rust-Oleum.</span>
                        </div>
                    </div>
                    <div class="lp-why-badge">
                        <div class="lp-why-icon"><i class="bi bi-headset"></i></div>
                        <div>
                            <strong>Tư vấn kỹ thuật</strong>
                            <span>Đội ngũ am hiểu sản phẩm, tư vấn đúng nhu cầu.</span>
                        </div>
                    </div>
                    <div class="lp-why-badge">
                        <div class="lp-why-icon"><i class="bi bi-truck"></i></div>
                        <div>
                            <strong>Giao hàng toàn quốc</strong>
                            <span>Giao nhanh – đóng gói kỹ, đến mọi tỉnh thành.</span>
                        </div>
                    </div>
                    <div class="lp-why-badge">
                        <div class="lp-why-icon"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <strong>Hỗ trợ chọn đúng SP</strong>
                            <span>Hướng dẫn chọn đúng sản phẩm cho từng bề mặt & mục đích.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lp-why-right">
                <div class="lp-why-img-card lp-why-img-showroom sr from-right">
                    <img src="image/showroom.png" alt="Showroom"
                        onerror="this.style.background='#c8b89a';this.removeAttribute('src')">
                    <div class="lp-why-img-label">Showroom</div>
                </div>
                <div class="lp-why-img-card lp-why-img-storage">
                    <img src="image/stronge.png" alt="Kho hàng"
                        onerror="this.style.background='#8aa0b0';this.removeAttribute('src')">
                    <div class="lp-why-img-label">Kho hàng</div>
                </div>
                <div class="lp-why-img-card lp-why-img-staff">
                    <img src="image/staff.png" alt="Đội ngũ tư vấn"
                        onerror="this.style.background='#a0b8c0';this.removeAttribute('src')">
                    <div class="lp-why-img-label">Đội ngũ tư vấn</div>
                </div>
            </div>
        </div>
        <!-- Final CTA -->
        <div class="lp-final-cta">
            <a href="<?= $baseUrl ?>/shopping" class="lp-btn-red" style="font-size:15px;padding:16px 36px;">
                <i class="bi bi-cart3"></i> Mua sản phẩm ngay
            </a>
            <a href="/contact" class="lp-btn-outline" style="font-size:15px;padding:16px 36px;">
                <i class="bi bi-telephone"></i> Liên hệ tư vấn
            </a>
        </div>
    </section>

    <!-- Sticky CTA Bar - Mobile only (hidden on desktop via CSS) -->
    <div class="lp-sticky-cta d-flex d-md-none" id="lpStickyCta">
        <a href="<?= $baseUrl ?>/shopping" class="lp-sticky-buy">
            <i class="bi bi-cart-fill"></i> Mua ngay
        </a>
        <a href="/contact" class="lp-sticky-contact">
            <i class="bi bi-headset"></i> Tư vấn
        </a>
    </div>

</div><!-- .lp-page -->

<script>
    $(function() {

        /* ── Before/After sliders ── */
        $('.lp-ba-pair').each(function() {
            const $container = $(this);
            const $range = $container.find('.slider-range');
            $range.on('input change', function() {
                $container.css('--percent', $(this).val() + '%');
            });
        });

        /* ── Comparison Table Toggle (mobile only) ── */
        $('#btnCmpToggle').on('click', function() {
            const $table = $('.lp-cmp');
            const isExpanded = $table.hasClass('lp-cmp-expanded');
            if (isExpanded) {
                $table.removeClass('lp-cmp-expanded');
                $(this).find('span').text('Xem thêm tiêu chí so sánh');
                $(this).find('i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
                $('html, body').animate({
                    scrollTop: $table.offset().top - 80
                }, 300);
            } else {
                $table.addClass('lp-cmp-expanded');
                $(this).find('span').text('Thu gọn so sánh');
                $(this).find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            }
        });

        /* ── Audience Cards carousel nav (mobile only) ── */
        (function() {
            const $track = $('#audCards');
            const $prev = $('#audPrev');
            const $next = $('#audNext');
            const $dots = $('#audDots .lp-aud-dot');
            let current = 0;
            const total = $dots.length;

            function scrollTo(idx) {
                if (idx < 0) idx = total - 1;
                if (idx >= total) idx = 0;
                current = idx;
                const item = $track.find('.lp-aud-card')[current];
                if (item) {
                    $track[0].scrollTo({
                        left: item.offsetLeft - 16,
                        behavior: 'smooth'
                    });
                }
                $dots.removeClass('active').eq(current).addClass('active');
            }

            $prev.on('click', function() {
                scrollTo(current - 1);
            });
            $next.on('click', function() {
                scrollTo(current + 1);
            });

            $track.on('scroll', function() {
                const items = $track.find('.lp-aud-card');
                let closest = 0,
                    minDist = Infinity;
                items.each(function(i) {
                    const dist = Math.abs(this.offsetLeft - $track[0].scrollLeft - 16);
                    if (dist < minDist) {
                        minDist = dist;
                        closest = i;
                    }
                });
                if (closest !== current) {
                    current = closest;
                    $dots.removeClass('active').eq(current).addClass('active');
                }
            });
        })();

        /* ── BA Trio carousel nav (mobile only) ── */
        (function() {
            const $track = $('#baTrio');
            const $prev = $('#baTrioPrev');
            const $next = $('#baTrioNext');
            const $dots = $('#baTrioDots .lp-ba-trio-dot');
            let current = 0;
            const total = $dots.length;

            function scrollTo(idx) {
                if (idx < 0) idx = total - 1;
                if (idx >= total) idx = 0;
                current = idx;

                const item = $track.find('.lp-ba-item')[current];
                if (item) {
                    $track[0].scrollTo({
                        left: item.offsetLeft - 16,
                        behavior: 'smooth'
                    });
                }
                $dots.removeClass('active').eq(current).addClass('active');
            }

            $prev.on('click', function() {
                scrollTo(current - 1);
            });
            $next.on('click', function() {
                scrollTo(current + 1);
            });

            // Sync dots khi user vuốt tay
            $track.on('scroll', function() {
                const items = $track.find('.lp-ba-item');
                let closest = 0,
                    minDist = Infinity;
                items.each(function(i) {
                    const dist = Math.abs(this.offsetLeft - $track[0].scrollLeft - 16);
                    if (dist < minDist) {
                        minDist = dist;
                        closest = i;
                    }
                });
                if (closest !== current) {
                    current = closest;
                    $dots.removeClass('active').eq(current).addClass('active');
                }
            });
        })();

        /* ── Timeline carousel nav (mobile only) ── */
        (function() {
            const $track = $('#tlCarousel');
            const $prev = $('#tlPrev');
            const $next = $('#tlNext');
            const $dots = $('#tlDots .lp-tl-dot');
            let current = 0;
            const total = $dots.length;

            function scrollTo(idx) {
                if (idx < 0) idx = total - 1;
                if (idx >= total) idx = 0;
                current = idx;
                const item = $track.find('.lp-tl-slide')[current];
                if (item) {
                    $track[0].scrollTo({
                        left: item.offsetLeft - 16,
                        behavior: 'smooth'
                    });
                }
                $dots.removeClass('active').eq(current).addClass('active');
            }

            $prev.on('click', function() {
                scrollTo(current - 1);
            });
            $next.on('click', function() {
                scrollTo(current + 1);
            });

            $track.on('scroll', function() {
                const items = $track.find('.lp-tl-slide');
                let closest = 0,
                    minDist = Infinity;
                items.each(function(i) {
                    const dist = Math.abs(this.offsetLeft - $track[0].scrollLeft - 16);
                    if (dist < minDist) {
                        minDist = dist;
                        closest = i;
                    }
                });
                if (closest !== current) {
                    current = closest;
                    $dots.removeClass('active').eq(current).addClass('active');
                }
            });
        })();
    });

    /* ── Scroll Reveal: Intersection Observer ── */
    (function () {
        var els = document.querySelectorAll('.sr');
        if (!els.length) return;

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    io.unobserve(entry.target); // fire once
                }
            });
        }, { threshold: 0.12 });

        els.forEach(function (el) { io.observe(el); });
    })();
</script>

<?php
/**
 * Cấu hình thông tin liên lạc (Sao chép từ root foot.php)
 */
$contact_phone     = $site_hotline;
$contact_zalo      = $site_hotline;
$contact_messenger = 'paintandmoreasia';

// Chỉ hiển thị contact-buttons-fixed khi không có tham số loại trừ
$_diy_showContact = (
    !isset($_GET['normal']) &&
    !isset($_GET['user']) &&
    !isset($_GET['ithanhloc']) &&
    !isset($_GET['ghn'])
);
?>

<?php if ($_diy_showContact): ?>
<!-- Contact Button Styles -->
<style>
    .contact-buttons-fixed {
        position: fixed;
        bottom: 60px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .contact-item {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background-color: #fff;
        padding: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        background: #fff;
        overflow: hidden;
    }

    .contact-item:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    .contact-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Hiệu ứng rung cho nút gọi */
    .contact-item.phone-btn {
        animation: quick-shake 2s infinite;
    }

    @keyframes quick-shake {
        0%, 100% { transform: rotate(0); }
        10%, 30%, 50% { transform: rotate(-10deg); }
        20%, 40%, 60% { transform: rotate(10deg); }
    }

    @media (max-width: 600px) {
        .contact-buttons-fixed {
            bottom: 80px; /* Tránh đè lên thanh lp-sticky-cta trên mobile */
            right: 16px;
            gap: 10px;
        }
        .contact-item {
            width: 40px;
            height: 40px;
            padding: 5px;
        }
    }
</style>

<!-- Contact Button HTML -->
<div class="contact-buttons-fixed">
    <!-- Messenger -->
    <?php if ($contact_messenger): ?>
    <a href="https://m.me/<?php echo htmlspecialchars($contact_messenger, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="contact-item" title="Chat Messenger">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/be/Facebook_Messenger_logo_2020.svg" alt="Messenger">
    </a>
    <?php endif; ?>

    <!-- Zalo -->
    <?php if ($contact_zalo): ?>
    <a href="https://zalo.me/<?php echo htmlspecialchars($contact_zalo, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="contact-item" title="Chat Zalo">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/91/Icon_of_Zalo.svg/250px-Icon_of_Zalo.svg.png" alt="Zalo">
    </a>
    <?php endif; ?>

    <!-- Phone -->
    <?php if ($contact_phone): ?>
    <a href="tel:<?php echo htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8'); ?>" class="contact-item phone-btn" title="Gọi ngay">
        <img src="https://cdn-icons-png.flaticon.com/512/724/724664.png" alt="Phone">
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include_once __DIR__ . '/foot.php'; ?>