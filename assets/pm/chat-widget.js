/* ============================================================
   Chat hỗ trợ trực tuyến 24/7 — logic phía khách/user.
   Cấu hình từ window.PMCHAT_CFG: { base, csrf, isLogged, endpoint }
   Tái dùng: AJAX polling, localStorage giữ phiên, lightbox sẵn có.
   ============================================================ */
(function () {
    'use strict';
    var CFG = window.PMCHAT_CFG || {};
    if (!CFG.endpoint) return;

    var BASE = (CFG.base || '').replace(/\/+$/, '');
    var LS_KEY = 'pmchat_session';
    var POLL_OPEN = 4000;   // panel đang mở
    var POLL_IDLE = 15000;  // panel đóng (chỉ cập nhật badge)

    var state = {
        ticketId: 0,
        lastId: 0,
        status: '',
        opened: false,
        pollTimer: null,
        files: []
    };

    // ---- DOM refs ----
    var launcher = document.getElementById('pmchatLauncher');
    var panel = document.getElementById('pmchatPanel');
    if (!launcher || !panel) return;
    var badge = launcher.querySelector('.pmchat-badge');
    var bodyEl = panel.querySelector('.pmchat-body');
    var guestEl = panel.querySelector('.pmchat-guest');
    var footEl = panel.querySelector('.pmchat-foot');
    var txt = panel.querySelector('#pmchatText');
    var sendBtn = panel.querySelector('#pmchatSend');
    var fileInput = panel.querySelector('#pmchatFile');
    var previews = panel.querySelector('#pmchatPreviews');
    var hsubEl = panel.querySelector('.pmchat-hsub');

    function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
    function updateAssignee(name) {
        if (!hsubEl) return;
        // BUG-003 FIX: Không lộ tên thật nhân viên với người dùng thông thường.
        // Chỉ dùng "name" để biết có/chưa phân công — không hiển thị tên.
        if (name) {
            hsubEl.innerHTML = '<span class="dot"></span> Nhân viên hỗ trợ đang trực';
        } else {
            hsubEl.innerHTML = '<span class="dot"></span> Tư vấn viên 24/7';
        }
    }
    function mediaUrl(u) {
        if (!u) return '';
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(u);
        if (/^https?:\/\//i.test(u)) return u;
        return BASE + '/' + String(u).replace(/^\/+/, '');
    }

    function loadSession() {
        try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); } catch (e) { return {}; }
    }
    function saveSession(obj) {
        try { localStorage.setItem(LS_KEY, JSON.stringify(obj || {})); } catch (e) {}
    }

    // ---- Âm thanh (WebAudio, không cần file) ----
    // Dùng chung 1 AudioContext, phát chuỗi nốt cho từng sự kiện.
    var _audioCtx = null;
    function audioCtx() {
        if (_audioCtx) return _audioCtx;
        try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { _audioCtx = null; }
        return _audioCtx;
    }
    // Tiếng "pop/bubble" kiểu Messenger: pitch quét nhanh từ f0→f1 + lowpass + tắt nhanh.
    // opts: { f0, f1, dur, vol, type, delay }
    function playPop(opts) {
        var ctx = audioCtx();
        if (!ctx) return;
        if (ctx.state === 'suspended' && ctx.resume) { try { ctx.resume(); } catch (e) {} }
        var o = opts || {};
        var f0 = o.f0 || 380, f1 = o.f1 || 720, dur = o.dur || 0.16, vol = o.vol == null ? 0.13 : o.vol;
        var t = ctx.currentTime + (o.delay || 0);

        var osc = ctx.createOscillator();
        osc.type = o.type || 'sine';
        // Glide pitch lên nhanh rồi chững (đường cong mũ tạo cảm giác "nảy")
        osc.frequency.setValueAtTime(f0, t);
        osc.frequency.exponentialRampToValueAtTime(f1, t + dur * 0.45);

        // Lowpass cho âm tròn, "mềm" như bong bóng
        var lp = ctx.createBiquadFilter();
        lp.type = 'lowpass';
        lp.frequency.setValueAtTime(1800, t);
        lp.frequency.exponentialRampToValueAtTime(3200, t + dur * 0.4);

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(vol, t + 0.012);  // tấn công nhanh
        gain.gain.exponentialRampToValueAtTime(0.0001, t + dur); // tắt nhanh

        osc.connect(lp); lp.connect(gain); gain.connect(ctx.destination);
        osc.start(t);
        osc.stop(t + dur + 0.03);
    }

    // Gửi tin: pop trầm, ngắn gọn ("tup").
    function playSendSound() { playPop({ f0: 300, f1: 520, dur: 0.12, vol: 0.09 }); }
    // Nhận tin (đang mở chat): pop sáng, tròn — tiếng tin nhắn quen thuộc.
    function playNotificationSound() { playPop({ f0: 440, f1: 860, dur: 0.16, vol: 0.14 }); }
    // Thông báo (panel đóng / tab ẩn): hai pop liền (ding-dong) nổi bật hơn.
    function playAlertSound() {
        playPop({ f0: 520, f1: 980, dur: 0.15, vol: 0.15 });
        playPop({ f0: 660, f1: 1240, dur: 0.18, vol: 0.13, delay: 0.14 });
    }

    function showBrowserNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                new Notification(title, {
                    body: body,
                    icon: BASE + '/favicon.ico'
                });
            } catch (e) {}
        }
    }

    // Tách marker [[PMCARD:type]]{json} (do admin gửi). Trả {type,data,text} hoặc null.
    function parseCard(content) {
        if (!content) return null;
        var mt = content.match(/^\[\[PMCARD:(product|voucher)\]\](\{[\s\S]*?\})(?:\n([\s\S]*))?$/);
        if (!mt) return null;
        try { return { type: mt[1], data: JSON.parse(mt[2]), text: (mt[3] || '').trim() }; }
        catch (e) { return null; }
    }

    // Đổi content (có thể chứa marker) thành text thân thiện cho thông báo đẩy.
    function cardSnippet(content) {
        var card = parseCard(content);
        if (!card) return content || '';
        var label = card.type === 'product'
            ? ('🛍️ Gợi ý: ' + ((card.data && card.data.name) || 'sản phẩm'))
            : ('🎟️ Mã ưu đãi' + (card.data && card.data.code ? ': ' + card.data.code : ''));
        return card.text ? (label + ' — ' + card.text) : label;
    }

    // Card sản phẩm — phía khách: nút Mua ngay / Thêm giỏ hoạt động.
    function buildProductCardHtml(d) {
        var img = d.img ? mediaUrl(d.img) : '';
        var nameAttr = esc(d.name);
        return '<div class="pmchat-card pmchat-card--product">' +
            (img ? '<div class="pmchat-card-thumb"><img src="' + esc(img) + '" alt=""></div>' : '') +
            '<div class="pmchat-card-info">' +
                (d.cat ? '<div class="pmchat-card-cat">' + esc(d.cat) + '</div>' : '') +
                '<div class="pmchat-card-name">' + esc(d.name) + '</div>' +
                '<div class="pmchat-card-price">' + esc(d.price) + '</div>' +
                '<div class="pmchat-card-actions">' +
                    '<button type="button" class="pmchat-card-btn pmchat-card-buy" data-pid="' + esc(d.id) + '" data-name="' + nameAttr + '">Mua ngay</button>' +
                    '<button type="button" class="pmchat-card-btn pmchat-card-add" data-pid="' + esc(d.id) + '" data-name="' + nameAttr + '"><i class="bi bi-cart-plus"></i> Thêm giỏ</button>' +
                '</div>' +
            '</div></div>';
    }

    // Card voucher gọn: icon + tiêu đề rõ (giảm bao nhiêu, hình thức gì) + mã + điều kiện + nút sao chép.
    function buildVoucherCardHtml(d) {
        var variant = 'pmchat-voux-' + (d.variant || 'order');
        var icon = d.icon || 'bi-percent';
        return '<div class="pmchat-voux ' + variant + '">' +
            '<span class="pmchat-voux-ico"><i class="bi ' + esc(icon) + '"></i></span>' +
            '<div class="pmchat-voux-body">' +
                '<div class="pmchat-voux-label">' + esc(d.title || d.label) + '</div>' +
                '<div class="pmchat-voux-code">Mã: <b>' + esc(d.code) + '</b></div>' +
                '<div class="pmchat-voux-cond">' + esc(d.min || 'Áp dụng mọi đơn') + '</div>' +
                (d.exp ? '<div class="pmchat-voux-exp">' + esc(d.exp) + '</div>' : '') +
            '</div>' +
            '<button type="button" class="pmchat-voux-copy pmchat-card-copy" data-code="' + esc(d.code) + '">Sao chép</button>' +
        '</div>';
    }

    // ---- Render ----
    function getMessageHtml(m) {
        var side = m.sender_type === 'user' ? 'us' : (m.sender_type === 'system' ? 'sys' : 'them');
        var sendingClass = m.is_temp ? ' pmchat-msg-sending' : '';
        var idAttr = m.is_temp ? ' data-temp-id="' + m.id + '"' : ' data-msg-id="' + m.id + '"';

        var html = '<div class="pmchat-msg ' + side + sendingClass + '"' + idAttr + '><div>';
        html += '<div class="pmchat-bubble">';
        var card = parseCard(m.content);
        if (card) {
            html += card.type === 'product' ? buildProductCardHtml(card.data) : buildVoucherCardHtml(card.data);
            if (card.text) html += '<div class="pmchat-card-text">' + esc(card.text) + '</div>';
        } else if (m.content) {
            html += esc(m.content);
        }
        (m.media || []).forEach(function (u) {
            var src = (m.is_temp && u.indexOf('blob:') === 0) ? u : mediaUrl(u);
            html += '<a href="' + esc(src) + '" data-toggle="lightbox"><img src="' + esc(src) + '" alt="ảnh"></a>';
        });
        html += '</div>';
        if (side !== 'sys') {
            var timeText = m.is_temp ? 'Đang gửi...' : (m.time || '');
            html += '<div class="pmchat-time">' + esc(timeText) + '</div>';
        }
        html += '</div></div>';
        return html;
    }

    function renderMessages(list, clear) {
        if (clear) bodyEl.innerHTML = '';
        if (!list || !list.length) return;
        var hasNewThem = false;
        list.forEach(function (m) {
            if (m.id <= state.lastId && !m.is_temp) return;
            // Chống trùng: nếu tin thật này đã có trong DOM (do optimistic send vừa chèn,
            // hoặc poll chạy xen kẽ) thì bỏ qua, không render lần nữa.
            if (!m.is_temp && bodyEl.querySelector('[data-msg-id="' + m.id + '"]')) {
                if (m.id > state.lastId) state.lastId = m.id;
                return;
            }
            if (!m.is_temp) {
                state.lastId = m.id;
            }
            var side = m.sender_type === 'user' ? 'us' : (m.sender_type === 'system' ? 'sys' : 'them');
            if (side !== 'us' && !m.is_temp && !clear) {
                hasNewThem = true;
            }
            var html = getMessageHtml(m);
            bodyEl.insertAdjacentHTML('beforeend', html);
        });
        bodyEl.scrollTop = bodyEl.scrollHeight;

        if (hasNewThem) {
            if (document.hidden || !state.opened) {
                playAlertSound(); // thông báo: panel đóng / tab ẩn
                var lastMsg = list[list.length - 1];
                var notifyBody = lastMsg ? (cardSnippet(lastMsg.content) || '[Đã gửi ảnh]') : 'Có tin nhắn mới từ tư vấn viên.';
                showBrowserNotification('Tin nhắn mới từ Paint&More', notifyBody);
            } else {
                playNotificationSound(); // nhận tin khi đang mở chat
            }
        }
    }

    function setBadge(n) {
        if (!badge) return;
        if (n > 0) { badge.textContent = n > 9 ? '9+' : n; badge.classList.add('show'); }
        else { badge.classList.remove('show'); }
    }

    // ---- API ----
    function api(action, opts) {
        opts = opts || {};
        var url = CFG.endpoint + '?action=' + encodeURIComponent(action);
        var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: {} };
        if (opts.method === 'POST') {
            init.body = opts.body;
            init.headers['X-CSRF-Token'] = CFG.csrf || '';
        } else if (opts.query) {
            url += '&' + opts.query;
        }
        init.headers['X-Requested-With'] = 'XMLHttpRequest';
        return fetch(url, init).then(function (r) { return r.json(); });
    }

    function openSession(guest) {
        var fd = new FormData();
        fd.append('action', 'open');
        fd.append('csrf_token', CFG.csrf || '');
        if (guest) { fd.append('guest_name', guest.name); fd.append('guest_phone', guest.phone); }
        return api('open', { method: 'POST', body: fd }).then(function (res) {
            if (!res.ok) return res;
            state.ticketId = res.ticket_id;
            state.lastId = 0;
            state.status = res.status;
            saveSession({ ticketId: res.ticket_id, code: res.code });
            showChatUI();
            updateAssignee(res.assignee_name);
            renderMessages(res.messages, true);
            return res;
        });
    }

    // Khôi phục phiên đang mở (KHÔNG tạo mới) — dùng khi tải/F5 lại trang.
    // applyUI=true: cập nhật giao diện (mở chat hoặc hiện form khách).
    function resumeSession(applyUI) {
        return api('resume', {}).then(function (res) {
            if (res.ok) {
                state.ticketId = res.ticket_id;
                state.lastId = 0;
                state.status = res.status;
                saveSession({ ticketId: res.ticket_id, code: res.code });
                if (applyUI) { showChatUI(); updateAssignee(res.assignee_name); renderMessages(res.messages, true); if (res.status === 'closed') disableInput('Phiên chat đã kết thúc.'); }
                else { (res.messages || []).forEach(function (m) { if (m.id > state.lastId) state.lastId = m.id; }); }
            } else if (applyUI) {
                // Chưa có phiên: KHÔNG tạo phiên ngay (tránh sinh ticket "rác" khi user
                // chỉ tò mò bấm vào chat). User đã đăng nhập → mở khung chat trống, phiên
                // sẽ được tạo khi gửi tin đầu tiên. Khách → hiện form nhập tên/SĐT.
                if (CFG.isLogged) { showChatUI(); showStartHint(); } else { showGuestForm(); }
            }
            return res;
        }).catch(function () { return { ok: false }; });
    }

    function showGuestForm() { guestEl.style.display = 'flex'; bodyEl.style.display = 'none'; footEl.style.display = 'none'; }
    function showChatUI() { guestEl.style.display = 'none'; bodyEl.style.display = 'flex'; footEl.style.display = 'block'; }
    // Gợi ý khi khung chat trống (chưa tạo phiên) — phiên sẽ tạo khi gửi tin đầu tiên.
    function showStartHint() {
        if (state.ticketId) return;
        bodyEl.innerHTML = '<div class="pmchat-msg them"><div><div class="pmchat-bubble">'
            + 'Paint&amp;More xin chào! Nhập tin nhắn bên dưới để bắt đầu trò chuyện nhé.'
            + '</div></div></div>';
    }

    // Quên phiên hiện tại khi server báo phiên không còn tồn tại (admin đã xoá).
    // Xoá localStorage + reset state để F5 không kẹt vào phiên đã xoá.
    function forgetSession() {
        saveSession(null);
        state.ticketId = 0;
        state.lastId = 0;
        state.status = '';
        if (txt) { txt.disabled = false; txt.placeholder = 'Nhập tin nhắn...'; }
        if (sendBtn) sendBtn.disabled = false;
        if (state.opened) {
            // đang mở panel → đưa về trạng thái khởi đầu (KHÔNG tạo phiên ngay):
            // user đăng nhập → khung trống chờ gửi tin; khách → form nhập tên/SĐT.
            if (CFG.isLogged) { showChatUI(); bodyEl.innerHTML = ''; } else { showGuestForm(); }
        }
    }

    function poll() {
        if (!state.ticketId) return;
        api('poll', { query: 'ticket_id=' + state.ticketId + '&after_id=' + state.lastId }).then(function (res) {
            if (!res.ok) { forgetSession(); return; } // phiên đã bị xoá → quên đi
            state.status = res.status;
            updateAssignee(res.assignee_name);
            if (res.messages && res.messages.length) {
                // nếu panel đóng & có tin admin mới → tăng badge
                if (!state.opened) {
                    var adminNew = res.messages.filter(function (m) { return m.sender_type !== 'user'; }).length;
                    if (adminNew) setBadge(adminNew);
                }
                renderMessages(res.messages, false);
            }
            if (res.status === 'closed') disableInput('Phiên chat đã kết thúc.');
        }).catch(function () {});
    }

    function startPolling() {
        stopPolling();
        var iv = state.opened ? POLL_OPEN : POLL_IDLE;
        state.pollTimer = setInterval(poll, iv);
    }
    function stopPolling() { if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; } }

    function disableInput(msg) {
        if (txt) { txt.disabled = true; txt.placeholder = msg || 'Phiên đã đóng'; }
        if (sendBtn) sendBtn.disabled = true;
    }

    // ---- Send ----
    function doSend() {
        var content = (txt.value || '').trim();
        if (!content && !state.files.length) return;

        // Tạo phiên LƯỜI: nếu chưa có ticket (user đăng nhập vừa mở khung trống) → tạo
        // phiên rồi gửi lại. Khách chưa có phiên → đưa về form nhập tên/SĐT.
        if (!state.ticketId) {
            if (CFG.isLogged) {
                if (state.creating) return; state.creating = true;
                openSession(null).then(function (res) {
                    state.creating = false;
                    if (res && res.ok) { doSend(); }
                    else if (window.toastr) { window.toastr.error((res && res.msg) || 'Không mở được chat.'); }
                }).catch(function () { state.creating = false; });
            } else {
                showGuestForm();
            }
            return;
        }

        // Construct optimistic temporary message
        var tempId = 'temp_' + Date.now();
        var tempMsg = {
            id: tempId,
            sender_type: 'user',
            content: content,
            media: state.files.map(function (f) { return URL.createObjectURL(f); }),
            time: 'Đang gửi...',
            is_temp: true
        };

        // Render optimistic message immediately!
        renderMessages([tempMsg], false);
        playSendSound();

        var fd = new FormData();
        fd.append('action', 'send');
        fd.append('csrf_token', CFG.csrf || '');
        fd.append('ticket_id', state.ticketId);
        fd.append('content', content);
        state.files.forEach(function (f) { fd.append('attachments[]', f); });

        // Reset input fields instantly for ultra responsive feel
        txt.value = '';
        state.files = [];
        renderPreviews();

        api('send', { method: 'POST', body: fd }).then(function (res) {
            if (!res.ok) {
                // Mark temp node as failed
                var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
                if (tempNode) {
                    tempNode.classList.add('pmchat-msg-failed');
                    var timeEl = tempNode.querySelector('.pmchat-time');
                    if (timeEl) timeEl.textContent = 'Gửi lỗi';
                }
                if (window.toastr) { window.toastr.error(res.msg || 'Không gửi được tin.'); }
                else { alert(res.msg || 'Không gửi được tin.'); }
                if (res.status === 'closed') disableInput();
                if (res.session_gone) forgetSession(); // phiên đã bị xoá → quên + clear localStorage
                return;
            }

            // Replace the optimistic message with the final message from the server
            if (res.message) {
                var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
                if (tempNode) {
                    tempNode.outerHTML = getMessageHtml(res.message);
                    if (res.message.id > state.lastId) {
                        state.lastId = res.message.id;
                    }
                } else {
                    renderMessages([res.message], false);
                }
            }
        }).catch(function () {
            // Connection/Network error
            var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
            if (tempNode) {
                tempNode.classList.add('pmchat-msg-failed');
                var timeEl = tempNode.querySelector('.pmchat-time');
                if (timeEl) timeEl.textContent = 'Lỗi kết nối';
            }
        });
    }

    function renderPreviews() {
        if (!previews) return;
        previews.innerHTML = '';
        state.files.forEach(function (f, i) {
            var url = URL.createObjectURL(f);
            var div = document.createElement('div');
            div.className = 'pv';
            div.innerHTML = '<img src="' + url + '"><button type="button" class="rm" data-i="' + i + '">&times;</button>';
            previews.appendChild(div);
        });
    }

    // ---- Mở / đóng panel ----
    function openPanel() {
        panel.classList.add('open');
        launcher.style.display = 'none'; // ẩn nút tròn khi panel đang mở
        state.opened = true;
        setBadge(0);

        // Yêu cầu quyền thông báo trình duyệt
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        if (state.ticketId) {
            // Đã có phiên trong bộ nhớ → hiện hội thoại, lấy tin mới
            showChatUI();
            // nạp lại toàn bộ để chắc chắn không thiếu (reset lastId)
            state.lastId = 0; bodyEl.innerHTML = '';
            resumeSession(true);
        } else {
            // Chưa có trong bộ nhớ → thử khôi phục từ server (cookie/session)
            resumeSession(true);
        }
        startPolling();
    }
    function closePanel() { panel.classList.remove('open'); launcher.style.display = ''; state.opened = false; startPolling(); }

    // ---- Bind events ----
    launcher.addEventListener('click', function () { panel.classList.contains('open') ? closePanel() : openPanel(); });
    var closeBtn = panel.querySelector('.pmchat-close');
    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    // ---- Phóng to / thu nhỏ toàn màn hình ----
    var expandBtn = panel.querySelector('#pmchatExpand');
    if (expandBtn) expandBtn.addEventListener('click', function () {
        var on = panel.classList.toggle('pmchat-panel--expanded');
        var ic = expandBtn.querySelector('i');
        if (ic) ic.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        expandBtn.setAttribute('title', on ? 'Thu nhỏ' : 'Phóng to');
    });

    if (sendBtn) sendBtn.addEventListener('click', doSend);
    if (txt) txt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); }
    });

    panel.addEventListener('paste', function (e) {
        var clipboardData = e.clipboardData || window.clipboardData;
        if (!clipboardData || !clipboardData.items) return;
        var items = clipboardData.items;
        var pastedFiles = [];
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                var file = items[i].getAsFile();
                if (file) {
                    var ext = 'png';
                    if (file.type === 'image/jpeg') ext = 'jpg';
                    else if (file.type === 'image/gif') ext = 'gif';
                    else if (file.type === 'image/webp') ext = 'webp';
                    var filename = 'pasted_image_' + Date.now() + '.' + ext;
                    var renamedFile = new File([file], filename, { type: file.type });
                    pastedFiles.push(renamedFile);
                }
            }
        }
        if (pastedFiles.length > 0) {
            e.preventDefault();
            state.files = state.files.concat(pastedFiles).slice(0, 5);
            renderPreviews();
        }
    });

    var attachBtn = panel.querySelector('#pmchatAttach');
    if (attachBtn && fileInput) attachBtn.addEventListener('click', function () { fileInput.click(); });
    if (fileInput) fileInput.addEventListener('change', function () {
        var arr = Array.prototype.slice.call(fileInput.files || []);
        state.files = state.files.concat(arr).slice(0, 5);
        fileInput.value = '';
        renderPreviews();
    });
    if (previews) previews.addEventListener('click', function (e) {
        var b = e.target.closest('.rm'); if (!b) return;
        state.files.splice(parseInt(b.getAttribute('data-i'), 10), 1); renderPreviews();
    });

    // Guest start
    var startBtn = panel.querySelector('#pmchatGuestStart');
    if (startBtn) startBtn.addEventListener('click', function () {
        var nameInput = panel.querySelector('#pmchatGuestName');
        var phoneInput = panel.querySelector('#pmchatGuestPhone');
        var nameErr = panel.querySelector('#pmchatGuestNameError');
        var phoneErr = panel.querySelector('#pmchatGuestPhoneError');

        // Reset state
        nameInput.classList.remove('is-invalid', 'is-valid');
        phoneInput.classList.remove('is-invalid', 'is-valid');
        if (nameErr) { nameErr.textContent = ''; nameErr.style.display = 'none'; }
        if (phoneErr) { phoneErr.textContent = ''; phoneErr.style.display = 'none'; }

        var nameVal = (nameInput.value || '').trim();
        var phoneVal = (phoneInput.value || '').trim();
        var hasError = false;

        // Validate Name
        if (!nameVal) {
            nameInput.classList.add('is-invalid');
            if (nameErr) { nameErr.textContent = 'Vui lòng nhập họ và tên.'; nameErr.style.display = 'block'; }
            hasError = true;
        } else {
            var cleanName = nameVal.replace(/[<>\/\{\}\[\]\(\)\\\]]/g, '').trim();
            if (cleanName.length < 2 || cleanName.length > 50) {
                nameInput.classList.add('is-invalid');
                if (nameErr) { nameErr.textContent = 'Họ tên từ 2 - 50 ký tự, không chứa ký tự lạ.'; nameErr.style.display = 'block'; }
                hasError = true;
            } else {
                nameInput.classList.add('is-valid');
            }
        }

        // Validate Phone
        var cleanPhone = '';
        if (!phoneVal) {
            phoneInput.classList.add('is-invalid');
            if (phoneErr) { phoneErr.textContent = 'Vui lòng nhập số điện thoại.'; phoneErr.style.display = 'block'; }
            hasError = true;
        } else {
            cleanPhone = phoneVal.replace(/[^\d\+]/g, '');
            if (cleanPhone.indexOf('+84') === 0) {
                cleanPhone = '0' + cleanPhone.substring(3);
            } else if (cleanPhone.indexOf('84') === 0) {
                cleanPhone = '0' + cleanPhone.substring(2);
            }

            if (!/^0(3|5|7|8|9)\d{8}$/.test(cleanPhone)) {
                phoneInput.classList.add('is-invalid');
                if (phoneErr) { phoneErr.textContent = 'Số điện thoại gồm 10 chữ số (đầu 03, 05, 07, 08, 09).'; phoneErr.style.display = 'block'; }
                hasError = true;
            } else {
                phoneInput.classList.add('is-valid');
            }
        }

        if (hasError) return;

        // Yêu cầu quyền thông báo trình duyệt khi click bắt đầu cuộc trò chuyện
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        startBtn.disabled = true;
        openSession({ name: cleanName, phone: cleanPhone }).then(function (res) {
            startBtn.disabled = false;
            if (!res.ok) {
                if (window.toastr) { window.toastr.error(res.msg || 'Không mở được chat.'); }
                else { alert(res.msg || 'Không mở được chat.'); }
            }
        }).catch(function () { startBtn.disabled = false; });
    });

    // Lightbox cho ảnh (nếu thư viện sẵn có)
    bodyEl.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-toggle="lightbox"]');
        if (a && window.Lightbox) { e.preventDefault(); new window.Lightbox(a); return; }

        // Card sản phẩm: Thêm giỏ (tái dùng global addToCartFromCard ở foot.php)
        var addBtn = e.target.closest('.pmchat-card-add');
        if (addBtn) {
            var pid = parseInt(addBtn.getAttribute('data-pid'), 10) || 0;
            var name = addBtn.getAttribute('data-name') || '';
            if (pid && typeof window.addToCartFromCard === 'function') {
                window.addToCartFromCard(pid, name, null, {});
                if (window.toastr) window.toastr.success('Đã thêm "' + name + '" vào giỏ.');
            } else {
                cardAddToCartFallback(pid, name, false);
            }
            return;
        }

        // Card sản phẩm: Mua ngay → set giỏ 1 SP rồi sang checkout
        var buyBtn = e.target.closest('.pmchat-card-buy');
        if (buyBtn) {
            var bpid = parseInt(buyBtn.getAttribute('data-pid'), 10) || 0;
            if (bpid) cardAddToCartFallback(bpid, buyBtn.getAttribute('data-name') || '', true);
            return;
        }

        // Card voucher: sao chép mã
        var copyBtn = e.target.closest('.pmchat-card-copy');
        if (copyBtn) {
            var code = copyBtn.getAttribute('data-code') || '';
            copyVoucherCode(code, copyBtn);
            return;
        }
    });

    // Gọi cart_add / cart_set_single trực tiếp (dự phòng khi không có global helper).
    function cardAddToCartFallback(pid, name, buyNow) {
        if (!pid) return;
        var body = new URLSearchParams();
        body.set('action', buyNow ? 'cart_set_single' : 'cart_add');
        body.set('pid', pid);
        body.set('qty', 1);
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        fetch(BASE + '/core_user/ecommerce/ajax/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': (csrfMeta ? csrfMeta.getAttribute('content') : '') || CFG.csrf || ''
            },
            body: body.toString(),
            credentials: 'include'
        }).then(function (r) { return r.ok ? r.json() : null; })
        .then(function (res) {
            if (!res || !res.ok) {
                if (window.toastr) window.toastr.error((res && res.msg) || 'Không thể thực hiện.');
                return;
            }
            if (buyNow) { window.location.href = BASE + '/checkout'; return; }
            if (window.toastr) window.toastr.success('Đã thêm "' + name + '" vào giỏ.');
            if (typeof window.refreshCartBadge === 'function') window.refreshCartBadge();
        }).catch(function () {
            if (window.toastr) window.toastr.error('Lỗi kết nối.');
        });
    }

    function copyVoucherCode(code, btn) {
        if (!code) return;
        var done = function () {
            if (window.toastr) window.toastr.success('Đã sao chép mã ' + code);
            if (btn) { var old = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check2"></i> Đã chép'; setTimeout(function () { btn.innerHTML = old; }, 1500); }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(done).catch(function () { legacyCopy(code, done); });
        } else { legacyCopy(code, done); }
    }
    function legacyCopy(text, cb) {
        try {
            var ta = document.createElement('textarea'); ta.value = text;
            ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta);
            ta.select(); document.execCommand('copy'); document.body.removeChild(ta); cb && cb();
        } catch (e) {}
    }

    // ---- Khởi tạo nền: KHÔI PHỤC phiên đang mở (user theo session, khách theo cookie
    // guest_key) mà KHÔNG cần nhập lại tên/SĐT, kể cả sau khi F5. Chỉ chạy nền để
    // hiện badge tin chưa đọc; không tự bật panel.
    (function initBackground() {
        resumeSession(false).then(function (res) {
            if (res && res.ok) startPolling(); // có phiên → polling nền cập nhật badge
        });
    })();
})();
