<?php
require_once __DIR__ . '/../_admin_guard.php';
?>
<style>
.blog-badge{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.2rem .55rem;font-size:.75rem;font-weight:700;border:1px solid transparent;line-height:1.2;}
.blog-badge.on{background:var(--bs-success-bg-subtle);color:var(--bs-success-text-emphasis);border-color:var(--bs-success-border-subtle);}
.blog-badge.off{background:var(--bs-danger-bg-subtle);color:var(--bs-danger-text-emphasis);border-color:var(--bs-danger-border-subtle);}

.blog-thumb-preview{width:120px;height:80px;border-radius:10px;border:1px dashed var(--bs-border-color);background:var(--bs-body-bg);display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:.78rem;color:var(--bs-secondary-color);}
.blog-thumb-preview img{width:100%;height:100%;object-fit:cover;display:block;}

.blog-editor-shell{border:1px solid var(--bs-border-color);border-radius:14px;background:#fff;overflow:hidden;}
.blog-editor-shell textarea{min-height:300px;}

/* KPI grid riêng cho blog: 2 cột mobile -> 4 cột desktop */
#blogSummaryGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
@media(min-width:768px){#blogSummaryGrid{grid-template-columns:repeat(4,1fr);}}

/* Stepper trong modal bài viết */
.blog-stepper{font-size:.82rem;}
.blog-step{display:inline-flex;align-items:center;gap:.4rem;color:var(--bs-secondary-color);font-weight:600;}
.blog-step .blog-step-num{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#e5e7eb;color:#6b7280;font-size:.78rem;font-weight:700;flex-shrink:0;}
.blog-step.is-active{color:var(--theme-primary,#0c4c29);}
.blog-step.is-active .blog-step-num{background:var(--theme-primary,#0c4c29);color:#fff;}
.blog-step.is-done .blog-step-num{background:#16a34a;color:#fff;}
.blog-step-line{flex:1;height:2px;background:#e5e7eb;border-radius:2px;min-width:20px;}
@media(max-width:575.98px){.blog-step-label{display:none;}}
</style>

<div class="container-fluid py-4">
    <!-- MODERN PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08); color: var(--theme-primary, #0c4c29); border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-journal-richtext fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b; letter-spacing: -0.01em;">Quản lý bài viết</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="blogPostMeta" style="font-size: 0.72rem;">Đang tải dữ liệu...</span>
                </div>
                <p class="text-muted mb-0 small" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Tạo chuyên mục, soạn bài viết chuẩn SEO và quản lý trạng thái xuất bản.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3 py-2 shadow-sm" id="blogCatManage" data-bs-toggle="modal" data-bs-target="#blogCatModal" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-folder2-open"></i><span class="d-none d-sm-inline">Chuyên mục</span>
            </button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2 px-3 py-2 shadow-sm" id="blogImportWp" data-bs-toggle="modal" data-bs-target="#blogImportModal" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-wordpress"></i><span class="d-none d-sm-inline">Import từ WordPress</span><span class="d-inline d-sm-none">Import</span>
            </button>
            <button type="button" class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm text-white" id="blogPostAdd" data-bs-toggle="modal" data-bs-target="#blogPostModal" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-plus-lg fs-5 text-white"></i>
                <span class="d-none d-sm-inline">Thêm bài viết</span>
                <span class="d-inline d-sm-none">Thêm</span>
            </button>
        </div>
    </div>

    <!-- SUMMARY KPI CARDS -->
    <div class="mb-4" id="blogSummaryGrid">
        <div class="summary-card active" data-blog-tab="all">
            <div class="d-flex flex-column">
                <span>Tổng bài viết</span>
                <strong class="mt-1" id="kpiBlogTotal">0</strong>
            </div>
            <div class="summary-icon" style="background: rgba(12,76,41,.08); color: var(--theme-primary, #0c4c29);">
                <i class="bi bi-collection-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-blog-tab="published">
            <div class="d-flex flex-column">
                <span>Đang đăng</span>
                <strong class="mt-1" id="kpiBlogPublished">0</strong>
            </div>
            <div class="summary-icon" style="background: #ecfdf5; color: #16a34a;">
                <i class="bi bi-check-circle-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-blog-tab="draft">
            <div class="d-flex flex-column">
                <span>Nháp / Ẩn</span>
                <strong class="mt-1" id="kpiBlogDraft">0</strong>
            </div>
            <div class="summary-icon" style="background: #fef2f2; color: #dc2626;">
                <i class="bi bi-eye-slash-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-blog-cats="1">
            <div class="d-flex flex-column">
                <span>Chuyên mục</span>
                <strong class="mt-1" id="kpiBlogCats">0</strong>
            </div>
            <div class="summary-icon" style="background: #eff6ff; color: #2563eb;">
                <i class="bi bi-folder2-open fs-5"></i>
            </div>
        </div>
    </div>

    <!-- Filters & List Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body pb-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-6 col-12">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control border-0" id="blogPostSearch" placeholder="Tìm theo tiêu đề hoặc slug...">
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <select class="form-select form-select-sm border shadow-sm rounded-3" id="blogPostFilterCat"></select>
                </div>
            </div>
            <div class="mt-3 overflow-auto" id="blogCatTabs" style="white-space:nowrap;"></div>
        </div>

        <div class="fb-table-responsive border-top" id="blogPostTableWrap">
            <table class="table fb-table table-hover align-middle mb-0 text-nowrap" id="blogPostTable">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Tiêu đề</th>
                        <th style="width:180px;">Chuyên mục</th>
                        <th style="width:180px;">Tác giả</th>
                        <th style="width:120px;">Trạng thái</th>
                        <th style="width:170px;" class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6" class="text-center text-muted py-3">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="blogPostsEmpty" class="text-center py-5 text-muted" style="display:none;">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
            Không có bài viết phù hợp bộ lọc.
        </div>
    </div>
</div>

<div class="modal fade" id="blogPostModal" tabindex="-1" aria-labelledby="blogPostModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch gap-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="modal-title" id="blogPostModalLabel">Chỉnh sửa bài viết</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- Stepper -->
                <div class="blog-stepper d-flex align-items-center gap-2" id="blogStepper">
                    <div class="blog-step is-active" data-step-indicator="1">
                        <span class="blog-step-num">1</span>
                        <span class="blog-step-label">Ảnh bìa &amp; thông tin</span>
                    </div>
                    <div class="blog-step-line"></div>
                    <div class="blog-step" data-step-indicator="2">
                        <span class="blog-step-num">2</span>
                        <span class="blog-step-label">Nội dung chi tiết</span>
                    </div>
                </div>
            </div>
            <div class="modal-body bg-light">
                <input type="hidden" id="blogPostId" value="0">
                <input type="hidden" id="blogPostThumbCurrent" value="">

                <!-- STEP 1: Ảnh bìa + thông tin cơ bản -->
                <div class="bg-white border rounded-3 p-3" id="blogStep1">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-2 fw-semibold">Ảnh bìa bài viết</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="blog-thumb-preview w-100" id="blogPostThumbPreview" style="height:160px;">Chưa có ảnh</div>
                                <button type="button" class="btn btn-sm btn-primary w-100" id="btnPickBlogThumb">
                                    <i class="bi bi-image"></i> Chọn từ thư viện
                                </button>
                                <small class="text-muted">Gợi ý: 800x450px, hiển thị trên danh sách.</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1 fw-semibold">Tiêu đề</label>
                                    <input type="text" class="form-control form-control-sm" id="blogPostTitle" placeholder="Tiêu đề chuẩn SEO">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1 fw-semibold">Slug</label>
                                    <input type="text" class="form-control form-control-sm" id="blogPostSlug" placeholder="vd: huong-dan-chon-son-ngoai-that">
                                </div>
                                <div class="col-6 col-md-6">
                                    <label class="form-label mb-1 fw-semibold">Chuyên mục</label>
                                    <select class="form-select form-select-sm" id="blogPostCategory"></select>
                                </div>
                                <div class="col-6 col-md-6">
                                    <label class="form-label mb-1 fw-semibold">Tác giả</label>
                                    <input type="text" class="form-control form-control-sm" id="blogPostAuthor" placeholder="VD: Đội ngũ Paintmore">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1 fw-semibold">Tag / Từ khóa</label>
                                    <input type="text" class="form-control form-control-sm" id="blogPostTags" placeholder="vd: sơn ngoại thất, chống thấm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1 fw-semibold">Mô tả ngắn (excerpt)</label>
                                    <textarea class="form-control form-control-sm" id="blogPostExcerpt" rows="2" placeholder="Tóm tắt ngắn gọn, hiển thị trên listing và thẻ meta."></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0 fw-semibold">Tối ưu SEO</label>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btnSeoAuto" style="font-size:.75rem;">
                                                <i class="bi bi-magic"></i> Tự tạo SEO
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnKeywordAuto" style="font-size:.75rem;">
                                                <i class="bi bi-tags"></i> Tạo keyword
                                            </button>
                                        </div>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mb-1" id="blogMetaTitle" placeholder="Nếu bỏ trống sẽ dùng tiêu đề bài viết làm thẻ title">
                                    <input type="text" class="form-control form-control-sm mb-1" id="blogMetaDesc" placeholder="Mô tả (khoảng 150-160 ký tự)">
                                    <input type="text" class="form-control form-control-sm" id="blogMetaKeywords" placeholder="Từ khóa (phân tách bằng dấu phẩy)">
                                    <small class="text-muted" style="font-size:.72rem;">Gợi ý dựa trên tiêu đề, mô tả & nội dung đã nhập. Ưu tiên AI, tự động chuyển sang gợi ý cơ bản nếu chưa cấu hình AI.</small>
                                </div>
                                <div class="col-12">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="blogPostActive" checked>
                                        <label class="form-check-label" for="blogPostActive">Xuất bản (hiển thị)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Nội dung chi tiết (MCE) -->
                <div class="bg-white border rounded-3 p-3" id="blogStep2" style="display:none;">
                    <label class="form-label mb-1 fw-semibold">Nội dung bài viết</label>
                    <div class="blog-editor-shell mt-1">
                        <textarea id="blogPostContent" class="form-control" rows="14" placeholder="Soạn nội dung bài viết..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="blogStepBack" style="display:none;">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="blogStepNext">
                        Tiếp theo <i class="bi bi-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="blogPostSave" style="display:none;"><i class="bi bi-save"></i> Lưu bài viết</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: IMPORT BÀI VIẾT TỪ WORDPRESS -->
<div class="modal fade" id="blogImportModal" tabindex="-1" aria-labelledby="blogImportModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="blogImportModalLabel">
                    <i class="bi bi-wordpress text-primary"></i> Import bài viết từ WordPress
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">URL bài viết WordPress</label>
                    <input type="url" class="form-control" id="wpImportUrl" placeholder="https://paintandmore.vn/ten-bai-viet/" autocomplete="off">
                    <div class="form-text">Dán link bài viết. Hệ thống sẽ tự lấy tiêu đề, nội dung, ảnh đại diện, chuyên mục và thẻ.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Chuyên mục lưu vào</label>
                    <select class="form-select" id="wpImportCategory">
                        <option value="0">Tự động theo WordPress (tạo mới nếu chưa có)</option>
                    </select>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="wpImportPublish" checked>
                    <label class="form-check-label" for="wpImportPublish">Đăng hiển thị ngay sau khi import</label>
                </div>
                <div id="wpImportResult" class="small mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="wpImportSubmit">
                    <i class="bi bi-download"></i> Import &amp; đăng bài
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="blogCatModal" tabindex="-1" aria-labelledby="blogCatModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="blogCatModalLabel">Quản lý chuyên mục</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="bg-white border rounded-3 p-3 mb-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-striped table-sm" id="blogCatTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th>Tên chuyên mục</th>
                                <th>Slug</th>
                                <th style="width:130px;">Trạng thái</th>
                                <th style="width:150px;" class="text-end">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-3">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
                <br>
                    <div class="fw-semibold text-secondary mb-2">Chuyên mục</div>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-12">
                            <label class="form-label mb-1 fw-semibold">Tên chuyên mục</label>
                            <input type="hidden" id="blogCatId" value="0">
                            <input type="text" class="form-control form-control-sm" id="blogCatName" placeholder="VD: Tin tức, Kiến thức sơn">
                        </div>
                        <div class="col-12 col-md-12">
                            <label class="form-label mb-1 fw-semibold">Slug</label>
                            <input type="text" class="form-control form-control-sm" id="blogCatSlug" placeholder="vd: tin-tuc">
                            <small class="text-muted">Không dấu, chữ thường, ngăn cách bằng dấu gạch ngang.</small>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="blogCatReset">Làm mới</button>
                            <button type="button" class="btn btn-primary btn-sm" id="blogCatSave"><i class="bi bi-save"></i> Lưu thông tin</button>
                        </div>
                    </div>
                </div>
            <!-- /./ -->
            </div>
        </div>
    </div>
</div>

<script src="<?= $_TinyMceUrl ?>" referrerpolicy="origin"></script>
<?php $mceToolbarVer = @filemtime(__DIR__ . '/../../assets/js/mce-toolbar.js') ?: time(); ?>
<script src="<?= h($baseUrl) ?>/assets/js/mce-toolbar.js?v=<?= (int)$mceToolbarVer ?>"></script>

<script>
(function(){
    const API = '<?= h($baseUrl . '/core_admin/blog/ajax.php') ?>';
    const SITE_URL = '<?= h(rtrim($baseUrl, '/')) ?>';
    const $catTableBody = $('#blogCatTable tbody');
    const $postTableBody = $('#blogPostTable tbody');
    const $postTableWrap = $('#blogPostTableWrap');
    const $postEmpty = $('#blogPostsEmpty');
    const $postMeta = $('#blogPostMeta');
    const $catTabs = $('#blogCatTabs');

    let blogCats = [];
    let blogPosts = [];
    let editorReady = false;
    let pendingEditorContent = null;
    let postModal = null;
    let activeCatId = 0;
    let statusFilter = 'all'; // all | published | draft (theo KPI card)
    let postDataTable = null;

    function updateBlogKpi(){
        const posts = Array.isArray(blogPosts) ? blogPosts : [];
        const total = posts.length;
        const published = posts.filter(p => Number(p.is_active || 0) === 1).length;
        $('#kpiBlogTotal').text(total);
        $('#kpiBlogPublished').text(published);
        $('#kpiBlogDraft').text(total - published);
        $('#kpiBlogCats').text(Array.isArray(blogCats) ? blogCats.length : 0);
    }

    function destroyPostDataTable(){
        if (postDataTable) {
            try {
                postDataTable.clear().destroy();
            } catch (e) {}
            postDataTable = null;
        }
    }

    function ensurePostDataTable(){
        if (!$.fn || !$.fn.DataTable) return;
        destroyPostDataTable();
        postDataTable = $('#blogPostTable').DataTable({
            paging: true,
            searching: false,
            ordering: false,
            lengthChange: false,
            pageLength: 5,
            pagingType: 'simple_numbers',
            dom: "t<'d-flex justify-content-between align-items-center p-3 flex-column flex-md-row'<'dataTables_info'i><'dataTables_paginate paging_simple_numbers'p>>",
            language: {
                processing: 'Đang xử lý...',
                lengthMenu: 'Hiển thị _MENU_ bài',
                zeroRecords: 'Không tìm thấy bài viết phù hợp',
                info: 'Hiển thị _START_ - _END_ / _TOTAL_',
                infoEmpty: 'Không có bài viết',
                infoFiltered: '(lọc từ _MAX_ bài)',
                paginate: {
                    first: 'Đầu',
                    last: 'Cuối',
                    next: '>',
                    previous: '<'
                }
            }
        });
    }

    function notify(msg, type = 'info'){
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    }

    function parseJsonLoose(payload){
        if (payload && typeof payload === 'object') return payload;
        const text = String(payload || '').trim();
        if (!text) return null;
        try { return JSON.parse(text); } catch (e) {}
        const m = text.match(/\{[\s\S]*\}$/);
        if (m && m[0]) {
            try { return JSON.parse(m[0]); } catch (e2) {}
        }
        return null;
    }

    function requestJson(opts, onSuccess){
        const ajaxOpts = Object.assign({ dataType: 'text' }, opts || {});
        $.ajax(ajaxOpts).done(function(raw){
            const res = parseJsonLoose(raw);
            if (!res) {
                notify('Phản hồi server không hợp lệ', 'error');
                return;
            }
            onSuccess(res);
        }).fail(function(xhr){
            const msg = xhr?.responseJSON?.msg || 'Lỗi kết nối server';
            notify(msg, 'error');
        });
    }

    function esc(v){ return $('<div>').text(String(v || '')).html(); }

    function slugifyClient(str){
        let s = String(str || '').toLowerCase();
        const vnFrom = 'àáảãạâầấẩẫậăằắẳẵặèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ';
        const vnTo   = 'aaaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyyd';
        for (let i = 0; i < vnFrom.length; i++) {
            s = s.split(vnFrom[i]).join(vnTo[i]);
        }
        s = s.normalize ? s.normalize('NFD').replace(/\p{Diacritic}/gu, '') : s;
        s = s.replace(/[^a-z0-9\s\-]/g, '');
        s = s.replace(/[\s\-]+/g, '-');
        s = s.replace(/^-+|-+$/g, '');
        return s;
    }

    function ensurePostModal(){
        if (postModal) return postModal;
        const el = document.getElementById('blogPostModal');
        if (!el) return null;
        if (window.bootstrap && window.bootstrap.Modal){
            postModal = window.bootstrap.Modal.getOrCreateInstance(el);
        } else if ($(el).modal) {
            $(el).modal({ show: false });
            postModal = {
                show: () => $(el).modal('show'),
                hide: () => $(el).modal('hide')
            };
        }
        return postModal;
    }

    function openPostModal(){
        const m = ensurePostModal();
        if (m && typeof m.show === 'function') m.show();
    }

    // ===== Điều hướng 2 bước trong modal bài viết =====
    let blogStep = 1;
    function gotoStep(step){
        blogStep = (step === 2) ? 2 : 1;
        const onStep2 = blogStep === 2;
        $('#blogStep1').toggle(!onStep2);
        $('#blogStep2').toggle(onStep2);
        // Footer buttons
        $('#blogStepBack').toggle(onStep2);
        $('#blogStepNext').toggle(!onStep2);
        $('#blogPostSave').toggle(onStep2);
        // Stepper indicator
        $('#blogStepper [data-step-indicator="1"]').toggleClass('is-active', !onStep2).toggleClass('is-done', onStep2);
        $('#blogStepper [data-step-indicator="2"]').toggleClass('is-active', onStep2).removeClass('is-done');
        if (onStep2 && !getEditor()) {
            // Khởi tạo editor lần đầu khi sang bước 2 (khung đã hiển thị).
            // Nếu quay lại bước 1 rồi sang lại, editor đã tồn tại -> giữ nguyên nội dung đang gõ.
            initEditor();
        }
        // reset scroll modal
        const body = document.querySelector('#blogPostModal .modal-body');
        if (body) body.scrollTop = 0;
    }

    $('#blogStepNext').on('click', function(){
        // Validate tối thiểu trước khi sang bước nội dung
        const title = String($('#blogPostTitle').val() || '').trim();
        const categoryId = Number($('#blogPostCategory').val() || 0);
        if (!title){ notify('Vui lòng nhập tiêu đề bài viết', 'warning'); $('#blogPostTitle').focus(); return; }
        if (!categoryId){ notify('Vui lòng chọn chuyên mục', 'warning'); $('#blogPostCategory').focus(); return; }
        gotoStep(2);
    });
    $('#blogStepBack').on('click', function(){ gotoStep(1); });

    function renderCatTable(){
        if (!blogCats.length){
            $catTableBody.html('<tr><td colspan="5" class="text-center text-muted py-3">Chưa có chuyên mục.</td></tr>');
            return;
        }
        const rows = blogCats.map(function(item){
            const id = Number(item.id || 0);
            const active = Number(item.is_active || 0) === 1;
            const badge = active
                ? '<span class="blog-badge on">Đang bật</span>'
                : '<span class="blog-badge off">Đang tắt</span>';
            return '<tr data-id="' + id + '">' +
                '<td>' + id + '</td>' +
                '<td class="fw-semibold">' + esc(item.name || '') + '</td>' +
                '<td class="text-muted">' + esc(item.slug || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td class="text-end">' +
                    '<button type="button" class="btn btn-sm btn-light border me-1 blog-cat-toggle" data-id="' + id + '"><i class="bi ' + (active ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted') + '"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-light border me-1 blog-cat-edit" data-id="' + id + '"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-light border text-danger blog-cat-del" data-id="' + id + '"><i class="bi bi-trash"></i></button>' +
                '</td>' +
            '</tr>';
        }).join('');
        $catTableBody.html(rows);
    }

    function renderCatSelects(){
        const $sel = $('#blogPostCategory');
        const $filter = $('#blogPostFilterCat');
        if (!blogCats.length){
            $sel.html('<option value="0">-- Chưa có chuyên mục --</option>');
            $filter.html('<option value="0">Tất cả chuyên mục</option>');
            activeCatId = 0;
            return;
        }
        $sel.html('<option value="0">-- Chọn chuyên mục --</option>' + blogCats.map(function(c){
            return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || '') + '</option>';
        }).join(''));
        $filter.html('<option value="0">Tất cả chuyên mục</option>' + blogCats.map(function(c){
            return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || '') + '</option>';
        }).join(''));

        const current = Number($filter.val() || 0);
        activeCatId = Number.isFinite(current) ? current : 0;

        // Select chuyên mục cho modal Import WordPress (giữ option "Tự động" đầu danh sách)
        const $imp = $('#wpImportCategory');
        if ($imp.length){
            $imp.html('<option value="0">Tự động theo WordPress (tạo mới nếu chưa có)</option>' + blogCats.map(function(c){
                return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || '') + '</option>';
            }).join(''));
        }
    }

    function renderCatTabs(){
        if (!$catTabs || !$catTabs.length) return;
        const tabs = [{ id: 0, name: 'Tất cả' }].concat((Array.isArray(blogCats) ? blogCats : []).map(function(c){
            return { id: Number(c.id || 0), name: String(c.name || '') };
        }));

        const html = tabs.map(function(t){
            const isActive = Number(t.id) === Number(activeCatId);
            const cls = isActive ? 'btn-primary' : 'btn-outline-secondary';
            return '<button type="button" class="btn btn-sm ' + cls + ' me-2 cat-tab-item" data-cat-id="' + Number(t.id) + '">' +
                '<i class="bi bi-folder2-open me-1"></i>' + esc(t.name || '') +
            '</button>';
        }).join('');

        $catTabs.html(html);
    }

    function resetCatForm(){
        $('#blogCatId').val('0');
        $('#blogCatName').val('');
        $('#blogCatSlug').val('');
        // $('#blogCatActive').prop('checked', true); // Đã bỏ nút Bật hiển thị
    }

    $('#blogCatName').on('input', function(){
        const name = $(this).val();
        const current = $('#blogCatSlug').val();
        if (!current) {
            $('#blogCatSlug').val(slugifyClient(name));
        }
    });

    $('#blogCatSave').on('click', function(){
        const id = Number($('#blogCatId').val() || 0);
        const name = String($('#blogCatName').val() || '').trim();
        const slug = String($('#blogCatSlug').val() || '').trim();
        // const active = $('#blogCatActive').is(':checked') ? 1 : 0; // Đã bỏ nút Bật hiển thị
        if (!name){
            notify('Vui lòng nhập tên chuyên mục', 'warning');
            return;
        }
        requestJson({
            url: API,
            method: 'POST',
            data: { action: 'save_category', id, name, slug } // Không gửi is_active nữa
        }, function(res){
            if (!res || !res.ok){
                notify(res?.msg || 'Không lưu được chuyên mục', 'error');
                return;
            }
            notify(res.msg || 'Đã lưu chuyên mục', 'success');
            loadCategories();
        });
    });

    $('#blogCatReset').on('click', function(){ resetCatForm(); });

    $(document)
        .on('click', '.blog-cat-edit', function(){
            const id = Number($(this).data('id') || 0);
            const item = blogCats.find(c => Number(c.id || 0) === id);
            if (!item) return;
            $('#blogCatId').val(id);
            $('#blogCatName').val(String(item.name || ''));
            $('#blogCatSlug').val(String(item.slug || ''));
            // $('#blogCatActive').prop('checked', Number(item.is_active || 0) === 1); // Đã bỏ nút Bật hiển thị
        })
        .on('click', '.blog-cat-toggle', function(){
            const id = Number($(this).data('id') || 0);
            const item = blogCats.find(c => Number(c.id || 0) === id);
            if (!item) return;
            const next = Number(item.is_active || 0) === 1 ? 0 : 1;
            requestJson({ url: API, method: 'POST', data: { action: 'toggle_category', id, is_active: next } }, function(res){
                if (!res || !res.ok){
                    notify(res?.msg || 'Không cập nhật được chuyên mục', 'error');
                    return;
                }
                loadCategories();
            });
        })
        .on('click', '.blog-cat-del', function(){
            const id = Number($(this).data('id') || 0);
            if (!id) return;
            if (!confirm('Xóa chuyên mục này?')) return;
            requestJson({ url: API, method: 'POST', data: { action: 'delete_category', id } }, function(res){
                if (!res || !res.ok){
                    notify(res?.msg || 'Không xóa được chuyên mục', 'error');
                    return;
                }
                notify(res.msg || 'Đã xóa chuyên mục', 'success');
                loadCategories();
            });
        });

    function normalizeThumb(src){
        const raw = String(src || '').trim();
        if (!raw) return '';
        if (/^https?:\/\//i.test(raw) || raw.indexOf('data:image/') === 0) return raw;
        // Ưu tiên media domain (toMediaUrl từ head.php) cho file trong thư mục upload.
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(raw);
        return raw.startsWith('/') ? raw : ('/' + raw.replace(/^\/+/, ''));
    }

    function renderThumbPreview(src){
        const el = $('#blogPostThumbPreview');
        const img = normalizeThumb(src);
        if (!img){
            el.text('Chưa có ảnh');
            return;
        }
        el.html('<img src="' + esc(img) + '" alt="thumb" onerror="this.remove();this.parentNode.innerText=\'Ảnh lỗi\';">');
    }

    $('#btnPickBlogThumb').on('click', function(){
        MediaLibrary.open({
            type: 'image',
            multiple: false,
            onSelect: (items) => {
                if (items && items.length > 0) {
                    const url = items[0].url;
                    $('#blogPostThumbCurrent').val(url);
                    renderThumbPreview(url);
                }
            }
        });
    });

    // ===== Tự động tạo SEO + keyword (AI ưu tiên, fallback client-side) =====
    const VN_STOPWORDS = new Set(('và,của,các,cho,với,là,được,khi,này,đó,một,những,trong,trên,dưới,về,từ,đến,theo,như,để,có,không,thì,mà,ở,ra,vào,nên,bị,hay,hoặc,cũng,rất,đã,sẽ,đang,bạn,chúng,tôi,ta,họ,nó,nếu,vì,do,bởi,giúp,bằng,sau,trước,còn,nhiều,ít,phải,chỉ,thêm,nữa,lại,cùng,việc,sản,phẩm').split(','));

    function plainTextFromHtml(html){
        const d = document.createElement('div');
        d.innerHTML = String(html || '');
        return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
    }

    // Sinh keyword cơ bản: đếm tần suất cụm từ nổi bật, loại stopword
    function clientKeywords(text, max){
        const words = String(text || '').toLowerCase()
            .replace(/[^\p{L}\p{N}\s]/gu, ' ')
            .split(/\s+/).filter(w => w.length >= 3 && !VN_STOPWORDS.has(w) && !/^\d+$/.test(w));
        const freq = {};
        words.forEach(w => { freq[w] = (freq[w] || 0) + 1; });
        // ghép cụm 2 từ liền kề có tần suất tốt
        const bigrams = {};
        for (let i = 0; i < words.length - 1; i++){
            const bg = words[i] + ' ' + words[i+1];
            bigrams[bg] = (bigrams[bg] || 0) + 1;
        }
        const top = Object.entries(bigrams).filter(([,c]) => c >= 2).sort((a,b) => b[1]-a[1]).map(e => e[0])
            .concat(Object.entries(freq).sort((a,b) => b[1]-a[1]).map(e => e[0]));
        const seen = new Set(); const out = [];
        for (const k of top){
            if (out.some(o => o.includes(k) || k.includes(o))) continue;
            if (!seen.has(k)){ seen.add(k); out.push(k); }
            if (out.length >= (max || 8)) break;
        }
        return out;
    }

    function clientSeoFill({ onlyKeywords } = {}){
        const title = String($('#blogPostTitle').val() || '').trim();
        const excerpt = String($('#blogPostExcerpt').val() || '').trim();
        const content = plainTextFromHtml(getEditorContent());
        const corpus = (title + '. ' + excerpt + '. ' + content).trim();

        if (!onlyKeywords){
            if (!$('#blogMetaTitle').val().trim() && title) $('#blogMetaTitle').val(title.slice(0, 60));
            if (!$('#blogMetaDesc').val().trim()){
                let desc = excerpt || content;
                desc = desc.replace(/\s+/g, ' ').trim().slice(0, 160);
                $('#blogMetaDesc').val(desc);
            }
        }
        const kws = clientKeywords(corpus, onlyKeywords ? 10 : 8);
        if (kws.length) $('#blogMetaKeywords').val(kws.join(', '));
    }

    function runSeoSuggest(mode){ // mode: 'seo' | 'keywords'
        const title = String($('#blogPostTitle').val() || '').trim();
        const excerpt = String($('#blogPostExcerpt').val() || '').trim();
        const content = plainTextFromHtml(getEditorContent());
        const catName = $('#blogPostCategory option:selected').text() || '';
        if (!title && !excerpt && !content){
            notify('Hãy nhập tiêu đề hoặc nội dung trước', 'warning');
            return;
        }
        const $btn = mode === 'keywords' ? $('#btnKeywordAuto') : $('#btnSeoAuto');
        const oldHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: API, method: 'POST', dataType: 'text',
            data: { action: 'seo_suggest', title, excerpt, category: catName, content: content.slice(0, 4000) }
        }).done(function(raw){
            const res = parseJsonLoose(raw);
            if (res && res.ok && res.ai_used && res.data){
                const d = res.data;
                if (mode !== 'keywords'){
                    if (d.meta_title) $('#blogMetaTitle').val(d.meta_title);
                    if (d.meta_description) $('#blogMetaDesc').val(d.meta_description);
                }
                if (Array.isArray(d.keywords) && d.keywords.length) $('#blogMetaKeywords').val(d.keywords.join(', '));
                notify(mode === 'keywords' ? 'Đã tạo keyword bằng AI' : 'Đã tạo SEO bằng AI', 'success');
            } else {
                clientSeoFill({ onlyKeywords: mode === 'keywords' });
                notify((res && res.msg) ? res.msg : 'Đã tạo gợi ý cơ bản', 'info');
            }
        }).fail(function(){
            // Lỗi mạng -> vẫn sinh được bằng client
            clientSeoFill({ onlyKeywords: mode === 'keywords' });
            notify('Đã tạo gợi ý cơ bản (offline)', 'info');
        }).always(function(){
            $btn.prop('disabled', false).html(oldHtml);
        });
    }

    $('#btnSeoAuto').on('click', function(){ runSeoSuggest('seo'); });
    $('#btnKeywordAuto').on('click', function(){ runSeoSuggest('keywords'); });

    function resetPostForm(){
        $('#blogPostId').val('0');
        $('#blogPostTitle').val('');
        $('#blogPostSlug').val('');
        $('#blogPostCategory').val('0');
        $('#blogPostAuthor').val('');
        $('#blogPostTags').val('');
        $('#blogPostActive').prop('checked', true);
        $('#blogPostExcerpt').val('');
        $('#blogMetaTitle').val('');
        $('#blogMetaDesc').val('');
        $('#blogMetaKeywords').val('');
        $('#blogPostThumbCurrent').val('');
        $('#blogPostThumbFile').val('');
        renderThumbPreview('');
        // Sẽ chuẩn bị lại editor khi mở modal
        pendingEditorContent = '';
        $('#blogPostContent').val('');
    }

    $('#blogPostTitle').on('input', function(){
        const title = $(this).val();
        const current = $('#blogPostSlug').val();
        if (!current) {
            $('#blogPostSlug').val(slugifyClient(title));
        }
        const metaTitle = $('#blogMetaTitle').val();
        if (!metaTitle){
            $('#blogMetaTitle').val(String(title || ''));
        }
    });


    $('#blogPostAdd').on('click', function(){
        resetPostForm();
        // Khi tạo bài mới: editor rỗng, bắt đầu từ bước 1 (editor init khi sang bước 2)
        pendingEditorContent = '';
        $('#blogPostContent').val('');
        if (getEditor()) setEditorContent(''); // nếu editor đã tồn tại từ lần trước -> xoá nội dung cũ
        $('#blogPostModalLabel').text('Thêm bài viết');
        gotoStep(1);
        openPostModal();
    });

    function getEditor(){
        if (!window.tinymce || typeof window.tinymce.get !== 'function') return null;
        return window.tinymce.get('blogPostContent');
    }

    function setEditorContent(html){
        // Lưu lại nội dung mong muốn và sync xuống textarea gốc
        pendingEditorContent = String(html || '');
        $('#blogPostContent').val(pendingEditorContent);
        const ed = getEditor();
        if (ed) {
            try {
                ed.setContent(pendingEditorContent);
            } catch (e) {
                console.error('TinyMCE setContent error', e);
            }
        }
    }

    function getEditorContent(){
        const ed = getEditor();
        if (ed) return String(ed.getContent() || '');
        return String($('#blogPostContent').val() || '');
    }

    function initEditor(){
        if (!window.tinymce || typeof window.tinymce.init !== 'function') return;
        if (typeof window.initMceToolbar !== 'function') {
            notify('Không tải được mce-toolbar.js', 'warning');
            return;
        }
        try {
            // Mỗi lần gọi sẽ remove instance cũ (trong mce-toolbar.js) và tạo lại
            window.initMceToolbar({
                selector: '#blogPostContent',
                uploadUrl: '<?= h($baseUrl) ?>/core_admin/ecommerce/product.php',
                baseUrl: '<?= h($baseUrl) ?>',
                onChange: () => {},
                onReady: (editor) => {
                    editorReady = true;
                    if (pendingEditorContent !== null) {
                        try {
                            editor.setContent(pendingEditorContent);
                        } catch (e) {
                            console.error('TinyMCE onReady setContent error', e);
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Init TinyMCE blog failed', e);
            notify('Không thể khởi tạo TinyMCE', 'warning');
        }
    }

    function renderPostTable(){
        destroyPostDataTable();
        const totalAll = Array.isArray(blogPosts) ? blogPosts.length : 0;
        const filterCat = Number($('#blogPostFilterCat').val() || 0);
        activeCatId = Number.isFinite(filterCat) ? filterCat : 0;
        renderCatTabs();

        updateBlogKpi();

        const search = String($('#blogPostSearch').val() || '').toLowerCase();
        const filtered = (Array.isArray(blogPosts) ? blogPosts : []).filter(function(p){
            if (filterCat && Number(p.category_id || 0) !== filterCat) return false;
            if (statusFilter === 'published' && Number(p.is_active || 0) !== 1) return false;
            if (statusFilter === 'draft' && Number(p.is_active || 0) === 1) return false;
            if (search){
                const hay = (String(p.title || '') + ' ' + String(p.slug || '')).toLowerCase();
                if (hay.indexOf(search) === -1) return false;
            }
            return true;
        });

        if ($postMeta && $postMeta.length) {
            if (totalAll === 0) $postMeta.text('Chưa có bài viết');
            else if (filtered.length === totalAll) $postMeta.text('Tổng ' + totalAll + ' bài viết');
            else $postMeta.text('Hiển thị ' + filtered.length + '/' + totalAll + ' bài viết');
        }

        if (!filtered.length){
            $postTableBody.html('');
            if ($postEmpty && $postEmpty.length) $postEmpty.show();
            if ($postTableWrap && $postTableWrap.length) $postTableWrap.hide();
            return;
        }

        if ($postEmpty && $postEmpty.length) $postEmpty.hide();
        if ($postTableWrap && $postTableWrap.length) $postTableWrap.show();

        const rows = filtered.map(function(p){
            const id = Number(p.id || 0);
            const active = Number(p.is_active || 0) === 1;
            const badge = active
                ? '<span class="blog-badge on">Đang bật</span>'
                : '<span class="blog-badge off">Nháp / Ẩn</span>';
            return '<tr data-id="' + id + '">' +
                '<td>' + id + '</td>' +
                '<td><div class="fw-semibold">' + esc(p.title || '') + '</div><div class="small text-muted">' + esc(p.slug || '') + '</div></td>' +
                '<td>' + esc(p.category_name || '') + '</td>' +
                '<td>' + esc(p.author_name || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td class="text-end">' +
                    (active
                        ? '<a href="' + SITE_URL + '/blog/' + encodeURIComponent(p.slug || '') + '" target="_blank" rel="noopener" class="btn btn-sm btn-light border me-1 blog-post-view" title="Xem bài viết"><i class="bi bi-eye"></i></a>'
                        : '<button type="button" class="btn btn-sm btn-light border me-1 text-muted blog-post-view-off" disabled title="Bài đang ẩn/nháp — bật hiển thị để xem trên web"><i class="bi bi-eye-slash"></i></button>') +
                    '<button type="button" class="btn btn-sm btn-light border me-1 blog-post-toggle" data-id="' + id + '" title="Bật/Tắt hiển thị"><i class="bi ' + (active ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted') + '"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-light border me-1 blog-post-edit" data-id="' + id + '" title="Sửa"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-light border text-danger blog-post-del" data-id="' + id + '" title="Xóa"><i class="bi bi-trash"></i></button>' +
                '</td>' +
            '</tr>';
        }).join('');
        $postTableBody.html(rows);

        ensurePostDataTable();
    }

    let postSearchTimer = null;
    $('#blogPostSearch').on('input', function(){
        clearTimeout(postSearchTimer);
        postSearchTimer = setTimeout(renderPostTable, 250);
    });
    $('#blogPostFilterCat').on('change', function(){
        renderPostTable();
    });

    $catTabs.on('click', '.cat-tab-item', function(){
        const id = Number($(this).attr('data-cat-id') || 0);
        activeCatId = Number.isFinite(id) ? id : 0;
        $('#blogPostFilterCat').val(String(activeCatId));
        renderPostTable();
    });

    // KPI card: lọc theo trạng thái (Tổng / Đang đăng / Nháp-Ẩn)
    $(document).on('click', '.summary-card[data-blog-tab]', function(){
        statusFilter = String($(this).attr('data-blog-tab') || 'all');
        $('.summary-card[data-blog-tab]').removeClass('active');
        $(this).addClass('active');
        renderPostTable();
    });
    // KPI card "Chuyên mục": mở modal quản lý chuyên mục
    $(document).on('click', '.summary-card[data-blog-cats]', function(){
        const el = document.getElementById('blogCatModal');
        if (el && window.bootstrap && window.bootstrap.Modal){
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        }
    });

    $('#blogPostSave').on('click', function(){
        const id = Number($('#blogPostId').val() || 0);
        const title = String($('#blogPostTitle').val() || '').trim();
        const slug = String($('#blogPostSlug').val() || '').trim();
        const categoryId = Number($('#blogPostCategory').val() || 0);
        const author = String($('#blogPostAuthor').val() || '').trim();
        const tags = String($('#blogPostTags').val() || '').trim();
        const excerpt = String($('#blogPostExcerpt').val() || '').trim();
        const metaTitle = String($('#blogMetaTitle').val() || '').trim();
        const metaDesc = String($('#blogMetaDesc').val() || '').trim();
        const metaKeywords = String($('#blogMetaKeywords').val() || '').trim();
        const isActive = $('#blogPostActive').is(':checked') ? 1 : 0;
        const content = getEditorContent();

        if (!title){
            notify('Vui lòng nhập tiêu đề bài viết', 'warning');
            return;
        }
        if (!categoryId){
            notify('Vui lòng chọn chuyên mục', 'warning');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'save_post');
        fd.append('id', String(id));
        fd.append('category_id', String(categoryId));
        fd.append('title', title);
        fd.append('slug', slug);
        fd.append('excerpt', excerpt);
        fd.append('content', content);
        fd.append('author_name', author);
        fd.append('tags', tags);
        fd.append('meta_title', metaTitle);
        fd.append('meta_description', metaDesc);
        fd.append('meta_keywords', metaKeywords);
        fd.append('is_active', String(isActive));
        fd.append('current_thumb', $('#blogPostThumbCurrent').val());
        // Ảnh bìa chọn qua MediaLibrary (URL lưu ở #blogPostThumbCurrent); input upload file đã bỏ.
        const thumbFileEl = document.getElementById('blogPostThumbFile');
        const file = thumbFileEl && thumbFileEl.files ? thumbFileEl.files[0] : null;
        if (file) fd.append('thumb_file', file);

        $.ajax({
            url: API,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'text'
        }).done(function(raw){
            const res = parseJsonLoose(raw);
            if (!res || !res.ok){
                notify(res?.msg || 'Không lưu được bài viết', 'error');
                return;
            }
            notify(res.msg || 'Đã lưu bài viết', 'success');
            loadPosts();
            const m = ensurePostModal();
            if (m && typeof m.hide === 'function') m.hide();
        }).fail(function(){
            notify('Lỗi kết nối server', 'error');
        });
    });

    $(document)
        .on('click', '.blog-post-edit', function(){
            const id = Number($(this).data('id') || 0);
            if (!id) return;
            requestJson({ url: API, method: 'GET', data: { action: 'get_post', id } }, function(res){
                if (!res || !res.ok || !res.data){
                    notify(res?.msg || 'Không tải được bài viết', 'error');
                    return;
                }
                const p = res.data;
                $('#blogPostId').val(Number(p.id || 0));
                $('#blogPostTitle').val(String(p.title || ''));
                $('#blogPostSlug').val(String(p.slug || ''));
                $('#blogPostCategory').val(String(p.category_id || '0'));
                $('#blogPostAuthor').val(String(p.author_name || ''));
                $('#blogPostTags').val(String(p.tags || ''));
                $('#blogPostActive').prop('checked', Number(p.is_active || 0) === 1);
                $('#blogPostExcerpt').val(String(p.excerpt || ''));
                $('#blogMetaTitle').val(String(p.meta_title || ''));
                $('#blogMetaDesc').val(String(p.meta_description || ''));
                $('#blogMetaKeywords').val(String(p.meta_keywords || ''));
                $('#blogPostThumbCurrent').val(String(p.thumbnail_url || ''));
                $('#blogPostThumbFile').val('');
                renderThumbPreview(String(p.thumbnail_url || ''));

                // Chuẩn bị nội dung cho TinyMCE: lưu vào pending + textarea gốc
                pendingEditorContent = String(p.content || '');
                $('#blogPostContent').val(pendingEditorContent);
                if (getEditor()) setEditorContent(pendingEditorContent); // editor đã tồn tại -> nạp nội dung bài này

                // Mở modal ở bước 1; nếu editor chưa có sẽ init khi sang bước 2 (onReady tự set content)
                $('#blogPostModalLabel').text('Chỉnh sửa bài viết');
                gotoStep(1);
                openPostModal();
            });
        })
        .on('click', '.blog-post-toggle', function(){
            const id = Number($(this).data('id') || 0);
            if (!id) return;
            const row = blogPosts.find(p => Number(p.id || 0) === id);
            const next = row && Number(row.is_active || 0) === 1 ? 0 : 1;
            requestJson({ url: API, method: 'POST', data: { action: 'toggle_post', id, is_active: next } }, function(res){
                if (!res || !res.ok){
                    notify(res?.msg || 'Không cập nhật được bài viết', 'error');
                    return;
                }
                loadPosts();
            });
        })
        .on('click', '.blog-post-del', function(){
            const id = Number($(this).data('id') || 0);
            if (!id) return;
            if (!confirm('Xóa bài viết này?')) return;
            requestJson({ url: API, method: 'POST', data: { action: 'delete_post', id } }, function(res){
                if (!res || !res.ok){
                    notify(res?.msg || 'Không xóa được bài viết', 'error');
                    return;
                }
                notify(res.msg || 'Đã xóa bài viết', 'success');
                loadPosts();
            });
        });

    function loadCategories(){
        requestJson({ url: API, method: 'GET', data: { action: 'list_categories' } }, function(res){
            if (!res || !res.ok){
                notify(res?.msg || 'Không tải được danh sách chuyên mục', 'error');
                return;
            }
            blogCats = Array.isArray(res.rows) ? res.rows : [];
            renderCatTable();
            renderCatSelects();
            renderCatTabs();
            updateBlogKpi();
        });
    }

    function loadPosts(){
        if ($postMeta && $postMeta.length) $postMeta.text('Đang tải dữ liệu...');
        if ($postEmpty && $postEmpty.length) $postEmpty.hide();
        if ($postTableWrap && $postTableWrap.length) $postTableWrap.show();
        $postTableBody.html('<tr><td colspan="6" class="text-center text-muted py-3">Đang tải...</td></tr>');
        requestJson({ url: API, method: 'GET', data: { action: 'list_posts' } }, function(res){
            if (!res || !res.ok){
                notify(res?.msg || 'Không tải được danh sách bài viết', 'error');
                return;
            }
            blogPosts = Array.isArray(res.rows) ? res.rows : [];
            renderPostTable();
        });
    }

    // ===== IMPORT BÀI VIẾT TỪ WORDPRESS =====
    function bindWpImport(){
        const $btn = $('#wpImportSubmit');
        if (!$btn.length || $btn.data('bound')) return;
        $btn.data('bound', true);

        $btn.on('click', function(){
            const url = String($('#wpImportUrl').val() || '').trim();
            const categoryId = Number($('#wpImportCategory').val() || 0);
            const publish = $('#wpImportPublish').is(':checked') ? 1 : 0;
            const $result = $('#wpImportResult');

            if (!url){
                $result.html('<span class="text-danger">Vui lòng nhập URL bài viết WordPress.</span>');
                return;
            }

            const original = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Đang import...');
            $result.html('<span class="text-muted">Đang tải bài viết từ nguồn, vui lòng đợi...</span>');

            requestJson({
                url: API,
                method: 'POST',
                data: { action: 'import_wp', url: url, category_id: categoryId, is_active: publish }
            }, function(res){
                $btn.prop('disabled', false).html(original);
                if (!res || !res.ok){
                    $result.html('<span class="text-danger">' + esc(res?.msg || 'Import thất bại') + '</span>');
                    return;
                }
                const d = res.data || {};
                $result.html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>'
                    + esc(res.msg || 'Đã import') + '</span>'
                    + '<div class="mt-1 text-muted">Tiêu đề: <b>' + esc(d.title || '') + '</b>'
                    + (d.category ? ' · Chuyên mục: ' + esc(d.category) : '') + '</div>');
                notify('Đã import bài viết từ WordPress', 'success');
                $('#wpImportUrl').val('');
                loadCategories();
                loadPosts();
                setTimeout(function(){
                    const el = document.getElementById('blogImportModal');
                    if (el && window.bootstrap){
                        const inst = window.bootstrap.Modal.getInstance(el);
                        if (inst) inst.hide();
                    }
                    $result.empty();
                }, 1600);
            });
        });
    }

    $(function(){
        loadCategories();
        loadPosts();
        bindWpImport();
        // Chỉ khởi tạo editor khi cần (thêm/sửa), tránh set nội dung sai thời điểm
    });
})();
</script>
