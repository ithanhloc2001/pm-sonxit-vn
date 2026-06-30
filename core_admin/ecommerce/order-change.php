<?php
require_once __DIR__ . '/../_admin_guard.php';

// Lấy cấu hình GHN để xác định môi trường (test hay prod) phục vụ link tra cứu vận đơn
$ghnConfig = app_get_default_ghn_env_config($ithanhloc);
$ghnEnv = $ghnConfig['env'] ?? 'test';
?>

<?php
// Kiểm tra tham số order_id và xác thực đơn hàng tồn tại
$orderId = trim((string)($_GET['order_id'] ?? ''));
$orderExists = false;
// Chỉ thực hiện truy vấn nếu orderId không rỗng để tránh truy vấn không cần thiết
if ($orderId !== '') {
    $stmt = $ithanhloc->prepare('SELECT 1 FROM ecommerce_order WHERE order_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $stmt->store_result();
        $orderExists = $stmt->num_rows > 0;
        $stmt->close();
    }
}
?>
<?php if ($orderId === ''): ?>
    <div class="text-center py-5">
        <div class="mb-3"><i class="bi bi-search fs-1 text-muted"></i></div>
        <h4 class="fw-bold">Thiếu mã đơn hàng</h4>
        <p class="text-muted">Vui lòng quay lại danh sách đơn hàng.</p>
        <a href="<?= h($baseUrl) ?>/admin/order" class="btn btn-primary px-4 rounded-pill">Quay lại danh sách</a>
    </div>
<?php elseif (!$orderExists): ?>
    <div class="text-center py-5">
        <div class="mb-3"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i></div>
        <h4 class="fw-bold">Đơn hàng không tồn tại</h4>
        <p class="text-muted">Đơn hàng với mã <strong><?= h($orderId) ?></strong> không tìm thấy trong hệ thống.</p>
        <a href="<?= h($baseUrl) ?>/admin/order" class="btn btn-primary px-4 rounded-pill">Quay lại danh sách</a>
    </div>
<?php else: ?>
<div class="container-fluid py-4 order-change-container">
    <!-- ── PAGE HEADER ── -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="oc-page-title mb-0">Đơn hàng <span id="metaOrder" class="text-primary">#...</span></h2>
            <p class="text-muted small mb-0 mt-1"><i class="bi bi-calendar3 me-1"></i><span id="metaCreated">--</span></p>
        </div>
    </div>
    <!-- ── ALERTS ── -->
    <div class="alert alert-danger d-flex align-items-center gap-2 rounded-3 border-0 d-none mb-3" id="lockAlert">
        <i class="bi bi-lock-fill"></i>
        <div><strong>Đơn hàng đã bị hủy.</strong> Không thể chỉnh sửa thông tin hoặc thay đổi trạng thái.</div>
    </div>
    <div class="alert d-flex align-items-start gap-2 rounded-3 d-none mb-3" id="reasonBanner">
        <i class="bi fs-5" id="reasonBannerIcon"></i>
        <div class="flex-grow-1">
            <strong id="reasonBannerTitle"></strong>
            <div id="reasonBannerBody" class="mt-1 small" style="white-space:pre-line;"></div>
        </div>
    </div>

    <!-- ── PROGRESS STEPPER ── -->
    <div class="card mb-4" id="orderProgressBar">
        <div class="card-body">
            <div class="stepper-wrap">
                <div id="orderProgressLine"></div>
                <div class="step-item" data-step="pending">
                    <div class="step-icon"><i class="bi bi-receipt"></i></div>
                    <div class="step-label">Chờ xác nhận</div>
                </div>
                <div class="step-item" data-step="processing">
                    <div class="step-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="step-label">Đang chuẩn bị</div>
                </div>
                <div class="step-item" data-step="shipping">
                    <div class="step-icon"><i class="bi bi-truck"></i></div>
                    <div class="step-label">Đang giao hàng</div>
                </div>
                <div class="step-item" data-step="delivered">
                    <div class="step-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="step-label">Đã giao hàng</div>
                </div>
                <div class="step-item" data-step="completed">
                    <div class="step-icon"><i class="bi bi-star"></i></div>
                    <div class="step-label">Hoàn thành</div>
                </div>
            </div>
        </div>
    </div>

    <form id="orderEditForm">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="order_id" id="e_order_id">
        <input type="hidden" id="e_products_json" value="[]">
        <textarea id="e_product" class="d-none"></textarea>

        <div class="row g-4">
            <!-- ════════════════ MAIN COL ════════════════ -->
            <div class="col-lg-8">

                <!-- Card: Sản phẩm -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Sản phẩm (<span id="detailItemsCount">0 sản phẩm</span>)</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btnEditItems" style="border-radius:7px;font-size:0.75rem;padding:2px 9px;">
                            <i class="bi bi-pencil me-1"></i>Sửa sản phẩm
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="productList">
                            <!-- Items populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Card: Giao hàng (Preview + Collapsible Address Form + Carrier Details) -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-truck me-1 text-primary"></i>Giao hàng</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btnEditAddress" style="border-radius:7px;font-size:0.75rem;padding:2px 9px;">
                            <i class="bi bi-pencil me-1"></i>Đổi địa chỉ
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <!-- Preview Table matching view-order style -->
                        <table class="table table-borderless mb-0" style="font-size:0.82rem;">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-3 pe-2 py-2 align-top" style="width:110px;white-space:nowrap;">Người nhận</td>
                                    <td class="fw-medium py-2 pe-3">
                                        <span id="previewRecipientName" class="fw-bold">--</span> · <span id="previewRecipientPhone" class="fw-normal text-muted">--</span>
                                        <div id="previewRecipientAddress" class="text-muted mt-1">--</div>
                                    </td>
                                </tr>
                                <tr class="border-top">
                                    <td class="text-muted ps-3 pe-2 py-2 align-middle" style="white-space:nowrap;">Vận chuyển</td>
                                    <td class="fw-medium py-2 pe-3" id="previewShippingMethod">--</td>
                                </tr>
                                <tr class="border-top d-none" id="previewEtaWrapper">
                                    <td class="text-muted ps-3 pe-2 py-2 align-middle" style="white-space:nowrap;">Dự kiến</td>
                                    <td class="fw-medium py-2 pe-3 text-success" id="previewEta">--</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Hidden input for address, updated dynamically after loading details -->
                        <input type="hidden" name="address" id="e_address">
                        
                        <!-- GHN shipping live status sub-section (Always visible) -->
                        <div class="p-3 border-top">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
                                <h6 class="fw-bold text-secondary small mb-0 text-uppercase" style="letter-spacing: 0.5px;" id="shippingCardTitle"><i class="bi bi-truck-flatbed me-1"></i>Vận chuyển</h6>
                                <div class="d-flex gap-2 flex-wrap" id="ghnActionBar">
                                    <a href="#" class="btn btn-sm btn-primary rounded-pill px-2 px-sm-3" id="btnGhnCreate" target="_blank" style="display:none;"><i class="bi bi-plus-circle"></i><span class="d-none d-sm-inline ms-1">Tạo GHN</span></a>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 px-sm-3" id="btnGhnSync" style="display:none;"><i class="bi bi-arrow-repeat"></i><span class="d-none d-sm-inline ms-1">Đồng bộ</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 px-sm-3" id="btnGhnCancel" style="display:none;"><i class="bi bi-x-circle"></i><span class="d-none d-sm-inline ms-1">Huỷ vận đơn</span></button>
                                </div>
                            </div>
                            <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-3 d-none" id="shipFeeWarn" style="font-size:0.82rem;">
                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                                <span id="shipFeeWarnText"></span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="oc-info-box">
                                        <div class="oc-label mb-1">Mã vận đơn</div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span id="ghnTrackingPreview" class="fw-bold text-primary">--</span>
                                            <button type="button" class="btn btn-sm btn-link p-0 text-secondary" id="btnGhnCopy" style="display:none;" title="Sao chép"><i class="bi bi-clipboard"></i></button>
                                            <a href="#" id="btnGhnTrack" target="_blank" class="btn btn-sm btn-link p-0 text-secondary" style="display:none;" title="Tra cứu GHN"><i class="bi bi-box-arrow-up-right"></i></a>
                                        </div>
                                        <input type="hidden" id="e_shipping_tracking">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="oc-info-box">
                                        <div class="oc-label mb-1">Trạng thái giao hàng</div>
                                        <div id="ghnStatusPreview" class="fw-semibold text-success">--</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="oc-label">Dịch vụ vận chuyển</label>
                                    <input class="form-control form-control-sm bg-light" id="e_shipping_service" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="oc-label">Dự kiến giao</label>
                                    <input class="form-control form-control-sm bg-light" id="e_shipping_eta" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Thông tin đơn hàng (Merged Overview + Billing Totals + Invoice) -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-2"></i>Thông tin đơn hàng</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btnEditCustomer" style="border-radius:7px;font-size:0.75rem;padding:2px 9px;">
                            <i class="bi bi-pencil me-1"></i>Sửa thông tin KH
                        </button>
                    </div>
                    <div class="card-body pb-3_">
                        <!-- Overview rows matching view-order design -->
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <span class="text-muted small">Mã đơn hàng</span>
                            <span class="fw-medium small">
                                <span id="previewOrderId">--</span>
                                <button type="button" class="copy-btn ms-2" id="btnCopyOrderId">Sao chép</button>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <span class="text-muted small">Thời gian đặt hàng</span>
                            <span class="fw-medium small" id="previewOrderCreated">--</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <span class="text-muted small">Phương thức thanh toán</span>
                            <span class="fw-medium small" id="previewPaymentMethod">--</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <span class="text-muted small">Trạng thái thanh toán</span>
                            <span class="fw-bold small text-success" id="previewPaymentStatus">--</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                            <span class="text-muted small">Hóa đơn GTGT</span>
                            <span class="fw-medium small" id="previewInvoiceState">Không yêu cầu</span>
                        </div>

                        <!-- Collapsible Customer & Payment Editing Panel -->
                        <div id="customerEditPanel" class="p-3 mt-3 rounded bg-light border" style="display:none;">
                            <h6 class="fw-bold mb-3 text-secondary small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-person-badge-fill me-1"></i>Thông tin khách hàng</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="oc-label">Tên khách hàng</label>
                                    <input class="form-control form-control-sm" name="user_name" id="e_user_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="oc-label">Số điện thoại</label>
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" name="phone" id="e_phone">
                                        <a class="btn btn-outline-success" id="btnCallPhone" href="#" target="_blank" title="Gọi điện"><i class="bi bi-telephone-fill"></i></a>
                                        <a class="btn btn-outline-primary" id="btnZaloPhone" href="#" target="_blank" title="Zalo"><i class="bi bi-chat-dots-fill"></i></a>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="oc-label">Email</label>
                                    <input class="form-control form-control-sm" name="email" id="e_email">
                                </div>
                                <div class="col-md-6">
                                    <label class="oc-label">Ghi chú từ khách</label>
                                    <input class="form-control form-control-sm bg-light" name="note" id="e_note" placeholder="Không có ghi chú" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="oc-label">Phương thức thanh toán (Metadata)</label>
                                    <div id="metaPay" class="form-control form-control-sm bg-light fw-semibold text-dark border" style="min-height:31px;">--</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="oc-label">Cổng thanh toán</label>
                                    <input class="form-control form-control-sm bg-light" id="e_payment_gateway" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="oc-label">Mã giao dịch</label>
                                    <input class="form-control form-control-sm bg-light" id="e_payment_ref" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="oc-label">Mã ngân hàng</label>
                                    <input class="form-control form-control-sm bg-light" id="e_bank_code" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Chi tiết thanh toán -->
                        <div class="mt-4 pt-3 border-top border-light">
                            <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2"></i>Chi tiết thanh toán</h6>
                            <div class="summary-row">
                                <span>Tổng tiền hàng</span>
                                <span id="e_subtotal_text" class="fw-medium">0 đ</span>
                                <input type="hidden" id="e_subtotal">
                            </div>
                            <div class="summary-row">
                                <span>Phí vận chuyển</span>
                                <span id="e_shipping_fee_text" class="fw-medium">0 đ</span>
                                <input type="hidden" id="e_shipping_fee">
                            </div>
                            <div class="summary-total">
                                <span class="fw-bold text-dark">Tổng thanh toán</span>
                                <span id="e_total_amount_text" class="fw-bold text-danger">0 đ</span>
                                <input type="hidden" id="e_total_amount">
                            </div>
                        </div>

                        <!-- Invoice Section (GTGT) -->
                        <div id="e_invoice_section" class="mt-4 pt-3 border-top border-light" style="display:none;">
                            <h6 class="fw-bold mb-3 text-secondary small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-file-earmark-text-fill me-1"></i>Hóa đơn GTGT</h6>
                            <div id="e_invoice_box">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="oc-label">Loại hoá đơn</label>
                                        <input class="form-control form-control-sm bg-light" id="e_invoice_type" readonly>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="oc-label">Đơn vị / Công ty</label>
                                        <input class="form-control form-control-sm bg-light" id="e_invoice_company_name" readonly>
                                        <div class="small text-muted mt-1" id="e_invoice_buyer_name"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="oc-label">Mã số thuế</label>
                                        <input class="form-control form-control-sm bg-light" id="e_invoice_tax_code" readonly>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="oc-label">Địa chỉ &amp; Email</label>
                                        <div class="small fw-medium" id="e_invoice_address"></div>
                                        <div class="small text-primary font-monospace" id="e_invoice_email"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div><!-- /col-lg-8 -->

            <!-- ════════════════ SIDEBAR ════════════════ -->
            <div class="col-lg-4">
                <div class="oc-sidebar-sticky">

                    <!-- Card: Trạng thái & Hành trình -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-ol me-2"></i>Trạng thái</h6>
                            <div id="metaStatus" class="ms-auto">--</div>
                        </div>
                        
                        <ul class="nav nav-tabs nav-fill oc-tabs" id="orderTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-panel" type="button" role="tab">
                                    <i class="bi bi-map fs-5"></i><span>Hành trình</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="review-tab" data-bs-toggle="tab" data-bs-target="#review-panel" type="button" role="tab">
                                    <i class="bi bi-star fs-5"></i><span>Đánh giá</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-panel" type="button" role="tab">
                                    <i class="bi bi-clock-history fs-5"></i><span>Lịch sử</span>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content p-3">
                            <div class="tab-pane fade show active" id="timeline-panel" role="tabpanel">
                                <div id="shippingTimeline" class="status-timeline"></div>
                            </div>
                            <div class="tab-pane fade" id="review-panel" role="tabpanel">
                                <div id="reviewInfo" class="mb-3"><div class="text-muted small text-center py-3">Chưa có đánh giá.</div></div>
                                <div class="pt-3 border-top">
                                    <label class="oc-label">Phản hồi của Admin</label>
                                    <textarea class="form-control form-control-sm" id="reviewReply" rows="3" placeholder="Nhập phản hồi..."></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 w-100 rounded-pill" id="btnSaveReply">Lưu phản hồi</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="history-panel" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <span class="small text-muted">Số thao tác</span>
                                    <span class="badge bg-light text-dark border" id="activityLogCount">0</span>
                                </div>
                                <div id="activityLog" style="max-height:360px;overflow-y:auto;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Hoàn tiền (ẩn/hiện theo JS) -->
                    <div class="card border border-warning" id="refundCard" style="display:none;">
                        <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-warning-emphasis"><i class="bi bi-cash-coin me-2"></i>Cần hoàn tiền</h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">Đơn đã thanh toán nhưng bị huỷ/trả. Chọn cách hoàn tiền:</p>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-sm rounded-pill" id="btnRefundAuto" style="display:none;">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Tự động qua <span id="btnRefundAutoGateway">—</span>
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm rounded-pill" id="btnRefundManual">
                                    <i class="bi bi-check2-square me-1"></i>Đã hoàn tay
                                </button>
                            </div>
                            <div class="small text-muted mt-2" id="refundHint">Hỗ trợ hoàn tự động: MoMo, ZaloPay.</div>
                        </div>
                    </div>

                    <!-- Card: Ghi chú nội bộ -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Ghi chú nội bộ</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btnSaveAdminNote" style="border-radius:7px;font-size:0.75rem;padding:2px 9px;">
                                <i class="bi bi-save me-1"></i>Lưu
                            </button>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control form-control-sm" id="e_admin_note" rows="3" placeholder="Ghi chú nội bộ (khách không thấy)..."></textarea>
                        </div>
                    </div>

                </div><!-- /oc-sidebar-sticky -->
            </div><!-- /col-lg-4 -->
        </div>

        <!-- ── BOTTOM ACTIONS ── -->
        <div class="order-header-action mt-4">
            <button type="button" class="btn btn-outline-secondary" id="btnPrintOrder">
                <i class="bi bi-printer me-2"></i>In
            </button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderStatusModal">
                <i class="bi bi-arrow-left-right me-2"></i> Xử lý đơn
            </button>
            <button type="button" class="btn btn-primary" id="btnSave">
                <i class="bi bi-check-lg me-2"></i>Lưu chỉnh sửa
            </button>
        </div>
    </form>
