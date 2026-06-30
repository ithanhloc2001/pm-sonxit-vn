<?php if ($isAdmin): ?>
<script>var baseUrl = '<?= h($baseUrl) ?>';</script>
<!-- Thư viện media gallery -->
<link href="<?= h($baseUrl) ?>/assets/pm/media-gallery.css?v=<?= @filemtime(__DIR__ . '/assets/pm/media-gallery.css') ?: '1.0.0' ?>" rel="stylesheet">
<div id="mediaGalleryModal">
    <div class="mg-container">
        <div class="mg-header">
            <div class="mg-title">Thư viện Media</div>
            <button class="mg-close">&times;</button>
        </div>
        <div class="mg-tabs">
            <div class="mg-tab" data-tab="upload">Tải lên tệp mới</div>
            <div class="mg-tab active" data-tab="library">Tất cả các tập tin</div>
        </div>
        <div class="mg-body">
            <!-- View: Library -->
            <div id="mgViewLibrary" class="mg-content">
                <div class="mg-toolbar">
                    <div class="mg-search">
                        <input type="text" class="mg-input" placeholder="Tìm tệp...">
                    </div>
                    <select id="mgFilterType" class="mg-select">
                        <option value="">Tất cả loại media</option>
                        <option value="image">Hình ảnh</option>
                        <option value="video">Video</option>
                    </select>
                    <button class="mg-btn mg-btn-outline ms-auto" id="mgBtnSync" title="Đồng bộ tệp cũ từ thư mục upload">
                        <i class="fa-solid fa-rotate"></i> <span class="d-none d-sm-inline">Quét tệp cũ</span>
                    </button>
                </div>
                <div class="mg-grid-wrapper">
                    <div class="mg-grid">
                        <!-- Media items logic handled in media-gallery.js -->
                    </div>
                </div>
            </div>
            <!-- View: Upload -->
            <div id="mgViewUpload" class="mg-content" style="display: none;">
                <div class="mg-upload-area">
                    <div class="mg-upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <h3>Thả tệp để tải lên</h3>
                    <p>hoặc</p>
                    <button class="mg-btn mg-btn-primary">Chọn tệp</button>
                    <input type="file" id="mgFileInput" multiple style="display: none;">
                </div>
            </div>
            <!-- Sidebar -->
            <div class="mg-sidebar">
                <div style="color: var(--mg-text-muted); text-align:center">Chọn một tệp để xem chi tiết</div>
            </div>
            <div class="mg-mobile-info">
                <i class="fa-solid fa-circle-info"></i>
            </div>
        </div>
        <!-- Progress Bar -->
        <div class="mg-progress-container">
            <div class="mg-progress-bar">
                <div class="mg-progress-fill"></div>
            </div>
            <div class="mg-progress-text">Đang tải lên...</div>
        </div>
        <div class="mg-footer">
            <button class="mg-btn mg-btn-secondary">Hủy</button>
            <button class="mg-btn mg-btn-primary" id="mgBtnSelect" disabled>Chọn media</button>
        </div>
    </div>
</div>
<script src="<?= h($baseUrl) ?>/assets/pm/media-gallery.js?v=<?= @filemtime(__DIR__ . '/assets/pm/media-gallery.js') ?: '1.0.0' ?>"></script>
<!-- /./ -->

<!-- ===== Hộp chat ADMIN — trả lời chat khách (chỉ admin) ===== -->
<link rel="stylesheet" href="<?= h($baseUrl) ?>/assets/pm/admin-chat.css?v=<?= @filemtime(__DIR__ . '/assets/pm/admin-chat.css') ?: '1' ?>">
<button type="button" id="pmacLauncher" class="pmac-launcher" aria-label="Hộp chat hỗ trợ khách">
    <i class="bi bi-chat-left-dots-fill"></i>
    <span class="pmac-badge"></span>
</button>
<div id="pmacPanel" class="pmac-panel" role="dialog" aria-label="Hộp chat quản trị">
    <div class="pmac-head">
        <i class="bi bi-headset"></i><span class="t">Chat hỗ trợ khách hàng</span>
        <button type="button" class="pmac-expand" id="pmacExpand" aria-label="Phóng to" title="Phóng to"><i class="bi bi-arrows-fullscreen"></i></button>
        <button type="button" class="pmac-close" aria-label="Đóng">&times;</button>
    </div>
    <div class="pmac-main">
        <div class="pmac-sidebar">
            <div class="pmac-filters">
                <button type="button" class="pmac-filter-btn active" data-filter="all">Tất cả</button>
                <button type="button" class="pmac-filter-btn" data-filter="unread">Chưa đọc</button>
                <button type="button" class="pmac-filter-btn" data-filter="read">Đã đọc</button>
            </div>
            <div class="pmac-list" id="pmacList"><div class="pmac-list-empty">Đang tải...</div></div>
        </div>
        <div class="pmac-conv">
            <div class="pmac-conv-head" id="pmacConvHead" style="display:none;">
                <button type="button" class="pmac-back" id="pmacBack" aria-label="Quay lại danh sách"><i class="bi bi-arrow-left"></i></button>
                <div>
                    <span class="cn"></span> <span class="cp"></span>
                    <span class="pmac-assignee-badge" id="pmacAssignee" style="display:none; margin-left:8px;"></span>
                </div>
                <div class="pmac-actions">
                    <button type="button" class="pmac-close-sess"><i class="bi bi-x-circle me-1"></i>Đóng phiên</button>
                    <button type="button" class="pmac-delete-sess"><i class="bi bi-trash3-fill me-1"></i>Xoá</button>
                </div>
            </div>
            <div class="pmac-body" id="pmacBody"><div class="pmac-placeholder">Chọn một phiên chat bên trái để trả lời.</div></div>
            <div class="pmac-foot" id="pmacFoot" style="display:none;">
                <div id="pmacPreviews" class="pmac-previews"></div>
                <div class="pmac-inputrow">
                    <button type="button" id="pmacAttach" class="pmac-iconbtn pmac-attach" title="Đính kèm ảnh"><i class="bi bi-image"></i></button>
                    <button type="button" id="pmacSuggestProduct" class="pmac-iconbtn pmac-suggest" title="Gợi ý sản phẩm"><i class="bi bi-box-seam"></i></button>
                    <button type="button" id="pmacSuggestVoucher" class="pmac-iconbtn pmac-suggest" title="Gợi ý mã ưu đãi"><i class="bi bi-ticket-perforated"></i></button>
                    <input type="file" id="pmacFile" accept="image/*" multiple style="display:none;">
                    <textarea id="pmacText" rows="1" placeholder="Nhập tin trả lời..."></textarea>
                    <button type="button" id="pmacSend" class="pmac-iconbtn pmac-send" title="Gửi"><i class="bi bi-send-fill"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal gợi ý sản phẩm / mã ưu đãi (nổi giữa màn hình, tách khỏi khung chat) -->
<div class="pmac-modal" id="pmacProductPicker" style="display:none;">
    <div class="pmac-modal-backdrop" data-picker="product"></div>
    <div class="pmac-modal-box">
        <div class="pmac-picker-head">
            <span class="pmac-picker-title"><i class="bi bi-box-seam me-1"></i>Gợi ý sản phẩm</span>
            <button type="button" class="pmac-picker-close" data-picker="product" aria-label="Đóng">&times;</button>
        </div>
        <div class="pmac-picker-tools">
            <input type="text" id="pmacProductSearch" class="pmac-picker-search" placeholder="Tìm sản phẩm...">
            <select id="pmacProductCat" class="pmac-picker-cat"><option value="0">Tất cả danh mục</option></select>
        </div>
        <div class="pmac-picker-grid" id="pmacProductGrid"><div class="pmac-picker-empty">Nhập từ khoá hoặc chọn danh mục…</div></div>
    </div>
</div>
<div class="pmac-modal" id="pmacVoucherPicker" style="display:none;">
    <div class="pmac-modal-backdrop" data-picker="voucher"></div>
    <div class="pmac-modal-box">
        <div class="pmac-picker-head">
            <span class="pmac-picker-title"><i class="bi bi-ticket-perforated me-1"></i>Gợi ý mã ưu đãi</span>
            <button type="button" class="pmac-picker-close" data-picker="voucher" aria-label="Đóng">&times;</button>
        </div>
        <div class="pmac-picker-list" id="pmacVoucherList"><div class="pmac-picker-empty">Đang tải…</div></div>
    </div>
</div>
<script>
window.PMAC_CFG = {
    base: <?= json_encode(rtrim((string)($baseUrl ?? ''), '/'), JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode((string)($csrfToken ?? ''), JSON_UNESCAPED_SLASHES) ?>,
    endpoint: <?= json_encode(rtrim((string)($baseUrl ?? ''), '/') . '/core_admin/support/ajax/chat.php', JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= h($baseUrl) ?>/assets/pm/admin-chat.js?v=<?= @filemtime(__DIR__ . '/assets/pm/admin-chat.js') ?: '1' ?>"></script>
<?php endif; ?>

<!-- /./ -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="<?= h($baseUrl) ?>/assets/pm/footer.css?v=<?= @filemtime(__DIR__ . '/assets/pm/footer.css') ?: '1.0.0' ?>" rel="stylesheet">
<script src="<?= h($baseUrl) ?>/assets/bootstrap/lightbox.js?v=<?= @filemtime(__DIR__ . '/assets/bootstrap/lightbox.js') ?: '1.0.0' ?>"></script>
<?php
// Social links: ưu tiên lấy từ cấu hình động SOCIAL_*, fallback về URL mặc định
$fbHref = !empty($social_facebook) ? (string)$social_facebook : 'https://facebook.com/paintmore';
$insHref = !empty($social_instagram) ? (string)$social_instagram : 'https://www.instagram.com/';
$ytHref = !empty($social_youtube) ? (string)$social_youtube : 'https://www.youtube.com/';
$ttHref = !empty($social_tiktok) ? (string)$social_tiktok : 'https://www.tiktok.com/';
// Zalo: nếu có SOCIAL_ZALO thì dùng, nếu không mà có hotline thì tạo link zalo.me/{hotline_tel}
$zaloHref = '';
if (!empty($social_zalo)) {
    $zaloHref = (string)$social_zalo;
} elseif (!empty($site_hotline)) {
    $zaloTel = preg_replace('/[^0-9+]/', '', (string)$site_hotline);
    if ($zaloTel !== '') {
        $zaloHref = 'https://zalo.me/' . $zaloTel;
    }
}
if ($zaloHref === '') {
    $zaloHref = 'https://zalo.me/'. $hotline;
}
$List_Payments_Links = [
    [
        'title' => 'COD',
        'href' => 'javascript:void(0)',
        'image' => 'https://cdn-icons-png.flaticon.com/512/5278/5278605.png',
    ],
    [
        'title' => 'MoMo',
        'href' => 'javascript:void(0)',
        'image' => 'https://developers.momo.vn/v3/vi/img/logo.svg',
    ],
    [
        'title' => 'Vnpay',
        'href' => h($baseUrl) . '/blog/huong-dan-thanh-toan-vnpay',
        'image' => 'https://vnpay.vn/apple-touch-icon.png',
    ],
    [
        'title' => 'Zalopay',
        'href' => 'javascript:void(0)',
        'image' => 'https://simg.zalopay.com.vn/zlp-website/assets/new_logo_6c5db2d21b.svg',
    ],
];
$List_Social_Links = [
    [
        'label' => 'Facebook',
        'href' => h($fbHref),
        'icon' => h($baseUrl) . '/image/social/fb.svg',
    ],
    [
        'label' => 'Instagram',
        'href' => h($insHref),
        'icon' => h($baseUrl) . '/image/social/ins.svg',
    ],
    [
        'label' => 'YouTube',
        'href' => h($ytHref),
        'icon' => h($baseUrl) . '/image/social/yt.svg',
    ],
    [
        'label' => 'Zalo',
        'href' => h($zaloHref),
        'icon' => h($baseUrl) . '/image/social/zalol.svg',
    ],
    [
        'label' => 'TikTok',
        'href' => h($ttHref),
        'icon' => h($baseUrl) . '/image/social/tik.svg',
    ],
];
?>
<footer class="site-footer">
    <!-- Scroll to Top Button -->
    <button id="btnScrollTop" type="button" class="btn btn-outline-primary position-fixed" style="right:20px;bottom:92px;z-index:1030;display:none;width:44px;height:44px;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.12);background:#fff;">
        <i class="bi bi-arrow-up-short" style="font-size:1.6rem;"></i>
    </button>


    <div class="container footer-container">
        <!-- Footer SEO Links Section -->
        <?php
        // Lấy 10 sản phẩm ngẫu nhiên cho footer backlinks (SEO)
        $footerProducts = [];
        if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
            $productTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product']) : 'ecommerce_product';
            if ($productTable !== '') {
                $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
                
                $pCols = function_exists('list_table_columns') ? list_table_columns($ithanhloc, $productTable) : [];
                $productActiveExprSql = "status = 'true'";
                if (!empty($pCols)) {
                    if (in_array('status', $pCols, true)) {
                        $productActiveExprSql = "LOWER(TRIM(CAST(status AS CHAR))) IN ('true','1','on','yes','active','enabled')";
                    } elseif (in_array('is_active', $pCols, true)) {
                        $productActiveExprSql = "LOWER(TRIM(CAST(is_active AS CHAR))) IN ('1','true','on','yes')";
                    }
                }
                
                $vCols = ($variantTable !== '') ? (function_exists('list_table_columns') ? list_table_columns($ithanhloc, $variantTable) : []) : [];
                $variantActiveWhere = "";
                if (!empty($vCols)) {
                    if (in_array('status', $vCols, true)) {
                        $variantActiveWhere = " AND (status = 1 OR status = '1' OR LOWER(status) = 'true')";
                    } elseif (in_array('is_active', $vCols, true)) {
                        $variantActiveWhere = " AND is_active = 1";
                    }
                }
                
                $stockCheckSql = "0";
                if ($variantTable !== '') {
                    $stockCheckSql = "(EXISTS (SELECT 1 FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.stock_quantity > 0{$variantActiveWhere}))";
                }
                
                $sql = "SELECT id, product_name, slug 
                        FROM `{$productTable}` p 
                        WHERE TRIM(product_name) <> '' AND {$productActiveExprSql} 
                        ORDER BY {$stockCheckSql} DESC, id DESC 
                        LIMIT 10";
                $productRes = $ithanhloc->query($sql);
                if ($productRes instanceof mysqli_result) {
                    while ($row = $productRes->fetch_assoc()) {
                        $footerProducts[] = [
                            'id' => (int)($row['id'] ?? 0),
                            'name' => trim((string)($row['product_name'] ?? '')),
                            'slug' => trim((string)($row['slug'] ?? '')),
                        ];
                    }
                }
            }
        }
        // Lấy 10 bài viết blog mới nhất cho footer backlinks (SEO)
        $footerBlogPosts = [];
        if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
            $blogTablePosts = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_blog']) : 'ecommerce_blog';
            if ($blogTablePosts !== '') {
                $sqlBlogPosts = "SELECT id, title, slug FROM `{$blogTablePosts}` WHERE is_active = 1 ORDER BY published_at DESC, id DESC LIMIT 10";
                $resBlogPosts = $ithanhloc->query($sqlBlogPosts);
                if ($resBlogPosts instanceof mysqli_result) {
                    while ($row = $resBlogPosts->fetch_assoc()) {
                        $footerBlogPosts[] = [
                            'id' => (int)($row['id'] ?? 0),
                            'title' => trim((string)($row['title'] ?? '')),
                            'slug' => trim((string)($row['slug'] ?? '')),
                        ];
                    }
                }
            }
        }
        // Lấy 10 tags từ blog cho footer backlinks (SEO)
        $footerBlogTags = [];
        if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
            // Blog đang dùng bảng ecommerce_blog với cột tags dạng "tag1, tag2, ..."
            $blogTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_blog']) : 'ecommerce_blog';
            if ($blogTable !== '') {
                $blogRes = $ithanhloc->query("SELECT tags FROM `{$blogTable}` WHERE is_active=1 LIMIT 100");
                if ($blogRes instanceof mysqli_result) {
                    $tagCounts = [];
                    while ($row = $blogRes->fetch_assoc()) {
                        $tagsStr = trim((string)($row['tags'] ?? ''));
                        if ($tagsStr !== '') {
                            // Tách theo dấu phẩy
                            $parts = preg_split('/\\s*,\\s*/', $tagsStr);
                            if (is_array($parts)) {
                                foreach ($parts as $tag) {
                                    $tag = trim((string)$tag);
                                    if ($tag !== '') {
                                        if (!isset($tagCounts[$tag])) {
                                            $tagCounts[$tag] = 0;
                                        }
                                        $tagCounts[$tag]++;
                                    }
                                }
                            }
                        }
                    }
                    // Sắp xếp theo số lần xuất hiện và lấy top 10
                    if (!empty($tagCounts)) {
                        arsort($tagCounts);
                        $topTags = array_slice(array_keys($tagCounts), 0, 10);
                        foreach ($topTags as $tag) {
                            $footerBlogTags[] = [
                                'name' => $tag,
                                'slug' => function_exists('pm_slugify') ? pm_slugify($tag) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tag)),
                            ];
                        }
                    }
                }
            }
        }
        ?>
        <?php if (!empty($footerProducts) || !empty($footerBlogPosts) || !empty($footerBlogTags)): ?>
        <div class="row footer-seo-section">
            <?php if (!empty($footerProducts)): ?>
            <div class="col-12 col-md-4 mb-3">
                <h4 class="footer-seo-title">SẢN PHẨM NỔI BẬT</h4>
                <ul class="footer-seo-links">
                    <?php foreach ($footerProducts as $product): ?>
                        <?php
                            $pId = (int)($product['id'] ?? 0);
                            $pName = (string)($product['name'] ?? '');
                            $pUrl = function_exists('pm_product_url')
                                ? pm_product_url($pId, $pName, (string)$baseUrl)
                                : (rtrim((string)$baseUrl, '/') . '/' . (($product['slug'] ?? '') !== '' ? $product['slug'] : ('product-' . $pId)) . '.html');
                        ?>
                        <li>
                            <a href="<?= h($pUrl) ?>" 
                               title="<?= h($pName) ?>">
                                <?= h($pName) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($footerBlogPosts)): ?>
            <div class="col-12 col-md-4 mb-3">
                <h4 class="footer-seo-title">BÀI VIẾT TỪ BLOG</h4>
                <ul class="footer-seo-links">
                    <?php foreach ($footerBlogPosts as $post): ?>
                        <?php
                            $bSlug = (string)($post['slug'] ?? '');
                            $bTitle = (string)($post['title'] ?? 'Bài viết');
                            $bUrl = rtrim((string)$baseUrl, '/') . '/blog/' . rawurlencode($bSlug !== '' ? $bSlug : ('bai-viet-' . (int)($post['id'] ?? 0)));
                        ?>
                        <li>
                            <a href="<?= h($bUrl) ?>" 
                               title="<?= h($bTitle) ?>">
                                <?= h($bTitle) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($footerBlogTags)): ?>
            <div class="col-12 col-md-4 mb-3">
                <h4 class="footer-seo-title">CHỦ ĐỀ BLOG PHỔ BIẾN</h4>
                <ul class="footer-tag-links">
                    <?php foreach ($footerBlogTags as $tag): ?>
                        <li>
                            <a href="<?= h($baseUrl) ?>/blog?tag=<?= urlencode($tag['name']) ?>" 
                               title="Blog về <?= h($tag['name']) ?>">
                                #<?= h($tag['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <hr><br>
        <!-- /./ -->
        <div class="row g-4">
            <!-- THÔNG TIN CÔNG TY -->
            <div class="col-12 col-md-5" style="color:black">    
                <h3 class="footer-title">THÔNG TIN CÔNG TY</h3>
                <p><b><?= h($company_name) ?></b> là đơn vị phân phối sơn và giải pháp hoàn thiện bề mặt cho nhà thầu, kiến trúc sư và khách hàng cá nhân. 
                <p><h>- Mã số thuế: <b><?= h($company_tax_code) ?></b> cấp ngày <b>01/11/2006</b></h></p> 
                <p><h>- Giờ làm việc: <b>08:00 - 17:00 (Thứ 2 - Thứ 7)</b></p>
                <p><h>- Điện thoại: <a href="tel:<?= h($company_hotline) ?>" class="fw-semibold"><?= h($company_hotline) ?></a></p>
                <p><h>- Email: <a href="mailto:<?= h($company_email) ?>" class="fw-semibold"><?= h($company_email) ?></a></p>
                <p>Chịu trách nhiệm nội dung: <b><?= h($company_responsible_person) ?></b></p>
                <h3 class="footer-pay-title">LIÊN HỆ & KẾT NỐI</h3>
                <!--<p class="mb-1">Hotline: <a href="tel:<?= h($company_hotline) ?>" class="fw-semibold"><?= h($company_hotline) ?></a></p>
                <p class="mb-1">Email: <a href="mailto:<?= h($company_email) ?>"><?= h($company_email) ?></a></p>
                <p class="mb-1">Website: <a href="<?= h($baseUrl) ?>"><?= h($baseUrl) ?></a></p>
                -->
                <div class="footer-social mt-2">
                    <?php foreach ($List_Social_Links ?? [] as $social): ?>
                        <a href="<?= h($social['href']) ?>" target="_blank" rel="noopener" aria-label="<?= h($social['label']) ?>" class="footer-social-link" title="<?= h($social['label']) ?>">
                            <img src="<?= h($social['icon']) ?>" alt="<?= h($social['label']) ?>" loading="lazy" decoding="async">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- HỆ THỐNG TRÊN TOÀN QUỐC -->
            <div class="col-12 col-md-4">
                <h3 class="footer-title">HỆ THỐNG TRÊN TOÀN QUỐC</h3>
                <?php 
                // Lấy danh sách chi nhánh
                    $footerBranches = [];
                    if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
                        $tableExists = $ithanhloc->query("SHOW TABLES LIKE 'site_store'");
                        if ($tableExists instanceof mysqli_result && $tableExists->num_rows > 0) {
                            // Bắt buộc show tất cả chi nhánh đang active, không giới hạn 8 chi nhánh
                            $res = $ithanhloc->query("SELECT id, branch_name, region, hotline, address_detail, map_url FROM site_store WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
                            if ($res instanceof mysqli_result) {
                                while ($row = $res->fetch_assoc()) {
                                    $branchName = trim((string)($row['branch_name'] ?? ''));
                                    $region = trim((string)($row['region'] ?? ''));
                                    $hotlineRaw = trim((string)($row['hotline'] ?? ''));
                                    $address = trim((string)($row['address_detail'] ?? ''));
                                    $mapUrl = trim((string)($row['map_url'] ?? ''));

                                    if ($mapUrl !== '' && !preg_match('#^https?://#i', $mapUrl)) {
                                        $mapUrl = 'https://' . ltrim($mapUrl, '/');
                                    }
                                    $hotlineTel = preg_replace('/[^0-9+]/', '', $hotlineRaw);
                                    $footerBranches[] = [
                                        'id' => (int)($row['id'] ?? 0),
                                        'branch_name' => $branchName,
                                        'region' => $region,
                                        'region_key' => footer_region_key($region),
                                        'hotline_raw' => $hotlineRaw,
                                        'hotline_tel' => $hotlineTel,
                                        'address' => $address,
                                        'map_url' => $mapUrl,
                                    ];
                                }
                            }
                        }
                    }
                    ?>
                    <?php if (!empty($footerBranches)): ?>
                    <div class="store-tabs" id="footerStoreTabs">
                        <button type="button" class="store-tab-btn active" data-region="south">Khu vực: <br><b style="color:red">Miền Nam</b></button>
                        <button type="button" class="store-tab-btn" data-region="central">Khu vực: <br><b style="color:red">Miền Trung</b></button>
                        <button type="button" class="store-tab-btn" data-region="north">Khu vực: <br><b style="color:red">Miền Bắc</b></button>
                    </div>
                    <p class="store-empty" id="footerStoreEmpty">Chưa có chi nhánh thuộc khu vực này.</p>
                    <ul class="store-list" id="footerStoreList">
                        <?php foreach ($footerBranches as $branch): ?>
                            <li class="store-list__item cursor-pointer" store-id="<?= (int)($branch['id'] ?? 0) ?>" data-region="<?= h($branch['region_key'] ?? 'south') ?>">
                                <i class="bi bi-geo-alt-fill" style="font-size: 1.2rem;"></i>
                                <div>
                                    <span class="store-list__name"><?= h($branch['address'] ?: ($branch['branch_name'] ?: 'Chi nhánh')) ?></span>
                                    <?php if (!empty($branch['hotline_raw']) && !empty($branch['hotline_tel'])): ?>
                                        : <a href="tel:<?= h($branch['hotline_tel']) ?>" title="Hotline" aria-label="Hotline" onclick="event.stopPropagation();"><b class="phone-number"><?= h($branch['hotline_raw']) ?></b></a>
                                    <?php endif; ?>
                                    <?php if (!empty($branch['map_url'])): ?>
                                        - <a href="<?= h($branch['map_url']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();"><strong style="color: blue;">Bản đồ đường đi</strong></a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mb-3">Kho vận: Đang cập nhật thêm chi nhánh tại Hà Nội & Đà Nẵng.</p>
                <?php endif; ?>    
            </div>
            <!-- CHÍNH SÁCH & BẢO MẬT -->
            <div class="col-12 col-md-3">
                <h4 class="footer-title">CHÍNH SÁCH & BẢO MẬT</h4>
                <ul class="footer-links">
                    <li><a class="fw-bold" href="<?= h($baseUrl) ?>/terms.html">Chính sách & điều kiện chung</a></li>
                    <li><a class="fw-bold" href="<?= h($baseUrl) ?>/huong-dan-thanh-toan.html">Hướng dẫn thanh toán</a></li>
                    <li><a class="fw-bold" href="<?= h($baseUrl) ?>/blog/huong-dan-thanh-toan-vnpay">Hướng dẫn thanh toán VNPAY</a></li>   
                    <li><a class="fw-bold" href="<?= h($baseUrl) ?>/chinh-sach-van-chuyen.html">Chính sách vận chuyển</a></li>
                    <li><a class="fw-bold" href="<?= h($baseUrl) ?>/chinh-sach-doi-tra.html">Chính sách đổi trả & hoàn tiền</a></li>
                    <li><a class="fw-bold" href="<?= h($baseUrl) ?>/chinh-sach-bao-mat.html">Chính sách bảo mật</a></li>      
                </ul>
                <br>
               <h4 class="footer-title">ĐỐI TÁC</h4>
                <ul class="flex flex-wrap items-center gap-x-2 gap-y-0.5 footer-pay-list">
                    <?php foreach ($List_Payments_Links ?? [] as $payment): ?>
                        <li class="h-10 md:h-8">
                            <a href="<?= h($payment['href']) ?>" title="<?= h($payment['title']) ?>" class="inline-block" rel="noopener">
                                <span class="cps-image-cdn">
                                    <img alt="<?= h($payment['title']) ?>" loading="lazy" decoding="async" data-nimg="fill" class="transition-opacity duration-500 opacity-100 object-contain h-10 w-15 rounded-sm border border-neutral-100 object-contain md:h-8 md:w-12" style="position: absolute; height: 100%; width: 100%; inset: 0px; color: transparent;" src="<?= h($payment['image']) ?>">
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <!-- Xác nhận website -->
                <p class="">
                    <a href="<?= h($bocongthuong ?? 'http://online.gov.vn/Home/WebDetails/141354') ?>" target="_blank" rel="noopener" aria-label="Xác nhận website" class="m-0 p-0" title="Xác nhận website">
                    <img style="width:auto;height:60px" src="<?= h($baseUrl) ?>/image/bo-cong-thuong.webp" alt="Xác nhận website" loading="lazy" decoding="async">
                    </a>
                 </p>
                <?php 
                /* 
                <a href="<?= h($bocongthuong) ?>" target="_blank">
                    <img style="width:auto;height:40px" src="<?= h($baseUrl) ?>/image/bocongthuong-xacnhan.png" alt="Đã thông báo Bộ Công Thương" title="Đã thông báo Bộ Công Thương" style="max-width: 160px; border: none;">
                </a> 
                */ 
                 ?>
            </div> 
        </div>
    </div>
