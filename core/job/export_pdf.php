<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/job_lib.php';

// Cho phép xuất PDF báo cáo công việc đối với mọi đối tượng được quyền xem trang báo cáo


if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    echo 'No DB';
    exit;
}

job_ensure_tables($ithanhloc);

$employeeId = (int)($_GET['employee_id'] ?? 0);
$week = job_week_start((string)($_GET['week'] ?? date('Y-m-d')));
$weekDates = job_week_dates_mon_to_sat($week);

$employee = $employeeId > 0 ? job_db_get_employee($ithanhloc, $employeeId) : null;
if (!$employee) {
    echo 'Không tìm thấy nhân viên.';
    exit;
}

$tasks = job_db_list_tasks_for_employee_week($ithanhloc, $employeeId, $week);
$byDay = [];
foreach ($weekDates as $d) $byDay[$d] = [];
foreach ($tasks as $t) {
    $d = (string)($t['work_date'] ?? '');
    if (!isset($byDay[$d])) $byDay[$d] = [];
    $byDay[$d][] = $t;
}

// Tổng hợp toàn bộ ảnh đính kèm (trang phụ khi xuất PDF)
$allImages = [];
foreach ($tasks as $t) {
    $workDate = (string)($t['work_date'] ?? '');
    $title = (string)($t['title'] ?? '');
    $media = isset($t['_media']) && is_array($t['_media']) ? $t['_media'] : [];
    foreach ($media as $m) {
        if (!is_array($m)) continue;
        if ((string)($m['file_kind'] ?? '') !== 'image') continue;
        $p = trim((string)($m['file_path'] ?? ''));
        if ($p === '') continue;
        $allImages[] = [
            'path' => $p,
            'name' => (string)($m['original_name'] ?? ''),
            'date' => $workDate,
            'title' => $title,
        ];
    }
}

$statusOpts = job_task_status_options();
$statusCounts = array_fill_keys(array_keys($statusOpts), 0);
foreach ($tasks as $t) {
    $sk = (string)($t['status'] ?? 'todo');
    if (!isset($statusCounts[$sk])) $statusCounts[$sk] = 0;
    $statusCounts[$sk]++;
}