</div>
<!-- Modal chỉnh sửa sản phẩm trong đơn -->
<div class="modal fade" id="editItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Chỉnh sửa sản phẩm trong đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Cột trái: danh sách sản phẩm hiện tại -->
                    <div class="col-lg-6">
                        <h6 class="fw-bold text-secondary small text-uppercase mb-2" style="letter-spacing:.5px;"><i class="bi bi-list-check me-1"></i>Sản phẩm trong đơn</h6>
                        <div id="eiItemList" class="border rounded-3" style="min-height:200px;max-height:480px;overflow-y:auto;"></div>
                        <div class="mt-2 pt-2 border-top">
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">Tổng tiền hàng</span>
                                <span class="fw-bold text-danger" id="eiSubtotalPreview">0 đ</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm mt-3 w-100 rounded-pill fw-semibold" id="btnAddGift">
                            <i class="bi bi-gift me-1"></i>Thêm sản phẩm tặng kèm
                        </button>
                    </div>
                    <!-- Cột phải: tìm & thêm sản phẩm -->
                    <div class="col-lg-6">
                        <h6 class="fw-bold text-secondary small text-uppercase mb-2" style="letter-spacing:.5px;"><i class="bi bi-search me-1"></i>Tìm & thêm sản phẩm</h6>

                        <!-- Lối tắt: tìm theo tên -->
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="eiSearchInput" class="form-control" placeholder="Tìm nhanh theo tên sản phẩm..." autocomplete="off">
                            <span class="input-group-text bg-white" id="eiSearchSpinner" style="display:none;"><span class="spinner-border spinner-border-sm text-primary"></span></span>
                        </div>

                        <!-- Hoặc duyệt: Danh mục -> Sản phẩm -> Nhóm -> Phân loại -->
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <select id="eiCategorySel" class="form-select form-select-sm" style="max-width:240px;">
                                <option value="">-- Chọn danh mục --</option>
                            </select>
                            <span class="text-muted small">hoặc tìm nhanh ở trên</span>
                        </div>

                        <!-- Breadcrumb điều hướng -->
                        <div id="eiBrowseCrumb" class="small mb-2 d-none">
                            <a href="#" class="text-decoration-none" data-ei-back="category"><i class="bi bi-grid me-1"></i>Danh mục</a>
                            <span id="eiCrumbProduct" class="d-none"> <i class="bi bi-chevron-right small"></i> <a href="#" class="text-decoration-none" data-ei-back="product"></a></span>
                            <span id="eiCrumbGroup" class="d-none"> <i class="bi bi-chevron-right small"></i> <span class="fw-semibold"></span></span>
                        </div>

                        <div id="eiSearchResults" class="border rounded-3" style="min-height:100px;max-height:420px;overflow-y:auto;">
                            <div class="text-muted small text-center py-4"><i class="bi bi-search me-1"></i>Chọn danh mục hoặc gõ tên để tìm sản phẩm</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary px-5 rounded-pill fw-semibold" id="btnConfirmItems">
                    <i class="bi bi-check-lg me-1"></i>Xác nhận & Cập nhật tổng
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal duyệt đơn / cập nhật trạng thái -->
<div class="modal fade" id="orderStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Cập nhật trạng thái đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="mb-4 text-center p-3 bg-light rounded-4 border border-dashed">
                    <label class="form-label d-block text-muted small mb-1">TRẠNG THÁI HIỆN TẠI</label>
                    <div id="currentStatusDisplay" class="h5 fw-bold mb-0 text-primary">--</div>
                    <select class="form-select d-none" name="status" id="e_status">
                        <option value="pending">Chờ xác nhận</option>
                        <option value="processing">Đang chuẩn bị hàng</option>
                        <option value="shipping">Đang giao hàng</option>
                        <option value="delivered">Đã giao thành công</option>
                        <option value="cancel_requested">Đang chờ duyệt hủy</option>
                        <option value="return_requested">Đang chờ duyệt trả hàng</option>
                        <option value="returned">Đã hoàn trả hàng</option>
                        <option value="refunded">Đã hoàn tiền</option>
                        <option value="canceled">Đơn đã hủy</option>
                    </select>
                </div>

                <div id="statusActionGroup">
                    <!-- Nhánh Bình Thường -->
                    <div class="status-branch p-3 rounded-4 mb-3 border border-primary-subtle bg-primary-subtle bg-opacity-10" data-branch="normal">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px; height:24px;">
                                <i class="bi bi-check2 small"></i>
                            </div>
                            <h6 class="text-primary fw-bold mb-0 small">TIẾN TRÌNH XỬ LÝ</h6>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="processing" id="btnToProcessing">
                                <span><i class="bi bi-box-seam me-2"></i>Xác nhận & Chuẩn bị hàng</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                            <button type="button" class="btn btn-primary status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="shipping" id="btnToShipping">
                                <span><i class="bi bi-truck me-2"></i>Bàn giao vận chuyển</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                            <button type="button" class="btn btn-success status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="delivered" id="btnToDelivered">
                                <span><i class="bi bi-check-all me-2"></i>Hoàn tất giao hàng</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nhánh Rủi Ro -->
                    <div class="status-branch p-3 rounded-4 mb-3 border border-warning-subtle bg-warning-subtle bg-opacity-10" data-branch="risk">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px; height:24px;">
                                <i class="bi bi-exclamation-triangle small"></i>
                            </div>
                            <h6 class="text-warning-emphasis fw-bold mb-0 small">KHIẾU NẠI / TRẢ HÀNG</h6>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-warning status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="return_requested" id="btnToReturnReq">
                                <span><i class="bi bi-arrow-return-left me-2"></i>Khách yêu cầu trả hàng</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="returned" id="btnApproveReturn">
                                <span><i class="bi bi-archive me-2"></i>Đã nhận lại hàng hoàn</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                            <button type="button" class="btn btn-warning status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="refunded" id="btnToRefunded">
                                <span><i class="bi bi-cash-coin me-2"></i>Đã hoàn tiền cho khách</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nhánh Huỷ -->
                    <div class="status-branch p-3 rounded-4 border border-danger-subtle bg-danger-subtle bg-opacity-10" data-branch="cancel">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-danger status-action py-2 text-start px-3 d-flex justify-content-between align-items-center" data-status="canceled" id="btnToCanceled">
                                <span><i class="bi bi-x-circle me-2"></i>Huỷ đơn hàng này</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nhánh Xét duyệt yêu cầu trả hàng từ khách -->
                    <div class="status-branch p-3 rounded-4 border border-warning bg-warning bg-opacity-10 mt-3" data-branch="return_review">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px; height:24px;">
                                <i class="bi bi-arrow-return-left small"></i>
                            </div>
                            <h6 class="text-warning-emphasis fw-bold mb-0 small">YÊU CẦU TRẢ HÀNG ĐANG CHỜ DUYỆT</h6>
                        </div>
                        <p class="small text-muted mb-3">Khách hàng đã gửi yêu cầu trả hàng kèm lý do, ảnh/video minh chứng và thông tin nhận hoàn tiền. Mở chi tiết để xem và xét duyệt.</p>
                        <div class="d-grid">
                            <button type="button" class="btn btn-warning py-2 text-start px-3 d-flex justify-content-between align-items-center" id="btnOpenReturnReview" data-bs-toggle="modal" data-bs-target="#returnReviewModal">
                                <span><i class="bi bi-eye me-2"></i>Xem chi tiết &amp; xét duyệt</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nhánh Duyệt yêu cầu huỷ từ khách -->
                    <div class="status-branch p-3 rounded-4 border border-danger bg-danger bg-opacity-10 mt-3" data-branch="cancel_review">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px; height:24px;">
                                <i class="bi bi-person-x small"></i>
                            </div>
                            <h6 class="text-danger fw-bold mb-0 small">KHÁCH YÊU CẦU HUỶ ĐƠN</h6>
                        </div>
                        <div id="cancelRequestReasonBox" class="mb-3 p-2 bg-white rounded-3 border text-muted small" style="min-height:36px;"></div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-danger py-2 text-start px-3 d-flex justify-content-between align-items-center" id="btnApproveCancelRequest">
                                <span><i class="bi bi-check-circle me-2"></i>Duyệt — Xác nhận huỷ đơn</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success py-2 text-start px-3 d-flex justify-content-between align-items-center" id="btnRejectCancelRequest">
                                <span><i class="bi bi-x-circle me-2"></i>Từ chối — Tiếp tục xử lý đơn</span>
                                <i class="bi bi-chevron-right small opacity-50"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lý do huỷ -->
                <div id="canceledReasonWrapper" class="mt-4 p-3 bg-danger-subtle rounded-4 border border-danger border-opacity-25" style="display:none;">
                    <label class="form-label fw-bold text-danger mb-2"><i class="bi bi-chat-dots me-1"></i>Lý do huỷ đơn <span class="text-muted small fw-normal">(Bắt buộc)</span></label>
                    <textarea class="form-control border-danger border-opacity-50" id="e_canceled_reason" rows="3" placeholder="Vui lòng nhập lý do huỷ đơn hàng..."></textarea>
                    <div class="d-grid mt-3">
                        <button type="button" class="btn btn-danger py-2" id="btnSaveStatusCancel">Xác nhận huỷ đơn</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary px-4 d-none" id="btnSaveStatus">Lưu trạng thái</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal xét duyệt yêu cầu trả hàng -->
<div class="modal fade" id="returnReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning bg-opacity-10 border-bottom-0">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-arrow-return-left text-warning me-2"></i>Xét duyệt yêu cầu trả hàng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div id="returnReviewLoading" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>Đang tải yêu cầu...
                </div>

                <div id="returnReviewEmpty" class="text-center py-5 text-muted d-none">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Chưa tìm thấy bản ghi yêu cầu trả hàng cho đơn này.
                </div>

                <div id="returnReviewBox" class="d-none">
                    <!-- Tóm tắt -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="text-muted small mb-1">LÝ DO TRẢ HÀNG</div>
                                <div class="fw-bold text-dark" id="rrReason">--</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="text-muted small mb-1">SỐ TIỀN HOÀN</div>
                                <div class="fw-bold text-danger fs-5" id="rrRefund">--</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="text-muted small mb-1">THỜI GIAN GỬI</div>
                                <div class="fw-bold text-dark" id="rrCreated">--</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tài khoản nhận hoàn -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">TÀI KHOẢN NHẬN HOÀN TIỀN</label>
                        <div class="p-3 bg-white rounded-3 border" id="rrBank">--</div>
                    </div>

                    <!-- Mô tả -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">MÔ TẢ VẤN ĐỀ</label>
                        <div class="p-3 bg-white rounded-3 border text-dark" id="rrDescription" style="white-space:pre-line; min-height:60px;">--</div>
                    </div>

                    <!-- Email liên hệ -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">EMAIL LIÊN HỆ</label>
                        <div class="p-2 px-3 bg-white rounded-3 border text-primary" id="rrContact">--</div>
                    </div>

                    <!-- Media -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">HÌNH ẢNH / VIDEO MINH CHỨNG</label>
                        <div id="rrMedia" class="d-flex flex-wrap gap-2 p-3 bg-light rounded-3 border"></div>
                    </div>

                    <hr>

                    <!-- Tiến trình xử lý trả hàng -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">TIẾN TRÌNH XỬ LÝ</label>
                        <div id="rrProgressSteps" class="d-flex flex-column gap-2"></div>
                    </div>

                    <!-- Ghi chú admin -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-pencil-square me-1"></i>Ghi chú của admin
                            <span class="text-muted small fw-normal">(tuỳ chọn — sẽ gửi cho khách)</span>
                        </label>
                        <textarea id="rrAdminNote" class="form-control" rows="3" placeholder="VD: Hàng còn nguyên seal, đồng ý hoàn 100%..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 d-flex justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Đóng</button>
                <div class="d-flex gap-2 flex-wrap" id="rrActionGroup">
                    <!-- Các nút hành động render động theo progress -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Đổi Địa Chỉ Nhận Hàng -->
