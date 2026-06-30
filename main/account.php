<?php
require_once __DIR__ . '/../config.php';

if (empty($isLoggedIn)) {
    echo '<div class="text-center">Vui lòng đăng nhập để sử dụng.</div>';
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userData = loadUser($ithanhloc, $userId);

if (!$userData) {
    echo '<div class="text-center">Vui lòng đăng nhập để sử dụng.</div>';
    exit;
}

// Các biến hiển thị tổng quan account header/sidebar.
$displayName = $userData['full_name'] ?: $userData['username'];
$initials = strtoupper(substr($displayName, 0, 2));
$joined = $userData['created_at'] ? date('d/m/Y', strtotime($userData['created_at'])) : '—';
$roleLabel = $userData['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng';
$avatarUrl = app_get_media_url($userData['avatar'] ?? null);

// Nạp địa chỉ đang áp dụng (DB hoặc Session) để prefill form.
$fallbackRecipientName = trim((string)($userData['full_name'] ?? ($userData['username'] ?? '')));
$appliedLocation = function_exists('ecommerce_get_active_location_fields')
    ? ecommerce_get_active_location_fields($ithanhloc, $userId, ['fallback_recipient_name' => $fallbackRecipientName])
    : [];

$selectedLocation = $appliedLocation['raw_location'] ?? [];
$fallbackPhone = (string)($appliedLocation['fallback_phone'] ?? '');
$selectedAddressId = (string)($appliedLocation['address_id'] ?? '');
$selectedStreet = (string)($appliedLocation['street'] ?? '');
$selectedWard = (string)($appliedLocation['ward'] ?? '');
$selectedWardCode = (string)($appliedLocation['ward_code'] ?? '');
$selectedDistrict = (string)($appliedLocation['district'] ?? '');
$selectedDistrictId = (int)($appliedLocation['district_id'] ?? 0);
$selectedProvince = (string)($appliedLocation['province'] ?? '');
$selectedRegion = $selectedProvince;
$selectedProvinceId = (int)($appliedLocation['province_id'] ?? 0);
$selectedContactPhone = (string)($appliedLocation['contact_phone'] ?? $fallbackPhone);
$selectedRecipientName = (string)($appliedLocation['recipient_name'] ?? $fallbackRecipientName);
$selectedAddressType = (string)($appliedLocation['address_type'] ?? 'home');
$selectedDeliveryNote = (string)($appliedLocation['delivery_note'] ?? '');
?>
<style>
    /* ── Account Page ── */
    .account-section {
        display: none;
    }

    .account-section.active {
        display: block;
    }

    .alert-soft {
        display: none;
    }

    .alert-soft.show {
        display: block;
        border-radius: 8px;
        margin-top: 8px;
    }

    /* ── Sidebar nav ── */
    .sidebar-link {
        display: flex;
        align-items: center;
        padding: 8px 10px;
        border-radius: 6px;
        color: #222;
        text-decoration: none;
        font-weight: 500;
        transition: background .15s;
    }

    .sidebar-link:hover {
        background: #f5f5f5;
    }

    .sidebar-link.active {
        color: var(--theme-primary, #0c4c29);
        font-weight: 700;
    }

    .sidebar-sublink {
        display: block;
        padding: 6px 0 6px 28px;
        color: #555;
        font-size: .88rem;
        text-decoration: none;
        transition: color .15s;
    }

    .sidebar-sublink:hover,
    .sidebar-sublink.active {
        color: var(--theme-primary, #0c4c29);
        font-weight: 700;
    }

    [data-submenu] {
        display: none;
    }

    [data-submenu].menu-sublist--open {
        display: block;
    }

    /* ── Avatar divider (desktop) ── */
    .avatar-divider {
        padding-left: 24px;
        position: relative;
    }

    .avatar-divider::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 1px;
        background: #e5e5e5;
    }

    @media(max-width:768px) {
        .avatar-divider::before {
            display: none;
        }

        .avatar-divider {
            padding-left: 0;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e5e5;
        }
    }

    /* ── Section headers ── */
    .account-header {
        border-bottom: 1px solid #f1f1f1;
        padding-bottom: 12px;
        margin-bottom: 18px;
    }

    .account-header--notify {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .account-title {
        font-size: 1.1rem;
        font-weight: 800;
        margin: 0 0 4px;
    }

    .account-subtitle {
        color: #888;
        font-size: .9rem;
    }

    /* ── Bank / Card sections ── */
    .bacc-shell {
        display: grid;
        gap: 16px;
    }

    .bacc-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px;
    }

    .bacc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        border-bottom: 1px solid #f1f1f1;
        padding-bottom: 12px;
        margin-bottom: 12px;
    }

    .bacc-title {
        font-weight: 800;
        font-size: 1rem;
        color: #0f172a;
    }

    .bacc-subtitle {
        font-size: .85rem;
        color: #64748b;
    }

    .bacc-btn {
        background: var(--theme-primary, #0c4c29);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 8px 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .85rem;
        cursor: pointer;
    }

    .bacc-btn svg {
        width: 12px;
        height: 12px;
        fill: currentColor;
    }

    .bacc-list {
        display: grid;
        gap: 10px;
    }

    .bacc-form {
        border: 1px dashed #e5e7eb;
        border-radius: 12px;
        padding: 12px;
        background: #fafafa;
        margin-bottom: 12px;
    }

    .bacc-form.hidden {
        display: none;
    }

    .bacc-form-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .bacc-toggle-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .85rem;
        color: #64748b;
        margin-top: 8px;
    }

    .bank-empty {
        font-size: .85rem;
        color: #94a3b8;
    }

    .bacc-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .bacc-card-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bacc-default-badge {
        font-size: .7rem;
        font-weight: 700;
        color: var(--theme-primary, #0c4c29);
        background: rgba(12, 76, 41, .08);
        border: 1px solid var(--theme-primary, #0c4c29);
        border-radius: 999px;
        padding: 2px 8px;
        white-space: nowrap;
    }

    /* Bank account row */
    .bacc-bank {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fff;
        flex-wrap: wrap;
    }

    .bacc-bank-logo {
        flex-shrink: 0;
        width: 52px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        padding: 4px;
    }

    .bacc-bank-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
    }

    .bacc-bank-main {
        flex: 1 1 0;
        min-width: 0;
    }

    .bacc-bank-name {
        font-weight: 600;
        font-size: .9rem;
        color: #111827;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bacc-bank-meta {
        font-size: .8rem;
        color: #6b7280;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bacc-bank-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
        margin-left: auto;
    }

    /* Card row */
    .bacc-card-logo {
        flex-shrink: 0;
        width: 52px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        padding: 4px;
    }

    .bacc-card-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
    }

    /* ── Orders (ordx) ── */
    .ordx-shell {
        display: grid;
        gap: 12px;
    }

    .ordx-main-card {
        border: 1px solid #ececec;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }

    .ordx-main-card-body {
        padding: 12px;
        display: grid;
        gap: 12px;
    }

    .ordx-tablist {
        display: flex;
        overflow-x: auto;
        background: #fff;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    .ordx-tablist::-webkit-scrollbar {
        display: none;
    }

    .ordx-tab {
        flex: 1 0 auto;
        min-width: 110px;
        border: none;
        border-right: 1px solid #f0f0f0;
        background: #fff;
        color: #4b5563;
        font-size: .88rem;
        padding: 11px 10px;
        font-weight: 700;
        cursor: pointer;
    }

    .ordx-tab:last-child {
        border-right: none;
    }

    .ordx-tab.active {
        color: var(--theme-primary, #0c4c29);
        box-shadow: inset 0 -2px 0 var(--theme-primary, #0c4c29);
    }

    .ordx-search {
        position: relative;
    }

    .ordx-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .ordx-search input {
        width: 100%;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 12px 10px 36px;
        font-size: .88rem;
    }

    .ordx-meta {
        font-size: .82rem;
        color: #6b7280;
    }

    .ordx-list {
        display: grid;
        gap: 12px;
    }

    .ordx-card {
        border: 1px solid #ededed;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .ordx-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-bottom: 1px solid #f4f4f4;
        flex-wrap: wrap;
    }

    .ordx-shop {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #111827;
        font-weight: 700;
        font-size: .9rem;
    }

    .ordx-status {
        color: var(--theme-primary, #0c4c29);
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .ordx-body {
        padding: 10px 12px;
        display: grid;
        gap: 8px;
    }

    .ordx-item {
        display: grid;
        grid-template-columns: 52px 1fr auto;
        gap: 10px;
        align-items: center;
    }

    .ordx-thumb {
        width: 52px;
        height: 52px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .ordx-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ordx-item-name {
        font-size: .88rem;
        color: #111827;
        line-height: 1.35;
    }

    .ordx-item-variant {
        font-size: .78rem;
        color: #6b7280;
    }

    .ordx-foot {
        border-top: 1px solid #f4f4f4;
        padding: 10px 12px;
        display: grid;
        gap: 10px;
    }

    .ordx-total {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }

    .ordx-total-value {
        font-size: 1rem;
        font-weight: 800;
        color: var(--theme-primary, #0c4c29);
    }

    .ordx-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ordx-btn {
        border-radius: 6px;
        font-size: .82rem;
        padding: 7px 10px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        font-weight: 700;
        cursor: pointer;
    }

    .ordx-btn.primary {
        background: var(--theme-primary, #0c4c29);
        border-color: var(--theme-primary, #0c4c29);
        color: #fff;
    }

    .ordx-empty {
        border: 1px dashed #d1d5db;
        border-radius: 8px;
        padding: 20px 12px;
        text-align: center;
        color: #6b7280;
        font-size: .88rem;
    }

    .ordx-loadmore {
        border: 1px solid var(--theme-primary, #0c4c29);
        border-radius: 8px;
        background: #fff;
        color: var(--theme-primary, #0c4c29);
        font-weight: 700;
        padding: 10px 12px;
        display: none;
        width: 100%;
        cursor: pointer;
    }

    .ordx-loadmore.show {
        display: block;
    }

    /* item: qty căn trên cho thẳng tên */
    .ordx-item { align-items: start; }
    .ordx-item-qty { font-size: .82rem; color: #6b7280; white-space: nowrap; }
    .ordx-more { font-size: .8rem; color: #6b7280; }

    /* ── Đơn mua: gọn lại trên mobile ── */
    @media (max-width: 576px) {
        .ordx-list { gap: 10px; }

        .ordx-head { padding: 8px 10px; }
        .ordx-shop { font-size: .82rem; gap: 6px; }
        .ordx-status { font-size: .72rem; }

        .ordx-body { padding: 8px 10px; gap: 8px; }

        .ordx-item {
            grid-template-columns: 44px 1fr auto;
            gap: 8px;
        }
        .ordx-thumb { width: 44px; height: 44px; border-radius: 6px; }
        .ordx-item-name {
            font-size: .82rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .ordx-item-variant { font-size: .72rem; }
        .ordx-item-qty { font-size: .78rem; }

        /* foot: tổng tiền (trái) + nút (phải) trên CÙNG 1 hàng -> đỡ cao */
        .ordx-foot {
            padding: 8px 10px;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 8px;
        }
        .ordx-total { justify-content: flex-start; gap: 4px; min-width: 0; flex-wrap: wrap; }
        .ordx-total-label { font-size: .75rem; color: #6b7280; }
        .ordx-total-value { font-size: .95rem; }

        .ordx-actions { justify-content: flex-end; gap: 6px; }
        .ordx-btn {
            font-size: .78rem;
            padding: 6px 10px;
        }
    }

    /* ── Notifications ── */
    .notify-list {
        display: grid;
        gap: 10px;
    }

    .notify-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #fff;
        cursor: pointer;
        transition: background .15s, border-color .15s, box-shadow .15s;
        flex-wrap: wrap;
    }

    .notify-card:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
    }

    .bacc-card--unread.notify-item {
        border-color: var(--theme-primary, #0c4c29);
        background: rgba(12, 76, 41, .04);
    }

    .notify-card-thumb {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: #fee2e2;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f97316;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .notify-card-thumb--order {
        background: #eff6ff;
        color: #2563eb;
    }

    .notify-card-thumb--payment {
        background: #ecfdf5;
        color: #16a34a;
    }

    .notify-card-thumb--security {
        background: #fefce8;
        color: #ca8a04;
    }

    .notify-card-thumb--account {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .notify-card-thumb--has-image {
        background-size: cover;
        background-position: center;
    }

    .notify-card-thumb--has-image i {
        display: none;
    }

    .notify-card-main {
        flex: 1 1 0;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .notify-card-title {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.4;
    }

    .notify-card-body {
        font-size: .87rem;
        color: #4b5563;
        white-space: pre-line;
    }

    .notify-card-time {
        font-size: .8rem;
        color: #94a3b8;
    }

    .notify-card-btn {
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 6px 14px;
        font-size: .82rem;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        cursor: pointer;
    }

    .notify-card-btn:hover {
        border-color: var(--theme-primary, #0c4c29);
        color: var(--theme-primary, #0c4c29);
    }

    .notify-pagination {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 12px;
    }

    .notify-page-btn {
        border: none;
        background: #f3f3f3;
        color: #333;
        border-radius: 4px;
        padding: 4px 12px;
        cursor: pointer;
    }

    .notify-page-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .notify-page-info {
        min-width: 48px;
        text-align: center;
        font-weight: 500;
        color: #666;
    }

    /* ── Thông báo: gọn lại trên mobile ── */
    @media (max-width: 576px) {
        .notify-list { gap: 8px; }

        .notify-card {
            gap: 10px;
            padding: 10px;
            border-radius: 12px;
            flex-wrap: nowrap;            /* không xuống dòng -> thumb | nội dung | nút */
            align-items: center;
            position: relative;
        }

        .notify-card-thumb {
            width: 38px;
            height: 38px;
            font-size: .95rem;
            border-radius: 9px;
        }

        .notify-card-main { gap: 2px; }

        .notify-card-title {
            font-size: .85rem;
            line-height: 1.3;
            /* tối đa 1 dòng cho gọn */
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notify-card-body {
            font-size: .78rem;
            line-height: 1.35;
            /* rút gọn còn 2 dòng — đọc đủ thì bấm "Xem chi tiết" */
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notify-card-time { font-size: .7rem; }

        /* Nút "Xem chi tiết" -> icon mũi tên gọn ở mép phải, không chiếm chỗ ngang */
        .notify-card-actions { flex-shrink: 0; align-self: center; }
        .notify-card-btn {
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0;                 /* ẩn chữ "Xem chi tiết" */
        }
        .notify-card-btn::before {
            content: "\F285";             /* bi-chevron-right */
            font-family: "bootstrap-icons";
            font-size: .9rem;
        }
    }

    /* ── Address (addrx) ── */
    .addrx-shell {
        border: 1px solid #f0f0f0;
        border-radius: 8px;
        background: #fff;
    }

    .addrx-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #f1f1f1;
        flex-wrap: wrap;
    }

    .addrx-title {
        font-size: 1rem;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }

    .addrx-meta {
        font-size: .82rem;
        color: #6b7280;
    }

    .addrx-add-btn {
        background: var(--theme-primary, #0c4c29);
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 8px 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .addrx-list {
        display: flex;
        flex-direction: column;
    }

    /* ===== Hàng địa chỉ — thiết kế lại 2026-06-22: list gọn, không card, ngăn cách bằng kẻ ===== */
    .addrx-card {
        position: relative;
        display: grid;
        grid-template-columns: 34px 1fr;
        gap: 12px;
        padding: 14px 4px;
        border-top: 1px solid #eef0f2;
        transition: background-color .15s ease;
    }
    .addrx-card:first-child { border-top: 0; }
    .addrx-card:hover { background: #fafbfc; }

    /* Ngôi sao vàng đánh dấu địa chỉ mặc định */
    .addrx-badge--default i { color: #f5b50a; }

    /* Avatar chữ cái đầu của người nhận */
    .addrx-avatar {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        font-weight: 800;
        color: var(--theme-primary, #0c4c29);
        background: rgba(12, 76, 41, .09);
        text-transform: uppercase;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .addrx-card--active .addrx-avatar {
        background: var(--theme-primary, #0c4c29);
        color: #fff;
    }

    .addrx-card-body {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .addrx-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }

    .addrx-card-ident {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .addrx-card-name {
        font-size: .92rem;
        font-weight: 800;
        color: #111827;
        text-transform: uppercase;
        line-height: 1.2;
        letter-spacing: .2px;
    }

    .addrx-card-phone {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #6b7280;
        font-size: .82rem;
        padding-left: 8px;
        border-left: 1px solid #e5e7eb;
    }
    .addrx-card-phone i { color: #9ca3af; font-size: .8rem; }

    .addrx-card-tags {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .addrx-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        font-size: .7rem;
        padding: 3px 9px;
        font-weight: 700;
        line-height: 1.4;
        white-space: nowrap;
    }
    .addrx-badge i { font-size: .7rem; }
    .addrx-badge--default {
        background: var(--theme-primary, #0c4c29);
        color: #fff;
    }
    .addrx-badge--type {
        background: #f3f4f6;
        color: #4b5563;
    }

    /* Dòng địa chỉ đầy đủ */
    .addrx-card-addr {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        color: #4b5563;
        font-size: .85rem;
        line-height: 1.5;
    }
    .addrx-card-addr i {
        color: var(--theme-primary, #0c4c29);
        font-size: .9rem;
        margin-top: 1px;
        flex-shrink: 0;
    }

    /* Hàng hành động dưới cùng */
    .addrx-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 2px;
    }

    .addrx-card-actions {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .addrx-icon-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #475569;
        font-size: .78rem;
        font-weight: 600;
        border-radius: 7px;
        padding: 4px 9px;
        cursor: pointer;
        transition: all .15s ease;
    }
    .addrx-icon-btn:hover {
        border-color: var(--theme-primary, #0c4c29);
        color: var(--theme-primary, #0c4c29);
        background: rgba(12, 76, 41, .05);
    }
    .addrx-icon-btn.addrx-danger:hover {
        border-color: #dc2626;
        color: #dc2626;
        background: rgba(220, 38, 38, .05);
    }

    .addrx-default-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        border-radius: 7px;
        font-size: .78rem;
        font-weight: 700;
        padding: 4px 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .addrx-default-btn i { font-size: .85rem; }

    .addrx-default-btn:hover:not([disabled]) {
        border-color: var(--theme-primary, #0c4c29);
        color: var(--theme-primary, #0c4c29);
        background: rgba(12, 76, 41, 0.05);
    }

    .addrx-default-btn[disabled] {
        color: var(--theme-primary, #0c4c29);
        border-color: transparent;
        background: transparent;
        cursor: default;
        padding-left: 0;
    }

    .addrx-empty {
        padding: 28px 16px;
        font-size: .9rem;
        color: #6b7280;
        text-align: center;
        border: 1px dashed #e5e7eb;
        border-radius: 12px;
    }

    /* Mobile: hàng action xếp dọc gọn gàng */
    @media (max-width: 480px) {
        .addrx-card-foot {
            align-items: stretch;
            flex-direction: column;
        }
        .addrx-card-actions {
            justify-content: flex-end;
        }
        .addrx-icon-btn,
        .addrx-default-btn {
            justify-content: center;
        }
    }

    .addrx-suggest-list {
        margin-top: 4px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .08);
        max-height: 220px;
        overflow-y: auto;
        font-size: .85rem;
    }

    .addrx-map-wrap {
        position: relative;
    }
    .addrx-map {
        width: 100%;
        height: 220px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        overflow: hidden;
        background: #eef2f7;
    }
    .addrx-map .leaflet-container {
        height: 100%;
        width: 100%;
        border-radius: 10px;
    }
    .addrx-map-gps {
        position: absolute;
        right: 10px;
        bottom: 10px;
        z-index: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: none;
        border-radius: 999px;
        background: #16613a;
        color: #fff;
        font-size: .8rem;
        font-weight: 600;
        padding: 7px 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        cursor: pointer;
    }
    .addrx-map-gps:hover { background: #11502f; }
    .addrx-map-gps:disabled { opacity: .7; cursor: default; }
    
    .addrx-suggest-item {
        width: 100%;
        text-align: left;
        padding: 7px 12px;
        border: none;
        background: #fff;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        color: #374151;
        line-height: 1.4;
    }

    .addrx-suggest-item-main {
        font-weight: 500;
        font-size: .875rem;
        color: #111827;
    }

    .addrx-suggest-item-sub {
        font-size: .78rem;
        color: #6b7280;
    }

    .addrx-suggest-item:hover {
        background: #f0fdf4;
    }

    .addrx-suggest-item:hover .addrx-suggest-item-main {
        color: #0c4c29;
    }

    /* ── Security / Password (secv) ── */
    .secv-wrap {
        border: 1px solid #ececec;
        border-radius: 10px;
        background: #fff;
    }

    .secv-pane {
        display: none;
        padding: 22px 18px;
    }

    .secv-pane.active {
        display: block;
    }

    .secv-head {
        display: grid;
        justify-items: center;
        text-align: center;
        gap: 10px;
    }

    .secv-head svg {
        width: 74px;
        height: 74px;
    }

    .secv-intro {
        max-width: 560px;
        color: #4b5563;
        font-size: .92rem;
        margin: 0;
    }

    .secv-method {
        margin-top: 10px;
        display: flex;
        justify-content: center;
    }

    .secv-verify-card,
    .secv-change-card {
        max-width: 560px;
        margin: 0 auto;
        display: grid;
        gap: 14px;
    }

    .secv-verify-head {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .secv-back-btn {
        border: none;
        background: transparent;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #4b5563;
        cursor: pointer;
    }

    .secv-back-btn:hover {
        background: #f3f4f6;
    }

    .secv-title {
        font-size: 1rem;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }

    .secv-input-wrap {
        position: relative;
    }

    .secv-input {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 42px 10px 12px;
    }

    .secv-eye-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        color: #6b7280;
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        cursor: pointer;
    }

    .secv-submit-btn {
        border: none;
        border-radius: 8px;
        background: var(--theme-primary, #0c4c29);
        color: #fff;
        font-weight: 700;
        padding: 10px 14px;
        cursor: pointer;
    }

    .secv-submit-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .secv-field {
        display: grid;
        gap: 6px;
    }

    .secv-field label {
        font-weight: 700;
        color: #374151;
        font-size: .88rem;
    }

    .secv-actions {
        display: flex;
        justify-content: flex-start;
    }

    /* ── Bank modal ── */
    .bank-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .35);
        backdrop-filter: blur(2px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 12px;
    }

    .bank-modal-backdrop.show {
        display: flex;
    }

    .bank-modal {
        width: 100%;
        max-width: 720px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .16);
        overflow: hidden;
        display: grid;
        grid-template-rows: auto 1fr auto;
    }

    .bank-modal__head {
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .bank-modal__title {
        font-weight: 800;
        font-size: 1rem;
        color: #0f172a;
    }

    .bank-modal__close {
        border: none;
        background: transparent;
        font-size: 1.2rem;
        color: #64748b;
        cursor: pointer;
    }

    .bank-modal__body {
        padding: 16px 18px;
        display: grid;
        gap: 14px;
    }

    .bank-modal__footer {
        padding: 14px 18px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .bank-modal__section {
        display: grid;
        gap: 10px;
    }

    .bank-modal__hidden {
        display: none;
    }

    .bank-modal__hint {
        font-size: .82rem;
        color: #64748b;
    }

    .bank-steps {
        display: flex;
        gap: 10px;
        align-items: center;
        font-size: .85rem;
        font-weight: 700;
        color: #94a3b8;
    }

    .bank-step {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .bank-step__dot {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        border: 2px solid #e2e8f0;
        color: #94a3b8;
    }

    .bank-step.active .bank-step__dot {
        border-color: var(--theme-primary, #0c4c29);
        color: var(--theme-primary, #0c4c29);
    }

    .bank-step.active {
        color: #0f172a;
    }

    .branch-inline {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .branch-inline .form-control {
        flex: 1 1 240px;
    }

    /* ── Skeleton ── */
    .skeleton-line,
    .skeleton-block {
        position: relative;
        overflow: hidden;
        background: #e5e7eb;
    }

    .skeleton-line {
        border-radius: 999px;
        min-height: 10px;
    }

    .skeleton-block {
        border-radius: 8px;
    }

    .skeleton-line::after,
    .skeleton-block::after {
        content: "";
        position: absolute;
        inset: 0;
        transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .6), transparent);
        animation: skeletonSlide 1.2s infinite;
    }

    @keyframes skeletonSlide {
        to {
            transform: translateX(100%);
        }
    }

    /* Icon trong menu (ẩn ở desktop, hiện ở grid mobile) */
    .sublink-icon { display: none; }

    /* Badge số thông báo chưa đọc — nổi ở góc trên-phải mục menu (trên icon chuông) */
    .sidebar-menu [data-section] { position: relative; }
    .menu-notify-badge {
        position: absolute;
        top: 2px;
        right: 8px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ef4444;
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1;
        border-radius: 999px;
        box-shadow: 0 0 0 2px #fff;
        pointer-events: none;
        z-index: 2;
    }

    /* ── Mobile sidebar: LƯỚI THẺ ICON ── */
    @media(max-width:992px) {
        /* Container menu thành lưới thẻ */
        nav.nav.flex-column.sidebar-menu {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
        }

        /* Ẩn link cha "Tài khoản" — các mục con đã nằm thẳng trong lưới */
        .sidebar-link--parent { display: none !important; }

        /* Submenu: bỏ kiểu cuộn ngang, để các item chảy thẳng vào lưới cha */
        [data-submenu] {
            display: contents !important;
        }

        /* Thẻ icon dùng chung cho cả sublink và 2 link chính (Đơn Mua / Hỗ trợ) */
        .sidebar-sublink,
        .sidebar-link:not(.sidebar-link--parent) {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
            padding: 14px 6px;
            min-height: 78px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: .8rem;
            line-height: 1.2;
            white-space: normal;
            color: #334155;
            transition: all .15s ease;
        }

        /* Icon to, label nhỏ ở dưới */
        .sublink-icon,
        .sidebar-link-icon {
            display: block !important;
            margin: 0 !important;
            font-size: 1.5rem !important;
            color: var(--theme-primary, #0c4c29);
        }
        .sublink-label,
        .sidebar-link-label { display: block; }

        /* Trạng thái đang chọn */
        .sidebar-sublink.active,
        .sidebar-link:not(.sidebar-link--parent).active {
            color: #fff;
            background: var(--theme-primary, #0c4c29);
            border-color: var(--theme-primary, #0c4c29);
        }
        .sidebar-sublink.active .sublink-icon,
        .sidebar-link:not(.sidebar-link--parent).active .sidebar-link-icon {
            color: #fff;
        }

        .sidebar-sublink:active,
        .sidebar-link:not(.sidebar-link--parent):active {
            transform: scale(0.96);
        }
    }

    @media(max-width:380px) {
        nav.nav.flex-column.sidebar-menu {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Custom premium design system components */
    .btn {
        border-radius: 10px !important;
        transition: all 0.2s ease;
    }

    .form-control,
    .form-select {
        border-radius: 10px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--theme-primary, #0c4c29) !important;
        box-shadow: 0 0 0 3px rgba(12, 76, 41, 0.15) !important;
    }

    /* Form Grid */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .form-grid .full {
        grid-column: span 2;
    }

    @media(max-width:768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-grid .full {
            grid-column: span 1;
        }
    }

    /* Custom input focuses */
    .secv-input:focus {
        border-color: var(--theme-primary, #0c4c29) !important;
        outline: none;
        box-shadow: 0 0 0 3px rgba(12, 76, 41, 0.15) !important;
    }

    .ordx-search input:focus {
        border-color: var(--theme-primary, #0c4c29) !important;
        outline: none;
        box-shadow: 0 0 0 3px rgba(12, 76, 41, 0.12) !important;
    }

    /* ===== Modal Địa chỉ — tinh gọn cho mobile =====
       Trên màn nhỏ: biến modal thành full-height sheet, body cuộn được,
       header + footer (nút Lưu/Hủy) luôn dính, không bị che. */
    @media (max-width: 576px) {
        #addrxModal .modal-dialog {
            margin: 0;
            max-width: 100%;
            min-height: 100%;
            /* dùng 100dvh để né thanh địa chỉ trình duyệt mobile */
            height: 100dvh;
            display: flex;
            align-items: stretch;
        }
        #addrxModal .modal-content {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            border-radius: 0;
            border: 0;
        }
        #addrxModal .modal-header,
        #addrxModal .modal-footer {
            flex: 0 0 auto;
            padding: 12px 14px;
        }
        #addrxModal .modal-body {
            flex: 1 1 auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 12px 14px;
        }
        /* footer dính đáy, nền trắng để luôn nổi trên nội dung */
        #addrxModal .modal-footer {
            position: sticky;
            bottom: 0;
            background: #fff;
            box-shadow: 0 -4px 12px rgba(15, 23, 42, .06);
        }
        #addrxModal .modal-footer .btn {
            flex: 1 1 auto;
        }
        /* thu gọn khoảng cách + bản đồ để thấy nhiều trường hơn */
        #addrxModal .modal-body .mb-3 {
            margin-bottom: .75rem !important;
        }
        #addrxModal .addrx-map {
            height: 150px;
        }
        #addrxModal .addrx-input-desc {
            font-size: .72rem;
        }
    }
</style>

<div class="container py-4" style="max-width: 1200px;">
    <div class="row g-4">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle me-2 border shadow-sm overflow-hidden" style="width:48px;height:48px;" data-avatar-preview>
                    <?php if ($avatarUrl): ?>
                        <img src="<?= h($avatarUrl) ?>" alt="Avatar" width="48" height="48" class="w-100 h-100" loading="lazy" decoding="async" style="object-fit:cover;">
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light text-secondary fw-bold" style="font-size:1.1rem;">
                            <?= h($initials) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="fw-bold text-dark lh-sm" data-summary="display"><?= h($displayName) ?></div>
                    <div class="text-secondary text-opacity-75 small" data-summary="username">@<?= h($userData['username']) ?></div>
                </div>
            </div>

            <form class="d-none" data-ajax-form data-endpoint="/main/account/account-avatar.php" enctype="multipart/form-data">
                <input class="visually-hidden" id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" data-avatar-input>
            </form>

            <nav class="nav flex-column sidebar-menu">
                <a href="javascript:void(0)" class="sidebar-link sidebar-link--parent" data-toggle="submenu">
                    <i class="bi bi-person me-2 fs-5 sidebar-link-icon"></i> <span class="sidebar-link-label">Tài khoản</span>
                </a>
                <div class="mb-2 menu-sublist--open" data-submenu>
                    <a href="<?= h($baseUrl) ?>/account?tab=profile" class="sidebar-sublink" data-section="profile"><i class="bi bi-person-vcard sublink-icon"></i><span class="sublink-label">Hồ sơ</span></a>
                    <a href="<?= h($baseUrl) ?>/account?tab=bank" class="sidebar-sublink" data-section="bank"><i class="bi bi-bank sublink-icon"></i><span class="sublink-label">Ngân hàng</span></a>
                    <a href="<?= h($baseUrl) ?>/account?tab=address" class="sidebar-sublink" data-section="address"><i class="bi bi-geo-alt sublink-icon"></i><span class="sublink-label">Địa chỉ</span></a>
                    <a href="<?= h($baseUrl) ?>/account?tab=security" class="sidebar-sublink" data-section="security"><i class="bi bi-shield-lock sublink-icon"></i><span class="sublink-label">Đổi mật khẩu</span></a>
                    <a href="<?= h($baseUrl) ?>/account?tab=notifications" class="sidebar-sublink" data-section="notifications"><i class="bi bi-bell sublink-icon"></i><span class="sublink-label">Thông báo hệ thống</span></a>
                    <a href="<?= h($baseUrl) ?>/account?tab=promos" class="sidebar-sublink" data-section="promos"><i class="bi bi-gift sublink-icon"></i><span class="sublink-label">Khuyến mãi</span></a>
                    <?php if (!empty($isAdmin)): ?>
                        <a href="<?= h($baseUrl) ?>/account?tab=system_notifications" class="sidebar-sublink" data-section="system_notifications"><i class="bi bi-megaphone sublink-icon"></i><span class="sublink-label">Thông báo quản trị</span></a>
                    <?php endif; ?>
                </div>

                <a href="javascript:void(0)" class="sidebar-link" data-section="orders">
                    <i class="bi bi-bag me-2 fs-5 sidebar-link-icon"></i> <span class="sidebar-link-label">Đơn Mua</span>
                </a>
                <a href="javascript:void(0)" class="sidebar-link" data-section="support">
                    <i class="bi bi-life-preserver me-2 fs-5 sidebar-link-icon"></i> <span class="sidebar-link-label">Hỗ trợ<span class="d-none d-sm-inline"> (Ticket)</span></span>
                </a>
            </nav>
        </div>

        <div class="col-12 col-md-9 col-lg-9">
            <div class="account-content" role="main">
                <div class="account-section active" data-section="profile">
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-soft" role="alert"></div>
                            <form data-ajax-form data-endpoint="<?= $baseUrl ?>/main/account/account-profile.php">
                                <div class="row h-100">
                                    <div class="col-md-8 pe-md-5 order-1 order-md-0">
                                        <div class="row mb-3 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label">Tên đăng nhập</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" name="username" readonly value="<?= h($userData['username'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label">Họ và Tên</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" name="full_name" value="<?= h($userData['full_name'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label">Email</label>
                                            <div class="col-sm-9 d-flex align-items-center gap-3">
                                                <input type="email" class="form-control" name="email" data-edit-field="email" value="<?= h($userData['email'] ?? '') ?>" readonly>
                                                <a href="#" class="text-decoration-none text-primary flex-shrink-0 js-edit-btn" data-edit="email" style="font-size: 0.9rem;">Thay đổi</a>
                                            </div>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label">Số điện thoại</label>
                                            <div class="col-sm-9 d-flex align-items-center gap-3">
                                                <input type="text" class="form-control" name="phone" data-edit-field="phone" value="<?= h($userData['phone'] ?? '') ?>" readonly>
                                                <a href="#" class="text-decoration-none text-primary flex-shrink-0 js-edit-btn" data-edit="phone" style="font-size: 0.9rem;">Thay đổi</a>
                                            </div>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label d-flex align-items-center gap-1">
                                                Giới tính <i class="bi bi-question-circle text-secondary" style="font-size: 0.8rem;"></i>
                                            </label>
                                            <div class="col-sm-9 d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input border-secondary" type="radio" name="gender" id="genderMale" value="male" <?= ($userData['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                                    <label class="form-check-label text-dark" for="genderMale">Nam</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input border-secondary" type="radio" name="gender" id="genderFemale" value="female" <?= ($userData['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                                    <label class="form-check-label text-dark" for="genderFemale">Nữ</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input border-secondary" type="radio" name="gender" id="genderOther" value="other" <?= ($userData['gender'] ?? '') === 'other' ? 'checked' : '' ?>>
                                                    <label class="form-check-label text-dark" for="genderOther">Khác</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label d-flex align-items-center gap-1">
                                                Ngày sinh <i class="bi bi-question-circle text-secondary" style="font-size: 0.8rem;"></i>
                                            </label>
                                            <div class="col-sm-9 d-flex align-items-center gap-3">
                                                <input type="date" class="form-control" name="birthday" data-edit-field="birthday" value="<?= h($userData['birthday'] ?? '') ?>" readonly>
                                                <a href="#" class="text-decoration-none text-primary flex-shrink-0 js-edit-btn" data-edit="birthday" style="font-size: 0.9rem;">Thay đổi</a>
                                            </div>
                                        </div>

                                        <div class="row mb-4 align-items-center">
                                            <label class="col-sm-3 col-form-label form-label">Địa chỉ</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" name="address" value="<?= h($userData['address'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-sm-3"></div>
                                            <div class="col-sm-9">
                                                <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm fw-bold">Lưu thông tin</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 d-flex flex-column align-items-center justify-content-start pt-3 avatar-divider order-0 order-md-1 mb-4 mb-md-0">
                                        <div class="rounded-circle border overflow-hidden mb-4 shadow-sm" style="width:120px;height:120px;" data-avatar-trigger>
                                            <?php if ($avatarUrl): ?>
                                                <img src="<?= h($avatarUrl) ?>" alt="Avatar" width="120" height="120" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;" data-avatar-preview>
                                            <?php else: ?>
                                                <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light text-secondary fw-bold fs-3" data-avatar-preview>
                                                    <?= h($initials) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <button type="button" class="btn btn-outline-secondary bg-white text-dark shadow-sm px-4 py-2 mb-3 fw-medium border-secondary-subtle" data-avatar-trigger>
                                            Chọn ảnh
                                        </button>

                                        <div class="text-secondary text-center lh-sm" style="font-size: 0.85rem;">
                                            Dung lượng file tối đa 2 MB<br>
                                            Định dạng: .JPEG, .PNG, .WEBP
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="account-section" data-section="security">
                    <div class="account-header">
                        <h1 class="account-title">Đổi mật khẩu</h1>
                        <div class="account-subtitle">Đổi mật khẩu để bảo vệ tài khoản đăng nhập.</div>
                    </div>
                    <div class="alert alert-soft" role="alert" data-alert="security"></div>
                    <div class="secv-wrap" id="secvRoot">
                        <div class="secv-pane active" data-secv-step="guard">
                            <div class="secv-head">
                                <svg aria-hidden="true" viewBox="0 0 80 80" fill="none">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M11.348 54.153c-8.05-16.329-5.904-41.708-5.904-41.708 22.053.65 34.686-7.268 34.686-7.268v70.306c-13.094-5.7-20.277-11.185-20.277-11.185-5.076-3.943-8.505-10.145-8.505-10.145zM40.131 5.177s12.633 7.918 34.685 7.268c0 0 2.145 25.38-5.904 41.708 0 0-3.43 6.202-8.505 10.145 0 0-7.183 5.485-20.276 11.184V5.177z" fill="url(#secvG)"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M69.355 16.767c-18.593.548-29.245-6.127-29.245-6.127v59.277c11.04-4.806 17.097-9.43 17.097-9.43 4.279-3.325 7.17-8.554 7.17-8.554 6.787-13.768 4.978-35.166 4.978-35.166z" fill="var(--theme-primary-soft, #e6f0eb)"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.865 16.767s-1.809 21.398 4.978 35.166c0 0 2.891 5.23 7.17 8.554 0 0 6.057 4.624 17.097 9.43V10.64s-10.652 6.675-29.245 6.127z" fill="#fff"></path>
                                    <path d="M51.808 29.967a2.273 2.273 0 113.334 3.09l-14.85 16.02c-.4.43-1.077.444-1.493.028l-1.749-1.75a1.037 1.037 0 01-.027-1.437l14.785-15.951z" fill="var(--theme-primary, #0c4c29)"></path>
                                    <path d="M28.377 36.8a2.27 2.27 0 013.105-.098l9.48 8.35a2.27 2.27 0 11-3 3.406l-9.48-8.349a2.27 2.27 0 01-.105-3.309z" fill="var(--theme-primary, #0c4c29)"></path>
                                    <defs>
                                        <linearGradient id="secvG" x1="5.185" y1="5.177" x2="5.185" y2="75.483" gradientUnits="userSpaceOnUse">
                                            <stop stop-color="var(--theme-primary, #0c4c29)"></stop>
                                            <stop offset="1" stop-color="var(--theme-primary-soft, #e6f0eb)"></stop>
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <p class="secv-intro">Để tăng cường bảo mật cho tài khoản của bạn, hãy xác minh thông tin bằng một trong những cách sau.</p>
                            </div>
                            <div class="secv-method">
                                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" id="secvStartVerify">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    <span>Xác minh bằng Mật khẩu</span>
                                </button>
                            </div>
                        </div>

                        <div class="secv-pane" data-secv-step="verify">
                            <div class="secv-verify-card">
                                <div class="secv-verify-head">
                                    <button type="button" class="secv-back-btn" id="secvBackGuard" aria-label="Quay lại">←</button>
                                    <h3 class="secv-title">Nhập mật khẩu hiện tại</h3>

                                </div>
                                <form id="secvVerifyForm">
                                    <label class="form-label" for="secvCurrentPassword">(Với tài khoản đăng nhập bằng OTP thì mật khẩu mặc định sẽ là: 12345)</label>
                                    <div class="secv-input-wrap">
                                        <input class="secv-input form-control pe-5" type="password" id="secvCurrentPassword" name="password" placeholder="Nhập mật khẩu hiện tại để xác minh" autocomplete="current-password" maxlength="64">
                                        <button type="button" class="secv-eye-btn" data-secv-toggle="#secvCurrentPassword" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="secv-actions mt-3">
                                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" id="secvVerifySubmit" disabled>XÁC NHẬN</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="secv-pane" data-secv-step="change">
                            <div class="secv-change-card">
                                <div class="secv-change-head">
                                    <h2>Đổi mật khẩu</h2>
                                    <p>Để bảo mật tài khoản, vui lòng không chia sẻ mật khẩu cho người khác</p>
                                </div>
                                <form id="secvChangeForm">
                                    <div class="secv-field">
                                        <label class="form-label fw-bold" for="secvNewPassword">Mật khẩu mới</label>
                                        <div class="secv-input-wrap">
                                            <input class="secv-input form-control pe-5" type="password" id="secvNewPassword" name="newPassword" autocomplete="off" maxlength="64">
                                            <button type="button" class="secv-eye-btn" data-secv-toggle="#secvNewPassword" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="secv-field mt-2">
                                        <label class="form-label fw-bold" for="secvNewPasswordRepeat">Xác nhận mật khẩu</label>
                                        <div class="secv-input-wrap">
                                            <input class="secv-input form-control pe-5" type="password" id="secvNewPasswordRepeat" name="newPasswordRepeat" autocomplete="off" maxlength="64">
                                            <button type="button" class="secv-eye-btn" data-secv-toggle="#secvNewPasswordRepeat" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="secv-actions mt-3">
                                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" id="secvChangeSubmit" disabled>XÁC NHẬN</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="account-section" data-section="address">
                    <div class="account-header">
                        <h1 class="account-title">Địa chỉ của tôi</h1>
                        <div class="account-subtitle">Quản lý địa chỉ nhận hàng của bạn.</div>
                    </div>
                    <div class="alert alert-soft" role="alert" data-alert="shipping"></div>
                    <div class="addrx-shell">
                        <div class="addrx-topbar">
                            <div>
                                <div class="addrx-title">Địa chỉ</div>
                                <div class="addrx-meta" id="addrxCountHint">Tối đa 5 địa chỉ</div>
                            </div>
                            <button type="button" class="addrx-add-btn" id="addrxAddNewBtn"><i class="bi bi-plus-lg"></i>Thêm địa chỉ mới</button>
                        </div>

                        <div id="locSavedAddresses" class="addrx-list"></div>

                    </div>
                </div>

                <div class="account-section" data-section="bank">
                    <div class="account-header">
                        <h1 class="account-title">Ngân hàng / Ví</h1>
                        <div class="account-subtitle">Liên kết tài khoản ngân hàng và ví điện tử để nhận tiền.</div>
                    </div>
                    <div class="bacc-shell">
                        <div class="bacc-section">
                            <div class="bacc-header">
                                <div>
                                    <div class="bacc-title">Thẻ tín dụng / ghi nợ</div>
                                    <div class="bacc-subtitle"></div>
                                </div>
                                <button class="bacc-btn" type="button" id="btnAddCard">
                                    <svg viewBox="0 0 10 10" aria-hidden="true">
                                        <polygon points="10 4.5 5.5 4.5 5.5 0 4.5 0 4.5 4.5 0 4.5 0 5.5 4.5 5.5 4.5 10 5.5 10 5.5 5.5 10 5.5"></polygon>
                                    </svg>
                                    <span>Thêm thẻ mới</span>
                                </button>
                            </div>

                            <div class="bacc-form hidden" id="cardFormWrap">
                                <div class="form-grid">
                                    <div class="full">
                                        <label class="form-label">Tên chủ thẻ</label>
                                        <input class="form-control" id="cardName" placeholder="VD: NGUYỄN VĂN A">
                                    </div>
                                    <div class="full">
                                        <label class="form-label">Số thẻ</label>
                                        <input class="form-control" id="cardNumber" placeholder="XXXX XXXX XXXX XXXX" maxlength="19">
                                    </div>
                                    <div>
                                        <label class="form-label">Ngày hết hạn</label>
                                        <input class="form-control" id="cardExp" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div>
                                        <label class="form-label">CVV</label>
                                        <input class="form-control" id="cardCvv" placeholder="***" maxlength="4" type="password">
                                    </div>
                                </div>
                                <div class="form-check mt-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="cardDefault">
                                    <label class="form-check-label text-dark fw-bold" for="cardDefault">Đặt làm mặc định</label>
                                </div>
                                <div class="text-secondary small mb-3">CVV chỉ dùng để token hóa qua cổng thanh toán. Không lưu trên server.</div>
                                <div class="bacc-form-actions">
                                    <button class="btn btn-primary" id="btnSaveCard" type="button">Lưu thẻ</button>
                                    <button class="btn btn-outline-secondary" id="btnCancelCard" type="button">Hủy</button>
                                </div>
                            </div>

                            <div class="bacc-list" id="cardList">
                                <div class="bank-empty">Chưa có thẻ nào.</div>
                            </div>
                        </div>

                        <div class="bacc-section">
                            <div class="bacc-header">
                                <div>
                                    <div class="bacc-title">Tài khoản ngân hàng của tôi</div>
                                    <div class="bacc-subtitle"></div>
                                </div>
                                <button class="bacc-btn" type="button" id="btnAddBank">
                                    <svg viewBox="0 0 10 10" aria-hidden="true">
                                        <polygon points="10 4.5 5.5 4.5 5.5 0 4.5 0 4.5 4.5 0 4.5 0 5.5 4.5 5.5 4.5 10 5.5 10 5.5 5.5 10 5.5"></polygon>
                                    </svg>
                                    <span>Thêm ngân hàng liên kết</span>
                                </button>
                            </div>
                            <div class="bacc-form hidden" id="bankFormWrap">
                                <div class="text-muted">Form thêm ngân hàng đã chuyển sang modal hai bước. Nhấn "Thêm ngân hàng liên kết" để bắt đầu.</div>
                            </div>

                            <div class="bacc-list" id="bankList">
                                <div class="bank-empty">Chưa có tài khoản nào.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="account-section" data-section="orders">
                    <div class="ordx-shell" id="ordxRoot">
                        <article class="ordx-main-card">
                            <section class="ordx-tablist" role="tablist" aria-label="Bộ lọc trạng thái đơn mua">
                                <button type="button" class="ordx-tab active" data-ordx-status="all">Tất cả</button>
                                <button type="button" class="ordx-tab" data-ordx-status="pending">Chờ thanh toán</button>
                                <button type="button" class="ordx-tab" data-ordx-status="processing">Vận chuyển</button>
                                <button type="button" class="ordx-tab" data-ordx-status="shipping">Chờ giao hàng</button>
                                <button type="button" class="ordx-tab" data-ordx-status="delivered">Hoàn thành</button>
                                <button type="button" class="ordx-tab" data-ordx-status="canceled">Đã hủy</button>
                                <button type="button" class="ordx-tab" data-ordx-status="refund">Trả hàng/Hoàn tiền</button>
                            </section>
                        </article>

                        <article class="ordx-main-card">
                            <div class="ordx-main-card-body">
                                <section class="ordx-search">
                                    <i class="bi bi-search"></i>
                                    <input type="search" id="ordxSearchInput" placeholder="Bạn có thể tìm kiếm theo tên Shop, ID đơn hàng hoặc Tên Sản phẩm" autocomplete="off">
                                </section>

                                <div class="ordx-meta" id="ordxMeta">Đang tải đơn hàng...</div>
                                <main id="ordxList" class="ordx-list" aria-live="polite"></main>
                                <div id="ordxEmpty" class="ordx-empty d-none">Không có đơn hàng phù hợp.</div>
                                <button type="button" class="ordx-loadmore" id="ordxLoadMoreBtn">Xem thêm</button>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="account-section" data-section="notifications">
                    <div class="account-header account-header--notify">
                        <div>
                            <h1 class="account-title">Thông báo hệ thống</h1>
                            <div class="account-subtitle" id="notificationsMeta">Đang tải...</div>
                        </div>
                        <button type="button" class="bacc-btn" id="notifyBtnMarkAll">Đánh dấu đã đọc tất cả</button>
                    </div>
                    <div class="notify-section">
                        <div class="notify-tabs ordx-tablist" role="tablist">
                            <button type="button" class="ordx-tab active" data-notify-filter="all">Tất cả</button>
                            <button type="button" class="ordx-tab" data-notify-filter="unread">Chưa đọc</button>
                            <button type="button" class="ordx-tab" data-notify-filter="order">Đơn hàng</button>
                        </div>
                        <div id="notificationsList" class="notify-list mt-2"></div>
                        <div class="notify-pagination">
                            <button type="button" class="notify-page-btn" id="notificationsPrevBtn" disabled>&lt;</button>
                            <span class="notify-page-info" id="notificationsPageInfo">1/1</span>
                            <button type="button" class="notify-page-btn" id="notificationsNextBtn" disabled>&gt;</button>
                        </div>
                    </div>
                </div>

                <div class="account-section" data-section="promos">
                    <div class="account-header account-header--notify">
                        <div>
                            <h1 class="account-title">Thông báo khuyến mãi</h1>
                            <div class="account-subtitle" id="promosMeta">Đang tải...</div>
                        </div>
                        <button type="button" class="bacc-btn" id="promosBtnMarkAll">Đánh dấu đã đọc tất cả</button>
                    </div>
                    <div class="notify-section">
                        <div class="notify-tabs ordx-tablist" role="tablist">
                            <button type="button" class="ordx-tab active" data-promo-filter="all">Tất cả</button>
                            <button type="button" class="ordx-tab" data-promo-filter="unread">Chưa đọc</button>
                        </div>
                        <div id="promosList" class="notify-list mt-4"></div>
                        <div class="notify-pagination">
                            <button type="button" class="notify-page-btn" id="promosPrevBtn" disabled>&lt;</button>
                            <span class="notify-page-info" id="promosPageInfo">1/1</span>
                            <button type="button" class="notify-page-btn" id="promosNextBtn" disabled>&gt;</button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($isAdmin)): ?>
                    <div class="account-section" data-section="system_notifications">
                        <div class="account-header account-header--notify">
                            <div>
                                <h1 class="account-title">Thông báo hệ thống</h1>
                                <div class="account-subtitle" id="systemNotificationsMeta">Bình luận thông báo, đánh giá sản phẩm, đánh giá đơn hàng</div>
                            </div>
                        </div>

                        <div class="notify-section">
                            <div class="notify-tabs ordx-tablist" role="tablist" aria-label="Bộ lọc loại thông báo hệ thống">
                                <button type="button" class="ordx-tab active" data-system-filter="all">Tất cả</button>
                                <button type="button" class="ordx-tab" data-system-filter="product_review">Đánh giá sản phẩm</button>
                                <button type="button" class="ordx-tab" data-system-filter="order_review">Đánh giá đơn hàng</button>
                                <button type="button" class="ordx-tab" data-system-filter="comment">Bình luận</button>
                            </div>
                            <div id="systemNotificationsList" class="notify-list mt-2" aria-live="polite">
                                <div class="bank-empty">Đang tải thông báo...</div>
                            </div>
                            <div class="notify-pagination" id="systemNotificationsPager">
                                <button type="button" class="notify-page-btn" id="systemNotificationsPrevBtn" disabled>&lt;</button>
                                <span class="notify-page-info" id="systemNotificationsPageInfo">1/1</span>
                                <button type="button" class="notify-page-btn" id="systemNotificationsNextBtn" disabled>&gt;</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="account-section" data-section="activity">
                    <div class="account-header">
                        <h1 class="account-title">Nhật ký hoạt động</h1>
                        <div class="account-subtitle">Lịch sử các hoạt động quan trọng liên quan đến tài khoản</div>
                    </div>
                    <div class="bacc-section">
                        <div id="activityList" class="bacc-list">
                            <div class="bank-empty">Chức năng nhật ký chưa khởi tạo.</div>
                        </div>
                    </div>
                </div>

                <div class="account-section" data-section="support">
                    <style>
                        .ticket-card {
                            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                        }
                        .ticket-card:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05) !important;
                            border-color: #0c4c29 !important;
                        }
                        .ticket-card:hover .ticket-chevron {
                            color: #0c4c29 !important;
                            transform: translateX(3px);
                        }
                        .ticket-chevron {
                            transition: all 0.2s ease;
                        }
                        @media (max-width: 576px) {
                            .ticket-card {
                                flex-direction: column;
                                align-items: flex-start !important;
                                gap: 12px;
                            }
                            .ticket-card-right {
                                width: 100%;
                                display: flex;
                                justify-content-between;
                                align-items: center;
                                border-top: 1px solid #f1f5f9;
                                padding-top: 10px;
                            }
                        }
                    </style>
                    <div class="account-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1 class="account-title">Hỗ trợ / Ticket</h1>
                            <div class="account-subtitle">Các yêu cầu hỗ trợ và ticket của bạn</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= h($baseUrl) ?>/faq" class="btn btn-sm btn-outline-secondary"><i class="bi bi-patch-question me-1"></i>FAQ</a>
                            <a href="<?= h($baseUrl) ?>/support" class="btn btn-sm" style="background:#0c4c29;color:#fff;"><i class="bi bi-plus-lg me-1"></i>Tạo yêu cầu</a>
                        </div>
                    </div>
                    <div class="bacc-section">
                        <div id="supportList" class="bacc-list">
                            <div class="bank-empty">Đang tải yêu cầu hỗ trợ...</div>
                        </div>
                        <div class="text-center mt-3">
                            <a href="<?= h($baseUrl) ?>/support" class="btn text-decoration-none px-4" style="background: rgba(12, 76, 41, 0.08) !important; color: #0c4c29 !important; border: 1px solid rgba(12, 76, 41, 0.2) !important; font-weight: 600 !important; border-radius: 30px !important; width: auto !important; height: auto !important; padding: 8px 24px !important; display: inline-flex !important; align-items: center !important;">Xem tất cả yêu cầu <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    var box = document.getElementById('supportList');
                    if (!box) return;
                    function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
                    var catMap = {
                        'order': 'Sự cố đơn hàng',
                        'payment': 'Lỗi thanh toán',
                        'faq': 'Câu hỏi thường gặp',
                        'chat': 'Chat trực tuyến',
                        'other': 'Khác'
                    };
                    var iconMap = {
                        'order': 'bi-box-seam',
                        'payment': 'bi-credit-card',
                        'faq': 'bi-question-circle',
                        'chat': 'bi-chat-dots',
                        'other': 'bi-ticket-detailed'
                    };
                    var catColorMap = {
                        'order': ['#0c4c29', 'rgba(12, 76, 41, 0.08)'],
                        'payment': ['#2563eb', 'rgba(37, 99, 235, 0.08)'],
                        'faq': ['#7c3aed', 'rgba(124, 58, 237, 0.08)'],
                        'chat': ['#0891b2', 'rgba(8, 145, 178, 0.08)'],
                        'other': ['#64748b', 'rgba(100, 116, 139, 0.08)']
                    };
                    var prioMap = {
                        'high': ['#ef4444', 'rgba(239, 68, 68, 0.08)', 'Cao'],
                        'normal': ['#2563eb', 'rgba(37, 99, 235, 0.08)', 'Thường'],
                        'low': ['#64748b', 'rgba(100, 116, 139, 0.08)', 'Thấp']
                    };
                    var statMap = {
                        open: ['#2563eb', 'rgba(37, 99, 235, 0.08)', 'Đang mở'],
                        pending: ['#b45309', 'rgba(180, 83, 9, 0.08)', 'Chờ phản hồi'],
                        resolved: ['#15803d', 'rgba(21, 128, 61, 0.08)', 'Đã xử lý'],
                        closed: ['#64748b', 'rgba(100, 116, 139, 0.08)', 'Đã đóng']
                    };

                    fetch('<?= h($baseUrl) ?>/core_user/support/ajax/ticket.php?action=my_tickets', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                      .then(function (r) { return r.json(); })
                      .then(function (d) {
                          if (!d || !d.ok) { box.innerHTML = '<div class="bank-empty">Không tải được dữ liệu.</div>'; return; }
                          if (!d.tickets || !d.tickets.length) { box.innerHTML = '<div class="bank-empty">Bạn chưa có yêu cầu hỗ trợ nào.</div>'; return; }
                          box.innerHTML = d.tickets.map(function (t) {
                              var cKey = t.category || 'other';
                              var catName = catMap[cKey] || 'Khác';
                              var icon = iconMap[cKey] || 'bi-ticket-detailed';
                              var catColor = catColorMap[cKey] || ['#64748b', 'rgba(100, 116, 139, 0.08)'];
                              
                              var pKey = t.priority || 'normal';
                              var prio = prioMap[pKey] || ['#2563eb', 'rgba(37, 99, 235, 0.08)', 'Thường'];
                              
                              var s = statMap[t.status] || ['#64748b', 'rgba(100, 116, 139, 0.08)', t.status];

                              return '<a href="<?= h($baseUrl) ?>/support-detail?code=' + encodeURIComponent(t.code) + '" class="ticket-card d-flex align-items-center justify-content-between p-3 border rounded-3 mb-2 text-decoration-none text-dark bg-white shadow-sm">' +
                                  '  <div class="d-flex align-items-center gap-3">' +
                                  '    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background:' + catColor[1] + '; color:' + catColor[0] + ';">' +
                                  '      <i class="bi ' + icon + ' fs-5"></i>' +
                                  '    </div>' +
                                  '    <div>' +
                                  '      <div class="d-flex align-items-center gap-2 flex-wrap mb-1">' +
                                  '        <span class="fw-bold text-dark" style="font-size: 14.4px;">' + esc(t.subject) + '</span>' +
                                  '        <span class="badge bg-light text-secondary border fw-normal" style="font-size: 11.5px;">' + esc(t.code) + '</span>' +
                                  '        <span class="badge" style="background:' + prio[1] + '; color:' + prio[0] + '; font-size: 11.5px; font-weight: 500;">Ưu tiên: ' + esc(prio[2]) + '</span>' +
                                  '      </div>' +
                                  '      <div class="small text-muted d-flex align-items-center gap-2 flex-wrap" style="font-size: 12px;">' +
                                  '        <span>Danh mục: ' + esc(catName) + '</span>' +
                                  '        <span class="d-none d-sm-inline">•</span>' +
                                  '        <span>Cập nhật: ' + esc(t.updated) + '</span>' +
                                  '      </div>' +
                                  '    </div>' +
                                  '  </div>' +
                                  '  <div class="ticket-card-right d-flex align-items-center gap-2 flex-shrink-0">' +
                                  '    <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background:' + s[1] + '; color:' + s[0] + '; font-size: 11.5px;">' + esc(s[2]) + '</span>' +
                                  '    <i class="bi bi-chevron-right text-secondary fs-5 ticket-chevron"></i>' +
                                  '  </div>' +
                                  '</a>';
                          }).join('');
                      })
                      .catch(function () { box.innerHTML = '<div class="bank-empty">Không tải được dữ liệu.</div>'; });
                })();
                </script>
            </div>
        </div>
    </div>
</div>

<!-- Modal Địa chỉ giao hàng - Bootstrap 5.3 -->
<div class="modal fade" id="addrxModal" tabindex="-1" aria-labelledby="addrxModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold" id="addrxModalLabel">Địa chỉ mới</h5>
                <button type="button" class="btn-close" id="addrxModalClose" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="regionLocationForm">
                <div class="modal-body">
                    <input type="hidden" id="locAddressId" value="<?= h($selectedAddressId) ?>">

                    <div class="mb-3">
                        <label class="addrx-modal-label">Họ và tên &amp; Số điện thoại</label>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <input type="text" class="form-control" id="locRecipientName" name="recipient_name" value="<?= h($selectedRecipientName) ?>" placeholder="Họ và tên">
                            </div>
                            <div class="col-12 col-md-6">
                                <input type="text" class="form-control" id="locContactPhone" name="contact_phone" value="<?= h($selectedContactPhone) ?>" placeholder="Số điện thoại">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="addrx-modal-label" for="locStreet">Địa chỉ cụ thể</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="locStreet" name="street" value="<?= h($selectedStreet) ?>" placeholder="VD: 123 Lê Lợi" autocomplete="off">
                            <button class="btn btn-outline-secondary me-2" type="button" id="locGpsBtn" title="Dùng vị trí hiện tại"><i class="bi bi-geo-alt"></i></button>
                            <!-- <button class="btn btn-outline-primary" type="button" id="locAiStreetBtn"><i class="bi bi-stars me-1"></i>AI gợi ý</button>-->
                        </div> 
                        <div id="locStreetSuggestList" class="addrx-suggest-list d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label class="addrx-modal-label">Khu vực</label>
                        <div class="row g-2">
                            <div class="col-6 col-md-4">
                                <select class="form-select" id="locProvinceSel" data-selected-name="<?= h($selectedProvince) ?>" data-selected-id="<?= (int)$selectedProvinceId ?>">
                                    <option value="">Tỉnh / Thành phố</option>
                                </select>
                                <!-- <div class="addrx-input-desc">Chọn tỉnh/thành phố.</div> -->
                            </div>
                            <div class="col-6 col-md-4">
                                <select class="form-select" id="locDistrictSel" data-selected-name="<?= h($selectedDistrict) ?>" data-selected-id="<?= (int)$selectedDistrictId ?>">
                                    <option value="">Quận / Huyện</option>
                                </select>
                                <!-- <div class="addrx-input-desc">Chọn quận/huyện tương ứng.</div> -->
                            </div>
                            <div class="col-12 col-md-4">
                                <select class="form-select" id="locWardSel" data-selected-name="<?= h($selectedWard) ?>" data-selected-code="<?= h($selectedWardCode) ?>">
                                    <option value="">Phường / Xã</option>
                                </select>
                                <!-- <div class="addrx-input-desc">Chọn phường/xã nhận hàng.</div> -->
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="addrx-modal-label">Ghim vị trí trên bản đồ</label>
                        <div class="addrx-map-wrap">
                            <div id="locMap" class="addrx-map"></div>
                            <button type="button" id="locMapGpsBtn" class="addrx-map-gps" title="Định vị tôi">
                                <i class="bi bi-crosshair"></i> Định vị tôi
                            </button>
                        </div>
                        <div class="addrx-input-desc">Kéo ghim hoặc chạm vào bản đồ để chọn đúng vị trí giao hàng.</div>
                        <input type="hidden" id="locLat" name="customer_lat" value="">
                        <input type="hidden" id="locLng" name="customer_lng" value="">
                    </div>

                    <div class="mb-3">
                        <label class="addrx-modal-label">Loại địa chỉ</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="address_type" id="locAddressTypeHome" value="home" <?= $selectedAddressType === 'home' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="locAddressTypeHome">Nhà riêng</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="address_type" id="locAddressTypeOffice" value="office" <?= $selectedAddressType === 'office' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="locAddressTypeOffice">Văn phòng</label>
                            </div>
                        </div>
                    </div>

                    <textarea class="form-control d-none" id="locDeliveryNote" name="delivery_note" rows="2"><?= h($selectedDeliveryNote) ?></textarea>
                    <div id="locDistanceHint" class="addrx-note d-none"></div>
                </div>
                <div class="modal-footer border-top justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="locSaveBtn">Hoàn thành</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="bank-modal-backdrop" id="bankModal">
    <div class="bank-modal" role="dialog" aria-modal="true">
        <div class="bank-modal__head">
            <div class="bank-modal__title">Liên kết tài khoản ngân hàng</div>
            <button class="bank-modal__close" type="button" aria-label="Đóng" data-bank-modal-close>&times;</button>
        </div>
        <div class="bank-modal__body">
            <div class="bank-steps">
                <div class="bank-step" data-bank-step-label="1">
                    <span class="bank-step__dot">1</span>
                    <span>Xác thực</span>
                </div>
                <div class="bank-step" data-bank-step-label="2">
                    <span class="bank-step__dot">2</span>
                    <span>Thông tin ngân hàng</span>
                </div>
            </div>

            <div class="bank-modal__section" data-bank-step="1">
                <div>
                    <label class="form-label" for="bankStepFullName">Họ và tên</label>
                    <input class="form-control" id="bankStepFullName" placeholder="VD: NGUYỄN VĂN A">
                </div>
                <div>
                    <label class="form-label" for="bankStepCccd">CCCD/CMND</label>
                    <input class="form-control" id="bankStepCccd" placeholder="Nhập số CCCD/CMND">
                </div>
                <div class="bank-modal__hint">Bước này giúp xác thực danh tính trước khi liên kết ngân hàng.</div>
            </div>

            <div class="bank-modal__section bank-modal__hidden" data-bank-step="2">
                <div class="bank-support__status bank-status" id="bankStatus">Đang tải danh sách ngân hàng...</div>
                <div>
                    <label class="form-label" for="bankStepBankSelect">Chọn ngân hàng</label>
                    <select class="form-select" id="bankStepBankSelect"></select>
                </div>
                <div>
                    <label class="form-label" for="bankStepBranch">Chi nhánh</label>
                    <div class="branch-inline">
                        <input class="form-control" id="bankStepBranch" placeholder="Nhập tên chi nhánh (VD: Quận 1)">
                        <datalist id="branchSuggestions"></datalist>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="bankStepAccount">Số tài khoản</label>
                    <input class="form-control" id="bankStepAccount" placeholder="VD: 0123456789">
                </div>
                <div>
                    <label class="form-label" for="bankStepOwner">Tên đầy đủ chủ tài khoản</label>
                    <input class="form-control" id="bankStepOwner" placeholder="VD: NGUYỄN VĂN A">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="bankStepDefault">
                    <label class="form-check-label" for="bankStepDefault">Đặt làm mặc định</label>
                </div>
            </div>
        </div>
        <div class="bank-modal__footer">
            <button class="btn btn-outline-secondary" type="button" data-bank-prev>Bước trước</button>
            <button class="btn btn-primary" type="button" data-bank-next>Tiếp tục</button>
            <button class="btn btn-primary bank-modal__hidden" type="button" data-bank-save>Lưu ngân hàng</button>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-toggle-password]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.closest('.input-group').querySelector('[data-password-field]');
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
            } else {
                btn.textContent = isHidden ? 'Ẩn' : 'Hiện';
            }
        });
    });

    function initAccountSections() {
        const sections = Array.from(document.querySelectorAll('.account-section'));
        const menuItems = Array.from(document.querySelectorAll('.sidebar-menu [data-section]'));
        const accountContent = document.querySelector('.account-content');
        const submenuToggle = document.querySelector('[data-toggle="submenu"]');
        const submenu = document.querySelector('[data-submenu]');
        const dropdown = submenu?.closest('[data-dropdown]') || null;
        const accountSections = ['profile', 'security', 'address', 'bank', 'orders', 'notifications', 'promos', 'system_notifications', 'activity', 'support'];

        function setActiveMenu(key) {
            menuItems.forEach(item => {
                const section = item.getAttribute('data-section');
                item.classList.toggle('active', section === key);
            });

            const isAccountSub = ['profile', 'security', 'address', 'bank', 'notifications', 'promos', 'system_notifications'].includes(key);
            if (submenuToggle) {
                submenuToggle.classList.toggle('active', isAccountSub);
            }

            if (submenu) {
                const shouldOpen = (window.innerWidth > 992 && accountSections.includes(key)) || isAccountSub;
                submenu.classList.toggle('menu-sublist--open', shouldOpen);
                if (dropdown) {
                    dropdown.classList.toggle('menu-group--open', shouldOpen);
                }
            }
        }

        function showSection(key) {
            const target = sections.find(sec => sec.getAttribute('data-section') === key) || sections[0];
            sections.forEach(sec => sec.classList.toggle('active', sec === target));
            const currentKey = target?.getAttribute('data-section') || 'profile';
            setActiveMenu(currentKey);
            if (accountContent) {
                accountContent.classList.toggle('account-content--split-orders', currentKey === 'orders');
            }

            // Lazy init các panel nặng (chỉ khởi tạo 1 lần khi người dùng mở tab tương ứng)
            if (currentKey === 'bank' && typeof initBankPanel === 'function') {
                initBankPanel();
            } else if (currentKey === 'orders' && typeof initOrdersPanel === 'function') {
                initOrdersPanel();
            } else if (currentKey === 'address' && typeof initAddressPanel === 'function') {
                initAddressPanel();
            } else if (currentKey === 'notifications' && typeof initNotificationsPanel === 'function') {
                initNotificationsPanel();
            } else if (currentKey === 'promos' && typeof initPromosPanel === 'function') {
                initPromosPanel();
            } else if (currentKey === 'system_notifications' && typeof initSystemNotificationsPanel === 'function') {
                initSystemNotificationsPanel();
            }
        }

        menuItems.forEach(item => {
            item.addEventListener('click', (event) => {
                if (item.tagName.toLowerCase() === 'a') {
                    event.preventDefault();
                }
                const key = item.getAttribute('data-section');
                if (!key) return;
                showSection(key);
            });
        });

        if (submenuToggle && submenu) {
            submenuToggle.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    const isOpen = submenu.classList.contains('menu-sublist--open');
                    if (isOpen) {
                        submenu.classList.remove('menu-sublist--open');
                        if (dropdown) dropdown.classList.remove('menu-group--open');
                    } else {
                        submenu.classList.add('menu-sublist--open');
                        if (dropdown) dropdown.classList.add('menu-group--open');
                        const hasActiveSub = submenu.querySelector('.sidebar-sublink.active');
                        if (!hasActiveSub) {
                            showSection('profile');
                        }
                    }
                } else {
                    submenu.classList.toggle('menu-sublist--open');
                    if (dropdown) dropdown.classList.toggle('menu-group--open');
                }
            });
        }

        const tabParam = new URLSearchParams(window.location.search).get('tab');
        if (tabParam) {
            const map = {
                profile: 'profile',
                security: 'security',
                address: 'address',
                bank: 'bank',
                orders: 'orders',
                notifications: 'notifications',
                promos: 'promos',
                'system-notifications': 'system_notifications',
                activity: 'activity',
                support: 'support'
            };
            showSection(map[tabParam] || 'profile');
            return;
        }
        showSection('profile');
    }

    let bankPanelInited = false;

    function initBankPanel() {
        if (bankPanelInited) return;
        bankPanelInited = true;

        const bankApiUrl = '<?= h($baseUrl) ?>/main/account/account-bank.php';
        const DEFAULT_BANK_LOGO = '<?= h($baseUrl) ?>/image/bank/default.png';

        const notify = (msg, type = 'info') => {
            const text = String(msg || '').trim();
            if (!text) return;
            if (window.toastr && typeof toastr[type] === 'function') {
                toastr[type](text);
                return;
            }
            alert(text);
        };

        const cardList = document.getElementById('cardList');
        const bankList = document.getElementById('bankList');
        if (!cardList || !bankList) return;

        const btnAddCard = document.getElementById('btnAddCard');
        const btnCancelCard = document.getElementById('btnCancelCard');
        const btnSaveCard = document.getElementById('btnSaveCard');
        const btnAddBank = document.getElementById('btnAddBank');
        const cardFormWrap = document.getElementById('cardFormWrap');
        const cardDefault = document.getElementById('cardDefault');

        const bankModal = document.getElementById('bankModal');
        const bankCloseBtn = bankModal ? bankModal.querySelector('[data-bank-modal-close]') : null;
        const bankBtnPrev = bankModal ? bankModal.querySelector('[data-bank-prev]') : null;
        const bankBtnNext = bankModal ? bankModal.querySelector('[data-bank-next]') : null;
        const bankBtnSave = bankModal ? bankModal.querySelector('[data-bank-save]') : null;

        const bankStatus = document.getElementById('bankStatus');
        const bankSelect = document.getElementById('bankStepBankSelect');
        const bankBranchInput = document.getElementById('bankStepBranch');
        const bankAccountInput = document.getElementById('bankStepAccount');
        const bankOwnerInput = document.getElementById('bankStepOwner');
        const bankDefaultToggle = document.getElementById('bankStepDefault');
        const bankNameStep = document.getElementById('bankStepFullName');
        const bankCccdStep = document.getElementById('bankStepCccd');
        const branchSuggestions = document.getElementById('branchSuggestions');

        let selectedBankCode = '';
        let savedAccounts = [];
        let editingCardId = 0;
        let editingBankId = 0;


        const bankCardSkeletonHtml = [1, 2].map(() => `
        <div class="bacc-card" aria-hidden="true">
            <div class="bacc-card-logo"><div class="skeleton-block" style="width:36px;height:24px;border-radius:6px;"></div></div>
            <div class="bacc-card-info" style="flex:1 1 auto;">
                <div class="skeleton-line skeleton-line--md" style="max-width:160px"></div>
                <div class="skeleton-line skeleton-line--sm" style="max-width:120px"></div>
            </div>
            <div class="skeleton-line skeleton-line--sm" style="max-width:90px"></div>
        </div>
    `).join('');

        const bankAccountSkeletonHtml = [1, 2].map(() => `
        <div class="bacc-bank" aria-hidden="true">
            <div class="bacc-bank-logo"><div class="skeleton-block" style="width:40px;height:40px;border-radius:10px;"></div></div>
            <div class="bacc-bank-main" style="flex:1 1 auto;">
                <div class="skeleton-line skeleton-line--md" style="max-width:220px"></div>
                <div class="skeleton-line skeleton-line--sm" style="max-width:180px"></div>
            </div>
            <div class="skeleton-line skeleton-line--sm" style="max-width:90px"></div>
        </div>
    `).join('');

        function toggleForm(el, show) {
            if (!el) return;
            el.classList.toggle('hidden', !show);
        }

        function ensureBankSelectPlaceholder() {
            if (!bankSelect) return;
            if (bankSelect.options.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Chọn ngân hàng';
                bankSelect.appendChild(opt);
            }
            if (bankStatus) bankStatus.textContent = 'Chọn ngân hàng để liên kết';
            if (branchSuggestions && branchSuggestions.childNodes.length === 0) {
                // datalist để trống (tuỳ chọn)
            }
        }

        let vnpayBanksLoaded = false;

        function loadVnpayBanks() {
            if (vnpayBanksLoaded) return;
            vnpayBanksLoaded = true;
            if (!bankSelect) return;
            if (bankStatus) bankStatus.textContent = 'Đang tải danh sách ngân hàng...';

            fetch('<?= h($baseUrl) ?>/core_admin/vnpay/vnpay_banks.php', {
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    const rows = Array.isArray(data?.data) ? data.data : [];
                    if (!data?.ok || rows.length === 0) {
                        if (bankStatus) bankStatus.textContent = 'Không thể tải danh sách ngân hàng.';
                        ensureBankSelectPlaceholder();
                        return;
                    }

                    const currentValue = bankSelect.value || '';
                    bankSelect.innerHTML = '';
                    const ph = document.createElement('option');
                    ph.value = '';
                    ph.textContent = 'Chọn ngân hàng';
                    bankSelect.appendChild(ph);

                    rows.forEach(item => {
                        const code = String(item.bank_code || '').trim();
                        const name = String(item.bank_name || item.short_name || code).trim();
                        if (!code) return;
                        const opt = document.createElement('option');
                        opt.value = code;
                        opt.textContent = name || code;
                        opt.setAttribute('data-name', name || code);
                        bankSelect.appendChild(opt);
                    });

                    if (currentValue) bankSelect.value = currentValue;
                    if (bankStatus) bankStatus.textContent = 'Chọn ngân hàng để liên kết';
                })
                .catch(() => {
                    if (bankStatus) bankStatus.textContent = 'Không thể tải danh sách ngân hàng.';
                    ensureBankSelectPlaceholder();
                });
        }

        function showBankStep(step) {
            if (!bankModal) return;
            const stepNum = Number(step) === 2 ? 2 : 1;
            bankModal.querySelectorAll('[data-bank-step]').forEach(sec => {
                const isActive = String(sec.getAttribute('data-bank-step')) === String(stepNum);
                sec.classList.toggle('bank-modal__hidden', !isActive);
            });
            bankModal.querySelectorAll('[data-bank-step-label]').forEach(label => {
                const isActive = String(label.getAttribute('data-bank-step-label')) === String(stepNum);
                label.classList.toggle('active', isActive);
            });
            if (bankBtnPrev) bankBtnPrev.classList.toggle('bank-modal__hidden', stepNum === 1);
            if (bankBtnNext) bankBtnNext.classList.toggle('bank-modal__hidden', stepNum !== 1);
            if (bankBtnSave) bankBtnSave.classList.toggle('bank-modal__hidden', stepNum !== 2);
            if (stepNum === 2) {
                ensureBankSelectPlaceholder();
                loadVnpayBanks();
            }
        }

        function openBankModal() {
            if (!bankModal) return;
            bankModal.classList.add('show');
            if (bankBtnPrev) bankBtnPrev.classList.remove('bank-modal__hidden');
            showBankStep(1);
        }

        function closeBankModal() {
            if (!bankModal) return;
            bankModal.classList.remove('show');
            showBankStep(1);
            editingBankId = 0;
        }

        function renderCardList(list) {
            if (!Array.isArray(list) || list.length === 0) {
                cardList.innerHTML = '<div class="bank-empty">Chưa có thẻ nào.</div>';
                return;
            }
            cardList.innerHTML = list.map(item => {
                const name = item.card_name || 'Thẻ ngân hàng';
                const number = item.card_last4 ? ('**** **** **** ' + item.card_last4) : '**** **** **** ****';
                const isDefault = Number(item.is_default) === 1;
                return `
                <div class="bacc-card" data-id="${item.id}">
                    <div class="bacc-card-logo"><img src="${DEFAULT_BANK_LOGO}" alt="${name}" loading="lazy" decoding="async"></div>
                    <div class="bacc-card-info">
                        <div class="bacc-card-type">${name}</div>
                        <div class="bacc-card-number">${number}</div>
                    </div>
                    ${isDefault ? '<span class="bacc-default-badge">Mặc định</span>' : ''}
                    <div class="bacc-card-actions">
                        <button class="btn btn-outline-primary btn-sm" type="button" data-action="edit" data-id="${item.id}">Sửa</button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-action="delete" data-id="${item.id}">Xóa</button>
                        <button class="btn btn-light btn-sm" type="button" data-action="set_default" data-id="${item.id}" ${isDefault ? 'disabled' : ''}>Thiết lập mặc định</button>
                    </div>
                </div>
            `;
            }).join('');
        }

        function renderBankList(list) {
            if (!Array.isArray(list) || list.length === 0) {
                bankList.innerHTML = '<div class="bank-empty">Chưa có tài khoản nào.</div>';
                return;
            }
            bankList.innerHTML = list.map(item => {
                const name = item.bank_name || 'Ngân hàng';
                const owner = item.account_owner || '';
                const branch = item.bank_branch || '';
                const number = item.account_no || (item.account_last4 ? ('* ' + item.account_last4) : '—');
                const isDefault = Number(item.is_default) === 1;
                const logo = item.bank_code ? `/image/bank/${item.bank_code}.png` : DEFAULT_BANK_LOGO;
                const metaParts = [owner, branch, number].filter(Boolean).join(' • ');
                return `
                <div class="bacc-bank" data-id="${item.id}">
                    <div class="bacc-bank-logo"><img src="${logo}" alt="${name}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='${DEFAULT_BANK_LOGO}';"></div>
                    <div class="bacc-bank-main">
                        <div class="bacc-bank-name">${name}</div>
                        <div class="bacc-bank-meta">${metaParts || '—'}</div>
                    </div>
                    ${isDefault ? '<span class="bacc-default-badge">Mặc định</span>' : ''}
                    <div class="bacc-bank-actions">
                        <button class="btn btn-outline-primary btn-sm" type="button" data-action="edit" data-id="${item.id}">Sửa</button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-action="delete" data-id="${item.id}">Xóa</button>
                        <button class="btn btn-light btn-sm" type="button" data-action="set_default" data-id="${item.id}" ${isDefault ? 'disabled' : ''}>Thiết lập mặc định</button>
                    </div>
                </div>
            `;
            }).join('');
        }

        function loadSavedBanks() {
            cardList.innerHTML = bankCardSkeletonHtml;
            bankList.innerHTML = bankAccountSkeletonHtml;
            fetch(bankApiUrl + '?action=list', {
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.ok) {
                        cardList.innerHTML = '<div class="bank-empty">Không thể tải dữ liệu.</div>';
                        bankList.innerHTML = '<div class="bank-empty">Không thể tải dữ liệu.</div>';
                        return;
                    }
                    savedAccounts = Array.isArray(data.data) ? data.data : [];
                    renderCardList(savedAccounts.filter(row => row.type === 'card'));
                    renderBankList(savedAccounts.filter(row => row.type === 'bank'));
                })
                .catch(() => {
                    cardList.innerHTML = '<div class="bank-empty">Không thể kết nối server.</div>';
                    bankList.innerHTML = '<div class="bank-empty">Không thể kết nối server.</div>';
                });
        }

        function onlyDigits(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function formatCardNumber(value) {
            const digits = onlyDigits(value).slice(0, 16);
            return digits.replace(/(\d{4})(?=\d)/g, '$1 ');
        }

        function formatExp(value) {
            const digits = onlyDigits(value).slice(0, 4);
            if (digits.length <= 2) return digits;
            return digits.slice(0, 2) + '/' + digits.slice(2);
        }

        function resetCardForm() {
            ['cardName', 'cardNumber', 'cardExp', 'cardCvv'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            if (cardDefault) cardDefault.checked = false;
        }

        function resetBankForm() {
            if (bankSelect) bankSelect.value = '';
            if (bankBranchInput) bankBranchInput.value = '';
            if (bankAccountInput) bankAccountInput.value = '';
            if (bankOwnerInput) bankOwnerInput.value = '';
            if (bankNameStep) bankNameStep.value = '';
            if (bankCccdStep) bankCccdStep.value = '';
            if (bankDefaultToggle) bankDefaultToggle.checked = false;
            selectedBankCode = '';
        }

        btnAddCard?.addEventListener('click', () => {
            editingCardId = 0;
            resetCardForm();
            toggleForm(cardFormWrap, true);
        });

        btnAddBank?.addEventListener('click', () => {
            editingBankId = 0;
            toggleForm(cardFormWrap, false);
            resetBankForm();
            openBankModal();
        });

        btnCancelCard?.addEventListener('click', () => {
            toggleForm(cardFormWrap, false);
            resetCardForm();
            editingCardId = 0;
        });

        const cardNumber = document.getElementById('cardNumber');
        const cardExp = document.getElementById('cardExp');
        if (cardNumber) {
            cardNumber.addEventListener('input', () => {
                cardNumber.value = formatCardNumber(cardNumber.value);
            });
        }
        if (cardExp) {
            cardExp.addEventListener('input', () => {
                cardExp.value = formatExp(cardExp.value);
            });
        }

        bankBtnPrev?.addEventListener('click', () => {
            showBankStep(1);
        });

        bankBtnNext?.addEventListener('click', () => {
            const fullName = (bankNameStep?.value || '').trim();
            const cccd = (bankCccdStep?.value || '').trim();
            if (!fullName || !cccd) {
                notify('Vui lòng nhập Họ tên và CCCD/CMND', 'warning');
                return;
            }
            if (bankOwnerInput && !bankOwnerInput.value) bankOwnerInput.value = fullName;
            showBankStep(2);
        });

        bankBtnSave?.addEventListener('click', () => {
            const bankCode = bankSelect?.value || '';
            const bankName = bankSelect?.selectedOptions?.[0]?.getAttribute('data-name') || '';
            const branch = bankBranchInput?.value || '';
            const accountNo = bankAccountInput?.value || '';
            const owner = bankOwnerInput?.value || '';
            const idCard = bankCccdStep?.value || '';
            const fullName = bankNameStep?.value || '';

            if (!bankCode) return notify('Vui lòng chọn ngân hàng', 'warning');
            if (!accountNo.trim()) return notify('Vui lòng nhập số tài khoản', 'warning');
            if (!owner.trim()) return notify('Vui lòng nhập tên chủ tài khoản', 'warning');

            const payload = new URLSearchParams();
            payload.set('action', 'save');
            payload.set('type', 'bank');
            if (editingBankId > 0) {
                payload.set('id', editingBankId);
            }
            payload.set('bank_code', bankCode.trim());
            payload.set('bank_name', bankName.trim());
            payload.set('bank_branch', branch.trim());
            payload.set('account_no', accountNo.trim());
            payload.set('account_owner', owner.trim());
            payload.set('id_card', idCard.trim());
            payload.set('full_name', fullName.trim());
            if (bankDefaultToggle?.checked) payload.set('is_default', '1');

            fetch(bankApiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: payload.toString()
                })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data && data.ok) {
                        notify(data.msg || 'Đã lưu ngân hàng', 'success');
                        editingBankId = 0;
                        loadSavedBanks();
                        closeBankModal();
                        resetBankForm();
                    } else {
                        notify(data?.msg || 'Không thể lưu ngân hàng', 'error');
                    }
                })
                .catch(err => notify('Lỗi kết nối server: ' + (err?.message || ''), 'error'));
        });

        bankCloseBtn?.addEventListener('click', closeBankModal);
        bankModal?.addEventListener('click', (e) => {
            if (e.target === bankModal) closeBankModal();
        });


        bankSelect?.addEventListener('change', () => {
            selectedBankCode = bankSelect.value || '';
            const selectedOpt = bankSelect.options[bankSelect.selectedIndex];
            const selectedName = selectedOpt ? (selectedOpt.getAttribute('data-name') || selectedOpt.textContent || '') : '';
            if (bankOwnerInput && !bankOwnerInput.value) bankOwnerInput.value = selectedName;
        });

        btnSaveCard?.addEventListener('click', () => {
            const payload = new URLSearchParams();
            payload.set('action', 'save');
            payload.set('type', 'card');
            if (editingCardId > 0) {
                payload.set('id', editingCardId);
            }
            const cardName = document.getElementById('cardName')?.value || '';
            const cardNumber = document.getElementById('cardNumber')?.value || '';
            const cardExp = document.getElementById('cardExp')?.value || '';
            payload.set('card_name', cardName.trim());
            payload.set('card_number', cardNumber);
            payload.set('card_exp', cardExp.trim());
            if (cardDefault?.checked) payload.set('is_default', '1');

            fetch(bankApiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: payload.toString()
                })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data && data.ok) {
                        notify(data.msg || 'Đã lưu thẻ', 'success');
                        editingCardId = 0;
                        loadSavedBanks();
                        resetCardForm();
                        toggleForm(cardFormWrap, false);
                    } else {
                        notify(data?.msg || 'Không thể lưu thẻ', 'error');
                    }
                })
                .catch(err => notify('Lỗi kết nối server: ' + (err?.message || ''), 'error'));
        });

        function handleListAction(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const id = btn.getAttribute('data-id');
            if (!id) return;

            if (action === 'edit') {
                const item = savedAccounts.find(row => String(row.id) === String(id));
                if (!item) return;
                if (item.type === 'card') {
                    editingCardId = item.id;
                    document.getElementById('cardName').value = item.card_name || '';
                    document.getElementById('cardNumber').value = item.card_last4 ? ('**** **** **** ' + item.card_last4) : '';
                    document.getElementById('cardExp').value = item.card_exp || '';
                    if (cardDefault) cardDefault.checked = Number(item.is_default) === 1;
                    toggleForm(cardFormWrap, true);
                    cardFormWrap.scrollIntoView({
                        behavior: 'smooth'
                    });
                } else {
                    editingBankId = item.id;
                    if (bankSelect) {
                        bankSelect.value = item.bank_code || '';
                    }
                    if (bankBranchInput) bankBranchInput.value = item.bank_branch || '';
                    if (bankAccountInput) bankAccountInput.value = item.account_no || '';
                    if (bankOwnerInput) bankOwnerInput.value = item.account_owner || '';
                    if (bankDefaultToggle) bankDefaultToggle.checked = Number(item.is_default) === 1;

                    openBankModal();
                    showBankStep(2);
                    if (bankBtnPrev) bankBtnPrev.classList.add('bank-modal__hidden');
                }
                return;
            }

            const payload = new URLSearchParams();
            if (action === 'delete') {
                if (!confirm('Bạn có chắc chắn muốn xóa tài khoản này?')) return;
                payload.set('action', 'delete');
                payload.set('id', id);
            } else if (action === 'set_default') {
                payload.set('action', 'set_default');
                payload.set('id', id);
            } else {
                return;
            }
            fetch(bankApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: payload.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.ok) {
                        notify(data.msg || 'Đã cập nhật', 'success');
                        loadSavedBanks();
                    } else {
                        notify(data?.msg || 'Không thể cập nhật', 'error');
                    }
                })
                .catch(() => notify('Lỗi kết nối server', 'error'));
        }

        cardList.addEventListener('click', handleListAction);
        bankList.addEventListener('click', handleListAction);

        // Chỉ load danh sách VNPAY Banks khi người dùng bắt đầu thao tác thêm ngân hàng (trong openBankModal)
        loadSavedBanks();
    }

    document.querySelectorAll('.js-edit-btn').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const field = link.getAttribute('data-edit') || '';
            if (!field) return;
            const container = link.closest('.row, .profile-row, td, .d-flex') || document;
            const input = container.querySelector('[data-edit-field="' + field + '"]') || document.querySelector('[data-edit-field="' + field + '"]');
            if (!input) return;
            input.removeAttribute('readonly');
            input.focus();
            if (typeof input.select === 'function') input.select();
            link.textContent = 'Đang sửa...';
            link.style.pointerEvents = 'none';
            link.style.opacity = '0.6';
        });
    });

    function showAlert(target, type, text) {
        const message = String(text || '').trim();
        if (target) {
            target.classList.remove('show');
            target.textContent = '';
        }
        if (!message) return;

        const mapType = {
            danger: 'error',
            error: 'error',
            success: 'success',
            warning: 'warning',
            info: 'info'
        };
        const toastType = mapType[String(type || 'info').toLowerCase()] || 'info';

        if (window.toastr && typeof toastr[toastType] === 'function') {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                newestOnTop: true,
                preventDuplicates: true,
                timeOut: toastType === 'error' ? 4500 : 2800,
                extendedTimeOut: 1000,
                positionClass: 'toast-top-right'
            };
            toastr[toastType](message);
            return;
        }

        alert(message);
    }

    function bindAjaxForms() {
        document.querySelectorAll('[data-ajax-form]').forEach(form => {
            const endpoint = form.getAttribute('data-endpoint');
            if (form.id === 'secvVerifyForm' || form.id === 'secvChangeForm') return;
            if (!endpoint) return;
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const alertBox = form.closest('.account-main')?.querySelector('[data-alert]') || form.querySelector('[data-alert]');
                showAlert(alertBox, 'info', 'Đang xử lý...');
                const fd = new FormData(form);
                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                    });
                    const json = await res.json();
                    if (!json || json.ok !== true) {
                        showAlert(alertBox, 'danger', json?.msg || 'Không thể xử lý.');
                        return;
                    }
                    showAlert(alertBox, 'success', json.msg || 'Thành công.');
                    updateSnapshots(json.data || {});
                    if (form.matches('[data-endpoint*="account-password"]')) {
                        form.reset();
                    }
                    if (form.matches('[data-endpoint*="account-avatar"]') && json.data?.avatar) {
                        refreshAvatar(json.data.avatar);
                    }
                } catch (err) {
                    showAlert(alertBox, 'danger', 'Lỗi kết nối, thử lại.');
                }
            });
        });
    }

    function initSecurityFlow() {
        const root = document.getElementById('secvRoot');
        if (!root) return;

        const endpoint = '<?= h($baseUrl) ?>/main/account/account-password.php';
        const panes = Array.from(root.querySelectorAll('[data-secv-step]'));
        const startBtn = document.getElementById('secvStartVerify');
        const backBtn = document.getElementById('secvBackGuard');
        const verifyForm = document.getElementById('secvVerifyForm');
        const changeForm = document.getElementById('secvChangeForm');
        const currentInput = document.getElementById('secvCurrentPassword');
        const verifySubmit = document.getElementById('secvVerifySubmit');
        const newInput = document.getElementById('secvNewPassword');
        const repeatInput = document.getElementById('secvNewPasswordRepeat');
        const changeSubmit = document.getElementById('secvChangeSubmit');
        const securityAlert = document.querySelector('[data-alert="security"]');

        let verifiedCurrentPassword = '';

        function showStep(step) {
            panes.forEach(pane => pane.classList.toggle('active', pane.getAttribute('data-secv-step') === step));
        }

        function updateVerifyButtonState() {
            if (!verifySubmit || !currentInput) return;
            verifySubmit.disabled = String(currentInput.value || '').trim().length < 1;
        }

        function updateChangeButtonState() {
            if (!changeSubmit || !newInput || !repeatInput) return;
            const newPassword = String(newInput.value || '');
            const repeatPassword = String(repeatInput.value || '');
            changeSubmit.disabled = newPassword.length < 6 || repeatPassword.length < 6 || newPassword !== repeatPassword;
        }

        root.querySelectorAll('[data-secv-toggle]').forEach(btn => {
            btn.addEventListener('click', () => {
                const selector = btn.getAttribute('data-secv-toggle');
                const input = selector ? document.querySelector(selector) : null;
                if (!input) return;
                const hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye', !hidden);
                    icon.classList.toggle('bi-eye-slash', hidden);
                }
                btn.setAttribute('aria-label', hidden ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
                btn.setAttribute('title', hidden ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            });
        });

        startBtn?.addEventListener('click', () => showStep('verify'));
        backBtn?.addEventListener('click', () => {
            verifiedCurrentPassword = '';
            if (verifyForm) verifyForm.reset();
            updateVerifyButtonState();
            showStep('guard');
        });

        currentInput?.addEventListener('input', updateVerifyButtonState);
        newInput?.addEventListener('input', updateChangeButtonState);
        repeatInput?.addEventListener('input', updateChangeButtonState);

        verifyForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const currentPassword = String(currentInput?.value || '').trim();
            if (!currentPassword) {
                showAlert(securityAlert, 'warning', 'Vui lòng nhập mật khẩu hiện tại để xác minh.');
                return;
            }
            if (verifySubmit) verifySubmit.disabled = true;
            try {
                const payload = new URLSearchParams();
                payload.set('action', 'verify_current');
                payload.set('current_password', currentPassword);
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload.toString()
                });
                const json = await res.json();
                if (!json || json.ok !== true) {
                    showAlert(securityAlert, 'danger', json?.msg || 'Xác minh thất bại.');
                    updateVerifyButtonState();
                    return;
                }

                verifiedCurrentPassword = currentPassword;
                showAlert(securityAlert, 'success', json.msg || 'Xác minh thành công.');
                if (changeForm) changeForm.reset();
                updateChangeButtonState();
                showStep('change');
            } catch (err) {
                showAlert(securityAlert, 'danger', 'Lỗi kết nối, thử lại.');
                updateVerifyButtonState();
            }
        });

        changeForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPassword = String(newInput?.value || '');
            const repeatPassword = String(repeatInput?.value || '');
            if (!verifiedCurrentPassword) {
                showAlert(securityAlert, 'warning', 'Vui lòng xác minh mật khẩu hiện tại trước.');
                showStep('verify');
                return;
            }
            if (newPassword.length < 6) {
                showAlert(securityAlert, 'warning', 'Mật khẩu mới cần tối thiểu 6 ký tự.');
                return;
            }
            if (newPassword !== repeatPassword) {
                showAlert(securityAlert, 'warning', 'Mật khẩu xác nhận không khớp.');
                return;
            }

            if (changeSubmit) changeSubmit.disabled = true;
            try {
                const payload = new URLSearchParams();
                payload.set('action', 'change');
                payload.set('current_password', verifiedCurrentPassword);
                payload.set('new_password', newPassword);
                payload.set('new_password_confirm', repeatPassword);
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload.toString()
                });
                const json = await res.json();
                if (!json || json.ok !== true) {
                    showAlert(securityAlert, 'danger', json?.msg || 'Không thể cập nhật mật khẩu.');
                    updateChangeButtonState();
                    return;
                }

                showAlert(securityAlert, 'success', json.msg || 'Đã cập nhật mật khẩu.');
                verifiedCurrentPassword = '';
                if (verifyForm) verifyForm.reset();
                if (changeForm) changeForm.reset();
                updateVerifyButtonState();
                updateChangeButtonState();
                showStep('guard');
            } catch (err) {
                showAlert(securityAlert, 'danger', 'Lỗi kết nối, thử lại.');
                updateChangeButtonState();
            }
        });

        showStep('guard');
        updateVerifyButtonState();
        updateChangeButtonState();
    }

    function initOrdersPanel() {
        const root = document.getElementById('ordxRoot');
        if (!root || typeof jQuery === 'undefined') return;
        if (initOrdersPanel._initialized) return;
        initOrdersPanel._initialized = true;
        const isAdminAccount = <?= json_encode(($userData['role'] ?? '') === 'admin') ?>;

        const $ = jQuery;
        const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/order.php';
        const DETAIL_URL = '<?= h($baseUrl) ?>/view-order';
        const CONFIRM_URL = '<?= h($baseUrl) ?>/order-confirm';

        const state = {
            status: 'all',
            search: '',
            page: 1,
            limit: 4,
            hasMore: false,
            total: 0,
            loading: false
        };

        const $tabs = $(root).find('.ordx-tab');
        const $search = $('#ordxSearchInput');
        const $meta = $('#ordxMeta');
        const $list = $('#ordxList');
        const $empty = $('#ordxEmpty');
        const $more = $('#ordxLoadMoreBtn');

        const orderSkeletonHtml = [1, 2].map(() => `
        <article class="ordx-card ordx-card--skeleton">
            <div class="ordx-head">
                <div class="skeleton-line skeleton-line--md" style="max-width:140px"></div>
                <div class="skeleton-line skeleton-line--sm" style="max-width:90px"></div>
            </div>
            <div class="ordx-body">
                <div class="ordx-item">
                    <div class="ordx-thumb skeleton-block"></div>
                    <div style="flex:1 1 auto; display:grid; gap:6px;">
                        <div class="skeleton-line skeleton-line--md"></div>
                        <div class="skeleton-line skeleton-line--sm" style="max-width:60%"></div>
                    </div>
                    <div class="skeleton-line skeleton-line--sm" style="width:26px"></div>
                </div>
                <div class="ordx-item">
                    <div class="ordx-thumb skeleton-block"></div>
                    <div style="flex:1 1 auto; display:grid; gap:6px;">
                        <div class="skeleton-line skeleton-line--md"></div>
                        <div class="skeleton-line skeleton-line--sm" style="max-width:50%"></div>
                    </div>
                    <div class="skeleton-line skeleton-line--sm" style="width:26px"></div>
                </div>
            </div>
            <div class="ordx-foot" style="grid-template-columns:repeat(2,minmax(0,1fr));align-items:center;">
                <div class="skeleton-line skeleton-line--sm" style="max-width:120px"></div>
                <div class="skeleton-line skeleton-line--sm" style="max-width:150px;justify-self:end"></div>
            </div>
        </article>
    `).join('');
        <?php
        /*if (isAdminAccount) {
        $meta.text('Mục Đơn mua chỉ áp dụng cho tài khoản người dùng.');
        $empty.text('Tài khoản quản trị không có dữ liệu đơn mua cá nhân.').removeClass('d-none');
        $tabs.prop('disabled', true);
        $search.prop('disabled', true);
        $more.removeClass('show');
        return;
    }*/
        ?>
        const notify = (msg, type = 'info') => {
            if (window.toastr && typeof toastr[type] === 'function') toastr[type](msg);
        };

        const esc = (value) => $('<div>').text(String(value ?? '')).html();

        const debounce = (fn, delay = 350) => {
            let timer = null;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        };

        function parseDateTimeToTs(raw) {
            const txt = String(raw || '').trim();
            if (!txt) return 0;
            const m = txt.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
            if (!m) return 0;
            const dt = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4] || 0), Number(m[5] || 0), Number(m[6] || 0));
            return Number.isNaN(dt.getTime()) ? 0 : dt.getTime();
        }

        function canShowPaymentActions(order) {
            if (!order) return false;
            const method = String(order.payment_method || '').toLowerCase();
            if (method !== 'momo' && method !== 'vnpay') return false;
            const status = String(order.status || '').toLowerCase();
            const payStatus = String(order.payment_status || '').toLowerCase();
            if (status !== 'pending' || payStatus !== 'pending') return false;
            const createdTs = parseDateTimeToTs(order.created_at || '');
            if (!createdTs) return false;
            const now = Date.now();
            if (now - createdTs > 24 * 60 * 60 * 1000) return false;
            const expTs = Number(order.payment_expires_ts || 0);
            if (expTs > 0 && now > expTs * 1000) return false;
            return true;
        }

        function normalizeStatus(status) {
            const s = String(status || '').toLowerCase();
            if (s === 'returned' || s === 'return_requested') return 'refund';
            return s || 'pending';
        }

        function getServerStatus() {
            if (state.status === 'refund') return 'return_requested';
            return state.status;
        }

        function getVisibleRows(rows) {
            return rows;
        }

        function buildActions(order) {
            const id = String(order.order_id || '');
            const btns = [`<button type="button" class="ordx-btn" data-ordx-action="detail" data-id="${esc(id)}">Chi tiết</button>`];

            if (canShowPaymentActions(order)) {
                const payUrl = esc((order && order.payment_meta && order.payment_meta.pay_url) ? order.payment_meta.pay_url : '');
                btns.push(`<button type="button" class="ordx-btn primary" data-ordx-action="pay_now" data-id="${esc(id)}" data-pay-url="${payUrl}">Thanh toán ngay</button>`);
                btns.push(`<button type="button" class="ordx-btn" data-ordx-action="change_payment" data-id="${esc(id)}">Đổi PTTT</button>`);
            }
            if (order && order.actions && order.actions.can_confirm) {
                btns.push(`<button type="button" class="ordx-btn" data-ordx-action="confirm" data-id="${esc(id)}">Đã nhận hàng</button>`);
            }
            if (order && order.actions && order.actions.can_return) {
                btns.push(`<button type="button" class="ordx-btn" data-ordx-action="return" data-id="${esc(id)}">Trả hàng</button>`);
            }
            return btns.join('');
        }

        function renderOrders(rows, reset) {
            if (reset) {
                $list.empty();
                $empty.addClass('d-none');
            }
            if (!rows.length && state.page === 1) {
                $empty.removeClass('d-none');
                return;
            }

            const html = rows.map(order => {
                const items = Array.isArray(order.items) ? order.items : [];
                const firstItems = items.slice(0, 2);
                const moreCount = Math.max(0, items.length - firstItems.length);
                const firstShop = firstItems.find(item => String(item.shop_name || '').trim()) || items.find(item => String(item.shop_name || '').trim());
                const shopName = String((firstShop && firstShop.shop_name) || order.shop_name || 'Shop').trim();
                const statusLabel = String(order.status_label || '').trim() || String(order.status || '').trim() || 'Đơn hàng';
                const statusKey = normalizeStatus(order.status);
                const cardItems = firstItems.map(item => {
                    const img = String(item.image || item.thumb || item.thumbnail || '').trim();
                    const name = String(item.name || 'Sản phẩm').trim();
                    const variant = String(item.variant || '').trim();
                    const qty = Number(item.qty || item.quantity || 1);
                    return '' +
                        '<div class="ordx-item">' +
                        '  <div class="ordx-thumb">' + (img ? '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async">' : 'SP') + '</div>' +
                        '  <div>' +
                        '    <div class="ordx-item-name">' + esc(name) + '</div>' +
                        (variant ? '<div class="ordx-item-variant">Phân loại: ' + esc(variant) + '</div>' : '') +
                        '  </div>' +
                        '  <div class="ordx-item-qty">x' + esc(qty) + '</div>' +
                        '</div>';
                }).join('');

                return '' +
                    '<article class="ordx-card" data-ordx-order="' + esc(order.order_id) + '">' +
                    '  <div class="ordx-head">' +
                    '    <div class="ordx-shop"><i class="bi bi-shop"></i><span>' + esc(shopName) + '</span></div>' +
                    '    <div class="ordx-status ordx-status-' + esc(statusKey) + '">' + esc(statusLabel) + '</div>' +
                    '  </div>' +
                    '  <div class="ordx-body">' +
                    cardItems +
                    (moreCount > 0 ? '<div class="ordx-more">+' + esc(moreCount) + ' sản phẩm khác</div>' : '') +
                    '  </div>' +
                    '  <div class="ordx-foot">' +
                    '    <div class="ordx-total"><span class="ordx-total-label">Thành tiền:</span><span class="ordx-total-value">' + esc((order.totals && order.totals.grand_total) ? order.totals.grand_total : '0 đ') + '</span></div>' +
                    '    <div class="ordx-actions">' + buildActions(order) + '</div>' +
                    '  </div>' +
                    '</article>';
            }).join('');

            $list.append(html);
            state.page += 1;
        }

        function extractAjaxError(xhr, fallback) {
            const def = String(fallback || 'Không thể tải dữ liệu');
            if (!xhr) return def;
            if (xhr.responseJSON && xhr.responseJSON.msg) return String(xhr.responseJSON.msg);
            const raw = String(xhr.responseText || '').trim();
            if (raw) {
                try {
                    const parsed = JSON.parse(raw);
                    if (parsed && parsed.msg) return String(parsed.msg);
                } catch (e) {}
            }
            if (xhr.status === 401) return 'Bạn chưa đăng nhập để xem đơn mua.';
            if (xhr.status === 403) return 'Tài khoản hiện tại không có quyền xem danh sách đơn mua.';
            if (xhr.status >= 500) return 'Máy chủ đang bận, vui lòng thử lại sau.';
            return def;
        }

        function fetchOrders(reset = false) {
            if (state.loading) return;
            state.loading = true;
            if (reset) {
                state.page = 1;
                $list.html(orderSkeletonHtml);
                $empty.addClass('d-none');
            }
            $meta.text('Đang tải đơn hàng...');

            $.ajax({
                    url: API,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'list',
                        status: getServerStatus(),
                        search: state.search,
                        page: state.page,
                        limit: state.limit
                    }
                })
                .done((res) => {
                    if (!res || !res.ok) {
                        notify((res && res.msg) || 'Không tải được đơn hàng', 'error');
                        $meta.text('Không tải được dữ liệu đơn hàng.');
                        $list.empty();
                        $empty.removeClass('d-none');
                        return;
                    }

                    const rows = Array.isArray(res.data) ? res.data : [];
                    const visibleRows = getVisibleRows(rows);
                    state.total = Number(res.total || 0);
                    state.hasMore = !!res.has_more;
                    renderOrders(visibleRows, reset);
                    $meta.text((state.total > 0 ? state.total : visibleRows.length) + ' đơn hàng');
                    $more.toggleClass('show', state.hasMore);
                })
                .fail((xhr) => {
                    const msg = extractAjaxError(xhr, 'Không thể kết nối máy chủ');
                    notify(msg, 'error');
                    $meta.text(msg);
                    $list.empty();
                    $empty.removeClass('d-none');
                })
                .always(() => {
                    state.loading = false;
                });
        }

        function postAction(action, orderId) {
            if (!orderId) return;
            const payload = {
                action: action,
                order_id: orderId
            };
            if (action === 'return') {
                const reason = prompt('Nhập lý do trả hàng:') || '';
                if (!reason.trim()) return;
                payload.reason = reason.trim();
            }
            $.ajax({
                    url: API,
                    method: 'POST',
                    dataType: 'json',
                    data: payload
                })
                .done((res) => {
                    if (!res || !res.ok) {
                        notify((res && res.msg) || 'Thao tác thất bại', 'error');
                        return;
                    }
                    notify(res.msg || 'Cập nhật thành công', 'success');
                    fetchOrders(true);
                })
                .fail((xhr) => notify(extractAjaxError(xhr, 'Không thực hiện được thao tác'), 'error'));
        }

        $tabs.on('click', function() {
            const status = String($(this).data('ordx-status') || 'all');
            state.status = status;
            $tabs.removeClass('active');
            $(this).addClass('active');
            fetchOrders(true);
        });

        $search.on('input', debounce(function() {
            state.search = String($(this).val() || '').trim();
            fetchOrders(true);
        }, 400));

        $more.on('click', function() {
            if (!state.hasMore) return;
            fetchOrders(false);
        });

        $list.on('click', '[data-ordx-action]', function() {
            const action = String($(this).data('ordx-action') || '');
            const orderId = String($(this).data('id') || '');
            if (!orderId) return;

            if (action === 'pay_now') {
                const payUrl = String($(this).data('pay-url') || '').trim();
                if (payUrl) {
                    window.open(payUrl, '_blank');
                } else {
                    window.location.href = `${CONFIRM_URL}&order_id=${encodeURIComponent(orderId)}`;
                }
                return;
            }
            if (action === 'change_payment') {
                window.location.href = `${CONFIRM_URL}&order_id=${encodeURIComponent(orderId)}&change_payment=1`;
                return;
            }
            if (action === 'detail') {
                window.location.href = `${DETAIL_URL}?order_id=${encodeURIComponent(orderId)}`;
                return;
            }
            if (action === 'confirm') {
                if (confirm('Xác nhận đã nhận hàng?')) postAction('confirm', orderId);
                return;
            }
            if (action === 'return') {
                if (confirm('Gửi yêu cầu trả hàng?')) postAction('return', orderId);
                return;
            }
        });
        fetchOrders(true);
    }

    // Panel Địa chỉ: khởi tạo lười, chỉ khi người dùng mở tab Địa chỉ lần đầu
    function initAddressPanel() {
        if (typeof jQuery === 'undefined') return;
        if (initAddressPanel._initialized) return;
        initAddressPanel._initialized = true;

        const endpoint = '<?= h($baseUrl) ?>/main/account/region-session.php';
        const AI_ENDPOINT = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
        const GOONG_MAP_KEY = '<?= h($goong_map_key ?? '') ?>';
        if (!$('#regionLocationForm').length) return;

        const $street = $('#locStreet');
        const $district = $('#locDistrictSel');
        const $ward = $('#locWardSel');
        const $province = $('#locProvinceSel');
        const $recipientName = $('#locRecipientName');
        const $contactPhone = $('#locContactPhone');
        const $deliveryNote = $('#locDeliveryNote');
        const $addressId = $('#locAddressId');
        const $distance = $('#locDistanceHint');
        const $saved = $('#locSavedAddresses');
        const $saveBtn = $('#locSaveBtn');
        const $aiStreetBtn = $('#locAiStreetBtn');
        const $gpsBtn = $('#locGpsBtn');
        const $form = $('#regionLocationForm');
        const $modal = $('#addrxModal');
        const bsModal = new bootstrap.Modal(document.getElementById('addrxModal'), {
            backdrop: 'static',
            keyboard: false
        });
        const $modalCloseBtn = $('#addrxModalClose');
        const $addNewBtn = $('#addrxAddNewBtn');
        const $countHint = $('#addrxCountHint');
        const $streetSuggestList = $('#locStreetSuggestList');
        const shippingAlert = document.querySelector('[data-alert="shipping"]');

        // Thông tin user để auto-fill khi thêm mới
        const defaultRecipientName = '<?= h($selectedRecipientName) ?>';
        const defaultUserPhone = '<?= h($userData["phone"] ?? $selectedContactPhone) ?>';
        let selectedRegion = '<?= h($selectedRegion) ?>';
        let savedLocations = [];
        let allProvinces = [];
        let allDistricts = [];
        let allWards = [];
        let streetSuggestTimer = null;

        // skeleton cho danh sách địa chỉ lần đầu tải
        const addressSkeletonHtml = [1, 2].map(() => `
        <div class="addrx-card">
            <div class="skeleton-line" style="width:34px;height:34px;border-radius:9px;"></div>
            <div class="addrx-card-body">
                <div class="addrx-card-top">
                    <div class="addrx-card-ident" style="gap:8px;">
                        <div class="skeleton-line skeleton-line--md" style="width:120px"></div>
                        <div class="skeleton-line skeleton-line--sm" style="width:90px"></div>
                    </div>
                    <div class="addrx-card-tags" style="gap:6px;">
                        <span class="skeleton-line skeleton-line--sm" style="width:74px;border-radius:999px;"></span>
                        <span class="skeleton-line skeleton-line--sm" style="width:64px;border-radius:999px;"></span>
                    </div>
                </div>
                <div class="addrx-card-addr" style="gap:8px;">
                    <div class="skeleton-line skeleton-line--sm" style="width:100%;max-width:280px;"></div>
                </div>
                <div class="addrx-card-foot">
                    <div class="skeleton-line skeleton-line--sm" style="width:120px"></div>
                    <div class="addrx-card-actions" style="gap:6px;">
                        <div class="skeleton-line skeleton-line--sm" style="width:58px"></div>
                        <div class="skeleton-line skeleton-line--sm" style="width:58px"></div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

        function flash(type, msg) {
            showAlert(shippingAlert, type, msg);
        }

        function esc(v) {
            return $('<div>').text(String(v || '')).html();
        }

        function getAddressTypeValue() {
            const raw = String($form.find('input[name="address_type"]:checked').val() || 'home').trim().toLowerCase();
            return raw === 'office' ? 'office' : 'home';
        }

        function setAddressTypeValue(value) {
            const normalized = String(value || 'home').trim().toLowerCase() === 'office' ? 'office' : 'home';
            $form.find('input[name="address_type"]').prop('checked', false);
            $form.find('input[name="address_type"][value="' + normalized + '"]').prop('checked', true);
        }

        function renderSavedLocations() {
            if (!savedLocations.length) {
                $saved.html('<div class="addrx-empty">Bạn chưa có địa chỉ nào. Nhấn “Thêm địa chỉ mới” để bắt đầu.</div>');
                if ($countHint.length) $countHint.text('0/5 địa chỉ đã lưu');
                return;
            }
            if ($countHint.length) $countHint.text(savedLocations.length + '/5 địa chỉ đã lưu');
            const activeId = String($addressId.val() || '');
            const html = savedLocations.map(function(loc) {
                const id = String(loc.address_id || '');
                const active = id === activeId;
                const isOffice = String(loc.address_type || '') === 'office';
                const addressTypeLabel = isOffice ? 'Văn phòng' : 'Nhà riêng';
                const typeIcon = isOffice ? 'bi-building' : 'bi-house-door';
                const recipient = String(loc.recipient_name || defaultRecipientName || '').trim();
                const phone = String(loc.contact_phone || '').trim();
                const streetLine = String(loc.street || '').trim();
                const areaLine = [loc.ward, loc.district, loc.province].filter(Boolean).join(', ');
                const fullAddress = [streetLine || loc.customer_address || 'Địa chỉ giao hàng', areaLine].filter(Boolean).join(', ');
                const initial = (recipient || 'N').trim().charAt(0);
                return '' +
                    '<div class="p-3 addrx-card' + (active ? ' addrx-card--active' : '') + '" data-address-id="' + esc(id) + '">' +
                    '  <div class="addrx-avatar">' + esc(initial) + '</div>' +
                    '  <div class="addrx-card-body">' +
                    '    <div class="addrx-card-top">' +
                    '      <div class="addrx-card-ident">' +
                    '        <div class="addrx-card-name">' + esc(recipient || 'Người nhận') + '</div>' +
                    '        <div class="addrx-card-phone"><i class="bi bi-telephone"></i>' + esc(phone || 'Chưa có SĐT') + '</div>' +
                    '      </div>' +
                    '      <div class="addrx-card-tags">' +
                    (active ? '<span class="addrx-badge addrx-badge--default"><i class="bi bi-star-fill"></i>Mặc định</span>' : '') +
                    '        <span class="addrx-badge addrx-badge--type"><i class="bi ' + typeIcon + '"></i>' + esc(addressTypeLabel) + '</span>' +
                    '      </div>' +
                    '    </div>' +
                    '    <div class="addrx-card-addr"><i class="bi bi-geo-alt"></i><span>' + esc(fullAddress) + '</span></div>' +
                    '    <div class="addrx-card-foot">' +
                    '      <button type="button" class="addrx-default-btn" data-addrx-action="set_default" ' + (active ? 'disabled' : '') + '>' +
                    (active ? '<i class="bi bi-check-lg"></i>Đang dùng' : '<i class="bi bi-star"></i>Đặt mặc định') + '</button>' +
                    '      <div class="addrx-card-actions">' +
                    '        <button type="button" class="addrx-icon-btn" data-addrx-action="edit" title="Cập nhật địa chỉ"><i class="bi bi-pencil-square"></i><span>Sửa</span></button>' +
                    '        <button type="button" class="addrx-icon-btn addrx-danger" data-addrx-action="delete" title="Xóa địa chỉ"><i class="bi bi-trash3"></i><span>Xóa</span></button>' +
                    '      </div>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';
            }).join('');
            $saved.html(html);
        }

        // opts.skipRegion = true: CHỈ điền ô địa chỉ + ghim toạ độ, KHÔNG tự chọn
        // tỉnh/huyện/xã (dùng cho ghim bản đồ/GPS vì Nominatim địa giới VN hay lệch).
        // opts.fullStreet = true: dùng cả chuỗi full làm ô "Địa chỉ cụ thể".
        function applyAiAddressSuggestion(top, opts) {
            if (!top || typeof top !== 'object') return;
            opts = opts || {};
            const full = String(top.full || '').trim();
            const aiStreet = String(top.street || '').trim();

            // ─── Ô "Địa chỉ cụ thể" ───
            if (opts.fullStreet && full) {
                $street.val(full);
            } else if (aiStreet) {
                // Giữ số nhà user đã gõ ở đầu ô (vd "380") nếu tên đường gợi ý chưa kèm số đó.
                const cur = String($street.val() || '').trim();
                const houseNo = (cur.match(/^\s*(\d+[a-zA-Z]?(?:\/\d+[a-zA-Z]?)*)\b/) || [])[1] || '';
                const hasNum = /\d/.test(aiStreet);
                $street.val(houseNo && !hasNum ? (houseNo + ' ' + aiStreet) : aiStreet);
            } else if (full) {
                const firstComma = full.split(',')[0] || full;
                $street.val(firstComma.trim());
            }

            // ─── Khu vực: ưu tiên candidates[] từ Goong (province_candidates, district_candidates, ward_candidates)
            // để tăng tỉ lệ khớp với danh mục GHN vì Goong dùng tên đầy đủ ("Thành phố Hồ Chí Minh")
            // còn GHN hay dùng tên rút gọn ("Hồ Chí Minh").
            const provinceCandidates = Array.isArray(top.province_candidates) && top.province_candidates.length
                ? top.province_candidates : [String(top.province || '')].filter(Boolean);
            const districtCandidates = Array.isArray(top.district_candidates) && top.district_candidates.length
                ? top.district_candidates : [String(top.district || '')].filter(Boolean);
            const wardCandidates = Array.isArray(top.ward_candidates) && top.ward_candidates.length
                ? top.ward_candidates : [String(top.ward || '')].filter(Boolean);

            const aiProvince = String(provinceCandidates[0] || '').trim();
            const aiDistrict = String(districtCandidates[0] || '').trim();
            const aiWard     = String(wardCandidates[0]     || '').trim();

            if (!opts.skipRegion && (aiProvince || aiDistrict || aiWard)) {
                $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Đang chuẩn hoá...');
                if (aiProvince) {
                    // Lưu cả mảng candidates để findProvinceByName thử lần lượt
                    $province.data('selectedName', aiProvince)
                             .data('selectedId', Number(top.province_id || 0))
                             .data('nameCandidates', provinceCandidates);
                }
                if (aiDistrict) {
                    $district.data('selectedName', aiDistrict)
                             .data('selectedId', Number(top.district_id || 0))
                             .data('nameCandidates', districtCandidates);
                }
                if (aiWard) {
                    $ward.data('selectedName', aiWard)
                         .data('selectedCode', String(top.ward_code || ''))
                         .data('nameCandidates', wardCandidates);
                }
                loadProvinces(aiProvince || '')
                    .finally(function() {
                        $saveBtn.prop('disabled', false).text('Hoàn thành');
                    });
            }

            // Nếu gợi ý kèm toạ độ (geo_search/geo_reverse) → di chuyển ghim bản đồ.
            const sLat = parseFloat(top.lat), sLng = parseFloat(top.lng);
            if (isFinite(sLat) && isFinite(sLng) && !(sLat === 0 && sLng === 0)) {
                if (typeof setMapPoint === 'function') setMapPoint(sLat, sLng, true);
            }
        }

        function renderStreetSuggestions(list) {
            if (!$streetSuggestList.length) return;
            if (!Array.isArray(list) || !list.length) {
                $streetSuggestList.empty().addClass('d-none');
                return;
            }
            const items = list.slice(0, 5);
            const html = items.map(function(item) {
                const fullRaw = String(item.full || '').trim();
                // Main: ưu tiên street; nếu rỗng lấy phần đầu (trước dấu phẩy) của full.
                // Tránh hiển thị cả display_name dài + lặp lại khu vực ở dòng sub.
                let mainRaw = String(item.street || '').trim();
                if (!mainRaw) mainRaw = fullRaw.split(',')[0] || fullRaw;
                // Sub: ưu tiên secondary_text của Goong; rồi tới ward/district/province; cuối cùng rút từ full.
                let subRaw = String(item.sub || '').trim();
                if (!subRaw) subRaw = [item.ward, item.district, item.province].filter(Boolean).join(', ');
                if (!subRaw && fullRaw) {
                    const rest = fullRaw.split(',').slice(1).map(function(s){ return s.trim(); })
                        .filter(function(s){ return s && !/^\d+$/.test(s) && s !== 'Việt Nam'; });
                    subRaw = rest.join(', ');
                }
                const main = esc(mainRaw);
                const sub = esc(subRaw);
                return '' +
                    '<button type="button" class="addrx-suggest-item" data-suggest="item"' +
                    ' data-full="' + esc(item.full || '') + '"' +
                    ' data-street="' + esc(item.street || '') + '"' +
                    ' data-ward="' + esc(item.ward || '') + '"' +
                    ' data-district="' + esc(item.district || '') + '"' +
                    ' data-province="' + esc(item.province || '') + '"' +
                    ' data-place-id="' + esc(item.place_id || '') + '"' +
                    ' data-lat="' + esc(item.lat != null ? item.lat : '') + '"' +
                    ' data-lng="' + esc(item.lng != null ? item.lng : '') + '">' +
                    '  <span class="addrx-suggest-item-main">' + main + '</span>' +
                    (sub ? '<span class="addrx-suggest-item-sub">' + sub + '</span>' : '') +
                    '</button>';
            }).join('');
            $streetSuggestList.html(html).removeClass('d-none');
        }

        function openEditor() {
            bsModal.show();
        }

        function closeEditor() {
            bsModal.hide();
        }

        function resetAddressForm() {
            $addressId.val('');
            $street.val('');
            // Tự động điền tên và SĐT từ thông tin tài khoản khi thêm mới
            $recipientName.val(defaultRecipientName || '');
            $contactPhone.val(defaultUserPhone || '');
            $deliveryNote.val('');
            setAddressTypeValue('home');
            $province.data('selectedName', '').data('selectedId', 0).val('');
            $district.data('selectedName', '').data('selectedId', 0).html('<option value="">-- Chọn quận/huyện --</option>');
            $ward.data('selectedName', '').data('selectedCode', '').html('<option value="">-- Chọn phường/xã --</option>');
            $('#locLat').val('');
            $('#locLng').val('');
            renderDistanceHint({});
            loadProvinces('');
            $saveBtn.text('Hoàn thành');
            // Cập nhật tiêu đề modal
            const modalLabel = document.getElementById('addrxModalLabel');
            if (modalLabel) modalLabel.textContent = 'Thêm địa chỉ mới';
        }

        function fillFormFromSavedLocation(loc) {
            if (!loc) return;
            selectedRegion = String(loc.region || selectedRegion || '').trim();
            $addressId.val(String(loc.address_id || ''));

            $street.val(String(loc.street || ''));
            $recipientName.val(String(loc.recipient_name || defaultRecipientName || ''));
            setAddressTypeValue(String(loc.address_type || 'home'));
            $contactPhone.val(String(loc.contact_phone || ''));
            $deliveryNote.val(String(loc.delivery_note || ''));

            $province.data('selectedName', String(loc.province || ''));
            $province.data('selectedId', Number(loc.province_id || 0));
            $district.data('selectedName', String(loc.district || ''));
            $district.data('selectedId', Number(loc.district_id || 0));
            $ward.data('selectedName', String(loc.ward || ''));
            $ward.data('selectedCode', String(loc.ward_code || ''));
            // Toạ độ đã lưu → để initLocMap đặt ghim đúng vị trí cũ.
            $('#locLat').val(loc.customer_lat != null ? String(loc.customer_lat) : '');
            $('#locLng').val(loc.customer_lng != null ? String(loc.customer_lng) : '');
            if (typeof setMapPoint === 'function' && loc.customer_lat && loc.customer_lng) {
                setMapPoint(loc.customer_lat, loc.customer_lng, true);
            }
            $saveBtn.text('Hoàn thành');
            // Cập nhật tiêu đề modal khi sửa
            const modalLabel = document.getElementById('addrxModalLabel');
            if (modalLabel) modalLabel.textContent = 'Cập nhật địa chỉ';
            openEditor();

            loadOptions(selectedRegion)
                .finally(function() {
                    renderSavedLocations();
                    renderDistanceHint(loc || {});
                    loadProvinces(String(loc.province || ''));
                    flash('info', 'Đang chỉnh sửa địa chỉ đã chọn. Nhấn “Hoàn thành” để lưu.');
                });
        }

        function renderDistanceHint(location) {
            const d = Number(location?.distance_km);
            if (Number.isFinite(d) && d > 0) {
                $distance.text('Khoảng cách ước tính từ chi nhánh: ' + d.toFixed(2) + ' km').removeClass('d-none');
            } else {
                $distance.addClass('d-none').text('');
            }
        }

        function normalizeLocationName(str) {
            let s = String(str || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // b\u1ecf d\u1ea5u ti\u1ebfng Vi\u1ec7t
                .replace(/\s+/g, ' ')
                .trim();
            // B\u1ecf ti\u1ec1n t\u1ed1 \u0111\u01a1n v\u1ecb h\u00e0nh ch\u00ednh \u0111\u1ec3 kh\u1edbp t\u00ean gi\u1eefa Nominatim v\u00e0 DB.
            // (vd "thanh pho ho chi minh" \u2192 "ho chi minh", "quan binh thanh" \u2192 "binh thanh")
            s = s.replace(/^(thanh pho|tinh|quan|huyen|thi xa|thi tran|phuong|xa)\s+/i, '').trim();
            s = s.replace(/\b0+(\d+)\b/g, '$1');
            return s;
        }

        function extractNumberToken(str) {
            const m = String(str || '').match(/\d+/);
            return m ? m[0] : '';
        }

        // So tên (đã normalize) với tên chính + mảng alts (NameExtension của GHN).
        // numAware=true: nếu cả 2 đều có số (phường số) thì số phải trùng.
        function matchRegionName(keyword, mainName, alts, numAware) {
            const kwNum = numAware ? extractNumberToken(keyword) : '';
            const candidates = [mainName].concat(Array.isArray(alts) ? alts : []);
            for (let i = 0; i < candidates.length; i++) {
                const txt = normalizeLocationName(candidates[i] || '');
                if (!txt) continue;
                if (numAware) {
                    const txtNum = extractNumberToken(txt);
                    if (kwNum && txtNum && Number(kwNum) !== Number(txtNum)) continue;
                }
                if (txt === keyword || txt.includes(keyword) || keyword.includes(txt)) return true;
            }
            return false;
        }

        function findProvinceByName(name) {
            const keyword = normalizeLocationName(name);
            if (!keyword) return null;
            // Ưu tiên khớp tuyệt đối (tên chính hoặc alt) trước khi rơi xuống khớp một phần.
            return allProvinces.find(function(p) {
                return matchRegionName(keyword, p.ProvinceName || p.name || '', p.alts, false);
            }) || null;
        }

        function findProvinceByNameCandidates(candidates) {
            if (!Array.isArray(candidates)) return null;
            for (var i = 0; i < candidates.length; i++) {
                var r = findProvinceByName(candidates[i]);
                if (r) return r;
            }
            return null;
        }

        function findDistrictByName(name) {
            const keyword = normalizeLocationName(name);
            if (!keyword) return null;
            return allDistricts.find(function(d) {
                return matchRegionName(keyword, d.DistrictName || d.name || '', d.alts, true);
            }) || null;
        }

        function findDistrictByNameCandidates(candidates) {
            if (!Array.isArray(candidates)) return null;
            for (var i = 0; i < candidates.length; i++) {
                var r = findDistrictByName(candidates[i]);
                if (r) return r;
            }
            return null;
        }

        function findWardByName(name) {
            const keyword = normalizeLocationName(name);
            if (!keyword) return null;
            return allWards.find(function(w) {
                return matchRegionName(keyword, w.WardName || w.name || '', w.alts, true);
            }) || null;
        }

        function findWardByNameCandidates(candidates) {
            if (!Array.isArray(candidates)) return null;
            for (var i = 0; i < candidates.length; i++) {
                var r = findWardByName(candidates[i]);
                if (r) return r;
            }
            return null;
        }

        function loadProvinces(selectedName) {
            return fetch(endpoint + '?action=region_provinces', {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    const rows = Array.isArray(res?.rows) ? res.rows : [];
                    allProvinces = rows;
                    const options = ['<option value="">-- Chọn tỉnh/thành --</option>'];
                    allProvinces.forEach(function(p) {
                        options.push('<option value="' + esc(p.ProvinceID) + '">' + esc(p.ProvinceName) + '</option>');
                    });
                    $province.html(options.join(''));

                    const selectedId = Number($province.data('selectedId') || 0);
                    const pCandidates = $province.data('nameCandidates');
                    const matched = selectedId > 0
                        ? allProvinces.find(function(p) { return Number(p.ProvinceID || 0) === selectedId; })
                        : (findProvinceByNameCandidates(Array.isArray(pCandidates) ? pCandidates : null)
                           || findProvinceByName(selectedName || $province.data('selectedName') || ''));
                    if (matched) {
                        $province.val(String(matched.ProvinceID));
                        return loadDistricts(matched.ProvinceID, $district.data('selectedName') || '');
                    }
                    $district.html('<option value="">-- Chọn quận/huyện --</option>');
                    $ward.html('<option value="">-- Chọn phường/xã --</option>');
                })
                .catch(function() {
                    $province.html('<option value="">Không tải được danh sách tỉnh/thành</option>');
                    $district.html('<option value="">Không tải được danh sách quận/huyện</option>');
                    $ward.html('<option value="">Không tải được danh sách phường/xã</option>');
                });
        }

        function loadDistricts(provinceCode, selectedName) {
            if (!provinceCode) {
                allDistricts = [];
                allWards = [];
                $district.html('<option value="">-- Chọn quận/huyện --</option>');
                $ward.html('<option value="">-- Chọn phường/xã --</option>');
                return Promise.resolve();
            }
            return fetch(endpoint + '?action=region_districts&province_id=' + encodeURIComponent(provinceCode), {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    const districts = Array.isArray(res?.rows) ? res.rows : [];
                    allDistricts = districts;
                    const options = ['<option value="">-- Chọn quận/huyện --</option>'];
                    districts.forEach(function(d) {
                        options.push('<option value="' + esc(d.DistrictID) + '">' + esc(d.DistrictName) + '</option>');
                    });
                    $district.html(options.join(''));
                    const selectedId2 = Number($district.data('selectedId') || 0);
                    const dCandidates = $district.data('nameCandidates');
                    const matched = selectedId2 > 0
                        ? allDistricts.find(function(d) { return Number(d.DistrictID || 0) === selectedId2; })
                        : (findDistrictByNameCandidates(Array.isArray(dCandidates) ? dCandidates : null)
                           || findDistrictByName(selectedName || ''));
                    if (matched) {
                        $district.val(String(matched.DistrictID));
                        return loadWards(matched.DistrictID, $ward.data('selectedName') || '');
                    }
                    $ward.html('<option value="">-- Chọn phường/xã --</option>');
                })
                .catch(function() {
                    $district.html('<option value="">Không tải được danh sách quận/huyện</option>');
                    $ward.html('<option value="">Không tải được danh sách phường/xã</option>');
                });
        }

        function loadWards(districtCode, selectedName) {
            if (!districtCode) {
                allWards = [];
                $ward.html('<option value="">-- Chọn phường/xã --</option>');
                return Promise.resolve();
            }
            return fetch(endpoint + '?action=region_wards&district_id=' + encodeURIComponent(districtCode), {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    const wards = Array.isArray(res?.rows) ? res.rows : [];
                    allWards = wards;
                    const options = ['<option value="">-- Chọn phường/xã --</option>'];
                    wards.forEach(function(w) {
                        options.push('<option value="' + esc(w.WardCode) + '">' + esc(w.WardName) + '</option>');
                    });
                    $ward.html(options.join(''));
                    const selectedCode = String($ward.data('selectedCode') || '').trim();
                    const wCandidates = $ward.data('nameCandidates');
                    const matched = selectedCode !== ''
                        ? allWards.find(function(w) { return String(w.WardCode || '') === selectedCode; })
                        : (findWardByNameCandidates(Array.isArray(wCandidates) ? wCandidates : null)
                           || findWardByName(selectedName || ''));
                    if (matched) $ward.val(String(matched.WardCode));
                })
                .catch(function() {
                    $ward.html('<option value="">Không tải được danh sách phường/xã</option>');
                });
        }

        function getSelectedProvinceName() {
            const code = String($province.val() || '');
            const row = allProvinces.find(function(p) {
                return String(p.ProvinceID) === code;
            });
            return row ? String(row.ProvinceName || '') : '';
        }

        function getSelectedDistrictName() {
            const code = String($district.val() || '');
            const row = allDistricts.find(function(d) {
                return String(d.DistrictID) === code;
            });
            return row ? String(row.DistrictName || '') : '';
        }

        function getSelectedWardName() {
            const code = String($ward.val() || '');
            const row = allWards.find(function(w) {
                return String(w.WardCode) === code;
            });
            return row ? String(row.WardName || '') : '';
        }

        function loadOptions(region) {
            return fetch(endpoint + '?region=' + encodeURIComponent(region || ''), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Không tải được dữ liệu vị trí');
                    return response.json();
                })
                .then(function(result) {
                    if (!result || !result.ok) throw new Error(result?.message || 'Dữ liệu vị trí không hợp lệ');
                    savedLocations = Array.isArray(result.saved_locations) ? result.saved_locations : [];

                    const loc = result.location || {};
                    if (!selectedRegion) selectedRegion = String(result.selected_region || '');
                    $addressId.val(String(loc.address_id || ''));

                    renderSavedLocations();
                    renderDistanceHint(result.location || {});
                    return result;
                });
        }

        $province.on('change', function() {
            const code = String($(this).val() || '');
            loadDistricts(code, '');
        });

        $district.on('change', function() {
            const code = String($(this).val() || '');
            loadWards(code, '');
        });

        $aiStreetBtn.on('click', function() {
            const q = [
                String($street.val() || '').trim(),
                getSelectedWardName(),
                getSelectedDistrictName(),
                getSelectedProvinceName()
            ].filter(Boolean).join(', ');
            if (!q) return;
            const $btn = $(this);
            const old = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Đang gợi ý');
            $.get(AI_ENDPOINT, {
                ajax: 'address_ai',
                q: q
            }, function(res) {
                if (res && res.ok && Array.isArray(res.data) && res.data.length) {
                    const top = res.data[0] || {};
                    applyAiAddressSuggestion(top);
                }
            }).always(function() {
                $btn.prop('disabled', false).html(old);
            });
        });

        // Lấy vị trí hiện tại → reverse geocode → điền địa chỉ + chọn dropdown + di chuyển ghim.
        function requestGpsLocate($btn) {
            if (!navigator.geolocation) {
                if (window.toastr) toastr.warning('Trình duyệt không hỗ trợ định vị');
                return;
            }
            const old = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            navigator.geolocation.getCurrentPosition(function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                // Đảm bảo map sẵn sàng rồi ghim + reverse (Nominatim OSM).
                const after = function () {
                    setMapPoint(lat, lng, true);
                    onMarkerMoved(lat, lng); // reverse → điền địa chỉ + dropdown
                    if (window.toastr) toastr.success('Đã ghim vị trí của bạn');
                    $btn.prop('disabled', false).html(old);
                };
                if (locMap) after();
                else loadLeaflet().then(function () { initLocMap(); setTimeout(after, 300); })
                                  .catch(function () { $btn.prop('disabled', false).html(old); if (window.toastr) toastr.error('Không tải được bản đồ'); });
            }, function(err) {
                $btn.prop('disabled', false).html(old);
                const msg = err && err.code === 1 ? 'Bạn đã từ chối quyền truy cập vị trí' : 'Không lấy được vị trí hiện tại';
                if (window.toastr) toastr.warning(msg);
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        }
        $gpsBtn.on('click', function() { requestGpsLocate($(this)); });
        $('#locMapGpsBtn').on('click', function() { requestGpsLocate($(this)); });

        // ===== Bản đồ ghim vị trí giao hàng (Leaflet + OpenStreetMap, miễn phí, không cần API key) =====
        let locMap = null, locMarker = null, mapLibLoading = null, reverseTimer = null;
        const DEFAULT_CENTER = { lat: 10.7769, lng: 106.7009 }; // TP.HCM mặc định
        const $locLat = $('#locLat'), $locLng = $('#locLng');
        // true = đang dùng Goong Maps GL JS, false = Leaflet OSM fallback
        const USE_GOONG_MAP = !!(GOONG_MAP_KEY && GOONG_MAP_KEY.length > 8);

        function setLatLngInputs(lat, lng) {
            $locLat.val(lat != null ? Number(lat).toFixed(7) : '');
            $locLng.val(lng != null ? Number(lng).toFixed(7) : '');
        }

        // Đặt ghim + (tuỳ chọn) di chuyển bản đồ tới toạ độ.
        function setMapPoint(lat, lng, pan) {
            lat = Number(lat); lng = Number(lng);
            if (!isFinite(lat) || !isFinite(lng) || (lat === 0 && lng === 0)) return;
            setLatLngInputs(lat, lng);
            if (!locMap || !locMarker) return;

            if (USE_GOONG_MAP) {
                // Goong Maps dùng [lng, lat] (Mapbox GL style)
                locMarker.setLngLat([lng, lat]);
                if (pan !== false) {
                    const z = locMap.getZoom();
                    locMap.flyTo({ center: [lng, lat], zoom: z < 16 ? 17 : z, speed: 1.4 });
                }
            } else {
                // Leaflet dùng [lat, lng]
                locMarker.setLatLng([lat, lng]);
                if (pan !== false) {
                    const z = locMap.getZoom();
                    locMap.setView([lat, lng], z < 16 ? 17 : z);
                }
            }
        }

        // ─── Tải Goong Maps JS SDK (lazy, 1 lần) ───
        function loadGoongMap() {
            if (window.goongjs) return Promise.resolve();
            if (mapLibLoading) return mapLibLoading;
            mapLibLoading = new Promise(function (resolve, reject) {
                if (!document.getElementById('goong-js-css')) {
                    const link = document.createElement('link');
                    link.id = 'goong-js-css';
                    link.rel = 'stylesheet';
                    link.href = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.css';
                    document.head.appendChild(link);
                }
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.js';
                s.async = true;
                s.onload = function () { resolve(); };
                s.onerror = function () { reject(new Error('goong-load-failed')); };
                document.head.appendChild(s);
            });
            return mapLibLoading;
        }

        // ─── Tải Leaflet (fallback khi không có Goong Map key) ───
        function loadLeaflet() {
            if (window.L && window.L.map) return Promise.resolve();
            if (mapLibLoading) return mapLibLoading;
            mapLibLoading = new Promise(function (resolve, reject) {
                if (!document.getElementById('leaflet-css')) {
                    const link = document.createElement('link');
                    link.id = 'leaflet-css';
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(link);
                }
                const s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                s.async = true;
                s.onload = function () { resolve(); };
                s.onerror = function () { reject(new Error('leaflet-load-failed')); };
                document.head.appendChild(s);
            });
            return mapLibLoading;
        }

        // Kéo/chạm bản đồ → reverse geocode → điền địa chỉ + dropdown.
        function onMarkerMoved(lat, lng) {
            setLatLngInputs(lat, lng);
            if (reverseTimer) { clearTimeout(reverseTimer); reverseTimer = null; }
            reverseTimer = setTimeout(function () {
                $.get(AI_ENDPOINT, { ajax: 'geo_reverse', lat: lat, lng: lng }, function (res) {
                    if (res && res.ok && res.data) {
                        applyAiAddressSuggestion(res.data, { fullStreet: true });
                    }
                    setLatLngInputs(lat, lng); // giữ toạ độ theo ghim
                });
            }, 300);
        }

        // ─── Khởi tạo Goong Maps GL JS ───
        function initGoongMap(el, lat0, lng0) {
            if (locMap) {
                locMap.resize();
                return;
            }
            const hasSaved = isFinite(lat0) && isFinite(lng0) && !(lat0 === 0 && lng0 === 0);
            const centerLng = hasSaved ? lng0 : DEFAULT_CENTER.lng;
            const centerLat = hasSaved ? lat0 : DEFAULT_CENTER.lat;

            goongjs.accessToken = GOONG_MAP_KEY;
            locMap = new goongjs.Map({
                container: el,
                style: 'https://tiles.goong.io/assets/goong_map_web.json?api_key=' + GOONG_MAP_KEY,
                center: [centerLng, centerLat],
                zoom: hasSaved ? 17 : 12,
            });

            // Marker kéo được
            locMarker = new goongjs.Marker({ color: '#0c4c29', draggable: true })
                .setLngLat([centerLng, centerLat])
                .addTo(locMap);

            if (hasSaved) setLatLngInputs(lat0, lng0);

            locMarker.on('dragend', function () {
                const p = locMarker.getLngLat();
                onMarkerMoved(p.lat, p.lng);
            });

            locMap.on('click', function (e) {
                locMarker.setLngLat([e.lngLat.lng, e.lngLat.lat]);
                onMarkerMoved(e.lngLat.lat, e.lngLat.lng);
            });

            setTimeout(function () { locMap.resize(); }, 150);
        }

        // ─── Khởi tạo Leaflet (fallback) ───
        function initLeafletMap(el, lat0, lng0) {
            if (locMap) { locMap.invalidateSize(); return; }
            const hasSaved = isFinite(lat0) && isFinite(lng0) && !(lat0 === 0 && lng0 === 0);
            const center = hasSaved ? [lat0, lng0] : [DEFAULT_CENTER.lat, DEFAULT_CENTER.lng];

            locMap = L.map(el, { zoomControl: true }).setView(center, hasSaved ? 17 : 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap'
            }).addTo(locMap);

            locMarker = L.marker(center, { draggable: true }).addTo(locMap);
            if (hasSaved) setLatLngInputs(lat0, lng0);

            locMarker.on('dragend', function () {
                const p = locMarker.getLatLng();
                onMarkerMoved(p.lat, p.lng);
            });
            locMap.on('click', function (e) {
                locMarker.setLatLng(e.latlng);
                onMarkerMoved(e.latlng.lat, e.latlng.lng);
            });
            setTimeout(function () { locMap.invalidateSize(); }, 150);
        }

        function initLocMap() {
            const el = document.getElementById('locMap');
            if (!el) return;
            const lat0 = parseFloat($locLat.val()), lng0 = parseFloat($locLng.val());

            if (USE_GOONG_MAP) {
                loadGoongMap().then(function () {
                    initGoongMap(el, lat0, lng0);
                }).catch(function () {
                    // fallback về Leaflet nếu Goong JS tải thất bại
                    loadLeaflet().then(function () { initLeafletMap(el, lat0, lng0); })
                        .catch(function () {
                            el.innerHTML = '<div class="p-3 text-muted small">Không tải được bản đồ.</div>';
                        });
                });
            } else {
                loadLeaflet().then(function () {
                    initLeafletMap(el, lat0, lng0);
                }).catch(function () {
                    el.innerHTML = '<div class="p-3 text-muted small">Không tải được bản đồ. Vui lòng kiểm tra kết nối mạng.</div>';
                });
            }
        }

        // Khởi tạo map khi modal đã hiển thị (cần container có kích thước).
        $('#addrxModal').on('shown.bs.modal', function () {
            initLocMap();
            if (locMap) setTimeout(function () {
                USE_GOONG_MAP ? locMap.resize() : locMap.invalidateSize();
            }, 150);
        });

        $street.on('input', function() {
            const val = String($(this).val() || '').trim();
            if ($countHint.length) {
                $countHint.toggleClass('d-none', !val);
            }

            if (!$streetSuggestList.length) return;

            if (streetSuggestTimer) {
                clearTimeout(streetSuggestTimer);
                streetSuggestTimer = null;
            }

            if (!val || val.length < 3) {
                $streetSuggestList.empty().addClass('d-none');
                return;
            }

            streetSuggestTimer = setTimeout(function() {
                const keyword = val.toLowerCase();
                // 1) Ưu tiên gợi ý từ địa chỉ đã lưu của user.
                const local = savedLocations.map(function(loc) {
                    const streetLine = String(loc.street || '').trim();
                    const areaLine = [loc.ward, loc.district, loc.province].filter(Boolean).join(', ');
                    const full = [streetLine, areaLine].filter(Boolean).join(', ');
                    return {
                        full: full,
                        street: streetLine,
                        ward: String(loc.ward || ''),
                        district: String(loc.district || ''),
                        province: String(loc.province || '')
                    };
                }).filter(function(item) {
                    return String(item.full || '').toLowerCase().includes(keyword);
                });

                if (local.length) {
                    renderStreetSuggestions(local);
                    return;
                }

                // 2) Gợi ý địa chỉ mới qua Nominatim OSM (geo_search) — miễn phí, không cần key.
                $.get(AI_ENDPOINT, { ajax: 'geo_search', q: val }, function(res) {
                    if (res && res.ok && Array.isArray(res.data)) {
                        renderStreetSuggestions(res.data);
                    } else {
                        renderStreetSuggestions([]);
                    }
                }).fail(function() {
                    renderStreetSuggestions([]);
                });
            }, 500);
        });

        $saved.on('click', '[data-addrx-action]', function() {
            const action = String($(this).data('addrx-action') || '');
            const $item = $(this).closest('.addrx-card');
            const id = String($item.data('address-id') || '');
            if (!id) return;

            const targetLoc = savedLocations.find(function(loc) {
                return String(loc.address_id || '') === id;
            }) || null;

            if (action === 'edit') {
                fillFormFromSavedLocation(targetLoc);
                return;
            }

            if (action !== 'delete' && action !== 'set_default') {
                return;
            }

            const payload = new URLSearchParams({
                action: action === 'delete' ? 'delete_address' : 'set_active',
                address_id: id,
                region: selectedRegion
            });
            fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: payload.toString()
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    if (!res || !res.ok) throw new Error(res?.message || 'Không thể cập nhật địa chỉ');
                    if (action === 'delete') {
                        savedLocations = Array.isArray(res.saved_locations) ? res.saved_locations : [];
                        renderSavedLocations();
                        flash('success', 'Đã xoá địa chỉ.');
                    } else {
                        $addressId.val(id);
                        loadOptions(selectedRegion);
                        flash('success', 'Đã áp dụng địa chỉ mặc định.');
                    }
                })
                .catch(function(err) {
                    flash('danger', err?.message || 'Không thể cập nhật địa chỉ');
                });
        });

        $addNewBtn.on('click', function() {
            if (savedLocations.length >= 5) {
                flash('danger', 'Bạn đã đạt tối đa 5 địa chỉ. Vui lòng xoá bớt địa chỉ cũ.');
                return;
            }
            resetAddressForm();
            openEditor();
            flash('info', 'Nhập thông tin để thêm địa chỉ mới.');
        });

        $modalCloseBtn.on('click', function() {
            closeEditor();
            renderDistanceHint({});
        });

        // Xử lý đóng modal Bootstrap (click backdrop hoặc Escape)
        document.getElementById('addrxModal').addEventListener('hidden.bs.modal', function() {
            renderDistanceHint({});
        });


        $form.on('submit', function(event) {
            event.preventDefault();

            const region = String(selectedRegion || '').trim();
            const streetText = String($street.val() || '').trim();
            const recipientNameText = String($recipientName.val() || '').trim();
            const addressTypeText = getAddressTypeValue();
            const phoneText = String($contactPhone.val() || '').trim();
            const deliveryNoteText = String($deliveryNote.val() || '').trim();
            const phoneDigits = phoneText.replace(/\D+/g, '');
            const provinceCode = String($province.val() || '').trim();
            const districtCode = String($district.val() || '').trim();
            const wardCode = String($ward.val() || '').trim();
            const provinceName = getSelectedProvinceName();
            const districtName = getSelectedDistrictName();
            const wardName = getSelectedWardName();
            if (!streetText) {
                flash('danger', 'Vui lòng nhập địa chỉ giao hàng');
                return;
            }
            if (!recipientNameText) {
                flash('danger', 'Vui lòng nhập tên người nhận');
                return;
            }
            const phoneRegex = /^(0|84|\+84)[35789]\d{8}$/;
            if (!phoneText || !phoneRegex.test(phoneText.replace(/\s+/g, ''))) {
                flash('danger', 'Số điện thoại không hợp lệ (phải là số điện thoại Việt Nam 10 chữ số)');
                return;
            }
            if (!provinceCode) {
                flash('danger', 'Vui lòng chọn tỉnh / thành phố');
                return;
            }
            if (!districtCode) {
                flash('danger', 'Vui lòng chọn quận / huyện');
                return;
            }
            if (!wardCode) {
                flash('danger', 'Vui lòng chọn phường / xã');
                return;
            }

            const payload = new URLSearchParams({
                action: 'save_address',
                address_id: String($addressId.val() || ''),
                region: region,
                branch_id: '0',
                street: streetText,
                province_id: String($province.val() || ''),
                district_id: String($district.val() || ''),
                ward_code: String($ward.val() || ''),
                ward: wardName,
                district: districtName,
                province: provinceName,
                contact_phone: phoneText,
                recipient_name: recipientNameText,
                address_type: addressTypeText,
                delivery_note: deliveryNoteText,
                customer_lat: String($('#locLat').val() || ''),
                customer_lng: String($('#locLng').val() || ''),
            });

            $saveBtn.prop('disabled', true);

            fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: payload.toString()
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Không thể lưu vị trí giao hàng');
                    return response.json();
                })
                .then(function(result) {
                    if (!result || !result.ok) {
                        throw new Error(result?.message || 'Không thể lưu vị trí giao hàng');
                    }
                    $addressId.val(String(result.location?.address_id || ''));
                    renderDistanceHint(result.location || {});
                    loadOptions(region);
                    flash('success', 'Đã lưu vị trí giao hàng.');
                    closeEditor();
                })
                .catch(function(err) {
                    flash('danger', err?.message || 'Không thể lưu vị trí giao hàng');
                })
                .finally(function() {
                    $saveBtn.prop('disabled', false);
                });
        });

        $(document).on('click', '.addrx-suggest-item', function() {
            const lat = $(this).data('lat');
            const lng = $(this).data('lng');
            const placeId = String($(this).data('place-id') || '');
            const item = {
                full: $(this).data('full') || '',
                street: $(this).data('street') || '',
                ward: $(this).data('ward') || '',
                district: $(this).data('district') || '',
                province: $(this).data('province') || '',
                lat: lat,
                lng: lng
            };
            if ($streetSuggestList.length) {
                $streetSuggestList.empty().addClass('d-none');
            }

            // Gợi ý Goong (có place_id, chưa kèm toạ độ) → gọi Place Detail lấy toạ độ + khu vực đầy đủ.
            const hasCoord = (lat !== undefined && lat !== '' && lng !== undefined && lng !== '');
            if (placeId && !hasCoord) {
                $.get(AI_ENDPOINT, { ajax: 'geo_place_detail', place_id: placeId }, function(res) {
                    if (res && res.ok && res.data) {
                        // street rỗng → dùng main_text đã gõ trước đó (giữ tên đường gọn).
                        if (!res.data.street) res.data.street = item.street;
                        applyAiAddressSuggestion(res.data, {});
                    } else {
                        applyAiAddressSuggestion(item, {}); // fallback: ít nhất điền tên đường
                    }
                }).fail(function() { applyAiAddressSuggestion(item, {}); });
                return;
            }

            // Gợi ý đã đủ dữ liệu (Nominatim hoặc địa chỉ đã lưu) → apply trực tiếp.
            applyAiAddressSuggestion(item, {});
        });

        $(document).on('click', function(e) {
            if (!$streetSuggestList.length) return;
            const $target = $(e.target);
            if ($target.closest('#locStreet').length || $target.closest('#locStreetSuggestList').length) {
                return;
            }
            $streetSuggestList.empty().addClass('d-none');
        });

        // Hiển thị skeleton lần đầu trước khi tải danh sách địa chỉ
        $saved.html(addressSkeletonHtml);
        loadProvinces('<?= h($selectedProvince) ?>');
        loadOptions(selectedRegion).catch(function(err) {
            flash('danger', err?.message || 'Không tải được dữ liệu vị trí giao hàng');
        });
    }

    function updateSnapshots(data) {
        if (data.display_name) {
            const el = document.querySelector('[data-summary="display"]');
            if (el) el.textContent = data.display_name;
        }
        if (data.username) {
            const el = document.querySelector('[data-summary="username"]');
            if (el) el.textContent = '@' + data.username;
        }
        if ('email' in data) {
            const el = document.querySelector('[data-summary="email"]');
            if (el) el.textContent = data.email || 'Chưa có';
        }
        if ('phone' in data) {
            const el = document.querySelector('[data-summary="phone"]');
            if (el) el.textContent = data.phone || 'Chưa có';
        }
        if ('address' in data) {
            const el = document.querySelector('[data-summary="address"]');
            if (el) el.textContent = data.address || 'Chưa có';
        }
    }

    function refreshAvatar(url) {
        document.querySelectorAll('[data-avatar-preview]').forEach(preview => {
            if (preview.classList.contains('profile-avatar-img')) {
                preview.style.backgroundImage = url ? `url('${url}')` : '';
                return;
            }
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Avatar';
            preview.innerHTML = '';
            preview.appendChild(img);
        });
    }

    const avatarInput = document.querySelector('[data-avatar-input]');
    const avatarTriggers = document.querySelectorAll('[data-avatar-trigger]');
    if (avatarInput && avatarTriggers.length) {
        avatarTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => avatarInput.click());
        });
    }
    if (avatarInput) {
        avatarInput.addEventListener('change', () => {
            const [file] = avatarInput.files || [];
            if (!file) return;
            const url = URL.createObjectURL(file);
            refreshAvatar(url);

            const form = avatarInput.closest('form');
            if (form) {
                const alertBox = form.closest('.account-sidebar')?.querySelector('[data-alert]') || form.querySelector('[data-alert]');
                showAlert(alertBox, 'info', 'Đang tải ảnh...');
                const fd = new FormData(form);
                fetch(form.getAttribute('data-endpoint'), {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (!json || json.ok !== true) {
                            showAlert(alertBox, 'danger', json?.msg || 'Không thể xử lý.');
                            return;
                        }
                        showAlert(alertBox, 'success', json.msg || 'Thành công.');
                        if (json.data?.avatar) {
                            refreshAvatar(json.data.avatar);
                        }
                    })
                    .catch(() => showAlert(alertBox, 'danger', 'Lỗi kết nối, thử lại.'));
            }
        });
    }

    const notificationSkeletonHtml = [1, 2, 3].map(() => `
        <div class="notify-card notify-item">
            <div class="notify-card-media">
                <div class="notify-card-thumb skeleton-block"></div>
            </div>
            <div class="notify-card-main">
                <div class="skeleton-line skeleton-line--md" style="max-width:80%"></div>
                <div class="skeleton-line skeleton-line--sm" style="max-width:95%;margin-top:6px;"></div>
                <div class="skeleton-line skeleton-line--sm" style="width:120px;margin-top:8px;"></div>
            </div>
            <div class="notify-card-actions">
                <div class="skeleton-line skeleton-line--sm" style="width:90px;"></div>
            </div>
        </div>
    `).join('');

    function escapeHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escapeMultiline(s) {
        return escapeHtml(s).replace(/\n/g, '<br>');
    }

    function stripHtml(html) {
        return String(html || '')
            .replace(/<\/?[^>]+(>|$)/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function truncateText(text, maxLength = 150) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength).trim() + '...';
    }

    function escapeAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Slug hoá tiêu đề tiếng Việt cho URL SEO
    function nfSlugify(text) {
        const map = {
            'à': 'a',
            'á': 'a',
            'ạ': 'a',
            'ả': 'a',
            'ã': 'a',
            'â': 'a',
            'ầ': 'a',
            'ấ': 'a',
            'ậ': 'a',
            'ẩ': 'a',
            'ẫ': 'a',
            'ă': 'a',
            'ằ': 'a',
            'ắ': 'a',
            'ặ': 'a',
            'ẳ': 'a',
            'ẵ': 'a',
            'è': 'e',
            'é': 'e',
            'ẹ': 'e',
            'ẻ': 'e',
            'ẽ': 'e',
            'ê': 'e',
            'ề': 'e',
            'ế': 'e',
            'ệ': 'e',
            'ể': 'e',
            'ễ': 'e',
            'ì': 'i',
            'í': 'i',
            'ị': 'i',
            'ỉ': 'i',
            'ĩ': 'i',
            'ò': 'o',
            'ó': 'o',
            'ọ': 'o',
            'ỏ': 'o',
            'õ': 'o',
            'ô': 'o',
            'ồ': 'o',
            'ố': 'o',
            'ộ': 'o',
            'ổ': 'o',
            'ỗ': 'o',
            'ơ': 'o',
            'ờ': 'o',
            'ớ': 'o',
            'ợ': 'o',
            'ở': 'o',
            'ỡ': 'o',
            'ù': 'u',
            'ú': 'u',
            'ụ': 'u',
            'ủ': 'u',
            'ũ': 'u',
            'ư': 'u',
            'ừ': 'u',
            'ứ': 'u',
            'ự': 'u',
            'ử': 'u',
            'ữ': 'u',
            'ỳ': 'y',
            'ý': 'y',
            'ỵ': 'y',
            'ỷ': 'y',
            'ỹ': 'y',
            'đ': 'd'
        };
        let s = String(text || '').toLowerCase();
        s = s.replace(/[À-ỹ]/g, ch => map[ch] || ch);
        s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (s.length > 80) s = s.substring(0, 80).replace(/-+$/, '');
        return s || 'thong-bao';
    }

    function buildNoticeUrl(id, title) {
        const pid = parseInt(id, 10) || 0;
        if (pid <= 0) return '/view-notification';
        return '/view-notification/' + nfSlugify(title) + '-' + pid;
    }

    function initGenericNotificationsPanel(config) {
        const {
            section,
            source,
            metaId,
            listId,
            prevBtnId,
            nextBtnId,
            pageInfoId,
            markAllId,
            filterAttr,
            menuSection
        } = config;
        const root = document.querySelector(`.account-section[data-section="${section}"]`);
        if (!root) return;

        const listEl = root.querySelector(`#${listId}`);
        const metaEl = document.getElementById(metaId);
        const btnMarkAll = document.getElementById(markAllId);
        const filterButtons = Array.from(root.querySelectorAll(`[${filterAttr}]`));
        const prevBtn = document.getElementById(prevBtnId);
        const nextBtn = document.getElementById(nextBtnId);
        const pageInfoEl = document.getElementById(pageInfoId);
        const API = '<?= h($baseUrl) ?>/core/ajax/notification.php';

        if (!listEl || !metaEl) return;

        let currentFilter = 'all';
        const pageSize = 4;
        let currentPage = 1;
        let allRows = [];

        function updateMenuBadge(unread) {
            const menuLink = document.querySelector(`.sidebar-menu [data-section="${menuSection || section}"]`);
            if (!menuLink) return;
            let badge = menuLink.querySelector('.menu-notify-badge');
            if (!badge && Number(unread) > 0) {
                badge = document.createElement('span');
                badge.className = 'menu-notify-badge';
                menuLink.appendChild(badge);
            }
            if (badge) {
                if (Number(unread) > 0) {
                    const n = Number(unread);
                    badge.textContent = n > 99 ? '99+' : n;
                    menuLink.classList.add('has-notify-badge');
                } else {
                    badge.remove();
                    menuLink.classList.remove('has-notify-badge');
                }
            }
        }

        function setActiveFilter(filter) {
            currentFilter = filter || 'all';
            filterButtons.forEach(btn => {
                const key = btn.getAttribute(filterAttr) || 'all';
                btn.classList.toggle('active', key === currentFilter);
            });
        }

        function renderRows(rows) {
            if (!Array.isArray(rows) || rows.length === 0) {
                listEl.innerHTML = '<div class="bank-empty">Không có thông báo.</div>';
                metaEl.textContent = '0 thông báo';
                updateMenuBadge(0);
                return;
            }
            const html = rows.map(row => {
                const id = row.id || row.notice_id || row.notification_id || '';
                const title = row.title || '';
                const body = row.body_text || '';
                const time = row.created_at_fmt || row.created_at || '';
                const commentCount = Number(row.comment_count || 0);
                const lastCommentPreview = String(row.last_comment_preview || '');
                const readState = Number(row.read_state || 0);
                const unreadClass = readState === 0 ? ' bacc-card--unread' : '';
                const href = row.link || '';
                const thumbImage = row.thumb_image || '';
                const typeKey = String(row.type_resolved || row.type || '').toLowerCase();
                let typeClass = '';
                if (typeKey === 'order') typeClass = ' notify-card-thumb--order';
                else if (typeKey === 'payment') typeClass = ' notify-card-thumb--payment';
                else if (typeKey === 'security') typeClass = ' notify-card-thumb--security';
                else if (typeKey === 'account') typeClass = ' notify-card-thumb--account';
                else if (typeKey === 'system') typeClass = ' notify-card-thumb--system';
                else if (typeKey === 'promotion' || typeKey === 'promo') typeClass = ' notify-card-thumb--promotion';

                const thumbClass = (thumbImage ? ' notify-card-thumb--has-image' : '') + typeClass;
                const thumbStyle = thumbImage ? ` style="background-image:url('${escapeAttr(thumbImage)}');"` : '';
                const iconClassRaw = String(row.thumb_icon || '').trim();
                let iconClass = iconClassRaw;
                if (!iconClass) {
                    if (typeKey === 'order') iconClass = 'bi bi-bag-check-fill';
                    else if (typeKey === 'payment') iconClass = 'bi bi-credit-card-2-front-fill';
                    else if (typeKey === 'security') iconClass = 'bi bi-shield-lock-fill';
                    else if (typeKey === 'account') iconClass = 'bi bi-person-badge-fill';
                    else if (typeKey === 'system') iconClass = 'bi bi-megaphone-fill';
                    else if (typeKey === 'promotion' || typeKey === 'promo') iconClass = 'bi bi-gift-fill';
                    else iconClass = 'bi bi-bell-fill';
                }
                const rowSource = row.table_source || 'system';
                const hideDetailBtn = (rowSource === 'system' && !href);
                return `
                    <div class="notify-card notify-item${unreadClass}" data-id="${id}" data-href="${href}" data-source="${rowSource}" data-title="${escapeAttr(title || '')}">
                        <div class="notify-card-media">
                            <div class="notify-card-thumb${thumbClass}"${thumbStyle}>${iconClass ? `<i class="${escapeAttr(iconClass)}"></i>` : ''}</div>
                        </div>
                        <div class="notify-card-main">
                            <h2 class="notify-card-title">${escapeHtml(title || 'Thông báo')}</h2>
                            <div class="notify-card-body">${(typeKey === 'promotion' || typeKey === 'promo' || rowSource === 'promo') ? escapeHtml(truncateText(stripHtml(body), 150)) : escapeMultiline(body || '')}</div>
                            ${commentCount > 0 && lastCommentPreview ? `<div class="notify-card-comment"><i class="bi bi-chat-dots"></i> ${escapeMultiline(lastCommentPreview)}</div>` : ''}
                            <p class="notify-card-time">${escapeHtml(time)}${commentCount > 0 ? escapeHtml(` · ${commentCount} bình luận`) : ''}</p>
                        </div>
                        <div class="notify-card-actions">
                            ${!hideDetailBtn ? '<button type="button" class="notify-card-btn">Xem chi tiết</button>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
            listEl.innerHTML = html;
        }

        function renderPage(page) {
            if (!Array.isArray(allRows)) allRows = [];
            const total = allRows.length;
            const maxPage = total > 0 ? Math.ceil(total / pageSize) : 1;
            if (page < 1) page = 1;
            if (page > maxPage) page = maxPage;
            currentPage = page;
            const start = (currentPage - 1) * pageSize;
            const slice = allRows.slice(start, start + pageSize);
            renderRows(slice);
            if (pageInfoEl) {
                pageInfoEl.textContent = total > 0 ? `Trang ${currentPage}/${maxPage}` : 'Không có dữ liệu';
            }
            metaEl.textContent = total > 0 ? `${total} thông báo` : 'Không có thông báo';
            if (prevBtn) prevBtn.disabled = currentPage <= 1 || total === 0;
            if (nextBtn) nextBtn.disabled = currentPage >= maxPage || total === 0;
        }

        function loadList(filter = 'all') {
            setActiveFilter(filter || 'all');
            metaEl.textContent = 'Đang tải...';
            listEl.innerHTML = notificationSkeletonHtml;
            const params = new URLSearchParams();
            params.set('action', 'list');
            params.set('limit', '60');
            params.set('source', source);

            let clientTypeResolved = null;
            if (filter === 'unread') {
                params.set('unread', '1');
            } else if (filter === 'order') {
                params.set('type', 'order');
            } else if (filter === 'account') {
                clientTypeResolved = 'account';
            }

            fetch(API + '?' + params.toString(), {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(json => {
                    if (!json || json.ok !== true) {
                        listEl.innerHTML = '<div class="bank-empty">Không thể tải thông báo.</div>';
                        metaEl.textContent = 'Lỗi tải dữ liệu';
                        allRows = [];
                        renderPage(1);
                        return;
                    }
                    let rows = Array.isArray(json.data) ? json.data : [];
                    if (clientTypeResolved) {
                        const key = clientTypeResolved;
                        rows = rows.filter(r => String(r.type_resolved || r.type || '').toLowerCase() === key);
                    }
                    const unreadCount = Number(json.unread || 0);
                    updateMenuBadge(unreadCount);
                    allRows = rows;
                    renderPage(1);
                })
                .catch(() => {
                    listEl.innerHTML = '<div class="bank-empty">Không thể kết nối server.</div>';
                    metaEl.textContent = 'Lỗi kết nối';
                    allRows = [];
                    renderPage(1);
                });
        }

        listEl.addEventListener('click', function(e) {
            const btn = e.target.closest('.notify-card-btn');
            const item = e.target.closest('.notify-item');
            if (!item) return;
            const id = item.getAttribute('data-id');
            if (!id) return;
            const payload = new URLSearchParams();
            payload.set('action', 'mark_read');
            payload.set('id', id);
            fetch(API, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: payload.toString()
                })
                .then(r => r.json())
                .then(json => {
                    if (json && json.ok) {
                        item.classList.remove('bacc-card--unread');
                        if (typeof json.unread !== 'undefined') {
                            updateMenuBadge(Number(json.unread || 0));
                        }
                        if (currentFilter === 'unread') {
                            item.remove();
                            const remaining = listEl.querySelectorAll('.notify-item').length;
                            if (remaining === 0) {
                                listEl.innerHTML = '<div class="bank-empty">Không có thông báo.</div>';
                                metaEl.textContent = '0 thông báo';
                            } else {
                                metaEl.textContent = remaining + ' thông báo';
                            }
                        }
                    }
                    if (btn) {
                        const hrefRaw = item.getAttribute('data-href');
                        const rowSource = item.getAttribute('data-source');
                        if (hrefRaw && hrefRaw.trim()) {
                            window.location.href = hrefRaw;
                        } else if (rowSource === 'promo') {
                            window.location.href = buildNoticeUrl(id, item.getAttribute('data-title') || '');
                        }
                    }
                }).catch(() => {
                    if (btn) {
                        const hrefRaw = item.getAttribute('data-href');
                        const rowSource = item.getAttribute('data-source');
                        if (hrefRaw && hrefRaw.trim()) {
                            window.location.href = hrefRaw;
                        } else if (rowSource === 'promo') {
                            window.location.href = buildNoticeUrl(id, item.getAttribute('data-title') || '');
                        }
                    }
                });
        });

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.getAttribute(filterAttr) || 'all';
                loadList(key);
            });
        });

        prevBtn?.addEventListener('click', () => {
            if (currentPage > 1) renderPage(currentPage - 1);
        });

        nextBtn?.addEventListener('click', () => {
            renderPage(currentPage + 1);
        });

        btnMarkAll?.addEventListener('click', () => {
            if (!confirm('Đánh dấu tất cả thông báo đã đọc?')) return;
            const payload = new URLSearchParams();
            payload.set('action', 'mark_all');
            fetch(API, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: payload.toString()
                })
                .then(r => r.json())
                .then(json => {
                    if (json && json.ok) {
                        listEl.querySelectorAll('.notify-item').forEach(el => {
                            el.classList.remove('bacc-card--unread');
                        });
                        if (typeof json.unread !== 'undefined') {
                            updateMenuBadge(Number(json.unread || 0));
                        }
                        if (currentFilter === 'unread') {
                            listEl.innerHTML = '<div class="bank-empty">Không có thông báo.</div>';
                            metaEl.textContent = '0 thông báo';
                        } else {
                            const total = listEl.querySelectorAll('.notify-item').length;
                            metaEl.textContent = total + ' thông báo';
                        }
                    }
                }).catch(() => {});
        });

        loadList('all');
    }

    function initNotificationsPanel() {
        if (initNotificationsPanel._initialized) return;
        initNotificationsPanel._initialized = true;
        initGenericNotificationsPanel({
            section: 'notifications',
            source: 'system',
            metaId: 'notificationsMeta',
            listId: 'notificationsList',
            prevBtnId: 'notificationsPrevBtn',
            nextBtnId: 'notificationsNextBtn',
            pageInfoId: 'notificationsPageInfo',
            markAllId: 'notifyBtnMarkAll',
            filterAttr: 'data-notify-filter'
        });
    }

    function initPromosPanel() {
        if (initPromosPanel._initialized) return;
        initPromosPanel._initialized = true;
        initGenericNotificationsPanel({
            section: 'promos',
            source: 'promo',
            metaId: 'promosMeta',
            listId: 'promosList',
            prevBtnId: 'promosPrevBtn',
            nextBtnId: 'promosNextBtn',
            pageInfoId: 'promosPageInfo',
            markAllId: 'promosBtnMarkAll',
            filterAttr: 'data-promo-filter'
        });
    }

    function initSystemNotificationsPanel() {
        if (initSystemNotificationsPanel._initialized) return;
        initSystemNotificationsPanel._initialized = true;
        const root = document.querySelector('.account-section[data-section="system_notifications"]');
        if (!root) return;

        const listEl = root.querySelector('#systemNotificationsList');
        const metaEl = document.getElementById('systemNotificationsMeta');
        const filterButtons = Array.from(root.querySelectorAll('[data-system-filter]'));
        const prevBtn = root.querySelector('#systemNotificationsPrevBtn');
        const nextBtn = root.querySelector('#systemNotificationsNextBtn');
        const pageInfoEl = root.querySelector('#systemNotificationsPageInfo');
        const API = '<?= h($baseUrl) ?>/core/ajax/notification.php';
        let currentFilter = 'all';
        let allItems = [];
        const pageSize = 4;
        let currentPage = 1;

        const systemSkeletonHtml = [1, 2, 3].map(() => `
        <div class="notify-card">
            <div class="notify-card-media">
                <div class="notify-card-thumb skeleton-block"></div>
            </div>
            <div class="notify-card-main">
                <div class="skeleton-line skeleton-line--md" style="max-width:80%"></div>
                <div class="skeleton-line skeleton-line--sm" style="max-width:95%;margin-top:6px;"></div>
                <div class="skeleton-line skeleton-line--sm" style="width:120px;margin-top:8px;"></div>
            </div>
            <div class="notify-card-actions">
                <div class="skeleton-line skeleton-line--sm" style="width:90px;"></div>
            </div>
        </div>
    `).join('');

        function setActiveSystemFilter(filter) {
            currentFilter = filter || 'all';
            filterButtons.forEach(btn => {
                const key = btn.getAttribute('data-system-filter') || 'all';
                btn.classList.toggle('active', key === currentFilter);
            });
        }

        function renderSystemItems(items) {
            if (!Array.isArray(items) || items.length === 0) {
                listEl.innerHTML = '<div class="bank-empty">Không có thông báo hệ thống.</div>';
                if (metaEl) metaEl.textContent = '0 thông báo';
                return;
            }
            if (metaEl) metaEl.textContent = items.length + ' thông báo';

            const iconFor = (kind) => {
                kind = String(kind || '').toLowerCase();
                if (kind === 'noti_comment') return 'bi bi-chat-dots-fill';
                if (kind === 'product_review') return 'bi bi-star-fill';
                if (kind === 'order_review') return 'bi bi-receipt';
                return 'bi bi-megaphone-fill';
            };

            const html = items.map(it => {
                const title = String(it?.title || '').trim() || 'Thông báo hệ thống';
                const sub = String(it?.sub || '').trim();
                const body = String(it?.content || '').trim();
                const time = String(it?.created_at_fmt || it?.created_at || '').trim();
                const href = String(it?.href || '').trim();
                const icon = iconFor(it?.kind);
                return `
                <div class="notify-card system-notify-item" data-href="${escapeAttr(href)}">
                    <div class="notify-card-media">
                        <div class="notify-card-thumb notify-card-thumb--system"><i class="${escapeAttr(icon)}"></i></div>
                    </div>
                    <div class="notify-card-main">
                        <h2 class="notify-card-title">${escapeHtml(title)}</h2>
                        ${body ? `<div class="notify-card-body">${escapeMultiline(body)}</div>` : ''}
                        <p class="notify-card-time">${escapeHtml(time)}${sub ? ' · ' + escapeHtml(sub) : ''}</p>
                    </div>
                    <div class="notify-card-actions">
                        <button type="button" class="notify-card-btn">Xem</button>
                    </div>
                </div>
            `;
            }).join('');
            listEl.innerHTML = html;
        }

        function applySystemFilter(filter) {
            setActiveSystemFilter(filter);
            if (!Array.isArray(allItems) || allItems.length === 0) {
                renderSystemItems([]);
                return;
            }
            currentPage = 1;
            renderSystemPage(currentPage);
        }

        function getFilteredSystemItems() {
            if (!Array.isArray(allItems)) return [];
            let items = allItems;
            const f = currentFilter;
            if (f === 'product_review') {
                items = allItems.filter(it => String(it.kind || '').toLowerCase() === 'product_review');
            } else if (f === 'order_review') {
                items = allItems.filter(it => String(it.kind || '').toLowerCase() === 'order_review');
            } else if (f === 'comment') {
                items = allItems.filter(it => String(it.kind || '').toLowerCase() === 'noti_comment');
            }
            return items;
        }

        function renderSystemPage(page) {
            const items = getFilteredSystemItems();
            const total = items.length;
            const maxPage = total > 0 ? Math.ceil(total / pageSize) : 1;
            if (page < 1) page = 1;
            if (page > maxPage) page = maxPage;
            currentPage = page;
            const start = (currentPage - 1) * pageSize;
            const slice = items.slice(start, start + pageSize);
            renderSystemItems(slice);
            if (pageInfoEl) {
                pageInfoEl.textContent = total > 0 ? `Trang ${currentPage}/${maxPage}` : 'Không có dữ liệu';
            }
            if (prevBtn) prevBtn.disabled = currentPage <= 1 || total === 0;
            if (nextBtn) nextBtn.disabled = currentPage >= maxPage || total === 0;
        }

        function loadSystemFeed() {
            if (metaEl) metaEl.textContent = 'Đang tải...';
            listEl.innerHTML = systemSkeletonHtml;
            fetch(API + '?action=system_feed&limit=60&offset=0', {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(json => {
                    if (!json || json.ok !== true) {
                        listEl.innerHTML = '<div class="bank-empty">Không thể tải thông báo hệ thống.</div>';
                        if (metaEl) metaEl.textContent = json?.msg || 'Lỗi tải dữ liệu';
                        return;
                    }
                    allItems = Array.isArray(json.items) ? json.items : [];
                    applySystemFilter(currentFilter);
                })
                .catch(() => {
                    listEl.innerHTML = '<div class="bank-empty">Không thể kết nối server.</div>';
                    if (metaEl) metaEl.textContent = 'Lỗi kết nối';
                });
        }

        listEl?.addEventListener('click', function(e) {
            const item = e.target.closest('.system-notify-item');
            if (!item) return;
            const href = item.getAttribute('data-href') || '';
            if (href) window.location.href = href;
        });

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.getAttribute('data-system-filter') || 'all';
                applySystemFilter(key);
            });
        });

        prevBtn?.addEventListener('click', () => {
            if (currentPage > 1) renderSystemPage(currentPage - 1);
        });

        nextBtn?.addEventListener('click', () => {
            renderSystemPage(currentPage + 1);
        });

        loadSystemFeed();
    }

    document.addEventListener('DOMContentLoaded', function() {
        initAccountSections();
        initSecurityFlow();
    });

    bindAjaxForms();
</script>