$dept = (string)($employee['department'] ?? '');
$name = (string)($employee['name'] ?? '');
$pos = (string)($employee['position'] ?? '');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Báo cáo công việc - <?= job_h($name) ?></title>
    <link href="<?= job_h(rtrim((string)$baseUrl, '/')) ?>/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{--rpt-radius:.5rem;--rpt-gap:.75rem;--rpt-img:170px;--rpt-img-summary:240px;}
        body{font-size:14px;background:#f8f9fa;}
        .rpt-wrap{max-width:900px;margin:0 auto;padding:1rem;}

        /* ── Print ── */
        @media print{
            .no-print{display:none !important;}
            body{background:#fff;font-size:12px;}
            .rpt-wrap{max-width:100%;padding:0;}
            :root{--rpt-img:120px;--rpt-img-summary:150px;--rpt-gap:.5rem;}
            .day-section{page-break-inside:avoid;}
            .task-card{page-break-inside:avoid;box-shadow:none !important;border:1px solid #dee2e6 !important;}
            .photo-summary{page-break-before:always;}
            .photo-item{page-break-inside:avoid;}
            /* PDF/Print: mỗi dòng 1 ảnh */
            .task-gallery{grid-template-columns:1fr !important;}
            .photo-gallery{grid-template-columns:1fr !important;}
            .task-gallery img,.photo-item img{aspect-ratio:16/9;}
        }

        /* ── Header ── */
        .rpt-header{background:#fff;border-radius:var(--rpt-radius);padding:1rem 1.25rem;margin-bottom:1rem;border:1px solid var(--bs-border-color);}
        .rpt-header h4{font-size:1.15rem;margin:0 0 .25rem;}

        /* ── Status pills ── */
        .status-row{display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:1rem;}
        .status-pill{font-size:12px;padding:.25rem .6rem;border-radius:2rem;background:#fff;border:1px solid var(--bs-border-color);}

        /* ── Day section ── */
        .day-section{margin-bottom:1.25rem;}
        .day-label{font-size:.95rem;font-weight:700;padding:.35rem .75rem;background:#fff;border:1px solid var(--bs-border-color);border-radius:var(--rpt-radius);margin-bottom:.5rem;}

        /* ── Attachment summary ── */
        .photo-summary{margin-top:1.25rem;}
        .photo-summary-head{display:flex;align-items:flex-end;justify-content:space-between;gap:.5rem;margin-bottom:.5rem;}
        .photo-summary-title{font-size:1rem;font-weight:800;margin:0;}
        .photo-summary-sub{font-size:12px;color:var(--bs-secondary-color);}
        .photo-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(var(--rpt-img-summary),1fr));gap:.5rem;}
        .photo-item{background:#fff;border:1px solid var(--bs-border-color);border-radius:.45rem;overflow:hidden;}
        .photo-item img{width:100%;aspect-ratio:4/3;object-fit:cover;cursor:pointer;}
        .photo-cap{padding:.35rem .5rem;font-size:11.5px;line-height:1.35;color:var(--bs-secondary-color);}
        .photo-cap b{color:var(--bs-body-color);}

        /* ── Task card ── */
        .task-card{background:#fff;border:1px solid var(--bs-border-color);border-radius:var(--rpt-radius);padding:.75rem 1rem;margin-bottom:var(--rpt-gap);box-shadow:0 1px 3px rgba(0,0,0,.04);}
        .task-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;margin-bottom:.35rem;}
        .task-top-title{font-weight:700;font-size:13.5px;line-height:1.35;min-width:0;word-break:break-word;}
        .task-badge{font-size:11px;white-space:nowrap;padding:.2rem .55rem;border-radius:2rem;flex-shrink:0;}
        .task-time{font-size:12px;color:var(--bs-secondary-color);margin-bottom:.5rem;}

        /* ── Description ── */
        .task-desc{word-break:break-word;font-size:13px;line-height:1.55;}
        .task-desc p{margin:0 0 .4rem;}
        .task-desc ul,.task-desc ol{margin:0 0 .4rem 1.15rem;padding:0;}
        .task-desc li{margin:.1rem 0;}
        .task-desc table{width:100%;border-collapse:collapse;font-size:12.5px;}
        .task-desc table th,.task-desc table td{border:1px solid var(--bs-border-color);padding:.2rem .4rem;vertical-align:top;}

        /* ── Media grid ── */
        .task-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(var(--rpt-img),1fr));gap:.4rem;margin-top:.5rem;}
        .task-gallery img{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:.4rem;border:1px solid var(--bs-border-color);cursor:pointer;transition:opacity .15s;}
        .task-gallery img:hover{opacity:.85;}

        /* ── Mobile ── */
        @media(max-width:575.98px){
            :root{--rpt-img:130px;--rpt-img-summary:170px;}
            .rpt-wrap{padding:.5rem;}
            .rpt-header{padding:.75rem;}
            .rpt-header h4{font-size:1rem;}
            .task-card{padding:.6rem .75rem;}
            .task-top-title{font-size:13px;}
            .task-gallery{grid-template-columns:repeat(auto-fill,minmax(110px,1fr));}
        }

        /* ── Lightbox (screen only) ── */
        .lb-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;padding:1rem;}
        .lb-overlay.active{display:flex;}
        .lb-overlay img{max-width:95vw;max-height:90vh;border-radius:.5rem;box-shadow:0 4px 24px rgba(0,0,0,.5);}
        .lb-close{position:absolute;top:12px;right:16px;font-size:2rem;color:#fff;cursor:pointer;line-height:1;background:none;border:none;z-index:10;}
        @media print{.lb-overlay{display:none !important;}}

        /* html2pdf export doesn't use print media; force export rules via class */
        .pdf-exporting .no-print{display:none !important;}
        .pdf-exporting .task-gallery{grid-template-columns:1fr !important;}
        .pdf-exporting .photo-gallery{grid-template-columns:1fr !important;}
        .pdf-exporting .task-gallery img,.pdf-exporting .photo-item img{aspect-ratio:16/9;}
    </style>
</head>
<body>
    <div class="rpt-wrap">
        <!-- Toolbar (no-print) -->
        <div class="no-print d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">Bấm <strong>In</strong> → Lưu dưới dạng PDF</span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success" id="btnDownloadPdf">📥 Tải PDF</button>
                <button class="btn btn-sm btn-primary" onclick="window.print()">🖨️ In</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= job_h(rtrim((string)$baseUrl, '/')) ?>/job-report?view=employee&employee_id=<?= (int)$employeeId ?>&week=<?= job_h($week) ?>">← Quay lại</a>
            </div>
        </div>

        <!-- Header -->
        <div class="rpt-header">
            <h4>📋 Báo cáo công việc tuần</h4>
            <div class="text-muted small">Tuần: <?= job_h($week) ?> • In lúc: <?= job_h(date('d/m/Y H:i')) ?></div>
            <div class="mt-2 small">
                <strong><?= job_h($name) ?></strong>
                <?php if ($dept !== ''): ?><span class="text-muted"> • <?= job_h($dept) ?></span><?php endif; ?>
                <?php if ($pos !== ''): ?><span class="text-muted"> • <?= job_h($pos) ?></span><?php endif; ?>
            </div>
        </div>

        <!-- Status summary -->
        <div class="status-row">
            <?php foreach ($statusOpts as $k => $lab): ?>
                <span class="status-pill"><?= job_h($lab) ?>: <strong><?= (int)($statusCounts[$k] ?? 0) ?></strong></span>
            <?php endforeach; ?>
        </div>

        <!-- Days -->
        <?php foreach ($weekDates as $d): ?>
            <?php $list = $byDay[$d] ?? []; ?>
            <div class="day-section">
                <div class="day-label"><?= job_h(job_weekday_label_vi($d)) ?></div>
                <?php if (!$list): ?>
                    <div class="text-muted small ps-1">Không có công việc.</div>
                <?php else: ?>
                    <?php foreach ($list as $t): ?>
                        <?php
                            $st = (string)($t['status'] ?? 'todo');
                            $rawDesc = (string)($t['description_html'] ?? '');
                            $descHtml = job_sanitize_mce_html_for_print($rawDesc, (string)$baseUrl);
                            $media = isset($t['_media']) && is_array($t['_media']) ? $t['_media'] : [];
                            $imgMedia = [];
                            foreach ($media as $m) {
                                if (!is_array($m)) continue;
                                if ((string)($m['file_kind'] ?? '') === 'image') {
                                    $p = trim((string)($m['file_path'] ?? ''));
                                    if ($p !== '') $imgMedia[] = ['path' => $p, 'name' => (string)($m['original_name'] ?? '')];
                                }
                            }
                            $badgeClass = match($st) {
                                'done' => 'text-bg-success',
                                'doing' => 'text-bg-primary',
                                'blocked' => 'text-bg-danger',
                                'canceled' => 'text-bg-secondary',
                                default => 'text-bg-warning',
                            };
                        ?>
                        <div class="task-card">
                            <div class="task-top">
                                <div class="task-top-title"><?= job_h((string)($t['title'] ?? '')) ?></div>
                                <span class="badge <?= $badgeClass ?> task-badge"><?= job_h($statusOpts[$st] ?? $st) ?></span>
                            </div>
                            <div class="task-time">
                                🕐 <?= job_h((string)($t['start_at'] ?? '--')) ?> → <?= job_h((string)($t['end_at'] ?? '--')) ?>
                            </div>

                            <?php if ($descHtml !== ''): ?>
                                <div class="task-desc"><?php echo $descHtml; ?></div>
                            <?php endif; ?>

                            <?php if (!empty($imgMedia)): ?>
                                <div class="task-gallery">
                                    <?php foreach ($imgMedia as $im): ?>
                                        <img src="<?= job_h((string)$im['path']) ?>" alt="<?= job_h((string)$im['name']) ?>" loading="lazy" onclick="openLb(this)">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($allImages)): ?>
            <div class="photo-summary">
                <div class="day-label">Tổng hợp ảnh đính kèm</div>
                <div class="photo-summary-head">
                    <h5 class="photo-summary-title">Tất cả ảnh trong tuần</h5>
                    <div class="photo-summary-sub"><?= (int)count($allImages) ?> ảnh</div>
                </div>
                <div class="photo-gallery">
                    <?php foreach ($allImages as $im): ?>
                        <div class="photo-item">
                            <img src="<?= job_h((string)$im['path']) ?>" alt="<?= job_h((string)$im['name']) ?>" loading="lazy" onclick="openLb(this)">
                            <div class="photo-cap">
                                <?php if (!empty($im['date'])): ?><span><?= job_h((string)$im['date']) ?></span><?php endif; ?>
                                <?php if (!empty($im['title'])): ?> • <b><?= job_h((string)$im['title']) ?></b><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox overlay (screen only) -->
    <div class="lb-overlay" id="lbOverlay" onclick="closeLb(event)">
        <button class="lb-close" aria-label="Đóng">&times;</button>
        <img id="lbImg" src="" alt="preview">
    </div>

    <script src="<?= job_h(rtrim((string)$baseUrl, '/')) ?>/assets/js/html2pdf.bundle.min.js"></script>
    <script>
        (function(){
            var btn = document.getElementById('btnDownloadPdf');
            if (!btn) return;

            btn.addEventListener('click', async function(){
                if (typeof window.html2pdf !== 'function') {
                    alert('Thiếu thư viện tạo PDF (html2pdf). Vui lòng F5 trang hoặc dùng nút In.');
                    return;
                }

                var el = document.querySelector('.rpt-wrap');
                if (!el) {
                    alert('Không tìm thấy nội dung báo cáo để xuất PDF.');
                    return;
                }

                var oldText = btn.textContent;
                btn.disabled = true;
                btn.textContent = '⏳ Đang tạo...';

                // Hide toolbar + force 1 image per row during export
                document.body.classList.add('pdf-exporting');

                // Ensure images are not skipped due to lazy-loading
                var imgs = el.querySelectorAll('img[loading="lazy"]');
                imgs.forEach(function(img){ img.setAttribute('loading','eager'); });

                // Reduce memory footprint to avoid failures on mobile/low-RAM
                var opt = {
                    margin: [8, 6, 10, 6],
                    filename: 'bao-cao-cong-viec-<?= preg_replace('/[^a-zA-Z0-9_-]/','',job_safe_slug($name)) ?>-<?= $week ?>.pdf',
                    image: { type: 'jpeg', quality: 0.88 },
                    html2canvas: { scale: 1.5, useCORS: true, logging: false },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'] }
                };

                try {
                    await window.html2pdf().set(opt).from(el).save();
                } catch (e) {
                    console.error(e);
                    alert('Không tạo được PDF (có thể do ảnh quá lớn hoặc trình duyệt hạn chế). Vui lòng dùng nút In → Lưu PDF.');
                } finally {
                    document.body.classList.remove('pdf-exporting');
                    btn.disabled = false;
                    btn.textContent = oldText;
                }
            });
        })();
        function openLb(el){
            var o=document.getElementById('lbOverlay'),i=document.getElementById('lbImg');
            if(!o||!i)return;
            i.src=el.src; i.alt=el.alt||'';
            o.classList.add('active');
        }
        function closeLb(e){
            var o=document.getElementById('lbOverlay');
            if(e.target===o||e.target.classList.contains('lb-close')){
                o.classList.remove('active');
                document.getElementById('lbImg').src='';
            }
        }
        if(new URLSearchParams(location.search).get('print')==='1'){
            setTimeout(function(){window.print();},300);
        }
    </script>
</body>
</html>
