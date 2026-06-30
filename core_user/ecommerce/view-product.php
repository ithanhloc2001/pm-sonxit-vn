<?php
// Đọc tham số pid (id sản phẩm) từ URL
$pid = 0;
if (isset($_GET['pid'])) {
    $pid = (int)$_GET['pid'];
}
// Khởi tạo CSRF token cho form đánh giá / Q&A
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Đọc tham số q (từ khoá tìm kiếm) từ URL để dùng cho highlight
$searchQ = '';
if (isset($_GET['q'])) {
    $searchQ = trim((string)$_GET['q']);
}
// Bắt buộc đăng nhập để xem chi tiết sản phẩm
$requireLoginForPurchase = false;

// Trạng thái block sản phẩm (mặc định là không block)
$productBlocked = false;
$productBlockReason = 'Sản phẩm không khả dụng.';

// Kiểm tra sản phẩm có ít nhất 1 phương thức vận chuyển đang hoạt động hay không
$productHasAnyActiveShipping = static function ($rawJson) {
    $arr = json_decode($rawJson, true);
    if (!is_array($arr)) {
        return false;
    }
    foreach ($arr as $item) {
        // Trường hợp lưu dạng chuỗi đơn giản
        if (is_string($item)) {
            if (trim($item) !== '') {
                return true;
            }
        }
        // Trường hợp lưu dạng mảng có key / value / active
        if (is_array($item)) {
            $key = '';
            if (isset($item['key'])) {
                $key = trim((string)$item['key']);
            } elseif (isset($item['value'])) {
                $key = trim((string)$item['value']);
            }

            $isActive = true;
            if (array_key_exists('active', $item)) {
                $isActive = (bool)$item['active'];
            }

            if ($isActive && $key !== '') {
                return true;
            }
        }
    }

    return false;
};

// Xác định bảng sản phẩm đang sử dụng trong CSDL
$productTable = '';
if (function_exists('first_existing_table')) {
    $productTable = first_existing_table($ithanhloc, array('ecommerce_product'));
}

// Dữ liệu để dựng Product JSON-LD (sẽ điền sau khi load $rowSeo)
$_pdSeoJsonLd = null;

// 10) Nếu tìm được bảng sản phẩm thì lấy thông tin để set SEO + validate trạng thái / vận chuyển
if ($productTable !== '') {
    // Lấy thêm manufacturer/sku cho Product JSON-LD
    $sqlSeo = "SELECT id, product_name, image_url, description, status, shipping_methods, manufacturer, sku FROM `{$productTable}` WHERE id = ? AND (status = 'true' OR status = '1') AND shipping_methods IS NOT NULL AND shipping_methods != '' LIMIT 1";
    $stmtSeo = $ithanhloc->prepare($sqlSeo);

    if ($stmtSeo) {
        $stmtSeo->bind_param('i', $pid);
        $stmtSeo->execute();
        $resSeo = $stmtSeo->get_result();
        $rowSeo = $resSeo ? $resSeo->fetch_assoc() : null;

        if (is_array($rowSeo)) {
            // 10.1) Lấy các trường cơ bản phục vụ SEO
            $pNameSeo = isset($rowSeo['product_name']) ? trim((string)$rowSeo['product_name']) : '';
            $pDescSeo = isset($rowSeo['description']) ? trim((string)$rowSeo['description']) : '';
            $pImageSeo = isset($rowSeo['image_url']) ? trim((string)$rowSeo['image_url']) : '';

            // 10.2) Kiểm tra trạng thái sản phẩm
            $statusValue = isset($rowSeo['status']) ? (string)$rowSeo['status'] : 'true';
            $statusRaw   = strtolower(trim($statusValue));

            // 10.3) Kiểm tra cấu hình phương thức vận chuyển
            $shippingRaw = isset($rowSeo['shipping_methods']) ? (string)$rowSeo['shipping_methods'] : '';
            $hasShipping = $productHasAnyActiveShipping($shippingRaw);

            if ($statusRaw !== 'true' && $statusRaw !== '1') {
                // Sản phẩm đang bị tạm dừng bán
                $productBlocked = true;
                $productBlockReason = 'Sản phẩm này hiện đang tạm dừng bán.';
            } elseif (!$hasShipping) {
                // Sản phẩm chưa được cấu hình phương thức vận chuyển hợp lệ
                $productBlocked = true;
                $productBlockReason = 'Sản phẩm này hiện chưa thiết lập phương thức vận chuyển phù hợp.';
            }

            // 10.4) Thiết lập tiêu đề SEO từ tên sản phẩm
            if ($pNameSeo !== '') {
                $siteTitleRaw = (isset($site_title) && $site_title !== '') ? (string)$site_title : 'Paintmore';
                $pageTitle = $pNameSeo . ' | ' . $siteTitleRaw;
            }

            // 10.5) Thiết lập mô tả SEO từ description (nếu chưa có bên ngoài truyền vào)
            if (!isset($pageDescription) || $pageDescription === null) {
                if ($pDescSeo !== '') {
                    $plain = strip_tags($pDescSeo);
                    $plain = preg_replace('/\s+/', ' ', $plain);
                    $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (function_exists('mb_substr')) {
                        $plain = mb_substr($plain, 0, 220, 'UTF-8');
                    } else {
                        $plain = substr($plain, 0, 220);
                    }
                    $pageDescription = trim($plain);
                }
            }

            // 10.6) Thiết lập URL ảnh SEO (ảnh sản phẩm)
            if ($pImageSeo !== '') {
                // image_url có thể là JSON mảng nhiều ảnh hoặc chuỗi ngăn cách (",", "|",
                // xuống dòng). Tách lấy ảnh ĐẦU TIÊN trước khi route qua media domain —
                // nếu không, to_abs_url() không nhận ra tiền tố 'uploads/' và rơi về baseUrl.
                $firstImg = $pImageSeo;
                $decoded  = json_decode($pImageSeo, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $cand) {
                        $cand = trim((string)$cand);
                        if ($cand !== '') { $firstImg = $cand; break; }
                    }
                } elseif (preg_match('/[\r\n,|]/', $pImageSeo)) {
                    $parts = preg_split('/[\r\n,|]+/', $pImageSeo);
                    foreach ((array)$parts as $cand) {
                        $cand = trim((string)$cand);
                        if ($cand !== '') { $firstImg = $cand; break; }
                    }
                }
                $firstImg = trim($firstImg);

                $isAbsolute         = (bool)preg_match('~^https?://~i', $firstImg);
                $isProtocolRelative = substr($firstImg, 0, 2) === '//';
                $isDataUri          = stripos($firstImg, 'data:') === 0;

                if ($isAbsolute || $isProtocolRelative || $isDataUri) {
                    $pageImageUrl = $firstImg;
                } else {
                    // Ảnh media → route qua media domain (og:image cần URL tuyệt đối).
                    $pageImageUrl = to_abs_url($firstImg, (string)$baseUrl);
                }
            }

            // 10.7) Thiết lập canonical URL cho sản phẩm (nếu hàm helper tồn tại)
            if (function_exists('pm_product_url')) {
                $prodIdSeo   = isset($rowSeo['id']) ? (int)$rowSeo['id'] : 0;
                $prodNameSeo = isset($rowSeo['product_name']) ? (string)$rowSeo['product_name'] : '';
                $baseUrlSeo  = isset($baseUrl) ? (string)$baseUrl : '';
                $pageCanonicalUrl = pm_product_url($prodIdSeo, $prodNameSeo, $baseUrlSeo);
            }

            // 10.8) Khai báo loại nội dung cho Open Graph
            $pageOgType = 'product';

            // 10.9) Chuẩn bị dữ liệu Product JSON-LD
            $_pdSeoId    = (int)$rowSeo['id'];
            $_pdSeoSku   = trim((string)($rowSeo['sku'] ?? ''));
            $_pdSeoBrand = trim((string)($rowSeo['manufacturer'] ?? ''));
            $_pdSeoPriceMin = 0;
            $_pdSeoInStock  = true;
            $_pdSeoVTbl = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
            if ($_pdSeoVTbl) {
                $_pdQv = $ithanhloc->prepare("SELECT MIN(price) AS pmin, SUM(GREATEST(stock_quantity,0)) AS sqty FROM `{$_pdSeoVTbl}` WHERE product_id = ?");
                if ($_pdQv) {
                    $_pdQv->bind_param('i', $_pdSeoId);
                    $_pdQv->execute();
                    $_pdRv = $_pdQv->get_result();
                    $_pdRow = $_pdRv ? $_pdRv->fetch_assoc() : null;
                    if ($_pdRow) {
                        $_pdSeoPriceMin = (int)($_pdRow['pmin'] ?? 0);
                        $_pdSeoInStock  = ((int)($_pdRow['sqty'] ?? 0)) > 0;
                    }
                    $_pdQv->close();
                }
            }
            // Aggregate rating từ ecommerce_product_review (status = 1)
            $_pdSeoAvg = 0;
            $_pdSeoCnt = 0;
            $_pdQr = $ithanhloc->prepare("SELECT AVG(rating) AS avgR, COUNT(*) AS cnt FROM ecommerce_product_review WHERE product_id = ? AND status = 1 AND rating > 0");
            if ($_pdQr) {
                $_pdQr->bind_param('i', $_pdSeoId);
                $_pdQr->execute();
                $_pdRr = $_pdQr->get_result();
                $_pdRowR = $_pdRr ? $_pdRr->fetch_assoc() : null;
                if ($_pdRowR) {
                    $_pdSeoAvg = round((float)($_pdRowR['avgR'] ?? 0), 1);
                    $_pdSeoCnt = (int)($_pdRowR['cnt'] ?? 0);
                }
                $_pdQr->close();
            }

            $_pdSeoJsonLd = [
                'name'        => $pNameSeo,
                'description' => trim(strip_tags($pDescSeo)),
                'image'       => $pageImageUrl ? [$pageImageUrl] : [],
                'sku'         => $_pdSeoSku,
                'mpn'         => $_pdSeoSku,
                'url'         => $pageCanonicalUrl ?? '',
                'brand'       => $_pdSeoBrand,
                'publisher'   => [
                    'name' => (isset($site_title) && $site_title !== '') ? (string)$site_title : 'Paint&More',
                ],
                'offers' => [
                    'currency'     => 'VND',
                    'price'        => $_pdSeoPriceMin,
                    'availability' => $_pdSeoInStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url'          => $pageCanonicalUrl ?? '',
                ],
                'aggregateRating' => [
                    'ratingValue' => $_pdSeoAvg,
                    'reviewCount' => $_pdSeoCnt,
                ],
            ];
        } else {
            // Không tìm thấy bản ghi sản phẩm
            $productBlocked = true;
            $productBlockReason = 'Sản phẩm này hiện không tồn tại hoặc đã được gỡ bỏ.';
        }

        // Đóng statement
        $stmtSeo->close();
    }
} else {
    // Không tìm thấy bảng sản phẩm trong CSDL
    $productBlocked = true;
}
?>
<?php
// Nếu sản phẩm bị block: trả về 404 + thông điệp thân thiện
if ($productBlocked) {
    if (!headers_sent()) {
        http_response_code(404);
    }
    if (!isset($pageTitle) || $pageTitle === null || $pageTitle === '') {
        $siteTitleRaw = isset($site_title) && $site_title !== '' ? (string)$site_title : 'Paintmore';
        $pageTitle = 'Sản phẩm không khả dụng | ' . $siteTitleRaw;
    }
    if (!isset($pageDescription) || $pageDescription === null || $pageDescription === '') {
        $pageDescription = $productBlockReason;
    }

    // Nếu chỉ chạy để set SEO (từ index.php) thì dừng tại đây
    if (!empty($APP_SEO_ONLY)) {
        return;
    }

?>
    <div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 16px;">
        <div style="text-align:center;max-width:480px;">
            <div style="width:96px;height:96px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 28px;border:2px solid #e2e8f0;">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="9" y1="13" x2="15" y2="13" />
                    <line x1="9" y1="17" x2="13" y2="17" />
                    <line x1="9" y1="9" x2="10" y2="9" />
                </svg>
            </div>
            <h3 style="font-size:1.25rem;font-weight:800;color:#1e293b;margin-bottom:10px;">Sản phẩm không tìm thấy</h3>
            <p style="font-size:0.95rem;color:#64748b;line-height:1.7;margin-bottom:28px;">
                <?= h($productBlockReason ?: 'Sản phẩm bạn đang tìm kiếm có thể đã bị ẩn, ngừng bán hoặc đường dẫn không chính xác.') ?>
            </p>
            <a href="<?= h($baseUrl) ?>/shopping" style="display:inline-flex;align-items:center;gap:8px;background:#0c4c29;color:#fff;text-decoration:none;padding:11px 24px;border-radius:10px;font-weight:700;font-size:0.9rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
                Xem tất cả sản phẩm
            </a>
        </div>
    </div>
<?php
    return;
}
// Nếu chỉ chạy để set SEO (từ index.php) thì dừng tại đây
if (!empty($APP_SEO_ONLY)) {
    return;
}

// JSON-LD Product + Breadcrumb
if (file_exists(__DIR__ . '/../../core/seo_jsonld.php')) {
    require_once __DIR__ . '/../../core/seo_jsonld.php';
    if (is_array($_pdSeoJsonLd ?? null)) {
        echo seo_jsonld_product($_pdSeoJsonLd);
        $_pdBcBase = isset($baseUrl) ? rtrim((string)$baseUrl, '/') : '';
        echo seo_jsonld_breadcrumb([
            ['name' => 'Trang chủ', 'url' => $_pdBcBase . '/'],
            ['name' => 'Mua sắm',   'url' => $_pdBcBase . '/shopping'],
            ['name' => (string)($_pdSeoJsonLd['name'] ?? 'Sản phẩm'), 'url' => (string)($_pdSeoJsonLd['url'] ?? '')],
        ]);
    }
}
?>
<!-- Breadcrumb desktop -->
<div class="d-none d-sm-block product-card-header justify-content-between align-items-center mb-3 pb-2 border-bottom">
    <!-- breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <!-- <li class="breadcrumb-item">
                <a href="<?= h($baseUrl) ?>/" class="text-decoration-none text-muted">
                    <i class="bi bi-house-door-fill"></i> Trang chủ
                </a>
            </li> -->
            <li class="breadcrumb-item">
                <a href="<?= h($baseUrl) ?>/shopping" class="text-decoration-none text-muted" id="pBreadcrumbCategory">Mua sắm</a>
            </li>
            <li class="breadcrumb-item active fw-bold" aria-current="page" id="pBreadcrumbName" style="color: var(--theme-primary) !important;">
                Chi tiết
            </li>
        </ol>
    </nav>
</div>
<!-- Stickey manager -->
<?php 
$realIsAdmin = isset($_SESSION['role']) && (string)$_SESSION['role'] === 'admin';
if ($realIsAdmin && $pid > 0): 
?>
    <div class="edit-product-manager__ p-2">
        <a href="<?= h($baseUrl) ?>/admin/product-change?id=<?= (int)$pid ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-pencil-square"></i> Chỉnh sửa
        </a>
        <a href="<?= h($baseUrl) ?>/admin/product" class="btn btn-sm btn-secondary">
            <i class="bi bi-gear"></i> Danh sách
        </a>
    </div>
<?php endif; ?>
<div class="row g-4 justify-content-between align-items-start p-2" id="productMainLayout">
    <!-- CỘT TRÁI (PC) -->
    <div class="col-12 col-lg-6 sticky-column" id="leftColumn">
        <!-- 1. PHẦN GALLERY ẢNH VÀ VIDEO SẢN PHẨM -->
        <div class="gallery-wraps w-100" id="gallerySection">
            <div class="row g-2">
                <!-- Danh sách ảnh/video thu nhỏ -->
                <div class="col-12 col-md-3 col-lg-2 order-2 order-md-1 d-flex flex-column">
                    <div class="gallery-thumb-section">
                        <div class="gallery-thumb-row" id="galleryThumbs">
                            <div class="skeleton-gallery-thumb skeleton-line"></div>
                            <div class="skeleton-gallery-thumb skeleton-line"></div>
                            <div class="skeleton-gallery-thumb skeleton-line"></div>
                            <div class="skeleton-gallery-thumb skeleton-line"></div>
                        </div>
                    </div>
                </div>

                <!-- Ảnh hiển thị đầy đủ -->
                <div class="col-12 col-md-9 col-lg-10 order-1 order-md-2">
                    <div class="gallery-main" id="galleryMain">
                        <div class="skeleton-gallery-main skeleton-line position-absolute w-100 h-100 top-0 start-0" style="z-index: 1;"></div>
                        <button type="button" class="btnCopyProductLink btn btn-sm btn-outline-secondary __btn __btn-sm __btn-outline-secondary" id="btnCopyProductLink" aria-label="Sao chép liên kết sản phẩm"><i class="bi bi-link-45deg"></i></button>
                        <button type="button" id="idBtnFavorite" class="btn-favorite-pro floating-fav" title="Yêu thích">
                            <i class="bi bi-heart"></i>
                            <span id="idFavoriteCount">0</span>
                        </button>
                        <button type="button" class="gallery-zoom-btn" id="btnZoom" aria-label="Phóng to hình ảnh"><i class="bi bi-zoom-in"></i></button>
                        <button type="button" class="gallery-main-arrow gallery-main-prev" id="galleryMainPrev" aria-label="Ảnh trước"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="gallery-main-arrow gallery-main-next" id="galleryMainNext" aria-label="Ảnh sau"><i class="bi bi-chevron-right"></i></button>
                        <a id="galleryMainLink" href="<?= h(!empty($pageImageUrl) ? $pageImageUrl : $fallbackUrl) ?>" data-toggle="lightbox" data-gallery="product-gallery">
                            <img id="galleryMainImage" src="<?= h(!empty($pageImageUrl) ? $pageImageUrl : $fallbackUrl) ?>" class="img-fluid">
                            <video id="galleryMainVideo" preload="metadata" muted playsinline autoplay style="display:none;"></video>
                        </a>
                        <div id="galleryLightboxLinks" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6 & 7. THÔNG TIN CHI TIẾT & THÔNG SỐ KỸ THUẬT -->
        <div class="product-tabs-container mt-3">
            <ul class="nav flex-nowrap overflow-auto border-0" id="productInfoTabsNav" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tabOverviewBtn" data-bs-toggle="tab" data-bs-target="#tabOverviewPane" type="button" role="tab" aria-controls="tabOverviewPane" aria-selected="true">
                        <i class="bi bi-journal-richtext"></i> <span>Thông tin sản phẩm</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tabConstructionBtn" data-bs-toggle="tab" data-bs-target="#tabConstructionPane" type="button" role="tab" aria-controls="tabConstructionPane" aria-selected="false">
                        <i class="bi bi-tools"></i> <span>Hướng dẫn thi công</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tabCoatingBtn" data-bs-toggle="tab" data-bs-target="#tabCoatingPane" type="button" role="tab" aria-controls="tabCoatingPane" aria-selected="false">
                        <i class="bi bi-paint-bucket"></i> <span>Hệ thống Sơn</span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="py-4 mt-0 pt-0" id="productDetailCard">
            <div class="mt-2">
                <div class="tab-content" id="productInfoTabContent">
                    <!-- Thông tin sản phẩm -->
                    <div class="tab-pane fade show active" id="tabOverviewPane" role="tabpanel" aria-labelledby="tabOverviewBtn">
                        <div class="card overview-card" id="overviewCard">
                            <div class="overview-body" id="overviewBody">
                                <article id="pDesc" class="overview-content"><?= isset($pDescSeo) && $pDescSeo !== '' ? $pDescSeo : '—' ?></article>
                            </div>
                            <div class="mt-3 text-center border-top pt-3">
                                <button type="button" class="btn btn-primary btn-sm xxoverview-toggle" id="overviewToggle" aria-expanded="false">Xem thêm</button>
                            </div>
                        </div>
                    </div>

                    <!-- Thi công -->
                    <div class="tab-pane fade" id="tabConstructionPane" role="tabpanel" aria-labelledby="tabConstructionBtn">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <tbody>
                                    <tr>
                                        <th class="bg-light" style="width: 200px;">Dụng cụ</th>
                                        <td id="tabConsTools">—</td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Chuẩn bị bề mặt</th>
                                        <td id="tabConsSurface">—</td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Quy trình thi công</th>
                                        <td id="tabConsMethod">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Hệ thống sơn -->
                    <div class="tab-pane fade" id="tabCoatingPane" role="tabpanel" aria-labelledby="tabCoatingBtn">
                        <div id="tabCoatingContent" class="py-3 border rounded bg-light px-3">Chưa có thông tin hệ thống sơn.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Câu hỏi thường gặp -->
        <div class="py-4 mt-2 d-none" id="faqCard">
            <div class="mb-4">
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center">
                    <i class="bi bi-patch-question text-primary me-2"></i>
                    <span id="faqTitle">Câu hỏi thường gặp</span>
                </h5>
            </div>
            <div class="faq-simple-list">
                <div class="faq-simple-item">
                    <div class="faq-q fw-bold">
                        <i class="bi bi-question-circle"></i>
                        Sản phẩm này phù hợp với bề mặt nào?
                    </div>
                    <div class="faq-a">
                        Phù hợp cho các bề mặt đúng theo hướng dẫn kỹ thuật và có thể thi công nội/ngoại thất tùy dòng sản phẩm.
                    </div>
                </div>

                <div class="faq-simple-item">
                    <div class="faq-q fw-bold">
                        <i class="bi bi-question-circle"></i>
                        Định mức sử dụng trung bình là bao nhiêu?
                    </div>
                    <div class="faq-a">
                        Định mức sẽ thay đổi theo độ hút của bề mặt, phương pháp thi công và số lớp sơn.
                    </div>
                </div>

                <div class="faq-simple-item">
                    <div class="faq-q fw-bold">
                        <i class="bi bi-question-circle"></i>
                        Có thể hỗ trợ giao hàng tận nơi không?
                    </div>
                    <div class="faq-a">
                        Có. Chúng tôi hỗ trợ giao hàng tận nơi trên toàn quốc, phí vận chuyển sẽ được tính dựa trên khối lượng và địa điểm nhận hàng.
                    </div>
                </div>

                <div class="faq-simple-item">
                    <div class="faq-q fw-bold">
                        <i class="bi bi-question-circle"></i>
                        Thời gian giao hàng và đổi trả như thế nào?
                    </div>
                    <div class="faq-a">
                        Đơn hàng được xử lý nhanh theo khu vực; hỗ trợ đổi trả theo chính sách nếu phát sinh lỗi từ nhà sản xuất.
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="<?= h($baseUrl) ?>/support/faq" class="text-primary fw-bold small text-decoration-none">
                    Xem tất cả câu hỏi <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <!-- end Câu hỏi thường gặp -->
    </div>
    <!-- CỘT PHẢI (PC) -->
    <div class="col-12 col-lg-6" id="rightColumn">
        <!-- 2. THÔNG TIN SẢN PHẨM (order-2) -->
        <div class="product-info-stack w-100" id="productInfoSection">
            <div class="product-meta-compact d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <h1 class="product-title shopping flex-grow-1" id="pName"><?= h($pNameSeo ?: 'Sản phẩm') ?></h1>
            </div>
            <!-- ĐÁNH GIÁ, LƯỢT MUA, SKU -->
            <div class="rating-group">
                <span class="rating-chip" id="pRating"><i class="bi bi-star-fill text-warning"></i>Đánh giá</span>
                <span role="button" class="rating-chip rating-chip-favorite" id="btnFavoriteChip" title="Yêu thích">
                    <i class="bi bi-heart fav-heart-icon"></i>
                    <span>Yêu thích</span>
                    <span id="idFavoriteCountChip">0</span>
                </span>
                <!--span class="rating-chip " id="pSold"><i class="bi bi-bag-check"></i>Lượt mua</span-->
                <span role="button" class="rating-chip text-success" id="pSku" aria-label="SKU"></span>

            </div>
            <!-- GIÁ BÁN -->
            <div class="detail-block">
                <div class="price-box">
                    <div id="skeletonPrice" class="skeleton-price skeleton-line"></div>
                    <div class="price-current" id="priceTotal" style="display:none;">—</div>
                    <div class="price-original" id="priceOriginal" style="display:none;">—</div>
                    <p class="price-save d-none" id="priceSave" style="display:none;font-size:13px">—</p>
                </div>
                <div class="text-muted small" id="vatNote" style="margin-top:2px;display:none;">*Giá chưa bao gồm VAT (<span id="vatPercentNote"></span>%)*</div>
            </div>

            <!-- THÔNG TIN VẬN CHUYỂN -->
            <div class="product-ship-box __d-none" id="btnShippingInfo">
                <div class="product-ship-eta-row">
                    <span class="product-ship-eta" id="pShipEtaText">Nhận từ --/-- - --/--</span>
                    <span class="product-ship-arrow"><i class="bi bi-chevron-right"></i></span>
                </div>
                <div class="product-ship-fee-line" id="pShipFeeText"> Phí ship 0đ</div>
            </div>

            <!-- THƯƠNG HIỆU & XUẤT XỨ -->
            <!-- <div class="detail-block">
                <div class="product-info-row">
                    <div class="product-info-label">HÃNG SẢN XUẤT</div>
                    <div class="product-info-value" id="pBrand">—</div>
                </div>
                <div class="product-info-row _d-none">
                    <div class="product-info-label">KHỐI LƯỢNG</div>
                    <div class="product-info-value" id="pWeight">—</div>
                </div>
            </div> -->

            <!-- VOUCHER -->
            <div class="detail-block" id="voucherSection">
                <div class="voucher-group-title">Mã ưu đãi</div>
                <div id="voucherList" class="voucher-badge-list"></div>
                <div class="voucher-note" id="voucherNote">Chọn mã để xem giá sau giảm.</div>
            </div>

            <!-- PROMO SECTIONS (đặt sau ƯU ĐÃI, ngay trên PHÂN LOẠI) -->
            <div class="promo-deal-block w-100" id="promoSection">
                <!-- BY ONE GET ONE FREE -->
                <div class="promo-deal-blocks w-100" id="promoBxgySection" style="display:none;">
                    <div class="promo-deal-body">
                        <div class="promo-deal-title" id="promoBxgyTitle"></div>
                        <div class="promo-deal-grid" id="promoBxgyGrid"></div>
                    </div>
                </div>
                <!-- COMBO -->
                <div class="promo-deal-blocks w-100" id="promoComboSection" style="display:none;">
                    <div class="promo-deal-body">
                        <div class="promo-deal-title" id="promoComboTitle"></div>
                        <div class="promo-deal-grid" id="promoComboGrid"></div>
                        <div class="promo-deal-action">
                            <button type="button" class="btn btn-sm btn-primary" id="btnBuyCombo"><i class="bi bi-bag-plus me-1"></i>Mua kèm deal sốc</button>
                        </div>
                    </div>
                </div>
                <!-- GIFT -->
                <div class="promo-deal-blocks w-100" id="promoGiftSection" style="display:none;">
                    <div class="promo-deal-body">
                        <div class="promo-deal-title" id="promoGiftTitle"></div>
                        <div class="promo-deal-grid" id="promoGiftGrid"></div>
                        <div class="promo-deal-action">
                            <button type="button" class="btn btn-sm btn-primary" id="btnBuyGift"><i class="bi bi-bag-plus me-1"></i>Mua kèm quà tặng</button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- PHÂN LOẠI & BIẾN THỂ SẢN PHẨM -->
            <div class="detail-block" id="variantSection" style="display:none;">
                <div class="detail-label">PHÂN LOẠI</div>
                <div id="variantWrap"></div>
            </div>

            <!-- SỐ LƯỢNG + KHO -->
            <div class="detail-block">
                <div class="action-qty d-flex align-items-center gap-3 flex-wrap">
                    <div class="detail-label">SỐ LƯỢNG</div>
                    <div class="cart-stepper" data-key="detail">
                        <button type="button" class="stepper-btn stepper-minus" data-step="-1">-</button>
                        <input id="qty" type="number" min="1" class="stepper-input" value="1">
                        <button type="button" class="stepper-btn stepper-plus" data-step="1">+</button>
                    </div>
                    <span class="stock-status text-muted" id="stockStatus">Đang cập nhật</span>
                </div>
            </div>

            <!-- NÚT THÊM VÀO GIỎ HÀNG / Mua ngay -->
            <div class="action-row">
                <button class="btn btn-lg btn-outline-primary" id="btnAdd"><i class="bi bi-cart-plus me-1"></i> Thêm giỏ hàng</button>
                <button class="btn btn-lg btn-primary" id="btnBuy"><i class="bi bi-bag-check me-1"></i> Mua ngay</button>
            </div>
            <div id="preorderNote" class="d-none small text-warning-emphasis bg-warning-subtle border border-warning-subtle rounded-2 px-3 py-2 mt-2">
                <i class="bi bi-clock-history me-1"></i> Sản phẩm đặt trước — đơn sẽ được giao khi có hàng.
            </div>
            <hr>
        </div>

        <!-- 7. THÔNG SỐ KỸ THUẬT CARD -->
        <div class="spec-card w-100 mt-4" id="specSection">
            <h6 class="fw-bold"><i class="bi bi-box-seam me-1"></i> Thông số kỹ thuật</h6>
            <div class="card-body p-0">
                <div class="table-responsive rounded-3 overflow-hidden border">
                    <table class="table table-sm align-middle mb-0 spec-table">
                        <tbody>
                            <tr class="border-bottom">
                                <td class="bg-light fw-bold text-muted small ps-3" style="width: 35%; border-right: 1px solid #eee;">Thương hiệu</td>
                                <td class="small ps-3 py-2" id="specBrand">—</td>
                            </tr>
                            <tr class="border-bottom">
                                <td class="bg-light fw-bold text-muted small ps-3" style="border-right: 1px solid #eee;">Xuất xứ</td>
                                <td class="small ps-3 py-2" id="specOrigin">—</td>
                            </tr>
                            <!-- <tr class="border-bottom">
                                <td class="bg-light fw-bold text-muted small ps-3" style="border-right: 1px solid #eee;">Loại nhựa</td>
                                <td class="small ps-3 py-2" id="specResin">—</td>
                            </tr>
                            <tr class="border-bottom">
                                <td class="bg-light fw-bold text-muted small ps-3" style="border-right: 1px solid #eee;">Độ phủ</td>
                                <td class="small ps-3 py-2" id="specCoverage">—</td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-bold text-muted small ps-3" style="border-right: 1px solid #eee;">Bề mặt</td>
                                <td class="small ps-3 py-2" id="specGloss">—</td>
                            </tr> -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2 fw-bold" id="btnShowFullSpec" style="font-size: 0.75rem;">
                        Xem chi tiết <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- 9. GỢI Ý SẢN PHẨM LIÊN QUAN -->
        <div class="related-products-card w-100 mt-4" id="relatedProductsSection">
            <div class="card p-1 border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-lightbulb me-2 text-primary"></i>Gợi ý sản phẩm liên quan</h6>
                    <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none fw-bold" id="btnReloadRelated">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                    </button>
                </div>
                <div class="list-group list-group-flush" id="relatedProductsList">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="list-group-item d-flex gap-2 py-3">
                            <div class="skeleton-line" style="width:50px;height:50px;border-radius:6px;flex-shrink:0;"></div>
                            <div class="flex-grow-1">
                                <div class="skeleton-line w-100 mb-2" style="height:12px;"></div>
                                <div class="skeleton-line w-50" style="height:12px;"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="card-footer bg-white border-top py-3">
                    <a href="<?= h($baseUrl) ?>/shopping" class="btn btn-sm btn-outline-primary w-100">Xem tất cả sản phẩm</a>
                </div>
            </div>
        </div>

        <!-- 10. TIN TỨC MỚI NHẤT -->
        <div class="latest-news-card w-100 mt-4" id="latestNewsSection">
            <div class="card p-1 border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-newspaper me-2 text-primary"></i>Tin tức mới nhất</h6>
                    <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none fw-bold" id="btnReloadNews">
                        <i class="bi bi-arrow-clockwise me-1"></i> LÀM MỚI
                    </button>
                </div>
                <div class="list-group list-group-flush" id="latestNewsList">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="list-group-item d-flex gap-2 py-3">
                            <div class="skeleton-line" style="width:60px;height:45px;border-radius:6px;flex-shrink:0;"></div>
                            <div class="flex-grow-1">
                                <div class="skeleton-line w-100 mb-2" style="height:12px;"></div>
                                <div class="skeleton-line w-50" style="height:12px;"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="card-footer bg-white border-top py-3">
                    <a href="<?= h($baseUrl) ?>/blog" class="btn btn-sm btn-outline-primary w-100">Xem tất cả tin tức</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PHẦN ĐÁNH GIÁ & GỢI Ý (FULL WIDTH) -->
