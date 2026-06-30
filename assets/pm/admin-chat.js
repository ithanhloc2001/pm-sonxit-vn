/* ============================================================
   Hộp chat nổi ADMIN — quản lý & trả lời nhiều phiên chat.
   Cấu hình từ window.PMAC_CFG: { base, csrf, endpoint }
   ============================================================ */
(function () {
    'use strict';
    var CFG = window.PMAC_CFG || {};
    if (!CFG.endpoint) return;
    var BASE = (CFG.base || '').replace(/\/+$/, '');
    var POLL_OPEN = 4000, POLL_IDLE = 12000;

    var state = { activeTicket: 0, lastId: 0, opened: false, timer: null, files: [], status: '', filter: 'all', lastUnread: -1 };
    var lastReceiveAt = 0; // mốc thời gian vừa phát âm "nhận tin" (tránh kêu trùng với chuông thông báo)

    var launcher = document.getElementById('pmacLauncher');
    var panel = document.getElementById('pmacPanel');
    if (!launcher || !panel) return;
    var badge = launcher.querySelector('.pmac-badge');
    var listEl = panel.querySelector('#pmacList');
    var bodyEl = panel.querySelector('#pmacBody');
    var convHead = panel.querySelector('#pmacConvHead');
    var mainEl = panel.querySelector('.pmac-main');
    var backBtn = panel.querySelector('#pmacBack');
    var footEl = panel.querySelector('#pmacFoot');
    var txt = panel.querySelector('#pmacText');
    var sendBtn = panel.querySelector('#pmacSend');
    var fileInput = panel.querySelector('#pmacFile');
    var previews = panel.querySelector('#pmacPreviews');
    var expandBtn = panel.querySelector('#pmacExpand');
    // Picker là modal nổi (sibling của panel) → lấy từ document, không từ panel.
    var productPicker = document.getElementById('pmacProductPicker');
    var voucherPicker = document.getElementById('pmacVoucherPicker');
    var productSearch = document.getElementById('pmacProductSearch');
    var productCat = document.getElementById('pmacProductCat');
    var productGrid = document.getElementById('pmacProductGrid');
    var voucherList = document.getElementById('pmacVoucherList');
    var catsLoaded = false;

    function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
    function mediaUrl(u) {
        if (!u) return '';
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(u);
        if (/^https?:\/\//i.test(u)) return u;
        return BASE + '/' + String(u).replace(/^\/+/, '');
    }

    // ---- Âm thanh (WebAudio, không cần file) ----
    var _audioCtx = null;
    function audioCtx() {
        if (_audioCtx) return _audioCtx;
        try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { _audioCtx = null; }
        return _audioCtx;
    }
    // Tiếng "pop/bubble" kiểu Messenger: pitch quét nhanh + lowpass + tắt nhanh.
    function playPop(opts) {
        var ctx = audioCtx();
        if (!ctx) return;
        if (ctx.state === 'suspended' && ctx.resume) { try { ctx.resume(); } catch (e) {} }
        var o = opts || {};
        var f0 = o.f0 || 380, f1 = o.f1 || 720, dur = o.dur || 0.16, vol = o.vol == null ? 0.13 : o.vol;
        var t = ctx.currentTime + (o.delay || 0);

        var osc = ctx.createOscillator();
        osc.type = o.type || 'sine';
        osc.frequency.setValueAtTime(f0, t);
        osc.frequency.exponentialRampToValueAtTime(f1, t + dur * 0.45);

        var lp = ctx.createBiquadFilter();
        lp.type = 'lowpass';
        lp.frequency.setValueAtTime(1800, t);
        lp.frequency.exponentialRampToValueAtTime(3200, t + dur * 0.4);

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(vol, t + 0.012);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

        osc.connect(lp); lp.connect(gain); gain.connect(ctx.destination);
        osc.start(t);
        osc.stop(t + dur + 0.03);
    }
    function playSendSound() { playPop({ f0: 300, f1: 520, dur: 0.12, vol: 0.09 }); }       // gửi tin
    function playReceiveSound() { playPop({ f0: 440, f1: 860, dur: 0.16, vol: 0.14 }); }    // nhận tin (đang mở)
    function playAlertSound() {                                                              // thông báo (panel đóng / phiên khác)
        playPop({ f0: 520, f1: 980, dur: 0.15, vol: 0.15 });
        playPop({ f0: 660, f1: 1240, dur: 0.18, vol: 0.13, delay: 0.14 });
    }

    function api(action, opts) {
        opts = opts || {};
        var url = CFG.endpoint + '?action=' + encodeURIComponent(action);
        var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (opts.method === 'POST') { init.body = opts.body; init.headers['X-CSRF-Token'] = CFG.csrf || ''; }
        else if (opts.query) { url += '&' + opts.query; }
        return fetch(url, init).then(function (r) { return r.json(); });
    }

    function setBadge(n) {
        if (!badge) return;
        if (n > 0) { badge.textContent = n > 9 ? '9+' : n; badge.classList.add('show'); }
        else { badge.classList.remove('show'); }
    }

    // ---- Inbox ----
    function loadInbox() {
        api('inbox', { query: 'filter=' + encodeURIComponent(state.filter) }).then(function (res) {
            if (!res.ok) return;
            var unread = res.unread_total || 0;
            // Thông báo: số tin chưa đọc tăng so với lần poll trước (bỏ lần đầu lastUnread=-1).
            // Nếu phiên đang mở vừa phát âm "nhận tin" (trong ~1.5s) thì bỏ qua để khỏi kêu trùng;
            // tin đến ở phiên khác vẫn kêu chuông thông báo bình thường.
            if (state.lastUnread >= 0 && unread > state.lastUnread && (Date.now() - lastReceiveAt > 1500)) {
                playAlertSound();
            }
            state.lastUnread = unread;
            setBadge(unread);
            renderInbox(res.sessions || []);
        }).catch(function () {});
    }

    function renderInbox(sessions) {
        if (!sessions.length) { listEl.innerHTML = '<div class="pmac-list-empty">Chưa có phiên chat nào.</div>'; return; }
        listEl.innerHTML = sessions.map(function (s) {
            return '<div class="pmac-sess' + (s.unread ? ' unread' : '') + (s.ticket_id === state.activeTicket ? ' active' : '') + '" data-id="' + s.ticket_id + '">' +
                '<div class="nm"><span class="udot"></span>' + esc(s.name) + (s.is_guest ? ' <small style="color:#94a3b8">(khách)</small>' : '') + '</div>' +
                '<div class="sn">' + esc(s.snippet || '...') + '</div>' +
                '<div class="mt">' + esc(s.time || '') + '</div>' +
                '</div>';
        }).join('');
    }

    // ---- Thread ----
    function openThread(ticketId) {
        state.activeTicket = ticketId;
        state.lastId = 0;
        bodyEl.innerHTML = '';
        if (typeof closePickers === 'function') closePickers();
        if (mainEl) mainEl.classList.add('show-conv');  // mobile: trượt sang khung hội thoại
        api('thread', { query: 'ticket_id=' + ticketId }).then(function (res) {
            if (!res.ok) { bodyEl.innerHTML = '<div class="pmac-placeholder">' + esc(res.msg || 'Lỗi tải hội thoại') + '</div>'; return; }
            state.status = res.status;
            convHead.style.display = 'flex';
            convHead.querySelector('.cn').textContent = res.name || 'Khách';
            convHead.querySelector('.cp').textContent = res.phone ? ('• ' + res.phone) : '';
            
            var assEl = convHead.querySelector('#pmacAssignee');
            if (assEl) {
                if (res.assignee_name) {
                    assEl.textContent = 'Phụ trách: ' + res.assignee_name;
                    assEl.style.display = 'inline-flex';
                } else {
                    assEl.style.display = 'none';
                }
            }

            footEl.style.display = res.status === 'closed' ? 'none' : 'block';
            renderMessages(res.messages, true);
            loadInbox();
        }).catch(function () {});
    }

    // Tách marker [[PMCARD:type]]{json} ở đầu content (do server tạo). Trả {type,data,text} hoặc null.
    function parseCard(content) {
        if (!content) return null;
        var mt = content.match(/^\[\[PMCARD:(product|voucher)\]\](\{[\s\S]*?\})(?:\n([\s\S]*))?$/);
        if (!mt) return null;
        try {
            return { type: mt[1], data: JSON.parse(mt[2]), text: (mt[3] || '').trim() };
        } catch (e) { return null; }
    }

    // Card sản phẩm — phía admin: nút chỉ để xem (khách mới bấm mua được).
    function buildProductCardHtml(d) {
        var img = d.img ? mediaUrl(d.img) : '';
        return '<div class="pmac-card pmac-card--product">' +
            (img ? '<div class="pmac-card-thumb"><img src="' + esc(img) + '" alt=""></div>' : '') +
            '<div class="pmac-card-info">' +
                (d.cat ? '<div class="pmac-card-cat">' + esc(d.cat) + '</div>' : '') +
                '<div class="pmac-card-name">' + esc(d.name) + '</div>' +
                '<div class="pmac-card-price">' + esc(d.price) + '</div>' +
                '<div class="pmac-card-note"><i class="bi bi-info-circle"></i> Khách sẽ thấy nút Mua ngay / Thêm giỏ</div>' +
            '</div></div>';
    }

    // Card voucher gọn — đồng bộ với phía khách (tiêu đề rõ + mã + điều kiện).
    function buildVoucherCardHtml(d) {
        var variant = 'pmac-voux-' + (d.variant || 'order');
        var icon = d.icon || 'bi-percent';
        return '<div class="pmac-voux ' + variant + '">' +
            '<span class="pmac-voux-ico"><i class="bi ' + esc(icon) + '"></i></span>' +
            '<div class="pmac-voux-body">' +
                '<div class="pmac-voux-label">' + esc(d.title || d.label) + '</div>' +
                '<div class="pmac-voux-code">Mã: <b>' + esc(d.code) + '</b></div>' +
                '<div class="pmac-voux-cond">' + esc(d.min || 'Áp dụng mọi đơn') + '</div>' +
                (d.exp ? '<div class="pmac-voux-exp">' + esc(d.exp) + '</div>' : '') +
            '</div>' +
        '</div>';
    }

    function getMessageHtml(m) {
        var side = m.sender_type === 'admin' ? 'us' : (m.sender_type === 'system' ? 'sys' : 'them');
        var sendingClass = m.is_temp ? ' pmac-msg-sending' : '';
        var idAttr = m.is_temp ? ' data-temp-id="' + m.id + '"' : ' data-msg-id="' + m.id + '"';

        var html = '<div class="pmac-msg ' + side + sendingClass + '"' + idAttr + '><div><div class="pmac-bubble">';
        var card = parseCard(m.content);
        if (card) {
            html += card.type === 'product' ? buildProductCardHtml(card.data) : buildVoucherCardHtml(card.data);
            if (card.text) html += '<div class="pmac-card-text">' + esc(card.text) + '</div>';
        } else if (m.content) {
            html += esc(m.content);
        }
        (m.media || []).forEach(function (u) {
            var src = (m.is_temp && u.indexOf('blob:') === 0) ? u : mediaUrl(u);
            html += '<a href="' + esc(src) + '" data-toggle="lightbox"><img src="' + esc(src) + '"></a>';
        });
        html += '</div>';
        if (side !== 'sys') {
            var timeText = m.is_temp ? 'Đang gửi...' : (m.time || '');
            html += '<div class="pmac-time">' + esc(timeText) + '</div>';
        }
        html += '</div></div>';
        return html;
    }

    function renderMessages(list, clear) {
        if (clear) bodyEl.innerHTML = '';
        if (!list || !list.length) { if (clear) bodyEl.innerHTML = '<div class="pmac-placeholder">Chưa có tin nhắn.</div>'; return; }
        list.forEach(function (m) {
            if (m.id <= state.lastId && !m.is_temp) return;
            // Chống trùng: tin thật đã có trong DOM (optimistic/poll xen kẽ) → bỏ qua.
            if (!m.is_temp && bodyEl.querySelector('[data-msg-id="' + m.id + '"]')) {
                if (m.id > state.lastId) state.lastId = m.id;
                return;
            }
            if (!m.is_temp) {
                state.lastId = m.id;
            }
            var html = getMessageHtml(m);
            bodyEl.insertAdjacentHTML('beforeend', html);
        });
        bodyEl.scrollTop = bodyEl.scrollHeight;
    }

    // ---- Poll ----
    function poll() {
        // Poll phiên đang mở TRƯỚC để biết có tin mới (phát âm "nhận tin" + đặt mốc lastReceiveAt),
        // rồi mới loadInbox() — nhờ vậy chuông thông báo của inbox không kêu trùng.
        if (state.activeTicket && state.opened) {
            api('poll', { query: 'ticket_id=' + state.activeTicket + '&after_id=' + state.lastId }).then(function (res) {
                if (!res.ok) { loadInbox(); return; }
                state.status = res.status;

                var assEl = convHead.querySelector('#pmacAssignee');
                if (assEl) {
                    if (res.assignee_name) {
                        assEl.textContent = 'Phụ trách: ' + res.assignee_name;
                        assEl.style.display = 'inline-flex';
                    } else {
                        assEl.style.display = 'none';
                    }
                }

                if (res.messages && res.messages.length) {
                    var hasNewThem = res.messages.some(function (m) { return m.sender_type !== 'admin' && m.sender_type !== 'system'; });
                    renderMessages(res.messages, false);
                    if (hasNewThem) { lastReceiveAt = Date.now(); playReceiveSound(); }
                }
                if (res.status === 'closed') footEl.style.display = 'none';
                loadInbox();
            }).catch(function () { loadInbox(); });
        } else {
            loadInbox();
        }
    }
    function startPoll() { stopPoll(); state.timer = setInterval(poll, state.opened ? POLL_OPEN : POLL_IDLE); }
    function stopPoll() { if (state.timer) { clearInterval(state.timer); state.timer = null; } }

    // ---- Send ----
    function doSend() {
        if (!state.activeTicket) return;
        var content = (txt.value || '').trim();
        if (!content && !state.files.length) return;

        // Construct optimistic temporary message
        var tempId = 'temp_' + Date.now();
        var tempMsg = {
            id: tempId,
            sender_type: 'admin',
            content: content,
            media: state.files.map(function (f) { return URL.createObjectURL(f); }),
            time: 'Đang gửi...',
            is_temp: true
        };

        // Render optimistic message instantly!
        renderMessages([tempMsg], false);
        playSendSound();

        var fd = new FormData();
        fd.append('action', 'send'); fd.append('csrf_token', CFG.csrf || '');
        fd.append('ticket_id', state.activeTicket); fd.append('content', content);
        state.files.forEach(function (f) { fd.append('attachments[]', f); });

        // Reset inputs immediately
        txt.value = '';
        state.files = [];
        renderPreviews();

        api('send', { method: 'POST', body: fd }).then(function (res) {
            if (!res.ok) {
                var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
                if (tempNode) {
                    tempNode.classList.add('pmac-msg-failed');
                    var timeEl = tempNode.querySelector('.pmac-time');
                    if (timeEl) timeEl.textContent = 'Gửi lỗi';
                }
                if (window.toastr) { window.toastr.error(res.msg || 'Không gửi được.'); }
                else { alert(res.msg || 'Không gửi được.'); }
                if (res.status === 'closed') footEl.style.display = 'none';
                return;
            }

            // Replace temp message with server message
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
            loadInbox();
        }).catch(function () {
            var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
            if (tempNode) {
                tempNode.classList.add('pmac-msg-failed');
                var timeEl = tempNode.querySelector('.pmac-time');
                if (timeEl) timeEl.textContent = 'Lỗi kết nối';
            }
        });
    }

    // ---- Gửi card gợi ý (sản phẩm / voucher) ----
    // optimisticCard: object dữ liệu card để hiển thị ngay; JSON.stringify đảm bảo hợp lệ.
    function sendCard(cardType, ref, optimisticCard) {
        if (!state.activeTicket) return;
        var tempId = 'temp_' + Date.now();
        var optimisticContent = '[[PMCARD:' + cardType + ']]' + JSON.stringify(optimisticCard || {});
        renderMessages([{ id: tempId, sender_type: 'admin', content: optimisticContent, media: [], time: 'Đang gửi...', is_temp: true }], false);
        playSendSound();

        var fd = new FormData();
        fd.append('action', 'send'); fd.append('csrf_token', CFG.csrf || '');
        fd.append('ticket_id', state.activeTicket); fd.append('content', '');
        fd.append('card_type', cardType);
        if (cardType === 'product') fd.append('card_id', ref);
        else fd.append('voucher_code', ref);

        api('send', { method: 'POST', body: fd }).then(function (res) {
            var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
            if (!res.ok) {
                if (tempNode) { tempNode.classList.add('pmac-msg-failed'); var te = tempNode.querySelector('.pmac-time'); if (te) te.textContent = 'Gửi lỗi'; }
                if (window.toastr) window.toastr.error(res.msg || 'Không gửi được.'); else alert(res.msg || 'Không gửi được.');
                if (res.status === 'closed') footEl.style.display = 'none';
                return;
            }
            if (res.message) {
                if (tempNode) { tempNode.outerHTML = getMessageHtml(res.message); if (res.message.id > state.lastId) state.lastId = res.message.id; }
                else renderMessages([res.message], false);
            }
            loadInbox();
        }).catch(function () {
            var tempNode = bodyEl.querySelector('[data-temp-id="' + tempId + '"]');
            if (tempNode) { tempNode.classList.add('pmac-msg-failed'); var te = tempNode.querySelector('.pmac-time'); if (te) te.textContent = 'Lỗi kết nối'; }
        });
    }

    // ---- Picker sản phẩm ----
    function closePickers() {
        if (productPicker) productPicker.style.display = 'none';
        if (voucherPicker) voucherPicker.style.display = 'none';
    }
    function openProductPicker() {
        if (!state.activeTicket) { if (window.toastr) window.toastr.info('Hãy chọn một phiên chat trước.'); return; }
        closePickers();
        productPicker.style.display = 'flex';
        if (!catsLoaded) {
            catsLoaded = true;
            api('suggest_categories').then(function (res) {
                if (res.ok && res.data) {
                    res.data.forEach(function (c) {
                        var o = document.createElement('option'); o.value = c.id; o.textContent = c.name; productCat.appendChild(o);
                    });
                }
            }).catch(function () {});
        }
        loadProducts();
        productSearch.focus();
    }
    var productSearchTimer = null;
    function loadProducts() {
        var q = encodeURIComponent((productSearch.value || '').trim());
        var cat = parseInt(productCat.value || '0', 10) || 0;
        productGrid.innerHTML = '<div class="pmac-picker-empty">Đang tải…</div>';
        api('suggest_products', { query: 'q=' + q + '&cat_id=' + cat + '&limit=15' }).then(function (res) {
            if (!res.ok || !res.data || !res.data.length) {
                productGrid.innerHTML = '<div class="pmac-picker-empty">Không có sản phẩm phù hợp.</div>'; return;
            }
            productGrid.innerHTML = res.data.map(function (p) {
                var img = p.img ? mediaUrl(p.img) : '';
                return '<div class="pmac-pp-item" data-id="' + p.id + '">' +
                    '<div class="pmac-pp-thumb">' + (img ? '<img src="' + esc(img) + '" alt="">' : '') + '</div>' +
                    '<div class="pmac-pp-name">' + esc(p.name) + '</div>' +
                    '<div class="pmac-pp-price">' + esc(p.price) + '</div>' +
                    '<button type="button" class="pmac-pp-send" data-id="' + p.id + '" data-name="' + esc(p.name) + '">Gửi</button>' +
                '</div>';
            }).join('');
        }).catch(function () { productGrid.innerHTML = '<div class="pmac-picker-empty">Lỗi tải sản phẩm.</div>'; });
    }

    // ---- Picker voucher ----
    function openVoucherPicker() {
        if (!state.activeTicket) { if (window.toastr) window.toastr.info('Hãy chọn một phiên chat trước.'); return; }
        closePickers();
        voucherPicker.style.display = 'flex';
        voucherList.innerHTML = '<div class="pmac-picker-empty">Đang tải…</div>';
        api('suggest_vouchers').then(function (res) {
            if (!res.ok || !res.data || !res.data.length) {
                voucherList.innerHTML = '<div class="pmac-picker-empty">Chưa có mã ưu đãi nào đang hoạt động.</div>'; return;
            }
            // Render card voucher gọn (đồng bộ với chat) + nút "Gửi" bên cạnh.
            voucherList.innerHTML = res.data.map(function (v) {
                return '<div class="pmac-vp-item">' +
                    buildVoucherCardHtml(v) +
                    '<button type="button" class="pmac-vp-send" data-code="' + esc(v.code) + '">Gửi</button>' +
                '</div>';
            }).join('');
        }).catch(function () { voucherList.innerHTML = '<div class="pmac-picker-empty">Lỗi tải mã ưu đãi.</div>'; });
    }

    function renderPreviews() {
        if (!previews) return;
        previews.innerHTML = '';
        state.files.forEach(function (f, i) {
            var div = document.createElement('div'); div.className = 'pv';
            div.innerHTML = '<img src="' + URL.createObjectURL(f) + '"><button type="button" class="rm" data-i="' + i + '">&times;</button>';
            previews.appendChild(div);
        });
    }

    function closeSession() {
        if (!state.activeTicket) return;
        if (window.Swal) {
            window.Swal.fire({
                title: 'Đóng phiên chat này?',
                text: 'Hành động này sẽ đóng cuộc trò chuyện của khách hàng.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'pm-swal-popup',
                    confirmButton: 'pm-swal-confirm',
                    cancelButton: 'pm-swal-cancel'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    executeCloseSession();
                }
            });
        } else {
            if (confirm('Đóng phiên chat này?')) {
                executeCloseSession();
            }
        }
    }

    function executeCloseSession() {
        var fd = new FormData(); fd.append('action', 'close'); fd.append('csrf_token', CFG.csrf || ''); fd.append('ticket_id', state.activeTicket);
        api('close', { method: 'POST', body: fd }).then(function (res) {
            if (res.ok) { 
                if (window.toastr) window.toastr.success('Đã đóng phiên chat.');
                footEl.style.display = 'none'; 
                openThread(state.activeTicket); 
                loadInbox(); 
            }
        });
    }

    // ---- Panel ----
    function openPanel() { panel.classList.add('open'); launcher.style.display = 'none'; state.opened = true; setBadge(0); loadInbox(); startPoll(); }
    function closePanel() { panel.classList.remove('open'); launcher.style.display = ''; state.opened = false; startPoll(); }

    launcher.addEventListener('click', function () { panel.classList.contains('open') ? closePanel() : openPanel(); });
    var closeBtn = panel.querySelector('.pmac-close'); if (closeBtn) closeBtn.addEventListener('click', closePanel);
    listEl.addEventListener('click', function (e) { var s = e.target.closest('.pmac-sess'); if (s) openThread(parseInt(s.getAttribute('data-id'), 10)); });
    if (backBtn) backBtn.addEventListener('click', function () { if (mainEl) mainEl.classList.remove('show-conv'); });
    if (sendBtn) sendBtn.addEventListener('click', doSend);
    if (txt) txt.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); } });

    // ---- Phóng to / thu nhỏ panel ----
    if (expandBtn) expandBtn.addEventListener('click', function () {
        var on = panel.classList.toggle('pmac-panel--expanded');
        var ic = expandBtn.querySelector('i');
        if (ic) ic.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        expandBtn.setAttribute('title', on ? 'Thu nhỏ' : 'Phóng to');
    });

    // ---- Mở picker gợi ý ----
    var btnSugProduct = panel.querySelector('#pmacSuggestProduct');
    var btnSugVoucher = panel.querySelector('#pmacSuggestVoucher');
    if (btnSugProduct) btnSugProduct.addEventListener('click', openProductPicker);
    if (btnSugVoucher) btnSugVoucher.addEventListener('click', openVoucherPicker);

    // Đóng picker: nút × và click ra nền mờ (backdrop)
    document.querySelectorAll('.pmac-picker-close, .pmac-modal-backdrop').forEach(function (b) {
        b.addEventListener('click', closePickers);
    });

    // Tìm sản phẩm (debounce) + đổi danh mục
    if (productSearch) productSearch.addEventListener('input', function () {
        clearTimeout(productSearchTimer);
        productSearchTimer = setTimeout(loadProducts, 350);
    });
    if (productCat) productCat.addEventListener('change', loadProducts);

    // Gửi card sản phẩm
    if (productGrid) productGrid.addEventListener('click', function (e) {
        var btn = e.target.closest('.pmac-pp-send'); if (!btn) return;
        var id = btn.getAttribute('data-id');
        sendCard('product', id, {
            id: parseInt(id, 10) || 0,
            name: btn.getAttribute('data-name') || '',
            price: '…', img: '', cat: ''
        });
        closePickers();
    });

    // Gửi card voucher
    if (voucherList) voucherList.addEventListener('click', function (e) {
        var btn = e.target.closest('.pmac-vp-send'); if (!btn) return;
        var code = btn.getAttribute('data-code');
        sendCard('voucher', code, { code: code, title: 'Mã ưu đãi', brand: 'Giảm giá', icon: 'bi-percent', variant: 'order', min: '', target: 'order', exp: '' });
        closePickers();
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

    function deleteSession() {
        if (!state.activeTicket) return;
        if (window.Swal) {
            window.Swal.fire({
                title: 'Xoá cuộc trò chuyện?',
                text: 'Bạn có chắc chắn muốn xoá vĩnh viễn cuộc trò chuyện này? Hành động này không thể hoàn tác.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa ngay',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'pm-swal-popup',
                    confirmButton: 'pm-swal-confirm',
                    cancelButton: 'pm-swal-cancel'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    executeDeleteSession();
                }
            });
        } else {
            if (confirm('Bạn có chắc chắn muốn xoá vĩnh viễn cuộc trò chuyện này? Hành động này không thể hoàn tác.')) {
                executeDeleteSession();
            }
        }
    }

    function executeDeleteSession() {
        var fd = new FormData(); fd.append('action', 'delete'); fd.append('csrf_token', CFG.csrf || ''); fd.append('ticket_id', state.activeTicket);
        api('delete', { method: 'POST', body: fd }).then(function (res) {
            if (res.ok) {
                if (window.toastr) window.toastr.success('Đã xoá cuộc trò chuyện.');
                state.activeTicket = 0;
                convHead.style.display = 'none';
                footEl.style.display = 'none';
                bodyEl.innerHTML = '<div class="pmac-placeholder">Chọn một phiên chat bên trái để trả lời.</div>';
                if (mainEl) mainEl.classList.remove('show-conv');  // mobile: quay về danh sách
                loadInbox();
            } else {
                if (window.toastr) { window.toastr.error(res.msg || 'Không thể xoá cuộc trò chuyện.'); }
                else { alert(res.msg || 'Không thể xoá cuộc trò chuyện.'); }
            }
        });
    }

    // Bind bộ lọc click
    panel.querySelectorAll('.pmac-filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            panel.querySelectorAll('.pmac-filter-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            state.filter = btn.getAttribute('data-filter') || 'all';
            loadInbox();
        });
    });

    var closeSessBtn = panel.querySelector('.pmac-close-sess'); if (closeSessBtn) closeSessBtn.addEventListener('click', closeSession);
    var deleteSessBtn = panel.querySelector('.pmac-delete-sess'); if (deleteSessBtn) deleteSessBtn.addEventListener('click', deleteSession);
    var attachBtn = panel.querySelector('#pmacAttach'); if (attachBtn && fileInput) attachBtn.addEventListener('click', function () { fileInput.click(); });
    if (fileInput) fileInput.addEventListener('change', function () {
        state.files = state.files.concat(Array.prototype.slice.call(fileInput.files || [])).slice(0, 5);
        fileInput.value = ''; renderPreviews();
    });
    if (previews) previews.addEventListener('click', function (e) { var b = e.target.closest('.rm'); if (b) { state.files.splice(+b.getAttribute('data-i'), 1); renderPreviews(); } });
    bodyEl.addEventListener('click', function (e) { var a = e.target.closest('a[data-toggle="lightbox"]'); if (a && window.Lightbox) { e.preventDefault(); new window.Lightbox(a); } });

    // Khởi tạo polling nền để hiện badge tin chưa đọc
    startPoll();
    loadInbox();
})();