</footer>


<?php /*
<div class="container fb-main">
   <!--div class="m-4 pb-4 text-center" style="font-size: .9rem; color: var(--fb-text-sub);">© 2026 PAINT & MORE CORPORATION. Bản quyền được bảo lưu.</div>-->
    <p class="text-10 text-black text-left" style="font-size: .75rem; color: var(--fb-text-sub);">
     Công Ty Cổ phần TP. Hồ Chí Minh.
     Mã số doanh nghiệp: , nơi cấp: Sở Tài chính thành phố Hồ Chí Minh. </p>
    <p class="text-10 text-black text-left" style="font-size: .75rem; color: var(--fb-text-sub);">
     MST: . Đại diện theo pháp luật: - Điện thoại:(miễn phí) - Email: lienhe - Bản quyền thuộc về      
    </p>
</div>
*/ 
?>


<!-- SETTING THEME LAYOUT -->
<?php if (!empty($isAdmin)): ?>
<button id="layoutSettingsBtn" class="btn btn-primary layout-fab" title="Cài đặt bố cục"><i class="bi bi-gear"></i></button>
<div id="layoutPanel" class="layout-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>Cài đặt giao diện</strong>
        <button type="button" class="btn-close btn-sm" aria-label="Đóng" id="layoutPanelClose"></button>
    </div>
    <!--div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" id="toggleLeftSidebar">
        <label class="form-check-label" for="toggleLeftSidebar">Ẩn sidebar trái</label>
    </div>
    <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" id="toggleRightSidebar">
        <label class="form-check-label" for="toggleRightSidebar">Ẩn sidebar phải</label>
    </div>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="toggleHorizon">
        <label class="form-check-label" for="toggleHorizon">Horizon (full width)</label>
    </div>
     -->
    <div class="mb-3">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="dark-mode-icon-wrap" style="position:relative;width:1rem;height:1rem;">
                    <i class="bi bi-moon-stars-fill"></i>
                    <i class="bi bi-sun-fill"></i>
                </span>
                <label class="form-check-label small fw-semibold mb-0" for="toggleDarkMode">Chế độ tối</label>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="toggleDarkMode" style="cursor:pointer;">
            </div>
        </div>
    </div>
    <hr class="my-2" style="opacity:.15;">
    <div class="mb-2">
        <label class="form-label small fw-semibold">Màu chủ đạo</label>
        <div class="theme-swatches" id="themeSwatches">
            <button type="button" class="theme-swatch" data-theme="blue" style="--swatch:#1877f2" title="Xanh dương"></button>
            <button type="button" class="theme-swatch" data-theme="green" style="--swatch:#0b4b28" title="Xanh lá"></button>
            <button type="button" class="theme-swatch" data-theme="orange" style="--swatch:#ee4d2d" title="Cam"></button>
            <button type="button" class="theme-swatch" data-theme="teal" style="--swatch:#0f766e" title="Teal"></button>
            <button type="button" class="theme-swatch" data-theme="red" style="--swatch:#dc2626" title="Đỏ"></button>
        </div>
    </div>

    <!--div class="mb-2">
        <label class="form-label small fw-semibold">Căn lề logo</label>
        <div class="layout-segment" id="headerAlignSeg">
            <button type="button" class="layout-seg-btn" data-align="left">Trái</button>
            <button type="button" class="layout-seg-btn" data-align="center">Giữa</button>
        </div>
    </div-->
    <!-- <div class="mb-2">
        <label class="form-label small fw-semibold">Màu nền header</label>
        <div class="layout-segment" id="headerBgSeg">
            <button type="button" class="layout-seg-btn" data-bg="light">Sáng</button>
            <button type="button" class="layout-seg-btn" data-bg="dark">Tối</button>
            <button type="button" class="layout-seg-btn" data-bg="transparent">Trong suốt</button>
        </div>
    </div> -->
    <div class="mb-2 mt-3">
        <label class="form-label small fw-semibold">Màu chữ</label>
        <div class="theme-swatches" id="fontSwatches">
            <button type="button" class="font-swatch" data-font="default" style="--swatch:#0f172a" title="Mặc định"></button>
            <button type="button" class="font-swatch" data-font="graphite" style="--swatch:#1f2937" title="Graphite"></button>
            <button type="button" class="font-swatch" data-font="forest" style="--swatch:#0b4b28" title="Forest"></button>
            <button type="button" class="font-swatch" data-font="cocoa" style="--swatch:#3f2a1d" title="Cocoa"></button>
            <button type="button" class="font-swatch" data-font="berry" style="--swatch:#3b1d4a" title="Berry"></button>
        </div>
    </div>
    <div class="mb-2 mt-3">
        <label class="form-label small fw-semibold" for="fontFamilySelect">Font chữ hệ thống</label>
        <select class="form-select form-select-sm" id="fontFamilySelect">
            <option value="montserrat">Montserrat (mặc định)</option>
            <option value="helvetica">Helvetica Neue</option>
            <option value="system">Hệ thống (System UI)</option>
            <option value="arial">Arial / Helvetica</option>
            <option value="verdana">Verdana</option>
            <option value="tahoma">Tahoma</option>
            <option value="serif">Serif (Georgia)</option>
        </select>
    </div>
    <div class="mb-2 mt-2">
        <label class="form-label small fw-semibold" for="fontWeightSelect">Độ dày font chữ</label>
        <select class="form-select form-select-sm" id="fontWeightSelect">
            <option value="300">Light (300)</option>
            <option value="400">Regular (400 - Mặc định)</option>
            <option value="500">Medium (500)</option>
            <option value="600">Semi Bold (600)</option>
            <option value="700">Bold (700)</option>
        </select>
    </div>
    <div class="small text-muted">Tùy chỉnh được lưu vào trình duyệt của bạn.</div>
</div>
<?php endif; ?>


<!-- END SETTING THEME LAYOUT -->
<!-- DataTables (Bootstrap 5) - load in footer -->
<script src="<?= h($baseUrl) ?>/assets/js/jquery.dataTables.min.js?v=<?= @filemtime(__DIR__ . '/assets/js/jquery.dataTables.min.js') ?: '5.3.8' ?>"></script>
<script src="<?= h($baseUrl) ?>/assets/js/dataTables.bootstrap5.min.js?v=<?= @filemtime(__DIR__ . '/assets/js/dataTables.bootstrap5.min.js') ?: '5.3.8' ?>"></script>

<script src="<?= h($baseUrl) ?>/assets/js/chart.min.js?v=<?= @filemtime(__DIR__ . '/assets/js/chart.min.js') ?: '1.0.0' ?>"></script>
<script>
(function(window){
    'use strict';

    function toNumber(val){
        var n = Number(val);
        return isNaN(n) ? 0 : n;
    }

    function formatVNDShort(amount){
        var raw = toNumber(amount);
        // Làm tròn về 1.000đ gần nhất, chỉ làm tròn lên khi phần lẻ >= 500đ
        var n = raw > 0 ? Math.round(raw / 1000) * 1000 : 0;
        if (!isFinite(n)) return '0đ';
        try {
            return new Intl.NumberFormat('vi-VN').format(n) + 'đ';
        } catch(e){
            return String(Math.round(n)) + 'đ';
        }
    }

    function formatDate(raw){
        var txt = String(raw || '').trim();
        if (!txt) return '';
        var d = txt.split(' ')[0];
        var parts = d.split('-');
        if (parts.length !== 3) return txt;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }
    // Chuẩn hóa đơn vị: percent, vnd, ...
    function normalizeUnit(unit, fallback){
        var u = (unit !== undefined && unit !== null) ? String(unit).toLowerCase() : '';
        if (!u && fallback){
            u = String(fallback).toLowerCase();
        }
        if (u === 'percent' || u === '%') return 'percent';
        if (u === 'vnd' || u === 'amount' || u === 'fixed') return 'đ';
        return 'đ';
    }
    function primaryTarget(row){
        var raw = (row && row.discount_target) ? String(row.discount_target) : '';
        raw = raw.toLowerCase();
        if (raw.indexOf(',') !== -1){
            raw = raw.split(',')[0].trim();
        }
        return raw === 'shipping' ? 'shipping' : 'order';
    }

    function voucherValueLabel(row){
        if (!row || typeof row !== 'object') return '';
        var unit = normalizeUnit(row.value_unit, null);
        var value = toNumber(row.value || 0);
        var rawLabel = (row.value_label !== undefined && row.value_label !== null)
            ? String(row.value_label).trim()
            : '';
        if (rawLabel && /^miễn\s*phí$/i.test(rawLabel)) return 'Miễn phí';
        if (value <= 0){
            if (rawLabel){
                var compact = rawLabel.replace(/\s+/g, '').replace(/K$/,'k');
                return compact || rawLabel;
            }
            return '';
        }
        if (unit === 'percent'){
            if (value >= 100){
                return 'Miễn phí';
            }
            var pctTxt = value.toFixed(1).replace(/\.0$/, '');
            return 'Giảm ' + pctTxt + '%';
        }
        var roundedVal = value > 0 ? Math.round(value / 1000) * 1000 : 0;
        try {
            return 'Giảm ' + new Intl.NumberFormat('vi-VN').format(roundedVal) + 'đ';
        } catch(e){
            return 'Giảm ' + String(Math.round(roundedVal)) + 'đ';
        }
    }
    function baseDiscountText(row){
        if (!row || typeof row !== 'object') return 'Giảm trên đơn hàng';
        var target = primaryTarget(row);
        var target_label = (target === 'shipping') ? 'cho vận chuyển' : 'cho đơn hàng';
        var label = voucherValueLabel(row);
        if (!label){
            return 'Giảm ' + target_label;
        }
        return label + ' ' + target_label;
    }
    function baseDiscountTextCart(row){
        if (!row || typeof row !== 'object') return 'Giảm trên đơn hàng';
        var target = primaryTarget(row);
        var target_label = (target === 'shipping') ? '' : '';
        var label = voucherValueLabel(row);
        if (!label){
            return 'Giảm ' + target_label;
        }
        return label + ' ' + target_label;
    }
    // Hiển thị đầy đủ với phần ghi chú "Giảm tối đa ..." nếu có max_discount > 0
    function fullTitle(row, opts){
        opts = opts || {};
        var title = baseDiscountText(row);
        var rawMax = row && row.max_discount;
        var hasMax = rawMax !== null && rawMax !== undefined && String(rawMax) !== '';
        if (!hasMax) return title;
        var maxVal = toNumber(rawMax || 0);
        if (maxVal <= 0) return title;
        var style = opts.maxStyle || 'dot';
        var rawMaxUnit = row && row.max_discount_unit;
        var isPercentCap = false;
        if (rawMaxUnit !== undefined && rawMaxUnit !== null){
            var v = String(rawMaxUnit).toLowerCase();
            if (v === 'percent' || v === '%') {
                isPercentCap = true;
            }
        }
        var maxText;
        if (isPercentCap){
            var pct = maxVal;
            if (!isFinite(pct)) pct = 0;
            var pctTxt = pct.toFixed(1).replace(/\.0$/, '');
            maxText = pctTxt + '%';
        } else {
            var roundedMax = maxVal > 0 ? Math.round(maxVal / 1000) * 1000 : 0;
            try {
                maxText = new Intl.NumberFormat('vi-VN').format(roundedMax) + 'đ';
            } catch(e){
                maxText = String(Math.round(roundedMax)) + 'đ';
            }
        }
        if (style === 'paren'){
            return title; // + ' (tối đa ' + maxText + ')';
        }
        return title; //+ ' · Giảm tối đa ' + maxText;
    }

    function minText(row, opts){
        opts = opts || {};
        if (!row || typeof row !== 'object'){
            var baseZero = opts.zeroText || '';
            return baseZero;
        }

        var raw = row.min_subtotal;
        var hasMin = raw !== null && raw !== undefined && String(raw) !== '';
        var minVal = hasMin ? toNumber(raw) : 0;
        var unit = normalizeUnit(row.min_subtotal_unit, null);
        var minLabel;

        if (!hasMin || minVal <= 0){
            minLabel = opts.zeroText || '';
        } else if (unit === 'percent'){
            var pctTxt = minVal.toFixed(1).replace(/\.0$/, '');
            minLabel = 'Đơn tối thiểu ' + pctTxt + '%';
        } else {
            var roundedMin = minVal > 0 ? Math.round(minVal / 1000) * 1000 : 0;
            try {
                minLabel = 'Đơn tối thiểu ' + new Intl.NumberFormat('vi-VN').format(roundedMin) + 'đ';
            } catch(e){
                minLabel = 'Đơn tối thiểu ' + String(Math.round(roundedMin)) + 'đ';
            }
        }

        return minLabel;
    }

    // Trả về riêng phần ghi chú "Giảm tối đa ..." để hiển thị dòng khác
    function maxText(row){
        if (!row || typeof row !== 'object') return '';
        var rawMax = row.max_discount;
        var hasMax = rawMax !== null && rawMax !== undefined && String(rawMax) !== '';
        var maxVal = hasMax ? toNumber(rawMax) : 0;
        var maxUnit = normalizeUnit(row.max_discount_unit, null);
        if (!hasMax || maxVal <= 0) return '';

        if (maxUnit === 'percent'){
            var maxPctTxt = maxVal.toFixed(1).replace(/\.0$/, '');
            return 'Giảm tối đa ' + maxPctTxt + '%';
        }
        var roundedMaxVal = maxVal > 0 ? Math.round(maxVal / 1000) * 1000 : 0;
        try {
            return 'Giảm tối đa ' + new Intl.NumberFormat('vi-VN').format(roundedMaxVal) + 'đ';
        } catch(e){
            return 'Giảm tối đa ' + String(Math.round(roundedMaxVal)) + 'đ';
        }
    }

    function calcOrderDiscount(row, subtotal){
        var total = toNumber(subtotal || 0);
        if (!row || total <= 0) return 0;
        var min = toNumber(row.min_subtotal || 0);
        if (total < min) return 0;
        var type = (row.type ? String(row.type) : '').toLowerCase();
        if (!type) type = 'fixed';
        if (type === 'fixed') type = 'amount';
        var value = toNumber(row.value || 0);
        var discount = 0;
        if (type === 'percent'){
            discount = total * value / 100;
            var rawMax = row.max_discount;
            if (rawMax !== null && rawMax !== undefined && String(rawMax) !== ''){
                var maxVal = toNumber(rawMax || 0);
                if (maxVal > 0){
                    // Đồng bộ backend: cap có thể là percent (theo base) hoặc số tiền cố định
                    var maxUnit = (row.max_discount_unit ? String(row.max_discount_unit) : 'fixed').toLowerCase();
                    var cap = maxUnit === 'percent' ? (total * maxVal / 100) : maxVal;
                    discount = Math.min(discount, cap);
                }
            }
        } else if (type === 'amount'){
            discount = value;
        }
        if (discount < 0) discount = 0;
        if (discount > total) discount = total;
        return discount;
    }

    function calcShipDiscount(row, fee, subtotal){
        var shipFee = toNumber(fee || 0);
        if (!row || shipFee <= 0) return 0;
        var min = toNumber(row.min_subtotal || 0);
        if (toNumber(subtotal || 0) < min) return 0;
        var type = (row.type ? String(row.type) : '').toLowerCase();
        if (!type) type = 'fixed';
        if (type === 'fixed') type = 'amount';
        var value = toNumber(row.value || 0);
        var discount = 0;
        if (type === 'percent'){
            discount = shipFee * value / 100;
            var rawMax = row.max_discount;
            if (rawMax !== null && rawMax !== undefined && String(rawMax) !== ''){
                var maxVal = toNumber(rawMax || 0);
                if (maxVal > 0){
                    var maxUnit = (row.max_discount_unit ? String(row.max_discount_unit) : 'fixed').toLowerCase();
                    var cap = maxUnit === 'percent' ? (shipFee * maxVal / 100) : maxVal;
                    discount = Math.min(discount, cap);
                }
            }
        } else if (type === 'amount'){
            discount = value;
        }
        if (discount < 0) discount = 0;
        if (discount > shipFee) discount = shipFee;
        return discount;
    }

    window.pmVoucher = window.pmVoucher || {};
    window.pmVoucher.toNumber = toNumber;
    window.pmVoucher.formatVNDShort = formatVNDShort;
    window.pmVoucher.formatDate = formatDate;
    window.pmVoucher.primaryTarget = primaryTarget;
    window.pmVoucher.valueLabel = voucherValueLabel;
    window.pmVoucher.baseDiscountText = baseDiscountText;
    window.pmVoucher.fullTitle = fullTitle;
    window.pmVoucher.minText = minText;
    window.pmVoucher.maxText = maxText;
    window.pmVoucher.calcOrderDiscount = calcOrderDiscount;
    window.pmVoucher.calcShipDiscount = calcShipDiscount;

    window.pmFormatPrice = formatVNDShort;
    window.pmNormalizePrice = function(n) { return Math.round(toNumber(n) / 1000) * 1000; };

    window.pmVoucherAPI = {
        validate: function(payload) {
            return $.get('<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php', $.extend({ ajax: 'validate_voucher' }, payload));
        },
        persistSession: function(code, target) {
            var normalizedTarget = target === 'shipping' ? 'shipping' : 'order';
            var payload = { action: code ? 'voucher_session_set' : 'voucher_session_clear', target: normalizedTarget };
            if (code) payload.code = code;
            return $.post('<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php', payload);
        },
        loadSavedCodes: function() {
            return $.get('<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php', { ajax: 'my_saved_vouchers' })
                .then(function(res) {
                    if (res && res.ok && Array.isArray(res.codes)) {
                        return res.codes.map(function(c) { return String(c || '').trim().toUpperCase(); });
                    }
                    return $.Deferred().reject().promise();
                });
        }
    };

})(window);
</script>