<div class="row g-4 mt-2 px-2" id="bottomLayout">
    <div class="col-12" id="bottomSections">
        <!-- 8. ĐÁNH GIÁ SẢN PHẨM -->
        <div class="product-comment w-100 mt-4" id="reviewSection">
            <div class="card__ mb-3" id="blockRating">
                <div class="card-header bg-white mb-3">
                    <h5 class="fw-bold mb-0">ĐÁNH GIÁ SẢN PHẨM</h5>
                </div>
                <div class="card-body p-0">
                    <div class="row g-4 align-items-center">
                        <!-- Summary -->
                        <div class="col-12 col-md-4 text-center">
                            <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                                <div class="text-primary display-4 fw-bold mb-0"><span id="reviewAvgValue">0.0</span></div>
                                <div class="text-start">
                                    <div class="text-warning h4 mb-0">
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                    <div class="text-muted small" id="reviewTotalCount">0 đánh giá</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold w-100 w-md-auto shadow-sm" id="btnOpenReview">
                                <i class="bi bi-pencil-square me-2"></i>Viết đánh giá
                            </button>
                        </div>
                        <!-- Progress Bars -->
                        <div class="col-12 col-md-8">
                            <div class="rating-chart px-lg-4" id="reviewChart">
                                <!-- JS will render rows here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="review-filters mb-4 d-flex flex-wrap gap-2 scroll-x-mobile" id="reviewFilters">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill active fw-bold" data-rating="0" data-media="0">Tất cả</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-rating="5" data-media="0">5 Sao (0)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-rating="4" data-media="0">4 Sao (0)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-rating="3" data-media="0">3 Sao (0)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-rating="2" data-media="0">2 Sao (0)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-rating="1" data-media="0">1 Sao (0)</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-rating="0" data-media="1">Có ảnh/Video (0)</button>
            </div>
            <div class="comments-list list" id="reviewList">
                <div class="skeleton-card skeleton-line-wrap p-3 mb-3">
                    <div class="skeleton-avatar skeleton-line"></div>
                    <div class="flex-grow-1">
                        <div class="skeleton-line w-25 mb-2"></div>
                        <div class="skeleton-line w-75 mb-2"></div>
                        <div class="skeleton-line w-50"></div>
                    </div>
                </div>
            </div>
            <div class="review-empty text-center py-4" id="reviewEmpty" style="display:none;">
                <div class="mb-3">
                    <img src="<?= h($baseUrl) ?>/image/character_ratings.png?v=1" alt="Ratings Mascot" style="max-width: 240px; height: auto;">
                </div>
                <div class="text-muted mb-3">
                    <div class="fw-semibold text-dark mb-1" style="font-size: 14px; color: #334155;">Hiện chưa có đánh giá nào.</div>
                    <div style="font-size: 13px; color: #64748b;">Bạn sẽ là người đầu tiên đánh giá sản phẩm này chứ?</div>
                </div>
                <div>
                    <button type="button" class="btn text-white px-4 py-2 fw-semibold btn-write-review-now" style="background-color: var(--theme-primary, #0c4c29); border-color: var(--theme-primary, #0c4c29); border-radius: 8px; font-size: 14px;">Đánh giá ngay</button>
                </div>
            </div>
        </div>

        <div class="comments" id="blockQA">
            <div class="comments__title mb-3 mt-3"><i class="bi bi-question-circle me-2"></i>HỎI VÀ ĐÁP</div>
            <div class="qa-grid-card p-4 my-3 d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4">
                <!-- Mascot Image -->
                <div class="qa-grid-mascot flex-shrink-0">
                    <img src="<?= h($baseUrl) ?>/image/character_rating.png?v=2" alt="Mascot">
                </div>

                <!-- Info & Action -->
                <div class="qa-grid-content flex-grow-1 w-100">
                    <h5 class="fw-bold text-dark mb-2">Hãy đặt câu hỏi cho chúng tôi</h5>
                    <p class="text-muted small mb-3" style="line-height: 1.6;">
                        Paint&More sẽ phản hồi trong vòng 1 giờ. Nếu Quý khách gửi câu hỏi sau 22h, chúng tôi sẽ trả lời vào sáng hôm sau.<br>
                        Thông tin có thể thay đổi theo thời gian, vui lòng đặt câu hỏi để nhận được cập nhật mới nhất!
                    </p>

                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
                        <div class="flex-grow-1 qa-grid-input-bar" id="btnOpenQAInput">
                            Viết câu hỏi của bạn tại đây
                        </div>
                        <button type="button" class="btn qa-grid-btn px-4" id="btnOpenQA">
                            Gửi câu hỏi <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="comments-list list" id="qaList"></div>
            <div class="review-empty text-center py-4" id="qaEmpty" style="display:none;">
                <i class="bi bi-question-circle" style="font-size:2.2rem; color:#94a3b8;"></i>
                <div class="fw-semibold text-dark mt-2 mb-1" style="font-size: 14px;">Chưa có câu hỏi nào.</div>
                <div style="font-size: 13px; color: #64748b;">Bạn có thể là người đầu tiên đặt câu hỏi cho sản phẩm này!</div>
            </div>
        </div>
    </div>

</div>
<!-- end Product -->


<!-- Chọn quà -->
<div id="bxgyGiftDropdownDetail" class="variant-dropdown d-none">
    <div id="bxgyGiftDropdownDetailBody" class="variant-dropdown-body small text-muted px-3 py-2">
        <div class="skeleton-line w-75 mb-2" style="height:10px;"></div>
        <div class="skeleton-line w-50" style="height:10px;"></div>
    </div>
</div>

