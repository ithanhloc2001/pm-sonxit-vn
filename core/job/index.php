<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config.php';
$ithanhloc->set_charset('utf8mb4');
require_once __DIR__ . '/job_lib.php';
job_ensure_tables($ithanhloc);
$tinyMceApiKey = $tinyMceApiKey ?? 'txuhjwh153p7ftwaz7qmpjld118svnfamxi9x5ofyyk77c76';
$apiUrl = $baseUrl . '/core/job/ajax.php';
?>
<div class="" id="jobReportApp">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Báo Cáo Công Việc</h4>
            <div class="text-muted small">Quản lý profile nhân viên và công việc theo tuần.</div>
        </div>
        <div class="d-flex align-items-center gap-2" id="jobTopActions">
            <button class="btn btn-outline-secondary" type="button" id="jobBtnBack" style="display:none;"><i class="bi bi-arrow-left"></i> Danh sách</button>
            <a class="btn btn-outline-primary" target="_blank" id="jobBtnExport" style="display:none;" href="#"><i class="bi bi-file-earmark-pdf"></i> Xuất PDF</a>
            <a class="btn btn-outline-secondary" target="_blank" id="jobBtnWeekPanel" style="display:none;" href="#"><i class="bi bi-layout-text-sidebar-reverse"></i> Panel tuần</a>
            <button class="btn btn-primary" type="button" id="jobBtnEditProfile" style="display:none;"><i class="bi bi-person-gear"></i> Sửa profile</button>
            <button class="btn btn-primary" type="button" id="jobBtnCreateEmployee"><i class="bi bi-person-plus"></i> Thêm nhân viên</button>
        </div>
    </div>

    <div id="jobAlerts"></div>

    <div id="jobViewList">
        <div class="text-muted">Đang tải…</div>
    </div>

    <div id="jobViewEmployeeEdit" style="display:none;"></div>

    <div id="jobViewEmployee" style="display:none;">
      <div class="row g-3">
        <div class="col-12 col-lg-12">
            <div class="card mb-3">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div id="jobEmpAvatar"></div>
                        <div>
                            <div class="fw-bold" id="jobEmpName">—</div>
                            <div class="text-muted small" id="jobEmpMeta">—</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted">Tuần (Thứ 2):</label>
                        <input class="form-control form-control-sm" type="date" id="jobWeekInput" style="max-width:170px;">
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="jobWeekBtn">Xem</button>
                    </div>
                </div>
            </div>
            <div class="card d-none">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Timeline công việc (tuần)</div>
                    <div id="jobTimeline" class="vstack gap-2" style="max-height:420px; overflow:auto;"></div>
                    <div id="jobTimelineEmpty" class="text-muted" style="display:none;">Chưa có công việc trong tuần.</div>
                 </div>
            </div>
            <!--/./-->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="jobDayTabs"></ul>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div class="fw-semibold" id="jobDayTitle">Công việc</div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="jobPreviewDayBtn">Xem nhanh trong ngày</button>
                            <button class="btn btn-sm btn-primary" type="button" id="jobOpenTaskModal">Thêm công việc</button>
                           
                        </div>
                    </div>

                    <div id="jobTasksList" class="vstack gap-2"></div>
                    <div id="jobTasksEmpty" class="text-muted" style="display:none;">Chưa có công việc.</div>
                </div>
            </div>
        </div>
        <!-- Biểu đồ thống kê đã bị xoá theo yêu cầu -->
         </div>
        <div class="row g-3">
            <div class="col-12 col-lg-7">
                
            </div>
        </div>
        <!-- Modal chỉnh sửa / thêm công việc -->
        <div class="modal fade" id="jobTaskModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="jobTaskModalTitle">Công việc</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form class="py-2" id="jobTaskForm">
                            <input type="hidden" name="task_id" value="0" id="jobTaskId">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label">Tên công việc</label>
                                    <input class="form-control" name="title" id="jobTaskTitle" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Trạng thái</label>
                                    <select class="form-select" name="status" id="jobTaskStatus"></select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Ngày (tab)</label>
                                    <input class="form-control" type="date" name="work_date" id="jobTaskWorkDate" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Bắt đầu (không bắt buộc)</label>
                                    <input class="form-control" type="datetime-local" name="start_at" id="jobTaskStart">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Kết thúc (không bắt buộc)</label>
                                    <input class="form-control" type="datetime-local" name="end_at" id="jobTaskEnd">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Mô tả chung</label>
                                    <textarea class="form-control" id="jobDescEditor" name="description_html" rows="8"></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Upload ảnh / video đính kèm</label>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" id="jobUploadTrigger"><i class="bi bi-cloud-upload"></i> Chọn tệp</button>
                                        <span class="text-muted small">Có thể chọn nhiều file.</span>
                                    </div>
                                    <input class="form-control d-none" type="file" name="attachments[]" id="jobTaskAttachments" multiple accept="image/*,video/*">
                                    <div id="jobAttachmentPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Đã đính kèm trước đó</div>
                                        <div id="jobExistingMedia" class="d-flex flex-wrap gap-2"></div>
                                    </div>
                                </div>

                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button class="btn btn-primary" type="submit" id="jobTaskSubmit">Lưu công việc</button>
                                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal xem nhanh công việc trong ngày -->
        <div class="modal fade" id="jobDayPreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tổng quan công việc trong ngày</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="jobDayPreviewBody">
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal xem nhanh ảnh / video đính kèm -->
        <div class="modal fade" id="jobMediaPreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="jobMediaPreviewTitle">Xem file đính kèm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="jobMediaPreviewBody"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= h($baseUrl) ?>/assets/js/chart.min.js"></script>