<script>
(function(window){
    if (window.pmVoucherCard) return;
    function parseTargets(row){
        const raw = String(row && row.discount_target || '').toLowerCase();
        const list = raw
            .split(',')
            .map(t => t.trim())
            .filter(Boolean)
            .map(t => (t === 'shipping' ? 'shipping' : 'order'));
        return list.length ? Array.from(new Set(list)) : ['order'];
    }

    function classifyTemplate(row){
        const targets = parseTargets(row);
        const rawTpl = String(row && row.voucher_template || '').trim().toLowerCase();
        const allowedTpl = ['order_discount', 'shipping_discount', 'only_category_discount', 'category_discount', 'payment_discount'];
        let templateKey = allowedTpl.includes(rawTpl) ? rawTpl : '';
        const applyScope = String(row && row.apply_scope || 'all').toLowerCase();
        const hasPaymentFilter = String(row && row.payment_methods || '').trim() !== '';
        const hasCategories = String(row && row.apply_category_ids || '').trim() !== '';

        if (!templateKey) {
            if (targets.includes('shipping') && !hasPaymentFilter) {
                templateKey = 'shipping_discount';
            } else if (hasPaymentFilter) {
                templateKey = 'payment_discount';
            } else if (hasCategories) {
                templateKey = applyScope === 'products' ? 'only_category_discount' : 'category_discount';
            } else {
                templateKey = 'order_discount';
            }
        }

        return { templateKey, hasCategories, hasPaymentFilter, targets };
    }

    function variantFromTemplate(templateKey){
        let variant = 'order';
        let iconHtml = '<i class="bi bi-percent"></i>';
        let typeLabel = 'Giảm đơn';
        let tagLabel = 'Đơn hàng';

        if (templateKey === 'shipping_discount') {
            variant = 'ship';
            iconHtml = '<i class="bi bi-truck"></i>';
            typeLabel = 'Mã ship';
            tagLabel = 'Vận chuyển';
        } else if (templateKey === 'only_category_discount') {
            variant = 'category';
            iconHtml = '<i class="bi bi-grid-3x3-gap"></i>';
            typeLabel = 'Ngành hàng';
            tagLabel = 'Danh mục';
        } else if (templateKey === 'category_discount') {
            variant = 'all';
            iconHtml = '<i class="bi bi-collection"></i>';
            typeLabel = 'Toàn ngành';
            tagLabel = 'Toàn bộ';
        } else if (templateKey === 'payment_discount') {
            variant = 'payment';
            iconHtml = '<i class="bi bi-credit-card-2-front"></i>';
            typeLabel = 'Thanh toán';
            tagLabel = 'Thanh toán';
        }

        return { variant, iconHtml, typeLabel, tagLabel };
    }

    window.pmVoucherCard = {
        computeMeta: function(row, options){
            options = options || {};
            const categoryMap = options.categoryMap || {};
            const savedCodes = (options.savedCodes || []).map(c => String(c || '').trim().toUpperCase());

            const code = String(row && row.code || '').trim();
            const { templateKey, hasCategories, targets } = classifyTemplate(row || {});
            const { variant, iconHtml, typeLabel, tagLabel } = variantFromTemplate(templateKey);

            const maxUses = (row && row.max_uses !== null && row.max_uses !== '') ? window.pmVoucher.toNumber(row.max_uses) : null;
            const usedCount = window.pmVoucher.toNumber(row && row.used_count);
            const remain = maxUses !== null ? Math.max(maxUses - usedCount, 0) : null;
            const qtyLabel = remain && remain > 0 ? ('x' + remain) : '';

            let categoryNames = [];
            if ((templateKey === 'only_category_discount' || templateKey === 'category_discount') && hasCategories) {
                const rawIds = String(row && row.apply_category_ids || '')
                    .split(',')
                    .map(id => id.trim())
                    .filter(Boolean);
                categoryNames = rawIds.map(id => categoryMap[id] || ('ID ' + id));
            }

            let paymentLabels = [];
            if (templateKey === 'payment_discount') {
                const keys = String(row && row.payment_methods || '')
                    .split(',')
                    .map(k => k.trim().toLowerCase())
                    .filter(Boolean);
                if (keys.length) {
                    paymentLabels = keys.map(k => {
                        if (k === 'cod') return 'COD';
                        if (k === 'vnpay') return 'VN PAY';
                        if (k === 'momo') return 'MOMO';
                        return k.toUpperCase();
                    });
                }
            }

            const isSaved = !!(row && row.is_saved) || (code && savedCodes.includes(code.toUpperCase()));

            let titleText = '';
            let minText = '';
            let maxText = '';
            if (window.pmVoucher) {
                if (typeof window.pmVoucher.baseDiscountText === 'function') {
                    titleText = String(window.pmVoucher.baseDiscountText(row || {}) || '').trim();
                }
                if (typeof window.pmVoucher.minText === 'function') {
                    minText = String(window.pmVoucher.minText(row || {}, { 
                        //zeroText: ''
                     }) || '').trim();
                }
                if (typeof window.pmVoucher.maxText === 'function') {
                    maxText = String(window.pmVoucher.maxText(row || {}) || '').trim();
                }
            }
            if (!titleText && window.pmVoucher && typeof window.pmVoucher.valueLabel === 'function') {
                const lblRaw = window.pmVoucher.valueLabel(row || {});
                const lbl = String(lblRaw || '').trim();
                if (lbl) {
                    if (lbl.toLowerCase() === 'miễn phí') {
                        const primaryTarget = (targets[0] || 'order') === 'shipping' ? 'vận chuyển' : 'đơn hàng';
                        titleText = 'Miễn phí ' + primaryTarget;
                    } else {
                        titleText = 'Giảm ' + lbl;
                    }
                }
            }
            if (!titleText) {
                titleText = code ? ('Mã ' + code) : 'Voucher ưu đãi';
            }
            if (!minText) {
                const rawMin = row && row.min_subtotal;
                const hasMin = rawMin !== null && rawMin !== undefined && String(rawMin) !== '';
                const minVal = hasMin ? window.pmVoucher.toNumber(rawMin) : 0;
                if (!hasMin || minVal <= 0) {
                    minText = '';
                } else {
                    const unit = String(row && row.min_subtotal_unit || '').toLowerCase();
                    if (unit === 'percent' || unit === '%') {
                        const pctTxt = minVal.toFixed(1).replace(/\.0$/, '');
                        minText = 'Đơn tối thiểu ' + pctTxt + '%';
                    } else {
                        minText = 'Đơn tối thiểu ' + window.pmVoucher.formatVNDShort(minVal);
                    }
                }
            }

            const end = window.pmVoucher.formatDate(row && row.end_at);
            const start = window.pmVoucher.formatDate(row && row.start_at);
            let endText = '';
            if (end) {
                endText = 'HSD: ' + end;
            } else if (start) {
                endText = 'Từ ' + start;
            } else {
                endText = 'Không giới hạn';
            }

            const primaryTarget = targets[0] || 'order';

            return {
                code,
                templateKey,
                variant,
                iconHtml,
                typeLabel,
                tagLabel,
                targets,
                primaryTarget,
                qtyLabel,
                categoryNames,
                paymentLabels,
                isSaved,
                titleText,
                minText,
                maxText,
                endText
            };
        }
    };
})(window);
</script>

