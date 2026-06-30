# Hướng dẫn deploy LP thành trang con WordPress `/architect/solution`

Mục tiêu: trang chạy tại **`https://paintandmore.vn/architect/solution`**, tích hợp trong theme
(có **header + footer** của theme), URL đúng dạng 2 cấp (trang cha `architect` → trang con `solution`).

---

## 📁 Các file trong gói này

```
wordpress/
├── page-solution.php                    ← Template tự động cho Page slug "solution"
├── assets/
│   ├── solution-lp.css                  ← CSS landing page (copy vào theme/assets/)
│   └── solution-img/                    ← Ảnh (copy vào theme/assets/)
│       ├── hero_banner.png
│       ├── 9100.png  watertite.png  sealkrete.png  7781.png  pro.png
```

---

## ⚠️ Theme của bạn: `hello-theme-child-master` (child theme của Hello Elementor)

✅ Đây CHÍNH LÀ child theme — quá tốt, file thêm vào sẽ **không mất khi update** theme cha
(Hello Elementor). Mọi đường dẫn dưới đây trỏ thẳng vào:

```
wp-content/themes/hello-theme-child-master/
```

**Lưu ý Hello Elementor:** trang này là **template PHP thuần**, KHÔNG cần Elementor để render.
Trang sẽ hiện đúng dù bạn có/không dùng Elementor cho các trang khác. (Header/footer vẫn là của
Hello Elementor qua `get_header()`/`get_footer()`.)

**Convention theme:** child theme này dùng `page-<slug>.php` (đã có sẵn `page-cart.php`,
`page-checkout.php`, `page-color-visual.php`…). File `page-solution.php` sẽ được WordPress **tự động**
dùng cho Page có slug `solution` — **không cần** chọn Template thủ công trong Page Attributes.

---

## BƯỚC 1 — Copy file vào child theme

Qua FTP / File Manager của hosting, copy vào `wp-content/themes/hello-theme-child-master/`:

1. `page-solution.php`
   → `wp-content/themes/hello-theme-child-master/page-solution.php`
2. Cả thư mục `assets/` → gộp vào `wp-content/themes/hello-theme-child-master/assets/`
   (theme đã có sẵn `assets/` — cứ copy gộp thêm vào, không ghi đè gì)
   - Kết quả: `…/hello-theme-child-master/assets/solution-lp.css`
   - Và:      `…/hello-theme-child-master/assets/solution-img/hero_banner.png` (+ 5 ảnh sản phẩm)

> Nếu muốn đặt ảnh/CSS chỗ khác, sửa 2 đường dẫn trong `page-solution.php`:
> `'/assets/solution-lp.css'` và biến `$img_base` (`'/assets/solution-img'`).

> ℹ️ Template dùng `get_stylesheet_directory()` / `get_stylesheet_directory_uri()` (không phải
> `get_template_directory…`), nên nó **luôn đọc từ child theme** `hello-theme-child-master` —
> đúng như mong muốn.

---

## BƯỚC 2 — Tạo trang cha `architect`

Để có URL **2 cấp** `/architect/solution`, cần 1 trang cha tên slug `architect`.

1. WP Admin → **Pages → Add New**.
2. Title: `Architect` (hoặc “Kiến trúc sư”). **Permalink (slug) phải là** `architect`.
3. Nội dung để trống cũng được (hoặc làm trang giới thiệu mảng KTS sau này).
4. **Publish**.

> Nếu sau này không muốn ai vào thẳng `/architect`, có thể để nó redirect hoặc để trống — không ảnh hưởng trang con.

---

## BƯỚC 3 — Tạo trang con `solution`

1. WP Admin → **Pages → Add New**.
2. Title: `Solution`. **Slug** = `solution`  ← bắt buộc đúng `solution` để khớp `page-solution.php`.
3. Bên phải, mục **Page Attributes**:
   - **Parent**: chọn `Architect` (trang vừa tạo ở Bước 2)  ← đây là thứ tạo URL 2 cấp.
   - **Template**: **để mặc định (Default)** — KHÔNG cần chọn gì. WordPress tự nhận `page-solution.php`
     theo slug.
4. **Publish**.

→ WordPress tự sinh URL: **`https://paintandmore.vn/architect/solution`** ✅

> Trang ra trắng / không đúng layout? → kiểm tra:
> • File tên đúng `page-solution.php` và nằm trong `hello-theme-child-master/` (theme đang active).
> • Slug Page đúng là `solution` (không phải `solution-2` do bị trùng — nếu trùng, xoá Page cũ rồi tạo lại).

---

## BƯỚC 4 — Refresh permalinks (nếu URL báo 404)

WP Admin → **Settings → Permalinks** → bấm **Save Changes** (không cần đổi gì).
Thao tác này “flush” rewrite rules, sửa lỗi 404 sau khi tạo trang cha-con.

---

## BƯỚC 5 — Kiểm tra

Mở `https://paintandmore.vn/architect/solution`:
- ✅ Có **header + footer** của theme bao quanh.
- ✅ Hero, marquee 12 case, form, sticky CTA (mobile) hiển thị đúng.
- ✅ Mobile (≤900px): sticky CTA hiện sau khi cuộn qua hero.
- ✅ Font Montserrat + icon (bi-*) hiển thị (nạp qua CDN trong template).

Test responsive: DevTools → device toolbar → 360 / 390 / 768 / 1280px.

---

## 🔧 Tuỳ chỉnh thường gặp

**Đổi ảnh nền hero / ảnh sản phẩm**: thay file trong `assets/solution-img/` (giữ đúng tên), hoặc
upload qua Media Library rồi sửa `$img_base` trong template thành URL Media.

**Bỏ header/footer theme (full-width)**: trong `page-solution.php`, đổi `get_header()`/
`get_footer()` thành `get_header('blank')`/… hoặc xoá 2 lệnh đó (cần tự xuất `wp_head()`/`wp_footer()`).

**Theme đã có Bootstrap Icons rồi**: xoá block `wp_enqueue_style('lp-sol-icons', …)` để tránh nạp trùng.

**CSS bị theme đè** (vd theme set `box-sizing`, `font` toàn cục): CSS đã được scope dưới `.lp-sol`,
nhưng nếu vẫn lệch, tăng độ ưu tiên bằng cách thêm `body .lp-sol …` hoặc dùng `!important` cho rule
bị đè. Báo mình biết chỗ lệch để xử lý cụ thể.

---

## 📨 BƯỚC TUỲ CHỌN — Lưu dữ liệu form (lead)

Hiện form **chỉ chạy client-side**: validate xong hiện màn cảm ơn, **chưa gửi dữ liệu đi đâu**.
Để thu lead, chọn 1 trong các cách:

- **Plugin form** (đơn giản nhất): cài Contact Form 7 / WPForms / Fluent Forms, tạo form 5 trường,
  rồi thay khối `<form>` trong template bằng shortcode plugin. Plugin lo việc lưu + gửi mail.
- **REST/admin-ajax tự code**: thêm `fetch('/wp-admin/admin-ajax.php', {method:'POST', …})` trong
  hàm submit (chỗ đã ghi chú trong file `.php`), kèm 1 handler `wp_ajax_nopriv_…` ở `functions.php`
  để ghi vào DB / gửi mail.
- **Google Form / Formspree**: POST sang endpoint ngoài — nhanh, không đụng DB WP.

Muốn mình code sẵn phương án nào, nhắn là làm tiếp.