<!-- Vận chuyển -->
<div class="modal fade" id="shippingInfoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Thông tin vận chuyển</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Info View: Shows Destination and Shipping Methods -->
            <div id="shipModalInfoView" class="modal-body d-flex flex-column gap-3">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="fw-bold">Điểm nhận hàng</div>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btnUpdateShippingAddress" style="font-size: 0.8rem; border-radius: 6px;">
                            <i class="bi bi-pencil-square me-1"></i>Cập nhật
                        </button>
                    </div>
                    <div class="ship-destination text-muted p-2 rounded bg-light" id="shipModalDestination" style="font-size: 0.9rem; line-height: 1.4;">Chưa thiết lập địa chỉ giao hàng</div>
                </div>
                <div>
                    <div class="fw-bold mb-2">Phương thức</div>
                    <div class="ship-method-list" id="shipModalMethodList"></div>
                </div>
            </div>

            <!-- Edit View: Address Form -->
            <div id="shipModalEditView" class="modal-body d-none">
                <div class="fw-bold mb-3"><i class="bi bi-geo-alt me-2"></i>Cập nhật địa chỉ nhận hàng</div>
                <form id="shipModalAddressForm" class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Họ tên người nhận</label>
                        <input name="recipient_name" id="shipFormRecipientName" class="form-control form-control-sm" placeholder="Nhập họ tên" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Số điện thoại</label>
                        <input name="contact_phone" id="shipFormContactPhone" class="form-control form-control-sm" placeholder="Nhập số điện thoại" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Tỉnh / Thành phố</label>
                        <select id="shipFormProvince" class="form-select form-select-sm" required>
                            <option value="">-- Chọn tỉnh/thành --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Quận / Huyện</label>
                        <select id="shipFormDistrict" class="form-select form-select-sm" disabled required>
                            <option value="">-- Chọn quận/huyện --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Phường / Xã</label>
                        <select id="shipFormWard" class="form-select form-select-sm" disabled required>
                            <option value="">-- Chọn phường/xã --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Địa chỉ chi tiết</label>
                        <input name="address_detail" id="shipFormAddressDetail" class="form-control form-control-sm" placeholder="Số nhà, tên đường..." required>
                    </div>
                    <div class="col-12 mt-3 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-sm btn-secondary px-3" id="btnCancelUpdateAddress" style="border-radius: 8px;">Hủy</button>
                        <button type="submit" class="btn btn-sm btn-primary px-3" id="btnSaveShippingAddress" style="border-radius: 8px;">Lưu địa chỉ</button>
                    </div>
                </form>
            </div>

            <div class="modal-footer" id="shipModalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<style>
    /* Custom styling for specModal on desktop/PC */
    @media (min-width: 992px) {
        #specModal.modal {
            padding-right: 0 !important;
            overflow: hidden;
        }

        #specModal .modal-dialog {
            position: fixed;
            margin: 0;
            top: 0;
            right: 0;
            bottom: 0;
            height: 100vh;
            max-height: 100vh;
            width: 500px;
            max-width: 100%;
            transform: translate3d(100%, 0, 0);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        #specModal.show .modal-dialog {
            transform: translate3d(0, 0, 0) !important;
        }

        #specModal .modal-content {
            height: 100%;
            border-radius: 0;
            border: none;
            border-left: 1px solid var(--bs-border-color);
            box-shadow: -10px 0 30px rgba(15, 23, 42, 0.08);
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }

        #specModal .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }

        #specModal .modal-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--theme-primary);
            font-size: 1.1rem;
        }

        #specModal .modal-body {
            overflow-y: auto;
            padding: 1.5rem;
            flex-grow: 1;
        }

        #specModal .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.5rem;
            background: #f8fafc;
        }

        #specModal .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(11, 75, 40, 0.15);
        }
    }

    /* Elegant Table and Badge styles for specModal (both PC & mobile) */
    #specModal .table {
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    #specModal .table tr th {
        background-color: #f8fafc !important;
        color: #475569;
        font-weight: 600;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        border-right: 1px solid rgba(0, 0, 0, 0.05);
        font-size: 0.85rem;
        padding: 14px 16px;
        width: 32%;
    }

    #specModal .table tr td {
        color: #1e293b;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-size: 0.85rem;
        padding: 14px 16px;
        line-height: 1.5;
    }

    #specModal .table tr:last-child th,
    #specModal .table tr:last-child td {
        border-bottom: none;
        border-right: none;
    }

    #specModal .table tr th:last-child,
    #specModal .table tr td:last-child {
        border-right: none;
    }

    /* Feature badges inside specModal */
    #specModal .feature-badge-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    #specModal .feature-badge {
        background-color: rgba(12, 76, 41, 0.08);
        color: var(--theme-primary);
        border: 1px solid rgba(12, 76, 41, 0.15);
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 500;
        display: inline-block;
    }

    /* System-wide modern glassmorphism backdrop */
    .modal-backdrop.show {
        backdrop-filter: blur(4px);
        background-color: rgba(15, 23, 42, 0.3) !important;
    }

    /* Q&A Suggestion Chips styling */
    .qa-suggest-chip {
        font-size: 0.78rem;
        border-radius: 20px;
        font-weight: 500;
        border: 1px solid #cbd5e1;
        color: #475569;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .qa-suggest-chip:hover {
        border-color: var(--theme-primary, #0c4c29);
        color: var(--theme-primary, #0c4c29);
        background: var(--theme-primary-soft, rgba(12, 76, 41, 0.08));
    }

    /* Review Suggestion Chips styling */
    .review-suggest-chip {
        font-size: 0.78rem;
        border-radius: 20px;
        font-weight: 500;
        border: 1px solid #cbd5e1;
        color: #475569;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .review-suggest-chip:hover {
        border-color: var(--theme-primary, #0c4c29);
        color: var(--theme-primary, #0c4c29);
        background: var(--theme-primary-soft, rgba(12, 76, 41, 0.08));
    }

    .review-suggest-chip.active {
        border-color: var(--theme-primary, #0c4c29) !important;
        color: #ffffff !important;
        background: var(--theme-primary, #0c4c29) !important;
    }
</style>

<!-- Thông số kỹ thuật chi tiết -->
<div class="modal fade" id="specModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cpu me-2"></i>Thông số kỹ thuật chi tiết</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <tbody>
                            <tr>
                                <th class="bg-light" style="width: 200px;">Tên sản phẩm</th>
                                <td id="specName">—</td>
                            </tr>
                            <tr>
                                <th class="bg-light">SKU</th>
                                <td id="specSku">—</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Giá cơ bản</th>
                                <td id="specPrice">—</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Đặc tính nổi bật</th>
                                <td id="pDacTinh">—</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Thông số nhanh</th>
                                <td id="specThongSo">—</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Ứng dụng</th>
                                <td id="pUngDung">—</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Bảo quản</th>
                                <td id="specBaoQuan">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Đánh giá sản phẩm -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-square-text me-2"></i>Viết đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="comments-add logged show" id="commentsActions">
                    <input id="reviewParentId" type="hidden" value="0">
                    <input id="reviewRating" type="hidden" value="0">
                    <input id="reviewEditId" type="hidden" value="0">

                    <div class="comments-add__top">
                        <p class="notes-rep" id="reviewReplyNote" style="display:none;">Đang trả lời bình luận</p>
                    </div>

                    <div class="comments-add__rate">
                        <div class="comments-rate" id="reviewRateStars">
                            <button type="button" class="comments-rate-star" data-rate="1"><i class="bi bi-star-fill"></i>Rất tệ</button>
                            <button type="button" class="comments-rate-star" data-rate="2"><i class="bi bi-star-fill"></i>Tệ</button>
                            <button type="button" class="comments-rate-star" data-rate="3"><i class="bi bi-star-fill"></i>Bình thường</button>
                            <button type="button" class="comments-rate-star" data-rate="4"><i class="bi bi-star-fill"></i>Tốt</button>
                            <button type="button" class="comments-rate-star" data-rate="5"><i class="bi bi-star-fill"></i>Rất tốt</button>
                        </div>
                    </div>
                    <div class="comments-add__form">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <div class="row g-2 mb-2">
                                <div class="col-12 col-md-5">
                                    <input type="text" class="form-control" id="reviewGuestName" placeholder="Tên của bạn" maxlength="100">
                                </div>
                                <div class="col-12 col-md-4">
                                    <input type="text" class="form-control" id="reviewGuestPhone" placeholder="SĐT (tuỳ chọn)" maxlength="30">
                                </div>
                                <div class="col-12 col-md-3">
                                    <input type="email" class="form-control" id="reviewGuestEmail" placeholder="Email (tuỳ chọn)" maxlength="120">
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small">Bạn đang bình luận với tư cách khách. Hệ thống sẽ lưu kèm cookie để nhận diện lượt thích.</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="qa-suggestions-wrap mb-3" id="qaSuggestions" style="display:none;">
                            <div class="small text-muted mb-2" style="font-size: 0.8rem;"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Gợi ý câu hỏi thường gặp:</div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm qa-suggest-chip" data-text="Sản phẩm này có bắt buộc phải sử dụng sơn lót trước khi thi công không?" style="font-size: 0.78rem; border-radius: 20px; font-weight: 500; border: 1px solid #cbd5e1; color: #475569; background: #f8fafc; transition: all 0.2s;">Có cần sơn lót không?</button>
                                <button type="button" class="btn btn-sm qa-suggest-chip" data-text="Một hộp/lon sản phẩm này định mức sơn phủ được khoảng bao nhiêu m² bề mặt?" style="font-size: 0.78rem; border-radius: 20px; font-weight: 500; border: 1px solid #cbd5e1; color: #475569; background: #f8fafc; transition: all 0.2s;">Độ phủ bao nhiêu m²?</button>
                                <button type="button" class="btn btn-sm qa-suggest-chip" data-text="Thời gian khô bề mặt và khô hoàn toàn của loại sơn này là lâu không?" style="font-size: 0.78rem; border-radius: 20px; font-weight: 500; border: 1px solid #cbd5e1; color: #475569; background: #f8fafc; transition: all 0.2s;">Bao lâu thì khô?</button>
                                <button type="button" class="btn btn-sm qa-suggest-chip" data-text="Shop có hỗ trợ giao hàng nhanh hỏa tốc trong ngày không?" style="font-size: 0.78rem; border-radius: 20px; font-weight: 500; border: 1px solid #cbd5e1; color: #475569; background: #f8fafc; transition: all 0.2s;">Có giao nhanh không?</button>
                                <button type="button" class="btn btn-sm qa-suggest-chip" data-text="Quy trình chuẩn bị bề mặt trước khi thi công sản phẩm này như thế nào để đạt hiệu quả tốt nhất?" style="font-size: 0.78rem; border-radius: 20px; font-weight: 500; border: 1px solid #cbd5e1; color: #475569; background: #f8fafc; transition: all 0.2s;">Chuẩn bị bề mặt thế nào?</button>
                            </div>
                        </div>
                        <div class="review-suggestions-wrap mb-3" id="reviewSuggestions" style="display:none;">
                            <div class="small text-muted mb-2" style="font-size: 0.8rem;"><i class="bi bi-tag-fill text-success me-1"></i>Đề xuất gợi ý đánh giá (chọn để thêm tag):</div>
                            <div class="d-flex flex-wrap gap-2" id="reviewSuggestChips">
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Hoạt động Tuyệt vời">Hoạt động Tuyệt vời</button>
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Độ bền Cực bền">Độ bền Cực bền</button>
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Giao hàng nhanh">Giao hàng nhanh</button>
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Đóng gói cẩn thận">Đóng gói cẩn thận</button>
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Chất lượng tuyệt vời">Chất lượng tuyệt vời</button>
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Màu sắc cực đẹp">Màu sắc cực đẹp</button>
                                <button type="button" class="btn btn-sm review-suggest-chip" data-tag="Dễ thi công">Dễ thi công</button>
                            </div>
                        </div>

                        <textarea class="comments-add__form-field" id="reviewContent" placeholder="Hãy để lại bình luận hoặc câu hỏi của bạn tại đây!"></textarea>
                        <div class="review-media-tools">
                            <button type="button" class="review-media-trigger" id="reviewMediaBtn"><i class="bi bi-paperclip"></i>Thêm ảnh/video</button>
                            <span class="review-media-hint">Tối đa 6 ảnh hoặc video</span>
                        </div>
                        <div class="review-media-preview" id="reviewMediaPreview"></div>
                        <input class="d-none" id="reviewMediaInput" type="file" accept="image/*,video/mp4,video/webm,video/quicktime" multiple>
                        <!-- <div class="text-muted small mt-2"><?php if (isset($_SESSION['user_id'])): ?>Hệ thống sẽ dùng tên + SĐT từ tài khoản của bạn.<?php else: ?>Bạn có thể nhập tên/SĐT ở phía trên.<?php endif; ?></div> -->
                    </div>
                    <div class="comments-add__action mt-2">
                        <button type="button" class="button__cmt-send" id="btnSendReview"><i class="bi bi-send"></i>Gửi đánh giá</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Product Footer -->
<div class="product-footer" id="productFooter">
    <div class="product-footer-inner container">
        <!-- Left Section: Thumbnail + Name -->
        <div class="product-footer-meta">
            <div class="product-footer-thumb">
                <?php
                $pfFallbackPath = $site_fallback_logo ?: '';
                $pfFallbackUrl = rtrim((string)$baseUrl, '/') . '/' . ltrim((string)$pfFallbackPath, '/');
                ?>
                <img id="pfThumb" src="<?= h($pfFallbackUrl) ?>" alt="Sản phẩm" loading="lazy" decoding="async" onerror="this.src='<?= h($pfFallbackUrl) ?>';">
            </div>
            <div class="product-footer-text">
                <div class="product-footer-name" id="pfName">Sản phẩm</div>
                <div class="product-footer-meta-line" id="pfMeta"></div>
            </div>
        </div>

        <!-- Right Section: Price Box + Buttons -->
        <div class="product-footer-right">
            <!-- Price Box -->
            <div class="product-footer-price-box">
                <div class="product-footer-price" id="pfPrice">0 đ</div>
                <div class="product-footer-price-original" id="pfPriceOriginal" style="display: none;">0 đ</div>
            </div>

            <!-- Buttons -->
            <div class="product-footer-buttons">
                <button class="btn btn-buy-now" id="pfBuy" type="button">Mua Ngay</button>
                <button class="btn btn-add-cart" id="pfAdd" type="button" aria-label="Thêm vào giỏ hàng">
                    <i class="bi bi-cart-plus"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        // Hỗ trợ Lightbox cho nội dung động (Đánh giá, Gallery, v.v.) và đồng bộ thời gian phát video
        let lastMainVideoTime = 0;
        let wasMainVideoPlaying = false;

        $(document).on('click', '[data-toggle="lightbox"]', function(e) {
            if (e.isDefaultPrevented()) return;
            e.preventDefault();

            // Nếu click trên link gallery chính và video đang chạy, lưu lại timestamp
            if (this.id === 'galleryMainLink') {
                const mainVideo = document.getElementById('galleryMainVideo');
                if (mainVideo && !mainVideo.paused && $(mainVideo).is(':visible')) {
                    lastMainVideoTime = mainVideo.currentTime;
                    wasMainVideoPlaying = true;
                    mainVideo.pause();
                } else {
                    lastMainVideoTime = 0;
                    wasMainVideoPlaying = false;
                }
            } else {
                lastMainVideoTime = 0;
                wasMainVideoPlaying = false;
            }

            const LightboxClass = (window.bootstrap && window.bootstrap.Lightbox) || window.Lightbox;
            if (LightboxClass) {
                new LightboxClass(this).show();
            }
        });

        // Đồng bộ thời gian phát khi mở lightbox
        $(document).on('show.bs.modal shown.bs.modal', '.lightbox-modal', function() {
            if (lastMainVideoTime > 0 && wasMainVideoPlaying) {
                const activeVideo = $(this).find('.carousel-item.active video').get(0);
                if (activeVideo) {
                    activeVideo.currentTime = lastMainVideoTime;
                }
            }
        });

        $(document).on('loadedmetadata', '.lightbox-modal video', function() {
            if (lastMainVideoTime > 0 && wasMainVideoPlaying) {
                this.currentTime = lastMainVideoTime;
            }
        });

        // Đồng bộ ngược lại thời gian phát khi đóng lightbox
        $(document).on('hide.bs.modal', '.lightbox-modal', function() {
            const activeVideoInModal = $(this).find('.carousel-item.active video').get(0);
            const mainVideo = document.getElementById('galleryMainVideo');
            if (activeVideoInModal && mainVideo && $(mainVideo).is(':visible')) {
                const lightboxTime = activeVideoInModal.currentTime;
                const lightboxPaused = activeVideoInModal.paused;

                mainVideo.currentTime = lightboxTime;
                if (!lightboxPaused) {
                    setTimeout(() => {
                        mainVideo.play().catch(() => {});
                    }, 50);
                }
            }
        });
        const PID = <?= (int) $pid ?>;
        const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
        const COMMENT_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/comment.php';
        const REVIEW_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/review.php';
        const VOUCHER_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
        const SHIPPING_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/shipping.php';
        const REGION_API = '<?= h($baseUrl) ?>/main/account/region-session.php';
        const FAVORITE_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/favorite.php';
        const BASE_URL = '<?= h($baseUrl) ?>';
        const SITE_HOTLINE = '<?= h($site_hotline ?? '') ?>';
        const FALLBACK_IMG = '<?= $site_fallback_logo ? h(to_abs_url((string)$site_fallback_logo, (string)$baseUrl)) : '' ?>';
        const CART_API = API;
        const SEARCH_Q = <?= json_encode($searchQ, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const REQUIRE_LOGIN_FOR_PURCHASE = <?= $requireLoginForPurchase ? 'true' : 'false' ?>;
        const LOGIN_URL = '<?= h($baseUrl) ?>/login';
        const CURRENT_USER_ID = <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0 ?>;
        const IS_ADMIN = <?= !empty($isAdmin) ? 'true' : 'false' ?>;
        const VAT_DEFAULT = <?= json_encode(isset($vatDefault) ? (float)$vatDefault : 0.0) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
        // Gắn CSRF token vào mọi $.ajax request (header)
        $.ajaxSetup({
            headers: {
                'X-CSRF-Token': CSRF_TOKEN
            }
        });
        const setProductText = (sel, val) => {
            const v = String(val || '').trim();
            $(sel).text(v || '—');
        };
        const renderFeatureBadges = (sel, val) => {
            const v = String(val || '').trim();
            if (!v || v === '—' || v === '-') {
                $(sel).html('—');
                return;
            }
            let parts = v.split(/[\n;]/).map(s => s.trim()).filter(Boolean);
            if (parts.length === 1 && parts[0].includes(' - ')) {
                parts = parts[0].split(' - ').map(s => s.trim()).filter(Boolean);
            }
            const cleanParts = parts.map(p => p.replace(/^[-\u2022]\s*/, '').trim()).filter(Boolean);
            if (cleanParts.length === 0) {
                $(sel).html('—');
                return;
            }
            const html = cleanParts.map(p => `<span class="feature-badge">${esc(p)}</span>`).join('');
            $(sel).html(`<div class="feature-badge-container">${html}</div>`);
        };
        const formatWeight = (value, unit) => {
            let v = parseFloat(value) || 0;
            if (v <= 0) return '';
            let u = (unit || 'kg').toLowerCase().trim();
            const unitMap = {
                'kg': 'Kg',
                'l': 'L',
                'gr': 'g',
                'gram': 'g',
                'ml': 'ml',
            };
            if (v >= 1000) {
                if (u === 'gr' || u === 'gram' || u === 'g') {
                    v /= 1000;
                    u = 'kg';
                } else if (u === 'ml') {
                    v /= 1000;
                    u = 'l';
                }
            }
            const displayUnit = unitMap[u] || u;
            let formattedValue = parseFloat(v.toFixed(3));

            return formattedValue + ' ' + displayUnit;
        };
        const formatVariantWeight = (v) => {
            if (!v) return '';
            return formatWeight(v.shipping_weight_value || v.weight_value, v.shipping_weight_unit || v.weight_unit);
        };
        let PRODUCT_SEO_URL = '';
        const buildProductSeoUrl = (prod) => {
            const id = Number(PID || 0);
            if (!id) return '';
            const base = String(BASE_URL || '').replace(/\/$/, '');
            let raw = '';
            if (prod && typeof prod === 'object') {
                raw = String(prod.slug || '').trim();
                if (!raw && prod.product_name) raw = String(prod.product_name).trim();
            }
            if (!raw) raw = 'san pham';

            let slug = '';
            if (typeof window.pmSlugify === 'function') {
                slug = window.pmSlugify(raw) || '';
            }
            if (!slug) {
                let txt = String(raw || '').trim();
                try {
                    if (txt.normalize) {
                        txt = txt.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                } catch (e) {}
                slug = txt.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            }
            if (!slug) slug = 'product';
            return base + '/product/' + encodeURIComponent(slug) + '-' + id;
        };
        const normalizeProductDetailUrl = (prod) => {
            if (!window.history || typeof window.history.replaceState !== 'function') return;
            const target = buildProductSeoUrl(prod);
            if (!target) return;
            PRODUCT_SEO_URL = target;
            const currentPath = (window.location.pathname || '').toLowerCase();
            const targetUrl = target + (window.location.search || '').replace(/\?(.*)$/, (m, qs) => {
                if (!qs) return '';
                const kept = qs
                    .split('&')
                    .filter(Boolean)
                    .filter(p => !/^pid=\d+$/i.test(p) && !/^normal=view-product$/i.test(p));
                return kept.length ? '?' + kept.join('&') : '';
            }) + (window.location.hash || '');

            const fullCurrent = (window.location.origin || '') + window.location.pathname + (window.location.search || '') + (window.location.hash || '');
            if (fullCurrent === targetUrl) return;

            const isProductDetailRoute = currentPath.indexOf('/product/') === 0 ||
                (window.location.search || '').toLowerCase().indexOf('normal=view-product') !== -1;
            if (!isProductDetailRoute) return;

            try {
                window.history.replaceState({}, document.title, targetUrl);
            } catch (e) {}
        };

        const setCardState = (card, expand) => {
            if (!card) return;
            const trigger = card.querySelector('.collapsible-trigger');
            if (expand) {
                card.classList.add('is-expanded');
                card.classList.remove('is-collapsed');
                if (trigger) trigger.setAttribute('aria-expanded', 'true');
            } else {
                card.classList.add('is-collapsed');
                card.classList.remove('is-expanded');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        };

        const toggleCollapsibleCard = (card) => {
            if (!card) return;
            const willExpand = !card.classList.contains('is-expanded');
            const group = card.getAttribute('data-collapse-group');
            if (willExpand && group) {
                document.querySelectorAll(`.collapsible-card[data-collapse-group="${group}"]`).forEach((other) => {
                    if (other !== card) setCardState(other, false);
                });
            }
            setCardState(card, willExpand);
        };

        const initCollapsibles = () => {
            document.querySelectorAll('.collapsible-card').forEach((card) => {
                const trigger = card.querySelector('.collapsible-trigger');
                if (!trigger) return;
                const isExpanded = card.classList.contains('is-expanded');
                card.classList.toggle('is-collapsed', !isExpanded);
                trigger.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                trigger.addEventListener('click', () => toggleCollapsibleCard(card));
            });
        };
        /*
        const hidePageLoader = () => {
            const loader = document.getElementById('pageLoader');
            if (!loader) return;
            loader.classList.add('is-hidden');
            setTimeout(() => {
                if (loader.parentNode) {
                    loader.remove();
                }
            }, 300);
        };*/

        const notify = (msg, type = 'info') => {
            const safeMsg = (msg === null || typeof msg === 'undefined' || String(msg).trim() === '') ?
                'Có lỗi xảy ra, vui lòng thử lại.' :
                String(msg);
            if (window.toastr && toastr[type]) toastr[type](safeMsg);
            else alert(safeMsg);
        };

        const requireLogin = () => {
            notify('Vui lòng đăng nhập để mua hàng.', 'warning');
            window.location.href = LOGIN_URL;
        };

        const sanitizeRichHtml = (html = '') => {
            const raw = String(html || '').trim();
            if (!raw) return '';

            const parser = new DOMParser();
            const doc = parser.parseFromString(`<div>${raw}</div>`, 'text/html');
            const root = doc.body && doc.body.firstElementChild ? doc.body.firstElementChild : null;
            if (!root) return '';

            // Allowlist tags used in product description.
            const ALLOWED_TAGS = new Set([
                'p', 'br', 'div', 'span',
                'strong', 'em', 'b', 'i', 'u',
                'h2', 'h3', 'h4',
                'ul', 'ol', 'li',
                'blockquote',
                'a', 'img', 'iframe'
            ]);

            const ALLOWED_ATTRS = {
                a: new Set(['href', 'target', 'rel', 'title']),
                img: new Set(['src', 'alt', 'title', 'width', 'height', 'loading', 'decoding']),
                iframe: new Set(['src', 'title', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'referrerpolicy', 'loading']),
            };

            const isSafeHttpUrl = (value) => {
                const v = String(value || '').trim();
                if (!v) return false;
                if (/^javascript:/i.test(v)) return false;
                if (/^data:/i.test(v)) return false;
                if (/^\//.test(v)) return true; // relative path
                return /^https?:\/\//i.test(v);
            };

            const isAllowedYoutubeEmbed = (src) => {
                try {
                    const s = String(src || '').trim();
                    if (!s) return false;
                    if (!/^https?:\/\//i.test(s)) return false;
                    const u = new URL(s);
                    const host = String(u.hostname || '').toLowerCase();
                    const path = String(u.pathname || '');
                    const allowedHosts = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];
                    if (!allowedHosts.includes(host)) return false;
                    // Only allow embed URL format
                    return /^\/embed\//i.test(path);
                } catch (e) {
                    return false;
                }
            };

            const unwrap = (el) => {
                const parent = el.parentNode;
                if (!parent) return;
                while (el.firstChild) parent.insertBefore(el.firstChild, el);
                parent.removeChild(el);
            };

            const cleanElement = (el) => {
                if (!el || el.nodeType !== 1) return;
                const tag = String(el.tagName || '').toLowerCase();

                // Remove dangerous elements entirely.
                if (['script', 'style', 'object', 'embed', 'link', 'meta'].includes(tag)) {
                    el.remove();
                    return;
                }

                // If tag not allowed: unwrap it (keep children text/content).
                if (!ALLOWED_TAGS.has(tag)) {
                    unwrap(el);
                    return;
                }

                // Strip all inline styles & event handlers.
                if (el.hasAttribute('style')) el.removeAttribute('style');
                [...el.attributes].forEach((attr) => {
                    const name = String(attr.name || '').toLowerCase();
                    const value = String(attr.value || '').trim();
                    if (name.startsWith('on')) {
                        el.removeAttribute(attr.name);
                        return;
                    }

                    const allowed = (ALLOWED_ATTRS[tag] && ALLOWED_ATTRS[tag].has(name)) ||
                        (!ALLOWED_ATTRS[tag] && ['class'].includes(name));

                    if (!allowed) {
                        el.removeAttribute(attr.name);
                        return;
                    }

                    if ((name === 'href' || name === 'src') && !isSafeHttpUrl(value)) {
                        el.removeAttribute(attr.name);
                    }
                });

                // Normalize links.
                if (tag === 'a') {
                    const href = el.getAttribute('href') || '';
                    if (href && /^https?:\/\//i.test(href)) {
                        el.setAttribute('target', '_blank');
                        el.setAttribute('rel', 'nofollow noopener noreferrer');
                    }
                }

                // Iframe: only allow YouTube embed.
                if (tag === 'iframe') {
                    const src = el.getAttribute('src') || '';
                    if (!isAllowedYoutubeEmbed(src)) {
                        el.remove();
                        return;
                    }
                    el.setAttribute('loading', 'lazy');
                    el.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                    if (!el.hasAttribute('allowfullscreen')) el.setAttribute('allowfullscreen', 'allowfullscreen');
                    if (el.hasAttribute('frameborder')) {
                        const fb = String(el.getAttribute('frameborder') || '').trim();
                        el.setAttribute('frameborder', fb === '' ? '0' : fb);
                    }
                }

                if (tag === 'img') {
                    el.setAttribute('loading', el.getAttribute('loading') || 'lazy');
                    el.setAttribute('decoding', el.getAttribute('decoding') || 'async');
                }
            };

            const all = Array.from(root.querySelectorAll('*')).reverse();
            all.forEach(cleanElement);
            cleanElement(root);

            return (root.innerHTML || '').trim();
        };

        const renderProductPromos = (promos) => {
            const $block = $('#promoSection');
            const $giftSection = $('#promoGiftSection');
            const $comboSection = $('#promoComboSection');
            const $bxgySection = $('#promoBxgySection');
            const $giftTitle = $('#promoGiftTitle');
            const $giftGrid = $('#promoGiftGrid');
            const $comboTitle = $('#promoComboTitle');
            const $comboGrid = $('#promoComboGrid');
            const $bxgyTitle = $('#promoBxgyTitle');
            const $bxgyGrid = $('#promoBxgyGrid');
            if (!$block.length) return;

            const gift = promos && promos.gift ? promos.gift : null;
            const combo = promos && promos.combo ? promos.combo : null;
            const bxgy = promos && promos.bxgy ? promos.bxgy : null;

            const hasCombo = Array.isArray(combo) ? combo.length > 0 : (combo && combo.items && combo.items.length);
            const hasGift = gift && Array.isArray(gift.products) && gift.products.length;
            const bxgyList = Array.isArray(bxgy) ? bxgy : (bxgy ? [bxgy] : []);

            BXGY_PROMOS_DETAIL = {};
            bxgyList.forEach((p) => {
                const id = Number(p && p.id ? p.id : 0);
                if (id > 0) BXGY_PROMOS_DETAIL[id] = p;
            });
            const hasBxgy = bxgyList.length > 0;
            if (!hasCombo && !hasGift && !hasBxgy) {
                $block.hide();
                return;
            }
            let giftRendered = false;
            let comboRendered = false;
            let bxgyRendered = false;

            if (hasGift && $giftSection.length && $giftGrid.length && $giftTitle.length) {
                const baseLabel = String(gift.label || 'Ưu đãi khác dành cho bạn').trim();
                $giftTitle.text(baseLabel || 'Ưu đãi khác dành cho bạn');
                const giftEndText = buildPromoEndText(gift.end_at || gift.endAt || gift.end_time);
                const giftItems = (gift.products || []).map((p) => {
                    const pid = p.product_id || p.id;
                    if (!pid) return null;
                    return {
                        product_id: pid,
                        name: p.name,
                        thumb: p.thumb,
                        price: p.price,
                        price_text: p.price_text,
                        qty: p.qty || 1,
                        variant: p.variant || p.variant_name || '',
                        variant_name: p.variant_name,
                        threshold_amount: Number(p.threshold_amount || gift.threshold_amount || 0),
                    };
                }).filter(Boolean);

                if (!giftItems.length) {
                    $giftSection.hide();
                } else {

                    const tiersMap = {};
                    giftItems.forEach((it) => {
                        const t = Number(it.threshold_amount || 0);
                        const key = Number.isFinite(t) && t > 0 ? t : 0;
                        if (!tiersMap[key]) tiersMap[key] = [];
                        tiersMap[key].push(it);
                    });
                    const tierKeys = Object.keys(tiersMap).map(k => Number(k)).sort((a, b) => a - b);
                    const giftHtml = tierKeys.map((t) => {
                        const itemsInTier = tiersMap[t] || [];
                        if (!itemsInTier.length) return '';
                        const tierLabel = t > 0 ?
                            ('Đơn từ ' + fmtPrice(t)) :
                            'Ưu đãi cho mọi đơn hàng';
                        const deadlineHtml = giftEndText ?
                            `<span class="promo-deadline text-danger"> · ${esc(giftEndText)}</span>` :
                            '';
                        const cardsHtml = itemsInTier.map((it) => {
                            const pid = Number(it.product_id || it.id || 0);
                            const name = esc(it.name || 'Sản phẩm');
                            const thumb = String(it.thumb || '') ? (typeof window.toMediaUrl === 'function' ? window.toMediaUrl(it.thumb) : (BASE_URL + '/' + String(it.thumb || ''))) : FALLBACK_IMG;
                            const origText = fmtPrice(it.price || 0);
                            const dealText = 'Miễn phí';
                            const qtyText = 'Số lượng: ' + (Number(it.qty || 1));
                            const variantRaw = it.variant || it.variant_name || '';
                            const variantText = 'Phân loại: ' + (variantRaw ? esc(variantRaw) : 'Mặc định');
                            const threshold = Number(it.threshold_amount || 0);
                            return `
                            <div class="promo-deal-card" data-pid="${pid}" data-threshold="${threshold}">
                                <div class="promo-deal-thumb"><img src="${thumb}" alt="${name}" loading="lazy" decoding="async"></div>
                                <div class="promo-deal-main">
                                    <div class="promo-deal-name"><span class="badge bg-success text-white me-1">Quà tặng</span>${name}</div>
                                    <div class="promo-deal-pick"><label class="small"><input type="checkbox" class="promo-choice-input-gift form-check-input me-1" name="promo_gift_choice" value="gift"> Chọn quà tặng</label></div>
                                    <div class="promo-deal-meta">${variantText} · ${qtyText}</div>
                                    <div class="promo-deal-prices">
                                        ${origText ? `<span class="promo-deal-price-original">${origText}</span>` : ''}
                                        <span class="promo-deal-price-deal">${dealText}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                        }).join('');
                        return `
                        <div class="promo-gift-tier">
                            <div class="promo-gift-tier-title">${tierLabel}${deadlineHtml}</div>
                            <div class="promo-gift-tier-grid">
                                ${cardsHtml}
                            </div>
                        </div>
                    `;
                    }).join('');

                    $giftGrid.html(giftHtml);

                    $giftGrid.off('change.promoGiftChoice').on('change.promoGiftChoice', '.promo-choice-input-gift', function() {
                        const $self = $(this);
                        if ($self.prop('checked')) {
                            $giftGrid.find('.promo-choice-input-gift').not(this).prop('checked', false);
                        }
                    });

                    $giftGrid.off('click.promoGiftCard').on('click.promoGiftCard', '.promo-deal-card', function(e) {
                        if ($(e.target).is('input, label')) return;
                        const $input = $(this).find('.promo-choice-input-gift').first();
                        if (!$input.length) return;
                        if ($input.prop('checked')) return;
                        $input.prop('checked', true).trigger('change');
                    });
                    const $gInputs = $giftGrid.find('.promo-choice-input-gift');
                    if ($gInputs.length) {
                        $gInputs.prop('checked', false).first().prop('checked', true).trigger('change');
                    }
                    $giftSection.show();
                    giftRendered = true;
                }
            } else if ($giftSection.length) {
                $giftSection.hide();
            }

            const comboList = Array.isArray(combo) ? combo : (combo ? [combo] : []);
            if (comboList.length && $comboSection.length && $comboGrid.length) {
                const comboHtml = comboList.map((cb) => {
                    const label = String(cb.label || 'Mua thêm deal sốc').trim();
                    const comboEndText = buildPromoEndText(cb.end_at || cb.endAt || cb.end_time);
                    const deadlineHtml = comboEndText ? ` · <span class="promo-deadline text-danger">${esc(comboEndText)}</span>` : '';

                    const itemsHtml = (cb.items || []).map((it) => {
                        const pid = Number(it.product_id || it.id || 0);
                        const name = esc(it.name || 'Sản phẩm');
                        const thumb = String(it.thumb || '') ? (typeof window.toMediaUrl === 'function' ? window.toMediaUrl(it.thumb) : (BASE_URL + '/' + String(it.thumb || ''))) : FALLBACK_IMG;
                        const origText = fmtPrice(it.price || 0);
                        const dealText = (typeof it.promo_price !== 'undefined' ? fmtPrice(it.promo_price) : origText);
                        const qtyText = 'Số lượng: ' + (Number(it.qty || 1));
                        const variantRaw = it.variant || it.variant_name || '';
                        const variantText = 'Phân loại: ' + (variantRaw ? esc(variantRaw) : 'Mặc định');
                        const comboBadge = '<span class="badge bg-warning text-dark me-1"><i class="bi bi-lightning-charge-fill"></i> Deal sốc</span>';

                        return `
                        <div class="promo-deal-card" data-pid="${pid}">
                            <div class="promo-deal-thumb"><img src="${thumb}" alt="${name}" loading="lazy" decoding="async"></div>
                            <div class="promo-deal-main">
                                <div class="promo-deal-name">${comboBadge}${name}</div>
                                <div class="promo-deal-pick"><label class="small"><input type="checkbox" class="promo-choice-input-combo form-check-input me-1" name="promo_combo_choice" value="combo"> Chọn sản phẩm deal sốc</label></div>
                                <div class="promo-deal-meta">${variantText} · ${qtyText}</div>
                                <div class="promo-deal-prices">
                                    ${origText ? `<span class="promo-deal-price-original">${origText}</span>` : ''}
                                    <span class="promo-deal-price-deal">${dealText}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    }).join('');

                    return `
                    <div class="promo-combo-group mb-3">
                        <div class="promo-deal-title mb-2">${label}${deadlineHtml}</div>
                        <div class="promo-deal-grid">
                            ${itemsHtml}
                        </div>
                    </div>
                `;
                }).join('');

                $comboGrid.html(comboHtml);
                $comboTitle.hide();
                $comboGrid.off('change.promoComboChoice').on('change.promoComboChoice', '.promo-choice-input-combo', function() {
                    const $self = $(this);
                    if ($self.prop('checked')) {
                        $comboGrid.find('.promo-choice-input-combo').not(this).prop('checked', false);
                    }
                });

                $comboGrid.off('click.promoComboCard').on('click.promoComboCard', '.promo-deal-card', function(e) {
                    if ($(e.target).is('input, label')) return;
                    const $input = $(this).find('.promo-choice-input-combo').first();
                    if (!$input.length) return;
                    if ($input.prop('checked')) return;
                    $input.prop('checked', true).trigger('change');
                });

                const $cInputs = $comboGrid.find('.promo-choice-input-combo');
                if ($cInputs.length) {
                    $cInputs.prop('checked', false).first().prop('checked', true).trigger('change');
                }
                $comboSection.show();
                comboRendered = true;
            } else if ($comboSection.length) {
                $comboSection.hide();
            }

            if (hasBxgy && $bxgySection.length && $bxgyGrid.length && $bxgyTitle.length) {
                const html = bxgyList.map((c) => {
                    const buy = Number(c.buy_qty || 0);
                    const giftQty = Number(c.gift_qty || 0);
                    const maxPer = Number(c.max_gift_per_order || 0);
                    const deadlineText = buildPromoEndText(c.end_at || c.endAt || c.end_time);
                    const labelCore = (buy > 0 && giftQty > 0) ? ('Mua ' + buy + ' tặng ' + giftQty) : 'Mua 2 tặng 1';
                    const maxText = maxPer > 0 ?
                        ('Tối đa: <b style="color:red">' + maxPer + '</b> quà / đơn hàng') :
                        'Không giới hạn số quà trên 1 đơn hàng';
                    const name = esc(c.name || 'Chiến dịch ưu đãi');

                    const promoId = Number(c.id || 0);
                    const gifts = promoGiftProducts(c);
                    if (!promoId || !gifts.length) return '';

                    const firstGift = gifts[0];
                    const giftPid = Number(firstGift.product_id || firstGift.id || 0);
                    if (!giftPid) return '';
                    const giftName = esc(firstGift.name || firstGift.product_name || ('Quà #' + giftPid));

                    const promoState = BXGY_PROMOS_DETAIL[promoId] || {};
                    promoState.selectedGiftPid = promoState.selectedGiftPid || giftPid;
                    promoState.selectedGiftVid = promoState.selectedGiftVid || 0;
                    BXGY_PROMOS_DETAIL[promoId] = promoState;

                    const currentGiftId = promoState.selectedGiftPid || giftPid;
                    const currentVid = promoState.selectedGiftVid || 0;
                    const giftThumbRaw = String(firstGift.thumb || firstGift.image_url || '').trim();
                    const giftThumb = giftThumbRaw ? esc(toAbs(giftThumbRaw)) : '';
                    const thumbInner = giftThumb
                        ? `<img src="${giftThumb}" alt="Quà" loading="lazy" decoding="async" onerror="this.parentNode.innerHTML='<i class=\\'bi bi-gift-fill\\'></i>'">`
                        : `<i class="bi bi-gift-fill"></i>`;

                    return `
                    <div class="promo-bxgy-group mb-3">
                        <div class="promo-deal-title mb-2">FLASH SALE - ${labelCore}${deadlineText ? ' · <span class="promo-deadline text-danger">' + esc(deadlineText) + '</span>' : ''}</div>
                        <div class="promo-deal-card promo-bxgy-card">
                            <div class="promo-deal-main">
                                <div class="promo-deal-name">
                                    <img src="<?= htmlentities((string) $baseUrl, ENT_QUOTES, 'UTF-8') ?>/image/fire.gif" height="20" width="20" alt="hot" loading="lazy" decoding="async">
                                    <span class="me-2">${name}</span>
                                </div>
                                <div class="promo-deal-meta d-none">
                                    Chương trình: <b class="text-danger">${labelCore}</b>
                                </div>
                                <div class="bxgy-selector-wrap mt-2" data-bxgy="1" data-promo_id="${promoId}">
                                    <div class="bxgy-selector" data-promo_id="${promoId}" data-gift_pid="${currentGiftId}" data-gift_vid="${currentVid}">
                                        <div class="bxgy-selector-thumb">${thumbInner}</div>
                                        <div class="bxgy-selector-main">
                                            <div class="bxgy-selector-title">Chọn quà tặng</div>
                                            <div class="bxgy-selector-current">${giftName}</div>
                                        </div>
                                        <i class="bi bi-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="mt-2 promo-deal-meta small text-muted">${maxText}</div>
                            </div>
                        </div>
                    </div>
                `;
                }).join('');

                $bxgyGrid.html(html);
                //$bxgyTitle.text('Ưu đãi Mua X tặng Y').show();
                $bxgySection.show();
                bxgyRendered = !!html;
            } else if ($bxgySection.length) {
                $bxgySection.hide();
            }

            if (giftRendered || comboRendered || bxgyRendered) {
                $block.show();
            } else {
                $block.hide();
            }
        };
        const fmtPrice = (n) => {
            if (window.pmFormatPrice && typeof window.pmFormatPrice === 'function') {
                return window.pmFormatPrice(n);
            }
            const num = Number(n) || 0;
            return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
        };
        const esc = (str) => $('<div>').text(str || '').html();

        const promoGiftProducts = (promo) => {
            if (!promo) return [];
            if (Array.isArray(promo.gift_products) && promo.gift_products.length) {
                return promo.gift_products;
            }
            const ids = Array.isArray(promo.gift_product_ids) ? promo.gift_product_ids : [];
            if (ids.length) {
                return ids.map(id => ({
                    product_id: Number(id || 0),
                    name: '#' + String(id || '')
                }));
            }
            const gid = Number(promo.gift_product_id || 0);
            if (gid > 0) {
                return [{
                    product_id: gid,
                    name: '#' + String(gid)
                }];
            }
            return [];
        };
        const getAllowedGiftVariantIds = (promo, giftPid) => {
            if (!promo) return [];
            const map = promo.gift_variant_ids;
            if (!map || typeof map !== 'object') return [];
            const key1 = String(Number(giftPid || 0));
            const arr = Array.isArray(map[giftPid]) ? map[giftPid] :
                (Array.isArray(map[key1]) ? map[key1] : []);
            return (Array.isArray(arr) ? arr : []).map(v => Number(v || 0)).filter(v => v > 0);
        };

        // Tập hợp variant_id của SẢN PHẨM HIỆN TẠI có tham gia khuyến mãi (làm SP chính hoặc làm quà)
        // dùng để gắn badge nổi bật trên từng variant-card. Trả về Map(vid -> nhãn ngắn).
        const getPromoVariantBadgeMap = () => {
            const out = {};
            const key = String(Number(PID || 0));
            const addFrom = (map, label) => {
                if (!map || typeof map !== 'object') return;
                const arr = Array.isArray(map[PID]) ? map[PID] : (Array.isArray(map[key]) ? map[key] : []);
                (Array.isArray(arr) ? arr : []).forEach(v => {
                    const vid = Number(v || 0);
                    if (vid > 0 && !out[vid]) out[vid] = label;
                });
            };
            Object.keys(BXGY_PROMOS_DETAIL || {}).forEach(k => {
                const p = BXGY_PROMOS_DETAIL[k] || {};
                addFrom(p.main_variant_ids, 'KM');
                addFrom(p.gift_variant_ids, 'Quà');
            });
            return out;
        };

        const ensureGiftVariantsLoaded = (pid) => {
            const productId = Number(pid || 0);
            if (!productId) return $.Deferred().resolve([]).promise();
            if (Array.isArray(bxgyGiftVariantsCache[productId])) {
                return $.Deferred().resolve(bxgyGiftVariantsCache[productId]).promise();
            }
            return $.get(API, {
                ajax: 'product_detail',
                pid: productId
            }).then(res => {
                const list = (res && res.ok && res.data && Array.isArray(res.data.variants)) ? res.data.variants : [];
                bxgyGiftVariantsCache[productId] = list;
                return list;
            }, () => {
                bxgyGiftVariantsCache[productId] = [];
                return [];
            });
        };
        const formatDate = (value) => {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '';

            const now = new Date();
            const diffSeconds = Math.floor((now.getTime() - d.getTime()) / 1000);

            if (diffSeconds < 0) {
                return d.toLocaleDateString('vi-VN');
            }
            if (diffSeconds < 60) {
                return 'Vừa mới đây';
            }
            const diffMinutes = Math.floor(diffSeconds / 60);
            if (diffMinutes < 60) {
                return `${diffMinutes} phút trước`;
            }
            const diffHours = Math.floor(diffMinutes / 60);
            if (diffHours < 24) {
                return `${diffHours} tiếng trước`;
            }
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) {
                return `${diffDays} ngày trước`;
            }
            const diffWeeks = Math.floor(diffDays / 7);
            if (diffDays < 30) {
                return `${diffWeeks} tuần trước`;
            }
            const diffMonths = Math.floor(diffDays / 30);
            if (diffDays < 365) {
                return `${diffMonths} tháng trước`;
            }
            const diffYears = Math.floor(diffDays / 365);
            return `${diffYears} năm trước`;
        };

        // Định dạng date-time đầy đủ: HH:MM:SS dd-mm-YYYY
        const formatDateTimeFull = (value) => {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '';
            const pad = (n) => String(n).padStart(2, '0');
            const h = pad(d.getHours());
            const m = pad(d.getMinutes());
            const s = pad(d.getSeconds());
            const dd = pad(d.getDate());
            const mm = pad(d.getMonth() + 1);
            const yy = d.getFullYear();
            return `${h}:${m}:${s} ${dd}-${mm}-${yy}`;
        };

        const buildPromoEndText = (endAt) => {
            if (!endAt) return '';
            const end = new Date(endAt);
            if (Number.isNaN(end.getTime())) return '';

            const now = new Date();
            const diffMs = end.getTime() - now.getTime();
            if (diffMs <= 0) return '';

            const ONE_DAY = 24 * 60 * 60 * 1000;
            if (diffMs > ONE_DAY) {
                return 'Áp dụng đến ' + formatDateTimeFull(endAt);
            }

            let remain = Math.floor(diffMs / 1000); // giây
            const hours = Math.floor(remain / 3600);
            remain %= 3600;
            const mins = Math.floor(remain / 60);
            const secs = remain % 60;

            if (hours > 0) return `Sắp hết hạn: Còn ${hours} tiếng`;
            if (mins > 0) return `Sắp hết hạn: Còn ${mins} phút`;
            return `Sắp hết hạn: Còn ${secs} giây`;
        };

        const renderStars = (rating) => {
            const r = Math.max(0, Math.min(5, Number(rating) || 0));
            let html = '';
            for (let i = 1; i <= 5; i += 1) {
                html += `<i class="bi ${i <= r ? 'bi-star-fill' : 'bi-star'}"></i>`;
            }
            return html;
        };
        const clampRating = (rating) => Math.max(0, Math.min(5, Number(rating) || 0));
        const buildRatingStarsHtml = (rating) => {
            const value = clampRating(rating);
            const emptyStarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 14 14"><path fill="#D1D5DB" d="m3.597 8.977-.74 4.538a.44.44 0 0 0 .644.454l3.844-2.128 3.845 2.128a.44.44 0 0 0 .463-.026.44.44 0 0 0 .18-.428l-.74-4.538 3.128-3.21a.44.44 0 0 0-.247-.74L9.67 4.37 7.74.254c-.143-.307-.647-.307-.79 0L5.02 4.369l-4.303.659a.437.437 0 0 0-.248.739zM5.383 5.2a.44.44 0 0 0 .33-.246L7.345 1.47l1.632 3.482a.44.44 0 0 0 .33.247L13 5.765l-2.686 2.757a.44.44 0 0 0-.119.377l.63 3.866-3.268-1.809a.44.44 0 0 0-.423 0l-3.268 1.809.63-3.866a.44.44 0 0 0-.12-.377L1.69 5.765z"></path></svg>';
            const fullStarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 14 14"><path fill="#F2994A" d="m3.252 8.952-.74 4.538a.438.438 0 0 0 .644.454L7 11.816l3.844 2.129a.438.438 0 0 0 .644-.454l-.74-4.538 3.127-3.21a.438.438 0 0 0-.246-.739l-4.304-.658L7.395.23c-.143-.307-.647-.307-.79 0l-1.93 4.115-4.303.659a.438.438 0 0 0-.247.739z"></path></svg>';
            let html = '<span class="rating-chip-stars">';
            for (let i = 1; i <= 5; i += 1) {
                const fillPercent = Math.max(0, Math.min(100, (value - (i - 1)) * 100));
                html += '<span class="rating-chip-star">' +
                    '<span class="rating-chip-star-base">' + emptyStarSvg + '</span>' +
                    '<span class="rating-chip-star-fill" style="width:' + fillPercent + '%;">' + fullStarSvg + '</span>' +
                    '</span>';
            }
            html += '</span>';
            return html;
        };

        const buildRatingChipHtml = (rating, count) => {
            const value = clampRating(rating);
            const total = Number.isFinite(Number(count)) ? Number(count) : 0;
            return buildRatingStarsHtml(value)
                //+ '<span class="rating-chip-text">Đánh giá ' + value.toFixed(1) + ' (' + total + ' lượt)</span>';
                +
                '<span class="rating-chip-text"> (' + total + ')</span>';
        };

        const renderReviewChart = (summary) => {
            const $chart = $('#reviewChart');
            if (!$chart.length) return;
            const dist = summary?.distribution || {};
            const html = [5, 4, 3, 2, 1].map((star) => {
                const count = Number(dist[String(star)] || 0);
                const base = Number(summary?.rating_count || 0);
                const percent = base > 0 ? Math.max(0, Math.min(100, Math.round((count * 100) / base))) : 0;
                return `
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="text-muted small fw-bold" style="width: 12px;">${star}</div>
                    <div class="text-warning small"><i class="bi bi-star-fill"></i></div>
                    <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px; background-color: #f0f0f0;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: ${percent}%; border-radius: 4px;"></div>
                    </div>
                    <div class="text-muted small d-none d-sm-block" style="width: 80px;">${count} đánh giá</div>
                    <div class="text-muted small d-block d-sm-none" style="width: 30px;">${count}</div>
                </div>
            `;
            }).join('');
            $chart.html(html);
        };

        const buildReviewMediaHtml = (mediaList) => {
            if (!Array.isArray(mediaList) || !mediaList.length) return '';
            const items = mediaList.map((item) => {
                const type = String(item?.type || 'image').toLowerCase();
                const url = esc(toAbs(item?.url || ''));
                if (!url) return '';
                if (type === 'video') {
                    return `<div class="review-attach-item">
                            <a href="${url}" data-toggle="lightbox" data-gallery="review-gallery" data-type="video">
                                <video src="${url}" preload="metadata"></video>
                                <div class="review-attach-play"><i class="bi bi-play-fill"></i></div>
                            </a>
                        </div>`;
                }
                return `<div class="review-attach-item">
                        <a href="${url}" data-toggle="lightbox" data-gallery="review-gallery" data-type="image">
                            <img src="${url}" alt="Đính kèm" loading="lazy" decoding="async">
                        </a>
                    </div>`;
            }).filter(Boolean).join('');
            if (!items) return '';
            return `<div class="review-attach-grid">${items}</div>`;
        };

        const getRatingText = (rating) => {
            switch (Number(rating)) {
                case 5:
                    return 'Tuyệt vời';
                case 4:
                    return 'Rất tốt';
                case 3:
                    return 'Bình thường';
                case 2:
                    return 'Tạm ổn';
                case 1:
                    return 'Không hài lòng';
                default:
                    return '';
            }
        };

        const renderReviewItem = (item, rootId = 0) => {
            const name = esc(item?.name || 'Khách hàng');
            const phone = esc(item?.phone_mask || '');
            const comment = esc(item?.comment || '');
            const time = formatDate(item?.created_at || '');
            const rating = Number(item?.rating || 0);
            const replies = Array.isArray(item?.replies) ? item.replies : [];
            const currentId = Number(item?.id || 0);
            const root = rootId || currentId;
            const mediaHtml = buildReviewMediaHtml(item?.media || []);
            const reviewType = String(item?.review_type || 'review');
            const likeCount = Number(item?.like_count || 0);
            const likedByMe = !!item?.liked_by_me;
            const canEdit = (IS_ADMIN || (CURRENT_USER_ID > 0 && Number(item?.user_id || 0) === CURRENT_USER_ID)) && reviewType !== 'deleted';

            const ratingHtml = rating > 0 ? `
                <div class="d-inline-flex align-items-center gap-2 ms-sm-2 ms-0 mt-sm-0 mt-1">
                    <div class="rating-star" style="color: #fbbf24; font-size: 13.5px;">${renderStars(rating)}</div>
                    <span class="rating-label-text" style="color: #475569; font-size: 12px; font-weight: 500;">${getRatingText(rating)}</span>
                </div>
            ` : '';

            const toggleBtn = replies.length ? `<button type="button" class="btn btn-link btn-sm text-decoration-none item-replies-toggle p-0 my-1 d-inline-flex align-items-center" data-total="${replies.length}" style="margin-left: 45px; color: var(--theme-primary, #0c4c29); font-size: 0.8rem; font-weight: 600; outline: none; border: none; box-shadow: none;"><i class="bi bi-chevron-down me-1"></i>Xem ${replies.length} phản hồi</button>` : '';
            const childHtml = replies.length ? `<div class="item-child" style="display:none;">${replies.map(child => renderReviewItem(child, root)).join('')}</div>` : '';
            const phoneHtml = phone ? `<span class="item-author__phone text-muted ms-1" style="font-size: 11px;">(${phone})</span>` : '';

            const isItemAdmin = item?.user_role === 'admin';
            const avatarStyle = isItemAdmin ? 'style="background: var(--theme-primary, #0c4c29); color: #ffffff; width: 36px; height: 36px; border-radius: 50%; font-weight: bold;"' : 'style="background: #e2e8f0; color: #475569; width: 36px; height: 36px; border-radius: 50%; font-weight: bold;"';
            const avatarInner = isItemAdmin ? '<i class="bi bi-person-fill" style="font-size: 0.9rem;"></i>' : name.substring(0, 1).toUpperCase();

            let nameHtml = '';
            if (isItemAdmin) {
                nameHtml = `<span class="fw-bold" style="color: var(--theme-primary, #0c4c29); font-size: 13.5px;"><i class="bi bi-patch-check-fill me-1"></i>Quản trị viên</span>`;
                if (IS_ADMIN) {
                    nameHtml += `<span class="text-muted fw-normal ms-2" style="font-size: 11px;">(Tên thật: ${esc(item?.name)})</span>`;
                }
            } else {
                nameHtml = `<span class="fw-bold" style="color: #1e293b; font-size: 13.5px;">${name}</span>${phoneHtml}`;
            }

            // Verified Badge: chỉ hiển thị khi backend xác nhận user_id đã có đơn DELIVERED chứa product này
            const isVerifiedBuyer = item?.is_verified_buyer === true;
            const verifiedHtml = isVerifiedBuyer ? `
                <div class="review-verified-badge text-success d-flex align-items-center gap-1 mt-1" style="font-size: 12px; font-weight: 500;" title="Người dùng đã mua và nhận được sản phẩm này tại Paint&More">
                    <i class="bi bi-patch-check-fill" style="font-size: 13.5px;"></i> Đã mua tại Paint&More
                </div>
            ` : '';

            // Selected tags html
            let tagsHtml = '';
            if (item?.tags) {
                const tagsList = String(item.tags).split(',').map(t => t.trim()).filter(Boolean);
                if (tagsList.length > 0) {
                    tagsHtml = `<div class="review-item-tags d-flex flex-wrap gap-2 mt-2 mb-1">` +
                        tagsList.map(tag => `<span class="badge review-item-tag" style="background-color: #f1f5f9; color: #475569; font-weight: 500; padding: 4px 8px; border-radius: 6px; font-size: 11.5px; border: 1px solid #e2e8f0;">${esc(tag)}</span>`).join('') +
                        `</div>`;
                }
            }

            const likeBtn = reviewType === 'deleted' ?
                '' :
                `<button type="button" class="item-action-btn ${likedByMe ? 'active' : ''}" data-like-id="${currentId}"><i class="bi bi-heart-fill"></i>${likeCount}</button>`;
            const editBtn = canEdit ? `<button type="button" class="item-action-btn" data-edit-id="${currentId}"><i class="bi bi-pencil"></i>Sửa</button>` : '';
            const deleteBtn = canEdit ? `<button type="button" class="item-action-btn is-danger" data-delete-id="${currentId}"><i class="bi bi-trash"></i>Xóa</button>` : '';
            const toolsActions = [editBtn, deleteBtn].filter(Boolean).join('');
            const actionToolsHtml = toolsActions ?
                `<div class="item-action-tools ms-auto">
                    <button type="button" class="item-action-toggle" aria-label="Tùy chọn bình luận">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <div class="item-action-menu">
                        ${toolsActions}
                    </div>
               </div>` :
                '';
            const replyBtn = reviewType === 'deleted' ?
                '<span class="item-action-btn is-muted">Bình luận đã xóa</span>' :
                `<div class="item-action__reply" data-reply-id="${currentId}"><span>Trả lời</span></div>`;

            return `
            <div class="item p-3 mb-3 border-bottom" data-comment_id="${currentId}" data-parent_id="${Number(item?.parent_id || 0)}" data-root_id="${root}" data-rating="${rating}" style="background-color: #ffffff;">
                <div class="d-flex align-items-start gap-3">
                    <div class="item-author__avatar flex-shrink-0 d-flex align-items-center justify-content-center" ${avatarStyle}>${avatarInner}</div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div class="d-flex flex-wrap align-items-center">
                                ${nameHtml}
                                ${ratingHtml}
                            </div>
                            ${actionToolsHtml}
                        </div>
                        
                        ${verifiedHtml}
                        
                        ${tagsHtml}
                        
                        <div class="item-content mt-2" style="font-size: 13.5px; color: #334155; line-height: 1.5;">${comment || 'Không có nội dung'}</div>
                        
                        ${mediaHtml}
                        
                        <div class="review-time mt-2 mb-2 d-flex align-items-center gap-1 text-muted" style="font-size: 11.5px;">
                            <i class="bi bi-clock"></i> Đăng ${time}
                        </div>
                        
                        <div class="item-action d-flex align-items-center gap-3">
                            ${replyBtn}
                            ${likeBtn}
                        </div>
                    </div>
                </div>
                ${toggleBtn}
                ${childHtml}
            </div>
            `;
        };

        const countReviewItems = (list) => {
            if (!Array.isArray(list)) return 0;
            return list.reduce((sum, item) => {
                const replies = Array.isArray(item?.replies) ? item.replies : [];
                return sum + 1 + countReviewItems(replies);
            }, 0);
        };

        const renderReviews = (list, summary) => {
            const $reviewList = $('#reviewList');
            const $qaList = $('#qaList');
            const $reviewEmpty = $('#reviewEmpty');
            const $qaEmpty = $('#qaEmpty');
            const $avg = $('#reviewAvgValue');
            const $count = $('#reviewTotalCount');
            if (!$reviewList.length || !$reviewEmpty.length || !$avg.length || !$count.length) return;

            const reviewCount = Number(summary?.rating_count || 0);
            const avg = Number(summary?.rating_avg || 0);
            const dist = summary?.distribution || {};
            const mediaCount = Number(summary?.has_media_count || 0);

            $avg.text(reviewCount > 0 ? avg.toFixed(1) : '0.0');
            $count.text(`${reviewCount} đánh giá`);

            // Cập nhật số lượng trong các nút filter
            $('#reviewFilters [data-rating]').each(function() {
                const r = $(this).data('rating');
                const m = $(this).data('media');
                if (m === 1) {
                    $(this).text(`Có ảnh/Video (${mediaCount})`);
                } else if (r > 0) {
                    const count = Number(dist[String(r)] || 0);
                    $(this).text(`${r} Sao (${count})`);
                } else {
                    $(this).text(`Tất cả (${reviewCount})`);
                }
            });

            renderReviewChart(summary || {});

            // Cập nhật chip đánh giá tổng quan dựa trên dữ liệu mới nhất
            $('#pRating').html(buildRatingChipHtml(avg, reviewCount)).show();

            // Lưu lại dữ liệu bình luận (bao gồm media) để dùng khi chỉnh sửa
            REVIEW_STORE = {};
            const walkStore = (items) => {
                if (!Array.isArray(items)) return;
                items.forEach((it) => {
                    const id = Number(it?.id || 0);
                    if (id > 0) REVIEW_STORE[id] = it;
                    const replies = Array.isArray(it?.replies) ? it.replies : [];
                    if (replies.length) walkStore(replies);
                });
            };
            walkStore(list || []);

            if (!Array.isArray(list) || list.length === 0) {
                $reviewList.empty();
                if ($qaList.length) $qaList.empty();
                $reviewEmpty.show();
                if ($qaEmpty.length) $qaEmpty.show();
                return;
            }

            // Tách danh sách thành Đánh giá (có sao) và Hỏi đáp (không sao)
            const reviews = list.filter(item => Number(item.rating) > 0);
            const questions = list.filter(item => Number(item.rating) === 0);

            if (reviews.length === 0) {
                $reviewList.empty();
                $reviewEmpty.show();
            } else {
                $reviewList.html(reviews.map(item => renderReviewItem(item)).join(''));
                $reviewEmpty.hide();
            }

            if ($qaList.length) {
                if (questions.length === 0) {
                    $qaList.empty();
                    $qaEmpty.show();
                } else {
                    $qaList.html(questions.map(item => renderReviewItem(item)).join(''));
                    $qaEmpty.hide();
                }
            }
        };

        let currentReviewFilter = {
            rating: 0,
            has_media: 0
        };
        const REVIEW_SKELETON_HTML = `
        <div class="skeleton-review mb-4 p-3 border border-light rounded-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="skeleton-line rounded-circle" style="width: 45px; height: 45px;"></div>
                <div class="flex-grow-1">
                    <div class="skeleton-line w-25 mb-2" style="height: 12px;"></div>
                    <div class="skeleton-line w-15" style="height: 10px;"></div>
                </div>
            </div>
            <div class="skeleton-line w-100 mb-2" style="height: 14px;"></div>
            <div class="skeleton-line w-75 mb-3" style="height: 14px;"></div>
            <div class="d-flex gap-3">
                <div class="skeleton-line w-15" style="height: 12px;"></div>
                <div class="skeleton-line w-10" style="height: 12px;"></div>
            </div>
        </div>
    `.repeat(3);

        $(document).on('click', '#reviewFilters button', function() {
            const $btn = $(this);
            if ($btn.hasClass('active')) return;

            $('#reviewFilters button').removeClass('active').addClass('btn-outline-secondary').removeClass('btn-primary');
            $btn.addClass('active').removeClass('btn-outline-secondary').addClass('btn-primary');

            currentReviewFilter.rating = parseInt($btn.data('rating')) || 0;
            currentReviewFilter.has_media = parseInt($btn.data('media')) || 0;

            // Show skeletons
            $('#reviewList').html(REVIEW_SKELETON_HTML);
            if ($('#qaList').length) $('#qaList').html(REVIEW_SKELETON_HTML);

            reloadReviews();
        });

        const reloadReviews = () => {
            if (!PID) return;
            $.get(REVIEW_API, {
                ajax: 'product_reviews',
                pid: PID,
                rating: currentReviewFilter.rating,
                has_media: currentReviewFilter.has_media
            }, (res) => {
                if (!res || !res.ok) return;
                renderReviews(res.reviews || [], res.summary || {});
            }).fail(() => {
                // im lặng nếu lỗi, tránh chặn luồng mua hàng
            });
        };

        const clearReviewMedia = () => {
            reviewMediaFiles.forEach(item => {
                if (item?.previewUrl) {
                    try {
                        URL.revokeObjectURL(item.previewUrl);
                    } catch (err) {}
                }
            });
            reviewMediaFiles = [];
            $('#reviewMediaPreview').empty();
            $('#reviewMediaInput').val('');
        };

        const renderReviewMediaPreview = () => {
            const $wrap = $('#reviewMediaPreview');
            if (!$wrap.length) return;
            if (!reviewMediaFiles.length) {
                $wrap.empty();
                return;
            }
            const html = reviewMediaFiles.map((item, idx) => {
                const type = String(item?.type || 'image');
                const src = esc(item?.previewUrl || '');
                if (!src) return '';
                const mediaEl = (type === 'video') ?
                    `<video src="${src}" muted preload="metadata"></video>` :
                    `<img src="${src}" alt="Media" loading="lazy" decoding="async">`;
                return `
                <div class="review-media-item" data-idx="${idx}">
                    ${mediaEl}
                    <button type="button" class="review-media-remove" aria-label="Xóa">&times;</button>
                </div>
            `;
            }).join('');
            $wrap.html(html);
        };

        const addReviewMediaFiles = (files) => {
            if (!files || !files.length) return;
            const maxFiles = 6;
            const maxImageSize = 5 * 1024 * 1024;
            const maxVideoSize = 30 * 1024 * 1024;
            Array.from(files).forEach((file) => {
                if (reviewMediaFiles.length >= maxFiles) return;
                const type = String(file.type || '').toLowerCase();
                const isImage = type.startsWith('image/');
                const isVideo = type.startsWith('video/');
                if (!isImage && !isVideo) return;
                if (isImage && file.size > maxImageSize) return;
                if (isVideo && file.size > maxVideoSize) return;
                const previewUrl = URL.createObjectURL(file);
                reviewMediaFiles.push({
                    file,
                    type: isVideo ? 'video' : 'image',
                    previewUrl,
                });
            });
            renderReviewMediaPreview();
        };

        const renderFaqList = (payload) => {
            const $list = $('#faqList');
            if (!$list.length) return;
            const faqs = Array.isArray(payload?.faqs) ? payload.faqs : [];
            const tips = Array.isArray(payload?.usage_tips) ? payload.usage_tips : [];
            const cautions = Array.isArray(payload?.cautions) ? payload.cautions : [];
            const items = [];

            faqs.forEach((item, idx) => {
                const q = esc(item?.question || '');
                const a = esc(item?.answer || '');
                if (!q || !a) return;
                items.push(`
                <div class="faq-item">
                    <div class="faq-q">${idx + 1}) ${q}</div>
                    <div class="faq-a">${a}</div>
                </div>
            `);
            });

            if (tips.length) {
                const tipHtml = tips.map(t => `<li>${esc(t)}</li>`).join('');
                items.push(`
                <div class="faq-item">
                    <div class="faq-q">Cách sử dụng nhanh</div>
                    <div class="faq-a"><ul class="mb-0">${tipHtml}</ul></div>
                </div>
            `);
            }

            if (cautions.length) {
                const warnHtml = cautions.map(t => `<li>${esc(t)}</li>`).join('');
                items.push(`
                <div class="faq-item">
                    <div class="faq-q">Lưu ý an toàn & bảo quản</div>
                    <div class="faq-a"><ul class="mb-0">${warnHtml}</ul></div>
                </div>
            `);
            }

            $list.html(items.length ? items.join('') : '<div class="text-muted small">Chưa có gợi ý.</div>');
        };

        function toAbs(url) {
            if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
            const raw = String(url || '').trim();
            if (!raw) return '';
            if (/^(javascript|vbscript|data):/i.test(raw)) return '';
            if (/^(https?:)?\/\//i.test(raw)) return raw;
            const base = String(BASE_URL || '').replace(/\/$/, '');
            if (!base) return raw;
            const path = raw.startsWith('/') ? raw : '/' + raw;
            return base + path;
        }

        let product = null;
        let variants = [];
        let groups = [];
        let activeGroupId = 'all';
        let selectedVariantId = 0;
        let productBasePrice = 0;
        let productBasePriceOld = 0;
        let selectedVariantPrice = 0;
        let selectedVariantPriceOld = 0;
        let selectedVariantSku = '';
        let productDefaultSku = '';
        let priceAvailable = true;

        const skuOfVariant = (v) => {
            if (!v) return '';
            return String(
                v.sku_variant ||
                v.sku ||
                v.variant_sku ||
                v.sku_code ||
                v.ma_sku ||
                v.ma ||
                ''
            ).trim();
        };

        const skuOfProduct = (p) => {
            if (!p) return '';
            return String(
                p.sku ||
                p.product_sku ||
                p.sku_product ||
                p.sku_code ||
                p.ma_sku ||
                p.ma ||
                ''
            ).trim();
        };
        let shippingInfoModalInstance = null;
        let reviewModalInstance = null;
        let mediaItems = [];
        let defaultMediaItems = [];
        let currentMediaIdx = 0;
        let slideshowTimer = null;
        let productStockBase = 0;
        let selectedVariantStock = 0;
        let selectedReviewRating = 0;
        let reviewMediaFiles = [];
        let REVIEW_STORE = {};
        let voucherOptions = [];
        let selectedVoucherCode = '';
        let selectedShipVoucherCode = '';
        let lastClickedVoucherCode = '';
        let selectedShipMethodKey = '';
        let lastSubtotal = 0;
        let selectedVariantShipping = {
            weight_value: 1,
            weight_unit: 'kg',
            length_cm: 20,
            width_cm: 20,
            height_cm: 20,
        };
        let shippingInfo = {
            destination: 'Chưa thiết lập địa chỉ giao hàng',
            methods: [],
            default_fee_text: '0 đ'
        };
        // BXGY promos + cache gift variants (dùng cho chọn phân loại quà tặng)
        let BXGY_PROMOS_DETAIL = {};
        const bxgyGiftVariantsCache = {};
        let bxgyDropdownStateDetail = null;

        const detectMediaType = (url = '') => {
            const clean = String(url).toLowerCase();
            if (/\.(mp4|webm|mov|m4v)(\?|$)/.test(clean)) return 'video';
            return 'image';
        };

        const normalizeMediaItem = (item) => {
            if (!item) return null;
            if (typeof item === 'string') return {
                url: item,
                type: detectMediaType(item)
            };
            if (typeof item === 'object') {
                const src = item.url || item.src || '';
                if (!src) return null;
                return {
                    url: src,
                    type: item.type || detectMediaType(src),
                    thumb: item.thumb || item.preview || src,
                    label: item.label || item.title || ''
                };
            }
            return null;
        };

        const parseMediaGallery = (raw) => {
            if (!raw) return [];
            if (Array.isArray(raw)) return raw;
            if (typeof raw === 'string') {
                try {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) return parsed;
                } catch (err) {}
                return raw.split(',').map(str => str.trim()).filter(Boolean);
            }
            return [];
        };

        function buildMediaList(productData) {
            const list = [];
            const mainImg = productData?.image_url ? toAbs(productData.image_url) : '';
            if (mainImg) list.push({
                url: mainImg,
                thumb: mainImg,
                type: detectMediaType(mainImg)
            });
            const galleryRaw = productData?.media_gallery ?? productData?.album ?? [];
            const galleryArr = parseMediaGallery(galleryRaw);
            galleryArr.forEach(entry => {
                const normalized = normalizeMediaItem(entry);
                if (!normalized) return;
                const absUrl = toAbs(normalized.url);
                const thumb = normalized.thumb ? toAbs(normalized.thumb) : absUrl;
                list.push({
                    ...normalized,
                    url: absUrl,
                    thumb
                });
            });
            if (!list.length) {
                list.push({
                    url: FALLBACK_IMG,
                    thumb: FALLBACK_IMG,
                    type: 'image',
                    skeleton: true
                });
            }
            return list;
        }

        function updateMainMedia(item) {
            const $img = $('#galleryMainImage');
            const $video = $('#galleryMainVideo');
            const $link = $('#galleryMainLink');
            if (!$img.length || !$video.length || !$link.length) return;

            // Hide skeleton overlay if present (remove hẳn để không đè lên ảnh)
            $('#galleryMain').find('.skeleton-gallery-main').remove();

            const url = item?.url || FALLBACK_IMG;
            $link.attr('href', url);
            $link.attr('data-type', item && item.type === 'video' ? 'video' : 'image');
            $link.attr('data-caption', item?.label || '');

            if (item && item.type === 'video') {
                $video.attr('src', url).show().addClass('slide-in-right');
                const videoEl = $video.get(0);
                if (videoEl) {
                    videoEl.controls = false;
                    videoEl.removeAttribute('controls');
                    videoEl.autoplay = true;
                    setTimeout(() => {
                        try {
                            videoEl.play();
                        } catch (e) {}
                    }, 0);
                }
                $img.hide();
            } else {
                $img.attr('src', url).attr('alt', item?.label || 'Hình sản phẩm').show().addClass('slide-in-right');
                if ($video.length) {
                    const videoEl = $video.get(0);
                    if (videoEl && typeof videoEl.pause === 'function') videoEl.pause();
                }
                $video.hide().attr('src', '');
            }
            setTimeout(() => {
                $img.removeClass('slide-in-right');
                $video.removeClass('slide-in-right');
            }, 400);
        }

        function selectMediaByIndex(idx) {
            if (!Array.isArray(mediaItems) || !mediaItems[idx]) return;
            currentMediaIdx = idx;
            updateMainMedia(mediaItems[idx]);
            $('#galleryThumbs .gallery-thumb').removeClass('active');
            $(`#galleryThumbs .gallery-thumb[data-idx="${idx}"]`).addClass('active');
            if (slideshowTimer) {
                clearInterval(slideshowTimer);
                slideshowTimer = null;
            }
        }

        function renderMediaGallery() {
            if (!mediaItems.length) {
                mediaItems = [{
                    url: FALLBACK_IMG,
                    thumb: FALLBACK_IMG,
                    type: 'image'
                }];
            }
            if (currentMediaIdx < 0 || currentMediaIdx >= mediaItems.length) currentMediaIdx = 0;
            updateMainMedia(mediaItems[currentMediaIdx]);
            const $thumbs = $('#galleryThumbs');

            let thumbsHtml = '';
            mediaItems.forEach((item, idx) => {
                const active = idx === currentMediaIdx ? 'active' : '';
                const thumbUrl = esc(item.thumb || item.url || FALLBACK_IMG);
                const label = esc(item.label || ('Media ' + (idx + 1)));
                const isVideo = item.type === 'video';
                const badge = isVideo ? '<i class="bi bi-play-btn-fill thumb-badge"></i>' : '';
                if (isVideo) {
                    thumbsHtml += `<button type="button" class="gallery-thumb ${active}" data-idx="${idx}" aria-label="Media ${idx + 1}">` +
                        `<video src="${thumbUrl}" preload="metadata" muted playsinline disablePictureInPicture controlsList="nodownload nofullscreen noremoteplayback noplaybackrate" tabindex="-1" style="pointer-events:none;user-select:none;outline:none;" oncontextmenu="return false;" onmousedown="return false;" onmouseup="return false;" onkeydown="return false;" onkeyup="return false;" onfocus="this.blur();" onplay="this.pause();" onpause="this.pause();"></video>${badge}</button>`;
                } else {
                    thumbsHtml += `<button type="button" class="gallery-thumb ${active}" data-idx="${idx}" aria-label="Media ${idx + 1}">` +
                        `<img src="${thumbUrl}" alt="${label}" loading="lazy" decoding="async" onerror="this.src='${FALLBACK_IMG}';">${badge}</button>`;
                }
            });

            if ($thumbs.length) {
                $thumbs.html(thumbsHtml);

                // Render hidden links for lightbox gallery to enable swiping through ALL media
                let hiddenLinksHtml = '';
                mediaItems.forEach((item, idx) => {
                    if (idx === currentMediaIdx) return; // Main link handles the current one
                    const itemUrl = item.url || FALLBACK_IMG;
                    const itemType = item.type === 'video' ? 'video' : 'image';
                    const itemLabel = item.label || '';
                    hiddenLinksHtml += `<a href="${itemUrl}" data-toggle="lightbox" data-gallery="product-gallery" data-type="${itemType}" data-caption="${itemLabel}"></a>`;
                });
                $('#galleryLightboxLinks').html(hiddenLinksHtml);
                // Ngăn mọi thao tác trên video thumbnail
                setTimeout(function() {
                    document.querySelectorAll('#galleryThumbs video').forEach(function(v) {
                        v.controls = false;
                        v.removeAttribute('controls');
                        v.addEventListener('contextmenu', e => e.preventDefault());
                        v.addEventListener('mousedown', e => e.preventDefault());
                        v.addEventListener('mouseup', e => e.preventDefault());
                        v.addEventListener('keydown', e => e.preventDefault());
                        v.addEventListener('keyup', e => e.preventDefault());
                        v.addEventListener('focus', e => {
                            v.blur();
                            e.preventDefault();
                        });
                        v.addEventListener('play', e => v.pause());
                        v.addEventListener('pause', e => v.pause());
                    });
                }, 100);
            }
        }

        function startSlideshow() {
            if (slideshowTimer) {
                clearInterval(slideshowTimer);
                slideshowTimer = null;
            }
        }


        function openZoom() {
            $('#galleryMainLink').trigger('click');
        }



        const getQty = () => Math.max(1, parseInt($('#qty').val(), 10) || 1);
        const currentUnitPrice = () => selectedVariantPrice || productBasePrice || 0;
        const currentUnitPriceOld = () => selectedVariantPriceOld || productBasePriceOld || 0;
        const hasPriceValue = (val) => Number(val) > 0;

        function formatVoucherAmount(value) {
            // Dùng chuẩn K cho mệnh giá cố định; nếu có helper valueLabel thì bỏ tiền tố "Giảm " để tránh lặp
            if (window.pmVoucher && typeof window.pmVoucher.valueLabel === 'function') {
                const lbl = window.pmVoucher.valueLabel({
                    type: 'fixed',
                    value: value
                });
                if (lbl) {
                    return lbl.replace(/^Giảm\s*/i, '');
                }
            }
            const amount = Math.max(0, Number(value) || 0);
            if (amount >= 1000) {
                const k = amount * 1000;
                const txt = (Math.abs(k - Math.round(k)) < 0.01) ? String(Math.round(k)) : k.toFixed(1);
                return txt.replace(/\.0$/, '') + 'K';
            }
            return String(Math.round(amount));
        }

        function voucherTarget(voucher) {
            if (window.pmVoucher && typeof window.pmVoucher.primaryTarget === 'function') {
                return window.pmVoucher.primaryTarget(voucher || {});
            }
            const raw = String(voucher?.discount_target || '').toLowerCase();
            return raw === 'shipping' ? 'shipping' : 'order';
        }

        function formatMinSubtotalShorthand(minVal) {
            const min = Number(minVal || 0);
            if (min <= 0) return '';
            if (min >= 1000000) {
                return (min / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            }
            if (min >= 1000) {
                return (min / 1000).toFixed(0) + 'k';
            }
            return min + 'đ';
        }

        // Title hiển thị cho voucher giảm đơn: dùng mệnh giá giống home_user
        function voucherTitle(voucher) {
            if (!voucher || typeof voucher !== 'object') return 'Ưu đãi';
            const type = String(voucher.type || '').toLowerCase();
            const value = Number(voucher.value || 0);
            const minVal = Number(voucher.min_subtotal || 0);
            const cond = minVal > 0 ? ' đơn từ ' + formatMinSubtotalShorthand(minVal) : '';
            if (type === 'percent') {
                return '- ' + value + '%' + cond;
            }
            return '- ' + formatVoucherAmount(value) + cond;
        }

        // Title hiển thị cho voucher freeship: nếu là phần trăm thì vẫn hiển thị phần trăm nhưng tính toán dựa trên phí ship để gợi ý chính xác hơn, nếu có mệnh giá cố định thì dùng mệnh giá giống home_user
        function voucherShipTitle(voucher) {
            if (!voucher || typeof voucher !== 'object') return 'Ưu đãi';
            const type = String(voucher.type || '').toLowerCase();
            const value = Number(voucher.value || 0);
            const minVal = Number(voucher.min_subtotal || 0);
            const cond = minVal > 0 ? ' đơn từ ' + formatMinSubtotalShorthand(minVal) : '';
            if (type === 'percent') {
                const pct = Math.max(0, value);
                if (pct >= 100) return 'Miễn phí' + cond;
                return '- ' + (pct + '%') + cond;
            }
            return '- ' + formatVoucherAmount(value) + cond;
        }

        // Phân loại voucher để chọn 1 nhãn/màu hiển thị (ưu tiên: ship > payment > category > order)
        function voucherKind(voucher) {
            if (!voucher || typeof voucher !== 'object') return 'order';
            if (voucherTarget(voucher) === 'shipping') return 'shipping';
            const pm = String(voucher.payment_methods || '').trim();
            if (pm !== '' && pm !== '[]' && pm.toLowerCase() !== 'all') return 'payment';
            const scope = String(voucher.apply_scope || '').toLowerCase();
            if (scope === 'category') return 'category';
            return 'order';
        }

        // Cấu hình hiển thị cho từng loại vé
        const VOUCHER_KIND_META = {
            order: {
                cls: 'order',
                icon: 'bi-ticket-perforated-fill',
                label: 'Đơn hàng'
            },
            shipping: {
                cls: 'ship',
                icon: 'bi-truck',
                label: 'Vận chuyển'
            },
            category: {
                cls: 'category',
                icon: 'bi-tags-fill',
                label: 'Danh mục'
            },
            payment: {
                cls: 'payment',
                icon: 'bi-credit-card-2-front-fill',
                label: 'Thanh toán'
            },
        };

        function voucherBadgeTitle(voucher) {
            return voucherKind(voucher) === 'shipping' ? voucherShipTitle(voucher) : voucherTitle(voucher);
        }
        // Tính toán mức giảm giá của voucher giảm đơn dựa trên subtotal để gợi ý chính xác hơn
        function calcVoucherDiscount(voucher, subtotal) {
            const total = Number(subtotal || 0);
            if (!voucher || total <= 0) return 0;
            const min = Number(voucher.min_subtotal || 0);
            if (total < min) return 0;
            const type = String(voucher.type || '').toLowerCase();
            const value = Number(voucher.value || 0);
            let discount = 0;
            if (type === 'percent') {
                discount = total * value / 100;
                const maxDiscountRaw = voucher.max_discount;
                if (maxDiscountRaw !== null && maxDiscountRaw !== undefined && String(maxDiscountRaw) !== '') {
                    discount = Math.min(discount, Number(maxDiscountRaw || 0));
                }
            } else {
                discount = value;
            }
            return Math.max(0, Math.min(discount, total));
        }
        // Lấy voucher giảm đơn đã chọn, nếu có
        function getSelectedShipVoucher() {
            if (!selectedShipVoucherCode) return null;
            return voucherOptions.find(v => String(v.code || '') === selectedShipVoucherCode && voucherTarget(v) === 'shipping') || null;
        }
        // Tính toán mức giảm giá của voucher freeship dựa trên phí ship hiện tại và subtotal để gợi ý chính xác hơn
        function calcShipVoucherDiscount(voucher, fee, subtotal) {
            const shipFee = Number(fee || 0);
            if (!voucher || shipFee <= 0) return 0;
            const min = Number(voucher.min_subtotal || 0);
            if (subtotal < min) return 0;
            const type = String(voucher.type || '').toLowerCase();
            const value = Number(voucher.value || 0);
            let discount = 0;
            if (type === 'percent') {
                discount = shipFee * value / 100;
                const maxDiscountRaw = voucher.max_discount;
                if (maxDiscountRaw !== null && maxDiscountRaw !== undefined && String(maxDiscountRaw) !== '') {
                    discount = Math.min(discount, Number(maxDiscountRaw || 0));
                }
            } else {
                discount = value;
            }
            return Math.max(0, Math.min(discount, shipFee));
        }

        function getSelectedVoucher() {
            if (!selectedVoucherCode) return null;
            return voucherOptions.find(v => String(v.code || '') === selectedVoucherCode && voucherTarget(v) === 'order') || null;
        }
        // Cập nhật giao diện phần chọn mã giảm giá dựa trên danh sách mã hiện có và mã đã chọn, đồng thời ước lượng mức giảm giá để gợi ý mã tốt nhất
        function renderVoucherSelect() {
            const $section = $('#voucherSection');
            const $list = $('#voucherList');
            const $note = $('#voucherNote');
            if (!$section.length || !$list.length) return;

            if (!voucherOptions.length) {
                $section.hide();
                selectedVoucherCode = '';
                selectedShipVoucherCode = '';
                return;
            }

            const orderVouchers = voucherOptions.filter(v => voucherTarget(v) === 'order');
            const shipVouchers = voucherOptions.filter(v => voucherTarget(v) === 'shipping');

            // Tính tổng tiền hiện tại để ước lượng mức giảm tốt nhất
            const unitPrice = currentUnitPrice();
            const qty = getQty();
            const subtotal = Math.max(0, unitPrice * qty);

            // Chọn và tự động áp mã giảm giá đơn tốt nhất (dùng để tính giá)
            let bestOrder = null;
            let bestOrderDiscount = 0;
            orderVouchers.forEach(v => {
                const d = calcVoucherDiscount(v, subtotal);
                if (d > bestOrderDiscount) {
                    bestOrderDiscount = d;
                    bestOrder = v;
                }
            });
            if (!bestOrder && orderVouchers.length) {
                bestOrder = orderVouchers[0];
            }
            selectedVoucherCode = bestOrder ? String(bestOrder.code || '') : '';

            // Chọn và tự động áp mã freeship tốt nhất
            const methods = Array.isArray(shippingInfo.methods) ? shippingInfo.methods : [];
            const defaultKey = String(shippingInfo.default_method_key || '').trim().toLowerCase();
            const activeMethod = methods.find(m => m.key === selectedShipMethodKey) ||
                methods.find(m => m.key === defaultKey) ||
                methods.find(m => m.active) ||
                methods[0] ||
                null;
            const baseShipFee = activeMethod ? Number(activeMethod.fee_raw || 0) : 0;

            let bestShip = null;
            let bestShipDiscount = 0;
            shipVouchers.forEach(v => {
                const d = baseShipFee > 0 ? calcShipVoucherDiscount(v, baseShipFee, subtotal) : 0;
                if (d > bestShipDiscount) {
                    bestShipDiscount = d;
                    bestShip = v;
                }
            });
            if (!bestShip && shipVouchers.length) {
                bestShip = shipVouchers[0];
            }
            selectedShipVoucherCode = bestShip ? String(bestShip.code || '') : '';

            // Gom các vé hiển thị: ship + order (best mỗi loại) + danh mục + thanh toán.
            // Mỗi loại lấy tối đa 1 vé tiêu biểu, gộp chung 1 hàng, xếp sát trái.
            const displayed = [];
            const seen = new Set();
            const pushVoucher = (v) => {
                if (!v) return;
                const code = String(v.code || '');
                if (!code || seen.has(code)) return;
                seen.add(code);
                displayed.push(v);
            };

            pushVoucher(bestOrder);
            pushVoucher(bestShip);
            // Vé danh mục & thanh toán (lấy vé đầu tiên mỗi loại trong số chưa hiển thị)
            const categoryVoucher = voucherOptions.find(v => voucherKind(v) === 'category' && !seen.has(String(v.code || '')));
            pushVoucher(categoryVoucher);
            const paymentVoucher = voucherOptions.find(v => voucherKind(v) === 'payment' && !seen.has(String(v.code || '')));
            pushVoucher(paymentVoucher);

            const html = displayed.map(v => {
                const code = esc(String(v.code || ''));
                const kind = voucherKind(v);
                const meta = VOUCHER_KIND_META[kind] || VOUCHER_KIND_META.order;
                const title = esc(voucherBadgeTitle(v));
                const target = voucherTarget(v); // 'order' | 'shipping' cho logic chọn mã
                return `<button type="button" class="voucher-badge ${meta.cls}" data-code="${code}" data-target="${target}" data-kind="${kind}" title="${meta.label}"><span class="vc-ic"><i class="bi ${meta.icon}"></i></span><span class="vc-val">${title}</span></button>`;
            }).join('');

            $list.html(html || '<div class="text-muted small">Chưa có mã ưu đãi.</div>');

            // Đánh dấu vé đang áp (order & ship) là active
            if (selectedVoucherCode) {
                $list.find(`.voucher-badge[data-target="order"][data-code="${selectedVoucherCode}"]`).addClass('active');
            }
            if (selectedShipVoucherCode) {
                $list.find(`.voucher-badge[data-target="shipping"][data-code="${selectedShipVoucherCode}"]`).addClass('active');
            }

            $note.text('Chọn mã để xem giá sau giảm.').toggle(displayed.length > 0);
            $section.show();
        }
        // Tải danh sách mã giảm giá áp dụng cho sản phẩm này từ server và cập nhật giao diện
        function loadVoucherOptions() {
            $.get(VOUCHER_API, {
                ajax: 'vouchers_public',
                product_ids: [PID]
            }, (res) => {
                voucherOptions = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
                renderVoucherSelect();
                updatePriceBox();
            }).fail(() => {
                voucherOptions = [];
                renderVoucherSelect();
                updatePriceBox();
            });
        }
        // Đổi nhãn nút mua khi sản phẩm là "Hàng đặt trước"
        function applyPreorderButtonLabel(isPreorder) {
            const $btnBuyDesktop = $('#btnBuy');
            const $btnBuyMobile = $('#pfBuy');
            if (isPreorder) {
                $btnBuyDesktop.html('<i class="bi bi-clock-history me-1"></i> Đặt trước');
                $btnBuyMobile.text('Đặt trước');
                $('#preorderNote').removeClass('d-none');
            } else {
                if (!$btnBuyDesktop.data('relabeled-default')) {
                    $btnBuyDesktop.html('<i class="bi bi-bag-check me-1"></i> Mua ngay');
                    $btnBuyMobile.text('Mua Ngay');
                }
                $('#preorderNote').addClass('d-none');
            }
        }
        // Cập nhật trạng thái và nội dung của các nút hành động (Mua ngay, Thêm vào giỏ) dựa trên tình trạng kho hàng và giá cả hiện tại
        function updateActionButtons() {
            const isPreorder = !!(product && Number(product.preorder_enabled || 0) === 1);
            const stockOk = isPreorder || currentStockQty() > 0;
            const $btnBuy = $('#btnBuy, #pfBuy');
            const $btnAdd = $('#btnAdd, #pfAdd');

            // Mặc định: đảm bảo nút Thêm giỏ hiện lại khi còn hàng
            $btnAdd.show();

            // Nhãn nút "Mua ngay" -> "Đặt trước" khi là hàng đặt trước
            applyPreorderButtonLabel(isPreorder);

            // Variant is required for purchase.
            if (!Array.isArray(variants) || variants.length === 0) {
                //$btnBuy.html('<i class="bi bi-cart me-1"></i> Đã hết hàng');
                $btnBuy.prop('disabled', true);
                $btnBuy.attr('data-mode', 'buy');
                $btnAdd.prop('disabled', true).addClass('disabled');
                $btnAdd.attr('title', 'Sản phẩm chưa có phân loại (biến thể)');
                return;
            }
            if (!stockOk) {
                const href = getHotlineTelHref();
                // $btnBuy.html('<i class="bi bi-cart me-1"></i> Đã hết hàng');
                $btnBuy.prop('disabled', true);
                //$btnBuy.prop('disabled', !href);
                $btnBuy.attr('data-mode', href ? 'call' : 'buy');
                $btnAdd.prop('disabled', true).addClass('disabled');
                //$btnAdd.hide();
                $btnBuy.attr('title', href ? ('Liên hệ: ' + SITE_HOTLINE) : 'Sản phẩm hết hàng');
                $btnAdd.attr('title', 'Sản phẩm hết hàng');
                return;
            }

            if (REQUIRE_LOGIN_FOR_PURCHASE) {
                $btnBuy.prop('disabled', false);
                $btnBuy.attr('data-mode', 'buy');
                $btnAdd.prop('disabled', false).removeClass('disabled');
                $btnAdd.attr('title', 'Vui lòng đăng nhập để mua hàng');
                return;
            }

            if (priceAvailable) {
                //$btnBuy.html('<i class="bi bi-bag-check me-1"></i> Mua ngay');
                $btnBuy.prop('disabled', false);
                $btnBuy.attr('data-mode', 'buy');
                $btnAdd.prop('disabled', false).removeClass('disabled');
                $btnAdd.attr('title', '');
            } else {
                //$btnBuy.html('<i class="bi bi-telephone me-1"></i> Liên hệ đặt hàng');
                $btnBuy.prop('disabled', false);
                $btnBuy.attr('data-mode', 'buy');
                $btnAdd.prop('disabled', true).addClass('disabled');
                $btnAdd.attr('title', 'Sản phẩm chưa có giá, vui lòng liên hệ để đặt hàng');
            }
        }

        function setPriceAvailability(hasPrice) {
            priceAvailable = !!hasPrice;
            updateActionButtons();
        }
        // Cập nhật nội dung và trạng thái của ghi chú VAT dựa trên thông tin sản phẩm hiện tại
        function updateVatNote() {
            const noteEl = document.getElementById('vatNote');
            const percentEl = document.getElementById('vatPercentNote');
            if (!noteEl || !percentEl || !product) return;

            let vatEnabled = 1;
            if (typeof product.vat_enabled !== 'undefined' && product.vat_enabled !== null) {
                const ve = Number(product.vat_enabled);
                vatEnabled = Number.isNaN(ve) ? 1 : ve;
            }

            if (vatEnabled !== 1) {
                noteEl.style.display = 'none';
                return;
            }

            let vatRaw = (typeof product.vat !== 'undefined' && product.vat !== null && product.vat !== '') ?
                Number(product.vat) :
                Number(VAT_DEFAULT || 0);

            if (!Number.isFinite(vatRaw)) vatRaw = 0;
            if (vatRaw < 0) vatRaw = 0;
            if (vatRaw > 100) vatRaw = 100;

            let label = vatRaw.toFixed(2).replace(/\.0+$/, '').replace(/\.([^0-9]*)$/, (m, p1) => p1 ? '.' + p1 : '');
            label = label.replace(/\.0+$/, '').replace(/\.([1-9])0$/, '.$1');
            if (!label) {
                label = '0';
            }

            percentEl.textContent = label;
            noteEl.style.display = '';
        }
        // Cập nhật nội dung và trạng thái của footer tóm tắt đơn hàng dựa trên sản phẩm, biến thể, số lượng, và giá hiện tại
        function updateProductFooterSummary() {
            const $footer = $('#productFooter');
            if (!$footer.length || !product) return;

            const name = ($('#pName').text() || 'Sản phẩm').trim() || 'Sản phẩm';
            const qty = getQty();
            const unit = currentUnitPrice();
            const hasPrice = priceAvailable && Number(unit) > 0;

            $('#pfName').text(name);

            let first = '';
            let second = '';
            const $activeVariant = $('#variantWrap .variant-card.active .variant-title');
            if ($activeVariant.length) {
                const spans = $activeVariant.find('span');
                if (spans.length > 0) {
                    first = spans.eq(0).text().trim();
                    second = spans.eq(1).text().trim();
                } else {
                    first = $activeVariant.text().trim();
                }
            }

            let details = [];
            if (second) details.push(second);
            details.push('SL: ' + qty);

            const detailsText = details.length ? ' • ' + details.join(' • ') : '';
            const safeFirst = first ? esc(first) : 'Chưa chọn';

            $('#pfMeta').html(`<span class="pf-meta-name">${safeFirst}</span><span class="pf-meta-specs">${esc(detailsText)}</span>`);

            const total = Math.max(0, unit * qty);
            $('#pfPrice').text(hasPrice ? fmtPrice(total) : 'Liên hệ');

            const unitOld = currentUnitPriceOld() || unit;
            const totalOld = Math.max(0, unitOld * qty);
            const $pfPriceOriginal = $('#pfPriceOriginal');
            if ($pfPriceOriginal.length) {
                if (hasPrice && totalOld > total) {
                    $pfPriceOriginal.text(fmtPrice(totalOld)).show();
                } else {
                    $pfPriceOriginal.hide();
                }
            }

            let thumbUrl = '';
            // Ưu tiên hình ảnh theo phân loại đang chọn
            if (Array.isArray(variants) && variants.length && Number(selectedVariantId || 0) > 0) {
                const pickedVariant = variants.find(v => Number(v.id || 0) === Number(selectedVariantId || 0));
                if (pickedVariant) {
                    const rawImg = pickedVariant.image_url || pickedVariant.hinh_anh_variant || pickedVariant.image || pickedVariant.anh || (product && product.image_url) || FALLBACK_IMG;
                    thumbUrl = toAbs(rawImg);
                }
            }
            // Nếu chưa có, fallback về media gallery hoặc ảnh sản phẩm chung
            if (!thumbUrl) {
                if (Array.isArray(mediaItems) && mediaItems.length) {
                    thumbUrl = mediaItems[0].thumb || mediaItems[0].url || FALLBACK_IMG;
                } else if (product && product.image_url) {
                    thumbUrl = toAbs(product.image_url);
                } else {
                    thumbUrl = FALLBACK_IMG;
                }
            }
            $('#pfThumb').attr('src', esc(thumbUrl));
        }
        // Hiển thị hoặc ẩn footer tóm tắt đơn hàng khi cuộn trang, dựa trên vị trí của một phần tử kích hoạt (nếu có) hoặc ngưỡng cố định
        function handleProductFooterVisibility() {
            const $footer = $('#productFooter');
            if (!$footer.length || !product) {
                $footer.removeClass('show');
                return;
            }
            const scrollY = window.scrollY || window.pageYOffset || 0;
            let threshold = 260;
            const triggerEl = document.getElementById('overviewCard');
            if (triggerEl) {
                const rect = triggerEl.getBoundingClientRect();
                const top = rect.top + (window.scrollY || window.pageYOffset || 0);
                threshold = Math.max(200, top - 480);
            }
            if (scrollY > threshold) {
                $footer.addClass('show');
            } else {
                $footer.removeClass('show');
            }
        }
        // Cập nhật giá hiển thị dựa trên đơn vị giá hiện tại, số lượng, và voucher đã chọn
        function updatePriceBox() {
            const $priceOriginal = $('#priceOriginal');
            const $priceSave = $('#priceSave');
            const $voucherNote = $('#voucherNote');
            const $skeletonPrice = $('#skeletonPrice');

            if (!priceAvailable) {
                $skeletonPrice.hide();
                $('#priceTotal').text('Liên hệ').show();
                $('#specPrice').text('Liên hệ');
                if ($priceOriginal.length) $priceOriginal.hide();
                if ($priceSave.length) $priceSave.hide();
                if ($voucherNote.length) $voucherNote.text('Chọn mã để xem giá sau giảm.');
                updateProductFooterSummary();
                return;
            }
            const unit = currentUnitPrice();
            const unitOld = currentUnitPriceOld() || unit;
            const qty = getQty();

            const totalCurrent = Math.max(0, unit * qty);
            const totalOriginal = Math.max(0, unitOld * qty);

            const selectedVoucher = getSelectedVoucher();
            const voucherDiscount = calcVoucherDiscount(selectedVoucher, totalCurrent);

            const finalTotal = Math.max(0, totalCurrent - voucherDiscount);
            const totalSave = Math.max(0, totalOriginal - finalTotal);

            if ($priceOriginal.length) {
                if (totalOriginal > finalTotal) {
                    $priceOriginal.text(fmtPrice(totalOriginal)).show();
                } else {
                    $priceOriginal.hide();
                }
            }
            if ($priceSave.length) {
                if (totalSave > 0) {
                    $priceSave.text('Tiết kiệm ' + fmtPrice(totalSave)).show();
                } else {
                    $priceSave.hide();
                }
            }

            if ($voucherNote.length) {
                let noteText = '';
                if (lastClickedVoucherCode) {
                    if (selectedVoucher && String(selectedVoucher.code || '').toUpperCase() === lastClickedVoucherCode.toUpperCase()) {
                        if (voucherDiscount > 0) {
                            const detail = selectedVoucher.detail_text || '';
                            const promo = selectedVoucher.promo_note || '';
                            noteText = detail;
                            if (promo) {
                                noteText = noteText ? (noteText + ' - ' + promo) : promo;
                            }
                        } else {
                            const min = Number(selectedVoucher.min_subtotal || 0);
                            noteText = min > 0 ? ('Đơn tối thiểu ' + fmtPrice(min) + ' để áp dụng mã này') : 'Mã này hiện chưa áp dụng cho đơn hàng này';
                        }
                    } else {
                        const shipVoucher = getSelectedShipVoucher();
                        if (shipVoucher && String(shipVoucher.code || '').toUpperCase() === lastClickedVoucherCode.toUpperCase()) {
                            const detail = shipVoucher.detail_text || '';
                            const promo = shipVoucher.promo_note || '';
                            noteText = detail;
                            if (promo) {
                                noteText = noteText ? (noteText + ' - ' + promo) : promo;
                            }
                        }
                    }
                }

                if (!noteText && selectedVoucher) {
                    if (voucherDiscount > 0) {
                        const detail = selectedVoucher.detail_text || '';
                        const promo = selectedVoucher.promo_note || '';
                        noteText = detail;
                        if (promo) {
                            noteText = noteText ? (noteText + ' - ' + promo) : promo;
                        }
                    } else {
                        const min = Number(selectedVoucher.min_subtotal || 0);
                        noteText = min > 0 ? ('Đơn tối thiểu ' + fmtPrice(min) + ' để áp dụng mã này') : 'Mã này hiện chưa áp dụng cho đơn hàng này';
                    }
                }

                if (!noteText) {
                    const shipVoucher = getSelectedShipVoucher();
                    if (shipVoucher) {
                        const detail = shipVoucher.detail_text || '';
                        const promo = shipVoucher.promo_note || '';
                        noteText = detail;
                        if (promo) {
                            noteText = noteText ? (noteText + ' - ' + promo) : promo;
                        }
                    }
                }

                if (!noteText) {
                    noteText = 'Chọn mã để xem giá sau giảm.';
                }
                $voucherNote.text(noteText);
            }

            $skeletonPrice.hide();
            $('#priceTotal').text(fmtPrice(finalTotal)).show();
            if ($priceOriginal.length && totalOriginal > finalTotal) $priceOriginal.show();

            $('#specPrice').text(fmtPrice(unit));
            updateProductFooterSummary();
        }

        function parseFeeRaw(val, fallback) {
            const num = Number(val || 0);
            if (Number.isFinite(num) && num > 0) return num;
            const txt = String(fallback || '').replace(/[^0-9]/g, '');
            return Number(txt || 0);
        }
        // Chuẩn hoá dữ liệu phương thức vận chuyển từ API về một cấu trúc nhất quán, dễ dùng cho hiển thị và tính toán
        function normalizeShippingInfo(raw) {
            const methodsInput = Array.isArray(raw?.methods) ? raw.methods : [];
            const methods = methodsInput
                .filter(item => item && item.key)
                .map(item => ({
                    key: String(item.key || '').trim().toLowerCase(),
                    label: String(item.label || '').trim() || '—',
                    fee_text: String(item.fee_text || '').trim() || fmtPrice(item.fee || 0),
                    fee_raw: parseFeeRaw(item.fee, item.fee_text),
                    eta_text: String(item.eta_text || '').trim(),
                    policy_text: String(item.policy_text || '').trim(),
                    active: !!item.active
                }));
            const destination = String(raw?.destination || '').trim() || 'Chưa thiết lập địa chỉ giao hàng';
            const activeMethods = methods.filter(item => item.active);
            const preferredKey = String(raw?.default_method_key || '').trim().toLowerCase();
            const defaultMethod = activeMethods[0] || methods.find(item => item.key === preferredKey) || methods[0] || null;
            return {
                destination,
                methods,
                default_method_key: defaultMethod ? defaultMethod.key : '',
                default_fee_text: String(raw?.default_fee_text || '').trim() || (defaultMethod ? defaultMethod.fee_text : fmtPrice(0))
            };
        }

        window.setActiveGroup = (id) => {
            activeGroupId = id;
            renderVariants();
            // Sau khi lọc nhóm, tự động chọn biến thể đầu tiên khả dụng
            setTimeout(() => {
                const firstVariant = $('#variantWrap .variant-card:not(.out-of-stock)').first();
                if (firstVariant.length) {
                    firstVariant.trigger('click');
                } else {
                    // Nếu không có cái nào còn hàng, chọn cái đầu tiên bất kỳ
                    $('#variantWrap .variant-card').first().trigger('click');
                }
            }, 50);
        };

        window.scrollTabs = (dir) => {
            const el = document.getElementById('vgTabsScroll');
            if (el) el.scrollBy({
                left: dir,
                behavior: 'smooth'
            });
        };

        window.showAllVariants = (btn) => {
            const $btn = $(btn);
            const $row = $btn.closest('.variant-row');
            $row.find('.variant-card.d-none').removeClass('d-none');
            $btn.remove();
        };
        // Hàm xây dựng HTML cho một mục phương thức vận chuyển, có thể dùng lại cho cả danh sách trong modal và hiển thị tóm tắt
        function buildShipMethodItemHtml(method, active, feeTextOverride) {
            const eta = String(method?.eta_text || '').trim();
            let feeText = String(feeTextOverride || method?.fee_text || '').trim();
            const rawFee = Number(method?.fee_raw || 0);
            if (!feeText) {
                feeText = rawFee > 0 ? fmtPrice(rawFee) : 'Miễn phí';
            } else if (rawFee === 0) {
                feeText = 'Miễn phí';
            }
            return '<label class="ship-method-item ' + (active ? 'active' : '') + '" data-method="' + esc(method?.key || '') + '">' +
                '<div class="ship-method-main">' +
                '<div class="ship-method-name">' + esc(method?.label || '—') + '</div>' +
                '<div class="ship-method-meta">' + esc(eta || 'Giao hàng tiêu chuẩn') + '</div>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2">' +
                '<span class="ship-method-fee">' + esc(feeText) + '</span>' +
                '<input class="form-check-input ship-method-radio" type="radio" name="product_ship_method" value="' + esc(method?.key || '') + '" ' + (active ? 'checked' : '') + '>' +
                '</div>' +
                '</label>';
        }

        function renderShippingSummary(raw, applyVoucherPreview = true) {
            if (raw) {
                shippingInfo = normalizeShippingInfo(raw || {});
            }
            const methods = Array.isArray(shippingInfo.methods) ? shippingInfo.methods : [];
            if (!methods.length) {
                $('#pShipEtaText').html('Chưa có phương thức vận chuyển');
                $('#pShipFeeText').text(' Miễn phí vận chuyển');
                $('#shipModalDestination').text(shippingInfo.destination || 'Chưa thiết lập địa chỉ giao hàng');
                $('#shipModalMethodList').html('<div class="text-muted small">Chưa có phương thức vận chuyển.</div>');
                return;
            }

            const byKey = methods.find(item => item.key === selectedShipMethodKey);
            const activeMethods = methods.filter(item => item.active);
            const fallback = activeMethods[0] || methods.find(item => item.key === shippingInfo.default_method_key) || methods[0];
            const selectedMethod = byKey || fallback;
            selectedShipMethodKey = String(selectedMethod?.key || '');

            const etaText = String(selectedMethod?.eta_text || '').trim() || 'Nhận từ --/-- - --/--';
            const baseFeeRaw = Number(selectedMethod?.fee_raw || 0);
            let feeText = String(selectedMethod?.fee_text || '').trim();
            if (!feeText) {
                feeText = baseFeeRaw > 0 ? fmtPrice(baseFeeRaw) : 'Miễn phí';
            } else if (baseFeeRaw === 0) {
                feeText = 'Miễn phí';
            }
            const subtotalForVoucher = Math.max(0, Number(lastSubtotal || (currentUnitPrice() * getQty()) || 0));
            if (applyVoucherPreview) {
                const voucher = getSelectedShipVoucher();
                const feeRaw = Number(selectedMethod?.fee_raw || 0);
                if (voucher && feeRaw > 0) {
                    const discount = calcShipVoucherDiscount(voucher, feeRaw, subtotalForVoucher);
                    const finalFee = Math.max(0, feeRaw - discount);
                    if (finalFee > 0)
                        feeText = fmtPrice(finalFee);
                    else
                        feeText = 'Miễn phí';
                }
            }
            $('#pShipEtaText').text(etaText);
            $('#pShipFeeText').html('<i class="bi bi-truck"></i> Phí vận chuyển: ' + feeText);
            $('#shipModalDestination').text(shippingInfo.destination || 'Chưa thiết lập địa chỉ giao hàng');
            const renderList = methods.map(item => {
                const baseRaw = Number(item.fee_raw || 0);
                let itemFeeText = String(item.fee_text || '').trim();
                if (!itemFeeText) {
                    itemFeeText = baseRaw > 0 ? fmtPrice(baseRaw) : 'Miễn phí';
                } else if (baseRaw === 0) {
                    itemFeeText = 'Miễn phí';
                }
                if (applyVoucherPreview) {
                    const voucher = getSelectedShipVoucher();
                    const feeRaw = Number(item.fee_raw || 0);
                    if (voucher && feeRaw > 0) {
                        const discount = calcShipVoucherDiscount(voucher, feeRaw, subtotalForVoucher);
                        const finalFee = Math.max(0, feeRaw - discount);
                        itemFeeText = finalFee > 0 ? fmtPrice(finalFee) : 'Miễn phí';
                    }
                }
                return buildShipMethodItemHtml(item, item.key === selectedShipMethodKey, itemFeeText);
            }).join('');
            $('#shipModalMethodList').html(renderList || '<div class="text-muted small">Chưa có phương thức vận chuyển.</div>');
        }
        // Hàm này được gọi khi thay đổi số lượng hoặc đơn vị để cập nhật lại phí vận chuyển dự kiến
        function refreshShippingQuote() {
            if (!PID) return;
            const qty = getQty();
            const unit = currentUnitPrice();
            const subtotal = Math.max(0, unit * qty);
            const payload = [{
                product_id: PID,
                variant_id: Number(selectedVariantId || 0),
                qty,
                price: unit,
                weight_value: 0,
                length_cm: 0,
                width_cm: 0,
                height_cm: 0,
            }];
            lastSubtotal = subtotal;
            const req = {
                ajax: 'shipping_quote',
                subtotal,
                products_json: JSON.stringify(payload)
            };
            if (selectedShipMethodKey) {
                req.shipping_method = selectedShipMethodKey;
            }
            $.get(SHIPPING_API, req, (res) => {
                if (!res || !res.ok) {
                    console.error('[shipping] shipping_quote trả về không hợp lệ:', res);
                    return;
                }
                const quote = res.shipping_quote || {};
                renderShippingSummary({
                    destination: quote.destination || shippingInfo.destination,
                    methods: quote.methods || [],
                    default_method_key: quote.shipping_method || selectedShipMethodKey,
                    default_fee_text: quote.shipping_fee_text || fmtPrice(quote.shipping_fee || 0)
                }, true);
            }).fail((xhr) => {
                console.error('[shipping] Không gọi được shipping.php:', xhr.status, SHIPPING_API, xhr.responseText);
            });
        }

        function clearSearchHighlights(root) {
            if (!root) return;
            const marks = root.querySelectorAll('mark.search-highlight');
            marks.forEach(mark => {
                const parent = mark.parentNode;
                if (!parent) return;
                parent.replaceChild(document.createTextNode(mark.textContent || ''), mark);
                parent.normalize();
            });
        }

        function highlightInElement(root, keyword) {
            if (!root || !keyword) return 0;
            const needle = String(keyword).trim().toLocaleLowerCase('vi');
            if (!needle || needle.length < 2) return 0;

            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                acceptNode(node) {
                    if (!node || !node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                    const parent = node.parentElement;
                    if (!parent) return NodeFilter.FILTER_REJECT;
                    const tag = parent.tagName;
                    if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'MARK') return NodeFilter.FILTER_REJECT;
                    return NodeFilter.FILTER_ACCEPT;
                }
            });

            const textNodes = [];
            let current;
            while ((current = walker.nextNode())) {
                textNodes.push(current);
            }

            let hits = 0;
            textNodes.forEach(node => {
                const text = node.nodeValue || '';
                const hay = text.toLocaleLowerCase('vi');
                let start = 0;
                let idx = hay.indexOf(needle, start);
                if (idx < 0) return;

                const frag = document.createDocumentFragment();
                while (idx >= 0) {
                    if (idx > start) {
                        frag.appendChild(document.createTextNode(text.slice(start, idx)));
                    }
                    const mark = document.createElement('mark');
                    mark.className = 'search-highlight';
                    mark.textContent = text.slice(idx, idx + needle.length);
                    frag.appendChild(mark);
                    hits += 1;
                    start = idx + needle.length;
                    idx = hay.indexOf(needle, start);
                }
                if (start < text.length) {
                    frag.appendChild(document.createTextNode(text.slice(start)));
                }
                if (node.parentNode) {
                    node.parentNode.replaceChild(frag, node);
                }
            });

            return hits;
        }

        function renderCoatingSystemTable(layers) {
            const $cell = $('#specHeThongSon');
            const $tabWrap = $('#tabCoatingWrap');
            if (!$cell.length && !$tabWrap.length) return;
            const items = Array.isArray(layers) ? layers : [];
            if (!items.length) {
                const fallback = String(product && product.coating_system || '').trim();
                const fallhtml = `<div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                <th>Sản phẩm</th>
                <th>Phân loại</th>
                <th class="text-center" style="width:150px;">Số lớp đề nghị</th>
                </tr>
            </thead>
            <tbody>
            <tr>
                <td>-</td>
                <td>-</td>
                <td class="text-center">-</td>
            </tr>
            </tbody></table></div>`;

                if ($cell.length) $cell.text(fallback || '-');
                if ($tabWrap.length) {
                    $tabWrap.html(fallback ? '<div>' + esc(fallback) + '</div>' : fallhtml);
                }
                return;
            }
            let html = `<div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr>
            <!--th class="text-center" style="width:48px;">#</th-->
            <th>Sản phẩm</th>
            <th>Phân loại</th>
            <th class="text-center" style="width:150px;">Số lớp đề nghị</th>
            </tr></thead><tbody>`;
            items.forEach((row, idx) => {
                const name = String(row.suggest_product_name || row.category_name || '').trim() || 'Không có tên sản phẩm';
                const rawThumb = String(row.suggest_product_thumb || row.suggest_product_image_url || row.thumb || row.image_url || '').trim();
                const thumbSrc = rawThumb ? toAbs(rawThumb) : (String(FALLBACK_IMG || '').trim() || '');
                const type = String(row.layer_type || '').trim() || 'Không có phân loại';
                const count = Number(row.layer_count || 0) || 0;
                const thumbHtml = thumbSrc ? `<img src="${esc(thumbSrc)}" alt="" loading="lazy" decoding="async" style="width:40px;height:40px;object-fit:cover;border-radius:10px;border:1px solid var(--bs-border-color);">` : '';
                const nameHtml = `<div class="fw-semibold small">${esc(name)}</div>`;
                html += `<tr>
                <!--td class="text-center">${idx + 1}</td-->
                <td><div class="d-flex align-items-center gap-2">${thumbHtml}${nameHtml}</div></td>
                <td>${esc(type)}</td>
                <td class="text-center">${count > 0 ? count : ''}</td>
            </tr>`;
            });
            html += '</tbody></table></div>';
            if ($cell.length) $cell.html(html);
            if ($tabWrap.length) $tabWrap.html(html);
        }

        function renderConstructionPanel(construction) {
            const $specCell = $('#specConstruction');
            const $toolsCell = $('#tabConsTools');
            const $surfaceCell = $('#tabConsSurface');
            const $methodCell = $('#tabConsMethod');
            const row = construction || {};
            const parseTools = (raw) => {
                const txt = String(raw || '').trim();
                if (!txt) return {
                    enabled: false,
                    text: ''
                };
                if (txt.startsWith('{')) {
                    try {
                        const obj = JSON.parse(txt);
                        if (obj && typeof obj === 'object') {
                            const enabled = Number(obj.enabled || 0) === 1;
                            const text = String(obj.text || '').trim();
                            return {
                                enabled,
                                text
                            };
                        }
                    } catch (e) {}
                }
                return {
                    enabled: true,
                    text: txt
                };
            };
            const toolsObj = parseTools(row.tools || '');
            const tools = toolsObj.enabled ? String(toolsObj.text || '').trim() : '';
            const surfaceEnabled = Number(row.surface_prep_enabled || 0) === 1;
            const surfaceNew = String(row.surface_prep_new || '').trim();
            const surfaceOld = String(row.surface_prep_old || '').trim();
            const methodText = String(row.method_text || '').trim();
            // chấp nhận cả method_file (snake_case) và methodFile (camelCase) nếu backend đổi key
            const methodFileRaw = String(row.method_file || row.methodFile || '').trim();
            const parseMethodFiles = (raw) => {
                const txt = String(raw || '').trim();
                if (!txt) return [];
                if (txt.startsWith('[')) {
                    try {
                        const arr = JSON.parse(txt);
                        if (Array.isArray(arr)) {
                            return arr.map(x => String(x || '').trim()).filter(Boolean);
                        }
                    } catch (e) {}
                }
                return [txt];
            };
            const methodFiles = parseMethodFiles(methodFileRaw);

            const hasAny = !!(tools || surfaceEnabled || methodText || methodFiles.length);
            if (!hasAny) {
                if ($specCell.length) $specCell.text('-');
                if ($toolsCell.length) $toolsCell.text('-');
                if ($surfaceCell.length) $surfaceCell.text('-');
                if ($methodCell.length) $methodCell.text('-');
                return;
            }

            // Tóm tắt ngắn ở bảng thông số bên phải
            if ($specCell.length) {
                let summary = 'Đã cấu hình dữ liệu thi công';
                if (tools) summary += ' • Dụng cụ: ' + tools;
                $specCell.text(summary);
            }

            // Bảng chi tiết trong tab "Dữ liệu thi công"
            if ($toolsCell.length) {
                $toolsCell.text(tools || '-');
            }

            if ($surfaceCell.length) {
                if (!surfaceEnabled) {
                    $surfaceCell.text('-');
                } else {
                    let html = '';
                    if (surfaceNew) html += '<div><strong>Mới:</strong> ' + esc(surfaceNew) + '</div>';
                    if (surfaceOld) html += '<div><strong>Cũ:</strong> ' + esc(surfaceOld) + '</div>';
                    if (!html) html = 'Đã bật chuẩn bị bề mặt.';
                    $surfaceCell.html(html);
                }
            }

            if ($methodCell.length) {
                if (!methodFiles.length && !methodText) {
                    // Nếu không có cả hướng dẫn thi công lẫn file đính kèm
                    $methodCell.text('-');
                } else {
                    const parts = [];
                    if (methodText) {
                        const safeHtml = (typeof sanitizeRichHtml === 'function') ?
                            sanitizeRichHtml(methodText) :
                            esc(methodText);
                        parts.push('<div class="mb-3">' + safeHtml + '</div>');
                    }
                    if (methodFiles.length) {
                        const btns = methodFiles.map((p, idx) => {
                            const url = toAbs(p);
                            const label = methodFiles.length === 1 ? 'Tải về PDF' : ('Tải PDF ' + (idx + 1));
                            return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-2 mb-2">' + esc(label) + '</a>';
                        }).join('');
                        parts.push('<div>' + btns + '</div>');
                    }
                    $methodCell.html(parts.join(' '));
                }
            }
        }

        function applySearchHighlight(keyword) {
            const targets = ['#pName', '#pDesc', '#pDacTinh', '#pUngDung', '#specThongSo', '#specHeThongSon', '#specConstruction', '#specBaoQuan'];
            const term = String(keyword || '').trim();
            targets.forEach(sel => {
                const el = document.querySelector(sel);
                if (el) clearSearchHighlights(el);
            });
            if (!term || term.length < 2) return;
            targets.forEach(sel => {
                const el = document.querySelector(sel);
                if (el) highlightInElement(el, term);
            });
        }

        function generateToC() {
            const $desc = $('#pDesc');
            if (!$desc.length) return;
            $desc.find('#productToc').remove();
            const headings = $desc.find('h2, h3');
            if (headings.length < 3) return;

            let tocHtml = `
            <div class="toc-box" id="productToc">
                <div class="toc-header" id="tocToggle">
                    <h6 class="toc-title"><i class="bi bi-list-ul"></i>Nội dung chính</h6>
                    <i class="bi bi-chevron-down toc-toggle-icon"></i>
                </div>
                <div class="toc-body">
                    <ol class="toc-list toc-list-h2">
        `;

            let lastLevel = 2;
            headings.each(function(index) {
                const $h = $(this);
                const level = parseInt(this.tagName.substring(1));
                const title = $h.text().trim();
                if (!title) return;

                let id = $h.attr('id');
                if (!id) {
                    id = 'toc-' + (typeof window.pmSlugify === 'function' ? window.pmSlugify(title) : index);
                    $h.attr('id', id);
                }

                if (level === 2) {
                    if (lastLevel === 3) tocHtml += `</ol></li>`;
                    else if (index > 0) tocHtml += `</li>`;

                    tocHtml += `<li class="toc-item is-h2"><a href="#${id}" class="toc-link">${esc(title)}</a>`;
                    lastLevel = 2;
                } else if (level === 3) {
                    if (lastLevel === 2) tocHtml += `<ol class="toc-list toc-list-h3">`;

                    tocHtml += `<li class="toc-item is-h3"><a href="#${id}" class="toc-link">${esc(title)}</a></li>`;
                    lastLevel = 3;
                }
            });

            if (lastLevel === 3) tocHtml += `</ol></li>`;
            else tocHtml += `</li>`;

            tocHtml += `</ol></div></div>`;

            // Chèn vào đầu nội dung
            $desc.prepend(tocHtml);

            // Event đóng mở
            $('#tocToggle').on('click', function() {
                $('#productToc').toggleClass('is-collapsed');
            });

            // Smooth scroll
            $('#productToc .toc-link').on('click', function(e) {
                e.preventDefault();
                const targetId = $(this).attr('href');
                const $target = $(targetId);
                if (!$target.length) return;

                const offset = 100;
                // Cuộn tới đích. Vị trí offset().top được tính NGAY trước khi animate,
                // nên phải đợi layout ổn định (transition giãn card xong) mới gọi.
                const doScroll = () => {
                    $('html, body').animate({
                        scrollTop: $target.offset().top - offset
                    }, 500);
                };

                // Nếu phần mô tả đang thu gọn (chưa "Xem thêm") thì mở rộng trước,
                // vì heading đích có thể nằm trong vùng bị cắt -> không cuộn tới được.
                const $card = $('#overviewCard');
                const needExpand = $card.length && !$card.hasClass('is-expanded');
                if (!needExpand) {
                    doScroll();
                    return;
                }

                $card.addClass('is-expanded');
                const $btn = $('#overviewToggle');
                if ($btn.length) {
                    $btn.text('Thu gọn').attr('aria-expanded', 'true');
                }

                // Đợi transition max-height của .overview-body kết thúc rồi mới cuộn,
                // để offset().top phản ánh đúng vị trí cuối cùng (tránh cuộn lệch, nhất là mục cuối).
                const $body = $('#overviewBody');
                let scrolled = false;
                const fire = () => {
                    if (scrolled) return;
                    scrolled = true;
                    $body.off('transitionend', onEnd);
                    doScroll();
                };
                const onEnd = (ev) => {
                    if (ev.originalEvent && ev.originalEvent.propertyName !== 'max-height') return;
                    fire();
                };
                $body.on('transitionend', onEnd);
                // Fallback: nếu vì lý do nào đó transitionend không bắn (đã ở max-height,
                // bị giảm chuyển động...) thì vẫn cuộn sau thời gian transition (.3s) + đệm.
                setTimeout(fire, 380);
            });
        }

        function checkOverviewHeight() {
            const $card = $('#overviewCard');
            const $body = $('#overviewBody');
            const $btn = $('#overviewToggle');
            if (!$card.length || !$body.length || !$btn.length) return;

            setTimeout(() => {
                const threshold = 220;
                const realHeight = $body[0].scrollHeight;
                if (realHeight <= threshold + 30) {
                    $card.addClass('is-expanded');
                    $btn.parent().hide();
                } else {
                    $card.removeClass('is-expanded');
                    $btn.parent().show();
                    $btn.text('Xem thêm').attr('aria-expanded', 'false');
                }
            }, 150);
        }

        function initOverviewToggle() {
            const $card = $('#overviewCard');
            const $btn = $('#overviewToggle');
            if (!$card.length || !$btn.length) return;

            $btn.on('click', function() {
                const expanded = $card.hasClass('is-expanded');
                $card.toggleClass('is-expanded', !expanded);
                $(this).attr('aria-expanded', !expanded ? 'true' : 'false');
                $(this).text(!expanded ? 'Thu gọn' : 'Xem thêm');

                // Cuộn lên đầu card nếu thu gọn
                if (expanded) {
                    $card[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });

            generateToC();
            checkOverviewHeight();
        }




        function ensureShippingInfoModal() {
            if (!shippingInfoModalInstance) {
                const modalEl = document.getElementById('shippingInfoModal');
                if (modalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                    shippingInfoModalInstance = new window.bootstrap.Modal(modalEl);
                }
            }
            return shippingInfoModalInstance;
        }

        function ensureReviewModal() {
            if (!reviewModalInstance) {
                const modalEl = document.getElementById('reviewModal');
                if (modalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                    reviewModalInstance = new window.bootstrap.Modal(modalEl);
                }
                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        $('#commentsActions').removeClass('show');
                        resetReviewForm();
                    });
                }
            }
            return reviewModalInstance;
        }

        function openReviewModal(mode = 'review') {
            const modal = ensureReviewModal();
            $('#commentsActions').addClass('show');
            const $modal = $('#reviewModal');

            if (mode === 'comment') {
                $modal.find('.modal-title').html('<i class="bi bi-question-circle me-2"></i>Đặt câu hỏi');
                $modal.find('.comments-add__rate').hide();
                $modal.find('#reviewContent').attr('placeholder', 'Hãy đặt câu hỏi cho chúng tôi...');
                $modal.find('#qaSuggestions').show();
                $modal.find('#reviewSuggestions').hide();
                setReviewRating(0);
            } else {
                $modal.find('.modal-title').html('<i class="bi bi-chat-square-text me-2"></i>Viết đánh giá');
                $modal.find('.comments-add__rate').show();
                $modal.find('#reviewContent').attr('placeholder', 'Hãy để lại bình luận hoặc đánh giá của bạn tại đây!');
                $modal.find('#qaSuggestions').hide();
                $modal.find('#reviewSuggestions').show();
            }

            if (modal && modal.show) {
                modal.show();
            } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
                $modal.modal('show');
            }
        }

        function openShippingInfoModal() {
            // Reset modal to default info view
            $('#shipModalEditView').addClass('d-none');
            $('#shipModalInfoView').removeClass('d-none');
            $('#shipModalFooter').removeClass('d-none');

            const modal = ensureShippingInfoModal();
            if (modal && modal.show) {
                modal.show();
            } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
                $('#shippingInfoModal').modal('show');
            }
        }



        // Tính độ sáng tương đối của mã màu HEX, trả về số trong khoảng 0-255 hoặc null nếu không hợp lệ
        const getHexLuminance = (hex) => {
            if (!hex) return null;
            let v = String(hex).trim().replace('#', '');
            if (v.length === 3) v = v.split('').map(x => x + x).join('');
            if (v.length !== 6) return null;
            const r = parseInt(v.substring(0, 2), 16);
            const g = parseInt(v.substring(2, 4), 16);
            const b = parseInt(v.substring(4, 6), 16);
            if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) return null;
            return 0.299 * r + 0.587 * g + 0.114 * b;
        };



        // Hàm này được gọi khi chọn một biến thể để cập nhật lại giá, kho, và các thông tin liên quan đến biến thể đó
        const currentStockQty = () => variants.length ? selectedVariantStock : productStockBase;
        // Cập nhật trạng thái kho hàng hiển thị và trạng thái của nút thêm vào giỏ dựa trên số lượng tồn kho hiện tại
        function updateStockStatus() {
            const $label = $('#stockStatus');
            if (!$label.length) return;
            const stock = currentStockQty();
            const isPreorder = !!(product && Number(product.preorder_enabled || 0) === 1);
            $label.removeClass('text-muted text-success text-danger text-warning');

            // V9: Cập nhật max attribute của input qty theo tồn kho thực tế
            const $qtyInput = $('#qty');
            if ($qtyInput.length) {
                if (!isPreorder && stock > 0) {
                    const maxAllowed = Math.min(stock, 100); // Server cũng giới hạn 100
                    $qtyInput.attr('max', maxAllowed);
                    // Clamp giá trị hiện tại nếu vượt tồn kho
                    const cur = parseInt($qtyInput.val(), 10) || 1;
                    if (cur > maxAllowed) $qtyInput.val(maxAllowed);
                } else {
                    $qtyInput.removeAttr('max');
                }
            }

            if (stock > 0) {
                $label.html('Kho: <b>' + stock + '</b> sản phẩm').addClass('text-success');
            } else if (isPreorder) {
                $label.html('<i class="bi bi-clock-history me-1"></i>Hàng đặt trước').addClass('text-warning');
            } else {
                $label.text('Hết hàng').addClass('text-danger');
            }
            updateActionButtons();
        }


        // BXGY selector dropdown (chi tiết sản phẩm)
        let $bxgyDropdownDetail = null;

        function hideBxgyDropdownDetail() {
            if (!$bxgyDropdownDetail) $bxgyDropdownDetail = $('#bxgyGiftDropdownDetail');
            if (!$bxgyDropdownDetail.length) return;
            $bxgyDropdownDetail.addClass('d-none').removeAttr('style');
            $('#bxgyGiftDropdownDetailBody').empty();
            $('#promoBxgyGrid .bxgy-selector.is-open').removeClass('is-open');
            bxgyDropdownStateDetail = null;
        }

        // Mở dropdown khi click pill bxgy-selector
        $('#promoBxgyGrid').on('click', '.bxgy-selector', function(e) {
            e.stopPropagation();
            const $pill = $(this);
            const promoId = Number($pill.data('promo_id') || 0);
            if (!promoId) return;
            const promo = BXGY_PROMOS_DETAIL[promoId] || null;
            if (!promo) return;

            bxgyDropdownStateDetail = {
                promoId
            };
            if (!$bxgyDropdownDetail) $bxgyDropdownDetail = $('#bxgyGiftDropdownDetail');
            if (!$bxgyDropdownDetail.length) return;
            const $body = $('#bxgyGiftDropdownDetailBody');
            $body.html('<div class="py-2 text-center text-muted small">Đang tải lựa chọn quà...</div>');

            $('#promoBxgyGrid .bxgy-selector.is-open').removeClass('is-open');
            $pill.addClass('is-open');

            const offset = $pill.offset();
            const height = $pill.outerHeight() || 0;
            const width = $pill.outerWidth() || 0;
            let left = offset.left;
            const top = offset.top + height + 6; // đặt ngay bên dưới pill, không trừ scrollTop vì dropdown dùng position:absolute theo body

            $bxgyDropdownDetail.removeClass('d-none');
            $bxgyDropdownDetail.css({
                top: top + 'px',
                left: left + 'px',
                minWidth: width + 'px'
            });

            const dropdownWidth = $bxgyDropdownDetail.outerWidth() || 0;
            const viewportWidth = $(window).width() || 0;
            if (left + dropdownWidth + 8 > viewportWidth) {
                left = Math.max(8, viewportWidth - dropdownWidth - 8);
                $bxgyDropdownDetail.css({
                    left: left + 'px'
                });
            }

            const gifts = promoGiftProducts(promo);
            if (!gifts.length) {
                $body.html('<div class="px-3 py-2 text-muted small">Không có quà tặng khả dụng.</div>');
                return;
            }

            $body.html('<div class="px-3 pt-2 pb-1 text-muted small">Chọn quà tặng và phân loại:</div>');
            const currentGiftPid = Number($pill.data('gift_pid') || 0);
            const currentGiftVid = Number($pill.data('gift_vid') || 0);

            gifts.forEach((g) => {
                const gid = Number(g.product_id || g.id || 0);
                if (!gid) return;
                const giftName = String(g.name || g.product_name || ('Quà #' + gid)).trim();
                const baseThumb = String(g.thumb || g.image_url || '').trim();
                const allowedIds = getAllowedGiftVariantIds(promo, gid);

                ensureGiftVariantsLoaded(gid).then((variants) => {
                    const list = Array.isArray(variants) ? variants : [];
                    let filtered = list;
                    if (allowedIds.length) {
                        const allowedSet = new Set(allowedIds.map(x => Number(x || 0)));
                        filtered = list.filter(v => allowedSet.has(Number(v && v.id ? v.id : 0)));
                    }

                    const safeName = $('<div>').text(giftName).html();
                    const safeThumb = baseThumb ? $('<div>').text(toAbs(baseThumb)).html() : '';
                    // Nếu filtered rỗng-> vẫn hiển thị sản phẩm chính
                    if (!filtered.length) {
                        const isCurrent = (gid === currentGiftPid) && (!currentGiftVid || currentGiftVid === 0);
                        const badge = isCurrent ? ' <span class="badge bg-success ms-1">Đang chọn</span>' : '';
                        const thumbHtml = safeThumb ? `<img class="me-2" src="${safeThumb}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;background:#f8fafc;" alt="Quà" loading="lazy" decoding="async">` : '<span class="me-2">🎁</span>';
                        $body.append(`
                        <button type="button" class="cart-variant-option bxgy-option" data-gift_pid="${gid}" data-gift_vid="0" data-gift_name="${safeName}" data-variant_label="" data-img="${safeThumb}">
                            <span class="d-flex align-items-center flex-grow-1">
                                ${thumbHtml}
                                <span>${badge} ${safeName}</span>
                                <span class="fw-semibold ms-2 text-success">Miễn phí</span>
                            </span>
                        </button>
                    `);
                        return;
                    }
                    // Nếu filtered không rỗng -> hiển thị từng biến thể thỏa điều kiện
                    filtered.forEach((v) => {
                        const vid = Number(v.id || 0);
                        if (!vid) return;
                        const label = (v.variant_name || '').trim() || 'Mặc định';
                        const price = Number(v.price || 0);
                        const isCurrent = (gid === currentGiftPid) && (currentGiftVid > 0 ? (vid === currentGiftVid) : false);
                        const safeLabel = $('<div>').text(label).html();
                        const variantThumb = String(v.image_url || baseThumb || '').trim();
                        const safeVThumb = variantThumb ? $('<div>').text(toAbs(variantThumb)).html() : safeThumb;
                        const badge = isCurrent ? ' <span class="badge bg-success ms-1">Đang chọn</span>' : '';
                        const thumbHtml = safeVThumb ? `<img class="me-2" src="${safeVThumb}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;background:#f8fafc;" alt="Quà" loading="lazy" decoding="async">` : '<span class="me-2">🎁</span>';
                        $body.append(`
                        <button type="button" class="cart-variant-option bxgy-option" data-gift_pid="${gid}" data-gift_vid="${vid}" data-gift_name="${safeName}" data-variant_label="${safeLabel}" data-img="${safeVThumb}">
                            <span class="d-flex align-items-center flex-grow-1 text-start">
                                ${thumbHtml}
                                <span>
                                    <div class="small fw-semibold text-dark">${badge} ${safeName}</div>
                                    <div class="small text-muted">${safeLabel}</div>
                                    <span class="fw-semibold text-success">${fmtPrice(price)}</span>
                                </span>
                            </span>
                        </button>
                    `);
                    });
                });
            });
        });

        // Áp dụng lựa chọn quà BXGY cho giỏ hàng
        function applyBxgyChoiceToCart(promoId, giftPid, giftVariantId) {
            promoId = Number(promoId || 0);
            giftPid = Number(giftPid || 0);
            giftVariantId = Number(giftVariantId || 0);
            if (!promoId || !giftPid) return;

            // Lấy cấu hình promo để biết số lượng mua tối thiểu (buy_qty)
            const promoCfg = (BXGY_PROMOS_DETAIL && BXGY_PROMOS_DETAIL[promoId]) ? BXGY_PROMOS_DETAIL[promoId] : null;
            const minBuyQty = promoCfg && Number(promoCfg.buy_qty || 0) > 0 ? Number(promoCfg.buy_qty || 0) : 1;

            // Lấy giỏ hàng hiện tại để tìm dòng sản phẩm chính tương ứng (PID + selectedVariantId)
            $.get(API, {
                ajax: 'cart_get'
            }, function(res) {
                const cart = (res && res.ok && Array.isArray(res.data)) ? res.data : [];
                const basePid = Number(PID || 0);
                const baseVid = Number(selectedVariantId || 0);
                let targetItem = null;
                cart.forEach(function(it) {
                    if (targetItem) return;
                    if (!it || it.is_gift || it.is_combo) return;
                    const pidIt = Number(it.product_id || it.id || 0);
                    if (pidIt !== basePid) return;
                    const vidIt = Number(it.variant_id || 0);
                    if (baseVid > 0 && vidIt !== baseVid) return;
                    targetItem = it;
                });

                // Nếu chưa có sản phẩm chính trong giỏ -> tự thêm sản phẩm (cùng lựa chọn quà) vào giỏ,
                // đảm bảo số lượng ít nhất bằng buy_qty của chương trình
                if (!targetItem) {
                    const currentQty = Math.max(1, parseInt($('#qty').val(), 10) || 1);
                    const desiredQty = currentQty < minBuyQty ? minBuyQty : currentQty;
                    $('#qty').val(desiredQty);
                    updateStockStatus();
                    addToCart('add');
                    return;
                }

                const key = String(targetItem.key || '').trim();
                if (!key) return;
                const currentQty = Number(targetItem.qty || 1);
                const desiredQty = currentQty < minBuyQty ? minBuyQty : currentQty;

                const doSetBxgyChoice = function() {
                    $.post(API, {
                        action: 'cart_set_bxgy_choice',
                        key: key,
                        promo_id: promoId,
                        gift_pid: giftPid,
                        gift_variant_id: giftVariantId
                    }, function(resp) {
                        if (!resp || !resp.ok) {
                            notify((resp && resp.msg) || 'Không cập nhật được quà tặng trong giỏ', 'error');
                            return;
                        }
                        if (window.refreshCartBadge) window.refreshCartBadge();
                        if (window.renderMiniCartPopup) window.renderMiniCartPopup(resp.data || r1.data || [], (product ? (product.product_name || product.name) : ''));
                        notify('Đã cập nhật quà tặng trong giỏ hàng.', 'success');
                    }, 'json').fail(function() {
                        notify('Lỗi kết nối server khi cập nhật quà tặng', 'error');
                    });
                };

                // Nếu số lượng hiện tại nhỏ hơn buy_qty, cập nhật lại trước rồi mới áp dụng quà
                if (desiredQty !== currentQty) {
                    $.post(API, {
                        action: 'cart_update_qty',
                        key: key,
                        qty: desiredQty
                    }, function(r1) {
                        if (!r1 || !r1.ok) {
                            notify((r1 && r1.msg) || 'Không cập nhật được số lượng cho chương trình Mua X Tặng Y', 'error');
                            return;
                        }
                        if (window.refreshCartBadge) window.refreshCartBadge();
                        if (window.renderMiniCartPopup) window.renderMiniCartPopup(r1.data || [], (product ? (product.product_name || product.name) : ''));
                        doSetBxgyChoice();
                    }, 'json').fail(function() {
                        notify('Lỗi kết nối server khi cập nhật số lượng cho chương trình Mua X Tặng Y', 'error');
                    });
                } else {
                    doSetBxgyChoice();
                }
            }).fail(function() {
                // Nếu không lấy được giỏ, bỏ qua (không làm hỏng trải nghiệm chọn quà)
            });
        }

        // Chọn quà + phân loại trong dropdown BXGY (cập nhật pill + state BXGY_PROMOS_DETAIL, đồng thời cập nhật giỏ)
        $('#bxgyGiftDropdownDetailBody').on('click', '.bxgy-option', function(e) {
            e.stopPropagation();
            if (!bxgyDropdownStateDetail) return;
            const gid = Number($(this).data('gift_pid') || 0);
            const vid = Number($(this).data('gift_vid') || 0);
            const giftName = String($(this).data('gift_name') || '').trim();
            const variantLabel = String($(this).data('variant_label') || '').trim();
            const giftImg = String($(this).data('img') || '').trim();
            if (!gid) return;
            const {
                promoId
            } = bxgyDropdownStateDetail;
            if (!promoId) return;

            BXGY_PROMOS_DETAIL[promoId] = BXGY_PROMOS_DETAIL[promoId] || {};
            BXGY_PROMOS_DETAIL[promoId].selectedGiftPid = gid;
            BXGY_PROMOS_DETAIL[promoId].selectedGiftVid = vid;

            const $pill = $('#promoBxgyGrid .bxgy-selector[data-promo_id="' + promoId + '"]');
            if ($pill.length) {
                $pill.attr('data-gift_pid', String(gid));
                $pill.attr('data-gift_vid', String(vid));
                const $current = $pill.find('.bxgy-selector-current');
                if ($current.length) {
                    if (variantLabel) {
                        $current.html(giftName + ' · ' + variantLabel);
                    } else {
                        $current.html(giftName);
                    }
                }
                // Cập nhật ảnh sản phẩm/phân loại đã chọn vào thumb của selector
                const $thumb = $pill.find('.bxgy-selector-thumb');
                if ($thumb.length) {
                    if (giftImg) {
                        $thumb.html('<img src="' + giftImg + '" alt="Quà" loading="lazy" decoding="async" onerror="this.parentNode.innerHTML=\'<i class=\\\'bi bi-gift-fill\\\'></i>\'">');
                    } else {
                        $thumb.html('<i class="bi bi-gift-fill"></i>');
                    }
                }
            }
            hideBxgyDropdownDetail();
            applyBxgyChoiceToCart(promoId, gid, vid);
        });

        function renderVariants() {
            const $wrap = $('#variantWrap');
            const $section = $('#variantSection');
            if (!variants.length) {
                $section.show();
                $('#variantEmpty').text('Hiện chưa có phân loại phù hợp cho bạn.').show();
                $wrap.empty();
                selectedVariantId = 0;
                selectedVariantPrice = 0;
                selectedVariantStock = 0;
                selectedVariantSku = '';
                setPriceAvailability(false);
                updatePriceBox();
                updateStockStatus();
                updateSkuDisplay();
                return;
            }
            $section.show();
            $('#variantEmpty').hide();

            // Sắp xếp các biến thể theo thứ tự đã định (sort_order) hoặc theo giá
            const sortedVariants = [...variants].sort((a, b) => {
                const sa = Number(a.sort_order ?? a.stt ?? 0);
                const sb = Number(b.sort_order ?? b.stt ?? 0);
                if (sa !== sb) return sa - sb;

                const pa = Number(a.price || 0);
                const pb = Number(b.price || 0);
                if (pa <= 0 && pb > 0) return 1;
                if (pb <= 0 && pa > 0) return -1;
                if (pa !== pb) return pa - pb;
                return Number(a.id || 0) - Number(b.id || 0);
            });

            // Group variants by group_id
            const groupMap = {};
            const noGroupKey = '__none__';
            if (groups && groups.length > 0) {
                groups.forEach(g => {
                    groupMap[String(g.id)] = {
                        name: g.name,
                        variants: []
                    };
                });
            }
            sortedVariants.forEach(v => {
                const gid = v.group_id != null ? String(v.group_id) : noGroupKey;
                if (!groupMap[gid]) groupMap[gid] = {
                    name: null,
                    variants: []
                };
                groupMap[gid].variants.push(v);
            });

            // Determine which variant to select and which group to display
            let bestV = null;

            // If first load or no selection, prioritize selecting the first variant in the first available group
            if (!selectedVariantId) {
                // Find the first group (by sort_order) that contains at least one variant
                const firstGroup = (groups || []).find(g => groupMap[String(g.id)] && groupMap[String(g.id)].variants.length);
                let targetGroupVariants = [];

                if (firstGroup) {
                    activeGroupId = String(firstGroup.id);
                    targetGroupVariants = groupMap[activeGroupId].variants;
                } else if (groupMap[noGroupKey] && groupMap[noGroupKey].variants.length) {
                    activeGroupId = noGroupKey;
                    targetGroupVariants = groupMap[noGroupKey].variants;
                }

                if (targetGroupVariants.length > 0) {
                    // Select the first variant in this group
                    bestV = targetGroupVariants[0];
                    selectedVariantId = Number(bestV.id);
                } else if (sortedVariants.length > 0) {
                    // Fallback to the globally best variant if no groups exist
                    bestV = sortedVariants.find(v => Number(v.stock_quantity ?? v.kho ?? 0) > 0) || sortedVariants[0];
                    selectedVariantId = Number(bestV.id);
                    if (bestV.group_id != null) activeGroupId = String(bestV.group_id);
                }
            }

            // Determine displayGid
            let displayGid = activeGroupId;
            if (displayGid === 'all' || !groupMap[displayGid] || !groupMap[displayGid].variants.length) {
                // Default to first group that has variants
                const firstWithVars = (groups || []).find(g => groupMap[String(g.id)] && groupMap[String(g.id)].variants.length);
                displayGid = firstWithVars ? String(firstWithVars.id) : (groupMap[noGroupKey] && groupMap[noGroupKey].variants.length ? noGroupKey : 'all');
                activeGroupId = displayGid;
            }

            // Ensure selected variant is in the displayed group
            const currentGroupVars = (groupMap[displayGid] || {}).variants || [];
            const isCurrentInGroup = currentGroupVars.some(v => Number(v.id) === selectedVariantId);
            if (!isCurrentInGroup && currentGroupVars.length > 0) {
                // If user switched group and previous selection is not in it, pick best in this group
                bestV = currentGroupVars.find(v => Number(v.stock_quantity ?? v.kho ?? 0) > 0) || currentGroupVars[0];
                selectedVariantId = Number(bestV.id);
            } else if (!bestV) {
                // Find current selected object
                bestV = variants.find(v => Number(v.id) === selectedVariantId);
            }

            // Update global selection state from bestV
            if (bestV) {
                selectedVariantPrice = Number(bestV.price || 0);
                selectedVariantPriceOld = Number(bestV.price_old || bestV.gia_cu || 0);
                selectedVariantStock = Number(bestV.stock_quantity ?? bestV.kho ?? bestV.stock ?? bestV.ton_kho ?? 0);
                selectedVariantSku = skuOfVariant(bestV);
                selectedVariantShipping = {
                    weight_value: Math.max(0, Number(bestV.shipping_weight_value ?? 1)),
                    weight_unit: String(bestV.shipping_weight_unit || 'kg').toLowerCase(),
                    length_cm: Math.max(1, Number(bestV.shipping_length_cm ?? 20)),
                    width_cm: Math.max(1, Number(bestV.shipping_width_cm ?? 20)),
                    height_cm: Math.max(1, Number(bestV.shipping_height_cm ?? 20)),
                };
            }
            // Map variant_id -> nhãn KM (để gắn badge nổi bật cho phân loại có khuyến mãi)
            const promoBadgeMap = getPromoVariantBadgeMap();

            // formatWeight is now defined globally around line 1127
            const renderCard = (v, isHidden = false) => {
                const wLabel = formatWeight(v.shipping_weight_value, v.shipping_weight_unit);
                const label = v.variant_name; // || ''} ${wLabel}`.trim() || 'Biến thể'
                const price = Number(v.price || 0);
                const priceOld = Number(v.price_old || v.gia_cu || 0);
                const stock = Number(v.stock_quantity ?? v.kho ?? v.stock ?? v.ton_kho ?? 0);
                const rawImg = v.image_url || v.hinh_anh_variant || v.image || v.anh || (product && product.image_url) || FALLBACK_IMG;
                const imgUrl = esc(toAbs(rawImg));
                const isActive = Number(v.id) === selectedVariantId;
                const vSku = skuOfVariant(v);
                const promoLabel = promoBadgeMap[Number(v.id || 0)] || '';
                const promoBadge = promoLabel ?
                    `<span class="variant-promo-badge" title="Phân loại đang có khuyến mãi">SALE</span>` :
                    '';

                return `<div class="variant-card ${isActive ? 'active' : ''} ${stock <= 0 ? 'out-of-stock' : ''} ${isHidden ? 'd-none' : ''}"
                data-id="${v.id}" data-price="${price}" data-price_old="${priceOld}" data-stock="${stock}" data-sku="${esc(vSku)}">
                <span class="variant-img-wrap">
                    <img src="${imgUrl}" class="variant-img" loading="lazy" onerror="this.src='${FALLBACK_IMG}'">
                    ${promoBadge}
                </span>
                <div class="variant-title">
                <span>${esc(label)}</span><br>
                <span style="font-size: 10px;color: #7a7d85">${wLabel ? 'Dung tích: ' + esc(wLabel) : ''}</span>
                </div>

            </div>`;
            };

            let html = '';
            const hasNamedGroups = groups && groups.length > 0;

            if (hasNamedGroups) {
                html += `<div class="vg-tabs-wrapper">
                <div class="vg-nav-btn prev" onclick="scrollTabs(-150)"><i class="bi bi-chevron-left"></i></div>
                <div class="vg-tabs" id="vgTabsScroll">`;
                groups.forEach(g => {
                    const gid = String(g.id);
                    if (!groupMap[gid] || !groupMap[gid].variants || !groupMap[gid].variants.length) return;
                    const isActiveTab = (displayGid === gid);
                    html += `<div class="vg-tab ${isActiveTab ? 'active' : ''}" onclick="setActiveGroup('${gid}')">${esc(g.name)}</div>`;
                });
                if (groupMap[noGroupKey] && groupMap[noGroupKey].variants.length) {
                    const isActiveTab = (displayGid === noGroupKey);
                    html += `<div class="vg-tab ${isActiveTab ? 'active' : ''}" onclick="setActiveGroup('${noGroupKey}')">Khác</div>`;
                }
                html += `</div>
                <div class="vg-nav-btn next" onclick="scrollTabs(150)"><i class="bi bi-chevron-right"></i></div>
            </div>`;

                const activeGroupVariants = (groupMap[displayGid] || {}).variants || [];
                html += `<div class="variant-row">`;
                activeGroupVariants.forEach((v, idx) => {
                    html += renderCard(v, idx >= 6);
                });
                if (activeGroupVariants.length > 6) {
                    html += `<div class="variant-show-more" onclick="showAllVariants(this)">+${activeGroupVariants.length - 6} sản phẩm</div>`;
                }
                html += `</div>`;
            } else {
                html += `<div class="variant-row">`;
                sortedVariants.forEach((v, idx) => {
                    html += renderCard(v, idx >= 6);
                });
                if (sortedVariants.length > 6) {
                    html += `<div class="variant-show-more" onclick="showAllVariants(this)">+${sortedVariants.length - 6} sản phẩm</div>`;
                }
                html += `</div>`;
            }

            $wrap.html(html);
            updatePriceBox();
            updateStockStatus();
            updateSkuDisplay();
            if (bestV) {
                setProductText('#pWeight', formatVariantWeight(bestV, product));
            }
        }

        function updateSkuDisplay() {
            const skuVariant = String(selectedVariantSku || '').trim();
            const skuProduct = String(productDefaultSku || '').trim();
            const finalSku = skuVariant || skuProduct;
            $('#pSku').text(finalSku ? ('Mã: ' + finalSku) : 'Mã: —');
            $('#specSku').text(finalSku || '—');
        }

        function load() {
            if (!PID) {
                notify('Thiếu sản phẩm', 'warning');
                hidePageLoader();
                return;
            }
            $.get(API, {
                ajax: 'product_detail',
                pid: PID
            }, res => {
                if (!res || !res.ok) {
                    notify(res?.msg || 'Không tải được sản phẩm', 'error');
                    hidePageLoader();
                    return;
                }
                product = res.data.product;
                const rawVariants = res?.data?.variants;
                const rawGroups = res?.data?.groups;
                variants = Array.isArray(rawVariants) ?
                    rawVariants :
                    (rawVariants && typeof rawVariants === 'object' ? Object.values(rawVariants) : []);
                groups = Array.isArray(rawGroups) ?
                    rawGroups :
                    (rawGroups && typeof rawGroups === 'object' ? Object.values(rawGroups) : []);

                // Bắt buộc hiển thị theo thứ tự đã sắp xếp (sort_order)
                groups.sort((a, b) => {
                    const sa = Number(a.sort_order ?? 0);
                    const sb = Number(b.sort_order ?? 0);
                    if (sa !== sb) return sa - sb;
                    return Number(a.id || 0) - Number(b.id || 0);
                });
                productDefaultSku = skuOfProduct(product);
                const summary = res.data.summary || {};
                const promos = res.data.promos || {};
                const coatingLayers = Array.isArray(res.data.coating_layers) ? res.data.coating_layers : [];
                const construction = res.data.construction || null;
                productBasePrice = Number(product.gia || 0) || 0;
                productBasePriceOld = Number(product.gia_cu || product.price_old || 0) || 0;
                selectedVariantPrice = productBasePrice;
                selectedVariantPriceOld = productBasePriceOld;
                productStockBase = Number(product.kho ?? product.ton_kho ?? product.so_luong ?? product.stock ?? 0) || 0;
                selectedVariantStock = productStockBase;

                loadVoucherOptions();
                renderProductPromos(promos);
                // Đảm bảo khối khuyến mãi nằm ngay trên Phân loại sau khi render
                if (typeof placePromoBeforeVariants === 'function') placePromoBeforeVariants();
                // Khởi tạo danh sách media mặc định cho sản phẩm (không phụ thuộc phân loại)
                mediaItems = buildMediaList(product);
                defaultMediaItems = Array.isArray(mediaItems) ? mediaItems.slice() : [];
                currentMediaIdx = 0;
                renderMediaGallery();
                startSlideshow();

                $('#pName').text(product.product_name || '—');
                $('#specName').text(product.product_name || '—');
                updateSkuDisplay();
                const catName = (product.category_name || product.category || '').trim();
                const catId = product.category_id || product.cat_id || '';
                const catSlug = product.category_slug || '';
                if (catName) {
                    $('#pBreadcrumbCategory').text(catName);
                    if (catSlug && catId) {
                        $('#pBreadcrumbCategory').attr('href', BASE_URL + '/shopping?cat=' + encodeURIComponent(catSlug) + '-' + catId);
                    } else if (catId) {
                        $('#pBreadcrumbCategory').attr('href', BASE_URL + '/shopping?cat_id=' + catId);
                    } else {
                        $('#pBreadcrumbCategory').attr('href', BASE_URL + '/shopping');
                    }
                } else {
                    //$('#pBreadcrumbCategory').text('Đặt hàng');
                    $('#pBreadcrumbCategory').attr('href', BASE_URL + '/shopping');
                }
                $('#pBreadcrumbName').text(product.product_name || 'Chi tiết');
                $('#faqTitle').text('Câu hỏi thường gặp về ' + (product.product_name || 'sản phẩm ABC'));
                try {
                    renderShippingSummary(res?.data?.shipping || null);
                } catch (e) {
                    console.error('[shipping] renderShippingSummary lỗi:', e);
                }
                refreshShippingQuote();
                const descHtml = sanitizeRichHtml(product.description || '');
                $('#pDesc').html(descHtml || '—');
                generateToC();
                checkOverviewHeight();
                renderFeatureBadges('#pDacTinh', product.key_features);
                renderFeatureBadges('#pUngDung', product.applications);
                setProductText('#pBrand', product.manufacturer || product.brand_name || product.brand);
                setProductText('#pWeight', formatVariantWeight(product, product));
                const resinType = product.resin_type || '';
                const solidContent = product.solid_content || '';
                const coverage = product.coverage || '';
                const glossLevel = product.gloss_level || '';
                const dryingTime = product.drying_time || '';
                const thongSoArr = [
                    resinType ? ('Loại nhựa: ' + resinType) : '',
                    product.voc ? ('VOC: ' + product.voc) : '',
                    solidContent ? ('Tỷ lệ rắn: ' + solidContent) : '',
                    coverage ? ('Độ phủ: ' + coverage) : '',
                    glossLevel ? ('Độ bóng: ' + glossLevel) : '',
                    dryingTime ? ('Thời gian khô: ' + dryingTime) : ''
                ].filter(Boolean);
                let thongSoHtml = '—';
                if (thongSoArr.length) {
                    thongSoHtml = thongSoArr.map(item => `<div>${item}</div>`).join('');
                }
                $('#specThongSo').html(thongSoHtml);
                renderCoatingSystemTable(coatingLayers);
                renderConstructionPanel(construction);
                setProductText('#specBaoQuan', product.storage);

                // Cập nhật card thông số gọn bên phải
                setProductText('#specBrand', product.manufacturer || product.brand_name || product.brand);
                setProductText('#specOrigin', product.origin || 'Việt Nam');
                setProductText('#specResin', product.resin_type || '—');
                setProductText('#specCoverage', product.coverage || '—');
                setProductText('#specGloss', product.gloss_level || '—');
                applySearchHighlight(SEARCH_Q);
                renderVariants();

                if (!variants.length) updatePriceBox();
                updateStockStatus();
                updateActionButtons();
                updateVatNote();
                // Meta pills
                const sold = Number.isFinite(Number(summary.sold_count)) ? Number(summary.sold_count) : 0;
                $('#pSold').html('<i class="bi bi-bag-check"></i> Đã mua (' + sold + ')').show();
                const ratingAvg = Number.isFinite(Number(summary.rating_avg)) ? Number(summary.rating_avg) : 0;
                const ratingCount = Number.isFinite(Number(summary.rating_count)) ? Number(summary.rating_count) : 0;
                $('#pRating').html(buildRatingChipHtml(ratingAvg, ratingCount)).show();
                reloadReviews();
                updateProductFooterSummary();
                handleProductFooterVisibility();
                normalizeProductDetailUrl(product);
                hidePageLoader();
            }).fail(() => {
                notify('Lỗi kết nối server', 'error');
                hidePageLoader();
            });
        }

        function shortNeedsText(raw) {
            const txt = String(raw || '').trim();
            if (!txt) return '';
            // Try JSON array first
            try {
                const parsed = JSON.parse(txt);
                if (Array.isArray(parsed)) {
                    const parts = parsed.map(v => String(v || '').trim()).filter(Boolean);
                    return parts.slice(0, 2).join(', ');
                }
            } catch (e) {}
            // Fallback: split by common separators
            const parts = txt
                .split(/\r?\n|\||,|;|\//g)
                .map(s => String(s || '').trim())
                .filter(Boolean);
            return parts.slice(0, 2).join(', ') || txt;
        }

        function buildSeoUrlByItem(it) {
            const id = Number(it && (it.id || it.product_id) || 0);
            if (!id) return '';
            const base = String(BASE_URL || '').replace(/\/$/, '');
            let raw = String(it && (it.slug || '') || '').trim();
            if (!raw) raw = String(it && (it.product_name || it.name || '') || '').trim();
            if (!raw) raw = 'san pham';

            let slug = '';
            if (typeof window.pmSlugify === 'function') {
                slug = window.pmSlugify(raw) || '';
            }
            if (!slug) {
                let s = raw;
                try {
                    if (s.normalize) {
                        s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                } catch (e) {}
                slug = String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            }
            if (!slug) slug = 'product';
            return base + '/product/' + encodeURIComponent(slug) + '-' + id;
        }

        function renderRelatedProducts(items) {
            const $list = $('#relatedProductsList');
            if (!$list.length) return;
            const rows = Array.isArray(items) ? items : [];
            if (!rows.length) {
                $list.html('<div class="list-group-item text-muted small">Chưa có gợi ý phù hợp.</div>');
                return;
            }
            const html = rows.map((it) => {
                const name = esc(it?.product_name || it?.name || 'Sản phẩm');
                const href = buildSeoUrlByItem(it) || (BASE_URL + '/view-product?pid=' + encodeURIComponent(String(it?.id || it?.product_id || '')));
                const thumb = toAbs(it?.thumb || it?.image_url || FALLBACK_IMG);
                const cat = String(it?.category_name || '').trim();
                const manu = String(it?.manufacturer || '').trim();
                const price = Number(it?.price || 0);
                const priceOld = Number(it?.price_old || 0);

                let metaHtml = '';
                if (cat || manu) {
                    const parts = [];
                    if (cat) parts.push(esc(cat));
                    if (manu) parts.push(esc(manu));
                    metaHtml = `<div class="related-meta">${parts.join(' • ')}</div>`;
                }

                let priceHtml = '';
                if (price > 0) {
                    priceHtml = `<div class="related-price-box">
                    <span class="related-price">${fmtPrice(price)}</span>`;
                    if (priceOld > price) {
                        priceHtml += `<span class="related-price-old">${fmtPrice(priceOld)}</span>`;
                    }
                    priceHtml += `</div>`;
                } else {
                    priceHtml = `<div class="related-price-box"><span class="related-price text-muted">Liên hệ</span></div>`;
                }

                return `
                <a class="related-item" href="${href}">
                    <img src="${thumb}" class="related-thumb" loading="lazy" onerror="this.src='${FALLBACK_IMG}'">
                    <div class="related-info">
                        <div class="related-name" title="${name}">${name}</div>
                        ${metaHtml}
                        ${priceHtml}
                    </div>
                </a>
            `;
            }).join('');
            $list.html(html);
        }

        function loadRelatedProducts(options = {}) {
            const $list = $('#relatedProductsList');
            if (!$list.length || !PID) return;

            const $btn = $('#btnReloadRelated');
            $btn.prop('disabled', true).find('i').addClass('spinner-border spinner-border-sm border-0').removeClass('bi-arrow-clockwise');

            const params = {
                ajax: 'related_products',
                pid: PID,
                limit: 5
            };
            if (options.shuffle) params.shuffle = 1;

            $.get(API, params, function(res) {
                if (!res || !res.ok) {
                    $list.html('<div class="list-group-item text-muted small">Chưa có gợi ý phù hợp.</div>');
                    return;
                }
                renderRelatedProducts(res.items || []);
            }, 'json').fail(function() {
                $list.html('<div class="list-group-item text-muted small">Không tải được gợi ý.</div>');
            }).always(function() {
                $btn.prop('disabled', false).find('i').removeClass('spinner-border spinner-border-sm border-0').addClass('bi-arrow-clockwise');
            });
        }

        $('#btnReloadRelated').on('click', function() {
            loadRelatedProducts({
                shuffle: true
            });
        });

        const BLOG_API = '<?= h($baseUrl) ?>/core/blog/ajax.php';
        const BLOG_BASE = '<?= h($baseUrl) ?>';

        function renderLatestNews(posts) {
            const $list = $('#latestNewsList');
            if (!Array.isArray(posts) || !posts.length) {
                $list.html('<div class="list-group-item text-muted small py-4 text-center">Chưa có tin tức mới.</div>');
                return;
            }

            const html = posts.map(item => {
                const url = BLOG_BASE + '/blog/' + encodeURIComponent(item.slug || '');
                const thumb = toAbs(item.thumbnail_url || '');
                const title = esc(item.title || '');
                const date = item.published_at_fmt || '';
                const cat = item.category_name || 'Tin tức';

                return `
                <a href="${url}" class="list-group-item list-group-item-action bg-white d-flex gap-3 py-3 border-0">
                    <div class="news-thumb" style="width:80px;height:60px;flex-shrink:0;border-radius:8px;overflow:hidden;background:#f8f9fa;">
                        <img src="${thumb}" class="w-100 h-100 object-fit-cover" loading="lazy" onerror="this.style.display='none';">
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="text-primary small fw-bold mb-1" style="font-size:0.7rem;">${cat}</div>
                        <div class="fw-bold text-dark small mb-1 text-truncate-2" style="line-height:1.3;">${title}</div>
                        <div class="text-muted" style="font-size:0.7rem;">${date}</div>
                    </div>
                </a>
            `;
            }).join('');
            $list.html(html);
        }

        function loadLatestNews() {
            const $list = $('#latestNewsList');
            if (!$list.length) return;

            const $btn = $('#btnReloadNews');
            $btn.prop('disabled', true).find('i').addClass('spinner-border spinner-border-sm border-0').removeClass('bi-arrow-clockwise');

            $.get(BLOG_API, function(res) {
                if (!res || !res.ok) {
                    $list.html('<div class="list-group-item text-muted small py-4 text-center">Không tải được tin tức.</div>');
                    return;
                }

                // Lấy 5 bài mới nhất từ các groups
                let allPosts = [];
                Object.keys(res.groups || {}).forEach(key => {
                    const g = res.groups[key];
                    if (Array.isArray(g.posts)) allPosts = allPosts.concat(g.posts);
                });

                // Sắp xếp theo ngày (mặc định API đã sắp xếp nhưng để chắc chắn)
                allPosts.sort((a, b) => new Date(b.published_at) - new Date(a.published_at));

                renderLatestNews(allPosts.slice(0, 5));
            }, 'json').fail(function() {
                $list.html('<div class="list-group-item text-muted small py-4 text-center">Lỗi kết nối máy chủ.</div>');
            }).always(function() {
                $btn.prop('disabled', false).find('i').removeClass('spinner-border spinner-border-sm border-0').addClass('bi-arrow-clockwise');
            });
        }

        $('#btnReloadNews').on('click', loadLatestNews);

        $('#galleryThumbs').on('click', '.gallery-thumb', function() {
            const idx = Number($(this).data('idx'));
            if (Number.isNaN(idx) || !mediaItems[idx]) return;
            selectMediaByIndex(idx);
        });

        /**
         * Kích hoạt kéo để cuộn (drag-to-scroll) trên PC cho các container có overflow-x: auto
         */
        function initDragToScroll(selector) {
            const containers = document.querySelectorAll(selector);
            containers.forEach(el => {
                let isDown = false;
                let startX;
                let scrollLeft;
                let moved = false;

                el.addEventListener('mousedown', (e) => {
                    isDown = true;
                    el.style.cursor = 'grabbing';
                    startX = e.pageX - el.offsetLeft;
                    scrollLeft = el.scrollLeft;
                    moved = false;
                });

                el.addEventListener('mouseleave', () => {
                    isDown = false;
                    el.style.cursor = 'grab';
                });

                el.addEventListener('mouseup', () => {
                    isDown = false;
                    el.style.cursor = 'grab';
                });

                el.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - el.offsetLeft;
                    const walk = (x - startX) * 2;
                    if (Math.abs(walk) > 5) moved = true;
                    el.scrollLeft = scrollLeft - walk;
                });

                // Ngăn chặn click nếu user đang kéo
                el.addEventListener('click', (e) => {
                    if (moved) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true);

                el.style.cursor = 'grab';
                el.style.userSelect = 'none';
            });
        }

        /**
         * Kích hoạt chuyển ảnh bằng cách kéo chuột trên PC
         */
        function initGallerySwipe(selector) {
            const el = document.querySelector(selector);
            if (!el) return;

            let startX;
            let isDown = false;
            let moved = false;
            const threshold = 60;

            el.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX;
                el.style.cursor = 'grabbing';
                moved = false;
            });

            el.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                const diff = Math.abs(e.pageX - startX);
                if (diff > 10) moved = true;
            });

            el.addEventListener('mouseleave', () => {
                if (!isDown) return;
                isDown = false;
                el.style.cursor = 'auto';
            });

            el.addEventListener('mouseup', (e) => {
                if (!isDown) return;
                isDown = false;
                el.style.cursor = 'auto';

                const endX = e.pageX;
                const diff = endX - startX;

                if (Math.abs(diff) > threshold) {
                    moved = true;
                    if (diff > 0) {
                        // Kéo sang phải -> Ảnh trước
                        $('#galleryMainPrev').trigger('click');
                    } else {
                        // Kéo sang trái -> Ảnh sau
                        $('#galleryMainNext').trigger('click');
                    }
                }
            });

            // Ngăn chặn lightbox mở ra nếu user đang thực hiện thao tác kéo
            el.addEventListener('click', (e) => {
                if (moved) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);

            el.addEventListener('dragstart', (e) => e.preventDefault());
        }

        function initThumbScrollNav() {
            const stepMedia = (dir) => {
                if (!Array.isArray(mediaItems) || !mediaItems.length) return;
                let next = currentMediaIdx + dir;
                if (next < 0) next = mediaItems.length - 1;
                if (next >= mediaItems.length) next = 0;
                selectMediaByIndex(next);

                const container = document.getElementById('galleryThumbs');
                if (container) {
                    const activeThumb = container.querySelector('.gallery-thumb.active');
                    if (activeThumb && typeof activeThumb.scrollIntoView === 'function') {
                        activeThumb.scrollIntoView({
                            behavior: 'smooth',
                            inline: 'center',
                            block: 'nearest'
                        });
                    }
                }
            };

            const mainPrev = document.getElementById('galleryMainPrev');
            const mainNext = document.getElementById('galleryMainNext');
            if (mainPrev) mainPrev.addEventListener('click', () => stepMedia(-1));
            if (mainNext) mainNext.addEventListener('click', () => stepMedia(1));
        }

        $('#btnZoom').on('click', () => openZoom());
        $('#btnShippingInfo').on('click', () => openShippingInfoModal());

        $('#shipModalMethodList').on('click', '.ship-method-item', function() {
            const key = String($(this).data('method') || '').trim().toLowerCase();
            if (!key) return;
            selectedShipMethodKey = key;
            renderShippingSummary(shippingInfo);
            refreshShippingQuote();
        });

        $('#shipModalMethodList').on('change', '.ship-method-radio', function() {
            const key = String($(this).val() || '').trim().toLowerCase();
            if (!key) return;
            selectedShipMethodKey = key;
            renderShippingSummary(shippingInfo);
            refreshShippingQuote();
        });

        // ─── UPDATE SHIPPING ADDRESS IN MODAL ───
        const $shipProvince = $('#shipFormProvince');
        const $shipDistrict = $('#shipFormDistrict');
        const $shipWard = $('#shipFormWard');

        function shipFormLoadProvinces(selectedProvinceId, selectedDistrictId, selectedWardCode) {
            $shipProvince.prop('disabled', true).html('<option value="">Đang tải...</option>');
            $shipDistrict.prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
            $shipWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');

            $.get(REGION_API, { action: 'region_provinces' }, function(res) {
                const rows = Array.isArray(res?.rows) ? res.rows : [];
                const opts = ['<option value="">-- Chọn tỉnh/thành --</option>'];
                rows.forEach(function(p) {
                    opts.push('<option value="' + esc(p.ProvinceID) + '">' + esc(p.ProvinceName) + '</option>');
                });
                $shipProvince.html(opts.join('')).prop('disabled', false);

                if (selectedProvinceId) {
                    $shipProvince.val(selectedProvinceId);
                    shipFormLoadDistricts(selectedProvinceId, selectedDistrictId, selectedWardCode);
                }
            }, 'json').fail(function() {
                $shipProvince.html('<option value="">Không tải được danh sách tỉnh/thành</option>');
            });
        }

        function shipFormLoadDistricts(provinceId, selectedDistrictId, selectedWardCode) {
            $shipDistrict.prop('disabled', true).html('<option value="">Đang tải...</option>');
            $shipWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');

            if (!provinceId) {
                $shipDistrict.html('<option value="">-- Chọn quận/huyện --</option>').prop('disabled', true);
                return;
            }

            $.get(REGION_API, { action: 'region_districts', province_id: provinceId }, function(res) {
                const rows = Array.isArray(res?.rows) ? res.rows : [];
                const opts = ['<option value="">-- Chọn quận/huyện --</option>'];
                rows.forEach(function(d) {
                    opts.push('<option value="' + esc(d.DistrictID) + '">' + esc(d.DistrictName) + '</option>');
                });
                $shipDistrict.html(opts.join('')).prop('disabled', false);

                if (selectedDistrictId) {
                    $shipDistrict.val(selectedDistrictId);
                    shipFormLoadWards(selectedDistrictId, selectedWardCode);
                }
            }, 'json').fail(function() {
                $shipDistrict.html('<option value="">Không tải được danh sách quận/huyện</option>');
            });
        }

        function shipFormLoadWards(districtId, selectedWardCode) {
            $shipWard.prop('disabled', true).html('<option value="">Đang tải...</option>');

            if (!districtId) {
                $shipWard.html('<option value="">-- Chọn phường/xã --</option>').prop('disabled', true);
                return;
            }

            $.get(REGION_API, { action: 'region_wards', district_id: districtId }, function(res) {
                const rows = Array.isArray(res?.rows) ? res.rows : [];
                const opts = ['<option value="">-- Chọn phường/xã --</option>'];
                rows.forEach(function(w) {
                    opts.push('<option value="' + esc(w.WardCode) + '">' + esc(w.WardName) + '</option>');
                });
                $shipWard.html(opts.join('')).prop('disabled', false);

                if (selectedWardCode) {
                    $shipWard.val(selectedWardCode);
                }
            }, 'json').fail(function() {
                $shipWard.html('<option value="">Không tải được danh sách phường/xã</option>');
            });
        }

        // Tỉnh/Thành thay đổi
        $shipProvince.on('change', function() {
            const pId = $(this).val();
            shipFormLoadDistricts(pId);
        });

        // Quận/Huyện thay đổi
        $shipDistrict.on('change', function() {
            const dId = $(this).val();
            shipFormLoadWards(dId);
        });

        // Mở Form Cập Nhật Địa Chỉ
        $('#btnUpdateShippingAddress').on('click', function() {
            // Hiển thị trạng thái đang tải ban đầu
            $('#shipFormProvince').html('<option value="">Đang tải...</option>');
            $('#shipFormDistrict').prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
            $('#shipFormWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
            
            // Chuyển view
            $('#shipModalInfoView').addClass('d-none');
            $('#shipModalFooter').addClass('d-none');
            $('#shipModalEditView').removeClass('d-none');

            // Gọi API lấy thông tin địa chỉ hiện tại
            $.get(REGION_API, {}, function(res) {
                const loc = res?.location || {};
                
                // Điền thông tin cá nhân
                $('#shipFormRecipientName').val(loc.recipient_name || '');
                $('#shipFormContactPhone').val(loc.contact_phone || '');
                $('#shipFormAddressDetail').val(loc.street || loc.address_detail || '');

                // Tải dropdowns
                const pId = loc.province_id ? Number(loc.province_id) : 0;
                const dId = loc.district_id ? Number(loc.district_id) : 0;
                const wCode = loc.ward_code || '';

                shipFormLoadProvinces(pId, dId, wCode);
            }, 'json').fail(function() {
                // Fallback nếu API lỗi, vẫn tải tỉnh thành trống
                shipFormLoadProvinces();
            });
        });

        // Hủy Cập Nhật
        $('#btnCancelUpdateAddress').on('click', function() {
            $('#shipModalEditView').addClass('d-none');
            $('#shipModalInfoView').removeClass('d-none');
            $('#shipModalFooter').removeClass('d-none');
        });

        // Submit Form Cập Nhật Địa Chỉ
        $('#shipModalAddressForm').on('submit', function(e) {
            e.preventDefault();
            
            const $btnSave = $('#btnSaveShippingAddress');
            const originalText = $btnSave.text();
            $btnSave.prop('disabled', true).text('Đang lưu...');

            const provinceId = $('#shipFormProvince').val();
            const districtId = $('#shipFormDistrict').val();
            const wardCode = $('#shipFormWard').val();
            const provinceName = $('#shipFormProvince option:selected').text().trim();
            const districtName = $('#shipFormDistrict option:selected').text().trim();
            const wardName = $('#shipFormWard option:selected').text().trim();
            const recipientName = $('#shipFormRecipientName').val().trim();
            const contactPhone = $('#shipFormContactPhone').val().trim();
            const street = $('#shipFormAddressDetail').val().trim();

            const payload = {
                action: 'save_address',
                region: '',
                branch_id: 0,
                street: street,
                address_detail: street,
                province_id: provinceId,
                district_id: districtId,
                ward_code: wardCode,
                ward: wardName,
                district: districtName,
                province: provinceName,
                contact_phone: contactPhone,
                recipient_name: recipientName,
                address_type: 'home',
                delivery_note: '',
            };

            $.post(REGION_API, payload, function(res) {
                if (res && res.ok) {
                    if (window.toastr) {
                        toastr.success('Cập nhật địa chỉ thành công');
                    } else {
                        alert('Cập nhật địa chỉ thành công');
                    }
                    
                    // Quay lại View thông tin & cập nhật lại phí ship
                    $('#shipModalEditView').addClass('d-none');
                    $('#shipModalInfoView').removeClass('d-none');
                    $('#shipModalFooter').removeClass('d-none');

                    refreshShippingQuote();
                } else {
                    const msg = res?.message || 'Có lỗi xảy ra khi lưu địa chỉ.';
                    if (window.toastr) {
                        toastr.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            }, 'json').fail(function(xhr) {
                const res = xhr.responseJSON;
                const msg = res?.message || 'Không thể lưu địa chỉ. Vui lòng kiểm tra lại.';
                if (window.toastr) {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            }).always(function() {
                $btnSave.prop('disabled', false).text(originalText);
            });
        });

        $('#variantWrap').on('click', '.variant-card', function() {
            if ($(this).hasClass('active')) return;
            const vid = Number($(this).data('id') || 0);
            $('#variantWrap .variant-card').removeClass('active');
            $(this).addClass('active');
            selectedVariantId = vid;
            selectedVariantPrice = Number($(this).data('price') || productBasePrice || 0);
            selectedVariantPriceOld = Number($(this).data('price_old') || productBasePriceOld || 0);
            selectedVariantStock = Number($(this).data('stock') || 0);
            const pickedVariant = variants.find(v => Number(v.id || 0) === selectedVariantId) || null;
            selectedVariantSku = pickedVariant ? skuOfVariant(pickedVariant) : String($(this).data('sku') || '').trim();



            if (pickedVariant) {
                selectedVariantShipping = {
                    weight_value: Math.max(0, Number(pickedVariant.shipping_weight_value ?? 1)),
                    weight_unit: String(pickedVariant.shipping_weight_unit || 'kg').toLowerCase(),
                    length_cm: Math.max(1, Number(pickedVariant.shipping_length_cm ?? 20)),
                    width_cm: Math.max(1, Number(pickedVariant.shipping_width_cm ?? 20)),
                    height_cm: Math.max(1, Number(pickedVariant.shipping_height_cm ?? 20)),
                };
            }

            // Cập nhật gallery media ưu tiên ảnh của phân loại đang chọn nếu có
            (function() {
                // Nếu chưa có gallery mặc định thì khởi tạo lại từ sản phẩm
                if (!Array.isArray(defaultMediaItems) || !defaultMediaItems.length) {
                    if (product) {
                        defaultMediaItems = buildMediaList(product);
                    } else {
                        defaultMediaItems = [];
                    }
                }

                const baseList = Array.isArray(defaultMediaItems) && defaultMediaItems.length ?
                    defaultMediaItems.slice() :
                    (Array.isArray(mediaItems) ? mediaItems.slice() : []);

                let variantImgUrl = '';
                if (pickedVariant) {
                    const rawImg = pickedVariant.image_url ||
                        pickedVariant.hinh_anh_variant ||
                        pickedVariant.image ||
                        pickedVariant.anh ||
                        (product && product.image_url) ||
                        '';
                    if (rawImg) variantImgUrl = toAbs(rawImg);
                }

                if (variantImgUrl) {
                    // Tạo phần tử media cho ảnh phân loại và đưa lên đầu danh sách
                    let firstItem = {
                        url: variantImgUrl,
                        thumb: variantImgUrl,
                        type: detectMediaType(variantImgUrl)
                    };

                    const existIdx = baseList.findIndex(it => {
                        const u = String(it.url || '');
                        const t = String(it.thumb || '');
                        return u === variantImgUrl || t === variantImgUrl;
                    });
                    if (existIdx >= 0) {
                        const exist = baseList.splice(existIdx, 1)[0];
                        firstItem = {
                            ...exist,
                            url: variantImgUrl,
                            thumb: exist.thumb || variantImgUrl
                        };
                    }

                    mediaItems = [firstItem, ...baseList];
                } else {
                    // Không có ảnh riêng cho phân loại: quay lại gallery mặc định
                    mediaItems = baseList;
                }

                currentMediaIdx = 0;
                renderMediaGallery();
            })();
            setPriceAvailability(hasPriceValue(selectedVariantPrice));
            updateStockStatus();
            updatePriceBox();
            updateSkuDisplay();
            updateActionButtons();
            refreshShippingQuote();

            // Cập nhật Khối lượng theo variant
            if (pickedVariant) {
                setProductText('#pWeight', formatVariantWeight(pickedVariant, product));
            }
        });

        const setReviewRating = (value) => {
            const rate = Number(value || 0);
            selectedReviewRating = (rate >= 1 && rate <= 5) ? rate : 0;
            $('#reviewRating').val(String(selectedReviewRating));
            $('#reviewRateStars .comments-rate-star').removeClass('active');
            if (selectedReviewRating > 0) {
                $(`#reviewRateStars .comments-rate-star[data-rate="${selectedReviewRating}"]`).addClass('active');
            }
        };

        const resetReviewForm = () => {
            setReviewRating(0);
            $('#reviewParentId').val('0');
            $('#reviewEditId').val('0');
            $('#reviewReplyNote').hide().text('Đang trả lời bình luận');
            $('#reviewContent').val('');
            clearReviewMedia();
            // Reset cả 2 loại chips: review tags + Q&A suggest chips
            $('#reviewSuggestChips .review-suggest-chip').removeClass('active');
            $('#qaSuggestions .qa-suggest-chip').removeClass('active');
        };

        $('#btnOpenReview, .btn-write-review-now').on('click', function() {
            resetReviewForm();
            setReviewRating(5); // Default to 5 stars
            openReviewModal('review');
        });

        $(document).on('click', '#btnOpenQA, #btnOpenQAInput', function() {
            resetReviewForm();
            openReviewModal('comment');
        });

        // Click review suggestion tag to toggle active
        $('#reviewModal').on('click', '.review-suggest-chip', function() {
            $(this).toggleClass('active');
        });


        $('#reviewRateStars').on('click', '.comments-rate-star', function() {
            const rate = Number($(this).data('rate') || 0);
            setReviewRating(rate);
        });

        $('#reviewMediaBtn').on('click', () => {
            $('#reviewMediaInput').trigger('click');
        });

        $('#reviewMediaInput').on('change', function() {
            const files = this.files ? Array.from(this.files) : [];
            addReviewMediaFiles(files);
            this.value = '';
        });

        $('#reviewMediaPreview').on('click', '.review-media-remove', function() {
            const $item = $(this).closest('.review-media-item');
            const idx = Number($item.data('idx'));
            if (Number.isNaN(idx) || !reviewMediaFiles[idx]) return;
            const removed = reviewMediaFiles.splice(idx, 1);
            if (removed[0]?.previewUrl) {
                try {
                    URL.revokeObjectURL(removed[0].previewUrl);
                } catch (err) {}
            }
            renderReviewMediaPreview();
        });

        $(document).on('click', '.item-action__reply', function() {
            const replyId = Number($(this).attr('data-reply-id') || $(this).data('reply-id') || 0);
            if (!replyId) return;
            const $item = $(this).closest('.item');
            const rootId = Number($item.data('root-id') || 0);
            const targetId = rootId > 0 ? rootId : replyId;

            resetReviewForm();
            openReviewModal('comment');
            $('#reviewModal .modal-title').html('<i class="bi bi-reply me-2"></i>Trả lời bình luận');
            $('#reviewParentId').val(String(targetId));
            $('#reviewReplyNote').show().text('Đang trả lời bình luận #' + replyId);
            $('#reviewContent').focus();
        });

        $(document).on('click', '[data-edit-id]', function() {
            const editId = Number($(this).attr('data-edit-id') || $(this).data('edit-id') || 0);
            if (!editId) return;
            const $item = $(this).closest('.item');

            resetReviewForm();
            clearReviewMedia();

            const data = REVIEW_STORE && REVIEW_STORE[editId] ? REVIEW_STORE[editId] : null;
            // Lấy nội dung & số sao TỪ ĐÚNG bản ghi trong store để tránh gộp nhầm
            // text của bình luận con (vd: auto-reply) khi dùng $item.find('.item-content').
            const content = data && typeof data.comment === 'string'
                ? data.comment
                : ($item.find('.item-content').not($item.find('.item-child .item-content')).first().text().trim());
            const rating = data ? Number(data.rating || 0) : Number($item.data('rating') || 0);
            const mediaList = data && Array.isArray(data.media) ? data.media : [];
            if (mediaList.length) {
                mediaList.forEach((m) => {
                    const url = toAbs(m?.url || '');
                    if (!url) return;
                    const type = String(m?.type || 'image').toLowerCase();
                    reviewMediaFiles.push({
                        file: null,
                        type: type === 'video' ? 'video' : 'image',
                        previewUrl: url,
                        existing: true,
                        url: m?.url || url,
                    });
                });
                renderReviewMediaPreview();
            }

            openReviewModal(rating > 0 ? 'review' : 'comment');
            $('#reviewModal .modal-title').html('<i class="bi bi-pencil-square me-2"></i>Chỉnh sửa');
            $('#reviewEditId').val(String(editId));
            $('#reviewParentId').val('0');
            $('#reviewReplyNote').show().text('Đang chỉnh sửa bình luận #' + editId);
            setReviewRating(rating);
            $('#reviewContent').val(content).focus();
        });

        // Xóa bình luận: dùng click + delegated (đồng bộ với nút Sửa).
        // Stop propagation để outside-click handler không đóng menu trước khi confirm().
        $(document).on('click', '[data-delete-id]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const delId = Number($(this).attr('data-delete-id') || $(this).data('delete-id') || 0);
            if (!delId) return;
            if (!confirm('Xóa bình luận này?')) return;
            $.post(COMMENT_API, {
                action: 'product_review_delete',
                pid: PID,
                review_id: delId,
                csrf_token: CSRF_TOKEN
            }, function(res) {
                if (!res || !res.ok) {
                    notify((res && res.msg) ? res.msg : 'Không thể xóa bình luận', 'error');
                    return;
                }
                notify(res.msg || 'Đã xóa bình luận', 'success');
                reloadReviews();
                resetReviewForm();
            }, 'json').fail(function() {
                notify('Lỗi kết nối server', 'error');
            });
        });

        $(document).on('click', '[data-like-id]', function() {
            const likeId = Number($(this).attr('data-like-id') || $(this).data('like-id') || 0);
            if (!likeId) return;
            $.post(COMMENT_API, {
                action: 'product_review_like',
                review_id: likeId
            }, (res) => {
                if (!res || !res.ok) {
                    notify(res?.msg || 'Không thể thả tim', 'error');
                    return;
                }
                const liked = !!res.liked;
                const count = Number(res.like_count || 0);
                const $btn = $(`[data-like-id="${likeId}"]`);
                $btn.toggleClass('active', liked);
                $btn.html(`<i class="bi bi-heart-fill"></i>${count}`);
            }).fail(() => {
                notify('Lỗi kết nối server', 'error');
            });
        });

        // Chọn câu hỏi gợi ý khi đặt câu hỏi
        $('#reviewModal').on('click', '.qa-suggest-chip', function() {
            const text = $(this).data('text');
            const $textarea = $('#reviewContent');
            const currentVal = $textarea.val().trim();
            if (currentVal) {
                $textarea.val(currentVal + '\n' + text);
            } else {
                $textarea.val(text);
            }
            $textarea.focus();
        });

        // Bấm sổ/thu gọn phản hồi của bình luận
        $(document).on('click', '.item-replies-toggle', function() {
            const $btn = $(this);
            const $child = $btn.next('.item-child');
            const total = Number($btn.data('total') || 0);
            if ($child.is(':visible')) {
                $child.slideUp(150);
                $btn.html(`<i class="bi bi-chevron-down me-1"></i>Xem ${total} phản hồi`);
            } else {
                $child.slideDown(150);
                $btn.html(`<i class="bi bi-chevron-up me-1"></i>Thu gọn phản hồi`);
            }
        });

        // Toggle menu cho 
        $(document).on('click', '.item-action-toggle', function(e) {
            e.stopPropagation();
            const $tools = $(this).closest('.item-action-tools');
            if (!$tools.length) return;
            const isOpen = $tools.hasClass('is-open');
            $('.item-action-tools.is-open').not($tools).removeClass('is-open');
            $tools.toggleClass('is-open', !isOpen);
        });

        // Đóng menu khi click ra ngoài và đóng luôn BXGY dropdown
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.item-action-tools').length) {
                $('.item-action-tools.is-open').removeClass('is-open');
            }
            hideBxgyDropdownDetail();
        });

        // Sau khi chọn action trong menu thì tự đóng menu (dùng setTimeout để tránh tranh chấp click event ẩn element)
        $(document).on('click', '.item-action-menu .item-action-btn', function() {
            const $tools = $(this).closest('.item-action-tools');
            setTimeout(() => {
                $tools.removeClass('is-open');
            }, 100);
        });

        $('#btnSendReview').on('click', function() {
            const content = String($('#reviewContent').val() || '').trim();
            const parentId = Number($('#reviewParentId').val() || 0);
            const rating = parentId > 0 ? 0 : Number($('#reviewRating').val() || 0);
            const editId = Number($('#reviewEditId').val() || 0);

            const isReviewMode = $('#reviewModal .comments-add__rate').is(':visible');
            if (isReviewMode && parentId === 0 && rating === 0) {
                notify('Vui lòng chọn số sao đánh giá (từ 1 đến 5 sao)', 'warning');
                return;
            }

            if (!content) {
                notify('Vui lòng nhập nội dung bình luận', 'warning');
                return;
            }

            const fd = new FormData();
            fd.append('action', editId > 0 ? 'product_review_edit' : 'product_review_add');
            fd.append('pid', String(PID));
            if (editId > 0) {
                fd.append('review_id', String(editId));
                fd.append('rating', String(Number($('#reviewRating').val() || 0)));
                // gửi trạng thái media hiện tại (sau khi user remove)
                const original = REVIEW_STORE && REVIEW_STORE[editId] && Array.isArray(REVIEW_STORE[editId].media) ?
                    REVIEW_STORE[editId].media : [];
                if (original) {
                    const existingMedia = reviewMediaFiles
                        .filter(it => it && it.existing && it.url)
                        .map(it => ({
                            url: it.url,
                            type: it.type || 'image'
                        }));
                    try {
                        fd.append('existing_media', JSON.stringify(existingMedia));
                    } catch (err) {
                        // bỏ qua nếu JSON lỗi
                    }
                }
            } else {
                fd.append('parent_id', String(parentId));
                fd.append('rating', String(rating));
                if (!CURRENT_USER_ID) {
                    fd.append('guest_name', String($('#reviewGuestName').val() || '').trim());
                    fd.append('guest_phone', String($('#reviewGuestPhone').val() || '').trim());
                    fd.append('guest_email', String($('#reviewGuestEmail').val() || '').trim());
                }
            }
            fd.append('content', content);

            // Get selected tag suggestion chips (cả review tags và Q&A tags)
            const selectedTags = [];
            // Review tags (khi mode đánh giá)
            $('#reviewSuggestChips .review-suggest-chip.active').each(function() {
                selectedTags.push($(this).data('tag'));
            });
            // Q&A suggest chips không có tag data → bỏ qua (chips Q&A chỉ điền text vào textarea)
            fd.append('tags', selectedTags.join(','));
            // Đính kèm CSRF token vào FormData (dự phòng nếu header không hoạt động)
            fd.append('csrf_token', CSRF_TOKEN);

            if (reviewMediaFiles && reviewMediaFiles.length > 0) {
                reviewMediaFiles.forEach((item) => {
                    if (item?.file) {
                        fd.append('review_media[]', item.file);
                    }
                });
            }

            // Chặn spam: disable nút gửi trong khi AJAX chạy
            const $sendBtn = $('#btnSendReview').prop('disabled', true).css('opacity', '0.65');

            $.ajax({
                url: COMMENT_API,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: (res) => {
                    if (!res || !res.ok) {
                        notify(res?.msg || 'Không thể gửi bình luận', 'error');
                        return;
                    }
                    notify(res.msg || (editId > 0 ? 'Đã cập nhật bình luận' : 'Đã gửi bình luận'), 'success');
                    reloadReviews();
                    resetReviewForm();

                    // Tự động đóng modal sau khi gửi thành công
                    const modalEl = document.getElementById('reviewModal');
                    if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                        const modal = window.bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
                        $(modalEl).modal('hide');
                    }
                },
                error: () => {
                    notify('Lỗi kết nối server', 'error');
                },
                complete: () => {
                    // Khôi phục nút gửi sau khi request hoàn tất (thành công hoặc lỗi)
                    $sendBtn.prop('disabled', false).css('opacity', '');
                }
            });
        });

        // Tính năng FAQ AI đã bị tắt, không còn gọi API product_faq_ai

        $('#qty').on('input change', function() {
            const qty = getQty();
            $(this).val(qty);
            updatePriceBox();
            refreshShippingQuote();
        });

        $('#voucherSection').on('click', '.voucher-badge', function() {
            const nextCode = String($(this).data('code') || '');
            const target = String($(this).data('target') || 'order');

            lastClickedVoucherCode = nextCode;

            // Tìm và hiển thị tóm tắt mã voucher khi click
            const voucher = voucherOptions.find(v => String(v.code || '').toUpperCase() === nextCode.toUpperCase());
            if (voucher) {
                const detail = voucher.detail_text || '';
                const promo = voucher.promo_note || '';
                let text = detail;
                if (promo) {
                    text = text ? (text + ' - ' + promo) : promo;
                }
                if (!text) {
                    text = 'Áp dụng mã ' + voucher.code + ' để nhận ưu đãi.';
                }
                $('#voucherNote').text(text).show();
            }

            if (target === 'shipping') {
                selectedShipVoucherCode = nextCode;
                // Chỉ bỏ active các vé cùng nhóm vận chuyển (giữ vé đơn hàng đang chọn)
                $('#voucherList .voucher-badge[data-target="shipping"]').removeClass('active');
                $(this).addClass('active');
                renderShippingSummary(null, true);
                return;
            }
            selectedVoucherCode = nextCode;
            $('#voucherList .voucher-badge[data-target="order"]').removeClass('active');
            $(this).addClass('active');
            updatePriceBox();
        });
        $('.action-qty').on('click', '.stepper-btn', function() {
            const $btn = $(this);
            const step = parseInt($btn.data('step'), 10) || 0;
            const $wrap = $btn.closest('.cart-stepper');
            const $input = $wrap.find('#qty');
            if (!$input.length) return;
            let qty = parseInt($input.val(), 10) || 1;
            qty += step;
            if (qty < 1) qty = 1;
            $input.val(qty).trigger('change');
        });

        function addToCart(mode) {
            if (REQUIRE_LOGIN_FOR_PURCHASE) {
                requireLogin();
                return;
            }

            // Nếu hết hàng thì chuyển sang liên hệ ngay — TRỪ hàng đặt trước (luôn cho mua)
            const isPreorder = !!(product && Number(product.preorder_enabled || 0) === 1);
            if (!isPreorder && currentStockQty() <= 0) {
                const href = getHotlineTelHref();
                if (href) {
                    window.location.href = href;
                } else {
                    notify('Sản phẩm hết hàng', 'info');
                }
                return;
            }

            if (!priceAvailable) {
                notify('Sản phẩm chưa có giá, vui lòng liên hệ để đặt hàng.', 'info');
                return;
            }
            const qty = Math.max(1, parseInt($('#qty').val(), 10) || 1);


            // Lựa chọn quà BXGY theo promo_id (nếu có), đọc từ trạng thái bxgy-selector
            const bxgyChoice = {};
            const bxgyChoiceVariant = {};
            Object.keys(BXGY_PROMOS_DETAIL || {}).forEach((k) => {
                const promoId = parseInt(k, 10) || 0;
                if (!promoId) return;
                const st = BXGY_PROMOS_DETAIL[promoId] || {};
                const giftPid = parseInt(st.selectedGiftPid || 0, 10) || 0;
                const giftVid = parseInt(st.selectedGiftVid || 0, 10) || 0;
                if (!giftPid) return;
                bxgyChoice[promoId] = giftPid;
                if (giftVid > 0) bxgyChoiceVariant[promoId] = giftVid;
            });

            let bxgyChoiceJson = '';
            let bxgyChoiceVariantJson = '';
            if (bxgyChoice && Object.keys(bxgyChoice).length) {
                try {
                    bxgyChoiceJson = JSON.stringify(bxgyChoice);
                } catch (e) {
                    bxgyChoiceJson = '';
                }
            }
            if (bxgyChoiceVariant && Object.keys(bxgyChoiceVariant).length) {
                try {
                    bxgyChoiceVariantJson = JSON.stringify(bxgyChoiceVariant);
                } catch (e) {
                    bxgyChoiceVariantJson = '';
                }
            }

            const action = (mode === 'buy') ? 'cart_set_single' : 'cart_add';
            const payload = {
                action,
                pid: Number(PID || 0),
                variant_id: Number(selectedVariantId || 0),
                qty: Number(qty || 1),
            };
            if (bxgyChoiceJson) payload.bxgy_gift_choice = bxgyChoiceJson;
            if (bxgyChoiceVariantJson) payload.bxgy_gift_choice_variant = bxgyChoiceVariantJson;
            $.post(API, payload, res => {
                if (!res || !res.ok) {
                    notify(res?.msg || 'Không thể thêm vào giỏ', 'error');
                    return;
                }

                // Animation
                if (mode !== 'buy' && window.pmFlyToCart) {
                    const imgEl = document.getElementById('galleryMainImage');
                    if (imgEl) window.pmFlyToCart(imgEl);
                }

                if (window.refreshCartBadge) window.refreshCartBadge();
                if (mode === 'buy') {
                    window.location.href = '<?= h($baseUrl) ?>/checkout';
                } else {
                    if (window.renderMiniCartPopup) window.renderMiniCartPopup(res.data || [], (product ? (product.product_name || product.name) : ''));
                }
            }).fail(() => notify('Đang gặp lỗi với hệ thống, liên hệ qua Hotline để được hỗ trợ', 'error'));
        }

        $('#btnAdd').click(() => addToCart('add'));
        $('#pfAdd').click(() => addToCart('add'));
        $('#btnBuy, #pfBuy').off('click').on('click', function() {
            const mode = String($(this).attr('data-mode') || 'buy');
            if (mode === 'call') {
                const href = getHotlineTelHref();
                if (href) {
                    window.location.href = href;
                    return;
                }
            }
            addToCart('buy');
        });

        $('#btnCopyProductLink').on('click', () => {
            const url = PRODUCT_SEO_URL || buildProductSeoUrl(product);
            if (!url) {
                notify('Không xác định được link sản phẩm', 'error');
                return;
            }
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(url).then(() => {
                    notify('Đã copy link sản phẩm', 'success');
                }).catch(() => {
                    notify('Không copy được link. Vui lòng thử lại.', 'error');
                });
            } else {
                const temp = document.createElement('textarea');
                temp.value = url;
                temp.style.position = 'fixed';
                temp.style.opacity = '0';
                document.body.appendChild(temp);
                temp.select();
                try {
                    document.execCommand('copy');
                    notify('Đã copy link sản phẩm', 'success');
                } catch (e) {
                    notify('Không copy được link. Vui lòng thử lại.', 'error');
                }
                document.body.removeChild(temp);
            }
        });

        // Chọn quà tặng hoá đơn: chỉ cho phép thêm nếu tổng đơn hiện tại đạt ngưỡng
        $('#btnBuyGift').off('click').on('click', function() {
            const $btn = $(this);
            if ($btn.prop('disabled')) return;

            const $checked = $('#promoGiftGrid').find('.promo-choice-input-gift:checked').first();
            if (!$checked.length) {
                notify('Vui lòng chọn 1 quà tặng.', 'warning');
                return;
            }

            const $card = $checked.closest('.promo-deal-card');
            const pid = Number($card.data('pid') || 0);
            if (!pid) {
                notify('Không xác định được sản phẩm quà tặng.', 'error');
                return;
            }

            const threshold = Number($card.data('threshold') || 0);

            const doAddGift = () => {
                $btn.prop('disabled', true).addClass('disabled');
                $.ajax({
                    url: API,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cart_add_free',
                        pid: pid,
                        main_pid: PID,
                        main_variant_id: selectedVariantId || 0,
                    },
                }).done(function(resp) {
                    if (!resp || !resp.ok) {
                        notify(resp && resp.msg ? resp.msg : 'Không thêm được quà tặng.', 'error');
                        return;
                    }
                    if (window.refreshCartBadge) window.refreshCartBadge();
                    notify('Đã chọn quà tặng cho đơn hàng.', 'success');
                    $('#promoGiftSection .promo-deal-action').html('<a href="' + BASE_URL + '/cart" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-cart3 me-1"></i>Xem giỏ hàng</a>');
                }).fail(function() {
                    notify('Không thể thêm quà tặng, vui lòng thử lại.', 'error');
                }).always(function() {
                    $btn.prop('disabled', false).removeClass('disabled');
                });
            };

            if (threshold > 0) {
                $btn.prop('disabled', true).addClass('disabled');
                $.get(API, {
                    ajax: 'cart_get'
                }, function(res) {
                    if (!res || !res.ok) {
                        notify(res && res.msg ? res.msg : 'Không kiểm tra được giỏ hàng.', 'error');
                        $btn.prop('disabled', false).removeClass('disabled');
                        return;
                    }
                    const items = Array.isArray(res.data) ? res.data : [];
                    let subtotal = 0;
                    items.forEach((it) => {
                        const isGift = !!it.is_gift || (Number(it.price || 0) === 0 && Number(it.qty || 0) === 1);
                        if (isGift) return;
                        subtotal += Number(it.price || 0) * Number(it.qty || 0);
                    });
                    if (subtotal < threshold) {
                        notify('Đơn hàng cần đạt tối thiểu ' + fmtPrice(threshold) + ' để nhận quà tặng.', 'warning');
                        $btn.prop('disabled', false).removeClass('disabled');
                        return;
                    }
                    // Đã đủ điều kiện hoá đơn, cho phép thêm quà
                    $btn.prop('disabled', false).removeClass('disabled');
                    doAddGift();
                }).fail(function() {
                    notify('Không thể kiểm tra điều kiện hoá đơn.', 'error');
                    $btn.prop('disabled', false).removeClass('disabled');
                });
            } else {
                // Không cấu hình ngưỡng: cho phép thêm trực tiếp, backend vẫn tự kiểm tra nếu có logic
                doAddGift();
            }
        });

        // Mua kèm deal sốc: luôn gắn kèm sản phẩm chính và kiểm tra trên backend
        $('#btnBuyCombo').off('click').on('click', function() {
            const $btn = $(this);
            if ($btn.prop('disabled')) return;

            const $checked = $('#promoComboGrid').find('.promo-choice-input-combo:checked').first();
            if (!$checked.length) {
                notify('Vui lòng chọn 1 sản phẩm deal sốc.', 'warning');
                return;
            }

            const $card = $checked.closest('.promo-deal-card');
            const pid = Number($card.data('pid') || 0);
            if (!pid) {
                notify('Không xác định được sản phẩm deal sốc.', 'error');
                return;
            }

            if (!PID) {
                notify('Thiếu thông tin sản phẩm chính.', 'error');
                return;
            }

            const mainPid = PID;

            const doAddCombo = () => {
                $btn.prop('disabled', true).addClass('disabled');
                $.ajax({
                    url: API,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cart_add_combo',
                        pid: pid,
                        main_pid: mainPid,
                        main_variant_id: selectedVariantId || 0,
                    },
                }).done(function(resp) {
                    if (!resp || !resp.ok) {
                        notify(resp && resp.msg ? resp.msg : 'Không thêm được sản phẩm deal sốc.', 'error');
                        return;
                    }
                    if (window.refreshCartBadge) window.refreshCartBadge();
                    notify('Đã thêm sản phẩm deal sốc vào giỏ hàng.', 'success');
                    $('#promoComboSection .promo-deal-action').html('<a href="' + BASE_URL + '/cart" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-cart3 me-1"></i>Xem giỏ hàng</a>');
                }).fail(function() {
                    notify('Không thể thêm sản phẩm deal sốc, vui lòng thử lại.', 'error');
                }).always(function() {
                    $btn.prop('disabled', false).removeClass('disabled');
                });
            };

            // Đảm bảo đã có sản phẩm chính trong giỏ
            if (REQUIRE_LOGIN_FOR_PURCHASE) {
                requireLogin();
                return;
            }
            if (!priceAvailable) {
                notify('Sản phẩm chưa có giá, vui lòng liên hệ để đặt hàng.', 'info');
                return;
            }

            // Thêm sản phẩm chính (với variant_id đang chọn) trước, sau đó mới thêm combo
            const qtyBefore = Math.max(1, parseInt($('#qty').val(), 10) || 1);
            $('#qty').val(qtyBefore);
            $.post(API, {
                action: 'cart_add',
                pid: PID,
                variant_id: selectedVariantId || 0,
                qty: qtyBefore
            }, function(res) {
                if (!res || !res.ok) {
                    notify(res && res.msg ? res.msg : 'Không thể thêm sản phẩm chính vào giỏ.', 'error');
                    return;
                }
                refreshCartBadge();
                doAddCombo();
            }).fail(function() {
                notify('Không thể thêm sản phẩm chính vào giỏ.', 'error');
            });
        });

        // === REORDER LAYOUT FOR MOBILE ===
        // Đặt khối khuyến mãi (#promoSection) ngay TRƯỚC PHÂN LOẠI (#variantSection),
        // tức nằm trong cột thông tin: Giá -> Ưu đãi -> Khuyến mãi -> Phân loại.
        function placePromoBeforeVariants() {
            const $promo = $('#promoSection');
            const $variant = $('#variantSection');
            if ($promo.length && $variant.length) {
                $variant.before($promo);
            }
        }

        function syncProductLayout() {
            if (window.innerWidth < 992) {
                // Mobile Layout Order: Gallery -> Info -> Specs -> Tabs -> FAQ -> Related -> News
                $('#gallerySection').after($('#productInfoSection'), $('#specSection'));
                $('#productDetailCard').after($('#faqCard'));
                $('#faqCard').after($('#relatedProductsSection'));
                $('#relatedProductsSection').after($('#latestNewsSection'));
                // Cuối cùng: Đánh giá -> Hỏi đáp
                $('#bottomSections').append($('#reviewSection'), $('#blockQA'));
            } else {
                // PC Layout: Restore 2, 3, 4, 5, 7, 9 to right column
                $('#rightColumn').append(
                    $('#productInfoSection'),
                    $('#specSection'),
                    $('#relatedProductsSection'),
                    $('#latestNewsSection')
                );
                // Restore Đánh giá & Hỏi đáp to bottom full-width section
                $('#bottomSections').append(
                    $('#reviewSection'),
                    $('#blockQA')
                );
                // FAQ stays in left column
                $('#leftColumn').append($('#faqCard'));
            }
            // Luôn đặt promo ngay trên Phân loại (cả PC lẫn mobile)
            placePromoBeforeVariants();
        }

        $(document).on('click', '#btnShowFullSpec', function() {
            const modalEl = document.getElementById('specModal');
            if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const modal = new window.bootstrap.Modal(modalEl);
                modal.show();
            } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
                $(modalEl).modal('show');
            }
        });
        $(window).on('resize', syncProductLayout);
        syncProductLayout();

        initCollapsibles();
        initOverviewToggle();
        refreshCartBadge();
        initDragToScroll('#productInfoTabsNav, #galleryThumbs, .vg-tabs');
        initGallerySwipe('#galleryMain');
        initThumbScrollNav();

        const initFavorite = () => {
            const updateFavUI = (res) => {
                if (!res || !res.ok) return;
                $('#idBtnFavorite, #btnFavoriteChip').toggleClass('active', !!res.liked);
                $('#idBtnFavorite').attr('title', res.liked ? 'Bỏ yêu thích' : 'Thêm vào yêu thích');
                $('#idFavoriteCount').text(res.count || 0);

                // Sync icon and count on chip
                if (res.liked) {
                    $('#btnFavoriteChip i').removeClass('bi-heart').addClass('bi-heart-fill text-danger');
                } else {
                    $('#btnFavoriteChip i').removeClass('bi-heart-fill text-danger').addClass('bi-heart');
                }
                $('#idFavoriteCountChip').text(res.count || 0);
            };
            // Initial state
            $.post(FAVORITE_API, {
                action: 'get',
                pid: PID
            }, updateFavUI);
            // Click handler
            $('#idBtnFavorite, #btnFavoriteChip').on('click', function(e) {
                e.preventDefault();
                const $btn = $('#idBtnFavorite, #btnFavoriteChip');
                $btn.prop('disabled', true);
                $.post(FAVORITE_API, {
                    action: 'toggle',
                    pid: PID
                }, (res) => {
                    updateFavUI(res);
                    if (res.ok) {
                        if (res.liked) {
                            notify('Đã thêm vào danh sách yêu thích', 'success');
                        } else {
                            notify('Đã bỏ yêu thích', 'info');
                        }
                    }
                }).always(() => {
                    $btn.prop('disabled', false);
                });
            });
        };
        initFavorite();

        load();
        loadRelatedProducts();
        loadLatestNews();
        window.addEventListener('scroll', handleProductFooterVisibility);
        window.addEventListener('resize', handleProductFooterVisibility);
    });