<script>
(function(window){
    if (window.pmVoucherShared) return;

    function escHtml(str){
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }


    // Tạo tiêu đề hiển thị cho voucher dựa trên loại và giá trị
    function couponText(c){
        if (window.pmVoucher && typeof window.pmVoucher.baseDiscountText === 'function'){
            var title = window.pmVoucher.baseDiscountText(c || {});
            if (title && String(title).trim()) return String(title).trim();
        }
        var type = String(c && c.type || '').toLowerCase();
        var value = Number(c && c.value || 0);
        var rawMax = c && c.max_discount;
        var maxDiscount = (rawMax !== null && rawMax !== undefined && rawMax !== '') ? Number(rawMax || 0) : 0;
        var targets = String(c && c.discount_target || '')
            .toLowerCase()
            .split(',')
            .map(function(t){ return t.trim(); })
            .filter(Boolean);
        var targetPrimary = targets[0] || 'order';

        var titleMain = '';
        if (type === 'percent') {
            if (value >= 100) {
                titleMain = 'Miễn phí ' + (targetPrimary === 'shipping' ? 'vận chuyển' : 'đơn hàng');
            } else if (value > 0) {
                titleMain = 'Giảm ' + value + '%';
            } else {
                titleMain = 'Giảm giá';
            }
        } else if (type === 'amount') {
            if (value > 0) {
                if (window.pmVoucher && typeof window.pmVoucher.valueLabel === 'function'){
                    var lbl = window.pmVoucher.valueLabel(c || {});
                    titleMain = 'Giảm ' + (lbl || window.pmFormatPrice(value));
                } else {
                    titleMain = 'Giảm ' + window.pmFormatPrice(value);
                }
            } else {
                titleMain = 'Giảm giá';
            }
        } else {
            titleMain = 'Giảm giá';
        }

        if (maxDiscount > 0) {
            var maxLabel;
            if (window.pmVoucher && typeof window.pmVoucher.valueLabel === 'function'){
                maxLabel = window.pmVoucher.valueLabel({ type: 'fixed', value: maxDiscount }) || window.pmFormatPrice(maxDiscount);
            } else {
                maxLabel = window.pmFormatPrice(maxDiscount);
            }
            titleMain += ' (tối đa ' + maxLabel + ')';
        }

        return titleMain;
    }

    // Mô tả điều kiện áp dụng: min value + khoảng thời gian
    function condText(c){
        var end = window.pmVoucher.formatDate(c && c.end_at);
        var start = window.pmVoucher.formatDate(c && c.start_at);
        var range = 'Không giới hạn';
        if (end) range = 'HSD ' + end;
        else if (start) range = 'Từ ' + start;

        var minLabel = '';
        var maxNote = '';
        if (window.pmVoucher && typeof window.pmVoucher.minText === 'function'){
            minLabel = window.pmVoucher.minText(c || {}, { 
                //zeroText: '' 
                });
            if (typeof window.pmVoucher.maxText === 'function'){
                maxNote = window.pmVoucher.maxText(c || {}) || '';
            }
        } else {
            var min = Number(c && c.min_subtotal || 0);
            minLabel = min > 0 ? ('Đơn tối thiểu ' + window.pmFormatPrice(min)) : '';
        }
        return {
            min: minLabel,
            max: maxNote,
            range: range
        };
    }

    // Quick card nhỏ (checkout / giỏ hàng / header): dùng chung template tpl-voux-card giống modal/voucher page
    function _classifyType(row){
        var tpl = String(row && row.voucher_template || '').trim().toLowerCase();
        if (tpl === 'shipping_discount') return 'shipping';
        if (tpl === 'payment_discount') return 'payment';
        if (tpl === 'only_category_discount') return 'only_category';
        if (tpl === 'category_discount') return 'category';
        return 'order';
    }

    function _voucherTargets(row){
        var typeKey = _classifyType(row || {});
        // voucher_template là nguồn sự thật
        if (typeKey === 'payment') return ['payment'];
        if (typeKey === 'shipping') return ['shipping'];
        // order_discount / category_discount / only_category_discount đều giảm trên đơn hàng
        return ['order'];
    }

    function _paymentLabels(row){
        var out = [];
        var raw = row && row.payment_methods;
        if (!raw) return out;
        var list = Array.isArray(raw) ? raw : String(raw).split(',');
        list.map(function(v){ return String(v || '').trim().toLowerCase(); }).filter(Boolean).forEach(function(k){
            if (k === 'cod' || k === 'cash' || k === 'tienmat' || k === 'tien_mat' || k === 'cash_on_delivery') out.push('Tiền mặt');
            else if (k === 'vnpay' || k === 'vn_pay' || k === 'vn pay') out.push('VNPay');
            else if (k === 'momo') out.push('MoMo');
            else if (k === 'zalopay' || k === 'zalo_pay' || k === 'zalo pay') out.push('ZaloPay');
            else out.push(k.toUpperCase());
        });
        return out;
    }

    function _buildMeta(row){
        row = row || {};
        var typeKey = _classifyType(row);
        var targets = _voucherTargets(row);
        var primaryTarget = targets[0] || 'order';

        var cardCls = 'tpl-voux-card tpl-voux-order';
        var logoIconHtml = '<i class="bi bi-percent"></i>';
        var brandName = 'Giảm giá';
        var tagText = 'Đơn hàng';

        if (typeKey === 'only_category') {
            cardCls = 'tpl-voux-card tpl-voux-category';
            logoIconHtml = '<i class="bi bi-grid-3x3-gap"></i>';
            brandName = 'Ngành hàng';
            tagText = 'Danh mục';
        } else if (typeKey === 'category') {
            cardCls = 'tpl-voux-card tpl-voux-all';
            logoIconHtml = '<i class="bi bi-collection"></i>';
            brandName = 'Toàn ngành';
            tagText = 'Toàn ngành';
        } else if (typeKey === 'shipping') {
            cardCls = 'tpl-voux-card tpl-voux-ship';
            logoIconHtml = '<i class="bi bi-truck"></i>';
            brandName = 'Vận chuyển';
            tagText = 'Vận chuyển';
        } else if (typeKey === 'payment') {
            cardCls = 'tpl-voux-card tpl-voux-payment';
            logoIconHtml = '<i class="bi bi-credit-card-2-front"></i>';
            brandName = 'Thanh toán';
            tagText = 'Thanh toán';
        }

        // title
        var unit = String(row.value_unit || '').toLowerCase();
        if (!unit) {
            var t = String(row.type || '').toLowerCase();
            if (t === 'percent') unit = 'percent';
        }
        var value = Number(row.value || 0);
        var titleText = '';
        if (unit === 'percent') {
            if (value >= 100) {
                titleText = 'Miễn phí ' + (primaryTarget === 'shipping' ? 'vận chuyển' : 'đơn hàng');
            } else {
                titleText = 'Giảm ' + (row.value || 0) + '% trên ' + (primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng');
            }
        } else {
            titleText = 'Giảm ' + (window.pmFormatPrice ? window.pmFormatPrice(value) : String(value)) + ' trên ' + (primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng');
        }

        // min
        var min = Number(row.min_subtotal || 0);
        var minText = (!min) ? 'Đơn tối thiểu 0đ' : ('Đơn tối thiểu ' + (window.pmFormatPrice ? window.pmFormatPrice(min) : String(min)));

        // end
        var endText = '';
        try {
            var endDate = (window.pmVoucher && typeof window.pmVoucher.formatDate === 'function')
                ? window.pmVoucher.formatDate(row.end_at)
                : '';
            endText = endDate ? ('Hết hạn: ' + endDate) : '';
        } catch (e) {
            endText = '';
        }

        // category badges (only for category templates)
        var categoryNames = [];
        if (typeKey === 'only_category' || typeKey === 'category') {
            var catMap = window.pmVoucherCategoryMap || {};
            if (row.apply_category_ids) {
                String(row.apply_category_ids).split(',').map(function(id){ return String(id || '').trim(); }).filter(Boolean).forEach(function(id){
                    var name = catMap[id];
                    if (name) categoryNames.push(name);
                });
            }
        }

        // max
        var maxText = '';
        if (window.pmVoucher && typeof window.pmVoucher.maxText === 'function') {
            maxText = window.pmVoucher.maxText(row || {}) || '';
        }

        return {
            cardCls: cardCls,
            logoIconHtml: logoIconHtml,
            brandName: brandName,
            tagText: tagText,
            titleText: titleText,
            minText: minText,
            maxText: maxText,
            endText: endText,
            categoryNames: categoryNames,
            paymentLabels: _paymentLabels(row),
            primaryTarget: primaryTarget
        };
    }

    function renderTplCard(row, opts){
        row = row || {};
        opts = opts || {};

        var code = String(opts.code || row.code || '').trim();
        var safeCode = escHtml(code);
        var meta = _buildMeta(row);

        var active = !!opts.active;
        var useLabel = String(opts.useLabel || (active ? 'Đang dùng' : 'Lưu ngay'));
        var detailUrlPrefix = String(opts.detailUrlPrefix || '').trim();
        var detailHref = '';
        if (detailUrlPrefix && code) {
            detailHref = detailUrlPrefix + encodeURIComponent(code);
        }
        var termsTitle = escHtml(meta.minText + (meta.maxText ? (' • ' + meta.maxText) : '') + (meta.endText ? (' • ' + meta.endText) : ''));
        var categoryBadges = '';
        if (meta.categoryNames && meta.categoryNames.length) {
            var maxShow = 3;
            var total = meta.categoryNames.length;
            var visible = meta.categoryNames.slice(0, maxShow);
            categoryBadges = visible.map(function(name){ return '<span class="tpl-voux-badge">' + escHtml(name) + '</span>'; }).join(' ');
            if (total > maxShow) {
                categoryBadges += ' <span class="tpl-voux-badge">+' + escHtml(total - maxShow) + '</span>';
            }
        }
        var paymentBadges = (meta.paymentLabels && meta.paymentLabels.length)
            ? meta.paymentLabels.map(function(l){ return '<span class="tpl-voux-badge">' + escHtml(l) + '</span>'; }).join(' ')
            : '';

        var html = '';
        html += '<article class="' + meta.cardCls + '">';
        html += '  <div class="tpl-voux-accent"></div>';
        html += '  <div class="tpl-voux-brand">';
        html += '    <span class="tpl-voux-logo-icon">' + meta.logoIconHtml + '</span>';
        html += '    <div class="tpl-voux-brand-name">' + escHtml(meta.brandName) + '</div>';
        html += '  </div>';
        html += '  <div class="tpl-voux-main">';
        html += '    <div class="tpl-voux-main-title">' + escHtml(meta.titleText) + '</div>';
        html += (categoryBadges || paymentBadges) ? ('    <div>' + categoryBadges + paymentBadges + '</div>') : '';
        html += '    <div class="tpl-voux-sub">' + escHtml(meta.minText) + '</div>';
        html += meta.maxText ? ('    <div class="tpl-voux-sub">' + escHtml(meta.maxText) + '</div>') : '';
        html += meta.endText ? ('    <div class="tpl-voux-foot"><span class="tpl-voux-time">' + escHtml(meta.endText) + '</span></div>') : '';
        html += '  </div>';
        html += '  <div class="tpl-voux-side">';
        html += '    <button type="button" class="tpl-voux-btn vcp-use-btn' + (active ? ' active' : '') + '" data-vcp-use="' + safeCode + '" data-vcp-target="' + escHtml(meta.primaryTarget) + '">' + escHtml(useLabel) + '</button>';
        html += detailHref
            ? ('    <span class="tpl-voux-tag"><a href="' + escHtml(detailHref) + '" class="vcp-tnc" title="' + termsTitle + '" target="_blank" rel="noopener">Điều kiện</a></span>')
            : '';
        html += '  </div>';
        html += '</article>';
        return html;
    }

    function renderQuickCard(opts){
        // Backward-compat: nếu có row thì render đúng template như modal
        if (opts && opts.row) {
            return renderTplCard(opts.row, opts);
        }
        // Fallback tối giản
        var title = escHtml(opts && opts.mainTitle ? opts.mainTitle : 'Ưu đãi');
        return '<article class="tpl-voux-card tpl-voux-order">'
            + '<div class="tpl-voux-accent"></div>'
            + '<div class="tpl-voux-brand"><span class="tpl-voux-logo-icon"><i class="bi bi-percent"></i></span><div class="tpl-voux-brand-name">Giảm giá</div></div>'
            + '<div class="tpl-voux-main"><div class="tpl-voux-main-title">' + title + '</div></div>'
            + '<div class="tpl-voux-side"></div>'
            + '</article>';
    }
    window.pmVoucherShared = {
        couponText: couponText,
        condText: condText,
        renderTplCard: renderTplCard,
        renderQuickCard: renderQuickCard,
        fmtPrice: window.pmFormatPrice
    };
})(window);
</script>

<style>
/* Modernized Voucher Modal Styles */
:root {
    --vcp-primary: #ea580c;
    --vcp-primary-dark: #c06f4fff;
    --vcp-primary-soft: rgb(255 247 237);
    --vcp-bg: #f8fafc;
    --vcp-card-border: rgba(187, 90, 10, 0.57);
    --vcp-text-main: #050505;
    --vcp-text-sub: #65676b;
}

/* Backdrop glass effect */
.voucher-backdrop {
    backdrop-filter: blur(4px);
    background: rgba(15, 23, 42, 0.5) !important;
    z-index: 1201;
}

/* Main Panel - PC Default */
.voucher-panel {
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -45%) scale(0.95) !important;
    max-width: 500px !important;
    width: 95% !important;
    border-radius: 20px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    overflow: hidden !important;
    padding: 0 !important;
    background: #fff !important;
    border: none !important;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s ease !important;
}

.voucher-panel.show {
    transform: translate(-50%, -50%) scale(1) !important;
    opacity: 1 !important;
}

.voucher-shell {
    background: #fff !important;
    padding: 0 !important;
    gap: 0 !important;
    border: none !important;
    display: flex !important;
    flex-direction: column !important;
}

/* Mobile Bottom Sheet */
@media (max-width: 768px) {
    .voucher-panel {
        top: auto !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        transform: translateY(100%) !important;
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 24px 24px 0 0 !important;
        max-height: 85vh !important;
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .voucher-panel.show {
        transform: translateY(0) !important;
    }
    
    .vcp-handle {
        display: block !important;
        width: 40px;
        height: 4px;
        background: #e2e8f0;
        border-radius: 2px;
        margin: 12px auto 0;
        flex-shrink: 0;
    }
    
    .voucher-head {
        padding: 8px 24px 10px !important;
    }
}

.vcp-handle { display: none; }

.voucher-head {
    padding: 20px 24px 10px !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.voucher-title {
    font-size: 20px !important;
    font-weight: 800 !important;
    color: var(--vcp-text-main);
}

.voucher-meta {
    font-size: 0.8rem !important;
    color: var(--vcp-text-sub);
}

/* Improved Codebar */
.voux-codebar {
    margin: 0 24px 16px !important;
    padding: 4px 4px 4px 16px !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    display: grid !important;
    grid-template-columns: 1fr auto !important;
    gap: 8px !important;
    align-items: center !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
    background: #fff !important;
}

.voux-code-label { display: none !important; }

.voux-code-input {
    border: none !important;
    padding: 10px 0 !important;
    font-weight: 500 !important;
    font-size: 0.9rem !important;
    width: 100% !important;
    outline: none !important;
}

.voux-save-btn {
    border-radius: 10px !important;
    padding: 8px 18px !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
}

/* Premium Tabs */
.voux-tabs-wrap {
    margin: 0 24px 16px !important;
    border: none !important;
    background: #f1f5f9 !important;
    border-radius: 12px !important;
    padding: 4px !important;
    flex-shrink: 0;
}

.voux-tabs {
    display: flex !important;
    gap: 4px !important;
    border: none !important;
}

.voux-tab {
    flex: 1 !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 10px 4px !important;
    font-size: 0.82rem !important;
    font-weight: 700 !important;
    color: var(--vcp-text-sub) !important;
    transition: all 0.2s ease !important;
    background: transparent !important;
    text-align: center !important;
}

.voux-tab.active {
    background: #fff !important;
    color: var(--vcp-primary) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
}

/* List Area */
.voux-list {
    padding: 0 24px 10px !important;
    max-height: 50vh !important;
    overflow-y: auto !important;
    display: grid !important;
    gap: 12px !important;
}

.voux-empty {
    margin: 20px 24px !important;
    padding: 30px 20px !important;
    border: 1px dashed #e2e8f0 !important;
    border-radius: 12px !important;
    text-align: center;
    color: var(--vcp-text-sub);
    font-size: 0.9rem;
}

.vcp-done-bar {
    padding: 16px 24px 20px !important;
    border-top: 1px solid #f1f5f9;
    background: #fff;
    flex-shrink: 0;
}

#voucherDoneBtn {
    width: 100%;
    padding: 6px !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
    background: var(--vcp-primary) !important;
    border: none !important;
    color: #fff !important;
}

#voucherClose {
    background: #f1f5f9 !important;
    border: none !important;
    border-radius: 50% !important;
    width: 32px !important;
    height: 32px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    color: var(--vcp-text-sub) !important;
}

/* Override existing card styles for better fit */
.tpl-voux-card {
    grid-template-columns: 6px 70px 1fr auto !important;
}
@media (max-width: 480px) {
    .tpl-voux-card {
        grid-template-columns: 6px 60px 1fr auto !important;
    }
    .tpl-voux-side {
        min-width: 70px !important;
        padding: 8px 6px !important;
    }
}

/* Theme overrides for cards inside modal */
.tpl-voux-card.tpl-voux-order {
    border-color: var(--vcp-card-border) !important;
}
.tpl-voux-card.tpl-voux-order .tpl-voux-accent,
.tpl-voux-card.tpl-voux-order .tpl-voux-logo-icon {
    background: var(--vcp-primary) !important;
}
.tpl-voux-card.tpl-voux-order .tpl-voux-badge {
    color: var(--vcp-primary) !important;
    background: var(--vcp-primary-soft) !important;
    border-color: var(--vcp-card-border) !important;
}
.tpl-voux-card.tpl-voux-order .tpl-voux-btn {
    color: var(--vcp-primary) !important;
    border-color: var(--vcp-primary) !important;
}
.tpl-voux-card.tpl-voux-order .tpl-voux-btn.active {
    background: var(--vcp-primary) !important;
    color: #fff !important;
}
</style>

<!--  Voucher Modal (Kho Voucher) -->
<div class="voucher-backdrop" id="voucherBackdrop"></div>
<div class="voucher-panel" id="voucherPanel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="voucher-shell">
        <div class="vcp-handle"></div>
        <div class="voucher-head">
            <div>
                <div class="voucher-title">Kho Voucher</div>
                <div class="voucher-meta" id="vouxMeta">Đang tải voucher...</div>
            </div>
            <button class="btn btn-sm" id="voucherClose" type="button"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="voux-codebar">
            <input type="text" id="vouxCodeInput" class="voux-code-input" placeholder="Nhập mã voucher tại đây" maxlength="255" autocomplete="off">
            <button id="vouxSaveBtn" class="btn btn-primary voux-save-btn" type="button" disabled>Lưu ngay</button>
        </div>

        <div class="voux-tabs-wrap">
            <div class="voux-tabs" role="tablist" aria-label="Bộ lọc voucher">
                <button type="button" class="voux-tab active" data-voux-tab="order">Giảm giá</button>
                <button type="button" class="voux-tab" data-voux-tab="shipping">Vận chuyển</button>
                <button type="button" class="voux-tab" data-voux-tab="payment">Thanh toán</button>
            </div>
        </div>

        <div class="voux-list" id="vouxList"></div>
        <div class="voux-empty d-none" id="vouxEmpty">Chưa có voucher phù hợp.</div>

        <div class="vcp-done-bar">
            <button class="btn btn-primary" type="button" id="voucherDoneBtn">Hoàn tất</button>
        </div>
    </div>
</div>
<?php
// Tạo danh mục voucher trong footer
$voucherCategoryMap = [];
if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
    $categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category']) : 'ecommerce_category';
    if ($categoryTable !== '') {
        $catRes = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY id ASC");
        if ($catRes instanceof mysqli_result) {
            while ($row = $catRes->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                $name = trim((string)($row['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $voucherCategoryMap[$id] = $name;
                }
            }
        }
    }
}
?>
<script>
(function(window){
    if (typeof jQuery === 'undefined') return;
    const $ = jQuery;

    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
    const DETAIL_URL = '<?= h($baseUrl) ?>/view-voucher';
    const CATEGORY_MAP = <?= json_encode($voucherCategoryMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // Expose for shared voucher renderers (checkout preview, etc.)
    try { window.pmVoucherCategoryMap = CATEGORY_MAP; } catch (e) {}

    const $panel = $('#voucherPanel');
    const $backdrop = $('#voucherBackdrop');
    if (!$panel.length) return;

    const state = {
        tab: 'order',
        coupons: [],
        savedCodes: []
    };

    let modalOptions = {
        onApply: null,
        onSaved: null
    };
    let isModalOpen = false;

    const $list = $('#vouxList');
    const $empty = $('#vouxEmpty');
    const $meta = $('#vouxMeta');
    const $codeInput = $('#vouxCodeInput');
    const $saveBtn = $('#vouxSaveBtn');
    const $tabs = $('.voux-tab');
    const $closeBtn = $('#voucherClose');
    const $doneBtn = $('#voucherDoneBtn');

    const esc = (v) => $('<div>').text(String(v ?? '')).html();



    function openPanel(){
        $backdrop.addClass('show');
        $panel.addClass('show').attr('aria-hidden', 'false');
    }

    function closePanel(){
        $backdrop.removeClass('show');
        $panel.removeClass('show').attr('aria-hidden', 'true');
    }

    function classifyType(row){
        const tpl = String(row.voucher_template || '').trim().toLowerCase();
        if (tpl === 'shipping_discount') return 'shipping';
        if (tpl === 'payment_discount') return 'payment';
        if (tpl === 'only_category_discount') return 'only_category';
        if (tpl === 'category_discount') return 'category';
        return 'order';
    }

    function voucherTargets(row){
        const typeKey = classifyType(row);
        // voucher_template là nguồn sự thật
        if (typeKey === 'payment') return ['payment'];
        if (typeKey === 'shipping') return ['shipping'];
        // order_discount / category_discount / only_category_discount đều giảm trên đơn hàng
        return ['order'];
    }

    function isSaved(row){
        const code = String(row.code || '').trim().toUpperCase();
        if (!code) return false;
        if (row.is_saved || row.saved) return true;
        return state.savedCodes.includes(code);
    }

    function rowVisible(row){
        const targets = voucherTargets(row);
        if (state.tab === 'shipping') return targets.includes('shipping');
        if (state.tab === 'payment') return targets.includes('payment');
        return targets.includes('order');
    }

    function setTab(key){
        const next = key === 'shipping' ? 'shipping' : (key === 'payment' ? 'payment' : 'order');
        state.tab = next;
        $tabs.removeClass('active');
        $tabs.filter('[data-voux-tab="' + next + '"]').addClass('active');
        renderList();
    }

    function buildMeta(row){
        const typeKey = classifyType(row);
        const targets = voucherTargets(row);
        const primaryTarget = targets[0] || 'order';

        let cardCls = 'tpl-voux-card tpl-voux-order';
        let logoIconHtml = '<i class="bi bi-percent"></i>';
        let brandName = 'Giảm giá';
        let tagText = 'Đơn hàng';

        if (typeKey === 'only_category') {
            cardCls = 'tpl-voux-card tpl-voux-category';
            logoIconHtml = '<i class="bi bi-grid-3x3-gap"></i>';
            brandName = 'Ngành hàng';
            tagText = 'Danh mục';
        } else if (typeKey === 'category') {
            cardCls = 'tpl-voux-card tpl-voux-all';
            logoIconHtml = '<i class="bi bi-collection"></i>';
            brandName = 'Toàn ngành';
            tagText = 'Toàn ngành';
        } else if (typeKey === 'shipping') {
            cardCls = 'tpl-voux-card tpl-voux-ship';
            logoIconHtml = '<i class="bi bi-truck"></i>';
            brandName = 'Vận chuyển';
            tagText = 'Vận chuyển';
        } else if (typeKey === 'payment') {
            cardCls = 'tpl-voux-card tpl-voux-payment';
            logoIconHtml = '<i class="bi bi-credit-card-2-front"></i>';
            brandName = 'Thanh toán';
            tagText = 'Thanh toán';
        }

        const unit = String(row.value_unit || '').toLowerCase();
        let titleText = '';
        if (unit === 'percent') {
            if (Number(row.value) >= 100) {
                titleText = 'Miễn phí ' + (primaryTarget === 'shipping' ? 'vận chuyển' : 'đơn hàng');
            } else {
                titleText = 'Giảm ' + (row.value || 0) + '% trên ' + (primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng');
            }
            
        } else {
            titleText = 'Giảm ' + window.pmFormatPrice(row.value || 0) + ' trên ' + (primaryTarget === 'shipping' ? 'phí vận chuyển' : 'đơn hàng');
        }

        const min = Number(row.min_subtotal || 0);
        let minText = '';
        if (!min) {
            minText = 'Đơn tối thiểu 0đ';
        } else {
            minText = 'Đơn tối thiểu ' + window.pmFormatPrice(min);
        }

        const endDate = window.pmVoucher.formatDate(row.end_at);
        const endText = endDate ? ('Hết hạn: ' + endDate) : '';

        const categoryNames = [];
        // Chỉ gắn badge danh mục cho voucher ngành hàng / toàn ngành, bỏ với loại "Giảm giá" chung
        if (typeKey === 'only_category' || typeKey === 'category') {
            if (row.apply_category_ids) {
                String(row.apply_category_ids).split(',').map(id => id.trim()).filter(Boolean).forEach(id => {
                    const name = CATEGORY_MAP[id];
                    if (name) categoryNames.push(name);
                });
            }
        }

        const paymentLabels = [];
        if (row.payment_methods) {
            String(row.payment_methods).split(',').map(k => k.trim().toLowerCase()).filter(Boolean).forEach(k => {
                if (k === 'cod' || k === 'cash' || k === 'tienmat' || k === 'tien_mat' || k === 'cash_on_delivery') paymentLabels.push('Tiền mặt');
                else if (k === 'vnpay' || k === 'vn_pay' || k === 'vn pay') paymentLabels.push('VNPay');
                else if (k === 'momo') paymentLabels.push('MoMo');
                else if (k === 'zalopay' || k === 'zalo_pay' || k === 'zalo pay') paymentLabels.push('ZaloPay');
                else paymentLabels.push(k.toUpperCase());
            });
        }

        return { cardCls, logoIconHtml, brandName, tagText, titleText, minText, endText, categoryNames, paymentLabels, primaryTarget };
    }

    function renderList(){
        const rows = state.coupons.filter(rowVisible);
        if (!rows.length) {
            $list.empty();
            $empty.removeClass('d-none');
            $meta.text('0 voucher');
            return;
        }

        const html = rows.map(row => {
            const code = String(row.code || '').trim();
            const safeCode = esc(code);
            const saved = isSaved(row);
            const normalized = code.trim().toUpperCase();
            const isActive = (function(){
                if (!normalized) return false;
                if (state.tab === 'shipping') return normalized === String(state.selectedShipCode || '').trim().toUpperCase();
                if (state.tab === 'payment') return normalized === String(state.selectedPaymentCode || '').trim().toUpperCase();
                return normalized === String(state.selectedOrderCode || '').trim().toUpperCase();
            })();

            const useLabel = isActive ? 'Đang dùng' : (saved ? 'Đã lưu' : 'Lưu ngay');
            const meta = buildMeta(row);

            let categoryBadges = '';
            if (meta.categoryNames && meta.categoryNames.length) {
                const maxShow = 3;
                const total = meta.categoryNames.length;
                const visible = meta.categoryNames.slice(0, maxShow);
                categoryBadges = visible.map(name => '<span class="tpl-voux-badge">' + esc(name) + '</span>').join(' ');
                if (total > maxShow) {
                    const moreCount = total - maxShow;
                    categoryBadges += ' <span class="tpl-voux-badge">+' + esc(moreCount) + '</span>';
                }
            }
            const paymentBadges = (meta.paymentLabels && meta.paymentLabels.length)
                ? meta.paymentLabels.map(l => '<span class="tpl-voux-badge">' + esc(l) + '</span>').join(' ')
                : '';

            const detailHref = DETAIL_URL + '?code=' + encodeURIComponent(code);
            const maxText = (meta.maxText || (window.pmVoucher && typeof window.pmVoucher.maxText === 'function' ? (window.pmVoucher.maxText(row || {}) || '') : ''));
            const termsTitle = esc(meta.minText + (maxText ? (' • ' + maxText) : '') + (meta.endText ? (' • ' + meta.endText) : ''));

            return ''
                + '<article class="' + meta.cardCls + '">' 
                + '  <div class="tpl-voux-accent"></div>'
                + '  <div class="tpl-voux-brand">'
                + '    <span class="tpl-voux-logo-icon">' + meta.logoIconHtml + '</span>'
                + '    <div class="tpl-voux-brand-name">' + esc(meta.brandName) + '</div>'
                + '  </div>'
                + '  <div class="tpl-voux-main">'
                + '    <div class="tpl-voux-main-title">' + esc(meta.titleText) + '</div>'
                + (categoryBadges || paymentBadges ? ('    <div>' + categoryBadges + paymentBadges + '</div>') : '')
                + '    <div class="tpl-voux-sub">' + esc(meta.minText) + '</div>'
                + (maxText ? '    <div class="tpl-voux-sub">' + esc(maxText) + '</div>' : '')
                //+ '    <div class="tpl-voux-sub"><a href="' + detailHref + '" class="vcp-tnc" title="' + termsTitle + '" target="_blank" rel="noopener">Điều kiện</a></div>'
                + (meta.endText ? '    <div class="tpl-voux-foot"><span class="tpl-voux-time">' + esc(meta.endText) + '</span></div>' : '')
                + '  </div>'
                + '  <div class="tpl-voux-side">'
                + '    <button type="button" class="tpl-voux-btn vcp-use-btn' + (isActive ? ' active' : '') + '" data-vcp-use="' + safeCode + '" data-vcp-target="' + esc(meta.primaryTarget) + '">' + esc(useLabel) + '</button>'
                //+ '    <span class="tpl-voux-tag">' + esc(meta.tagText) + '</span>'
                + '    <span class="tpl-voux-tag"><a href="' + detailHref + '" class="vcp-tnc" title="' + termsTitle + '" target="_blank" rel="noopener">Điều kiện</a></span>'
                + '  </div>'
                + '</article>';
        }).join('');

        $list.html(html);
        $empty.addClass('d-none');
        $meta.text(rows.length + ' voucher');
    }

    function loadSavedCodes(){
        return $.get(API, { ajax: 'my_saved_vouchers' })
            .done(res => {
                if (res && res.ok && Array.isArray(res.codes)) {
                    state.savedCodes = res.codes.map(c => String(c || '').trim().toUpperCase());
                } else {
                    state.savedCodes = [];
                }
            })
            .fail(() => { state.savedCodes = []; });
    }

    function loadVouchers(){
        $meta.text('Đang tải voucher...');
        return $.get(API, { ajax: 'vouchers_public', target: 'all' })
            .done(res => {
                if (!res || !res.ok) {
                    $meta.text('Không tải được voucher.');
                    state.coupons = [];
                    state._defaultCoupons = [];
                    state._usingExternalCoupons = false;
                    renderList();
                    return;
                }
                state.coupons = Array.isArray(res.data) ? res.data : [];
                state._defaultCoupons = state.coupons;
                state._usingExternalCoupons = false;
                renderList();
            })
            .fail(() => {
                $meta.text('Không thể kết nối máy chủ voucher.');
                state.coupons = [];
                state._defaultCoupons = [];
                state._usingExternalCoupons = false;
                renderList();
            });
    }

    // Tab filter
    $('.voux-tabs').on('click', '.voux-tab', function(){
        const key = String($(this).data('voux-tab') || 'order');
        setTab(key);
    });

    // Nhập và lưu mã thủ công
    $codeInput.on('input', function(){
        const has = String($(this).val() || '').trim().length > 0;
        $saveBtn.prop('disabled', !has);
    });

    $saveBtn.on('click', function(){
        const code = String($codeInput.val() || '').trim();
        if (!code) return;
        $.post(API, { action: 'voucher_save', code })
            .done(res => {
                if (!res || !res.ok) {
                    if (window.toastr && toastr.warning) toastr.warning((res && res.msg) || 'Không lưu được mã voucher');
                    return;
                }
                if (window.toastr && toastr.success) toastr.success(res.msg || ('Đã lưu mã: ' + code));
                $codeInput.val('');
                $saveBtn.prop('disabled', true);
                $.when(loadSavedCodes(), loadVouchers()).done(() => {
                    renderList();
                    if (isModalOpen && typeof modalOptions.onSaved === 'function') {
                        modalOptions.onSaved(code);
                    }
                });
            })
            .fail(() => {
                if (window.toastr && toastr.error) toastr.error('Lỗi kết nối server');
            });
    });

    // Click chọn voucher trong modal
    $list.on('click', '.vcp-use-btn', function(){
        const codeRaw = String($(this).data('vcp-use') || '').trim();
        if (!codeRaw) return;
        const codeUpper = codeRaw.toUpperCase();
        const targetRaw = String($(this).data('vcp-target') || 'order');
        const target = targetRaw === 'shipping' ? 'shipping' : (targetRaw === 'payment' ? 'payment' : 'order');

        const applyFromCheckout = () => {
            if (isModalOpen && typeof modalOptions.onSaved === 'function') {
                modalOptions.onSaved(codeRaw);
            }
            if (isModalOpen && typeof modalOptions.onApply === 'function') {
                modalOptions.onApply({ code: codeRaw, target });
            }
        };

        // Nếu modal được mở từ checkout/cart (có callback onApply)
        if (isModalOpen && modalOptions.onApply) {
            if (state.savedCodes.includes(codeUpper)) {
                applyFromCheckout();
                return;
            }
            $.post(API, { action: 'voucher_save', code: codeRaw })
                .done(res => {
                    if (!res || !res.ok) {
                        if (window.toastr && toastr.warning) toastr.warning((res && res.msg) || 'Không lưu được mã');
                        return;
                    }
                    if (window.toastr && toastr.success) toastr.success(res.msg || ('Đã lưu mã: ' + codeRaw));
                    state.savedCodes.push(codeUpper);
                    renderList();
                    applyFromCheckout();
                })
                .fail(() => {
                    if (window.toastr && toastr.error) toastr.error('Lỗi kết nối server');
                });
            return;
        }

        // Chế độ kho voucher độc lập: chỉ lưu mã giống voucher.php
        if (state.savedCodes.includes(codeUpper)) {
            if (window.toastr && toastr.info) toastr.info('Mã đã được lưu trước đó.');
            return;
        }
        $.post(API, { action: 'voucher_save', code: codeRaw })
            .done(res => {
                if (!res || !res.ok) {
                    if (window.toastr && toastr.warning) toastr.warning((res && res.msg) || 'Không lưu được mã');
                    return;
                }
                if (window.toastr && toastr.success) toastr.success(res.msg || ('Đã lưu mã: ' + codeRaw));
                state.savedCodes.push(codeUpper);
                renderList();
            })
            .fail(() => {
                if (window.toastr && toastr.error) toastr.error('Lỗi kết nối server');
            });
    });

    function openModalWithOptions(opts){
        opts = opts || {};
        modalOptions.onApply = typeof opts.onApply === 'function' ? opts.onApply : null;
        modalOptions.onSaved = typeof opts.onSaved === 'function' ? opts.onSaved : null;
        isModalOpen = true;

        if (Array.isArray(opts.savedVoucherCodes)) {
            state.savedCodes = opts.savedVoucherCodes
                .map(v => String(v || '').trim().toUpperCase())
                .filter(Boolean);
        }

        // Allow checkout/cart to supply a pre-filtered list.
        if (Array.isArray(opts.vouchers)) {
            state.coupons = opts.vouchers;
            state._usingExternalCoupons = true;
        } else if (state._usingExternalCoupons && Array.isArray(state._defaultCoupons)) {
            state.coupons = state._defaultCoupons;
            state._usingExternalCoupons = false;
        }

        state.selectedOrderCode = String(opts.selectedOrderCode || '').trim();
        state.selectedShipCode = String(opts.selectedShipCode || '').trim();
        state.selectedPaymentCode = String(opts.selectedPaymentCode || '').trim();

        const initialTab = opts.initialTab === 'shipping' ? 'shipping' : (opts.initialTab === 'payment' ? 'payment' : 'order');
        setTab(initialTab);
        openPanel();
    }

    function closeModal(){
        isModalOpen = false;
        modalOptions.onApply = null;
        modalOptions.onSaved = null;
        closePanel();
    }

    $backdrop.on('click', closeModal);
    $closeBtn.on('click', closeModal);
    $doneBtn.on('click', closeModal);

    window.pmVoucherModal = {
        open: openModalWithOptions,
        close: closeModal
    };

    // Khởi tạo dữ liệu lần đầu cho kho voucher dùng chung (trường hợp chỉ xem/lưu mã, không mở từ checkout/cart)
    $.when(loadSavedCodes(), loadVouchers()).done(() => {
        renderList();
    });
})(window);
</script>


<script>
(function(){
    const storeTabs = document.getElementById('footerStoreTabs');
    const storeList = document.getElementById('footerStoreList');
    const storeEmpty = document.getElementById('footerStoreEmpty');
    if (storeTabs && storeList) {
        const tabButtons = Array.from(storeTabs.querySelectorAll('.store-tab-btn'));
        const storeItems = Array.from(storeList.querySelectorAll('.store-list__item'));

        const applyStoreRegion = (regionKey) => {
            let visibleCount = 0;
            storeItems.forEach((item) => {
                const key = String(item.getAttribute('data-region') || 'south').toLowerCase();
                const match = key === regionKey;
                item.style.display = match ? '' : 'none';
                if (match) visibleCount += 1;
            });

            tabButtons.forEach((btn) => {
                const key = String(btn.getAttribute('data-region') || '').toLowerCase();
                btn.classList.toggle('active', key === regionKey);
            });

            if (storeEmpty) {
                storeEmpty.classList.toggle('show', visibleCount === 0);
            }
        };

        tabButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const region = String(btn.getAttribute('data-region') || '').toLowerCase();
                if (region !== 'north' && region !== 'south' && region !== 'central') return;
                applyStoreRegion(region);
            });
        });

        applyStoreRegion('south');
    }
})();

