<?php
require_once __DIR__ . '/../../core/support/support_common.php';
support_ensure_tables($ithanhloc);

$curUserId = (int)($_SESSION['user_id'] ?? 0);
$isLogged  = $curUserId > 0;
$statuses  = support_statuses();
$cats      = support_categories();
$pris      = support_priorities();
$csrf      = (string)($_SESSION['csrf_token'] ?? '');
$preOrderId = trim((string)($_GET['order_id'] ?? ''));
// Tự mở modal tạo yêu cầu khi có ?create=1 hoặc khi truyền sẵn order_id.
$autoOpenCreate = ($preOrderId !== '') || (isset($_GET['create']) && (string)$_GET['create'] !== '0');

// Lấy ticket của user đăng nhập
$myTickets = [];
if ($isLogged) {
    $stmt = $ithanhloc->prepare('SELECT * FROM support_ticket WHERE user_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 100');
    $stmt->bind_param('i', $curUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $myTickets[] = $row;
    $stmt->close();
}

$statusBadge = function (string $s): string {
    $map = [
        'open' => ['#0c4c29', 'Đang mở'],
        'pending' => ['#b45309', 'Chờ phản hồi'],
        'resolved' => ['#15803d', 'Đã xử lý'],
        'closed' => ['#64748b', 'Đã đóng'],
    ];
    $c = $map[$s] ?? ['#64748b', $s];
    return '<span class="badge rounded-pill" style="background:' . $c[0] . '1a;color:' . $c[0] . ';">' . h($c[1]) . '</span>';
};
$priBadge = function (string $p): string {
    if ($p === 'high') return '<span class="badge rounded-pill text-bg-danger">Ưu tiên cao</span>';
    if ($p === 'low')  return '<span class="badge rounded-pill text-bg-secondary">Thấp</span>';
    return '';
};
?>
<div class="py-4">
  <div class="container" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
      <div class="d-flex align-items-center gap-3">
        <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(12,76,41,.1);">
          <i class="bi bi-life-preserver fs-4" style="color:#0c4c29;"></i>
        </div>
        <div>
          <h1 class="h3 mb-1 fw-bold" style="font-size:1.45rem;color:#1e293b;">Hỗ trợ của tôi</h1>
          <p class="text-muted mb-0 small">Theo dõi và phản hồi các yêu cầu hỗ trợ.</p>
        </div>
      </div>
      <button type="button" class="btn px-3" style="background:#0c4c29;color:#fff;" data-bs-toggle="modal" data-bs-target="#supportCreateModal"><i class="bi bi-plus-lg me-1"></i>Tạo yêu cầu</button>
    </div>

    <?php if (!$isLogged): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-search me-1"></i>Tra cứu yêu cầu (khách)</h2>
      <p class="text-muted small mb-3">Nhập mã yêu cầu (TK-XXXXXX) và số điện thoại bạn đã dùng khi gửi.</p>
      <form id="supLookupForm" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="lookup">
        <div class="col-sm-5">
          <label class="form-label small fw-semibold">Mã yêu cầu</label>
          <input type="text" name="code" class="form-control" placeholder="TK-XXXXXX" required>
        </div>
        <div class="col-sm-5">
          <label class="form-label small fw-semibold">Số điện thoại</label>
          <input type="tel" name="phone" class="form-control" required>
        </div>
        <div class="col-sm-2">
          <button class="btn w-100" style="background:#0c4c29;color:#fff;">Tra cứu</button>
        </div>
      </form>
    </div>
    <?php else: ?>
      <?php if (empty($myTickets)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
          <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
          <p class="mb-3">Bạn chưa có yêu cầu hỗ trợ nào.</p>
          <div><button type="button" class="btn" style="background:#0c4c29;color:#fff;" data-bs-toggle="modal" data-bs-target="#supportCreateModal">Tạo yêu cầu đầu tiên</button></div>
        </div>
      <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <!-- Desktop Table View -->
          <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Mã</th><th>Tiêu đề</th><th>Loại</th><th>Trạng thái</th><th>Cập nhật</th><th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($myTickets as $t): ?>
                <tr>
                  <td class="ps-3 fw-semibold"><?= h($t['code']) ?></td>
                  <td>
                    <?= h($t['subject']) ?>
                    <?= $priBadge((string)$t['priority']) ?>
                  </td>
                  <td class="small text-muted"><?= h($cats[$t['category']] ?? $t['category']) ?></td>
                  <td><?= $statusBadge((string)$t['status']) ?></td>
                  <td class="small text-muted"><?= h(date('H:i d/m/Y', strtotime((string)$t['updated_at']))) ?></td>
                  <td class="text-end pe-3">
                    <a href="/support-detail?code=<?= rawurlencode((string)$t['code']) ?>" class="btn btn-sm btn-outline-secondary">Xem</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Card List View -->
          <div class="d-block d-md-none">
            <div class="list-group list-group-flush">
              <?php foreach ($myTickets as $t): ?>
                <div class="list-group-item p-3 border-bottom">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark" style="font-size: .88rem;"><?= h($t['code']) ?></span>
                    <div>
                      <?= $statusBadge((string)$t['status']) ?>
                    </div>
                  </div>
                  <h3 class="h6 mb-2 fw-semibold text-dark" style="font-size: .95rem; line-height: 1.4;">
                    <?= h($t['subject']) ?>
                    <?= $priBadge((string)$t['priority']) ?>
                  </h3>
                  <div class="d-flex justify-content-between align-items-center small text-muted">
                    <div>
                      <i class="bi bi-tag me-1"></i><?= h($cats[$t['category']] ?? $t['category']) ?>
                    </div>
                    <div>
                      <i class="bi bi-clock me-1"></i><?= h(date('H:i d/m', strtotime((string)$t['updated_at']))) ?>
                    </div>
                  </div>
                  <div class="mt-3 text-end">
                    <a href="/support-detail?code=<?= rawurlencode((string)$t['code']) ?>" class="btn btn-sm w-100 btn-outline-secondary" style="border-radius: 8px;">
                      Xem chi tiết <i class="bi bi-chevron-right ms-1"></i>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL: TẠO YÊU CẦU HỖ TRỢ (gộp từ trang support-create) -->
<div class="modal fade" id="supportCreateModal" tabindex="-1" aria-labelledby="supportCreateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header border-0">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(12,76,41,.1);">
            <i class="bi bi-life-preserver fs-5" style="color:#0c4c29;"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0 fw-bold" id="supportCreateModalLabel" style="font-size:1.1rem;">Gửi yêu cầu hỗ trợ</h5>
            <small class="text-muted">Mô tả vấn đề — chúng tôi sẽ phản hồi sớm nhất.</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body pt-0">
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 small">
          <i class="bi bi-info-circle"></i>
          <span>Trước khi gửi, bạn có thể xem <a href="/faq" class="fw-semibold">Câu hỏi thường gặp</a> để được giải đáp ngay.</span>
        </div>

        <form id="supportCreateForm" onsubmit="return false;" enctype="multipart/form-data">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

          <?php if (!$isLogged): ?>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Họ tên <span class="text-danger">*</span></label>
              <input type="text" name="guest_name" class="form-control" required maxlength="100">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Số điện thoại <span class="text-danger">*</span></label>
              <input type="tel" name="guest_phone" class="form-control" required maxlength="15">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Email</label>
              <input type="email" name="guest_email" class="form-control" maxlength="150">
            </div>
          </div>
          <?php endif; ?>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Loại yêu cầu</label>
              <select name="category" class="form-select" id="supCategory">
                <?php foreach ($cats as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Mức độ ưu tiên</label>
              <select name="priority" class="form-select">
                <?php foreach ($pris as $k => $v): ?>
                  <option value="<?= h($k) ?>" <?= $k === 'normal' ? 'selected' : '' ?>><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3" id="orderPickWrap">
            <label class="form-label fw-semibold small">Đơn hàng liên quan (nếu có)</label>
            <?php if ($isLogged): ?>
              <select name="order_id" class="form-select" id="supOrderSelect">
                <option value="">— Không gắn đơn hàng —</option>
              </select>
            <?php else: ?>
              <input type="text" name="order_id" class="form-control" placeholder="Nhập mã đơn hàng (vd: DH123...)" value="<?= h($preOrderId) ?>">
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" name="subject" class="form-control" required minlength="5" maxlength="200" placeholder="Tóm tắt ngắn gọn vấn đề">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Nội dung chi tiết <span class="text-danger">*</span></label>
            <textarea name="content" class="form-control" rows="5" required maxlength="5000" placeholder="Mô tả chi tiết vấn đề bạn gặp phải..."></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold small d-block">Đính kèm ảnh (tối đa 5 ảnh, mỗi ảnh ≤ 12MB)</label>
            <input type="file" id="supAttachInput" accept="image/*" multiple class="d-none">
            <div class="d-flex align-items-center flex-wrap gap-2" id="supAttachPreview">
              <button type="button" id="supAttachBtn" class="d-flex flex-column align-items-center justify-content-center text-muted"
                style="width:78px;height:78px;border:1.5px dashed #cbd5e1;border-radius:12px;background:#f8fafc;cursor:pointer;gap:2px;">
                <i class="bi bi-image fs-4"></i>
                <span style="font-size:.68rem;">Thêm ảnh</span>
              </button>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Đóng</button>
        <button type="button" class="btn px-4" style="background:#0c4c29;color:#fff;" id="supSubmitBtn" form="supportCreateForm">
          <i class="bi bi-send me-1"></i>Gửi yêu cầu
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= h($baseUrl) ?>/core/support/support.js?v=<?= @filemtime(__DIR__ . '/../../core/support/support.js') ?: time() ?>"></script>
<script>
(function () {
  function notify(ok, msg) { if (window.SupportUI) SupportUI.toast(ok, msg); else if (!ok) alert(msg); }

  // ===== Tra cứu yêu cầu (khách) =====
  var f = document.getElementById('supLookupForm');
  if (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var p = new URLSearchParams(new FormData(f));
      fetch('/core_user/support/ajax/ticket.php?' + p.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok && d.redirect) window.location.href = d.redirect;
          else notify(false, (d && d.msg) || 'Không tìm thấy yêu cầu.');
        }).catch(function () { notify(false, 'Lỗi kết nối.'); });
    });
  }

  // ===== Tạo yêu cầu (modal) =====
  var form = document.getElementById('supportCreateForm');
  if (form) {
    // ----- Đính kèm ảnh: quản lý mảng File + preview + xóa từng ảnh -----
    var MAX_FILES = 5;
    var MAX_SIZE = 12 * 1024 * 1024;
    var attachInput = document.getElementById('supAttachInput');
    var attachBtn = document.getElementById('supAttachBtn');
    var attachPreview = document.getElementById('supAttachPreview');
    var attachFiles = []; // mảng File hiện tại

    function renderAttachPreview() {
      // Xoá các thẻ preview cũ (giữ lại nút "Thêm ảnh").
      attachPreview.querySelectorAll('.sup-attach-item').forEach(function (el) { el.remove(); });
      attachFiles.forEach(function (file, idx) {
        var item = document.createElement('div');
        item.className = 'sup-attach-item position-relative';
        item.style.cssText = 'width:78px;height:78px;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;flex:0 0 auto;';
        var img = document.createElement('img');
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
        img.src = URL.createObjectURL(file);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'position-absolute d-flex align-items-center justify-content-center';
        rm.style.cssText = 'top:2px;right:2px;width:20px;height:20px;border:none;border-radius:50%;background:rgba(15,23,42,.75);color:#fff;line-height:1;cursor:pointer;padding:0;';
        rm.innerHTML = '<i class="bi bi-x" style="font-size:14px;"></i>';
        rm.title = 'Xóa ảnh';
        rm.addEventListener('click', function () {
          attachFiles.splice(idx, 1);
          renderAttachPreview();
          updateAttachBtn();
        });
        item.appendChild(img);
        item.appendChild(rm);
        // Chèn trước nút "Thêm ảnh".
        attachPreview.insertBefore(item, attachBtn);
      });
    }

    function updateAttachBtn() {
      if (!attachBtn) return;
      attachBtn.style.display = attachFiles.length >= MAX_FILES ? 'none' : '';
    }

    if (attachBtn && attachInput) {
      attachBtn.addEventListener('click', function () { attachInput.click(); });
      attachInput.addEventListener('change', function () {
        var picked = Array.from(attachInput.files || []);
        var rejected = [];
        picked.forEach(function (f) {
          if (attachFiles.length >= MAX_FILES) { rejected.push(f.name + ' (vượt quá ' + MAX_FILES + ' ảnh)'); return; }
          if (!/^image\//i.test(f.type)) { rejected.push(f.name + ' (không phải ảnh)'); return; }
          if (f.size > MAX_SIZE) { rejected.push(f.name + ' (lớn hơn 12MB)'); return; }
          attachFiles.push(f); // append, không ghi đè
        });
        attachInput.value = ''; // reset để chọn lại cùng file vẫn kích hoạt change
        renderAttachPreview();
        updateAttachBtn();
        if (rejected.length) notify(false, 'Bỏ qua: ' + rejected.join(', '));
      });
    }

    // Nạp đơn hàng cho user đăng nhập (lười: chỉ nạp 1 lần khi mở modal)
    var sel = document.getElementById('supOrderSelect');
    var ordersLoaded = false;
    function loadOrders() {
      if (ordersLoaded || !sel) return;
      ordersLoaded = true;
      fetch('/core_user/support/ajax/ticket.php?action=my_orders', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok && Array.isArray(d.orders)) {
            d.orders.forEach(function (o) {
              var opt = document.createElement('option');
              opt.value = o.order_id;
              opt.textContent = o.order_id + ' • ' + o.created + ' • ' + o.status;
              sel.appendChild(opt);
            });
            var pre = <?= json_encode($preOrderId) ?>;
            if (pre) sel.value = pre;
          }
        }).catch(function () {});
    }
    var modalEl = document.getElementById('supportCreateModal');
    if (modalEl) modalEl.addEventListener('show.bs.modal', loadOrders);
    // Tự mở modal khi vào /support?create=1 hoặc /support?order_id=...
    <?php if ($autoOpenCreate): ?>
    if (modalEl && window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      // Dọn query param để reload/back không mở lại modal ngoài ý muốn.
      try {
        var u = new URL(window.location.href);
        u.searchParams.delete('create');
        u.searchParams.delete('order_id');
        window.history.replaceState({}, '', u.pathname + (u.search ? u.search : '') + u.hash);
      } catch (_) {}
    }
    <?php endif; ?>

    var btn = document.getElementById('supSubmitBtn');
    btn.addEventListener('click', function () {
      if (!form.reportValidity()) return;
      btn.disabled = true;
      var old = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang gửi...';
      // Build FormData thủ công: các field của form + mảng ảnh đã chọn (attachFiles).
      var fd = new FormData(form);
      attachFiles.forEach(function (f) { fd.append('attachments[]', f, f.name); });
      fetch('/core_user/support/ajax/ticket.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': form.querySelector('[name=csrf_token]').value }
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            notify(true, d.msg || 'Đã gửi yêu cầu hỗ trợ');
            setTimeout(function () { window.location.href = d.redirect || '/support'; }, 900);
          } else {
            btn.disabled = false; btn.innerHTML = old;
            notify(false, (d && d.msg) || 'Có lỗi xảy ra');
          }
        })
        .catch(function () {
          btn.disabled = false; btn.innerHTML = old;
          notify(false, 'Lỗi kết nối, vui lòng thử lại.');
        });
    });
  }
})();
</script>
