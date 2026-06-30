<?php
/* =====================================================================
   OneCoat Solution Kit 2026 — LANDING PAGE STANDALONE
   Bản tách rời, KHÔNG phụ thuộc head.php/foot.php/config.php của hệ thống.
   Dùng để deploy lên WordPress (hoặc bất kỳ host PHP nào).
   - Font + Icons nạp qua CDN.
   - Ảnh đặt trong thư mục ./img (đường dẫn tương đối).
   - CSS tách ở ./style.css
   ---------------------------------------------------------------------
   TRIỂN KHAI WORDPRESS:
   • Cách 1 (đơn giản): upload cả thư mục /solution lên web root, truy cập
     https://domain/solution/  → trang chạy độc lập, không qua theme WP.
   • Cách 2 (trong theme): tạo Page Template, copy phần <style>/HTML/<script>
     bên dưới vào template, đổi src ảnh sang thư mục theme.
   ===================================================================== */

$IMG = 'img'; // thư mục ảnh tương đối

// 12 vấn đề bề mặt (marquee)
$surfaceCases = [
    ['ic' => 'bi-window', 'txt' => 'Kính bong tróc'],
    ['ic' => 'bi-tree',   'txt' => 'Gỗ ngoài trời bạc màu'],
    ['ic' => 'bi-water',  'txt' => 'Hồ bơi trơn trượt'],
    ['ic' => 'bi-car-front-fill', 'txt' => 'Sàn gara xuống cấp'],
    ['ic' => 'bi-droplet-half', 'txt' => 'Muối biển ăn mòn'],
    ['ic' => 'bi-bricks', 'txt' => 'Tường nứt chân chim'],
    ['ic' => 'bi-nut-fill', 'txt' => 'Kim loại gỉ sét'],
    ['ic' => 'bi-moisture', 'txt' => 'Bê tông thấm nước'],
    ['ic' => 'bi-thermometer-sun', 'txt' => 'Mái tôn nóng bức'],
    ['ic' => 'bi-grid-1x2-fill', 'txt' => 'Nền epoxy bong rộp'],
    ['ic' => 'bi-gem', 'txt' => 'Đá tự nhiên ố mốc'],
    ['ic' => 'bi-layers-half', 'txt' => 'Sơn cũ phấn hoá'],
];

// Hạng mục BỀ MẶT CÔNG TRÌNH / DỰ ÁN
$projectSurfaces = [
    ['ic' => 'bi-bricks',          'title' => 'Kết cấu thép & kim loại',  'desc' => 'Khung thép, lan can, hàng rào, kết cấu nhà xưởng — chống ăn mòn, gỉ sét.'],
    ['ic' => 'bi-house-up',        'title' => 'Mái tôn & vách công trình', 'desc' => 'Mái tôn, vách kim loại, ống dẫn — chịu nhiệt, chống gỉ ngoài trời.'],
    ['ic' => 'bi-layers',          'title' => 'Bê tông & sàn dự án',       'desc' => 'Sàn gara, tầng hầm, nền nhà xưởng, bãi đỗ — chống thấm, chịu mài mòn.'],
    ['ic' => 'bi-water',           'title' => 'Hồ bơi & khu vực ẩm',       'desc' => 'Thành hồ, sàn ướt, khu kỹ thuật — chống trơn trượt, kháng nước & hoá chất.'],
    ['ic' => 'bi-buildings',       'title' => 'Tường ngoài & mặt dựng',    'desc' => 'Tường ngoại thất, mặt dựng công trình — chống nứt chân chim, phấn hoá.'],
    ['ic' => 'bi-tsunami',         'title' => 'Công trình ven biển',       'desc' => 'Hạng mục chịu muối biển, độ ẩm cao — kháng ăn mòn môi trường mặn.'],
];