(function(){
    /*window.addEventListener('load', () => {
        const loader = document.getElementById('pageLoader');
        if (!loader) return;
        loader.classList.add('is-hidden');
        setTimeout(() => loader.remove(), 300);
    });*/

    // Nút + panel chỉ render cho admin. Với user thường chúng không tồn tại,
    // nhưng phần áp dụng setting (theme/font/dark-mode mặc định) BÊN DƯỚI vẫn phải chạy.
    const btn = document.getElementById('layoutSettingsBtn');
    const panel = document.getElementById('layoutPanel');
    const closeBtn = document.getElementById('layoutPanelClose');
    const leftChk = document.getElementById('toggleLeftSidebar');
    const rightChk = document.getElementById('toggleRightSidebar');
    const horizonChk = document.getElementById('toggleHorizon');
    const mainContainer = document.getElementById('mainContainer');
    const swatches = document.querySelectorAll('.theme-swatch');
    const fontSwatches = document.querySelectorAll('.font-swatch');
    const darkModeChk = document.getElementById('toggleDarkMode');

    const themeMap = {
        blue: { primary: '#1877f2', dark: '#0f5bd6', soft: 'rgba(24, 119, 242, 0.12)', rgb: '24, 119, 242' },
        green: { primary: '#0b4b28', dark: '#083a1e', soft: 'rgba(11, 75, 40, 0.12)', rgb: '11, 75, 40' },
        ithanhloc: { primary: '#142c0c', dark: '#383535', soft: 'rgba(47, 110, 228, 0.12)', rgb: '255, 255, 255' },
        orange: { primary: '#ee4d2d', dark: '#d63d1f', soft: 'rgba(255, 255, 255, 0.12)', rgb: '238, 77, 45' },
        teal: { primary: '#0f766e', dark: '#0f5f59', soft: 'rgba(15, 118, 110, 0.12)', rgb: '15, 118, 110' },
        red: { primary: '#dc2626', dark: '#b91c1c', soft: 'rgba(220, 38, 38, 0.12)', rgb: '220, 38, 38' }
    };

    const fontMap = {
        default: { text: '#050505', sub: '#65676b' },
        graphite: { text: '#1f2937', sub: '#6b7280' },
        forest: { text: '#0b4b28', sub: '#4b6b57' },
        cocoa: { text: '#3f2a1d', sub: '#7c5c3f' },
        berry: { text: '#3b1d4a', sub: '#7a6488' }
    };

    // Font chữ hệ thống (font-family)
    const fontFamilyMap = {
        montserrat: '"Montserrat", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
        helvetica: '"Helvetica Neue", Helvetica, Arial, sans-serif',
        system: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif',
        arial: 'Arial, "Helvetica Neue", Helvetica, sans-serif',
        verdana: 'Verdana, Geneva, "Segoe UI", sans-serif',
        tahoma: 'Tahoma, Geneva, Verdana, sans-serif',
        serif: 'Georgia, "Times New Roman", Times, serif'
    };
    const fontFamilySelect = document.getElementById('fontFamilySelect');
    const fontWeightSelect = document.getElementById('fontWeightSelect');

    const alignSeg = document.getElementById('headerAlignSeg');
    const bgSeg = document.getElementById('headerBgSeg');
    const alignBtns = alignSeg ? alignSeg.querySelectorAll('.layout-seg-btn') : [];
    const bgBtns = bgSeg ? bgSeg.querySelectorAll('.layout-seg-btn') : [];

    // Header controls (headerMenuChk / headerStaticChk đã bị xóa khỏi HTML)
    // Chủ động xóa localStorage cũ để không bị apply class header-menu-hidden / header-static
    localStorage.removeItem('layout_header_menu_hidden');
    localStorage.removeItem('layout_header_static');

    const state = {
        hideLeft: localStorage.getItem('layout_hide_left') !== '0',
        hideRight: localStorage.getItem('layout_hide_right') !== '0',
        horizon: localStorage.getItem('layout_horizon') === '1',
        theme: localStorage.getItem('layout_theme') || 'green',
        font: localStorage.getItem('layout_font') || 'default',
        fontFamily: localStorage.getItem('layout_font_family') || 'montserrat', //montserrat
        fontWeight: localStorage.getItem('layout_font_weight') || '400',
        headerAlign: localStorage.getItem('layout_header_align') || 'left',
        headerBg: localStorage.getItem('layout_header_bg') || 'light',
        darkMode: localStorage.getItem('layout_dark_mode') === '1'
    };
    if (!themeMap[state.theme]) state.theme = 'green';
    if (!fontFamilyMap[state.fontFamily]) state.fontFamily = 'montserrat';

    const applyTheme = () => {
        const theme = themeMap[state.theme] || themeMap.blue;
        const root = document.documentElement;
        root.style.setProperty('--theme-primary', theme.primary);
        root.style.setProperty('--theme-primary-dark', theme.dark);
        root.style.setProperty('--theme-primary-soft', theme.soft);
        root.style.setProperty('--theme-primary-rgb', theme.rgb);
        root.style.setProperty('--theme-primary-contrast', '#ffffff');

        swatches.forEach(btn => btn.classList.toggle('active', btn.dataset.theme === state.theme));
    };

    const applyHeaderLayout = () => {
        const body = document.body;
        // Luôn xóa class cũ (không apply lại từ state đã bị gỡ)
        body.classList.remove('header-menu-hidden', 'header-static');
        body.classList.toggle('header-logo-center', state.headerAlign === 'center');
        body.classList.remove('header-bg-dark', 'header-bg-transparent');
        if (state.headerBg === 'dark') body.classList.add('header-bg-dark');
        else if (state.headerBg === 'transparent') body.classList.add('header-bg-transparent');

        alignBtns.forEach(b => b.classList.toggle('active', b.dataset.align === state.headerAlign));
        bgBtns.forEach(b => b.classList.toggle('active', b.dataset.bg === state.headerBg));
    };

    const applyFont = () => {
        const font = fontMap[state.font] || fontMap.default;
        const root = document.documentElement;
        root.style.setProperty('--theme-font', font.text);
        root.style.setProperty('--theme-font-sub', font.sub);

        fontSwatches.forEach(btn => btn.classList.toggle('active', btn.dataset.font === state.font));
    };

    const applyFontFamily = () => {
        const stack = fontFamilyMap[state.fontFamily] || fontFamilyMap.montserrat;
        document.documentElement.style.setProperty('--theme-font-family', stack);
        if (fontFamilySelect) fontFamilySelect.value = state.fontFamily;
    };

    const applyFontWeight = () => {
        const weight = state.fontWeight || '400';
        document.documentElement.style.setProperty('--theme-font-weight', weight);
        if (fontWeightSelect) fontWeightSelect.value = weight;
    };

    const applyDarkMode = (animate) => {
        const body = document.body;
        if (animate) {
            body.classList.add('dark-mode-transition');
            setTimeout(() => body.classList.remove('dark-mode-transition'), 400);
        }
        body.classList.toggle('dark-mode', state.darkMode);
        if (darkModeChk) darkModeChk.checked = state.darkMode;
        // Update Bootstrap color-scheme meta for native elements
        document.documentElement.setAttribute('data-bs-theme', state.darkMode ? 'dark' : 'light');
    };

    const applyState = () => {
        document.body.classList.toggle('hide-left', state.hideLeft);
        document.body.classList.toggle('hide-right', state.hideRight);
        document.body.classList.toggle('autohide-left', false);
        document.body.classList.toggle('horizon', state.horizon);
        document.body.classList.remove('skin-default','skin-soft','skin-warm','skin-cool');
        document.body.classList.remove('style-rounded','style-flat','style-glass');
        if (mainContainer) {
            mainContainer.classList.toggle('main-fluid', state.horizon);
            mainContainer.classList.toggle('main-boxed', !state.horizon);
        }
        if (leftChk) leftChk.checked = state.hideLeft;
        if (rightChk) rightChk.checked = state.hideRight;
        if (horizonChk) horizonChk.checked = state.horizon;

        applyDarkMode(false);
        applyTheme();
        applyFont();
        applyFontFamily();
        applyFontWeight();
        applyHeaderLayout();
    };

    const saveState = () => {
        localStorage.setItem('layout_hide_left', state.hideLeft ? '1' : '0');
        localStorage.setItem('layout_hide_right', state.hideRight ? '1' : '0');
        localStorage.setItem('layout_horizon', state.horizon ? '1' : '0');
        localStorage.setItem('layout_theme', state.theme);
        localStorage.setItem('layout_font', state.font);
        localStorage.setItem('layout_font_family', state.fontFamily);
        localStorage.setItem('layout_font_weight', state.fontWeight);
        localStorage.setItem('layout_header_align', state.headerAlign);
        localStorage.setItem('layout_header_bg', state.headerBg);
        localStorage.setItem('layout_dark_mode', state.darkMode ? '1' : '0');
        localStorage.removeItem('layout_header_menu_hidden');
        localStorage.removeItem('layout_header_static');
        localStorage.removeItem('layout_skin');
        localStorage.removeItem('layout_style');
    };

    // Chỉ gắn sự kiện mở/đóng panel khi panel tồn tại (admin)
    if (btn && panel) {
        btn.addEventListener('click', () => panel.classList.toggle('show'));
        closeBtn?.addEventListener('click', () => panel.classList.remove('show'));
    }

    leftChk?.addEventListener('change', () => { state.hideLeft = leftChk.checked; saveState(); applyState(); });
    rightChk?.addEventListener('change', () => { state.hideRight = rightChk.checked; saveState(); applyState(); });
    horizonChk?.addEventListener('change', () => { state.horizon = horizonChk.checked; saveState(); applyState(); });

    darkModeChk?.addEventListener('change', () => { state.darkMode = darkModeChk.checked; saveState(); applyDarkMode(true); });



    alignBtns.forEach(b => {
        b.addEventListener('click', () => { state.headerAlign = b.dataset.align || 'left'; saveState(); applyHeaderLayout(); });
    });
    bgBtns.forEach(b => {
        b.addEventListener('click', () => { state.headerBg = b.dataset.bg || 'light'; saveState(); applyHeaderLayout(); });
    });

    swatches.forEach(b => {
        b.addEventListener('click', () => { state.theme = b.dataset.theme || 'green'; saveState(); applyTheme(); });
    });

    fontFamilySelect?.addEventListener('change', () => {
        state.fontFamily = fontFamilySelect.value || 'montserrat';
        saveState();
        applyFontFamily();
    });

    fontWeightSelect?.addEventListener('change', () => {
        state.fontWeight = fontWeightSelect.value || '400';
        saveState();
        applyFontWeight();
    });

    fontSwatches.forEach(btn => {
        btn.addEventListener('click', () => {
            state.font = btn.dataset.font || 'default';
            saveState();
            applyFont();
        });
    });

    applyState();
})();

</script>
<!-- Zalo Official Plugin Button
<div class="zalo-chat-widget" data-oaid="2202859046012173275" data-welcome-message="Rất vui khi được hỗ trợ bạn!" data-autopopup="1" data-width="250" data-height="320"></div>
<script src="https://sp.zalo.me/plugins/sdk.js"></script>-->

<?php
// ===== Chat hỗ trợ trực tuyến 24/7 (widget khách/user, mọi trang) =====
$__liveChat = function_exists('app_get_config_value_by_path') ? app_get_config_value_by_path('LIVE_CHAT.enabled') : true;
$__liveChatOn = ($__liveChat === null) ? true
    : ($__liveChat === true || $__liveChat === 1 || $__liveChat === '1' || strtolower((string)$__liveChat) === 'true');
// Ẩn nút chat KHÁCH khi đang là admin (admin đã có hộp chat riêng ở cùng góc phải).
if ($__liveChatOn && empty($isAdmin)):
?>
<link rel="stylesheet" href="<?= h($baseUrl) ?>/assets/pm/chat-widget.css?v=<?= @filemtime(__DIR__ . '/assets/pm/chat-widget.css') ?: '1' ?>">
<button type="button" id="pmchatLauncher" class="pmchat-launcher" aria-label="Chat hỗ trợ trực tuyến">
    <span class="pmchat-tooltip"><i class="bi bi-chat-text-fill"></i> Chat với nhân viên</span>
    <i class="bi bi-chat-dots-fill"></i>
    <span class="pmchat-badge"></span>
</button>
<button type="button" class="chat-close-btn" id="hideChatSupport" title="Ẩn chat hỗ trợ 24h" aria-label="Ẩn chat hỗ trợ">
    <i class="bi bi-x"></i>
</button>
<div id="pmchatPanel" class="pmchat-panel" role="dialog" aria-label="Hộp chat hỗ trợ">
    <div class="pmchat-head">
        <div class="pmchat-avatar"><i class="bi bi-headset"></i></div>
        <div>
            <div class="pmchat-htitle">Hỗ trợ trực tuyến</div>
            <div class="pmchat-hsub"><span class="dot"></span> Tư vấn viên 24/7</div>
        </div>
        <button type="button" class="pmchat-expand" id="pmchatExpand" aria-label="Phóng to" title="Phóng to"><i class="bi bi-arrows-fullscreen"></i></button>
        <button type="button" class="pmchat-close" aria-label="Đóng">&times;</button>
    </div>
    <!-- Mini-form khách vãng lai -->
    <div class="pmchat-guest" style="display:none;">
        <h6>Chào bạn 👋</h6>
        <p>Vui lòng để lại thông tin để nhân viên tư vấn hỗ trợ bạn nhanh nhất.</p>
        <div class="pmchat-form-group">
            <input type="text" id="pmchatGuestName" placeholder="Họ và tên" autocomplete="name">
            <div class="pmchat-error-msg" id="pmchatGuestNameError"></div>
        </div>
        <div class="pmchat-form-group">
            <input type="tel" id="pmchatGuestPhone" placeholder="Số điện thoại" autocomplete="tel">
            <div class="pmchat-error-msg" id="pmchatGuestPhoneError"></div>
        </div>
        <button type="button" id="pmchatGuestStart" class="pmchat-startbtn">Bắt đầu trò chuyện</button>
    </div>
    <!-- Khung tin nhắn -->
    <div class="pmchat-body" style="display:none;">
        <div class="pmchat-empty">Hãy gửi tin nhắn để bắt đầu trò chuyện.</div>
    </div>
    <!-- Ô nhập -->
    <div class="pmchat-foot" style="display:none;">
        <div id="pmchatPreviews" class="pmchat-previews"></div>
        <div class="pmchat-inputrow">
            <button type="button" id="pmchatAttach" class="pmchat-iconbtn pmchat-attach" title="Đính kèm ảnh"><i class="bi bi-image"></i></button>
            <input type="file" id="pmchatFile" accept="image/*" multiple style="display:none;">
            <textarea id="pmchatText" rows="1" placeholder="Nhập tin nhắn..."></textarea>
            <button type="button" id="pmchatSend" class="pmchat-iconbtn pmchat-send" title="Gửi"><i class="bi bi-send-fill"></i></button>
        </div>
    </div>
</div>
<script>
window.PMCHAT_CFG = {
    base: <?= json_encode(rtrim((string)($baseUrl ?? ''), '/'), JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode((string)($csrfToken ?? ''), JSON_UNESCAPED_SLASHES) ?>,
    isLogged: <?= !empty($_SESSION['user_id']) ? 'true' : 'false' ?>,
    endpoint: <?= json_encode(rtrim((string)($baseUrl ?? ''), '/') . '/core_user/support/ajax/chat.php', JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= h($baseUrl) ?>/assets/pm/chat-widget.js?v=<?= @filemtime(__DIR__ . '/assets/pm/chat-widget.js') ?: '1' ?>"></script>
<?php endif; /* LIVE_CHAT */ ?>

<?php
/**
 * Cấu hình thông tin liên lạc
 */
$contact_phone     = $site_hotline; // Thay số điện thoại của bạn
$contact_zalo      = $site_hotline; // Thay link hoặc số Zalo
$contact_messenger = 'paintandmoreasia'; // Thay Facebook Username hoặc ID

// Chỉ hiển thị contact-buttons-fixed trên trang Home (index)
$_foot_isHomePage = (
    !isset($_GET['normal']) &&
    !isset($_GET['user']) &&
    !isset($_GET['ithanhloc']) &&
    !isset($_GET['ghn'])
);
?>

<?php if ($_foot_isHomePage): ?>
<!-- Contact Button Styles -->
<style>
    .contact-buttons-fixed {
        position: fixed;
        bottom: 24px;
        left: 20px; /* chuyển sang TRÁI: nhường cạnh phải cho nút chat support */
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    @media (max-width: 480px) {
        .contact-buttons-fixed { left: 12px; bottom: 16px; }
    }

    /* Đẩy nút cài đặt bố cục layout-fab lên khi có các nút liên hệ ở trang chủ */
    .layout-fab {
        bottom: 210px !important;
    }
    .layout-panel {
        bottom: 270px !important;
        max-height: calc(100vh - 310px) !important;
    }
    @media (max-width: 768px) {
        .layout-fab {
            left: 14px !important;
            bottom: 200px !important;
        }
        .layout-panel {
            left: 14px !important;
            bottom: 260px !important;
            max-height: calc(100vh - 290px) !important;
        }
    }
    @media (max-width: 480px) {
        .layout-fab {
            left: 12px !important;
            bottom: 190px !important;
        }
        .layout-panel {
            left: 12px !important;
            bottom: 250px !important;
            max-height: calc(100vh - 270px) !important;
        }
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

    /* Hiệu ứng rung cho nút gọi (tùy chọn) */
    .contact-item.phone-btn {
        animation: quick-shake 2s infinite;
    }

    @keyframes quick-shake {
        0%, 100% { transform: rotate(0); }
        10%, 30%, 50% { transform: rotate(-10deg); }
        20%, 40%, 60% { transform: rotate(10deg); }
    }
</style>

<!-- Contact Button HTML -->
<div class="contact-buttons-fixed">
    <!-- Close/Hide Button -->
    <button type="button" class="contact-close-btn" id="hideContactButtons" title="Ẩn liên hệ 24h" aria-label="Ẩn các nút liên hệ">
        <i class="bi bi-x"></i>
    </button>
    <!-- Messenger -->
    <?php if ($contact_messenger): ?>
    <a href="https://m.me/<?php echo $contact_messenger; ?>" target="_blank" class="contact-item" title="Chat Messenger">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/be/Facebook_Messenger_logo_2020.svg" alt="Messenger">
    </a>
    <?php endif; ?>

    <!-- Zalo -->
    <?php if ($contact_zalo): ?>
    <a href="https://zalo.me/<?php echo $contact_zalo; ?>" target="_blank" class="contact-item" title="Chat Zalo">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/91/Icon_of_Zalo.svg/250px-Icon_of_Zalo.svg.png" alt="Zalo">
    </a>
    <?php endif; ?>

    <!-- Phone -->
    <?php if ($contact_phone): ?>
    <a href="tel:<?php echo $contact_phone; ?>" class="contact-item phone-btn" title="Gọi ngay">
        <img src="https://cdn-icons-png.flaticon.com/512/724/724664.png" alt="Phone">
    </a>
    <?php endif; ?>
</div>

<?php endif; /* end $_foot_isHomePage */ ?>

<!-- Store Detail Modal (App-like Location Card - Compact) -->
<div class="modal fade" id="storeDetailModal" tabindex="-1" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <!-- Cover -->
            <div class="position-relative bg-light" style="height: 160px;">
                <img id="sdCover" src="" class="w-100 h-100 object-fit-cover" alt="Branch Cover">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 bg-white p-2 rounded-circle shadow-sm" data-bs-dismiss="modal" aria-label="Close" style="width:10px; height:10px;"></button>
            </div>
            
            <div class="modal-body p-3 p-md-4">
                <h5 id="sdName" class="fw-bold mb-2 text-dark">Chi nhánh của chúng tôi</h5>
                
                <!-- Quick Actions -->
                <div class="d-flex gap-2 mb-3 overflow-auto pb-1 custom-scrollbar" style="white-space: nowrap;">
                    <a id="sdPhoneBtnPrimary" href="tel:" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium flex-fill text-center">
                        <i class="bi bi-telephone-fill me-1"></i><span id="sdPhone">Gọi ngay</span>
                    </a>
                    <a id="sdMapBtn" href="" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-medium flex-fill text-center">
                        <i class="bi bi-geo-alt-fill me-1"></i>Chỉ đường
                    </a>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-geo-alt text-primary fs-6 me-2 mt-1"></i>
                        <div>
                            <h6 class="fw-bold mb-1 small">Địa chỉ</h6>
                            <p id="sdAddress" class="text-muted mb-0 small">Đang tải...</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start">
                        <i class="bi bi-clock text-primary fs-6 me-2 mt-1"></i>
                        <div class="w-100">
                            <h6 class="fw-bold mb-1 small">Giờ hoạt động</h6>
                            <div id="sdHours" class="text-muted small mt-1">
                                <!-- Rendered by JS -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Horizontal scrollable gallery -->
                <div id="sdGallerySection" class="d-none mt-3 pt-3 border-top">
                    <h6 class="fw-bold mb-2 small">Hình ảnh tại chi nhánh</h6>
                    <div id="sdGallery" class="d-flex gap-2 overflow-auto pb-2 custom-scrollbar">
                        <!-- Rendered by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { height: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.gallery-item-scroll { width: 80px; height: 80px; flex-shrink: 0; }
.sd-hour-row-app { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dashed #f1f5f9; font-size: 0.85rem; }
.sd-hour-row-app:last-child { border-bottom: none; }
.store-list__item.cursor-pointer { cursor: pointer; }
.store-list__item:hover { background: rgba(0,0,0,0.02); }
.cursor-zoom-in { cursor: zoom-in; }
.group-hover-scale-110:hover { transform: scale(1.1); }
.transition-all { transition: all 0.3s ease; }
html.pm-hide-contacts-24h .contact-buttons-fixed {
    display: none !important;
}
/* Khi ẩn nút liên hệ (social) 24h, hạ nút cài đặt layout và nút chat support xuống */
html.pm-hide-contacts-24h .layout-fab {
    bottom: 20px !important;
}
html.pm-hide-contacts-24h .layout-panel {
    bottom: 80px !important;
    max-height: calc(100vh - 120px) !important;
}
html.pm-hide-contacts-24h .pmchat-launcher,
html.pm-hide-contacts-24h .pmac-launcher {
    bottom: 20px !important;
}
html.pm-hide-contacts-24h .chat-close-btn {
    bottom: 64px !important;
}

@media (max-width: 768px) {
    html.pm-hide-contacts-24h .layout-fab {
        left: 20px !important;
        bottom: 20px !important;
    }
    html.pm-hide-contacts-24h .layout-panel {
        left: 20px !important;
        bottom: 80px !important;
        max-height: calc(100vh - 120px) !important;
    }
    html.pm-hide-contacts-24h .pmchat-launcher,
    html.pm-hide-contacts-24h .pmac-launcher {
        bottom: 20px !important;
    }
    html.pm-hide-contacts-24h .chat-close-btn {
        bottom: 64px !important;
    }
}
@media (max-width: 480px) {
    html.pm-hide-contacts-24h .layout-fab {
        left: 12px !important;
        bottom: 16px !important;
    }
    html.pm-hide-contacts-24h .layout-panel {
        left: 12px !important;
        bottom: 76px !important;
        max-height: calc(100vh - 110px) !important;
    }
    html.pm-hide-contacts-24h .pmchat-launcher,
    html.pm-hide-contacts-24h .pmac-launcher {
        bottom: 16px !important;
    }
    html.pm-hide-contacts-24h .chat-close-btn {
        bottom: 60px !important;
    }
}
html.pm-hide-chat-24h .pmchat-launcher,
html.pm-hide-chat-24h #hideChatSupport {
    display: none !important;
}
html.pm-hide-chat-24h #btnScrollTop {
    bottom: 24px !important;
    transition: bottom 0.3s ease;
}
@media (max-width: 480px) {
    html.pm-hide-chat-24h #btnScrollTop {
        bottom: 16px !important;
    }
}
.chat-close-btn {
    position: fixed;
    right: 14px;
    bottom: 68px;
    z-index: 10005;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background-color: #ef4444;
    color: #fff;
    border: 1px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    transition: all 0.2s ease;
    cursor: pointer;
}
.chat-close-btn:hover {
    background-color: #dc2626;
    transform: scale(1.1);
}
.chat-close-btn i {
    font-size: 12px;
    line-height: 1;
    display: flex;
}
.contact-close-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background-color: #ef4444;
    color: #fff;
    border: 1px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
    margin-bottom: 4px;
    cursor: pointer;
    z-index: 10000;
    align-self: center;
}
.contact-close-btn:hover {
    background-color: #dc2626;
    transform: scale(1.1);
}
.contact-close-btn i {
    font-size: 14px;
    line-height: 1;
    display: flex;
}
</style>

<script>
(function(){
    const modalEl = document.getElementById('storeDetailModal');
    if (!modalEl) return;
    const bsModal = new bootstrap.Modal(modalEl);
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/store.php';
    const dayLabels = {
        mon: 'Thứ 2', tue: 'Thứ 3', wed: 'Thứ 4', thu: 'Thứ 5', fri: 'Thứ 6', sat: 'Thứ 7', sun: 'Chủ nhật'
    };

    const renderHours = (json) => {
        if (!json) return '<p class="mb-0">Đang cập nhật...</p>';
        try {
            const data = JSON.parse(json);
            let html = '';
            ['mon','tue','wed','thu','fri','sat','sun'].forEach(k => {
                const cfg = data[k] || {};
                const label = dayLabels[k] || k;
                const status = (cfg.enabled !== false && cfg.open && cfg.close) ? `<b class="text-dark">${cfg.open} - ${cfg.close}</b>` : `<span class="text-muted">Nghỉ</span>`;
                html += `<div class="sd-hour-row-app"><span>${label}</span>${status}</div>`;
            });
            return html;
        } catch(e) { return '<p class="mb-0">Đang cập nhật...</p>'; }
    };

    const renderGallery = (json) => {
        if (!json) return '';
        try {
            const list = JSON.parse(json);
            if (!Array.isArray(list) || !list.length) return '';
            return list.map(url => `
                <div class="gallery-item-scroll rounded-3 overflow-hidden border shadow-sm group cursor-zoom-in" onclick="window.open('${url}', '_blank')">
                    <img src="${url}" class="w-100 h-100 object-fit-cover transition-all group-hover-scale-110" alt="Gallery">
                </div>
            `).join('');
        } catch(e) { return ''; }
    };

    $(document).on('click', '.store-list__item', function(){
        const id = $(this).attr('store-id');
        if (!id) return;

        const loadingText = 'Đang tải...';
        $('#sdName').text(loadingText);
        $('#sdAddress').text('Vui lòng đợi...');
        $('#sdHours').empty();
        $('#sdGallery').empty();
        $('#sdGallerySection').addClass('d-none');
        const placeholder = 'https://via.placeholder.com/800x400?text=Paint+%26+More';
        $('#sdCover').attr('src', placeholder);

        bsModal.show();

        $.get(API, { action: 'get_details', id: id }, function(res){
            if (res && res.ok) {
                const d = res.data;
                $('#sdName').text(d.branch_name || 'Chi nhánh Paint & More');
                $('#sdAddress').text(d.address_detail || 'Đang cập nhật địa chỉ...');
                
                const hotlineRaw = d.hotline || 'Gọi ngay';
                const hotlineTel = hotlineRaw.replace(/[^0-9+]/g, '');
                
                $('#sdPhone').text(hotlineRaw);
                $('#sdPhoneBtnPrimary').attr('href', 'tel:' + hotlineTel);
                $('#sdMapBtn').attr('href', d.map_url || '#');
                $('#sdHours').html(renderHours(d.opening_hours_json));
                
                if (d.avatar_image) {
                    $('#sdCover').attr('src', d.avatar_image);
                }

                const galleryHtml = renderGallery(d.gallery_images_json);
                if (galleryHtml) {
                    $('#sdGallery').html(galleryHtml);
                    $('#sdGallerySection').removeClass('d-none');
                }
            } else {
                $('#sdName').text('Lỗi');
                $('#sdAddress').text(res.msg || 'Không thể tải thông tin chi nhánh');
            }
        }, 'json');
    });
})();
</script>
<style>
.cursor-pointer { cursor: pointer !important; }
.cursor-zoom-in { cursor: zoom-in; }
.group-hover-scale-110:hover { transform: scale(1.1); }
.transition-all { transition: all 0.3s ease; }
.hover-danger:hover { color: #dc3545 !important; }
.hover-primary:hover { color: var(--theme-primary) !important; }

/* ============ MINI CART — SIDE DRAWER (offcanvas) ============ */
#miniCartModal.offcanvas-end {
    width: 420px;
    max-width: 100vw;
    border-left: 0;
    box-shadow: -8px 0 40px rgba(15, 23, 42, 0.12);
    /* Nâng trên các nút nổi (chat z-index:10010, setting FAB:1056) để không bị che */
    z-index: 10060;
}
/* Backdrop của offcanvas phải nằm trên các nút nổi nhưng dưới drawer */
.offcanvas-backdrop {
    z-index: 10059;
}
#miniCartModal .mc-drawer-inner { background: #fff; min-height: 0; }

/* Fixed header / undo, scrollable body, pinned footer */
#miniCartModal .modal-header { flex-shrink: 0; }
#miniCartModal #miniCartUndoBar { flex-shrink: 0; }
#miniCartModal .mc-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
#miniCartModal .mc-footer { flex-shrink: 0; }

/* In a narrow drawer the table won't fit — render every row as a card */
#miniCartModal thead { display: none; }
#miniCartModal .table,
#miniCartModal tbody,
#miniCartModal tbody tr { display: block; width: 100%; }
#miniCartModal .table { table-layout: fixed; margin: 0; }

#miniCartModal tbody tr {
    position: relative;
    border: 1px solid #eef1f5;
    border-radius: 14px;
    padding: 10px 12px;
    margin-bottom: 10px;
    background: #fff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    transition: box-shadow .2s ease;
}
#miniCartModal tbody tr:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); }
#miniCartModal tbody tr:last-child { margin-bottom: 0; }
#miniCartModal tbody td {
    display: block;
    padding: 0 !important;
    border: 0;
    text-align: left !important;
}

/* --- Single horizontal row: thumb | info (flex) | side (price + qty/remove) --- */
#miniCartModal .mc-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
#miniCartModal .mc-thumb-wrap { position: relative; flex-shrink: 0; }
#miniCartModal .mc-thumb {
    width: 56px;
    height: 56px;
    object-fit: contain;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
#miniCartModal .mc-just-added-dot {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    width: 18px; height: 18px;
    background: #16a34a;
    color: #fff;
    border: 2px solid #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px;
}

