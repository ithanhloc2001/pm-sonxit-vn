<?php
require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../core/support/support_common.php';
support_ensure_tables($ithanhloc);
$csrf = (string)($_SESSION['csrf_token'] ?? '');

$bUrl = (string)($baseUrl ?? '');
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
      <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(12,76,41,.08);color:#0c4c29;border:1px solid rgba(12,76,41,.15);">
        <i class="bi bi-patch-question fs-4"></i>
      </div>
      <div>
        <h1 class="h3 mb-1 fw-bold" style="font-size:1.45rem;color:#1e293b;">Câu hỏi thường gặp (FAQ)</h1>
        <p class="text-muted mb-0 small">Soạn câu hỏi tự phục vụ hiển thị tại trang /faq.</p>
      </div>
    </div>
    <button class="btn px-3" style="background:#0c4c29;color:#fff;" id="faqAddBtn"><i class="bi bi-plus-lg me-1"></i>Thêm câu hỏi</button>
  </div>

  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3" style="width:60px;">#</th><th>Câu hỏi</th><th style="width:120px;">Trạng thái</th><th style="width:120px;"></th></tr></thead>
        <tbody id="faqRows">
          <tr><td colspan="4" class="text-center text-muted py-4">Đang tải...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="faqModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0">
      <form id="faqForm" onsubmit="return false;">
        <div class="modal-header"><h5 class="modal-title">Câu hỏi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="action" value="faq_save">
          <input type="hidden" name="id" id="faqId" value="0">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Câu hỏi</label>
            <input type="text" name="question" id="faqQuestion" class="form-control" required maxlength="250">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Câu trả lời</label>
            <textarea name="answer" id="faqAnswer" class="form-control" rows="6" required></textarea>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Danh mục</label>
              <input type="text" name="category" id="faqCategory" class="form-control" value="general" maxlength="40">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Thứ tự</label>
              <input type="number" name="order_index" id="faqOrder" class="form-control" value="0">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="faqActive" value="1" checked>
                <label class="form-check-label" for="faqActive">Hiển thị</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn" style="background:#0c4c29;color:#fff;">Lưu</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?= h($basePath) ?>/core/support/support.js?v=<?= @filemtime(__DIR__ . '/../../core/support/support.js') ?: time() ?>"></script>
<script>
(function () {
  var AJAX = '<?= h($basePath) ?>/core_admin/support/ajax/ticket.php';
  var csrf = '<?= h($csrf) ?>';
  var modal = new bootstrap.Modal(document.getElementById('faqModal'));
  var tbody = document.getElementById('faqRows');
  var esc = SupportUI.esc;

  function rowHtml(f) {
    var badge = (String(f.is_active) !== '0')
      ? '<span class="badge text-bg-success">Hiển thị</span>'
      : '<span class="badge text-bg-secondary">Ẩn</span>';
    return '<tr data-id="' + f.id + '"' +
      ' data-question="' + esc(f.question) + '"' +
      ' data-answer="' + esc(f.answer) + '"' +
      ' data-category="' + esc(f.category) + '"' +
      ' data-order="' + esc(f.order_index) + '"' +
      ' data-active="' + esc(f.is_active) + '">' +
      '<td class="ps-3 text-muted">' + esc(f.order_index) + '</td>' +
      '<td>' + esc(f.question) + '</td>' +
      '<td>' + badge + '</td>' +
      '<td class="text-end pe-3">' +
        '<button class="btn btn-sm btn-outline-secondary faqEdit"><i class="bi bi-pencil"></i></button> ' +
        '<button class="btn btn-sm btn-outline-danger faqDel"><i class="bi bi-trash"></i></button>' +
      '</td></tr>';
  }

  function emptyRow() { return '<tr><td colspan="4" class="text-center text-muted py-4">Chưa có câu hỏi nào.</td></tr>'; }

  function load() {
    SupportUI.get(AJAX + '?action=faq_list').then(function (d) {
      if (!d || !d.ok) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Không tải được dữ liệu.</td></tr>'; return; }
      tbody.innerHTML = d.faqs.length ? d.faqs.map(rowHtml).join('') : emptyRow();
    });
  }

  function openForm(data) {
    document.getElementById('faqId').value = data.id || 0;
    document.getElementById('faqQuestion').value = data.question || '';
    document.getElementById('faqAnswer').value = data.answer || '';
    document.getElementById('faqCategory').value = data.category || 'general';
    document.getElementById('faqOrder').value = data.order || 0;
    document.getElementById('faqActive').checked = (String(data.active) !== '0');
    modal.show();
  }

  document.getElementById('faqAddBtn').addEventListener('click', function () { openForm({ active: 1 }); });

  // Event delegation: nút sửa/xóa trên các hàng render động
  tbody.addEventListener('click', function (e) {
    var editBtn = e.target.closest('.faqEdit');
    var delBtn = e.target.closest('.faqDel');
    if (editBtn) {
      var tr = editBtn.closest('tr');
      openForm({ id: tr.dataset.id, question: tr.dataset.question, answer: tr.dataset.answer, category: tr.dataset.category, order: tr.dataset.order, active: tr.dataset.active });
    } else if (delBtn) {
      var trd = delBtn.closest('tr');
      SupportUI.confirm({ message: 'Xóa câu hỏi này?', okText: 'Xóa', danger: true }).then(function (yes) {
        if (!yes) return;
        SupportUI.post(AJAX, { action: 'faq_delete', id: trd.dataset.id, csrf_token: csrf }, csrf).then(function (d) {
          if (d && d.ok) {
            trd.remove();
            if (!tbody.querySelector('tr[data-id]')) tbody.innerHTML = emptyRow();
            SupportUI.toast(true, 'Đã xóa');
          } else SupportUI.toast(false, (d && d.msg) || 'Xóa thất bại');
        });
      });
    }
  });

  document.getElementById('faqForm').addEventListener('submit', function (e) {
    e.preventDefault();
    SupportUI.post(AJAX, new FormData(this), csrf).then(function (d) {
      if (!d || !d.ok) { SupportUI.toast(false, (d && d.msg) || 'Lưu thất bại'); return; }
      var f = d.faq;
      var existing = tbody.querySelector('tr[data-id="' + f.id + '"]');
      if (d.is_new || !existing) {
        var empty = tbody.querySelector('tr:not([data-id])');
        if (empty) empty.remove();
        tbody.insertAdjacentHTML('beforeend', rowHtml(f));
      } else {
        existing.outerHTML = rowHtml(f);
      }
      modal.hide();
      SupportUI.toast(true, 'Đã lưu câu hỏi');
    });
  });

  load();
})();
</script>
