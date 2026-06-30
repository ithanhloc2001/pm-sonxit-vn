<?php
require_once __DIR__ . '/../../core/support/support_common.php';
support_ensure_tables($ithanhloc);

$faqs = [];
$res = $ithanhloc->query("SELECT * FROM support_faq WHERE is_active = 1 ORDER BY order_index ASC, id ASC");
if ($res) { while ($r = $res->fetch_assoc()) $faqs[] = $r; }
?>
<div class="py-4">
  <div class="container" style="max-width: 820px;">
    <div class="text-center mb-4">
      <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:rgba(12,76,41,.1);">
        <i class="bi bi-patch-question fs-2" style="color:#0c4c29;"></i>
      </div>
      <h1 class="h3 fw-bold" style="color:#1e293b;">Câu hỏi thường gặp</h1>
      <p class="text-muted">Giải đáp nhanh các thắc mắc phổ biến về đơn hàng, thanh toán và dịch vụ.</p>
    </div>

    <?php if (empty($faqs)): ?>
      <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
        <p class="mb-0">Chưa có câu hỏi nào. Vui lòng <a href="/support">gửi yêu cầu hỗ trợ</a>.</p>
      </div>
    <?php else: ?>
      <div class="accordion" id="faqAccordion">
        <?php foreach ($faqs as $i => $f): ?>
          <div class="accordion-item border-0 shadow-sm rounded-4 mb-2 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int)$f['id'] ?>">
                <?= h($f['question']) ?>
              </button>
            </h2>
            <div id="faq<?= (int)$f['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted" style="white-space:pre-wrap;"><?= nl2br(h($f['answer'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4 mt-4 text-center" style="background:#f0fdf4;">
      <h2 class="h6 fw-bold mb-1">Vẫn cần hỗ trợ?</h2>
      <p class="text-muted small mb-3">Không tìm thấy câu trả lời? Gửi yêu cầu, chúng tôi sẽ phản hồi sớm nhất.</p>
      <div><a href="/support" class="btn px-4" style="background:#0c4c29;color:#fff;"><i class="bi bi-life-preserver me-1"></i>Gửi yêu cầu hỗ trợ</a></div>
    </div>
  </div>
</div>