/* Middle: name + variant, takes remaining width */
#miniCartModal .mc-item-info { min-width: 0; flex: 1 1 auto; }
#miniCartModal .mc-item-name {
    font-weight: 700;
    color: #1e293b;
    font-size: 0.82rem;
    line-height: 1.3;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
#miniCartModal .mc-item-variant { margin-bottom: 0; }

/* Right column: price on top, qty stepper + remove underneath */
#miniCartModal .mc-item-side {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}
#miniCartModal .mc-item-price {
    font-weight: 700;
    color: #1e293b;
    font-size: 0.9rem;
    white-space: nowrap;
}
#miniCartModal .mc-side-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Remove button — small trash icon, right of the stepper */
#miniCartModal .mc-remove-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    line-height: 1;
    flex-shrink: 0;
    transition: background .15s ease, color .15s ease;
}
#miniCartModal .mc-remove-btn:hover { background: #fee2e2; color: #dc2626; }

/* Qty stepper */
#miniCartModal .mc-qty-stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    overflow: hidden;
}
#miniCartModal .mc-qty-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #334155;
    font-size: 0.95rem;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s ease;
}
#miniCartModal .mc-qty-btn:not(.disabled):hover { background: #f1f5f9; }
#miniCartModal .mc-qty-input {
    width: 30px;
    height: 26px;
    border: 0;
    border-left: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
    text-align: center;
    font-weight: 700;
    font-size: 0.78rem;
    color: #1e293b;
    background: transparent;
    padding: 0;
}
#miniCartModal .mc-qty-input:focus { outline: none; }

.mini-cart-variant-select { min-width: 80px !important; font-size: 0.68rem !important; }

/* Footer: order summary on top, full-width CTA, then continue link */
#miniCartModal .mc-footer {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    border-top: 1px solid #eef1f5;
}
#miniCartModal .mc-summary {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
/* Each summary line: label left, value right */
#miniCartModal .mc-summary-line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}
#miniCartModal .mc-total-line {
    padding-top: 6px;
    border-top: 1px dashed #e2e8f0;
}
/* Checkout button — text centered inside the full-width button is correct */
#miniCartModal .mc-checkout {
    width: 100%;
    border-radius: 12px;
    padding: 0.7rem 1rem;
    font-size: 0.95rem;
}
/* Continue link — centered under the button, this is intentional */
#miniCartModal .mc-continue {
    display: block;
    width: 100%;
    text-align: center;
    font-size: 0.8rem;
}
#miniCartTotalAmount { font-size: 1.25rem !important; }

/* Full-screen drawer on phones */
@media (max-width: 575.98px) {
    #miniCartModal.offcanvas-end { width: 100vw; }
    #miniCartModal .mc-body { padding: 0.75rem !important; }

    .floating-cart-wrap {
        top: 90px;
        bottom: 80px;
        right: 10px;
        width: 48px;
        height: 48px;
    }
    .floating-cart-btn i { font-size: 1.2rem !important; }
}
@media (min-width: 576px) {
    .floating-cart-wrap {
        top: 160px;
        bottom: 80px;
        right: 20px;
        width: 48px;
        height: 48px;
    }
}
/* Floating Cart Wrapper & Trigger Zone */
.floating-cart-wrap {
    position: fixed;
    z-index: 1060;
    cursor: pointer;
    transition: opacity 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275),
                visibility 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275),
                transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.floating-cart-wrap.floating-cart-hidden {
    opacity: 0;
    visibility: hidden;
    transform: scale(0.6) translateX(20px);
    pointer-events: none;
}

/* Floating Cart Button (Visual container) */
.floating-cart-btn {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--theme-primary, #0d6efd);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
}

/* Trạng thái "thu nhỏ về mép": nút nép sát mép phải, chỉ ló một phần. */
.floating-cart-wrap.floating-cart-peek .floating-cart-btn {
    transform: translateX(58%) scale(0.9);
    opacity: 0.65;
}

/* Hover/focus sẽ bung trở lại đầy đủ mà không bị giật vì vùng trigger vẫn giữ nguyên vị trí */
.floating-cart-wrap.floating-cart-peek:hover .floating-cart-btn,
.floating-cart-wrap.floating-cart-peek:focus .floating-cart-btn {
    transform: translateX(0) scale(1.1) translateY(-2px);
    opacity: 1;
    background: #0b5ed7;
}

/* Ẩn alert + badge khi đang nép mép để không lòi ra ngoài màn hình */
.floating-cart-wrap.floating-cart-peek .badge {
    opacity: 0;
    transition: opacity 0.2s ease;
}
.floating-cart-wrap.floating-cart-peek:hover .badge,
.floating-cart-wrap.floating-cart-peek:focus .badge {
    opacity: 1;
}

/* Hover trạng thái bình thường (không peek) */
.floating-cart-wrap:not(.floating-cart-peek):hover .floating-cart-btn {
    transform: scale(1.1) translateY(-2px);
    background: #0b5ed7;
}

.floating-cart-btn i {
    font-size: 1.4rem;
}
.floating-cart-btn .badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    padding: 4px 7px;
    border-radius: 20px;
    border: 2px solid #fff;
    font-weight: 800;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Animations */
@keyframes cart-shake {
    0% { transform: scale(1.1) rotate(0); }
    25% { transform: scale(1.1) rotate(-15deg); }
    50% { transform: scale(1.1) rotate(15deg); }
    75% { transform: scale(1.1) rotate(-15deg); }
    100% { transform: scale(1.1) rotate(0); }
}
.cart-shake {
    animation: cart-shake 0.5s ease-in-out;
}

.cart-fly-item {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transition: all 0.8s cubic-bezier(0.1, 0.4, 0.1, 1);
    border: 2px solid #fff;
    transform: scale(1) rotate(0deg);
}

.floating-cart-alert {
    position: absolute;
    right: 130%;
    top: 50%;
    transform: translateY(-50%) translateX(20px);
    background: #fff;
    color: #333;
    padding: 8px 15px;
    border-radius: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid #e2e8f0;
}
.floating-cart-alert.show {
    opacity: 1;
    transform: translateY(-50%) translateX(0);
}
</style>

<!-- Floating Cart Icon -->
<div id="floatingCart" class="floating-cart-wrap floating-cart-peek" style="display:none;" onclick="if(window.pmShowMiniCart) window.pmShowMiniCart();">
    <div class="floating-cart-btn">
        <i class="fa-solid fa-bag-shopping"></i>
        <span id="floatingCartBadge" class="badge">0</span>
        <div id="floatingCartAlert" class="floating-cart-alert">
            <i class="bi bi-check-circle-fill text-success me-2"></i><span>Đã thêm vào giỏ</span>
        </div>
    </div>
</div>

