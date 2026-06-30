<?php 
require_once __DIR__ . '/_admin_guard.php';
?>
<style>
.fb-table-card { transition: all 0.3s ease; }
.fb-table tbody tr { transition: background-color 0.2s ease; }
.fb-table tbody tr:hover { background-color: rgba(var(--theme-primary-rgb), 0.02) !important; }
.hover-opacity-100 { transition: opacity 0.2s ease; }
.hover-opacity-100:hover { opacity: 1 !important; }
.btn-white { background: #fff; color: #444; border: 1px solid #eee; }
.btn-white:hover { background: #f8f9fa; color: var(--theme-primary); }
.dropdown-menu { 
    background-color: #fff !important; 
    z-index: 1060 !important; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
}
#u_avatar_trigger { cursor: pointer; transition: transform 0.2s; display: block; }
#u_avatar_trigger:hover { transform: scale(1.05); }
#u_avatar_trigger:hover .bg-primary { background-color: var(--theme-primary-dark) !important; }
</style>
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Quản lý tài khoản</h1>
            <p class="text-muted mb-0 small">Danh sách tài khoản hệ thống, chỉnh sửa thông tin và quản lý số dư xu.</p>
        </div>
        <button class="btn btn-primary btn-sm rounded-pill shadow-sm d-flex align-items-center gap-2" onclick="openForm()">
            <i class="bi bi-person-plus-fill"></i>
            <span class="fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Thêm tài khoản</span>
        </button>
    </div>

    <!-- Table Container -->
    <div class="card fb-table-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-0" id="tableSearch" placeholder="Tìm tên, username, email...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm border shadow-sm rounded-3" id="filterRole">
                        <option value="">Tất cả quyền</option>
                        <option value="admin">Quản trị viên</option>
                        <option value="user">Thành viên</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm border shadow-sm rounded-3" id="filterProvider">
                        <option value="">Tất cả nguồn</option>
                        <option value="google">Google</option>
                        <option value="phone_otp">Zalo OTP</option>
                        <option value="password">Thủ công</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <!-- Bulk Delete Button (Hidden by default, shown when selection > 0) -->
                    <div class="d-none" id="bulkActionsContainer">
                        <button class="btn btn-sm btn-danger rounded-pill px-4 shadow-sm fw-bold" type="button" id="btnBulkDelete">
                            <i class="bi bi-trash3-fill me-1"></i> Xóa (<span id="selectedCountText">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="fb-table-responsive border-top">
            <table class="table fb-table table-hover align-middle mb-0" id="userTable">
                <thead>
                    <tr>
                        <th style="width:40px;" class="ps-4">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="checkAllUsers">
                            </div>
                        </th>
                        <th style="width: 60px;">ID</th>
                        <th>Thông tin tài khoản</th>
                        <th>Liên hệ</th>
                        <th>Phân loại</th>
                        <th>Phân quyền</th>
                        <th>Số dư (Xu)</th>
                        <th>Ngày tạo</th>
                        <th class="text-end pe-4">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JS loaded content -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Wallet Modal -->
<div class="modal fade" id="walletModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <div class="bg-warning bg-opacity-10 p-2 rounded-3 text-warning me-3">
                    <i class="bi bi-coin fs-4"></i>
                </div>
                <h5 class="modal-title fw-bold">Điều chỉnh số dư xu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-3 pb-4">
                <form id="walletForm" class="row g-3">
                    <input type="hidden" name="action" value="wallet_update">
                    <input type="hidden" name="id" id="wallet_user_id">
                    
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-3 mb-2">
                            <label class="form-label smaller text-uppercase fw-bold text-muted mb-1" style="font-size: 0.65rem;">Tài khoản người dùng</label>
                            <input class="form-control-plaintext fw-bold p-0" id="wallet_user_label" readonly style="font-size: 0.9rem;">
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <label class="form-label small fw-bold text-dark">Số dư hiện tại</label>
                        <div class="input-group">
                            <input class="form-control bg-light border-0 fw-bold text-primary" id="wallet_current_balance" readonly>
                            <span class="input-group-text bg-light border-0"><i class="bi bi-wallet2"></i></span>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <label class="form-label small fw-bold text-dark">Loại thao tác</label>
                        <select class="form-select border-0 bg-light rounded-3" name="mode" id="wallet_mode" required>
                            <option value="gift">Tặng xu</option>
                            <option value="refund">Hoàn xu</option>
                            <option value="deduct">Giảm xu</option>
                            <option value="set_balance">Thiết lập lại</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label small fw-bold text-dark" id="wallet_amount_label">Số xu thay đổi</label>
                        <div class="input-group">
                            <input type="number" min="0" step="1" class="form-control border-0 bg-light rounded-start-3" name="amount" id="wallet_amount" required placeholder="Nhập số xu...">
                            <span class="input-group-text border-0 bg-light rounded-end-3 text-warning"><i class="bi bi-plus-circle-fill"></i></span>
                        </div>
                        <div class="form-text smaller text-muted italic" id="wallet_amount_hint" style="font-size: 0.7rem;">Nhập số xu cần cộng/trừ.</div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label small fw-bold text-dark">Lý do điều chỉnh</label>
                        <textarea class="form-control border-0 bg-light rounded-3" name="note" id="wallet_note" rows="3" placeholder="Nhập lý do thao tác (bắt buộc)" required style="font-size: 0.85rem;"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="btnWalletSubmit">Xác nhận cập nhật</button>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">Thông tin tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-3 pb-4">
                <form id="userForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="u_id">
                    <div class="mb-4 d-flex justify-content-center">
                        <label for="u_avatar_file" class="position-relative" id="u_avatar_trigger">
                            <div id="u_avatar_preview" class="rounded-circle border shadow-sm overflow-hidden" style="width: 100px; height: 100px;">
                                <img src="" class="w-100 h-100 object-fit-cover d-none">
                                <div class="bg-light d-flex align-items-center justify-content-center h-100 w-100 text-muted" style="font-size: 2rem;">
                                    <i class="bi bi-person"></i>
                                </div>
                            </div>
                            <div class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px; font-size: 1rem; border: 3px solid #fff; pointer-events: none;">
                                <i class="bi bi-camera-fill"></i>
                            </div>
                            <input type="file" id="u_avatar_file" name="avatar_file" accept="image/*" class="d-none">
                            <input type="hidden" name="avatar" id="u_avatar">
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tên đăng nhập (Username)</label>
                        <div class="input-group">
                            <span class="input-group-text border-0 bg-light rounded-start-3 text-muted">@</span>
                            <input class="form-control border-0 bg-light rounded-end-3" name="username" id="u_username" required placeholder="username_cua_ban">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Họ và tên</label>
                            <input class="form-control border-0 bg-light rounded-3" name="full_name" id="u_full_name" placeholder="Nguyễn Văn A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Email</label>
                            <input type="email" class="form-control border-0 bg-light rounded-3" name="email" id="u_email" placeholder="example@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Số điện thoại</label>
                            <input class="form-control border-0 bg-light rounded-3" name="phone" id="u_phone" placeholder="0901234xxx">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Quyền hệ thống</label>
                            <select class="form-select border-0 bg-light rounded-3" name="role" id="u_role">
                                <option value="user">Thành viên (Member)</option>
                                <option value="admin">Quản trị viên (Administrator)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-1">
                        <label class="form-label small fw-bold text-dark">Mật khẩu mới</label>
                        <input type="password" class="form-control border-0 bg-light rounded-3" name="password" id="u_password" placeholder="Để trống nếu không muốn thay đổi">
                        <div class="form-text smaller" style="font-size: 0.7rem;">Mật khẩu nên có ít nhất 6 ký tự để đảm bảo bảo mật.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="saveUser()">Lưu thông tin</button>
            </div>
        </div>
    </div>
</div>

<script>
const API = '<?= h($baseUrl) ?>/core_admin/ajax/users.php';
const BASE_URL = '<?= h($baseUrl) ?>';
let tableData = [];
const selectedUserIds = new Set();
const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

function updateSelectedUsersUi() {
    const count = selectedUserIds.size;
    $('#selectedCountText').text(count);
    
    const container = $('#bulkActionsContainer');
    if (count > 0) {
        container.removeClass('d-none');
    } else {
        container.addClass('d-none');
    }

    const visibleChecks = $('#userTable tbody .user-check');
    const allChecked = visibleChecks.length > 0 && visibleChecks.filter(':checked').length === visibleChecks.length;
    $('#checkAllUsers').prop('checked', allChecked);
}

let userDataTable = null;

function renderTable() {
    if (userDataTable) {
        userDataTable.destroy();
    }

    const tbody = $('#userTable tbody');
    tbody.html('');
    
    tableData.forEach(u => {
        const username = escapeHtml(u.username || '');
        const fullName = escapeHtml(u.full_name || 'Chưa cập nhật');
        const email = escapeHtml(u.email || 'N/A');
        const phone = escapeHtml(u.phone || 'N/A');
        const roleStr = String(u.role || 'user').toLowerCase();
        const roleBadge = roleStr === 'admin' ? '<span class="badge bg-danger bg-opacity-10 text-danger border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.65rem;">ADMIN</span>' : '<span class="badge bg-primary bg-opacity-10 text-primary border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.65rem;">MEMBER</span>';
        const created = escapeHtml(u.created_at || '').split(' ')[0];
        const xu = Number(u.xu_balance || 0).toLocaleString('vi-VN');
        const isChecked = selectedUserIds.has(String(u.id)) ? 'checked' : '';
        const avatar = u.avatar || '';
        const initial = fullName.charAt(0).toUpperCase();
        
        let method = 'password';
        try { if (u.reg_meta) method = JSON.parse(u.reg_meta).method || 'password'; } catch(e) {}
        
        let providerBadge = '';
        if (method === 'google') providerBadge = '<span class="badge bg-white text-dark border rounded-pill px-2 py-1 fw-normal" style="font-size: 0.7rem;"><i class="bi bi-google text-danger me-1"></i>Google</span>';
        else if (method === 'phone_otp') providerBadge = '<span class="badge bg-white text-dark border rounded-pill px-2 py-1 fw-normal" style="font-size: 0.7rem;"><i class="bi bi-chat-dots-fill text-primary me-1"></i>Zalo OTP</span>';
        else providerBadge = '<span class="badge bg-white text-dark border rounded-pill px-2 py-1 fw-normal" style="font-size: 0.7rem;"><i class="bi bi-person-badge text-muted me-1"></i>Thủ công</span>';

        let avatarHtml = avatar ? `<img src="${(avatar.startsWith('http')||avatar.startsWith('//'))?avatar:(BASE_URL+(avatar.startsWith('/')?'':'/')+avatar)}" class="rounded-circle border shadow-sm" style="width: 38px; height: 38px; object-fit: cover;" onerror="this.outerHTML='<div class=\'bg-white text-dark border fw-bold d-flex align-items-center justify-content-center rounded-circle border\' style=\'width: 38px; height: 38px; font-size: 0.9rem;\'>${initial}</div>'">` : `<div class="bg-primary text-white fw-bold d-flex align-items-center justify-content-center rounded-circle border" style="width: 38px; height: 38px; font-size: 0.9rem;">${initial}</div>`;

        tbody.append(`<tr>
            <td class="ps-4"><div class="form-check mb-0"><input class="form-check-input user-check" type="checkbox" data-id="${u.id}" ${isChecked}></div></td>
            <td class="text-muted small">#${u.id}</td>
            <td><div class="d-flex align-items-center gap-3">${avatarHtml}<div><div class="fw-bold text-dark mb-0" style="font-size: 0.85rem;">${fullName}</div><div class="text-muted smaller" style="font-size: 0.75rem;">@${username}</div></div></div></td>
            <td><div class="d-flex flex-column"><div class="small text-dark mb-1"><i class="bi bi-envelope-fill me-1 text-muted"></i>${email}</div><div class="small text-muted"><i class="bi bi-telephone-fill me-1 text-muted"></i>${phone}</div></div></td>
            <td>${providerBadge}<span class="d-none">${method}</span></td>
            <td>${roleBadge}<span class="d-none">${roleStr}</span></td>
            <td><span class="fw-bold text-dark" style="font-size: 0.9rem;">${xu}</span></td>
            <td class="small text-muted">${created}</td>
            <td class="text-end pe-4">
                <div class="dropdown">
                    <button class="btn btn-sm btn-white border shadow-sm rounded-pill px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size: 0.75rem;">
                        Thao tác
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2" style="font-size: 0.8rem;">
                        <li><h6 class="dropdown-header smaller text-uppercase fw-bold text-muted" style="font-size: 0.65rem;">Quản lý tài khoản</h6></li>
                        <li><button class="dropdown-item py-2" type="button" data-edit-user="${u.id}"><i class="bi bi-pencil-square me-2 text-primary"></i>Sửa thông tin</button></li>
                        <li><hr class="dropdown-divider opacity-50"></li>
                        <li><h6 class="dropdown-header smaller text-uppercase fw-bold text-muted" style="font-size: 0.65rem;">Quản lý số dư</h6></li>
                        <li><button class="dropdown-item py-2" type="button" onclick="openWalletTool(${u.id}, 'gift')"><i class="bi bi-gift me-2 text-success"></i>Tặng xu</button></li>
                        <li><button class="dropdown-item py-2" type="button" onclick="openWalletTool(${u.id}, 'deduct')"><i class="bi bi-dash-circle me-2 text-warning"></i>Giảm xu</button></li>
                        <li><button class="dropdown-item py-2" type="button" onclick="openWalletTool(${u.id}, 'set_balance')"><i class="bi bi-sliders me-2 text-secondary"></i>Thiết lập số dư</button></li>
                        <li><hr class="dropdown-divider opacity-50"></li>
                        <li><button class="dropdown-item py-2 text-danger" type="button" data-delete-user="${u.id}"><i class="bi bi-trash3-fill me-2"></i>Xóa tài khoản</button></li>
                    </ul>
                </div>
            </td>
        </tr>`);
    });

    userDataTable = $('#userTable').DataTable({
        pageLength: 10,
        dom: 'rt<"d-flex justify-content-between align-items-center p-4 border-top"ip>',
        language:{
                    info: "Hiển thị _START_ - _END_ / _TOTAL_",
                    infoEmpty: "Không có dữ liệu",
                    infoFiltered: "(lọc từ _MAX_ mục)",
                    lengthMenu: "Hiển thị _MENU_ mục",
                    zeroRecords: "Không tìm thấy dữ liệu",
                    search: "Tìm kiếm:",
                    paginate: {
                        first: "Đầu",
                        last: "Cuối",
                        next: ">",
                        previous: "<"
                    },
                    processing: "Đang xử lý...",
                    loadingRecords: "Đang tải...",
                    emptyTable: "Không có dữ liệu trong bảng"
        },
        columnDefs: [
            { orderable: false, targets: [0, 8] }
        ],
        order: [[1, 'desc']]
    });
    applyFilters();
    updateSelectedUsersUi();
}

function applyFilters() {
    if (!userDataTable) return;
    const search = $('#tableSearch').val();
    const role = $('#filterRole').val();
    const provider = $('#filterProvider').val();

    userDataTable.search(search);
    userDataTable.column(5).search(role);
    userDataTable.column(4).search(provider);
    userDataTable.draw();
}

function loadUsers(){
    $.get(API + '?ajax=list', res => {
        if (!res || res.ok === false) { toastr.error((res && res.msg) ? res.msg : 'Không tải được danh sách tài khoản'); return; }
        tableData = res.data || [];
        renderTable();
    }, 'json');
}

function walletModeText(mode){
    if (mode === 'gift') return 'Tặng xu';
    if (mode === 'refund') return 'Hoàn xu';
    if (mode === 'deduct') return 'Giảm xu';
    return 'Chỉnh sửa chi tiết';
}

function walletHint(mode, currentBalance){
    if (mode === 'set_balance') return `Nhập số dư mới. Số dư hiện tại: ${Number(currentBalance || 0).toLocaleString('vi-VN')} xu.`;
    if (mode === 'deduct') return `Nhập số xu cần giảm. Tối đa ${Number(currentBalance || 0).toLocaleString('vi-VN')} xu.`;
    if (mode === 'refund') return 'Nhập số xu hoàn cho người dùng.';
    return 'Nhập số xu cần tặng cho người dùng.';
}

function openWalletTool(userId, mode = 'gift'){
    const user = tableData.find(x => String(x.id) === String(userId));
    if (!user) { toastr.error('Không tìm thấy tài khoản'); return; }
    const currentBalance = Number(user.xu_balance || 0);
    $('#wallet_user_id').val(user.id);
    $('#wallet_user_label').val(`${user.username || ''} (#${user.id})`);
    $('#wallet_current_balance').val(currentBalance.toLocaleString('vi-VN') + ' xu');
    $('#wallet_mode').val(mode);
    $('#wallet_note').val('');
    if (mode === 'set_balance') { $('#wallet_amount').val(currentBalance); $('#wallet_amount_label').text('Số dư mới'); } else { $('#wallet_amount').val(''); $('#wallet_amount_label').text('Số xu'); }
    $('#wallet_amount_hint').text(walletHint(mode, currentBalance));
    new bootstrap.Modal('#walletModal').show();
}

function openForm(id=0){
    $('#userForm')[0].reset();
    $('#u_id').val(id);
    const updateAvatarPreview = (val) => {
        const preview = $('#u_avatar_preview');
        const img = preview.find('img');
        const placeholder = preview.find('.bg-light');
        if (val) { const abs = (val.startsWith('http') || val.startsWith('//')) ? val : (BASE_URL + (val.startsWith('/') ? '' : '/') + val); img.attr('src', abs).removeClass('d-none'); placeholder.addClass('d-none'); } else { img.addClass('d-none').attr('src', ''); placeholder.removeClass('d-none'); }
    };
    if(id){
        const u = tableData.find(x=>x.id==id);
        $('#u_username').val(u?.username||'');
        $('#u_full_name').val(u?.full_name||'');
        $('#u_email').val(u?.email||'');
        $('#u_phone').val(u?.phone||'');
        $('#u_avatar').val(u?.avatar||'');
        $('#u_role').val(u?.role||'user');
        updateAvatarPreview(u?.avatar);
    } else { updateAvatarPreview(''); }
    $('#u_avatar_file').val('');
    const modalEl = document.getElementById('userModal');
    let userModal = bootstrap.Modal.getInstance(modalEl);
    if (!userModal) userModal = new bootstrap.Modal(modalEl);
    userModal.show();
}

function saveUser(){
    const form = document.getElementById('userForm');
    const formData = new FormData(form);
    
    $.ajax({
        url: API,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: res => {
            if(res.ok){ 
                loadUsers(); 
                bootstrap.Modal.getInstance(document.getElementById('userModal')).hide(); 
                toastr.success('Đã lưu'); 
            } else {
                toastr.error(res.msg||'Lỗi');
            }
        },
        error: () => toastr.error('Lỗi kết nối')
    });
}

function delUser(id){
    if(!confirm('Xóa tài khoản này?')) return;
    $.post(API, {action:'delete', id}, res => { if(res.ok){ loadUsers(); toastr.success('Đã xóa'); } else toastr.error(res.msg||'Lỗi'); }, 'json');
}

$(function(){
    loadUsers();
    $('#tableSearch').on('input', applyFilters);
    $('#filterRole, #filterProvider').on('change', applyFilters);

    $('#userTable').on('click', '[data-edit-user]', function(){ openForm($(this).data('edit-user')); });

    // Avatar Preview
    $(document).on('change', '#u_avatar_file', function(){
        const file = this.files[0];
        if (file) {
            // Basic size check (e.g. 5MB)
            if (file.size > 5 * 1024 * 1024) {
                toastr.warning('Ảnh quá lớn (tối đa 5MB)');
                this.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e){
                const preview = $('#u_avatar_preview');
                preview.find('img').attr('src', e.target.result).removeClass('d-none');
                preview.find('.bg-light').addClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    });
    $('#userTable').on('click', '[data-delete-user]', function(){ delUser($(this).data('delete-user')); });
    $('#userTable').on('change', '.user-check', function(){ const id = String($(this).data('id') || ''); if (!id) return; if ($(this).is(':checked')) selectedUserIds.add(id); else selectedUserIds.delete(id); updateSelectedUsersUi(); });
    $('#checkAllUsers').on('change', function(){ const checked = $(this).is(':checked'); $('#userTable tbody .user-check').each(function(){ const id = String($(this).data('id')); $(this).prop('checked', checked); if (checked) selectedUserIds.add(id); else selectedUserIds.delete(id); }); updateSelectedUsersUi(); });
    $('#btnBulkDelete').on('click', function(){ 
        if (selectedUserIds.size === 0) return; 
        if (!confirm(`Bạn có chắc muốn xóa ${selectedUserIds.size} tài khoản đã chọn?`)) return; 
        $.post(API, { action: 'delete_multi', ids: Array.from(selectedUserIds) }, res => { 
            if (res.ok) { toastr.success('Đã xóa'); selectedUserIds.clear(); loadUsers(); } 
            else toastr.error(res.msg || 'Lỗi'); 
        }, 'json'); 
    });
    $('#wallet_mode').on('change', function(){ const mode = String($(this).val() || 'gift'); const currentText = String($('#wallet_current_balance').val() || '').replace(/[^0-9]/g, ''); const current = Number(currentText || 0); $('#wallet_amount_label').text(mode === 'set_balance' ? 'Số dư mới' : 'Số xu'); $('#wallet_amount_hint').text(walletHint(mode, current)); if (mode === 'set_balance') $('#wallet_amount').val(current); else $('#wallet_amount').val(''); });
    $('#btnWalletSubmit').on('click', function(){
        const mode = String($('#wallet_mode').val() || 'gift');
        const amount = Number($('#wallet_amount').val() || 0);
        const note = String($('#wallet_note').val() || '').trim();
        if (!Number.isInteger(amount) || amount < 0 || (mode !== 'set_balance' && amount === 0)) { toastr.warning(mode === 'set_balance' ? 'Số dư mới không hợp lệ' : 'Số xu không hợp lệ'); return; }
        if (!note) { toastr.warning('Vui lòng nhập lý do thao tác'); return; }
        
        $.post(API, $('#walletForm').serialize(), res => {
            if (res && res.ok) {
                loadUsers();
                bootstrap.Modal.getInstance(document.getElementById('walletModal')).hide();
                toastr.success(walletModeText(mode) + ' thành công');
            } else {
                toastr.error((res && res.msg) ? res.msg : 'Không thể cập nhật xu');
            }
        }, 'json').fail(() => { toastr.error('Lỗi kết nối server'); });
    });
});
</script>
<script src="<?= h($baseUrl) ?>/assets/js/jquery.dataTables.min.js"></script>
<script src="<?= h($baseUrl) ?>/assets/js/dataTables.bootstrap5.min.js"></script>
