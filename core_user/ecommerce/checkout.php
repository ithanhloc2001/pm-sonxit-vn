<?php
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}
// V4: Sanitize voucher prefill – chỉ cho phép ký tự hợp lệ (A-Z, 0-9, gạch nối, dưới), tối đa 32 ký tự
$voucherPrefill = strtoupper(substr(preg_replace('/[^A-Z0-9_\-]/i', '', trim($_GET['voucher'] ?? '')), 0, 32));
// Sử dụng JSON_HEX_TAG | JSON_HEX_AMP để ngăn XSS thông qua </script> injection
$voucherPrefillJs = json_encode($voucherPrefill, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<style>
        .product-row {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 12px 0 !important;
            gap: 12px !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        .product-row:last-child {
            border-bottom: none !important;
        }
        .product-info-group {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 0;
        }
        .product-thumb-wrap {
            position: relative;
            flex-shrink: 0;
        }
        .product-thumb {
            width: 64px !important;
            height: 64px !important;
            border-radius: 12px !important;
            object-fit: cover !important;
            border: 1px solid #e2e8f0 !important;
            background: #f8fafc !important;
        }
        .product-qty-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #65676b;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 800;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            border: 1.5px solid #fff;
        }
        .product-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .product-name {
            font-size: 0.88rem !important;
            font-weight: 700 !important;
            color: #050505 !important;
            line-height: 1.3 !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-variant {
            font-size: 0.78rem !important;
            color: #65676b !important;
        }
        .product-price-group {
            text-align: right;
            flex-shrink: 0;
        }
        .price-new {
            font-weight: 700 !important;
            color: #050505 !important;
            font-size: 0.92rem !important;
        }
        .price-old {
            font-size: 0.78rem !important;
            color: #65676b !important;
            text-decoration: line-through;
        }
        .product-row-gift .product-qty-badge {
            background: var(--vcp-primary) !important;
        }

        /* See More Button */
        .show-more-wrap {
            text-align: center;
            padding: 12px 0;
            border-top: 1px dashed #e2e8f0;
        }
        .btn-show-more {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #0b4b28;
            padding: 8px 20px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-show-more:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }
        .btn-show-more b {
            color: #ef4444;
        }
        #itemsHidden {
            display: none;
        }
</style>
<div class="checkout-shell">
     <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
            <div class="d-flex gap-2">
                <?php if (isset($userId) && $userId > 0): ?>
                <a class="btn btn-sm bg-white border text-decoration-none fw-medium text-dark px-3 rounded-pill" href="<?= h($baseUrl) ?>/cart">
                    <i class="bi bi-arrow-left me-1"></i>Giỏ hàng
                </a>
                <?php else: ?>
                <a class="btn btn-sm bg-white border text-decoration-none fw-medium text-dark px-3 rounded-pill" href="<?= h($baseUrl) ?>/shopping">
                    <i class="bi bi-arrow-left me-1"></i> Quay lại
                </a>
                <?php endif; ?>
            </div>
            <div>
               <a class="btn btn-sm bg-white border text-decoration-none fw-medium text-dark px-3 rounded-pill" href="<?= h($baseUrl) ?>/order">
                   <i class="bi bi-list-check me-1"></i>Đơn mua
               </a>
            </div>
        </div>
    <form id="checkoutForm" class="checkout-form">
        <input type="hidden" name="checkout_token" id="checkoutToken" value="<?= h($_SESSION['checkout_token'] ?? '') ?>">
        <div class="checkout-card address-card-pick" id="addressCardPick">
            <div class="card-title">
                <span class="fw-bold">Địa chỉ giao hàng</span>
            </div>
            <?php if ($isLoggedIn): ?>
                <div class="address-view">
                    <div class="address-selected-option" id="addressSelectedWrap">
                        <div class="address-selected-left">
                            <div class="address-line-main text-muted fw-medium" id="addressMainText">Chưa có thông tin người nhận</div>
                            <div class="address-line-detail" id="addressDetailText">Chưa thiết lập địa chỉ giao hàng</div>
                        </div>
                        <div class="address-selected-right">
                            <input class="form-check-input" type="radio" checked disabled>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="user_name" id="inputUserName" required>
                <input type="hidden" name="phone" id="inputPhone">
                <input type="hidden" name="email" id="inputEmail">
                <input type="hidden" name="address" id="inputAddress">
            <?php else: ?>
                <div class="row">
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Họ tên người nhận</label>
                        <input name="user_name" id="inputUserName" class="form-control" placeholder="Nhập họ tên người nhận" required>
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Số điện thoại</label>
                        <input name="phone" id="inputPhone" class="form-control" placeholder="Nhập số điện thoại" required>
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Email (không bắt buộc)</label>
                        <input name="email" id="inputEmail" class="form-control" placeholder="Nhập email nếu có">
                    </div>
                     <div class="col-12 col-md-6 mb-2">
                         <label class="label-sm">Tỉnh / thành phố</label>
                        <select id="guestProvince" class="form-select">
                            <option value="">-- Chọn tỉnh/thành --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Quận / huyện</label>
                        <select id="guestDistrict" class="form-select" disabled>
                            <option value="">-- Chọn quận/huyện --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Phường / xã</label>
                        <select id="guestWard" class="form-select" disabled>
                            <option value="">-- Chọn phường/xã --</option>
                        </select>
                    </div>
                     <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Địa chỉ giao hàng</label>
                        <input name="address" id="inputAddress" class="form-control" placeholder="Số nhà, tên đường (thôn/xóm nếu có)" required>
                     </div>
                      
                </div> 
            <?php endif; ?>
        </div>

        <div class="checkout-card">
            <div class="card-title fw-medium"><span>Thông tin đơn hàng</span></div>
            <div id="itemsWrap" class="product-list"><div class="cart-empty">Đang tải...</div></div>

            <div class="line-sep"></div>
            <label class="label-sm">Ghi chú</label>
            <input name="note" class="form-control" placeholder="Ghi chú cho shop">

            <div class="line-sep"></div>
            <div class="shipping-picker-box" style="cursor: pointer;" id="shipPickerBox">
                <div class="shipping-head d-flex justify-content-between align-items-center mb-1">
                    <span class="label fw-medium text-dark">Phương thức vận chuyển</span>
                    <span class="text-primary small fw-semibold">Thay đổi</span>
                </div>
                <div class="shipping-selected-wrap" id="shipMethodWrap">
                    <div class="cart-empty">Đang tải phương thức vận chuyển...</div>
                </div>
            </div>
            <!--div class="text-muted small" id="shipPolicyText">Không đồng kiểm</div-->

            <div class="line-sep"></div>
            <div class="summary-line">
                <span>Tổng số tiền (sản phẩm)</span>
                <strong id="sumItemTotal">0 đ</strong>
            </div>
        </div>

        <div class="checkout-card">
            <div class="card-title"><span>Ưu đãi đơn hàng</span>
                <!--button class="btn btn-sm btn-outline-primary" type="button" id="voucherOpen2">Chọn voucher</button-->
            </div>
             <?php if(!isset($userId) || $userId <= 0): ?>
                <!--div class="inline-row text-center">
                <span class="label-sm text-danger">Không áp dụng với đơn hàng này</span>
                </div-->
            <?php else: ?>
            <!--div class="inline-row">
                <span class="label-sm">Voucher giảm giá / giảm ship</span>
                <button class="btn btn-sm btn-outline-primary" type="button" id="voucherOpen2">Chọn voucher</button>
            </div-->
            <?php endif; ?>

            <div class="voucher-quick-wrap">
                <div class="voucher-quick-row">
                    <div id="quickVoucherOrder" class="voucher-quick-card-slot"></div>
                    <div id="quickVoucherShip" class="voucher-quick-card-slot"></div>
                </div>
            </div>
            <div class="xu-toggle-row d-none">
                <div class="d-flex align-items-center gap-2">
                     <div class="label-sm xu-balance" id="xuBalanceText"><i class="bi bi-coin"></i><span>Hiện có <b style="color:red">0 xu</b></span></div>
                    <label class="xu-switch" for="xuToggle">
                        <input type="checkbox" id="xuToggle">
                        <span class="xu-slider"></span>
                    </label>
                </div>
            </div>
            <input type="hidden" id="xuUseInput" value="0">
            <div class="voucher-hint">Bạn chỉ có thể áp dụng 1 mã giảm giá cho mỗi đơn hàng.</div>
        </div>
        <div class="checkout-card d-none_" id="paymentVoucherCard">
            <div class="card-title"><span>Ưu đãi thanh toán</span>
                <button class="btn btn-sm btn-outline-success d-none" type="button" id="paymentVoucherOpen">Chọn mã</button>
            </div>
            <div class="voucher-quick-wrap">
                <div class="voucher-quick-row">
                    <div id="quickVoucherPayment" class="voucher-quick-card-slot"></div>
                </div>
            </div>
        </div>
        <div class="checkout-card">
            <div class="card-title"><span>Phương thức thanh toán</span></div>
            <div class="payment-grid" id="payWrap">
                <?php foreach ($paymentMethods as $idx => $m): ?>
                    <?php
                        $paymentKey = (string)($m['key'] ?? '');
                        $paymentLogo = '';
                        if ($paymentKey === 'cod') {
                            $paymentLogo = $baseUrl . '/image/pay/cod.png';
                        } elseif ($paymentKey === 'vnpay') {
                            $paymentLogo = $baseUrl . '/image/pay/vnpay.jpg';
                        } elseif ($paymentKey === 'momo') {
                            $paymentLogo = $baseUrl . '/image/pay/momo.webp';
                        } elseif ($paymentKey === 'zalopay') {
                            $paymentLogo = $baseUrl . '/image/pay/zalopay.png';
                        }
                    ?>
                    <label class="payment-chip <?= $idx === 0 ? 'active' : '' ?>" data-key="<?= h($m['key']) ?>" for="pm_<?= h($m['key']) ?>">
                        <input class="form-check-input me-1" type="radio" name="payment_method" id="pm_<?= h($m['key']) ?>" value="<?= h($m['key']) ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                        <?php if ($paymentLogo !== ''): ?>
                            <img class="payment-logo" src="<?= h($paymentLogo) ?>" alt="<?= h($m['label']) ?>" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <span><?= h($m['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <!--div class="text-muted small" id="paymentHint"></--div-->
            <div class="text-muted small" id="paymentSelected"></div>
        </div>
        <div class="checkout-card">
            <div class="card-title"><span>Thông tin xuất hoá đơn</span></div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="1" id="invoiceToggle" name="invoice_want">
                <label class="form-check-label" for="invoiceToggle">
                    Tôi muốn xuất hoá đơn VAT
                </label>
            </div>
            <div id="invoiceFieldsWrap" style="display:none;">
                <div class="mb-2">
                    <label class="label-sm d-block mb-1">Loại hoá đơn</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="invoice_type" id="invoiceTypePersonal" value="personal" checked>
                        <label class="form-check-label" for="invoiceTypePersonal">Cá nhân</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="invoice_type" id="invoiceTypeCompany" value="company">
                        <label class="form-check-label" for="invoiceTypeCompany">Doanh nghiệp</label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Tên người mua hàng</label>
                        <input name="invoice_buyer_name" class="form-control" placeholder="Họ tên người mua trên hoá đơn">
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Tên công ty</label>
                        <input name="invoice_company_name" class="form-control" placeholder="Tên doanh nghiệp (nếu có)">
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Mã số thuế</label>
                        <input name="invoice_tax_code" class="form-control" placeholder="Mã số thuế công ty">
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Địa chỉ xuất hoá đơn</label>
                        <input name="invoice_address" class="form-control" placeholder="Địa chỉ ghi trên hoá đơn">
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label class="label-sm">Email nhận hoá đơn (PDF)</label>
                        <input name="invoice_email" class="form-control" placeholder="Email nhận hoá đơn điện tử">
                    </div>
                </div>
            </div>
        </div>

        <div class="checkout-card">
            <div class="card-title"><span>Tóm tắt yêu cầu</span></div>
            <div class="summary-line"><span>Tổng phí sản phẩm</span><strong id="sumSubtotal">0 đ</strong></div>
                <div class="summary-line" id="sumVatLine" style="display:none;"><span>Thuế VAT</span><strong id="sumVat">0 đ</strong></div>
            <div class="summary-line discount" id="sumVoucherDiscountLine"><span>+ Giảm giá sản phẩm</span><strong id="sumVoucherDiscount">0 đ</strong></div>
            <div class="summary-line discount" id="sumPaymentDiscountLine" style="display:none;"><span>+ Ưu đãi thanh toán</span><strong id="sumPaymentDiscount">0 đ</strong></div>
            <div class="summary-line"><span>Tổng phí vận chuyển</span><strong id="sumShip">0 đ</strong></div>
            <div class="summary-line discount" id="sumShipDiscountLine"><span>+ Giảm phí vận chuyển</span><strong id="sumShipDiscount">0 đ</strong></div>
            <div class="line-sep"></div>
            <div class="text-muted small" id="sumShipMeta"></div>
            <div class="summary-line"><span><strong>Tổng</strong></span><strong id="sumTotal" style="font-size: 1.4em;color:red">0 đ</strong></div>
           <div class="text-muted small h1" style="margin-top:2px;">Lưu ý: Thuế VAT sẽ được áp dụng khi bạn chọn xuất hoá đơn cá nhân hoặc công ty.</div>
        </div>
    </form>
    
</div>

<div class="checkout-footer">
    <div class="checkout-footer-inner">
        <div class="footer-meta">
            <div class="footer-total">Tổng cộng: <span id="footerTotal">0 đ</span></div>
            <div class="footer-save">Tiết kiệm: <span id="footerSavings">0 đ</span></div>
        </div>
        <button class="btn btn-primary __btn-submit" type="submit" form="checkoutForm" id="btnSubmit">Mua ngay</button>
    </div>
</div>
<div class="address-backdrop" id="addressBackdrop"></div>
<div class="address-panel" id="addressPanel" aria-hidden="true">
    <div class="address-panel-head">
        <div class="address-panel-title">Địa chỉ đã lưu</div>
        <button class="btn btn-sm btn-light" type="button" id="btnCloseAddressPicker"><i class="bi bi-x"></i></button>
    </div>
    <div class="address-panel-body" id="addressListWrap">
        <div class="cart-empty">Đang tải danh sách địa chỉ...</div>
    </div>
    <div class="address-panel-foot">
        <a class="btn btn-sm btn-outline-primary w-100" href="<?= h($baseUrl) ?>/account?tab=address">Thiết lập địa chỉ giao hàng</a>
    </div>
</div>

<div class="ship-backdrop" id="shipBackdrop"></div>
<div class="ship-panel" id="shipPanel" aria-hidden="true">
    <div class="ship-panel-head">
        <div class="ship-panel-title">Chọn phương thức vận chuyển</div>
        <button class="btn btn-sm btn-light" type="button" id="btnCloseShipPicker"><i class="bi bi-x"></i></button>
    </div>
    <div class="ship-panel-body">
        <div class="shipping-grid" id="shipMethodListWrap">
            <div class="cart-empty">Đang tải phương thức vận chuyển...</div>
        </div>
    </div>
</div>
<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
    const VOUCHER_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/voucher.php';
    const SHIPPING_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/shipping.php';
    const REGION_API = '<?= h($baseUrl) ?>/main/account/region-session.php';
    const BASE_URL = '<?= h($baseUrl) ?>';
    const ACCOUNT_ADDRESS_URL = '<?= h($baseUrl) ?>/account?tab=address';
    const IS_GUEST = <?= $isLoggedIn ? 'false' : 'true' ?>;
    const IS_ADMIN = <?= !empty($isAdmin) ? 'true' : 'false' ?>;
    const PAYMENT_LABELS = <?= json_encode($paymentLabelMap, JSON_UNESCAPED_UNICODE) ?>;
    const VNPAY_ENABLED = <?= !empty($ECOMMERCE_PAYMENT_METHODS['vnpay']['enabled']) ? 'true' : 'false' ?>;
    const MOMO_ENABLED = <?php $momoCfg = app_get_momo_config_by_env(); echo !empty($momoCfg['enabled']) ? 'true' : 'false'; ?>;
    const ZALOPAY_ENABLED = <?= !empty($ECOMMERCE_PAYMENT_METHODS['zalopay']['enabled']) ? 'true' : 'false'; ?>;
    const VOUCHER_PREFILL = <?= $voucherPrefillJs ?>;

    let checkoutSubmitting = false;

    const notify = (msg, type = 'info') => {
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    };

    const setCheckoutLocked = (locked, msg = '', isHtml = false) => {
        const $btn = $('#btnSubmit');
        if ($btn.length) {
            $btn.prop('disabled', !!locked || !!checkoutSubmitting);
        }
        if (locked && msg) {
            if (isHtml) {
                $('#shipMethodWrap').html(String(msg));
                $('#shipMethodListWrap').html(String(msg));
                return;
            }
            const safe = $('<div>').text(String(msg)).html();
            const html = '<div class="cart-empty">' + safe + '</div>';
            $('#shipMethodWrap').html(html);
            $('#shipMethodListWrap').html(html);
        }
    };

    const buildShippingBlockedHtml = () => {
        const rawReason = String(shippingQuote?.block_reason || shippingQuote?.msg || '').trim();
        const reason = rawReason || 'Không thể đặt hàng do giỏ hàng chưa có phương thức vận chuyển hợp lệ.';
        const missingIdsRaw = Array.isArray(shippingQuote?.missing_product_ids) ? shippingQuote.missing_product_ids : [];
        const missingIds = [...new Set(missingIdsRaw.map(v => Number(v || 0)).filter(v => v > 0))];

        const mapName = (it) => {
            const n = String(it?.product_name || it?.name || it?.title || '').trim();
            return n || ('Sản phẩm #' + String(it?.product_id || it?.id || ''));
        };

        let missingItems = [];
        if (IS_ADMIN && missingIds.length) {
            const seen = {};
            cartItems.forEach(it => {
                const pid = Number(it?.product_id || it?.id || 0);
                if (!pid || !missingIds.includes(pid) || seen[pid]) return;
                seen[pid] = true;
                missingItems.push({ pid, name: mapName(it) });
            });
            missingIds.forEach(pid => {
                if (missingItems.some(x => x.pid === pid)) return;
                missingItems.push({ pid, name: 'Sản phẩm #' + pid });
            });
        }

        const esc = (s) => $('<div>').text(String(s || '')).html();
        const listHtml = (IS_ADMIN && missingItems.length)
            ? ('<div class="mt-2 text-start">'
                + '<div class="fw-semibold small mb-1">Sản phẩm thiếu cấu hình vận chuyển:</div>'
                + '<ul class="mb-0 ps-3 small">'
                + missingItems.map(x => '<li>' + esc(x.name) + ' <span class="text-muted">(#' + esc(x.pid) + ')</span></li>').join('')
                + '</ul>'
            + '</div>')
            : '';

        return '<div class="cart-empty">'
            + '<div>' + esc(reason) + '</div>'
            + listHtml
            + (IS_ADMIN
                ? '<div class="mt-2 small text-muted">Chưa cấu hình "Phương thức vận chuyển" cho các sản phẩm này.</div>'
                : '<div class="mt-2 small text-muted">Vui lòng liên hệ cửa hàng để được hỗ trợ.</div>')
            + '</div>';
    };

    // chuẩn hoá giá trị tiền về đúng số mà người dùng thấy (làm tròn lên 1.000đ nếu có pmNormalizePrice)
    const normalizePrice = (n) => {
        if (window.pmNormalizePrice && typeof window.pmNormalizePrice === 'function') {
            return window.pmNormalizePrice(n);
        }
        const num = Number(n) || 0;
        return num;
    };

    const fmtPrice = (n) => {
        if (window.pmFormatPrice && typeof window.pmFormatPrice === 'function') {
            return window.pmFormatPrice(n);
        }
        const num = normalizePrice(n);
        return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
    };
    const paymentLabel = (key) => {
        const k = String(key || '').toLowerCase();
        if (k === 'cod') return 'Tiền mặt';
        if (k === 'vnpay') return 'VNpay';
        if (k === 'momo') return 'Momo';
        if (k === 'zalopay') return 'Zalopay';
        return PAYMENT_LABELS[key] || (key ? key.toUpperCase() : '');
    };

    const normalizeList = (val) => {
        if (Array.isArray(val)) {
            return val.map(v => String(v || '').trim().toLowerCase()).filter(v => v);
        }
        if (val === null || val === undefined) return [];
        return String(val).split(',').map(v => v.trim().toLowerCase()).filter(v => v);
    };

    const getSelectedPaymentMethod = () => String($('#payWrap input[name="payment_method"]:checked').val() || '').toLowerCase();

    function updatePaymentSelected(){
        const $el = $('#paymentSelected');
        if (!$el.length) return;
        const pm = getSelectedPaymentMethod();
        if (!pm) {
            $el.text('');
            return;
        }
        const label = paymentLabel(pm);
        $el.html('Phương thức đang chọn: <b>' + $('<div>').text(label).html() + '</b>');
    }

    const voucherAllowsPayment = (voucher) => {
        const allowed = normalizeList(voucher?.payment_methods || []);
        if (!allowed.length) return true;
        const pm = getSelectedPaymentMethod();
        if (!pm) return true;
        return allowed.includes(pm);
    };

    const voucherAllowsShipping = (voucher) => {
        const allowed = normalizeList(voucher?.shipping_methods || []);
        if (!allowed.length) return true;
        const sm = String(selectedShippingMethod || '').toLowerCase();
        if (!sm) return true;
        return allowed.includes(sm);
    };

    const voucherAllowsChannel = (voucher) => {
        // Checkout luôn ở kênh 'web'. Voucher có thể giới hạn apply_channel (chuỗi/CSV).
        // Cho qua nếu không giới hạn hoặc danh sách chứa web/all/*. Đồng bộ alias với backend
        // (ecommerce_voucher_allows_channel): website/site/online => web; mobile/app => app.
        const allowed = normalizeList(voucher?.apply_channel || []);
        if (!allowed.length) return true;
        const webAliases = ['web', 'website', 'site', 'online', 'all', '*', 'any'];
        return allowed.some(c => webAliases.includes(c));
    };

    const voucherAllowedByContext = (voucher) => {
        return voucherAllowsChannel(voucher) && voucherAllowsPayment(voucher) && voucherAllowsShipping(voucher);
    };

    // Voucher có GIỚI HẠN phương thức thanh toán thì CHỈ coi là "đã xác định khớp" khi:
    // user đã chọn PT thanh toán VÀ PT đó nằm trong danh sách cho phép.
    // Dùng cho auto-apply/đề xuất: tránh tự áp voucher khi chưa chọn PT (chưa chắc khớp).
    const voucherPaymentResolved = (voucher) => {
        const allowed = normalizeList(voucher?.payment_methods || []);
        if (!allowed.length) return true; // không giới hạn -> luôn ổn
        const pm = getSelectedPaymentMethod();
        if (!pm) return false;            // có giới hạn nhưng chưa chọn PT -> chưa đủ điều kiện
        return allowed.includes(pm);
    };

    // Kiểm tra ĐƠN TỐI THIỂU (min_subtotal) đã đạt chưa.
    // target 'order' so với tiền hàng; 'shipping' cũng so với tiền hàng (điều kiện đơn).
    const voucherMeetsMinSubtotal = (voucher) => {
        const min = Number(voucher?.min_subtotal || 0);
        if (!(min > 0)) return true;
        const sub = Number(subtotal() || 0);
        // Đồng bộ backend: nếu đơn vị min là percent thì ngưỡng = subtotal * min / 100, ngược lại là số tiền.
        const unit = String(voucher?.min_subtotal_unit || 'fixed').toLowerCase();
        const threshold = unit === 'percent' ? (sub * min / 100) : min;
        return sub >= threshold;
    };

    // Điều kiện ĐẦY ĐỦ phía frontend để coi 1 voucher là khả dụng NGAY bây giờ (dùng cho
    // auto-apply / đề xuất / hiển thị thẻ gợi ý):
    // - kênh + vận chuyển khớp, đạt đơn tối thiểu
    // - PT thanh toán phải ĐÃ XÁC ĐỊNH KHỚP (voucher giới hạn payment mà chưa chọn PT -> chưa đủ).
    const voucherEligibleNow = (voucher) => {
        return voucherAllowsChannel(voucher)
            && voucherAllowsShipping(voucher)
            && voucherPaymentResolved(voucher)
            && voucherMeetsMinSubtotal(voucher);
    };

    function toAbs(url){
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(url);
        const raw = String(url || '').trim();
        if (!raw) return '';
        if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
        const base = String(BASE_URL || '').replace(/\/$/, '');
        if (!base) return raw;
        const path = raw.startsWith('/') ? raw : '/' + raw;
        return base + path;
    }

    let cartItems = [];
    let discount = 0;
    let shippingVoucherDiscount = 0;
    let bxgyDiscount = 0;
    let bxgyGifts = [];
    let shippingFee = 0;
    let shippingQuote = null;
    let selectedShippingMethod = '';
    let allowedPaymentKeys = null;
    let vouchersOrder = [];
    let vouchersShipping = [];
    let vouchersPayment = [];
    let savedVoucherCodes = [];
    let selectedVoucherOrderCode = String($('#voucherSearch').val() || '').trim();
    let selectedVoucherShipCode = '';
    let selectedVoucherPaymentCode = '';
    let paymentBenefitEnabled = false;
    let paymentVoucherDiscount = 0;
    let suggestedVoucherOrderCode = '';
    let suggestedVoucherShipCode = '';
    let walletBalance = 0;
    let vndPerXu = 1000;
    let maxUsePercent = 50;
    let requestedXu = 0;
    let xuDiscount = 0;
    let activeVoucherTab = 'order';
    let profileInfo = { user_name: '', phone: '', email: '', address: '' };
    let savedLocations = [];
    let selectedLocation = null;
    let guestLocationReady = false;

    function formatPhoneVN(raw){
        const digits = String(raw || '').replace(/\D+/g, '');
        if (!digits) return '';
        if (digits.startsWith('84')) return '(+84) ' + digits.slice(2);
        if (digits.startsWith('0')) return '(+84) ' + digits.slice(1);
        return '(+84) ' + digits;
    }

    function normalizePhoneDigits(raw){
        return String(raw || '').replace(/\D+/g, '');
    }

    function syncAddressInputs(){
        const name = String(profileInfo.user_name || '').trim();
        const phone = String((selectedLocation && selectedLocation.contact_phone) ? selectedLocation.contact_phone : profileInfo.phone || '').trim();
        const email = String(profileInfo.email || '').trim();
        const address = String((selectedLocation && selectedLocation.customer_address) ? selectedLocation.customer_address : profileInfo.address || '').trim();
        $('#inputUserName').val(name);
        $('#inputPhone').val(phone);
        $('#inputEmail').val(email);
        $('#inputAddress').val(address);
    }

    function isGhnLocationReady(loc){
        if (!loc || typeof loc !== 'object') return false;
        const districtId = Number(loc.district_id || 0);
        const wardCode = String(loc.ward_code || '').trim();
        const addr = String(loc.customer_address || '').trim();
        return districtId > 0 && wardCode !== '' && addr !== '';
    }

    function renderAddressCard(){
        const name = String(profileInfo.user_name || '').trim();
        const phoneRaw = String((selectedLocation && selectedLocation.contact_phone) ? selectedLocation.contact_phone : profileInfo.phone || '').trim();
        const phoneText = formatPhoneVN(phoneRaw);
        const fullAddress = String((selectedLocation && selectedLocation.customer_address) ? selectedLocation.customer_address : profileInfo.address || '').trim();
        const ghnReady = isGhnLocationReady(selectedLocation);

        const mainText = name
            ? `${name}${phoneText ? ' ' + phoneText : ''}`
            : (phoneText || 'Chưa có thông tin người nhận');
        let detailText = fullAddress || 'Chưa thiết lập địa chỉ giao hàng';
        if (fullAddress && !ghnReady) {
            detailText = detailText + ' • Chưa chuẩn hoá GHN (vui lòng cập nhật lại địa chỉ)';
        }

        $('#addressMainText').text(mainText);
        $('#addressDetailText').text(detailText);
        syncAddressInputs();
    }

    function openAddressPanel(){
        $('#addressPanel').addClass('show').attr('aria-hidden', 'false');
        $('#addressBackdrop').addClass('show');
    }

    function closeAddressPanel(){
        $('#addressPanel').removeClass('show').attr('aria-hidden', 'true');
        $('#addressBackdrop').removeClass('show');
    }

    function renderSavedAddresses(){
        const $wrap = $('#addressListWrap');
        if (!savedLocations.length) {
            $wrap.html('<div class="cart-empty">Bạn chưa có địa chỉ đã lưu.</div>');
            return;
        }
        const activeId = String((selectedLocation && selectedLocation.address_id) ? selectedLocation.address_id : '');
        const html = savedLocations.map(loc => {
            const id = String(loc.address_id || '');
            const active = id === activeId ? 'active' : '';
            const phone = formatPhoneVN(loc.contact_phone || profileInfo.phone || '');
            const main = `${profileInfo.user_name || 'Người nhận'}${phone ? ' ' + phone : ''}`;
            const detail = String(loc.customer_address || 'Địa chỉ giao hàng');
            return `<div class="address-item ${active}" data-address-id="${$('<div>').text(id).html()}">
                <div class="address-item-left">
                    <div class="address-item-main">${$('<div>').text(main).html()}</div>
                    <div class="address-item-detail">${$('<div>').text(detail).html()}</div>
                </div>
                <div class="address-item-right">
                    <input class="form-check-input" type="radio" name="saved_address_pick" ${active ? 'checked' : ''}>
                </div>
            </div>`;
        }).join('');
        $wrap.html(html);
    }

    function openAddressPicker(){
        if (IS_GUEST) return;
        $('#addressListWrap').html('<div class="cart-empty">Đang tải danh sách địa chỉ...</div>');
        $.get(REGION_API, {}, res => {
            const list = Array.isArray(res?.saved_locations) ? res.saved_locations : [];
            if (!list.length) {
                window.location.href = ACCOUNT_ADDRESS_URL;
                return;
            }
            savedLocations = list;
            renderSavedAddresses();
            openAddressPanel();
        }, 'json').fail(() => {
            notify('Không tải được địa chỉ đã lưu', 'warning');
            window.location.href = ACCOUNT_ADDRESS_URL;
        });
    }

    function openShipPanel(){
        $('#shipPanel').addClass('show').attr('aria-hidden', 'false');
        $('#shipBackdrop').addClass('show');
    }

    function closeShipPanel(){
        $('#shipPanel').removeClass('show').attr('aria-hidden', 'true');
        $('#shipBackdrop').removeClass('show');
    }

    const currentProductIds = () => {
        const ids = [];
        const seen = {};
        cartItems.forEach(it => {
            const pid = Number(it.product_id || it.id || 0);
            if (pid > 0 && !seen[pid]) {
                seen[pid] = true;
                ids.push(pid);
            }
        });
        return ids;
    };

    function buildCheckoutItemsPayload(){
        // V3: Ch\u1ec9 g\u1eedi c\u00e1c tr\u01b0\u1eddng c\u1ea7n thi\u1ebft \u2013 server l\u00e0 source of truth cho gi\u00e1 v\u00e0 is_gift.
        // Kh\u00f4ng g\u1eedi is_gift, price \u0111\u1ec3 tr\u00e1nh client-side spoofing.
        const mainItems = cartItems.map(i => ({
            id: Number(i.product_id || i.id || 0),
            product_id: Number(i.product_id || i.id || 0),
            variant_id: Number(i.variant_id || i.v_id || i.vid || 0),
            color_code: i.color_code || '',
            qty: Number(i.qty || 0),
            is_combo: i.is_combo ? 1 : 0,
        })).filter(it => it.product_id > 0 && it.qty > 0);
        return mainItems;
    }


    // Ensure we never crash the checkout flow if voucher list rendering is not present.
    // This page primarily uses quick voucher cards + shared modal.
    function renderVouchers(){
        try {
        renderQuickVoucherOrder();
        renderQuickVoucherShip();
        renderQuickVoucherPayment();;
        } catch (e) {
            // no-op
        }
    }

    const getItemBaseUnitPrice = (it) => {
        const direct = Number(it?.price_base ?? 0);
        if (direct > 0) return normalizePrice(direct);

        // Backward-compat: older session carts may not have price_base.
        const gross = normalizePrice(it?.price);
        const vatPct = Number(it?.vat_percent ?? 0);
        const includesVat = Number(it?.price_includes_vat ?? it?.vat_enabled ?? 0) === 1;
        if (includesVat && gross > 0 && vatPct > 0) {
            const net = gross / (1 + (vatPct / 100));
            return normalizePrice(net);
        }
        return normalizePrice(gross);
    };

    const computeVatTotal = () => {
        if (!$('#invoiceToggle').is(':checked')) return 0;
        let vatSum = 0;
        cartItems.forEach(it => {
            const qty = Number(it?.qty || 0);
            if (qty <= 0) return;
            const isGift = !!it?.is_gift || Number(it?.price || 0) <= 0;
            if (isGift) return;
            const baseUnit = getItemBaseUnitPrice(it);
            const baseLine = baseUnit * qty;
            if (baseLine <= 0) return;
            const vatPct = Math.max(0, Math.min(100, Number(it?.vat_percent ?? 0)));
            if (vatPct <= 0) return;
            vatSum += (baseLine * vatPct / 100);
        });
        return normalizePrice(vatSum);
    };

    function subtotal(){
        // Tính tổng tiền hàng trước khi áp dụng voucher, giảm giá, và trước khi cộng VAT (nếu có). Đây cũng là cơ sở để tính toán số xu được phép dùng.
        return cartItems.reduce((s, it) => {
            const qty = Number(it?.qty || 0);
            if (qty <= 0) return s;
            const isGift = !!it?.is_gift || Number(it?.price || 0) <= 0;
            if (isGift) return s;
            return s + (getItemBaseUnitPrice(it) * qty);
        }, 0);
    }

    function computeMaxXuAllowed(){
        // Tính toán số xu tối đa được phép dùng dựa trên tổng tiền hàng (không bao gồm VAT), đã trừ đi các loại giảm giá khác nhưng chưa trừ giảm giá từ xu, và quy đổi từ vnd sang xu.
        const base = Math.max(0, subtotal() - discount + Number(shippingFee || 0) - Number(shippingVoucherDiscount || 0));
        const byTotal = Math.floor(base / Math.max(1, Number(vndPerXu || 1000)));
        const byPercent = Math.floor((base * (Number(maxUsePercent || 50) / 100)) / Math.max(1, Number(vndPerXu || 1000)));
        return Math.max(0, Math.min(Number(walletBalance || 0), byTotal, byPercent));
    }
                         
    function refreshXu(){
        const maxXu = computeMaxXuAllowed();
        if (requestedXu > maxXu) requestedXu = maxXu;
        if (requestedXu < 0) requestedXu = 0;
        xuDiscount = requestedXu * Math.max(1, Number(vndPerXu || 1000));
        const balanceText = new Intl.NumberFormat('vi-VN').format(Number(walletBalance || 0));
        $('#xuBalanceText').html('🪙Dùng <b>'+ balanceText + '</b> xu hệ thống');
        $('#xuUseInput').attr('max', String(maxXu)).val(requestedXu > 0 ? requestedXu : '');
        $('#xuToggle').prop('checked', requestedXu > 0);
        $('#xuToggleLabel').text(requestedXu > 0 ? 'Bật' : 'Tắt');
    }

    function render(){
        const $wrap = $('#itemsWrap');
        if (!cartItems.length){
            $wrap.html('<div class="cart-empty">Giỏ hàng trống. <a href="<?= h($baseUrl) ?>/shopping">Chọn sản phẩm</a></div>');
            $('#sumSubtotal,#sumShip,#sumShipDiscount,#sumVoucherDiscount,#sumBxgyDiscount,#sumTotal,#sumItemTotal,#footerTotal,#footerSavings').text('0 đ');
            $('#sumVat').text('0 đ');
            $('#sumVatLine,#sumVoucherDiscountLine,#sumShipDiscountLine,#sumPaymentDiscountLine').hide();
            // Khi không có sản phẩm, không cho phép dùng xu
            requestedXu = 0;
            refreshXu();
            return;
        }

        // Cập nhật lại xu được phép dùng và số tiền giảm trước khi tính tổng
        refreshXu();

        const colorToken = '| Màu:';
        const formatWeight = (value, unit) => {
            let v = parseFloat(value) || 0;
            if (v <= 0) return '';
            let u = (unit || 'kg').toLowerCase().trim();
            if (v >= 1000) {
                if (u === 'gr' || u === 'gram') { v /= 1000; u = 'kg'; }
                else if (u === 'ml') { v /= 1000; u = 'l'; }
            }
            const unitMap = { 'kg': 'kg', 'l': 'L', 'gr': ' gr', 'ml': 'ml', 'gram': ' gr' };
            const displayUnit = unitMap[u] || u;
            return parseFloat(v.toFixed(3)) + displayUnit;
        };

        const variantLabelOf = (it) => {
            const vName = String(it?.variant || it?.variant_name || '').trim();
            const wLabel = formatWeight(it?.shipping_weight_value || it?.weight_value, it?.shipping_weight_unit || it?.weight_unit);
            
            let label = vName || 'Mặc định';

            if (!label.trim()) return '';
            const finalVariant = label.trim();
            if (!finalVariant.includes(colorToken)) return finalVariant;
            return String((finalVariant.split(colorToken)[0] || '')).trim();
        };
        const nameLabelOf = (it) => String(it?.name || it?.product_name || '').trim();
        const sortByVariantName = (a, b) => {
            const va = variantLabelOf(a).toLowerCase();
            const vb = variantLabelOf(b).toLowerCase();
            if (va !== vb) {
                if (!va) return 1;
                if (!vb) return -1;
                return va.localeCompare(vb, 'vi');
            }
            const na = nameLabelOf(a).toLowerCase();
            const nb = nameLabelOf(b).toLowerCase();
            return na.localeCompare(nb, 'vi');
        };

        const normalItemsInCart = [];
        const giftItemsInCart = [];
        cartItems.forEach(it => {
            const isGift = !!it.is_gift || Number(it.price || 0) <= 0;
            (isGift ? giftItemsInCart : normalItemsInCart).push(it);
        });
        normalItemsInCart.sort(sortByVariantName);
        giftItemsInCart.sort(sortByVariantName);

        const LIMIT = 10;
        let renderedCount = 0;
        let mainHtml = '';
        let hiddenHtml = '';

        function buildRowHtml(it, isGift) {
            const rawThumb = String(it.variant_thumb || it.variant_image_url || it.thumb || it.image_url || '').trim();
            const img = rawThumb ? toAbs(rawThumb) : <?= json_encode($site_fallback_logo ? '../'.ltrim($site_fallback_logo,'/') : '../image/paintmore.svg') ?>;
            const productName = String((it.name || it.product_name || '').trim() || 'Sản phẩm');
            const vName = (it.variant || it.variant_name || '').trim();
            const wLabel = formatWeight(it?.shipping_weight_value || it?.weight_value, it?.shipping_weight_unit || it?.weight_unit);
            
            let variantHtml = '';
            let displayVName = vName;
            
            if (wLabel) {
                // Xoá sạch các pattern dung tích/cân nặng ở cuối (ví dụ: (1L), 4.000l, 4L)
                let prev;
                do {
                    prev = displayVName;
                    // Xoá pattern chung: số + đơn vị
                    displayVName = displayVName.replace(/\s*\(?\s*\d+([.,]\d+)?\s*(l|kg|ml|gr|gram)\s*\)?\s*$/i, '').trim();
                    // Xoá wLabel cụ thể
                    const escapedW = wLabel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    displayVName = displayVName.replace(new RegExp('\\s*\\(?\\s*' + escapedW + '\\s*\\)?\\s*$', 'i'), '').trim();
                } while (displayVName !== prev && displayVName.length > 0);
            }

            if (displayVName) {
                variantHtml += `<div class="product-variant">Phân loại: ${$('<div>').text(displayVName).html()}</div>`;
            }
            if (wLabel) {
                variantHtml += `<div class="product-variant">Dung tích: ${$('<div>').text(wLabel).html()}</div>`;
            }
            if (!displayVName && !wLabel) {
                variantHtml = `<div class="product-variant">Phân loại: Mặc định</div>`;
            }
            const qty = Number(it.qty || 0);

            if (isGift) {
                if (qty <= 0) return '';
                return `
                    <div class="product-row product-row-gift">
                        <div class="product-info-group">
                            <div class="product-thumb-wrap">
                                <img class="product-thumb" src="${$('<div>').text(img).html()}" alt="gift-thumb" loading="lazy" decoding="async">
                                <span class="product-qty-badge">${qty}</span>
                            </div>
                            <div class="product-details">
                                <div class="product-name">
                                    <span class="badge text-white bg-primary">Quà tặng</span>
                                    ${$('<div>').text(productName).html()}
                                </div>
                                ${variantHtml}
                            </div>
                        </div>
                        <div class="product-price-group">
                            <div class="price-new">Miễn phí</div>
                        </div>
                    </div>
                `;
            } else {
                const lineTotal = Number(it.price || 0) * qty;
                const compareTotal = Number(it.compare_price || it.old_price || 0) * qty;
                const oldPriceText = compareTotal > lineTotal ? fmtPrice(compareTotal) : '';
                return `
                    <div class="product-row">
                        <div class="product-info-group">
                            <div class="product-thumb-wrap">
                                <img class="product-thumb" src="${$('<div>').text(img).html()}" alt="thumb" loading="lazy" decoding="async">
                                <span class="product-qty-badge">${qty}</span>
                            </div>
                            <div class="product-details">
                                <div class="product-name">${$('<div>').text(productName).html()}</div>
                                ${variantHtml}
                            </div>
                        </div>
                        <div class="product-price-group">
                            <div class="price-old">${oldPriceText}</div>
                            <div class="price-new">${fmtPrice(lineTotal)}</div>
                        </div>
                    </div>
                `;
            }
        }

        normalItemsInCart.forEach(it => {
            const row = buildRowHtml(it, false);
            if (renderedCount < LIMIT) mainHtml += row;
            else hiddenHtml += row;
            renderedCount++;
        });
        // Hiển thị danh sách sản phẩm tặng (bao gồm Mua X tặng Y và các quà tặng khác nếu có)
        if (giftItemsInCart.length) {
            const sep = '<div class="line-sep_"></div><div class="label-sm mb-1 text-danger">Bạn nhận được quà tặng</div>';
            giftItemsInCart.forEach((it, idx) => {
                let row = buildRowHtml(it, true);
                if (!row) return;
                if (idx === 0) row = sep + row;

                if (renderedCount < LIMIT) mainHtml += row;
                else hiddenHtml += row;
                renderedCount++;
            });
        }

        let finalHtml = mainHtml;
        if (hiddenHtml) {
            finalHtml += `<div id="itemsHidden">${hiddenHtml}</div>`;
            finalHtml += `
                <div class="show-more-wrap mt-2">
                    <button type="button" class="btn-show-more" id="btnShowMoreItems">
                        Xem thêm <b>+${renderedCount - LIMIT}</b> sản phẩm
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                </div>
            `;
        }
        $wrap.html(finalHtml);
         //==============================================================================                       
        const sub = subtotal();
        const vat = computeVatTotal();
        const ship = Number(shippingFee || 0);
        const shipDiscount = Math.max(0, Number(shippingQuote?.shipping_discount || 0));
        const total = Math.max(0, (sub + vat) - discount - paymentVoucherDiscount - xuDiscount + Math.max(0, ship - shippingVoucherDiscount));
        const totalVoucherDiscount = Math.max(0, Number(discount || 0));
        const totalPaymentDiscount = Math.max(0, Number(paymentVoucherDiscount || 0));
        const totalShipDiscount = Math.max(0, shipDiscount + shippingVoucherDiscount);
        const totalSaved = Math.max(0, totalVoucherDiscount + totalPaymentDiscount + totalShipDiscount + xuDiscount);
        $('#sumSubtotal').text(fmtPrice(sub));
        if (vat > 0) {
            // Collect unique VAT percents > 0
            const vatPercents = Array.from(new Set(cartItems
                .filter(it => Number(it?.qty||0) > 0 && !it?.is_gift && Number(it?.vat_percent||0) > 0)
                .map(it => Number(it.vat_percent))
                .filter(p => p > 0)
            ));
            let vatLabel = '';
            if (vatPercents.length === 1) {
                vatLabel = `VAT (${vatPercents[0]}%)`;
            } else if (vatPercents.length > 1) {
                vatLabel = 'VAT (' + vatPercents.join('%, ') + '%)';
            } else {
                vatLabel = 'VAT';
            }
            $('#sumVatLine span').text(vatLabel);
            $('#sumVat').text(fmtPrice(vat));
            $('#sumVatLine').show();
        } else {
            $('#sumVat').text('0 đ');
            $('#sumVatLine span').text('VAT');
            $('#sumVatLine').hide();
        }
        $('#sumShip').text(fmtPrice(ship));

        // Hiển thị giảm giá theo type voucher (percent/amount)
        let voucherDiscountLabel = fmtPrice(totalVoucherDiscount);
        if (totalVoucherDiscount > 0 && selectedVoucherOrderCode) {
            const orderVoucher = findVoucherByCode('order', selectedVoucherOrderCode);
            if (orderVoucher) {
                const t = String(orderVoucher.type || '').toLowerCase();
                const v = Number(orderVoucher.value || 0);
                const rawMax = orderVoucher.max_discount;
                const hasMax = rawMax !== null && rawMax !== undefined && String(rawMax) !== '';
                if (t === 'percent' && v > 0) {
                    if (hasMax) {
                        // Có giới hạn giảm tối đa: chỉ hiển thị số tiền thực giảm
                        voucherDiscountLabel = fmtPrice(totalVoucherDiscount);
                    } else {
                        // Không giới hạn: hiển thị X% (Y đ)
                        voucherDiscountLabel = v + '% (' + fmtPrice(totalVoucherDiscount) + ')';
                    }
                }
            }
        }

        let shipDiscountLabel = fmtPrice(totalShipDiscount);
        if (totalShipDiscount > 0 && selectedVoucherShipCode) {
            const shipVoucher = findVoucherByCode('shipping', selectedVoucherShipCode);
            if (shipVoucher) {
                const t = String(shipVoucher.type || '').toLowerCase();
                const v = Number(shipVoucher.value || 0);
                const rawMax = shipVoucher.max_discount;
                const hasMax = rawMax !== null && rawMax !== undefined && String(rawMax) !== '';
                if (t === 'percent' && v > 0) {
                    if (hasMax) {
                        // Có giới hạn giảm tối đa: chỉ hiển thị số tiền thực giảm
                        shipDiscountLabel = fmtPrice(totalShipDiscount);
                    } else {
                        shipDiscountLabel = v + '% (' + fmtPrice(totalShipDiscount) + ')';
                    }
                }
            }
        }

        $('#sumShipDiscount').text('- ' + shipDiscountLabel);
        if (totalShipDiscount > 0) {
            $('#sumShipDiscountLine').show();
        } else {
            $('#sumShipDiscountLine').hide();
        }

        $('#sumVoucherDiscount').text('- ' + voucherDiscountLabel);
        if (totalVoucherDiscount > 0) {
            $('#sumVoucherDiscountLine').show();
        } else {
            $('#sumVoucherDiscountLine').hide();
        }

        if (totalPaymentDiscount > 0) {
            $('#sumPaymentDiscount').text('- ' + fmtPrice(totalPaymentDiscount));
            $('#sumPaymentDiscountLine').show();
        } else {
            $('#sumPaymentDiscount').text('0 đ');
            $('#sumPaymentDiscountLine').hide();
        }
        $('#sumBxgyDiscount').text('0 đ');
        $('#sumItemTotal').text(fmtPrice(sub));
        const shipMethodText = String(shippingQuote?.shipping_method_text || '').trim();
        const carrierName = String(shippingQuote?.carrier_name || '').trim();
        const etaText = String(shippingQuote?.eta_text || '').trim();
        const shipMetaParts = [];
        if (shipMethodText) {
            shipMetaParts.push('<span class="ship-meta-chip method"><i class="bi bi-box-seam"></i>' + $('<div>').text(shipMethodText).html() + '</span>');
        }
        /*if (carrierName) {
            shipMetaParts.push('<span class="ship-meta-chip carrier"><i class="bi bi-truck"></i>' + $('<div>').text(carrierName).html() + '</span>');
        }*/
        if (etaText) {
            shipMetaParts.push('<span class="ship-meta-chip eta"><i class="bi bi-clock-history"></i>' + $('<div>').text(etaText).html() + '</span>');
        }
        $('#sumShipMeta').html(shipMetaParts.length ? ('<div class="ship-meta-wrap">' + shipMetaParts.join('') + '</div>') : '');
        $('#sumTotal').text(fmtPrice(total));
        $('#footerTotal').text(fmtPrice(total));
        $('#footerSavings').text(fmtPrice(totalSaved));

        renderQuickVoucherOrder();
        renderQuickVoucherShip();
        renderQuickVoucherPayment();
    }

    function renderShippingMethods(){
        const $wrap = $('#shipMethodWrap');
        const $listWrap = $('#shipMethodListWrap');
        if (!$wrap.length) return;

        const isBlocked = !!(shippingQuote && shippingQuote.blocked);
        if (isBlocked) {
            selectedShippingMethod = '';
            shippingFee = 0;
            setCheckoutLocked(true, buildShippingBlockedHtml(), true);
            return;
        }
        const methods = Array.isArray(shippingQuote?.methods) ? shippingQuote.methods : [];
        if (!methods.length) {
            $wrap.html('<div class="cart-empty">Chưa có phương thức vận chuyển phù hợp địa chỉ hiện tại.</div>');
            if ($listWrap.length) {
                $listWrap.html('<div class="cart-empty">Chưa có phương thức vận chuyển phù hợp địa chỉ hiện tại.</div>');
            }
            setCheckoutLocked(true);
            return;
        }

        setCheckoutLocked(false);

        const hasSelected = methods.some(m => String(m.key || '').trim() === selectedShippingMethod);
        if (!hasSelected) {
            selectedShippingMethod = String(methods[0]?.key || '').trim();
        }

        const listHtml = methods.map((m) => {
            const key = String(m.key || '').trim();
            const active = key === selectedShippingMethod;
            return `<label class="ship-method-option ${active ? 'active' : ''}" data-method="${$('<div>').text(key).html()}">
                <div class="ship-method-left">
                    <div class="ship-method-title">${$('<div>').text(m.label || '').html()}</div>
                    <div class="ship-method-meta">${$('<div>').text(m.eta_text || '').html()}</div>
                    <div class="ship-method-meta">${$('<div>').text(m.policy_text || '').html()}</div>
                </div>
                <div class="ship-method-right">
                    <span class="ship-method-price">${$('<div>').text(m.fee_text || '0 đ').html()}</span>
                    <input class="form-check-input ship-method-radio" type="radio" name="shipping_method_pick" value="${$('<div>').text(key).html()}" ${active ? 'checked' : ''}>
                </div>
            </label>`;
        }).join('');
        if ($listWrap.length) {
            $listWrap.html(listHtml);
        }

        const selectedMethod = methods.find(m => String(m.key || '').trim() === selectedShippingMethod) || methods[0];
        const selectedKey = String(selectedMethod?.key || '').trim();
        const selectedCard = selectedMethod ? `<div class="ship-method-option active" style="cursor: pointer;" data-method="${$('<div>').text(selectedKey).html()}">
            <div class="ship-method-left">
                <div class="ship-method-title fw-bold text-dark">${$('<div>').text(selectedMethod.label || '').html()}</div>
                <div class="ship-method-meta text-muted small">${$('<div>').text(selectedMethod.eta_text || '').html()}</div>
                <div class="ship-method-meta text-muted small">${$('<div>').text(selectedMethod.policy_text || '').html()}</div>
            </div>
            <div class="ship-method-right d-flex align-items-center">
                <span class="ship-method-price fw-bold text-primary me-2">${$('<div>').text(selectedMethod.fee_text || '0 đ').html()}</span>
                <i class="bi bi-chevron-right text-muted"></i>
            </div>
        </div>` : '<div class="cart-empty">Chưa có phương thức vận chuyển.</div>';
        $wrap.html(selectedCard);
        $('#shipPolicyText').text(String(selectedMethod?.policy_text || 'Không đồng kiểm'));

    }

    function refreshShippingQuote(){
        const sub = subtotal();
        const checkoutItems = buildCheckoutItemsPayload();
        $.get(SHIPPING_API, {
            ajax: 'shipping_quote',
            subtotal: sub,
            shipping_method: selectedShippingMethod,
            products_json: JSON.stringify(checkoutItems)
        }).done(res => {
            if (!res || !res.ok) return;
            shippingQuote = res.shipping_quote || null;
            shippingFee = Number(res.shipping_fee || 0);
            const methodFromQuote = String(shippingQuote?.shipping_method || '').trim();
            if (methodFromQuote) selectedShippingMethod = methodFromQuote;
            renderShippingMethods();
            render();
            renderVouchers();
            clearVoucherIfInvalid();
            autoSelectBestVouchers();
            // Tự đề xuất voucher tương thích với đơn vị vận chuyển hiện tại
            recomputeVoucherSuggestions();
        });
    }

    function deriveAllowedPayments(items){
        let intersection = null;
        items.forEach(it => {
            if (!Array.isArray(it.payment_options) || !it.payment_options.length) return;
            const normalized = it.payment_options.map(val => String(val));
            if (intersection === null) {
                intersection = normalized;
            } else {
                intersection = intersection.filter(val => normalized.includes(val));
            }
        });
        return intersection;
    }

    function applyPaymentFilter(prefList){
        let allowed = Array.isArray(prefList) && prefList.length ? prefList : deriveAllowedPayments(cartItems);
        if (VNPAY_ENABLED && Array.isArray(allowed) && !allowed.includes('vnpay')) {
            allowed = [...allowed, 'vnpay'];
        }
        if (MOMO_ENABLED && Array.isArray(allowed) && !allowed.includes('momo')) {
            allowed = [...allowed, 'momo'];
        }
        if (ZALOPAY_ENABLED && Array.isArray(allowed) && !allowed.includes('zalopay')) {
            allowed = [...allowed, 'zalopay'];
        }
        allowedPaymentKeys = allowed && allowed.length ? allowed : null;
        let firstEnabled = null;
        $('#payWrap .payment-chip').each(function(){
            const key = String($(this).data('key') || '');
            const isAllowed = !allowedPaymentKeys || allowedPaymentKeys.includes(key);
            $(this).toggleClass('disabled', !isAllowed);
            $(this).find('input').prop('disabled', !isAllowed);
            if (!isAllowed) {
                $(this).removeClass('active');
            }
            if (isAllowed && !firstEnabled) firstEnabled = $(this).find('input');
        });
        if (firstEnabled && !$('#payWrap input[name="payment_method"]:enabled:checked').length) {
            $('#payWrap input[name="payment_method"]').prop('checked', false).closest('.payment-chip').removeClass('active');
            firstEnabled.prop('checked', true);
            firstEnabled.closest('.payment-chip').addClass('active');
        }
        const hint = allowedPaymentKeys && allowedPaymentKeys.length
            ? '<b>Phương thức khả dụng:</b> ' + allowedPaymentKeys.map(paymentLabel).join(', ')
            : '<b>Không có phương thức thanh toán khả dụng</b>';
        $('#paymentHint').html(hint);
        updatePaymentSelected();
    }

    function loadProfile(){
        if (IS_GUEST) return;
        const checkoutItems = buildCheckoutItemsPayload();
        $.get(API, {
            ajax: 'profile_get',
            subtotal: subtotal(),
            shipping_method: selectedShippingMethod,
            products_json: JSON.stringify(checkoutItems)
        }, res => {
            if (!res || !res.ok) return;
            const p = res.data || {};
            const loc = res.selected_location || {};
            profileInfo.user_name = String(p.user_name || '').trim();
            const phone = String(loc.contact_phone || '').trim() || String(p.phone || '').trim();
            profileInfo.phone = phone;
            profileInfo.email = String(p.email || '').trim();
            const addressFromSaved = String(loc.customer_address || '').trim();
            const addressFromProfile = String(p.address || '').trim();
            if (addressFromSaved) {
                selectedLocation = loc;
                profileInfo.address = addressFromSaved;
            } else if (addressFromProfile) {
                selectedLocation = null;
                profileInfo.address = addressFromProfile;
            } else {
                selectedLocation = null;
                profileInfo.address = '';
            }

            if (selectedLocation && !isGhnLocationReady(selectedLocation)) {
                notify('Địa chỉ hiện tại chưa có mã vùng GHN. Vui lòng vào Địa chỉ giao hàng để lưu lại.', 'warning');
            }

            shippingQuote = res.shipping_quote || null;
            shippingFee = Number(res.shipping_fee || 0);
            const methodFromQuote = String(shippingQuote?.shipping_method || '').trim();
            if (methodFromQuote) selectedShippingMethod = methodFromQuote;
            renderAddressCard();
            renderShippingMethods();
            render();
        });
    }

    //=========================
    // KHÁCH VÃNG LAI: GHN REGION PICKER
    //=========================

    const $guestProvince = $('#guestProvince');
    const $guestDistrict = $('#guestDistrict');
    const $guestWard = $('#guestWard');

    function escHtml(str){
        return $('<div>').text(String(str || '')).html();
    }

    function guestLoadProvinces(){
        if (!IS_GUEST || !$guestProvince.length) return;
        $guestProvince.prop('disabled', true).html('<option value="">Đang tải...</option>');
        $guestDistrict.prop('disabled', true).html('<option value="">-- Chọn quận/huyện --</option>');
        $guestWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
        $.get(REGION_API, { action: 'region_provinces' }, function(res){
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            const opts = ['<option value="">-- Chọn tỉnh/thành --</option>'];
            rows.forEach(function(p){
                opts.push('<option value="' + escHtml(p.ProvinceID) + '">' + escHtml(p.ProvinceName) + '</option>');
            });
            $guestProvince.html(opts.join('')).prop('disabled', false);
        }, 'json').fail(function(){
            $guestProvince.html('<option value="">Không tải được danh sách tỉnh/thành</option>');
        });
    }

    function guestLoadDistricts(provinceId){
        $guestDistrict.prop('disabled', true).html('<option value="">Đang tải...</option>');
        $guestWard.prop('disabled', true).html('<option value="">-- Chọn phường/xã --</option>');
        guestLocationReady = false;
        if (!provinceId){
            $guestDistrict.html('<option value="">-- Chọn quận/huyện --</option>').prop('disabled', true);
            return;
        }
        $.get(REGION_API, { action: 'region_districts', province_id: provinceId }, function(res){
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            const opts = ['<option value="">-- Chọn quận/huyện --</option>'];
            rows.forEach(function(d){
                opts.push('<option value="' + escHtml(d.DistrictID) + '">' + escHtml(d.DistrictName) + '</option>');
            });
            $guestDistrict.html(opts.join('')).prop('disabled', false);
        }, 'json').fail(function(){
            $guestDistrict.html('<option value="">Không tải được danh sách quận/huyện</option>');
        });
    }

    function guestLoadWards(districtId){
        $guestWard.prop('disabled', true).html('<option value="">Đang tải...</option>');
        guestLocationReady = false;
        if (!districtId){
            $guestWard.html('<option value="">-- Chọn phường/xã --</option>').prop('disabled', true);
            return;
        }
        $.get(REGION_API, { action: 'region_wards', district_id: districtId }, function(res){
            const rows = Array.isArray(res?.rows) ? res.rows : [];
            const opts = ['<option value="">-- Chọn phường/xã --</option>'];
            rows.forEach(function(w){
                opts.push('<option value="' + escHtml(w.WardCode) + '">' + escHtml(w.WardName) + '</option>');
            });
            $guestWard.html(opts.join('')).prop('disabled', false);
        }, 'json').fail(function(){
            $guestWard.html('<option value="">Không tải được danh sách phường/xã</option>');
        });
    }

    function guestPersistLocationIfReady(){
        if (!IS_GUEST) return;
        const provinceId = String($guestProvince.val() || '').trim();
        const districtId = String($guestDistrict.val() || '').trim();
        const wardCode = String($guestWard.val() || '').trim();
        const street = String($('#inputAddress').val() || '').trim();
        if (!provinceId || !districtId || !wardCode || !street) {
            guestLocationReady = false;
            return;
        }

        const provinceName = $guestProvince.find('option:selected').text().trim();
        const districtName = $guestDistrict.find('option:selected').text().trim();
        const wardName = $guestWard.find('option:selected').text().trim();

        const payload = {
            action: 'save_address',
            region: '',
            branch_id: 0,
            street: street,
            province_id: provinceId,
            district_id: districtId,
            ward_code: wardCode,
            ward: wardName,
            district: districtName,
            province: provinceName,
            contact_phone: String($('#inputPhone').val() || '').trim(),
            recipient_name: String($('#inputUserName').val() || '').trim(),
            address_type: 'home',
            delivery_note: String($('input[name="note"]').val() || '').trim(),
        };

        $.post(REGION_API, payload, function(res){
            if (res && res.ok && res.location) {
                guestLocationReady = isGhnLocationReady(res.location);
                selectedLocation = res.location;
            }
        }, 'json');
    }

    function loadWalletSummary(){
        $.get(API, { ajax: 'wallet_summary' }, res => {
            if (!res || !res.ok) return;
            walletBalance = Number(res.balance || 0);
            vndPerXu = Math.max(1, Number(res.vnd_per_xu || 1000));
            maxUsePercent = Math.min(100, Math.max(1, Number(res.max_use_percent || 50)));
            refreshXu();
            render();
        });
    }

    function findVoucherByCode(target, code){
        const normalizedCode = String(code || '').trim().toUpperCase();
        if (!normalizedCode) return null;
        const source = target === 'shipping' ? vouchersShipping : vouchersOrder;
        return (Array.isArray(source) ? source : []).find(v => String(v.code || '').trim().toUpperCase() === normalizedCode) || null;
    }

    function findBestPaymentVoucherForCurrentMethod(){
        const pm = getSelectedPaymentMethod();
        if (!pm) return null;
        const sub = subtotal();
        let best = null;
        let bestDiscount = 0;
        (Array.isArray(vouchersPayment) ? vouchersPayment : []).forEach(v => {
            const payments = normalizeList(v?.payment_methods || []);
            if (!payments.length || !payments.includes(pm)) return;
            if (!voucherAllowedByContext(v)) return;
            if (!window.pmVoucher || typeof window.pmVoucher.calcOrderDiscount !== 'function') return;
            const d = window.pmVoucher.calcOrderDiscount(v, sub);
            if (d > bestDiscount){
                bestDiscount = d;
                best = v;
            }
        });
        if (!best) return null;
        return { voucher: best, discount: bestDiscount };
    }

    function validatePaymentVoucherOnServer(code){
        const normalized = String(code || '').trim();
        if (!normalized) return $.Deferred().resolve({ ok:true, discount:0, code:'' }).promise();
        const sub = subtotal();
        return $.get(VOUCHER_API, {
            ajax: 'validate_voucher',
            code: normalized,
            target: 'order',
            subtotal: sub,
            shipping_fee: 0,
            product_ids: currentProductIds().join(','),
            payment_method: getSelectedPaymentMethod(),
            shipping_method: String(selectedShippingMethod || ''),
            channel: 'web'
        }, null, 'json');
    }

    function setPaymentBenefitEnabled(enabled){
        paymentBenefitEnabled = !!enabled;
        if (!paymentBenefitEnabled) {
            selectedVoucherPaymentCode = '';
            paymentVoucherDiscount = 0;
        }
    }

    function renderQuickVoucherPayment(){
        const $card = $('#paymentVoucherCard');
        const $quick = $('#quickVoucherPayment');
        if (!$card.length || !$quick.length) return;

        const pm = getSelectedPaymentMethod();
        if (!pm) {
            $card.hide();
            return;
        }

        $card.show();

        if (paymentBenefitEnabled && String(selectedVoucherPaymentCode || '').trim()) {
            const code = String(selectedVoucherPaymentCode || '').trim();
            const v = findVoucherInList(vouchersPayment, code);
            if (v && window.pmVoucherShared && typeof window.pmVoucherShared.renderTplCard === 'function') {
                $quick.html(window.pmVoucherShared.renderTplCard(v, {
                    code: v?.code || code,
                    active: true,
                    useLabel: 'Đang dùng',
                    detailUrlPrefix: '<?= h($baseUrl) ?>/view-voucher?code='
                }));
                return;
            }
            // Fallback tối giản
            $quick.html(
                '<article class="tpl-voux-card tpl-voux-payment">'
                + '  <div class="tpl-voux-accent"></div>'
                + '  <div class="tpl-voux-brand">'
                + '    <span class="tpl-voux-logo-icon"><i class="bi bi-credit-card-2-front"></i></span>'
                + '    <div class="tpl-voux-brand-name">Thanh toán</div>'
                + '  </div>'
                + '  <div class="tpl-voux-main">'
                + '    <div class="tpl-voux-main-title">Ưu đãi thanh toán</div>'
                + '    <div class="tpl-voux-sub">Đang áp dụng: ' + escHtml(code) + '</div>'
                + '  </div>'
                + '  <div class="tpl-voux-side"></div>'
                + '</article>'
            );
            return;
        }

        $quick.html(
            '<article class="tpl-voux-card tpl-voux-payment">'
            + '  <div class="tpl-voux-accent"></div>'
            + '  <div class="tpl-voux-brand">'
            + '    <span class="tpl-voux-logo-icon"><i class="bi bi-credit-card-2-front"></i></span>'
            + '    <div class="tpl-voux-brand-name">Thanh toán</div>'
            + '  </div>'
            + '  <div class="tpl-voux-main">'
            + '    <div class="tpl-voux-main-title">Ưu đãi thanh toán</div>'
            + '    <div class="tpl-voux-sub">Bạn chưa chọn mã ưu đãi thanh toán.</div>'
            + '  </div>'
            + '  <div class="tpl-voux-side">'
            + '    <span class="tpl-voux-btn">Chọn ngay</span>'
            + '  </div>'
            + '</article>'
        );
    }

    function renderQuickVoucherOrder(){
        const $quick = $('#quickVoucherOrder');
        if (!$quick.length) return;

        const selectedCode = String(selectedVoucherOrderCode || '').trim();
        const suggestedCode = String(suggestedVoucherOrderCode || '').trim();
        const codeToShow = selectedCode || suggestedCode;

        if (codeToShow) {
            const voucher = findVoucherByCode('order', codeToShow);
            // Voucher đang được chọn (đã qua validate server) thì luôn hiển thị;
            // còn voucher GỢI Ý chỉ hiển thị khi thực sự khả dụng (đạt đơn tối thiểu + ngữ cảnh).
            const showable = voucher && (selectedCode ? true : voucherEligibleNow(voucher));
            if (showable && window.pmVoucherShared && typeof window.pmVoucherShared.renderTplCard === 'function') {
                $quick.html(window.pmVoucherShared.renderTplCard(voucher, {
                    code: voucher?.code || codeToShow,
                    active: !!selectedCode,
                    useLabel: selectedCode ? 'Đang dùng' : 'Dùng ngay',
                    detailUrlPrefix: '<?= h($baseUrl) ?>/view-voucher?code='
                }));
                return;
            }
        }

        // Placeholder khi chưa chọn/không có voucher gợi ý
        $quick.html(
            `<article class="tpl-voux-card tpl-voux-order">
                <div class="tpl-voux-accent"></div>
                <div class="tpl-voux-brand">
                    <span class="tpl-voux-logo-icon"><i class="bi bi-percent"></i></span>
                    <div class="tpl-voux-brand-name">Giảm giá</div>
                </div>
                <div class="tpl-voux-main">
                    <div class="tpl-voux-main-title">Ưu đãi đơn hàng</div>
                    <div class="tpl-voux-sub">Bạn chưa chọn mã ưu đãi cho đơn hàng này</div>
                </div>
                <div class="tpl-voux-side">
                    <span class="tpl-voux-btn">Chọn ngay</span>
                </div>
            </article>`
        );
    }
    // Tìm voucher tốt nhất cho vận chuyển hiện tại và hiển thị ở vị trí nổi bật
    function renderQuickVoucherShip(){
        const $quick = $('#quickVoucherShip');
        if (!$quick.length) return;

        const selectedCode = String(selectedVoucherShipCode || '').trim();
        const suggestedCode = String(suggestedVoucherShipCode || '').trim();
        const codeToShow = selectedCode || suggestedCode;

        if (codeToShow) {
            const voucher = findVoucherByCode('shipping', codeToShow);
            const showable = voucher && (selectedCode ? true : voucherEligibleNow(voucher));
            if (showable && window.pmVoucherShared && typeof window.pmVoucherShared.renderTplCard === 'function') {
                $quick.html(window.pmVoucherShared.renderTplCard(voucher, {
                    code: voucher?.code || codeToShow,
                    active: !!selectedCode,
                    useLabel: selectedCode ? 'Đang dùng' : 'Dùng ngay',
                    detailUrlPrefix: '<?= h($baseUrl) ?>/view-voucher?code='
                }));
                return;
            }
        }

        $quick.html(
            `<article class="tpl-voux-card tpl-voux-ship">
                <div class="tpl-voux-accent"></div>
                <div class="tpl-voux-brand">
                    <span class="tpl-voux-logo-icon"><i class="bi bi-truck"></i></span>
                    <div class="tpl-voux-brand-name">Vận chuyển</div>
                </div>
                <div class="tpl-voux-main">
                    <div class="tpl-voux-main-title">Ưu đãi vận chuyển</div>
                    <div class="tpl-voux-sub">Bạn chưa chọn mã ưu đãi cho đơn hàng này</div>
                </div>
                <div class="tpl-voux-side">
                    <span class="tpl-voux-btn">Chọn ngay</span>
                </div>
            </article>`
        );
    }
    function findVoucherInList(list, code){
        const targetCode = String(code || '').trim().toUpperCase();
        if (!targetCode) return null;
        return (Array.isArray(list) ? list : []).find(v => String(v?.code || '').trim().toUpperCase() === targetCode) || null;
    }

    // Ước lượng số tiền giảm của 1 voucher để SO CHỌN voucher tốt nhất.
    // Ưu tiên server_discount (server tính đúng scope ngành hàng/sản phẩm + cap) nếu có;
    // chỉ fallback tự tính trên toàn giỏ khi server chưa trả (giá trị tham khảo).
    function estimateVoucherDiscount(v, target){
        const sd = Number(v?.server_discount);
        if (Number.isFinite(sd) && sd > 0) return sd;
        if (window.pmVoucher && typeof window.pmVoucher.calcOrderDiscount === 'function') {
            return target === 'shipping'
                ? window.pmVoucher.calcOrderDiscount(v, Number(shippingFee || 0))
                : window.pmVoucher.calcOrderDiscount(v, Number(subtotal() || 0));
        }
        return 0;
    }

    function findBestSavedVoucher(target){
        const list = target === 'shipping' ? vouchersShipping : vouchersOrder;
        if (!Array.isArray(list) || !list.length) return null;
        const sub = subtotal();
        if (sub <= 0 && target !== 'shipping') return null;
        let best = null;
        let bestDiscount = 0;
        list.forEach(v => {
            if (target !== 'shipping') {
                const tpl = String(v?.voucher_template || '').trim().toLowerCase();
                if (tpl === 'payment_discount') return;
            }
            const code = String(v?.code || '').trim().toUpperCase();
            if (!code) return;
            const isSaved = Array.isArray(savedVoucherCodes) && savedVoucherCodes.includes(code);
            if (!isSaved && !v?.is_saved && !v?.saved) return;
            if (!voucherEligibleNow(v)) return;
            const d = estimateVoucherDiscount(v, target);
            if (d > bestDiscount) {
                bestDiscount = d;
                best = v;
            }
        });
        return best;
    }

    function applyPaymentBenefitVoucher(code, closeAfter = false){
        const normalized = String(code || '').trim();
        if (!normalized) return;
        setPaymentBenefitEnabled(true);
        selectedVoucherPaymentCode = normalized;
        paymentVoucherDiscount = 0;
        validatePaymentVoucherOnServer(normalized).done(function(res){
            if (res && res.ok) {
                paymentVoucherDiscount = Math.max(0, Number(res.discount || 0));
                notify(res.msg || 'Áp dụng ưu đãi thanh toán thành công', 'success');
                if (closeAfter && window.pmVoucherModal && typeof window.pmVoucherModal.close === 'function') {
                    window.pmVoucherModal.close();
                }
            } else {
                notify(res?.msg || 'Ưu đãi thanh toán không khả dụng', 'warning');
                setPaymentBenefitEnabled(false);
            }
            render();
        }).fail(function(){
            notify('Không kiểm tra được ưu đãi thanh toán', 'warning');
            setPaymentBenefitEnabled(false);
            render();
        });
    }

    let _autoSelectDone = false;
    function autoSelectBestVouchers(){
        if (_autoSelectDone) return;
        if (!cartItems.length) return;
        _autoSelectDone = true;
        const bestOrder = findBestSavedVoucher('order');
        if (bestOrder && !selectedVoucherOrderCode) {
            applyVoucher(bestOrder.code, 'order');
        }
        const bestShip = findBestSavedVoucher('shipping');
        if (bestShip && !selectedVoucherShipCode) {
            applyVoucher(bestShip.code, 'shipping');
        }
    }

    // Tìm voucher giảm giá nhiều nhất, KHẢ DỤNG NGAY theo ngữ cảnh hiện tại (đã đạt đơn tối thiểu
    // + đúng phương thức thanh toán/vận chuyển). Ưu tiên voucher đã lưu nhưng vẫn xét cả voucher khả dụng khác.
    function findBestEligibleVoucher(target){
        const list = target === 'shipping' ? vouchersShipping : vouchersOrder;
        if (!Array.isArray(list) || !list.length) return null;
        let best = null;
        let bestDiscount = -1;
        list.forEach(v => {
            if (target !== 'shipping') {
                const tpl = String(v?.voucher_template || '').trim().toLowerCase();
                if (tpl === 'payment_discount') return; // voucher thanh toán xử lý riêng
            }
            if (!String(v?.code || '').trim()) return;
            if (!voucherEligibleNow(v)) return;
            const d = estimateVoucherDiscount(v, target);
            // Ưu tiên voucher đã lưu khi mức giảm bằng nhau
            const code = String(v?.code || '').trim().toUpperCase();
            const isSaved = (Array.isArray(savedVoucherCodes) && savedVoucherCodes.includes(code)) || !!v?.is_saved || !!v?.saved;
            const score = d + (isSaved ? 0.5 : 0); // tie-breaker nhẹ cho voucher đã lưu
            if (score > bestDiscount) {
                bestDiscount = score;
                best = v;
            }
        });
        return best;
    }

    // Tự cập nhật voucher GỢI Ý theo ngữ cảnh thanh toán/vận chuyển hiện tại.
    // Không tự ép áp dụng nếu user chưa chọn — chỉ đề xuất mã tương thích để user bấm "Dùng ngay".
    function recomputeVoucherSuggestions(){
        if (!selectedVoucherOrderCode) {
            const best = findBestEligibleVoucher('order');
            suggestedVoucherOrderCode = best ? String(best.code || '').trim() : '';
        }
        if (!selectedVoucherShipCode) {
            const best = findBestEligibleVoucher('shipping');
            suggestedVoucherShipCode = best ? String(best.code || '').trim() : '';
        }
        renderVouchers();
    }

    function clearVoucherIfInvalid(){
        let changed = false;
        if (selectedVoucherOrderCode) {
            const voucher = findVoucherInList(vouchersOrder, selectedVoucherOrderCode);
            if (voucher && !voucherAllowedByContext(voucher)) {
                selectedVoucherOrderCode = '';
                discount = 0;
                persistSessionVoucher('', 'order');
                notify('Mã giảm giá không phù hợp với phương thức thanh toán/đơn vị vận chuyển hiện tại', 'warning');
                changed = true;
            } else if (voucher && !voucherMeetsMinSubtotal(voucher)) {
                selectedVoucherOrderCode = '';
                discount = 0;
                persistSessionVoucher('', 'order');
                notify('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá', 'warning');
                changed = true;
            }
        }
        if (selectedVoucherShipCode) {
            const voucher = findVoucherInList(vouchersShipping, selectedVoucherShipCode);
            if (voucher && !voucherAllowedByContext(voucher)) {
                selectedVoucherShipCode = '';
                shippingVoucherDiscount = 0;
                persistSessionVoucher('', 'shipping');
                notify('Mã giảm ship không phù hợp với phương thức thanh toán/đơn vị vận chuyển hiện tại', 'warning');
                changed = true;
            } else if (voucher && !voucherMeetsMinSubtotal(voucher)) {
                selectedVoucherShipCode = '';
                shippingVoucherDiscount = 0;
                persistSessionVoucher('', 'shipping');
                notify('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm ship', 'warning');
                changed = true;
            }
        }
        if (changed) {
            render();
        }
    }
/*
    function renderVoucherList(target, listData, listSelector, emptySelector){
        const $list = $(listSelector);
        if (!$list.length) return;
        const q = String($('#voucherSearch').val() || '').trim().toUpperCase();
        const list = (Array.isArray(listData) ? listData : []).filter(v => {
            const code = String(v?.code || '').trim().toUpperCase();
            if (!code || !savedVoucherCodes.includes(code)) return false;
            if (!q) return true;
            return code.includes(q);
        });

        const selectedCode = target === 'shipping' ? selectedVoucherShipCode : selectedVoucherOrderCode;

        if (!list.length) {
            $list.empty();
            $(emptySelector).show();
            return;
        }
        $(emptySelector).hide();

        const html = list.map(v => {
            const ct = (window.pmVoucherShared && typeof window.pmVoucherShared.condText === 'function')
                ? window.pmVoucherShared.condText(v || {})
                : { min: '', range: 'Không giới hạn' };
            const safeCode = $('<div>').text(v.code || '').html();
            const isShipping = target === 'shipping';
            const cardCls = isShipping ? 'voux-card voux-ship' : 'voux-card voux-order';
            const targetText = isShipping ? 'Mã ship' : 'Mã giảm giá';
            const savedPill = v.is_saved ? 'Đã lưu' : 'Mới';
            const isActive = selectedCode && selectedCode === String(v.code || '');
            const allowed = voucherAllowedByContext(v);
            const buttonText = allowed ? (isActive ? 'Đang dùng' : 'Dùng') : 'Không phù hợp';
            const disabledAttr = allowed ? '' : 'disabled';
            const dataUseAttr = allowed ? ('data-use="' + safeCode + '"') : '';
            const promoNote = v.promo_note ? $('<div>').text(v.promo_note).html() : '';
            const detailText = v.detail_text ? $('<div>').text(v.detail_text).html() : '';
            const restrictNote = allowed ? '' : 'Không áp dụng cho phương thức thanh toán/đơn vị vận chuyển hiện tại';
            const termsTitle = ct.min + ' • ' + ct.range;
            return ''
                + '<article class="' + cardCls + '">'
                + '  <div class="voux-accent"></div>'
                + '  <div class="voux-brand">'
                + '    <span class="voux-logo-icon"><i class="bi bi-ticket-perforated"></i></span>'
                + '    <div class="voux-brand-name">' + $('<div>').text(targetText).html() + '</div>'
                + '  </div>'
                + '  <div class="voux-main">'
                //+ '    <span class="voux-badge">' + $('<div>').text(savedPill).html() + '</span>'
                + '    <div class="voux-main-title">' + $('<div>').text(
                    (window.pmVoucherShared && typeof window.pmVoucherShared.couponText === 'function')
                        ? window.pmVoucherShared.couponText(v || {})
                        : ''
                ).html() + '</div>'
                //+ '    <div class="voux-sub coupon-code">' + safeCode + '</div>'
                + '    <div class="voux-sub">' + $('<div>').text(ct.min || 'Áp dụng mọi đơn hàng').html() + ' <a href="<?= h($baseUrl) ?>/view-voucher?code='+safeCode+'" class="voucher-terms-link" title="' + $('<div>').text(termsTitle).html() + '">Điều kiện</a></div>'
                //+ (promoNote ? '    <div class="voux-sub">' + promoNote + '</div>' : '')
                //+ (detailText ? '    <div class="voux-sub">' + detailText + '</div>' : '')
                //+ (restrictNote ? '    <div class="voux-sub text-danger">' + restrictNote + '</div>' : '')
                + '    <div class="voux-foot"><span class="voux-time">' + $('<div>').text(ct.range).html() + '</span></div>'
                + '  </div>'
                + '  <div class="voux-side">'
                + '    <button class="voux-btn ' + (isActive ? 'active' : '') + '" type="button" ' + dataUseAttr + ' data-target="' + target + '" ' + disabledAttr + '>' + $('<div>').text(buttonText).html() + '</button>'
                + '    <span class="voux-tag">' + $('<div>').text(targetText).html() + '</span>'
                + '  </div>'
                + '</article>';
        }).join('');
        $list.html(html);
    }*/

    function loadSavedCodes(){
        return window.pmVoucherAPI.loadSavedCodes()
            .done((codes) => {
                savedVoucherCodes = codes || [];
            })
            .fail(() => {
                savedVoucherCodes = [];
            });
    }

    function persistSessionVoucher(code, target){
        return window.pmVoucherAPI.persistSession(code, target);
    }

    function loadCart(){
        $.when(loadSavedCodes(), $.get(API, { ajax: 'cart_get' }))
            .done((savedRes, cartResRaw) => {
                const res = cartResRaw && cartResRaw[0] ? cartResRaw[0] : cartResRaw;
                if (!res || !res.ok){
                    notify(res?.msg || 'Không tải được giỏ hàng', 'error');
                    return;
                }
                const rawCart = res.data;
                cartItems = Array.isArray(rawCart)
                    ? rawCart
                    : (rawCart && typeof rawCart === 'object' ? Object.values(rawCart) : []);
                const bxgy = res.bxgy || {};
                bxgyDiscount = Number(bxgy.discount || 0);
                bxgyGifts = Array.isArray(bxgy.gifts) ? bxgy.gifts : [];
                vouchersOrder = Array.isArray(res.vouchers_order) ? res.vouchers_order : (Array.isArray(res.saved_vouchers_order) ? res.saved_vouchers_order : []);
                vouchersShipping = Array.isArray(res.vouchers_shipping) ? res.vouchers_shipping : (Array.isArray(res.saved_vouchers_shipping) ? res.saved_vouchers_shipping : []);
                vouchersPayment = Array.isArray(res.vouchers_payment) ? res.vouchers_payment : (Array.isArray(res.saved_vouchers_payment) ? res.saved_vouchers_payment : []);
                suggestedVoucherOrderCode = String(res?.suggested_voucher_order?.code || '').trim();
                suggestedVoucherShipCode = String(res?.suggested_voucher_shipping?.code || '').trim();
                const sessionVoucherOrderCode = String(res?.selected_voucher_code_order || res?.selected_voucher_code || '').trim();
                const sessionVoucherShipCode = String(res?.selected_voucher_code_shipping || '').trim();
                // Server gợi ý theo "voucher đã lưu đầu tiên", chưa xét PT thanh toán/vận chuyển hiện tại.
                // Bỏ ngay gợi ý không khả dụng để không hiển thị thẻ "Dùng ngay" sai (vd voucher giới hạn ZaloPay khi chưa chọn PT).
                if (suggestedVoucherOrderCode) {
                    const sv = findVoucherByCode('order', suggestedVoucherOrderCode);
                    if (!sv || !voucherEligibleNow(sv)) suggestedVoucherOrderCode = '';
                }
                if (suggestedVoucherShipCode) {
                    const sv = findVoucherByCode('shipping', suggestedVoucherShipCode);
                    if (!sv || !voucherEligibleNow(sv)) suggestedVoucherShipCode = '';
                }
                render();
                applyPaymentFilter(res.allowed_payments || null);
                refreshShippingQuote();
                if (!cartItems.length) {
                    $('#checkoutForm :input').prop('disabled', true);
                }
            })
            .fail(() => notify('Lỗi kết nối server', 'error'));
    }

    function applyVoucher(codeOverride, targetOverride, closeAfter = false){
        const target = targetOverride === 'shipping' ? 'shipping' : 'order';
        const code = String(codeOverride || $('#voucherSearch').val() || '').trim();
        const sub = subtotal();
        if (!code){
            if (target === 'shipping') {
                shippingVoucherDiscount = 0;
                selectedVoucherShipCode = '';
            } else {
                discount = 0;
                selectedVoucherOrderCode = '';
            }
            notify('Đã bỏ voucher ' + (target === 'shipping' ? 'ship' : 'đơn hàng'), 'info');
            persistSessionVoucher('', target);
            render();
            return;
        }
        const paymentMethod = getSelectedPaymentMethod();
        const shippingMethod = String(selectedShippingMethod || '');

        // Nếu người dùng chọn/nhập voucher thanh toán nhưng đang apply vào "đơn hàng" thì route sang ưu đãi thanh toán.
        if (target === 'order') {
            const vPay = findVoucherInList(vouchersPayment, code);
            const tplPay = String(vPay?.voucher_template || '').trim().toLowerCase();
            if (tplPay === 'payment_discount') {
                applyPaymentBenefitVoucher(code, closeAfter);
                return;
            }
        }

        window.pmVoucherAPI.validate({
            target,
            code,
            subtotal: sub,
            shipping_fee: Number(shippingFee || 0),
            product_ids: currentProductIds().join(','),
            payment_method: paymentMethod,
            shipping_method: shippingMethod,
            channel: 'web'
        }).done(res => {
            if (!res){
                if (target === 'shipping') shippingVoucherDiscount = 0;
                else discount = 0;
                notify('Không kiểm tra được mã voucher', 'warning');
                if (target === 'shipping') selectedVoucherShipCode = code;
                else selectedVoucherOrderCode = code;
                render();
                return;
            }
            if (res.ok){
                if (target === 'shipping') {
                    selectedVoucherShipCode = code;
                    // Sử dụng đúng số tiền giảm ship server trả về để đồng bộ với backend
                    shippingVoucherDiscount = Math.max(0, Number(res.discount || 0));
                } else {
                    selectedVoucherOrderCode = code;
                    // Sử dụng đúng số tiền giảm đơn server trả về để đồng bộ với backend
                    discount = Math.max(0, Number(res.discount || 0));
                }
                notify(res.msg || 'Áp dụng voucher thành công', 'success');
                const req = persistSessionVoucher(code, target);
                if (closeAfter && window.pmVoucherModal && typeof window.pmVoucherModal.close === 'function') {
                    req.always(() => window.pmVoucherModal.close());
                }
            } else {
                if (target === 'shipping') {
                    shippingVoucherDiscount = 0;
                    selectedVoucherShipCode = '';
                } else {
                    discount = 0;
                    selectedVoucherOrderCode = '';
                }
                notify(res.msg || 'Mã voucher không hợp lệ', 'warning');
                persistSessionVoucher('', target);
            }
            render();
        });
    }

    function openSharedVoucherModal(initialTab){
        if (!window.pmVoucherModal || typeof window.pmVoucherModal.open !== 'function') {
            notify('Không mở được Kho Voucher lúc này', 'warning');
            return;
        }
        const combined = [];
        (Array.isArray(vouchersOrder) ? vouchersOrder : []).forEach(v => {
            const tpl = String(v?.voucher_template || '').trim().toLowerCase();
            if (tpl === 'payment_discount') return;
            combined.push({ ...v, discount_target: 'order' });
        });
        (Array.isArray(vouchersShipping) ? vouchersShipping : []).forEach(v => combined.push({ ...v, discount_target: 'shipping' }));
        (Array.isArray(vouchersPayment) ? vouchersPayment : []).forEach(v => combined.push({ ...v, discount_target: 'order' }));
        const opts = {
            vouchers: combined,
            savedVoucherCodes: Array.isArray(savedVoucherCodes) ? savedVoucherCodes : [],
            initialTab: initialTab === 'shipping' ? 'shipping' : (initialTab === 'payment' ? 'payment' : 'order'),
            selectedOrderCode: selectedVoucherOrderCode,
            selectedShipCode: selectedVoucherShipCode,
            selectedPaymentCode: (paymentBenefitEnabled ? selectedVoucherPaymentCode : ''),
            onApply: ({ code, target }) => {
                if (String(target || '') === 'payment') {
                    applyPaymentBenefitVoucher(code, true);
                    return;
                }
                // Safety: nếu vì lý do nào đó voucher thanh toán vẫn được apply từ modal, route sang ưu đãi thanh toán.
                if (String(target || '') === 'order') {
                    const vPay = findVoucherInList(vouchersPayment, code);
                    const tplPay = String(vPay?.voucher_template || '').trim().toLowerCase();
                    if (tplPay === 'payment_discount') {
                        applyPaymentBenefitVoucher(code, true);
                        return;
                    }
                }
                applyVoucher(code, target, true);
            },
            onSaved: (code) => {
                loadCart();
            }
        };
        activeVoucherTab = opts.initialTab;
        window.pmVoucherModal.open(opts);
    }

    $('#voucherOpen2').click(function(){
        openSharedVoucherModal('order');
    });
    $('#quickVoucherOrder').on('click', function(e){
        if ($(e.target).closest('.vcp-use-btn, .vcp-tnc').length) return;
        openSharedVoucherModal('order');
    });
    $('#quickVoucherShip').on('click', function(e){
        if ($(e.target).closest('.vcp-use-btn, .vcp-tnc').length) return;
        openSharedVoucherModal('shipping');
    });

    $('.voucher-quick-wrap').on('click', '.vcp-use-btn', function(e){
        e.preventDefault();
        e.stopPropagation();
        const code = String($(this).attr('data-vcp-use') || '').trim();
        const target = String($(this).attr('data-vcp-target') || 'order').trim();
        if (!code) return;
        if (target === 'payment') {
            applyPaymentBenefitVoucher(code, true);
            return;
        }
        applyVoucher(code, target === 'shipping' ? 'shipping' : 'order', true);
    });

    $('.voucher-quick-wrap').on('click', '.voucher-remove-btn', function(e){
        e.preventDefault();
        e.stopPropagation();
        const target = String($(this).data('target') || 'order');
        if (target === 'shipping') {
            shippingVoucherDiscount = 0;
            selectedVoucherShipCode = '';
            notify('Đã bỏ voucher ship', 'info');
            persistSessionVoucher('', 'shipping');
        } else if (target === 'payment') {
            setPaymentBenefitEnabled(false);
            notify('Đã bỏ ưu đãi thanh toán', 'info');
        } else {
            discount = 0;
            selectedVoucherOrderCode = '';
            notify('Đã bỏ voucher đơn', 'info');
            persistSessionVoucher('', 'order');
        }
        render();
    });
    $('#xuToggle').on('change', function(){
        if ($(this).is(':checked')) {
            requestedXu = computeMaxXuAllowed();
        } else {
            requestedXu = 0;
        }
        render();
    });
    $('#addressCardPick').on('click', function(e){
        if (IS_GUEST) return;
        if ($(e.target).closest('button, a, input, label').length) return;
        openAddressPicker();
    });
    $('#btnCloseAddressPicker, #addressBackdrop').on('click', closeAddressPanel);
    $('#addressListWrap').on('click', '.address-item', function(){
        const addressId = String($(this).data('address-id') || '').trim();
        if (!addressId) return;
        $.post(REGION_API, { action: 'set_active', address_id: addressId }, res => {
            if (!res || !res.ok) {
                notify(res?.message || 'Không chọn được địa chỉ', 'warning');
                return;
            }
            selectedLocation = res.location || selectedLocation;
            if (Array.isArray(res.saved_locations)) savedLocations = res.saved_locations;
            renderAddressCard();
            renderSavedAddresses();
            closeAddressPanel();
            refreshShippingQuote();
        }, 'json').fail(() => notify('Lỗi kết nối khi chọn địa chỉ', 'error'));
    });

    $('#shipPickerBox').on('click', function(e){
        const methods = Array.isArray(shippingQuote?.methods) ? shippingQuote.methods : [];
        if (!methods.length) {
            notify('Chưa có phương thức vận chuyển để chọn', 'info');
            return;
        }
        openShipPanel();
    });
    $('#btnCloseShipPicker, #shipBackdrop').on('click', closeShipPanel);

    if (IS_GUEST) {
        guestLoadProvinces();
        $guestProvince.on('change', function(){
            const provinceId = String($(this).val() || '').trim();
            guestLoadDistricts(provinceId);
        });
        $guestDistrict.on('change', function(){
            const districtId = String($(this).val() || '').trim();
            guestLoadWards(districtId);
        });
        $guestWard.on('change', function(){
            guestPersistLocationIfReady();
            refreshShippingQuote();
        });
        $('#inputAddress, #inputPhone, #inputUserName').on('blur', function(){
            guestPersistLocationIfReady();
        });
    }

    $('#invoiceToggle').on('change', function(){
        const show = $(this).is(':checked');
        $('#invoiceFieldsWrap').toggle(show);
        render();
    });

    $('#checkoutForm').submit(function(e){
        e.preventDefault();
        if (checkoutSubmitting) return;
        if (!cartItems.length){ notify('Giỏ hàng trống', 'warning'); return; }

        if (shippingQuote && shippingQuote.blocked) {
            notify(String(shippingQuote.block_reason || 'Không thể đặt hàng do cấu hình vận chuyển sản phẩm'), 'warning');
            return;
        }
        if (!shippingQuote || !Array.isArray(shippingQuote.methods) || !shippingQuote.methods.length) {
            notify('Chưa có phương thức vận chuyển hợp lệ cho giỏ hàng này', 'warning');
            return;
        }
        if (!String(selectedShippingMethod || '').trim()) {
            notify('Vui lòng chọn phương thức vận chuyển', 'warning');
            return;
        }
        const allowedShipKeys = shippingQuote.methods.map(m => String(m?.key || '').trim()).filter(Boolean);
        if (allowedShipKeys.length && !allowedShipKeys.includes(String(selectedShippingMethod || '').trim())) {
            notify('Phương thức vận chuyển không hợp lệ, vui lòng chọn lại', 'warning');
            selectedShippingMethod = '';
            renderShippingMethods();
            return;
        }

        const isGhnMethod = (function(){
            const key = String(selectedShippingMethod || '').trim().toLowerCase();
            return key !== '' && key.indexOf('ghn_') === 0;
        })();

        // Chặn sớm trên giao diện: không cho tạo đơn nếu tổng thanh toán quá lớn
        const currentTotal = (function(){
            const sub = subtotal();
            const vat = computeVatTotal();
            const ship = Number(shippingFee || 0);
            const shipVoucher = Number(shippingVoucherDiscount || 0);
            const orderDiscount = Number(discount || 0);
            const payDiscount = Number(paymentVoucherDiscount || 0);
            const xuDisc = Number(xuDiscount || 0);
            // BXGY đã thể hiện bằng sản phẩm tặng 0đ nên không trừ thêm nữa
            return Math.max(0, (sub + vat) - orderDiscount - payDiscount - xuDisc + Math.max(0, ship - shipVoucher));
        })();
        if (currentTotal >= 100000000) {
            notify('Giá trị đơn hàng vượt giới hạn 100.000.000 đ. Vui lòng tách thành nhiều đơn nhỏ hơn.', 'warning');
            return;
        }

        const form = Object.fromEntries(new FormData(this).entries());
        const receiverPhone = String(form.phone || '').trim();
        const receiverAddress = String(form.address || '').trim();
        const phoneDigits = normalizePhoneDigits(receiverPhone);

        if (!receiverAddress) {
            notify('Vui lòng nhập địa chỉ giao hàng (số nhà, tên đường)', 'warning');
            return;
        }
        const phoneRegex = /^(0|84|\+84)[35789]\d{8}$/;
        if (!receiverPhone || !phoneRegex.test(receiverPhone.replace(/\s+/g, ''))) {
            notify('Số điện thoại không hợp lệ (phải là số điện thoại Việt Nam 10 chữ số)', 'warning');
            return;
        }
        if (IS_GUEST) {
            const provinceId = String($('#guestProvince').val() || '').trim();
            const districtId = String($('#guestDistrict').val() || '').trim();
            const wardCode = String($('#guestWard').val() || '').trim();
            if (!provinceId || !districtId || !wardCode) {
                notify('Vui lòng chọn đầy đủ Tỉnh/Thành, Quận/Huyện, Phường/Xã để nhận hàng', 'warning');
                return;
            }
            if (isGhnMethod) {
                // Cập nhật location GHN cho phiên khách trước khi gửi checkout
                guestPersistLocationIfReady();
            }
        }
        if (isGhnMethod) {
            if (!IS_GUEST && (!selectedLocation || !isGhnLocationReady(selectedLocation))) {
                notify('Địa chỉ chưa chuẩn hoá theo GHN. Vui lòng cập nhật lại trong tab Địa chỉ giao hàng.', 'warning');
                setTimeout(() => { window.location.href = ACCOUNT_ADDRESS_URL; }, 450);
                return;
            }
        }
        const pm = form.payment_method || 'cod';
        if (allowedPaymentKeys && allowedPaymentKeys.length && !allowedPaymentKeys.includes(pm)) {
            notify('Phương thức thanh toán không khả dụng cho đơn hàng này', 'warning');
            return;
        }

        const btnText = (pm === 'cod') ? 'Đặt hàng (Thanh toán khi nhận hàng)' : 'Tạo đơn (Chờ thanh toán)';
        const $btn = $('#btnSubmit');
        $btn.text(btnText);

        // Lock form submission and show loading spinner
        checkoutSubmitting = true;
        $btn.prop('disabled', true);
        const originalHtml = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xử lý...');

        const payload = {
            action: 'checkout_save',
            checkout_token: form.checkout_token || '',
            user_name: String(form.user_name || '').trim() || 'Khách hàng',
            phone: form.phone || '',
            email: form.email || '',
            address: form.address || '',
            note: form.note || '',
            payment_method: pm,
            shipping_method: selectedShippingMethod || '',
            voucher_order_code: selectedVoucherOrderCode || '',
            voucher_ship_code: selectedVoucherShipCode || '',
            voucher_payment_code: (paymentBenefitEnabled ? (selectedVoucherPaymentCode || '') : ''),
            xu_use: requestedXu || 0,
            products_json: JSON.stringify(buildCheckoutItemsPayload()),
            invoice_want: $('#invoiceToggle').is(':checked') ? 1 : 0,
            invoice_type: form.invoice_type || 'personal',
            invoice_buyer_name: form.invoice_buyer_name || '',
            invoice_company_name: form.invoice_company_name || '',
            invoice_tax_code: form.invoice_tax_code || '',
            invoice_address: form.invoice_address || '',
            invoice_email: form.invoice_email || ''
        };

        $.post(API, payload, res => {
            if (res && res.ok){
                const momoPayUrl = res?.momo?.pay_url || res?.momo?.deeplink || res?.momo?.qr_url || '';
                const redirectUrl = res.payment_url || momoPayUrl;
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                    return;
                }
                const url = '<?= h($baseUrl) ?>/order-confirm?order_id=' + encodeURIComponent(res.order_id);
                window.location.href = url;
            } else {
                notify(res?.msg || 'Không thể tạo đơn', 'error');
                if (res && res.new_token) {
                    $('#checkoutToken').val(res.new_token);
                }
                checkoutSubmitting = false;
                $btn.prop('disabled', false).html(originalHtml);
            }
        }).fail(() => {
            notify('Lỗi kết nối server', 'error');
            checkoutSubmitting = false;
            $btn.prop('disabled', false).html(originalHtml);
        });
    });

    $('#payWrap').on('change', 'input[name="payment_method"]', function(){
        if ($(this).prop('disabled')) return;
        $('#payWrap .payment-chip').removeClass('active');
        $(this).closest('.payment-chip').addClass('active');
        updatePaymentSelected();
        renderVouchers();
        clearVoucherIfInvalid();
        // Khi đổi phương thức thanh toán, nếu voucher hiện tại không còn hợp lệ thì tự động chọn mã tốt nhất tiếp theo
        if (!selectedVoucherOrderCode) {
            const bestOrder = findBestSavedVoucher('order');
            if (bestOrder) applyVoucher(bestOrder.code, 'order');
        }
        if (!selectedVoucherShipCode) {
            const bestShip = findBestSavedVoucher('shipping');
            if (bestShip) applyVoucher(bestShip.code, 'shipping');
        }
        // Tự đề xuất voucher tương thích với phương thức thanh toán vừa chọn
        recomputeVoucherSuggestions();
        // Nếu đang áp dụng ưu đãi thanh toán, re-validate theo phương thức mới
        if (paymentBenefitEnabled && String(selectedVoucherPaymentCode || '').trim()) {
            paymentVoucherDiscount = 0;
            validatePaymentVoucherOnServer(selectedVoucherPaymentCode).done(function(res){
                if (res && res.ok) {
                    paymentVoucherDiscount = Math.max(0, Number(res.discount || 0));
                } else {
                    notify(res?.msg || 'Ưu đãi thanh toán không khả dụng', 'warning');
                    setPaymentBenefitEnabled(false);
                }
                render();
            }).fail(function(){
                notify('Không kiểm tra được ưu đãi thanh toán', 'warning');
                setPaymentBenefitEnabled(false);
                render();
            });
            return;
        }
        render();
    });

    // See more products in checkout
    $('#itemsWrap').on('click', '#btnShowMoreItems', function(){
        $('#itemsHidden').slideDown(300);
        $(this).closest('.show-more-wrap').fadeOut(200, function(){ $(this).remove(); });
    });

    function openPaymentVoucherModal(){
        const pm = getSelectedPaymentMethod();
        if (!pm) {
            notify('Vui lòng chọn phương thức thanh toán', 'info');
            return;
        }
        openSharedVoucherModal('payment');
    }

    $('#paymentVoucherOpen').on('click', function(){
        openPaymentVoucherModal();
    });

    $('#quickVoucherPayment').on('click', function(e){
        if ($(e.target).closest('.vcp-use-btn, .vcp-tnc').length) return;
        openPaymentVoucherModal();
    });

    $('#shipMethodListWrap').on('click', '.ship-method-option', function(){
        const method = String($(this).data('method') || '').trim();
        if (!method) return;
        selectedShippingMethod = method;
        closeShipPanel();
        refreshShippingQuote();
    });

    $('#shipMethodListWrap').on('change', '.ship-method-radio', function(){
        const method = String($(this).val() || '').trim();
        if (!method) return;
        selectedShippingMethod = method;
        closeShipPanel();
        refreshShippingQuote();
    });

    // Flash message from cart (e.g. removed out-of-stock items)
    try {
        const flashMsg = sessionStorage.getItem('checkout_flash_msg');
        if (flashMsg) {
            notify(String(flashMsg), 'info');
            sessionStorage.removeItem('checkout_flash_msg');
        }
    } catch (e) {}

    $(function() {
        // Khách vãng lai: chỉ cần giỏ hàng + phí ship cơ bản
        updatePaymentSelected();
        loadCart();
        if (!IS_GUEST) {
            loadProfile();
            loadWalletSummary();
        } else {
            // Ẩn các khối chỉ dành cho tài khoản đăng nhập
            $('.xu-toggle-row').hide();
            $('#xuUseInput').val('0');
            //$('#voucherOpen2').hide();
            //$('.voucher-quick-wrap').hide();
        }
        if (VOUCHER_PREFILL && String(VOUCHER_PREFILL).trim()) {
            setTimeout(() => applyVoucher(String(VOUCHER_PREFILL).trim(), 'order'), 200);
        }
    });
})();
</script>