<!-- Mini cart drawer - Global shared component (side drawer / offcanvas) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="miniCartModal" aria-labelledby="miniCartModalLabel">
    <div class="mc-drawer-inner d-flex flex-column h-100">
            <!-- Header with notification -->
            <div class="modal-header border-0 bg-light p-3 position-relative">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cart-check-fill fs-4" style="color: var(--theme-primary, #0d6efd) !important;"></i>
                    <span class="fw-semibold text-dark">Sản phẩm trong giỏ hàng</span>
                </div>
                <button type="button" class="btn-close shadow-none position-absolute" data-bs-dismiss="offcanvas" aria-label="Close" style="top: 20px; right: 20px; z-index: 10;"></button>
            </div>
            <!-- Undo Bar -->
            <div id="miniCartUndoBar" class="bg-dark text-white p-2 d-none transition-all d-flex justify-content-between align-items-center" style="font-size: 0.75rem;">
                <span id="miniCartUndoMsg"></span>
                <button type="button" id="miniCartUndoBtn" class="btn btn-sm btn-link text-white text-decoration-none fw-bold p-0 px-2" style="font-size: 0.75rem;">HOÀN TÁC</button>
            </div>
            <div class="modal-body mc-body p-3">
                <h6 class="fw-bold text-uppercase mb-3" style="letter-spacing: 0.5px; font-size: 0.9rem;">GIỎ HÀNG CỦA BẠN</h6>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <thead class="text-muted text-uppercase border-bottom" style="font-size: 0.7rem;">
                            <tr>
                                <th class="mc-col-product" style="padding-left: 0;">Sản phẩm</th>
                                <!--th class="text-center">Đơn giá</th-->
                                <th class="mc-col-qty text-center">Số lượng</th>
                                <th class="mc-col-total text-end" style="padding-right: 0;">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody id="miniCartItemsTable">
                            <!-- Items will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mc-footer border-0 bg-light p-3">
                <div class="mc-summary">
                    <div class="mc-summary-line">
                        <span class="text-muted" style="font-size: 0.72rem;">Phí vận chuyển</span>
                        <span class="fw-medium text-dark" style="font-size: 0.72rem;">Tính lúc thanh toán</span>
                    </div>
                    <div class="mc-summary-line mc-total-line">
                        <span class="fw-semibold text-dark" style="font-size: 0.85rem;">Tổng cộng</span>
                        <span class="fw-bold" id="miniCartTotalAmount" style="color: var(--theme-primary) !important; font-size: 1.25rem;">0đ</span>
                    </div>
                </div>
                <a href="<?= h($baseUrl) ?>/checkout" class="mc-checkout btn btn-primary fw-bold d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-cart-check"></i> Đặt hàng ngay
                </a>
                <a href="<?= h($baseUrl) ?>/shopping" class="mc-continue text-decoration-none text-muted fw-medium transition-all hover-primary">
                    <i class="bi bi-arrow-left me-1"></i> Tiếp tục mua hàng
                </a>
            </div>
    </div>
</div>

<script>
(function() {
    const modalEl = document.getElementById('miniCartModal');
    const tableBodyEl = document.getElementById('miniCartItemsTable');
    const totalAmountEl = document.getElementById('miniCartTotalAmount');
    const floatingBadge = document.getElementById('floatingCartBadge');
    const floatingBtn = document.getElementById('floatingCart');
    const CART_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
    const FALLBACK_IMG = '<?= $site_fallback_logo ? h(to_abs_url((string)$site_fallback_logo, (string)$baseUrl)) : '' ?>';
    let miniCartModal = null;
    let lastCartAction = null;
    let undoTimeout = null;

    // Ẩn nút giỏ nổi khi drawer mở, hiện lại khi đóng (bắt cả overlay/ESC)
    if (modalEl && floatingBtn) {
        modalEl.addEventListener('show.bs.offcanvas', function() {
            floatingBtn.classList.add('floating-cart-hidden');
        });
        modalEl.addEventListener('hidden.bs.offcanvas', function() {
            floatingBtn.classList.remove('floating-cart-hidden');
            // Hiện đầy đủ một lúc rồi thu về mép như hành vi mặc định
            if (window.pmExpandFloatingCart) window.pmExpandFloatingCart();
        });
    }

    function showUndoBar(msg, action) {
        const bar = document.getElementById('miniCartUndoBar');
        const msgEl = document.getElementById('miniCartUndoMsg');
        if (!bar || !msgEl) return;

        lastCartAction = action;
        msgEl.textContent = msg;
        bar.classList.remove('d-none');

        if (undoTimeout) clearTimeout(undoTimeout);
        undoTimeout = setTimeout(() => {
            bar.classList.add('d-none');
            lastCartAction = null;
        }, 5000);
    }

    function hideUndoBar() {
        const bar = document.getElementById('miniCartUndoBar');
        if (bar) bar.classList.add('d-none');
        if (undoTimeout) clearTimeout(undoTimeout);
        lastCartAction = null;
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = String(str || '');
        return div.innerHTML;
    }

    function formatWeight(value, unit) {
        let v = parseFloat(value) || 0;
        if (v <= 0) return '';
        let u = (unit || 'kg').toLowerCase().trim();
        const unitMap = { 'kg': 'Kg', 'l': 'L', 'gr': 'g', 'gram': 'g', 'ml': 'ml', 'oz': 'oz' };
        if (v >= 1000) {
            if (u === 'gr' || u === 'gram' || u === 'g') { v /= 1000; u = 'kg'; }
            else if (u === 'ml') { v /= 1000; u = 'l'; }
        }
        const displayUnit = unitMap[u] || u;
        return parseFloat(v.toFixed(3)) + ' ' + displayUnit;
    }

    function cleanVariantWeightLegacy(text, weight, unit) {
        let t = String(text || '').trim();
        if (t === '' || t === 'Mặc định') return 'Mặc định';
        
        // Pattern 1: Any number followed by weight/volume units
        const unitPattern = /\b\d+([.,]\d+)?\s*(gram|gr|g|kg|ml|l|oz|ounce|ounces)\b/gi;
        // Pattern 2: Same but inside parentheses
        const parenPattern = /\(\s*\d+([.,]\d+)?\s*(gram|gr|g|kg|ml|l|oz|ounce|ounces)\s*\)/gi;
        
        t = t.replace(parenPattern, ' ');
        t = t.replace(unitPattern, ' ');
        
        // If we have a specific weight value, try to remove it too even if units don't match exactly
        if (weight) {
            const v = parseFloat(weight);
            if (v > 0) {
                const valPattern = new RegExp('\\b' + v + '(\\.0+)?\\b', 'gi');
                // Only remove if it looks like a weight (followed by something or at end)
                // But let's be careful not to remove "2X" if weight is 2.
                // For now, the general patterns above are safer.
            }
        }
        
        t = t.replace(/\s+/g, ' ').trim();
        return t || 'Mặc định';
    }

    function toAbs(url) {
        // Dùng helper chung để route file media sang media domain.
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
        const raw = String(url || '').trim();
        if (!raw) return '';
        if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
        const base = '<?= h($baseUrl) ?>'.replace(/\/$/, '');
        const path = raw.startsWith('/') ? raw : '/' + raw;
        return base + path;
    }

    window.renderMiniCartPopup = function(cart, addedName) {
        if (!modalEl || !tableBodyEl || !Array.isArray(cart)) return;
        
        if (!miniCartModal) {
            miniCartModal = bootstrap.Offcanvas.getOrCreateInstance(modalEl);
        }


        let total = 0;
        if (!cart.length) {
            tableBodyEl.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted">Giỏ hàng trống.</td></tr>';
            if (totalAmountEl) totalAmountEl.textContent = '0đ';
        } else {
            const itemsHtml = cart.map(function(it) {
                const key = it && it.key ? String(it.key) : '';
                const name = it && (it.name || it.product_name) ? (it.name || it.product_name) : 'Sản phẩm';
                const qty = it && it.qty ? parseInt(it.qty) : 1;
                const thumb = it && it.thumb ? it.thumb : '';
                const price = typeof it.price !== 'undefined' ? parseFloat(it.price) : 0;
                const subtotal = price * qty;
                total += subtotal;

                const isGift = !!it.is_gift || (price <= 0);
                const giftBadge = isGift ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size: 9px; padding: 2px 4px; vertical-align: middle;">Quà tặng</span>' : '';
                const preorderBadge = (!isGift && !!it.is_preorder) ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size: 9px; padding: 2px 4px; vertical-align: middle;"><i class="bi bi-clock-history"></i> Đặt trước</span>' : '';
                
                const priceText = isGift ? '<span class="text-success fw-bold" style="font-size: 0.75rem;">Miễn phí</span>' : (window.pmFormatPrice ? window.pmFormatPrice(price) : price.toLocaleString('vi-VN') + 'đ');
                const subtotalText = isGift ? '<span class="text-success fw-bold" style="font-size: 0.8rem;">Miễn phí</span>' : (window.pmFormatPrice ? window.pmFormatPrice(subtotal) : subtotal.toLocaleString('vi-VN') + 'đ');
                
                const variantText = it.variant || it.variant_label || it.variant_name || 'Mặc định';
                const safeName = esc(name);
                const src = thumb ? toAbs(thumb) : FALLBACK_IMG;
                const safeThumb = esc(src);
                
                const isJustAdded = addedName && name === addedName;
                const justAddedHtml = isJustAdded ? 
                    '<div class="text-success d-flex align-items-center gap-1 mt-1" style="font-size: 10px; font-weight: 500;">' +
                    '<i class="bi bi-check2-circle"></i> Vừa thêm vào giỏ' +
                    '</div>' : '';

                const availableVariants = it.available_variants || [];
                const currentVariantId = parseInt(it.variant_id || 0);
                
                // Clean variant text from legacy weight strings and DO NOT append formatted weight
                let rawVariant = String(it.variant || it.variant_label || it.variant_name || 'Mặc định').trim();
                const wVal = it.shipping_weight_value || it.weight_value || 0;
                const wUnit = it.shipping_weight_unit || it.weight_unit || '';
                
                let cleanVariantText = cleanVariantWeightLegacy(rawVariant, wVal, wUnit);
                
                let variantHtml = '<span class="text-muted" style="font-size: 0.75rem;">' + esc(cleanVariantText) + '</span>';

                // Quà BXGY: cho đổi phân loại (dùng action cart_set_bxgy_choice). Sản phẩm thường: như cũ.
                const isBxgyGift = isGift && !!it.bxgy_promo_id;
                const allowVariantSelect = availableVariants.length > 1 && (!isGift || isBxgyGift);
                if (allowVariantSelect) {
                    const selExtra = isBxgyGift
                        ? ' data-gift_select="1" data-promo_id="' + esc(String(it.bxgy_promo_id)) + '" data-gift_pid="' + esc(String(it.product_id || it.id || 0)) + '"'
                        : '';
                    variantHtml = '<div class="position-relative d-inline-block">' +
                                  '<select class="form-select form-select-sm mini-cart-variant-select shadow-none border-0 bg-light-subtle pe-4"' + selExtra + ' ' +
                                  'style="font-size: 0.7rem; min-width: 100px; height: 24px; cursor: pointer; border-radius: 6px; -webkit-appearance: none; -moz-appearance: none; appearance: none; padding-top: 0; padding-bottom: 0;">';
                    availableVariants.forEach(function(v) {
                        const isSelected = (parseInt(v.id) === currentVariantId);
                        variantHtml += '<option value="' + v.id + '"' + (isSelected ? ' selected' : '') + '>' + esc(v.name) + '</option>';
                    });
                    variantHtml += '</select>' +
                                  '<i class="bi bi-chevron-down position-absolute top-50 end-0 translate-middle-y me-2 pointer-events-none" style="font-size: 8px; color: #94a3b8; pointer-events: none;"></i>' +
                                  '</div>';
                }

                return (
                    '<tr data-key="' + esc(key) + '"' +
                    ' data-pid="' + esc(String(it.product_id || it.id || 0)) + '"' +
                    ' data-variant_id="' + esc(String(it.variant_id || 0)) + '"' +
                    ' data-qty="' + esc(String(qty)) + '"' +
                    ' data-color_code="' + esc(String(it.color_code || '')) + '"' +
                    ' data-name="' + esc(String(name)) + '">' +
                    '  <td class="mc-col-product">' +
                    '    <div class="mc-row">' +
                    '      <div class="mc-thumb-wrap">' +
                    '        <img src="' + safeThumb + '" class="mc-thumb" onerror="this.src=\'' + FALLBACK_IMG + '\'">' +
                    '        ' + (isJustAdded ? '<span class="mc-just-added-dot"><i class="bi bi-check"></i></span>' : '') +
                    '      </div>' +
                    '      <div class="mc-item-info">' +
                    '        <div class="mc-item-name">' + safeName + giftBadge + preorderBadge + '</div>' +
                    '        <div class="mc-item-variant">' + variantHtml + '</div>' +
                    '        ' + justAddedHtml +
                    '      </div>' +
                    '      <div class="mc-item-side">' +
                    '        <div class="mc-item-price">' + subtotalText + '</div>' +
                    '        <div class="mc-side-actions">' +
                    '          <div class="mc-qty-stepper">' +
                    '            <button type="button" class="mc-qty-btn ' + (isGift ? 'disabled opacity-50' : 'mini-cart-qty-change') + '" data-delta="-1" aria-label="Giảm">−</button>' +
                    '            <input type="text" class="mc-qty-input" value="' + qty + '" readonly aria-label="Số lượng">' +
                    '            <button type="button" class="mc-qty-btn ' + (isGift ? 'disabled opacity-50' : 'mini-cart-qty-change') + '" data-delta="1" aria-label="Tăng">+</button>' +
                    '          </div>' +
                    '          <button type="button" class="mc-remove-btn mini-cart-remove-item" aria-label="Xóa sản phẩm" title="Xóa">' +
                    '            <i class="bi bi-trash3"></i>' +
                    '          </button>' +
                    '        </div>' +
                    '      </div>' +
                    '    </div>' +
                    '  </td>' +
                    '</tr>'
                );
            }).join('');
            tableBodyEl.innerHTML = itemsHtml;
            if (totalAmountEl) {
                totalAmountEl.textContent = window.pmFormatPrice ? window.pmFormatPrice(total) : total.toLocaleString('vi-VN') + 'đ';
            }
        }
    };

    window.cartRemoveItem = function(key) {
        // Đọc thông tin item từ DOM (data-*) để dựng undo, không cần fetch cart_get
        const row = tableBodyEl ? tableBodyEl.querySelector('tr[data-key="' + (window.CSS && CSS.escape ? CSS.escape(key) : key) + '"]') : null;
        const undoData = {
            action: 'cart_add',
            pid: row ? (row.getAttribute('data-pid') || 0) : 0,
            variant_id: row ? (row.getAttribute('data-variant_id') || 0) : 0,
            qty: row ? (parseInt(row.getAttribute('data-qty'), 10) || 1) : 1,
            color_code: row ? (row.getAttribute('data-color_code') || '') : ''
        };
        const itemName = row ? (row.getAttribute('data-name') || 'sản phẩm') : 'sản phẩm';

        const body = new URLSearchParams();
        body.set('action', 'cart_remove');
        body.set('key', key);
        fetch(CART_API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: body.toString(),
            credentials: 'include',
        }).then(r => r.ok ? r.json() : null)
        .then(res => {
            if (!res || !res.ok) {
                if (window.toastr && window.toastr.error) window.toastr.error((res && res.msg) || 'Không thể xóa');
                return;
            }
            updateCartBadgeFrom(res);
            window.renderMiniCartPopup(res.data || []);
            showUndoBar('Đã xóa ' + itemName, undoData);
        });
    };

    window.cartUpdateQty = function(key, qty, isUndo = false, oldQty = null) {
        // Lấy qty cũ từ DOM (không cần fetch cart_get -> nhanh hơn nhiều)
        if (oldQty === null) {
            const row = tableBodyEl ? tableBodyEl.querySelector('tr[data-key="' + (window.CSS && CSS.escape ? CSS.escape(key) : key) + '"]') : null;
            const inp = row ? row.querySelector('input') : null;
            oldQty = inp ? (parseInt(inp.value, 10) || 1) : qty;
        }
        const undoData = { action: 'cart_update_qty', key: key, qty: oldQty };

        const body = new URLSearchParams();
        body.set('action', 'cart_update_qty');
        body.set('key', key);
        body.set('qty', qty);
        fetch(CART_API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: body.toString(),
            credentials: 'include',
        }).then(r => r.ok ? r.json() : null)
        .then(res => {
            if (!res || !res.ok) {
                if (window.toastr && window.toastr.error) window.toastr.error((res && res.msg) || 'Không thể cập nhật');
                return;
            }
            updateCartBadgeFrom(res);
            window.renderMiniCartPopup(res.data || []);
            if (!isUndo) {
                showUndoBar('Đã đổi số lượng thành ' + qty, undoData);
            } else {
                hideUndoBar();
            }
        });
    };

    window.cartUpdateVariant = function(key, variantId, isUndo = false) {
        // Đọc thông tin cũ từ DOM (data-*) thay vì fetch cart_get
        const row = tableBodyEl ? tableBodyEl.querySelector('tr[data-key="' + (window.CSS && CSS.escape ? CSS.escape(key) : key) + '"]') : null;
        const oldVariantId = row ? (parseInt(row.getAttribute('data-variant_id'), 10) || 0) : 0;
        const itemPid = row ? (parseInt(row.getAttribute('data-pid'), 10) || 0) : 0;
        const undoData = { action: 'cart_update_variant', oldVariantId: oldVariantId, currentVariantId: variantId };

        const body = new URLSearchParams();
        body.set('action', 'cart_update_variant');
        body.set('key', key);
        body.set('variant_id', variantId);
        fetch(CART_API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: body.toString(),
            credentials: 'include',
        }).then(r => r.ok ? r.json() : null)
        .then(res => {
            if (!res || !res.ok) {
                if (window.toastr && window.toastr.error) window.toastr.error((res && res.msg) || 'Không thể cập nhật');
                return;
            }
            updateCartBadgeFrom(res);
            // After variant update, the key likely changed.
            const newCart = res.data || [];
            window.renderMiniCartPopup(newCart);

            if (!isUndo) {
                const newItem = newCart.find(it => (it.product_id || it.id) === itemPid && parseInt(it.variant_id) === parseInt(variantId));
                if (newItem) {
                    undoData.newKey = newItem.key;
                    showUndoBar('Đã đổi phân loại', undoData);
                }
            } else {
                hideUndoBar();
            }
        });
    };

    // Đổi phân loại cho dòng QUÀ TẶNG BXGY (dùng action cart_set_bxgy_choice)
    window.cartChangeGiftVariant = function(key, promoId, giftPid, giftVariantId) {
        const body = new URLSearchParams();
        body.set('action', 'cart_set_bxgy_choice');
        body.set('key', key);
        body.set('promo_id', promoId);
        body.set('gift_pid', giftPid);
        body.set('gift_variant_id', giftVariantId);
        fetch(CART_API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: body.toString(),
            credentials: 'include',
        }).then(r => r.ok ? r.json() : null)
        .then(res => {
            if (!res || !res.ok) {
                if (window.toastr && window.toastr.error) window.toastr.error((res && res.msg) || 'Không thể đổi phân loại quà');
                return;
            }
            updateCartBadgeFrom(res);
            window.renderMiniCartPopup(res.data || []);
        });
    };

    document.getElementById('miniCartUndoBtn')?.addEventListener('click', function() {
        if (!lastCartAction) return;
        
        const data = lastCartAction;
        const body = new URLSearchParams();
        
        if (data.action === 'cart_add') {
            body.set('action', 'cart_add');
            body.set('pid', data.pid);
            body.set('variant_id', data.variant_id);
            body.set('qty', data.qty);
            body.set('color_code', data.color_code);
            fetch(CART_API, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: body.toString(),
                credentials: 'include',
            }).then(r => r.json()).then(res => {
                if (res && res.ok) {
                    if (window.refreshCartBadge) window.refreshCartBadge();
                    window.renderMiniCartPopup(res.data || []);
                    hideUndoBar();
                }
            });
        } else if (data.action === 'cart_update_qty') {
            window.cartUpdateQty(data.key, data.qty, true);
        } else if (data.action === 'cart_update_variant') {
            // Use the new key to change back to old variant
            if (data.newKey) {
                window.cartUpdateVariant(data.newKey, data.oldVariantId, true);
            }
        }
    });

    if (tableBodyEl) {
        tableBodyEl.addEventListener('click', function(ev) {
            const removeBtn = ev.target.closest('.mini-cart-remove-item');
            if (removeBtn) {
                const row = removeBtn.closest('tr');
                const key = row ? row.getAttribute('data-key') : '';
                if (key) window.cartRemoveItem(key);
                return;
            }
            const qtyBtn = ev.target.closest('.mini-cart-qty-change');
            if (qtyBtn) {
                const delta = parseInt(qtyBtn.getAttribute('data-delta') || 0);
                const row = qtyBtn.closest('tr');
                const key = row ? row.getAttribute('data-key') : '';
                if (!key || delta === 0) return;
                const input = row.querySelector('input');
                const oldQty = Math.max(1, parseInt(input.value || 1, 10));
                const next = Math.max(1, oldQty + delta);
                if (next === oldQty) return;
                // Cập nhật lạc quan ngay trên UI để phản hồi tức thì
                input.value = next;
                row.setAttribute('data-qty', String(next));
                window.cartUpdateQty(key, next, false, oldQty);
            }
        });

        tableBodyEl.addEventListener('change', function(ev) {
            const select = ev.target.closest('.mini-cart-variant-select');
            if (select) {
                const row = select.closest('tr');
                const key = row ? row.getAttribute('data-key') : '';
                const variantId = select.value;
                if (!key || !variantId) return;
                if (select.getAttribute('data-gift_select') === '1') {
                    // Đổi phân loại quà tặng BXGY
                    const promoId = select.getAttribute('data-promo_id') || 0;
                    const giftPid = select.getAttribute('data-gift_pid') || 0;
                    window.cartChangeGiftVariant(key, promoId, giftPid, variantId);
                } else {
                    window.cartUpdateVariant(key, variantId);
                }
            }
        });
    }
    
    // Cập nhật badge trực tiếp từ response của action (không cần fetch thêm)
    function updateCartBadgeFrom(res) {
        if (!res || !res.ok) return;
        let count = res.count;
        if (typeof count === 'undefined' && Array.isArray(res.data)) {
            count = res.data.reduce((s, it) => s + (parseInt(it.qty, 10) || 0), 0);
        }
        count = count || 0;
        const badge = document.getElementById('cartBadge');
        if (badge) badge.textContent = count;
        if (floatingBadge) floatingBadge.textContent = count;
        // Không còn hàng -> ẩn hẳn nút. Có hàng -> để logic peek/expand quyết định hiển thị.
        if (floatingBtn) {
            if (count > 0) {
                floatingBtn.style.display = 'flex';
            } else {
                floatingBtn.style.display = 'none';
                floatingBtn.classList.remove('floating-cart-peek');
            }
        }
    }

    // ----- Quản lý hiển thị nút giỏ nổi: bung đầy đủ rồi tự thu về mép sau 3s -----
    let _floatingPeekTimer = null;
    function expandFloatingCart(autoPeek = true) {
        if (!floatingBtn) return;
        if (floatingBtn.style.display === 'none') return; // giỏ trống thì không bung
        floatingBtn.classList.remove('floating-cart-peek');
        if (_floatingPeekTimer) clearTimeout(_floatingPeekTimer);
        if (autoPeek) {
            _floatingPeekTimer = setTimeout(() => {
                // Không thu về mép nếu mini-cart đang mở (lúc đó nút vốn đã ẩn)
                floatingBtn.classList.add('floating-cart-peek');
            }, 3000);
        }
    }
    window.pmExpandFloatingCart = expandFloatingCart;

    window.refreshCartBadge = function() {
        fetch(CART_API + '?ajax=cart_get')
            .then(r => r.ok ? r.json() : null)
            .then(res => updateCartBadgeFrom(res));
    };

    window.pmFlyToCart = function(imgEl) {
        // Show alert from cart button
        if (floatingBtn) {
            expandFloatingCart(); // bung nút ra rồi tự thu về mép sau 3s
            floatingBtn.classList.add('cart-shake');
            setTimeout(() => floatingBtn.classList.remove('cart-shake'), 600);
            
            const alertEl = document.getElementById('floatingCartAlert');
            if (alertEl) {
                alertEl.classList.add('show');
                if (window._cartAlertTimeout) clearTimeout(window._cartAlertTimeout);
                window._cartAlertTimeout = setTimeout(() => {
                    alertEl.classList.remove('show');
                }, 2500);
            }
        }
    };

    /**
     * Global Add to Cart function with Animation
     * @param {number} pid 
     * @param {string} name 
     * @param {HTMLElement} imgEl Source image for animation
     * @param {Object} extra Additional payload fields
     */
    window.addToCartFromCard = function(pid, name, imgEl, extra = {}) {
        if (!pid) return;
        
        const body = new URLSearchParams();
        body.set('action', 'cart_add');
        body.set('pid', pid);
        body.set('qty', 1);
        if (extra) {
            Object.keys(extra).forEach(k => body.set(k, extra[k]));
        }
        
        fetch(CART_API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: body.toString(),
            credentials: 'include',
        }).then(r => r.ok ? r.json() : null)
        .then(res => {
            if (!res || !res.ok) {
                if (window.toastr) toastr.error(res?.msg || 'Lỗi thêm vào giỏ');
                return;
            }
            
            // Animation
            if (imgEl) window.pmFlyToCart(imgEl);
            
            // UI Updates
            if (window.refreshCartBadge) window.refreshCartBadge();
            if (window.renderMiniCartPopup) window.renderMiniCartPopup(res.data || [], name);
        });
    };

    let miniCartFetching = false;
    window.pmShowMiniCart = function() {
        if (!modalEl) return;
        if (!miniCartModal) miniCartModal = bootstrap.Offcanvas.getOrCreateInstance(modalEl);

        // Mở drawer NGAY (không chờ mạng) để tránh khựng/lag. Nội dung đã có sẵn từ
        // lần render trước; dữ liệu mới sẽ được nạp song song rồi cập nhật sau.
        miniCartModal.show();

        // Tránh bắn nhiều request chồng nhau (đặc biệt khi hover liên tục).
        if (miniCartFetching) return;
        miniCartFetching = true;
        fetch(CART_API + '?ajax=cart_get')
            .then(r => r.ok ? r.json() : null)
            .then(res => {
                if (res && res.ok) {
                    window.renderMiniCartPopup(res.data || []);
                }
            })
            .catch(() => {})
            .finally(() => { miniCartFetching = false; });
    };

    // Initial check: cập nhật badge + render sẵn nội dung mini-cart để khi mở drawer
    // (hover/click) hiển thị ngay, không bị trống nhấp nháy hay chờ mạng.
    fetch(CART_API + '?ajax=cart_get')
        .then(r => r.ok ? r.json() : null)
        .then(res => {
            updateCartBadgeFrom(res);
            if (res && res.ok) {
                window.renderMiniCartPopup(res.data || []);
            }
            if (floatingBtn && floatingBtn.style.display !== 'none') {
                expandFloatingCart();
            }
        });

    // Make floatingCart draggable like a chat bubble
    (function() {
        if (!floatingBtn) return;

        var isDragging = false;
        var startX, startY;
        var initialLeft, initialTop;
        var moved = false;
        
        function snapToEdge() {
            var rect = floatingBtn.getBoundingClientRect();
            var margin = window.innerWidth < 576 ? 10 : 20;
            var screenWidth = window.innerWidth;
            var targetLeft;

            if (rect.left + rect.width / 2 < screenWidth / 2) {
                targetLeft = margin;
            } else {
                targetLeft = screenWidth - rect.width - margin;
            }

            floatingBtn.style.transition = 'left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), top 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)';
            floatingBtn.style.left = targetLeft + 'px';
            
            setTimeout(function() {
                if (!isDragging) {
                    floatingBtn.style.transition = '';
                }
            }, 300);
        }

        function onStart(e) {
            if (e.type === 'mousedown' && e.button !== 0) return;
            
            var clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
            var clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;

            isDragging = true;
            moved = false;
            startX = clientX;
            startY = clientY;

            var rect = floatingBtn.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            floatingBtn.style.transition = 'none';
            floatingBtn.style.userSelect = 'none';

            if (e.type === 'mousedown') {
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onEnd);
            } else {
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('touchend', onEnd);
            }
        }

        function onMove(e) {
            if (!isDragging) return;
            
            var clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            var clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;

            var dx = clientX - startX;
            var dy = clientY - startY;

            if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
                moved = true;
            }

            if (moved) {
                if (e.cancelable) e.preventDefault();

                var newLeft = initialLeft + dx;
                var newTop = initialTop + dy;

                var rect = floatingBtn.getBoundingClientRect();
                var minLeft = 0;
                var maxLeft = window.innerWidth - rect.width;
                var minTop = 0;
                var maxTop = window.innerHeight - rect.height;

                newLeft = Math.max(minLeft, Math.min(newLeft, maxLeft));
                newTop = Math.max(minTop, Math.min(newTop, maxTop));

                floatingBtn.style.left = newLeft + 'px';
                floatingBtn.style.top = newTop + 'px';
                floatingBtn.style.bottom = 'auto';
                floatingBtn.style.right = 'auto';
            }
        }

        function onEnd(e) {
            if (!isDragging) return;
            isDragging = false;

            floatingBtn.style.userSelect = '';

            if (e.type === 'mouseup') {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onEnd);
            } else {
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onEnd);
            }

            if (moved) {
                snapToEdge();
                
                // Prevent click drawer from opening on drag end
                var clickCapture = function(event) {
                    event.stopImmediatePropagation();
                    event.preventDefault();
                    floatingBtn.removeEventListener('click', clickCapture, true);
                };
                floatingBtn.addEventListener('click', clickCapture, true);
            } else {
                floatingBtn.style.transition = '';
            }
        }

        floatingBtn.addEventListener('mousedown', onStart);
        floatingBtn.addEventListener('touchstart', onStart, { passive: true });
    })();
})();
</script>

<?php if (empty($isLoggedIn)):
    $quickAuthSiteKey = trim((string)($GOOGLE_RECAPTCHA['site_key'] ?? ''));
    $quickAuthGoogleCfg = $GOOGLE_LOGIN ?? [];
    $quickAuthGoogleClientId = trim((string)($quickAuthGoogleCfg['client_id'] ?? ''));
    $quickAuthGoogleEnabled = !empty($quickAuthGoogleCfg['enabled'])
        && $quickAuthGoogleClientId !== ''
        && stripos($quickAuthGoogleClientId, 'YOUR_GOOGLE_CLIENT_ID') === false;
?>
<!-- ============ QUICK AUTH MODAL (Đăng nhập / Đăng ký nhanh) ============ -->
<!-- Bổ sung popup, KHÔNG thay thế trang /login chính thức (vẫn dùng được link đầy đủ) -->
<div class="modal fade" id="quickAuthModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="quickAuthTitle">Chào mừng bạn!</h5>
                    <p class="text-muted small mb-0" id="quickAuthSubtitle">Đăng nhập để tiếp tục.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="d-flex border-bottom mb-3" style="gap:24px;">
                    <div class="qa-tab active fw-bold pb-2" data-mode="login" style="cursor:pointer;border-bottom:2px solid var(--theme-primary,#0c4c29);color:var(--theme-primary,#0c4c29);">Đăng nhập</div>
                    <div class="qa-tab fw-bold pb-2 text-muted" data-mode="register" style="cursor:pointer;">Đăng ký</div>
                </div>

                <div class="qa-alert mb-2"></div>

                <!-- Login -->
                <form id="qaLoginForm" class="qa-section">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Tài khoản</label>
                        <input name="username" type="text" class="form-control" placeholder="Tên đăng nhập / Email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Mật khẩu</label>
                        <div class="qa-pass-wrap">
                            <input name="password" type="password" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="qa-pass-toggle" aria-label="Hiện mật khẩu" tabindex="-1"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">ĐĂNG NHẬP</button>
                </form>

                <!-- Register -->
                <form id="qaRegisterForm" class="qa-section" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Tên đăng nhập</label>
                        <input name="reg_username" type="text" class="form-control" placeholder="viết liền, không dấu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Email</label>
                        <input name="reg_email" type="email" class="form-control" placeholder="user@gmail.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Mật khẩu</label>
                        <div class="qa-pass-wrap">
                            <input name="reg_password" type="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required>
                            <button type="button" class="qa-pass-toggle" aria-label="Hiện mật khẩu" tabindex="-1"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">TẠO TÀI KHOẢN</button>
                </form>

<?php if ($quickAuthGoogleEnabled): ?>
                <div class="qa-social" id="qaSocialBlock">
                    <div class="qa-divider"><span>hoặc</span></div>
                    <div id="qaGoogleBtn" class="qa-google-slot"></div>
                </div>
<?php endif; ?>

                <div class="text-center mt-3">
                    <a href="<?= h($baseUrl) ?>/login" class="small text-decoration-none text-muted no-auth-modal">
                        Quên mật khẩu / Đăng nhập bằng OTP &raquo;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #quickAuthModal .qa-pass-wrap { position: relative; }
    #quickAuthModal .qa-pass-wrap .form-control { padding-right: 42px; }
    #quickAuthModal .qa-pass-toggle {
        position: absolute;
        top: 50%;
        right: 6px;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        color: #6b7280;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-radius: 6px;
        line-height: 1;
    }
    #quickAuthModal .qa-pass-toggle:hover { color: var(--theme-primary, #0c4c29); }
    #quickAuthModal .qa-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #9ca3af;
        font-size: 12px;
        margin: 16px 0 12px;
    }
    #quickAuthModal .qa-divider::before,
    #quickAuthModal .qa-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }
    #quickAuthModal .qa-google-slot {
        display: flex;
        justify-content: center;
        min-height: 40px;
    }
</style>