<script src="<?= $_TinyMceUrl ?>" referrerpolicy="origin"></script>
<?php $mceToolbarVer = @filemtime(__DIR__ . '/../../assets/js/mce-toolbar.js') ?: time(); ?>
<script src="<?= h($baseUrl) ?>/assets/js/mce-toolbar.js?v=<?= (int)$mceToolbarVer ?>"></script>

<script>
(function(){
    const API = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const BASE = <?= json_encode($baseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    // Route ảnh/file media (uploads/...) sang media domain nếu có cấu hình.
    function mUrl(u){
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(u);
        const s = String(u || '').trim();
        if (!s) return '';
        if (/^(https?:)?\/\//i.test(s) || /^data:/i.test(s)) return s;
        return BASE.replace(/\/+$/,'') + '/' + s.replace(/^\/+/, '');
    }

    const $alerts = document.getElementById('jobAlerts');
    const $viewList = document.getElementById('jobViewList');
    const $viewEmp = document.getElementById('jobViewEmployee');
    const $viewEmpEdit = document.getElementById('jobViewEmployeeEdit');

    const $btnBack = document.getElementById('jobBtnBack');
    const $btnExport = document.getElementById('jobBtnExport');
    const $btnWeekPanel = document.getElementById('jobBtnWeekPanel');
    const $btnEditProfile = document.getElementById('jobBtnEditProfile');
    const $btnCreate = document.getElementById('jobBtnCreateEmployee');

    const $empAvatar = document.getElementById('jobEmpAvatar');
    const $empName = document.getElementById('jobEmpName');
    const $empMeta = document.getElementById('jobEmpMeta');
    const $weekInput = document.getElementById('jobWeekInput');
    const $weekBtn = document.getElementById('jobWeekBtn');
    const $tabs = document.getElementById('jobDayTabs');
    const $dayTitle = document.getElementById('jobDayTitle');

    const $taskForm = document.getElementById('jobTaskForm');
    const $taskId = document.getElementById('jobTaskId');
    const $taskTitle = document.getElementById('jobTaskTitle');
    const $taskStatus = document.getElementById('jobTaskStatus');
    const $taskWorkDate = document.getElementById('jobTaskWorkDate');
    const $taskStart = document.getElementById('jobTaskStart');
    const $taskEnd = document.getElementById('jobTaskEnd');
    const $taskAttachments = document.getElementById('jobTaskAttachments');
    const $taskSubmit = document.getElementById('jobTaskSubmit');
    const $attachPreview = document.getElementById('jobAttachmentPreview');
    const $existingMedia = document.getElementById('jobExistingMedia');

    const $tasksList = document.getElementById('jobTasksList');
    const $tasksEmpty = document.getElementById('jobTasksEmpty');
    const $timeline = document.getElementById('jobTimeline');
    const $timelineEmpty = document.getElementById('jobTimelineEmpty');

    const $btnOpenTaskModal = document.getElementById('jobOpenTaskModal');
    const $btnPreviewDay = document.getElementById('jobPreviewDayBtn');
    const $btnUploadTrigger = document.getElementById('jobUploadTrigger');
    const $taskModalEl = document.getElementById('jobTaskModal');
    const $dayPreviewModalEl = document.getElementById('jobDayPreviewModal');
    const $dayPreviewBody = document.getElementById('jobDayPreviewBody');
    const $mediaPreviewModalEl = document.getElementById('jobMediaPreviewModal');
    const $mediaPreviewBody = document.getElementById('jobMediaPreviewBody');
    const $mediaPreviewTitle = document.getElementById('jobMediaPreviewTitle');

    let meta = null;
    let state = {
        view: 'list',
        employeeId: 0,
        weekMonday: '',
        activeDay: '',
    };
    let chart = null;
    let lastDayTasks = [];
    let lastStatusOptions = {};
    let attachmentFiles = [];
    let currentExistingMedia = [];
    let mcePendingContent = null;

    function escapeHtml(v){
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(type, msg){
        if (!$alerts) return;
        const cls = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
        $alerts.innerHTML = '<div class="alert ' + cls + '">' + escapeHtml(msg || '') + '</div>';
        setTimeout(() => { if ($alerts) $alerts.innerHTML = ''; }, 2500);
    }

    function qs(obj){
        const p = new URLSearchParams();
        Object.keys(obj || {}).forEach(k => {
            const v = obj[k];
            if (v === undefined || v === null || v === '') return;
            p.set(k, String(v));
        });
        return p.toString();
    }

    function showModal(el){
        if (!el || !(window.bootstrap && window.bootstrap.Modal)) return;
        const m = window.bootstrap.Modal.getOrCreateInstance(el);
        m.show();
    }

    function hideModal(el){
        if (!el || !(window.bootstrap && window.bootstrap.Modal)) return;
        const m = window.bootstrap.Modal.getOrCreateInstance(el);
        m.hide();
    }

    function openMediaPreview(kind, src, title){
        if (!$mediaPreviewBody || !$mediaPreviewModalEl) return;
        const url = String(src || '').trim();
        if (!url) return;
        const safeTitle = String(title || '').trim();
        if ($mediaPreviewTitle) {
            $mediaPreviewTitle.textContent = safeTitle !== '' ? safeTitle : 'Xem file đính kèm';
        }
        let html = '';
        if (kind === 'image') {
            html = '<img src="' + escapeHtml(url) + '" alt="preview" class="img-fluid rounded">';
        } else if (kind === 'video') {
            html = '<video src="' + escapeHtml(url) + '" controls class="w-100 rounded" style="max-height:480px;"></video>';
        } else {
            html = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">Mở file trong tab mới</a>';
        }
        $mediaPreviewBody.innerHTML = html;
        showModal($mediaPreviewModalEl);
    }

    function apiGet(action, params){
        const query = qs(Object.assign({action}, params || {}));
        return fetch(API + '?' + query, { credentials: 'same-origin' }).then(r => r.json());
    }

    function apiPost(action, formData){
        if (!(formData instanceof FormData)) formData = new FormData();
        formData.set('action', action);
        return fetch(API, { method: 'POST', body: formData, credentials: 'same-origin' }).then(r => r.json());
    }

    function setUrl(params){
        const p = new URLSearchParams(window.location.search);
        ['view','employee_id','week','day','id'].forEach(k => p.delete(k));
        Object.keys(params).forEach(k => {
            if (params[k] != null && params[k] !== '') p.set(k, String(params[k]));
        });
        const q = p.toString();
        const url = window.location.pathname + (q ? ('?' + q) : '');
        window.history.replaceState({}, '', url);
    }

    function setView(v){
        state.view = v;
        $viewList.style.display = (v === 'list') ? '' : 'none';
        $viewEmp.style.display = (v === 'employee') ? '' : 'none';
        $viewEmpEdit.style.display = (v === 'employee-edit') ? '' : 'none';

        $btnBack.style.display = (v === 'employee' || v === 'employee-edit') ? '' : 'none';
        $btnEditProfile.style.display = (v === 'employee') ? '' : 'none';
        $btnExport.style.display = (v === 'employee') ? '' : 'none';
        if ($btnWeekPanel) $btnWeekPanel.style.display = (v === 'employee') ? '' : 'none';
        $btnCreate.style.display = (v === 'list') ? '' : 'none';
    }

    function ensureMce(){
        if (!window.tinymce) return;

        // Nếu editor đã tồn tại thì dùng lại
        if (typeof window.tinymce.get === 'function') {
            const inst = window.tinymce.get('jobDescEditor');
            if (inst) return;
        }

        // Khởi tạo TinyMCE đơn giản, luôn cho phép nhập liệu
        window.tinymce.init({
            selector: '#jobDescEditor',
            height: 260,
            menubar: false,
            branding: false,
            statusbar: true,
            promotion: false,
            plugins: 'advlist autolink lists link image media table code fullscreen charmap searchreplace wordcount visualblocks',
            toolbar: [
                'undo redo | blocks fontfamily fontsize',
                'bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify',
                'bullist numlist outdent indent | link image media table | removeformat code fullscreen'
            ].join(' | '),
            font_family_formats: 'Montserrat=Montserrat,sans-serif; Arial=arial,helvetica,sans-serif; Tahoma=tahoma,arial,helvetica,sans-serif; Verdana=verdana,geneva,sans-serif; Times New Roman=times new roman,times,serif; Courier New=courier new,courier,monospace',
            font_family_default: 'Montserrat',
            content_css: ['<?= h($baseUrl) ?>/assets/css/montserrat.css'],
            content_style: 'body { font-family: Montserrat, sans-serif !important; }',
            language: 'vi',
            language_url: '<?= $_TinyMceLangUrl ?>',
            readonly: false,
            setup: function (editor) {
                editor.on('init', function () {
                    try {
                        const body = editor.getBody();
                        if (body) body.setAttribute('contenteditable', 'true');
                        if (mcePendingContent !== null) {
                            editor.setContent(String(mcePendingContent));
                        }
                    } catch (e) {}
                });
            }
        });
    }

    function setMceContent(html){
        mcePendingContent = String(html || '');
        if (window.tinymce && typeof window.tinymce.get === 'function') {
            const ed = window.tinymce.get('jobDescEditor');
            if (ed) { ed.setContent(mcePendingContent); return; }
        }
        const ta = document.getElementById('jobDescEditor');
        if (ta) ta.value = mcePendingContent;
    }

    function getMceContent(){
        if (window.tinymce && typeof window.tinymce.get === 'function') {
            const ed = window.tinymce.get('jobDescEditor');
            if (ed) return ed.getContent();
        }
        const ta = document.getElementById('jobDescEditor');
        return ta ? ta.value : '';
    }

    function pickBg(className){
        const el = document.createElement('div');
        el.className = className;
        el.style.position = 'absolute';
        el.style.left = '-9999px';
        el.style.top = '-9999px';
        document.body.appendChild(el);
        const c = getComputedStyle(el).backgroundColor;
        document.body.removeChild(el);
        return c;
    }

    // Biểu đồ thống kê đã bị xoá theo yêu cầu

    function badgeClass(status){
        switch(String(status||'')){
            case 'done': return 'bg-success';
            case 'doing': return 'bg-primary';
            case 'blocked': return 'bg-warning text-dark';
            case 'canceled': return 'bg-secondary';
            default: return 'bg-light text-dark';
        }
    }

    function toLocal(dt){
        const s = String(dt||'').trim();
        if (!s) return '';
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(s)) return s.slice(0,16).replace(' ','T');
        return '';
    }

    function resetTaskForm(day){
        $taskId.value = '0';
        $taskTitle.value = '';
        $taskStatus.value = 'todo';
        $taskWorkDate.value = day || '';
        $taskStart.value = '';
        $taskEnd.value = '';
        $taskAttachments.value = '';
        attachmentFiles = [];
        if ($attachPreview) $attachPreview.innerHTML = '';
        currentExistingMedia = [];
        if ($existingMedia) $existingMedia.innerHTML = '';
        setMceContent('');
        $taskSubmit.textContent = 'Thêm công việc';
    }

    function setEditingTask(task){
        const t = task || {};
        $taskId.value = String(t.id || 0);
        $taskTitle.value = String(t.title || '');
        $taskStatus.value = String(t.status || 'todo');
        $taskWorkDate.value = String(t.work_date || state.activeDay || '');
        $taskStart.value = toLocal(t.start_at);
        $taskEnd.value = toLocal(t.end_at);
        $taskAttachments.value = '';
        attachmentFiles = [];
        if ($attachPreview) $attachPreview.innerHTML = '';
        setMceContent(String(t.description_html || ''));
        $taskSubmit.textContent = 'Cập nhật công việc';
    }

    function renderExistingMedia(list){
        currentExistingMedia = Array.isArray(list) ? list : [];
        if (!$existingMedia) return;
        $existingMedia.innerHTML = '';
        if (!currentExistingMedia.length) {
            $existingMedia.innerHTML = '<div class="text-muted small">Không có file đính kèm.</div>';
            return;
        }
        currentExistingMedia.forEach(m => {
            const id = Number(m.id || 0);
            const p = mUrl(m.file_path || '');
            const k = String(m.file_kind || 'other');
            const orig = String(m.original_name || '');
            const wrap = document.createElement('div');
            wrap.className = 'position-relative';
            let body = '';
            if (k === 'image') {
                body = '<button type="button" class="btn p-0 border-0 bg-transparent" data-preview-kind="image" data-preview-src="' + escapeHtml(p) + '" data-preview-title="' + escapeHtml(orig || 'Ảnh') + '">' +
                    '<img src="' + escapeHtml(p) + '" alt="img" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--bs-border-color);">' +
                '</button>';
            } else if (k === 'video') {
                body = '<button type="button" class="btn btn-sm btn-outline-secondary" data-preview-kind="video" data-preview-src="' + escapeHtml(p) + '" data-preview-title="' + escapeHtml(orig || 'Video') + '"><i class="bi bi-play-circle"></i> Video</button>';
            } else {
                body = '<button type="button" class="btn btn-sm btn-outline-secondary" data-preview-kind="file" data-preview-src="' + escapeHtml(p) + '" data-preview-title="' + escapeHtml(orig || 'File') + '"><i class="bi bi-paperclip"></i> ' + escapeHtml(orig || 'File') + '</button>';
            }
            wrap.innerHTML = body + (id > 0 ? '<button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 translate-middle" data-media-id="' + id + '"><i class="bi bi-x"></i></button>' : '');

            const btnRemove = wrap.querySelector('button[data-media-id]');
            if (btnRemove) {
                btnRemove.addEventListener('click', () => {
                    const mediaId = Number(btnRemove.getAttribute('data-media-id') || 0);
                    if (!mediaId) return;
                    if (!confirm('Xoá file đính kèm này?')) return;
                    const fd = new FormData();
                    fd.set('media_id', String(mediaId));
                    apiPost('task_media_delete', fd).then(res => {
                        if (!res || !res.ok) {
                            showAlert('danger', res?.msg || 'Không xoá được file');
                            return;
                        }
                        showAlert('success', res.msg || 'Đã xoá file đính kèm');
                        currentExistingMedia = currentExistingMedia.filter(x => Number(x.id || 0) !== mediaId);
                        renderExistingMedia(currentExistingMedia);
                    }).catch(() => showAlert('danger', 'Không xoá được file'));
                });
            }

            const btnPreview = wrap.querySelector('[data-preview-kind]');
            if (btnPreview) {
                btnPreview.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    const kind = btnPreview.getAttribute('data-preview-kind') || 'file';
                    const src = btnPreview.getAttribute('data-preview-src') || '';
                    const title = btnPreview.getAttribute('data-preview-title') || (orig || 'Đính kèm');
                    openMediaPreview(kind, src, title);
                });
            }

            $existingMedia.appendChild(wrap);
        });
    }

    function renderEmployeeList(payload){
        const departments = payload.departments || {};
        const groups = payload.groups || {};
        const deptKeys = Object.keys(departments);

        const html = '<div class="row g-3">' + deptKeys.map(deptKey => {
            const deptLabel = departments[deptKey] || deptKey;
            const list = Array.isArray(groups[deptKey]) ? groups[deptKey] : [];
            const items = list.length ? '<div class="vstack gap-2">' + list.map(e => {
                const avatar = e.avatar_path
                    ? '<img src="' + escapeHtml(mUrl(e.avatar_path)) + '" alt="avatar" style="width:40px;height:40px;object-fit:cover;border-radius:10px;">'
                    : '<div class="bg-light border" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-person"></i></div>';
                const badge = Number(e.is_active || 0) === 1 ? 'bg-success' : 'bg-secondary';
                const badgeText = Number(e.is_active || 0) === 1 ? 'Active' : 'Off';
                const pos = e.position ? escapeHtml(e.position) : '—';
                const phone = e.phone ? (' • ' + escapeHtml(e.phone)) : '';
                return (
                    '<button type="button" class="btn p-0 text-start" style="background:transparent;border:none;" data-employee-id="' + Number(e.id) + '">' +
                        '<div class="border rounded p-2 d-flex align-items-center justify-content-between gap-2">' +
                            '<div class="d-flex align-items-center gap-2">' +
                                avatar +
                                '<div>' +
                                    '<div class="fw-semibold" style="color:inherit;">' + escapeHtml(e.name || '') + '</div>' +
                                    '<div class="small text-muted">' + pos + phone + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<span class="badge ' + badge + '">' + badgeText + '</span>' +
                        '</div>' +
                    '</button>'
                );
            }).join('') + '</div>' : '<div class="text-muted">Chưa có nhân viên.</div>';

            return (
                '<div class="col-12 col-lg-6">' +
                    '<div class="card"><div class="card-body">' +
                        '<div class="d-flex align-items-center justify-content-between mb-2">' +
                            '<div class="fw-semibold">' + escapeHtml(deptLabel) + '</div>' +
                            '<div class="text-muted small">' + list.length + ' nhân viên</div>' +
                        '</div>' + items +
                    '</div></div>' +
                '</div>'
            );
        }).join('') + '</div>' +
        '<div class="text-muted small mt-3">Chọn nhân viên để xem timeline và báo cáo theo tuần (Thứ 2 → Thứ 7).</div>';

        $viewList.innerHTML = html;
        $viewList.querySelectorAll('button[data-employee-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-employee-id') || 0);
                if (id > 0) openEmployee(id);
            });
        });
    }

    function renderEmployeeEditForm(emp){
        const e = emp || {};
        const departments = (meta && meta.departments) ? meta.departments : {};
        const genders = (meta && meta.genders) ? meta.genders : {};

        const deptOptions = Object.keys(departments)
            .map(k => '<option value="' + escapeHtml(k) + '" ' + (String(e.department || 'Khac') === k ? 'selected' : '') + '>' + escapeHtml(departments[k]) + '</option>')
            .join('');
        const genderOptions = Object.keys(genders)
            .map(k => '<option value="' + escapeHtml(k) + '" ' + (String(e.gender || 'other') === k ? 'selected' : '') + '>' + escapeHtml(genders[k]) + '</option>')
            .join('');
        const avatarPreview = e.avatar_path
            ? '<div class="mt-2"><img src="' + escapeHtml(mUrl(e.avatar_path)) + '" alt="avatar" style="width:64px;height:64px;object-fit:cover;border-radius:10px;"></div>'
            : '';
        const isActiveChecked = Number(e.is_active ?? 1) === 1 ? 'checked' : '';

        $viewEmpEdit.innerHTML = (
            '<div class="card"><div class="card-body">' +
                '<h5 class="card-title mb-3">' + (e.id ? 'Cập nhật nhân viên' : 'Tạo nhân viên') + '</h5>' +
                '<form class="row g-3" id="jobEmployeeForm">' +
                    '<div class=""><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="jobEmpActive" ' + isActiveChecked + '><label class="form-check-label" for="jobEmpActive">Đang hoạt động</label></div></div>' +
                    '<input type="hidden" name="id" value="' + escapeHtml(e.id || 0) + '">' +
                    '<div class="col-md-6"><label class="form-label">Tên nhân viên</label><input class="form-control" name="name" value="' + escapeHtml(e.name || '') + '" required></div>' +
                    '<div class="col-md-6"><label class="form-label">Chức vụ</label><input class="form-control" name="position" value="' + escapeHtml(e.position || '') + '" placeholder="Nhập thủ công"></div>' +
                    '<div class="col-md-4"><label class="form-label">Giới tính</label><select class="form-select" name="gender">' + genderOptions + '</select></div>' +
                    '<div class="col-md-4"><label class="form-label">Số điện thoại</label><input class="form-control" name="phone" value="' + escapeHtml(e.phone || '') + '"></div>' +
                    '<div class="col-md-4"><label class="form-label">Phòng ban</label><select class="form-select" name="department">' + deptOptions + '</select></div>' +
                    '<div class="col-md-6"><label class="form-label">Ảnh đại diện</label><input class="form-control" type="file" name="avatar" accept="image/*">' + avatarPreview + '</div>' +
                    '<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Lưu</button><button class="btn btn-outline-secondary" type="button" id="jobEmpCancel">Huỷ</button></div>' +
                '</form>' +
            '</div></div>'
        );

        const $form = document.getElementById('jobEmployeeForm');
        const $cancel = document.getElementById('jobEmpCancel');
        if ($cancel) {
            $cancel.addEventListener('click', () => {
                if (state.employeeId > 0) openEmployee(state.employeeId);
                else openList();
            });
        }
        if ($form) {
            $form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                const fd = new FormData($form);
                apiPost('employee_save', fd).then(res => {
                    if (!res || !res.ok) {
                        showAlert('danger', res?.msg || 'Không thể lưu nhân viên');
                        return;
                    }
                    showAlert('success', res.msg || 'Đã lưu');
                    const empSaved = res.data && res.data.employee ? res.data.employee : null;
                    if (empSaved && empSaved.id) {
                        state.employeeId = Number(empSaved.id);
                        openEmployee(state.employeeId);
                    } else {
                        openList();
                    }
                }).catch(() => showAlert('danger', 'Không thể lưu nhân viên'));
            });
        }
    }

    function setEmployeeHeader(employee){
        const e = employee || {};
        $empName.textContent = e.name || '—';
        const parts = [];
        if (e.department) {
            const deptLabel = (meta && meta.departments && meta.departments[e.department]) ? meta.departments[e.department] : e.department;
            parts.push(String(deptLabel));
        }
        if (e.position) parts.push(String(e.position));
        if (e.phone) parts.push(String(e.phone));
        $empMeta.textContent = parts.join(' • ') || '—';

        if (e.avatar_path) {
            $empAvatar.innerHTML = '<img src="' + escapeHtml(mUrl(e.avatar_path)) + '" alt="avatar" style="width:54px;height:54px;object-fit:cover;border-radius:12px;">';
        } else {
            $empAvatar.innerHTML = '<div class="bg-light border" style="width:54px;height:54px;border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-person" style="font-size:1.5rem;"></i></div>';
        }
    }

    function renderTabs(weekDates, weekLabels, activeDay){
        $tabs.innerHTML = (weekDates || []).map(d => {
            const isActive = String(d) === String(activeDay);
            const label = (weekLabels && weekLabels[d]) ? weekLabels[d] : d;
            return '<li class="nav-item"><a href="#" class="nav-link ' + (isActive ? 'active' : '') + '" data-day="' + escapeHtml(d) + '">' + escapeHtml(label) + '</a></li>';
        }).join('');
        $tabs.querySelectorAll('a[data-day]').forEach(a => {
            a.addEventListener('click', (ev) => {
                ev.preventDefault();
                const day = a.getAttribute('data-day') || '';
                if (!day) return;
                state.activeDay = day;
                loadEmployeeWeek(state.employeeId, state.weekMonday, state.activeDay);
            });
        });
    }

    function renderTasks(dayTasks, statusOptions){
        const list = Array.isArray(dayTasks) ? dayTasks : [];
        lastDayTasks = list;
        lastStatusOptions = statusOptions || {};
        $tasksList.innerHTML = '';
        if (!list.length) {
            $tasksEmpty.style.display = '';
            if ($btnPreviewDay) $btnPreviewDay.disabled = true;
            return;
        }
        $tasksEmpty.style.display = 'none';
        if ($btnPreviewDay) $btnPreviewDay.disabled = false;

        list.forEach(t => {
            const media = Array.isArray(t._media) ? t._media : [];
            const st = String(t.status || 'todo');

            const timeLine = (t.start_at || t.end_at)
                ? ('<div class="text-muted small">Thời gian: ' + escapeHtml(t.start_at || '--') + ' → ' + escapeHtml(t.end_at || '--') + '</div>')
                : '';
            const desc = t.description_html
                ? ('<div class="mt-2"><div class="small text-muted mb-1">Mô tả:</div><div class="border rounded p-2 bg-light" style="max-height:220px; overflow:auto;">' + String(t.description_html) + '</div></div>')
                : '';
            const mediaHtml = media.length
                ? ('<div class="mt-2"><div class="small text-muted mb-1">Đính kèm:</div><div class="d-flex flex-wrap gap-2">' + media.map(m => {
                    const p = mUrl(m.file_path || '');
                    const k = String(m.file_kind || 'other');
                    const orig = String(m.original_name || '');
                    if (k === 'image') {
                        return '<button type="button" class="btn p-0 border-0 bg-transparent" data-preview-kind="image" data-preview-src="' + escapeHtml(p) + '" data-preview-title="' + escapeHtml(orig || (t.title || 'Ảnh')) + '"><img src="' + escapeHtml(p) + '" alt="img" style="width:86px;height:86px;object-fit:cover;border-radius:10px;border:1px solid var(--bs-border-color);"></button>';
                    }
                    if (k === 'video') {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary" data-preview-kind="video" data-preview-src="' + escapeHtml(p) + '" data-preview-title="' + escapeHtml(orig || 'Video') + '"><i class="bi bi-play-circle"></i> Video</button>';
                    }
                    return '<button type="button" class="btn btn-sm btn-outline-secondary" data-preview-kind="file" data-preview-src="' + escapeHtml(p) + '" data-preview-title="' + escapeHtml(orig || 'File') + '"><i class="bi bi-paperclip"></i> ' + escapeHtml(orig || 'File') + '</button>';
                }).join('') + '</div></div>')
                : '';

            const wrap = document.createElement('div');
            wrap.className = 'border rounded p-3';
            wrap.innerHTML =
                '<div class="d-flex align-items-start justify-content-between gap-2">' +
                    '<div>' +
                        '<div class="fw-semibold">' + escapeHtml(t.title || '') +
                            '<span class="badge ms-2 ' + badgeClass(st) + '">' + escapeHtml((statusOptions && statusOptions[st]) ? statusOptions[st] : st) + '</span>' +
                        '</div>' +
                        timeLine +
                    '</div>' +
                    '<div class="d-flex gap-2">' +
                        '<button class="btn btn-sm btn-outline-primary" type="button" data-action="edit" data-id="' + Number(t.id) + '"><i class="bi bi-pencil"></i></button>' +
                    '</div>' +
                '</div>' + desc + mediaHtml;

            wrap.querySelector('button[data-action="edit"]').addEventListener('click', () => {
                apiGet('task_get', { employee_id: state.employeeId, task_id: Number(t.id) }).then(res => {
                    if (!res || !res.ok) { showAlert('danger', res?.msg || 'Không tải được công việc'); return; }
                    const taskData = res.data && res.data.task ? res.data.task : t;
                    const media = res.data && Array.isArray(res.data.media) ? res.data.media : (Array.isArray(taskData._media) ? taskData._media : []);
                    ensureMce();
                    setEditingTask(taskData);
                    renderExistingMedia(media);
                    showModal($taskModalEl);
                });
            });

            wrap.querySelectorAll('[data-preview-kind]').forEach(btn => {
                btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    const kind = btn.getAttribute('data-preview-kind') || 'file';
                    const src = btn.getAttribute('data-preview-src') || '';
                    const title = btn.getAttribute('data-preview-title') || (t.title || 'Đính kèm');
                    openMediaPreview(kind, src, title);
                });
            });

            $tasksList.appendChild(wrap);
        });
    }

    function renderAttachmentPreview(){
        if (!$attachPreview) return;
        $attachPreview.innerHTML = '';
        if (!attachmentFiles.length) return;
        attachmentFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'position-relative';
            let inner = '';
            if (file.type && file.type.indexOf('image/') === 0) {
                const url = URL.createObjectURL(file);
                inner = '<img src="' + url + '" alt="preview" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--bs-border-color);">';
            } else {
                inner = '<div class="border rounded d-flex align-items-center justify-content-center" style="width:70px;height:70px;"><i class="bi bi-paperclip"></i></div>';
            }
            item.innerHTML = inner + '<button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 translate-middle" data-index="' + index + '"><i class="bi bi-x"></i></button>';
            item.querySelector('button[data-index]').addEventListener('click', () => {
                const idx = Number(item.querySelector('button[data-index]').getAttribute('data-index') || -1);
                if (idx >= 0) {
                    attachmentFiles.splice(idx, 1);
                    const dt = new DataTransfer();
                    attachmentFiles.forEach(f => dt.items.add(f));
                    $taskAttachments.files = dt.files;
                    renderAttachmentPreview();
                }
            });
            $attachPreview.appendChild(item);
        });
    }

    function openDayPreview(){
        if (!lastDayTasks.length) {
            showAlert('warning', 'Không có công việc trong ngày này');
            return;
        }
        if (!$dayPreviewBody) return;
        const statusOptions = lastStatusOptions || {};
        const html = '<div class="vstack gap-3">' + lastDayTasks.map(t => {
            const st = String(t.status || 'todo');
            const badge = '<span class="badge ' + badgeClass(st) + '">' + escapeHtml(statusOptions[st] || st) + '</span>';
            const timeLine = (t.start_at || t.end_at)
                ? ('<div class="small text-muted">Thời gian: ' + escapeHtml(t.start_at || '--') + ' → ' + escapeHtml(t.end_at || '--') + '</div>')
                : '';
            return '<div class="border rounded p-2">' +
                '<div class="d-flex align-items-center justify-content-between gap-2 mb-1">' +
                    '<div class="fw-semibold">' + escapeHtml(t.title || '') + '</div>' +
                    badge +
                '</div>' +
                timeLine +
            '</div>';
        }).join('') + '</div>';
        $dayPreviewBody.innerHTML = html;
        showModal($dayPreviewModalEl);
    }

    function renderTimeline(tasksWeek, statusOptions, weekLabels){
        const list = Array.isArray(tasksWeek) ? tasksWeek : [];
        $timeline.innerHTML = '';
        if (!list.length) {
            $timelineEmpty.style.display = '';
            return;
        }
        $timelineEmpty.style.display = 'none';

        const statusMap = statusOptions || {};
        const days = weekLabels ? Object.keys(weekLabels) : [];
        const grouped = {};
        days.forEach(d => { grouped[d] = []; });
        list.forEach(t => {
            const d = String(t.work_date || '');
            if (!grouped[d]) grouped[d] = [];
            grouped[d].push(t);
        });

        days.forEach(d => {
            const dayTasks = grouped[d] || [];
            const label = weekLabels && weekLabels[d] ? weekLabels[d] : d;
            const dayWrap = document.createElement('div');
            dayWrap.className = 'mb-3';
            let inner = '<div class="d-flex align-items-center justify-content-between mb-1">' +
                '<div class="fw-semibold">' + escapeHtml(label) + '</div>' +
                '<div class="text-muted small">' + escapeHtml(d) + '</div>' +
            '</div>';

            if (!dayTasks.length) {
                inner += '<div class="text-muted small fst-italic">Không có công việc.</div>';
                dayWrap.innerHTML = inner;
                $timeline.appendChild(dayWrap);
                return;
            }

            inner += '<div class="vstack gap-1">' + dayTasks.map(t => {
                const st = String(t.status || 'todo');
                const timeKey = t.start_at || t.created_at || '';
                const badge = '<span class="badge ' + badgeClass(st) + '">' + escapeHtml(statusMap[st] || st) + '</span>';
                const timeHtml = timeKey ? '<div class="small text-muted">' + escapeHtml(timeKey) + '</div>' : '';
                return '<div class="border rounded px-2 py-1 d-flex align-items-center justify-content-between gap-2">' +
                    '<div class="me-2">' +
                        '<div class="small fw-semibold text-truncate" style="max-width:220px;">' + escapeHtml(t.title || '') + '</div>' +
                        timeHtml +
                    '</div>' +
                    badge +
                '</div>';
            }).join('') + '</div>';

            dayWrap.innerHTML = inner;
            $timeline.appendChild(dayWrap);
        });
    }

    function loadEmployeeWeek(employeeId, weekMonday, day){
        if (!employeeId) return;
        apiGet('tasks_week', { employee_id: employeeId, week: weekMonday, day: day }).then(res => {
            if (!res || !res.ok) { showAlert('danger', res?.msg || 'Không tải được dữ liệu'); return; }
            const data = res.data || {};

            state.employeeId = Number((data.employee && data.employee.id) || employeeId);
            state.weekMonday = String(data.week_monday || weekMonday || '');
            state.activeDay = String(data.active_day || day || '');

            setEmployeeHeader(data.employee);
            if ($weekInput) $weekInput.value = state.weekMonday;

            renderTabs(data.week_dates || [], data.week_labels || {}, state.activeDay);
            $dayTitle.textContent = 'Công việc ngày ' + ((data.week_labels && data.week_labels[state.activeDay]) ? data.week_labels[state.activeDay] : state.activeDay);

            const statusOptions = data.status_options || (meta && meta.statuses) || {};
            $taskStatus.innerHTML = Object.keys(statusOptions)
                .map(k => '<option value="' + escapeHtml(k) + '">' + escapeHtml(statusOptions[k]) + '</option>')
                .join('');

            resetTaskForm(state.activeDay);
            ensureMce();

            renderTasks((data.tasks_by_day || {})[state.activeDay] || [], statusOptions);
            renderTimeline(data.tasks_week || [], statusOptions, data.week_labels || {});

            // Biểu đồ thống kê đã bị xoá theo yêu cầu

            $btnExport.href = (BASE || '') + '/core/job/export_pdf.php?employee_id=' + encodeURIComponent(String(state.employeeId)) + '&week=' + encodeURIComponent(String(state.weekMonday));
            if ($btnWeekPanel) {
                $btnWeekPanel.href = (BASE || '') + '/job-report-preview?employee_id=' + encodeURIComponent(String(state.employeeId)) + '&week=' + encodeURIComponent(String(state.weekMonday));
            }
            setUrl({ view: 'employee', employee_id: state.employeeId, week: state.weekMonday, day: state.activeDay });
        }).catch(() => showAlert('danger', 'Không tải được dữ liệu'));
    }

    function openList(){
        setView('list');
        state.employeeId = 0;
        state.weekMonday = '';
        state.activeDay = '';
        setUrl({});

        apiGet('employees_list').then(res => {
            if (!res || !res.ok) { showAlert('danger', res?.msg || 'Không tải được danh sách'); return; }
            renderEmployeeList(res.data || {});
        }).catch(() => showAlert('danger', 'Không tải được danh sách'));
    }

    function openEmployee(employeeId){
        setView('employee');
        state.employeeId = Number(employeeId || 0);
        loadEmployeeWeek(state.employeeId, state.weekMonday || undefined, state.activeDay || undefined);
    }

    function openEmployeeEdit(employee){
        setView('employee-edit');
        renderEmployeeEditForm(employee || null);
        setUrl({ view: 'employee-edit', id: employee && employee.id ? employee.id : '' });
    }

    // events
    $btnBack.addEventListener('click', () => openList());
    $btnCreate.addEventListener('click', () => openEmployeeEdit(null));
    $btnEditProfile.addEventListener('click', () => {
        if (!state.employeeId) return;
        apiGet('employee_get', { employee_id: state.employeeId }).then(res => {
            if (!res || !res.ok) { showAlert('danger', res?.msg || 'Không tải được nhân viên'); return; }
            openEmployeeEdit(res.data && res.data.employee ? res.data.employee : null);
        }).catch(() => showAlert('danger', 'Không tải được nhân viên'));
    });
    $weekBtn.addEventListener('click', () => {
        const week = String($weekInput.value || '').trim();
        if (!week) return;
        state.weekMonday = week;
        loadEmployeeWeek(state.employeeId, state.weekMonday, state.activeDay);
    });

    if ($btnOpenTaskModal) {
        $btnOpenTaskModal.addEventListener('click', () => {
            if (!state.employeeId) {
                showAlert('warning', 'Vui lòng chọn nhân viên trước');
                return;
            }
            resetTaskForm(state.activeDay);
            showModal($taskModalEl);
        });
    }

    if ($btnPreviewDay) {
        $btnPreviewDay.addEventListener('click', () => {
            openDayPreview();
        });
    }

    if ($taskAttachments) {
        $taskAttachments.addEventListener('change', () => {
            attachmentFiles = $taskAttachments.files ? Array.from($taskAttachments.files) : [];
            renderAttachmentPreview();
        });
    }

    if ($btnUploadTrigger && $taskAttachments) {
        $btnUploadTrigger.addEventListener('click', () => {
            $taskAttachments.click();
        });
    }

    $taskForm.addEventListener('submit', (ev) => {
        ev.preventDefault();
        if (!state.employeeId) return;
        const fd = new FormData();
        fd.set('employee_id', String(state.employeeId));
        fd.set('task_id', String($taskId.value || '0'));
        fd.set('title', String($taskTitle.value || '').trim());
        fd.set('status', String($taskStatus.value || 'todo'));
        fd.set('work_date', String($taskWorkDate.value || state.activeDay || ''));
        fd.set('start_at', String($taskStart.value || ''));
        fd.set('end_at', String($taskEnd.value || ''));
        fd.set('description_html', getMceContent());

        (attachmentFiles || []).forEach(f => fd.append('attachments[]', f));

        apiPost('task_save', fd).then(res => {
            if (!res || !res.ok) { showAlert('danger', res?.msg || 'Không thể lưu công việc'); return; }
            showAlert('success', res.msg || 'Đã lưu');

            const w = res.data && res.data.week_monday ? res.data.week_monday : state.weekMonday;
            const d = res.data && res.data.active_day ? res.data.active_day : state.activeDay;
            state.weekMonday = w;
            state.activeDay = d;
            loadEmployeeWeek(state.employeeId, state.weekMonday, state.activeDay);
            hideModal($taskModalEl);
        }).catch(() => showAlert('danger', 'Không thể lưu công việc'));
    });

    // Khởi tạo / huỷ TinyMCE theo vòng đời modal để tránh bị khoá soạn thảo
    if ($taskModalEl && window.bootstrap && window.bootstrap.Modal) {
        $taskModalEl.addEventListener('shown.bs.modal', () => {
            ensureMce();
        });
        $taskModalEl.addEventListener('hidden.bs.modal', () => {
            if (window.tinymce && typeof window.tinymce.get === 'function') {
                const ed = window.tinymce.get('jobDescEditor');
                if (ed && typeof ed.remove === 'function') {
                    try { ed.remove(); } catch (e) {}
                }
            }
        });
    }

    // init
    apiGet('meta').then(res => {
        meta = res && res.ok ? (res.data || null) : null;
        const p = new URLSearchParams(window.location.search);
        const v = String(p.get('view') || '');
        const employeeId = Number(p.get('employee_id') || 0);
        const editId = Number(p.get('id') || 0);
        const week = String(p.get('week') || '').trim();
        const day = String(p.get('day') || '').trim();

        if (v === 'employee' && employeeId > 0) {
            setView('employee');
            state.employeeId = employeeId;
            state.weekMonday = week;
            state.activeDay = day;
            loadEmployeeWeek(employeeId, week || undefined, day || undefined);
            return;
        }
        if (v === 'employee-edit') {
            if (editId > 0) {
                apiGet('employee_get', { employee_id: editId }).then(r => {
                    if (r && r.ok) {
                        state.employeeId = editId;
                        openEmployeeEdit(r.data && r.data.employee ? r.data.employee : null);
                    } else {
                        openEmployeeEdit(null);
                    }
                }).catch(() => openEmployeeEdit(null));
            } else {
                openEmployeeEdit(null);
            }
            return;
        }
        openList();
    }).catch(() => openList());
})();
</script>
