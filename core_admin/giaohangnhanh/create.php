<?php
require_once __DIR__ . '/../_admin_guard.php';
$apiUrl = $basePath . '/core_admin/giaohangnhanh/ajax/api.php';
?>

<div id="ghnCreatePage" class="container-fluid py-4" style="padding-bottom:190px;">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-box-seam fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Tạo đơn - Giao Hàng Nhanh</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" style="font-size: 0.72rem;">GHN API</span>
                </div>
                <p class="text-muted mb-0 small" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Nạp đơn hệ thống 1 click, chuẩn hóa dữ liệu nhận, tự tính phí khi thay đổi dữ liệu và tạo đơn GHN.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-white border shadow-sm btn-sm fw-semibold" href="<?= h($basePath) ?>/admin/giaohangnhanh/home" style="border-radius: 8px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="bi bi-arrow-left"></i> Quay lại GHN
            </a>
            <button class="btn btn-white border shadow-sm btn-sm" id="btnReloadAll" title="Làm mới" style="border-radius: 8px; height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- SENDER CARD -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-shop me-1 text-primary"></i> Bên gửi (Chi nhánh)</h5>
                    <div class="text-muted small" style="font-size: 0.78rem;">Đổi chi nhánh hoặc shop đang hoạt động để gửi hàng.</div>
                </div>
                <a class="btn btn-outline-primary btn-sm fw-semibold px-3 py-1.5" href="<?= h($basePath) ?>/admin/giaohangnhanh/store" style="border-radius: 8px;"><i class="bi bi-pencil-square me-1"></i>Đổi shop</a>
            </div>
            <div class="border border-light-subtle rounded-3 bg-light p-3 small shadow-sm" id="senderMiniCard" style="font-size: 0.85rem;">
                <div class="row g-2">
                    <div class="col-12 col-md-4"><strong>Tên shop:</strong> <span id="senderMiniName" class="text-dark fw-semibold">Đang tải...</span></div>
                    <div class="col-12 col-md-4"><strong>SĐT:</strong> <span id="senderMiniPhone" class="text-dark fw-semibold">—</span></div>
                    <div class="col-12 col-md-4"><strong>Địa chỉ:</strong> <span id="senderMiniAddress" class="text-muted">—</span></div>
                </div>
            </div>
            <input type="hidden" id="from_name">
            <input type="hidden" id="from_phone">
            <input type="hidden" id="from_address">
            <input type="hidden" id="from_district_id">
            <input type="hidden" id="from_ward_code">
        </div>
    </div>

    <!-- SYSTEM ORDERS LIST -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-list-check me-1 text-primary"></i> Chọn đơn hệ thống</h5>
                    <div class="text-muted small" style="font-size: 0.78rem;">Chọn đơn hệ thống để tự điền người nhận, sản phẩm, kích thước.</div>
                </div>
                <button class="btn btn-primary btn-sm fw-semibold px-3 py-1.5" id="btnLoadSystemOrders" style="border-radius: 8px;"><i class="bi bi-arrow-repeat me-1"></i> Tải đơn</button>
            </div>

            <div class="d-flex gap-2 mb-3 flex-wrap" id="sysStatusTabs">
                <button class="btn btn-sm btn-primary sys-status-tab active px-3 py-1.5" data-status="open" style="border-radius: 8px;"><i class="bi bi-inbox me-1"></i> Chưa xử lý</button>
                <button class="btn btn-sm btn-outline-primary sys-status-tab px-3 py-1.5" data-status="pending" style="border-radius: 8px;"><i class="bi bi-hourglass-split me-1"></i> Đang chờ</button>
                <button class="btn btn-sm btn-outline-primary sys-status-tab px-3 py-1.5" data-status="processing" style="border-radius: 8px;"><i class="bi bi-gear me-1"></i> Đang xử lý</button>
            </div>

            <div class="border border-light-subtle rounded-3 bg-light p-3 mb-3 shadow-sm">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Tìm nhanh</label>
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute" style="left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.88rem; pointer-events:none;"></i>
                            <input id="sys_search" class="form-control" placeholder="Tìm mã đơn/khách/sdt" style="padding-left:38px !important; border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                        </div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Thanh toán</label>
                        <select id="sys_payment_method" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            <option value="">Tất cả</option>
                            <option value="cod">Tiền mặt</option>
                            <option value="momo">MoMo</option>
                            <option value="vnpay">VNPAY</option>
                            <option value="zalopay">ZaloPay</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Cỡ đơn</label>
                        <select id="sys_order_size" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            <option value="">Tất cả</option>
                            <option value="small">Đơn nhỏ</option>
                            <option value="big">Đơn lớn</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Sắp xếp</label>
                        <select id="sys_sort" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            <option value="created_desc">Mới nhất</option>
                            <option value="created_asc">Cũ nhất</option>
                            <option value="amount_desc">Tổng tiền cao → thấp</option>
                            <option value="amount_asc">Tổng tiền thấp → cao</option>
                            <option value="qty_desc">SL nhiều → ít</option>
                            <option value="qty_asc">SL ít → nhiều</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Hiển thị</label>
                        <select id="sys_page_size" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            <option value="20">20 dòng</option>
                            <option value="50">50 dòng</option>
                            <option value="100">100 dòng</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Khoảng thời gian</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" id="sys_date_from" class="form-control" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;" title="Từ ngày">
                            </div>
                            <div class="col-6">
                                <input type="date" id="sys_date_to" class="form-control" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;" title="Đến ngày">
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Khoảng số lượng</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" id="sys_qty_min" class="form-control" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;" min="0" placeholder="SL từ">
                            </div>
                            <div class="col-6">
                                <input type="number" id="sys_qty_max" class="form-control" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;" min="0" placeholder="SL đến">
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Khoảng tổng tiền</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" id="sys_amount_min" class="form-control" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;" min="0" placeholder="Giá từ">
                            </div>
                            <div class="col-6">
                                <input type="number" id="sys_amount_max" class="form-control" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;" min="0" placeholder="Giá đến">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden mb-3">
                <div class="table-responsive" style="max-height:320px;">
                    <table class="table table-hover align-middle mb-0 table-striped">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="font-size: 0.8rem; font-weight: 700;">Mã</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Ngày + Tên</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Địa chỉ</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">SL</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Tổng tiền</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">HTTT</th>
                                <th class="text-end pe-3" style="font-size: 0.8rem; font-weight: 700;">Tác vụ</th>
                            </tr>
                        </thead>
                        <tbody id="sysOrderBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Chưa tải dữ liệu</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center" id="sysOrderPagerWrap">
                <div class="small text-muted fw-semibold" id="sysOrderPagerInfo">0 dòng</div>
                <div class="btn-group" role="group" aria-label="System order pager">
                    <button class="btn btn-outline-secondary btn-sm" id="sysPagePrev" type="button" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;"><i class="bi bi-chevron-left"></i> Trước</button>
                    <button class="btn btn-outline-secondary btn-sm" id="sysPageNext" type="button" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">Sau <i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- RECEIVER INFO -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <h5 class="fw-bold mb-3 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-person-bounding-box me-1 text-primary"></i> Thông tin người nhận</h5>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Tên người nhận</label>
                    <input class="form-control" id="to_name" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">SĐT người nhận</label>
                    <input class="form-control" id="to_phone" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Mã đơn riêng (client_order_code)</label>
                    <input class="form-control" id="client_order_code" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Địa chỉ chi tiết người nhận</label>
                    <input class="form-control" id="to_address" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Tỉnh/Thành</label>
                    <select class="form-select" id="to_province_id" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;"><option value="">Chọn Tỉnh/Thành</option></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Quận/Huyện</label>
                    <select class="form-select" id="to_district_id" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;"><option value="">Chọn Quận/Huyện</option></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Phường/Xã</label>
                    <select class="form-select" id="to_ward_code" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;"><option value="">Chọn Phường/Xã</option></select>
                </div>
                <div class="col-12 d-flex align-items-center gap-3 mt-3">
                    <button class="btn btn-outline-primary btn-sm fw-semibold px-3 py-2" id="btnResolveReceiver" style="border-radius: 8px;"><i class="bi bi-geo-alt me-1"></i> Quét địa chỉ</button>
                    <div class="small text-muted fw-medium" id="receiver_hint">Chờ nạp đơn để quét địa chỉ.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRODUCTS IN ORDER -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <h5 class="fw-bold mb-3 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-box2 me-1 text-primary"></i> Sản phẩm trong đơn</h5>
            <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden">
                <div class="table-responsive" style="max-height:320px;">
                    <table class="table table-hover align-middle mb-0 table-striped">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="font-size: 0.8rem; font-weight: 700;">Ảnh</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Tên sản phẩm</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Tên phân loại</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Mã SP/SKU</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">SL</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Khối lượng (gram)</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Dài (cm)</th>
                                <th style="font-size: 0.8rem; font-weight: 700;">Rộng (cm)</th>
                                <th class="pe-3 text-end" style="font-size: 0.8rem; font-weight: 700;">Cao (cm)</th>
                            </tr>
                        </thead>
                        <tbody id="productBody">
                            <tr><td colspan="9" class="text-center text-muted py-4">Chưa có sản phẩm</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- WEIGHT & PACKAGING SIZE -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <h5 class="fw-bold mb-3 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-aspect-ratio me-1 text-primary"></i> Trọng lượng & Kích thước đóng gói</h5>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="border border-light-subtle rounded-3 bg-light p-3 h-100 shadow-sm">
                        <div class="small fw-bold text-dark text-uppercase mb-3" style="font-size: 0.78rem;"><i class="bi bi-speedometer2 me-1 text-primary"></i> Khối lượng đóng gói (0-100kg)</div>
                        <div class="text-center mb-3">
                            <div class="fw-bold fs-4 text-primary lh-1" id="weight_kg_value">1.2 kg</div>
                            <div class="text-secondary small mt-1" id="weight_g_value">1,200 g</div>
                        </div>
                        <input type="range" class="form-range" id="weight_kg_dial" min="0" max="100" step="1" value="1">
                        <div class="d-flex justify-content-between gap-2 mt-3 flex-wrap">
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-weight-mark="1" data-kg="0">0</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-weight-mark="1" data-kg="25">25</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-weight-mark="1" data-kg="50">50</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-weight-mark="1" data-kg="75">75</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-weight-mark="1" data-kg="100">100</button>
                        </div>
                        
                        <div class="border-top mt-3 pt-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="weight_manual_toggle">
                                <label class="form-check-label small fw-semibold text-dark" for="weight_manual_toggle">Nhập SL thủ công</label>
                            </div>
                            <input type="number" class="form-control" id="weight_manual_kg" min="0" max="1000" step="0.1" placeholder="0 - 1000 kg" disabled style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            <div class="small text-muted mt-1" style="font-size: 0.75rem;">Tối đa 1000kg (khi bật thủ công)</div>
                        </div>
                    </div>
                    <input type="hidden" id="weight" value="1200">
                </div>
                <div class="col-12 col-md-6">
                    <div class="border border-light-subtle rounded-3 bg-light p-3 h-100 shadow-sm">
                        <div class="small fw-bold text-dark text-uppercase mb-3" style="font-size: 0.78rem;"><i class="bi bi-box-seam me-1 text-primary"></i> Kích thước thùng hàng</div>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-2.5 py-1.5 fw-bold" id="dimLenPreview" style="font-size: 0.75rem;">D: 20cm</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-2.5 py-1.5 fw-bold" id="dimWidPreview" style="font-size: 0.75rem;">R: 20cm</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-2.5 py-1.5 fw-bold" id="dimHeiPreview" style="font-size: 0.75rem;">C: 20cm</span>
                        </div>
                        <div class="row g-2 pt-1">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Dài (cm)</label>
                                <input type="number" class="form-control" id="length" value="20" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Rộng (cm)</label>
                                <input type="number" class="form-control" id="width" value="20" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Cao (cm)</label>
                                <input type="number" class="form-control" id="height" value="20" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SERVICES SELECT -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <h5 class="fw-bold mb-3 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-truck me-1 text-primary"></i> Chọn dịch vụ giao hàng</h5>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="border border-light-subtle rounded-3 bg-light p-3 h-100 shadow-sm">
                        <div class="small fw-bold text-dark text-uppercase mb-3" style="font-size: 0.78rem;"><i class="bi bi-info-circle me-1 text-primary"></i> Thông tin đơn hàng</div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Nội dung hàng gửi</label>
                                <input class="form-control" id="content" value="Đơn hàng từ hệ thống" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Tổng COD</label>
                                <input type="text" class="form-control" id="cod_amount" value="0" inputmode="numeric" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Tổng giá trị đơn hàng</label>
                                <input type="text" class="form-control" id="goods_value" value="0" inputmode="numeric" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Giao thất bại thu tiền</label>
                                <input type="text" class="form-control" id="cod_failed_amount" value="0" inputmode="numeric" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="border border-light-subtle rounded-3 bg-light p-3 h-100 shadow-sm">
                        <div class="small fw-bold text-dark text-uppercase mb-3" style="font-size: 0.78rem;"><i class="bi bi-shield-check me-1 text-primary"></i> Dịch vụ & người trả ship</div>
                        <div class="row g-2">
                            <div class="col-12 d-none">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Dịch vụ giao hàng GHN</label>
                                <select id="service_id" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                                    <option value="">Tự chọn theo loại dịch vụ</option>
                                </select>
                                <div class="small text-muted mt-1" id="service_hint">Chưa tải danh sách dịch vụ theo tuyến gửi/nhận.</div>
                            </div>
                            <div class="col-12 d-none">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Loại dịch vụ (fallback)</label>
                                <select id="service_type_id" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                                    <option value="1">Nhanh (service_type_id=1)</option>
                                    <option value="2"selected>Chuẩn (service_type_id=2)</option>
                                    <option value="3">Tiết kiệm (service_type_id=3)</option>
                                    <option value="5">Traditional Delivery (service_type_id=5)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Thanh toán phí ship</label>
                                <select id="payment_type_id" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                                    <option value="1">Người nhận trả phí</option>
                                    <option value="2" selected>Người gửi trả phí</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Yêu cầu xem hàng</label>
                                <select class="form-select" id="required_note" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                                    <option value="KHONGCHOXEMHANG">Không cho xem</option>
                                    <option value="CHOXEMHANGKHONGTHU">Cho xem không thử</option>
                                    <option value="CHOTHUHANG">Cho thử hàng</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Ghi chú thêm cho GHN</label>
                                <input class="form-control" id="delivery_note" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PICKUP TYPE -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <h5 class="fw-bold mb-3 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-geo-fill me-1 text-primary"></i> Hình thức lấy hàng</h5>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Chọn hình thức</label>
                    <select id="pickup_type" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;">
                        <option value="pickup">Lấy tận nơi</option>
                    </select>
                </div>
                <div class="col-12 col-md-4" id="pickShiftBox">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Ca lấy hàng</label>
                    <select id="pick_shift" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;"><option value="2">Ca mặc định</option></select>
                </div>
                <div class="col-12 col-md-4 d-none" id="stationBox">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">Bưu cục gần shop</label>
                    <select id="station_id" class="form-select" style="border-radius:10px; height: 40px; border-color: #cbd5e1; font-size: 0.88rem;"><option value="">Chọn bưu cục</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- DEBUG JSON -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="_card-body _p-4">
            <h5 class="fw-bold mb-3 text-dark text-uppercase" style="font-size: 0.88rem; letter-spacing: 0.05em;"><i class="bi bi-bug me-1 text-primary"></i> Debug JSON</h5>
            <pre class="mb-0 bg-dark text-light rounded-3 p-3 small font-monospace overflow-auto" id="debugJson" style="min-height:120px;max-height:300px;white-space:pre-wrap;">Chưa có dữ liệu.</pre>
        </div>
    </div>