<div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Đổi địa chỉ nhận hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted small mb-3">Chỉnh sửa thông tin địa chỉ giao hàng và thông tin người nhận của đơn hàng.</p>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small">Họ tên người nhận <span class="text-danger">*</span></label>
                        <input type="text" id="eaRecipientName" class="form-control" placeholder="Nguyễn Văn A" maxlength="120">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold small">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="tel" id="eaPhone" class="form-control" placeholder="0901234567" maxlength="20">
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label fw-semibold small">Tỉnh / Thành phố <span class="text-danger">*</span></label>
                        <select id="eaProvince" class="form-select">
                            <option value="">-- Chọn tỉnh/thành --</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label fw-semibold small">Quận / Huyện <span class="text-danger">*</span></label>
                        <select id="eaDistrict" class="form-select" disabled>
                            <option value="">-- Chọn quận/huyện --</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label fw-semibold small">Phường / Xã <span class="text-danger">*</span></label>
                        <select id="eaWard" class="form-select" disabled>
                            <option value="">-- Chọn phường/xã --</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Địa chỉ chi tiết (số nhà, tên đường) <span class="text-danger">*</span></label>
                        <input type="text" id="eaStreet" class="form-control" placeholder="Số 12, Đường Lê Lợi" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary px-4 rounded-pill fw-semibold" id="btnSaveAddress">
                    <span id="btnSaveAddressText">Lưu địa chỉ</span>
                    <span id="btnSaveAddressSpin" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // =========================================================================
    // 1. CONFIGURATION & CONSTANTS
    // =========================================================================
    const API_URL = '<?= h($baseUrl) ?>/core_admin/ecommerce/ajax/order.php';
    const API_GHN = '<?= h($baseUrl) ?>/core_admin/giaohangnhanh/ajax/api.php';
    const BASE_URL = '<?= h($baseUrl) ?>';
    const FALLBACK_IMG = <?= json_encode(to_abs_url(((string)($site_fallback_logo ?? '') !== '' ? (string)$site_fallback_logo : 'image/paintmore.svg'), (string)$baseUrl), JSON_UNESCAPED_UNICODE) ?>;
    const ORDER_ID = <?= json_encode($orderId) ?>;
    const GHN_ENV = <?= json_encode($ghnEnv) ?>;

    // =========================================================================
    // 2. STATE & CACHE VARIABLES
    // =========================================================================
    const orderItems = [];
    const productMetaCache = {};
    const regionCache = { p: [], d: {}, w: {} };

    // =========================================================================
    // 3. UTILITY & FORMATTING HELPERS
    // =========================================================================
    const esc = v => $('<div>').text(v ?? '').html();
    const parseMoney = v => Math.round(parseFloat(String(v || '').replace(/[^0-9.-]/g, '')) || 0);

    const fmtVnd = raw => {
        const num = typeof raw === 'number' ? raw : parseMoney(raw);
        return num ? new Intl.NumberFormat('vi-VN').format(Math.round(num / 1000) * 1000) + ' đ' : '0 đ';
    };

    function setCurrencyValue(id, raw) {
        const num = typeof raw === 'number' ? raw : parseMoney(raw);
        $(`#e_${id}`).val(num);
        $(`#e_${id}_text`).text(fmtVnd(num));
    }

    function fmtDateTime(raw) {
        const s = String(raw || '').trim();
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        return Number.isNaN(d.getTime()) ? s : d.toLocaleString('vi-VN');
    }

    const normalizeStatusKey = raw => {
        const key = String(raw || '').trim().toLowerCase();
        return ['pending', 'processing', 'shipping', 'delivered', 'cancel_requested', 'return_requested', 'returned', 'refunded', 'canceled'].includes(key) ? key : 'pending';
    };

    const statusLabelVi = key => {
        const map = {
            pending: 'Chờ xác nhận',
            processing: 'Đang chuẩn bị hàng',
            shipping: 'Đang giao hàng',
            delivered: 'Đã giao thành công',
            cancel_requested: 'Đang chờ duyệt hủy',
            return_requested: 'Đang chờ duyệt trả hàng',
            returned: 'Đã hoàn trả hàng',
            refunded: 'Đã hoàn tiền',
            canceled: 'Đơn đã hủy'
        };
        return map[key] || 'Chờ xử lý';
    };

    // =========================================================================
    // 4. PARSERS & NORMALIZERS
    // =========================================================================
    function parseProductsJson(raw) {
        try {
            const arr = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (!Array.isArray(arr)) return [];
            return arr.map(it => {
                if (!it) return null;
                const name = String(it.name || it.product || it.product_name || '').trim();
                if (!name) return null;
                const pid = Number(it.product_id || it.id || 0);
                const qty = Math.max(1, parseInt(it.qty ?? it.quantity ?? 1, 10) || 1);
                const price = Math.max(0, Number(it.price ?? it.unit_price ?? 0) || 0);
                // Ưu tiên cờ is_gift đã lưu; chỉ suy luận theo giá khi đơn cũ KHÔNG có cờ
                // (tránh biến dòng hàng mua giá 0đ thành quà ngoài ý muốn).
                const giftFlag = Object.prototype.hasOwnProperty.call(it, 'is_gift')
                    ? (it.is_gift ? 1 : 0)
                    : (price <= 0 ? 1 : 0);
                return {
                    id: pid,
                    product_id: pid,
                    variant_id: Number(it.variant_id || it.v_id || it.vid || 0),
                    name,
                    qty,
                    variant: String(it.variant || '').trim(),
                    price,
                    is_gift: giftFlag,
                    is_combo: it.is_combo ? 1 : 0,
                    is_preorder: (Number(it.is_preorder) === 1 || it.is_preorder === true) ? 1 : 0,
                    image_url: String(it.image_url || it.thumb || '').trim(),
                    variant_image_url: String(it.variant_image_url || it.variant_thumb || it.variant_image || '').trim()
                };
            }).filter(Boolean);
        } catch (e) {
            return [];
        }
    }

    function parseProductString(str) {
        return String(str || '').split(/\r\n|\r|\n|\||,/).map(s => s.trim()).filter(Boolean).map(p => {
            const m = p.match(/^(.*?)(?:\s*[xX]\s*(\d+))?$/);
            const name = m ? m[1].trim() : p;
            return name ? {
                id: 0,
                product_id: 0,
                name,
                qty: Math.max(1, parseInt(m?.[2] || 1, 10) || 1),
                variant: '',
                price: 0,
                is_gift: 0,
                is_combo: 0
            } : null;
        }).filter(Boolean);
    }

    function normalizeImgUrl(raw) {
        const url = String(raw || '').trim();
        if (!url) return FALLBACK_IMG;
        if (/^https?:\/\//i.test(url) || url.startsWith('//')) return url;
        return BASE_URL.replace(/\/$/, '') + '/' + url.replace(/^\.?\//, '');
    }

    // =========================================================================
    // 5. CORE RENDERERS
    // =========================================================================
    function renderProductList() {
        const $list = $('#productList');
        if (!orderItems.length) {
            $list.html('<div class="p-4 text-center text-muted small">Chưa chọn sản phẩm nào.</div>');
            syncLegacyFieldsFromItems();
            return;
        }

        const display = orderItems.slice().sort((a, b) => {
            const va = String(a.variant || '').trim().toLowerCase();
            const vb = String(b.variant || '').trim().toLowerCase();
            if (va !== vb) {
                if (!va) return 1;
                if (!vb) return -1;
                return va.localeCompare(vb, 'vi');
            }
            return String(a.name || '').trim().toLowerCase().localeCompare(String(b.name || '').trim().toLowerCase(), 'vi');
        });

        const rows = display.map(it => {
            const pid = Number(it.product_id || it.id || 0);
            const meta = pid > 0 ? (productMetaCache[pid] || {}) : {};
            const variant = String(it.variant || '').trim();
            // TỰ HỒI PHỤC variant_id nếu đơn cũ bị thiếu (để ảnh đúng + lưu lại không mất)
            if ((!Number(it.variant_id) || Number(it.variant_id) <= 0) && Array.isArray(meta.variants) && meta.variants.length) {
                let resolved = null;
                if (variant) resolved = meta.variants.find(v => String(v.variant_name || '').trim().toLowerCase() === variant.toLowerCase());
                if (!resolved && meta.variants.length === 1) resolved = meta.variants[0];
                if (!resolved && Number(it.price) > 0) resolved = meta.variants.find(v => Number(v.price || 0) === Number(it.price));
                if (resolved) {
                    it.variant_id = Number(resolved.variant_id || resolved.id || 0);
                    if (!it.variant && resolved.variant_name) it.variant = String(resolved.variant_name);
                    if (!it.variant_image_url && resolved.image_url) it.variant_image_url = String(resolved.image_url);
                }
            }
            const vid = Number(it.variant_id || 0);
            let variantImg = String(it.variant_image_url || '').trim();
            // Ưu tiên khớp ảnh theo variant_id (chính xác); fallback theo tên phân loại
            if (!variantImg && Array.isArray(meta.variants)) {
                let matched = null;
                if (vid > 0) matched = meta.variants.find(v => Number(v.variant_id || v.id || 0) === vid);
                if (!matched && variant) matched = meta.variants.find(v => String(v.variant_name || '').trim().toLowerCase() === variant.toLowerCase());
                if (matched?.image_url) variantImg = String(matched.image_url).trim();
            }
            const img = normalizeImgUrl(variantImg || it.image_url || meta.image_url || it.thumb || '');
            const name = String(it.name || meta.product_name || 'Sản phẩm').trim();
            const variantText = variant ? variant : 'Mặc định';
            const qty = Math.max(1, Number(it.qty) || 1);
            const isGift = !!it.is_gift || Number(it.price || 0) <= 0;
            const isPreorder = Number(it.is_preorder) === 1 || it.is_preorder === true;
            const unitPrice = Number(it.price || 0);
            const preorderBadge = isPreorder ? ' <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:0.6rem;"><i class="bi bi-clock-history me-1"></i>Đặt trước</span>' : '';
            // Gọn: chỉ đơn giá + số lượng (bỏ dòng thành tiền)
            const priceText = isGift
                ? '<span class="gift-badge">Quà tặng</span>'
                : (unitPrice > 0 ? fmtVnd(unitPrice) : '—');

            const thumbMarkup = img 
                ? `<img src="${esc(img)}" class="order-thumb" alt="thumb" onerror="this.style.display='none'">` 
                : `<div class="order-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-box"></i></div>`;

            const buildUrl = (itemId, itemName) => {
                const id = Number(itemId || 0);
                if (id <= 0) return '#';
                const slug = itemName.toString().toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[đĐ]/g, 'd')
                    .replace(/[^a-z0-9\s-]/g, '')
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                return BASE_URL.replace(/\/$/, '') + '/product/' + encodeURIComponent(slug) + '-' + id;
            };
            const productUrl = buildUrl(pid, name);

            return `
                <div class="order-item-row d-flex align-items-start gap-3">
                    <a href="${productUrl}" class="order-item-thumb-link">
                        ${thumbMarkup}
                    </a>
                    <div class="flex-grow-1 min-w-0">
                        <a href="${productUrl}" class="text-decoration-none order-item-name d-block" title="${esc(name)}">
                            ${esc(name)}${preorderBadge}
                        </a>
                        <span class="order-item-variant">Phân loại: ${esc(variantText)}</span>
                    </div>
                    <div class="text-end flex-shrink-0" style="min-width:74px;">
                        <div class="order-item-price">${priceText}</div>
                        <span class="order-item-qty">x${qty}</span>
                    </div>
                </div>
            `;
        }).join('');

        $list.html(rows);
        syncLegacyFieldsFromItems();
    }

    function renderReview(review) {
        const $info = $('#reviewInfo');
        if (!review) {
            $info.html('<div class="text-muted small italic">Chưa có đánh giá từ khách hàng.</div>');
            return;
        }
        const rating = Number(review.rating || 0);
        const stars = Array.from({ length: 5 }, (_, i) => `<i class="bi bi-star-fill ${i < rating ? 'text-warning' : 'text-light'}"></i>`).join('');
        $info.html(`
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fs-5">${stars}</div>
                <div class="text-muted small">${esc(review.created_at)}</div>
            </div>
            <div class="p-3 bg-light rounded-3 border small">
                ${esc(review.comment) || '<span class="text-muted italic">Khách hàng không để lại bình luận.</span>'}
            </div>
        `);
        $('#reviewReply').val(review.admin_reply || '');
    }

    let ghnOrderCode = '';
    function renderGhnPreview(order, shippingLive, ghnFee) {
        const live = shippingLive || {};
        const rawTracking = String(live.tracking || order?.shipping_tracking || '').trim();
        const tracking = rawTracking || 'Chưa có';
        const service = String(live.service || order?.shipping_method_label || order?.shipping_method || '').trim() || '—';
        const eta = String(live.eta || order?.shipping_eta || '').trim() || '—';
        const statusText = String(live.status_text || '').trim() || 'Đang cập nhật';

        $('#ghnTrackingPreview').text(tracking);
        $('#ghnStatusPreview').text(statusText);
        $('#e_shipping_tracking').val(rawTracking);
        $('#e_shipping_service').val(service);
        $('#e_shipping_eta').val(eta);

        // order_code GHN: ưu tiên field từ shipping_live, fallback mã vận đơn
        ghnOrderCode = String(live.order_code || live.tracking || rawTracking || '').trim();
        const carrierRaw = String(live.carrier || order?.shipping_carrier || '').trim().toLowerCase();
        const isGhnCarrier = carrierRaw === 'ghn' || carrierRaw === '';  // '' = chưa set, mặc định GHN
        const hasGhn = ghnOrderCode !== '' && isGhnCarrier;
        const hasTracking = ghnOrderCode !== '';
        const orderStatus = String(order?.status || '').toLowerCase();
        const isTerminal = ['canceled','cancelled','returned','refunded'].includes(orderStatus);

        // Cập nhật tiêu đề card theo carrier
        const carrierLabel = carrierRaw ? carrierRaw.toUpperCase() : 'GHN';
        $('#shippingCardTitle').html(`<i class="bi bi-truck me-2"></i>Thông tin vận chuyển${isGhnCarrier ? ' (GHN)' : ' (' + esc(carrierLabel) + ')'}`);

        // Có mã vận đơn → hiện Copy luôn; Đồng bộ + Track + Huỷ chỉ khi là GHN
        $('#btnGhnCopy').toggle(hasTracking);
        $('#btnGhnSync').toggle(hasGhn);
        $('#btnGhnTrack').toggle(hasGhn);
        $('#btnGhnCancel').toggle(hasGhn && !isTerminal);
        if (hasGhn) {
            const trackDomain = GHN_ENV === 'prod' ? 'https://donhang.ghn.vn' : 'https://tracking.ghn.dev';
            $('#btnGhnTrack').attr('href', trackDomain + '/?order_code=' + encodeURIComponent(ghnOrderCode));
        }

        // Chưa có vận đơn GHN + đơn chưa terminal → cho Tạo đơn GHN
        const canCreate = !hasTracking && !isTerminal && ['pending','processing'].includes(orderStatus);
        $('#btnGhnCreate').toggle(canCreate);
        if (canCreate) {
            $('#btnGhnCreate').attr('href', BASE_URL + '/admin/giaohangnhanh/create?system_order_id=' + encodeURIComponent(ORDER_ID));
        }

        // Cảnh báo lệch phí ship: phí khách trả (lúc checkout) vs phí GHN thực tế
        const $warn = $('#shipFeeWarn');
        const checkoutFee = Number(order?.shipping_fee || 0);
        const realFee = Number(ghnFee || 0);
        if (hasGhn && realFee > 0) {
            const diff = realFee - checkoutFee;
            const pct = checkoutFee > 0 ? Math.abs(diff) / checkoutFee * 100 : 100;
            // Cảnh báo khi lệch > 5% và > 2.000đ
            if (Math.abs(diff) > 2000 && pct > 5) {
                const fmt = n => new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ';
                const sign = diff > 0 ? 'cao hơn' : 'thấp hơn';
                $('#shipFeeWarnText').html(
                    `Phí ship GHN thực tế (<b>${fmt(realFee)}</b>) ${sign} phí thu của khách (<b>${fmt(checkoutFee)}</b>) ` +
                    `khoảng <b>${fmt(Math.abs(diff))}</b>. ` + (diff > 0 ? 'Shop đang chịu phần chênh.' : 'Khách trả dư.')
                );
                $warn.removeClass('d-none');
            } else {
                $warn.addClass('d-none');
            }
        } else {
            $warn.addClass('d-none');
        }

        // Update shipping previews.
        // Chỉ thêm tiền tố đơn vị (carrier) khi nó KHÁC tên dịch vụ — tránh lặp kiểu "NHANH - Nhanh"
        // (xảy ra khi carrier bị lưu trùng nội dung với service, vd cùng là "nhanh").
        const serviceNorm = service.trim().toLowerCase();
        const showCarrier = carrierRaw && carrierRaw !== serviceNorm;
        let shipMethodText = (showCarrier ? carrierRaw.toUpperCase() + ' - ' : '') + service;
        if (statusText && statusText !== 'Đang cập nhật') {
            shipMethodText += ' · ' + statusText;
        }
        $('#previewShippingMethod').text(shipMethodText);

        if (eta && eta !== '—') {
            $('#previewEtaWrapper').removeClass('d-none');
            $('#previewEta').text('Dự kiến: ' + eta);
        } else {
            $('#previewEtaWrapper').addClass('d-none');
        }
    }

    function renderReasonBanner(order, logs) {
        const $b = $('#reasonBanner');
        if (!$b.length) return;
        const status = String(order?.status || '').toLowerCase();
        const list = Array.isArray(logs) ? logs : [];

        // Tìm log yêu cầu huỷ/trả gần nhất (note chứa lý do)
        const findLast = ev => {
            for (let i = list.length - 1; i >= 0; i--) {
                if (String(list[i].event || '') === ev && String(list[i].note || '').trim()) return list[i];
            }
            return null;
        };

        let cfg = null;
        if (status === 'cancel_requested' || status === 'canceled' || status === 'cancelled') {
            const log = findLast('cancel_requested') || findLast('cancel_approved');
            const reason = log ? String(log.note || '') : (order?.canceled_reason || '');
            if (reason) cfg = { cls: 'alert-danger', icon: 'bi-x-octagon-fill', title: status === 'cancel_requested' ? 'Khách yêu cầu HUỶ đơn' : 'Lý do huỷ đơn', body: reason };
        } else if (status === 'return_requested' || status === 'returned') {
            const log = findLast('return_requested');
            const reason = log ? String(log.note || '') : '';
            if (reason) cfg = { cls: 'alert-warning', icon: 'bi-arrow-return-left', title: status === 'return_requested' ? 'Khách yêu cầu TRẢ hàng' : 'Lý do trả hàng', body: reason };
        }

        if (!cfg) { $b.addClass('d-none'); return; }
        $b.removeClass('d-none alert-danger alert-warning').addClass(cfg.cls);
        $('#reasonBannerIcon').removeClass().addClass('bi fs-5 ' + cfg.icon);
        $('#reasonBannerTitle').text(cfg.title);
        $('#reasonBannerBody').text(cfg.body);
    }

    function renderActivityLog(logs) {
        const $box = $('#activityLog');
        if (!$box.length) return;
        const list = Array.isArray(logs) ? logs : [];
        $('#activityLogCount').text(list.length);
        if (!list.length) {
            $box.html('<div class="text-muted small italic">Chưa có thao tác nào.</div>');
            return;
        }

        const actorLabel = a => {
            const k = String(a || '').toLowerCase();
            if (k === 'admin') return { text: 'Admin', cls: 'text-primary', icon: 'bi-person-badge' };
            if (k === 'customer') return { text: 'Khách hàng', cls: 'text-success', icon: 'bi-person' };
            if (k === 'carrier') return { text: 'GHN', cls: 'text-info', icon: 'bi-truck' };
            return { text: 'Hệ thống', cls: 'text-secondary', icon: 'bi-gear' };
        };
        const eventLabel = e => {
            const map = {
                'order_created': 'Tạo đơn hàng',
                'status_changed': 'Đổi trạng thái',
                'cancel_requested': 'Yêu cầu huỷ',
                'cancel_approved': 'Duyệt huỷ đơn',
                'cancel_rejected': 'Từ chối huỷ',
                'return_requested': 'Yêu cầu trả hàng',
                'return_approved': 'Duyệt trả hàng',
                'return_rejected': 'Từ chối trả hàng',
                'return_received': 'Đã nhận lại hàng từ khách',
                'return_inspected': 'Đã kiểm tra hàng hoàn',
                'stock_restored': 'Hoàn kho',
                'voucher_usage_restored': 'Hoàn lượt voucher',
                'refund_pending_marked': 'Đánh dấu cần hoàn tiền',
                'refund_completed': 'Đã hoàn tiền',
                'refund_attempt_failed': 'Hoàn tiền thất bại',
                'refund_attempt_pending': 'Hoàn tiền đang xử lý',
                'address_updated': 'Cập nhật địa chỉ',
                'payment_updated': 'Cập nhật thanh toán',
                'shipping_updated': 'Cập nhật vận chuyển',
                'carrier_updated': 'Cập nhật từ đơn vị vận chuyển',
                'ghn_status_synced': 'Đồng bộ trạng thái GHN',
                'ghn_sync_skipped': 'Bỏ qua đồng bộ GHN',
            };
            return map[String(e || '')] || String(e || 'Thao tác');
        };
        const fmtTime = ts => {
            if (!ts) return '';
            const d = new Date(String(ts).replace(' ', 'T'));
            if (isNaN(d.getTime())) return esc(ts);
            const p = n => String(n).padStart(2, '0');
            return `${p(d.getHours())}:${p(d.getMinutes())} ${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}`;
        };

        // Mới nhất lên đầu
        const rows = list.slice().reverse().map(log => {
            const a = actorLabel(log.actor_type);
            const ev = eventLabel(log.event);
            const note = String(log.note || '').trim();
            const transition = (log.status_from && log.status_to && log.status_from !== log.status_to)
                ? `<span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">${esc(statusLabelVi(log.status_from))} → ${esc(statusLabelVi(log.status_to))}</span>`
                : '';
            return `
                <div class="d-flex gap-2 py-2 border-bottom">
                    <div class="flex-shrink-0"><i class="bi ${a.icon} ${a.cls}"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="fw-semibold small">${esc(ev)}${transition}</span>
                            <span class="text-muted" style="font-size:0.7rem;white-space:nowrap;">${fmtTime(log.created_at)}</span>
                        </div>
                        <div class="small ${a.cls}" style="font-size:0.72rem;">${esc(a.text)}</div>
                        ${note ? `<div class="text-muted small mt-1" style="font-size:0.72rem;word-break:break-word;">${esc(note)}</div>` : ''}
                    </div>
                </div>`;
        }).join('');
        $box.html(rows);
    }

    function renderShippingTimeline(order, shippingLive) {
        const $box = $('#shippingTimeline');
        if (!$box.length) return;

        const timeline = shippingLive?.timeline || [];
        if (!timeline.length) {
            $box.html(`<div class="text-muted small italic">Chưa có hành trình. Trạng thái: ${esc(statusLabelVi(order?.status))}</div>`);
            return;
        }

        const lastIdx = timeline.length - 1;
        const actorBadgeClass = actor => {
            const a = String(actor || '').toLowerCase();
            if (a === 'admin') return 'bg-primary bg-opacity-10 text-primary border border-primary-subtle';
            if (a === 'khách hàng' || a === 'customer') return 'bg-success bg-opacity-10 text-success border border-success-subtle';
            if (a.includes('vận chuyển') || a === 'ghn' || a === 'carrier') return 'bg-info bg-opacity-10 text-info border border-info-subtle';
            return 'bg-light text-muted border';
        };

        const html = timeline.map((item, idx) => {
            const meta = item.time_human || fmtDateTime(item.time) || 'Đang cập nhật';
            const actor = String(item.actor || 'Hệ thống');
            return `
                <div class="timeline-node ${idx === lastIdx ? 'active' : ''}">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-bold small text-dark">${esc(item.label || 'Cập nhật')}</div>
                        <span class="badge ${actorBadgeClass(actor)}" style="font-size:0.65rem;">${esc(actor)}</span>
                    </div>
                    <div class="text-muted" style="font-size:0.75rem;">${esc(meta)}</div>
                    ${item.note ? `<div class="small mt-1 text-secondary" style="white-space:pre-line;">${esc(item.note)}</div>` : ''}
                </div>`;
        }).join('');

        $box.html(html);
    }

    function applyMetaStatusBadge(statusKey) {
        const key = normalizeStatusKey(statusKey);
        const colorMap = {
            pending: 'text-bg-secondary',
            processing: 'text-bg-primary',
            shipping: 'text-bg-info',
            delivered: 'text-bg-success',
            cancel_requested: 'text-bg-warning',
            return_requested: 'text-bg-warning',
            returned: 'text-bg-secondary',
            refunded: 'text-bg-success bg-opacity-75',
            canceled: 'text-bg-danger',
        };
        const colorCls = colorMap[key] || 'text-bg-secondary';
        const label = statusLabelVi(key);
        $('#metaStatus').html(`<span class="badge rounded-pill ${colorCls}">${esc(label)}</span>`);
        $('#currentStatusDisplay').text(label).removeClass().addClass('h5 fw-bold mb-0 ' + colorCls.replace('text-bg-', 'text-').replace(' bg-opacity-75', ''));
        updateProgressStepper(key);
    }

    // Cập nhật stepper ngang theo trạng thái đơn (đồng bộ phong cách view-order)
    function updateProgressStepper(statusKey) {
        const key = normalizeStatusKey(statusKey);
        const $bar = $('#orderProgressBar');
        const $wrap = $bar.find('.stepper-wrap');
        
        // Dọn dẹp các bước cũ (giữ lại thanh line)
        $wrap.find('.step-item').remove();

        const isCanceled = ['canceled', 'cancelled', 'cancel_requested', 'failed', 'error'].includes(key);
        const isReturned = ['returned', 'refunded', 'return_requested'].includes(key);

        let steps = [];
        let activeIdx = -1;
        let flowType = 'normal'; // 'normal', 'cancel', 'return'

        if (isCanceled) {
            flowType = 'cancel';
            steps = [
                { step: 'pending', icon: 'bi-receipt', label: 'Chờ xác nhận' },
                { step: 'cancel_requested', icon: 'bi-question-circle', label: 'Yêu cầu huỷ' },
                { step: 'canceled', icon: 'bi-x-circle', label: 'Đơn đã huỷ' }
            ];
            if (key === 'cancel_requested') {
                activeIdx = 1;
            } else {
                activeIdx = 2;
            }
        } else if (isReturned) {
            flowType = 'return';
            steps = [
                { step: 'delivered', icon: 'bi-check2-circle', label: 'Đã giao hàng' },
                { step: 'return_requested', icon: 'bi-arrow-return-left', label: 'Yêu cầu trả hàng' },
                { step: 'returned', icon: 'bi-box-arrow-in-down', label: 'Nhận hàng hoàn' },
                { step: 'refunded', icon: 'bi-cash-coin', label: 'Đã hoàn tiền' }
            ];
            if (key === 'return_requested') {
                activeIdx = 1;
            } else if (key === 'returned') {
                activeIdx = 2;
            } else if (key === 'refunded') {
                activeIdx = 3;
            }
        } else {
            flowType = 'normal';
            steps = [
                { step: 'pending', icon: 'bi-receipt', label: 'Chờ xác nhận' },
                { step: 'processing', icon: 'bi-box-seam', label: 'Đang chuẩn bị' },
                { step: 'shipping', icon: 'bi-truck', label: 'Đang giao hàng' },
                { step: 'delivered', icon: 'bi-check2-circle', label: 'Đã giao hàng' },
                { step: 'completed', icon: 'bi-star', label: 'Hoàn thành' }
            ];
            if (key === 'pending') activeIdx = 0;
            else if (key === 'processing') activeIdx = 1;
            else if (key === 'shipping') activeIdx = 2;
            else if (key === 'delivered') activeIdx = 3;
            else if (['completed', 'success'].includes(key)) activeIdx = 4;
        }

        // Render các step-item mới
        steps.forEach((st, idx) => {
            let itemClass = '';
            if (idx < activeIdx) {
                if (flowType === 'cancel') {
                    itemClass = 'completed completed-danger';
                } else if (flowType === 'return') {
                    itemClass = 'completed completed-warning';
                } else {
                    itemClass = 'completed';
                }
            } else if (idx === activeIdx) {
                if (flowType === 'cancel') {
                    itemClass = 'active active-danger';
                } else if (flowType === 'return') {
                    itemClass = 'active active-warning';
                } else {
                    itemClass = 'active';
                }
            }

            const stepHtml = `
                <div class="step-item ${itemClass}" data-step="${st.step}">
                    <div class="step-icon"><i class="bi ${st.icon}"></i></div>
                    <div class="step-label">${esc(st.label)}</div>
                </div>
            `;
            $wrap.append(stepHtml);
        });

        // Tính toán chiều dài thanh line (bắt đầu từ 10% đến 90% = 80%)
        const totalSteps = steps.length;
        let percent = 0;
        if (activeIdx >= 0 && totalSteps > 1) {
            percent = (activeIdx * 80) / (totalSteps - 1);
        }
        
        // Thiết lập màu sắc và độ rộng thanh line
        const $line = $('#orderProgressLine');
        if (flowType === 'cancel') {
            $line.css({
                'background': 'var(--bs-danger, #dc3545)',
                'width': percent + '%'
            });
        } else if (flowType === 'return') {
            $line.css({
                'background': 'var(--bs-warning, #ffc107)',
                'width': percent + '%'
            });
        } else {
            $line.css({
                'background': 'linear-gradient(90deg, var(--theme-primary, #0c4c29) 0%, var(--order-primary, #2563eb) 100%)',
                'width': percent + '%'
            });
        }
        
        $bar.removeClass('d-none');
    }

    function updateStatusActions(statusKey) {
        const st = normalizeStatusKey(statusKey);
        const $actions = $('#statusActionGroup .status-action').hide();
        const $branches = $('#statusActionGroup .status-branch').hide();
        const $reasonWrapper = $('#canceledReasonWrapper').hide();
        const $actionGroup = $('#statusActionGroup');

        if (st === 'canceled' || st === 'refunded') {
            $actionGroup.hide();
            return;
        }

        $actionGroup.show();
        if (st === 'pending') {
            $branches.filter('[data-branch="normal"], [data-branch="cancel"]').show();
            $('#btnToProcessing, #btnToCanceled').show();
        } else if (st === 'processing') {
            $branches.filter('[data-branch="normal"], [data-branch="cancel"]').show();
            $('#btnToShipping, #btnToCanceled').show();
        } else if (st === 'cancel_requested') {
            $branches.filter('[data-branch="cancel_review"]').show();
            const match = String($('#e_note').val() || '').match(/Yêu cầu hủy:\s*(.+)/);
            $('#cancelRequestReasonBox').text('Lý do: ' + (match ? match[1].trim() : 'Không có lý do cụ thể'));
        } else if (st === 'shipping') {
            $branches.filter('[data-branch="normal"], [data-branch="risk"]').show();
            $('#btnToDelivered, #btnToReturnReq').show();
        } else if (st === 'delivered') {
            $branches.filter('[data-branch="risk"]').show();
            $('#btnToReturnReq').show();
        } else if (st === 'return_requested') {
            $branches.filter('[data-branch="return_review"]').show();
        } else if (st === 'returned') {
            $branches.filter('[data-branch="risk"]').show();
            $('#btnToRefunded').show();
        }
    }

    // =========================================================================
    // 6. API LOADERS & RESOLVERS
    // =========================================================================
    function fetchProductMeta(ids) {
        const uniq = [...new Set((ids || []).map(Number).filter(v => v > 0))];
        const missing = uniq.filter(id => !productMetaCache[id]);
        if (!missing.length) return $.Deferred().resolve().promise();
        return $.get(API_URL, { ajax: 'product_meta', ids: missing.join(',') }, res => {
            if (res?.ok && res.data) {
                Object.assign(productMetaCache, res.data);
            }
        }, 'json');
    }

    function syncLegacyFieldsFromItems() {
        $('#e_product').val(orderItems.map(it => `${it.name} x${it.qty}`).join(' | '));
        try {
            $('#e_products_json').val(JSON.stringify(orderItems));
        } catch (e) {
            $('#e_products_json').val('[]');
        }
    }

    function toggleFormLock(isLocked) {
        if (isLocked) {
            $('#lockAlert').removeClass('d-none');
            $('#e_user_name, #e_phone, #e_email, #e_address, #e_province, #e_district, #e_ward, #e_street, #e_note').prop('disabled', true);
            $('#btnResolveAddress').prop('disabled', true);
            $('#btnSave').prop('disabled', true);
            $('button[data-bs-target="#orderStatusModal"]').prop('disabled', true);
            $('#reviewReply').prop('disabled', true);
            $('#btnSaveReply').prop('disabled', true);
        } else {
            $('#lockAlert').addClass('d-none');
            $('#e_user_name, #e_phone, #e_email, #e_address, #e_province, #e_district, #e_ward, #e_street, #e_note').prop('disabled', false);
            $('#btnResolveAddress').prop('disabled', false);
            $('#btnSave').prop('disabled', false);
            $('button[data-bs-target="#orderStatusModal"]').prop('disabled', false);
            $('#reviewReply').prop('disabled', false);
            $('#btnSaveReply').prop('disabled', false);
        }
    }

    let lastOrderData = null;
    let lastShippingLive = null;

    function updateContactLinks(phone) {
        const p = String(phone || '').replace(/[^0-9+]/g, '');
        const $call = $('#btnCallPhone'), $zalo = $('#btnZaloPhone');
        if (!p) {
            $call.attr('href', '#').addClass('disabled');
            $zalo.attr('href', '#').addClass('disabled');
            return;
        }
        $call.attr('href', 'tel:' + p).removeClass('disabled');
        // Zalo dùng số không có dấu +; bỏ tiền tố +84 -> 0 cho dễ
        let zp = p.replace(/^\+?84/, '0');
        $zalo.attr('href', 'https://zalo.me/' + encodeURIComponent(zp)).removeClass('disabled');
    }
    $('#e_phone').on('input', function(){ updateContactLinks($(this).val()); });

    function buildPrintHtml() {
        const o = lastOrderData || {};
        const live = lastShippingLive || {};
        const items = orderItems.map(it => {
            const variant = it.variant ? ` (${esc(it.variant)})` : '';
            return `<tr><td>${esc(it.name || '')}${variant}</td><td class="c">${esc(it.qty || 1)}</td></tr>`;
        }).join('') || '<tr><td colspan="2">Không có sản phẩm</td></tr>';

        const tracking = String(live.tracking || o.shipping_tracking || '').trim() || '—';
        const carrier = String(live.service || o.shipping_method_label || 'GHN').trim();
        return `<!doctype html><html lang="vi"><head><meta charset="utf-8">
            <title>Phiếu giao hàng #${esc(o.order_id || '')}</title>
            <style>
              *{box-sizing:border-box} body{font-family:Arial,sans-serif;color:#111;margin:24px;font-size:13px;line-height:1.5}
              h1{font-size:18px;margin:0} .muted{color:#666;font-size:12px}
              .hd{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:12px}
              .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
              .box{border:1px solid #ddd;border-radius:6px;padding:8px 10px}
              .box h3{font-size:12px;margin:0 0 6px;text-transform:uppercase}
              table{width:100%;border-collapse:collapse;margin-top:6px} th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}
              th{background:#f5f5f5} .c{text-align:center;width:70px}
              .tot{margin-top:10px;text-align:right;font-weight:700;font-size:15px}
            </style></head><body>
            <div class="hd">
              <div><h1>PHIẾU GIAO HÀNG</h1><div class="muted">PAINTMORE</div></div>
              <div style="text-align:right"><div><b>#${esc(o.order_id || '')}</b></div><div class="muted">${esc(o.created_fmt || '')}</div></div>
            </div>
            <div class="grid">
              <div class="box"><h3>Người nhận</h3>
                <div><b>${esc(o.user_name || '')}</b></div>
                <div>${esc(o.phone || '')}</div>
                <div>${esc(o.address || '')}</div>
              </div>
              <div class="box"><h3>Giao hàng & Thanh toán</h3>
                <div>ĐVVC: ${esc(carrier)}</div>
                <div>Mã vận đơn: <b>${esc(tracking)}</b></div>
                <div>Thanh toán: ${esc(o.payment_method_label || o.payment_method || 'COD')}</div>
                <div>Trạng thái TT: ${esc(o.payment_status_label || '')}</div>
              </div>
            </div>
            <table><thead><tr><th>Sản phẩm</th><th class="c">SL</th></tr></thead><tbody>${items}</tbody></table>
            <div class="tot">Tổng thanh toán: ${esc(o.total_amount_fmt || '')}</div>
            ${o.note ? `<div class="muted" style="margin-top:8px">Ghi chú KH: ${esc(o.note)}</div>` : ''}
            </body></html>`;
    }

    $('#btnPrintOrder').on('click', function(){
        if (!lastOrderData) { toastr.warning('Đơn chưa tải xong'); return; }
        const w = window.open('', '_blank');
        if (!w) { toastr.error('Trình duyệt chặn cửa sổ in'); return; }
        w.document.open(); w.document.write(buildPrintHtml()); w.document.close();
        w.focus(); setTimeout(() => w.print(), 300);
    });

    function loadDetail() {
        if (!ORDER_ID) return;
        $.get(API_URL, { ajax: 'order_detail', order_id: ORDER_ID }, res => {
            if (!res?.ok) {
                toastr.error(res?.msg || 'Không tải được đơn');
                return;
            }
            const o = res.order || {};
            lastOrderData = o;
            lastShippingLive = res.shipping_live || {};
            $('#e_order_id').val(o.order_id || '');
            $('#metaOrder').text('#' + (o.order_id || ''));
            $('#metaCreated').text(o.created_fmt || '--');
            applyMetaStatusBadge(o.status || 'pending');
            $('#metaPay').text(o.payment_method_label || o.payment_method || '--');

            $('#e_user_name').val(o.user_name || '');
            $('#e_phone').val(o.phone || '');
            updateContactLinks(o.phone || '');
            $('#e_email').val(o.email || '');
            $('#e_address').val(o.address || '');
            $('#e_note').val(o.note || '');
            $('#e_admin_note').val(o.admin_note || '');
            $('#e_status').val(o.status || 'pending');
            $('#e_canceled_reason').val(o.canceled_reason || '');

            $('#e_payment_gateway').val(o.payment_gateway || 'Trực tiếp');
            $('#e_payment_ref').val(o.payment_ref || o.order_id || '--');
            $('#e_bank_code').val(o.bank_code || '--');

            // Update preview elements
            $('#previewRecipientName').text(o.user_name || '—');
            $('#previewRecipientPhone').text(o.phone || '—');
            $('#previewRecipientAddress').text(o.address || '—');
            $('#previewOrderId').text(o.order_id || '—');
            $('#previewOrderCreated').text(o.created_fmt || '--');
            $('#previewPaymentMethod').text(o.payment_method_label || o.payment_method || '--');

            const _method = String(o.payment_method || '').toLowerCase();
            let payStatusText = '';
            if (_method === 'cod') {
                payStatusText = 'Thanh toán khi nhận hàng (Tiền mặt)';
            } else {
                payStatusText = o.payment_time_fmt 
                    ? 'Đã thanh toán (' + o.payment_time_fmt + ')' 
                    : 'Chờ thanh toán';
            }
            $('#previewPaymentStatus').text(payStatusText)
                .removeClass('text-success text-warning')
                .addClass((o.payment_time_fmt || _method === 'cod') ? 'text-success' : 'text-warning');

            // Refund card: hiện khi payment_status === 'refund_pending'
            const _payStatus = String(o.payment_status || '').toLowerCase().trim();
            const _gw = String(o.payment_gateway || o.payment_method || '').toLowerCase().trim();
            const $refundCard = $('#refundCard');
            const $btnAuto = $('#btnRefundAuto');
            const $btnAutoGw = $('#btnRefundAutoGateway');
            const $refundHint = $('#refundHint');
            if (_payStatus === 'refund_pending') {
                $refundCard.show();
                if (['momo','zalopay'].includes(_gw)) {
                    $btnAuto.show().data('gateway', _gw).data('order_id', o.order_id);
                    $btnAutoGw.text(_gw.toUpperCase());
                    $refundHint.text('Bấm "Hoàn tự động" để gọi API ' + _gw.toUpperCase() + ' refund. Tiền về khách 1-3 ngày làm việc.');
                } else {
                    $btnAuto.hide();
                    $refundHint.text('Gateway "' + (_gw || 'COD') + '" không hỗ trợ refund tự động. Hoàn tay qua tài khoản ngân hàng.');
                }
                $('#btnRefundManual').data('order_id', o.order_id);
            } else {
                $refundCard.hide();
            }

            const inv = res.invoice;
            if (inv) {
                const typeLabel = String(inv.invoice_type).toLowerCase() === 'company' ? 'Doanh nghiệp (VAT)' : 'Cá nhân';
                $('#e_invoice_type').val(typeLabel);
                $('#e_invoice_buyer_name').text('Người mua: ' + (inv.buyer_name || o.user_name || ''));
                $('#e_invoice_company_name').val(inv.company_name || inv.buyer_name || '');
                $('#e_invoice_tax_code').val(inv.tax_code || 'Không có');
                $('#e_invoice_address').text(inv.address || o.address || '');
                $('#e_invoice_email').text(inv.email || o.email || '');
                $('#e_invoice_section').show();
                $('#previewInvoiceState').text('Có yêu cầu xuất hóa đơn');
            } else {
                $('#e_invoice_section').hide();
                $('#previewInvoiceState').text('Không yêu cầu');
            }

            setCurrencyValue('subtotal', o.subtotal);
            setCurrencyValue('shipping_fee', o.shipping_fee);
            setCurrencyValue('total_amount', o.total_amount);

            orderItems.length = 0;
            const itemsFromJson = parseProductsJson(o.products_json);
            const itemsFromLegacy = itemsFromJson.length ? [] : parseProductString(o.product);
            itemsFromJson.concat(itemsFromLegacy).forEach(it => orderItems.push(it));
            const ids = orderItems.map(it => Number(it.product_id || it.id || 0)).filter(v => v > 0);

            $('#detailItemsCount').text(orderItems.length + ' sản phẩm');
            renderProductList();
            fetchProductMeta(ids).always(() => renderProductList());

            renderReview(res.review || null);
            renderGhnPreview(o, res.shipping_live || null, res.ghn_fee);
            renderShippingTimeline(res.order, res.shipping_live || null);
            renderActivityLog(res.full_logs || []);
            renderReasonBanner(o, res.full_logs || []);
            updateStatusActions(o.status || 'pending');
            toggleFormLock(o.status === 'canceled');
        }, 'json');
    }



    function saveOrder(redirect) {
        const status = String($('#e_status').val() || 'pending');
        const reason = String($('#e_canceled_reason').val() || '').trim();

        if (status === 'canceled' && !reason) {
            toastr.warning('Vui lòng nhập lý do huỷ đơn hàng');
            $('#orderStatusModal').modal('show');
            $('#statusActionGroup').hide();
            $('#canceledReasonWrapper').show();
            $('#e_canceled_reason').focus();
            return;
        }

        syncLegacyFieldsFromItems();
        const payload = {
            action: 'save',
            order_id: String($('#e_order_id').val() || ''),
            user_name: String($('#e_user_name').val() || ''),
            phone: String($('#e_phone').val() || ''),
            email: String($('#e_email').val() || ''),
            address: String($('#e_address').val() || ''),
            note: String($('#e_note').val() || ''),
            status: status,
            canceled_reason: reason,
            shipping_carrier: window._pendingCarrier || '',
            shipping_tracking: window._pendingTracking || '',
            products_json: String($('#e_products_json').val() || '[]'),
            product: String($('#e_product').val() || ''),
            subtotal: String($('#e_subtotal').val() || '0'),
            total_amount: String($('#e_total_amount').val() || '0')
        };

        delete window._pendingCarrier;
        delete window._pendingTracking;

        $.post(API_URL, payload, res => {
            if (res?.ok) {
                toastr.success(res.msg || 'Đã cập nhật thông tin đơn hàng');
                $('#orderStatusModal').modal('hide');
                if (redirect) {
                    window.location.href = BASE_URL + '/admin/order';
                } else {
                    loadDetail();
                }
            } else {
                toastr.error(res?.msg || 'Không thể cập nhật');
            }
        }, 'json').fail(() => toastr.error('Lỗi kết nối máy chủ'));
    }

    function setStatusAndSave(statusKey) {
        $('#e_status').val(statusKey);
        applyMetaStatusBadge(statusKey);

        if (statusKey === 'shipping') {
            const carrier = prompt('Nhập tên đơn vị vận chuyển (ví dụ: GHN, Viettel Post, ...):', 'GHN');
            if (carrier === null) return;
            const tracking = prompt('Nhập mã vận đơn (tracking number):');
            if (tracking === null) return;

            window._pendingCarrier = carrier;
            window._pendingTracking = tracking;
        }

        if (statusKey === 'canceled') {
            $('#statusActionGroup').hide();
            $('#canceledReasonWrapper').show();
            $('#e_canceled_reason').focus();
        } else {
            saveOrder(false);
        }
    }

    $('#btnSave, #btnSaveStatus, #btnSaveStatusCancel').on('click', () => saveOrder(false));

    function decideCancelRequest(decision) {
        const orderId = $('#e_order_id').val();
        if (!orderId) return;
        const label = decision === 'approve' ? 'duyệt hủy đơn' : 'tối từ chối hủy đơn';
        if (!confirm('Xác nhận ' + label + ' #' + orderId + '?')) return;
        $.post(API_URL, { action: 'admin_decide_cancel', order_id: orderId, decision: decision }, res => {
            if (res?.ok) {
                toastr.success(decision === 'approve' ? 'Đã duyệt hủy đơn' : 'Đã từ chối yêu cầu hủy');
                loadDetail();
            } else {
                toastr.error(res?.msg || 'Thao tác thất bại');
            }
        }, 'json');
    }

    $('#btnApproveCancelRequest').on('click', () => decideCancelRequest('approve'));
    $('#btnRejectCancelRequest').on('click', () => decideCancelRequest('reject'));

    // ========== Xét duyệt yêu cầu TRẢ HÀNG ==========
    function loadReturnRequestDetail() {
        const orderId = $('#e_order_id').val() || ORDER_ID;
        if (!orderId) return;
        $('#returnReviewLoading').removeClass('d-none');
        $('#returnReviewEmpty, #returnReviewBox').addClass('d-none');

        $.get(API_URL, { ajax: 'return_request_detail', order_id: orderId }, res => {
            $('#returnReviewLoading').addClass('d-none');
            if (!res?.ok || !res.request) {
                $('#returnReviewEmpty').removeClass('d-none');
                return;
            }
            const r = res.request;
            $('#rrReason').text(r.reason || '--');
            $('#rrRefund').text(r.refund_amount_fmt || (r.refund_amount > 0 ? fmtVnd(r.refund_amount) : '0 đ'));
            $('#rrCreated').text(r.created_fmt || r.created_at || '--');

            // Bank — admin xem đầy đủ thông tin
            const b = r.bank || {};
            if (b && (b.bank_name || b.bank_code || b.account_no)) {
                const rows = [];
                const typeLabel = String(b.type || '').toLowerCase() === 'card' ? 'Thẻ tín dụng/ghi nợ' : 'Tài khoản ngân hàng';
                rows.push(`<div class="d-flex justify-content-between"><span class="text-muted small">Loại</span><span class="fw-semibold">${esc(typeLabel)}</span></div>`);
                if (b.bank_name || b.bank_code) {
                    rows.push(`<div class="d-flex justify-content-between"><span class="text-muted small">Ngân hàng</span><span class="fw-semibold">${esc(b.bank_name || '')}${b.bank_code ? ' (' + esc(b.bank_code) + ')' : ''}</span></div>`);
                }
                if (b.bank_branch) {
                    rows.push(`<div class="d-flex justify-content-between"><span class="text-muted small">Chi nhánh</span><span class="fw-semibold">${esc(b.bank_branch)}</span></div>`);
                }
                const accNo = String(b.account_no || '').trim();
                if (accNo) {
                    rows.push(`<div class="d-flex justify-content-between align-items-center"><span class="text-muted small">Số tài khoản</span><span class="fw-bold text-primary font-monospace">${esc(accNo)} <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="btnCopyBankNo" title="Sao chép"><i class="bi bi-clipboard"></i></button></span></div>`);
                } else if (b.account_last4) {
                    rows.push(`<div class="d-flex justify-content-between"><span class="text-muted small">Số tài khoản</span><span class="fw-bold font-monospace">**** ${esc(b.account_last4)}</span></div>`);
                }
                if (b.account_owner) {
                    rows.push(`<div class="d-flex justify-content-between"><span class="text-muted small">Chủ tài khoản</span><span class="fw-semibold text-uppercase">${esc(b.account_owner)}</span></div>`);
                }
                $('#rrBank').html('<div class="d-flex flex-column gap-1">' + rows.join('') + '</div>');
                $('#btnCopyBankNo').off('click').on('click', function(){
                    navigator.clipboard?.writeText(accNo);
                    toastr.success('Đã sao chép số tài khoản');
                });
            } else {
                $('#rrBank').html('<span class="text-muted small italic">Khách không cung cấp thông tin ngân hàng.</span>');
            }

            $('#rrDescription').text(r.description || 'Khách không nhập mô tả.');
            $('#rrContact').text(r.contact_email || '--');

            // Media preview
            const $media = $('#rrMedia').empty();
            const media = Array.isArray(r.media) ? r.media : [];
            if (!media.length) {
                $media.html('<span class="text-muted small italic">Không có tệp đính kèm.</span>');
            } else {
                media.forEach(m => {
                    const url = String(m.url || '');
                    if (!url) return;
                    if (m.type === 'video') {
                        $media.append(`
                            <a href="${esc(url)}" target="_blank" class="d-inline-block position-relative" style="width:80px;height:80px;">
                                <video src="${esc(url)}" class="rounded border" style="width:80px;height:80px;object-fit:cover;background:#000;"></video>
                                <span class="position-absolute top-50 start-50 translate-middle text-white">
                                    <i class="bi bi-play-circle-fill fs-4"></i>
                                </span>
                            </a>
                        `);
                    } else {
                        $media.append(`
                            <a href="${esc(url)}" target="_blank">
                                <img src="${esc(url)}" class="rounded border" style="width:80px;height:80px;object-fit:cover;" onerror="this.style.display='none'">
                            </a>
                        `);
                    }
                });
            }

            $('#rrAdminNote').val('');
            $('#returnReviewBox').removeClass('d-none');

            // Render mini-timeline tiến trình + nhóm nút hành động theo bước hiện tại
            renderReturnProgress(r);
        }, 'json').fail(() => {
            $('#returnReviewLoading').addClass('d-none');
            $('#returnReviewEmpty').removeClass('d-none');
        });
    }

    function renderReturnProgress(r) {
        const p = r.progress || {};
        const evMap = {};
        (r.progress_events || []).forEach(ev => { evMap[ev.event] = ev; });

        const steps = [
            { key: 'approved',  event: 'return_approved',  label: 'Duyệt yêu cầu trả hàng', done: !!p.approved  },
            { key: 'received',  event: 'return_received',  label: 'Đã nhận lại hàng từ khách', done: !!p.received  },
            { key: 'inspected', event: 'return_inspected', label: 'Đã kiểm tra hàng hoàn',     done: !!p.inspected },
            { key: 'completed', event: 'completed',        label: 'Hoàn tất trả hàng (cập nhật kho)', done: !!p.completed },
        ];
        const $box = $('#rrProgressSteps').empty();
        steps.forEach((st, idx) => {
            const ev = evMap[st.event];
            const meta = ev ? `<div class="text-muted" style="font-size:11px;">${esc(ev.time_human)}${ev.note ? ' · ' + esc(ev.note) : ''}</div>` : '';
            const icon = st.done
                ? '<i class="bi bi-check-circle-fill text-success"></i>'
                : '<i class="bi bi-circle text-muted"></i>';
            $box.append(`
                <div class="d-flex align-items-start gap-2 ${st.done ? '' : 'opacity-75'}">
                    <div style="width:20px;">${icon}</div>
                    <div class="flex-grow-1">
                        <div class="small ${st.done ? 'fw-bold text-dark' : 'text-muted'}">${esc(st.label)}</div>
                        ${meta}
                    </div>
                </div>
            `);
        });

        // Footer action buttons — chỉ hiển thị nút phù hợp với bước kế tiếp
        const $actions = $('#rrActionGroup').empty();
        const isRejected = String(r.status || '').toLowerCase() === 'rejected';
        const isCompleted = !!p.completed;

        if (isRejected || isCompleted) {
            $actions.append('<span class="text-muted small fst-italic align-self-center">Yêu cầu đã được xử lý xong.</span>');
            return;
        }

        if (!p.approved) {
            $actions.append(`
                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" id="btnRejectReturnRequest">
                    <i class="bi bi-x-circle me-1"></i>Từ chối yêu cầu
                </button>
                <button type="button" class="btn btn-warning px-4 rounded-pill fw-semibold" id="btnApproveReturnRequest">
                    <i class="bi bi-check-circle me-1"></i>Duyệt yêu cầu trả hàng
                </button>
            `);
        } else if (!p.received) {
            $actions.append(`
                <button type="button" class="btn btn-primary px-4 rounded-pill fw-semibold" id="btnMarkReturnReceived">
                    <i class="bi bi-box-arrow-in-down me-1"></i>Đã nhận lại hàng từ khách
                </button>
            `);
        } else if (!p.inspected) {
            $actions.append(`
                <button type="button" class="btn btn-info text-white px-4 rounded-pill fw-semibold" id="btnMarkReturnInspected">
                    <i class="bi bi-clipboard-check me-1"></i>Đã kiểm tra hàng hoàn
                </button>
            `);
        } else {
            $actions.append(`
                <button type="button" class="btn btn-success px-4 rounded-pill fw-semibold" id="btnConfirmReturnCompleted">
                    <i class="bi bi-check2-all me-1"></i>Xác nhận hoàn tất trả hàng
                </button>
            `);
        }
    }

    function postReturnFlow(action, confirmMsg, successMsg) {
        const orderId = $('#e_order_id').val();
        if (!orderId) return;
        const note = String($('#rrAdminNote').val() || '').trim();
        if (confirmMsg && !confirm(confirmMsg)) return;

        $.post(API_URL, {
            action: action,
            order_id: orderId,
            note: note
        }, res => {
            if (res?.ok) {
                toastr.success(res.msg || successMsg || 'Đã cập nhật');
                // Nếu là bước cuối cùng (status đã chuyển sang 'returned') → đóng modal & reload
                if (action === 'admin_confirm_return_completed') {
                    $('#returnReviewModal').modal('hide');
                    loadDetail();
                } else {
                    // Các bước trung gian: làm mới modal để cập nhật progress
                    loadReturnRequestDetail();
                    loadDetail(); // refresh timeline phía sau modal status
                }
            } else {
                toastr.error(res?.msg || 'Thao tác thất bại');
            }
        }, 'json').fail(() => toastr.error('Lỗi kết nối máy chủ'));
    }

    function decideReturnRequest(decision) {
        const orderId = $('#e_order_id').val();
        if (!orderId) return;
        const note = String($('#rrAdminNote').val() || '').trim();
        const label = decision === 'approve' ? 'duyệt yêu cầu trả hàng' : 'từ chối yêu cầu trả hàng';
        if (!confirm('Xác nhận ' + label + ' cho đơn #' + orderId + '?')) return;

        $.post(API_URL, {
            action: 'admin_decide_return',
            order_id: orderId,
            decision: decision,
            note: note
        }, res => {
            if (res?.ok) {
                toastr.success(res.msg || (decision === 'approve' ? 'Đã duyệt yêu cầu trả hàng' : 'Đã từ chối yêu cầu trả hàng'));
                if (decision === 'reject') {
                    $('#returnReviewModal').modal('hide');
                    loadDetail();
                } else {
                    loadReturnRequestDetail();
                    loadDetail();
                }
            } else {
                toastr.error(res?.msg || 'Thao tác thất bại');
            }
        }, 'json').fail(() => toastr.error('Lỗi kết nối máy chủ'));
    }

    // Delegated handlers (vì các nút được render động trong #rrActionGroup)
    $(document).on('click', '#btnApproveReturnRequest',     () => decideReturnRequest('approve'));
    $(document).on('click', '#btnRejectReturnRequest',      () => decideReturnRequest('reject'));
    $(document).on('click', '#btnMarkReturnReceived',       () => postReturnFlow('admin_mark_return_received',  'Xác nhận đã nhận lại hàng từ khách?',  'Đã đánh dấu nhận lại hàng'));
    $(document).on('click', '#btnMarkReturnInspected',      () => postReturnFlow('admin_mark_return_inspected', 'Xác nhận đã kiểm tra hàng hoàn?',       'Đã đánh dấu kiểm tra'));
    $(document).on('click', '#btnConfirmReturnCompleted',   () => postReturnFlow('admin_confirm_return_completed', 'Xác nhận HOÀN TẤT trả hàng? Kho sẽ được cập nhật.', 'Đã hoàn tất trả hàng'));

    // Khi mở modal xét duyệt: ẩn modal trạng thái phía sau và load chi tiết
    $('#returnReviewModal').on('show.bs.modal', function() {
        $('#orderStatusModal').modal('hide');
        loadReturnRequestDetail();
    });

    $('#statusActionGroup').on('click', '.status-action', function() {
        const nextStatus = String($(this).attr('data-status') || '').trim();
        if (nextStatus) setStatusAndSave(nextStatus);
    });

    $('#e_status').on('change', function() {
        const next = $(this).val();
        applyMetaStatusBadge(next);
        updateStatusActions(next);
    });

    $('#btnSaveAdminNote').on('click', function(){
        const $btn = $(this).prop('disabled', true);
        const note = String($('#e_admin_note').val() || '');
        $.post(API_URL, { action: 'admin_save_note', order_id: ORDER_ID, admin_note: note }, res => {
            if (res?.ok) toastr.success(res.msg || 'Đã lưu ghi chú nội bộ');
            else toastr.error(res?.msg || 'Không lưu được ghi chú');
        }, 'json').fail(() => toastr.error('Lỗi kết nối máy chủ'))
          .always(() => $btn.prop('disabled', false));
    });

    $('#btnSaveReply').on('click', () => {
        const reply = String($('#reviewReply').val() || '');
        $.post(API_URL, { action: 'save_review_reply', order_id: ORDER_ID, admin_reply: reply }, res => {
            if (res?.ok) {
                toastr.success(res.msg || 'Đã lưu phản hồi');
                loadDetail();
            } else {
                toastr.error(res?.msg || 'Không thể lưu phản hồi');
            }
        }, 'json').fail(() => toastr.error('Lỗi kết nối máy chủ'));
    });

    // =========================================================================
    // 7.4 GHN INLINE HANDLERS
    // =========================================================================
    function ghnApiPost(action, extra){
        return $.ajax({
            url: API_GHN,
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify(Object.assign({ action: action }, extra || {}))
        });
    }

    $('#btnGhnSync').on('click', function(){
        const $btn = $(this).prop('disabled', true);
        const orig = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span>');
        ghnApiPost('order_sync_status', { system_order_id: ORDER_ID, order_code: ghnOrderCode })
            .done(function(res){
                if (res && res.ok) { toastr.success(res.msg || 'Đã đồng bộ GHN'); loadDetail(); }
                else toastr.error(res?.msg || 'Không đồng bộ được GHN');
            })
            .fail(function(){ toastr.error('Lỗi kết nối GHN'); })
            .always(function(){ $btn.prop('disabled', false).html(orig); });
    });

    $('#btnGhnCancel').on('click', function(){
        if (!ghnOrderCode) return;
        if (!confirm('Huỷ vận đơn GHN ' + ghnOrderCode + '?\nGHN sẽ ngừng giao đơn này.')) return;
        const $btn = $(this).prop('disabled', true);
        ghnApiPost('order_cancel', { order_code: ghnOrderCode })
            .done(function(res){
                if (res && res.ok) { toastr.success(res.msg || 'Đã huỷ vận đơn GHN'); loadDetail(); }
                else toastr.error(res?.msg || 'Không huỷ được vận đơn');
            })
            .fail(function(){ toastr.error('Lỗi kết nối GHN'); })
            .always(function(){ $btn.prop('disabled', false); });
    });

    $('#btnGhnCopy').on('click', function(){
        if (!ghnOrderCode) return;
        const done = () => { toastr.success('Đã sao chép: ' + ghnOrderCode); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(ghnOrderCode).then(done);
        } else {
            window.prompt('Sao chép mã vận đơn:', ghnOrderCode);
        }
    });

    // =========================================================================
    // 7.5 REFUND HANDLERS
    // =========================================================================
    $('#btnRefundAuto').on('click', function(){
        const $btn = $(this);
        const gateway = String($btn.data('gateway') || '').toUpperCase();
        const oid = String($btn.data('order_id') || ORDER_ID);
        if (!confirm('Gọi API ' + gateway + ' hoàn tiền tự động cho đơn #' + oid + '?\nTiền sẽ về ví/tài khoản khách trong 1-3 ngày làm việc.')) return;
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...');
        $.post(API_URL, { action: 'admin_refund_via_gateway', order_id: oid }, res => {
            if (res?.ok) {
                toastr.success(res.msg || 'Refund thành công qua ' + gateway);
                loadDetail();
            } else if (res?.status === 'pending') {
                toastr.warning((res.msg || 'Pending') + ' — đơn ở trạng thái pending, cần query lại sau.');
                $btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise me-2"></i>Hoàn tiền tự động qua <span id="btnRefundAutoGateway">' + gateway + '</span>');
            } else {
                toastr.error(res?.msg || 'Refund thất bại');
                $btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise me-2"></i>Hoàn tiền tự động qua <span id="btnRefundAutoGateway">' + gateway + '</span>');
            }
        }, 'json').fail(() => {
            toastr.error('Lỗi kết nối máy chủ');
            $btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise me-2"></i>Hoàn tiền tự động qua <span id="btnRefundAutoGateway">' + gateway + '</span>');
        });
    });

    $('#btnRefundManual').on('click', function(){
        const oid = String($(this).data('order_id') || ORDER_ID);
        const note = prompt('Xác nhận đã hoàn tiền cho đơn #' + oid + ' qua cổng thanh toán?\nGhi chú (tuỳ chọn — vd: mã giao dịch refund):', '');
        if (note === null) return;
        $.post(API_URL, { action: 'admin_mark_refunded', order_id: oid, note: note }, res => {
            if (res?.ok) { toastr.success(res.msg || 'Đã ghi nhận hoàn tiền'); loadDetail(); }
            else toastr.error(res?.msg || 'Không thể ghi nhận hoàn tiền');
        }, 'json').fail(() => toastr.error('Lỗi kết nối máy chủ'));
    });

    // ===== ĐỔI ĐỊA CHỈ MODAL =====
    $('#eaProvince').on('change', function() {
        const provinceId = $(this).val();
        $('#eaDistrict').prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
        $('#eaWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
        if (!provinceId) return;
        $.get(API_GHN, { action: 'region_districts', province_id: provinceId }, function(res) {
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            let opts = '<option value="">-- Chọn quận/huyện --</option>';
            rows.forEach(r => { opts += `<option value="${r.DistrictID}" data-name="${esc(r.DistrictName)}">${esc(r.DistrictName)}</option>`; });
            $('#eaDistrict').html(opts).prop('disabled', false);
        });
    });

    $('#eaDistrict').on('change', function() {
        const districtId = $(this).val();
        $('#eaWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
        if (!districtId) return;
        $.get(API_GHN, { action: 'region_wards', district_id: districtId }, function(res) {
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            let opts = '<option value="">-- Chọn phường/xã --</option>';
            rows.forEach(r => { opts += `<option value="${r.WardCode}" data-name="${esc(r.WardName)}">${esc(r.WardName)}</option>`; });
            $('#eaWard').html(opts).prop('disabled', false);
        });
    });

    $('#btnEditAddress').on('click', function() {
        if (!lastOrderData) return;
        let snap = {};
        try {
            snap = JSON.parse(lastOrderData.shipping_snapshot_json || '{}');
        } catch(e) {}

        const recipientName = snap.recipient_name || lastOrderData.user_name || '';
        const contactPhone  = snap.contact_phone || lastOrderData.phone || '';
        const street        = snap.street || lastOrderData.address || '';
        const savedProvinceId = String(snap.province_id || '');
        const savedDistrictId = String(snap.district_id || '');
        const savedWardCode   = String(snap.ward_code || '');

        $('#eaRecipientName').val(recipientName);
        $('#eaPhone').val(contactPhone);
        $('#eaStreet').val(street);
        $('#eaDistrict').prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
        $('#eaWard').prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');

        // Load tỉnh, sau đó restore quận/phường theo thứ tự
        $.get(API_GHN, { action: 'region_provinces' }, function(res) {
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            let opts = '<option value="">-- Chọn tỉnh/thành --</option>';
            rows.forEach(r => {
                const sel = String(r.ProvinceID) === savedProvinceId ? ' selected' : '';
                opts += `<option value="${r.ProvinceID}" data-name="${esc(r.ProvinceName)}"${sel}>${esc(r.ProvinceName)}</option>`;
            });
            $('#eaProvince').html(opts);

            if (!savedProvinceId || !savedDistrictId) return;
            // Load quận sau khi tỉnh đã chọn
            $.get(API_GHN, { action: 'region_districts', province_id: savedProvinceId }, function(res2) {
                const rows2 = Array.isArray(res2?.rows) ? res2.rows : [];
                let opts2 = '<option value="">-- Chọn quận/huyện --</option>';
                rows2.forEach(r => {
                    const sel = String(r.DistrictID) === savedDistrictId ? ' selected' : '';
                    opts2 += `<option value="${r.DistrictID}" data-name="${esc(r.DistrictName)}"${sel}>${esc(r.DistrictName)}</option>`;
                });
                $('#eaDistrict').html(opts2).prop('disabled', false);

                if (!savedWardCode) return;
                // Load phường sau khi quận đã chọn
                $.get(API_GHN, { action: 'region_wards', district_id: savedDistrictId }, function(res3) {
                    const rows3 = Array.isArray(res3?.rows) ? res3.rows : [];
                    let opts3 = '<option value="">-- Chọn phường/xã --</option>';
                    rows3.forEach(r => {
                        const sel = String(r.WardCode) === savedWardCode ? ' selected' : '';
                        opts3 += `<option value="${r.WardCode}" data-name="${esc(r.WardName)}"${sel}>${esc(r.WardName)}</option>`;
                    });
                    $('#eaWard').html(opts3).prop('disabled', false);
                });
            });
        });

        $('#editAddressModal').modal('show');
    });

    $('#btnSaveAddress').on('click', function() {
        const orderId = ORDER_ID;
        const recipientName = $('#eaRecipientName').val().trim();
        const phone         = $('#eaPhone').val().trim();
        const provinceId    = $('#eaProvince').val();
        const province      = $('#eaProvince').find(':selected').data('name') || $('#eaProvince').find(':selected').text();
        const districtId    = $('#eaDistrict').val();
        const district      = $('#eaDistrict').find(':selected').data('name') || $('#eaDistrict').find(':selected').text();
        const wardCode      = $('#eaWard').val();
        const ward          = $('#eaWard').find(':selected').data('name') || $('#eaWard').find(':selected').text();
        const street        = $('#eaStreet').val().trim();

        if (!recipientName) { toastr.warning('Vui lòng nhập họ tên người nhận'); return; }
        if (!phone || phone.replace(/[^0-9]/g, '').length < 9) { toastr.warning('Số điện thoại không hợp lệ'); return; }
        if (!provinceId) { toastr.warning('Vui lòng chọn Tỉnh/Thành phố'); return; }
        if (!districtId) { toastr.warning('Vui lòng chọn Quận/Huyện'); return; }
        if (!wardCode)   { toastr.warning('Vui lòng chọn Phường/Xã'); return; }
        if (!street)     { toastr.warning('Vui lòng nhập địa chỉ chi tiết'); return; }

        $('#btnSaveAddressText').text('Đang lưu...');
        $('#btnSaveAddressSpin').removeClass('d-none');
        $('#btnSaveAddress').prop('disabled', true);

        $.post(API_URL, {
            action: 'admin_update_address',
            order_id: orderId,
            recipient_name: recipientName,
            contact_phone: phone,
            province_id: provinceId,
            province: province,
            district_id: districtId,
            district: district,
            ward_code: wardCode,
            ward: ward,
            street: street
        }).done(res => {
            if (res?.ok) {
                toastr.success(res.msg || 'Đã cập nhật địa chỉ giao hàng');
                $('#editAddressModal').modal('hide');
                loadDetail();
            } else {
                toastr.error(res?.msg || 'Không thể cập nhật địa chỉ');
            }
        }).fail(() => {
            toastr.error('Lỗi kết nối, vui lòng thử lại');
        }).always(() => {
            $('#btnSaveAddressText').text('Lưu địa chỉ');
            $('#btnSaveAddressSpin').addClass('d-none');
            $('#btnSaveAddress').prop('disabled', false);
        });
    });

    // Toggle customer edit panel
    $('#btnEditCustomer').on('click', function() {
        $('#customerEditPanel').slideToggle(200);
    });

    // Copy order ID button
    $('#btnCopyOrderId').on('click', function() {
        const text = $('#previewOrderId').text();
        if (text && text !== '--') {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => toastr.success('Đã sao chép mã đơn hàng'));
            } else {
                toastr.success('Mã đơn hàng: ' + text);
            }
        }
    });

    // Sync input values to previews in real-time
    $('#e_user_name').on('input change', function() {
        $('#previewRecipientName').text($(this).val() || '—');
    });
    $('#e_phone').on('input change', function() {
        const val = $(this).val() || '';
        $('#previewRecipientPhone').text(val || '—');
        updateContactLinks(val);
    });
    $('#e_address').on('input change', function() {
        $('#previewRecipientAddress').text($(this).val() || '—');
    });

    // =========================================================================
    // 7.6 SỬA SẢN PHẨM TRONG ĐƠN
    // =========================================================================
    let eiWorkItems = []; // bản sao làm việc trong modal
    let eiSearchTimer = null;
    let eiCatLoaded = false;
    let eiBrowse = { catId: 0, catName: '', product: null, groupId: null }; // luồng duyệt danh mục

    function eiCloneItems() {
        return orderItems.map(it => {
            const clone = Object.assign({}, it);
            if (clone.is_gift) clone.price = 0;
            return clone;
        });
    }

    function eiCalcSubtotal() {
        return eiWorkItems.reduce((s, it) => s + (it.is_gift ? 0 : Number(it.price || 0) * Number(it.qty || 1)), 0);
    }

    function fmtVndRaw(n) {
        return n ? new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ' : '0 đ';
    }

    // Format số nguyên với dấu chấm phân cách nghìn: 10000 -> "10.000"
    function fmtThousand(n) {
        const v = Math.round(Math.abs(Number(n) || 0));
        return v.toLocaleString('vi-VN'); // dùng '.' cho nghìn theo locale vi-VN
    }
    // Parse chuỗi đã format về số nguyên: chỉ giữ chữ số ("10.000" hoặc "10000 đ" -> 10000)
    function parseThousand(s) {
        const digits = String(s == null ? '' : s).replace(/\D/g, '');
        return digits ? parseInt(digits, 10) : 0;
    }

    function eiRenderItems() {
        const $box = $('#eiItemList');
        if (!eiWorkItems.length) {
            $box.html('<div class="text-muted small text-center py-4">Chưa có sản phẩm nào.</div>');
            $('#eiSubtotalPreview').text('0 đ');
            return;
        }
        const rows = eiWorkItems.map((it, idx) => {
            const isGift = !!it.is_gift;
            const img = normalizeImgUrl(it.variant_image_url || it.image_url || '');
            const variantOpts = eiGetVariantOpts(it);
            const variantSel = variantOpts.length > 1
                ? `<select class="form-select form-select-sm ei-variant-sel" data-idx="${idx}" style="max-width:160px;">
                    ${variantOpts.map(v => `<option value="${esc(v.name)}" data-vid="${v.vid}" data-price="${v.price}" data-img="${esc(v.img)}" ${it.variant === v.name ? 'selected' : ''}>${esc(v.name)}</option>`).join('')}
                  </select>`
                : `<span class="badge bg-light text-secondary border" style="font-size:0.72rem;">${esc(it.variant || 'Mặc định')}</span>`;
            return `
                <div class="d-flex align-items-center gap-2 p-2 border-bottom ei-item-row" data-idx="${idx}">
                    <img src="${esc(img)}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #eee;" onerror="this.src='${esc(FALLBACK_IMG)}'">
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-semibold text-truncate_" title="${esc(it.name)}">${esc(it.name)}</div>
                        <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                            ${variantSel}
                            ${isGift ? '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.7rem;"><i class="bi bi-gift me-1"></i>Quà tặng</span>' : ''}
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                        ${isGift ? '' : `<input type="text" inputmode="numeric" class="form-control form-control-sm text-end ei-price-inp" data-idx="${idx}" value="${fmtThousand(it.price || 0)}" style="width:110px;" placeholder="Giá">`}
                        <div class="input-group input-group-sm" style="width:110px;">
                            <button class="btn btn-outline-secondary ei-qty-dec" data-idx="${idx}" type="button">−</button>
                            <input type="number" class="form-control text-center ei-qty-inp" data-idx="${idx}" value="${it.qty || 1}" min="1" max="999" style="width:44px;">
                            <button class="btn btn-outline-secondary ei-qty-inc" data-idx="${idx}" type="button">+</button>
                        </div>
                        <button class="btn btn-sm btn-link text-danger p-0 ei-remove-btn" data-idx="${idx}" type="button" title="Xóa"><i class="bi bi-trash3"></i></button>
                    </div>
                </div>`;
        }).join('');
        $box.html(rows);
        $('#eiSubtotalPreview').text(fmtVndRaw(eiCalcSubtotal()));
    }

    function eiGetVariantOpts(it) {
        const pid = Number(it.product_id || it.id || 0);
        const meta = pid > 0 ? (productMetaCache[pid] || {}) : {};
        if (!Array.isArray(meta.variants) || !meta.variants.length) return [];
        return meta.variants.map(v => ({
            vid: Number(v.variant_id || 0),
            name: String(v.variant_name || ''),
            price: Number(v.price || 0),
            img: String(v.image_url || ''),
        }));
    }

    // Delegate events cho item list trong modal
    $('#eiItemList').on('change', '.ei-variant-sel', function() {
        const idx = parseInt($(this).data('idx'), 10);
        const selectedOpt = $(this).find(':selected');
        const newVariant = $(this).val();
        const newPrice = parseFloat(selectedOpt.data('price') || 0);
        const newImg = String(selectedOpt.data('img') || '');
        const newVid = parseInt(selectedOpt.data('vid') || 0, 10);
        if (!eiWorkItems[idx]) return;
        eiWorkItems[idx].variant = newVariant;
        eiWorkItems[idx].variant_id = newVid;
        if (!eiWorkItems[idx].is_gift && newPrice > 0) eiWorkItems[idx].price = newPrice;
        eiWorkItems[idx].variant_image_url = newImg;
        eiRenderItems();
    });

    $('#eiItemList').on('input', '.ei-price-inp', function() {
        const idx = parseInt($(this).data('idx'), 10);
        const el = this;
        const val = parseThousand(el.value);
        if (eiWorkItems[idx]) { eiWorkItems[idx].price = val; }

        // Reformat hiển thị "10.000" và giữ vị trí con trỏ theo số chữ số bên phải
        const digitsRight = (el.value.slice(el.selectionStart).replace(/\D/g, '')).length;
        const formatted = val ? fmtThousand(val) : '';
        el.value = formatted;
        let pos = formatted.length;
        let seen = 0;
        for (let i = formatted.length - 1; i >= 0; i--) {
            if (seen >= digitsRight) { pos = i + 1; break; }
            if (/\d/.test(formatted[i])) seen++;
            if (i === 0) pos = 0;
        }
        try { el.setSelectionRange(pos, pos); } catch (e) {}

        $('#eiSubtotalPreview').text(fmtVndRaw(eiCalcSubtotal()));
    });

    $('#eiItemList').on('click', '.ei-qty-dec', function() {
        const idx = parseInt($(this).data('idx'), 10);
        if (!eiWorkItems[idx]) return;
        eiWorkItems[idx].qty = Math.max(1, (eiWorkItems[idx].qty || 1) - 1);
        eiRenderItems();
    });
    $('#eiItemList').on('click', '.ei-qty-inc', function() {
        const idx = parseInt($(this).data('idx'), 10);
        if (!eiWorkItems[idx]) return;
        eiWorkItems[idx].qty = Math.min(999, (eiWorkItems[idx].qty || 1) + 1);
        eiRenderItems();
    });
    $('#eiItemList').on('change', '.ei-qty-inp', function() {
        const idx = parseInt($(this).data('idx'), 10);
        const v = Math.max(1, Math.min(999, parseInt($(this).val(), 10) || 1));
        if (eiWorkItems[idx]) { eiWorkItems[idx].qty = v; $(this).val(v); }
        $('#eiSubtotalPreview').text(fmtVndRaw(eiCalcSubtotal()));
    });
    $('#eiItemList').on('click', '.ei-remove-btn', function() {
        const idx = parseInt($(this).data('idx'), 10);
        eiWorkItems.splice(idx, 1);
        eiRenderItems();
    });

    // Tìm kiếm sản phẩm
    $('#eiSearchInput').on('input', function() {
        clearTimeout(eiSearchTimer);
        const q = $(this).val().trim();
        // Dùng tìm theo tên -> thoát luồng duyệt danh mục để không lẫn
        eiBrowse = { catId: 0, catName: '', product: null, groupId: null };
        $('#eiCategorySel').val('');
        $('#eiBrowseCrumb').addClass('d-none');
        if (q.length < 1) {
            $('#eiSearchResults').html('<div class="text-muted small text-center py-4"><i class="bi bi-search me-1"></i>Chọn danh mục hoặc gõ tên để tìm sản phẩm</div>');
            return;
        }
        $('#eiSearchSpinner').show();
        eiSearchTimer = setTimeout(() => {
            $.get(API_URL, { ajax: 'product_search', q: q }, res => {
                $('#eiSearchSpinner').hide();
                if (!res?.ok || !res.rows?.length) {
                    $('#eiSearchResults').html('<div class="text-muted small text-center py-4">Không tìm thấy sản phẩm nào.</div>');
                    return;
                }
                // Lưu vào cache
                res.rows.forEach(p => {
                    if (!productMetaCache[p.id]) {
                        productMetaCache[p.id] = {
                            product_name: p.product_name,
                            image_url: p.image_url,
                            variants: p.variants || []
                        };
                    }
                });
                const html = res.rows.map(p => {
                    const img = normalizeImgUrl(p.image_url || '');
                    const variants = p.variants || [];
                    const variantsHtml = variants.map(v =>
                        `<button type="button" class="btn btn-sm btn-outline-secondary ei-add-variant rounded-pill"
                            data-pid="${p.id}" data-pname="${esc(p.product_name)}" data-pimg="${esc(p.image_url || '')}"
                            data-vid="${v.variant_id}" data-vname="${esc(v.variant_name)}" data-price="${v.price}"
                            data-vimg="${esc(v.image_url || '')}" style="font-size:0.72rem;">
                            ${esc(v.variant_name)} · ${fmtVndRaw(v.price)}
                        </button>`
                    ).join('');
                    const addNoVariantBtn = variants.length === 0
                        ? `<button type="button" class="btn btn-sm btn-primary ei-add-no-variant rounded-pill"
                                data-pid="${p.id}" data-pname="${esc(p.product_name)}" data-pimg="${esc(p.image_url || '')}">
                                <i class="bi bi-plus me-1"></i>Thêm
                           </button>` : '';
                    return `
                        <div class="d-flex align-items-start gap-2 p-2 border-bottom">
                            <img src="${esc(img)}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #eee;flex-shrink:0;" onerror="this.src='${esc(FALLBACK_IMG)}'">
                            <div class="flex-grow-1 min-w-0">
                                <div class="small fw-semibold text-truncate_ mb-1" title="${esc(p.product_name)}">${esc(p.product_name)}</div>
                                <div class="d-flex flex-wrap gap-1">${variantsHtml}${addNoVariantBtn}</div>
                            </div>
                        </div>`;
                }).join('');
                $('#eiSearchResults').html(html);
            }, 'json').fail(() => {
                $('#eiSearchSpinner').hide();
                $('#eiSearchResults').html('<div class="text-danger small text-center py-4">Lỗi kết nối.</div>');
            });
        }, 350);
    });

    function eiAddItem(pid, pname, pimg, vname, price, vimg, isGift, vid) {
        const exist = eiWorkItems.find(it => Number(it.product_id || it.id || 0) === pid && (it.variant || '') === vname && !!it.is_gift === !!isGift);
        if (exist) {
            exist.qty = Math.min(999, (exist.qty || 1) + 1);
        } else {
            eiWorkItems.push({
                id: pid, product_id: pid,
                name: pname,
                variant: vname,
                variant_id: Number(vid || 0),
                price: isGift ? 0 : price,
                qty: 1,
                is_gift: isGift ? 1 : 0,
                is_combo: 0,
                image_url: pimg,
                variant_image_url: vimg
            });
        }
        eiRenderItems();
        toastr.success((isGift ? '[Quà tặng] ' : '') + 'Đã thêm: ' + pname + (vname ? ' · ' + vname : ''));
    }

    // ============================================================
    // DUYỆT: Danh mục -> Sản phẩm -> Nhóm (nếu có) -> Phân loại
    // (state eiCatLoaded / eiBrowse khai báo ở đầu khối edit-items)
    // ============================================================
    function eiLoadCategories() {
        if (eiCatLoaded) return;
        $.get(API_URL, { ajax: 'category_list' }, res => {
            if (!res?.ok) return;
            const $sel = $('#eiCategorySel');
            (res.rows || []).forEach(c => {
                $sel.append(`<option value="${c.id}">${esc(c.name)}</option>`);
            });
            eiCatLoaded = true;
        }, 'json');
    }

    function eiRenderCrumb() {
        const $crumb = $('#eiBrowseCrumb');
        if (!eiBrowse.catId) { $crumb.addClass('d-none'); return; }
        $crumb.removeClass('d-none');
        const $cp = $('#eiCrumbProduct'), $cg = $('#eiCrumbGroup');
        if (eiBrowse.product) {
            $cp.removeClass('d-none').find('a').text(eiBrowse.product.name);
        } else { $cp.addClass('d-none'); }
        if (eiBrowse.product && eiBrowse.groupId != null) {
            const g = (eiBrowse.product.groups || []).find(x => Number(x.id) === Number(eiBrowse.groupId));
            $cg.removeClass('d-none').find('span').text(g ? g.name : 'Phân loại');
        } else { $cg.addClass('d-none'); }
    }

    function eiRenderCategoryProducts(rows) {
        eiBrowse.product = null; eiBrowse.groupId = null;
        eiRenderCrumb();
        if (!rows || !rows.length) {
            $('#eiSearchResults').html('<div class="text-muted small text-center py-4">Danh mục này chưa có sản phẩm.</div>');
            return;
        }
        const html = rows.map(p => `
            <div class="d-flex align-items-center gap-2 p-2 border-bottom ei-pick-product" data-pid="${p.id}" style="cursor:pointer;">
                <img src="${esc(normalizeImgUrl(p.image_url||''))}" style="width:38px;height:38px;object-fit:cover;border-radius:6px;border:1px solid #eee;flex-shrink:0;" onerror="this.src='${esc(FALLBACK_IMG)}'">
                <div class="flex-grow-1 min-w-0 small fw-semibold text-truncate_" title="${esc(p.product_name)}">${esc(p.product_name)}</div>
                <i class="bi bi-chevron-right text-muted"></i>
            </div>`).join('');
        $('#eiSearchResults').html(html);
    }

    function eiRenderGroups(prod) {
        eiBrowse.product = prod; eiBrowse.groupId = null;
        eiRenderCrumb();
        const groups = prod.groups || [];
        // Không có nhóm -> hiện luôn phân loại
        if (!groups.length) { eiRenderVariants(prod, null); return; }
        const html = groups.map(g => {
            const cnt = (prod.variants || []).filter(v => Number(v.group_id) === Number(g.id)).length;
            return `<button type="button" class="btn btn-sm btn-outline-primary ei-pick-group rounded-pill m-1" data-gid="${g.id}">
                        ${esc(g.name)} <span class="badge bg-light text-secondary ms-1">${cnt}</span>
                    </button>`;
        }).join('');
        // Phân loại không thuộc nhóm nào (nếu có)
        const ungrouped = (prod.variants || []).filter(v => !v.group_id);
        const ungroupedBtn = ungrouped.length
            ? `<button type="button" class="btn btn-sm btn-outline-secondary ei-pick-group rounded-pill m-1" data-gid="0">Khác <span class="badge bg-light text-secondary ms-1">${ungrouped.length}</span></button>`
            : '';
        $('#eiSearchResults').html(`<div class="p-2"><div class="text-muted small mb-1">Chọn nhóm phân loại:</div>${html}${ungroupedBtn}</div>`);
    }

    function eiRenderVariants(prod, groupId) {
        eiBrowse.product = prod; eiBrowse.groupId = groupId;
        eiRenderCrumb();
        let variants = prod.variants || [];
        if (groupId != null) variants = variants.filter(v => Number(v.group_id) === Number(groupId));
        if (!variants.length) {
            $('#eiSearchResults').html('<div class="text-muted small text-center py-4">Không có phân loại.</div>');
            return;
        }
        const btns = variants.map(v => `
            <button type="button" class="btn btn-sm btn-outline-secondary ei-add-variant rounded-pill m-1"
                data-pid="${prod.id}" data-pname="${esc(prod.name)}" data-pimg="${esc(prod.image_url||'')}"
                data-vid="${v.variant_id}" data-vname="${esc(v.variant_name)}" data-price="${v.price}"
                data-vimg="${esc(v.image_url||'')}" style="font-size:0.74rem;">
                ${esc(v.variant_name)} · ${fmtVndRaw(v.price)}
            </button>`).join('');
        $('#eiSearchResults').html(`<div class="p-2"><div class="text-muted small mb-1">Chọn phân loại để thêm:</div>${btns}</div>`);
    }

    function eiOpenProduct(pid) {
        $('#eiSearchSpinner').show();
        $.get(API_URL, { ajax: 'product_variants', product_id: pid }, res => {
            $('#eiSearchSpinner').hide();
            if (!res?.ok) { toastr.error('Không tải được phân loại'); return; }
            const prod = { id: pid, name: res.product_name || '', image_url: res.image_url || '', groups: res.groups || [], variants: res.variants || [] };
            productMetaCache[pid] = productMetaCache[pid] || {};
            productMetaCache[pid].product_name = prod.name;
            productMetaCache[pid].image_url = prod.image_url;
            productMetaCache[pid].variants = prod.variants;
            eiRenderGroups(prod);
        }, 'json').fail(() => { $('#eiSearchSpinner').hide(); toastr.error('Lỗi kết nối.'); });
    }

    // Chọn danh mục
    $('#eiCategorySel').on('change', function() {
        const catId = parseInt(this.value, 10) || 0;
        eiBrowse.catId = catId;
        eiBrowse.catName = $(this).find('option:selected').text();
        if (!catId) { eiBrowse = { catId: 0, catName: '', product: null, groupId: null }; eiRenderCrumb(); $('#eiSearchResults').html('<div class="text-muted small text-center py-4"><i class="bi bi-search me-1"></i>Chọn danh mục hoặc gõ tên để tìm sản phẩm</div>'); return; }
        $('#eiSearchInput').val('');
        $('#eiSearchSpinner').show();
        $.get(API_URL, { ajax: 'products_by_category', category_id: catId }, res => {
            $('#eiSearchSpinner').hide();
            eiRenderCategoryProducts(res?.rows || []);
        }, 'json').fail(() => { $('#eiSearchSpinner').hide(); $('#eiSearchResults').html('<div class="text-danger small text-center py-4">Lỗi kết nối.</div>'); });
    });

    // Chọn sản phẩm trong danh mục
    $('#eiSearchResults').on('click', '.ei-pick-product', function() {
        eiOpenProduct(parseInt($(this).data('pid'), 10));
    });
    // Chọn nhóm
    $('#eiSearchResults').on('click', '.ei-pick-group', function() {
        if (!eiBrowse.product) return;
        const gid = parseInt($(this).data('gid'), 10);
        eiRenderVariants(eiBrowse.product, gid === 0 ? 0 : gid);
    });
    // Breadcrumb quay lại
    $('#eiBrowseCrumb').on('click', '[data-ei-back]', function(e) {
        e.preventDefault();
        const to = $(this).data('ei-back');
        if (to === 'category') {
            $('#eiSearchSpinner').show();
            $.get(API_URL, { ajax: 'products_by_category', category_id: eiBrowse.catId }, res => {
                $('#eiSearchSpinner').hide();
                eiRenderCategoryProducts(res?.rows || []);
            }, 'json');
        } else if (to === 'product' && eiBrowse.product) {
            eiRenderGroups(eiBrowse.product);
        }
    });

    let eiAddingGift = false;
    $('#btnAddGift').on('click', function() {
        eiAddingGift = true;
        $('#eiSearchInput').val('').trigger('focus');
        toastr.info('Tìm và chọn sản phẩm tặng kèm bên phải', '', {timeOut: 2500});
    });

    // Unified handler cho thêm sản phẩm / quà tặng
    $('#eiSearchResults').on('click', '.ei-add-variant', function() {
        const asGift = eiAddingGift; eiAddingGift = false;
        const pid = parseInt($(this).data('pid'), 10);
        const pname = String($(this).data('pname') || '');
        const pimg = String($(this).data('pimg') || '');
        const vname = String($(this).data('vname') || '');
        const price = asGift ? 0 : parseFloat($(this).data('price') || 0);
        const vimg = String($(this).data('vimg') || '');
        const vid = parseInt($(this).data('vid') || 0, 10);
        eiAddItem(pid, pname, pimg, vname, price, vimg, asGift ? 1 : 0, vid);
    });
    $('#eiSearchResults').on('click', '.ei-add-no-variant', function() {
        const asGift = eiAddingGift; eiAddingGift = false;
        const pid = parseInt($(this).data('pid'), 10);
        const pname = String($(this).data('pname') || '');
        const pimg = String($(this).data('pimg') || '');
        eiAddItem(pid, pname, pimg, '', 0, '', asGift ? 1 : 0, 0);
    });

    // Mở modal
    $('#btnEditItems').on('click', function() {
        eiWorkItems = eiCloneItems();
        eiAddingGift = false;
        eiBrowse = { catId: 0, catName: '', product: null, groupId: null };
        $('#eiSearchInput').val('');
        $('#eiCategorySel').val('');
        $('#eiBrowseCrumb').addClass('d-none');
        $('#eiSearchResults').html('<div class="text-muted small text-center py-4"><i class="bi bi-search me-1"></i>Chọn danh mục hoặc gõ tên để tìm sản phẩm</div>');
        eiLoadCategories();
        eiRenderItems();
        $('#editItemsModal').modal('show');
    });

    // Xác nhận: cập nhật orderItems từ eiWorkItems + tính lại tổng
    $('#btnConfirmItems').on('click', function() {
        if (!eiWorkItems.length) {
            if (!confirm('Danh sách sản phẩm trống. Bạn có chắc muốn xóa hết sản phẩm?')) return;
        }
        // Chốt giá trực tiếp từ ô input đang hiển thị (đảm bảo lấy đúng giá vừa gõ,
        // không phụ thuộc thời điểm sự kiện 'input' đã chạy hay chưa).
        $('#eiItemList .ei-price-inp').each(function() {
            const idx = parseInt($(this).data('idx'), 10);
            if (eiWorkItems[idx] && !eiWorkItems[idx].is_gift) {
                eiWorkItems[idx].price = parseThousand(this.value);
            }
        });
        orderItems.length = 0;
        eiWorkItems.forEach(it => {
            const normalized = Object.assign({}, it);
            if (normalized.is_gift) normalized.price = 0;
            orderItems.push(normalized);
        });
        renderProductList();
        $('#detailItemsCount').text(orderItems.length + ' sản phẩm');

        // Cập nhật subtotal từ items
        const newSubtotal = orderItems.reduce((s, it) => s + (it.is_gift ? 0 : Number(it.price || 0) * Number(it.qty || 1)), 0);
        setCurrencyValue('subtotal', newSubtotal);
        const shippingFee = parseInt($('#e_shipping_fee').val(), 10) || 0;
        setCurrencyValue('total_amount', newSubtotal + shippingFee);

        $('#editItemsModal').modal('hide');
        toastr.success('Đã cập nhật danh sách sản phẩm. Nhấn "Lưu chỉnh sửa" để lưu vào đơn.');
    });

    // =========================================================================
    // 8. INITIALIZATION
    // =========================================================================
    loadDetail();
});
</script>
<?php endif; ?>
