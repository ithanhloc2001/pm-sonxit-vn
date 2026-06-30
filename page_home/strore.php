<!DOCTYPE html>

<html class="dark" lang="vi"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Hệ Thống Cơ Sở - QUÁN NHẬU TỰ DO</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400&amp;family=Oswald:wght@500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-container-lowest": "#0e0e0e",
                        "on-tertiary-fixed": "#221b00",
                        "outline-variant": "#534435",
                        "on-error": "#690005",
                        "primary-container": "#f6a236",
                        "surface-container-low": "#1c1b1b",
                        "on-secondary-fixed": "#00210c",
                        "inverse-surface": "#e5e2e1",
                        "secondary-fixed": "#b6f0c0",
                        "on-secondary": "#00391a",
                        "on-primary-container": "#663c00",
                        "primary-fixed": "#ffddbb",
                        "inverse-on-surface": "#313030",
                        "surface-bright": "#393939",
                        "surface-variant": "#353535",
                        "on-secondary-fixed-variant": "#1a512d",
                        "on-background": "#e5e2e1",
                        "error": "#ffb4ab",
                        "primary": "#ffc586",
                        "on-primary": "#482900",
                        "on-surface-variant": "#d8c3af",
                        "surface-tint": "#ffb867",
                        "tertiary-container": "#d3b200",
                        "surface-container-high": "#2a2a2a",
                        "tertiary-fixed": "#ffe16d",
                        "tertiary-fixed-dim": "#e9c400",
                        "outline": "#a08d7c",
                        "on-secondary-container": "#8ac295",
                        "surface-dim": "#131313",
                        "error-container": "#93000a",
                        "on-error-container": "#ffdad6",
                        "background": "#131313",
                        "secondary-container": "#1a512d",
                        "on-tertiary": "#3a3000",
                        "on-primary-fixed-variant": "#673d00",
                        "surface": "#131313",
                        "secondary-fixed-dim": "#9bd4a6",
                        "on-surface": "#e5e2e1",
                        "on-tertiary-container": "#534500",
                        "inverse-primary": "#885200",
                        "primary-fixed-dim": "#ffb867",
                        "on-primary-fixed": "#2b1700",
                        "surface-container-highest": "#353535",
                        "surface-container": "#20201f",
                        "tertiary": "#f3cd00",
                        "secondary": "#9bd4a6",
                        "on-tertiary-fixed-variant": "#544600"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "base": "8px",
                        "gutter": "24px",
                        "margin-desktop": "64px",
                        "margin-mobile": "16px",
                        "section-gap": "80px"
                    },
                    "fontFamily": {
                        "body-lg": ["Plus Jakarta Sans"],
                        "headline-lg-mobile": ["Oswald"],
                        "display-lg": ["Oswald"],
                        "body-md": ["Plus Jakarta Sans"],
                        "headline-sm": ["Oswald"],
                        "headline-md": ["Oswald"],
                        "headline-lg": ["Oswald"],
                        "label-lg": ["Oswald"]
                    },
                    "fontSize": {
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "headline-lg-mobile": ["36px", {"lineHeight": "44px", "fontWeight": "600"}],
                        "display-lg": ["72px", {"lineHeight": "80px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "headline-sm": ["24px", {"lineHeight": "32px", "fontWeight": "500"}],
                        "headline-md": ["32px", {"lineHeight": "40px", "fontWeight": "600"}],
                        "headline-lg": ["48px", {"lineHeight": "56px", "fontWeight": "600"}],
                        "label-lg": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "500"}]
                    }
                }
            }
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background text-on-background font-body-md min-h-screen flex flex-col">
<!-- TopNavBar -->
<nav class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-margin-desktop py-4 bg-surface/95 backdrop-blur-sm border-b-2 border-outline-variant shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
<div class="font-display-lg text-headline-md tracking-tighter text-primary">QUÁN NHẬU TỰ DO</div>
<ul class="hidden md:flex space-x-8">
<li><a class="text-on-surface font-label-lg hover:text-primary transition-colors" href="#">TRANG CHỦ</a></li>
<li><a class="text-on-surface font-label-lg hover:text-primary transition-colors" href="#">MENU</a></li>
<li><a class="text-primary font-bold border-b-2 border-primary pb-1" href="#">CƠ SỞ</a></li>
<li><a class="text-on-surface font-label-lg hover:text-primary transition-colors" href="#">KHUYẾN MÃI</a></li>
<li><a class="text-on-surface font-label-lg hover:text-primary transition-colors" href="#">LIÊN HỆ</a></li>
</ul>
<button class="bg-primary-container text-on-primary-container font-label-lg px-6 py-2 border-2 border-outline-variant shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-y-1 transition-transform">
            ĐẶT BÀN NGAY
        </button>
