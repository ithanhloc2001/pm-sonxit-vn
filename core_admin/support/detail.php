<?php
require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../core/support/support_common.php';
support_ensure_tables($ithanhloc);

$cats = support_categories();
$pris = support_priorities();
$stats = support_statuses();
$csrf = (string)($_SESSION['csrf_token'] ?? '');
$bUrl = (string)($baseUrl ?? '');

$id   = (int)($_GET['id'] ?? 0);
$code = strtoupper(trim((string)($_GET['code'] ?? '')));

$ticket = null;
if ($id > 0) {
    $stmt = $ithanhloc->prepare('SELECT * FROM support_ticket WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('i', $id);
} elseif ($code !== '') {
    $stmt = $ithanhloc->prepare('SELECT * FROM support_ticket WHERE code = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('s', $code);
} else {
    $stmt = null;
}
if ($stmt) { $stmt->execute(); $ticket = $stmt->get_result()->fetch_assoc(); $stmt->close(); }

if (!$ticket) {
    echo '<div class="container py-5">'
        . '<div class="text-center mx-auto" style="max-width:460px;">'
        . '<div class="d-inline-flex align-items-center justify-content-center mb-4" '
        . 'style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,#fef2f2,#fee2e2);box-shadow:0 8px 24px -8px rgba(239,68,68,.35);">'
        . '<i class="bi bi-ticket-perforated" style="font-size:2.5rem;color:#ef4444;"></i>'
        . '</div>'
        . '<h3 class="h4 fw-bold mb-2" style="color:#0f172a;">Yêu cầu không còn tồn tại</h3>'
        . '<p class="text-muted mb-4" style="line-height:1.6;">Ticket này đã bị xoá hoặc chưa từng tồn tại. '
        . 'Các ticket đã xoá sẽ không thể xem hay phản hồi được nữa.</p>'
        . '<a href="/admin/support-tickets" class="btn btn-primary px-4 rounded-pill">'
        . '<i class="bi bi-arrow-left me-1"></i>Về danh sách ticket</a>'
        . '</div></div>';
    return;
}
$tid = (int)$ticket['id'];

// Người gửi
$requesterName = $requesterPhone = $requesterEmail = '';
if ((int)$ticket['user_id'] > 0) {
    $u = function_exists('ecommerce_user_load') ? ecommerce_user_load($ithanhloc, (int)$ticket['user_id']) : null;
    $requesterName  = (string)($u['full_name'] ?? ('#' . $ticket['user_id']));
    $requesterPhone = (string)($u['phone'] ?? '');
    $requesterEmail = (string)($u['email'] ?? '');
} else {
    $requesterName  = (string)($ticket['guest_name'] ?? 'Khách');
    $requesterPhone = (string)($ticket['guest_phone'] ?? '');
    $requesterEmail = (string)($ticket['guest_email'] ?? '');
}

$messages = [];
$res = $ithanhloc->query('SELECT * FROM support_ticket_message WHERE ticket_id = ' . $tid . ' AND is_active = 1 ORDER BY id ASC');
while ($res && ($m = $res->fetch_assoc())) $messages[] = $m;

// Người phụ trách (assignee) — để hiển thị nhãn thay vì nút trùng lặp
$assigneeId = (int)($ticket['assignee_id'] ?? 0);
$assigneeName = '';
if ($assigneeId > 0) {
    $au = function_exists('ecommerce_user_load') ? ecommerce_user_load($ithanhloc, $assigneeId) : null;
    $assigneeName = trim((string)($au['full_name'] ?? '')) ?: ('#' . $assigneeId);
}
$curAdminId = (int)($_SESSION['user_id'] ?? 0);
?>
<?php
$isClosed = (string)$ticket['status'] === 'closed';
$statusColors = ['open' => '#2563eb', 'pending' => '#b45309', 'resolved' => '#15803d', 'closed' => '#64748b'];
$sc = $statusColors[$ticket['status']] ?? '#64748b';
?>
<style>
.sup-chat-shell{display:flex;flex-direction:column;height:calc(100vh - 210px);min-height:460px;background:#fff;border:1px solid var(--bs-border-color,#e9eef5);border-radius:1rem;overflow:hidden;}
.sup-chat-head{padding:14px 18px;border-bottom:1px solid var(--bs-border-color-translucent,#eef2f7);}
.sup-chat-body{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;background:var(--bs-tertiary-bg,#f7faf9);}
.sup-chat-body::-webkit-scrollbar{width:8px;}
.sup-chat-body::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px;}
.sup-composer{border-top:1px solid var(--bs-border-color-translucent,#eef2f7);padding:12px 14px;background:#fff;}
.sup-composer textarea{border:1px solid var(--bs-border-color,#e2e8f0);border-radius:12px;resize:none;}
.sup-composer textarea:focus{border-color:var(--theme-primary,#0c4c29);box-shadow:0 0 0 .15rem rgba(12,76,41,.12);}
.sup-icon-btn{width:42px;height:42px;border-radius:12px;border:1px solid var(--bs-border-color,#e2e8f0);background:#fff;color:var(--bs-secondary-color,#475569);display:inline-flex;align-items:center;justify-content:center;font-size:1.05rem;cursor:pointer;transition:.15s;flex:0 0 auto;}
.sup-icon-btn:hover{background:var(--bs-success-bg-subtle,#f0fdf4);color:var(--theme-primary,#0c4c29);border-color:var(--theme-primary,#0c4c29);}
.sup-send-btn{height:42px;border:none;border-radius:12px;background:var(--theme-primary,#0c4c29);color:#fff;padding:0 22px;font-weight:600;cursor:pointer;flex:0 0 auto;}
.sup-send-btn:disabled{opacity:.6;cursor:default;}
.sup-preview{display:none;flex-wrap:wrap;gap:8px;margin-bottom:10px;}
.sup-action-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.sup-pill{border:1px solid var(--bs-border-color,#e2e8f0);background:#fff;border-radius:10px;padding:9px 10px;font-size:.84rem;font-weight:600;color:var(--bs-secondary-color,#475569);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;}
.sup-pill:hover{border-color:var(--theme-primary,#0c4c29);color:var(--theme-primary,#0c4c29);}
.sup-pill.active{background:var(--theme-primary,#0c4c29);color:#fff;border-color:var(--theme-primary,#0c4c29);}
.sup-pill[data-pri="high"].active{background:#dc2626;border-color:#dc2626;}
.sup-pill[data-pri="low"].active{background:#64748b;border-color:#64748b;}
@media(max-width:991.98px){.sup-chat-shell{height:auto;min-height:0;}.sup-chat-body{max-height:62vh;}}
</style>

<div class="container-fluid py-4">
  <input type="hidden" id="admTicketId" value="<?= $tid ?>">
  <input type="hidden" id="admCsrf" value="<?= h($csrf) ?>">

  <!-- Header chuẩn hệ thống -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background-color:rgba(12,76,41,.1);border:1px solid rgba(12,76,41,.15);">
        <i class="bi bi-life-preserver fs-4" style="color:var(--theme-primary,#0c4c29);"></i>
      </div>
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
          <h1 class="h3 mb-0 fw-bold" style="font-size:1.45rem;color:#1e293b;letter-spacing:-0.01em;">Xử lý yêu cầu</h1>
          <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" style="font-size:.72rem;"><?= h($ticket['code']) ?></span>
        </div>
        <p class="text-muted mb-0 small" style="font-size:.82rem;line-height:1.45;">Trao đổi với khách hàng và cập nhật trạng thái yêu cầu hỗ trợ.</p>
      </div>
    </div>
    <a href="<?= h($bUrl) ?>/admin/support-tickets" class="btn btn-outline-secondary d-flex align-items-center gap-2"><i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline">Danh sách</span></a>
  </div>

  <div class="row g-3">
    <!-- Khung chat -->
    <div class="col-lg-8">
      <div class="sup-chat-shell shadow-sm">
        <!-- Header -->
        <div class="sup-chat-head">
          <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom border-light">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;background:rgba(12,76,41,.1);color:var(--theme-primary,#0c4c29);font-weight:600;font-size:1.1rem;border:1px solid rgba(12,76,41,.15);">
              <i class="bi bi-person-fill"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                <span class="fw-bold text-dark" style="font-size:1rem;letter-spacing:-0.01em;"><?= h($requesterName) ?></span>
                <div class="d-flex align-items-center flex-wrap gap-1">
                  <span class="badge rounded-pill" style="background:rgba(12,76,41,.1);color:var(--theme-primary,#0c4c29);font-size:.7rem;"><?= h($ticket['code']) ?></span>
                  <span class="badge rounded-pill" id="admHeadStatus" style="background:<?= $sc ?>1a;color:<?= $sc ?>;font-size:.7rem;"><?= h($stats[$ticket['status']] ?? $ticket['status']) ?></span>
                  <span class="badge text-bg-danger rounded-pill px-2 <?= $ticket['priority'] === 'high' ? '' : 'd-none' ?>" id="admHeadPriHigh" style="font-size:.7rem;">Ưu tiên cao</span>
                </div>
              </div>
              <div class="text-muted small mt-1" style="font-size:.75rem;">Khách hàng gửi yêu cầu hỗ trợ</div>
            </div>
          </div>
          <h2 class="h6 fw-bold mb-1 text-dark" style="font-size:1.05rem;line-height:1.45;"><?= h($ticket['subject']) ?></h2>
          <div class="small text-muted">
            <?= h($cats[$ticket['category']] ?? $ticket['category']) ?>
            <?php if (!empty($ticket['order_id'])): ?>
              • Đơn: <a href="<?= h($bUrl) ?>/admin/order-change?order_id=<?= rawurlencode((string)$ticket['order_id']) ?>"><strong><?= h($ticket['order_id']) ?></strong></a>
            <?php endif; ?>
            • <?= h(date('H:i d/m/Y', strtotime((string)$ticket['created_at']))) ?>
          </div>
        </div>

        <!-- Luồng tin nhắn -->
        <div class="sup-chat-body" id="admThread" data-requester="<?= h($requesterName) ?>">
          <div class="text-center my-2 p-2 rounded-3" style="background:rgba(0,0,0,0.03);font-size:.78rem;color:#64748b;border:1px dashed rgba(0,0,0,0.06);">
            <i class="bi bi-calendar-check me-1"></i>Yêu cầu được tạo lúc <strong><?= h(date('H:i d/m/Y', strtotime((string)$ticket['created_at']))) ?></strong>
          </div>
          <?php foreach ($messages as $m):
            $isAdmin = ($m['sender_type'] === 'admin');
            $isSystem = ($m['sender_type'] === 'system');
            $media = support_media_urls($m['media_json'] ?? '', $bUrl);
          ?>
            <?php if ($isSystem): ?>
              <div class="text-center small text-muted"><i class="bi bi-info-circle me-1"></i><?= h($m['content']) ?></div>
            <?php else: ?>
              <div class="d-flex <?= $isAdmin ? 'flex-row-reverse' : '' ?> gap-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:<?= $isAdmin ? 'var(--theme-primary,#0c4c29)' : '#e2e8f0' ?>;color:<?= $isAdmin ? '#fff' : '#475569' ?>;">
                  <i class="bi <?= $isAdmin ? 'bi-headset' : 'bi-person' ?>"></i>
                </div>
                <div class="p-3 rounded-4 shadow-sm" style="max-width:80%;background:<?= $isAdmin ? 'var(--bs-success-bg-subtle,#f0fdf4)' : '#fff' ?>;border:1px solid var(--bs-border-color,#e2e8f0);">
                  <div class="fw-semibold small mb-1" style="color:<?= $isAdmin ? 'var(--theme-primary,#0c4c29)' : '#475569' ?>;"><?= $isAdmin ? 'Nhân viên hỗ trợ' : h($requesterName) ?></div>
                  <div style="white-space:pre-wrap;word-break:break-word;"><?= support_render_message_content((string)$m['content']) ?></div>
                  <?php if ($media): ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                      <?php foreach ($media as $url): ?><img src="<?= h($url) ?>" data-lightbox data-full="<?= h($url) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:zoom-in;"><?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="text-muted mt-1" style="font-size:.72rem;"><?= h(date('H:i d/m/Y', strtotime((string)$m['created_at']))) ?></div>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <!-- Composer -->
        <div class="sup-composer" id="admComposer" style="<?= $isClosed ? 'display:none;' : '' ?>">
          <form id="admReplyForm" onsubmit="return false;" enctype="multipart/form-data">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticket_id" value="<?= $tid ?>">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div class="sup-preview" id="admPreview"></div>
            <div class="d-flex align-items-end gap-2">
              <button type="button" class="sup-icon-btn" id="admAttachBtn" title="Đính kèm ảnh"><i class="bi bi-image"></i></button>
              <button type="button" class="sup-icon-btn" id="admSuggestProduct" title="Gợi ý sản phẩm"><i class="bi bi-box-seam"></i></button>
              <button type="button" class="sup-icon-btn" id="admSuggestVoucher" title="Gợi ý mã ưu đãi"><i class="bi bi-ticket-perforated"></i></button>
              <textarea name="content" class="form-control" rows="1" placeholder="Nhập phản hồi gửi khách hàng..." style="max-height:140px;"></textarea>
              <button type="submit" class="sup-send-btn" id="admSendBtn"><i class="bi bi-send me-1"></i>Gửi</button>
            </div>
          </form>
        </div>
        <?php if ($isClosed): ?>
          <div class="sup-composer text-center small text-muted">Yêu cầu đã đóng — không thể trả lời.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Thông tin & thao tác -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
          <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-circle me-1 text-muted"></i>Người gửi</h2>
          <div class="mb-1"><i class="bi bi-person me-2 text-muted"></i><?= h($requesterName) ?> <?= (int)$ticket['user_id'] === 0 ? '<span class="badge text-bg-light border">Khách</span>' : '' ?></div>
          <?php if ($requesterPhone): ?><div class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i><a href="tel:<?= h($requesterPhone) ?>" class="text-decoration-none"><?= h($requesterPhone) ?></a></div><?php endif; ?>
          <?php if ($requesterEmail): ?><div class="mb-1"><i class="bi bi-envelope me-2 text-muted"></i><a href="mailto:<?= h($requesterEmail) ?>" class="text-decoration-none"><?= h($requesterEmail) ?></a></div><?php endif; ?>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
          <h2 class="h6 fw-bold mb-3"><i class="bi bi-sliders me-1 text-muted"></i>Thao tác nhanh</h2>

          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted mb-2">Trạng thái</label>
            <div class="sup-action-grid" id="admStatusGroup">
              <?php foreach ($stats as $k => $v): ?>
                <button type="button" class="sup-pill <?= $k === $ticket['status'] ? 'active' : '' ?>" data-status="<?= h($k) ?>"><?= h($v) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted mb-2">Mức độ ưu tiên</label>
            <div class="d-flex gap-2" id="admPriorityGroup">
              <?php foreach ($pris as $k => $v): ?>
                <button type="button" class="sup-pill flex-fill <?= $k === $ticket['priority'] ? 'active' : '' ?>" data-pri="<?= h($k) ?>"><?= h($v) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div id="admAssignWrap">
            <?php if ($assigneeId > 0): ?>
              <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background:var(--bs-success-bg-subtle,#f0fdf4);border:1px solid var(--bs-success-border-subtle,#a3cfbb);">
                <i class="bi bi-person-check-fill" style="color:var(--theme-primary,#0c4c29);"></i>
                <span class="small">Phụ trách: <strong><?= h($assigneeName) ?><?= $assigneeId === $curAdminId ? ' (bạn)' : '' ?></strong></span>
              </div>
            <?php else: ?>
              <button id="admAssignBtn" type="button" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-person-check me-1"></i>Nhận xử lý
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal gợi ý sản phẩm / mã ưu đãi (nổi giữa màn hình) -->
<div class="sup-pick-modal" id="admProductPicker" style="display:none;">
  <div class="sup-pick-backdrop" data-pick="product"></div>
  <div class="sup-pick-box">
    <div class="sup-pick-head"><span><i class="bi bi-box-seam me-1"></i>Gợi ý sản phẩm</span><button type="button" class="sup-pick-close" data-pick="product" aria-label="Đóng">&times;</button></div>
    <div class="sup-pick-tools">
      <input type="text" id="admProductSearch" class="form-control form-control-sm" placeholder="Tìm sản phẩm...">
      <select id="admProductCat" class="form-select form-select-sm"><option value="0">Tất cả danh mục</option></select>
    </div>
    <div class="sup-pick-grid" id="admProductGrid"><div class="sup-pick-empty">Nhập từ khoá hoặc chọn danh mục…</div></div>
  </div>
</div>
<div class="sup-pick-modal" id="admVoucherPicker" style="display:none;">
  <div class="sup-pick-backdrop" data-pick="voucher"></div>
  <div class="sup-pick-box">
    <div class="sup-pick-head"><span><i class="bi bi-ticket-perforated me-1"></i>Gợi ý mã ưu đãi</span><button type="button" class="sup-pick-close" data-pick="voucher" aria-label="Đóng">&times;</button></div>
    <div class="sup-pick-list" id="admVoucherList"><div class="sup-pick-empty">Đang tải…</div></div>
  </div>
</div>

<style>
.sup-pick-modal{position:fixed;inset:0;z-index:10560;display:flex;align-items:center;justify-content:center;padding:20px;}
.sup-pick-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5);}
.sup-pick-box{position:relative;z-index:1;background:#fff;width:min(880px,96vw);max-height:86vh;border-radius:16px;overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,.35);display:flex;flex-direction:column;}
.sup-pick-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--theme-primary,#0c4c29);color:#fff;font-weight:700;}
.sup-pick-close{background:transparent;border:none;color:#fff;font-size:1.5rem;line-height:1;cursor:pointer;}
.sup-pick-tools{display:flex;gap:10px;padding:14px 18px;}
.sup-pick-tools .form-control,.sup-pick-tools .form-select{flex:1 1 auto;}
.sup-pick-tools .form-select{max-width:220px;flex:0 0 auto;}
.sup-pick-grid,.sup-pick-list{overflow-y:auto;padding:4px 18px 18px;}
.sup-pick-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;}
.sup-pick-empty{padding:28px;text-align:center;color:#94a3b8;font-size:.9rem;grid-column:1/-1;}
.sup-pp-item{border:1px solid #eef2f7;border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:4px;}
.sup-pp-thumb{width:100%;aspect-ratio:1/1;background:#f1f5f9;border-radius:8px;overflow:hidden;}
.sup-pp-thumb img{width:100%;height:100%;object-fit:cover;}
.sup-pp-name{font-size:.8rem;font-weight:600;color:#1e293b;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.sup-pp-price{font-size:.82rem;font-weight:700;color:#dc2626;}
.sup-pick-send{margin-top:auto;border:none;background:var(--theme-primary,#0c4c29);color:#fff;border-radius:7px;padding:6px;font-size:.78rem;font-weight:700;cursor:pointer;}
.sup-vp-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.sup-vp-item .sup-voux{flex:1 1 auto;min-width:0;}
.sup-voux{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #e5e7eb;border-left:4px solid #ee4d2d;border-radius:10px;padding:8px 10px;}
.sup-voux-ico{flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#ee4d2d;color:#fff;font-size:.85rem;}
.sup-voux-body{min-width:0;}
.sup-voux-label{font-size:.8rem;font-weight:800;color:#111827;line-height:1.25;}
.sup-voux-code{font-size:.72rem;color:#374151;}
.sup-voux-cond{font-size:.68rem;color:#6b7280;}
.sup-voux-exp{font-size:.66rem;color:#ef4444;}
.sup-voux-ship{border-left-color:#26aa99;}.sup-voux-ship .sup-voux-ico{background:#26aa99;}
.sup-voux-payment{border-left-color:#16a34a;}.sup-voux-payment .sup-voux-ico{background:#16a34a;}
.sup-voux-category{border-left-color:#ea580c;}.sup-voux-category .sup-voux-ico{background:#ea580c;}
.sup-voux-all{border-left-color:#7c3aed;}.sup-voux-all .sup-voux-ico{background:#7c3aed;}
.sup-vp-send{flex:0 0 auto;border:none;background:var(--theme-primary,#0c4c29);color:#fff;border-radius:7px;padding:6px 14px;font-size:.78rem;font-weight:700;cursor:pointer;}
@media(max-width:640px){.sup-pick-modal{padding:0;}.sup-pick-box{width:100vw;height:100dvh;max-height:100dvh;border-radius:0;}.sup-pick-grid{grid-template-columns:repeat(2,1fr);}}
</style>

<script src="<?= h($basePath) ?>/core/support/support.js?v=<?= @filemtime(__DIR__ . '/../../core/support/support.js') ?: time() ?>"></script>
<script>
(function () {
  var AJAX = '<?= h($basePath) ?>/core_admin/support/ajax/ticket.php';
  var tid = document.getElementById('admTicketId').value;
  var csrf = document.getElementById('admCsrf').value;
  var thread = document.getElementById('admThread');
  var requester = thread ? (thread.dataset.requester || '') : '';

  if (typeof SupportUI === 'undefined') {
    console.error('[support] support.js chưa được nạp — kiểm tra đường dẫn /core/support/support.js');
    return;
  }

  // Lightbox cho ảnh trong luồng + cuộn xuống cuối
  try { SupportUI.bindLightbox(thread); } catch (err) {}
  if (thread) thread.scrollTop = thread.scrollHeight;

  function action(data, okMsg) {
    data.ticket_id = tid; data.csrf_token = csrf;
    return SupportUI.post(AJAX, data, csrf).then(function (d) {
      SupportUI.toast(!!(d && d.ok), (d && d.ok) ? (okMsg || 'Đã cập nhật') : ((d && d.msg) || 'Có lỗi xảy ra'));
      return d;
    });
  }

  function setHeadStatus(status) {
    var badge = document.getElementById('admHeadStatus');
    if (!badge) return;
    var c = (SupportUI.STAT[status] || ['#64748b', status]);
    badge.style.background = c[0] + '1a'; badge.style.color = c[0];
    badge.textContent = c[1];
  }

  // --- Nhóm nút trạng thái ---
  var statusGroup = document.getElementById('admStatusGroup');
  statusGroup.addEventListener('click', function (e) {
    var b = e.target.closest('.sup-pill'); if (!b) return;
    var status = b.dataset.status;
    if (b.classList.contains('active')) return;
    action({ action: 'update_status', status: status }, 'Đã đổi trạng thái').then(function (d) {
      if (d && d.ok) {
        statusGroup.querySelectorAll('.sup-pill').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        setHeadStatus(status);
        // Đóng -> ẩn composer; mở lại -> hiện
        var composer = document.getElementById('admComposer');
        if (composer) composer.style.display = (status === 'closed') ? 'none' : '';
      }
    });
  });

  // --- Nhóm nút ưu tiên ---
  var priGroup = document.getElementById('admPriorityGroup');
  priGroup.addEventListener('click', function (e) {
    var b = e.target.closest('.sup-pill'); if (!b) return;
    var pri = b.dataset.pri;
    if (b.classList.contains('active')) return;
    action({ action: 'update_priority', priority: pri }, 'Đã đổi ưu tiên').then(function (d) {
      if (d && d.ok) {
        priGroup.querySelectorAll('.sup-pill').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        var hp = document.getElementById('admHeadPriHigh');
        if (hp) hp.classList.toggle('d-none', pri !== 'high');
      }
    });
  });

  // --- Nhận xử lý ---
  var assignBtn = document.getElementById('admAssignBtn');
  if (assignBtn) assignBtn.addEventListener('click', function () {
    assignBtn.disabled = true;
    action({ action: 'assign' }, 'Đã nhận xử lý').then(function (d) {
      if (d && d.ok) {
        var wrap = document.getElementById('admAssignWrap');
        var me = <?= json_encode(trim((string)($_SESSION['user_full_name'] ?? '')) ?: 'bạn') ?>;
        if (wrap) {
          wrap.innerHTML = '<div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background:var(--bs-success-bg-subtle,#f0fdf4);border:1px solid var(--bs-success-border-subtle,#a3cfbb);">' +
            '<i class="bi bi-person-check-fill" style="color:var(--theme-primary,#0c4c29);"></i>' +
            '<span class="small">Phụ trách: <strong>' + SupportUI.esc(me) + ' (bạn)</strong></span></div>';
        }
      } else {
        assignBtn.disabled = false;
      }
    });
  });

  // --- Composer: textarea tự giãn + đính kèm ảnh có preview ---
  var f = document.getElementById('admReplyForm');
  if (f) {
    var ta = f.querySelector('textarea');
    ta.addEventListener('input', function () {
      ta.style.height = 'auto';
      ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
    });
    // Ctrl/Cmd + Enter để gửi nhanh
    ta.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); f.requestSubmit(); }
    });

    var picker = SupportUI.attachmentPicker({
      maxFiles: 5,
      previewEl: document.getElementById('admPreview'),
      triggerBtn: document.getElementById('admAttachBtn'),
      mount: f
    });

    // Gửi phản hồi (dùng chung cho text/ảnh và gửi card). extra: {card_type, card_id|voucher_code}
    function sendReply(extra) {
      var btn = document.getElementById('admSendBtn');
      var content = ta.value.trim();
      var isCard = !!(extra && extra.card_type);
      if (!isCard && !content && !picker.files().length) { ta.focus(); return; }
      if (btn) btn.disabled = true;

      var fd = new FormData();
      fd.append('action', 'reply');
      fd.append('ticket_id', tid);
      fd.append('csrf_token', csrf);
      fd.append('content', isCard ? '' : content);
      if (!isCard) picker.files().forEach(function (file) { fd.append('attachments[]', file); });
      if (extra) Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });

      fetch(AJAX, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf } })
        .then(function (r) { return r.json(); }).then(function (d) {
        if (btn) btn.disabled = false;
        if (d && d.ok) {
          if (d.message && thread) {
            thread.insertAdjacentHTML('beforeend', SupportUI.renderMessage(d.message, { adminView: true, requesterName: requester }));
            thread.scrollTop = thread.scrollHeight;
          }
          if (d.status) {
            var pill = statusGroup.querySelector('.sup-pill[data-status="' + d.status + '"]');
            if (pill && !pill.classList.contains('active')) {
              statusGroup.querySelectorAll('.sup-pill').forEach(function (x) { x.classList.remove('active'); });
              pill.classList.add('active');
              setHeadStatus(d.status);
            }
          }
          if (!isCard) { ta.value = ''; ta.style.height = 'auto'; picker.clear(); }
          SupportUI.toast(true, isCard ? 'Đã gửi gợi ý' : 'Đã gửi phản hồi');
        } else {
          SupportUI.toast(false, (d && d.msg) || 'Có lỗi xảy ra');
        }
      }).catch(function () { if (btn) btn.disabled = false; SupportUI.toast(false, 'Lỗi kết nối.'); });
    }

    f.addEventListener('submit', function (e) { e.preventDefault(); sendReply(null); });

    // ===== Picker gợi ý SP / voucher =====
    var prodPicker = document.getElementById('admProductPicker');
    var vouPicker = document.getElementById('admVoucherPicker');
    var prodSearch = document.getElementById('admProductSearch');
    var prodCat = document.getElementById('admProductCat');
    var prodGrid = document.getElementById('admProductGrid');
    var vouList = document.getElementById('admVoucherList');
    var catsLoaded = false, searchTimer = null;

    function closePickers() { if (prodPicker) prodPicker.style.display = 'none'; if (vouPicker) vouPicker.style.display = 'none'; }
    function esc(s) { return SupportUI.esc(s); }

    function loadProducts() {
      var q = encodeURIComponent((prodSearch.value || '').trim());
      var cat = parseInt(prodCat.value || '0', 10) || 0;
      prodGrid.innerHTML = '<div class="sup-pick-empty">Đang tải…</div>';
      SupportUI.get(AJAX + '?action=suggest_products&q=' + q + '&cat_id=' + cat + '&limit=15').then(function (res) {
        if (!res || !res.ok || !res.data || !res.data.length) { prodGrid.innerHTML = '<div class="sup-pick-empty">Không có sản phẩm phù hợp.</div>'; return; }
        prodGrid.innerHTML = res.data.map(function (p) {
          var img = p.img ? esc(p.img) : '';
          return '<div class="sup-pp-item">' +
            '<div class="sup-pp-thumb">' + (img ? '<img src="' + img + '" alt="">' : '') + '</div>' +
            '<div class="sup-pp-name">' + esc(p.name) + '</div>' +
            '<div class="sup-pp-price">' + esc(p.price) + '</div>' +
            '<button type="button" class="sup-pick-send" data-pid="' + esc(p.id) + '">Gửi</button>' +
            '</div>';
        }).join('');
      }).catch(function () { prodGrid.innerHTML = '<div class="sup-pick-empty">Lỗi tải sản phẩm.</div>'; });
    }

    function openProductPicker() {
      closePickers();
      prodPicker.style.display = 'flex';
      if (!catsLoaded) {
        catsLoaded = true;
        SupportUI.get(AJAX + '?action=suggest_categories').then(function (res) {
          if (res && res.ok && res.data) res.data.forEach(function (c) { var o = document.createElement('option'); o.value = c.id; o.textContent = c.name; prodCat.appendChild(o); });
        }).catch(function () {});
      }
      loadProducts();
      prodSearch.focus();
    }

    function vouCardHtml(v) {
      var variant = 'sup-voux-' + (v.variant || 'order');
      return '<div class="sup-voux ' + variant + '">' +
        '<span class="sup-voux-ico"><i class="bi ' + esc(v.icon || 'bi-percent') + '"></i></span>' +
        '<div class="sup-voux-body">' +
        '<div class="sup-voux-label">' + esc(v.title || v.label) + '</div>' +
        '<div class="sup-voux-code">Mã: <b>' + esc(v.code) + '</b></div>' +
        '<div class="sup-voux-cond">' + esc(v.min || 'Áp dụng mọi đơn') + '</div>' +
        (v.exp ? '<div class="sup-voux-exp">' + esc(v.exp) + '</div>' : '') +
        '</div></div>';
    }

    function openVoucherPicker() {
      closePickers();
      vouPicker.style.display = 'flex';
      vouList.innerHTML = '<div class="sup-pick-empty">Đang tải…</div>';
      SupportUI.get(AJAX + '?action=suggest_vouchers').then(function (res) {
        if (!res || !res.ok || !res.data || !res.data.length) { vouList.innerHTML = '<div class="sup-pick-empty">Chưa có mã ưu đãi nào đang hoạt động.</div>'; return; }
        vouList.innerHTML = res.data.map(function (v) {
          return '<div class="sup-vp-item">' + vouCardHtml(v) + '<button type="button" class="sup-vp-send" data-code="' + esc(v.code) + '">Gửi</button></div>';
        }).join('');
      }).catch(function () { vouList.innerHTML = '<div class="sup-pick-empty">Lỗi tải mã ưu đãi.</div>'; });
    }

    var bP = document.getElementById('admSuggestProduct');
    var bV = document.getElementById('admSuggestVoucher');
    if (bP) bP.addEventListener('click', openProductPicker);
    if (bV) bV.addEventListener('click', openVoucherPicker);
    document.querySelectorAll('.sup-pick-close, .sup-pick-backdrop').forEach(function (b) { b.addEventListener('click', closePickers); });
    if (prodSearch) prodSearch.addEventListener('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(loadProducts, 350); });
    if (prodCat) prodCat.addEventListener('change', loadProducts);
    if (prodGrid) prodGrid.addEventListener('click', function (e) { var b = e.target.closest('.sup-pick-send'); if (!b) return; sendReply({ card_type: 'product', card_id: b.getAttribute('data-pid') }); closePickers(); });
    if (vouList) vouList.addEventListener('click', function (e) { var b = e.target.closest('.sup-vp-send'); if (!b) return; sendReply({ card_type: 'voucher', voucher_code: b.getAttribute('data-code') }); closePickers(); });
  }
})();
</script>
