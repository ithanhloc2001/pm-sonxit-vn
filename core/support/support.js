/**
 * JS dùng chung cho hệ thống Hỗ trợ (Ticket) — frontend & admin.
 * Tất cả thao tác gửi/nhận đều qua AJAX, không reload trang.
 */
(function (w) {
  'use strict';

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = (s == null ? '' : String(s));
    return d.innerHTML;
  }

  // Render nội dung tin nhắn: nếu có marker [[PMCARD:type]]{json} → card gọn; else text escape.
  function renderMessageContent(content) {
    content = content == null ? '' : String(content);
    var mt = content.match(/^\[\[PMCARD:(product|voucher)\]\](\{[\s\S]*?\})(?:\n([\s\S]*))?$/);
    if (!mt) return esc(content);
    var d;
    try { d = JSON.parse(mt[2]); } catch (e) { return esc(content); }
    var extra = (mt[3] || '').trim();
    var card = '';
    if (mt[1] === 'product') {
      card = '<div style="display:flex;gap:10px;max-width:300px;border:1px solid #e2e8f0;border-radius:12px;padding:8px;background:#fff;">' +
        (d.img ? '<div style="width:60px;height:60px;flex:0 0 60px;border-radius:8px;overflow:hidden;background:#f1f5f9;"><img src="' + esc(d.img) + '" alt="" style="width:100%;height:100%;object-fit:cover;"></div>' : '') +
        '<div style="min-width:0;">' +
        (d.cat ? '<div style="font-size:.68rem;color:#94a3b8;">' + esc(d.cat) + '</div>' : '') +
        '<div style="font-size:.85rem;font-weight:700;color:#0f172a;">' + esc(d.name) + '</div>' +
        '<div style="font-size:.85rem;font-weight:700;color:#dc2626;">' + esc(d.price) + '</div>' +
        '</div></div>';
    } else {
      var accent = '#ee4d2d';
      if (d.variant === 'ship') accent = '#26aa99';
      else if (d.variant === 'payment') accent = '#16a34a';
      else if (d.variant === 'category') accent = '#ea580c';
      else if (d.variant === 'all') accent = '#7c3aed';
      card = '<div style="display:flex;align-items:center;gap:10px;max-width:300px;background:#fff;border:1px solid #e5e7eb;border-left:4px solid ' + accent + ';border-radius:10px;padding:8px 10px;">' +
        '<span style="flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:' + accent + ';color:#fff;font-size:.85rem;"><i class="bi ' + esc(d.icon || 'bi-percent') + '"></i></span>' +
        '<div style="min-width:0;">' +
        '<div style="font-size:.8rem;font-weight:800;color:#111827;line-height:1.25;">' + esc(d.title || d.label) + '</div>' +
        '<div style="font-size:.72rem;color:#374151;">Mã: <b>' + esc(d.code) + '</b></div>' +
        '<div style="font-size:.68rem;color:#6b7280;">' + esc(d.min || 'Áp dụng mọi đơn') + '</div>' +
        (d.exp ? '<div style="font-size:.66rem;color:#ef4444;">' + esc(d.exp) + '</div>' : '') +
        '</div></div>';
    }
    if (extra) card += '<div style="margin-top:6px;white-space:pre-wrap;word-break:break-word;">' + esc(extra) + '</div>';
    return card;
  }

  // Gửi form/FormData hoặc object tới endpoint, trả Promise<json>
  function post(url, data, csrf) {
    var opts = { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
    if (csrf) opts.headers['X-CSRF-Token'] = csrf;
    if (data instanceof FormData) {
      opts.body = data;
    } else {
      opts.body = new URLSearchParams(data);
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
  }

  function get(url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  // Render 1 bong bóng tin nhắn từ payload (support_message_payload)
  // opts: { requesterName: string }  — tên hiển thị cho phía khách
  function renderMessage(m, opts) {
    opts = opts || {};
    if (m.sender_type === 'system') {
      return '<div class="text-center small text-muted"><i class="bi bi-info-circle me-1"></i>' + esc(m.content) + '</div>';
    }
    var isAdmin = (m.sender_type === 'admin');
    // Phía admin: bong bóng admin nằm phải; phía khách: bong bóng admin nằm trái.
    var adminOnRight = !!opts.adminView;
    var rowDir = isAdmin ? (adminOnRight ? 'flex-row-reverse' : '') : (adminOnRight ? '' : 'flex-row-reverse');
    var avBg = isAdmin ? '#0c4c29' : '#e2e8f0';
    var avFg = isAdmin ? '#fff' : '#475569';
    var avIcon = isAdmin ? 'bi-headset' : 'bi-person';
    var bubBg = isAdmin ? '#f0fdf4' : '#fff';
    var labelColor = isAdmin ? '#0c4c29' : '#475569';
    var label = isAdmin ? 'Nhân viên hỗ trợ' : (opts.requesterName || 'Bạn');

    var media = '';
    if (m.media && m.media.length) {
      media = '<div class="d-flex flex-wrap gap-2 mt-2">' + m.media.map(function (u) {
        return '<img src="' + esc(u) + '" data-lightbox data-full="' + esc(u) + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:zoom-in;">';
      }).join('') + '</div>';
    }

    return '<div class="d-flex ' + rowDir + ' gap-2">' +
      '<div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:' + avBg + ';color:' + avFg + ';"><i class="bi ' + avIcon + '"></i></div>' +
      '<div class="p-3 rounded-4 shadow-sm" style="max-width:80%;background:' + bubBg + ';border:1px solid #e2e8f0;">' +
      '<div class="fw-semibold small mb-1" style="color:' + labelColor + ';">' + esc(label) + '</div>' +
      '<div style="white-space:pre-wrap;word-break:break-word;">' + renderMessageContent(m.content) + '</div>' +
      media +
      '<div class="text-muted mt-1" style="font-size:.72rem;">' + esc(m.time) + '</div>' +
      '</div></div>';
  }

  // Badge trạng thái ticket
  var STAT = {
    open: ['#2563eb', 'Đang mở'],
    pending: ['#b45309', 'Chờ phản hồi'],
    resolved: ['#15803d', 'Đã xử lý'],
    closed: ['#64748b', 'Đã đóng']
  };
  function statusBadge(s) {
    var c = STAT[s] || ['#64748b', s];
    return '<span class="badge rounded-pill" style="background:' + c[0] + '1a;color:' + c[0] + ';">' + esc(c[1]) + '</span>';
  }

  // ---- Toast (tự dựng, không phụ thuộc thư viện ngoài) ------------------
  var _toastWrap = null;
  function ensureToastWrap() {
    if (_toastWrap) return _toastWrap;
    _toastWrap = document.createElement('div');
    _toastWrap.className = 'sup-toast-wrap';
    _toastWrap.style.cssText = 'position:fixed;top:18px;right:18px;z-index:21000;display:flex;flex-direction:column;gap:10px;max-width:92vw;';
    document.body.appendChild(_toastWrap);
    return _toastWrap;
  }
  // toast(ok, msg) hoặc toast({ ok, msg, type:'success|error|info|warning', duration })
  function toast(a, b) {
    var ok, msg, type, duration;
    if (typeof a === 'object' && a) {
      ok = a.ok !== false; msg = a.msg || a.text || ''; type = a.type; duration = a.duration;
    } else {
      ok = !!a; msg = b || '';
    }
    if (!msg) return;
    type = type || (ok ? 'success' : 'error');
    var palette = {
      success: ['#15803d', '#f0fdf4', 'bi-check-circle-fill'],
      error:   ['#dc2626', '#fef2f2', 'bi-x-circle-fill'],
      info:    ['#2563eb', '#eff6ff', 'bi-info-circle-fill'],
      warning: ['#b45309', '#fffbeb', 'bi-exclamation-triangle-fill']
    };
    var c = palette[type] || palette.info;
    if (duration == null) duration = (type === 'error') ? 4000 : 2600;

    var el = document.createElement('div');
    el.style.cssText = 'display:flex;align-items:flex-start;gap:10px;min-width:240px;max-width:380px;padding:12px 14px;border-radius:12px;background:' + c[1] + ';border:1px solid ' + c[0] + '33;border-left:4px solid ' + c[0] + ';box-shadow:0 8px 24px rgba(15,23,42,.12);color:#1e293b;font-size:.9rem;opacity:0;transform:translateX(12px);transition:.22s ease;';
    el.innerHTML =
      '<i class="bi ' + c[2] + '" style="color:' + c[0] + ';font-size:1.1rem;line-height:1.3;flex:0 0 auto;"></i>' +
      '<div style="flex:1;line-height:1.4;">' + esc(msg) + '</div>' +
      '<button type="button" aria-label="Đóng" style="border:none;background:none;color:#94a3b8;cursor:pointer;font-size:1rem;line-height:1;flex:0 0 auto;padding:0;">&times;</button>';
    ensureToastWrap().appendChild(el);
    requestAnimationFrame(function () { el.style.opacity = '1'; el.style.transform = 'translateX(0)'; });

    var killed = false;
    function close() {
      if (killed) return; killed = true;
      el.style.opacity = '0'; el.style.transform = 'translateX(12px)';
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 240);
    }
    el.querySelector('button').addEventListener('click', close);
    if (duration > 0) setTimeout(close, duration);
    return { close: close };
  }

  // ---- Confirm dạng modal (Promise<boolean>) ---------------------------
  function confirmDialog(opts) {
    opts = (typeof opts === 'string') ? { message: opts } : (opts || {});
    var message = opts.message || 'Bạn có chắc chắn?';
    var okText = opts.okText || 'Đồng ý';
    var cancelText = opts.cancelText || 'Hủy';
    var danger = !!opts.danger;
    return new Promise(function (resolve) {
      var overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;z-index:21500;background:rgba(15,23,42,.5);display:flex;align-items:center;justify-content:center;padding:20px;';
      var box = document.createElement('div');
      box.style.cssText = 'background:#fff;border-radius:16px;max-width:400px;width:100%;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center;';
      box.innerHTML =
        '<div style="width:52px;height:52px;border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;background:' + (danger ? '#fef2f2' : '#eff6ff') + ';">' +
          '<i class="bi ' + (danger ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill') + '" style="font-size:1.5rem;color:' + (danger ? '#dc2626' : '#2563eb') + ';"></i></div>' +
        '<div style="font-size:1rem;color:#1e293b;margin-bottom:20px;line-height:1.5;">' + esc(message) + '</div>' +
        '<div style="display:flex;gap:10px;">' +
          '<button class="sup-cf-cancel" style="flex:1;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#475569;font-weight:600;cursor:pointer;">' + esc(cancelText) + '</button>' +
          '<button class="sup-cf-ok" style="flex:1;padding:10px;border:none;border-radius:10px;background:' + (danger ? '#dc2626' : '#0c4c29') + ';color:#fff;font-weight:600;cursor:pointer;">' + esc(okText) + '</button>' +
        '</div>';
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      function done(val) { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); resolve(val); }
      box.querySelector('.sup-cf-ok').addEventListener('click', function () { done(true); });
      box.querySelector('.sup-cf-cancel').addEventListener('click', function () { done(false); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(false); });
      document.addEventListener('keydown', function esc2(e) { if (e.key === 'Escape') { document.removeEventListener('keydown', esc2); done(false); } });
    });
  }

  // ---- Lightbox xem ảnh chi tiết ----------------------------------------
  var _lbEl = null;
  function ensureLightbox() {
    if (_lbEl) return _lbEl;
    _lbEl = document.createElement('div');
    _lbEl.className = 'sup-lightbox';
    _lbEl.style.cssText = 'position:fixed;inset:0;z-index:20000;background:rgba(15,23,42,.86);display:none;align-items:center;justify-content:center;padding:24px;';
    _lbEl.innerHTML =
      '<button type="button" class="sup-lb-close" aria-label="Đóng" style="position:absolute;top:18px;right:24px;width:44px;height:44px;border:none;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;font-size:1.4rem;cursor:pointer;">&times;</button>' +
      '<img class="sup-lb-img" src="" alt="" style="max-width:92vw;max-height:88vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);object-fit:contain;">';
    document.body.appendChild(_lbEl);
    function close() { _lbEl.style.display = 'none'; }
    _lbEl.addEventListener('click', function (e) { if (e.target === _lbEl || e.target.closest('.sup-lb-close')) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    return _lbEl;
  }
  function openLightbox(src) {
    var lb = ensureLightbox();
    lb.querySelector('.sup-lb-img').src = src;
    lb.style.display = 'flex';
  }
  // Bắt click vào ảnh trong 1 container để mở lightbox (event delegation)
  function bindLightbox(container) {
    if (!container || container.__supLb) return;
    container.__supLb = true;
    container.addEventListener('click', function (e) {
      var img = e.target.closest('img[data-lightbox]');
      if (!img) return;
      e.preventDefault();
      openLightbox(img.getAttribute('data-full') || img.src);
    });
  }

  // Lightbox toàn cục: bắt mọi click vào img[data-lightbox] ở bất kỳ đâu
  // (kể cả ticket đã đóng, ảnh chèn động) — không phụ thuộc trang gọi bindLightbox.
  // Dùng capture-phase để chạy TRƯỚC mọi handler khác (tránh stopPropagation chặn).
  if (!document.__supLbGlobal) {
    document.__supLbGlobal = true;
    document.addEventListener('click', function (e) {
      var t = e.target;
      var img = (t && t.closest) ? t.closest('img[data-lightbox]') : null;
      if (!img) return;
      e.preventDefault();
      e.stopPropagation();
      openLightbox(img.getAttribute('data-full') || img.src);
    }, true);
  }

  // ---- Quản lý preview ảnh đính kèm (remove trước khi gửi) --------------
  // Trả về object { files() -> File[], clear(), el }. Đồng bộ với 1 <input type=file>.
  function attachmentPicker(opts) {
    opts = opts || {};
    var maxFiles = opts.maxFiles || 5;
    var previewEl = opts.previewEl;       // nơi render thumbnail
    var triggerBtn = opts.triggerBtn;     // nút bấm chọn ảnh (tùy chọn)
    var store = [];                       // danh sách File đang chọn

    // input ẩn để mở hộp thoại chọn file
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.multiple = true;
    input.style.display = 'none';
    (opts.mount || document.body).appendChild(input);

    function render() {
      if (!previewEl) return;
      if (!store.length) { previewEl.innerHTML = ''; previewEl.style.display = 'none'; return; }
      previewEl.style.display = 'flex';
      previewEl.innerHTML = store.map(function (f, i) {
        var url = URL.createObjectURL(f);
        return '<div class="sup-thumb" data-i="' + i + '" style="position:relative;width:64px;height:64px;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;flex:0 0 auto;">' +
          '<img src="' + url + '" style="width:100%;height:100%;object-fit:cover;display:block;">' +
          '<button type="button" class="sup-thumb-x" aria-label="Xóa" style="position:absolute;top:2px;right:2px;width:20px;height:20px;border:none;border-radius:50%;background:rgba(15,23,42,.7);color:#fff;font-size:12px;line-height:1;cursor:pointer;">&times;</button>' +
          '</div>';
      }).join('');
    }

    function add(fileList) {
      Array.prototype.forEach.call(fileList, function (f) {
        if (store.length >= maxFiles) return;
        if (!/^image\//.test(f.type)) return;
        store.push(f);
      });
      if (fileList.length && store.length >= maxFiles) {
        toast(false, 'Tối đa ' + maxFiles + ' ảnh');
      }
      render();
    }

    input.addEventListener('change', function () { add(input.files); input.value = ''; });
    if (triggerBtn) triggerBtn.addEventListener('click', function (e) { e.preventDefault(); input.click(); });
    if (previewEl) previewEl.addEventListener('click', function (e) {
      var x = e.target.closest('.sup-thumb-x');
      if (!x) return;
      var i = +x.closest('.sup-thumb').dataset.i;
      store.splice(i, 1);
      render();
    });

    return {
      files: function () { return store.slice(); },
      clear: function () { store = []; render(); },
      open: function () { input.click(); },
      el: input
    };
  }

  w.SupportUI = {
    esc: esc, post: post, get: get,
    renderMessage: renderMessage, renderMessageContent: renderMessageContent, statusBadge: statusBadge,
    toast: toast, confirm: confirmDialog,
    STAT: STAT,
    openLightbox: openLightbox, bindLightbox: bindLightbox,
    attachmentPicker: attachmentPicker
  };
})(window);
