<?php
require_once __DIR__ . '/../../core/support/support_common.php';
support_ensure_tables($ithanhloc);

$curUserId = (int)($_SESSION['user_id'] ?? 0);
$csrf = (string)($_SESSION['csrf_token'] ?? '');
$cats = support_categories();
$pris = support_priorities();
$statuses = support_statuses();

$code  = strtoupper(trim((string)($_GET['code'] ?? '')));
$phone = (string)($_GET['phone'] ?? '');

// Tải ticket
$ticket = null;
if ($code !== '') {
    $stmt = $ithanhloc->prepare('
        SELECT t.*, u.full_name AS user_name, u.phone AS user_phone 
        FROM support_ticket t 
        LEFT JOIN users u ON u.id = t.user_id 
        WHERE t.code = ? AND t.is_active = 1 
        LIMIT 1
    ');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Kiểm tra quyền truy cập
$canView = false;
if ($ticket) {
    if ($curUserId > 0 && (int)$ticket['user_id'] === $curUserId) {
        $canView = true;
    } else {
        $guestKey = function_exists('app_guest_key') ? app_guest_key() : '';
        if (!empty($ticket['guest_key']) && $guestKey !== '' && hash_equals((string)$ticket['guest_key'], $guestKey)) {
            $canView = true;
        } elseif (!empty($ticket['guest_phone'])) {
            $tp = clean_phone_digits((string)$ticket['guest_phone']);
            $ip = clean_phone_digits($phone);
            if ($ip !== '' && hash_equals($tp, $ip)) $canView = true;
        }
    }
}

if (!$ticket || !$canView) {
    echo '<div class="py-5"><div class="container text-center" style="max-width:520px;">'
        . '<i class="bi bi-shield-lock fs-1 text-muted d-block mb-3"></i>'
        . '<h3 class="h5 fw-bold">Không thể xem yêu cầu</h3>'
        . '<p class="text-muted">Yêu cầu không tồn tại hoặc bạn không có quyền truy cập. '
        . 'Khách vãng lai vui lòng tra cứu kèm số điện thoại tại <a href="/support">trang Hỗ trợ</a>.</p>'
        . '</div></div>';
    return;
}

// Tải hội thoại
$messages = [];
$tid = (int)$ticket['id'];
$res = $ithanhloc->query('SELECT * FROM support_ticket_message WHERE ticket_id = ' . $tid . ' AND is_active = 1 ORDER BY id ASC');
if ($res) { while ($m = $res->fetch_assoc()) $messages[] = $m; }

$statusLabel = $statuses[$ticket['status']] ?? $ticket['status'];
$isClosed = (string)$ticket['status'] === 'closed';
$bUrl = (string)($baseUrl ?? '');
?>
<?php
$statusColors = ['open' => '#2563eb', 'pending' => '#b45309', 'resolved' => '#15803d', 'closed' => '#64748b'];
$sc = $statusColors[$ticket['status']] ?? '#64748b';
?>
<style>
.sup-icon-btn{width:42px;height:42px;border-radius:12px;border:1px solid var(--bs-border-color,#e2e8f0);background:#fff;color:var(--bs-secondary-color,#475569);display:inline-flex;align-items:center;justify-content:center;font-size:1.05rem;cursor:pointer;transition:.15s;flex:0 0 auto;}
.sup-icon-btn:hover{background:var(--bs-success-bg-subtle,#f0fdf4);color:var(--theme-primary,#0c4c29);border-color:var(--theme-primary,#0c4c29);}
.sup-send-btn{height:42px;border:none;border-radius:12px;background:var(--theme-primary,#0c4c29);color:#fff;padding:0 22px;font-weight:600;cursor:pointer;flex:0 0 auto;}
.sup-send-btn:disabled{opacity:.6;cursor:default;}
.sup-chat-shell{display:flex;flex-direction:column;height:calc(100vh - 210px);min-height:460px;background:#fff;border:1px solid var(--bs-border-color,#e9eef5);border-radius:1rem;overflow:hidden;}
.sup-chat-head{padding:14px 18px;border-bottom:1px solid var(--bs-border-color-translucent,#eef2f7);}
.sup-chat-body{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;background:var(--bs-tertiary-bg,#f7faf9);}
.sup-chat-body::-webkit-scrollbar{width:8px;}
.sup-chat-body::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px;}
.sup-composer{border-top:1px solid var(--bs-border-color-translucent,#eef2f7);padding:12px 14px;background:#fff;}
.sup-composer textarea{border:1px solid var(--bs-border-color,#e2e8f0);border-radius:12px;resize:none;}
.sup-composer textarea:focus{border-color:var(--theme-primary,#0c4c29);box-shadow:0 0 0 .15rem rgba(12,76,41,.12);}
.sup-info-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px dashed var(--bs-border-color-translucent,#eef2f7);font-size:.88rem;}
.sup-info-row:last-child{border-bottom:none;}
.sup-info-row .lbl{color:var(--bs-secondary-color,#94a3b8);}
.sup-info-row .val{font-weight:600;color:#1e293b;text-align:right;}

/* ── Mobile status strip (visible only on mobile) ── */
.sup-mobile-strip{display:none;background:#fff;border:1px solid var(--bs-border-color,#e9eef5);border-radius:.875rem;padding:12px 14px;margin-bottom:12px;overflow-x:auto;}
.sup-mobile-strip::-webkit-scrollbar{display:none;}
.sup-chip-row{display:flex;gap:8px;flex-wrap:nowrap;white-space:nowrap;align-items:center;}
.sup-chip{display:inline-flex;flex-direction:column;align-items:flex-start;background:var(--bs-tertiary-bg,#f8fafc);border:1px solid var(--bs-border-color,#e9eef5);border-radius:10px;padding:7px 12px;min-width:80px;flex-shrink:0;}
.sup-chip .chip-lbl{font-size:.68rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px;}
.sup-chip .chip-val{font-size:.82rem;font-weight:700;color:#1e293b;}

@media(max-width:991.98px){
  /* Panel order on mobile: info first, then chat */
  .sup-info-col{order:-1;}

  /* Mobile strip visible */
  .sup-mobile-strip{display:block;}

  /* Compact info panel on mobile — chips grid instead of rows */
  .sup-info-card-desktop{display:none;}

  /* Chat shell height on mobile */
  .sup-chat-shell{height:auto;min-height:0;border-radius:.875rem;}
  .sup-chat-body{max-height:55vh;min-height:220px;}

  /* Composer full-width on mobile */
  .sup-composer .d-flex.align-items-end{flex-wrap:wrap;gap:8px;}
  .sup-composer textarea{width:100%;order:0;min-height:60px;border-radius:10px;}
  .sup-composer .d-flex.align-items-end > .sup-icon-btn{order:1;}
  .sup-composer .d-flex.align-items-end > .sup-send-btn{order:2;flex:1;justify-content:center;border-radius:10px;height:44px;font-size:.9rem;}

  /* Close & new-ticket actions: stack side by side */
  .sup-action-col .card{display:none;} /* hide desktop sidebar cards */
  .sup-mobile-actions{display:flex !important;gap:8px;margin-top:12px;}
}
@media(min-width:992px){
  .sup-mobile-actions{display:none !important;}
}
</style>
<div class="py-4">
  <div class="container">
    <!-- Header chuẩn hệ thống -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background-color:rgba(12,76,41,.1);border:1px solid rgba(12,76,41,.15);">
          <i class="bi bi-life-preserver fs-4" style="color:var(--theme-primary,#0c4c29);"></i>
        </div>
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h1 class="h3 mb-0 fw-bold" style="font-size:1.45rem;color:#1e293b;letter-spacing:-0.01em;">Chi tiết yêu cầu</h1>
            <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" style="font-size:.72rem;"><?= h($ticket['code']) ?></span>
          </div>
          <p class="text-muted mb-0 small" style="font-size:.82rem;line-height:1.45;">Theo dõi và trao đổi với nhân viên hỗ trợ về yêu cầu của bạn.</p>
        </div>
      </div>
      <a href="<?= h($baseUrl) ?>/support" class="btn btn-outline-secondary d-flex align-items-center gap-2"><i class="bi bi-arrow-left"></i><span class="">Danh sách</span></a>
    </div>

    <!-- Mobile status strip (hidden on desktop via CSS) -->
    <div class="sup-mobile-strip d-lg-none" role="region" aria-label="Thông tin yêu cầu">
      <div class="sup-chip-row">
        <div class="sup-chip">
          <span class="chip-lbl">Mã</span>
          <span class="chip-val" style="font-size:.78rem;letter-spacing:.02em;"><?= h($ticket['code']) ?></span>
        </div>
        <div class="sup-chip">
          <span class="chip-lbl">Trạng thái</span>
          <span class="chip-val">
            <span class="badge rounded-pill" style="background:<?= $sc ?>1a;color:<?= $sc ?>;font-size:.76rem;"><?= h($statusLabel) ?></span>
          </span>
        </div>
        <div class="sup-chip">
          <span class="chip-lbl">Loại</span>
          <span class="chip-val" style="font-size:.78rem;"><?= h($cats[$ticket['category']] ?? $ticket['category']) ?></span>
        </div>
        <div class="sup-chip">
          <span class="chip-lbl">Tạo lúc</span>
          <span class="chip-val" style="font-size:.75rem;"><?= h(date('d/m H:i', strtotime((string)$ticket['created_at']))) ?></span>
        </div>
        <?php if (!empty($ticket['last_reply_at'])): ?>
        <div class="sup-chip">
          <span class="chip-lbl">Cập nhật</span>
          <span class="chip-val" style="font-size:.75rem;"><?= h(date('d/m H:i', strtotime((string)$ticket['last_reply_at']))) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($ticket['order_id'])): ?>
        <div class="sup-chip">
          <span class="chip-lbl">Đơn hàng</span>
          <span class="chip-val" style="font-size:.78rem;"><?= h($ticket['order_id']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mobile quick actions (close + new ticket) — hidden on desktop -->
    <?php if (!$isClosed): ?>
    <div class="sup-mobile-actions d-none" style="margin-bottom:10px;">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="supCloseBtnMobile"
        data-code="<?= h($ticket['code']) ?>" data-phone="<?= h($phone) ?>" style="flex:1;">
        <i class="bi bi-check2-circle me-1"></i>Đã giải quyết
      </button>
      <a href="<?= h($baseUrl) ?>/support?create=1" class="btn btn-sm" style="flex:1;background:var(--theme-primary,#0c4c29);color:#fff;">
        <i class="bi bi-plus-lg me-1"></i>Yêu cầu mới
      </a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
      <!-- Khung chat -->
      <div class="col-lg-8">
        <div class="sup-chat-shell shadow-sm">
          <div class="sup-chat-head">
            <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom border-light">
              <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:40px;height:40px;background:rgba(12,76,41,.1);color:var(--theme-primary,#0c4c29);font-weight:600;font-size:1.1rem;border:1px solid rgba(12,76,41,.15);">
                <i class="bi bi-person-fill"></i>
              </div>
              <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                  <span class="fw-bold text-dark" style="font-size:1rem;letter-spacing:-0.01em;">
                    <?= h($ticket['user_id'] > 0 ? ($ticket['user_name'] ?: '#' . $ticket['user_id']) : ($ticket['guest_name'] ?: 'Khách hàng')) ?>
                  </span>
                  <div class="d-flex align-items-center gap-1.5 style-badges">
                    <span class="badge rounded-pill" style="background:rgba(12,76,41,.1);color:var(--theme-primary,#0c4c29);font-size:.7rem;"><?= h($ticket['code']) ?></span>
                    <span class="badge rounded-pill" style="background:<?= $sc ?>1a;color:<?= $sc ?>;font-size:.7rem;"><?= h($statusLabel) ?></span>
                    <?php if ($ticket['priority'] === 'high'): ?><span class="badge text-bg-danger rounded-pill px-2" style="font-size:.7rem;">Ưu tiên cao</span><?php endif; ?>
                  </div>
                </div>
                <div class="text-muted small mt-0.5" style="font-size:.75rem;">
                  Khách hàng gửi yêu cầu hỗ trợ
                </div>
              </div>
            </div>
            <h2 class="h6 fw-bold mb-0 text-dark" style="font-size:1.05rem; line-height:1.45;"><?= h($ticket['subject']) ?></h2>
          </div>

          <div class="sup-chat-body" id="supThread">
            <!-- System message for Ticket Creation Time -->
            <div class="text-center my-2 p-2 rounded-3" style="background: rgba(0,0,0,0.03); font-size: 0.78rem; color: #64748b; border: 1px dashed rgba(0,0,0,0.06);">
              <i class="bi bi-calendar-check me-1"></i>
              Yêu cầu được tạo lúc <strong><?= h(date('H:i d/m/Y', strtotime((string)$ticket['created_at']))) ?></strong>
            </div>
            <?php foreach ($messages as $m):
              $isAdmin = ($m['sender_type'] === 'admin');
              $isSystem = ($m['sender_type'] === 'system');
              $media = support_media_urls($m['media_json'] ?? '', $bUrl);
            ?>
              <?php if ($isSystem): ?>
                <div class="text-center small text-muted"><i class="bi bi-info-circle me-1"></i><?= h($m['content']) ?></div>
              <?php else: ?>
                <div class="d-flex <?= $isAdmin ? '' : 'flex-row-reverse' ?> gap-2">
                  <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                       style="width:36px;height:36px;background:<?= $isAdmin ? 'var(--theme-primary,#0c4c29)' : '#e2e8f0' ?>;color:<?= $isAdmin ? '#fff' : '#475569' ?>;">
                    <i class="bi <?= $isAdmin ? 'bi-headset' : 'bi-person' ?>"></i>
                  </div>
                  <div class="p-3 rounded-4 shadow-sm" style="max-width:78%;background:<?= $isAdmin ? 'var(--bs-success-bg-subtle,#f0fdf4)' : '#fff' ?>;border:1px solid var(--bs-border-color,#e2e8f0);">
                    <div class="fw-semibold small mb-1" style="color:<?= $isAdmin ? 'var(--theme-primary,#0c4c29)' : '#475569' ?>;">
                      <?= $isAdmin ? 'Nhân viên hỗ trợ' : 'Bạn' ?>
                    </div>
                    <div style="white-space:pre-wrap;word-break:break-word;"><?= support_render_message_content((string)$m['content']) ?></div>
                    <?php if ($media): ?>
                      <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php foreach ($media as $url): ?>
                          <img src="<?= h($url) ?>" data-lightbox data-full="<?= h($url) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:zoom-in;">
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <div class="text-muted mt-1" style="font-size:.72rem;"><?= h(date('H:i d/m/Y', strtotime((string)$m['created_at']))) ?></div>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <?php if ($isClosed): ?>
            <div class="sup-composer text-center small text-muted">
              Yêu cầu đã đóng. Cần thêm hỗ trợ? <a href="/support">Tạo yêu cầu mới</a>.
            </div>
          <?php else: ?>
            <div class="sup-composer">
              <form id="supReplyForm" onsubmit="return false;" enctype="multipart/form-data">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="code" value="<?= h($ticket['code']) ?>">
                <input type="hidden" name="phone" value="<?= h($phone) ?>">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <div class="d-flex flex-wrap gap-2 mb-2" id="supPreview" style="display:none;"></div>
                <div class="d-flex align-items-end gap-2">
                  <button type="button" class="sup-icon-btn" id="supAttachBtn" title="Đính kèm ảnh"><i class="bi bi-image"></i></button>
                  <textarea name="content" class="form-control" rows="1" required placeholder="Nhập phản hồi của bạn..." style="max-height:140px;"></textarea>
                  <button type="submit" class="sup-send-btn" id="supSendBtn"><i class="bi bi-send me-1"></i>Gửi</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Panel thông tin ticket (on mobile: moves above chat via order CSS) -->
      <div class="col-lg-4 sup-info-col">
        <div class="card border-0 shadow-sm rounded-4 sup-info-card-desktop">
          <div class="card-body">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-info-circle me-1 text-muted"></i>Thông tin yêu cầu</h2>
            <div class="sup-info-row">
              <span class="lbl">Mã tra cứu</span>
              <span class="val"><?= h($ticket['code']) ?></span>
            </div>
            <div class="sup-info-row">
              <span class="lbl">Trạng thái</span>
              <span class="val"><span class="badge rounded-pill" style="background:<?= $sc ?>1a;color:<?= $sc ?>;"><?= h($statusLabel) ?></span></span>
            </div>
            <div class="sup-info-row">
              <span class="lbl">Loại yêu cầu</span>
              <span class="val"><?= h($cats[$ticket['category']] ?? $ticket['category']) ?></span>
            </div>
            <div class="sup-info-row">
              <span class="lbl">Ưu tiên</span>
              <span class="val"><?= h($pris[$ticket['priority']] ?? $ticket['priority']) ?></span>
            </div>
            <?php if (!empty($ticket['order_id'])): ?>
            <div class="sup-info-row">
              <span class="lbl">Đơn hàng</span>
              <span class="val"><a href="<?= h($baseUrl) ?>/order" class="text-decoration-none"><?= h($ticket['order_id']) ?></a></span>
            </div>
            <?php endif; ?>
            <div class="sup-info-row">
              <span class="lbl">Tạo lúc</span>
              <span class="val"><?= h(date('H:i d/m/Y', strtotime((string)$ticket['created_at']))) ?></span>
            </div>
            <?php if (!empty($ticket['last_reply_at'])): ?>
            <div class="sup-info-row">
              <span class="lbl">Cập nhật</span>
              <span class="val"><?= h(date('H:i d/m/Y', strtotime((string)$ticket['last_reply_at']))) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$isClosed): ?>
        <div class="card border-0 shadow-sm rounded-4 mt-3">
          <div class="card-body">
            <h2 class="h6 fw-bold mb-1"><i class="bi bi-check2-circle me-1 text-muted"></i>Vấn đề đã được giải quyết?</h2>
            <p class="small text-muted mb-2">Nếu bạn không cần hỗ trợ thêm, hãy đóng yêu cầu này. Sau khi đóng sẽ không thể trả lời tiếp.</p>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="supCloseBtn"
              data-code="<?= h($ticket['code']) ?>" data-phone="<?= h($phone) ?>">
              <i class="bi bi-check2-circle me-1"></i>Đóng yêu cầu
            </button>
          </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 mt-3" style="background:var(--bs-success-bg-subtle,#f0fdf4);">
          <div class="card-body text-center">
            <i class="bi bi-headset fs-3" style="color:var(--theme-primary,#0c4c29);"></i>
            <p class="small text-muted mb-2 mt-2">Cần hỗ trợ thêm về vấn đề khác?</p>
            <a href="<?= h($baseUrl) ?>/support?create=1" class="btn btn-sm w-100" style="background:var(--theme-primary,#0c4c29);color:#fff;"><i class="bi bi-plus-lg me-1"></i>Tạo yêu cầu mới</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= h($basePath) ?>/core/support/support.js?v=<?= @filemtime(__DIR__ . '/../../core/support/support.js') ?: time() ?>"></script>
<script>
(function () {
  var AJAX = '<?= h($basePath) ?>/core_user/support/ajax/ticket.php';
  var f = document.getElementById('supReplyForm');
  if (!f) return;
  var thread = document.getElementById('supThread');
  var csrf = f.querySelector('[name=csrf_token]').value;
  var ta = f.querySelector('textarea');
  var btn = document.getElementById('supSendBtn');
  var picker = null;

  var hasUI = (typeof SupportUI !== 'undefined');
  if (hasUI) {
    try { if (thread) SupportUI.bindLightbox(thread); } catch (err) {}
    try {
      if (SupportUI.attachmentPicker) {
        picker = SupportUI.attachmentPicker({
          maxFiles: 5,
          previewEl: document.getElementById('supPreview'),
          triggerBtn: document.getElementById('supAttachBtn'),
          mount: f
        });
      }
    } catch (err) { picker = null; }
  }

  // Fallback: nếu không có attachmentPicker (vd support.js bản cũ/cache),
  // vẫn cho phép đính kèm + preview + xóa bằng input file thủ công.
  if (!picker) {
    var fbInput = document.createElement('input');
    fbInput.type = 'file'; fbInput.accept = 'image/*'; fbInput.multiple = true; fbInput.style.display = 'none';
    f.appendChild(fbInput);
    var fbStore = [];
    var fbPreview = document.getElementById('supPreview');
    function fbRender() {
      if (!fbPreview) return;
      if (!fbStore.length) { fbPreview.innerHTML = ''; fbPreview.style.display = 'none'; return; }
      fbPreview.style.display = 'flex';
      fbPreview.innerHTML = fbStore.map(function (file, i) {
        return '<div class="sup-thumb" data-i="' + i + '" style="position:relative;width:64px;height:64px;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">' +
          '<img src="' + URL.createObjectURL(file) + '" style="width:100%;height:100%;object-fit:cover;display:block;">' +
          '<button type="button" class="sup-thumb-x" style="position:absolute;top:2px;right:2px;width:20px;height:20px;border:none;border-radius:50%;background:rgba(15,23,42,.7);color:#fff;font-size:12px;line-height:1;cursor:pointer;">&times;</button>' +
          '</div>';
      }).join('');
    }
    fbInput.addEventListener('change', function () {
      Array.prototype.forEach.call(fbInput.files, function (file) { if (fbStore.length < 5 && /^image\//.test(file.type)) fbStore.push(file); });
      fbInput.value = ''; fbRender();
    });
    var attachBtn = document.getElementById('supAttachBtn');
    if (attachBtn) attachBtn.addEventListener('click', function (e) { e.preventDefault(); fbInput.click(); });
    if (fbPreview) fbPreview.addEventListener('click', function (e) {
      var x = e.target.closest('.sup-thumb-x'); if (!x) return;
      fbStore.splice(+x.closest('.sup-thumb').dataset.i, 1); fbRender();
    });
    picker = { files: function () { return fbStore.slice(); }, clear: function () { fbStore = []; fbRender(); } };
  }

  // Fallback lightbox cho ảnh trong luồng (nếu bindLightbox/listener toàn cục thiếu)
  if (thread && !thread.__supLbFb) {
    thread.__supLbFb = true;
    thread.addEventListener('click', function (e) {
      var img = e.target.closest && e.target.closest('img[data-lightbox]');
      if (!img) return;
      e.preventDefault();
      if (hasUI && SupportUI.openLightbox) { SupportUI.openLightbox(img.getAttribute('data-full') || img.src); return; }
      window.open(img.getAttribute('data-full') || img.src, '_blank');
    });
  }

  // textarea tự giãn + Ctrl/Cmd+Enter
  ta.addEventListener('input', function () { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 140) + 'px'; });
  ta.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); send(); } });

  function notify(ok, msg) {
    if (hasUI && SupportUI.toast) SupportUI.toast(ok, msg);
    else if (!ok) alert(msg);
  }

  function send() {
    var content = ta.value.trim();
    var files = picker ? picker.files() : [];
    if (!content && !files.length) { ta.focus(); return; }
    btn.disabled = true;

    var fd = new FormData();
    fd.append('action', 'reply');
    fd.append('code', f.querySelector('[name=code]').value);
    fd.append('phone', f.querySelector('[name=phone]').value);
    fd.append('csrf_token', csrf);
    fd.append('content', content);
    files.forEach(function (file) { fd.append('attachments[]', file); });

    // Gửi bằng fetch trực tiếp — KHÔNG phụ thuộc SupportUI để đảm bảo luôn POST AJAX.
    fetch(AJAX, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf }
    }).then(function (r) { return r.json(); }).then(function (d) {
      btn.disabled = false;
      if (d && d.ok) {
        if (d.message && thread && hasUI && SupportUI.renderMessage) {
          thread.insertAdjacentHTML('beforeend', SupportUI.renderMessage(d.message, { adminView: false }));
          thread.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'end' });
        } else if (d.message && thread) {
          // fallback render tối giản nếu SupportUI chưa sẵn sàng
          var div = document.createElement('div');
          div.className = 'text-end';
          var cBody = (hasUI && SupportUI.renderMessageContent) ? SupportUI.renderMessageContent(d.message.content) : (SupportUI && SupportUI.esc ? SupportUI.esc(d.message.content || '') : (d.message.content || ''));
          div.innerHTML = '<div class="d-inline-block p-2 rounded-3" style="background:#fff;border:1px solid #e2e8f0;">' + cBody + '</div>';
          thread.appendChild(div);
        }
        ta.value = ''; ta.style.height = 'auto';
        if (picker) picker.clear();
        notify(true, 'Đã gửi phản hồi');
      } else {
        notify(false, (d && d.msg) || 'Có lỗi xảy ra');
      }
    }).catch(function () { btn.disabled = false; notify(false, 'Lỗi kết nối.'); });
  }

  // Bắt cả submit form lẫn click nút gửi — luôn chặn native, luôn dùng AJAX.
  f.addEventListener('submit', function (e) { e.preventDefault(); send(); });
  if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); send(); });

  // ===== Đóng yêu cầu (vai trò user/khách) =====
  function doClose(btn) {
    if (!confirm('Đóng yêu cầu này? Sau khi đóng bạn sẽ không thể trả lời thêm.')) return;
    btn.disabled = true;
    var old = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang đóng...';
    var fd = new FormData();
    fd.append('action', 'close');
    fd.append('code', btn.dataset.code || '');
    fd.append('phone', btn.dataset.phone || '');
    fd.append('csrf_token', csrf);
    fetch(AJAX, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf }
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.ok) {
        notify(true, d.msg || 'Đã đóng yêu cầu.');
        setTimeout(function () { window.location.reload(); }, 700);
      } else {
        btn.disabled = false; btn.innerHTML = old;
        notify(false, (d && d.msg) || 'Không thể đóng yêu cầu.');
      }
    }).catch(function () {
      btn.disabled = false; btn.innerHTML = old;
      notify(false, 'Lỗi kết nối.');
    });
  }

  var closeBtn = document.getElementById('supCloseBtn');
  if (closeBtn) closeBtn.addEventListener('click', function () { doClose(this); });

  var closeBtnMobile = document.getElementById('supCloseBtnMobile');
  if (closeBtnMobile) closeBtnMobile.addEventListener('click', function () { doClose(this); });
})();
</script>