<script>
(function(){
    var AUTH_ENDPOINT = '<?= h($baseUrl) ?>/login/';
    var SITE_KEY = <?= json_encode($quickAuthSiteKey) ?>;
    var GOOGLE_CLIENT_ID = <?= json_encode($quickAuthGoogleEnabled ? $quickAuthGoogleClientId : '') ?>;
    var modalEl = document.getElementById('quickAuthModal');
    if (!modalEl) return;

    // ===== Toggle hiện/ẩn mật khẩu =====
    modalEl.addEventListener('click', function(e){
        var btn = e.target.closest('.qa-pass-toggle');
        if (!btn) return;
        var input = btn.parentElement.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        var icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    function loadRecaptchaScript(cb) {
        if (!SITE_KEY) { if (cb) cb(); return; }
        if (typeof window.grecaptcha !== 'undefined') { if (cb) cb(); return; }
        var existing = document.getElementById('qaRecaptcha') || document.getElementById('recaptchaScript');
        if (existing) {
            existing.addEventListener('load', function(){ if (cb) cb(); }, { once: true });
            // Nếu script đã xong load nhưng sự kiện không kịp bắt
            if (typeof window.grecaptcha !== 'undefined') { if (cb) cb(); }
            return;
        }
        var s = document.createElement('script');
        s.id = 'qaRecaptcha';
        s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(SITE_KEY);
        s.async = true; s.defer = true;
        if (cb) s.addEventListener('load', cb, { once: true });
        document.head.appendChild(s);
    }

    // ===== Đăng nhập với Google (GIS) =====
    var googleInited = false;
    function loadGoogleScript(cb){
        if (!GOOGLE_CLIENT_ID) return;
        if (window.google && window.google.accounts && window.google.accounts.id) { cb(); return; }
        var existing = document.getElementById('qaGoogleGis');
        if (existing) { existing.addEventListener('load', cb, { once: true }); return; }
        var s = document.createElement('script');
        s.id = 'qaGoogleGis';
        s.src = 'https://accounts.google.com/gsi/client';
        s.async = true; s.defer = true;
        s.addEventListener('load', cb, { once: true });
        document.head.appendChild(s);
    }

    function handleGoogleCredential(resp){
        if (!resp || !resp.credential) return;
        clearAlert();
        showAlert('info', 'Đang xác thực với Google...');
        $.post(AUTH_ENDPOINT, { action: 'google-login', is_ajax: 1, credential: resp.credential }, function(raw){
            var r = parseResp(raw);
            if (r.success){
                showAlert('success', r.message || 'Thành công');
                setTimeout(function(){ location.href = r.redirect || location.href; }, 700);
            } else {
                showAlert('danger', r.message || 'Đăng nhập Google thất bại');
            }
        }, 'text').fail(function(){ showAlert('danger', 'Lỗi kết nối máy chủ'); });
    }

    function setupGoogle(){
        var slot = document.getElementById('qaGoogleBtn');
        if (!GOOGLE_CLIENT_ID || !slot) return;
        loadGoogleScript(function(){
            if (!(window.google && window.google.accounts && window.google.accounts.id)) return;
            if (!googleInited){
                google.accounts.id.initialize({ client_id: GOOGLE_CLIENT_ID, callback: handleGoogleCredential });
                googleInited = true;
            }
            slot.innerHTML = '';
            google.accounts.id.renderButton(slot, {
                theme: 'outline', size: 'large', type: 'standard',
                text: 'continue_with', shape: 'pill', width: 320, locale: 'vi'
            });
        });
    }

    function getModal(){ return bootstrap.Modal.getOrCreateInstance(modalEl); }
    window.openAuthModal = function(mode){
        switchMode(mode === 'register' ? 'register' : 'login');
        loadRecaptchaScript(null);
        setupGoogle();
        getModal().show();
    };

    function showAlert(type, msg){
        modalEl.querySelector('.qa-alert').innerHTML =
            '<div class="alert alert-' + type + ' py-2 px-3 small mb-0 rounded-3">' + msg + '</div>';
    }
    function clearAlert(){ modalEl.querySelector('.qa-alert').innerHTML = ''; }

    function switchMode(mode){
        modalEl.querySelectorAll('.qa-tab').forEach(function(t){
            var on = t.getAttribute('data-mode') === mode;
            t.classList.toggle('active', on);
            t.classList.toggle('text-muted', !on);
            t.style.borderBottom = on ? '2px solid var(--theme-primary,#0c4c29)' : 'none';
            t.style.color = on ? 'var(--theme-primary,#0c4c29)' : '';
        });
        document.getElementById('qaLoginForm').style.display = mode === 'login' ? '' : 'none';
        document.getElementById('qaRegisterForm').style.display = mode === 'register' ? '' : 'none';
        document.getElementById('quickAuthTitle').textContent = mode === 'login' ? 'Chào mừng bạn!' : 'Tham gia ngay!';
        document.getElementById('quickAuthSubtitle').textContent = mode === 'login' ? 'Đăng nhập để tiếp tục.' : 'Tạo tài khoản để nhận ưu đãi.';
        var social = document.getElementById('qaSocialBlock');
        if (social) social.style.display = mode === 'login' ? '' : 'none';
        clearAlert();
    }

    modalEl.querySelectorAll('.qa-tab').forEach(function(t){
        t.addEventListener('click', function(){ switchMode(t.getAttribute('data-mode')); });
    });

    function parseResp(raw){
        if (typeof raw === 'object') return raw;
        try { var s = raw.indexOf('{'), e = raw.lastIndexOf('}'); if (s !== -1) return JSON.parse(raw.substring(s, e + 1)); } catch(_){}
        return { success: false, message: 'Dữ liệu không hợp lệ' };
    }

    function submitAuth(data, $btn, originalText){
        $.post(AUTH_ENDPOINT, data, function(raw){
            var resp = parseResp(raw);
            if (resp.success){
                showAlert('success', resp.message || 'Thành công');
                setTimeout(function(){ location.href = resp.redirect || location.href; }, 700);
            } else {
                showAlert('danger', resp.message || 'Có lỗi xảy ra');
                $btn.prop('disabled', false).text(originalText);
            }
        }, 'text').fail(function(){
            showAlert('danger', 'Lỗi kết nối máy chủ');
            $btn.prop('disabled', false).text(originalText);
        });
    }

    function withRecaptcha(action, cb){
        if (!SITE_KEY) { cb(''); return; }
        loadRecaptchaScript(function(){
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function(){
                    grecaptcha.execute(SITE_KEY, { action: action }).then(function(token){ cb(token); });
                });
            } else { cb(''); }
        });
    }

    $('#qaLoginForm').on('submit', function(e){
        e.preventDefault(); clearAlert();
        var $btn = $(this).find('button[type="submit"]');
        var username = $(this).find('[name="username"]').val();
        var password = $(this).find('[name="password"]').val();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>XỬ LÝ...');
        withRecaptcha('login', function(token){
            var data = { action: 'login', is_ajax: 1, username: username, password: password };
            if (token) data['g-recaptcha-response'] = token;
            submitAuth(data, $btn, 'ĐĂNG NHẬP');
        });
    });

    $('#qaRegisterForm').on('submit', function(e){
        e.preventDefault(); clearAlert();
        var $btn = $(this).find('button[type="submit"]');
        var formData = $(this).serialize();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>XỬ LÝ...');
        withRecaptcha('register', function(token){
            var data = formData + '&action=register&is_ajax=1' + (token ? '&g-recaptcha-response=' + encodeURIComponent(token) : '');
            submitAuth(data, $btn, 'TẠO TÀI KHOẢN');
        });
    });

    // Chặn điều hướng tới trang /login: mở popup thay thế.
    // Thêm class "no-auth-modal" trên link nếu muốn vẫn sang trang đầy đủ.
    document.addEventListener('click', function(e){
        var a = e.target.closest('a');
        if (!a || a.classList.contains('no-auth-modal')) return;
        var href = a.getAttribute('href') || '';
        if (/(^|\/)login\/?($|\?)/.test(href) || /\/login$/.test(href)) {
            e.preventDefault();
            window.openAuthModal('login');
        }
    });

    // Phần tử có data-auth-modal cũng mở popup
    document.addEventListener('click', function(e){
        var t = e.target.closest('[data-auth-modal]');
        if (!t) return;
        e.preventDefault();
        window.openAuthModal(t.getAttribute('data-auth-modal') || 'login');
    });
})();
</script>
<?php endif; ?>


<!-- Tracking JS -->
<?php if (!empty($bootstrapOrder) && is_array($bootstrapOrder)): ?>
<script>
// 1. Định nghĩa và đổ dữ liệu động từ PHP vào Object Global JS
window.bootstrapOrder = {
    order_id: <?php echo json_encode($bootstrapOrder["order_id"] ?? ""); ?>,
    total_amount: <?php echo (float)($bootstrapOrder["total_amount"] ?? 0); ?>,
    // Kiểm tra và thay đổi các field PHP bên dưới cho khớp với Database thực tế của web
    customer_email: <?php echo json_encode($bootstrapOrder["customer"]["email"] ?? ""); ?>,
    customer_phone: <?php echo json_encode($bootstrapOrder["customer"]["phone"] ?? ""); ?>,
    customer_name: <?php echo json_encode($bootstrapOrder["customer"]["name"] ?? ""); ?>,

    items: [
        <?php
        $gtm_items = [];
        if (!empty($bootstrapOrder['items']) && is_array($bootstrapOrder['items'])) {
            foreach ($bootstrapOrder['items'] as $item) {
                $gtm_items[] = "{
                    id: " . json_encode((string)($item['id'] ?? '')) . ",
                    name: " . json_encode((string)($item['name'] ?? '')) . ",
                    price: " . (float)($item['price'] ?? 0) . ",
                    quantity: " . (int)($item['qty'] ?? 1) . "
                }";
            }
        }
        echo implode(",", $gtm_items);
        ?>
    ]
};

// 2. Push cấu hình chuẩn vào DataLayer của GTM
window.dataLayer = window.dataLayer || [];

if (window.bootstrapOrder && window.bootstrapOrder.order_id) {
    window.dataLayer.push({
        'event': "purchase",
        'event_id': window.bootstrapOrder.order_id, // Key bắt buộc để Meta CAPI đối soát gộp trùng (Deduplication)

        'ecommerce': {
            'transaction_id': window.bootstrapOrder.order_id,
            'value': Number(window.bootstrapOrder.total_amount || 0),
            'currency': "VND",
            'items': (window.bootstrapOrder.items || []).map(item => ({
                'item_id': item.id || "",
                'item_name': item.name || "",
                'price': Number(item.price || 0),
                'quantity': Number(item.quantity || 1)
            }))
        },

        'customer': {
            'email': window.bootstrapOrder.customer_email || "",
            'phone': window.bootstrapOrder.customer_phone || "",
            'first_name': window.bootstrapOrder.customer_name || ""
        }
    });
}
</script>
<?php endif; ?>

<!-- Popup xác nhận sử dụng Cookie -->
<?php
$pmCookieConsentName = 'pm_cookie_consent';
// Đã có lựa chọn (chấp nhận '1' giữ 1 năm, hoặc từ chối '0' giữ 24h) -> không hỏi lại.
$pmCookieConsentHandled = isset($_COOKIE[$pmCookieConsentName])
    && ($_COOKIE[$pmCookieConsentName] === '1' || $_COOKIE[$pmCookieConsentName] === '0');
// Không hiện popup trên các trang chính sách/điều khoản để khách đọc được nội dung
// mà không bị backdrop che chắn (tránh trường hợp bắt buộc đồng ý mới đọc được).
$pmCookieCurrentUri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
$pmCookiePolicyPaths = ['chinh-sach-bao-mat', 'chinh-sach-doi-tra', 'chinh-sach-van-chuyen', 'terms', 'huong-dan-thanh-toan'];
$pmCookieOnPolicyPage = false;
foreach ($pmCookiePolicyPaths as $pmCookiePath) {
    if (strpos($pmCookieCurrentUri, $pmCookiePath) !== false) { $pmCookieOnPolicyPage = true; break; }
}
?>
<?php if (!$pmCookieConsentHandled && !$pmCookieOnPolicyPage): ?>
<div id="pmCookieBackdrop" class="pm-cookie-backdrop"></div>
<div id="pmCookieConsent" class="pm-cookie-consent" role="dialog" aria-live="polite" aria-label="Thông báo sử dụng cookie">
    <div class="pm-cookie-consent__content">
        <div class="pm-cookie-consent__head">
            <span class="pm-cookie-consent__icon" aria-hidden="true"><i class="bi bi-cookie"></i></span>
            <span class="pm-cookie-consent__title">Cookie &amp; trải nghiệm <?= h($company_name ?? 'Paint &amp; More') ?></span>
        </div>
        <p class="pm-cookie-consent__body">
            Cookies được sử dụng nhằm ghi nhận các thiết lập cần thiết để website hoạt động ổn định
            và hỗ trợ trải nghiệm mua sắm. Việc sử dụng cookies không làm gián đoạn trải nghiệm và
            giúp quá trình mua hàng diễn ra thuận tiện hơn.
        </p>
        <p class="pm-cookie-consent__more">
            Tìm hiểu thêm tại
            <a href="<?= h($baseUrl) ?>/chinh-sach-bao-mat.html" target="_blank" rel="noopener">Chính sách thu thập và xử lý dữ liệu cá nhân</a>.
        </p>
    </div>
    <div class="pm-cookie-consent__actions">
        <button type="button" class="btn pm-cookie-consent__decline" id="pmCookieDecline">Từ chối</button>
        <button type="button" class="btn pm-cookie-consent__accept" id="pmCookieAccept">Chấp nhận</button>
    </div>
</div>
<style>
    .pm-cookie-backdrop{position:fixed;inset:0;z-index:1999;background:rgba(15,23,42,.45);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:opacity .4s ease,visibility .4s ease;}
    .pm-cookie-backdrop.is-visible{opacity:1;visibility:visible;}
    .pm-cookie-consent{position:fixed;left:50%;bottom:16px;z-index:2000;width:min(94vw,760px);transform:translateX(-50%) translateY(160%);opacity:0;transition:transform .4s cubic-bezier(0.16,1,0.3,1),opacity .4s ease;background:#fff;color:var(--fb-text);border:1px solid rgba(0,0,0,.06);border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,.16);padding:18px 24px;text-align:left;display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
    .pm-cookie-consent.is-visible{transform:translateX(-50%) translateY(0);opacity:1;animation:pmCookieFloat 3s ease-in-out infinite;}
    @keyframes pmCookieFloat{0%,100%{box-shadow:0 12px 32px rgba(15,23,42,.16),0 0 0 0 rgba(var(--theme-primary-rgb),.35);}50%{box-shadow:0 18px 40px rgba(15,23,42,.2),0 0 0 8px rgba(var(--theme-primary-rgb),.08);}}
    .pm-cookie-consent__content{flex:1 1 360px;min-width:0;}
    .pm-cookie-consent__head{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
    .pm-cookie-consent__icon{font-size:1.2rem;line-height:1;}
    .pm-cookie-consent__title{font-size:.95rem;font-weight:800;color:var(--fb-text);}
    .pm-cookie-consent__body{font-size:.82rem;line-height:1.5;color:var(--fb-text-sub);margin:0 0 4px;}
    .pm-cookie-consent__more{font-size:.8rem;line-height:1.45;color:var(--fb-text-sub);margin:0;}
    .pm-cookie-consent__more a{color:var(--theme-primary);font-weight:600;text-decoration:none;}
    .pm-cookie-consent__more a:hover{color:var(--theme-primary-dark);text-decoration:underline;}
    .pm-cookie-consent__actions{display:flex;gap:10px;flex-shrink:0;}
    .pm-cookie-consent__actions .btn{border-radius:9px;font-size:.82rem;font-weight:700;padding:8px 18px;line-height:1.2;white-space:nowrap;}
    .pm-cookie-consent__decline{border:1px solid rgba(var(--theme-primary-rgb),.3);background:#fff;color:var(--fb-text);}
    .pm-cookie-consent__decline:hover{border-color:var(--theme-primary);background:rgba(var(--theme-primary-rgb),.05);color:var(--fb-text);}
    .pm-cookie-consent__accept{position:relative;border:1px solid var(--theme-primary);background:var(--theme-primary);color:#fff;box-shadow:0 4px 12px rgba(var(--theme-primary-rgb),.3);animation:pmAcceptPulse 1.8s ease-in-out infinite;}
    .pm-cookie-consent__accept:hover{background:var(--theme-primary-dark);border-color:var(--theme-primary-dark);color:#fff;animation:none;}
    @keyframes pmAcceptPulse{0%,100%{transform:scale(1);box-shadow:0 4px 12px rgba(var(--theme-primary-rgb),.3),0 0 0 0 rgba(var(--theme-primary-rgb),.45);}50%{transform:scale(1.05);box-shadow:0 6px 16px rgba(var(--theme-primary-rgb),.4),0 0 0 6px rgba(var(--theme-primary-rgb),0);}}
    @media (max-width:575.98px){.pm-cookie-consent{left:12px;right:12px;bottom:12px;width:auto;transform:translateY(160%);flex-direction:column;align-items:stretch;gap:10px;padding:14px 16px;}.pm-cookie-consent.is-visible{transform:translateY(0);animation:none;}.pm-cookie-consent__content{flex:0 0 auto;}.pm-cookie-consent__body{font-size:.78rem;}.pm-cookie-consent__more{font-size:.76rem;}.pm-cookie-consent__actions{width:100%;gap:8px;}.pm-cookie-consent__actions .btn{flex:1;padding:9px 12px;}
    /* Khi banner cookie đang hiển thị: ẩn các nút nổi (social + chat) trên mobile để không che banner */
    body.pm-cookie-open .contact-buttons-fixed,body.pm-cookie-open .pmchat-launcher,body.pm-cookie-open #btnScrollTop{opacity:0!important;visibility:hidden!important;pointer-events:none!important;}}
</style>
<script>
(function(){
    var KEY = <?= json_encode($pmCookieConsentName) ?>;
    var box = document.getElementById('pmCookieConsent');
    var backdrop = document.getElementById('pmCookieBackdrop');
    if (!box) return;

    function setCookie(value, days){
        var expires = '';
        if (days){
            var d = new Date();
            d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + d.toUTCString();
        }
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = KEY + '=' + value + expires + '; path=/; SameSite=Lax' + secure;
    }

    function hide(){
        document.body.classList.remove('pm-cookie-open');
        box.classList.remove('is-visible');
        if (backdrop) backdrop.classList.remove('is-visible');
        setTimeout(function(){
            box.parentNode && box.parentNode.removeChild(box);
            if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        }, 400);
    }

    // Hiện popup + backdrop sau khi trang đã render
    document.body.classList.add('pm-cookie-open');
    requestAnimationFrame(function(){ requestAnimationFrame(function(){
        box.classList.add('is-visible');
        if (backdrop) backdrop.classList.add('is-visible');
    }); });

    // Đánh dấu đã hiển thị ngay khi popup xuất hiện -> chỉ hỏi lại sau 24h,
    // kể cả khi khách chưa bấm nút (chỉ ghi nếu chưa có cookie từ trước).
    if (document.cookie.indexOf(KEY + '=') === -1) setCookie('0', 1);

    var accept = document.getElementById('pmCookieAccept');
    var decline = document.getElementById('pmCookieDecline');
    // Chấp nhận: lưu 1 năm.
    if (accept) accept.addEventListener('click', function(){ setCookie('1', 365); hide(); });
    // Từ chối: giữ mức 24h như khi hiển thị.
    if (decline) decline.addEventListener('click', function(){ setCookie('0', 1); hide(); });
})();
</script>
<?php endif; ?>

<script>
(function() {
    // 1. Hide immediately to avoid FOUC (Flash of Unstyled Content)
    const now = Date.now();
    const contactHideUntil = localStorage.getItem('contact_hide_until');
    const chatHideUntil = localStorage.getItem('chat_support_hide_until');

    if (contactHideUntil && now < parseInt(contactHideUntil, 10)) {
        document.documentElement.classList.add('pm-hide-contacts-24h');
    }
    if (chatHideUntil && now < parseInt(chatHideUntil, 10)) {
        document.documentElement.classList.add('pm-hide-chat-24h');
    }

    // 2. Bind click events after DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const contactBtn = document.getElementById('hideContactButtons');
        if (contactBtn) {
            contactBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                const handleConfirm = function() {
                    localStorage.setItem('contact_hide_until', Date.now() + 24 * 60 * 60 * 1000);
                    document.documentElement.classList.add('pm-hide-contacts-24h');
                    if (window.toastr) {
                        toastr.success('Đã ẩn các nút liên hệ trong 24 giờ.');
                    } else {
                        alert('Đã ẩn các nút liên hệ trong 24 giờ.');
                    }
                };

                if (window.Swal) {
                    Swal.fire({
                        title: 'Xác nhận ẩn',
                        text: 'Bạn có chắc chắn muốn ẩn các nút liên hệ này trong 24 giờ không?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--theme-primary, #0c4c29)',
                        cancelButtonColor: '#dc3545',
                        confirmButtonText: 'Đồng ý',
                        cancelButtonText: 'Hủy'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            handleConfirm();
                        }
                    });
                } else {
                    if (confirm('Bạn có chắc chắn muốn ẩn các nút liên hệ này trong 24 giờ không?')) {
                        handleConfirm();
                    }
                }
            });
        }

        const chatBtn = document.getElementById('hideChatSupport');
        if (chatBtn) {
            chatBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();

                const handleConfirm = function() {
                    localStorage.setItem('chat_support_hide_until', Date.now() + 24 * 60 * 60 * 1000);
                    document.documentElement.classList.add('pm-hide-chat-24h');
                    if (window.toastr) {
                        toastr.success('Đã ẩn nút chat hỗ trợ trong 24 giờ.');
                    } else {
                        alert('Đã ẩn nút chat hỗ trợ trong 24 giờ.');
                    }
                };

                if (window.Swal) {
                    Swal.fire({
                        title: 'Xác nhận ẩn',
                        text: 'Bạn có chắc chắn muốn ẩn nút chat hỗ trợ trực tuyến trong 24 giờ không?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--theme-primary, #0c4c29)',
                        cancelButtonColor: '#dc3545',
                        confirmButtonText: 'Đồng ý',
                        cancelButtonText: 'Hủy'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            handleConfirm();
                        }
                    });
                } else {
                    if (confirm('Bạn có chắc chắn muốn ẩn nút chat hỗ trợ trực tuyến trong 24 giờ không?')) {
                        handleConfirm();
                    }
                }
            });
        }

        // Hide close button if launcher is clicked (opens chat panel)
        const launcher = document.getElementById('pmchatLauncher');
        if (launcher) {
            launcher.addEventListener('click', function() {
                const chatClose = document.getElementById('hideChatSupport');
                if (chatClose) chatClose.style.display = 'none';
            });
        }

        // Show close button again if chat panel is closed (if 24h timer not active)
        const panelCloseBtn = document.querySelector('.pmchat-close');
        if (panelCloseBtn) {
            panelCloseBtn.addEventListener('click', function() {
                const chatHideUntil = localStorage.getItem('chat_support_hide_until');
                if (!chatHideUntil || Date.now() >= parseInt(chatHideUntil, 10)) {
                    const chatClose = document.getElementById('hideChatSupport');
                    if (chatClose) chatClose.style.display = '';
                }
            });
        }
    });
})();
</script>

<!-- Copyright -->
<div class="site-copyright" style="text-align:center;padding:14px 16px;font-size:.85rem;color:var(--fb-text-sub,#6b7280);">
    &copy; <?= date('Y') ?> <?= h($company_name ?? 'Công ty Cổ Phần Paint&More') ?>. Bản quyền đã được bảo hộ.
</div>


