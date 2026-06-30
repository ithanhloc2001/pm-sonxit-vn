<?php
$apiUrl = $basePath . '/core_admin/giaohangnhanh/ajax/api.php';
require_once __DIR__ . '/../_admin_guard.php';

?>

<style>
    .shop-card { cursor: pointer; transition: border-color .15s, box-shadow .15s, background-color .15s; }
    .shop-card:hover { border-color: var(--theme-primary, #0c4c29) !important; box-shadow: 0 2px 10px rgba(15,23,42,.06); }
</style>

<div class="container-fluid py-4">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08); color: var(--theme-primary, #0c4c29); border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-shop fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b; letter-spacing: -0.01em;">Thiết lập Shop GHN</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="shopCountBadge" style="font-size: 0.72rem;">0 shop</span>
                </div>
                <p class="text-muted mb-0 small" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Quét, tải mới và chọn shop đang hoạt động để đồng bộ thông tin bên gửi.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3 py-2 shadow-sm" href="<?= h($basePath) ?>/admin/giaohangnhanh/home" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline">Quay lại</span>
            </a>
            <button class="btn btn-outline-primary d-flex align-items-center gap-2 px-3 py-2 shadow-sm" id="btnScanShop" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-search"></i><span class="d-none d-sm-inline">Quét shop</span>
            </button>
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm text-white" id="btnRefreshShop" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-arrow-clockwise fs-5 text-white"></i><span class="d-none d-sm-inline">Cập nhật</span>
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-semibold text-uppercase text-secondary" style="font-size:.8rem; letter-spacing:.03em;">Danh sách shop</div>
                <div class="small text-muted"><i class="bi bi-hand-index"></i> Click để chọn shop hoạt động</div>
            </div>

            <input type="hidden" id="shop_id" value="">
            <div id="shop_card_list" class="d-flex flex-column gap-2" style="max-height: 520px; overflow:auto;">
                <div class="small text-muted">Chưa có shop</div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API = '<?= h($apiUrl) ?>';
    let shopList = [];

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

    function shopDisplayName(s){
        const sid = Number(s?.shop_id || 0);
        const display = String(s?.display_name || '').trim();
        const name = String(s?.name || '').trim();
        return display || name || ('Shop #' + sid);
    }

    function getSelectedShopId(){
        return Number($('#shop_id').val() || 0);
    }

    function renderShopLoading(){
        $('#shop_card_list').html('<div class="d-flex align-items-center gap-2 text-muted small p-2"><span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span><span>Đang tải danh sách shop...</span></div>');
    }

    function updateShopCountBadge(){
        const n = Array.isArray(shopList) ? shopList.length : 0;
        $('#shopCountBadge').text(n + ' shop');
    }

    function renderShopCards(){
        updateShopCountBadge();
        if (!Array.isArray(shopList) || !shopList.length) {
            $('#shop_card_list').html('<div class="text-center text-muted py-4"><i class="bi bi-shop fs-3 d-block mb-2 text-secondary"></i>Chưa có shop. Bấm "Quét shop" để tải.</div>');
            $('#shop_id').val('');
            return;
        }
        const html = shopList.map(function(s){
            const sid = Number(s.shop_id || 0);
            const name = shopDisplayName(s);
            const phone = String(s.phone || '');
            const address = String(s.address || '').trim();
            const isActive = Number(s.is_active || 0) === 1;
            const activeClass = isActive ? ' border-primary bg-light' : '';
            const radioChecked = isActive ? ' checked' : '';
            const statusBadge = isActive ? '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Đang chọn</span>' : '';
            return ''
                + '<div class="shop-card border rounded-3 p-3 bg-white'+activeClass+'" data-shop-id="'+sid+'">'
                    + '<div class="d-flex justify-content-between align-items-start gap-2">'
                        + '<div class="d-flex align-items-start gap-2" style="min-width:0;">'
                            + '<input class="form-check-input mt-1 flex-shrink-0" type="radio" name="shopSelect"'+radioChecked+'>'
                            + '<div style="min-width:0;">'
                                + '<div class="d-flex align-items-center gap-2 flex-wrap">'
                                    + '<div class="fw-semibold text-dark">'+esc(name)+'</div>'
                                    + (statusBadge ? statusBadge : '')
                                + '</div>'
                                + '<div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i>'+esc(address || '—')+'</div>'
                                + '<div class="small text-muted"><i class="bi bi-telephone me-1"></i>'+esc(phone || '—')+'</div>'
                            + '</div>'
                        + '</div>'
                        + '<span class="badge bg-light text-secondary border font-monospace flex-shrink-0">#'+sid+'</span>'
                    + '</div>'
                + '</div>';
        }).join('');
        $('#shop_card_list').html(html);
    }

    function setSelectedShopId(shopId){
        const sid = Number(shopId || 0);
        const hasShop = (shopList || []).some(function(it){ return Number(it.shop_id || 0) === sid; });
        const finalId = hasShop ? sid : 0;
        $('#shop_id').val(finalId ? String(finalId) : '');
        $('#shop_card_list .shop-card').removeClass('border-primary bg-light');
        $('#shop_card_list .shop-card input[type="radio"][name="shopSelect"]').prop('checked', false);
        if (finalId) {
            const $card = $('#shop_card_list .shop-card[data-shop-id="'+finalId+'"]');
            $card.addClass('border-primary bg-light');
            $card.find('input[type="radio"][name="shopSelect"]').prop('checked', true);
            applySenderFromShopId(finalId);
        }
    }

    function pickDefaultShopId(){
        if (!Array.isArray(shopList) || !shopList.length) return 0;
        const current = getSelectedShopId();
        if (current && shopList.some(function(s){ return Number(s.shop_id || 0) === current; })) return current;
        const active = shopList.find(function(s){ return Number(s.is_active || 0) === 1; });
        if (active) return Number(active.shop_id || 0);
        return Number(shopList[0]?.shop_id || 0);
    }

    function applySenderFromShopId(shopId){
        const sid = Number(shopId || 0);
        const s = (shopList || []).find(function(it){ return Number(it.shop_id || 0) === sid; }) || null;
        if (!s) return;
        $('#shop_id').val(String(sid));
    }

    function saveActiveShopByCard(shopId){
        const sid = Number(shopId || 0);
        if (!sid) return;
        setSelectedShopId(sid);
        const selectedShop = (shopList || []).find(function(it){ return Number(it.shop_id || 0) === sid; }) || null;
        const selectedShopName = selectedShop ? shopDisplayName(selectedShop) : ('#' + sid);
        return api('shop_set_active', { shop_id: sid }, 'POST').then(function(res){
            if (!res.ok) {
                notify(res.msg || 'Lưu shop thất bại', 'warning');
                return;
            }
            notify('Đã chọn: ' + selectedShopName, 'success');
            loadShopList();
        }).catch(function(err){
            notify(err.msg || 'Lưu shop thất bại', 'error');
        });
    }

    function loadShopList(){
        renderShopLoading();
        return api('shop_list', {}, 'GET').then(function(res){
            if (!res.ok) return;
            shopList = Array.isArray(res.rows) ? res.rows : [];
            renderShopCards();
            const active = shopList.find(function(s){ return Number(s.is_active || 0) === 1; });
            const sid = pickDefaultShopId();
            if (sid) setSelectedShopId(sid);
        }).catch(function(err){ notify(err.msg || 'Không tải được shop', 'error'); });
    }

    function scanShops(){
        renderShopLoading();
        return api('shop_scan', { limit: 200 }, 'POST').then(function(res){
            if (!res.ok) return notify(res.msg || 'Quét shop thất bại', 'warning');
            shopList = Array.isArray(res.rows) ? res.rows : [];
            renderShopCards();
            const active = shopList.find(function(s){ return Number(s.is_active || 0) === 1; });
            const sid = pickDefaultShopId();
            if (sid) setSelectedShopId(sid);
            notify('Đã quét xong', 'success');
        }).catch(function(err){ notify(err.msg || 'Quét shop thất bại', 'error'); });
    }

    function refreshShopInfo(){
        const sid = getSelectedShopId();
        renderShopLoading();
        return api('shop_refresh', { shop_id: sid }, 'POST').then(function(res){
            if (!res.ok) return notify(res.msg || 'Tải mới shop thất bại', 'warning');
            shopList = Array.isArray(res.rows) ? res.rows : [];
            renderShopCards();
            const nextId = sid || pickDefaultShopId();
            if (nextId) setSelectedShopId(nextId);
            notify('Đã tải mới thông tin shop', 'success');
        }).catch(function(err){ notify(err.msg || 'Tải mới shop thất bại', 'error'); });
    }

    $('#btnScanShop').on('click', scanShops);
    $('#btnRefreshShop').on('click', refreshShopInfo);
    $(document).on('click', '#shop_card_list .shop-card', function(){
        const sid = Number($(this).attr('data-shop-id') || 0);
        if (sid) saveActiveShopByCard(sid);
    });

    loadShopList();
})();
</script>
