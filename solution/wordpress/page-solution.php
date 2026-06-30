<?php

/**
 * Template Name: Architect/Solution/ 12 Giải Pháp Sơn
 *
 * Landing page "OneCoat Solution Kit 2026" — nhúng vào theme paintandmore.vn.
 * Dùng cho Page có URL /architect/solution (xem hướng dẫn trong HUONG-DAN-DEPLOY.md).
 *
 * CÁCH DÙNG:
 *  1. Copy file này vào thư mục theme đang dùng (vd wp-content/themes/<ten-theme>/).
 *  2. Copy solution-lp.css vào wp-content/themes/<ten-theme>/assets/ (hoặc nơi tuỳ ý —
 *     nhớ sửa đường dẫn trong wp_enqueue_style bên dưới cho khớp).
 *  3. Copy thư mục ảnh img/ vào wp-content/themes/<ten-theme>/assets/solution-img/
 *     (hoặc upload qua Media Library rồi sửa $img_base bên dưới).
 *  4. Trong WP Admin → Pages, tạo Page "Solution" có Template = "Architect - Solution LP".
 *
 * @package paintandmore
 */

if (!defined('ABSPATH')) {
    exit;
} // chặn truy cập trực tiếp

get_header();

/* Đường dẫn ảnh trong child theme */
$img_base = get_stylesheet_directory_uri() . '/assets/solution-img';
?>
<!-- Font + Icons qua CDN -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Dancing+Script:wght@600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style id="lp-sol-inline-css">
    /* =====================================================================
   OneCoat Solution LP — STANDALONE (deploy WordPress)
   viết theo MOBILE-FIRST
   Rule cơ sở (không media query) = điện thoại.
   @media (min-width:601px)  → tablet
   @media (min-width:901px)  → desktop (khôi phục layout 2 cột)
   ===================================================================== */
    .lp-sol {
        --c-dark: #0c4c29;
        --c-darker: #062417;
        --c-accent: #FFA827;
        --c-accent2: #ff8a00;
        --c-ink: #0e1a14;
        --c-line: #e3ebe6;
        --c-grid: rgba(255, 255, 255, .06);
        font-family: 'Montserrat', sans-serif;
        color: var(--c-ink);
        background: #fff;
        overflow-x: hidden;
    }

    .lp-sol *,
    .lp-sol *::before,
    .lp-sol *::after {
        box-sizing: border-box;
    }

    .lp-sol .wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 16px;
        position: relative;
        z-index: 2;
    }

    .lp-sol section {
        padding: 56px 0;
        position: relative;
    }

    .lp-sol .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 11.5px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--c-accent2);
        margin-bottom: 18px;
    }

    .lp-sol .eyebrow::before {
        content: "";
        width: 34px;
        height: 2px;
        background: var(--c-accent);
        display: inline-block;
    }

    .lp-sol h2.sec-title {
        font-size: clamp(26px, 2vw, 40px);
        font-weight: 900;
        line-height: 1.12;
        margin: 0 0 16px;
        letter-spacing: -.015em;
    }

    .lp-sol .sec-sub {
        font-size: 15px;
        color: #5a6b62;
        line-height: 1.65;
        max-width: 680px;
        margin: 0 0 8px;
    }

    .lp-sol .btn-accent {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        justify-content: center;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, var(--c-accent), var(--c-accent2));
        color: #1a1200;
        font-weight: 800;
        font-size: 13.5px;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 16px 32px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: .25s;
        box-shadow: 0 8px 22px rgba(255, 138, 0, .35);
    }

    .lp-sol .btn-accent:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 30px rgba(255, 138, 0, .5);
    }

    .lp-sol .btn-accent::after {
        content: "";
        position: absolute;
        top: 0;
        left: -120%;
        width: 60%;
        height: 100%;
        background: linear-gradient(120deg, transparent, rgba(255, 255, 255, .55), transparent);
        transform: skewX(-20deg);
        transition: .6s;
    }

    .lp-sol .btn-accent:hover::after {
        left: 130%;
    }

    .lp-sol .btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        justify-content: center;
        background: transparent;
        color: #fff;
        font-weight: 700;
        font-size: 13.5px;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 16px 28px;
        border-radius: 10px;
        border: 1.5px solid rgba(255, 255, 255, .35);
        text-decoration: none;
        transition: .25s;
    }

    .lp-sol .btn-ghost:hover {
        background: rgba(255, 255, 255, .1);
        border-color: #fff;
    }

    /* ===== Chữ ký nghệ thuật "Trung tâm Giải pháp OneCoat" ===== */
    .lp-sol .lp-center-badge {
        flex: 0 0 100%;
        display: flex;
        justify-content: center;
        margin: 0 0 10px;
    }

    .lp-sol .lcb-signature {
        font-family: 'Dancing Script', cursive;
        font-weight: 700;
        font-size: clamp(22px, 6.5vw, 69px);
        line-height: 1.2;
        white-space: nowrap;
        background: linear-gradient(110deg, #fff 10%, var(--c-accent) 55%, #ffe0a0 100%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        filter: drop-shadow(0 2px 12px rgba(255, 168, 39, .35));
        animation: sigFloat 6s ease-in-out infinite;
        letter-spacing: 0.01em;
        position: relative; /* cần thiết để ::after vẫn làm việc */
        overflow: hidden;   /* clip shooting star trong biên chữ */
    }

    /* Vệt sao băng vụt lên (bottom-left → top-right) */
    .lp-sol .lcb-signature::after {
        content: '';
        position: absolute;
        top: 120%;          /* xuất phát từ phía dưới */
        left: -20%;
        width: 28%;
        height: 180%;       /* dài hơn để trông như vệt sáng xiên */
        background: linear-gradient(
            to top right,
            transparent 10%,
            rgba(255, 255, 255, .95) 50%,
            rgba(255, 215, 80, .6) 62%,
            transparent 85%
        );
        transform: rotate(-30deg); /* xiên 30° như quỹ đạo sao băng */
        animation: lpShootStar 4.5s ease-in-out 2s infinite;
        pointer-events: none;
    }

    @keyframes lpShootStar {
        0%        { top: 130%; left: -30%; opacity: 0;   }
        7%        {                        opacity: 1;   }
        38%       { top: -60%; left: 110%; opacity: .9;  }
        40%, 100% { top: -60%; left: 110%; opacity: 0;   }
    }

    @keyframes sigFloat {
        0%, 100% { transform: translateY(0);   opacity: .88; }
        50%       { transform: translateY(-3px); opacity: 1;   }
    }

    /* ===== TYPEWRITER — h1 + lead ===== */
    /* Các span dòng chữ gõ từng ký tự từ trái sang phải qua clip-path */
    .lp-tw {
        display: inline-block;
        overflow: hidden;
        white-space: nowrap;
        vertical-align: bottom;
        clip-path: inset(0 100% 0 0);
        animation: lpRevealType 1.8s cubic-bezier(.1, .5, .2, 1) both;
    }
    .lp-tw1 { animation-delay: 0.6s; }
    .lp-tw2 { animation-delay: 2.2s; }

    /* Con trỏ nhấp nháy gắn cuối dòng accent */
    .lp-tw2::after {
        content: '|';
        display: inline-block;
        -webkit-text-fill-color: var(--c-accent);
        color: var(--c-accent);
        animation: lpCursor .75s step-end infinite;
        margin-left: 2px;
        font-weight: 300;
    }

    /* Lead paragraph — reveal khối văn bản từ trái sang */
    .lp-tw-block {
        clip-path: inset(0 100% 0 0);
        animation: lpRevealType 1.5s cubic-bezier(.1, .5, .2, 1) 3.8s both;
    }

    @keyframes lpRevealType {
        from { clip-path: inset(0 100% 0 0); }
        to   { clip-path: inset(0 0%   0 0); }
    }

    @keyframes lpCursor {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0; }
    }

    @media (prefers-reduced-motion:reduce) {
        .lp-sol .lcb-signature,
        .lp-sol .lcb-signature::after { animation: none; }
        .lp-tw, .lp-tw-block { clip-path: none; animation: none; }
        .lp-tw2::after { animation: none; }
    }

    /* desktop: căn trái khớp mép nút CTA */
    @media (min-width:901px) {
        .lp-sol .lp-sol-hero .hero-cta .lp-center-badge {
            justify-content: flex-start;
        }
    }

    /* ===== scroll reveal ===== */
    .lp-sol .reveal {
        opacity: 0;
        transform: translateY(34px);
        transition: opacity .7s cubic-bezier(.2, .7, .2, 1), transform .7s cubic-bezier(.2, .7, .2, 1);
    }

    .lp-sol .reveal.in {
        opacity: 1;
        transform: none;
    }

    .lp-sol .reveal.d1 {
        transition-delay: .08s;
    }

    .lp-sol .reveal.d2 {
        transition-delay: .16s;
    }

    .lp-sol .reveal.d3 {
        transition-delay: .24s;
    }

    .lp-sol .reveal.d4 {
        transition-delay: .32s;
    }

    @media (prefers-reduced-motion:reduce) {
        .lp-sol .reveal {
            opacity: 1 !important;
            transform: none !important;
        }
    }

    /* ================= SECTION 1 — HERO ================= */
    .lp-sol-hero {
        background: linear-gradient(160deg, var(--c-darker) 0%, var(--c-dark) 100%);
        color: #fff;
        padding: 72px 0 56px;
        overflow: hidden;
        text-align: center;
    }

    /* ảnh nền hero (hiển thị rõ, chỉ phủ overlay nhẹ để chữ trắng đọc được) */
    .lp-hero-bg {
        position: absolute;
        inset: 0;
        z-index: 1;
        background-size: cover;
        background-position: center;
        opacity: 1;
        transform: scale(1.06);
        animation: heroZoom 18s ease-in-out infinite alternate;
    }

    .lp-hero-bg::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg, rgba(6, 36, 23, .86) 0%, rgba(6, 36, 23, .62) 42%, rgba(6, 36, 23, .18) 100%);
    }

    @keyframes heroZoom {
        from {
            transform: scale(1.06)
        }

        to {
            transform: scale(1.14)
        }
    }

    @media (prefers-reduced-motion:reduce) {
        .lp-hero-bg {
            animation: none;
        }
    }

    /* lưới blueprint + vệt sáng động */
    .lp-sol-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 0;
        background-image: linear-gradient(var(--c-grid) 1px, transparent 1px), linear-gradient(90deg, var(--c-grid) 1px, transparent 1px);
        background-size: 46px 46px;
        mask-image: radial-gradient(900px 500px at 75% 0%, #000, transparent 75%);
    }

    .lp-sol-hero::after {
        content: "";
        position: absolute;
        top: -20%;
        right: -10%;
        width: 680px;
        height: 680px;
        z-index: 0;
        background: radial-gradient(circle, rgba(255, 168, 39, .22), transparent 60%);
        filter: blur(10px);
        animation: floatGlow 9s ease-in-out infinite;
    }

    @keyframes floatGlow {

        0%,
        100% {
            transform: translate(0, 0)
        }

        50% {
            transform: translate(-30px, 30px)
        }
    }

    .lp-sol-hero .wrap {
        display: grid;
        grid-template-columns: 1fr;
        gap: 36px;
        align-items: center;
    }

    .lp-badge-row {
        display: inline-flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        justify-content: center;
    }

    .lp-badge-row .bd {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
        background: rgba(255, 255, 255, .07);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .16);
        padding: 7px 13px;
        border-radius: 30px;
        backdrop-filter: blur(6px);
    }

    .lp-badge-row .bd i {
        color: var(--c-accent);
        font-size: 12px;
    }

    .lp-sol-hero h1 {
        font-size: clamp(28px, 8.5vw, 52px);
        font-weight: 900;
        line-height: 1.1;
        margin: 0 0 20px;
        letter-spacing: -.025em;
        text-wrap: balance;
    }

    .lp-sol-hero h1 .accent {
        position: relative;
        display: inline-block;
        background: linear-gradient(95deg, var(--c-accent) 10%, #ffcf7a 90%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .lp-sol-hero h1 .accent::after {
        content: "";
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: -8px;
        width: 264px;
        height: 3px;
        border-radius: 3px;
        background: linear-gradient(90deg, transparent, var(--c-accent), transparent);
    }

    .lp-sol-hero .lead {
        font-size: 15px;
        line-height: 1.7;
        opacity: .85;
        margin: 14px auto 26px;
        max-width: 520px;
    }

    .lp-sol-hero .hero-cta {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 30px;
    }

    .lp-sol-hero .hero-cta .btn-accent,
    .lp-sol-hero .hero-cta .btn-ghost {
        width: 100%;
    }

    .lp-sol-hero .hero-stats {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .lp-sol-hero .hero-stats .st {
        flex: 1 1 0;
        min-width: 0;
        padding: 14px 8px;
        border-radius: 14px;
        background: rgba(255, 255, 255, .05);
        border: 1px solid rgba(255, 255, 255, .1);
    }

    .lp-sol-hero .hero-stats .st b {
        display: block;
        font-size: clamp(22px, 6vw, 30px);
        font-weight: 900;
        color: var(--c-accent);
        line-height: 1;
    }

    .lp-sol-hero .hero-stats .st span {
        display: block;
        margin-top: 6px;
        font-size: 11px;
        opacity: .72;
        font-weight: 600;
        line-height: 1.3;
    }

    /* khung video 9:16 nghiêng + viền sáng */
    .lp-video-wrap {
        position: relative;
        width: fit-content;
        max-width: 100%;
        margin: 0 auto;
    }

    /* vầng sáng gradient bao quanh khung video */
    .lp-video-wrap::before {
        content: "";
        position: absolute;
        inset: -14px;
        z-index: 0;
        border-radius: 30px;
        background: radial-gradient(closest-side, rgba(255, 168, 39, .35), transparent 75%);
        filter: blur(18px);
    }

    .lp-sol-video {
        position: relative;
        z-index: 1;
        height: 560px;
        /* Cố định chiều cao lớn nhất cho PC */
        width: 315px;
        border-radius: 22px;
        overflow: hidden;
        background: #000;
        box-shadow: 0 30px 70px rgba(0, 0, 0, .5);
        border: 4px solid rgba(255, 255, 255, .12);
        /* Để phẳng (không nghiêng 3D) → video to & rõ, không bị thu nhỏ/méo. */
        transition: transform .35s ease, box-shadow .35s ease;
        margin: 0 auto;
    }

    @media (max-width: 900px) {
        .lp-sol-video {
            /* mobile/tablet: 480×270 (9:16) — cố định cứng */
            height: 480px;
            width: 270px;
        }
    }

    @media (max-width: 360px) {
        .lp-sol-video {
            /* màn siêu nhỏ: 400×225 (9:16) */
            height: 400px;
            width: 225px;
        }
    }

    .lp-video-wrap:hover .lp-sol-video {
        transform: translateY(-4px);
        box-shadow: 0 36px 80px rgba(0, 0, 0, .55);
    }

    .lp-sol-video video,
    .lp-sol-video img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .lp-sol-video .video-ph {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        color: rgba(255, 255, 255, .78);
        text-align: center;
        padding: 24px;
        font-size: 13px;
        font-weight: 600;
        background: repeating-linear-gradient(45deg, #0e2a1c, #0e2a1c 16px, #103222 16px, #103222 32px);
    }

    .lp-sol-video .video-ph .pbtn {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--c-accent);
        color: #1a1200;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        box-shadow: 0 0 0 0 rgba(255, 168, 39, .6);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 168, 39, .55)
        }

        70% {
            box-shadow: 0 0 0 22px rgba(255, 168, 39, 0)
        }

        100% {
            box-shadow: 0 0 0 0 rgba(255, 168, 39, 0)
        }
    }

    .lp-vtag {
        position: absolute;
        z-index: 2;
        left: -12px;
        top: 20px;
        background: var(--c-accent);
        color: #1a1200;
        font-weight: 800;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .06em;
        padding: 7px 14px;
        border-radius: 8px;
        box-shadow: 0 8px 18px rgba(0, 0, 0, .3);
    }

    /* dải video: CUỘN NGANG 1 hàng, thumbnail nhỏ — gọn, không chiếm diện tích */
    .lp-hero-strip {
        position: relative;
        z-index: 2;
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
        margin-top: 16px;
        overflow-x: auto;
        padding: 6px 4px 10px;
        scroll-snap-type: x proximity;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        /* Firefox: ẩn scrollbar */
    }

    .lp-hero-strip::-webkit-scrollbar {
        display: none;
        /* Chrome/Safari: ẩn scrollbar */
    }

    .lp-hero-strip .pic {
        width: 72px;
        height: 44px;
        border-radius: 8px;
        background: #000;
        padding: 0;
        flex: 0 0 auto;
        overflow: hidden;
        box-shadow: 0 6px 14px rgba(0, 0, 0, .3);
        transition: .2s;
        scroll-snap-align: start;
        /* reset button */
        border: 2px solid transparent;
        cursor: pointer;
        outline: none;
        opacity: .6;
    }

    .lp-hero-strip .pic:hover {
        opacity: 1;
        transform: translateY(-2px);
    }

    /* video đang chọn → sáng rõ + viền cam */
    .lp-hero-strip .pic.active {
        border-color: var(--c-accent);
        opacity: 1;
        box-shadow: 0 8px 18px rgba(255, 168, 39, .45);
    }

    /* YouTube iframe lấp đầy khung video dọc 9:16 */
    .lp-sol-video #lpYtPlayer,
    .lp-sol-video #lpYtPlayer iframe {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
    }

    .lp-hero-strip .pic img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        border-radius: 6px;
    }

    .lp-video-title {
        text-align: center;
        margin-top: 12px;
        font-size: 12.5px;
        font-weight: 700;
        color: #fff;
        line-height: 1.4;
        min-height: 34px;
        padding: 0 8px;
        opacity: .9;
    }

    /* ===== Carousel: nút prev/next 2 bên khung video ===== */
    .lp-vnav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 3;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        background: rgba(0, 0, 0, .45);
        color: #fff;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        backdrop-filter: blur(4px);
        transition: background .2s, opacity .25s, transform .25s;
        opacity: 0;           /* ẩn mặc định */
        pointer-events: none; /* không chặn click khi ẩn */
    }

    /* Hiện nút khi hover vào khung video */
    .lp-sol-video:hover .lp-vnav {
        opacity: 1;
        pointer-events: auto;
    }

    .lp-vnav:hover {
        background: var(--c-accent);
        color: #1a1200;
        transform: translateY(-50%) scale(1.1);
    }

    .lp-vnav.prev {
        left: 8px;
    }

    .lp-vnav.next {
        right: 8px;
    }

    /* ===== Carousel: dots chỉ báo ===== */
    .lp-video-dots {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 7px;
        margin-top: 12px;
        padding: 0 8px;
    }

    .lp-video-dots .dot {
        width: 8px;
        height: 8px;
        padding: 0;
        border: none;
        border-radius: 50%;
        background: rgba(255, 255, 255, .3);
        cursor: pointer;
        transition: background .2s, transform .2s, width .2s;
    }

    .lp-video-dots .dot:hover {
        background: rgba(255, 255, 255, .6);
    }

    .lp-video-dots .dot.active {
        width: 22px;
        border-radius: 5px;
        background: var(--c-accent);
    }

    /* dải marquee 12 case */
    .lp-marquee {
        margin-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, .12);
        border-bottom: 1px solid rgba(255, 255, 255, .12);
        overflow: hidden;
        position: relative;
        z-index: 2;
    }

    .lp-marquee .track {
        display: flex;
        gap: 40px;
        white-space: nowrap;
        width: max-content;
        animation: scrollX 28s linear infinite;
        padding: 16px 0;
    }

    .lp-sol-hero:hover .lp-marquee .track {
        animation-play-state: paused;
    }

    .lp-marquee .mi {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: 13px;
        font-weight: 700;
        opacity: .85;
    }

    .lp-marquee .mi i {
        color: var(--c-accent);
        font-size: 16px;
    }

    @keyframes scrollX {
        to {
            transform: translateX(-50%);
        }
    }

    /* ================= HỆ SƠN ÁP DỤNG CHO (bề mặt công trình) ================= */
    .lp-apply {
        background: linear-gradient(160deg, #0a1f15, #0e2a1c);
        color: #fff;
        position: relative;
        overflow: hidden;
    }

    .lp-apply::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 0;
        background-image: linear-gradient(var(--c-grid) 1px, transparent 1px), linear-gradient(90deg, var(--c-grid) 1px, transparent 1px);
        background-size: 46px 46px;
        mask-image: radial-gradient(800px 500px at 80% 0, #000, transparent 75%);
    }

    .lp-apply .wrap {
        position: relative;
        z-index: 2;
    }

    /* ===== LƯỚI giải pháp — mobile 2 cột (gọn), desktop 3 cột ===== */
    .lp-apply-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 28px;
    }

    .lp-apply-card {
        border-radius: 14px;
        background: rgba(255, 255, 255, .04);
        border: 1px solid rgba(255, 255, 255, .09);
        padding: 16px 14px;
        transition: .25s;
    }

    .lp-apply-card:hover {
        background: rgba(255, 255, 255, .07);
        border-color: rgba(255, 168, 39, .4);
    }

    .lp-apply-card .ac-ico {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        background: rgba(255, 168, 39, .14);
        color: var(--c-accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin: 0 0 12px;
        border: 1px solid rgba(255, 168, 39, .3);
    }

    .lp-apply-card h3 {
        font-size: 14px;
        font-weight: 800;
        margin: 0 0 5px;
        line-height: 1.3;
    }

    .lp-apply-card p {
        font-size: 12px;
        line-height: 1.45;
        opacity: .78;
        margin: 0;
    }

    /* ẩn dấu vết carousel cũ (nếu còn) */
    .lp-apply-dots {
        display: none;
    }

    .lp-apply-note {
        margin-top: 32px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        background: rgba(255, 168, 39, .1);
        border: 1px solid rgba(255, 168, 39, .3);
        border-radius: 12px;
        padding: 16px 20px;
        font-size: 13.5px;
        line-height: 1.55;
    }

    .lp-apply-note i {
        color: var(--c-accent);
        font-size: 20px;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .lp-apply-note strong {
        color: var(--c-accent);
    }

    /* ================= SECTION 2 — FORM ================= */
    .lp-form-sec {
        background: linear-gradient(180deg, #fff, #f3f8f4);
    }

    .lp-form-sec .wrap {
        display: grid;
        grid-template-columns: 1fr;
        gap: 28px;
        align-items: stretch;
    }

    /* tiêu đề chung ở trên (full-width), căn giữa trên mobile */
    .lp-form-head {
        text-align: center;
    }

    /* cột trái: xếp dọc để ảnh kit giãn lấp đầy chiều cao */
    .lp-form-sec .form-left {
        display: flex;
        flex-direction: column;
    }

    .lp-form-sec .eyebrow {
        color: var(--c-dark);
    }

    .lp-form-sec .eyebrow::before {
        background: var(--c-dark);
    }

    .lp-form-sec h2.sec-title {
        color: var(--c-dark);
    }

    .lp-form-sec .form-left .sec-sub {
        font-size: 15.5px;
        color: #43564c;
        font-weight: 500;
    }

    /* ảnh hộp kit — khung bo góc + vầng sáng cam + badge MIỄN PHÍ */
    .lp-gift-box {
        position: relative;
        width: 100%;
        max-width: 450px;
        margin: 22px auto 0;
        aspect-ratio: 1/1;
        border-radius: 20px;
        overflow: visible;
    }

    /* vầng sáng cam toả sau ảnh */
    .lp-gift-box::after {
        content: "";
        position: absolute;
        inset: -10px;
        z-index: 0;
        border-radius: 28px;
        background: radial-gradient(closest-side, rgba(255, 168, 39, .3), transparent 75%);
        filter: blur(16px);
    }

    .lp-gift-box img {
        position: relative;
        z-index: 1;
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 20px;
        display: block;
        box-shadow: 0 22px 44px rgba(12, 76, 41, .18);
        border: 1px solid rgba(0, 0, 0, .05);
    }

    /* badge "MIỄN PHÍ" góc trên */
    .lp-gift-box::before {
        /* content: "🎁 MIỄN PHÍ"; */
        position: absolute;
        z-index: 2;
        top: -12px;
        right: 14px;
        background: linear-gradient(135deg, var(--c-accent), var(--c-accent2));
        color: #1a1200;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .04em;
        padding: 7px 14px;
        border-radius: 30px;
        box-shadow: 0 8px 18px rgba(255, 138, 0, .4);
    }

    @keyframes bob {

        0%,
        100% {
            transform: translateY(0)
        }

        50% {
            transform: translateY(-10px)
        }
    }

    /* 3 chip features — icon tròn cam, nền trắng, viền tinh tế, hover nhấc */
    .lp-gift-feats {
        display: flex;
        gap: 4px;
        justify-content: center;
        /* flex-wrap: wrap; */
        margin-top: 26px;
    }

    .lp-gift-feats .gf {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--c-dark);
        background: #fff;
        border: 1px solid #e2ebe5;
        padding: 8px 15px 8px 8px;
        border-radius: 30px;
        display: inline-flex;
        gap: 9px;
        align-items: center;
        box-shadow: 0 4px 12px rgba(12, 76, 41, .06);
        transition: .22s;
    }

    .lp-gift-feats .gf:hover {
        transform: translateY(-3px);
        border-color: rgba(255, 168, 39, .55);
        box-shadow: 0 10px 22px rgba(255, 138, 0, .18);
    }

    .lp-gift-feats .gf i {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(255, 168, 39, .16);
        color: var(--c-accent2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
    }

    .lp-form {
        background: #fff;
        border: 1px solid var(--c-line);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 24px 60px rgba(12, 76, 41, .1);
    }

    .lp-form .field {
        margin-bottom: 16px;
        position: relative;
    }

    .lp-form label {
        display: block;
        font-size: 12.5px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .lp-form label .req {
        color: #d63a3a;
    }

    /* font-size:16px → iOS Safari không auto-zoom khi focus input */
    .lp-form input {
        width: 100%;
        padding: 14px 15px;
        border: 1.5px solid #dfe4e8;
        border-radius: 10px;
        font-size: 16px;
        font-family: inherit;
        transition: .2s;
        background: #fafbfc;
    }

    .lp-form input:focus {
        outline: none;
        border-color: var(--c-dark);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(12, 76, 41, .1);
    }

    .lp-form input.is-invalid {
        border-color: #d63a3a;
        background: #fff5f5;
    }

    .lp-form .err {
        display: none;
        color: #d63a3a;
        font-size: 12px;
        margin-top: 5px;
        font-weight: 600;
    }

    .lp-form input.is-invalid+.err {
        display: block;
    }

    .lp-form .btn-accent {
        width: 100%;
        margin-top: 8px;
        padding: 17px;
        font-size: 14.5px;
    }

    /* ================= SECTION 3 — THÔNG TIN NHẬN QUÀ ================= */
    /* ===== SECTION TOÀN MÀN HÌNH — OneCoat Solution Center ===== */
    .lp-info {
        background: linear-gradient(160deg, var(--c-dark) 0%, var(--c-darker) 100%);
        color: #fff;
        position: relative;
        overflow: hidden;
    }

    /* lưới blueprint phủ kín nền section */
    .lp-info::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 0;
        background-image: linear-gradient(var(--c-grid) 1px, transparent 1px), linear-gradient(90deg, var(--c-grid) 1px, transparent 1px);
        background-size: 46px 46px;
        mask-image: radial-gradient(900px 600px at 50% 0%, #000, transparent 78%);
    }

    /* vầng sáng cam góc phải */
    .lp-info::after {
        content: "";
        position: absolute;
        top: -15%;
        right: -8%;
        width: 520px;
        height: 520px;
        z-index: 0;
        background: radial-gradient(circle, rgba(255, 168, 39, .18), transparent 60%);
        filter: blur(12px);
    }

    .lp-info .wrap {
        position: relative;
        z-index: 2;
        max-width: 820px;
        text-align: center;
    }

    .lp-info-card {
        background: #fff;
        border: 1px solid var(--c-line);
        border-radius: 18px;
        padding: 26px;
        transition: .25s;
    }

    .lp-info-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 40px rgba(12, 76, 41, .1);
    }

    .lp-info-card h3 {
        font-size: 16px;
        font-weight: 900;
        margin: 0 0 12px;
        color: var(--c-dark);
        line-height: 1.3;
    }

    .lp-info-card .kit-name {
        font-size: 21px;
        font-weight: 900;
        margin: 6px 0 16px;
    }

    .lp-info-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .lp-info-card ul li {
        position: relative;
        padding: 10px 0 10px 30px;
        font-size: 14px;
        font-weight: 600;
        border-bottom: 1px dashed #e6ece8;
    }

    .lp-info-card ul li:last-child {
        border-bottom: none;
    }

    .lp-info-card ul li::before {
        content: "\2713";
        position: absolute;
        left: 0;
        top: 10px;
        color: #fff;
        background: var(--c-dark);
        width: 18px;
        height: 18px;
        border-radius: 50%;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
    }

    /* card center giờ là nội dung trần, căn giữa trong section full */
    .lp-center {
        background: transparent;
        color: #fff;
        border: none;
        padding: 0;
        text-align: center;
    }

    .lp-center:hover {
        transform: none;
        box-shadow: none;
    }

    .lp-center h3 {
        color: var(--c-accent);
        font-size: clamp(15px, 2vw, 18px);
        letter-spacing: .04em;
        text-transform: uppercase;
        margin: 0 0 14px;
    }

    .lp-center .center-name {
        font-size: clamp(28px, 4.5vw, 46px);
        font-weight: 900;
        margin: 18px 0 12px;
        letter-spacing: .01em;
        line-height: 1.1;
    }

    /* danh sách 4 chi nhánh — chip bo tròn, căn giữa, gọn */
    .lp-center .center-addr-list {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin: 4px 0 22px;
    }

    .lp-center .center-addr {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: clamp(13.5px, 1.5vw, 15.5px);
        font-weight: 700;
        margin: 0;
        padding: 9px 18px;
        background: rgba(255, 255, 255, .06);
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: 40px;
        transition: .2s;
    }

    .lp-center .center-addr:hover {
        background: rgba(255, 168, 39, .12);
        border-color: rgba(255, 168, 39, .4);
    }

    .lp-center .center-addr i {
        color: var(--c-accent);
        font-size: 17px;
        flex-shrink: 0;
    }

    /* Khi center-addr là thẻ <a> — reset màu chữ & gạch chân */
    a.center-addr {
        color: #fff;
        text-decoration: none;
        cursor: pointer;
    }

    a.center-addr:hover {
        color: var(--c-accent);
    }

    .lp-center p {
        font-size: 15px;
        line-height: 1.7;
        opacity: .85;
        margin: 0 auto;
        max-width: 480px;
    }

    /* ===== THANK YOU ===== */
    .lp-thankyou {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        background: linear-gradient(160deg, var(--c-darker), var(--c-dark));
        color: #fff;
        align-items: center;
        justify-content: center;
        padding: 24px;
        text-align: center;
    }

    .lp-thankyou.show {
        display: flex;
        animation: tyIn .5s ease;
    }

    @keyframes tyIn {
        from {
            opacity: 0
        }

        to {
            opacity: 1
        }
    }

    .lp-thankyou .ty-inner {
        max-width: 560px;
        animation: tyUp .6s cubic-bezier(.2, .7, .2, 1);
    }

    @keyframes tyUp {
        from {
            opacity: 0;
            transform: translateY(30px)
        }

        to {
            opacity: 1;
            transform: none
        }
    }

    .lp-thankyou .ty-icon {
        font-size: 80px;
        color: var(--c-accent);
        margin-bottom: 18px;
        line-height: 1;
        animation: bob 3s ease-in-out infinite;
    }

    .lp-thankyou h2 {
        font-size: clamp(24px, 4vw, 38px);
        font-weight: 900;
        margin: 0 0 16px;
    }

    .lp-thankyou p {
        font-size: 16px;
        line-height: 1.65;
        opacity: .92;
        margin: 0 0 10px;
    }

    .lp-thankyou .ty-kit {
        margin-top: 22px;
        display: inline-block;
        background: var(--c-accent);
        color: #1a1200;
        font-weight: 900;
        font-size: 18px;
        padding: 14px 26px;
        border-radius: 12px;
    }

    .lp-thankyou .btn-close-ty {
        margin-top: 28px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        color: #fff;
        border: 1.5px solid rgba(255, 255, 255, 0.4);
        padding: 12px 28px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 15px;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.25s ease;
    }

    .lp-thankyou .btn-close-ty:hover {
        background: rgba(255, 255, 255, 0.12);
        border-color: #fff;
        transform: translateY(-2px);
    }

    .lp-thankyou .btn-close-ty:active {
        transform: translateY(0);
    }

    /* ================= STICKY CTA (chỉ mobile/tablet ≤900px) ================= */
    .lp-sticky-cta {
        display: none;
    }

    @media (max-width:900px) {
        .lp-sticky-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9000;
            background: linear-gradient(135deg, var(--c-accent), var(--c-accent2));
            color: #1a1200;
            font-weight: 800;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: .04em;
            text-decoration: none;
            padding: 15px 18px;
            padding-bottom: calc(15px + env(safe-area-inset-bottom, 0px));
            box-shadow: 0 -8px 24px rgba(0, 0, 0, .22);
            transform: translateY(120%);
            transition: transform .3s cubic-bezier(.2, .7, .2, 1);
        }

        .lp-sticky-cta.show {
            transform: none;
        }

        .lp-sticky-cta i {
            font-size: 17px;
        }

        /* khi bar hiện, chừa khoảng dưới section cuối để bar không che nội dung */
        .lp-sol.sticky-on .lp-info {
            padding-bottom: 84px;
        }
    }

    /* ================= TABLET ≥601px ================= */
    @media (min-width:601px) {
        .lp-sol section {
            padding: 72px 0;
        }

        /* tablet: lưới 3 cột */
        .lp-apply-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .lp-sol-hero .hero-cta {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
        }

        .lp-sol-hero .hero-cta .btn-accent,
        .lp-sol-hero .hero-cta .btn-ghost {
            width: auto;
        }

        /* section center: thoáng hơn trên tablet+ */
        .lp-info {
            padding: 96px 0;
        }
    }

    /* ================= DESKTOP ≥901px — khôi phục layout 2 cột ================= */
    @media (min-width:901px) {
        .lp-sol .wrap {
            padding: 0 24px;
        }

        .lp-sol section {
            padding: 88px 0;
        }

        .lp-sol-hero {
            padding: 96px 0 80px;
            text-align: left;
        }

        .lp-sol-hero .wrap {
            grid-template-columns: 1.08fr .92fr;
            gap: 56px;
        }

        .lp-badge-row {
            justify-content: flex-start;
        }

        .lp-sol-hero h1 {
            font-size: clamp(36px, 4.2vw, 52px);
        }

        .lp-sol-hero h1 .accent::after {
            left: 0;
            transform: none;
        }

        .lp-sol-hero .lead {
            margin: 18px 0 28px;
        }

        .lp-sol-hero .hero-cta {
            flex-direction: row;
            gap: 14px;
            justify-content: flex-start;
            margin-bottom: 34px;
        }

        .lp-sol-hero .hero-stats {
            gap: 14px;
            justify-content: flex-start;
        }

        .lp-sol-hero .hero-stats .st {
            flex: 0 1 140px;
            text-align: center;
        }

        .lp-video-wrap {
            max-width: none;
            width: fit-content;
        }

        /* DESKTOP: tiêu đề full-width hàng trên; ảnh + form hàng dưới CÙNG MỐC
           → đỉnh ảnh thẳng đỉnh form, đáy ảnh thẳng đáy form */
        .lp-form-sec .wrap {
            grid-template-columns: .92fr 1.08fr;
            grid-template-areas:
                "head head"
                "left form";
            column-gap: 54px;
            row-gap: 28px;
            align-items: stretch;
            text-align: left;
        }

        .lp-form-head {
            grid-area: head;
            text-align: left;
        }

        .lp-form-sec .form-left {
            grid-area: left;
        }

        .lp-form-sec .lp-form {
            grid-area: form;
        }

        .lp-form {
            padding: 32px;
        }

        /* ảnh kit giãn lấp đầy chiều cao cột trái (đáy thẳng đáy form) */
        .lp-form-sec .form-left .lp-gift-box {
            flex: 1;
            aspect-ratio: auto;
            max-width: none;
            min-height: 360px;
            margin: 0;
        }

        .lp-form-sec .form-left .lp-gift-box img {
            height: 100%;
        }

        /* 3 chip features canh trái cho khớp mép ảnh */
        .lp-form-sec .form-left .lp-gift-feats {
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        /* form giãn cao bằng ảnh, nút submit dính đáy → đáy form thẳng đáy ảnh */
        .lp-form-sec .lp-form {
            align-self: stretch;
            display: flex;
            flex-direction: column;
        }

        .lp-form-sec .lp-form .btn-accent {
            margin-top: auto;
        }

        /* section center toàn màn hình — thoáng trên desktop */
        .lp-info {
            padding: 120px 0;
        }

        /* DESKTOP: lưới 3 cột, card to & thoáng */
        .lp-apply-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
        }

        .lp-apply-card {
            padding: 26px;
            border-radius: 16px;
        }

        .lp-apply-card .ac-ico {
            width: 54px;
            height: 54px;
            border-radius: 13px;
            font-size: 26px;
            margin-bottom: 16px;
        }

        .lp-apply-card h3 {
            font-size: 16px;
            margin: 0 0 8px;
        }

        .lp-apply-card p {
            font-size: 13px;
            line-height: 1.55;
        }

        .lp-apply-card:hover {
            transform: translateY(-5px);
        }
    }

    /* ===== PAGE LOADER ===== */
    .lp-loader {
        position: fixed;
        inset: 0;
        z-index: 100000;
        background: linear-gradient(165deg, var(--c-darker), var(--c-dark));
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.5s cubic-bezier(0.25, 1, 0.5, 1), visibility 0.5s;
    }

    .lp-loader.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .lp-loader-spinner {
        position: relative;
        width: 80px;
        height: 80px;
        margin-bottom: 24px;
    }

    .lp-loader-circle {
        position: absolute;
        inset: 0;
        border: 3px solid rgba(255, 168, 39, 0.08);
        border-radius: 50%;
    }

    .lp-loader-bar {
        position: absolute;
        inset: 0;
        border: 3px solid transparent;
        border-top-color: var(--c-accent);
        border-radius: 50%;
        animation: lpSpin 1.2s cubic-bezier(0.5, 0.1, 0.4, 0.9) infinite;
        filter: drop-shadow(0 0 8px var(--c-accent));
    }

    .lp-loader-inner-circle {
        position: absolute;
        inset: 15px;
        border: 2px solid transparent;
        border-bottom-color: var(--c-accent2);
        border-radius: 50%;
        animation: lpSpinReverse 1.8s linear infinite;
        opacity: 0.8;
    }

    .lp-loader-text {
        font-family: 'Montserrat', sans-serif;
        font-size: 13.5px;
        font-weight: 800;
        letter-spacing: 0.25em;
        color: #fff;
        text-transform: uppercase;
        margin-top: 10px;
        animation: lpPulse 2s ease-in-out infinite;
        text-align: center;
    }

    .lp-loader-subtext {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.15em;
        color: var(--c-accent);
        text-transform: uppercase;
        margin-top: 6px;
        opacity: 0.75;
    }

    @keyframes lpSpin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes lpSpinReverse {
        0% {
            transform: rotate(360deg);
        }

        100% {
            transform: rotate(0deg);
        }
    }

    @keyframes lpPulse {

        0%,
        100% {
            opacity: 0.6;
            transform: scale(0.98);
        }

        50% {
            opacity: 1;
            transform: scale(1.02);
        }
    }
</style>

<?php

/* 12 vấn đề bề mặt (marquee) */
$surfaceCases = [
    ['ic' => 'bi-window', 'txt' => 'Kính'],
    ['ic' => 'bi-window', 'txt' => 'Gỗ'],
    ['ic' => 'bi-window', 'txt' => 'Sắt'],
    ['ic' => 'bi-window', 'txt' => 'Panel'],
    ['ic' => 'bi-window', 'txt' => 'Nhôm'],
    ['ic' => 'bi-window', 'txt' => 'Sứ'],
    ['ic' => 'bi-window', 'txt' => 'Đá'],
    ['ic' => 'bi-window', 'txt' => 'Bê tông'],
    ['ic' => 'bi-window', 'txt' => 'Mica'],
    ['ic' => 'bi-window', 'txt' => 'Innox'],
    ['ic' => 'bi-window', 'txt' => 'Tường'],
    ['ic' => 'bi-window', 'txt' => 'Kính'],
    ['ic' => 'bi-window', 'txt' => 'Gỗ'],
    ['ic' => 'bi-window', 'txt' => 'Sắt'],
    ['ic' => 'bi-window', 'txt' => 'Panel'],
    ['ic' => 'bi-window', 'txt' => 'Nhôm'],
    ['ic' => 'bi-window', 'txt' => 'Sứ'],
    ['ic' => 'bi-window', 'txt' => 'Đá'],
    ['ic' => 'bi-window', 'txt' => 'Bê tông'],
    ['ic' => 'bi-window', 'txt' => 'Mica'],
    ['ic' => 'bi-window', 'txt' => 'Innox'],
    ['ic' => 'bi-window', 'txt' => 'Tường'],
];

/* Hạng mục BỀ MẶT CÔNG TRÌNH / DỰ ÁN */
$projectSurfaces = [
    // ['ic' => 'bi-bricks',    'title' => 'Kết cấu thép & kim loại',   'desc' => 'Khung thép, lan can, hàng rào, kết cấu nhà xưởng — chống ăn mòn, gỉ sét.'],
    // ['ic' => 'bi-house-up',  'title' => 'Mái tôn & vách công trình', 'desc' => 'Mái tôn, vách kim loại, ống dẫn — chịu nhiệt, chống gỉ ngoài trời.'],
    // ['ic' => 'bi-layers',    'title' => 'Bê tông & sàn dự án',       'desc' => 'Sàn gara, tầng hầm, nền nhà xưởng, bãi đỗ — chống thấm, chịu mài mòn.'],
    // ['ic' => 'bi-water',     'title' => 'Hồ bơi & khu vực ẩm',       'desc' => 'Thành hồ, sàn ướt, khu kỹ thuật — chống trơn trượt, kháng nước & hoá chất.'],
    // ['ic' => 'bi-buildings', 'title' => 'Tường ngoài & mặt dựng',    'desc' => 'Tường ngoại thất, mặt dựng công trình — chống nứt chân chim, phấn hoá.'],
    // ['ic' => 'bi-tsunami',   'title' => 'Công trình ven biển',       'desc' => 'Hạng mục chịu muối biển, độ ẩm cao — kháng ăn mòn môi trường mặn.'],
];

/* Video cho dải hero. Mỗi item dùng MỘT trong hai nguồn:
   - 'video' = URL file video trực tiếp (mp4/webm). Tương đối ('/img/abc.mp4') hoặc tuyệt đối.
   - 'yt'    = YouTube VIDEO ID (phần sau v=, KHÔNG phải URL đầy đủ).
   Nếu có cả hai, 'video' (mp4) được ưu tiên.
   'name' = tiêu đề hiển thị. */
$heroProducts = [
    // -----------------------------------------------------------------------
    // Đặt file .mp4 tương ứng vào thư mục /img/ rồi khai báo đường dẫn ở đây.
    // Ví dụ:  ['name' => 'Sản phẩm X', 'video' => '/img/SanPhamX.mp4']
    // -----------------------------------------------------------------------
    /* Đã có video */
    ['name' => 'DuraPoxy HP',    'video' => 'https://ai.paintandmore.vn/video/DuraPoxyHP.mp4'],
    ['name' => 'Seal Krete',     'video' => 'https://ai.paintandmore.vn/video/SealKrete.mp4'],
    ['name' => '9100 DTM',     'video' => 'https://ai.paintandmore.vn/video/9100-DTM.mp4'],
    /* Chưa có video */
    ['name' => 'OneCoat Mono ',               'video' => 'https://ai.paintandmore.vn/video/OneCoatMono.mp4'],
    ['name' => 'OneCoat Pro',              'video' => 'https://ai.paintandmore.vn/video/OneCoatPro.mp4'],
    ['name' => 'OneCoat Biển',        'video' => 'https://ai.paintandmore.vn/video/OneCoatBien.mp4'],
    ['name' => 'OneCoat 365',              'video' => 'https://ai.paintandmore.vn/video/OneCoat365.mp4'],
    ['name' => 'OneCoat Dex',        'video' => 'https://ai.paintandmore.vn/video/OneCoatDex.mp4'],
];

// Chuẩn hoá thành danh sách item {type, src, title} cho JS.
// type = 'mp4' | 'youtube'. src = URL mp4 hoặc YouTube ID.
$heroVideoItems = [];
foreach ($heroProducts as $p) {
    $title = (string)($p['name'] ?? '');
    $mp4   = trim((string)($p['video'] ?? ''));
    $yt    = trim((string)($p['yt']    ?? ''));
    if ($mp4 !== '') {
        // Giữ nguyên URL (tuyệt đối https://... hoặc root-relative /img/...).
        // Browser tự resolve root-relative path — không cần ghép host ở đây.
        $heroVideoItems[] = ['type' => 'mp4', 'src' => $mp4, 'title' => $title];
    } elseif ($yt !== '') {
        $heroVideoItems[] = ['type' => 'youtube', 'src' => $yt, 'title' => $title];
    }
}
?>

<div class="lp-sol">

    <!-- ===== PAGE LOADER ===== -->
    <div class="lp-loader" id="lpLoader">
        <div class="lp-loader-spinner">
            <div class="lp-loader-circle"></div>
            <div class="lp-loader-bar"></div>
            <div class="lp-loader-inner-circle"></div>
        </div>
        <div class="lp-loader-text">Paint & More</div>
        <div class="lp-loader-subtext">OneCoat Solution</div>
    </div>

    <!-- ================= SECTION 1 — HERO ================= -->
    <section class="lp-sol-hero">
        <div class="lp-hero-bg" style="background-image:url('<?php echo esc_url($img_base . '/hero_banner.png'); ?>');"></div>
        <div class="wrap">
            <div class="hero-content">
                <div class="lp-badge-row reveal in">
                    <span class="bd"><i class="bi bi-calendar-event"></i> Đại hội Kiến trúc sư 2026</span>
                </div>
                <h1 class="reveal in d1">
                    <span class="lp-tw lp-tw1">KTS Kiến Tạo Không Gian</span><br>
                    <span class="accent lp-tw lp-tw2">ONECOAT ĐIỂM TÔ SẮC MÀU</span>
                </h1>
                <p class="lead reveal in d2">Mọi bề mặt khó như: kính, gỗ ngoài trời, hồ bơi, sàn gara, tường nứt,..<br>Sơn OneCoat đều có giải pháp. Đăng ký nhận ngay bộ kit giải pháp dành riêng cho Kiến trúc sư.</p>
                <div class="hero-cta reveal in d3">
                    <span class="lp-center-badge">
                        <span class="lcb-signature">OneCoat Solution Center</span>
                    </span>
                    <a href="#dangky" class="btn-accent"><i class="bi bi-gift-fill"></i> Đăng ký nhận quà</a>
                    <!-- <a href="#ap-dung" class="btn-ghost"><i class="bi bi-buildings"></i> Phạm vi áp dụng</a> -->
                </div>
                <div class="hero-stats reveal in d4">
                    <div class="st"><b _data-count="MỌI">MỌI</b><span>Bề mặt khó</span></div>
                    <div class="st"><b _data-count="100" data-suffix="%">100%</b><span>Tư vấn kỹ thuật</span></div>
                    <div class="st"><b _data-count="1" data-suffix=" lớp">MẪU</b><span>Thử miễn phí</span></div>
                </div>
            </div>

            <div class="lp-video-wrap reveal in d2">
                <div class="lp-sol-video">
                    <!-- Player (YouTube hoặc <video> mp4) do JS gắn vào, dựa trên data-items -->
                    <div id="lpYtPlayer"
                        data-items='<?php echo esc_attr(wp_json_encode(array_values($heroVideoItems))); ?>'></div>
                    <!-- placeholder khi chưa có video -->
                    <div class="video-ph" id="lpVideoPh">
                        <div class="pbtn"><i class="bi bi-play-fill"></i></div>
                        <span>Video giải pháp OneCoat</span>
                    </div>
                    <!-- Nút điều hướng carousel -->
                    <button type="button" class="lp-vnav prev" id="lpVnavPrev" aria-label="Video trước"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="lp-vnav next" id="lpVnavNext" aria-label="Video kế"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="lp-video-title" id="lpVideoTitle"></div>
                <!-- Dots chỉ báo vị trí video trong carousel (JS sinh) -->
                <div class="lp-video-dots" id="lpVideoDots"></div>
            </div>
        </div>

        <div class="lp-marquee">
            <div class="track">
                <?php for ($k = 0; $k < 2; $k++): foreach ($surfaceCases as $c): ?>
                        <span class="mi"><i class="bi <?php echo esc_attr($c['ic']); ?>"></i><?php echo esc_html($c['txt']); ?></span>
                <?php endforeach;
                endfor; ?>
            </div>
        </div>
    </section>

    <!-- ===== HỆ SƠN ÁP DỤNG CHO ===== -->
    <!-- <section class="lp-apply" id="ap-dung">
        <div class="wrap">
            <span class="eyebrow reveal" style="color:var(--c-accent)">Phạm vi áp dụng</span>
            <h2 class="sec-title reveal d1">Giải pháp cho bề mặt CÔNG TRÌNH &amp; DỰ ÁN</h2>
            <p class="sec-sub reveal d1" style="color:rgba(255,255,255,.75)">Hệ sơn OneCoat được thiết kế cho các hạng mục bề mặt công trình, dự án — nơi đòi hỏi độ bền, chống ăn mòn và chịu môi trường khắc nghiệt.</p>
            <div class="lp-apply-grid">
                <?php foreach ($projectSurfaces as $i => $s): ?>
                    <div class="lp-apply-card reveal d<?php echo ($i % 3) + 1; ?>">
                        <div class="ac-ico"><i class="bi <?php echo esc_attr($s['ic']); ?>"></i></div>
                        <h3><?php echo esc_html($s['title']); ?></h3>
                        <p><?php echo esc_html($s['desc']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="lp-apply-note reveal d1">
                <i class="bi bi-info-circle-fill"></i>
                <span><strong>Lưu ý:</strong> Hệ sơn này dành riêng cho bề mặt công trình &amp; dự án phù hợp — <strong>không áp dụng</strong> cho các vật dụng nội thất gia dụng trong nhà.</span>
            </div>
        </div>
    </section> -->

    <!-- ================= SECTION 2 — FORM ĐĂNG KÝ ================= -->
    <section class="lp-form-sec" id="dangky">
        <div class="wrap">
            <!-- tiêu đề full-width ở trên → ảnh & form bên dưới bắt đầu cùng mốc -->
            <div class="lp-form-head reveal">
                <span class="eyebrow">Nhận quà tặng</span>
                <h2 class="sec-title">ONECOAT SOLUTION KIT 2026</h2>
                <p class="sec-sub">Dành riêng cho Kiến Trúc Sư tham dự sự kiện.</p>
            </div>
            <div class="form-left reveal">
                <div class="lp-gift-box">
                    <img src="<?php echo esc_url($img_base . '/kit-box.png'); ?>" alt="OneCoat Solution Kit 2026" loading="lazy">
                </div>
                <div class="lp-gift-feats">
                    <span class="gf"><i class="bi bi-box-seam"></i> Mẫu vật liệu</span>
                    <span class="gf"><i class="bi bi-file-earmark-text"></i> Tài liệu kỹ thuật</span>
                    <span class="gf"><i class="bi bi-headset"></i> Tư vấn kỹ thuật</span>
                </div>
            </div>
            <form id="solutionForm" class="lp-form reveal d1" novalidate>
                <h3 class="lp-form-title">ĐĂNG KÝ NHẬN QUÀ TẶNG</h3>
                <style>
                    .lp-form-title {
                        font-size: clamp(26px, 1.5vw, 40px);
                        font-weight: 900;
                        line-height: 1.12;
                        margin: 0 0 16px;
                        letter-spacing: -.015em;
                    }
                </style>
                <div class="field">
                    <label>Họ và tên <span class="req">*</span></label>
                    <input type="text" name="name" autocomplete="name" placeholder="Nguyễn Văn A">
                    <div class="err">Vui lòng nhập họ và tên.</div>
                </div>
                <div class="field">
                    <label>Số điện thoại <span class="req">*</span></label>
                    <input type="tel" name="phone" inputmode="numeric" autocomplete="tel" placeholder="09xx xxx xxx">
                    <div class="err">Số điện thoại không hợp lệ.</div>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" autocomplete="email" placeholder="email@congty.vn (không bắt buộc)">
                    <div class="err">Email không hợp lệ.</div>
                </div>
                <div class="field">
                    <label style="line-height: 1.5;">Vui lòng điền đầy đủ thông tin ở trên để được nhận quà tặng hấp dẫn từ Paint&More</label>
                </div>
                <button type="submit" class="btn-accent"><i class="bi bi-send-fill"></i> GỬI THÔNG TIN </button>
            </form>
        </div>
    </section>

    <!-- ================= SECTION 3 — THÔNG TIN NHẬN QUÀ ================= -->
    <section class="lp-info">
        <div class="wrap">
            <!-- <div class="lp-info-card reveal">
                <h3>SAU KHI ĐĂNG KÝ</h3>
                <p style="font-size:14px;color:#5a6b62;margin:0 0 4px;">Vui lòng đến gian hàng OneCoat để nhận:</p>
                <div class="kit-name">🎁 ONECOAT SOLUTION KIT 2026</div>
                <p style="font-size:13px;color:#8a9a91;font-weight:700;margin:0 0 8px;">Bao gồm:</p>
                <ul>
                    <li>Bộ sample vật liệu thực tế</li>
                    <li>Tài liệu kỹ thuật tham khảo</li>
                </ul>
            </div> -->
            <div class="lp-info-card lp-center reveal d1">
                <h3>BẠN ĐANG CÓ VẬT LIỆU KHÓ HOẶC KÉN SƠN?</h3>
                <p style="opacity:.95;margin-bottom:6px;">OneCoat hỗ trợ test sampling và tư vấn giải pháp miễn phí.</p>
                <div class="center-name">ONECOAT SOLUTION CENTER</div>
                <div class="center-addr-list">
                    <a class="center-addr"
                       href="https://www.google.com/maps?q=10.805746,106.693833"
                       target="_blank" rel="noopener">
                        <i class="bi bi-geo-alt-fill"></i> 458A Điện Biên Phủ, Phường Gia Định, TP.HCM
                    </a>
                    <a class="center-addr"
                       href="https://www.google.com/maps?q=10.776197,106.701693"
                       target="_blank" rel="noopener">
                        <i class="bi bi-geo-alt-fill"></i> 10 Calmette, Phường Bến Thành, TP.HCM
                    </a>
                    <a class="center-addr"
                       href="https://www.google.com/maps?q=10.798557,106.720830"
                       target="_blank" rel="noopener">
                        <i class="bi bi-geo-alt-fill"></i> 135/37/71 Nguyễn Hữu Cảnh, Phường Thạnh Mỹ Tây, TP.HCM
                    </a>
                    <a class="center-addr"
                       href="https://www.google.com/maps?q=10.738905,106.717611"
                       target="_blank" rel="noopener">
                        <i class="bi bi-geo-alt-fill"></i> C-Space, KCX Tân Thuận, Phường Phú Thuận, TP.HCM
                    </a>
                </div>
                <p>Mang mẫu vật liệu đến hoặc liên hệ đội ngũ kỹ thuật để được hỗ trợ.</p>
            </div>
        </div>
    </section>

    <!-- ===== STICKY CTA (mobile) ===== -->
    <a href="#dangky" class="lp-sticky-cta" id="lpStickyCta">
        <i class="bi bi-gift-fill"></i> Đăng ký nhận quà tặng
    </a>

    <!-- ===== MÀN HÌNH CẢM ƠN ===== -->
    <div class="lp-thankyou" id="thankYouScreen">
        <div class="ty-inner">
            <div class="ty-icon"><i class="bi bi-check-circle-fill"></i></div>
            <h2>CẢM ƠN ANH/CHỊ ĐÃ ĐĂNG KÝ</h2>
            <p>Vui lòng xuất trình màn hình này tại gian hàng OneCoat để nhận:</p>
            <div class="ty-kit">🎁 ONECOAT SOLUTION KIT 2026</div>
            <div>
                <button type="button" class="btn-close-ty" id="closeThankYouBtn">
                    <i class="bi bi-arrow-left"></i> Quay lại trang chủ
                </button>
            </div>
        </div>
    </div>
</div><!-- .lp-sol -->

<script>
    (function() {
        function hideLoader() {
            var loader = document.getElementById('lpLoader');
            if (loader && !loader.classList.contains('is-hidden')) {
                loader.classList.add('is-hidden');
            }
        }
        window.addEventListener('load', hideLoader);
        // Fallback: Tự động tắt sau tối đa 2.5 giây
        setTimeout(hideLoader, 2500);
    })();

    (function() {
        var root = document.querySelector('.lp-sol');
        if (!root) return;

        // ---- smooth scroll
        // Link tới section đăng ký (#dangky) → cuộn thẳng tới Ô FORM điền thông tin
        // (không dừng ở đầu section) và focus ô đầu tiên để người dùng nhập ngay.
        var formEl = document.getElementById('solutionForm');

        function goToForm() {
            var target = formEl || document.getElementById('dangky');
            if (!target) return;
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            // Focus ô đầu tiên sau khi cuộn (delay để không giật/scroll-jump trên mobile).
            var firstInput = formEl ? formEl.querySelector('input[name="name"]') : null;
            if (firstInput) {
                setTimeout(function() {
                    try {
                        firstInput.focus({
                            preventScroll: true
                        });
                    } catch (e) {
                        firstInput.focus();
                    }
                }, 600);
            }
        }

        root.querySelectorAll('a[href^="#"]').forEach(function(a) {
            a.addEventListener('click', function(e) {
                var id = a.getAttribute('href').slice(1);
                // CTA dẫn tới form đăng ký → đưa tới đúng ô nhập.
                if (id === 'dangky') {
                    e.preventDefault();
                    goToForm();
                    return;
                }
                var t = document.getElementById(id);
                if (t) {
                    e.preventDefault();
                    t.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // ---- scroll reveal
        var revs = root.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function(es) {
                es.forEach(function(en) {
                    if (en.isIntersecting) {
                        en.target.classList.add('in');
                        io.unobserve(en.target);
                    }
                });
            }, {
                threshold: .12
            });
            revs.forEach(function(el) {
                if (!el.classList.contains('in')) io.observe(el);
            });
        } else {
            revs.forEach(function(el) {
                el.classList.add('in');
            });
        }

        // ---- sticky CTA: hiện sau khi cuộn qua hero
        var stickyCta = document.getElementById('lpStickyCta');
        var hero = root.querySelector('.lp-sol-hero');

        function showSticky(on) {
            if (!stickyCta) return;
            stickyCta.classList.toggle('show', on);
            root.classList.toggle('sticky-on', on);
        }
        if (stickyCta && hero && 'IntersectionObserver' in window) {
            var ioS = new IntersectionObserver(function(es) {
                es.forEach(function(en) {
                    showSticky(!en.isIntersecting);
                });
            }, {
                threshold: 0
            });
            ioS.observe(hero);
        }

        // ---- Carousel video: hỗ trợ cả file MP4 (<video>) lẫn YouTube; prev/next + dots; auto-next khi xem xong.
        (function() {
            var holder = document.getElementById('lpYtPlayer');
            var ph = document.getElementById('lpVideoPh');
            var titleEl = document.getElementById('lpVideoTitle');
            var dotsEl = document.getElementById('lpVideoDots');
            var btnPrev = document.getElementById('lpVnavPrev');
            var btnNext = document.getElementById('lpVnavNext');
            if (!holder) return;

            // items = [{type:'mp4'|'youtube', src, title}]
            var items = [];
            try {
                items = JSON.parse(holder.getAttribute('data-items') || '[]');
            } catch (e) {}
            items = (items || []).filter(function(it) {
                return it && it.src && String(it.src).trim() !== '';
            });
            if (!items.length) return; // chưa có video → giữ placeholder

            if (ph) ph.style.display = 'none';

            // Sinh dots theo số video.
            var dots = [];
            if (dotsEl) {
                dotsEl.innerHTML = '';
                items.forEach(function(it, pos) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'dot' + (pos === 0 ? ' active' : '');
                    b.setAttribute('aria-label', 'Video ' + (pos + 1));
                    b.addEventListener('click', function() {
                        playAt(pos);
                    });
                    dotsEl.appendChild(b);
                    dots.push(b);
                });
            }

            var pos = 0; // vị trí hiện tại
            var ytPlayer = null; // YouTube player (lazy)
            var videoEl = null; // thẻ <video> dùng chung cho mp4

            function syncUi() {
                if (titleEl) titleEl.textContent = (items[pos].title || '');
                dots.forEach(function(d, k) {
                    d.classList.toggle('active', k === pos);
                });
            }

            // Dọn player cũ trước khi đổi loại nguồn (mp4 ↔ youtube).
            function clearStage() {
                if (videoEl) {
                    try {
                        videoEl.pause();
                    } catch (e) {}
                    videoEl.remove();
                    videoEl = null;
                }
                if (ytPlayer && typeof ytPlayer.stopVideo === 'function') {
                    try {
                        ytPlayer.stopVideo();
                    } catch (e) {}
                }
            }

            function playAt(p) {
                pos = (p % items.length + items.length) % items.length; // wrap vòng
                syncUi();
                var it = items[pos];
                if (it.type === 'mp4') {
                    showMp4(it.src);
                } else {
                    showYouTube(it.src);
                }
            }

            // ----- MP4: dùng 1 thẻ <video> chèn vào khung; hết video → next -----
            function showMp4(src) {
                if (ytPlayer && ytPlayer.getIframe) {
                    var f = ytPlayer.getIframe();
                    if (f) f.style.display = 'none';
                }
                if (!videoEl) {
                    videoEl = document.createElement('video');
                    videoEl.setAttribute('playsinline', '');
                    videoEl.controls = false; // vô hiệu hoá thanh điều khiển
                    videoEl.autoplay = true;
                    videoEl.muted = true;     // autoplay trên mobile cần muted
                    videoEl.volume = 0.3;     // âm lượng mặc định 30%
                    videoEl.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;background:#000;';
                    videoEl.addEventListener('ended', function() {
                        playAt(pos + 1);
                    });
                    holder.parentNode.appendChild(videoEl);
                } else {
                    videoEl.style.display = 'block';
                }
                if (videoEl.getAttribute('src') !== src) videoEl.setAttribute('src', src);
                var pr = videoEl.play();
                if (pr && pr.catch) pr.catch(function() {});
            }

            // ----- YouTube: nạp IFrame API (lazy), dùng chung 1 player -----
            function loadYTApi(cb) {
                if (window.YT && window.YT.Player) {
                    cb();
                    return;
                }
                var prev = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = function() {
                    if (typeof prev === 'function') prev();
                    cb();
                };
                if (!document.getElementById('lp-yt-api')) {
                    var s = document.createElement('script');
                    s.id = 'lp-yt-api';
                    s.src = 'https://www.youtube.com/iframe_api';
                    document.head.appendChild(s);
                }
            }

            function showYouTube(videoId) {
                if (videoEl) {
                    try {
                        videoEl.pause();
                    } catch (e) {}
                    videoEl.style.display = 'none';
                }
                loadYTApi(function() {
                    if (!ytPlayer) {
                        ytPlayer = new YT.Player('lpYtPlayer', {
                            videoId: videoId,
                            playerVars: {
                                rel: 0,
                                modestbranding: 1,
                                playsinline: 1
                            },
                            events: {
                                onStateChange: function(e) {
                                    if (e.data === YT.PlayerState.ENDED) playAt(pos + 1);
                                }
                            }
                        });
                    } else {
                        var f = ytPlayer.getIframe && ytPlayer.getIframe();
                        if (f) f.style.display = 'block';
                        ytPlayer.loadVideoById(videoId);
                    }
                });
            }

            // Khởi động ở video đầu tiên.
            playAt(0);

            if (btnPrev) btnPrev.addEventListener('click', function() {
                playAt(pos - 1);
            });
            if (btnNext) btnNext.addEventListener('click', function() {
                playAt(pos + 1);
            });
        })();

        // ---- count up
        function countUp(el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var suffix = el.getAttribute('data-suffix') || '';
            var dur = 1100,
                start = null;

            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                el.textContent = Math.round(target * p) + suffix;
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = target + suffix;
            }
            requestAnimationFrame(step);
        }
        var counters = root.querySelectorAll('[data-count]');
        if ('IntersectionObserver' in window) {
            var ioC = new IntersectionObserver(function(es) {
                es.forEach(function(en) {
                    if (en.isIntersecting) {
                        countUp(en.target);
                        ioC.unobserve(en.target);
                    }
                });
            }, {
                threshold: .6
            });
            counters.forEach(function(el) {
                ioC.observe(el);
            });
        } else {
            counters.forEach(countUp);
        }

        // ---- form (client-only). Nối tới endpoint thật bằng fetch() nếu cần lưu lead.
        var form = document.getElementById('solutionForm');
        if (!form) return;
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        function isValidVNPhone(phone) {
            var cleaned = phone.replace(/[\s\-\.\+\(\)]/g, '');
            if (cleaned.indexOf('0084') === 0) {
                cleaned = cleaned.substring(2);
            }
            var vnMobileWith84 = /^84(3|5|7|8|9)\d{8}$/;
            var vnLandlineWith84 = /^84(2\d)\d{8}$/;
            var vnMobileWith0 = /^0(3|5|7|8|9)\d{8}$/;
            var vnMobileWith084 = /^084(3|5|7|8|9)\d{8}$/;
            var vnLandlineWith0 = /^02\d{9}$/;
            return vnMobileWith84.test(cleaned) ||
                vnLandlineWith84.test(cleaned) ||
                vnMobileWith0.test(cleaned) ||
                vnMobileWith084.test(cleaned) ||
                vnLandlineWith0.test(cleaned);
        }

        function setInvalid(input, bad) {
            input.classList.toggle('is-invalid', !!bad);
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var f = form.elements;
            var name = f['name'].value.trim(),
                phone = f['phone'].value.trim(),
                email = f['email'].value.trim();

            setInvalid(f['name'], name === '');
            setInvalid(f['phone'], !isValidVNPhone(phone));
            // email KHÔNG bắt buộc — chỉ báo lỗi nếu có nhập mà sai định dạng
            setInvalid(f['email'], email !== '' && !emailRe.test(email));

            if (form.querySelector('.is-invalid')) {
                form.querySelector('.is-invalid').focus();
                return;
            }

            // Ngày giờ theo múi giờ Hồ Chí Minh (UTC+7), định dạng dd/MM/yyyy HH:mm:ss.
            function hcmDateTime() {
                var parts = new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Asia/Ho_Chi_Minh',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).formatToParts(new Date());
                var g = {};
                parts.forEach(function(p) {
                    g[p.type] = p.value;
                });
                return g.day + '/' + g.month + '/' + g.year + ' ' + g.hour + ':' + g.minute + ':' + g.second;
            }

            var payload = {
                name: name,
                phone: phone,
                email: email,
                date: hcmDateTime()
            };

            // Gửi lead tới webhook (không chặn UX: dù webhook lỗi vẫn hiện màn cảm ơn).
            var btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            // Content-Type: text/plain → "simple request", KHÔNG kích hoạt preflight OPTIONS,
            // nên không bị CORS chặn dù webhook ở domain khác. n8n Webhook node tự parse JSON trong body.
            fetch('https://zalo.onecoat.vn/webhook/onecoat-solution', {
                method: 'POST',
                headers: {
                    'Content-Type': 'text/plain;charset=UTF-8'
                },
                body: JSON.stringify(payload)
            }).catch(function() {
                /* nuốt lỗi mạng — không cản người dùng */
            }).finally(function() {
                if (btn) btn.disabled = false;
                var ty = document.getElementById('thankYouScreen');
                if (ty) {
                    ty.classList.add('show');
                    window.scrollTo(0, 0);
                }
                showSticky(false);
                form.reset();
            });
        });
        form.querySelectorAll('input').forEach(function(inp) {
            inp.addEventListener('input', function() {
                inp.classList.remove('is-invalid');
            });
        });

        var closeTyBtn = document.getElementById('closeThankYouBtn');
        if (closeTyBtn) {
            closeTyBtn.addEventListener('click', function() {
                var ty = document.getElementById('thankYouScreen');
                if (ty) {
                    ty.classList.remove('show');
                }
                if (typeof showSticky === 'function') {
                    showSticky(true);
                }
            });
        }
    })();
</script>

<?php
get_footer();