</nav>
<main class="flex-grow pt-32 pb-section-gap px-margin-desktop bg-[url('https://images.unsplash.com/photo-1518605368461-1b1d1f054f0a?q=80&amp;w=2070&amp;auto=format&amp;fit=crop')] bg-cover bg-center bg-fixed bg-blend-overlay bg-surface/90">
<!-- Header Section -->
<div class="mb-gutter text-center">
<h1 class="font-display-lg text-display-lg text-primary uppercase mb-4 shadow-black drop-shadow-md">HỆ THỐNG CƠ SỞ</h1>
<p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">Khám phá không gian nhậu Tự Do với hơn 15 địa điểm phủ khắp Hà Nội. Chọn cơ sở gần bạn nhất để tận hưởng không khí sôi động và ẩm thực tuyệt đỉnh.</p>
</div>
<!-- Filter Section -->
<div class="mb-12 flex flex-wrap justify-center gap-4">
<button class="bg-primary text-on-primary font-label-lg px-4 py-2 border border-primary shadow-[2px_2px_0px_0px_rgba(246,162,54,1)]">Tất cả</button>
<button class="bg-surface-container text-on-surface font-label-lg px-4 py-2 border border-outline hover:border-primary transition-colors">Đống Đa</button>
<button class="bg-surface-container text-on-surface font-label-lg px-4 py-2 border border-outline hover:border-primary transition-colors">Hai Bà Trưng</button>
<button class="bg-surface-container text-on-surface font-label-lg px-4 py-2 border border-outline hover:border-primary transition-colors">Hoàn Kiếm</button>
<button class="bg-surface-container text-on-surface font-label-lg px-4 py-2 border border-outline hover:border-primary transition-colors">Cầu Giấy</button>
</div>
<!-- Locations Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-gutter">
<!-- Location Card 1 -->
<div class="bg-surface-container-high border-2 border-outline-variant p-4 flex flex-col group hover:border-primary transition-colors">
<div class="h-48 bg-surface-variant w-full mb-4 relative overflow-hidden border border-outline-variant">
<img alt="Cơ sở Quán Nhậu Tự Do - Không gian ngoài trời" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="A bustling outdoor restaurant space with an industrial brutalist aesthetic at night. Bare concrete walls, exposed metal beams, and warm Edison bulb lighting. Tables are filled with people enjoying food and drinks. The atmosphere is vibrant, high-energy, and authentically street-style, featuring high-contrast warm orange lighting against deep black shadows." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCrOqPc2GpiJqj6bamhht9Aq2QUjRs32Tcl2gn3phqrNab0oOZxELtzOtuap11oG0dj5Dir59S_8sUKd1YxGJC4xddly3CMpp2ZykV2A8jlWzX7OLgjzXQ0959Lvx1i2Al4wdVsY4WD09mIMEaYStkFLE4Z_SWe69tC5ymJyrVvY6mcuklk9YDJWoiuWoprzIM1Ozcz-O9xtUjLa-zpwIvtefzfyL7vkL_XUfp9uU72yYQm8WBuuatR11-IaSFUZFBe8VSgc14sugU"/>
<div class="absolute top-2 left-2 bg-primary text-on-primary font-label-lg px-2 py-1 uppercase font-bold">Đống Đa</div>
</div>
<h3 class="font-headline-md text-headline-md text-primary mb-2 uppercase">Cơ sở 1: Nguyễn Trãi</h3>
<div class="flex items-start gap-2 mb-2 text-on-surface-variant font-body-md">
<span class="material-symbols-outlined text-primary mt-1 text-[20px]" data-icon="location_on" data-weight="fill">location_on</span>
<p>Số 10 Nguyễn Trãi, P. Ngã Tư Sở, Q. Đống Đa, Hà Nội</p>
</div>
<div class="flex items-center gap-2 mb-6 text-on-surface-variant font-body-md">
<span class="material-symbols-outlined text-primary text-[20px]" data-icon="call" data-weight="fill">call</span>
<p>098.123.4567</p>
</div>
<div class="mt-auto flex gap-4">
<button class="flex-1 bg-transparent border-2 border-outline-variant text-on-surface font-label-lg py-2 hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[18px]" data-icon="directions">directions</span> Chỉ đường
                    </button>
