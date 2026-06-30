<?php
$orderId = trim((string)($_GET['order_id'] ?? ''));
// Zalo: if SOCIAL_ZALO is configured, use it, else fallback to site_hotline/hotline
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
    $zaloHref = 'https://zalo.me/' . ($hotline ?? '');
}
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
        <a href="<?= h($baseUrl) ?>/order" class="btn btn-primary px-4 rounded-pill">Quay lại danh sách</a>
    </div>
<?php elseif (!$orderExists): ?>
    <div class="text-center py-5">
        <div class="mb-3"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i></div>
        <h4 class="fw-bold">Đơn hàng không tồn tại</h4>
        <p class="text-muted">Đơn hàng với mã <strong><?= h($orderId) ?></strong> không tìm thấy trong hệ thống.</p>
        <a href="<?= h($baseUrl) ?>/order" class="btn btn-primary px-4 rounded-pill">Quay lại danh sách</a>
    </div>
<?php else: ?>
    <style>
        .ea-suggest-list {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1080;
            background: #fff;
            border: 1px solid #e3e6ea;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, .12);
            max-height: 260px;
            overflow-y: auto;
            padding: 6px;
        }
        .ea-suggest-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        .ea-suggest-item:hover {
            background: #f3f6f4;
        }
        .ea-suggest-item-main {
            display: block;
            font-weight: 600;
            font-size: .9rem;
            color: #1f2937;
        }
        .ea-suggest-item-sub {
            display: block;
            font-size: .8rem;
            color: #6b7280;
            margin-top: 2px;
        }
    </style>
    <div id="orderDetailPage" class="container-fluid py-4 <?= $orderId === '' ? 'd-none' : '' ?>" data-order-id="<?= h($orderId) ?>">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="h3 fw-bold mb-0">Đơn hàng <span id="detailOrderCode" class="text-primary">#...</span></h2>
                <p class="text-muted small mb-0" id="detailTime"></p>
            </div>
            <a class="btn btn-primary" href="<?= h($baseUrl) ?>/order">
                <i class="bi bi-list-check me-2"></i>Danh sách đơn
            </a>
        </div>
        <!-- Progress Bar -->
        <div class="card mb-4" id="orderProgressBar">
            <div class="card-body_ ">
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
                    <div class="step-item" data-step="received">
                        <div class="step-icon"><i class="bi bi-bag-check"></i></div>
                        <div class="step-label">Đã nhận hàng</div>
                    </div>
                    <div class="step-item" data-step="completed">
                        <div class="step-icon"><i class="bi bi-star"></i></div>
                        <div class="step-label">Hoàn thành</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Main Column -->
            <div class="col-lg-8">
                <!-- Products Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Sản phẩm (<span id="detailItemsCount">0</span>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="detailItems">
                            <!-- Items populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Shipping & Address Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-truck me-1 text-primary"></i>Giao hàng</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary d-none" id="btnEditAddress" style="border-radius:7px;font-size:0.75rem;padding:2px 9px;">
                            <i class="bi bi-pencil me-1"></i>Đổi địa chỉ
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-borderless mb-0" style="font-size:0.82rem;">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-3 pe-2 py-2 align-top" style="width:110px;white-space:nowrap;">Người nhận</td>
                                    <td class="fw-medium py-2 pe-3" id="detailAddress"></td>
                                </tr>
                                <tr class="border-top">
                                    <td class="text-muted ps-3 pe-2 py-2 align-middle" style="white-space:nowrap;">Vận chuyển</td>
                                    <td class="fw-medium py-2 pe-3" id="detailShippingMethod"></td>
                                </tr>
                                <tr class="border-top d-none" id="detailEtaWrapper">
                                    <td class="text-muted ps-3 pe-2 py-2 align-middle" style="white-space:nowrap;">Dự kiến</td>
                                    <td class="fw-medium py-2 pe-3 text-success" id="detailEta"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Thông tin đơn hàng (Merged: Overview + Payment Totals + Invoice) -->
                <div class="card shadow-sm border-0" id="detailOverviewSection">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-2"></i>Thông tin đơn hàng</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary d-none" id="btnChangePayment" style="border-radius:8px;font-size:0.8rem;padding:3px 10px;">
                            <i class="bi bi-arrow-repeat me-1"></i>Đổi thanh toán
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="detailOverview">
                            <!-- Meta populated by JS -->
                        </div>

                        <!-- Chi tiết thanh toán -->
                        <div class="mt-3 pt-3 border-top border-light">
                            <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2"></i>Chi tiết thanh toán</h6>
                            <div id="detailTotals">
                                <!-- Totals populated by JS -->
                            </div>
                        </div>

                        <!-- Continue payment button container -->
                        <div id="detailPaymentActionContainer" class="mt-3 d-none">
                            <button type="button" class="btn btn-primary w-100 fw-semibold" id="btnContinuePayment" style="border-radius:8px;">
                                <i class="bi bi-wallet2 me-1"></i>Tiếp tục thanh toán
                            </button>
                        </div>

                        <!-- Invoice Section -->
                        <div id="detailInvoiceSection" class="mt-3 pt-3 border-top border-light" style="display:none;">
                            <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-text me-2"></i>Hóa đơn GTGT</h6>
                            <div id="detailInvoice" class="small"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="col-lg-4">
                <!-- Status Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-list-ol me-2"></i>Trạng thái</h6>
                        <span class="badge rounded-pill" id="detailStatus">...</span>
                    </div>
                    <div class="card-body">
                        <div id="detailShippingTimeline" class="status-timeline">
                            <!-- Timeline populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── BOTTOM ACTIONS ── -->
        <div class="order-header-actions  mt-4" id="userOrderActionBar">
            <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancelOrder">
                <i class="bi bi-x-circle me-2"></i>Hủy đơn hàng
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning" id="btnRequestReturn">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Trả hàng
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" id="btnConfirmReceived">
                <i class="bi bi-check2-circle me-2"></i>Đã nhận
            </button>
            <!-- <button type="button" class="btn btn-sm btn-outline-primary" id="btnReorder">
            <i class="bi bi-arrow-repeat me-2"></i>Mua lại
            </button>
            <a class="btn btn-sm btn-outline-success d-inline-flex align-items-center rounded-pill px-3" id="btnContactSupport" href="<?= h($zaloHref) ?>" target="_blank">
                <i class="bi bi-chat me-2"></i>Liên hệ hỗ trợ
            </a> -->
        </div>
    </div>

    <!-- Modal Yêu Cầu Hủy Đơn -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-x-circle me-2 text-danger"></i>Yêu cầu hủy đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted small mb-3">Yêu cầu của bạn sẽ được gửi tới admin để xét duyệt. Đơn hàng sẽ chỉ được hủy sau khi admin xác nhận.</p>
                    <div class="d-flex flex-column gap-2" id="cancelReasonOptions">
                        <div class="form-check p-0 m-0">
                            <input class="btn-check" type="radio" name="cancelReason" id="reason1" value="Tôi muốn đổi địa chỉ giao hàng, số điện thoại">
                            <label class="btn btn-sm btn-outline-primary w-100 text-start py-2 px-3 rounded-3" for="reason1">Tôi muốn đổi địa chỉ giao hàng, SĐT</label>
                        </div>
                        <div class="form-check p-0 m-0">
                            <input class="btn-check" type="radio" name="cancelReason" id="reason2" value="Tôi không còn nhu cầu nữa">
                            <label class="btn btn-sm btn-outline-primary w-100 text-start py-2 px-3 rounded-3" for="reason2">Tôi không còn nhu cầu nữa</label>
                        </div>
                        <div class="form-check p-0 m-0">
                            <input class="btn-check" type="radio" name="cancelReason" id="reason3" value="Tôi muốn thay đổi đơn hàng hoặc áp dụng mã giảm giá">
                            <label class="btn btn-sm btn-outline-primary w-100 text-start py-2 px-3 rounded-3" for="reason3">Tôi muốn thay đổi đơn hàng / mã giảm giá</label>
                        </div>
                        <div class="form-check p-0 m-0">
                            <input class="btn-check" type="radio" name="cancelReason" id="reason4" value="Tôi muốn thay đổi phương thức thanh toán">
                            <label class="btn btn-sm btn-outline-primary w-100 text-start py-2 px-3 rounded-3" for="reason4">Tôi muốn thay đổi phương thức thanh toán</label>
                        </div>
                        <div class="form-check p-0 m-0">
                            <input class="btn-check" type="radio" name="cancelReason" id="reasonOther" value="__other__">
                            <label class="btn btn-sm btn-outline-primary w-100 text-start py-2 px-3 rounded-3" for="reasonOther">Lý do khác</label>
                        </div>
                    </div>
                    <div id="cancelReasonOtherWrapper" class="mt-3 d-none">
                        <textarea id="cancelReasonOtherText" class="form-control border-primary-subtle" rows="3" placeholder="Vui lòng nhập lý do cụ thể..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-danger px-4 rounded-pill fw-semibold" id="btnConfirmCancelSubmit">Gửi yêu cầu hủy</button>
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
                    <p class="text-muted small mb-3">Chỉ có thể đổi địa chỉ khi đơn hàng đang ở trạng thái <strong>Chờ xác nhận</strong> hoặc <strong>Đang chuẩn bị hàng</strong>.</p>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold small">Họ tên người nhận <span class="text-danger">*</span></label>
                            <input type="text" id="eaRecipientName" class="form-control" placeholder="Nguyễn Văn A" maxlength="120">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold small">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" id="eaPhone" class="form-control" placeholder="0901234567" maxlength="20">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Địa chỉ chi tiết (số nhà, tên đường) <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <input type="text" id="eaStreet" class="form-control" placeholder="Gõ địa chỉ để gợi ý, vd: 380 Lê Văn Lương" maxlength="255" autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" id="eaGpsBtn" title="Dùng vị trí hiện tại"><i class="bi bi-geo-alt"></i></button>
                                </div>
                                <div id="eaSearchList" class="ea-suggest-list d-none"></div>
                            </div>
                            <div class="form-text">Gõ rồi chọn một gợi ý để tự động điền Tỉnh/Quận/Phường theo hệ thống vận chuyển.</div>
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

    <!-- Modal Đổi Phương Thức Thanh Toán -->
    <div class="modal fade" id="changePaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Đổi phương thức thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted small mb-3">Chỉ có thể đổi phương thức khi đơn hàng <strong>chưa được thanh toán</strong>. Với ví điện tử, hệ thống sẽ tạo lại liên kết thanh toán mới.</p>
                    <div class="d-flex flex-column gap-2 text-center" id="cpMethodOptions">
                        <!-- Options populated by JS -->
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary px-4 rounded-pill fw-semibold" id="btnConfirmChangePayment">
                        <span id="btnCpText">Xác nhận</span>
                        <span id="btnCpSpin" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Yêu Cầu Trả Hàng (multi-step) -->
    <div class="modal fade" id="returnOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-counterclockwise me-2 text-warning"></i>Yêu cầu trả hàng / hoàn tiền</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <!-- Step indicator -->
                    <div class="d-flex align-items-center justify-content-center mb-4 px-2" id="roStepIndicator">
                        <div class="d-flex align-items-center gap-0">
                            <div class="ro-step-item d-flex flex-column align-items-center" data-step="1">
                                <div class="ro-step-circle rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;font-size:14px;">1</div>
                                <div class="ro-step-label mt-1 small fw-semibold" style="white-space:nowrap;">Lý do</div>
                            </div>
                            <div class="ro-step-line flex-grow-1 mx-2" style="height:2px;width:60px;"></div>
                            <div class="ro-step-item d-flex flex-column align-items-center" data-step="2">
                                <div class="ro-step-circle rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;font-size:14px;">2</div>
                                <div class="ro-step-label mt-1 small fw-semibold" style="white-space:nowrap;">Chi tiết</div>
                            </div>
                            <div class="ro-step-line flex-grow-1 mx-2" style="height:2px;width:60px;"></div>
                            <div class="ro-step-item d-flex flex-column align-items-center" data-step="3">
                                <div class="ro-step-circle rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;font-size:14px;">3</div>
                                <div class="ro-step-label mt-1 small fw-semibold" style="white-space:nowrap;">Hoàn tiền</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Lý do -->
                    <div id="roStep1" class="ro-step-panel">
                        <p class="text-muted small mb-3">Chọn lý do phù hợp nhất với tình trạng sản phẩm của bạn.</p>
                        <label class="form-label fw-semibold small">Lý do trả hàng <span class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2" id="returnReasonOptions"></div>
                    </div>

                    <!-- Step 2: Mô tả + Media -->
                    <div id="roStep2" class="ro-step-panel d-none">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Mô tả vấn đề</label>
                            <textarea id="roDescription" class="form-control" rows="3" maxlength="2000" placeholder="Mô tả chi tiết tình trạng sản phẩm..."></textarea>
                        </div>
                        <div class="mb-1">
                            <label class="form-label fw-semibold small">Ảnh/Video sản phẩm lỗi <span class="text-muted fw-normal">(không bắt buộc)</span></label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="roImages" class="upload-box d-flex flex-column align-items-center justify-content-center p-3 text-center" style="height: 110px;">
                                        <i class="bi bi-image fs-3 text-muted mb-1"></i>
                                        <span class="small fw-semibold text-secondary">Tải ảnh lên</span>
                                        <span class="text-muted" style="font-size: 10px;">(Nhiều ảnh)</span>
                                    </label>
                                    <input type="file" id="roImages" class="d-none" accept="image/*" multiple>
                                </div>
                                <div class="col-6">
                                    <label for="roVideos" class="upload-box d-flex flex-column align-items-center justify-content-center p-3 text-center" style="height: 110px;">
                                        <i class="bi bi-camera-video fs-3 text-muted mb-1"></i>
                                        <span class="small fw-semibold text-secondary">Tải video lên</span>
                                        <span class="text-muted" style="font-size: 10px;">(Nhiều video)</span>
                                    </label>
                                    <input type="file" id="roVideos" class="d-none" accept="video/mp4,video/webm,video/quicktime" multiple>
                                </div>
                            </div>
                            <div class="form-text mt-2">Tối đa 20MB. Hỗ trợ ảnh và video (mp4/webm/mov).</div>
                            <div id="roMediaList" class="d-flex flex-wrap gap-2 mt-2"></div>
                        </div>
                    </div>

                    <!-- Step 3: Ngân hàng + Hoàn tiền + Email -->
                    <div id="roStep3" class="ro-step-panel d-none">
                        <!-- Khi user có sẵn tài khoản ngân hàng -->
                        <div class="mb-3" id="roBankSelectWrap">
                            <label class="form-label fw-semibold small">Tài khoản ngân hàng nhận hoàn tiền <span class="text-danger">*</span></label>
                            <select id="roBankAccount" class="form-select"></select>
                            <div class="form-text" id="roBankHint"></div>
                        </div>

                        <!-- Khi khách vãng lai / chưa có tài khoản -->
                        <div class="mb-3 d-none" id="roBankManualWrap">
                            <label class="form-label fw-semibold small mb-2">
                                <i class="bi bi-bank me-1 text-warning"></i>Thông tin tài khoản nhận tiền hoàn <span class="text-danger">*</span>
                            </label>
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Tên ngân hàng <span class="text-danger">*</span></label>
                                        <input type="text" id="roManualBankName" class="form-control form-control-sm" placeholder="VD: Vietcombank, ACB, Techcombank..." maxlength="120">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Số tài khoản <span class="text-danger">*</span></label>
                                        <input type="text" id="roManualAccountNo" class="form-control form-control-sm font-monospace" inputmode="numeric" placeholder="Chỉ chữ số, 6–20 ký tự" maxlength="20">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Chủ tài khoản <span class="text-danger">*</span></label>
                                        <input type="text" id="roManualOwner" class="form-control form-control-sm text-uppercase" placeholder="VD: NGUYEN VAN A" maxlength="120">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Chi nhánh <span class="text-muted fw-normal">(không bắt buộc)</span></label>
                                        <input type="text" id="roManualBranch" class="form-control form-control-sm" placeholder="VD: CN Hồ Chí Minh" maxlength="190">
                                    </div>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="bi bi-shield-lock me-1"></i>Thông tin này chỉ dùng để chuyển tiền hoàn cho yêu cầu trả hàng này.
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 d-flex align-items-center">
                            <span class="fw-semibold small text-uppercase text-muted">Tổng tiền hoàn:</span>
                            <span class="fs-5 fw-bold text-danger ms-2" id="roRefundHint">0đ</span>
                            <input type="hidden" id="roRefundAmount" value="0">
                        </div>
                        <div class="mb-1">
                            <label class="form-label fw-semibold small">Email liên hệ</label>
                            <input type="email" id="roContactEmail" class="form-control" placeholder="email@domain.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-light rounded-pill fw-semibold px-4" data-bs-dismiss="modal" id="btnRoClose">Đóng</button>
                        <button type="button" class="btn btn-light rounded-pill fw-semibold px-4 ms-1 d-none" id="btnRoBack">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary rounded-pill fw-semibold px-4" id="btnRoNext">
                            Tiếp theo<i class="bi bi-arrow-right ms-1"></i>
                        </button>
                        <button type="button" class="btn btn-warning rounded-pill fw-semibold px-4 d-none" id="btnSubmitReturn">
                            <span id="btnRoText">Gửi yêu cầu</span>
                            <span id="btnRoSpin" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<script>
    (function($) {
        const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/order.php';
        const BASE_URL = '<?= h($baseUrl) ?>';

        // Gắn CSRF token vào MỌI request AJAX của trang để các action đổi trạng thái
        // (update_address, cancel_request, confirm, return, change_payment) không bị 403.
        $.ajaxSetup({
            beforeSend: function(xhr) {
                const t = $('meta[name="csrf-token"]').attr('content') || '';
                if (t) xhr.setRequestHeader('X-CSRF-Token', t);
            }
        });

        const page = $('#orderDetailPage');
        const dom = {
            detailStatus: $('#detailStatus'),
            detailCode: $('#detailOrderCode'),
            detailTime: $('#detailTime'),
            detailItemsCount: $('#detailItemsCount'),
            detailItems: $('#detailItems'),
            detailTotals: $('#detailTotals'),
            detailShippingTimeline: $('#detailShippingTimeline'),
            detailAddress: $('#detailAddress'),
            detailShippingMethod: $('#detailShippingMethod'),
            btnChangePayment: $('#btnChangePayment'),
            btnContinuePayment: $('#btnContinuePayment'),
            changePaymentModal: $('#changePaymentModal'),
            cpMethodOptions: $('#cpMethodOptions'),
            btnConfirmChangePayment: $('#btnConfirmChangePayment'),
            btnCpText: $('#btnCpText'),
            btnCpSpin: $('#btnCpSpin'),
            detailInvoiceSection: $('#detailInvoiceSection'),
            detailInvoice: $('#detailInvoice'),
            detailOverview: $('#detailOverview'),
            btnCancelOrder: $('#btnCancelOrder'),
            btnConfirmReceived: $('#btnConfirmReceived'),
            btnRequestReturn: $('#btnRequestReturn'),
            returnOrderModal: $('#returnOrderModal'),
            returnReasonOptions: $('#returnReasonOptions'),
            roBankAccount: $('#roBankAccount'),
            roBankHint: $('#roBankHint'),
            roRefundAmount: $('#roRefundAmount'),
            roRefundHint: $('#roRefundHint'),
            roDescription: $('#roDescription'),
            roImages: $('#roImages'),
            roVideos: $('#roVideos'),
            roMediaList: $('#roMediaList'),
            roContactEmail: $('#roContactEmail'),
            btnSubmitReturn: $('#btnSubmitReturn'),
            btnRoText: $('#btnRoText'),
            btnRoSpin: $('#btnRoSpin'),
            btnRoNext: $('#btnRoNext'),
            btnRoBack: $('#btnRoBack'),
            btnRoClose: $('#btnRoClose'),
            roStep1: $('#roStep1'),
            roStep2: $('#roStep2'),
            roStep3: $('#roStep3'),
            roStepIndicator: $('#roStepIndicator'),
            cancelOrderModal: $('#cancelOrderModal'),
            cancelReasonOtherWrapper: $('#cancelReasonOtherWrapper'),
            cancelReasonOtherText: $('#cancelReasonOtherText'),
            btnConfirmCancelSubmit: $('#btnConfirmCancelSubmit'),
            btnEditAddress: $('#btnEditAddress'),
            editAddressModal: $('#editAddressModal'),
            eaRecipientName: $('#eaRecipientName'),
            eaPhone: $('#eaPhone'),
            eaGpsBtn: $('#eaGpsBtn'),
            eaSearchList: $('#eaSearchList'),
            eaProvince: $('#eaProvince'),
            eaDistrict: $('#eaDistrict'),
            eaWard: $('#eaWard'),
            eaStreet: $('#eaStreet'),
            btnSaveAddress: $('#btnSaveAddress'),
            btnSaveAddressText: $('#btnSaveAddressText'),
            btnSaveAddressSpin: $('#btnSaveAddressSpin')
        };

        let currentDetailOrder = null;
        let shippingRefreshTimer = null;
        const orderId = page.data('orderId');

        const totalValue = (order, key) => (order && order.totals && order.totals[key]) ? order.totals[key] : '0 đ';
        const numericValue = (order, key) => (order && order.totals && order.totals[key + '_raw']) ? Number(order.totals[key + '_raw']) : (order && order.totals && order.totals[key] ? parseFloat(String(order.totals[key]).replace(/[^\d]/g, '')) || 0 : 0);
        const actionAvailable = (order, key) => !!(order && order.actions && order.actions[key]);
        const customerValue = (order, key) => (order && order.customer && order.customer[key]) ? order.customer[key] : '';

        const paymentLabel = (raw) => {
            const key = String(raw || '').toLowerCase();
            if (key === 'cod') return 'Thanh toán khi nhận hàng';
            if (key === 'momo') return 'Ví MoMo';
            if (key === 'vnpay') return 'Ví VN PAY';
            if (key === 'zalopay' || key === 'zalo') return 'Ví ZaloPay';
            return raw || 'Chưa cập nhật';
        };

        const notify = (type, msg) => {
            if (window.toastr && typeof toastr[type] === 'function') {
                toastr[type](msg);
            } else {
                alert(msg);
            }
        };

        function fmtMoney(raw) {
            const num = Number(raw) || 0;
            return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
        }

        function escapeHtml(str) {
            return $('<div>').text(str ?? '').html();
        }

        // Ưu tiên media domain (toMediaUrl từ head.php); fallback ghép BASE_URL.
        function toAbs(url) {
            const raw = String(url || '').trim();
            if (!raw) return '';
            if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(raw);
            if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
            const base = String(BASE_URL || '').replace(/\/$/, '');
            if (!base) return raw;
            return base + (raw.startsWith('/') ? raw : '/' + raw);
        }

        function buildItemThumb(item) {
            const src = item && (item.variant_image_url || item.variant_thumb || item.image || item.thumbnail || item.img || item.thumb || '');
            if (!src) return `<div class="order-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-box"></i></div>`;
            const safeSrc = escapeHtml(toAbs(src));
            return `<img src="${safeSrc}" class="order-thumb" alt="thumb" onerror="this.style.display='none'">`;
        }

        function slugify(text) {
            if (!text) return '';
            return text.toString().toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[đĐ]/g, 'd')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
        }

        function buildProductDetailUrl(item) {
            const base = '<?= h($baseUrl) ?>';
            const id = Number(item.product_id || item.pid || 0);
            if (id <= 0) return '#';
            let slug = String(item.slug || item.product_slug || '').trim();
            if (!slug) {
                slug = slugify(item.name || item.product_name || 'product');
            }
            return base + '/product/' + encodeURIComponent(slug) + '-' + id;
        }

        function copyText(value) {
            const text = String(value || '').trim();
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => notify('success', 'Đã sao chép')).catch(() => notify('warning', 'Không thể sao chép'));
        }

        function showSkeletons() {
            dom.detailCode.html('<span class="skeleton-line" style="width: 80px; height: 24px;"></span>');
            dom.detailTime.html('<span class="skeleton-line" style="width: 150px; height: 14px;"></span>');
            dom.detailStatus.html('<span class="skeleton-line skeleton-badge"></span>');

            dom.btnCancelOrder.addClass('d-none').removeClass('d-inline-flex');
            dom.btnConfirmReceived.addClass('d-none').removeClass('d-inline-flex');
            dom.btnRequestReturn.addClass('d-none').removeClass('d-inline-flex');
            dom.btnEditAddress.addClass('d-none').removeClass('d-inline-flex');

            let itemsHtml = '';
            for (let i = 0; i < 2; i++) {
                itemsHtml += `
                <div class="order-item-row d-flex align-items-start gap-3">
                    <div class="skeleton-line" style="width: 60px; height: 60px; border-radius: 8px;"></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="skeleton-line" style="width: 80%; height: 16px; margin-bottom: 8px;"></div>
                        <div class="skeleton-line" style="width: 50%; height: 12px;"></div>
                    </div>
                    <div class="text-end flex-shrink-0" style="min-width:70px;">
                        <div class="skeleton-line" style="width: 60px; height: 16px; margin-bottom: 4px;"></div>
                        <div class="skeleton-line" style="width: 30px; height: 12px; margin-left: auto;"></div>
                    </div>
                </div>
            `;
            }
            dom.detailItems.html(itemsHtml);

            dom.detailAddress.html(`
            <div class="skeleton-line" style="width: 60%; height: 14px; margin-bottom: 6px;"></div>
            <div class="skeleton-line" style="width: 90%; height: 14px;"></div>
        `);
            dom.detailShippingMethod.html(`<div class="skeleton-line" style="width: 50%; height: 14px;"></div>`);

            let overviewHtml = '';
            for (let i = 0; i < 4; i++) {
                overviewHtml += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="skeleton-line" style="width: 35%; height: 14px;"></span>
                    <span class="skeleton-line" style="width: 45%; height: 14px;"></span>
                </div>
            `;
            }
            dom.detailOverview.html(overviewHtml);

            let totalsHtml = '';
            for (let i = 0; i < 3; i++) {
                totalsHtml += `
                <div class="summary-row d-flex justify-content-between">
                    <span class="skeleton-line" style="width: 40%; height: 14px;"></span>
                    <span class="skeleton-line" style="width: 25%; height: 14px;"></span>
                </div>
            `;
            }
            dom.detailTotals.html(totalsHtml);

            dom.detailShippingTimeline.html(`
            <div class="skeleton-line" style="width: 80%; height: 14px; margin-bottom: 12px;"></div>
            <div class="skeleton-line" style="width: 70%; height: 14px; margin-bottom: 12px;"></div>
            <div class="skeleton-line" style="width: 60%; height: 14px;"></div>
        `);
        }

        function openDetail(orderId) {
            showSkeletons();
            $.get(API, {
                    action: 'detail',
                    order_id: orderId
                })
                .done(res => {
                    if (!res || !res.ok || !res.data) {
                        $('#orderDetailPage').hide();
                        $('#detailEmpty').removeClass('d-none');
                        return;
                    }
                    fillDetail(res.data);
                    scheduleShippingRefresh(res.data);
                })
                .fail(() => notify('error', 'Không thể tải thông tin đơn hàng'));
        }

        const SSE_URL = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/order-stream.php';
        let sseSource = null;
        let sseLastLogId = 0;

        function startSSE(statusKey) {
            const activeStatuses = ['pending', 'processing', 'shipping'];
            if (!activeStatuses.includes(statusKey)) {
                stopSSE();
                return;
            }
            if (sseSource) return; // Đã có kết nối

            if (!window.EventSource) {
                // Fallback: trình duyệt không hỗ trợ SSE → dùng polling 30s
                schedulePollingFallback();
                return;
            }

            const url = SSE_URL + '?order_id=' + encodeURIComponent(orderId) + '&last_log_id=' + sseLastLogId;
            sseSource = new EventSource(url);

            sseSource.addEventListener('order_updated', function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.last_log_id) sseLastLogId = data.last_log_id;
                    // Reload full detail để đảm bảo đồng bộ toàn bộ payload
                    openDetail(orderId);
                } catch (err) {}
            });

            sseSource.addEventListener('end', function() {
                // Server báo sắp đóng → đóng và reconnect sau 1s (EventSource tự reconnect)
                stopSSE();
                setTimeout(() => {
                    if (currentDetailOrder) startSSE(String(currentDetailOrder.status || '').toLowerCase());
                }, 1000);
            });

            sseSource.addEventListener('error', function() {
                // SSE lỗi → đóng và fallback polling
                stopSSE();
                schedulePollingFallback();
            });
        }

        function stopSSE() {
            if (sseSource) {
                sseSource.close();
                sseSource = null;
            }
            if (shippingRefreshTimer) {
                clearInterval(shippingRefreshTimer);
                shippingRefreshTimer = null;
            }
        }

        function schedulePollingFallback() {
            if (shippingRefreshTimer) return;
            shippingRefreshTimer = setInterval(() => {
                $.get(API, {
                    action: 'detail',
                    order_id: orderId
                }).done(res => {
                    if (res?.ok) fillDetail(res.data);
                });
            }, 30000);
        }

        function scheduleShippingRefresh(order) {
            stopSSE();
            const statusKey = String(order?.status || '').toLowerCase();
            startSSE(statusKey);
        }

        function statusBadgeClass(status) {
            const key = String(status || '').toLowerCase();
            if (['delivered', 'completed', 'success'].includes(key)) return 'text-bg-success';
            if (key === 'shipping') return 'text-bg-info';
            if (key === 'processing') return 'text-bg-primary';
            if (key === 'cancel_requested') return 'text-bg-warning';
            if (key === 'return_requested') return 'text-bg-warning';
            if (['cancelled', 'canceled', 'failed', 'error'].includes(key)) return 'text-bg-danger';
            if (key === 'returned') return 'text-bg-secondary';
            if (key === 'refunded') return 'text-bg-success bg-opacity-75';
            return 'text-bg-secondary';
        }

        function buildPayMethodOptions(currentMethod) {
            const methods = (currentDetailOrder && Array.isArray(currentDetailOrder.enabled_payment_methods)) ?
                currentDetailOrder.enabled_payment_methods : [{
                    key: 'cod',
                    label: 'Thanh toán khi nhận hàng'
                }];
            const cur = String(currentMethod || '').toLowerCase();
            return methods.map(m => {
                const key = String(m.key || '').toLowerCase();
                const checked = key === cur ? 'checked' : '';
                return `
                <div class="form-check p-0 m-0">
                    <input class="btn-check" type="radio" name="cpMethod" id="cp_${key}" value="${escapeHtml(key)}" ${checked}>
                    <label class="btn btn-sm btn-outline-primary w-100 text-start border py-2 px-3 rounded-3" for="cp_${key}">
                        ${escapeHtml(paymentLabel(m.key))}
                    </label>
                </div>`;
            }).join('');
        }

        function fillDetail(order) {
            currentDetailOrder = order;
            dom.detailCode.text('#' + (order.order_id || ''));
            dom.detailTime.text('Ngày đặt: ' + (order.created_human || ''));

            const actions = order.actions || {};
            // Nếu đang chờ duyệt hủy → ẩn nút hủy
            const isCancelPending = !!actions.cancel_requested;

            let statusText = order.status_label || '';
            let statusKey = order.status;
            if (isCancelPending) {
                statusKey = 'cancel_requested';
                statusText = 'Đang chờ duyệt hủy';
            }
            dom.detailStatus.attr('class', `badge rounded-pill ${statusBadgeClass(statusKey)}`).text(statusText);

            dom.btnCancelOrder.toggleClass('d-none', !actions.can_cancel || isCancelPending)
                .toggleClass('d-inline-flex', actions.can_cancel && !isCancelPending);
            dom.btnConfirmReceived.toggleClass('d-none', !actions.can_confirm).toggleClass('d-inline-flex', actions.can_confirm);
            dom.btnRequestReturn.toggleClass('d-none', !actions.can_return).toggleClass('d-inline-flex', actions.can_return);

            dom.btnCancelOrder.attr('data-id', order.order_id);
            dom.btnConfirmReceived.attr('data-id', order.order_id);
            dom.btnRequestReturn.attr('data-id', order.order_id);

            // Nút đổi địa chỉ
            dom.btnEditAddress
                .toggleClass('d-none', !actions.can_edit_address)
                .attr('data-id', order.order_id)
                .data('detail', order.shipping_address_detail || {});

            // Render Items
            const items = Array.isArray(order.items) ? order.items : [];
            dom.detailItemsCount.text(items.length);
            dom.detailItems.html(items.map(item => {
                const isGift = Number(item.is_gift) === 1;
                const qty = Math.max(1, Number(item.qty) || 1);
                // Đơn giá: ưu tiên price_fmt từ server; nếu thiếu thì suy ra từ line_total / qty
                const unit = Number(item.price || 0) > 0
                    ? Number(item.price)
                    : (Number(item.line_total || 0) > 0 ? Number(item.line_total) / qty : 0);
                const unitFmt = item.price_fmt || fmtMoney(unit);
                const variantText = item.variant ? item.variant : 'Mặc định';
                // Mobile gọn: chỉ đơn giá + số lượng (bỏ dòng thành tiền)
                const priceBlock = isGift ? '<span class="gift-badge">Quà tặng</span>' : unitFmt;
                return `
                <div class="order-item-row d-flex align-items-start gap-3">
                    <a href="${buildProductDetailUrl(item)}" class="order-item-thumb-link">
                        ${buildItemThumb(item)}
                    </a>
                    <div class="flex-grow-1 min-w-0">
                        <a href="${buildProductDetailUrl(item)}" class="text-decoration-none order-item-name d-block" title="${escapeHtml(item.name)}">
                            ${escapeHtml(item.name)}
                        </a>
                        <span class="order-item-variant">Phân loại: ${escapeHtml(variantText)}</span>
                    </div>
                    <div class="text-end flex-shrink-0" style="min-width:74px;">
                        <div class="order-item-price">${priceBlock}</div>
                        <span class="order-item-qty">x${qty}</span>
                    </div>
                </div>
            `;
            }).join('') || '<div class="p-4 text-center text-muted">Không có sản phẩm</div>');

            // Render Totals
            const shippingDiscount = numericValue(order, 'shipping_discount');
            const discount = numericValue(order, 'discount');
            const totals = [{
                    label: 'Tổng tiền hàng',
                    val: totalValue(order, 'subtotal')
                },
                {
                    label: 'Phí vận chuyển',
                    val: totalValue(order, 'shipping')
                },
                ...(shippingDiscount > 0 ? [{
                    label: 'Giảm phí vận chuyển',
                    val: '-' + totalValue(order, 'shipping_discount'),
                    color: 'text-success'
                }] : []),
                ...(discount > 0 ? [{
                    label: 'Giảm giá',
                    val: '-' + totalValue(order, 'discount'),
                    color: 'text-success'
                }] : []),
                {
                    label: 'Tổng thanh toán',
                    val: totalValue(order, 'grand_total'),
                    isTotal: true
                }
            ];
            dom.detailTotals.html(totals.map(t => `
            <div class="${t.isTotal ? 'summary-total' : 'summary-row'}">
                <span>${t.label}</span>
                <span class="${t.color || ''}">${t.val}</span>
            </div>
        `).join(''));

            // Render Address & Shipping
            const cust = order.customer || {};
            dom.detailAddress.html(
                `<div class="fw-semibold">${escapeHtml(cust.name || '—')}${cust.phone ? ' · <span class="fw-normal text-muted">' + escapeHtml(cust.phone) + '</span>' : ''}</div>` +
                `<div class="text-muted">${escapeHtml(order.shipping_address || cust.address || '—')}</div>`
            );

            const ship = order.shipping || {};
            const carrierStatus = ship.carrier_status_label || '';
            dom.detailShippingMethod.html(
                escapeHtml(ship.method_label || ship.carrier || '—') +
                (carrierStatus ? ` <span class="text-muted fw-normal">· ${escapeHtml(carrierStatus)}</span>` : '')
            );

            // Render ETA
            const etaText = order.eta_text || ship.eta_text || '';
            const etaLatest = order.eta_latest || ship.eta_latest || '';
            const $etaWrapper = $('#detailEtaWrapper');
            const $eta = $('#detailEta');
            if (etaText !== '') {
                $etaWrapper.removeClass('d-none');
                const fmtEta = v => {
                    // "HH:MM:SS DD/MM/YYYY" → "DD/MM/YYYY" hoặc ISO → vi-VN date
                    if (!v) return '';
                    const m = v.match(/(\d{2}\/\d{2}\/\d{4})/);
                    if (m) return m[1];
                    const d = new Date(v);
                    if (!isNaN(d)) return d.toLocaleDateString('vi-VN');
                    return v;
                };
                let etaDisplay = fmtEta(etaText);
                if (etaLatest && etaLatest !== etaText) etaDisplay += ' – ' + fmtEta(etaLatest);
                $eta.text('Dự kiến: ' + etaDisplay);
            } else {
                $etaWrapper.addClass('d-none');
            }

            // Update horizontal stepper progress bar
            const $bar = $('#orderProgressBar');
            const $wrap = $bar.find('.stepper-wrap');

            // Dọn dẹp các bước cũ (giữ lại thanh line)
            $wrap.find('.step-item').remove();

            // Ưu tiên status_raw (chưa bị alias completed->delivered) để stepper hiển thị đúng mốc "Hoàn thành".
            const currentStatus = isCancelPending ? 'cancel_requested' : String(order.status_raw || order.status || '').toLowerCase();
            const key = ['pending', 'processing', 'shipping', 'delivered', 'completed', 'success', 'cancel_requested', 'return_requested', 'returned', 'refunded', 'canceled', 'cancelled', 'failed', 'error'].includes(currentStatus) ? currentStatus : 'pending';

            const isCanceled = ['canceled', 'cancelled', 'cancel_requested', 'failed', 'error'].includes(key);
            const isReturned = ['returned', 'refunded', 'return_requested'].includes(key);

            let steps = [];
            let activeIdx = -1;
            let flowType = 'normal'; // 'normal', 'cancel', 'return'

            if (isCanceled) {
                flowType = 'cancel';
                steps = [{
                        step: 'pending',
                        icon: 'bi-receipt',
                        label: 'Chờ xác nhận'
                    },
                    {
                        step: 'cancel_requested',
                        icon: 'bi-question-circle',
                        label: 'Yêu cầu huỷ'
                    },
                    {
                        step: 'canceled',
                        icon: 'bi-x-circle',
                        label: 'Đơn đã huỷ'
                    }
                ];
                if (key === 'cancel_requested') {
                    activeIdx = 1;
                } else {
                    activeIdx = 2;
                }
            } else if (isReturned) {
                flowType = 'return';
                steps = [{
                        step: 'delivered',
                        icon: 'bi-check2-circle',
                        label: 'Đã giao hàng'
                    },
                    {
                        step: 'return_requested',
                        icon: 'bi-arrow-return-left',
                        label: 'Yêu cầu trả hàng'
                    },
                    {
                        step: 'returned',
                        icon: 'bi-box-arrow-in-down',
                        label: 'Nhận hàng hoàn'
                    },
                    {
                        step: 'refunded',
                        icon: 'bi-cash-coin',
                        label: 'Đã hoàn tiền'
                    }
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
                const isConfirmed = !!(actions && actions.customer_confirmed);
                steps = [{
                        step: 'pending',
                        icon: 'bi-receipt',
                        label: 'Chờ xác nhận'
                    },
                    {
                        step: 'processing',
                        icon: 'bi-box-seam',
                        label: 'Đang chuẩn bị'
                    },
                    {
                        step: 'shipping',
                        icon: 'bi-truck',
                        label: 'Đang giao hàng'
                    },
                    {
                        step: 'delivered',
                        icon: 'bi-check2-circle',
                        label: 'Đã giao hàng'
                    },
                    {
                        step: 'received',
                        icon: 'bi-bag-check',
                        label: 'Đã nhận hàng'
                    },
                    {
                        step: 'completed',
                        icon: 'bi-star',
                        label: 'Hoàn thành'
                    }
                ];
                if (key === 'pending') activeIdx = 0;
                else if (key === 'processing') activeIdx = 1;
                else if (key === 'shipping') activeIdx = 2;
                else if (key === 'delivered' && !isConfirmed) activeIdx = 3;
                else if (key === 'delivered' && isConfirmed) activeIdx = 4;
                else if (['completed', 'success'].includes(key)) activeIdx = 5;
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
                    <div class="step-label">${escapeHtml(st.label)}</div>
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

            // Nút mua lại (chỉ hiển thị khi đơn hàng đã hoàn tất, đã hủy hoặc đã trả hàng)
            const canReorder = ['delivered', 'completed', 'success', 'cancelled', 'canceled', 'returned', 'refunded'].includes(currentStatus);
            $('#btnReorder').toggleClass('d-none', !canReorder).toggleClass('d-inline-flex', canReorder);

            // Render Merged Overview, Payment, and Invoice Info
            const method = String(order.payment_method || '').toLowerCase();
            const payStatus = String(order.payment_status || '').toLowerCase();
            const canChangePayment = !!actions.can_change_payment;
            const isWalletMethod = ['momo', 'vnpay', 'zalopay'].includes(method);
            const needsPay = canChangePayment && isWalletMethod && payStatus !== 'paid';

            let paymentStatusText = '';
            if (method === 'cod') {
                paymentStatusText = 'Thanh toán khi nhận hàng (Tiền mặt)';
            } else {
                paymentStatusText = order.payment_time_human ?
                    'Đã thanh toán (' + order.payment_time_human + ')' :
                    'Chờ thanh toán';
            }

            const inv = order.invoice || {};
            const hasInv = inv.has_invoice === true || !!(inv.buyer_name || inv.company_name || inv.tax_code || inv.address || inv.email);

            const meta = [{
                    label: 'Mã đơn hàng',
                    val: order.order_id,
                    copy: true
                },
                {
                    label: 'Thời gian đặt hàng',
                    val: order.created_human || '—'
                },
                {
                    label: 'Phương thức thanh toán',
                    val: paymentLabel(order.payment_method)
                },
                {
                    label: 'Trạng thái thanh toán',
                    val: paymentStatusText,
                    isStatus: true
                }
            ];

            if (order.payment_expires_human && needsPay) {
                meta.push({
                    label: 'Hạn thanh toán',
                    val: order.payment_expires_human,
                    isExpire: true
                });
            }

            meta.push({
                label: 'Hóa đơn GTGT',
                val: hasInv ? 'Có yêu cầu xuất hóa đơn' : 'Không yêu cầu'
            });

            dom.detailOverview.html(meta.map(m => {
                let valHtml = escapeHtml(m.val);
                if (m.isStatus) {
                    const isPaid = order.payment_time_human || method === 'cod';
                    const statusClass = isPaid ? 'text-success' : 'text-warning';
                    valHtml = `<span class="${statusClass} fw-bold">${escapeHtml(m.val)}</span>`;
                } else if (m.isExpire) {
                    valHtml = `<span class="text-danger fw-semibold">${escapeHtml(m.val)}</span>`;
                }
                return `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="text-muted small">${m.label}</span>
                    <span class="fw-medium small">
                        ${valHtml} 
                        ${m.copy ? `<button class="copy-btn ms-2" onclick="navigator.clipboard.writeText('${escapeHtml(m.val)}')">Sao chép</button>` : ''}
                    </span>
                </div>
            `;
            }).join(''));

            // Toggle continue payment button container
            const existingPayUrl = (order.payment_meta && (order.payment_meta.pay_url || order.payment_meta.order_url || order.payment_meta.deeplink)) || '';
            const showContinueBtn = needsPay && existingPayUrl;
            $('#detailPaymentActionContainer').toggleClass('d-none', !showContinueBtn);
            dom.btnContinuePayment.data('url', existingPayUrl);

            // Toggle change payment button in header
            dom.btnChangePayment.toggleClass('d-none', !canChangePayment);

            // Render Invoice Details if requested
            if (hasInv) {
                dom.detailInvoiceSection.show();

                const typeKey = String(inv.invoice_type || '').toLowerCase();
                const typeLabel = typeKey === 'company' ? 'Doanh nghiệp' : 'Cá nhân';
                const typeClass = typeKey === 'company' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary';

                let html = `
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-light">
                    <span class="text-muted small fw-bold text-uppercase">Phân loại</span>
                    <span class="badge ${typeClass} px-3 py-1.5 rounded-pill small fw-semibold" style="font-size: 0.75rem;">${escapeHtml(typeLabel)}</span>
                </div>
            `;

                if (inv.buyer_name) {
                    html += `
                    <div class="mb-3">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Người mua hàng</label>
                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;">${escapeHtml(inv.buyer_name)}</div>
                    </div>
                `;
                }

                if (inv.company_name) {
                    html += `
                    <div class="mb-3">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Tên đơn vị / Công ty</label>
                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;">${escapeHtml(inv.company_name)}</div>
                    </div>
                `;
                }

                if (inv.tax_code) {
                    html += `
                    <div class="mb-3">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Mã số thuế</label>
                        <div class="fw-bold text-primary font-monospace" style="font-size: 0.95rem;">${escapeHtml(inv.tax_code)}</div>
                    </div>
                `;
                }

                if (inv.address) {
                    html += `
                    <div class="mb-3">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Địa chỉ xuất hóa đơn</label>
                        <div class="text-secondary fw-medium" style="font-size: 0.85rem; line-height: 1.4;">${escapeHtml(inv.address)}</div>
                    </div>
                `;
                }

                if (inv.email) {
                    html += `
                    <div class="mb-1">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Email nhận hóa đơn (PDF)</label>
                        <div class="text-secondary font-monospace fw-medium" style="font-size: 0.85rem;">${escapeHtml(inv.email)}</div>
                    </div>
                `;
                }

                dom.detailInvoice.html(html);
            } else {
                dom.detailInvoiceSection.hide();
            }

            // Render Timeline — ưu tiên order.timeline (từ ecommerce_order_log), fallback ship.timeline (GHN)
            const timeline = Array.isArray(order.timeline) && order.timeline.length > 0 ?
                order.timeline :
                (Array.isArray(ship.timeline) ? ship.timeline : []);
            const lastIdx = timeline.length - 1;
            dom.detailShippingTimeline.html(timeline.map((step, idx) => {
                const label = step.label || step.status || '';
                const actor = step.actor ? (step.actor.toLowerCase() === 'admin' ? 'Hệ thống' : escapeHtml(step.actor)) : '';
                // Chuẩn hoá chuỗi để so khớp: bỏ dấu, ký tự đặc biệt, gộp khoảng trắng
                const normalize = (s) => String(s || '')
                    .toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim();
                // Tách mã vận đơn (nếu có) để giữ lại, phần còn lại của note có thể là dư thừa
                let noteHtml = '';
                if (step.note) {
                    const codeMatch = step.note.match(/Mã[:\s]+([A-Z0-9]{6,})/i);
                    // Bỏ tiền tố "Cập nhật trạng thái:" và phần loại dịch vụ "(Nhanh)/(Chuẩn)..."
                    const noteCore = step.note
                        .replace(/^\s*cập nhật trạng thái\s*:\s*/i, '')
                        .replace(/\s*\((?:nhanh|chuẩn|tiết kiệm|hỏa tốc)\)\s*$/i, '')
                        .trim();
                    const normLabel = normalize(label);
                    const normNote = normalize(noteCore);
                    // Note coi là dư thừa nếu phần lõi trùng/nằm trong label (hoặc ngược lại)
                    const noteIsRedundant = normNote !== '' && normLabel !== '' &&
                        (normLabel.includes(normNote) || normNote.includes(normLabel));
                    if (codeMatch) {
                        noteHtml = `<div class="small mt-1 text-muted">Mã: <span class="fw-medium text-secondary">${escapeHtml(codeMatch[1])}</span></div>`;
                    } else if (!noteIsRedundant) {
                        noteHtml = `<div class="small mt-1 text-secondary" style="white-space:pre-line;">${escapeHtml(step.note)}</div>`;
                    }
                }
                return `
            <div class="timeline-node ${idx === lastIdx ? 'active' : ''}">
                <div class="fw-bold small">${escapeHtml(label)}</div>
                <div class="text-muted" style="font-size:0.75rem;">${escapeHtml(step.time_human || step.time)}${actor ? ' · ' + actor : ''}</div>
                ${noteHtml}
            </div>`;
            }).join('') || '<div class="text-muted small italic">Chưa có hành trình vận chuyển</div>');
        }

        function init() {
            if (!orderId) {
                $('#orderDetailPage').hide();
                $('#detailEmpty').removeClass('d-none');
                return;
            }

            // Click reorder
            $('#btnReorder').on('click', function() {
                if (!currentDetailOrder || !Array.isArray(currentDetailOrder.items) || !currentDetailOrder.items.length) {
                    notify('warning', 'Không tìm thấy sản phẩm để mua lại.');
                    return;
                }

                if (!confirm('Bạn có muốn mua lại tất cả sản phẩm trong đơn hàng này? Giỏ hàng hiện tại của bạn sẽ bị thay thế.')) {
                    return;
                }

                const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';

                // Step 1: Clear cart
                $.ajax({
                    url: BASE_URL + '/core_user/ecommerce/ajax/cart.php',
                    method: 'POST',
                    data: {
                        action: 'cart_clear'
                    },
                    headers: {
                        'X-CSRF-Token': csrfToken
                    }
                }).done(function(res) {
                    if (!res || !res.ok) {
                        notify('error', 'Không thể dọn dẹp giỏ hàng cũ.');
                        return;
                    }

                    // Step 2: Add all products from order items
                    const addPromises = [];
                    currentDetailOrder.items.forEach(item => {
                        if (Number(item.is_gift) === 1) return;

                        const pid = Number(item.product_id || item.id || 0);
                        const variantId = Number(item.variant_id || 0);
                        const qty = Number(item.qty || 1);

                        if (pid > 0) {
                            addPromises.push(
                                $.ajax({
                                    url: BASE_URL + '/core_user/ecommerce/ajax/cart.php',
                                    method: 'POST',
                                    data: {
                                        action: 'cart_add',
                                        pid: pid,
                                        variant_id: variantId,
                                        qty: qty
                                    },
                                    headers: {
                                        'X-CSRF-Token': csrfToken
                                    }
                                })
                            );
                        }
                    });

                    if (!addPromises.length) {
                        notify('warning', 'Không có sản phẩm hợp lệ để mua lại.');
                        return;
                    }

                    Promise.all(addPromises).then(results => {
                        const failed = results.filter(r => !r || !r.ok);
                        if (failed.length > 0) {
                            notify('warning', 'Một số sản phẩm không thể thêm vào giỏ hàng (có thể do hết hàng hoặc ngưng bán).');
                        } else {
                            notify('success', 'Đã thêm toàn bộ sản phẩm vào giỏ hàng.');
                        }

                        setTimeout(() => {
                            window.location.href = BASE_URL + '/cart';
                        }, 1000);
                    }).catch(() => {
                        notify('error', 'Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng.');
                    });

                }).fail(function() {
                    notify('error', 'Có lỗi xảy ra khi dọn dẹp giỏ hàng.');
                });
            });

            // Click contact support
            $('#btnContactSupport').on('click', function(e) {
                if (!currentDetailOrder || !currentDetailOrder.order_id) return;
                e.preventDefault();
                const code = currentDetailOrder.order_id;
                const zaloHref = $(this).attr('href');
                navigator.clipboard.writeText(code).catch(() => {});
                window.open(zaloHref, '_blank');
            });

            dom.btnCancelOrder.on('click', function() {
                const id = $(this).attr('data-id');
                if (!id) return;
                // Reset modal
                $('input[name="cancelReason"]').prop('checked', false);
                dom.cancelReasonOtherWrapper.addClass('d-none');
                dom.cancelReasonOtherText.val('');
                dom.cancelOrderModal.modal('show');
                dom.btnConfirmCancelSubmit.data('id', id);
            });

            $('input[name="cancelReason"]').on('change', function() {
                if ($(this).val() === '__other__') {
                    dom.cancelReasonOtherWrapper.removeClass('d-none');
                } else {
                    dom.cancelReasonOtherWrapper.addClass('d-none');
                }
            });

            dom.btnConfirmCancelSubmit.on('click', function() {
                const id = $(this).data('id');
                const selectedReason = $('input[name="cancelReason"]:checked').val();

                if (!selectedReason) {
                    notify('warning', 'Vui lòng chọn lý do hủy đơn');
                    return;
                }

                let reason = selectedReason;
                if (selectedReason === '__other__') {
                    reason = dom.cancelReasonOtherText.val().trim();
                    if (reason === '') {
                        notify('warning', 'Vui lòng nhập lý do cụ thể');
                        return;
                    }
                }

                $.post(API, {
                    action: 'cancel_request',
                    order_id: id,
                    reason: reason
                }).done(res => {
                    if (res?.ok) {
                        notify('success', res.msg || 'Đã gửi yêu cầu hủy. Vui lòng chờ admin xác nhận.');
                        dom.cancelOrderModal.modal('hide');
                        openDetail(id);
                    } else {
                        notify('error', res?.msg || 'Không thể gửi yêu cầu hủy đơn');
                    }
                });
            });

            dom.btnConfirmReceived.on('click', function() {
                const $btn = $(this);
                if ($btn.prop('disabled') || $btn.hasClass('is-loading')) return;
                const id = $btn.attr('data-id');
                if (id && confirm('Bạn xác nhận đã nhận được đầy đủ hàng và hài lòng?')) {
                    // Disable ngay để ngăn double-click
                    $btn.prop('disabled', true).addClass('is-loading');
                    const origHtml = $btn.html();
                    $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...');
                    $.post(API, {
                        action: 'confirm',
                        order_id: id
                    }).done(res => {
                        if (res?.ok) {
                            notify('success', res?.msg || 'Xác nhận thành công');
                            // Ẩn nút hoàn toàn sau khi thành công (đã nhận = 1 lần duy nhất)
                            $btn.addClass('d-none').removeClass('d-inline-flex');
                            openDetail(id);
                        } else {
                            notify('error', res?.msg || 'Thao tác thất bại');
                            $btn.prop('disabled', false).removeClass('is-loading').html(origHtml);
                        }
                    }).fail(function() {
                        notify('error', 'Không thể kết nối máy chủ');
                        $btn.prop('disabled', false).removeClass('is-loading').html(origHtml);
                    });
                }
            });

            // ===== TRẢ HÀNG MULTI-STEP =====
            let roCurrentStep = 1;
            let roSelectedFiles = [];
            let roObjectUrls = [];

            function clearObjectUrls() {
                roObjectUrls.forEach(url => URL.revokeObjectURL(url));
                roObjectUrls = [];
            }

            function roGoToStep(step) {
                roCurrentStep = step;
                dom.roStep1.toggleClass('d-none', step !== 1);
                dom.roStep2.toggleClass('d-none', step !== 2);
                dom.roStep3.toggleClass('d-none', step !== 3);
                dom.btnRoBack.toggleClass('d-none', step === 1);
                dom.btnRoClose.toggleClass('d-none', step !== 1);
                dom.btnRoNext.toggleClass('d-none', step === 3);
                dom.btnSubmitReturn.toggleClass('d-none', step !== 3);
                // update step indicator
                dom.roStepIndicator.find('.ro-step-item').each(function() {
                    const s = parseInt($(this).data('step'));
                    $(this).toggleClass('active', s === step).toggleClass('done', s < step);
                });
                dom.roStepIndicator.find('.ro-step-line').each(function(idx) {
                    $(this).toggleClass('done', idx < step - 1);
                });
            }

            // Bind selection events once on returnReasonOptions container
            dom.returnReasonOptions.on('click', '.reason-option-card', function(e) {
                if ($(e.target).is('input') || $(e.target).is('label')) return;
                const $radio = $(this).find('input[type="radio"]');
                $radio.prop('checked', true).trigger('change');
            });

            dom.returnReasonOptions.on('change', 'input[name="returnReason"]', function() {
                $('.reason-option-card').removeClass('selected');
                $(this).closest('.reason-option-card').addClass('selected');
            });

            dom.btnRequestReturn.on('click', function() {
                const id = $(this).attr('data-id');
                if (!id || !currentDetailOrder) return;
                const order = currentDetailOrder;

                // Step 1: Lý do
                const reasons = Array.isArray(order.return_reasons) ? order.return_reasons : [];
                dom.returnReasonOptions.html(reasons.map((r, i) => `
                <div class="reason-option-card border rounded-3 p-3 mb-2 d-flex align-items-center gap-3">
                    <input class="form-check-input m-0 flex-shrink-0" type="radio" name="returnReason" id="rr_${i}" value="${escapeHtml(r)}" style="width: 1.15rem; height: 1.15rem; cursor: pointer;">
                    <label class="fw-medium text-dark cursor-pointer mb-0 flex-grow-1" for="rr_${i}" style="font-size: 0.88rem; line-height: 1.4;">
                        ${escapeHtml(r)}
                    </label>
                </div>
            `).join(''));

                // Step 3: Tài khoản ngân hàng — chọn từ danh sách hoặc nhập thủ công (guest)
                const banks = Array.isArray(order.bank_accounts) ? order.bank_accounts : [];
                const $bankSelectWrap = $('#roBankSelectWrap');
                const $bankManualWrap = $('#roBankManualWrap');
                $('#roManualBankName, #roManualAccountNo, #roManualOwner, #roManualBranch').val('');

                if (banks.length === 0) {
                    // Hiển thị form thủ công cho guest hoặc user chưa có tài khoản
                    $bankSelectWrap.addClass('d-none');
                    $bankManualWrap.removeClass('d-none');
                    dom.roBankAccount.empty().val('');
                } else {
                    $bankManualWrap.addClass('d-none');
                    $bankSelectWrap.removeClass('d-none');
                    dom.roBankAccount.prop('disabled', false).html(banks.map(b => {
                        const label = `${b.bank_name || b.bank_code || 'Ngân hàng'} - ${b.account_no || ('****' + (b.account_last4 || ''))} - ${b.account_owner || ''}`;
                        const sel = String(b.is_default) === '1' ? 'selected' : '';
                        return `<option value="${b.id}" ${sel}>${escapeHtml(label)}</option>`;
                    }).join(''));
                    dom.roBankHint.html(`Muốn dùng tài khoản khác? <a href="${BASE_URL}/account" target="_blank">Quản lý tài khoản ngân hàng</a>`);
                }

                // Step 2: Tiền hoàn
                const grand = (order.raw_totals && Number(order.raw_totals.grand_total)) || 0;
                dom.roRefundAmount.val(grand);
                dom.roRefundHint.html(grand ? (new Intl.NumberFormat('vi-VN').format(grand) + 'đ') : '0đ');
                dom.roRefundAmount.data('max', grand);

                // Step 3 reset
                dom.roDescription.val('');
                dom.roImages.val('');
                dom.roVideos.val('');
                roSelectedFiles = [];
                clearObjectUrls();
                dom.roMediaList.empty();
                dom.roContactEmail.val((order.customer && order.customer.email) || '');

                roGoToStep(1);
                dom.returnOrderModal.modal('show');
            });

            dom.btnRoNext.on('click', function() {
                if (roCurrentStep === 1) {
                    const reason = $('input[name="returnReason"]:checked').val();
                    if (!reason) {
                        notify('warning', 'Vui lòng chọn lý do trả hàng');
                        return;
                    }
                    roGoToStep(2);
                } else if (roCurrentStep === 2) {
                    roGoToStep(3);
                }
            });

            dom.btnRoBack.on('click', function() {
                if (roCurrentStep > 1) roGoToStep(roCurrentStep - 1);
            });

            // Format tiền hoàn khi gõ
            dom.roRefundAmount.on('input', function() {
                let digits = String($(this).val()).replace(/[^0-9]/g, '');
                const max = Number(dom.roRefundAmount.data('max')) || 0;
                if (max > 0 && Number(digits) > max) digits = String(max);
                $(this).val(digits ? new Intl.NumberFormat('vi-VN').format(Number(digits)) : '');
            });

            function renderMediaPreviews() {
                clearObjectUrls();
                dom.roMediaList.empty();
                if (roSelectedFiles.length === 0) return;

                roSelectedFiles.forEach((file, idx) => {
                    const isVideo = file.type.startsWith('video/');
                    const isImage = file.type.startsWith('image/');
                    const objectURL = URL.createObjectURL(file);
                    roObjectUrls.push(objectURL);

                    const itemHtml = `
                    <div class="position-relative border rounded overflow-hidden" style="width: 80px; height: 80px; background: #f8fafc;">
                        ${isImage ? `
                            <img src="${objectURL}" style="width: 100%; height: 100%; object-fit: cover;">
                        ` : ''}
                        ${isVideo ? `
                            <video src="${objectURL}" style="width: 100%; height: 100%; object-fit: cover;" muted></video>
                            <div class="position-absolute top-50 start-50 translate-middle text-white fs-4" style="text-shadow: 0 1px 3px rgba(0,0,0,0.6); pointer-events: none;"><i class="bi bi-play-fill"></i></div>
                        ` : ''}
                        ${!isImage && !isVideo ? `
                            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted p-1">
                                <i class="bi bi-file-earmark fs-4"></i>
                                <span class="text-truncate w-100 small text-center" style="font-size: 8px;">${escapeHtml(file.name)}</span>
                            </div>
                        ` : ''}
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 bg-dark bg-opacity-75 rounded-circle p-1 m-1" style="width: 16px; height: 16px; font-size: 8px; line-height: 1;" data-index="${idx}"></button>
                    </div>
                `;

                    const $item = $(itemHtml);
                    $item.find('.btn-close').on('click', function(e) {
                        e.preventDefault();
                        const removeIdx = parseInt($(this).data('index'));
                        roSelectedFiles.splice(removeIdx, 1);
                        renderMediaPreviews();
                    });
                    dom.roMediaList.append($item);
                });
            }

            const handleMediaChange = (e) => {
                const files = e.target.files || [];
                let currentTotal = 0;
                roSelectedFiles.forEach(f => currentTotal += f.size);

                let addTotal = 0;
                const toAdd = [];
                for (let i = 0; i < files.length; i++) {
                    addTotal += files[i].size;
                    toAdd.push(files[i]);
                }

                if (currentTotal + addTotal > 20 * 1048576) {
                    notify('error', 'Tổng dung lượng tệp đính kèm vượt quá giới hạn 20MB. Vui lòng chọn lại.');
                    e.target.value = '';
                    return;
                }

                toAdd.forEach(f => roSelectedFiles.push(f));
                e.target.value = '';
                renderMediaPreviews();
            };

            dom.roImages.on('change', handleMediaChange);
            dom.roVideos.on('change', handleMediaChange);

            dom.returnOrderModal.on('hidden.bs.modal', function() {
                clearObjectUrls();
            });

            dom.btnSubmitReturn.on('click', function() {
                const id = currentDetailOrder ? currentDetailOrder.order_id : '';
                if (!id) return;
                const reason = $('input[name="returnReason"]:checked').val();
                if (!reason) {
                    notify('warning', 'Vui lòng chọn lý do trả hàng');
                    return;
                }
                const manualMode = !$('#roBankManualWrap').hasClass('d-none');
                const bankId = manualMode ? '' : (dom.roBankAccount.val() || '');
                let manualBankName = '',
                    manualAccountNo = '',
                    manualOwner = '',
                    manualBranch = '';

                if (manualMode) {
                    manualBankName = $('#roManualBankName').val().trim();
                    manualAccountNo = String($('#roManualAccountNo').val() || '').replace(/\s+/g, '');
                    manualOwner = $('#roManualOwner').val().trim();
                    manualBranch = $('#roManualBranch').val().trim();
                    if (!manualBankName) {
                        notify('warning', 'Vui lòng nhập tên ngân hàng');
                        return;
                    }
                    if (!/^[0-9]{6,20}$/.test(manualAccountNo)) {
                        notify('warning', 'Số tài khoản không hợp lệ (6–20 chữ số)');
                        return;
                    }
                    if (!manualOwner || manualOwner.length < 3) {
                        notify('warning', 'Vui lòng nhập chủ tài khoản');
                        return;
                    }
                } else if (!bankId) {
                    notify('warning', 'Vui lòng chọn tài khoản ngân hàng nhận hoàn tiền');
                    return;
                }

                const refund = String(dom.roRefundAmount.val()).replace(/[^0-9]/g, '');
                const email = dom.roContactEmail.val().trim();
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    notify('warning', 'Email liên hệ không hợp lệ');
                    return;
                }

                const files = roSelectedFiles;
                let total = 0;
                for (let i = 0; i < files.length; i++) total += files[i].size;
                if (total > 20 * 1048576) {
                    notify('error', 'Tổng dung lượng tệp đính kèm tối đa 20MB');
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'return');
                fd.append('order_id', id);
                fd.append('reason', reason);
                fd.append('bank_account_id', bankId || '0');
                if (manualMode) {
                    fd.append('manual_bank_name', manualBankName);
                    fd.append('manual_account_no', manualAccountNo);
                    fd.append('manual_account_owner', manualOwner);
                    fd.append('manual_bank_branch', manualBranch);
                }
                fd.append('refund_amount', refund || '0');
                fd.append('description', dom.roDescription.val().trim());
                fd.append('contact_email', email);
                for (let i = 0; i < files.length; i++) fd.append('media[]', files[i]);

                dom.btnRoText.text('Đang gửi...');
                dom.btnRoSpin.removeClass('d-none');
                dom.btnSubmitReturn.prop('disabled', true);

                $.ajax({
                    url: API,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false
                }).done(res => {
                    if (res && res.ok) {
                        notify('success', res.msg || 'Đã gửi yêu cầu trả hàng');
                        dom.returnOrderModal.modal('hide');
                        openDetail(id);
                    } else {
                        notify('error', (res && res.msg) || 'Gửi yêu cầu thất bại');
                    }
                }).fail(xhr => {
                    let msg = 'Lỗi kết nối, vui lòng thử lại';
                    try {
                        const j = JSON.parse(xhr.responseText);
                        if (j && j.msg) msg = j.msg;
                    } catch (e) {}
                    notify('error', msg);
                }).always(() => {
                    dom.btnRoText.text('Gửi yêu cầu');
                    dom.btnRoSpin.addClass('d-none');
                    dom.btnSubmitReturn.prop('disabled', false);
                });
            });

            // ===== ĐỔI PHƯƠNG THỨC THANH TOÁN =====
            dom.btnContinuePayment.on('click', function() {
                const url = $(this).data('url');
                if (url) {
                    window.location.href = url;
                } else {
                    notify('warning', 'Không tìm thấy liên kết thanh toán, vui lòng tạo lại bằng "Đổi phương thức".');
                }
            });

            dom.btnChangePayment.on('click', function() {
                if (!currentDetailOrder) return;
                dom.cpMethodOptions.html(buildPayMethodOptions(currentDetailOrder.payment_method));
                dom.changePaymentModal.modal('show');
            });

            dom.btnConfirmChangePayment.on('click', function() {
                const id = currentDetailOrder ? currentDetailOrder.order_id : '';
                const selected = $('input[name="cpMethod"]:checked').val();
                if (!id) return;
                if (!selected) {
                    notify('warning', 'Vui lòng chọn phương thức thanh toán');
                    return;
                }

                const curMethod = String((currentDetailOrder && currentDetailOrder.payment_method) || '').toLowerCase();
                if (selected === curMethod && selected === 'cod') {
                    notify('info', 'Đơn hàng đang dùng phương thức này.');
                    return;
                }

                dom.btnCpText.text('Đang xử lý...');
                dom.btnCpSpin.removeClass('d-none');
                dom.btnConfirmChangePayment.prop('disabled', true);

                $.post(API, {
                        action: 'change_payment',
                        order_id: id,
                        payment_method: selected
                    })
                    .done(res => {
                        if (res && res.ok) {
                            dom.changePaymentModal.modal('hide');
                            if (res.payment_url) {
                                notify('success', 'Đang chuyển đến trang thanh toán...');
                                setTimeout(() => {
                                    window.location.href = res.payment_url;
                                }, 600);
                            } else {
                                notify('success', res.msg || 'Đã cập nhật phương thức thanh toán');
                                openDetail(id);
                            }
                        } else {
                            notify('error', (res && res.msg) || 'Không thể đổi phương thức thanh toán');
                        }
                    })
                    .fail(xhr => {
                        let msg = 'Lỗi kết nối, vui lòng thử lại';
                        try {
                            const j = JSON.parse(xhr.responseText);
                            if (j && j.msg) msg = j.msg;
                        } catch (e) {}
                        notify('error', msg);
                    })
                    .always(() => {
                        dom.btnCpText.text('Xác nhận');
                        dom.btnCpSpin.addClass('d-none');
                        dom.btnConfirmChangePayment.prop('disabled', false);
                    });
            });

            // ===== ĐỔI ĐỊA CHỈ (Goong autocomplete + GHN region matching, không dùng bản đồ) =====
            const REGION_API = '<?= h($baseUrl) ?>/main/account/region-session.php';
            const GEO_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';

            // Cache danh mục GHN để tự khớp tên từ gợi ý Goong.
            let eaProvinces = [];
            let eaDistricts = [];
            let eaWards = [];
            let eaSearchTimer = null;
            let eaSavedLocations = []; // địa chỉ đã lưu của user (rỗng nếu khách vãng lai)

            // Nạp địa chỉ đã lưu (best-effort) để ưu tiên gợi ý — khách vãng lai sẽ trả rỗng.
            $.get(REGION_API, { region: '' }, function(res) {
                if (res && res.ok && Array.isArray(res.saved_locations)) {
                    eaSavedLocations = res.saved_locations;
                }
            }).fail(function() {});

            // ── Helpers khớp tên khu vực (đồng bộ với main/account.php) ──
            function eaNormalizeName(str) {
                let s = String(str || '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[̀-ͯ]/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                s = s.replace(/^(thanh pho|tinh|quan|huyen|thi xa|thi tran|phuong|xa)\s+/i, '').trim();
                return s;
            }

            function eaExtractNumber(str) {
                const m = String(str || '').match(/\d+/);
                return m ? m[0] : '';
            }

            // mode: 'exact' = chỉ khớp tuyệt đối; 'loose' = cho phép substring (dễ dính dữ liệu rác).
            function eaMatchName(keyword, mainName, alts, numAware, mode) {
                const kwNum = numAware ? eaExtractNumber(keyword) : '';
                const candidates = [mainName].concat(Array.isArray(alts) ? alts : []);
                for (let i = 0; i < candidates.length; i++) {
                    const txt = eaNormalizeName(candidates[i] || '');
                    if (!txt) continue;
                    if (numAware) {
                        const txtNum = eaExtractNumber(txt);
                        if (kwNum && txtNum && kwNum !== txtNum) continue;
                    }
                    if (txt === keyword) return true;
                    if (mode === 'loose' && (txt.includes(keyword) || keyword.includes(txt))) return true;
                }
                return false;
            }

            // Ưu tiên khớp tuyệt đối trước (tránh dữ liệu NameExtension lỗi của GHN, vd
            // "Binh Thanh District" bị gán nhầm vào Củ Chi), rồi mới rơi xuống khớp substring.
            function eaFindByName(list, nameKey, name, numAware) {
                const keyword = eaNormalizeName(name);
                if (!keyword) return null;
                return list.find(r => eaMatchName(keyword, r[nameKey] || r.name || '', r.alts, numAware, 'exact'))
                    || list.find(r => eaMatchName(keyword, r[nameKey] || r.name || '', r.alts, numAware, 'loose'))
                    || null;
            }

            function eaFindByCandidates(list, nameKey, candidates, numAware) {
                if (!Array.isArray(candidates)) return null;
                for (let i = 0; i < candidates.length; i++) {
                    const r = eaFindByName(list, nameKey, candidates[i], numAware);
                    if (r) return r;
                }
                return null;
            }

            // ── Tải danh mục GHN (Promise) ──
            function eaLoadProvinces() {
                return $.get(REGION_API, { action: 'region_provinces' }).then(res => {
                    eaProvinces = Array.isArray(res?.rows) ? res.rows : [];
                    let opts = '<option value="">-- Chọn tỉnh/thành --</option>';
                    eaProvinces.forEach(r => {
                        opts += `<option value="${r.ProvinceID}" data-name="${escapeHtml(r.ProvinceName)}">${escapeHtml(r.ProvinceName)}</option>`;
                    });
                    dom.eaProvince.html(opts);
                    return eaProvinces;
                });
            }

            function eaLoadDistricts(provinceId) {
                dom.eaWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
                eaWards = [];
                if (!provinceId) {
                    eaDistricts = [];
                    dom.eaDistrict.prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
                    return $.Deferred().resolve([]).promise();
                }
                return $.get(REGION_API, { action: 'region_districts', province_id: provinceId }).then(res => {
                    eaDistricts = Array.isArray(res?.rows) ? res.rows : [];
                    let opts = '<option value="">-- Chọn quận/huyện --</option>';
                    eaDistricts.forEach(r => {
                        opts += `<option value="${r.DistrictID}" data-name="${escapeHtml(r.DistrictName)}">${escapeHtml(r.DistrictName)}</option>`;
                    });
                    dom.eaDistrict.html(opts).prop('disabled', false);
                    return eaDistricts;
                });
            }

            function eaLoadWards(districtId) {
                if (!districtId) {
                    eaWards = [];
                    dom.eaWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
                    return $.Deferred().resolve([]).promise();
                }
                return $.get(REGION_API, { action: 'region_wards', district_id: districtId }).then(res => {
                    eaWards = Array.isArray(res?.rows) ? res.rows : [];
                    let opts = '<option value="">-- Chọn phường/xã --</option>';
                    eaWards.forEach(r => {
                        opts += `<option value="${r.WardCode}" data-name="${escapeHtml(r.WardName)}">${escapeHtml(r.WardName)}</option>`;
                    });
                    dom.eaWard.html(opts).prop('disabled', false);
                    return eaWards;
                });
            }

            // ── Áp dụng gợi ý Goong: điền đường + tự khớp Tỉnh/Quận/Phường theo GHN ──
            // opts.fullStreet = true → điền nguyên 'full' (dùng cho GPS reverse).
            function eaApplySuggestion(top, opts) {
                if (!top || typeof top !== 'object') return;
                opts = opts || {};
                const aiStreet = String(top.street || '').trim();
                const full = String(top.full || '').trim();
                if (opts.fullStreet && full) {
                    dom.eaStreet.val(full);
                } else if (aiStreet) {
                    const cur = String(dom.eaStreet.val() || '').trim();
                    const houseNo = (cur.match(/^\s*(\d+[a-zA-Z]?(?:\/\d+[a-zA-Z]?)*)\b/) || [])[1] || '';
                    const hasNum = /\d/.test(aiStreet);
                    dom.eaStreet.val(houseNo && !hasNum ? (houseNo + ' ' + aiStreet) : aiStreet);
                } else if (full) {
                    dom.eaStreet.val((full.split(',')[0] || full).trim());
                }

                const provCands = (Array.isArray(top.province_candidates) && top.province_candidates.length)
                    ? top.province_candidates : [String(top.province || '')].filter(Boolean);
                const distCands = (Array.isArray(top.district_candidates) && top.district_candidates.length)
                    ? top.district_candidates : [String(top.district || '')].filter(Boolean);
                const wardCands = (Array.isArray(top.ward_candidates) && top.ward_candidates.length)
                    ? top.ward_candidates : [String(top.ward || '')].filter(Boolean);

                if (!provCands.length && !distCands.length && !wardCands.length) return;

                // ID đã được server (cart_resolve_ghn_ids) khớp sẵn → ưu tiên dùng,
                // chỉ rơi xuống khớp theo tên khi không có ID. (Giống account.php)
                const provId = Number(top.province_id || 0);
                const distId = Number(top.district_id || 0);
                const wardCode = String(top.ward_code || '').trim();

                dom.btnSaveAddress.prop('disabled', true);
                dom.btnSaveAddressText.text('Đang chuẩn hoá khu vực...');

                $.when(eaProvinces.length ? eaProvinces : eaLoadProvinces()).then(() => {
                    const mp = (provId > 0 && eaProvinces.find(p => Number(p.ProvinceID) === provId))
                        || eaFindByCandidates(eaProvinces, 'ProvinceName', provCands, false);
                    if (!mp) return;
                    dom.eaProvince.val(String(mp.ProvinceID));
                    return eaLoadDistricts(mp.ProvinceID).then(() => {
                        const md = (distId > 0 && eaDistricts.find(d => Number(d.DistrictID) === distId))
                            || eaFindByCandidates(eaDistricts, 'DistrictName', distCands, true);
                        if (!md) return;
                        dom.eaDistrict.val(String(md.DistrictID));
                        return eaLoadWards(md.DistrictID).then(() => {
                            const mw = (wardCode !== '' && eaWards.find(w => String(w.WardCode) === wardCode))
                                || eaFindByCandidates(eaWards, 'WardName', wardCands, true);
                            if (mw) dom.eaWard.val(String(mw.WardCode));
                        });
                    });
                }).always(() => {
                    dom.btnSaveAddress.prop('disabled', false);
                    dom.btnSaveAddressText.text('Lưu địa chỉ');
                });
            }

            function eaRenderSuggestions(list) {
                if (!Array.isArray(list) || !list.length) {
                    dom.eaSearchList.empty().addClass('d-none');
                    return;
                }
                const html = list.slice(0, 6).map(item => {
                    const fullRaw = String(item.full || '').trim();
                    let mainRaw = String(item.street || '').trim();
                    if (!mainRaw) mainRaw = fullRaw.split(',')[0] || fullRaw;
                    let subRaw = String(item.sub || '').trim();
                    if (!subRaw) subRaw = [item.ward, item.district, item.province].filter(Boolean).join(', ');
                    if (!subRaw && fullRaw) {
                        subRaw = fullRaw.split(',').slice(1).map(s => s.trim())
                            .filter(s => s && !/^\d+$/.test(s) && s !== 'Việt Nam').join(', ');
                    }
                    return '' +
                        '<button type="button" class="ea-suggest-item"' +
                        ' data-full="' + escapeHtml(item.full || '') + '"' +
                        ' data-street="' + escapeHtml(item.street || '') + '"' +
                        ' data-ward="' + escapeHtml(item.ward || '') + '"' +
                        ' data-district="' + escapeHtml(item.district || '') + '"' +
                        ' data-province="' + escapeHtml(item.province || '') + '"' +
                        ' data-place-id="' + escapeHtml(item.place_id || '') + '">' +
                        '<span class="ea-suggest-item-main">' + escapeHtml(mainRaw) + '</span>' +
                        (subRaw ? '<span class="ea-suggest-item-sub">' + escapeHtml(subRaw) + '</span>' : '') +
                        '</button>';
                }).join('');
                dom.eaSearchList.html(html).removeClass('d-none');
            }

            // ── Ô "Địa chỉ chi tiết" kiêm autocomplete (giống account.php) ──
            // Ưu tiên gợi ý từ địa chỉ đã lưu của user, sau đó Goong AutoComplete qua server.
            dom.eaStreet.on('input', function() {
                const val = String($(this).val() || '').trim();
                if (eaSearchTimer) clearTimeout(eaSearchTimer);
                if (val.length < 3) {
                    dom.eaSearchList.empty().addClass('d-none');
                    return;
                }
                eaSearchTimer = setTimeout(function() {
                    const keyword = val.toLowerCase();
                    // 1) Địa chỉ đã lưu của user (nếu có).
                    const local = eaSavedLocations.map(function(loc) {
                        const streetLine = String(loc.street || '').trim();
                        const areaLine = [loc.ward, loc.district, loc.province].filter(Boolean).join(', ');
                        return {
                            full: [streetLine, areaLine].filter(Boolean).join(', '),
                            street: streetLine,
                            ward: String(loc.ward || ''),
                            district: String(loc.district || ''),
                            province: String(loc.province || '')
                        };
                    }).filter(function(item) {
                        return String(item.full || '').toLowerCase().includes(keyword);
                    });
                    if (local.length) {
                        eaRenderSuggestions(local);
                        return;
                    }
                    // 2) Goong AutoComplete (geo_search) — fallback Nominatim ở server.
                    $.get(GEO_API, { ajax: 'geo_search', q: val }, function(res) {
                        eaRenderSuggestions(res && res.ok && Array.isArray(res.data) ? res.data : []);
                    }).fail(function() {
                        eaRenderSuggestions([]);
                    });
                }, 450);
            });

            // Chọn 1 gợi ý → nếu có place_id (Goong) thì lấy chi tiết khu vực đầy đủ rồi áp dụng.
            dom.eaSearchList.on('click', '.ea-suggest-item', function() {
                const item = {
                    full: $(this).data('full') || '',
                    street: $(this).data('street') || '',
                    ward: $(this).data('ward') || '',
                    district: $(this).data('district') || '',
                    province: $(this).data('province') || ''
                };
                const placeId = String($(this).data('place-id') || '');
                dom.eaSearchList.empty().addClass('d-none');

                if (placeId) {
                    $.get(GEO_API, { ajax: 'geo_place_detail', place_id: placeId }, function(res) {
                        if (res && res.ok && res.data) {
                            if (!res.data.street) res.data.street = item.street;
                            eaApplySuggestion(res.data, {});
                        } else {
                            eaApplySuggestion(item, {});
                        }
                    }).fail(function() { eaApplySuggestion(item, {}); });
                } else {
                    eaApplySuggestion(item, {});
                }
            });

            // Đóng dropdown khi click ra ngoài.
            $(document).on('click', function(e) {
                if ($(e.target).closest('#eaStreet, #eaSearchList').length) return;
                dom.eaSearchList.empty().addClass('d-none');
            });

            // ── Nút GPS: lấy vị trí hiện tại → reverse geocode → điền địa chỉ + chọn khu vực ──
            dom.eaGpsBtn.on('click', function() {
                const $btn = $(this);
                if (!navigator.geolocation) {
                    notify('warning', 'Trình duyệt không hỗ trợ định vị');
                    return;
                }
                const old = $btn.html();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
                navigator.geolocation.getCurrentPosition(function(pos) {
                    $.get(GEO_API, { ajax: 'geo_reverse', lat: pos.coords.latitude, lng: pos.coords.longitude }, function(res) {
                        if (res && res.ok && res.data) {
                            eaApplySuggestion(res.data, { fullStreet: true });
                            notify('success', 'Đã lấy vị trí của bạn');
                        } else {
                            notify('error', 'Không xác định được địa chỉ từ vị trí hiện tại');
                        }
                    }).fail(function() {
                        notify('error', 'Không lấy được địa chỉ từ vị trí hiện tại');
                    }).always(function() {
                        $btn.prop('disabled', false).html(old);
                    });
                }, function(err) {
                    $btn.prop('disabled', false).html(old);
                    notify('warning', (err && err.code === 1) ? 'Bạn đã từ chối quyền truy cập vị trí' : 'Không lấy được vị trí hiện tại');
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
            });

            // Cascading thủ công khi user tự đổi select.
            dom.eaProvince.on('change', function() {
                eaLoadDistricts($(this).val());
            });

            dom.eaDistrict.on('change', function() {
                eaLoadWards($(this).val());
            });

            dom.btnEditAddress.on('click', function() {
                const detail = $(this).data('detail') || {};
                dom.eaRecipientName.val(detail.recipient_name || '');
                dom.eaPhone.val(detail.contact_phone || '');
                dom.eaStreet.val(detail.street || '');
                dom.eaSearchList.empty().addClass('d-none');
                dom.eaDistrict.prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
                dom.eaWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');

                const savedProvinceId = String(detail.province_id || '');
                const savedDistrictId = String(detail.district_id || '');
                const savedWardCode = String(detail.ward_code || '');

                // Tải tỉnh, sau đó khôi phục quận/phường đã lưu theo thứ tự.
                eaLoadProvinces().then(function() {
                    if (!savedProvinceId) return;
                    dom.eaProvince.val(savedProvinceId);
                    if (!savedDistrictId) return;
                    eaLoadDistricts(savedProvinceId).then(function() {
                        dom.eaDistrict.val(savedDistrictId);
                        if (!savedWardCode) return;
                        eaLoadWards(savedDistrictId).then(function() {
                            dom.eaWard.val(savedWardCode);
                        });
                    });
                });

                dom.editAddressModal.modal('show');
            });

            dom.btnSaveAddress.on('click', function() {
                const orderId = dom.btnEditAddress.attr('data-id');
                const recipientName = dom.eaRecipientName.val().trim();
                const phone = dom.eaPhone.val().trim();
                const provinceId = dom.eaProvince.val();
                const province = dom.eaProvince.find(':selected').data('name') || dom.eaProvince.find(':selected').text();
                const districtId = dom.eaDistrict.val();
                const district = dom.eaDistrict.find(':selected').data('name') || dom.eaDistrict.find(':selected').text();
                const wardCode = dom.eaWard.val();
                const ward = dom.eaWard.find(':selected').data('name') || dom.eaWard.find(':selected').text();
                const street = dom.eaStreet.val().trim();

                if (!recipientName) {
                    notify('warning', 'Vui lòng nhập họ tên người nhận');
                    return;
                }
                if (!phone || phone.replace(/[^0-9]/g, '').length < 9) {
                    notify('warning', 'Số điện thoại không hợp lệ');
                    return;
                }
                if (!provinceId) {
                    notify('warning', 'Vui lòng chọn Tỉnh/Thành phố');
                    return;
                }
                if (!districtId) {
                    notify('warning', 'Vui lòng chọn Quận/Huyện');
                    return;
                }
                if (!wardCode) {
                    notify('warning', 'Vui lòng chọn Phường/Xã');
                    return;
                }
                if (!street) {
                    notify('warning', 'Vui lòng nhập địa chỉ chi tiết');
                    return;
                }

                dom.btnSaveAddressText.text('Đang lưu...');
                dom.btnSaveAddressSpin.removeClass('d-none');
                dom.btnSaveAddress.prop('disabled', true);

                $.post(API, {
                    action: 'update_address',
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
                        notify('success', 'Đã cập nhật địa chỉ giao hàng');
                        dom.editAddressModal.modal('hide');
                        openDetail(orderId);
                    } else {
                        notify('error', res?.msg || 'Không thể cập nhật địa chỉ');
                    }
                }).fail(() => {
                    notify('error', 'Lỗi kết nối, vui lòng thử lại');
                }).always(() => {
                    dom.btnSaveAddressText.text('Lưu địa chỉ');
                    dom.btnSaveAddressSpin.addClass('d-none');
                    dom.btnSaveAddress.prop('disabled', false);
                });
            });

            openDetail(orderId);
        }

        $(init);
    })(jQuery);
</script>