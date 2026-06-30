<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/job_lib.php';
$ithanhloc->set_charset('utf8mb4');
// Ð?m b?o b?ng c?n thi?t t?n t?i
job_ensure_tables($ithanhloc);

// L?y thông tin nhân viên và công vi?c trong tu?n
$employeeId = (int)($_GET['employee_id'] ?? 0);
$week = job_week_start((string)($_GET['week'] ?? date('Y-m-d')));
$weekDates = job_week_dates_mon_to_sat($week);
// N?u không tìm th?y nhân viên, hi?n th? l?i
$employee = $employeeId > 0 ? job_db_get_employee($ithanhloc, $employeeId) : null;
if (!$employee) {
   #echo 'Không tìm th?y nhân viên.';
}

// L?y công vi?c c?a nhân viên trong tu?n dó, s?p x?p theo ngày
$tasks = job_db_list_tasks_for_employee_week($ithanhloc, $employeeId, $week);
$byDay = [];
foreach ($weekDates as $d) $byDay[$d] = [];
foreach ($tasks as $t) {
    $d = (string)($t['work_date'] ?? '');
    if (!isset($byDay[$d])) $byDay[$d] = [];
    $byDay[$d][] = $t;
}
$statusOpts = job_task_status_options();
$dept = (string)($employee['department'] ?? '');
$name = (string)($employee['name'] ?? '');
$pos = (string)($employee['position'] ?? '');

?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="mb-1">Báo cáo t?ng quan tu?n</h1>
            <div class="text-muted">Nhân viên: <?= h($name) ?><?= $pos !== '' ? ' • '.h($pos) : '' ?><?= $dept !== '' ? ' • '.h($dept) : '' ?></div>
            <div class="text-muted">Tu?n b?t d?u: <?= h($week) ?></div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-outline-primary" target="_blank" href="<?= h($baseUrl) ?>/core/job/export_pdf.php?employee_id=<?= (int)$employeeId ?>&week=<?= h($week) ?>"><i class="bi bi-file-earmark-pdf"></i> Xu?t PDF</a>
            <a class="btn btn-outline-secondary" href="<?= h($baseUrl) ?>/job-report?view=employee&employee_id=<?= (int)$employeeId ?>&week=<?= h($week) ?>">Quay l?i panel chính</a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="weekTabs" role="tablist">
        <?php foreach ($weekDates as $i => $d): $active = $i === 0 ? 'active' : ''; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active ?>" id="tab-<?= h($d) ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= h($d) ?>" type="button" role="tab">
                    <?= h(job_weekday_label_vi($d)) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content" id="weekTabContent">
        <?php foreach ($weekDates as $i => $d):
            $list = $byDay[$d] ?? [];
            $active = $i === 0 ? 'show active' : '';
        ?>
            <div class="tab-pane fade <?= $active ?>" id="pane-<?= h($d) ?>" role="tabpanel">
                <?php if (!$list): ?>
                    <div class="text-muted">Không có công vi?c.</div>
                <?php else: ?>
                    <div class="vstack gap-2">
                        <?php foreach ($list as $t):
                            $st = (string)($t['status'] ?? 'todo');
                            $media = isset($t['_media']) && is_array($t['_media']) ? $t['_media'] : [];
                        ?>
                            <div class="card task-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                        <div class="day-title"><?= h((string)($t['title'] ?? '')) ?></div>
                                        <span class="badge bg-secondary"><?= h($statusOpts[$st] ?? $st) ?></span>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        B?t d?u: <?= h((string)($t['start_at'] ?? '--')) ?> • K?t thúc: <?= h((string)($t['end_at'] ?? '--')) ?>
                                    </div>
                                    <?php if (!empty($t['description_html'])): ?>
                                        <div class="mb-2">
                                            <div class="small text-muted mb-1">Mô t?:</div>
                                            <div class="task-desc"><?php echo (string)$t['description_html']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($media): ?>
                                        <div class="mt-2">
                                            <div class="small text-muted mb-1">Ðính kèm:</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($media as $m):
                                                    $p = (string)($m['file_path'] ?? '');
                                                    $k = (string)($m['file_kind'] ?? 'other');
                                                    $orig = (string)($m['original_name'] ?? '');
                                                    if ($k === 'image'): ?>
                                                        <button type="button" class="btn p-0 border-0 bg-transparent" data-preview-kind="image" data-preview-src="<?= h($p) ?>" data-preview-title="<?= h($orig !== '' ? $orig : '?nh') ?>">
                                                            <img src="<?= h($p) ?>" alt="img" style="width:90px;height:90px;object-fit:cover;border-radius:10px;border:1px solid var(--bs-border-color);">
                                                        </button>
                                                    <?php elseif ($k === 'video'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-preview-kind="video" data-preview-src="<?= h($p) ?>" data-preview-title="<?= h($orig !== '' ? $orig : 'Video') ?>">
                                                            <i class="bi bi-play-circle"></i> Video
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-preview-kind="file" data-preview-src="<?= h($p) ?>" data-preview-title="<?= h($orig !== '' ? $orig : 'File') ?>">
                                                            <i class="bi bi-paperclip"></i> <?= h($orig !== '' ? $orig : 'File') ?>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>


<div class="modal fade" id="weekMediaPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="weekMediaPreviewTitle">Xem file dính kèm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="weekMediaPreviewBody"></div>
        </div>
    </div>
</div>

<script>
(function(){
    var body = document.getElementById('weekMediaPreviewBody');
    var titleEl = document.getElementById('weekMediaPreviewTitle');
    var modalEl = document.getElementById('weekMediaPreviewModal');

    function showModal(){
        if (!window.bootstrap || !window.bootstrap.Modal) return;
        var m = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        m.show();
    }

    document.querySelectorAll('[data-preview-kind]').forEach(function(btn){
        btn.addEventListener('click', function(ev){
            ev.preventDefault();
            var kind = btn.getAttribute('data-preview-kind') || 'file';
            var src = btn.getAttribute('data-preview-src') || '';
            var title = btn.getAttribute('data-preview-title') || 'Ðính kèm';
            if (!src) return;
            if (titleEl) titleEl.textContent = title;
            var html = '';
            if (kind === 'image') {
                html = '<img src="' + src.replace(/"/g,'&quot;') + '" class="img-fluid rounded" alt="preview">';
            } else if (kind === 'video') {
                html = '<video src="' + src.replace(/"/g,'&quot;') + '" controls class="w-100 rounded" style="max-height:480px;"></video>';
            } else {
                html = '<a href="' + src.replace(/"/g,'&quot;') + '" target="_blank" rel="noopener">M? file trong tab m?i</a>';
            }
            if (body) body.innerHTML = html;
            showModal();
        });
    });
})();
</script>