<button class="flex-1 bg-primary text-on-primary font-label-lg py-2 font-bold shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-primary-container transition-colors">
                        ĐẶT BÀN
                    </button>
</div>
</div>
<!-- Location Card 2 -->
<div class="bg-surface-container-high border-2 border-outline-variant p-4 flex flex-col group hover:border-primary transition-colors">
<div class="h-48 bg-surface-variant w-full mb-4 relative overflow-hidden border border-outline-variant">
<img alt="Cơ sở Quán Nhậu Tự Do - Không gian trong nhà" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="Interior view of an industrial chic bar and restaurant. Heavy use of metal grating, dark concrete floors, and distressed brick walls. Bright orange neon signs provide striking contrast against the dark environment. The layout features sturdy wooden tables with metal bases, conveying a raw, robust, and permanent structural feel." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCGg5lRpUBLBxOene1qJu4dp-6aUxOFnYbuXBME6hFCBS96VeLj0oopl2zJZ63WyhkkHtR_Uzccgy6VrkesWu2X6kr8KVy62UhjwOi5rQIX65qBAjAu-nM4xHXpomvy6vvCVG302Z2c3xWyc_JtuFENN_GvEN4x3iSOgsmIHxbqrBpgQTDv_EboOoLyUDvQOxGdyEphpD4JBFQ4-uFFCReim6auSXaj-tExuaY2TvxmvEQGVkuwQQDBp515_WB0w-Ccu4mp4UxvExc"/>
<div class="absolute top-2 left-2 bg-primary text-on-primary font-label-lg px-2 py-1 uppercase font-bold">Hai Bà Trưng</div>
</div>
<h3 class="font-headline-md text-headline-md text-primary mb-2 uppercase">Cơ sở 2: Trần Đại Nghĩa</h3>
<div class="flex items-start gap-2 mb-2 text-on-surface-variant font-body-md">
<span class="material-symbols-outlined text-primary mt-1 text-[20px]" data-icon="location_on" data-weight="fill">location_on</span>
<p>Số 45 Trần Đại Nghĩa, P. Bách Khoa, Q. Hai Bà Trưng, Hà Nội</p>
</div>
<div class="flex items-center gap-2 mb-6 text-on-surface-variant font-body-md">
<span class="material-symbols-outlined text-primary text-[20px]" data-icon="call" data-weight="fill">call</span>
<p>098.234.5678</p>
</div>
<div class="mt-auto flex gap-4">
<button class="flex-1 bg-transparent border-2 border-outline-variant text-on-surface font-label-lg py-2 hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[18px]" data-icon="directions">directions</span> Chỉ đường
                    </button>
<button class="flex-1 bg-primary text-on-primary font-label-lg py-2 font-bold shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-primary-container transition-colors">
                        ĐẶT BÀN
                    </button>