</script>

<style>
    #galleryMain {
        position: relative;
        cursor: crosshair;
    }

    #zoomLens {
        position: fixed;
        width: 340px;
        height: 280px;
        border-radius: 10px;
        border: 2px solid var(--theme-primary, #0c4c29);
        box-shadow: 0 0 0 2px rgba(12, 76, 41, .2), 0 8px 32px rgba(0, 0, 0, .22);
        overflow: hidden;
        pointer-events: none;
        display: none;
        z-index: 9999;
        transform: translate(-50%, -50%);
        background-color: #fff;
        background-repeat: no-repeat;
    }

    @media (max-width: 767px) {
        #zoomLens {
            display: none !important;
        }

        #galleryMain {
            cursor: default;
        }
    }
</style>

<script>
    (function() {
        const ZOOM = 3;
        const LW = 340;
        const LH = 280;
        const img = document.getElementById('galleryMainImage');
        const video = document.getElementById('galleryMainVideo');

        // Tạo lens và gắn vào body để tránh bị clip bởi overflow:hidden cha
        const lens = document.createElement('div');
        lens.id = 'zoomLens';
        document.body.appendChild(lens);

        if (!img || !lens) return;

        function isVideoVisible() {
            return video && video.style.display !== 'none';
        }

        function onMove(e) {
            if (window.innerWidth < 768 || isVideoVisible()) return;

            const rect = img.getBoundingClientRect();
            const cx = e.clientX - rect.left;
            const cy = e.clientY - rect.top;

            // clamp cursor within image
            if (cx < 0 || cy < 0 || cx > rect.width || cy > rect.height) {
                lens.style.display = 'none';
                return;
            }

            lens.style.display = 'block';
            lens.style.left = e.clientX + 'px';
            lens.style.top = e.clientY + 'px';

            const bgW = rect.width * ZOOM;
            const bgH = rect.height * ZOOM;
            const bgX = -(cx * ZOOM - LW / 2);
            const bgY = -(cy * ZOOM - LH / 2);

            lens.style.backgroundImage = 'url(' + img.src + ')';
            lens.style.backgroundSize = bgW + 'px ' + bgH + 'px';
            lens.style.backgroundPosition = bgX + 'px ' + bgY + 'px';
        }

        function onLeave() {
            lens.style.display = 'none';
        }

        img.addEventListener('mousemove', onMove);
        img.addEventListener('mouseleave', onLeave);

        // Reset khi đổi ảnh
        new MutationObserver(onLeave).observe(img, {
            attributes: true,
            attributeFilter: ['src']
        });
    })();
</script>