// Ảnh sản phẩm cho dải hero
$heroProducts = ['9100.png', 'watertite.png', 'sealkrete.png', '7781.png', 'pro.png'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>OneCoat Solution Kit 2026 | Đại hội Kiến trúc sư 2026</title>
    <meta name="description" content="KTS thiết kế ý tưởng — OneCoat giải quyết bề mặt. Đăng ký nhận miễn phí ONECOAT SOLUTION KIT 2026 tại Đại hội Kiến trúc sư 2026.">

    <!-- Font Montserrat + Bootstrap Icons qua CDN (không phụ thuộc hệ thống) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- CSS landing page -->
    <link rel="stylesheet" href="style.css">
</head>
<body style="margin:0;">

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
        <!-- Ảnh nền hero — đổi file trong ./img nếu muốn -->
        <div class="lp-hero-bg" style="background-image:url('<?= $IMG ?>/hero_banner.png');"></div>
        <div class="wrap">
            <div class="hero-content">
                <div class="lp-badge-row reveal in">
                    <span class="bd"><i class="bi bi-calendar-event"></i> Đại hội Kiến trúc sư 2026</span>
                    <span class="bd">OneCoat · King of Paint</span>
                </div>
                <h1 class="reveal in d1">KTS THIẾT KẾ Ý TƯỞNG<br><span class="accent">ONECOAT GIẢI QUYẾT BỀ MẶT</span></h1>
                <p class="lead reveal in d2">Mọi bề mặt khó — kính, gỗ ngoài trời, hồ bơi, sàn gara, tường nứt — OneCoat đều có giải pháp. Đăng ký nhận ngay bộ kit giải pháp dành riêng cho Kiến trúc sư.</p>
                <div class="hero-cta reveal in d3">
                    <a href="#dangky" class="btn-accent"><i class="bi bi-gift-fill"></i> Đăng ký nhận kit</a>
                    <a href="#ap-dung" class="btn-ghost"><i class="bi bi-buildings"></i> Phạm vi áp dụng</a>
                </div>
                <div class="hero-stats reveal in d4">
                    <div class="st"><b data-count="12">0</b><span>Giải pháp bề mặt</span></div>
                    <div class="st"><b data-count="100" data-suffix="%">0</b><span>Tư vấn kỹ thuật</span></div>
                    <div class="st"><b data-count="1" data-suffix=" lớp">0</b><span>One Coat phủ kín</span></div>
                </div>
            </div>

            <div class="lp-video-wrap reveal in d2">
                <span class="lp-vtag"><i class="bi bi-play-fill"></i> 12 video</span>
                <!-- Video/GIF dọc 9:16 — gắn video/poster thật vào đây -->
                <div class="lp-sol-video">
                    <!--
                    <video src="video/onecoat-solution.mp4" poster="<?= $IMG ?>/video-poster.jpg" autoplay muted loop playsinline></video>
                    -->
                    <div class="video-ph">
                        <div class="pbtn"><i class="bi bi-play-fill"></i></div>
                        <span>Video dọc 9:16<br>(12 video minh hoạ vấn đề bề mặt)</span>
                    </div>
                </div>
                <div class="lp-hero-strip">
                    <?php foreach ($heroProducts as $p): ?>
                    <div class="pic"><img src="<?= $IMG . '/' . $p ?>" alt="" loading="lazy"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- marquee 12 vấn đề bề mặt -->
        <div class="lp-marquee">
            <div class="track">
                <?php for ($k = 0; $k < 2; $k++): foreach ($surfaceCases as $c): ?>
                <span class="mi"><i class="bi <?= $c['ic'] ?>"></i><?= $c['txt'] ?></span>
                <?php endforeach; endfor; ?>
            </div>
        </div>
    </section>

    <!-- ===== HỆ SƠN ÁP DỤNG CHO (bề mặt công trình / dự án) ===== -->
    <section class="lp-apply" id="ap-dung">
        <div class="wrap">
            <span class="eyebrow reveal" style="color:var(--c-accent)">Phạm vi áp dụng</span>
            <h2 class="sec-title reveal d1">Giải pháp cho bề mặt CÔNG TRÌNH &amp; DỰ ÁN</h2>
            <p class="sec-sub reveal d1" style="color:rgba(255,255,255,.75)">Hệ sơn OneCoat được thiết kế cho các hạng mục bề mặt công trình, dự án — nơi đòi hỏi độ bền, chống ăn mòn và chịu môi trường khắc nghiệt.</p>
            <div class="lp-apply-grid">
                <?php foreach ($projectSurfaces as $i => $s): ?>
                <div class="lp-apply-card reveal d<?= ($i % 3) + 1 ?>">
                    <div class="ac-ico"><i class="bi <?= $s['ic'] ?>"></i></div>
                    <h3><?= $s['title'] ?></h3>
                    <p><?= $s['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="lp-apply-note reveal d1">
                <i class="bi bi-info-circle-fill"></i>
                <span><strong>Lưu ý:</strong> Hệ sơn này dành riêng cho bề mặt công trình &amp; dự án phù hợp — <strong>không áp dụng</strong> cho các vật dụng nội thất gia dụng trong nhà.</span>
            </div>
        </div>
    </section>

    <!-- ================= SECTION 2 — FORM ĐĂNG KÝ ================= -->
    <section class="lp-form-sec" id="dangky">
        <div class="wrap">
            <div class="form-left reveal">
                <span class="eyebrow">Nhận miễn phí</span>
                <h2 class="sec-title">ONECOAT SOLUTION KIT 2026</h2>
                <p class="sec-sub">Dành riêng cho Kiến Trúc Sư tham dự sự kiện.</p>
                <!-- Hình hộp quà tặng — thay ảnh kit-box.png vào ./img -->
                <div class="lp-gift-box">
                    <!-- <img src="<?= $IMG ?>/kit-box.png" alt="OneCoat Solution Kit 2026"> -->
                    <i class="bi bi-box2-heart-fill"></i>
                    <span>ONECOAT SOLUTION KIT 2026</span>
                </div>
                <div class="lp-gift-feats">
                    <span class="gf"><i class="bi bi-box-seam"></i> Sample vật liệu</span>
                    <span class="gf"><i class="bi bi-file-earmark-text"></i> Tài liệu kỹ thuật</span>
                    <span class="gf"><i class="bi bi-headset"></i> Tư vấn 1-1</span>
                </div>
            </div>

            <form id="solutionForm" class="lp-form reveal d1" novalidate>
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
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" autocomplete="email" placeholder="email@congty.vn">
                    <div class="err">Email không hợp lệ.</div>
                </div>
                <div class="field">
                    <label>Công ty <span class="req">*</span></label>
                    <input type="text" name="company" autocomplete="organization" placeholder="Tên công ty / văn phòng KTS">
                    <div class="err">Vui lòng nhập công ty.</div>
                </div>
                <div class="field">
                    <label>Lĩnh vực hoạt động <span class="req">*</span></label>
                    <input type="text" name="field" placeholder="VD: Kiến trúc, Nội thất, Cảnh quan...">
                    <div class="err">Vui lòng nhập lĩnh vực hoạt động.</div>
                </div>
                <button type="submit" class="btn-accent"><i class="bi bi-send-fill"></i> Đăng ký nhận kit</button>
            </form>
        </div>
    </section>

    <!-- ================= SECTION 3 — THÔNG TIN NHẬN QUÀ ================= -->
    <section class="lp-info">
        <div class="wrap">
            <div class="lp-info-card reveal">
                <h3>SAU KHI ĐĂNG KÝ</h3>
                <p style="font-size:14px;color:#5a6b62;margin:0 0 4px;">Vui lòng đến gian hàng OneCoat để nhận:</p>
                <div class="kit-name">🎁 ONECOAT SOLUTION KIT 2026</div>
                <p style="font-size:13px;color:#8a9a91;font-weight:700;margin:0 0 8px;">Bao gồm:</p>
                <ul>
                    <li>Bộ sample vật liệu thực tế</li>
                    <li>Tài liệu kỹ thuật tham khảo</li>
                </ul>
            </div>
            <div class="lp-info-card lp-center reveal d1">
                <h3>BẠN ĐANG CÓ VẬT LIỆU KHÓ HOẶC KÉN SƠN?</h3>
                <p style="opacity:.95;margin-bottom:6px;">OneCoat hỗ trợ test sampling và tư vấn giải pháp miễn phí.</p>
                <div class="center-name">ONECOAT SOLUTION CENTER</div>
                <div class="center-addr"><i class="bi bi-geo-alt-fill"></i> 458 Điện Biên Phủ, Gia Định, TP.HCM</div>
                <p>Mang mẫu vật liệu đến hoặc liên hệ đội ngũ kỹ thuật để được hỗ trợ.</p>
            </div>
        </div>
    </section>

    <!-- ===== STICKY CTA (chỉ hiện trên mobile, sau khi cuộn qua hero) ===== -->
    <a href="#dangky" class="lp-sticky-cta" id="lpStickyCta">
        <i class="bi bi-gift-fill"></i> Đăng ký nhận kit miễn phí
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
(function(){
    function hideLoader() {
        var loader = document.getElementById('lpLoader');
        if (loader && !loader.classList.contains('is-hidden')) {
            loader.classList.add('is-hidden');
        }
    }
    window.addEventListener('load', hideLoader);
    setTimeout(hideLoader, 2500);
})();

(function(){
    // ---- smooth scroll
    document.querySelectorAll('.lp-sol a[href^="#"]').forEach(function(a){
        a.addEventListener('click', function(e){
            var id = a.getAttribute('href').slice(1);
            var t = document.getElementById(id);
            if (t){ e.preventDefault(); t.scrollIntoView({behavior:'smooth', block:'start'}); }
        });
    });

    // ---- scroll reveal
    var revs = document.querySelectorAll('.lp-sol .reveal');
    if ('IntersectionObserver' in window){
        var io = new IntersectionObserver(function(es){
            es.forEach(function(en){ if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); } });
        }, {threshold:.12});
        revs.forEach(function(el){ if(!el.classList.contains('in')) io.observe(el); });
    } else { revs.forEach(function(el){ el.classList.add('in'); }); }

    // ---- sticky CTA: hiện sau khi cuộn qua hero, ẩn khi hero còn trong viewport
    var stickyCta = document.getElementById('lpStickyCta');
    var hero = document.querySelector('.lp-sol-hero');
    var lpRoot = document.querySelector('.lp-sol');
    function showSticky(on){
        if(!stickyCta) return;
        stickyCta.classList.toggle('show', on);
        if(lpRoot) lpRoot.classList.toggle('sticky-on', on);
    }
    if (stickyCta && hero && 'IntersectionObserver' in window){
        var ioS = new IntersectionObserver(function(es){
            es.forEach(function(en){ showSticky(!en.isIntersecting); });
        }, {threshold:0});
        ioS.observe(hero);
    }

    // ---- count up
    function countUp(el){
        var target = parseFloat(el.getAttribute('data-count')) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        var dur = 1100, start = null;
        function step(ts){
            if(!start) start = ts;
            var p = Math.min((ts - start)/dur, 1);
            el.textContent = Math.round(target * p) + suffix;
            if(p < 1) requestAnimationFrame(step); else el.textContent = target + suffix;
        }
        requestAnimationFrame(step);
    }
    var counters = document.querySelectorAll('.lp-sol [data-count]');
    if ('IntersectionObserver' in window){
        var ioC = new IntersectionObserver(function(es){
            es.forEach(function(en){ if(en.isIntersecting){ countUp(en.target); ioC.unobserve(en.target); } });
        }, {threshold:.6});
        counters.forEach(function(el){ ioC.observe(el); });
    } else { counters.forEach(countUp); }

    // ---- form (client-only — chưa gửi dữ liệu đi đâu)
    // GỢI Ý DEPLOY: nối tới WordPress (admin-ajax / REST), Google Form, hoặc
    // dịch vụ form (Formspree...) bằng cách thay phần hiển thị màn cảm ơn dưới đây
    // bằng 1 lệnh fetch() POST tới endpoint của bạn.
    var form = document.getElementById('solutionForm');
    if (!form) return;
    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    function setInvalid(input, bad){ input.classList.toggle('is-invalid', !!bad); }

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

    form.addEventListener('submit', function(e){
        e.preventDefault();
        var f = form.elements;
        var name=f['name'].value.trim(), phone=f['phone'].value.trim(), email=f['email'].value.trim(),
            company=f['company'].value.trim(), field=f['field'].value.trim();

        setInvalid(f['name'],    name==='');
        setInvalid(f['phone'],   !isValidVNPhone(phone));
        setInvalid(f['email'],   !emailRe.test(email));
        setInvalid(f['company'], company==='');
        setInvalid(f['field'],   field==='');

        if (form.querySelector('.is-invalid')){ form.querySelector('.is-invalid').focus(); return; }
        var ty = document.getElementById('thankYouScreen');
        if (ty){ ty.classList.add('show'); window.scrollTo(0,0); }
        showSticky(false); // ẩn sticky CTA khi đã đăng ký xong
    });
    form.querySelectorAll('input').forEach(function(inp){
        inp.addEventListener('input', function(){ inp.classList.remove('is-invalid'); });
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

</body>
</html>