</div>
</div>
<!-- Location Card 3 -->
<div class="bg-surface-container-high border-2 border-outline-variant p-4 flex flex-col group hover:border-primary transition-colors">
<div class="h-48 bg-surface-variant w-full mb-4 relative overflow-hidden border border-outline-variant">
<img alt="Cơ sở Quán Nhậu Tự Do - Không gian Rooftop" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" data-alt="A rooftop restaurant space at dusk with an industrial brutalist design language. Rough concrete pillars, oxidized metal railings, and string lights providing warm illumination. The city skyline is visible in the background. The scene is energetic, featuring deep black shadows contrasted by the glowing orange lights and vibrant atmosphere." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCX2y88Qfd49cDALh0qDC7a_64Q4x0u-Z_NRS41gB1mxba4KITbwo7A8F1CprnzHSlBhNFah5s0ft-G6ZNQutJLSYr0ZduJFyHgJ1fSoFPOZBmhDYLRMDCTBPK3KG5Nnj0RBH0T7SADnjsnoXT_IjzbiSfo6CBRlHhlNyzEHUDHiK2jYK3u6pMzf03hb8tXtjuUvPoseKF71-5KCXIFtk_bhcQSBXTaOUZiGqcUWevCZdM0BMrKF9CnFIGLASFvJ90R3vf00_0x6c4"/>
<div class="absolute top-2 left-2 bg-primary text-on-primary font-label-lg px-2 py-1 uppercase font-bold">Hoàn Kiếm</div>
</div>
<h3 class="font-headline-md text-headline-md text-primary mb-2 uppercase">Cơ sở 3: Tràng Thi</h3>
<div class="flex items-start gap-2 mb-2 text-on-surface-variant font-body-md">
<span class="material-symbols-outlined text-primary mt-1 text-[20px]" data-icon="location_on" data-weight="fill">location_on</span>
<p>Số 12 Tràng Thi, P. Hàng Trống, Q. Hoàn Kiếm, Hà Nội</p>
</div>
<div class="flex items-center gap-2 mb-6 text-on-surface-variant font-body-md">
<span class="material-symbols-outlined text-primary text-[20px]" data-icon="call" data-weight="fill">call</span>
<p>098.345.6789</p>
</div>
<div class="mt-auto flex gap-4">
<button class="flex-1 bg-transparent border-2 border-outline-variant text-on-surface font-label-lg py-2 hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[18px]" data-icon="directions">directions</span> Chỉ đường
                    </button>
<button class="flex-1 bg-primary text-on-primary font-label-lg py-2 font-bold shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:bg-primary-container transition-colors">
                        ĐẶT BÀN
                    </button>
</div>
</div>
</div>
</main>
<!-- Footer -->
<footer class="w-full py-section-gap px-margin-desktop grid grid-cols-1 md:grid-cols-4 gap-gutter bg-surface-container-lowest border-t-4 border-double border-outline-variant">
<div class="md:col-span-1">
<div class="font-headline-lg text-primary uppercase italic mb-4">QUÁN NHẬU TỰ DO</div>
<p class="font-body-md text-body-md text-on-surface-variant">© 2024 QUÁN NHẬU TỰ DO - HỆ THỐNG QUÁN NHẬU GIẢI TRÍ SỐ 1 HÀ NỘI</p>
</div>
<div class="flex flex-col gap-2">
<a class="text-on-surface-variant font-label-lg hover:text-primary transition-colors duration-200" href="#">Chính sách bảo mật</a>
<a class="text-on-surface-variant font-label-lg hover:text-primary transition-colors duration-200" href="#">Điều khoản sử dụng</a>
</div>
<div class="flex flex-col gap-2">
<a class="text-on-surface-variant font-label-lg hover:text-primary transition-colors duration-200" href="#">Tuyển dụng</a>
<a class="text-on-surface-variant font-label-lg hover:text-primary transition-colors duration-200" href="#">Hợp tác kinh doanh</a>
</div>
</footer>
</body></html>