<?php 
require_once __DIR__ . '/_admin_guard.php';
?>

<?php
$users = [];
// Đảm bảo cột sort_order tồn tại để tránh lỗi SQL khi truy vấn
$ithanhloc->query("ALTER TABLE user_notification ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0");

$resU = $ithanhloc->query('SELECT id, username, full_name, email, role FROM users ORDER BY id DESC LIMIT 300');
if ($resU) {
    while ($row = $resU->fetch_assoc()) {
        $users[] = $row;
    }
}

$recent = [];
$resN = $ithanhloc->query('SELECT n.id, n.user_id, n.title, n.body, n.type, n.link, n.is_active, n.created_at, n.send_at, u.username AS created_by_name
    FROM user_notification n
    LEFT JOIN users u ON u.id = n.created_by
    WHERE LOWER(TRIM(CAST(n.type AS CHAR))) IN ("promotion","promo","voucher","coupon")
    ORDER BY n.sort_order ASC, n.id DESC LIMIT 50');
if ($resN) {
    while ($row = $resN->fetch_assoc()) {
        $recent[] = $row;
    }
}


$productBannerOptions = [];
$notifyCategoryOptions = [];

// Chuẩn bị danh sách sản phẩm (kèm category_id + ảnh cover + giá tối thiểu) để dùng cho banner sản phẩm
try {
    $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
} catch (Throwable $e) {
    $variantTable = 'ecommerce_product_variants';
}

$priceMinExpr = $variantTable
    ? "COALESCE((SELECT MIN(price) FROM `{$variantTable}` v WHERE v.product_id = p.id), 0) AS price_min"
    : '0 AS price_min';

$skuExpr = $variantTable
    ? "COALESCE((SELECT sku_variant FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.sku_variant <> '' ORDER BY v.id ASC LIMIT 1), '') AS sku"
    : "'' AS sku";

$resP = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, p.category_id, {$skuExpr}, {$priceMinExpr} FROM ecommerce_product p ORDER BY p.product_name ASC, p.id DESC LIMIT 1000");
if ($resP) {
    while ($row = $resP->fetch_assoc()) {
        $raw = trim((string)($row['image_url'] ?? ''));
        if ($raw === '') {
            continue;
        }

        $images = [];
        $jsonDecoded = json_decode($raw, true);
        if (is_array($jsonDecoded) && !empty($jsonDecoded)) {
            foreach ($jsonDecoded as $candidate) {
                $candidate = trim((string)$candidate);
                if ($candidate !== '') {
                    $images[] = $candidate;
                }
            }
        }

        if (empty($images)) {
            $parts = preg_split('/[\r\n,|]+/', $raw);
            if (is_array($parts)) {
                foreach ($parts as $candidate) {
                    $candidate = trim((string)$candidate);
                    if ($candidate !== '') {
                        $images[] = $candidate;
                    }
                }
            }
        }

        if (empty($images)) {
            continue;
        }

        $normalized = [];
        foreach ($images as $img) {
            $img = trim((string)$img);
            if ($img === '') {
                continue;
            }
            if (!preg_match('/^https?:\/\//i', $img) && strpos($img, 'data:image/') !== 0) {
                $img = '/' . ltrim($img, '/\\');
            }
            $normalized[$img] = true;
        }
        $finalImages = array_keys($normalized);
        if (empty($finalImages)) {
            continue;
        }

        $productBannerOptions[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => trim((string)($row['product_name'] ?? ('SP #' . (int)($row['id'] ?? 0)))),
            'sku' => trim((string)($row['sku'] ?? '')),
            'category_id' => (int)($row['category_id'] ?? 0),
            'cover' => $finalImages[0],
            'price' => (int)($row['price_min'] ?? 0),
        ];
    }
}

// Chuẩn bị danh sách danh mục cho bước chọn danh mục -> sản phẩm
try {
    $categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category', 'list_category']) : 'ecommerce_category';
} catch (Throwable $e) {
    $categoryTable = 'ecommerce_category';
}
if ($categoryTable) {
    $qCat = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY name ASC, id ASC");
    if ($qCat) {
        while ($c = $qCat->fetch_assoc()) {
            $notifyCategoryOptions[] = [
                'id' => (int)($c['id'] ?? 0),
                'name' => (string)($c['name'] ?? ''),
            ];
        }
    }
}
?>
<div class="container-fluid py-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <a href="index.php" class="header-icon rounded-3 d-flex align-items-center justify-content-center text-decoration-none" style="width:48px;height:48px;min-width:48px;background-color:rgba(12,76,41,.08)!important;color:var(--theme-primary,#0c4c29)!important;border:1px solid rgba(12,76,41,.15);">
                <i class="bi bi-megaphone-fill fs-4"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size:1.45rem;color:#1e293b!important;letter-spacing:-.01em;">Thông báo khuyến mãi</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="notifyMeta" style="font-size:.72rem;">Tổng: <?= count($recent) ?> thông báo</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size:.82rem;line-height:1.45;max-width:620px;">
                    Soạn và quản lý thông báo khuyến mãi gửi tới khách hàng. Hỗ trợ 5 mẫu hiển thị, gắn sản phẩm, hẹn giờ và phân nhóm đối tượng.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size:.78rem;line-height:1.4;">
                    Quản lý thông báo khuyến mãi gửi khách hàng.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm" id="notifyComposeOpen" style="font-size:.88rem;font-weight:600;height:40px;">
                <i class="bi bi-plus-lg fs-5"></i>
                <span class="d-none d-sm-inline">Tạo thông báo</span>
                <span class="d-inline d-sm-none">Tạo mới</span>
            </button>
        </div>
    </div>

    <!-- KPI SUMMARY CARDS -->
    <div class="mb-4 grid-4" id="summaryGrid">
        <div class="summary-card active" data-nf-tab="all">
            <div class="d-flex flex-column"><span>Tổng thông báo</span><strong class="mt-1" id="nfKpiAll">0</strong></div>
            <div class="summary-icon"><i class="bi bi-collection-fill fs-5"></i></div>
        </div>
        <div class="summary-card" data-nf-tab="active">
            <div class="d-flex flex-column"><span>Đang bật</span><strong class="mt-1" id="nfKpiActive">0</strong></div>
            <div class="summary-icon"><i class="bi bi-bell-fill fs-5"></i></div>
        </div>
        <div class="summary-card" data-nf-tab="scheduled">
            <div class="d-flex flex-column"><span>Đã hẹn giờ</span><strong class="mt-1" id="nfKpiScheduled">0</strong></div>
            <div class="summary-icon"><i class="bi bi-clock-history fs-5"></i></div>
        </div>
        <div class="summary-card" data-nf-tab="targeted">
            <div class="d-flex flex-column"><span>Cá nhân hoá</span><strong class="mt-1" id="nfKpiTargeted">0</strong></div>
            <div class="summary-icon"><i class="bi bi-person-bounding-box fs-5"></i></div>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background:#fff;border:1px solid var(--order-border,#e5e7eb)!important;">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.68rem;letter-spacing:.03em;">Tìm kiếm thông báo</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" style="left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem;pointer-events:none;"></i>
                        <input type="text" id="notifySearchBox" class="form-control" placeholder="Tìm tiêu đề, nội dung, mẫu..." style="padding-left:38px!important;border-radius:10px;height:42px;border-color:#cbd5e1;font-size:.9rem;">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.68rem;letter-spacing:.03em;">Mẫu</label>
                    <select id="notifyFilterTpl" class="form-select" style="border-radius:10px;height:42px;border-color:#cbd5e1;font-size:.9rem;">
                        <option value="all">Tất cả mẫu</option>
                        <option value="TPL1">Mẫu 1 — Thumb + Banner</option>
                        <option value="TPL2">Mẫu 2 — + 3 sản phẩm</option>
                        <option value="TPL3">Mẫu 3 — + 3 ảnh upload</option>
                        <option value="TPL4">Mẫu 4 — Icon + Banner</option>
                        <option value="TPL5">Mẫu 5 — Đơn giản</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.68rem;letter-spacing:.03em;">Đối tượng</label>
                    <select id="notifyFilterTarget" class="form-select" style="border-radius:10px;height:42px;border-color:#cbd5e1;font-size:.9rem;">
                        <option value="all">Tất cả</option>
                        <option value="broadcast">Gửi toàn bộ</option>
                        <option value="targeted">Cá nhân</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.68rem;letter-spacing:.03em;">Sắp xếp</label>
                    <select id="notifySortOrder" class="form-select" style="border-radius:10px;height:42px;border-color:#cbd5e1;font-size:.9rem;">
                        <option value="id_desc">Mới nhất</option>
                        <option value="id_asc">Cũ nhất</option>
                        <option value="title_asc">Tiêu đề A-Z</option>
                        <option value="title_desc">Tiêu đề Z-A</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="p-2 rounded-3 d-inline-flex" style="background-color:rgba(12,76,41,.08);color:var(--theme-primary,#0c4c29);"><i class="bi bi-list-stars"></i></span>
                <h6 class="mb-0 fw-bold text-dark">Danh sách thông báo</h6>
                <span class="text-muted small ms-1">Kéo–thả icon để sắp xếp thứ tự ưu tiên</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped" id="notifyTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-center ps-3" style="width:60px;">#</th>
                        <th>Nội dung</th>
                        <th style="width:120px;">Mẫu</th>
                        <th style="width:160px;">Đối tượng</th>
                        <th style="width:170px;">Lịch gửi</th>
                        <th class="text-center" style="width:90px;">Hiển thị</th>
                        <th class="text-end pe-4" style="width:120px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <?php
                        $bodyRaw = trim((string)($row['body'] ?? ''));
                        $tplLabel = 'TPL4';
                        $snippet = '';
                        if ($bodyRaw !== '') {
                            $decoded = json_decode($bodyRaw, true);
                            if (is_array($decoded) && (($decoded['schema'] ?? '') === 'notx_v2')) {
                                $tplCode = strtoupper((string)($decoded['template'] ?? 'TPL1'));
                                $tplLabel = $tplCode !== '' ? $tplCode : 'TPL1';
                                $snippet = trim((string)($decoded['subtitle'] ?? ''));
                                if ($snippet === '') {
                                    $snippet = trim(strip_tags((string)($decoded['content'] ?? '')));
                                }
                            } else {
                                $snippet = trim(strip_tags($bodyRaw));
                            }
                        }
                        if ($snippet !== '' && mb_strlen($snippet, 'UTF-8') > 110) {
                            $snippet = mb_substr($snippet, 0, 110, 'UTF-8') . '...';
                        }
                        $uid = (int)($row['user_id'] ?? 0);
                        $isBroadcast = $uid === 0;
                        $sendAt = trim((string)($row['send_at'] ?? ''));
                        // Tone-by-template badge
                        $tplBg = '#f1f5f9'; $tplCol = '#475569'; $tplBd = '#e2e8f0';
                        switch ($tplLabel){
                            case 'TPL1': $tplBg = '#fef2f2'; $tplCol = '#dc2626'; $tplBd = '#fecaca'; break;
                            case 'TPL2': $tplBg = '#ecfdf5'; $tplCol = '#166534'; $tplBd = '#bbf7d0'; break;
                            case 'TPL3': $tplBg = '#eff6ff'; $tplCol = '#1d4ed8'; $tplBd = '#dbeafe'; break;
                            case 'TPL4': $tplBg = '#fff7ed'; $tplCol = '#c2410c'; $tplBd = '#fed7aa'; break;
                            case 'TPL5': $tplBg = '#f5f3ff'; $tplCol = '#6d28d9'; $tplBd = '#ddd6fe'; break;
                        }
                    ?>
                    <tr data-row='<?= h(json_encode($row, JSON_UNESCAPED_UNICODE)) ?>' data-tpl="<?= h($tplLabel) ?>" data-target="<?= $isBroadcast ? 'broadcast' : 'targeted' ?>" data-scheduled="<?= $sendAt !== '' ? '1' : '0' ?>" data-active="<?= (int)($row['is_active'] ?? 0) ?>">
                        <td class="text-center ps-3">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-grip-vertical js-drag-handle fs-5"></i>
                                <span class="text-muted small">#<?= (int)$row['id'] ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark-emphasis" style="font-size:.92rem; line-height:1.35;"><?= h($row['title'] ?? '') ?></div>
                            <?php if ($snippet !== ''): ?>
                                <div class="text-muted small mt-1" style="line-height:1.4;"><?= h($snippet) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-uppercase" style="font-size:.65rem; padding:3px 8px; border-radius:5px; background-color:<?= $tplBg ?>; color:<?= $tplCol ?>; border:1px solid <?= $tplBd ?>;">
                                <i class="bi bi-layout-text-window me-1"></i><?= h($tplLabel) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isBroadcast): ?>
                                <span class="badge" style="font-size:.7rem;padding:3px 8px;border-radius:5px;background-color:rgba(12,76,41,.08);color:var(--theme-primary,#0c4c29);border:1px solid rgba(12,76,41,.18);"><i class="bi bi-broadcast me-1"></i>Tất cả KH</span>
                            <?php else: ?>
                                <span class="badge" style="font-size:.7rem;padding:3px 8px;border-radius:5px;background-color:#eff6ff;color:#2563eb;border:1px solid #dbeafe;"><i class="bi bi-person-fill me-1"></i>User #<?= $uid ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sendAt !== ''): ?>
                                <div class="small text-dark"><i class="bi bi-clock-history me-1 text-warning"></i><?= h($sendAt) ?></div>
                                <div class="text-muted" style="font-size:.7rem;">Đã hẹn giờ</div>
                            <?php else: ?>
                                <div class="small text-muted"><i class="bi bi-lightning-charge-fill me-1 text-success"></i>Gửi ngay</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block m-0">
                                <input class="form-check-input jsToggleNotice" type="checkbox" role="switch" <?= ((int)($row['is_active'] ?? 0)===1)?'checked':'' ?> >
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <div class="voucher-actions">
                                <button class="btn btn-outline-primary jsEditNotice" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-outline-danger jsDelNotice" title="Xoá"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($recent)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-megaphone text-secondary" style="font-size:2.5rem;opacity:.5;"></i>
                    <div class="mt-2">Chưa có thông báo nào. Bấm <strong>Tạo thông báo</strong> để bắt đầu.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>


                
</div>


<div class="modal fade" id="notifyComposeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="notifyModalTitle"><i class="bi bi-pencil-square me-2"></i>Tạo thông báo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Step Indicator -->
                <div class="step-header">
                    <div class="step-item active" id="stepIndicator1">
                        <span class="step-number">1</span>
                        <span>Chọn mẫu</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-item" id="stepIndicator2">
                        <span class="step-number">2</span>
                        <span>Soạn nội dung</span>
                    </div>
                </div>

                <!-- Step 1: Template Selection -->
                <div class="step-container step-active" id="step1Container">
                    <div class="notice-template-grid">
                        <div class="notice-template-item js-template-pick" data-template-preset="tpl1">
                            <div class="notice-template-demo">
                                <div class="notice-template-demo-main">
                                    <div class="notice-template-demo-thumb"><img src="https://dummyimage.com/120x120/fee2e2/b91c1c&text=T" alt="tpl1"></div>
                                    <div class="notice-template-demo-lines"><span class="notice-template-demo-line"></span><span class="notice-template-demo-line w-85"></span><span class="notice-template-demo-line w-70"></span></div>
                                </div>
                                <div class="notice-template-demo-footer"><span class="notice-template-demo-chip full"></span></div>
                            </div>
                            <div class="notice-template-demo-meta"><h6>Mẫu 1</h6><span class="notice-template-demo-label">Thumb + Banner</span></div>
                        </div>
                        <div class="notice-template-item js-template-pick" data-template-preset="tpl2">
                            <div class="notice-template-demo">
                                <div class="notice-template-demo-main">
                                    <div class="notice-template-demo-thumb"><img src="https://dummyimage.com/120x120/dcfce7/166534&text=T" alt="tpl2"></div>
                                    <div class="notice-template-demo-lines"><span class="notice-template-demo-line"></span><span class="notice-template-demo-line w-85"></span><span class="notice-template-demo-line w-70"></span></div>
                                </div>
                                <div class="notice-template-demo-footer"><span class="notice-template-demo-chip sm"></span><span class="notice-template-demo-chip sm"></span><span class="notice-template-demo-chip sm"></span></div>
                            </div>
                            <div class="notice-template-demo-meta"><h6>Mẫu 2</h6><span class="notice-template-demo-label">Thumb + Banner + 3 sản phẩm</span></div>
                        </div>
                        <div class="notice-template-item js-template-pick" data-template-preset="tpl3">
                            <div class="notice-template-demo">
                                <div class="notice-template-demo-main">
                                    <div class="notice-template-demo-thumb"><img src="https://dummyimage.com/120x120/dbeafe/1d4ed8&text=T" alt="tpl3"></div>
                                    <div class="notice-template-demo-lines"><span class="notice-template-demo-line"></span><span class="notice-template-demo-line w-85"></span><span class="notice-template-demo-line w-70"></span></div>
                                </div>
                                <div class="notice-template-demo-footer"><span class="notice-template-demo-chip sm blue"></span><span class="notice-template-demo-chip sm blue"></span><span class="notice-template-demo-chip sm blue"></span></div>
                            </div>
                            <div class="notice-template-demo-meta"><h6>Mẫu 3</h6><span class="notice-template-demo-label">Thumb + Banner + 3 ảnh upload</span></div>
                        </div>
                        <div class="notice-template-item js-template-pick" data-template-preset="tpl4">
                            <div class="notice-template-demo">
                                <div class="notice-template-demo-main">
                                    <div class="notice-template-demo-thumb"><i class="bi bi-megaphone-fill"></i></div>
                                    <div class="notice-template-demo-lines"><span class="notice-template-demo-line"></span><span class="notice-template-demo-line w-85"></span><span class="notice-template-demo-line w-70"></span></div>
                                </div>
                                <div class="notice-template-demo-footer"><span class="notice-template-demo-chip full" style="background:#fef3c7;border-color:#fde68a;"></span></div>
                            </div>
                            <div class="notice-template-demo-meta"><h6>Mẫu 4</h6><span class="notice-template-demo-label">Icon + Banner</span></div>
                        </div>
                        <div class="notice-template-item js-template-pick" data-template-preset="tpl5">
                            <div class="notice-template-demo">
                                <div class="notice-template-demo-main">
                                    <div class="notice-template-demo-thumb"><i class="bi bi-bell-fill"></i></div>
                                    <div class="notice-template-demo-lines"><span class="notice-template-demo-line"></span><span class="notice-template-demo-line w-85"></span><span class="notice-template-demo-line w-55"></span></div>
                                </div>
                                <div class="notice-template-demo-footer"></div>
                            </div>
                            <div class="notice-template-demo-meta"><h6>Mẫu 5</h6><span class="notice-template-demo-label">Đơn giản (chỉ text)</span></div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Form Content -->
                <div class="step-container" id="step2Container">
                    <div class="notice-shell">
                        <div class="notice-cards">
                            <div class="notice-card-bodys">
                                <form id="notifyForm">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="id" value="0">
                                    <input type="hidden" name="type" value="promotion">
                                    <input type="hidden" name="link" value="">

                                    <div class="notice-section">
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Gửi đến</label>
                                                <select class="form-select" name="target" id="notifyTarget">
                                                    <option value="all">Tất cả</option>
                                                    <option value="user">Cá nhân</option>
                                                    <option value="role">Vai trò</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12" id="notifyUserWrap" style="display:none;">
                                                <label class="form-label small fw-semibold">Gửi đến khách hàng</label>
                                                <div class="product-picker border rounded-3 p-2 bg-light">
                                                    <div class="product-picker-toolbar mb-2 gap-2">
                                                        <input type="text" class="form-control form-control-sm" id="notifyUserFilterInput" placeholder="Tìm tên/ID/Email..." style="max-width:180px;">
                                                        <select class="form-select form-select-sm" id="notifyUserSort" style="max-width:120px;">
                                                            <option value="id_desc">Mới nhất</option>
                                                            <option value="id_asc">Cũ nhất</option>
                                                            <option value="name_asc">A-Z</option>
                                                            <option value="name_desc">Z-A</option>
                                                        </select>
                                                    </div>
                                                    <div class="product-picker-list thin-scroll" id="notifyUserPickerList" style="max-height:150px;">
                                                        <!-- Rendered by JS -->
                                                    </div>
                                                    <input type="hidden" name="user_id" id="notifyUserIdInput">
                                                    <div class="mt-2 x-small text-primary fw-bold" id="notifyUserSelectedLabel">Chưa chọn khách hàng</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6" id="notifyRoleWrap" style="display:none;">
                                                <label class="form-label small fw-semibold">Vai trò</label>
                                                <select class="form-select" name="role">
                                                    <option value="user">User</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Hẹn giờ</label>
                                                <input class="form-control" type="datetime-local" name="send_at">
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-12 col-md-12">
                                                <label class="form-label small fw-semibold">Tiêu đề</label>
                                                <input class="form-control" name="title" id="notifyTitleInput" placeholder="VD: Lịch bảo trì hệ thống" maxlength="120" required>
                                                <span class="char-counter" id="notifyTitleCounter">0/120</span>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small fw-semibold">Mô tả ngắn </label>
                                                <input class="form-control" id="notifySubtitle" placeholder="VD: Áp dụng trong 24h tới" maxlength="255">
                                                <span class="char-counter" id="notifySubtitleCounter">0/255</span>
                                            </div>
                                            <div class="col-12 col-md-8">
                                                <label class="form-label small fw-semibold">Mẫu đang chọn</label>
                                                <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-light">
                                                    <span class="fw-bold small" id="notifyTemplateLabel">Mẫu 1</span>
                                                    <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none jsToStep1" style="font-size:.75rem;">Đổi mẫu</button>
                                                </div>
                                                <input type="hidden" id="notifyTemplateCode" value="tpl1">
                                            </div>
                                        </div>
                                    </div>
                                  <!-- /./ --->  
                                    <div class="notice-section">
                                        <div class="notice-builder-grid">
                                            <div class="row g-2">
                                                <div class="col-12 col-md-12">
                                                    <div class="row g-2">
                                                        <div class="col-6 col-md-4" id="notifyThumbImageWrap">
                                                            <label class="form-label small fw-semibold">Tải lên ảnh đại diện</label>
                                                            <div class="notice-product-slot">
                                                                <div class="notice-product-slot-tools">
                                                                    <button type="button" class="btn btn-light" id="notifyThumbUploadBtn" title="Tải ảnh lên"><i class="bi bi-upload"></i></button>
                                                                    <button type="button" class="btn btn-light" id="notifyThumbClearBtn" title="Xóa ảnh"><i class="bi bi-x-lg"></i></button>
                                                                </div>
                                                                <input type="file" class="d-none" id="notifyThumbUpload" accept="image/*">
                                                                <input type="hidden" id="notifyThumbImage" value="">
                                                                <div class="notice-product-preview" id="notifyThumbPreview"><i class="bi bi-image"></i></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-8" id="notifyMainBannerWrap">
                                                            <label class="form-label small fw-semibold">Tải lên ảnh bìa</label>
                                                            <div class="notice-product-slot">
                                                                <div class="notice-product-slot-tools">
                                                                    <button type="button" class="btn btn-light" id="notifyMainBannerUploadBtn" title="Tải ảnh lên"><i class="bi bi-upload"></i></button>
                                                                    <button type="button" class="btn btn-light" id="notifyMainBannerClearBtn" title="Xóa ảnh"><i class="bi bi-x-lg"></i></button>
                                                                </div>
                                                                <input type="file" class="d-none" id="notifyMainBannerUpload" accept="image/*">
                                                                <input type="hidden" id="notifyMainBanner" value="">
                                                                <div class="notice-product-preview" id="notifyMainBannerPreview" style="aspect-ratio:21/9;"><i class="bi bi-image"></i></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12" id="notifyProductPickerWrap">
                                                    <label class="form-label small fw-semibold text-primary"><i class="bi bi-box-seam me-1"></i>Chọn 3 sản phẩm đính kèm</label>
                                                    <input type="hidden" id="notifyApplyCategoryIds" value="">
                                                    <input type="hidden" id="notifyApplyProductIds" value="">
                                                    <div class="row g-2">
                                                        <div class="col-12 col-md-12">
                                                            <label class="form-label small fw-semibold mb-1">Lọc danh mục</label>
                                                            <div class="product-picker">
                                                                <div class="product-picker-toolbar">
                                                                    <input type="text" class="form-control form-control-sm" id="notifyCategoryFilterInput" placeholder="Lọc theo tên/ID" style="max-width:200px;">
                                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="notifyBtnCategoryPickAll">Tất cả</button>
                                                                    <button type="button" class="btn btn-light btn-sm" id="notifyBtnCategoryPickClear">Bỏ</button>
                                                                    <span class="small text-muted align-self-center">Đã chọn: <b id="notifyPickedCategoryCount">0</b></span>
                                                                </div>
                                                                <div class="product-picker-list" id="notifyCategoryPickerList"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12">
                                                            <label class="form-label small fw-semibold mb-1">Chọn sản phẩm</label>
                                                            <div class="product-picker">
                                                                <div class="product-picker-toolbar">
                                                                    <input type="text" class="form-control form-control-sm" id="notifyProductFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:200px;">
                                                                    <select class="form-select form-select-sm" id="notifyProductSort" style="max-width:140px;">
                                                                        <option value="">Sắp xếp</option>
                                                                        <option value="name_asc">A-Z</option>
                                                                        <option value="price_asc">Giá tăng</option>
                                                                        <option value="price_desc">Giá giảm</option>
                                                                    </select>
                                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="notifyBtnProductPickAll">Tất cả</button>
                                                                    <button type="button" class="btn btn-light btn-sm" id="notifyBtnProductPickClear">Bỏ</button>
                                                                    <span class="small text-muted align-self-center">Đã chọn: <b id="notifyPickedCount">0</b></span>
                                                                </div>
                                                                <div class="product-picker-list" id="notifyProductPickerList"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 mt-2" id="notifyProductBannersWrap">
                                                            <div class="notice-banner-grid" id="notifyProductPreviewGrid">
                                                                <?php for ($slot = 1; $slot <= 3; $slot++): ?>
                                                                    <div class="notice-product-slot" data-product-slot="<?= $slot ?>">
                                                                        <div class="notice-product-slot-tools">
                                                                            <button type="button" class="btn btn-light jsSlotClearBtn" data-slot="<?= $slot ?>" data-type="product" title="Bỏ chọn"><i class="bi bi-x-lg"></i></button>
                                                                        </div>
                                                                        <input type="hidden" id="notifyProductBanner<?= $slot ?>" value="">
                                                                        <input type="hidden" id="notifyProductId<?= $slot ?>" value="">
                                                                        <div class="notice-product-preview" id="notifyProductPreview<?= $slot ?>"><i class="bi bi-plus-lg"></i></div>
                                                                        <div class="notice-product-meta" id="notifyProductMeta<?= $slot ?>">Trống <?= $slot ?></div>
                                                                    </div>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12" id="notifyImageSlotsWrap">
                                                    <label class="form-label small fw-semibold text-primary"><i class="bi bi-images me-1"></i>Upload 3 ảnh đính kèm</label>
                                                    <div class="notice-banner-grid" id="notifyImageSlotsGrid">
                                                        <?php for ($si = 1; $si <= 3; $si++): ?>
                                                            <div class="notice-product-slot" data-image-slot="<?= $si ?>">
                                                                <div class="notice-product-slot-tools">
                                                                    <button type="button" class="btn btn-light jsSlotUploadBtn" data-slot="<?= $si ?>" title="Tải ảnh lên"><i class="bi bi-upload"></i></button>
                                                                    <button type="button" class="btn btn-light jsSlotClearBtn" data-slot="<?= $si ?>" data-type="image" title="Xóa ảnh"><i class="bi bi-x-lg"></i></button>
                                                                </div>
                                                                <input type="file" class="d-none jsSlotFileInput" data-slot="<?= $si ?>" accept="image/*">
                                                                <input type="hidden" id="notifySlotImage<?= $si ?>" value="">
                                                                <div class="notice-product-preview" id="notifySlotPreview<?= $si ?>"><i class="bi bi-image"></i></div>
                                                                <div class="notice-product-meta" id="notifySlotMeta<?= $si ?>">Ảnh <?= $si ?></div>
                                                            </div>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <!-- Nội dung chi tiết (đặt cuối, full width) -->
                                                <div class="col-12 notice-content-block">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <label class="form-label small fw-semibold mb-0" for="notifyContentEditor">
                                                            <i class="bi bi-text-paragraph text-primary me-1"></i>Nội dung chi tiết
                                                        </label>
                                                        <span class="text-muted" style="font-size:.72rem;">Hỗ trợ định dạng đầy đủ (đậm, nghiêng, danh sách, ảnh, video, link)</span>
                                                    </div>
                                                    <div class="wp-editor-shell wp-editor-shell--wide">
                                                        <textarea class="wp-editor-content" id="notifyContentEditor" data-placeholder="Nhập nội dung thông báo..."></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <textarea name="body" id="notifyBody" class="d-none"></textarea>
                                    </div>
                                    <div class="step-footer">
                                        <button type="button" class="btn btn-link btn-back text-decoration-none jsToStep1"><i class="bi bi-chevron-left me-1"></i> Quay lại</button>
                                        <button type="submit" class="btn btn-primary px-4 fw-bold" id="notifySubmit">Gửi thông báo <i class="bi bi-send-fill ms-1"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="notice-card__">
                            <div class="notice-card-header__"><i class="bi bi-eye"></i> Xem trước</div>
                            <div class="notice-card-body_">
                                <div class="notice-preview__" id="notifyPreview">
                                    <div class="preview-meta mb-2">
                                        <span class="preview-badge badge-info" data-preview="type">Thông tin</span>
                                    </div>
                                    <div class="notice-preview-card" data-preview="card">
                                        <div class="notice-preview-hero d-none" data-preview="hero"></div>
                                        <div class="notice-preview-main">
                                            <div class="notice-preview-thumb" data-preview="thumb"></div>
                                            <div class="notice-preview-head">
                                                <div class="notice-preview-title" data-preview="title">Tiêu đề thông báo</div>
                                                <div class="notice-preview-subtitle" data-preview="subtitle">Mô tả ngắn</div>
                                                <div class="notice-preview-cta" data-preview="linkWrap">
                                                    <a class="btn btn-outline-primary btn-sm" data-preview="link" href="#">Xem chi tiết</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="notice-preview-footer">
                                            <div class="notice-preview-banners" data-preview="banners"></div>
                                            <span class="notice-preview-time" data-preview="footerTime">00:00 01-01-2026</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="<?= $_TinyMceUrl ?>" referrerpolicy="origin"></script>
<?php $mceToolbarVer = @filemtime(__DIR__ . '/../assets/js/mce-toolbar.js') ?: time(); ?>
<script src="<?= h($baseUrl) ?>/assets/js/mce-toolbar.js?v=<?= (int)$mceToolbarVer ?>"></script>


<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_admin/ajax/notification.php';
    const API_FALLBACK = '/core_admin/ajax/notification.php';
    const USER_CATALOG = <?= json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const PRODUCT_CATALOG = <?= json_encode($productBannerOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CATEGORY_OPTIONS = <?= json_encode($notifyCategoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const RECENT_ROWS = <?= json_encode($recent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const $target = $('#notifyTarget');
    const $userWrap = $('#notifyUserWrap');
    const $roleWrap = $('#notifyRoleWrap');
    const $body = $('#notifyBody');
    const $form = $('#notifyForm');
    const $submit = $('#notifySubmit');
    const $preview = $('#notifyPreview');
    const $titleInput = $form.find('[name=title]');
    const $templateCode = $('#notifyTemplateCode');
    const $templateLabel = $('#notifyTemplateLabel');
    const $templateOpen = $('#notifyTemplateOpen');
    const $thumbImage = $('#notifyThumbImage');
    const $thumbUploadBtn = $('#notifyThumbUploadBtn');
    const $thumbUpload = $('#notifyThumbUpload');
    const $thumbClearBtn = $('#notifyThumbClearBtn');
    const $thumbPreview = $('#notifyThumbPreview');
    const $subtitle = $('#notifySubtitle');
    const $contentEditor = $('#notifyContentEditor');
    const $mainBanner = $('#notifyMainBanner');
    const $mainBannerUploadBtn = $('#notifyMainBannerUploadBtn');
    const $mainBannerUpload = $('#notifyMainBannerUpload');
    const $mainBannerClearBtn = $('#notifyMainBannerClearBtn');
    const $mainBannerPreview = $('#notifyMainBannerPreview');
    const $productBanner1 = $('#notifyProductBanner1');
    const $productBanner2 = $('#notifyProductBanner2');
    const $productBanner3 = $('#notifyProductBanner3');
    const $productId1 = $('#notifyProductId1');
    const $productId2 = $('#notifyProductId2');
    const $productId3 = $('#notifyProductId3');
    const $productMeta1 = $('#notifyProductMeta1');
    const $productMeta2 = $('#notifyProductMeta2');
    const $productMeta3 = $('#notifyProductMeta3');
    const $productPreview1 = $('#notifyProductPreview1');
    const $productPreview2 = $('#notifyProductPreview2');
    const $productPreview3 = $('#notifyProductPreview3');
    const $applyCategoryIds = $('#notifyApplyCategoryIds');
    const $applyProductIds = $('#notifyApplyProductIds');
    const $composeOpen = $('#notifyComposeOpen');
    const $thumbImageWrap = $('#notifyThumbImageWrap');
    const $mainBannerWrap = $('#notifyMainBannerWrap');
    const $productBannersWrap = $('#notifyProductBannersWrap');
    const $productPickerWrap = $('#notifyProductPickerWrap');
    const $imageSlotsWrap = $('#notifyImageSlotsWrap');
    const $productSort = $('#notifyProductSort');
    const $userFilterInput = $('#notifyUserFilterInput');
    const $userSort = $('#notifyUserSort');
    const $userPickerList = $('#notifyUserPickerList');
    const $userIdInput = $('#notifyUserIdInput');
    const $userSelectedLabel = $('#notifyUserSelectedLabel');
    const templateNameMap = {
        tpl1: 'Mẫu 1',
        tpl2: 'Mẫu 2',
        tpl3: 'Mẫu 3',
        tpl4: 'Mẫu 4',
        tpl5: 'Mẫu 5'
    };

    const composeModalEl = document.getElementById('notifyComposeModal');
    const composeModal = (window.bootstrap && composeModalEl) ? new bootstrap.Modal(composeModalEl) : null;
    const $notifyTable = $('#notifyTable');
    const $notifySearch = $('#notifySearchBox');
    const $editorShell = $contentEditor.closest('.wp-editor-shell');

    let mceReady = false;
    let pendingEditorHtml = '';
    let resumeComposeAfterTemplate = false;

    const getMceEditor = () => {
        if (!window.tinymce || typeof window.tinymce.get !== 'function') return null;
        return window.tinymce.get('notifyContentEditor');
    };

    const applyEditorContent = () => {
        const editor = getMceEditor();
        if (!editor) return;
        const html = String(pendingEditorHtml || $contentEditor.val() || '').trim();
        editor.setContent(html);
        pendingEditorHtml = '';
    };

    const initNotifyEditor = () => {
        if (mceReady) return;
        if (!window.tinymce || typeof window.tinymce.init !== 'function') return;
        if (typeof window.initMceToolbar !== 'function') {
            notify('Không tải được mce-toolbar.js', 'warning');
            return;
        }
        try {
            window.initMceToolbar({
                selector: '#notifyContentEditor',
                uploadUrl: '<?= h($baseUrl) ?>/core_admin/ecommerce/product.php',
                baseUrl: '<?= h($baseUrl) ?>',
                onChange: () => {
                    syncPreview();
                },
                onReady: (editor) => {
                    mceReady = true;
                    $editorShell.addClass('is-mce');
                    applyEditorContent();
                }
            });
        } catch (error) {
            console.error('Init TinyMCE failed:', error);
            notify('Không thể khởi tạo TinyMCE', 'warning');
        }
    };

    if (composeModalEl) {
        composeModalEl.addEventListener('shown.bs.modal', function(){
            const existing = getMceEditor();
            if (existing) {
                const html = String(existing.getContent() || '').trim();
                existing.remove();
                mceReady = false;
                if (html) {
                    pendingEditorHtml = html;
                    $contentEditor.val(html);
                }
            }
            initNotifyEditor();
        });
    }

    const productSlots = {
        1: { input: $productBanner1, idInput: $productId1, preview: $productPreview1, meta: $productMeta1 },
        2: { input: $productBanner2, idInput: $productId2, preview: $productPreview2, meta: $productMeta2 },
        3: { input: $productBanner3, idInput: $productId3, preview: $productPreview3, meta: $productMeta3 }
    };

    const notify = (msg, type = 'info') => {
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    };

    function extractAjaxError(xhr){
        const raw = String(xhr?.responseText || '').trim();
        if (!raw) return '';
        try {
            const parsed = JSON.parse(raw);
            if (parsed && parsed.msg) return String(parsed.msg);
        } catch (e) {}
        return raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
    }

    function postApi(data, onOk){
        const run = (url, onFail) => {
            $.post(url, data, onOk, 'json').fail(onFail);
        };

        run(API, function(xhr){
            if (API !== API_FALLBACK) {
                run(API_FALLBACK, function(xhr2){
                    notify(extractAjaxError(xhr2) || extractAjaxError(xhr) || 'Lỗi kết nối server', 'error');
                });
                return;
            }
            notify(extractAjaxError(xhr) || 'Lỗi kết nối server', 'error');
        });
    }

    function syncTarget(){
        const val = $target.val();
        $userWrap.toggle(val === 'user');
        $roleWrap.toggle(val === 'role');
        if (val === 'user') renderNotifyUserPicker();
    }

    function typeBadgeClass(){ return 'badge-info'; }

    function typeLabel(){ return 'Khuyến mãi'; }

    function parseBodyPayload(raw){
        const txt = String(raw || '').trim();
        if (!txt) return null;
        try {
            const parsed = JSON.parse(txt);
            if (parsed && parsed.schema === 'notx_v2') {
                return parsed;
            }
        } catch (e) {}
        return null;
    }

    function stripHtml(value){
        const raw = String(value || '').trim();
        if (!raw) return '';
        const div = document.createElement('div');
        div.innerHTML = raw;
        return String(div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function norm(val){
        return String(val || '').trim();
    }

    // Chỉ dùng để HIỂN THỊ preview: ưu tiên media domain. Giá trị lưu (input.val)
    // vẫn giữ path tương đối qua norm() — KHÔNG ghi đè bằng URL tuyệt đối.
    function toAbs(val){
        const raw = String(val || '').trim();
        if (!raw) return '';
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(raw);
        return raw;
    }

    function getEditorHtml(){
        const editor = getMceEditor();
        if (editor) {
            return String(editor.getContent() || '').trim();
        }
        return String($contentEditor.val() || '').trim();
    }

    function getEditorText(){
        const editor = getMceEditor();
        if (editor) {
            return String(editor.getContent({ format: 'text' }) || '').replace(/\s+/g, ' ').trim();
        }
        return String($contentEditor.val() || '').replace(/\s+/g, ' ').trim();
    }

    function setEditorHtml(value){
        const html = String(value || '').trim();
        pendingEditorHtml = html;
        const editor = getMceEditor();
        if (editor) {
            editor.setContent(html);
            return;
        }
        $contentEditor.val(html);
    }

    function escapeHtml(value){
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }


    function isIconTemplate(code){
        const c = String(code || '').trim();
        return c === 'tpl4' || c === 'tpl5';
    }

    function setThumbImage(value){
        const finalValue = norm(value);
        $thumbImage.val(finalValue);
        if (finalValue) {
            $thumbPreview.html('<img src="' + toAbs(finalValue) + '" alt="thumb">');
        } else {
            $thumbPreview.html('<i class="bi bi-image"></i>');
        }
    }

    function setMainBanner(value){
        const finalValue = norm(value);
        $mainBanner.val(finalValue);
        if (finalValue) {
            $mainBannerPreview.html('<img src="' + toAbs(finalValue) + '" alt="main-banner">');
        } else {
            $mainBannerPreview.html('<i class="bi bi-image"></i>');
        }
    }

    function collectBanners(){
        return [
            norm($mainBanner.val()),
            norm($productBanner1.val()),
            norm($productBanner2.val()),
            norm($productBanner3.val())
        ].filter(Boolean);
    }

    function setSlotPreview(slotIndex){
        const slot = productSlots[slotIndex];
        if (!slot) return;
        const value = norm(slot.input.val());
        if (value) {
            slot.preview.html('<img src="' + toAbs(value) + '" alt="banner-' + slotIndex + '">');
        } else {
            slot.preview.html('<i class="bi bi-image"></i>');
        }
    }

    function setProductSlot(slotIndex, imageUrl, metaText, productId){
        const slot = productSlots[slotIndex];
        if (!slot) return;
        const value = norm(imageUrl);
        slot.input.val(value);
        if (!value) {
            if (slot.idInput) {
                slot.idInput.val('');
            }
            slot.meta.text('Chưa chọn sản phẩm');
            setSlotPreview(slotIndex);
            return;
        }
        if (slot.idInput) {
            const pid = Number(productId || 0);
            slot.idInput.val(pid > 0 ? String(pid) : '');
        }
        slot.meta.text(metaText || 'Đã chọn sản phẩm');
        setSlotPreview(slotIndex);
    }

    const MAX_NOTIFY_PRODUCTS = 3;

    function getProductById(pid){
        pid = Number(pid || 0);
        if (!pid) return null;
        return (PRODUCT_CATALOG || []).find(item => Number(item?.id || 0) === pid) || null;
    }

    function formatPriceVi(value){
        const n = Number(value || 0);
        if (!Number.isFinite(n) || n <= 0) return '';
        try {
            return n.toLocaleString('vi-VN') + 'đ';
        } catch (e) {
            return n.toString() + 'đ';
        }
    }
    
    // ==== Chọn danh mục & sản phẩm áp dụng (theo logic giống voucher) ====

    function getSelectedNotifyCategoryIds(){
        const raw = String($applyCategoryIds.val() || '').trim();
        if (!raw) return [];
        const parts = raw.split(',').map(v => parseInt(String(v).trim(), 10)).filter(v => Number.isFinite(v) && v > 0);
        return [...new Set(parts)];
    }

    function setSelectedNotifyCategoryIds(ids){
        const unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0))];
        $applyCategoryIds.val(unique.join(','));
        $('#notifyPickedCategoryCount').text(unique.length);
    }

    function getSelectedNotifyProductIds(){
        const raw = String($applyProductIds.val() || '').trim();
        if (!raw) return [];
        const parts = raw.split(',').map(v => parseInt(String(v).trim(), 10)).filter(v => Number.isFinite(v) && v > 0);
        return [...new Set(parts)];
    }

    function updateProductSlotsFromSelection(ids){
        const list = (ids || []).slice(0, MAX_NOTIFY_PRODUCTS);
        // Mẫu 2: Mỗi slot 1 sản phẩm
        for (let i = 0; i < 3; i++) {
            const slotIndex = i + 1;
            const pid = Number(list[i] || 0);
            if (!pid) {
                setProductSlot(slotIndex, '', 'Chưa chọn sản phẩm');
                continue;
            }
            const product = getProductById(pid);
            if (!product) {
                setProductSlot(slotIndex, '', 'Không tìm thấy SP');
                continue;
            }
            const cover = String(product.cover || '').trim();
            const label = 'SP #' + pid + ' - ' + String(product.name || '');
            setProductSlot(slotIndex, cover, label, pid);
        }
    }

    function setSelectedNotifyProductIds(ids){
        let unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0))];
        if (unique.length > MAX_NOTIFY_PRODUCTS) {
            unique = unique.slice(0, MAX_NOTIFY_PRODUCTS);
        }
        $applyProductIds.val(unique.join(','));
        $('#notifyPickedCount').text(unique.length);
        updateProductSlotsFromSelection(unique);
    }

    function renderNotifyCategoryPicker(){
        const selected = getSelectedNotifyCategoryIds();
        const selectedSet = new Set(selected);
        const keyword = String($('#notifyCategoryFilterInput').val() || '').trim().toLowerCase();
        const list = (CATEGORY_OPTIONS || []).filter((c) => {
            if (!keyword) return true;
            const hay = `${c.name || ''} ${c.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });

        if (!list.length) {
            $('#notifyCategoryPickerList').html('<div class="product-picker-empty">Không có danh mục phù hợp.</div>');
            setSelectedNotifyCategoryIds(selected);
            return;
        }

        const html = list.map((c) => {
            const id = Number(c.id || 0);
            const checked = selectedSet.has(id) ? 'checked' : '';
            const name = escapeHtml(c.name || 'Danh mục');
            return `
                <label class="product-picker-item">
                    <input type="checkbox" class="form-check-input me-1 jsNotifyCategoryPick" value="${id}" ${checked}>
                    <div>
                        <div class="fw-semibold">${name} <span class="meta">#${id}</span></div>
                    </div>
                </label>
            `;
        }).join('');
        $('#notifyCategoryPickerList').html(html);
        setSelectedNotifyCategoryIds(selected);
    }

    function renderNotifyProductPicker(){
        const selected = getSelectedNotifyProductIds();
        const selectedSet = new Set(selected);
        const keyword = String($('#notifyProductFilterInput').val() || '').trim().toLowerCase();
        const selectedCategories = getSelectedNotifyCategoryIds();
        const selectedCategorySet = new Set(selectedCategories.map(id => Number(id)));
        const sortVal = String($productSort.val() || '').trim();

        let list = (PRODUCT_CATALOG || []).filter((p) => {
            if (selectedCategories.length > 0 && !selectedCategorySet.has(Number(p.category_id || 0))) return false;
            if (!keyword) return true;
            const hay = `${p.name || ''} ${p.sku || ''} ${p.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });

        if (list.length) {
            list = list.slice();
            if (sortVal === 'name_asc' || sortVal === 'name_desc') {
                list.sort((a, b) => {
                    const an = String(a.name || '').toLowerCase();
                    const bn = String(b.name || '').toLowerCase();
                    if (an === bn) return 0;
                    const cmp = an < bn ? -1 : 1;
                    return sortVal === 'name_asc' ? cmp : -cmp;
                });
            } else if (sortVal === 'price_asc' || sortVal === 'price_desc') {
                list.sort((a, b) => {
                    const ap = Number(a.price || 0);
                    const bp = Number(b.price || 0);
                    if (ap === bp) return 0;
                    const cmp = ap < bp ? -1 : 1;
                    return sortVal === 'price_asc' ? cmp : -cmp;
                });
            }
        }

        if (!list.length) {
            $('#notifyProductPickerList').html('<div class="product-picker-empty">Không có sản phẩm phù hợp.</div>');
            setSelectedNotifyProductIds(selected);
            return;
        }

        const html = list.map((p) => {
            const id = Number(p.id || 0);
            const checked = selectedSet.has(id) ? 'checked' : '';
            const name = escapeHtml(p.name || 'Sản phẩm');
            const sku = escapeHtml(p.sku || 'â€”');
            const priceText = formatPriceVi(p.price);
            return `
                <label class="product-picker-item">
                    <input type="checkbox" class="form-check-input me-1 jsNotifyProductPick" value="${id}" ${checked}>
                    <div>
                        <div class="fw-semibold">${name} <span class="meta">#${id}</span></div>
                        <div class="meta">SKU: ${sku}${priceText ? ' • Giá từ: ' + priceText : ''}</div>
                    </div>
                </label>
            `;
        }).join('');
        $('#notifyProductPickerList').html(html);
        setSelectedNotifyProductIds(selected);
    }
    
    function renderNotifyUserPicker(){
        const keyword = String($userFilterInput.val() || '').trim().toLowerCase();
        const sortVal = String($userSort.val() || '').trim();
        const selectedId = Number($userIdInput.val() || 0);

        let list = (USER_CATALOG || []).filter((u) => {
            if (!keyword) return true;
            const hay = `${u.full_name || ''} ${u.username || ''} ${u.email || ''} ${u.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });

        if (list.length) {
            list = list.slice();
            list.sort((a, b) => {
                if (sortVal === 'name_asc' || sortVal === 'name_desc') {
                    const an = String(a.full_name || a.username || '').toLowerCase();
                    const bn = String(b.full_name || b.username || '').toLowerCase();
                    const cmp = an.localeCompare(bn, 'vi');
                    return sortVal === 'name_asc' ? cmp : -cmp;
                } else if (sortVal === 'id_asc') {
                    return Number(a.id || 0) - Number(b.id || 0);
                } else {
                    return Number(b.id || 0) - Number(a.id || 0);
                }
            });
        }

        if (!list.length) {
            $userPickerList.html('<div class="product-picker-empty">Không tìm thấy khách hàng.</div>');
            return;
        }

        const html = list.map((u) => {
            const id = Number(u.id || 0);
            const active = (id === selectedId) ? 'active' : '';
            const name = escapeHtml(u.full_name || u.username || 'Khách hàng');
            const email = escapeHtml(u.email || '');
            return `
                <div class="product-picker-item jsNotifyUserPick ${active}" data-id="${id}">
                    <div class="flex-grow-1">
                        <div class="fw-bold small">${name} <span class="text-muted">#${id}</span></div>
                        ${email ? `<div class="x-small text-muted">${email}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        $userPickerList.html(html);
    }

    function buildPayload(){
        const template = norm($templateCode.val()) || 'tpl1';
        const thumbType = isIconTemplate(template) ? 'icon' : 'image';
        const editorHtml = getEditorHtml();
        const payload = {
            schema: 'notx_v2',
            template,
            title: norm($titleInput.val()),
            subtitle: norm($subtitle.val()),
            content: editorHtml,
            thumb_type: thumbType,
            thumb_image: norm($thumbImage.val()),
            thumb_icon: 'bi bi-megaphone-fill',
            banners: []
        };

        const mainBannerVal = norm($mainBanner.val());
        if (mainBannerVal) {
            payload.main_banner = mainBannerVal;
        }

        if (template === 'tpl1' || template === 'tpl4') {
            // Mẫu 1 & 4: 1 banner chính
            payload.banners = [mainBannerVal].filter(Boolean);
        } else if (template === 'tpl2') {
            // Mẫu 2: 3 sản phẩm - lấy ảnh cover từ SP đã chọn
            const slots = [
                { banner: norm($productBanner1.val()), id: Number($productId1.val() || 0) || 0 },
                { banner: norm($productBanner2.val()), id: Number($productId2.val() || 0) || 0 },
                { banner: norm($productBanner3.val()), id: Number($productId3.val() || 0) || 0 }
            ];
            const banners = [];
            const productIds = [];
            slots.forEach((s) => {
                if (s.banner || s.id > 0) {
                    banners.push(s.banner || '');
                    productIds.push(s.id > 0 ? s.id : null);
                }
            });
            payload.banners = banners;
            if (productIds.some((id) => id)) {
                payload.product_ids = productIds;
            }
        } else if (template === 'tpl3') {
            // Mẫu 3: 3 ảnh upload tùy chỉnh
            const imgs = [
                norm($('#notifySlotImage1').val()),
                norm($('#notifySlotImage2').val()),
                norm($('#notifySlotImage3').val())
            ].filter(Boolean);
            payload.banners = imgs;
        }
        // tpl5: không có banners

        return payload;
    }

    function syncTemplateInputs(){
        const template = norm($templateCode.val()) || 'tpl1';
        $templateLabel.text(templateNameMap[template] || 'Mẫu 1');
        // Thumb image: ẩn khi tpl4, tpl5 (dùng icon)
        $thumbImageWrap.toggleClass('notice-hide', isIconTemplate(template));
        // Banner chính: hiện khi tpl1, tpl4, tpl2, tpl3
        $mainBannerWrap.toggleClass('notice-hide', !(template === 'tpl1' || template === 'tpl2' || template === 'tpl4' || template === 'tpl3'));
        // Product picker: chỉ hiện khi tpl2
        $productPickerWrap.toggleClass('notice-hide', template !== 'tpl2');
        // Image slots: chỉ hiện khi tpl3
        $imageSlotsWrap.toggleClass('notice-hide', template !== 'tpl3');
    }

    function syncThumbPreview(payload){
        const $mainThumb = $preview.find('[data-preview="thumb"]');
        if (payload.thumb_type === 'icon') {
            $mainThumb.html('<i class="bi bi-megaphone-fill"></i>');
        } else if (payload.thumb_image) {
            $mainThumb.html('<img src="' + toAbs(payload.thumb_image) + '" alt="thumb">');
        } else {
            $mainThumb.html('<i class="bi bi-image"></i>');
        }
    }

    function formatPreviewTime(sendAt){
        const raw = String(sendAt || '').trim();
        if (!raw) return '00:00 01-01-2026';
        const txt = raw.replace('T', ' ');
        const m = txt.match(/^(\d{4})-(\d{2})-(\d{2})\s(\d{2}):(\d{2})/);
        if (!m) return txt;
        return `${m[4]}:${m[5]} ${m[3]}-${m[2]}-${m[1]}`;
    }

    function syncPreview(){
        const payload = buildPayload();
        const sendAt = String($form.find('[name=send_at]').val() || '').trim();
        const template = String(payload.template || 'tpl1').trim();

        $body.val(JSON.stringify(payload));
        syncThumbPreview(payload);

        const $hero = $preview.find('[data-preview="hero"]');
        const heroBanner = norm(payload.main_banner) || ((template === 'tpl1' || template === 'tpl4') ? norm(payload.banners?.[0]) : '');
        if (heroBanner) {
            $hero.html('<img src="' + toAbs(heroBanner) + '" alt="hero">').removeClass('d-none');
        } else {
            $hero.addClass('d-none').empty();
        }

        $preview.find('[data-preview="title"]').text(payload.title || 'Tiêu đề thông báo');
        $preview.find('[data-preview="subtitle"]').text(payload.subtitle || 'Tiêu đề con');
        $preview.find('[data-preview="content"]').html(payload.content || 'Nội dung sẽ hiển thị ở đây.');

        const $badge = $preview.find('[data-preview="type"]');
        $badge.text(typeLabel());
        $badge.removeClass('badge-info badge-success badge-warn').addClass(typeBadgeClass());
        $preview.find('[data-preview="time"]').text(sendAt ? sendAt.replace('T', ' ') : 'Gửi ngay');
        $preview.find('[data-preview="footerTime"]').text(formatPreviewTime(sendAt));

        const $link = $preview.find('[data-preview="link"]');
        $link.attr('href', '#');

        const $footer = $preview.find('.notice-preview-footer');
        const $banners = $preview.find('[data-preview="banners"]');

        if (template === 'tpl1' || template === 'tpl4') {
            // Mẫu 1 & 4: Banner đã hiện ở Hero, footer có thể ẩn banners nếu chỉ có 1
            $footer.addClass('is-cover-layout');
            $banners.addClass('is-cover').addClass('d-none'); 
            $banners.empty();
        } else if (template === 'tpl2') {
            // Mẫu 2: 3 ảnh sản phẩm nhỏ
            $footer.removeClass('is-cover-layout');
            $banners.removeClass('is-cover').removeClass('d-none');
            const productBanners = [
                norm($productBanner1.val()),
                norm($productBanner2.val()),
                norm($productBanner3.val())
            ].filter(Boolean);
            if (!productBanners.length) {
                $banners.html('<span class="text-muted small">Chưa chọn sản phẩm</span>');
            } else {
                $banners.html(productBanners.map(url => '<img src="' + toAbs(url) + '" alt="sp">').join(''));
            }
        } else if (template === 'tpl3') {
            // Mẫu 3: 3 ảnh upload
            $footer.removeClass('is-cover-layout');
            $banners.removeClass('is-cover').removeClass('d-none');
            const slotImgs = [
                norm($('#notifySlotImage1').val()),
                norm($('#notifySlotImage2').val()),
                norm($('#notifySlotImage3').val())
            ].filter(Boolean);
            if (!slotImgs.length) {
                $banners.html('<span class="text-muted small">Chưa upload ảnh</span>');
            } else {
                $banners.html(slotImgs.map(url => '<img src="' + toAbs(url) + '" alt="img">').join(''));
            }
        } else {
            // Mẫu 5: Không có banner
            $footer.removeClass('is-cover-layout');
            $banners.removeClass('is-cover').addClass('d-none');
            $banners.empty();
        }

        setSlotPreview(1);
        setSlotPreview(2);
        setSlotPreview(3);
    }

    function clearImageSlots(){
        for (let i = 1; i <= 3; i++) {
            $('#notifySlotImage' + i).val('');
            $('#notifySlotMeta' + i).text('Ảnh ' + i + ' - chưa upload');
            $('#notifySlotPreview' + i).html('<i class="bi bi-image"></i>');
        }
    }

    function applyTemplatePreset(code){
        // Reset chung
        setMainBanner('');
        setSelectedNotifyCategoryIds([]);
        setSelectedNotifyProductIds([]);
        clearImageSlots();

        if (code === 'tpl1') {
            $templateCode.val('tpl1');
            $titleInput.val('SIÊU SALE CUỐI TUẦN');
            $subtitle.val('Giảm sâu cho đơn hàng hôm nay');
            setEditorHtml('<p>Săn deal giới hạn thời gian, áp dụng trên toàn hệ thống.</p>');
            setMainBanner('https://dummyimage.com/640x180/fee2e2/b91c1c&text=Main+Banner');
        }
        if (code === 'tpl2') {
            $templateCode.val('tpl2');
            $titleInput.val('BỘ SƯU TẬP DEAL HOT');
            $subtitle.val('3 sản phẩm nổi bật hôm nay');
            setEditorHtml('<p>Khuyến mãi theo danh mục sản phẩm, số lượng có hạn.</p>');
        }
        if (code === 'tpl3') {
            $templateCode.val('tpl3');
            $titleInput.val('KHÁM PHÁ NHIỀU HƠN');
            $subtitle.val('3 hình ảnh ấn tượng cho bạn');
            setEditorHtml('<p>Xem ngay bộ sưu tập ảnh mới nhất từ chúng tôi.</p>');
        }
        if (code === 'tpl4') {
            $templateCode.val('tpl4');
            $titleInput.val('THÔNG BÁO QUAN TRỌNG');
            $subtitle.val('Cập nhật từ hệ thống');
            setEditorHtml('<p>Nội dung thông báo quan trọng từ hệ thống.</p>');
            setMainBanner('https://dummyimage.com/640x180/fef3c7/92400e&text=Banner');
            setThumbImage('');
        }
        if (code === 'tpl5') {
            $templateCode.val('tpl5');
            $titleInput.val('CẢNH BÁO BẢO MẬT');
            $subtitle.val('Phát hiện đăng nhập thiết bị lạ');
            setEditorHtml('<p>Nếu không phải bạn, vui lòng đổi mật khẩu ngay.</p>');
            setThumbImage('');
        }
        syncTemplateInputs();
        syncPreview();
    }

    function resetForm(){
        $form[0].reset();
        $form.find('[name=id]').val('0');
        $submit.text('Gửi thông báo');
        $templateCode.val('tpl1');
        setEditorHtml('');
        pendingEditorHtml = '';
        setThumbImage('');
        setMainBanner('');
        setSelectedNotifyCategoryIds([]);
        setSelectedNotifyProductIds([]);
        $userIdInput.val('');
        $userSelectedLabel.text('Chưa chọn khách hàng');
        $('#notifyTitleCounter').text('0/120');
        $('#notifySubtitleCounter').text('0/255');
        clearImageSlots();
        syncTarget();
        syncTemplateInputs();
        syncPreview();
    }

    $target.on('change', syncTarget);
    $subtitle.on('input', function(){
        $('#notifySubtitleCounter').text(this.value.length + '/255');
        syncPreview();
    });
    $('#notifyTitleInput').on('input', function(){
        $('#notifyTitleCounter').text(this.value.length + '/120');
        syncPreview();
    });
    $contentEditor.on('input keyup blur', syncPreview);

    // Chọn danh mục / sản phẩm theo style voucher
    $('#notifyCategoryFilterInput').on('input', function(){
        renderNotifyCategoryPicker();
    });

    $('#notifyProductFilterInput').on('input', function(){
        renderNotifyProductPicker();
    });

    $productSort.on('change', function(){
        renderNotifyProductPicker();
    });

    $('#notifyCategoryPickerList').on('change', '.jsNotifyCategoryPick', function(){
        const current = new Set(getSelectedNotifyCategoryIds());
        const id = parseInt($(this).val(), 10);
        if (!Number.isFinite(id) || id <= 0) return;
        if ($(this).is(':checked')) current.add(id);
        else current.delete(id);
        setSelectedNotifyCategoryIds([...current]);
        renderNotifyProductPicker();
        syncPreview();
    });

    $('#notifyBtnCategoryPickAll').on('click', function(){
        const current = new Set(getSelectedNotifyCategoryIds());
        $('#notifyCategoryPickerList .jsNotifyCategoryPick').each(function(){
            const id = parseInt($(this).val(), 10);
            if (Number.isFinite(id) && id > 0) {
                current.add(id);
                $(this).prop('checked', true);
            }
        });
        setSelectedNotifyCategoryIds([...current]);
        renderNotifyProductPicker();
        syncPreview();
    });

    $('#notifyBtnCategoryPickClear').on('click', function(){
        $('#notifyCategoryPickerList .jsNotifyCategoryPick').prop('checked', false);
        setSelectedNotifyCategoryIds([]);
        renderNotifyProductPicker();
        syncPreview();
    });

    $('#notifyProductPickerList').on('change', '.jsNotifyProductPick', function(){
        const current = new Set(getSelectedNotifyProductIds());
        const id = parseInt($(this).val(), 10);
        if (!Number.isFinite(id) || id <= 0) return;

        if ($(this).is(':checked')) {
            if (current.size >= MAX_NOTIFY_PRODUCTS) {
                $(this).prop('checked', false);
                notify('Chỉ được chọn tối đa ' + MAX_NOTIFY_PRODUCTS + ' sản phẩm.', 'warning');
                return;
            }
            current.add(id);
        } else {
            current.delete(id);
        }
        setSelectedNotifyProductIds([...current]);
        syncPreview();
    });

    $('#notifyBtnProductPickAll').on('click', function(){
        const current = new Set(getSelectedNotifyProductIds());
        $('#notifyProductPickerList .jsNotifyProductPick').each(function(){
            const id = parseInt($(this).val(), 10);
            if (Number.isFinite(id) && id > 0) {
                if (!current.has(id) && current.size >= MAX_NOTIFY_PRODUCTS) {
                    $(this).prop('checked', false);
                    return;
                }
                current.add(id);
                $(this).prop('checked', true);
            }
        });
        setSelectedNotifyProductIds([...current]);
        syncPreview();
    });

    $('#notifyBtnProductPickClear').on('click', function(){
        $('#notifyProductPickerList .jsNotifyProductPick').prop('checked', false);
        setSelectedNotifyProductIds([]);
        syncPreview();
    });

    $thumbUploadBtn.on('click', function(){
        $thumbUpload.trigger('click');
    });

    function convertImageFileToWebP(file, maxWidth, quality){
        return new Promise(function(resolve, reject){
            if (!file) {
                reject(new Error('No file'));
                return;
            }
            if (!/^image\//i.test(file.type || '')) {
                reject(new Error('Not an image'));
                return;
            }
            const reader = new FileReader();
            reader.onload = function(evt){
                const dataUrl = String(evt && evt.target && evt.target.result || '').trim();
                if (!dataUrl) {
                    reject(new Error('Empty data URL'));
                    return;
                }
                const img = new Image();
                img.onload = function(){
                    try {
                        const canvas = document.createElement('canvas');
                        let w = img.naturalWidth || img.width;
                        let h = img.naturalHeight || img.height;
                        if (maxWidth && w > maxWidth) {
                            const ratio = maxWidth / w;
                            w = maxWidth;
                            h = Math.round(h * ratio);
                        }
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            resolve(dataUrl);
                            return;
                        }
                        ctx.drawImage(img, 0, 0, w, h);
                        const q = (typeof quality === 'number' && quality > 0 && quality <= 1) ? quality : 0.82;
                        let webpDataUrl = '';
                        try {
                            webpDataUrl = canvas.toDataURL('image/webp', q);
                        } catch (e) {
                            webpDataUrl = '';
                        }
                        if (!webpDataUrl || !/^data:image\/webp/i.test(webpDataUrl)) {
                            resolve(dataUrl);
                        } else {
                            resolve(webpDataUrl);
                        }
                    } catch (e) {
                        resolve(dataUrl);
                    }
                };
                img.onerror = function(){
                    resolve(dataUrl);
                };
                img.src = dataUrl;
            };
            reader.onerror = function(){
                reject(new Error('read error'));
            };
            reader.readAsDataURL(file);
        });
    }

    $thumbUpload.on('change', function(){
        const input = this;
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) return;
        if (!/^image\//i.test(file.type || '')) {
            notify('Chỉ hỗ trợ file ảnh cho thumbnail', 'warning');
            input.value = '';
            return;
        }
        convertImageFileToWebP(file, 600, 0.82)
            .then(function(dataUrl){
                setThumbImage(String(dataUrl || '').trim());
                syncPreview();
            })
            .catch(function(){
                notify('Không thể xử lý ảnh thumbnail', 'error');
            });
        input.value = '';
    });

    $thumbClearBtn.on('click', function(){
        setThumbImage('');
        syncPreview();
    });

    $mainBannerUploadBtn.on('click', function(){
        $mainBannerUpload.trigger('click');
    });

    $mainBannerUpload.on('change', function(){
        const input = this;
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) return;
        if (!/^image\//i.test(file.type || '')) {
            notify('Chỉ hỗ trợ file ảnh cho banner chính', 'warning');
            input.value = '';
            return;
        }
        convertImageFileToWebP(file, 1200, 0.82)
            .then(function(dataUrl){
                setMainBanner(String(dataUrl || '').trim());
                syncPreview();
            })
            .catch(function(){
                notify('Không thể xử lý ảnh banner chính', 'error');
            });
        input.value = '';
    });

    $mainBannerClearBtn.on('click', function(){
        setMainBanner('');
        syncPreview();
    });

    // Upload ảnh cho từng slot (dành cho Mẫu 3 - 3 ảnh upload)
    $(document).on('click', '.jsSlotUploadBtn', function(){
        const slot = $(this).data('slot');
        $('.jsSlotFileInput[data-slot="' + slot + '"]').trigger('click');
    });

    $userFilterInput.on('input', renderNotifyUserPicker);
    $userSort.on('change', renderNotifyUserPicker);

    $(document).on('click', '.jsNotifyUserPick', function(){
        const id = $(this).data('id');
        const user = (USER_CATALOG || []).find(u => Number(u.id) === id);
        if (!user) return;
        
        $userIdInput.val(id);
        $userSelectedLabel.html('<i class="bi bi-check-circle-fill me-1"></i>Đã chọn: ' + escapeHtml(user.full_name || user.username) + ' (#' + id + ')');
        renderNotifyUserPicker();
        syncPreview();
    });

    $(document).on('change', '.jsSlotFileInput', function(){
        const input = this;
        const slot = $(this).data('slot');
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) return;
        convertImageFileToWebP(file, 800, 0.82)
            .then(function(dataUrl){
                $('#notifySlotImage' + slot).val(dataUrl);
                $('#notifySlotMeta' + slot).text('Đã upload: ' + file.name);
                $('#notifySlotPreview' + slot).html('<img src="' + dataUrl + '" alt="slot-' + slot + '" style="width:100%;height:100%;object-fit:cover;">');
                syncPreview();
            })
            .catch(function(){
                notify('Không thể xử lý ảnh slot', 'error');
            });
        input.value = '';
    });

    $(document).on('click', '.jsSlotClearBtn', function(){
        const $btn = $(this);
        const slot = $btn.data('slot');
        const type = $btn.data('type'); // image or product

        if (type === 'product') {
            let ids = getSelectedNotifyProductIds();
            // Remove the product at this index (slot - 1)
            if (ids.length >= slot) {
                ids.splice(slot - 1, 1);
                setSelectedNotifyProductIds(ids);
                renderNotifyProductPicker();
            }
        } else {
            $('#notifySlotImage' + slot).val('');
            $('#notifySlotMeta' + slot).text('Ảnh ' + slot);
            $('#notifySlotPreview' + slot).html('<i class="bi bi-image"></i>');
        }
        syncPreview();
    });

    $form.on('input change', 'input, select, textarea', syncPreview);

    function goToStep(step){
        $('.step-container').removeClass('step-active');
        $('.step-item').removeClass('active');
        $('#stepIndicator' + step).addClass('active');
        $('#step' + step + 'Container').addClass('active step-active');
        const $header = $('.step-header');
        if (step === 2) {
            $header.addClass('d-none');
            $('#notifyModalTitle').html('<i class="bi bi-pencil-square me-2"></i>Soạn nội dung');
            initNotifyEditor();
        } else {
            $header.removeClass('d-none');
            $('#notifyModalTitle').html('<i class="bi bi-grid me-2"></i>Chọn template');
        }
    }

    $composeOpen.on('click', function(){
        resetForm();
        goToStep(1);
        if (composeModal) {
            composeModal.show();
        }
    });

    $(document).on('click', '.js-template-pick', function(){
        applyTemplatePreset(String($(this).data('template-preset') || 'tpl1'));
        goToStep(2);
    });

    $(document).on('click', '.jsToStep1', function(){
        goToStep(1);
    });

    $form.on('submit', function(e){
        e.preventDefault();
        const payload = buildPayload();
        if (!payload.title) {
            notify('Vui lòng nhập tiêu đề', 'warning');
            return;
        }
        if (!getEditorText()) {
            notify('Vui lòng nhập nội dung', 'warning');
            return;
        }
        $body.val(JSON.stringify(payload));
        const data = $form.serialize();
        postApi(data, (res) => {
            if (!res || !res.ok) {
                notify(res?.msg || 'Gửi thất bại', 'warning');
                return;
            }
            notify(res.msg || 'Đã lưu thông báo', 'success');
            window.location.reload();
        });
    });

    $(document).on('click', '#notifyTable tbody .jsEditNotice', function(){
        const tr = $(this).closest('tr');
        const row = tr.data('row');
        if (!row) return;
        $form.find('[name=id]').val(row.id || 0);
        $form.find('[name=title]').val(row.title || '');
        if (row.send_at) {
            const sendVal = String(row.send_at).replace(' ', 'T').slice(0, 16);
            $form.find('[name=send_at]').val(sendVal);
        } else {
            $form.find('[name=send_at]').val('');
        }

        const payload = parseBodyPayload(row.body || '');
        if (payload) {
            const tpl = payload.template || 'tpl1';
            $templateCode.val(tpl);
            setThumbImage(payload.thumb_image || '');
            $subtitle.val(payload.subtitle || '');
            setEditorHtml(payload.content || '');
            const banners = Array.isArray(payload.banners) ? payload.banners : [];
            const productIds = Array.isArray(payload.product_ids) ? payload.product_ids : [];
            const mainBanner = norm(payload.main_banner || '');
            clearImageSlots();

            if (tpl === 'tpl1' || tpl === 'tpl4') {
                // Mẫu 1 & 4: banner chính
                setMainBanner(mainBanner || (banners[0] || ''));
                setSelectedNotifyCategoryIds([]);
                setSelectedNotifyProductIds([]);
            } else if (tpl === 'tpl2') {
                // Mẫu 2: 3 sản phẩm + banner
                setMainBanner(mainBanner);
                if (productIds.some((pid) => pid)) {
                    const selectedIds = productIds.filter((pid) => pid).map((pid) => Number(pid));
                    setSelectedNotifyProductIds(selectedIds);
                    const catIds = [];
                    selectedIds.forEach((pid) => {
                        const p = getProductById(pid);
                        if (p && p.category_id) catIds.push(Number(p.category_id));
                    });
                    setSelectedNotifyCategoryIds(catIds);
                    renderNotifyCategoryPicker();
                    renderNotifyProductPicker();
                } else {
                    setSelectedNotifyCategoryIds([]);
                    setSelectedNotifyProductIds([]);
                    renderNotifyCategoryPicker();
                    renderNotifyProductPicker();
                }
            } else if (tpl === 'tpl3') {
                // Mẫu 3: 3 ảnh upload + banner
                setMainBanner(mainBanner);
                for (let i = 0; i < 3; i++) {
                    const imgVal = banners[i] || '';
                    $('#notifySlotImage' + (i + 1)).val(imgVal);
                    if (imgVal) {
                        $('#notifySlotMeta' + (i + 1)).text('Ảnh đã lưu');
                        $('#notifySlotPreview' + (i + 1)).html('<img src="' + toAbs(imgVal) + '" alt="slot" style="width:100%;height:100%;object-fit:cover;">');
                    }
                }
                setSelectedNotifyCategoryIds([]);
                setSelectedNotifyProductIds([]);
            } else {
                // Mẫu 5
                setMainBanner('');
                setSelectedNotifyCategoryIds([]);
                setSelectedNotifyProductIds([]);
            }
        } else {
            $templateCode.val('tpl5');
            setThumbImage('');
            $subtitle.val('');
            setEditorHtml('<p>' + escapeHtml(stripHtml(row.body || '')) + '</p>');
            setMainBanner('');
            setSelectedNotifyCategoryIds([]);
            setSelectedNotifyProductIds([]);
            clearImageSlots();
        }

        if (Number(row.user_id || 0) === 0) {
            $target.val('all');
            $userIdInput.val('');
            $userSelectedLabel.text('Chưa chọn khách hàng');
        } else {
            $target.val('user');
            const uid = Number(row.user_id);
            $userIdInput.val(uid);
            const user = (USER_CATALOG || []).find(u => Number(u.id) === uid);
            if (user) {
                $userSelectedLabel.html('<i class="bi bi-check-circle-fill me-1"></i>Đã chọn: ' + escapeHtml(user.full_name || user.username) + ' (#' + uid + ')');
            } else {
                $userSelectedLabel.text('User ID: #' + uid);
            }
        }
        $('#notifyTitleCounter').text(($titleInput.val() || '').length + '/120');
        $('#notifySubtitleCounter').text(($subtitle.val() || '').length + '/255');
        syncTarget();
        syncTemplateInputs();
        syncPreview();
        $submit.text('Cập nhật');
        goToStep(2);
        if (composeModal) {
            composeModal.show();
        }
    });

    $(document).on('click', '#notifyTable tbody .jsDelNotice', function(){
        const tr = $(this).closest('tr');
        const row = tr.data('row');
        if (!row || !confirm('Xoá thông báo này?')) return;
        postApi({ action: 'delete', id: row.id }, (res) => {
            if (!res || !res.ok) return notify(res?.msg || 'Xoá thất bại', 'warning');
            notify(res.msg || 'Đã xoá', 'success');
            window.location.reload();
        });
    });

    $(document).on('change', '#notifyTable tbody .jsToggleNotice', function(){
        const tr = $(this).closest('tr');
        const row = tr.data('row');
        if (!row) return;
        const next = this.checked ? 1 : 0;
        postApi({ action: 'toggle', id: row.id, is_active: next }, (res) => {
            if (!res || !res.ok) {
                notify(res?.msg || 'Cập nhật thất bại', 'warning');
                this.checked = !this.checked;
                return;
            }
            notify(res.msg || 'Đã cập nhật', 'success');
        });
    });

    // KPI tab + filter state (luôn bind, độc lập với DataTable init)
    window.__nfState = window.__nfState || { tab: 'all' };
    $(document).off('click.nfTab').on('click.nfTab', '#summaryGrid .summary-card', function(e){
        e.preventDefault();
        $('#summaryGrid .summary-card').removeClass('active');
        $(this).addClass('active');
        window.__nfState.tab = String($(this).attr('data-nf-tab') || 'all');
        // Nếu DataTable đã init → redraw để áp filter
        if (window.__nfDataTable) {
            try { window.__nfDataTable.draw(); } catch (e) {}
        } else {
            // Fallback: ẩn/hiện trực tiếp <tr>
            const t = window.__nfState.tab;
            $('#notifyTable tbody tr[data-tpl]').each(function(){
                const $r = $(this);
                const act   = Number($r.attr('data-active') || 0) === 1;
                const sched = String($r.attr('data-scheduled') || '0') === '1';
                const tgt   = String($r.attr('data-target') || '');
                let show = true;
                if (t === 'active' && !act) show = false;
                if (t === 'scheduled' && !sched) show = false;
                if (t === 'targeted' && tgt !== 'targeted') show = false;
                $r.toggle(show);
            });
        }
    });

    // Khởi tạo DataTables cho danh sách thông báo
    $(function(){
        if ($.fn.DataTable && $notifyTable.length) {
            const notifyTable = $notifyTable.DataTable({
                pageLength: 10,
                order: [], // Giữ nguyên thứ tự từ server (sort_order)
                dom: 't<"d-flex justify-content-between align-items-center p-2 flex-column flex-md-row"ip>',
                language: {
                    info: "Hiển thị _START_ - _END_ / _TOTAL_",
                    infoEmpty: "Không có dữ liệu",
                    infoFiltered: "(lọc từ _MAX_ mục)",
                    lengthMenu: "Hiển thị _MENU_ mục",
                    zeroRecords: "Không tìm thấy dữ liệu",
                    search: "Tìm kiếm:",
                    paginate: {
                        first: "Đầu",
                        last: "Cuối",
                        next: ">",
                        previous: "<"
                    }
                }
            });
            // Expose để KPI handler bên ngoài có thể trigger redraw
            window.__nfDataTable = notifyTable;

            // Kéo thả sắp xếp
            const tbody = $notifyTable.find('tbody')[0];
            if (tbody) {
                new Sortable(tbody, {
                    animation: 150,
                    handle: '.js-drag-handle',
                    ghostClass: 'sortable-ghost',
                    onEnd: function() {
                        const ids = [];
                        // Lấy danh sách ID từ các hàng sau khi kéo thả
                        $notifyTable.find('tbody tr').each(function() {
                            const r = $(this).data('row');
                            if (r && r.id) ids.push(r.id);
                        });
                        if (ids.length > 0) {
                            postApi({ action: 'sort', ids: ids }, (res) => {
                                if (res && res.ok) notify(res.msg || 'Đã cập nhật thứ tự', 'success');
                                else notify(res?.msg || 'Lỗi cập nhật thứ tự', 'error');
                            });
                        }
                    }
                });
            }

            const $search = $notifySearch;
            if ($search && $search.length) {
                $search.on('keyup', function(){
                    notifyTable.search(this.value).draw();
                });
            }

            // ==== Custom filters (tpl, target, KPI tab) + sort ====
            const $tplSel    = $('#notifyFilterTpl');
            const $targetSel = $('#notifyFilterTarget');
            const $sortSel   = $('#notifySortOrder');

            $.fn.dataTable.ext.search.push(function(settings, _searchData, dataIndex){
                if (settings.nTable !== $notifyTable[0]) return true;
                const row = settings.aoData[dataIndex];
                if (!row || !row.nTr) return true;
                const $row = $(row.nTr);
                const tpl   = String($row.attr('data-tpl') || '').toUpperCase();
                const tgt   = String($row.attr('data-target') || '');
                const sched = String($row.attr('data-scheduled') || '0') === '1';
                const act   = Number($row.attr('data-active') || 0) === 1;
                const filTpl = String($tplSel.val() || 'all');
                const filTgt = String($targetSel.val() || 'all');
                const tab    = String((window.__nfState && window.__nfState.tab) || 'all');
                if (filTpl !== 'all' && tpl !== filTpl) return false;
                if (filTgt !== 'all' && tgt !== filTgt) return false;
                if (tab === 'active' && !act) return false;
                if (tab === 'scheduled' && !sched) return false;
                if (tab === 'targeted' && tgt !== 'targeted') return false;
                return true;
            });

            $tplSel.add($targetSel).on('change', function(){ notifyTable.draw(); });
            $sortSel.on('change', function(){
                const v = String(this.value || 'id_desc');
                switch (v){
                    case 'id_asc':     notifyTable.order([0,'asc']).draw(); break;
                    case 'title_asc':  notifyTable.order([1,'asc']).draw(); break;
                    case 'title_desc': notifyTable.order([1,'desc']).draw(); break;
                    case 'id_desc':
                    default:           notifyTable.order([0,'desc']).draw(); break;
                }
            });

            // Compute KPIs from rows
            function updateKpis(){
                const $rows = $notifyTable.find('tbody tr[data-tpl]');
                let total = $rows.length, active = 0, scheduled = 0, targeted = 0;
                $rows.each(function(){
                    const $r = $(this);
                    if (Number($r.data('active') || 0) === 1) active++;
                    if (String($r.data('scheduled') || '0') === '1') scheduled++;
                    if (String($r.data('target') || '') === 'targeted') targeted++;
                });
                $('#nfKpiAll').text(total);
                $('#nfKpiActive').text(active);
                $('#nfKpiScheduled').text(scheduled);
                $('#nfKpiTargeted').text(targeted);
                $('#notifyMeta').text('Tổng: ' + total + ' thông báo');
            }
            updateKpis();
            // Re-compute sau khi toggle is_active hoặc thêm/xoá
            $(document).on('change', '#notifyTable tbody .jsToggleNotice', function(){
                const $row = $(this).closest('tr');
                $row.attr('data-active', $(this).is(':checked') ? '1' : '0');
                setTimeout(updateKpis, 50);
            });
        }
    });

    renderNotifyCategoryPicker();
    renderNotifyProductPicker();
    syncTarget();
    syncTemplateInputs();
    syncPreview();
})();
</script>
