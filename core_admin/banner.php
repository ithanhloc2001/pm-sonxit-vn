<?php
require_once __DIR__ . '/_admin_guard.php';
?>

<div class="container-fluid py-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <a href="index.php" class="header-icon rounded-3 d-flex align-items-center justify-content-center text-decoration-none" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-images fs-4"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Quản lý Banner & SlideShow</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="bmMeta" style="font-size: 0.72rem;">0 slide</span>
                </div>
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Quản lý carousel chính, các banner quảng cáo trái/phải và popup nổi cho từng loại trang chủ.
                </p>
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Carousel, banner quảng cáo và popup nổi.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm" id="bmOpenCarouselModal" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-plus-lg fs-5"></i>
                <span class="d-none d-sm-inline">Thêm slide</span>
                <span class="d-inline d-sm-none">Thêm</span>
            </button>
        </div>
    </div>

    <!-- KPI SUMMARY CARDS -->
    <div class="mb-4 grid-4" id="summaryGrid">
        <div class="summary-card" data-bm-tab="all">
            <div class="d-flex flex-column">
                <span>Tổng slide</span>
                <strong class="mt-1" id="bmKpiSlides">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-collection-play-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-bm-tab="active">
            <div class="d-flex flex-column">
                <span>Đang hiển thị</span>
                <strong class="mt-1" id="bmKpiActive">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-eye-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-bm-tab="video">
            <div class="d-flex flex-column">
                <span>Slide video</span>
                <strong class="mt-1" id="bmKpiVideo">0</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-film fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-bm-tab="ads">
            <div class="d-flex flex-column">
                <span>Banner phụ bật</span>
                <strong class="mt-1" id="bmKpiAds">0/3</strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-megaphone-fill fs-5"></i>
            </div>
        </div>
    </div>
    <!-- PAGE KEY SWITCH -->
    <div class="mb-4">
        <div class="bm-page-switch nav nav-pills bg-light p-1 rounded-pill border d-inline-flex" role="tablist" aria-label="Chọn loại trang chủ" style="gap: 4px;">
            <button type="button" class="bm-page-opt nav-link rounded-pill py-2 px-4 fw-semibold active d-flex align-items-center" data-key="home_user" role="tab" aria-selected="true">
                <i class="bi bi-person-check-fill me-1.5"></i>Đã đăng nhập
            </button>
            <button type="button" class="bm-page-opt nav-link rounded-pill py-2 px-4 fw-semibold d-flex align-items-center" data-key="home_guest" role="tab" aria-selected="false">
                <i class="bi bi-person-fill me-1.5"></i>Chưa đăng nhập
            </button>
        </div>
        <input type="hidden" id="bmPageKey" value="home_user">
    </div>
    
    <style>
        .bm-page-switch {
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
        }
        .bm-page-switch .bm-page-opt {
            border: 0 !important;
            background: transparent !important;
            color: #64748b !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            padding: 8px 18px !important;
            border-radius: 999px !important;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .bm-page-switch .bm-page-opt:hover {
            color: #0f172a !important;
        }
        .bm-page-switch .bm-page-opt.active {
            background-color: var(--theme-primary, #0c4c29) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(12, 76, 41, 0.2) !important;
        }
    </style>

    <div class="row g-4">
        <!-- Carousel List -->
        <div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 rounded-3 d-inline-flex"><i class="bi bi-collection-play-fill"></i></span>
                        <h6 class="mb-0 fw-bold text-dark">SlideShow</h6>
                        <span class="text-muted small ms-1">Kéo–thả để sắp xếp</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-striped" id="bmCarouselTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center ps-3" style="width:40px;"></th>
                                <th style="width:110px;">Ảnh/Video</th>
                                <th>Tiêu đề &amp; Nội dung</th>
                                <th style="width:200px;">URL liên kết</th>
                                <th class="text-center" style="width:70px;">STT</th>
                                <th class="text-center" style="width:90px;">Hiển thị</th>
                                <th class="text-end pe-4" style="width:110px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Banners Quảng cáo & Popup -->
        <div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 rounded-3 d-inline-flex" style="background-color: rgba(12,76,41,.08); color: var(--theme-primary, #0c4c29);"><i class="bi bi-megaphone-fill"></i></span>
                        <h6 class="mb-0 fw-bold text-dark">Banner quảng cáo &amp; Popup</h6>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">

                        <!-- Left Ad Slot -->
                        <div class="bm-ad-slot p-3 rounded-3" style="background:#f8fafc; border:1px solid #eef2f7;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge" style="font-size:.62rem; padding:3px 8px; border-radius:6px; background:#eff6ff; color:#2563eb; border:1px solid #dbeafe;"><i class="bi bi-arrow-left-square me-1"></i>TRÁI</span>
                                    <span class="fw-bold small text-dark">Banner cột trái</span>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="bmLeftActive" checked>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start">
                                <div style="flex-shrink:0; width:90px;">
                                    <input id="bmLeftFile" type="file" accept="image/*" class="d-none">
                                    <label for="bmLeftFile" class="banner-preview d-flex align-items-center justify-content-center text-center text-secondary small" id="bmPreviewLeft" style="height:90px; width:90px; border-radius:10px; margin:0; background:#fff; border:1.5px dashed #cbd5e1; overflow:hidden; cursor:pointer;">
                                        Chưa có ảnh
                                    </label>
                                    <input type="hidden" id="bmLeftCurrent" value="">
                                </div>
                                <div class="flex-grow-1">
                                    <input id="bmLeftLink" type="text" class="form-control form-control-sm mb-2" placeholder="URL liên kết" style="border-radius:8px;">
                                    <button class="btn btn-sm btn-primary w-100" id="bmSaveLeft" style="border-radius:8px; font-weight:600;"><i class="bi bi-save me-1"></i>Lưu cấu hình</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Ad Slot -->
                        <div class="bm-ad-slot p-3 rounded-3" style="background:#f8fafc; border:1px solid #eef2f7;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge" style="font-size:.62rem; padding:3px 8px; border-radius:6px; background:#eff6ff; color:#2563eb; border:1px solid #dbeafe;"><i class="bi bi-arrow-right-square me-1"></i>PHẢI</span>
                                    <span class="fw-bold small text-dark">Banner cột phải</span>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="bmRightActive" checked>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start">
                                <div style="flex-shrink:0; width:90px;">
                                    <input id="bmRightFile" type="file" accept="image/*" class="d-none">
                                    <label for="bmRightFile" class="banner-preview d-flex align-items-center justify-content-center text-center text-secondary small" id="bmPreviewRight" style="height:90px; width:90px; border-radius:10px; margin:0; background:#fff; border:1.5px dashed #cbd5e1; overflow:hidden; cursor:pointer;">
                                        Chưa có ảnh
                                    </label>
                                    <input type="hidden" id="bmRightCurrent" value="">
                                </div>
                                <div class="flex-grow-1">
                                    <input id="bmRightLink" type="text" class="form-control form-control-sm mb-2" placeholder="URL liên kết" style="border-radius:8px;">
                                    <button class="btn btn-sm btn-primary w-100" id="bmSaveRight" style="border-radius:8px; font-weight:600;"><i class="bi bi-save me-1"></i>Lưu cấu hình</button>
                                </div>
                            </div>
                        </div>

                        <!-- Popup Ad Slot -->
                        <div class="bm-ad-slot p-3 rounded-3" style="background:#fff7ed; border:1px solid #ffedd5;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge" style="font-size:.62rem; padding:3px 8px; border-radius:6px; background:#fef2f2; color:#dc2626; border:1px solid #fecaca;"><i class="bi bi-stickies-fill me-1"></i>POPUP</span>
                                    <span class="fw-bold small text-dark">Popup nổi</span>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="bmPopupActive" checked>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start">
                                <div style="flex-shrink:0; width:90px;">
                                    <input id="bmPopupFile" type="file" accept="image/*" class="d-none">
                                    <label for="bmPopupFile" class="banner-preview d-flex align-items-center justify-content-center text-center text-secondary small" id="bmPreviewPopup" style="height:90px; width:90px; border-radius:10px; margin:0; background:#fff; border:1.5px dashed #f59e0b; overflow:hidden; cursor:pointer;">
                                        Chưa có ảnh
                                    </label>
                                    <input type="hidden" id="bmPopupCurrent" value="">
                                </div>
                                <div class="flex-grow-1">
                                    <input id="bmPopupLink" type="text" class="form-control form-control-sm mb-2" placeholder="URL liên kết" style="border-radius:8px;">
                                    <button class="btn btn-sm btn-primary w-100" id="bmSavePopup" style="border-radius:8px; font-weight:600;"><i class="bi bi-save me-1"></i>Lưu cấu hình</button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal cấu hình SlideShow -->
<div class="modal fade" id="bmCarouselModal" tabindex="-1" aria-labelledby="bmCarouselModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bmCarouselModalLabel">Thêm / Chỉnh sửa SlideShow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <input type="hidden" id="bmCarouselId" value="0">
                        <input type="hidden" id="bmCarouselCurrent" value="">
                        <input id="bmCarouselFile" type="file" accept="image/*,video/*" class="d-none">
                        
                        <label class="form-label small fw-bold text-secondary mb-1">Phương tiện (Ảnh/Video)</label>
                        <label for="bmCarouselFile" class="banner-preview" id="bmPreviewCarousel" style="height: 180px; width: 100%; border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer; transition: all 0.2s ease-in-out;">
                            Chưa có phương tiện
                        </label>
                    </div>
                    <div class="col-12">
                        <label for="bmCarouselTitle" class="form-label small fw-bold text-secondary mb-1">Tiêu đề slide</label>
                        <input id="bmCarouselTitle" class="form-control form-control-sm" maxlength="255" placeholder="Nhập tiêu đề hiển thị">
                    </div>
                    <div class="col-12">
                        <label for="bmCarouselContent" class="form-label small fw-bold text-secondary mb-1">Nội dung mô tả ngắn</label>
                        <textarea id="bmCarouselContent" class="form-control form-control-sm" rows="2" placeholder="Nhập nội dung/mô tả"></textarea>
                    </div>
                    <div class="col-12">
                        <label for="bmCarouselLink" class="form-label small fw-bold text-secondary mb-1">URL khi click (Tùy chọn)</label>
                        <input id="bmCarouselLink" class="form-control form-control-sm" maxlength="255" placeholder="Ví dụ: /san-pham/tin-tuc">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="bmResetCarousel"><i class="bi bi-arrow-counterclockwise"></i> Thêm mới</button>
                <button type="button" class="btn btn-success btn-sm" id="bmSaveCarousel">Lưu</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?= h($baseUrl . '/core_admin/ajax/banner.php') ?>';
    const $page = $('#bmPageKey');
    const slotConfig = {
        ad_left: { current:'#bmLeftCurrent', file:'#bmLeftFile', fileName:'#bmLeftFileName', link:'#bmLeftLink', active:'#bmLeftActive', preview:'#bmPreviewLeft', saveBtn:'#bmSaveLeft', defaultText:'Chưa có ảnh', disabledText:'Đã tắt hiển thị' },
        ad_right:{ current:'#bmRightCurrent', file:'#bmRightFile', fileName:'#bmRightFileName', link:'#bmRightLink', active:'#bmRightActive', preview:'#bmPreviewRight', saveBtn:'#bmSaveRight', defaultText:'Chưa có ảnh', disabledText:'Đã tắt hiển thị' },
        popup:   { current:'#bmPopupCurrent', file:'#bmPopupFile', fileName:'#bmPopupFileName', link:'#bmPopupLink', active:'#bmPopupActive', preview:'#bmPreviewPopup', saveBtn:'#bmSavePopup', defaultText:'Chưa có ảnh', disabledText:'Đã tắt hiển thị' }
    };

    function notify(msg, type = 'info'){
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    }

    function parseJsonLoose(payload){
        if (payload && typeof payload === 'object') return payload;
        const text = String(payload || '').trim();
        if (!text) return null;
        try {
            return JSON.parse(text);
        } catch (e) {
            const m = text.match(/\{[\s\S]*\}$/);
            if (m && m[0]) {
                try {
                    return JSON.parse(m[0]);
                } catch (e2) {}
            }
        }
        return null;
    }

    function requestJson(opts, onSuccess){
        const ajaxOpts = Object.assign({ dataType: 'text' }, opts || {});
        $.ajax(ajaxOpts).done(function(raw){
            const res = parseJsonLoose(raw);
            if (!res) {
                notify('Phản hồi server không hợp lệ', 'error');
                return;
            }
            onSuccess(res);
        }).fail(function(xhr){
            handleAjaxFail(xhr);
        });
    }

    function handleAjaxFail(xhr){
        let msg = 'Lỗi kết nối server';
        if (xhr && xhr.responseJSON && xhr.responseJSON.msg) {
            msg = xhr.responseJSON.msg;
        } else if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
            const text = xhr.responseText.trim();
            const parsed = parseJsonLoose(text);
            if (parsed && parsed.msg) {
                msg = parsed.msg;
            } else {
                msg = 'Lỗi server (' + (xhr.status || 500) + ')';
            }
        }
        notify(msg, 'error');
    }

    function esc(value){
        return $('<div>').text(String(value || '')).html();
    }

    // Ưu tiên media domain (toMediaUrl từ head.php); fallback giữ nguyên URL.
    function toAbs(url){
        const raw = String(url || '').trim();
        if (!raw) return '';
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(raw);
        return raw;
    }

    function isMediaVideo(url) {
        const safeUrl = String(url || '').trim().toLowerCase();
        if (safeUrl.startsWith('data:video/')) return true;
        return /\.(mp4|webm|ogg|mov)(\?|$)/i.test(safeUrl);
    }

    function renderPreview($el, url){
        const safeUrl = String(url || '').trim();
        if (!safeUrl) {
            const id = $el.attr('id');
            if (id === 'bmPreviewCarousel') {
                $el.html('<div class="text-center text-secondary p-3" style="cursor:pointer;"><i class="bi bi-cloud-arrow-up fs-2 d-block mb-1 text-primary"></i><span class="small fw-semibold text-primary">Click để tải ảnh/video</span><br><span class="text-muted" style="font-size:11px;">Hỗ trợ ảnh và video (mp4, webm, ...)</span></div>');
            } else {
                $el.html('<div class="text-center text-secondary" style="cursor:pointer; font-size:.7rem; line-height:1.2; padding:6px;"><i class="bi bi-cloud-arrow-up d-block mb-1 text-primary" style="font-size:1.2rem;"></i><span class="fw-semibold text-primary">Tải ảnh</span></div>');
            }
            return;
        }
        const srcUrl = toAbs(safeUrl);
        if (isMediaVideo(safeUrl)) {
            $el.html('<video src="' + esc(srcUrl) + '" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;display:block;"></video>');
        } else {
            $el.html('<img src="' + esc(srcUrl) + '" alt="preview" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="this.remove();this.parentNode.innerHTML=\'<span class=&quot;text-danger small&quot;>Ảnh lỗi</span>\';">');
        }
    }

    function fillAd(slot, item){
        const cfg = slotConfig[slot];
        if (!cfg) return;

        const image = String(item?.image_url || '');
        const link = String(item?.link_url || '');
        const active = Number(item?.is_active ?? 1) === 1;

        $(cfg.current).val(image);
        $(cfg.file).val('');
        $(cfg.link).val(link);
        $(cfg.active).prop('checked', active);

        const $preview = $(cfg.preview);
        
        if (!active && image) {
            // có ảnh nhưng tắt: vẫn show ảnh, làm mờ + overlay icon mắt
            $preview.html('<div class="position-relative" style="width:100%;height:100%;"><img src="' + esc(toAbs(image)) + '" style="width:100%;height:100%;object-fit:cover;opacity:.35;" onerror="this.remove();"><div class="position-absolute top-50 start-50 translate-middle text-danger" title="Đã tắt"><i class="bi bi-eye-slash-fill fs-4"></i></div></div>');
        } else if (!active) {
            $preview.html('<div class="text-center text-danger" style="cursor:pointer; font-size:.68rem; line-height:1.2; padding:6px;"><i class="bi bi-eye-slash-fill d-block mb-1" style="font-size:1.3rem;"></i><span class="fw-semibold">Đã tắt</span></div>');
        } else if (!image) {
            renderPreview($preview, '');
        } else {
            renderPreview($preview, image);
        }
    }

    function resetCarouselForm(){
        $('#bmCarouselId').val('0');
        $('#bmCarouselCurrent').val('');
        $('#bmCarouselFile').val('');
        $('#bmCarouselFileName').text('Chưa chọn tệp');
        $('#bmCarouselTitle').val('');
        $('#bmCarouselContent').val('');
        $('#bmCarouselLink').val('');
        renderPreview($('#bmPreviewCarousel'), '');
    }

    function previewFromFile(input, $target, $nameTarget){
        const file = input && input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
            if ($nameTarget && $nameTarget.length) {
                $nameTarget.text('Chưa chọn tệp');
            }
            return;
        }
        const isImage = file.type && file.type.indexOf('image/') === 0;
        const isVideo = file.type && file.type.indexOf('video/') === 0;
        if (!file.type || (!isImage && !isVideo)) {
            notify('Vui lòng chọn tệp ảnh hoặc video hợp lệ', 'warning');
            input.value = '';
            if ($nameTarget && $nameTarget.length) {
                $nameTarget.text('Chưa chọn tệp');
            }
            return;
        }
        if ($nameTarget && $nameTarget.length) {
            $nameTarget.text(file.name);
        }
        const reader = new FileReader();
        reader.onload = function(e){
            renderPreview($target, e.target && e.target.result ? String(e.target.result) : '');
        };
        reader.readAsDataURL(file);
    }

    function saveCarouselOrder(){
        const ids = $('#bmCarouselTable tbody tr[data-id]').map(function(){
            return Number($(this).attr('data-id') || 0);
        }).get().filter(Boolean);
        if (!ids.length) return;
        requestJson({
            url: API,
            method: 'POST',
            data: { action: 'reorder_carousel', page_key: $page.val(), ids: JSON.stringify(ids) }
        }, function(res){
            if (res && res.ok) {
                notify(res.msg || 'Đã cập nhật thứ tự', 'success');
                loadAll();
            } else {
                notify(res?.msg || 'Không thể cập nhật thứ tự', 'error');
            }
        });
    }

    function bindDragSort(){
        const tbody = document.querySelector('#bmCarouselTable tbody');
        if (!tbody) return;

        tbody.querySelectorAll('.drag-handle').forEach(function(handle){
            handle.setAttribute('draggable', 'true');
        });

        if (tbody.dataset.dragBound === '1') {
            return;
        }
        tbody.dataset.dragBound = '1';
        let dragRow = null;

        tbody.addEventListener('dragstart', function(e){
            const actionTarget = e.target && e.target.closest ? e.target.closest('[data-edit],[data-del],button') : null;
            if (actionTarget) {
                if (e.preventDefault) e.preventDefault();
                return;
            }
            const handle = e.target && e.target.closest ? e.target.closest('.drag-handle') : null;
            if (!handle) {
                if (e.preventDefault) e.preventDefault();
                return;
            }
            const tr = handle.closest ? handle.closest('tr[data-id]') : null;
            if (!tr) return;
            dragRow = tr;
            tr.classList.add('dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(tr.getAttribute('data-id') || ''));
            }
        });

        tbody.addEventListener('dragover', function(e){
            e.preventDefault();
            const tr = e.target && e.target.closest ? e.target.closest('tr[data-id]') : null;
            if (!tr || !dragRow || tr === dragRow) return;
            const rect = tr.getBoundingClientRect();
            const before = e.clientY < rect.top + rect.height / 2;
            if (before) {
                tbody.insertBefore(dragRow, tr);
            } else {
                tbody.insertBefore(dragRow, tr.nextSibling);
            }
        });

        tbody.addEventListener('drop', function(e){
            e.preventDefault();
            if (dragRow) {
                dragRow.classList.remove('dragging');
                dragRow = null;
            }
            saveCarouselOrder();
        });

        tbody.addEventListener('dragend', function(){
            if (dragRow) {
                dragRow.classList.remove('dragging');
                dragRow = null;
            }
        });
    }

    function updateKpis(list, ads){
        const arr = Array.isArray(list) ? list : [];
        const total = arr.length;
        let active = 0, video = 0;
        arr.forEach(it => {
            if (Number(it.is_active || 0) === 1) active++;
            if (isMediaVideo(String(it.image_url || ''))) video++;
        });
        let adsOn = 0;
        if (ads){
            ['ad_left','ad_right','popup'].forEach(k => {
                if (ads[k] && Number(ads[k].is_active ?? 1) === 1) adsOn++;
            });
        }
        $('#bmKpiSlides').text(total);
        $('#bmKpiActive').text(active);
        $('#bmKpiVideo').text(video);
        $('#bmKpiAds').text(adsOn + '/3');
        $('#bmMeta').text(total + ' slide');
    }

    function renderCarousel(list){
        const $tbody = $('#bmCarouselTable tbody');
        if (!Array.isArray(list) || !list.length) {
            $tbody.html(`
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-collection-play text-secondary" style="font-size: 2.2rem; opacity:.5;"></i>
                    <div class="mt-2">Chưa có slide nào. Bấm <strong>Thêm slide</strong> để bắt đầu.</div>
                </td></tr>
            `);
            return;
        }

        carouselRowsById = {};
        list.forEach(function(item){
            const id = Number(item && item.id ? item.id : 0);
            if (id > 0) carouselRowsById[id] = item;
        });

        $tbody.html(list.map(item => {
            const id = Number(item.id || 0);
            const image = String(item.image_url || '');
            const title = String(item.title || '');
            const content = String(item.content || '');
            const link = String(item.link_url || '');
            const sort = Number(item.sort_order || 1);
            const active = Number(item.is_active || 0) === 1;
            const isVideo = isMediaVideo(image);
            const mediaSrc = toAbs(image);
            const mediaInner = image
                ? (isVideo
                    ? `<video src="${esc(mediaSrc)}" muted playsinline style="width:100%;height:100%;object-fit:cover;"></video>`
                    : `<img src="${esc(mediaSrc)}" alt="carousel" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">`)
                : `<i class="bi bi-image text-secondary fs-4"></i>`;
            const mediaBadge = isVideo
                ? `<span class="position-absolute" style="left:4px; bottom:4px; font-size:.6rem; font-weight:600; padding:1px 6px; border-radius:4px; background:rgba(37,99,235,.92); color:#fff;"><i class="bi bi-film me-1"></i>VIDEO</span>`
                : '';
            const linkHtml = link
                ? `<a href="${esc(link)}" target="_blank" class="text-decoration-none small text-truncate d-inline-block" style="max-width:180px;" title="${esc(link)}">${esc(link)} <i class="bi bi-box-arrow-up-right"></i></a>`
                : `<span class="text-muted small">—</span>`;
            return `
                <tr data-id="${id}">
                    <td class="text-center ps-3 drag-handle"><i class="bi bi-grip-vertical fs-5"></i></td>
                    <td>
                        <div class="position-relative rounded overflow-hidden border d-flex align-items-center justify-content-center" style="width:90px; height:60px; background:#f1f5f9;">
                            ${mediaInner}${mediaBadge}
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark-emphasis text-truncate" style="max-width:320px; font-size:.92rem;" title="${esc(title)}">${esc(title) || '<span class="text-muted">(Không tiêu đề)</span>'}</div>
                        <div class="text-muted small text-truncate" style="max-width:320px;" title="${esc(content)}">${esc(content) || '—'}</div>
                    </td>
                    <td>${linkHtml}</td>
                    <td class="text-center"><span class="badge bg-light text-secondary border" style="font-size:.7rem;">#${sort}</span></td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block m-0">
                            <input type="checkbox" class="form-check-input bm-carousel-toggle cursor-pointer" data-toggle-carousel="${id}" ${active ? 'checked' : ''} role="switch">
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <div class="voucher-actions">
                            <button type="button" class="btn btn-outline-primary bm-edit-carousel" data-edit="${id}" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger bm-del-carousel" data-del="${id}" title="Xóa"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        }).join(''));
        bindDragSort();
    }
    // Bật/tắt hiển thị carousel trực tiếp bằng switch
    $(document)
        .off('change.bmCarouselToggle', '.bm-carousel-toggle')
        .on('change.bmCarouselToggle', '.bm-carousel-toggle', function(){
            const id = Number($(this).data('toggle-carousel') || 0);
            if (!id) return;
            const row = carouselRowsById[id];
            if (!row) return;
            const newActive = $(this).is(':checked') ? 1 : 0;
            // Gửi request cập nhật trạng thái
            requestJson({
                url: API,
                method: 'POST',
                data: {
                    action: 'save_carousel',
                    page_key: $page.val(),
                    id: id,
                    current_image: row.image_url || '',
                    title: row.title || '',
                    content: row.content || '',
                    link_url: row.link_url || '',
                    sort_order: row.sort_order || 1,
                    is_active: newActive
                }
            }, function(res){
                if (res && res.ok) {
                    notify(res.msg || 'Đã cập nhật trạng thái', 'success');
                    loadAll();
                } else {
                    notify(res?.msg || 'Không thể cập nhật trạng thái', 'error');
                }
            });
        });

    let carouselRows = [];
    let carouselRowsById = {};

    function openCarouselEditor(id){
        const rowId = Number(id || 0);
        if (!rowId) return;
        const row = carouselRowsById[rowId] || carouselRows.find(x => Number(x.id) === rowId);
        if (!row) {
            notify('Không tìm thấy dữ liệu carousel', 'warning');
            return;
        }
        $('#bmCarouselId').val(rowId);
        $('#bmCarouselCurrent').val(String(row.image_url || ''));
        $('#bmCarouselTitle').val(String(row.title || ''));
        $('#bmCarouselContent').val(String(row.content || ''));
        $('#bmCarouselLink').val(String(row.link_url || ''));
        $('#bmCarouselFile').val('');
        $('#bmCarouselFileName').text('Chưa chọn tệp');
        $('#bmCarouselSort').val(Number(row.sort_order || 1));
        $('#bmCarouselActive').prop('checked', Number(row.is_active || 0) === 1);
        renderPreview($('#bmPreviewCarousel'), String(row.image_url || ''));
        const $title = $('#bmCarouselTitle');
        if ($title.length) {
            $title.trigger('focus');
        }
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalEl = document.getElementById('bmCarouselModal');
                if (modalEl) {
                    const m = bootstrap.Modal.getOrCreateInstance(modalEl);
                    m.show();
                }
            } else {
                $('#bmCarouselModal').modal('show');
            }
    }

    function loadAll(){
        requestJson({
            url: API,
            method: 'GET',
            data: { action: 'list', page_key: $page.val() }
        }, function(res){
            if (!res || !res.ok) {
                notify(res?.msg || 'Không tải được dữ liệu banner', 'error');
                return;
            }
            fillAd('ad_left', res.ads?.ad_left || null);
            fillAd('ad_right', res.ads?.ad_right || null);
            fillAd('popup', res.ads?.popup || null);
            carouselRows = Array.isArray(res.carousel) ? res.carousel : [];
            renderCarousel(carouselRows);
            updateKpis(carouselRows, res.ads || {});
            resetCarouselForm();
            $(document)
                .off('click.bmOpenCarouselModal', '#bmOpenCarouselModal')
                .on('click.bmOpenCarouselModal', '#bmOpenCarouselModal', function(e){
                    e.preventDefault();
                    resetCarouselForm();
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modalEl = document.getElementById('bmCarouselModal');
                        if (modalEl) {
                            const m = bootstrap.Modal.getOrCreateInstance(modalEl);
                            m.show();
                        }
                    } else {
                        $('#bmCarouselModal').modal('show');
                    }
                });
        });
    }

    function saveAd(slot){
        let fileInput;
        let currentImage = '';
        let active = 1;
        let link = '';

        if (slot === 'ad_left') {
            fileInput = $('#bmLeftFile').get(0);
            currentImage = $('#bmLeftCurrent').val().trim();
            active = $('#bmLeftActive').is(':checked') ? 1 : 0;
            link = $('#bmLeftLink').val().trim();
        } else if (slot === 'ad_right') {
            fileInput = $('#bmRightFile').get(0);
            currentImage = $('#bmRightCurrent').val().trim();
            active = $('#bmRightActive').is(':checked') ? 1 : 0;
            link = $('#bmRightLink').val().trim();
        } else if (slot === 'popup') {
            fileInput = $('#bmPopupFile').get(0);
            currentImage = $('#bmPopupCurrent').val().trim();
            active = $('#bmPopupActive').is(':checked') ? 1 : 0;
            link = $('#bmPopupLink').val().trim();
        }
        const formData = new FormData();
        formData.append('action', 'save_ad');
        formData.append('page_key', $page.val());
        formData.append('slot_key', slot);
        formData.append('is_active', String(active));
        formData.append('current_image', currentImage);
        formData.append('link_url', link);
        if (fileInput && fileInput.files && fileInput.files[0]) {
            formData.append('ad_image', fileInput.files[0]);
        }

        requestJson({
            url: API,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }, function(res){
            if (res && res.ok) {
                notify(res.msg || 'Đã lưu banner', 'success');
                loadAll();
            } else {
                notify(res?.msg || 'Không thể lưu banner', 'error');
            }
        });
    }

    $(document)
        .off('click.bmSaveLeft', '#bmSaveLeft')
        .on('click.bmSaveLeft', '#bmSaveLeft', function(e){
            e.preventDefault();
            saveAd('ad_left');
        });

    $(document)
        .off('click.bmSaveRight', '#bmSaveRight')
        .on('click.bmSaveRight', '#bmSaveRight', function(e){
            e.preventDefault();
            saveAd('ad_right');
        });

    $(document)
        .off('click.bmSavePopup', '#bmSavePopup')
        .on('click.bmSavePopup', '#bmSavePopup', function(e){
            e.preventDefault();
            saveAd('popup');
        });

    $(document)
        .off('change.bmLeftActive', '#bmLeftActive')
        .on('change.bmLeftActive', '#bmLeftActive', function(){
            saveAd('ad_left');
        });

    $(document)
        .off('change.bmRightActive', '#bmRightActive')
        .on('change.bmRightActive', '#bmRightActive', function(){
            saveAd('ad_right');
        });

    $(document)
        .off('change.bmPopupActive', '#bmPopupActive')
        .on('change.bmPopupActive', '#bmPopupActive', function(){
            saveAd('popup');
        });

    $(document)
    .off('click.bmSaveCarousel', '#bmSaveCarousel')
    .on('click.bmSaveCarousel', '#bmSaveCarousel', function(e){
        e.preventDefault();
        const id = Number($('#bmCarouselId').val() || 0);
        const currentImage = $('#bmCarouselCurrent').val().trim();
        const title = $('#bmCarouselTitle').val().trim();
        const content = $('#bmCarouselContent').val().trim();
        const linkUrl = $('#bmCarouselLink').val().trim();
        const fileInput = $('#bmCarouselFile').get(0);
        const formData = new FormData();
        formData.append('action', 'save_carousel');
        formData.append('page_key', $page.val());
        formData.append('id', String(id));
        formData.append('current_image', currentImage);
        formData.append('title', title);
        formData.append('content', content);
        formData.append('link_url', linkUrl);
        // Không gửi sort_order và is_active từ form, backend sẽ tự động set
        if (fileInput && fileInput.files && fileInput.files[0]) {
            formData.append('carousel_image', fileInput.files[0]);
        }

        requestJson({
            url: API,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }, function(res){
            if (res && res.ok) {
                notify(res.msg || 'Đã lưu carousel', 'success');
                loadAll();
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modalEl = document.getElementById('bmCarouselModal');
                    if (modalEl) {
                        const m = bootstrap.Modal.getOrCreateInstance(modalEl);
                        m.hide();
                    }
                } else {
                    $('#bmCarouselModal').modal('hide');
                }
            } else {
                notify(res?.msg || 'Không thể lưu carousel', 'error');
            }
        });
    });

    // Đã xoá sự kiện thay đổi bmCarouselActive

    $(document)
        .off('click.bmResetCarousel', '#bmResetCarousel')
        .on('click.bmResetCarousel', '#bmResetCarousel', function(e){
            e.preventDefault();
            resetCarouselForm();
        });

    $(document)
    .off('click.bmEditCarousel', '#bmCarouselTable .bm-edit-carousel')
    .on('click.bmEditCarousel', '#bmCarouselTable .bm-edit-carousel', function(e){
        e.preventDefault();
        e.stopPropagation();
        const id = Number($(this).data('edit') || 0);
        openCarouselEditor(id);
    });

    $(document)
    .off('click.bmDelCarousel', '#bmCarouselTable .bm-del-carousel')
    .on('click.bmDelCarousel', '#bmCarouselTable .bm-del-carousel', function(e){
        e.preventDefault();
        e.stopPropagation();
        const id = Number($(this).data('del') || 0);
        if (!id) return;
        if (!confirm('Xoá ảnh carousel này?')) return;
        requestJson({
            url: API,
            method: 'POST',
            data: { action: 'delete_carousel', page_key: $page.val(), id: id }
        }, function(res){
            if (res && res.ok) {
                notify(res.msg || 'Đã xoá carousel', 'success');
                loadAll();
            } else {
                notify(res?.msg || 'Không thể xoá carousel', 'error');
            }
        });
    });

    $(document)
        .off('change.bmLeftFile', '#bmLeftFile')
        .on('change.bmLeftFile', '#bmLeftFile', function(){ previewFromFile(this, $('#bmPreviewLeft'), null); });

    $(document)
        .off('change.bmRightFile', '#bmRightFile')
        .on('change.bmRightFile', '#bmRightFile', function(){ previewFromFile(this, $('#bmPreviewRight'), null); });

    $(document)
        .off('change.bmPopupFile', '#bmPopupFile')
        .on('change.bmPopupFile', '#bmPopupFile', function(){ previewFromFile(this, $('#bmPreviewPopup'), null); });

    $(document)
        .off('change.bmCarouselFile', '#bmCarouselFile')
        .on('change.bmCarouselFile', '#bmCarouselFile', function(){ previewFromFile(this, $('#bmPreviewCarousel'), null); });

    $(document)
        .off('change.bmPageKey', '#bmPageKey')
        .on('change.bmPageKey', '#bmPageKey', loadAll);

    // Segmented switch: chuyển page key
    $(document)
        .off('click.bmPageSwitch', '.bm-page-switch .bm-page-opt')
        .on('click.bmPageSwitch', '.bm-page-switch .bm-page-opt', function(e){
            e.preventDefault();
            const $btn = $(this);
            if ($btn.hasClass('active')) return;
            $('.bm-page-switch .bm-page-opt').removeClass('active').attr('aria-selected', 'false');
            $btn.addClass('active').attr('aria-selected', 'true');
            $('#bmPageKey').val($btn.data('key')).trigger('change');
        });

    // KPI cards → highlight + scroll
    $('#summaryGrid').on('click', '.summary-card', function(){
        $('#summaryGrid .summary-card').removeClass('active');
        $(this).addClass('active');
        const tab = $(this).data('bm-tab');
        if (tab === 'ads'){
            const el = document.querySelector('.bm-ad-slot');
            if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
        } else {
            const el = document.getElementById('bmCarouselTable');
            if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
        }
    });

    loadAll();
})();
</script>
