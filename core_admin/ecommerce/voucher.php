<?php require_once __DIR__ . '/../_admin_guard.php'; ?>
<?php require_once __DIR__ . '/ajax/voucher.php'; ?>

<div class="container-fluid py-4">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-ticket-perforated fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Quản lý voucher</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="voucherMeta" style="font-size: 0.72rem;">Tổng: <?= is_array($Vouchers) ? count($Vouchers) : 0 ?> voucher</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Trang quản trị mã ưu đãi giảm giá, vận chuyển, ngành hàng và thanh toán hiện có trên hệ thống Paint&More.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Cấu hình mã ưu đãi giảm giá, vận chuyển, ngành hàng và thanh toán.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm text-white" id="btnNew" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-plus-lg fs-5 text-white"></i>
                <span class="d-none d-sm-inline">Tạo voucher</span>
                <span class="d-inline d-sm-none">Tạo mới</span>
            </button>
        </div>
    </div>


    <!-- SUMMARY KPI CARDS GRID -->
    <div class="mb-4" id="summaryGrid">
        <div class="summary-card active" data-voucher-tab="all">
            <div class="d-flex flex-column">
                <span>Tất cả</span>
                <strong class="mt-1"><?= (int)$templateCounts['all'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-collection-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-voucher-tab="order_discount">
            <div class="d-flex flex-column">
                <span>Giảm đơn</span>
                <strong class="mt-1"><?= (int)$templateCounts['order_discount'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-bag-check-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-voucher-tab="shipping_discount">
            <div class="d-flex flex-column">
                <span>Vận chuyển</span>
                <strong class="mt-1"><?= (int)$templateCounts['shipping_discount'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-truck fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-voucher-tab="only_category_discount">
            <div class="d-flex flex-column">
                <span>Ngành hàng</span>
                <strong class="mt-1"><?= (int)$templateCounts['only_category_discount'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-voucher-tab="category_discount">
            <div class="d-flex flex-column">
                <span>Toàn ngành</span>
                <strong class="mt-1"><?= (int)$templateCounts['category_discount'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-collection-play-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-voucher-tab="payment_discount">
            <div class="d-flex flex-column">
                <span>Thanh toán</span>
                <strong class="mt-1"><?= (int)$templateCounts['payment_discount'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-credit-card-2-front-fill fs-5"></i>
            </div>
        </div>
    </div>

    <!-- Filters & List Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="mb-2">
            <div class="row g-3 align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-0" id="searchVoucher" placeholder="Tìm mã voucher, mô tả...">
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <select id="filterStatus" class="form-select form-select-sm border shadow-sm rounded-3">
                        <option value="all">Tất cả</option>
                        <option value="1">Đang hoạt động</option>
                        <option value="0">Tạm dừng</option>
                    </select>
                </div>
                <div class="col-md-4 col-6 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold" id="refreshVouchers">
                        <i class="bi bi-arrow-clockwise me-1"></i> Làm mới
                    </button>
                </div>
            </div>
        </div>

        <div class="fb-table-responsive border-top">
            <table id="voucherTable" class="table fb-table table-hover align-middle mb-0 text-nowrap">
                <thead>
                    <tr>
                        <th class="ps-4" style="min-width:280px">Voucher</th>
                        <th style="min-width:140px">Danh mục</th>
                        <th style="min-width:100px" class="text-end">Tối thiểu</th>
                        <th style="min-width:150px">Thời gian</th>
                        <th style="min-width:180px" class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($Vouchers as $c): ?>
                    <?php $tplKey = determineVoucherTemplateKey($c); ?>
                    <tr data-row='<?= h(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>' data-voucher-template="<?= h($tplKey) ?>">
                        <td class="ps-4">
                            <?php
                            $isPercent = (($c['value_unit'] ?? 'fixed') === 'percent');
                            $val = (float)($c['value'] ?? 0);
                            $mainLabel = $isPercent ? ($val . '(%)') : (($val) . '(đ)');
                            
                            if ($tplKey === 'shipping_discount') {
                                $iconClass = 'bi-truck';
                                $tplLabel = 'Ưu đãi vận chuyển';
                                $iconWrapClass = 'tpl-ship';
                            } elseif ($tplKey === 'only_category_discount') {
                                $iconClass = 'bi-grid-3x3-gap';
                                $tplLabel = 'Giảm theo danh mục';
                                $iconWrapClass = 'tpl-category';
                            } elseif ($tplKey === 'category_discount') {
                                $iconClass = 'bi-collection';
                                $tplLabel = 'Giảm toàn ngành';
                                $iconWrapClass = 'tpl-all';
                            } elseif ($tplKey === 'payment_discount') {
                                $iconClass = 'bi-credit-card-2-front';
                                $tplLabel = 'Ưu đãi thanh toán';
                                $iconWrapClass = 'tpl-payment';
                            } elseif ($tplKey === 'order_discount') {
                                $iconClass = 'bi-bag-check';
                                $tplLabel = 'Ưu đãi Đơn hàng';
                                $iconWrapClass = 'tpl-order';
                            } else {
                                $iconClass = 'bi-ticket-perforated';
                                $tplLabel = 'Mã chung';
                                $iconWrapClass = 'tpl-default';
                            }
                            ?>
                            <div class="d-flex align-items-start gap-3">
                                <div class="voucher-type-icon <?= $iconWrapClass ?> flex-shrink-0 mt-1" style="width:36px; height:36px; font-size:1.1rem;">
                                    <i class="bi <?= $iconClass ?>"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong class="text-dark" style="font-size: 0.95rem;"><?= h($c['code'] ?? '') ?></strong>
                                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25" style="font-size: 0.72rem; padding: 0.25rem 0.4rem;">-<?= h($mainLabel) ?></span>
                                    </div>
                                    <div class="text-muted mb-1" style="font-size: 0.8rem;"><?= h($tplLabel) ?></div>
                                    <div class="font-semibold text-secondary" style="font-size: 0.75rem;">
                                        Đã dùng: <span class="text-dark"><?= (int)($c['used_count'] ?? 0) ?></span><?= ($c['max_uses']!==null && $c['max_uses']!=='') ? (' / '.(int)$c['max_uses']) : '' ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted">
                            <?= h(VoucherCategorySummary($c)) ?>
                        </td>
                        <td class="text-end fw-semibold"><?= fmtMoney($c['min_subtotal'] ?? 0, false) ?></td>
                        <td class="small text-muted">
                            <?= h(VoucherTimeSummary($c)) ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="voucher-actions d-flex justify-content-end align-items-center gap-2">
                                <div class="form-check form-switch d-inline-block m-0 me-1">
                                   <input class="form-check-input jsToggle" type="checkbox" <?= ((int)($c['is_active'] ?? 0)===1)?'checked':'' ?> style="cursor: pointer;">
                                </div>
                                <button type="button" class="btn btn-outline-secondary jsViewDetail" title="Xem chi tiết"><i class="bi bi-eye"></i></button>
                                <button type="button" class="btn btn-outline-primary jsEdit" title="Sửa"><i class="bi bi-pencil-square"></i></button>
                                <button type="button" class="btn btn-outline-danger jsDel" title="Xóa"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="vouchersEmpty" class="text-center py-5 text-muted" style="display:none;">
            <div class="py-4">
                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                <p class="mb-0">Không có voucher phù hợp bộ lọc.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal chi tiết nhanh -->
<div class="modal fade" id="voucherDetailModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header py-3 bg-light border-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-info-circle-fill me-1 text-primary"></i> Chi tiết voucher</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="voucherDetailBody"></div>
        </div>
    </div>
</div>

<!-- Modal tạo/sửa -->
<div class="modal fade" id="voucherModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header py-3 bg-light border-0">
                <h5 class="modal-title fw-bold text-dark" id="mTitle">Tạo mã</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-sm-4">
                <form id="voucherForm">
                    <input type="hidden" name="id" value="0">
                    <input type="hidden" name="discount_targets[]" id="discountTargetInput" value="">
                    <input type="hidden" name="voucher_template" id="voucherTemplateKey" value="">
                    
                    <!-- Wizard/Stepper progress bar matching promotion.php's structure -->
                    <div class="wizard-steps flex-column flex-sm-row">
                        <div class="wizard-step-node step-item active" data-step="1">
                            <div class="wizard-step-circle">1</div>
                            <div class="wizard-step-info">
                                <div class="wizard-step-title">Chọn template</div>
                                <div class="wizard-step-subtitle">Mẫu riêng cho từng loại</div>
                            </div>
                        </div>
                        <div class="wizard-step-node step-item" data-step="2">
                            <div class="wizard-step-circle">2</div>
                            <div class="wizard-step-info">
                                <div class="wizard-step-title">Thiết lập chi tiết</div>
                                <div class="wizard-step-subtitle">Cấu hình toàn bộ</div>
                            </div>
                        </div>
                    </div>

                    <div class="voucher-step-content mt-3" data-step="1">
                        <div class="tpl-grid row g-2 g-sm-3">
                            <div class="col-12 col-md-6">
                                <button type="button" class="tpl-card tpl-default w-100 p-2 p-sm-3" data-template="order_discount">
                                <div class="tpl-title">Ưu đãi giảm giá</div>
                                <div class="tpl-desc">Áp dụng chuẩn cho đơn hàng thông thường.</div>
                                <article class="tpl-voux-card tpl-voux-order">
                                    <span class="tpl-voux-qty">x2</span>
                                    <div class="tpl-voux-accent"></div>
                                    <div class="tpl-voux-brand">
                                        <span class="tpl-voux-logo-icon"><i class="bi bi-percent"></i></span>
                                        <div class="tpl-voux-brand-name">Giảm giá</div>
                                    </div>
                                    <div class="tpl-voux-main">
                                        <div class="tpl-voux-main-title">Giảm 15% - Giảm tối đa 200k₫</div>
                                        <div class="tpl-voux-sub">Đơn tối thiểu 199k</div>
                                        <span class="tpl-voux-badge">Dành riêng cho bạn</span>
                                        <div class="tpl-voux-foot">
                                            <span class="tpl-voux-time">Sắp hết hạn: còn 14 giờ</span>
                                        </div>
                                    </div>
                                    <div class="tpl-voux-side">
                                        <span class="tpl-voux-tag">Đơn hàng</span>
                                    </div>
                                </article>
                                </button>
                            </div>
                            <div class="col-12 col-md-6">
                                <button type="button" class="tpl-card tpl-ship w-100 p-2 p-sm-3" data-template="shipping_discount">
                                <div class="tpl-title">Ưu đãi vận chuyển</div>
                                <div class="tpl-desc">Giảm phí ship đơn hàng GHN.</div>
                                <article class="tpl-voux-card tpl-voux-ship">
                                    <span class="tpl-voux-qty">x2</span>
                                    <div class="tpl-voux-accent"></div>
                                    <div class="tpl-voux-brand">
                                        <span class="tpl-voux-logo-icon"><i class="bi bi-truck"></i></span>
                                        <div class="tpl-voux-brand-name">Vận chuyển</div>
                                    </div>
                                    <div class="tpl-voux-main">
                                        <div class="tpl-voux-main-title">Giảm 20.000đ - Giảm tối đa 20.000đ</div>
                                        <div class="tpl-voux-badge">Đơn tối thiểu 0đ</div>
                                        <div class="tpl-voux-sub">Áp dụng mọi đơn · <a href="#" class="voux-tnc" data-voucher="HOT">Điều kiện</a></div>
                                        <div class="tpl-voux-foot">
                                            <span class="tpl-voux-time">Sắp hết hạn: còn 14 giờ</span>
                                        </div>
                                    </div>
                                    <div class="tpl-voux-side">
                                        <span class="tpl-voux-tag">Vận chuyển</span>
                                    </div>
                                </article>
                                </button>
                            </div>
                            <div class="col-12 col-md-6">
                                <button type="button" class="tpl-card tpl-category w-100 p-2 p-sm-3" data-template="only_category_discount">
                                <div class="tpl-title">Giảm theo ngành hàng</div>
                                <div class="tpl-desc">Chọn sản phẩm theo từng danh mục.</div>
                                <article class="tpl-voux-card tpl-voux-category">
                                    <span class="tpl-voux-qty">x2</span>
                                    <div class="tpl-voux-accent"></div>
                                    <div class="tpl-voux-brand">
                                        <span class="tpl-voux-logo-icon"><i class="bi bi-grid-3x3-gap"></i></span>
                                        <div class="tpl-voux-brand-name">Ngành hàng</div>
                                    </div>
                                    <div class="tpl-voux-main">
                                        <div class="tpl-voux-main-title">Giảm 20.000đ - Giảm tối đa 20.000đ</div>
                                        <div class="tpl-voux-badge">Sơn tường</div>
                                        <div class="tpl-voux-sub">Áp dụng mọi đơn · <a href="#" class="voux-tnc" data-voucher="HOT">Điều kiện</a></div>
                                        <div class="tpl-voux-foot">
                                            <span class="tpl-voux-time">Sắp hết hạn: còn 14 giờ</span>
                                        </div>
                                    </div>
                                    <div class="tpl-voux-side">
                                        <span class="tpl-voux-tag">Danh mục</span>
                                    </div>
                                </article>
                                </button>
                            </div>
                            <div class="col-12 col-md-6">
                                <button type="button" class="tpl-card tpl-all w-100 p-2 p-sm-3" data-template="category_discount">
                                <div class="tpl-title">Giảm giá toàn ngành</div>
                                <div class="tpl-desc">Áp dụng cho toàn bộ danh mục.</div>
                                <article class="tpl-voux-card tpl-voux-all">
                                    <span class="tpl-voux-qty">x2</span>
                                    <div class="tpl-voux-accent"></div>
                                    <div class="tpl-voux-brand">
                                        <span class="tpl-voux-logo-icon"><i class="bi bi-collection"></i></span>
                                        <div class="tpl-voux-brand-name">Toàn ngành</div>
                                    </div>
                                   <div class="tpl-voux-main">
                                        <div class="tpl-voux-main-title">Giảm 20.000đ - Giảm tối đa 20.000đ</div>
                                        <div class="tpl-voux-badge">Đơn tối thiểu 0đ</div>
                                        <div class="tpl-voux-sub">Áp dụng mọi đơn · <a href="#" class="voux-tnc" data-voucher="HOT">Điều kiện</a></div>
                                        <div class="tpl-voux-foot">
                                            <span class="tpl-voux-time">Sắp hết hạn: còn 14 giờ</span>
                                        </div>
                                    </div>
                                    <div class="tpl-voux-side">
                                        <span class="tpl-voux-tag">Toàn bộ</span>
                                    </div>
                                </article>
                                </button>
                            </div>
                            <div class="col-12">
                                <button type="button" class="tpl-card tpl-payment w-100 p-2 p-sm-3" data-template="payment_discount">
                                <div class="tpl-title">Ưu đãi thanh toán</div>
                                <div class="tpl-desc">MOMO, VNPAY, COD theo template.</div>
                                <article class="tpl-voux-card tpl-voux-payment">
                                    <span class="tpl-voux-qty">x2</span>
                                    <div class="tpl-voux-accent"></div>
                                    <div class="tpl-voux-brand">
                                        <span class="tpl-voux-logo-icon"><i class="bi bi-credit-card-2-front"></i></span>
                                        <div class="tpl-voux-brand-name">Thanh toán</div>
                                    </div>
                                    <div class="tpl-voux-main">
                                        <div class="tpl-voux-main-title">Giảm 20.000đ - Giảm tối đa 20.000đ</div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <div class="tpl-voux-badge">COD</div> <div class="tpl-voux-badge">VN PAY</div> <div class="tpl-voux-badge">MOMO</div>
                                        </div>
                                        <div class="tpl-voux-sub">Áp dụng mọi đơn · <a href="#" class="voux-tnc" data-voucher="HOT">Điều kiện</a></div>
                                        <div class="tpl-voux-foot">
                                            <span class="tpl-voux-time">Sắp hết hạn: còn 14 giờ</span>
                                        </div>
                                    </div>
                                    <div class="tpl-voux-side">
                                        <span class="tpl-voux-tag">Thanh toán</span>
                                    </div>
                                </article>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="voucher-step-content mt-3" data-step="2" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Bắt đầu</label>
                                <input name="start_at" type="datetime-local" class="form-control jsStep3Field">
                            </div>
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Kết thúc</label>
                                <input name="end_at" type="datetime-local" class="form-control jsStep3Field">
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input jsStep3Field" type="checkbox" role="switch" id="isActive" checked style="cursor: pointer;">
                                    <label class="form-check-label" for="isActive" style="cursor: pointer;">Trạng thái hoạt động</label>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row g-2">
                            <div class="col-md-4 col-6">
                                <label class="form-label small fw-semibold">MÃ VOUCHER</label>
                                <div class="input-group">
                                    <input name="code" class="form-control jsStep3Field" placeholder="VD: SALE10" required>
                                    <button type="button" class="btn btn-outline-secondary" id="btnGenerateCode"><i class="bi bi-shuffle"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label small fw-semibold">Giá trị ưu đãi</label>
                                <div class="input-group">
                                    <input name="value" type="number" class="form-control jsStep3Field" min="0" step="1" value="0">
                                    <select name="value_unit" class="form-select jsStep3Field" style="max-width:90px;">
                                        <option value="percent">%</option>
                                        <option value="fixed" selected>đ</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label small fw-semibold">Đơn tối thiểu</label>
                                <div class="input-group">
                                    <input name="min_subtotal" type="number" class="form-control jsStep3Field" min="0" step="1" value="0">
                                    <select name="min_subtotal_unit" class="form-select jsStep3Field" style="max-width:90px;">
                                        <option value="fixed" selected>đ</option>
                                        <option value="percent">%</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label small fw-semibold">Giảm tối đa</label>
                                <div class="input-group">
                                    <input name="max_discount" type="number" class="form-control jsStep3Field" min="0" step="1" placeholder="(trống = không giới hạn)">
                                    <select name="max_discount_unit" class="form-select jsStep3Field" style="max-width:90px;">
                                        <option value="fixed" selected>đ</option>
                                        <option value="percent">%</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label small fw-semibold">Lượt dùng</label>
                                <input name="max_uses" type="number" class="form-control jsStep3Field" min="0" step="1" placeholder="(trống = không giới hạn)">
                            </div>
                        </div>
                        <hr>
                        <div class="row g-2">
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Phạm vi áp dụng</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input jsStep3Field" type="radio" name="apply_scope" value="all" checked style="cursor: pointer;">
                                        <span class="small" style="cursor: pointer;">Tất cả danh mục</span>
                                    </label>
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input jsStep3Field" type="radio" name="apply_scope" value="products" style="cursor: pointer;">
                                        <span class="small" style="cursor: pointer;">Chọn danh mục</span>
                                    </label>
                                </div>
                            </div>
                             <div class="col-md-12" id="applyCategoryWrap" style="display:none;">
                                <label class="form-label small fw-semibold">Chọn danh mục</label>
                                <input type="hidden" name="apply_category_ids" id="applyCategoryIds">
                                <div class="product-picker">
                                    <div class="product-picker-toolbar">
                                        <input type="text" class="form-control form-control-sm" id="categoryFilterInput" placeholder="Lọc theo tên/ID" style="max-width:260px;">
                                        <button type="button" class="btn btn-outline-secondary btn-sm jsStep3Field" id="btnCategoryPickAll">Tất cả</button>
                                        <button type="button" class="btn btn-light btn-sm jsStep3Field" id="btnCategoryPickClear">Bỏ</button>
                                        <span class="small text-muted align-self-center">Đã chọn: <b id="pickedCategoryCount">0</b></span>
                                    </div>
                                    <div class="product-picker-list" id="categoryPickerList"></div>
                                </div>
                            </div>
                            <div class="col-md-12" id="applyProductWrap" style="display:none;">
                                <label class="form-label small fw-semibold">Chọn sản phẩm áp dụng</label>
                                <input type="hidden" name="apply_product_ids" id="applyProductIds">
                                <input type="hidden" name="apply_variant_group_ids" id="applyVariantGroupIds">
                                <input type="hidden" name="apply_variant_ids" id="applyVariantIds">
                                <div class="product-picker">
                                    <div class="product-picker-toolbar">
                                        <select class="form-select form-select-sm jsStep3Field" id="productCategoryFilter" style="max-width:200px;">
                                            <option value="0">Tất cả danh mục</option>
                                        </select>
                                        <input type="text" class="form-control form-control-sm" id="productFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:220px;">
                                        <button type="button" class="btn btn-outline-secondary btn-sm jsStep3Field" id="btnPickAll">Tất cả</button>
                                        <button type="button" class="btn btn-light btn-sm jsStep3Field" id="btnPickClear">Bỏ</button>
                                        <span class="small text-muted align-self-center">Đã chọn: <b id="pickedCount">0</b></span>
                                    </div>
                                    <div class="product-picker-list" id="productPickerList"></div>
                                </div>
                            </div>
                           
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Người dùng áp dụng</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input jsUserScope jsStep3Field" type="radio" name="apply_user_scope" value="all" checked style="cursor: pointer;">
                                        <span class="small" style="cursor: pointer;">Tất cả</span>
                                    </label>
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input jsUserScope jsStep3Field" type="radio" name="apply_user_scope" value="specific" style="cursor: pointer;">
                                        <span class="small" style="cursor: pointer;">Cá nhân</span>
                                    </label>
                                </div>
                                <input type="hidden" name="apply_user_ids" id="applyUserIds">
                                <input type="hidden" name="exclude_product_ids" id="excludeProductIds" value="">
                            </div>
                            <div class="col-md-12 mt-2" id="applyUserWrap" style="display:none;">
                                    <div class="product-picker">
                                        <div class="product-picker-toolbar">
                                            <input type="text" class="form-control form-control-sm" id="userFilterInput" placeholder="Lọc theo tên/SĐT/ID" style="max-width:260px;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm jsStep3Field" id="btnUserPickAll">Tất cả</button>
                                            <button type="button" class="btn btn-light btn-sm jsStep3Field" id="btnUserPickClear">Bỏ</button>
                                            <span class="small text-muted align-self-center">Đã chọn: <b id="pickedUserCount">0</b></span>
                                        </div>
                                        <div class="product-picker-list" id="userPickerList"></div>
                                    </div>
                             </div>
                        </div>
                        <hr>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Phương thức thanh toán</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($voucherPaymentOptions as $pm): ?>
                                        <label class="form-check form-check-inline mb-1">
                                            <input class="form-check-input jsStep3Field" type="checkbox" name="payment_methods[]" value="<?= h($pm['key']) ?>" checked style="cursor: pointer;">
                                            <span class="small" style="cursor: pointer;"><?= h($pm['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text small">Để trống sẽ áp dụng tất cả.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Đơn vị vận chuyển (GHN)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($ghnShippingOptions as $sm): ?>
                                        <label class="form-check form-check-inline mb-1">
                                            <input class="form-check-input jsStep3Field" type="checkbox" name="shipping_methods[]" value="<?= h($sm['key']) ?>" checked style="cursor: pointer;">
                                            <span class="small" style="cursor: pointer;"><?= h($sm['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <label class="form-label small fw-semibold">Mô tả hiển thị</label>
                                <input name="promo_note" class="form-control jsStep3Field" placeholder="Lượt sử dụng có hạn. Nhanh tay kẻo lỡ bạn nhé!">
                            </div>

                            <div class="col-12 mt-2">
                                <label class="form-label small fw-semibold">Thông tin chi tiết điều kiện</label>
                                <textarea name="detail_text" class="form-control jsStep3Field" rows="3" placeholder="Để trống để hệ thống tự tạo nội dung chi tiết."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-3 border-0">
                <button type="button" class="btn btn-outline-secondary px-3 py-2 fw-semibold" data-step-action="back" style="display:none; height: 40px; border-radius: 8px;"><i class="bi bi-arrow-left me-1"></i> Quay lại</button>
                <button type="button" class="btn btn-primary px-3 py-2 fw-semibold text-white" data-step-action="next" style="height: 40px; border-radius: 8px;">Tiếp tục <i class="bi bi-arrow-right ms-1"></i></button>
                <button type="button" class="btn btn-success px-4 py-2 fw-semibold text-white shadow-sm" id="btnSave" style="display:none; height: 40px; border-radius: 8px; background-color: var(--theme-primary, #0c4c29); border: none;">Áp dụng</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= h($baseUrl) ?>/assets/js/jquery.dataTables.min.js"></script>
<script src="<?= h($baseUrl) ?>/assets/js/dataTables.bootstrap5.min.js"></script>

<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_admin/ecommerce/ajax/voucher.php';
    const PRODUCT_OPTIONS = <?= json_encode($productOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]' ?>;
    const USER_OPTIONS = <?= json_encode($userOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]' ?>;
    const CATEGORY_OPTIONS = <?= json_encode($categoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]' ?>;
    const notify = (msg, type = 'info') => {
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    };

    const modalEl = document.getElementById('voucherModal');
    const modal = new bootstrap.Modal(modalEl);
    const detailModalEl = document.getElementById('voucherDetailModal');
    const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
    let currentStep = 1;

    const $voucherTable = $('#voucherTable');
    const emptyState = $('#vouchersEmpty');
    const searchInput = $('#searchVoucher');
    const statusFilter = $('#filterStatus');
    let voucherDt = null;
    let currentVoucherTab = 'all';

    function ensureVoucherDataTable(){
        if (!$voucherTable.length) return null;
        if (!$.fn.DataTable) return null;

        if ($.fn.DataTable.isDataTable($voucherTable)) {
            voucherDt = $voucherTable.DataTable();
            return voucherDt;
        }

        voucherDt = $voucherTable.DataTable({
            paging: true,
            searching: true,
            ordering: false,
            lengthChange: false,
            pageLength: 10,
            pagingType: 'simple_numbers',
            dom: "t<'d-flex justify-content-between align-items-center p-3 flex-column flex-md-row'<'dataTables_info'i><'dataTables_paginate paging_simple_numbers'p>>",
            language: {
                processing: 'Đang xử lý...',
                lengthMenu: 'Hiển thị _MENU_ voucher',
                zeroRecords: 'Không tìm thấy voucher phù hợp',
                info: 'Hiển thị _START_ - _END_ / _TOTAL_',
                infoEmpty: 'Không có voucher',
                infoFiltered: '(lọc từ _MAX_ voucher)',
                paginate: {
                    first: 'Đầu',
                    last: 'Cuối',
                    next: '>',
                    previous: '<'
                }
            }
        });

        if (emptyState && emptyState.length) {
            voucherDt.on('draw', function(){
                try {
                    const shown = voucherDt.rows({ filter: 'applied' }).data().length;
                    emptyState.toggle(shown === 0);
                } catch (e) {}
            });
            voucherDt.draw();
        }

        return voucherDt;
    }

    function getRowDataFromTrigger($trigger){
        const $tr = $trigger.closest('tr');
        let row = $tr.data('row');
        if (!row && voucherDt && $tr.length) {
            const node = voucherDt.row($tr).node();
            if (node) row = $(node).data('row');
        }
        return row || null;
    }

    function toDtLocal(v){
        if (!v) return '';
        // expects yyyy-mm-dd hh:mm:ss
        return String(v).replace(' ', 'T').slice(0, 16);
    }

    function fromDtLocal(v){
        return v ? String(v).replace('T', ' ') + ':00' : '';
    }

    function nowPlusDaysLocal(days){
        const d = new Date();
        const nDays = Number.isFinite(Number(days)) ? Number(days) : 0;
        if (nDays !== 0) {
            d.setDate(d.getDate() + nDays);
        }
        const pad = (n) => String(n).padStart(2, '0');
        return (
            d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            'T' + pad(d.getHours()) + ':' + pad(d.getMinutes())
        );
    }

    function escHtml(text){
        return String(text || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function fmtMoneyJs(n){
        return new Intl.NumberFormat('vi-VN').format(Number(n) || 0) + ' đ';
    }

    function buildDetailHtml(row){
        // Đơn vị ưu đãi: đồng hay % (ưu tiên value_unit giống cột "Ưu đãi" ngoài bảng)
        const rawUnit = String(row.value_unit || row.type || 'fixed').toLowerCase();
        const valueUnit = (rawUnit === 'percent') ? 'percent' : 'fixed';
        const value = Number(row.value || 0);

        // Trần giảm giá + đơn vị (mặc định tính theo tiền nếu không rõ)
        const hasMaxRaw = row.max_discount !== null && row.max_discount !== '';
        const maxDiscount = hasMaxRaw ? Number(row.max_discount || 0) : 0;
        const rawMaxUnit = String(row.max_discount_unit || 'fixed').toLowerCase();
        const maxUnit = (rawMaxUnit === 'percent') ? 'percent' : 'fixed';

        let discountText = '';
        if (valueUnit === 'percent') {
            // Ví dụ: Giảm 10(%) hoặc Giảm 10%
            discountText = 'Giảm ' + value + '(%)';
            if (maxDiscount > 0) {
                const maxLabel = (maxUnit === 'percent') ? (maxDiscount + '(%)') : fmtMoneyJs(maxDiscount);
                discountText += ' (tối đa ' + maxLabel + ')';
            }
        } else {
            // Giảm theo đồng
            discountText = 'Giảm ' + fmtMoneyJs(value);
            if (maxDiscount > 0) {
                const maxLabel = (maxUnit === 'percent') ? (maxDiscount + '(%)') : fmtMoneyJs(maxDiscount);
                discountText += ' · Giảm tối đa ' + maxLabel;
            }
        }

        const minSubtotal = Number(row.min_subtotal || 0);
        const minText = minSubtotal > 0 ? ('Đơn tối thiểu ' + fmtMoneyJs(minSubtotal)) : 'Không yêu cầu giá trị tối thiểu';

        const rawTpl = String(row.voucher_template || '').toLowerCase().trim();
        const allowed = ['order_discount','shipping_discount','only_category_discount','category_discount','payment_discount'];
        let tplKey = allowed.includes(rawTpl) ? rawTpl : '';
        const rawTarget = String(row.discount_target || 'order').toLowerCase();
        const targets = rawTarget.split(/[\s,;|]+/).map(t => t.trim()).filter(Boolean);
        const hasShipping = targets.includes('shipping');
        const hasPayment = String(row.payment_methods || '').trim() !== '';
        const hasCategories = String(row.apply_category_ids || '').trim() !== '';
        const applyScope = String(row.apply_scope || 'all').toLowerCase();
        if (!tplKey) {
            if (hasShipping && !hasPayment) tplKey = 'shipping_discount';
            else if (hasPayment) tplKey = 'payment_discount';
            else if (hasCategories) tplKey = applyScope === 'products' ? 'only_category_discount' : 'category_discount';
            else tplKey = 'order_discount';
        }

        let tplLabel = 'Ưu đãi đơn hàng';
        if (tplKey === 'shipping_discount') tplLabel = 'Ưu đãi Vận chuyển';
        else if (tplKey === 'only_category_discount') tplLabel = 'Ưu đãi Ngành hàng';
        else if (tplKey === 'category_discount') tplLabel = 'Ưu đãi Toàn ngành';
        else if (tplKey === 'payment_discount') tplLabel = 'Ưu đãi Thanh toán';

        const targetLabel = hasShipping ? 'Ưu đãi Vận chuyển' : 'Ưu đãi Đơn hàng';

        const payKeys = String(row.payment_methods || '').split(',').map(v => v.trim().toLowerCase()).filter(Boolean);
        let payText = 'Mọi phương thức thanh toán';
        if (payKeys.length) {
            const labels = payKeys.map(k => {
                if (k === 'cod') return 'COD';
                if (k === 'vnpay') return 'Ví VN PAY';
                if (k === 'momo') return 'Ví MoMo';
                return k.toUpperCase();
            });
            payText = labels.join(', ');
        }

        const shipKeys = String(row.shipping_methods || '').split(',').map(v => v.trim().toLowerCase()).filter(Boolean);
        let shipText = 'Mọi đơn vị vận chuyển';
        if (shipKeys.length) {
            const labels = shipKeys.map(k => {
                if (k === 'ghn_nhanh') return 'GHN Nhanh';
                if (k === 'ghn_tiet_kiem') return 'GHN Tiết kiệm';
                if (k === 'ghn_hoa_toc') return 'GHN Hỏa tốc';
                if (k === 'tieu_chuan') return 'Tiêu chuẩn';
                return k.toUpperCase();
            });
            shipText = labels.join(', ');
        }

        const startAt = String(row.start_at || '').trim();
        const endAt = String(row.end_at || '').trim();
        let timeText = 'Không giới hạn thời gian cụ thể';
        if (startAt && endAt) timeText = 'Từ ' + startAt + ' đến ' + endAt;
        else if (endAt) timeText = 'Đến hết ' + endAt;
        else if (startAt) timeText = 'Bắt đầu từ ' + startAt;

        const maxUses = row.max_uses !== null && row.max_uses !== '' ? Number(row.max_uses || 0) : null;
        const usedCount = Number(row.used_count || 0);
        let useText = 'Không giới hạn lượt dùng';
        if (maxUses !== null && maxUses > 0) {
            const remain = Math.max(maxUses - usedCount, 0);
            useText = 'Tối đa ' + maxUses + ' lượt, còn khoảng ' + remain + ' lượt';
        }

        const applyUsers = String(row.apply_user_ids || '').trim();
        const userText = applyUsers ? ('Khách hàng: ' + applyUsers.split(',').filter(v => v.trim() !== '').length + ' người') : 'Khách hàng: Tất cả';

        const promo = String(row.promo_note || '').trim();

        return `
            <div class="small">
                <div><strong>Mã:</strong> ${escHtml(row.code || '')}</div>
                <div><strong>Loại:</strong> ${escHtml(tplLabel)}</div>
                <div><strong>Ưu đãi:</strong> ${escHtml(discountText)}</div>
                <div><strong>Đơn tối thiểu:</strong> ${escHtml(minText)}</div>
                <div><strong>Áp dụng:</strong> ${escHtml(targetLabel)}</div>
                <div><strong>Thanh toán:</strong> ${escHtml(payText)}</div>
                <div><strong>Vận chuyển:</strong> ${escHtml(shipText)}</div>
                <div><strong>Thời gian:</strong> ${escHtml(timeText)}</div>
                <div><strong>Lượt dùng:</strong> ${escHtml(useText)}</div>
                <div><strong>${escHtml(userText)}</strong></div>
                ${promo ? ('<div class="mt-1"><strong>Ghi chú:</strong> ' + escHtml(promo) + '</div>') : ''}
            </div>
        `;
    }

    function setPaymentMethods(list){
        const normalized = Array.isArray(list)
            ? list.map(v => String(v || '').trim().toLowerCase()).filter(v => v)
            : String(list || '').split(',').map(v => v.trim().toLowerCase()).filter(v => v);
        const fallbackAll = normalized.length === 0;
        $('#voucherForm input[name="payment_methods[]"]').each(function(){
            const key = String($(this).val() || '').trim().toLowerCase();
            $(this).prop('checked', fallbackAll ? true : normalized.includes(key));
        });
    }

    function setShippingMethods(list){
        const normalized = Array.isArray(list)
            ? list.map(v => String(v || '').trim().toLowerCase()).filter(v => v)
            : String(list || '').split(',').map(v => v.trim().toLowerCase()).filter(v => v);
        const fallbackAll = normalized.length === 0;
        $('#voucherForm input[name="shipping_methods[]"]').each(function(){
            const key = String($(this).val() || '').trim().toLowerCase();
            $(this).prop('checked', fallbackAll ? true : normalized.includes(key));
        });
    }

    function setDiscountTargetValue(target){
        const val = String(target || '').toLowerCase().trim();
        if (!val) {
            $('#discountTargetInput').val('');
            return;
        }
        const normalized = val === 'shipping' ? 'shipping' : 'order';
        $('#discountTargetInput').val(normalized);
    }

    function setStep3Enabled(enabled){
        $('#voucherForm .jsStep3Field').prop('disabled', !enabled);
    }

    function setActiveStep(step){
        currentStep = step;
        $('.voucher-step-content').hide();
        $('.voucher-step-content[data-step="' + step + '"]').show();
        $('.step-item').removeClass('active');
        $('.step-item[data-step="' + step + '"]').addClass('active');
        const isFirst = step === 1;
        const isLast = step === 2;
        $('[data-step-action="back"]').toggle(!isFirst);
        $('[data-step-action="next"]').toggle(!isLast);
        $('#btnSave').toggle(isLast);
    }

    function setTemplate(key){
        const templateKey = String(key || '').trim();
        if (!templateKey) return;
        $('#voucherTemplateKey').val(templateKey);
        $('.tpl-card').removeClass('active');
        $('.tpl-card[data-template="' + templateKey + '"]').addClass('active');

        // Với template thanh toán: chỉ set mặc định tất cả phương thức
        // nếu hiện tại chưa có lựa chọn nào (tránh ghi đè cấu hình khi sửa)
        if (templateKey === 'payment_discount') {
            const currentChecked = $('#voucherForm input[name="payment_methods[]"]:checked')
                .map((_, el) => $(el).val())
                .get();
            if (!currentChecked.length) {
                setPaymentMethods($('#voucherForm input[name="payment_methods[]"]').map((_, el) => $(el).val()).get());
            }
        }
        // Gán loại mã (discount_target) theo template
        if (templateKey === 'shipping_discount') {
            setDiscountTargetValue('shipping');
        } else {
            // order_discount, only_category_discount, category_discount, payment_discount => giảm giá đơn hàng
            setDiscountTargetValue('order');
        }

        setStep3Enabled(true);
    }

    function getSelectedProductIds(){
        const raw = String($('#applyProductIds').val() || '').trim();
        if (!raw) return [];
        const parts = raw.split(',').map(v => parseInt(String(v).trim(), 10)).filter(v => Number.isFinite(v) && v > 0);
        return [...new Set(parts)];
    }

    function setSelectedProductIds(ids){
        const unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0))];
        $('#applyProductIds').val(unique.join(','));
        $('#pickedCount').text(unique.length);
    }

    function getSelectedUserIds(){
        const raw = String($('#applyUserIds').val() || '').trim();
        if (!raw) return [];
        const parts = raw.split(',').map(v => parseInt(String(v).trim(), 10)).filter(v => Number.isFinite(v) && v > 0);
        return [...new Set(parts)];
    }

    function setSelectedUserIds(ids){
        const unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0))];
        $('#applyUserIds').val(unique.join(','));
        $('#pickedUserCount').text(unique.length);
    }

    function getSelectedCategoryIds(){
        const raw = String($('#applyCategoryIds').val() || '').trim();
        if (!raw) return [];
        const parts = raw.split(',').map(v => parseInt(String(v).trim(), 10)).filter(v => Number.isFinite(v) && v > 0);
        return [...new Set(parts)];
    }

    function setSelectedCategoryIds(ids){
        const unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0))];
        $('#applyCategoryIds').val(unique.join(','));
        $('#pickedCategoryCount').text(unique.length);
    }

    function getSelectedVariantGroupIds(){
        const raw = String($('#applyVariantGroupIds').val() || '').trim();
        if (!raw) return [];
        return [...new Set(raw.split(',').map(v => parseInt(v.trim(), 10)).filter(v => v > 0))];
    }
    function setSelectedVariantGroupIds(ids){
        const unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => v > 0))];
        $('#applyVariantGroupIds').val(unique.join(','));
    }

    function getSelectedVariantIds(){
        const raw = String($('#applyVariantIds').val() || '').trim();
        if (!raw) return [];
        return [...new Set(raw.split(',').map(v => parseInt(v.trim(), 10)).filter(v => v > 0))];
    }
    function setSelectedVariantIds(ids){
        const unique = [...new Set((ids || []).map(v => parseInt(v, 10)).filter(v => v > 0))];
        $('#applyVariantIds').val(unique.join(','));
    }

    // Sản phẩm có biến thể "thật" (có nhóm, hoặc >1 biến thể, hoặc 1 biến thể khác "Mặc định").
    function hasRealVariantsV(p){
        const groups = Array.isArray(p.groups) ? p.groups : [];
        const noGroup = Array.isArray(p.no_group_variants) ? p.no_group_variants : [];
        if (groups.length > 0) return true;
        if (noGroup.length > 1) return true;
        if (noGroup.length === 1){
            const n = String(noGroup[0].name || '').trim();
            return n !== '' && n !== 'Mặc định';
        }
        return false;
    }

    // Picker dạng cây (port từ promotion.php): sản phẩm → nhóm → biến thể, có collapse + giá + SKU.
    // Vẫn đọc/ghi 3 hidden field cũ (apply_product_ids / apply_variant_group_ids / apply_variant_ids)
    // nên luồng lưu phía backend không đổi. group_id được giữ ở nút "Chọn cả nhóm".
    function renderProductPicker(){
        const selectedP = new Set(getSelectedProductIds());
        const selectedG = new Set(getSelectedVariantGroupIds());
        const selectedV = new Set(getSelectedVariantIds());

        const keyword = String($('#productFilterInput').val() || '').trim().toLowerCase();
        const catId = parseInt(String($('#productCategoryFilter').val() || '0'), 10) || 0;

        let list = (PRODUCT_OPTIONS || []).filter((p) => {
            if (catId > 0 && Number(p.category_id || 0) !== catId) return false;
            if (!keyword) return true;
            if (`${p.name || ''} ${p.sku || ''} ${p.id || ''}`.toLowerCase().includes(keyword)) return true;
            if (p.groups && p.groups.some(g => String(g.name||'').toLowerCase().includes(keyword))) return true;
            if (p.groups && p.groups.some(g => g.variants && g.variants.some(v => `${v.name} ${v.sku}`.toLowerCase().includes(keyword)))) return true;
            if (p.no_group_variants && p.no_group_variants.some(v => `${v.name} ${v.sku}`.toLowerCase().includes(keyword))) return true;
            return false;
        });

        // Đưa sản phẩm đã chọn lên đầu.
        list = list.slice().sort((a,b) => (selectedP.has(Number(a.id))?0:1) - (selectedP.has(Number(b.id))?0:1));

        if (!list.length) {
            $('#productPickerList').html('<div class="product-picker-empty">Không có sản phẩm phù hợp.</div>');
            updatePickedCount();
            return;
        }

        const variantCardHtml = (p, v) => {
            const vid = Number(v.id || 0);
            const vChecked = selectedV.has(vid) ? 'checked' : '';
            const vName = String(v.name || 'Biến thể');
            const vSku = String(v.sku || '');
            const vPrice = Number(v.price || 0);
            return `
                <div class="tree-variant-card">
                    <input type="checkbox" class="form-check-input jsVariantPick" data-pid="${p.id}" data-vid="${vid}" value="${vid}" ${vChecked}>
                    <div class="variant-info">
                        <div class="variant-name" title="${escHtml(vName)}">${escHtml(vName)} <span class="text-muted">#${vid}</span></div>
                        <div class="variant-meta">${vSku ? escHtml(vSku) + ' · ' : ''}${fmtMoneyJs(vPrice)}</div>
                    </div>
                </div>
            `;
        };

        const html = list.map((p) => {
            const pid = Number(p.id || 0);
            const pChecked = selectedP.has(pid) ? 'checked' : '';
            const pName = String(p.name || 'Sản phẩm');
            const pSku = String(p.sku || '');
            const pPrice = Number(p.price || 0);
            const groups = Array.isArray(p.groups) ? p.groups : [];
            const noGroup = Array.isArray(p.no_group_variants) ? p.no_group_variants : [];
            const hasVar = hasRealVariantsV(p);

            let treeContent = '';
            if (hasVar){
                const groupsHtml = groups.map(g => {
                    const gid = Number(g.id || 0);
                    const gVariants = Array.isArray(g.variants) ? g.variants : [];
                    const gVids = gVariants.map(v => Number(v.id||0)).filter(v => v>0).join(',');
                    return `
                        <div class="tree-group-section">
                            <div class="tree-group-header d-flex align-items-center gap-2">
                                <i class="bi bi-tags text-primary"></i>
                                <span>Nhóm: ${escHtml(String(g.name||'Nhóm'))}</span>
                                <span class="ms-auto d-flex gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 jsGroupAll" data-pid="${pid}" data-gid="${gid}" data-vids="${gVids}" style="font-size:0.7rem;">Chọn cả nhóm</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 jsGroupNone" data-pid="${pid}" data-gid="${gid}" data-vids="${gVids}" style="font-size:0.7rem;">Bỏ</button>
                                </span>
                            </div>
                            <div class="tree-variants-grid">${gVariants.map(v => variantCardHtml(p, v)).join('')}</div>
                        </div>
                    `;
                }).join('');

                let noGroupHtml = '';
                if (noGroup.length){
                    noGroupHtml = `<div class="tree-group-section"><div class="tree-variants-grid">${noGroup.map(v => variantCardHtml(p, v)).join('')}</div></div>`;
                }

                const expanded = selectedP.has(pid);
                treeContent = `
                    <div class="tree-content" style="display:${expanded ? 'block' : 'none'};" id="vProdTree_${pid}">
                        ${groupsHtml}${noGroupHtml}
                    </div>
                `;
            }

            const isExpanded = hasVar && selectedP.has(pid);
            const toggleIcon = hasVar
                ? `<button type="button" class="tree-toggle-btn ${isExpanded ? '' : 'collapsed'} jsTreeToggle" data-pid="${pid}"><i class="bi bi-chevron-down"></i></button>`
                : '';

            return `
                <div class="tree-node-product" id="vProdNode_${pid}">
                    <div class="tree-header d-flex align-items-center gap-2 py-2 px-3">
                        ${toggleIcon}
                        <input type="checkbox" class="form-check-input jsProductPick" value="${pid}" ${pChecked}>
                        <div class="product-info flex-grow-1 min-width-0">
                            <div class="product-name text-truncate fw-semibold">${escHtml(pName)} <span class="text-muted small">#${pid}</span></div>
                            <div class="product-meta small text-muted">${pSku ? 'SKU: ' + escHtml(pSku) + ' · ' : ''}Giá gốc: <span class="fw-semibold text-dark">${fmtMoneyJs(pPrice)}</span></div>
                        </div>
                    </div>
                    ${treeContent}
                </div>
            `;
        }).join('');

        $('#productPickerList').html(html);
        updatePickedCount();
    }

    function updatePickedCount(){
        const p = getSelectedProductIds().length;
        const g = getSelectedVariantGroupIds().length;
        const v = getSelectedVariantIds().length;
        let text = p + ' SP';
        if (g > 0) text += ', ' + g + ' Nhóm';
        if (v > 0) text += ', ' + v + ' Biến thể';
        $('#pickedCount').text(text);
    }



    function renderUserPicker(){
        const selected = getSelectedUserIds();
        const selectedSet = new Set(selected);
        const keyword = String($('#userFilterInput').val() || '').trim().toLowerCase();
        const list = (USER_OPTIONS || []).filter((u) => {
            if (!keyword) return true;
            const hay = `${u.name || ''} ${u.phone || ''} ${u.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });

        if (!list.length) {
            $('#userPickerList').html('<div class="product-picker-empty">Không có người dùng phù hợp.</div>');
            setSelectedUserIds(selected);
            return;
        }

        const html = list.map((u) => {
            const checked = selectedSet.has(Number(u.id)) ? 'checked' : '';
            const name = escHtml(u.name || 'Người dùng');
            const phone = escHtml(u.phone || '');
            return `
                <label class="product-picker-item">
                    <input type="checkbox" class="form-check-input me-1 jsUserPick" value="${Number(u.id)}" ${checked}>
                    <div>
                        <div class="fw-semibold">${name} <span class="meta">#${Number(u.id)}</span></div>
                        <div class="meta">${phone ? ('SĐT: ' + phone) : 'Không có SĐT'}</div>
                    </div>
                </label>
            `;
        }).join('');
        $('#userPickerList').html(html);
        setSelectedUserIds(selected);
    }

    function renderCategoryPicker(){
        const selected = getSelectedCategoryIds();
        const selectedSet = new Set(selected);
        const keyword = String($('#categoryFilterInput').val() || '').trim().toLowerCase();
        const list = (CATEGORY_OPTIONS || []).filter((c) => {
            if (!keyword) return true;
            const hay = `${c.name || ''} ${c.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });

        if (!list.length) {
            $('#categoryPickerList').html('<div class="product-picker-empty">Không có danh mục phù hợp.</div>');
            setSelectedCategoryIds(selected);
            return;
        }

        const html = list.map((c) => {
            const checked = selectedSet.has(Number(c.id)) ? 'checked' : '';
            const name = escHtml(c.name || 'Danh mục');
            return `
                <label class="product-picker-item">
                    <input type="checkbox" class="form-check-input me-1 jsCategoryPick" value="${Number(c.id)}" ${checked}>
                    <div>
                        <div class="fw-semibold">${name} <span class="meta">#${Number(c.id)}</span></div>
                    </div>
                </label>
            `;
        }).join('');
        $('#categoryPickerList').html(html);
        setSelectedCategoryIds(selected);
    }

    function openNew(){
        $('#mTitle').text('Tạo mã');
        $('#voucherForm')[0].reset();
        $('#voucherForm [name=id]').val('0');
        $('#isActive').prop('checked', true);
        // Mặc định thời gian bắt đầu = hiện tại, kết thúc = hiện tại + 30 ngày
        $('#voucherForm [name=start_at]').val(nowPlusDaysLocal(0));
        $('#voucherForm [name=end_at]').val(nowPlusDaysLocal(30));
        $('#applyProductIds').val('');
        $('#productFilterInput').val('');
        $('#productCategoryFilter').val('0');
        $('input[name="apply_scope"][value="all"]').prop('checked', true).trigger('change');
        $('#voucherForm [name=apply_user_ids]').val('');
        $('#voucherForm [name=exclude_product_ids]').val('');
        $('#applyCategoryIds').val('');
        $('#categoryFilterInput').val('');
        setSelectedCategoryIds([]);
        setSelectedVariantGroupIds([]);
        setSelectedVariantIds([]);

        $('#voucherForm [name=promo_note]').val('Lượt sử dụng có hạn. Nhanh tay kẻo lỡ bạn nhé!');
        $('#voucherForm [name=detail_text]').val('');
        setPaymentMethods($('#voucherForm input[name="payment_methods[]"]').map((_, el) => $(el).val()).get());
        setShippingMethods($('#voucherForm input[name="shipping_methods[]"]').map((_, el) => $(el).val()).get());
        setDiscountTargetValue('');
        $('#voucherForm [name="value_unit"]').val('fixed');
        $('#voucherForm [name="min_subtotal_unit"]').val('fixed');
        $('#voucherForm [name="max_discount_unit"]').val('fixed');
        setSelectedUserIds([]);
        $('input[name="apply_user_scope"][value="all"]').prop('checked', true).trigger('change');
        $('#excludeProductIds').val('');
        renderProductPicker();
        renderUserPicker();
        $('.tpl-card').removeClass('active');
        $('#applyCategoryWrap').hide();
        renderCategoryPicker();
        setStep3Enabled(false);
        setActiveStep(1);
        modal.show();
    }

    function openEdit(row){
        $('#mTitle').text('Sửa mã');
        const d = row;
        $('#voucherForm [name=id]').val(d.id || 0);
        $('#voucherForm [name=code]').val(d.code || '');
        const valueUnit = String(d.value_unit || d.type || 'fixed').toLowerCase() === 'percent' ? 'percent' : 'fixed';
        $('#voucherForm [name=value_unit]').val(valueUnit);
        const voucherValue = d.value || 0;
        $('#voucherForm [name=value]').val(voucherValue);
        const minUnit = String(d.min_subtotal_unit || 'fixed').toLowerCase() === 'percent' ? 'percent' : 'fixed';
        $('#voucherForm [name=min_subtotal_unit]').val(minUnit);
        const maxUnit = String(d.max_discount_unit || 'fixed').toLowerCase() === 'percent' ? 'percent' : 'fixed';
        $('#voucherForm [name=max_discount_unit]').val(maxUnit);
        $('#voucherForm [name=min_subtotal]').val(d.min_subtotal || 0);
        $('#voucherForm [name=max_discount]').val(d.max_discount ?? '');
        $('#voucherForm [name=max_uses]').val(d.max_uses ?? '');
        $('input[name="apply_scope"][value="' + (d.apply_scope || 'all') + '"]').prop('checked', true);
        $('#applyProductIds').val(d.apply_product_ids || '');
        $('#applyVariantGroupIds').val(d.apply_variant_group_ids || '');
        $('#applyVariantIds').val(d.apply_variant_ids || '');
        $('#productFilterInput').val('');
        $('#voucherForm [name=apply_user_ids]').val(d.apply_user_ids || '');
        $('#voucherForm [name=exclude_product_ids]').val(d.exclude_product_ids || '');
        $('#applyCategoryIds').val(d.apply_category_ids || '');

        $('#voucherForm [name=promo_note]').val(d.promo_note || '');
        $('#voucherForm [name=detail_text]').val(d.detail_text || '');
        setPaymentMethods(d.payment_methods || []);
        setShippingMethods(d.shipping_methods || []);
        const hasProducts = String(d.apply_scope || '') === 'products';
        const hasPaymentFilter = String(d.payment_methods || '').trim() !== '';
        const rawTarget = String(d.discount_target || 'order').toLowerCase();
        const hasCategories = String(d.apply_category_ids || '').trim() !== '';
        let templateKey = String(d.voucher_template || '').trim();
        if (!templateKey) {
            if (rawTarget.includes('shipping') && !hasPaymentFilter) {
                templateKey = 'shipping_discount';
            } else if (hasPaymentFilter) {
                templateKey = 'payment_discount';
            } else if (hasCategories) {
                templateKey = hasProducts ? 'only_category_discount' : 'category_discount';
            } else {
                templateKey = 'order_discount';
            }
        }
        setTemplate(templateKey);
        const hasUsers = String(d.apply_user_ids || '').trim() !== '';
        $('input[name="apply_user_scope"][value="specific"]').prop('checked', hasUsers);
        $('input[name="apply_user_scope"][value="all"]').prop('checked', !hasUsers);
        setSelectedUserIds(getSelectedUserIds());
        $('#voucherForm [name=start_at]').val(toDtLocal(d.start_at));
        $('#voucherForm [name=end_at]').val(toDtLocal(d.end_at));
        $('#isActive').prop('checked', String(d.is_active) === '1');
        $('input[name="apply_scope"]:checked').trigger('change');
        $('input[name="apply_user_scope"]:checked').trigger('change');
        $('#productCategoryFilter').val('0');
        $('#productFilterInput').val('');
        renderProductPicker();
        renderUserPicker();
        renderCategoryPicker();
        setActiveStep(2);
        modal.show();
    }

    $(document).on('change', 'input[name="apply_scope"]', function(){
        const scope = $('input[name="apply_scope"]:checked').val();
        const templateKey = $('#voucherTemplateKey').val();
        if (scope === 'products') {
            if (templateKey === 'category_discount') {
                // Cho phép hiển thị cả 2 để user chọn danh mục sau đó chọn sp (nếu muốn)
                $('#applyCategoryWrap').show();
                $('#applyProductWrap').show();
            } else if (['only_category_discount', 'order_discount', 'shipping_discount'].includes(templateKey)) {
                $('#applyCategoryWrap').show();
                $('#applyProductWrap').show();
            } else {
                $('#applyCategoryWrap').hide();
                $('#applyProductWrap').show();
            }
        } else {
            $('#applyProductWrap').hide();
            $('#applyCategoryWrap').hide();
        }
    });
    
    $(document).on('change', 'input[name="apply_user_scope"]', function(){
        const scope = $('input[name="apply_user_scope"]:checked').val();
        if (scope === 'specific') {
            $('#applyUserWrap').show();
            renderUserPicker();
        } else {
            $('#applyUserWrap').hide();
        }
    });

    $(document).on('change', '#userPickerList .jsUserPick', function(){
        const current = new Set(getSelectedUserIds());
        const id = parseInt($(this).val(), 10);
        if (!Number.isFinite(id) || id <= 0) return;
        if ($(this).is(':checked')) current.add(id);
        else current.delete(id);
        setSelectedUserIds([...current]);
    });

    $('#btnUserPickAll').on('click', function(){
        const ids = (USER_OPTIONS || []).map(u => parseInt(u.id));
        setSelectedUserIds(ids);
        renderUserPicker();
    });

    $('#btnUserPickClear').on('click', function(){
        setSelectedUserIds([]);
        renderUserPicker();
    });



    $('.tpl-card').on('click', function(){
        const key = String($(this).data('template') || '').trim();
        if (!key) return;
        setTemplate(key);
        setActiveStep(2);
    });

    $('[data-step-action="next"]').on('click', function(){
        if (currentStep === 1) {
            const tpl = String($('#voucherTemplateKey').val() || '').trim();
            if (!tpl) {
                notify('Vui lòng chọn template', 'warning');
                return;
            }
            setActiveStep(2);
            renderProductPicker();
            renderUserPicker();
            renderCategoryPicker();
            return;
        }
    });

    $('[data-step-action="back"]').on('click', function(){
        if (currentStep === 2) {
            setActiveStep(1);
            return;
        }
    });

    $('#btnNew').click(openNew);
    // Lọc theo tab (summary cards)
    $(document).on('click', '.summary-card[data-voucher-tab]', function(){
        const key = String($(this).data('voucher-tab') || 'all');
        $('.summary-card').removeClass('active');
        $(this).addClass('active');
        currentVoucherTab = key;
        if (!voucherDt) {
            ensureVoucherDataTable();
        }
        if (voucherDt) voucherDt.draw();
    });

    // Search + status filter (custom)
    let searchTimer = null;
    if (searchInput && searchInput.length) {
        searchInput.on('input', function(){
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                if (voucherDt) voucherDt.draw();
            }, 350);
        });
    }
    if (statusFilter && statusFilter.length) {
        statusFilter.on('change', function(){
            if (voucherDt) voucherDt.draw();
        });
    }
    $('#refreshVouchers').on('click', function(){
        location.reload();
    });

    // Delegated handlers (để không mất event khi DataTables phân trang)
    $(document).on('click', '#voucherTable .jsEdit', function(){
        const row = getRowDataFromTrigger($(this));
        if (!row) return;
        openEdit(row);
    });
    $(document).on('click', '#voucherTable .jsViewDetail', function(){
        if (!detailModal) return;
        const row = getRowDataFromTrigger($(this));
        if (!row) return;
        $('#voucherDetailBody').html(buildDetailHtml(row));
        detailModal.show();
    });

    // Delegated handlers for product picker (optimized: no full re-render)
    // Mở/thu gọn cây biến thể của 1 sản phẩm.
    $(document).on('click', '#productPickerList .jsTreeToggle', function(e){
        e.preventDefault(); e.stopPropagation();
        const pid = $(this).data('pid');
        const $content = $('#vProdTree_' + pid);
        if ($content.length){
            const collapsed = $content.css('display') === 'none';
            $content.css('display', collapsed ? 'block' : 'none');
            $(this).toggleClass('collapsed', !collapsed);
        }
    });

    // Chọn/bỏ cả 1 sản phẩm → tích tất cả biến thể; nếu có nhóm thì set/xóa toàn bộ group_id của SP.
    $(document).on('change', '#productPickerList .jsProductPick', function(){
        const pid = parseInt($(this).val(), 10);
        const checked = $(this).is(':checked');
        const pSet = new Set(getSelectedProductIds());
        const gSet = new Set(getSelectedVariantGroupIds());
        const vSet = new Set(getSelectedVariantIds());

        if (checked) pSet.add(pid); else pSet.delete(pid);

        // Đồng bộ toàn bộ checkbox biến thể trong cây của SP này.
        const $tree = $('#vProdTree_' + pid);
        $tree.find('.jsVariantPick').each(function(){
            $(this).prop('checked', checked);
            const vid = parseInt($(this).val(), 10);
            if (checked) vSet.add(vid); else vSet.delete(vid);
        });
        // group_id: chọn cả SP = chọn cả các nhóm của SP.
        $tree.find('.jsGroupAll').each(function(){
            const gid = parseInt($(this).data('gid'), 10);
            if (gid > 0){ if (checked) gSet.add(gid); else gSet.delete(gid); }
        });

        // Mở cây khi vừa chọn để thấy biến thể.
        if (checked && $tree.length && $tree.css('display') === 'none'){
            $tree.css('display', 'block');
            $('#productPickerList .jsTreeToggle[data-pid="' + pid + '"]').removeClass('collapsed');
        }

        setSelectedProductIds([...pSet]);
        setSelectedVariantGroupIds([...gSet]);
        setSelectedVariantIds([...vSet]);
        updatePickedCount();
    });

    // Tích/bỏ 1 biến thể → đồng bộ cờ sản phẩm cha.
    $(document).on('change', '#productPickerList .jsVariantPick', function(){
        const pid = parseInt($(this).data('pid'), 10);
        const vid = parseInt($(this).data('vid'), 10);
        const checked = $(this).is(':checked');
        const pSet = new Set(getSelectedProductIds());
        const vSet = new Set(getSelectedVariantIds());

        if (checked) vSet.add(vid); else vSet.delete(vid);

        // Còn biến thể nào của SP được tích không → cập nhật cờ cha.
        const $tree = $('#vProdTree_' + pid);
        const anyChecked = $tree.find('.jsVariantPick:checked').length > 0;
        const $parent = $('#productPickerList .jsProductPick[value="' + pid + '"]');
        if (anyChecked){ pSet.add(pid); $parent.prop('checked', true); }
        else { pSet.delete(pid); $parent.prop('checked', false); }

        setSelectedProductIds([...pSet]);
        setSelectedVariantIds([...vSet]);
        updatePickedCount();
    });

    // Nút "Chọn cả nhóm" / "Bỏ" trong 1 nhóm biến thể.
    $(document).on('click', '#productPickerList .jsGroupAll, #productPickerList .jsGroupNone', function(e){
        e.preventDefault(); e.stopPropagation();
        const makeChecked = $(this).hasClass('jsGroupAll');
        const pid = parseInt($(this).data('pid'), 10);
        const gid = parseInt($(this).data('gid'), 10);
        const vids = String($(this).data('vids') || '').split(',').map(s => parseInt(s,10)).filter(v => v>0);
        if (!vids.length) return;

        const pSet = new Set(getSelectedProductIds());
        const gSet = new Set(getSelectedVariantGroupIds());
        const vSet = new Set(getSelectedVariantIds());

        const $tree = $('#vProdTree_' + pid);
        vids.forEach(vid => {
            if (makeChecked) vSet.add(vid); else vSet.delete(vid);
            $tree.find('.jsVariantPick[data-vid="' + vid + '"]').prop('checked', makeChecked);
        });
        if (gid > 0){ if (makeChecked) gSet.add(gid); else gSet.delete(gid); }

        const anyChecked = $tree.find('.jsVariantPick:checked').length > 0;
        const $parent = $('#productPickerList .jsProductPick[value="' + pid + '"]');
        if (anyChecked){ pSet.add(pid); $parent.prop('checked', true); }
        else { pSet.delete(pid); $parent.prop('checked', false); }

        setSelectedProductIds([...pSet]);
        setSelectedVariantGroupIds([...gSet]);
        setSelectedVariantIds([...vSet]);
        updatePickedCount();
    });

    // Nút chọn tất cả sản phẩm (đang hiển thị theo filter).
    $('#btnPickAll').on('click', function(){
        const pSet = new Set(getSelectedProductIds());
        const gSet = new Set(getSelectedVariantGroupIds());
        const vSet = new Set(getSelectedVariantIds());

        $('#productPickerList .jsProductPick').each(function(){ pSet.add(parseInt($(this).val(), 10)); });
        $('#productPickerList .jsVariantPick').each(function(){ vSet.add(parseInt($(this).data('vid'), 10)); });
        $('#productPickerList .jsGroupAll').each(function(){
            const gid = parseInt($(this).data('gid'), 10); if (gid > 0) gSet.add(gid);
        });

        setSelectedProductIds([...pSet]);
        setSelectedVariantGroupIds([...gSet]);
        setSelectedVariantIds([...vSet]);
        renderProductPicker();
    });
   // Nút xóa tất cả sản phẩm đã chọn
    $('#btnPickClear').on('click', function(){
        setSelectedProductIds([]);
        setSelectedVariantGroupIds([]);
        setSelectedVariantIds([]);
        renderProductPicker();
    });

    // Lọc sản phẩm theo danh mục (dropdown trong toolbar picker).
    $(document).on('change', '#productCategoryFilter', function(){ renderProductPicker(); });

    // Danh mục cũng tương tự như sản phẩm nhưng có thêm bước lọc sản phẩm theo danh mục đã chọn   
    $('#categoryPickerList').on('change', '.jsCategoryPick', function(){
        const current = new Set(getSelectedCategoryIds());
        const id = parseInt($(this).val(), 10);
        if (!Number.isFinite(id) || id <= 0) return;
        if ($(this).is(':checked')) current.add(id);
        else current.delete(id);
        setSelectedCategoryIds([...current]);
        renderProductPicker();
    });
    // Nút chọn tất cả danh mục
    $('#btnCategoryPickAll').on('click', function(){
        const current = new Set(getSelectedCategoryIds());
        $('#categoryPickerList .jsCategoryPick').each(function(){
            const id = parseInt($(this).val(), 10);
            if (Number.isFinite(id) && id > 0) {
                current.add(id);
                $(this).prop('checked', true);
            }
        });
        setSelectedCategoryIds([...current]);
        renderProductPicker();
    });

    $('#btnCategoryPickClear').on('click', function(){
        $('#categoryPickerList .jsCategoryPick').prop('checked', false);
        setSelectedCategoryIds([]);
        renderProductPicker();
    });

    // Lọc theo keyword (debounced)
    let pickerSearchTimer = null;
    $(document).on('input', '#productFilterInput, #categoryFilterInput, #userFilterInput', function(){
        const id = $(this).attr('id');
        clearTimeout(pickerSearchTimer);
        pickerSearchTimer = setTimeout(() => {
            if (id === 'productFilterInput') renderProductPicker();
            else if (id === 'categoryFilterInput') renderCategoryPicker();
            else if (id === 'userFilterInput') renderUserPicker();
        }, 300);
    });

    $('#btnGenerateCode').on('click', function(){
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let code = '';
        for (let i = 0; i < 8; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('#voucherForm [name="code"]').val(code);
    });

    // Giới hạn giá trị theo đơn vị phần trăm / tiền cho field value
    $('#voucherForm [name="value_unit"]').on('change', function(){
        const unit = String($(this).val() || 'fixed');
        if (unit === 'percent') {
            $('#voucherForm [name="value"]').attr('min', 0).attr('max', 100).attr('step', 1);
        } else {
            $('#voucherForm [name="value"]').attr('min', 0).removeAttr('max').attr('step', 1);
        }
    });

    $(document).on('click', '#voucherTable .jsDel', function(){
        const row = getRowDataFromTrigger($(this));
        if (!row) return;
        if (!confirm('Xóa mã ' + (row.code || '') + '?')) return;
        $.post(API, { action: 'delete', id: row.id }, res => {
            if (res && res.ok) location.reload();
            else notify((res && res.msg) || 'Không xóa được', 'error');
        });
    });

    $(document).on('change', '#voucherTable .jsToggle', function(){
        const row = getRowDataFromTrigger($(this));
        if (!row) return;
        const is_active = $(this).is(':checked') ? 1 : 0;
        $.post(API, { action: 'toggle', id: row.id, is_active }, res => {
            if (!res || !res.ok) notify((res && res.msg) || 'Không cập nhật được', 'error');
        });
    });

    // init DataTables cho danh sách voucher (đợi DOM ready để chắc chắn libs đã load)
    // Đổ danh sách danh mục vào dropdown lọc của product picker (1 lần khi load).
    function fillProductCategoryFilter(){
        const $sel = $('#productCategoryFilter');
        if (!$sel.length) return;
        const opts = ['<option value="0">Tất cả danh mục</option>'];
        (CATEGORY_OPTIONS || []).forEach(c => {
            const id = Number(c.id || 0);
            if (id > 0) opts.push('<option value="' + id + '">' + escHtml(String(c.name || ('#'+id))) + '</option>');
        });
        $sel.html(opts.join(''));
    }

    $(function(){
        fillProductCategoryFilter();
        // Filter theo tab voucher (dựa trên data-voucher-template ở <tr>)
        if ($.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.search) {
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
                try {
                    if (!$voucherTable.length) return true;
                    if (settings && settings.nTable !== $voucherTable[0]) return true;

                    const api = new $.fn.dataTable.Api(settings);
                    const node = api.row(dataIndex).node();
                    if (!node) return true;
                    const $row = $(node);
                    const row = $row.data('row');

                    // Filter theo tab
                    if (currentVoucherTab && currentVoucherTab !== 'all') {
                        const tpl = String($row.data('voucher-template') || 'order_discount');
                        if (tpl !== currentVoucherTab) return false;
                    }

                    // Filter theo search
                    const searchVal = String(searchInput && searchInput.length ? (searchInput.val() || '') : '').trim().toLowerCase();
                    if (searchVal && row) {
                        const haystack = [
                            String(row.code || ''),
                            String(row.promo_note || ''),
                            String(row.detail_text || '')
                        ].join(' ').toLowerCase();
                        if (!haystack.includes(searchVal)) return false;
                    }

                    // Filter theo status
                    const statusVal = String(statusFilter && statusFilter.length ? (statusFilter.val() || 'all') : 'all');
                    if (statusVal !== 'all' && row) {
                        const isActive = String(row.is_active) === '1';
                        if (statusVal === '1' && !isActive) return false;
                        if (statusVal === '0' && isActive) return false;
                    }

                    return true;
                } catch (e) {
                    return true;
                }
            });
        }

        ensureVoucherDataTable();
    });

    $('#btnSave').click(function(){
        const templateKey = String($('#voucherTemplateKey').val() || '').trim();
        if (!templateKey || !String($('#discountTargetInput').val() || '').trim()) {
            notify('Vui lòng chọn template', 'warning');
            return;
        }
        const applyScope = String($('input[name="apply_scope"]:checked').val() || 'all');
        const applyIds = String($('#applyProductIds').val() || '').trim();
        const applyVgIds = String($('#applyVariantGroupIds').val() || '').trim();
        const applyVIds = String($('#applyVariantIds').val() || '').trim();

        if (applyScope === 'products' && templateKey !== 'category_discount' && applyIds === '' && applyVgIds === '' && applyVIds === '') {
            notify('Vui lòng chọn ít nhất 1 sản phẩm / nhóm / biến thể', 'warning');
            return;
        }


        const applyCatIds = String($('#applyCategoryIds').val() || '').trim();
        // Với template chỉ áp dụng cho ngành hàng thì có thể không cần chọn sản phẩm cụ thể nhưng phải chọn danh mục áp dụng
        if (templateKey === 'only_category_discount' && applyScope === 'products' && applyCatIds === '') {
            notify('Vui lòng chọn danh mục (ngành hàng) áp dụng', 'warning');
            return;
        }
        // Với template giảm giá theo phương thức thanh toán thì bắt buộc phải chọn ít nhất 1 phương thức thanh toán áp dụng
        if (templateKey === 'payment_discount') {
            const checkedPayments = $('#voucherForm input[name="payment_methods[]"]:checked').length;
            if (checkedPayments === 0) {
                notify('Vui lòng chọn ít nhất 1 phương thức thanh toán áp dụng cho mẫu voucher này', 'warning');
                return;
            }
        }
        // Với template giảm giá theo đơn vị vận chuyển thì bắt buộc phải chọn ít nhất 1 đơn vị vận chuyển áp dụng
        if (templateKey === 'shipping_discount') {
            const checkedShipping = $('#voucherForm input[name="shipping_methods[]"]:checked').length;
            if (checkedShipping === 0) {
                notify('Vui lòng chọn ít nhất 1 đơn vị vận chuyển áp dụng cho mẫu voucher này', 'warning');
                return;
            }
        }
        const userScope = String($('input[name="apply_user_scope"]:checked').val() || 'all');
        if (userScope !== 'specific') {
            $('#applyUserIds').val('');
        }
        $('#excludeProductIds').val('');
        // Bảo đảm mọi field bước 2 đang ENABLED trước khi gom FormData.
        // (Field bị disabled sẽ KHÔNG được FormData gửi đi -> mất apply_scope/end_at/payment_methods khi lưu)
        setStep3Enabled(true);

        // Đọc TRỰC TIẾP từ DOM (không phụ thuộc FormData) để tránh rớt giá trị khi field từng bị disabled
        const startRaw = String($('#voucherForm [name="start_at"]').val() || '').trim();
        const endRaw   = String($('#voucherForm [name="end_at"]').val() || '').trim();

        const fd = new FormData(document.getElementById('voucherForm'));
        fd.set('action', 'save');
        fd.set('is_active', $('#isActive').is(':checked') ? '1' : '0');
        // set() luôn ghi đè, kể cả khi FormData không có sẵn key (field disabled trước đó)
        fd.set('start_at', fromDtLocal(startRaw));
        fd.set('end_at', fromDtLocal(endRaw));

        $.ajax({
            url: API,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: (res) => {
                if (res && res.ok) location.reload();
                else notify((res && res.msg) || 'Không lưu được', 'error');
            },
            error: () => notify('Lỗi kết nối server', 'error')
        });
    });
})();
</script>