</div>

<!-- FIXED BOTTOM BAR -->
<div class="fixed-bottom bg-white border-top shadow-lg py-3" id="feeStickyCard" style="z-index: 1030;">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-12 col-lg-8 mb-3 mb-lg-0">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 fw-bold" style="font-size: 0.8rem;">
                        <i class="bi bi-receipt-cutoff me-1"></i> Phí ship ước tính
                    </span>
                    <div class="border border-light-subtle rounded-pill bg-light px-3 py-1.5 small fw-semibold text-dark">
                        <span class="text-muted fw-normal">Phí:</span> <span class="text-danger fw-bold" id="feeStickySummary">Nhẹ: — | Nặng: —</span>
                    </div>
                    <div class="border border-light-subtle rounded-pill bg-light px-3 py-1.5 small fw-semibold text-dark">
                        <span class="text-muted fw-normal">Khối lượng:</span> Đóng gói: <span id="weightOverviewDeclared" class="text-primary fw-bold">—</span> | Sp: <span id="weightOverviewProducts" class="text-secondary fw-bold">—</span> | Lệch: <span id="weightOverviewDiff" class="text-danger fw-bold">—</span>
                    </div>
                    <div class="text-muted small fw-medium" id="feeAutoHint"><i class="bi bi-info-circle me-1"></i> Chờ dữ liệu...</div>
                    <span class="d-none" id="feeLightAmount">—</span>
                    <span class="d-none" id="feeHeavyAmount">—</span>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary w-50 fw-bold py-2.5" id="btnPreviewOrderSticky" type="button" style="border-radius: 10px; font-size: 0.9rem;">
                        <i class="bi bi-eye me-1"></i> PREVIEW ĐƠN
                    </button>
                    <button class="btn btn-success w-50 fw-bold py-2.5 text-white" id="btnCreateOrderSticky" type="button" style="background-color: var(--theme-primary, #0c4c29); border: none; border-radius: 10px; font-size: 0.9rem;">
                        <i class="bi bi-check2-circle me-1"></i> TẠO ĐƠN
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PREVIEW MODAL -->
<div class="modal fade" id="previewInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Preview hoá đơn đơn hàng GHN</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pt-3 pb-4 bg-white">
                <div id="previewInvoiceBody" class="small">Chưa có dữ liệu preview.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="btnPrintPreviewInvoice"><i class="bi bi-printer"></i> In thử</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?=  h($apiUrl) ?>';
    const BASE_PATH = '<?= h($basePath) ?>';

    let currentSystemOrder = null;
    let currentSystemOrders = [];
    let currentSystemOrderStatus = 'open';
    let currentSystemOrderFiltered = [];
    let currentSystemOrderPage = 1;
    let currentAvailableServices = [];
    let autoFeeTimer = null;
    let loadingFee = false;

    function parseJsonSafe(text){
        if (text && typeof text === 'object') return text;
        try { return JSON.parse(String(text || '')); } catch(e){ return null; }
    }

    function api(action, data = {}, method = 'POST'){
        return new Promise((resolve, reject) => {
            $.ajax({
                url: API,
                method,
                dataType: 'text',
                data: Object.assign({ action }, data),
                timeout: 120000,
            }).done(function(raw){
                const parsed = parseJsonSafe(raw);
                if (!parsed) return reject({ ok:false, msg:'Phản hồi JSON không hợp lệ', raw });
                resolve(parsed);
            }).fail(function(xhr, textStatus){
                reject(parseJsonSafe(xhr?.responseText || '') || { ok:false, msg:textStatus || 'Request failed' });
            });
        });
    }

    function esc(text){
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function notify(message, type){
        if (window.toastr) {
            if (type === 'success') toastr.success(message);
            else if (type === 'warning') toastr.warning(message);
            else if (type === 'error') toastr.error(message);
            else toastr.info(message);
        } else {
            console.log((type || 'info').toUpperCase() + ': ' + message);
        }
    }

    function setDebug(data){
        $('#debugJson').text(JSON.stringify(data || {}, null, 2));
    }

    function toDateYmd(value){
        const raw = String(value || '').trim();
        if (!raw) return '';
        const normalized = raw.slice(0, 10).replace(/\//g, '-');
        if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) return normalized;
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return '';
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function inferPaymentMethod(row){
        const gatewayRaw = String(row?.payment_gateway || '').trim().toLowerCase();
        const methodRaw = String(row?.payment_method || row?.payment_type || row?.payment || row?.method || '').trim().toLowerCase();
        const source = gatewayRaw || methodRaw;

        if (!source && Number(row?.cod_amount || 0) > 0) return 'cod';

        const token = source.replace(/[^a-z0-9]/g, '');
        const aliasMap = {
            // COD / tiền mặt
            cod: 'cod',
            cash: 'cod',
            cashondelivery: 'cod',

            // MoMo / ví điện tử
            momo: 'momo',
            momoqr: 'momo',
            ewallet: 'momo',
            wallet: 'momo',
            qr: 'momo',

            // VNPAY / chuyển khoản qua cổng
            vnpay: 'vnpay',
            vnp: 'vnpay',
            bank: 'vnpay',
            transfer: 'vnpay',
            chuyenkhoan: 'vnpay',
            card: 'vnpay',
            atm: 'vnpay',
            installment: 'vnpay',

            // ZaloPay
            zalopay: 'zalopay',
            zalo: 'zalopay',
            zlp: 'zalopay'
        };

        if (token && aliasMap[token]) return aliasMap[token];

        if (source.includes('momo')) return 'momo';
        if (source.includes('vnp')) return 'vnpay';
        if (source.includes('zalo')) return 'zalopay';

        if (source.includes('cod') || source.includes('cash')) return 'cod';
        if (Number(row?.cod_amount || 0) > 0) return 'cod';

        return 'other';
    }

    function paymentLabel(method){
        if (method === 'cod') return 'COD';
        if (method === 'momo') return 'MoMo';
        if (method === 'vnpay') return 'VNPAY';
        if (method === 'zalopay') return 'ZaloPay';
        return 'Khác';
    }

    function renderSystemOrders(rows){
        const allRows = Array.isArray(rows) ? rows : [];
        currentSystemOrderFiltered = allRows;

        const pageSize = Math.max(1, Number($('#sys_page_size').val() || 20));
        const totalRows = allRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
        currentSystemOrderPage = Math.min(Math.max(1, currentSystemOrderPage), totalPages);

        const start = (currentSystemOrderPage - 1) * pageSize;
        const pageRows = allRows.slice(start, start + pageSize);

        if (!pageRows.length) {
            $('#sysOrderBody').html('<tr><td colspan="7" class="text-center text-muted">Không có đơn phù hợp bộ lọc</td></tr>');
            $('#sysOrderPagerInfo').text('0 dòng');
            $('#sysPagePrev,#sysPageNext').prop('disabled', true);
            return;
        }

        const html = pageRows.map(function(r){
            const qty = Number(r.total_qty || 0);
            const totalAmount = Number(r.total_amount || 0);
            const method = inferPaymentMethod(r);
            return '<tr>'
                + '<td><div class="fw-semibold">'+esc(r.order_id || '')+'</div></td>'
                + '<td><div class="small text-muted">'+esc(r.created_at || '')+'</div><div>'+esc(r.user_name || '')+'</div></td>'
                + '<td class="small">'+esc(r.address || '')+'</td>'
                + '<td>' + qty + '</td>'
                + '<td>' + totalAmount.toLocaleString('vi-VN') + 'đ</td>'
                + '<td><span class="badge text-bg-light border">' + paymentLabel(method) + '</span></td>'
                + '<td><button class="btn btn-sm btn-primary btnUseOrder" data-order="'+esc(r.order_id || '')+'">Nạp đơn</button></td>'
            + '</tr>';
        }).join('');
        $('#sysOrderBody').html(html);

        const fromRow = start + 1;
        const toRow = Math.min(start + pageRows.length, totalRows);
        $('#sysOrderPagerInfo').text('Hiển thị ' + fromRow + '-' + toRow + '/' + totalRows + ' (Trang ' + currentSystemOrderPage + '/' + totalPages + ')');
        $('#sysPagePrev').prop('disabled', currentSystemOrderPage <= 1);
        $('#sysPageNext').prop('disabled', currentSystemOrderPage >= totalPages);
    }

    function applySystemOrderFilters(){
        const q = String($('#sys_search').val() || '').trim().toLowerCase();
        const fromDate = String($('#sys_date_from').val() || '').trim();
        const toDate = String($('#sys_date_to').val() || '').trim();
        const qtyMin = Number($('#sys_qty_min').val() || 0);
        const qtyMaxRaw = Number($('#sys_qty_max').val() || 0);
        const amountMin = Number($('#sys_amount_min').val() || 0);
        const amountMaxRaw = Number($('#sys_amount_max').val() || 0);
        const payment = String($('#sys_payment_method').val() || '').trim();
        const sizeFilter = String($('#sys_order_size').val() || '').trim();
        const sortKey = String($('#sys_sort').val() || '').trim() || 'created_desc';

        const qtyMax = qtyMaxRaw > 0 ? qtyMaxRaw : Number.POSITIVE_INFINITY;
        const amountMax = amountMaxRaw > 0 ? amountMaxRaw : Number.POSITIVE_INFINITY;

        const filtered = (Array.isArray(currentSystemOrders) ? currentSystemOrders : []).filter(function(r){
            const orderId = String(r.order_id || '').toLowerCase();
            const userName = String(r.user_name || '').toLowerCase();
            const phone = String(r.phone || '').toLowerCase();
            const address = String(r.address || '').toLowerCase();
            const created = toDateYmd(r.created_at || '');
            const qty = Number(r.total_qty || 0);
            const amount = Number(r.total_amount || 0);
            const payMethod = inferPaymentMethod(r);
            const isBigOrder = (qty >= 5) || (amount >= 2000000);

            if (q && !(orderId.includes(q) || userName.includes(q) || phone.includes(q) || address.includes(q))) return false;
            if (fromDate && created && created < fromDate) return false;
            if (toDate && created && created > toDate) return false;
            if (qty < qtyMin || qty > qtyMax) return false;
            if (amount < amountMin || amount > amountMax) return false;
            if (payment && payMethod !== payment) return false;
            if (sizeFilter === 'big' && !isBigOrder) return false;
            if (sizeFilter === 'small' && isBigOrder) return false;
            return true;
        });

        const sorted = filtered.slice().sort(function(a, b){
            const qtyA = Number(a.total_qty || 0);
            const qtyB = Number(b.total_qty || 0);
            const amountA = Number(a.total_amount || 0);
            const amountB = Number(b.total_amount || 0);
            const timeA = (new Date(a.created_at || '')).getTime() || 0;
            const timeB = (new Date(b.created_at || '')).getTime() || 0;

            switch (sortKey) {
                case 'created_asc':
                    return timeA - timeB;
                case 'amount_desc':
                    return amountB - amountA;
                case 'amount_asc':
                    return amountA - amountB;
                case 'qty_desc':
                    return qtyB - qtyA;
                case 'qty_asc':
                    return qtyA - qtyB;
                case 'created_desc':
                default:
                    return timeB - timeA;
            }
        });

        renderSystemOrders(sorted);
    }

    function syncFeeSticky(){
        const light = $('#feeLightAmount').text() || '—';
        const heavy = $('#feeHeavyAmount').text() || '—';
        $('#feeStickySummary').text('Nhẹ: ' + light + ' | Nặng: ' + heavy);
    }

    function shopDisplayName(s){
        const sid = Number(s?.shop_id || 0);
        const display = String(s?.display_name || '').trim();
        const name = String(s?.name || '').trim();
        return display || name || ('Shop #' + sid);
    }

    function toAbsoluteUrl(path){
        const raw = String(path || '').trim();
        if (!raw) return '';
        if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
        const clean = raw.replace(/\\/g, '/').replace(/^\.?\//, '');
        const prefix = String(BASE_PATH || '').replace(/\/$/, '');
        if (clean.startsWith('/')) return (prefix ? prefix : '') + clean;
        return (prefix ? prefix + '/' : '/') + clean;
    }

    function vNum(selector, def = 0){
        const raw = String($(selector).val() || '').trim();
        if (!raw) return def;

        let normalized = raw.replace(/\s+/g, '').replace(/[^0-9,.-]/g, '');
        if (!normalized) return def;

        const hasDot = normalized.indexOf('.') >= 0;
        const hasComma = normalized.indexOf(',') >= 0;
        if (hasDot && hasComma) {
            normalized = normalized.replace(/\./g, '').replace(/,/g, '.');
        } else if (hasDot) {
            normalized = normalized.replace(/\./g, '');
        } else if (hasComma) {
            normalized = normalized.replace(/,/g, '');
        }

        const v = Number(normalized);
        return Number.isFinite(v) ? v : def;
    }

    function formatMoneyVnd(value){
        const amount = Math.max(0, Math.round(Number(value || 0)));
        return amount.toLocaleString('vi-VN') + 'đ';
    }

    function unformatMoneyVnd(text){
        const raw = String(text || '').trim();
        if (!raw) return 0;

        let normalized = raw.replace(/\s+/g, '').replace(/[^0-9,.-]/g, '');
        if (!normalized) return 0;

        const hasDot = normalized.indexOf('.') >= 0;
        const hasComma = normalized.indexOf(',') >= 0;
        if (hasDot && hasComma) {
            normalized = normalized.replace(/\./g, '').replace(/,/g, '.');
        } else if (hasDot) {
            normalized = normalized.replace(/\./g, '');
        } else if (hasComma) {
            normalized = normalized.replace(/,/g, '');
        }

        const value = Number(normalized || 0);
        return Number.isFinite(value) ? Math.max(0, Math.round(value)) : 0;
    }

    function setMoneyInput(selector, value){
        const amount = unformatMoneyVnd(value);
        $(selector).val(formatMoneyVnd(amount));
    }

    function collectProducts(){
        const rows = [];
        $('#productBody tr').each(function(){
            const tr = $(this);
            const name = String(tr.find('.prod-name').val() || '').trim();
            if (!name) return;
            rows.push({
                name,
                sku: String(tr.find('.prod-sku').val() || '').trim(),
                variant_name: String(tr.find('.prod-variant').val() || '').trim(),
                quantity: Math.max(1, Number(tr.find('.prod-qty').val() || 1)),
                price: Math.max(0, Number(tr.find('.prod-price').val() || 0)),
                weight: Math.max(1, Number(tr.find('.prod-weight').val() || 1200)),
                length: Math.max(1, Number(tr.find('.prod-length').val() || 20)),
                width: Math.max(1, Number(tr.find('.prod-width').val() || 20)),
                height: Math.max(1, Number(tr.find('.prod-height').val() || 20)),
                image: String(tr.find('.prod-image').val() || '').trim(),
                is_gift: Number(tr.find('.prod-is-gift').val() || 0) ? 1 : 0,
            });
        });
        return rows;
    }

    function buildPayload(){
        return {
            sender: {
                name: String($('#from_name').val() || '').trim(),
                phone: String($('#from_phone').val() || '').trim(),
                address: String($('#from_address').val() || '').trim(),
                from_district_id: vNum('#from_district_id', 0),
                from_ward_code: String($('#from_ward_code').val() || '').trim(),
            },
            receiver: {
                name: String($('#to_name').val() || '').trim(),
                phone: String($('#to_phone').val() || '').trim(),
                address: String($('#to_address').val() || '').trim(),
                to_province_id: vNum('#to_province_id', 0),
                to_district_id: vNum('#to_district_id', 0),
                to_ward_code: String($('#to_ward_code').val() || '').trim(),
            },
            products: collectProducts(),
            order_info: {
                system_order_id: String(currentSystemOrder?.order_id || ''),
                content: String($('#content').val() || '').trim(),
                weight: Math.max(1, vNum('#weight', 1200)),
                length: Math.max(1, vNum('#length', 20)),
                width: Math.max(1, vNum('#width', 20)),
                height: Math.max(1, vNum('#height', 20)),
                cod_amount: Math.max(0, vNum('#cod_amount', 0)),
                cod_failed_amount: Math.max(0, vNum('#cod_failed_amount', 0)),
                goods_value: Math.max(0, vNum('#goods_value', 0)),
                client_order_code: String($('#client_order_code').val() || '').trim(),
                payment_method: inferPaymentMethod(currentSystemOrder),
            },
            service: {
                pickup_type: String($('#pickup_type').val() || 'pickup'),
                pick_shift: [Math.max(1, Number($('#pick_shift').val() || 2))],
                station_id: vNum('#station_id', 0),
                service_id: vNum('#service_id', 0),
                service_type_id: vNum('#service_type_id', 2),
                payment_type_id: vNum('#payment_type_id', 2),
                coupon: String($('#coupon').val() || '').trim(),
            },
            delivery_note: {
                required_note: String($('#required_note').val() || 'KHONGCHOXEMHANG'),
                note: String($('#delivery_note').val() || '').trim(),
            }
        };
    }

    function validatePayload(payload, mode = 'create'){
        if (!payload.sender.from_district_id || !payload.sender.from_ward_code) return 'Chưa có thông tin shop gửi hàng hoạt động';
        if (!payload.receiver.name || !payload.receiver.phone || !payload.receiver.address) return 'Thiếu thông tin người nhận';
        if (!payload.receiver.to_district_id || !payload.receiver.to_ward_code) return 'Thiếu tỉnh/quận/phường người nhận';
        if (!Array.isArray(payload.products) || !payload.products.length) return 'Chưa có sản phẩm';
        if (mode === 'fee') return '';
        return '';
    }

    function scheduleAutoFee(){
        if (autoFeeTimer) clearTimeout(autoFeeTimer);
        autoFeeTimer = setTimeout(function(){
            calcFees(true);
        }, 700);
    }

    function syncWeightUIFromGram(valueGram, options = {}){
        const gram = Math.max(0, Number(valueGram || 0));
        const kg = gram / 1000;
        const kgRounded = Math.round(kg * 10) / 10;
        const manualMode = (typeof options.manualMode === 'boolean') ? options.manualMode : (kgRounded > 100);
        $('#weight').val(String(Math.round(gram)));
        $('#weight_kg_dial').val(String(Math.round(Math.max(0, Math.min(100, kgRounded)))));
        $('#weight_manual_kg').val(String(kgRounded));
        $('#weight_manual_toggle').prop('checked', manualMode);
        $('#weight_manual_kg').prop('disabled', !manualMode);
        $('#weight_kg_value').text(kgRounded.toLocaleString('vi-VN', { minimumFractionDigits: 0, maximumFractionDigits: 1 }) + ' kg');
        $('#weight_g_value').text(Math.round(gram).toLocaleString('vi-VN') + ' g');
    }

    function syncWeightFromSlider(){
        if (!$('#weight_manual_toggle').is(':checked')) {
            syncWeightFromProducts();
            return;
        }
        const kg = Math.max(0, Math.min(100, Number($('#weight_kg_dial').val() || 0)));
        const gram = Math.round(kg * 1000);
        syncWeightUIFromGram(gram, { manualMode: false });
    }

    function productsTotalWeightGram(){
        const products = collectProducts();
        return products.reduce(function(sum, item){
            const qty = Math.max(1, Number(item.quantity || 1));
            const gram = Math.max(1, Number(item.weight || 0));
            return sum + (qty * gram);
        }, 0);
    }

    function syncWeightFromProducts(){
        if ($('#weight_manual_toggle').is(':checked')) return;
        const totalGram = Math.max(1, Math.round(productsTotalWeightGram() || 1200));
        syncWeightUIFromGram(totalGram, { manualMode: false });
    }

    function syncWeightFromManual(){
        const enabled = $('#weight_manual_toggle').is(':checked');
        $('#weight_manual_kg').prop('disabled', !enabled);
        if (!enabled) {
            syncWeightFromProducts();
            return;
        }
        const kg = Math.max(0, Math.min(1000, Number($('#weight_manual_kg').val() || 0)));
        const gram = Math.round(kg * 1000);
        syncWeightUIFromGram(gram, { manualMode: true });
    }

    function updateDimensionPreview(){
        const length = Math.max(1, Number($('#length').val() || 20));
        const width = Math.max(1, Number($('#width').val() || 20));
        const height = Math.max(1, Number($('#height').val() || 20));
        $('#dimLenPreview').text('D: ' + length + 'cm');
        $('#dimWidPreview').text('R: ' + width + 'cm');
        $('#dimHeiPreview').text('C: ' + height + 'cm');
    }

    function updateWeightOverview(){
        if (!$('#weight_manual_toggle').is(':checked')) {
            syncWeightFromProducts();
        }

        const declared = Math.max(1, Number($('#weight').val() || 1200));
        const productsWeight = productsTotalWeightGram();
        const diff = declared - productsWeight;

        $('#weightOverviewDeclared').text(declared.toLocaleString('vi-VN') + ' g');
        $('#weightOverviewProducts').text(productsWeight.toLocaleString('vi-VN') + ' g');
        $('#weightOverviewDiff')
            .text((diff > 0 ? '+' : '') + diff.toLocaleString('vi-VN') + ' g')
            .removeClass('text-danger text-success')
            .addClass(diff < 0 ? 'text-danger' : 'text-success');
    }

    function applySender(activeShop){
        const s = activeShop || {};
        const shopId = Number(s.shop_id || 0);
        const districtId = Number(s.district_id || 0) || 0;
        const wardCode = String(s.ward_code || '').trim();
        const displayName = shopDisplayName(s);
        $('#from_name').val(displayName);
        $('#from_phone').val(String(s.phone || ''));
        $('#from_address').val(String(s.address || ''));
        $('#from_district_id').val(districtId || '');
        $('#from_ward_code').val(wardCode);
        $('#senderMiniName').text(displayName || '—');
        $('#senderMiniAddress').text(String(s.address || '—'));
        $('#senderMiniPhone').text(String(s.phone || '—'));

        const ok = shopId > 0 && districtId > 0 && wardCode !== '';
        if (!ok) {
            $('#senderMiniName').text('Chưa có shop hoạt động');
            $('#senderMiniAddress').text('Vui lòng cấu hình tại setup shop');
            $('#senderMiniPhone').text('—');
            $('#senderMiniCard').removeClass('border-success').addClass('border-danger');
            return;
        }

        $('#senderMiniCard').removeClass('border-danger').addClass('border-success');

        resolveSenderRegionText(districtId, wardCode).then(function(region){
            const districtLabel = region.districtName ? (region.districtName + ' (ID: ' + districtId + ')') : ('ID: ' + districtId);
            const wardLabel = region.wardName ? (region.wardName + ' (Code: ' + wardCode + ')') : ('Code: ' + wardCode);
            $('#senderMiniAddress').text(String(s.address || '') + ' | ' + districtLabel + ' | ' + wardLabel);
        });

        if (String($('#pickup_type').val() || 'pickup') === 'dropoff') {
            loadStations();
        }
    }

    function resolveSenderRegionText(districtId, wardCode){
        const did = Number(districtId || 0);
        const wCode = String(wardCode || '').trim();
        if (!did) {
            return Promise.resolve({ districtName: '', wardName: '' });
        }

        return api('region_lookup', { district_id: did, ward_code: wCode }, 'GET').then(function(res){
            if (!res.ok) return { districtName: '', wardName: '' };
            return {
                districtName: String(res?.district?.district_name || '').trim(),
                wardName: String(res?.ward?.ward_name || '').trim(),
            };
        }).catch(function(){
            return { districtName: '', wardName: '' };
        });
    }

    function loadSettingAndSender(){
        return api('setting_get', {}, 'GET').then(function(res){
            setDebug(res);
            if (!res.ok) {
                applySender({});
                return;
            }
            applySender(res.active_shop || {});
            loadAvailableServices();
            scheduleAutoFee();
        }).catch(function(err){
            setDebug(err);
            applySender({});
        });
    }

    function loadSystemOrders(statusOverride){
        const status = String(statusOverride || currentSystemOrderStatus || 'open');
        currentSystemOrderStatus = status;
        currentSystemOrderPage = 1;
        return api('system_orders_list', {
            status: status,
            only_new: 1,
            limit: 200
        }, 'GET').then(function(res){
            setDebug(res);
            if (!res.ok) {
                currentSystemOrders = [];
                currentSystemOrderFiltered = [];
                $('#sysOrderBody').html('<tr><td colspan="7" class="text-center text-danger">Không tải được danh sách don</td></tr>');
                $('#sysOrderPagerInfo').text('0 dòng');
                $('#sysPagePrev,#sysPageNext').prop('disabled', true);
                return;
            }
            currentSystemOrders = Array.isArray(res.rows) ? res.rows : [];
            applySystemOrderFilters();
        }).catch(function(err){
            setDebug(err);
            $('#sysOrderBody').html('<tr><td colspan="7" class="text-center text-danger">Lỗi kết nối</td></tr>');
        });
    }

    function renderProducts(items){
        if (!Array.isArray(items) || !items.length) {
            $('#productBody').html('<tr><td colspan="9" class="text-center text-muted">Chưa có sản phẩm</td></tr>');
            return;
        }
        const html = items.map(function(p, idx){
            const imgRaw = String(p.variant_image || p.image || '').trim();
            const imgUrl = toAbsoluteUrl(imgRaw);
            const isGift = !!(p.is_gift || p.isGift || p.gift);
            return '<tr data-idx="'+idx+'">'
                + '<td>'
                    + (imgUrl
                        ? '<img src="'+esc(imgUrl)+'" alt="sp" width="40" height="40" class="rounded border object-fit-cover">'
                        : '<div class="d-inline-flex align-items-center justify-content-center rounded border bg-body-tertiary text-muted" style="width:40px;height:40px;"><i class="bi bi-image"></i></div>'
                    )
                + '</td>'
                + '<td>'
                    + '<div class="d-flex flex-column gap-1">'
                        + '<input class="form-control form-control-sm prod-name" value="'+esc(p.name || '')+'">'
                        + (isGift ? '<div><span class="badge text-bg-success">Quà tặng</span></div>' : '')
                    + '</div>'
                + '</td>'
                + '<td>'
                    + '<input class="form-control form-control-sm prod-variant" value="'+esc(p.variant_name || p.variant || '')+'">'
                + '</td>'
                + '<td><input class="form-control form-control-sm prod-sku" value="'+esc(p.sku || '')+'"></td>'
                + '<td><input type="number" min="1" class="form-control form-control-sm prod-qty" value="'+Number(p.quantity || 1)+'"></td>'
                + '<td><input type="number" min="1" class="form-control form-control-sm prod-weight" value="'+Number(p.weight || 1200)+'"></td>'
                + '<td><input type="number" min="1" class="form-control form-control-sm prod-length" value="'+Number(p.length || 20)+'"></td>'
                + '<td><input type="number" min="1" class="form-control form-control-sm prod-width" value="'+Number(p.width || 20)+'"></td>'
                + '<td><input type="number" min="1" class="form-control form-control-sm prod-height" value="'+Number(p.height || 20)+'"></td>'
                + '<input type="hidden" class="prod-price" value="'+Number(p.price || 0)+'">'
                + '<input type="hidden" class="prod-image" value="'+esc(imgRaw)+'">'
                + '<input type="hidden" class="prod-is-gift" value="'+(isGift ? 1 : 0)+'">'
            + '</tr>';
        }).join('');
        $('#productBody').html(html);
    }

    function applyResolvedAddress(resolve){
        const data = resolve || {};
        const provinceId = Number(data.province_id || 0);
        const districtId = Number(data.district_id || 0);
        const wardCode = String(data.ward_code || '').trim();

        const needManual = !data.ok || !provinceId || !districtId || !wardCode;
        if (needManual) {
            $('#receiver_hint').text((data.msg || 'Không xác định được tỉnh/quận/phường') + ' -> Vui lòng chọn thủ công.');
        } else {
            $('#receiver_hint').text('Đã chuẩn hoá địa chỉ: ' + String(data.province_name || '') + ' / ' + String(data.district_name || '') + ' / ' + String(data.ward_name || ''));
        }

        if (provinceId > 0) {
            $('#to_province_id').val(String(provinceId));
            return loadDistricts(provinceId).then(function(){
                if (districtId > 0) {
                    $('#to_district_id').val(String(districtId));
                    return loadWards(districtId).then(function(){
                        if (wardCode) $('#to_ward_code').val(wardCode);
                        loadAvailableServices();
                        scheduleAutoFee();
                    });
                }
                loadAvailableServices();
                scheduleAutoFee();
            });
        }
        loadAvailableServices();
        scheduleAutoFee();
        return Promise.resolve();
    }

    function loadSystemOrderDetail(orderId){
        return api('system_order_detail', { order_id: orderId }, 'GET').then(function(res){
            setDebug(res);
            if (!res.ok) return notify(res.msg || 'Không đọc được chi tiết đơn', 'warning');

            currentSystemOrder = res.order || {};
            const o = res.order || {};
            $('#to_name').val(String(o.user_name || ''));
            $('#to_phone').val(String(o.phone || ''));
            $('#to_address').val(String(o.address || ''));
            $('#client_order_code').val(String(o.order_id || ''));

            setMoneyInput('#cod_amount', Number(o.cod_amount || 0));
            setMoneyInput('#goods_value', Number(o.goods_value || 0));
            setMoneyInput('#cod_failed_amount', Number(o.cod_failed_amount || 0));
            if (!$('#content').val()) $('#content').val('Đơn hàng ' + String(o.order_id || ''));

            renderProducts(res.items || []);
            const summary = res.summary || {};
            syncWeightUIFromGram(Number(summary.chargeable_weight_gram || summary.actual_weight_gram || 1200));
            $('#length').val(Number(summary.length || 20));
            $('#width').val(Number(summary.width || 20));
            $('#height').val(Number(summary.height || 20));
            updateDimensionPreview();

            return applyResolvedAddress(res.resolve || {}).then(function(){
                notify('Đã nạp đơn ' + orderId, 'success');
                scheduleAutoFee();
            });
        }).catch(function(err){ setDebug(err); notify(err.msg || 'Nạp đơn thất bại', 'error'); });
    }

    function resolveReceiverAddress(){
        const addr = String($('#to_address').val() || '').trim();
        if (addr.length < 6) return notify('Địa chỉ quá ngắn để quét', 'warning');
        return api('address_resolve', { address: addr }, 'POST').then(function(res){
            setDebug(res);
            return applyResolvedAddress(res.resolve || {});
        }).catch(function(err){ setDebug(err); notify(err.msg || 'Quét địa chỉ thất bại', 'error'); });
    }

    function loadProvinces(){
        return api('region_provinces', {}, 'GET').then(function(res){
            if (!res.ok) return;
            const rows = Array.isArray(res.rows) ? res.rows : [];
            let html = '<option value="">Chọn Tỉnh/Thành</option>';
            rows.forEach(function(p){
                const id = Number(p.ProvinceID || 0);
                const name = String(p.ProvinceName || '');
                if (id > 0) html += '<option value="'+id+'">'+esc(name)+'</option>';
            });
            $('#to_province_id').html(html);
        }).catch(function(){});
    }

    function loadDistricts(provinceId){
        const pid = Number(provinceId || 0);
        if (!pid) return Promise.resolve([]);
        $('#to_district_id').html('<option value="">Đang tải quận/huyện...</option>');
        $('#to_ward_code').html('<option value="">Chọn Phường/Xã</option>');
        return api('region_districts', { province_id: pid }, 'GET').then(function(res){
            if (!res.ok) return [];
            const rows = Array.isArray(res.rows) ? res.rows : [];
            let html = '<option value="">Chọn Quận/Huyện</option>';
            rows.forEach(function(d){
                const id = Number(d.DistrictID || 0);
                const name = String(d.DistrictName || '');
                if (id > 0) html += '<option value="'+id+'">'+esc(name)+'</option>';
            });
            $('#to_district_id').html(html);
            return rows;
        }).catch(function(){ return []; });
    }

    function loadWards(districtId){
        const did = Number(districtId || 0);
        if (!did) return Promise.resolve([]);
        $('#to_ward_code').html('<option value="">Đang tải phường/xã...</option>');
        return api('region_wards', { district_id: did }, 'GET').then(function(res){
            if (!res.ok) return [];
            const rows = Array.isArray(res.rows) ? res.rows : [];
            let html = '<option value="">Chọn Phường/Xã</option>';
            rows.forEach(function(w){
                const code = String(w.WardCode || '');
                const name = String(w.WardName || '');
                if (code) html += '<option value="'+esc(code)+'">'+esc(name)+'</option>';
            });
            $('#to_ward_code').html(html);
            return rows;
        }).catch(function(){ return []; });
    }

    function loadPickupShifts(){
        return api('pickup_shifts', {}, 'GET').then(function(res){
            setDebug(res);
            if (!res || !res.ok) return;

            // GHN có thể trả về mảng trực tiếp hoặc bọc trong thuộc tính con
            let rows = [];
            if (Array.isArray(res.data)) {
                rows = res.data;
            } else if (res.data && Array.isArray(res.data.shifts)) {
                rows = res.data.shifts;
            } else if (res.data && Array.isArray(res.data.data)) {
                rows = res.data.data;
            }

            if (!rows.length) return;

            const html = rows.map(function(s){
                const id = Number(s.id || s.shift_id || s.shiftID || s.code || 2);
                const name = String(s.title || s.name || s.shift_name || '').trim();
                const fromTime = String(s.from_time || s.start_time || s.time_from || s.start || '').trim();
                const toTime = String(s.to_time || s.end_time || s.time_to || '').trim();

                let label = name || ('Ca ' + (id || ''));
                if (fromTime || toTime) {
                    const range = fromTime && toTime ? (fromTime + ' - ' + toTime) : (fromTime || toTime);
                    label += ' (' + range + ')';
                }

                return '<option value="'+id+'">'+esc(label)+'</option>';
            }).join('');

            if (html) {
                $('#pick_shift').html(html);
            }
        }).catch(function(err){
            setDebug(err);
        });
    }

    function loadStations(){
        const senderDistrict = vNum('#from_district_id', 0);
        if (!senderDistrict) return;
        return api('dropoff_stations', { district_id: senderDistrict }, 'GET').then(function(res){
            setDebug(res);
            if (!res.ok) return;
            const rows = Array.isArray(res.rows) ? res.rows : [];
            let html = '<option value="">Chọn bưu cục</option>';
            rows.forEach(function(s){
                const id = Number(s.station_id || 0);
                const name = String(s.name || ('Station ' + id));
                if (id > 0) html += '<option value="'+id+'">'+esc(name)+' (#'+id+')</option>';
            });
            $('#station_id').html(html);
        }).catch(function(){});
    }

    function updatePickupType(){
        const mode = String($('#pickup_type').val() || 'pickup');
        if (mode === 'dropoff') {
            $('#stationBox').removeClass('d-none');
            $('#pickShiftBox').addClass('d-none');
            loadStations();
        } else {
            $('#stationBox').addClass('d-none');
            $('#pickShiftBox').removeClass('d-none');
            loadPickupShifts();
        }
        scheduleAutoFee();
    }

    function loadAvailableServices(){
        const fromDistrictId = vNum('#from_district_id', 0);
        const toDistrictId = vNum('#to_district_id', 0);
        if (fromDistrictId <= 0 || toDistrictId <= 0) {
            currentAvailableServices = [];
            $('#service_id').html('<option value="">Tự chọn theo loại dịch vụ</option>');
            $('#service_hint').text('Chưa đủ quận gửi/nhận để tải dịch vụ khả dụng.');
            return Promise.resolve();
        }

        $('#service_hint').text('Đang tải dịch vụ khả dụng...');
        return api('available_services', {
            from_district_id: fromDistrictId,
            to_district_id: toDistrictId,
        }, 'POST').then(function(res){
            if (!res.ok) {
                currentAvailableServices = [];
                $('#service_id').html('<option value="">Tự chọn theo loại dịch vụ</option>');
                $('#service_hint').text(res.msg || 'Không lấy được dịch vụ khả dụng, dùng fallback service_type_id.');
                return;
            }

            const rows = Array.isArray(res.rows) ? res.rows : [];
            currentAvailableServices = rows;
            if (!rows.length) {
                $('#service_id').html('<option value="">Tự chọn theo loại dịch vụ</option>');
                $('#service_hint').text('GHN không trả về service cụ thể cho tuyến này, dùng fallback service_type_id.');
                return;
            }

            let html = '<option value="">Tự chọn theo loại dịch vụ</option>';
            rows.forEach(function(s){
                const serviceId = Number(s.service_id || 0);
                if (serviceId <= 0) return;
                const typeId = Number(s.service_type_id || 0);
                const shortName = String(s.short_name || '').trim() || ('Service ' + serviceId);
                html += '<option value="'+serviceId+'">'+esc(shortName)+' (service_id='+serviceId+', type='+typeId+')</option>';
            });
            $('#service_id').html(html);

            const first = rows[0] || {};
            const firstType = Number(first.service_type_id || 0);
            if (firstType > 0) {
                $('#service_type_id').val(String(firstType));
            }
            $('#service_hint').text('Đã tải ' + rows.length + ' dịch vụ khả dụng theo tuyến hiện tại.');
        }).catch(function(err){
            currentAvailableServices = [];
            $('#service_id').html('<option value="">Tự chọn theo loại dịch vụ</option>');
            $('#service_hint').text(err.msg || 'Lỗi tải dịch vụ khả dụng, dùng fallback service_type_id.');
        });
    }

    function calcFees(silent){
        if (loadingFee) return;
        updateWeightOverview();
        const payload = buildPayload();
        const err = validatePayload(payload, 'fee');
        if (err) {
            $('#feeAutoHint').text('Auto-fee: chờ đủ dữ liệu (' + err + ')');
            syncFeeSticky();
            if (!silent) notify(err, 'warning');
            return;
        }

        loadingFee = true;
        $('#feeAutoHint').text('Auto-fee: đang tính...');
        syncFeeSticky();
        return api('service_fee', {
            from_district_id: payload.sender.from_district_id,
            from_ward_code: payload.sender.from_ward_code,
            to_district_id: payload.receiver.to_district_id,
            to_ward_code: payload.receiver.to_ward_code,
            weight: payload.order_info.weight,
            length: payload.order_info.length,
            width: payload.order_info.width,
            height: payload.order_info.height,
            goods_value: payload.order_info.goods_value,
            service_id: payload.service.service_id,
            service_type_id: payload.service.service_type_id,
            coupon: payload.service.coupon,
        }, 'POST').then(function(res){
            setDebug(res);
            if (!res.ok) {
                const msgRaw = String(res.msg || '').trim();
                const msg = (!msgRaw || msgRaw.toLowerCase() === 'error')
                    ? 'Không tính được phí, vui lòng kiểm tra địa chỉ nhận/gửi và thông số đơn hàng'
                    : msgRaw;
                $('#feeAutoHint').text('Auto-fee: lỗi - ' + msg);
                if (!silent) notify(msg, 'warning');
                return;
            }
            const lightFee = Number(res.light?.fee || 0).toLocaleString('vi-VN') + 'đ';
            const heavyApplies = !!(res.heavy?.applies);
            const heavyFee = heavyApplies
                ? (Number(res.heavy?.fee || 0).toLocaleString('vi-VN') + 'đ')
                : 'Không áp dụng (đơn > 20kg)';
            $('#feeLightAmount').text(lightFee);
            $('#feeHeavyAmount').text(heavyFee);
            $('#feeAutoHint').text('Auto-fee: đã cập nhật lúc ' + new Date().toLocaleTimeString());
            syncFeeSticky();
            if (!silent) notify('Đã tính phí', 'success');
        }).catch(function(err){
            setDebug(err);
            $('#feeAutoHint').text('Auto-fee: lỗi kết nối');
            syncFeeSticky();
            if (!silent) notify(err.msg || 'Tính phí thất bại', 'error');
        }).finally(function(){
            loadingFee = false;
        });
    }

    let creatingOrder = false;
    function createOrder(){
        if (creatingOrder) return;
        const payload = buildPayload();
        const err = validatePayload(payload, 'create');
        if (err) return notify(err, 'warning');

        creatingOrder = true;
        const $btn = $('#btnCreateOrderSticky');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Đang tạo...');

        return api('order_create', { payload: payload }, 'POST').then(function(res){
            setDebug(res);
            if (!res.ok) {
                const msgRaw = String(res.msg || '').trim();
                const msg = (!msgRaw || msgRaw.toLowerCase() === 'error')
                    ? 'Tạo đơn thất bại, vui lòng kiểm tra lại thông tin đơn hàng và cấu hình GHN'
                    : msgRaw;
                notify(msg, 'warning');
                return;
            }
            if (res.ghn_created_but_db_failed) {
                notify('Cảnh báo: GHN đã tạo vận đơn ' + (res.order_code || '') + ' nhưng không lưu được DB. Cần xử lý thủ công!', 'error');
            } else {
                notify('Tạo đơn GHN thành công', 'success');
            }
            loadSystemOrders();
        }).catch(function(err){
            setDebug(err);
            notify(err.msg || 'Tạo đơn thất bại', 'error');
        }).finally(function(){
            creatingOrder = false;
            $btn.prop('disabled', false).html(originalHtml);
        });
    }

    function previewOrder(){
        const payload = buildPayload();
        const err = validatePayload(payload, 'create');
        if (err) return notify(err, 'warning');

        return api('order_preview', { payload: payload }, 'POST').then(function(res){
            setDebug(res);
            if (!res.ok) {
                notify(res.msg || 'Preview đơn thất bại', 'warning');
                return;
            }
            renderPreviewInvoice(payload, res);
            notify('Preview đơn GHN thành công', 'success');
        }).catch(function(err){
            setDebug(err);
            notify(err.msg || 'Preview đơn thất bại', 'error');
        });
    }

    function moneyText(value){
        const amount = Math.max(0, Math.round(Number(value || 0)));
        return amount.toLocaleString('vi-VN') + 'đ';
    }

    function dateTimeText(value){
        const raw = String(value || '').trim();
        if (!raw) return '—';
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return raw;
        return d.toLocaleString('vi-VN');
    }

    function textOrDash(value){
        const raw = String(value == null ? '' : value).trim();
        return raw === '' ? '—' : esc(raw);
    }

    function renderPreviewInvoice(payload, res){
        const previewData = (res && res.preview && res.preview.data && typeof res.preview.data === 'object') ? res.preview.data : {};
        const sender = payload.sender || {};
        const receiver = payload.receiver || {};
        const orderInfo = payload.order_info || {};
        const service = payload.service || {};
        const delivery = payload.delivery_note || {};
        const items = Array.isArray(payload.products) ? payload.products : [];

        const feeTotalRaw = Number(previewData.total_fee || previewData.total || previewData.service_fee || 0);
        const feeTotal = Math.round(Math.max(0, feeTotalRaw) / 1000) * 1000;
        const leadtimeRaw = previewData.leadtime || previewData.expected_delivery_time || previewData.expected_delivery_date || '';
        const serviceTypeId = Number(service.service_type_id || 2);
        const serviceId = Number(service.service_id || 0);
        const serviceLabel = serviceId > 0
            ? ('Service #' + serviceId + ' (type ' + serviceTypeId + ')')
            : (serviceTypeId === 5 ? 'Traditional Delivery' : (serviceTypeId === 3 ? 'Tiết kiệm' : (serviceTypeId === 1 ? 'Nhanh' : 'Chuẩn')));

        const pickupTypeLabel = (function(){
            const v = String(service.pickup_type || 'pickup').toLowerCase();
            if (v === 'dropoff') return 'Gửi tại bưu cục GHN (Dropoff)';
            return 'GHN đến lấy tại shop (Pickup)';
        })();

        const requiredNoteLabel = (function(){
            const v = String(delivery.required_note || '').toUpperCase();
            if (v === 'KHONGCHOXEMHANG') return 'Không đồng kiểm';
            if (v === 'CHOXEMHANGKHONGTHU') return 'Đồng kiểm (không thử hàng)';
            if (v === 'CHOTHUHANG') return 'Đồng kiểm và thử hàng';
            return textOrDash(v);
        })();

        const productMetrics = (function(){
            // Package metrics must match checkout logic: totalWeight is sum(qty*weight),
            // but dimensions are max(length/width/height) (NOT sum height).
            let totalWeight = 0;
            let maxLength = 1;
            let maxWidth = 1;
            let maxHeight = 1;
            (Array.isArray(items) ? items : []).forEach(function(it){
                const qty = Math.max(1, Number(it.quantity || 1));
                const weight = Math.max(1, Number(it.weight || 1));
                const length = Math.max(1, Number(it.length || 1));
                const width = Math.max(1, Number(it.width || 1));
                const height = Math.max(1, Number(it.height || 1));
                totalWeight += weight * qty;
                maxLength = Math.max(maxLength, length);
                maxWidth = Math.max(maxWidth, width);
                maxHeight = Math.max(maxHeight, height);
            });

            const ow = Math.max(1, Math.round(Number(orderInfo.weight || 0)));
            const ol = Math.max(1, Math.round(Number(orderInfo.length || 0)));
            const owid = Math.max(1, Math.round(Number(orderInfo.width || 0)));
            const oh = Math.max(1, Math.round(Number(orderInfo.height || 0)));

            return {
                totalWeight: ow > 0 ? ow : Math.max(1, Math.round(totalWeight || 1)),
                length: ol > 0 ? ol : Math.max(1, Math.round(maxLength || 1)),
                width: owid > 0 ? owid : Math.max(1, Math.round(maxWidth || 1)),
                height: oh > 0 ? oh : Math.max(1, Math.round(maxHeight || 1)),
            };
        })();

        const paymentTypeId = Number(service.payment_type_id || 2);
        const paymentLabel = paymentTypeId === 1 ? 'Người nhận trả phí' : 'Người gửi trả phí';

        let itemRows = '';
        if (!items.length) {
            itemRows = '<tr><td colspan="6" class="text-center text-muted">Không có sản phẩm</td></tr>';
        } else {
            itemRows = items.map(function(it, idx){
                const name = textOrDash(it.name || 'Sản phẩm');
                const sku = textOrDash(it.sku || '');
                const isGift = !!(it.is_gift || it.isGift || it.gift);
                const imgRaw = String(it.variant_image || it.image || '').trim();
                const imgUrl = toAbsoluteUrl(imgRaw);
                const thumb = imgUrl
                    ? '<img src="' + esc(imgUrl) + '" alt="sp" width="34" height="34" class="rounded border object-fit-cover">'
                    : '<div class="d-inline-flex align-items-center justify-content-center rounded border bg-body-tertiary text-muted" style="width:34px;height:34px;"><i class="bi bi-image"></i></div>';
                const qty = Math.max(1, Number(it.quantity || 1));
                const weight = Math.max(1, Number(it.weight || 0));
                const dims = Math.max(1, Number(it.length || 1)) + ' x ' + Math.max(1, Number(it.width || 1)) + ' x ' + Math.max(1, Number(it.height || 1));
                const unitPrice = Math.max(0, Number(it.price || 0));
                const lineTotal = unitPrice * qty;
                return '<tr>'
                    + '<td>' + (idx + 1) + '</td>'
                    + '<td>'
                        + '<div class="d-flex gap-2 align-items-start">'
                            + '<div class="flex-shrink-0">' + thumb + '</div>'
                            + '<div class="flex-grow-1">'
                                + '<div class="fw-semibold">' + name + (isGift ? ' <span class="badge text-bg-success">Quà tặng</span>' : '') + '</div>'
                                + (sku !== '—' ? '<div class="text-muted">SKU: ' + sku + '</div>' : '')
                                + (isGift ? '<div class="text-success small">Ghi chú: Sản phẩm Tặng</div>' : '')
                            + '</div>'
                        + '</div>'
                    + '</td>'
                    + '<td>' + qty + '</td>'
                    + '<td>' + weight.toLocaleString('vi-VN') + ' g</td>'
                    + '<td>' + dims + '</td>'
                    + '<td class="text-end">' + (lineTotal > 0 ? moneyText(lineTotal) : '—') + '</td>'
                + '</tr>';
            }).join('');
        }

        const rowKV = function(k, v){
            return '<div class="d-flex justify-content-between gap-2">'
                + '<span class="text-muted fw-semibold">' + esc(k) + '</span>'
                + '<span class="fw-semibold text-end">' + v + '</span>'
            + '</div>';
        };

        const html = ''
            + '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 border-bottom pb-2 mb-3">'
                + '<div>'
                    + '<div class="h6 fw-bold mb-0">HOÁ ĐƠN XEM TRƯỚC GỬI GHN</div>'
                    + '<div class="text-muted small">Mã đơn nội bộ: ' + textOrDash(orderInfo.system_order_id || '') + ' | Client code: ' + textOrDash(orderInfo.client_order_code || '') + '</div>'
                + '</div>'
                + '<div class="text-md-end">'
                    + '<div class="text-muted small">Thời gian preview</div>'
                    + '<div class="fw-bold">' + dateTimeText(new Date().toISOString()) + '</div>'
                + '</div>'
            + '</div>'

            + '<div class="row g-2 mb-2">'
                + '<div class="col-12 col-md-6">'
                    + '<div class="card">'
                        + '<div class="card-body p-3">'
                            + '<div class="fw-bold mb-2">Bên gửi</div>'
                            + rowKV('Tên', textOrDash(sender.name))
                            + rowKV('SĐT', textOrDash(sender.phone))
                            + rowKV('Địa chỉ', textOrDash(sender.address))
                        + '</div>'
                    + '</div>'
                + '</div>'
                + '<div class="col-12 col-md-6">'
                    + '<div class="card">'
                        + '<div class="card-body p-3">'
                            + '<div class="fw-bold mb-2">Bên nhận</div>'
                            + rowKV('Tên', textOrDash(receiver.name))
                            + rowKV('SĐT', textOrDash(receiver.phone))
                            + rowKV('Địa chỉ', textOrDash(receiver.address))
                        + '</div>'
                    + '</div>'
                + '</div>'
            + '</div>'

            + '<div class="row g-2 mb-3">'
                + '<div class="col-12 col-md-6">'
                    + '<div class="card">'
                        + '<div class="card-body p-3">'
                            + '<div class="fw-bold mb-2">Dịch vụ</div>'
                            + rowKV('Service', esc(serviceLabel) + ' (ID: ' + serviceTypeId + ')')
                            + rowKV('Thanh toán ship', esc(paymentLabel))
                            + rowKV('Hình thức lấy hàng', esc(pickupTypeLabel))
                            + rowKV('Voucher', textOrDash(service.coupon || ''))
                        + '</div>'
                    + '</div>'
                + '</div>'
                + '<div class="col-12 col-md-6">'
                    + '<div class="card">'
                        + '<div class="card-body p-3">'
                            + '<div class="fw-bold mb-2">Thông số & ghi chú</div>'
                            + rowKV('Khối lượng', productMetrics.totalWeight.toLocaleString('vi-VN') + ' g')
                            + rowKV('Kích thước', productMetrics.length + ' x ' + productMetrics.width + ' x ' + productMetrics.height + ' cm')
                            + rowKV('Yêu cầu xem hàng', esc(requiredNoteLabel))
                            + rowKV('Ghi chú', textOrDash(delivery.note || ''))
                            + rowKV('Dự kiến giao', esc(dateTimeText(leadtimeRaw)))
                        + '</div>'
                    + '</div>'
                + '</div>'
            + '</div>'

            + '<div class="table-responsive border rounded-3 mb-3">'
                + '<table class="table table-sm mb-0">'
                    + '<thead class="table-light"><tr><th style="width:46px">#</th><th>Sản phẩm</th><th style="width:60px">SL</th><th style="width:90px">KL</th><th style="width:150px">Kích thước (cm)</th><th style="width:120px" class="text-end">Tạm tính</th></tr></thead>'
                    + '<tbody>' + itemRows + '</tbody>'
                + '</table>'
            + '</div>'

            + '<div class="row justify-content-end">'
                + '<div class="col-12 col-md-6 col-lg-5">'
                    + '<div class="border rounded-3 p-3 bg-light">'
                        + rowKV('Tổng COD', moneyText(orderInfo.cod_amount || 0))
                        + rowKV('Tổng giá trị hàng', moneyText(orderInfo.goods_value || 0))
                        + '<div class="d-flex justify-content-between gap-2 mt-2 pt-2 border-top">'
                            + '<span class="text-muted fw-semibold">Phí vận chuyển (preview)</span>'
                            + '<span class="fw-bold text-danger">' + moneyText(feeTotal) + '</span>'
                        + '</div>'
                    + '</div>'
                + '</div>'
            + '</div>';

        $('#previewInvoiceBody').html(html);

        const modalEl = document.getElementById('previewInvoiceModal');
        if (window.bootstrap && modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function printPreviewInvoice(){
        const source = document.getElementById('previewInvoiceBody');
        if (!source) return notify('Không có dữ liệu để in', 'warning');
        const printWin = window.open('', '_blank', 'width=980,height=760');
        if (!printWin) return notify('Trình duyệt đã chặn popup in', 'warning');

        const bsCss = BASE_PATH + '/assets/bootstrap/bootstrap.min.css';
        const html = '<!doctype html><html><head><meta charset="utf-8"><title>Preview đơn GHN</title>'
            + '<meta name="viewport" content="width=device-width, initial-scale=1">'
            + '<link rel="stylesheet" href="' + esc(bsCss) + '">' 
            + '<style>@media print{body{margin:0;}} body{padding:16px;}</style>'
            + '</head><body>' + source.innerHTML + '</body></html>';

        printWin.document.open();
        printWin.document.write(html);
        printWin.document.close();
        printWin.focus();
        printWin.print();
    }

    function bindAutoFeeEvents(){
        const selectors = '#weight_kg_dial,#weight_manual_kg,#weight_manual_toggle,#length,#width,#height,#goods_value,#service_id,#service_type_id,#coupon,#to_province_id,#to_district_id,#to_ward_code,#to_address,#pickup_type,#station_id,#pick_shift,#payment_type_id';
        $(document).on('input change', selectors, function(){
            if ($(this).is('#weight_kg_dial')) syncWeightFromSlider();
            if ($(this).is('#weight_manual_kg,#weight_manual_toggle')) syncWeightFromManual();
            if ($(this).is('#length,#width,#height')) updateDimensionPreview();
            updateWeightOverview();
            scheduleAutoFee();
        });

        $(document).on('input', '#cod_amount,#goods_value,#cod_failed_amount', function(){
            const amount = unformatMoneyVnd($(this).val());
            $(this).val(formatMoneyVnd(amount));
            scheduleAutoFee();
        });

        $(document).on('click', '[data-weight-mark]', function(){
            const kg = Number($(this).attr('data-kg') || 0);
            if (!Number.isFinite(kg)) return;
            $('#weight_manual_toggle').prop('checked', false);
            $('#weight_manual_kg').prop('disabled', true);
            $('#weight_kg_dial').val(String(Math.max(0, Math.min(100, kg))));
            syncWeightFromSlider();
            updateWeightOverview();
            scheduleAutoFee();
        });

        $(document).on('input change', '#productBody .prod-qty,#productBody .prod-weight,#productBody .prod-length,#productBody .prod-width,#productBody .prod-height', function(){
            updateWeightOverview();
            scheduleAutoFee();
        });
    }

    $('#btnLoadSystemOrders').on('click', function(){ loadSystemOrders(currentSystemOrderStatus); });
    $(document).on('click', '.sys-status-tab', function(){
        const status = String($(this).attr('data-status') || 'open');
        $('.sys-status-tab').removeClass('btn-primary active').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        loadSystemOrders(status);
    });
    $(document).on('input change', '#sys_search,#sys_date_from,#sys_date_to,#sys_qty_min,#sys_qty_max,#sys_amount_min,#sys_amount_max,#sys_payment_method,#sys_order_size,#sys_sort', function(){
        currentSystemOrderPage = 1;
        applySystemOrderFilters();
    });
    $('#sys_page_size').on('change', function(){
        currentSystemOrderPage = 1;
        renderSystemOrders(currentSystemOrderFiltered);
    });
    $('#sysPagePrev').on('click', function(){
        currentSystemOrderPage = Math.max(1, currentSystemOrderPage - 1);
        renderSystemOrders(currentSystemOrderFiltered);
    });
    $('#sysPageNext').on('click', function(){
        currentSystemOrderPage = currentSystemOrderPage + 1;
        renderSystemOrders(currentSystemOrderFiltered);
    });
    $(document).on('click', '.btnUseOrder', function(){
        const orderId = String($(this).data('order') || '').trim();
        if (orderId) loadSystemOrderDetail(orderId);
    });

    $('#btnResolveReceiver').on('click', resolveReceiverAddress);
    $('#to_province_id').on('change', function(){
        loadDistricts(Number($(this).val() || 0));
    });
    $('#to_district_id').on('change', function(){
        loadAvailableServices();
        loadWards(Number($(this).val() || 0));
    });

    $('#service_id').on('change', function(){
        const sid = Number($(this).val() || 0);
        if (sid > 0) {
            const found = (Array.isArray(currentAvailableServices) ? currentAvailableServices : []).find(function(s){
                return Number(s.service_id || 0) === sid;
            });
            const typeId = Number(found?.service_type_id || 0);
            if (typeId > 0) {
                $('#service_type_id').val(String(typeId));
            }
        }
        scheduleAutoFee();
    });

    $('#pickup_type').on('change', updatePickupType);
    $('#btnPreviewOrderSticky').on('click', previewOrder);
    $('#btnCreateOrderSticky').on('click', createOrder);
    $('#btnPrintPreviewInvoice').on('click', printPreviewInvoice);

    $('#btnReloadAll').on('click', function(){
        loadSettingAndSender();
        loadProvinces();
        loadSystemOrders();
        updatePickupType();
        scheduleAutoFee();
    });

    bindAutoFeeEvents();
    loadSettingAndSender();
    loadProvinces();
    loadSystemOrders().then(function(){
        // Pre-select đơn từ URL ?system_order_id=... (khi mở từ trang chi tiết đơn)
        try {
            const params = new URLSearchParams(window.location.search);
            const presel = String(params.get('system_order_id') || '').trim();
            if (presel) {
                loadSystemOrderDetail(presel);
                notify('Đã chọn sẵn đơn ' + presel + ' để tạo vận đơn', 'info');
            }
        } catch (e) {}
    });
    loadAvailableServices();
    updatePickupType();
    syncWeightUIFromGram(Number($('#weight').val() || 1200));
    setMoneyInput('#cod_amount', $('#cod_amount').val() || 0);
    setMoneyInput('#goods_value', $('#goods_value').val() || 0);
    setMoneyInput('#cod_failed_amount', $('#cod_failed_amount').val() || 0);
    updateDimensionPreview();
    updateWeightOverview();
    syncFeeSticky();
    scheduleAutoFee();
})();
</script>
