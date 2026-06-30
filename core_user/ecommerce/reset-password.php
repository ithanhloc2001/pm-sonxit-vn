<?php
// Tệp: core_user/ecommerce/reset-password.php
// Đảm bảo không truy cập trực tiếp nếu thiếu biến kết nối
if (!isset($ithanhloc)) {
    die('Yêu cầu không hợp lệ.');
}

// 1. Nhận và lọc đầu vào
$email = clean_email($_GET['email'] ?? '', 254);
$token = clean_input($_GET['token'] ?? '', 255);

$isValid = false;
$errorMsg = '';

if ($email === '' || $token === '') {
    $errorMsg = 'Yêu cầu không hợp lệ. Vui lòng kiểm tra lại liên kết khôi phục mật khẩu trong email của bạn.';
} else {
    // 2. Kiểm tra token trong CSDL
    $stmt = $ithanhloc->prepare('SELECT expires_at FROM log_password_reset WHERE email = ? AND token = ?');
    if ($stmt) {
        $stmt->bind_param('ss', $email, $token);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($expiresAt);
            $stmt->fetch();
            
            // So sánh thời gian hết hạn
            $resetTimeoutMinutes = 30;
            if (function_exists('app_get_config_value_by_path')) {
                $configMin = app_get_config_value_by_path('RESET_PASSWORD_TIMEOUT_MINUTES');
                if ($configMin !== null) {
                    $resetTimeoutMinutes = (int)$configMin;
                }
            }
            if (strtotime($expiresAt) >= time()) {
                $isValid = true;
            } else {
                $errorMsg = 'Liên kết khôi phục mật khẩu đã hết hạn (hiệu lực trong ' . $resetTimeoutMinutes . ' phút). Vui lòng gửi yêu cầu mới.';
                // Xoá token đã hết hạn
                $delStmt = $ithanhloc->prepare('DELETE FROM log_password_reset WHERE email = ?');
                if ($delStmt) {
                    $delStmt->bind_param('s', $email);
                    $delStmt->execute();
                    $delStmt->close();
                }
            }
        } else {
            $errorMsg = 'Liên kết khôi phục mật khẩu không hợp lệ hoặc đã được sử dụng.';
        }
        $stmt->close();
    } else {
        $errorMsg = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
    }
}

// 3a. Xử lý gửi lại liên kết khôi phục (AJAX POST) — tối đa 5 lần/ngày/email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'resend_reset')) {
    while (ob_get_level()) ob_end_clean();
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Chống spam: tối thiểu 30 giây giữa 2 lần bấm gửi lại
    $lastResend = $_SESSION['last_reset_resend_at'] ?? 0;
    if (time() - $lastResend < 30) {
        jOut(['success' => false, 'message' => 'Bạn thao tác quá nhanh. Vui lòng đợi ' . (30 - (time() - $lastResend)) . ' giây.']);
    }

    $resendEmail = clean_email($_POST['email'] ?? ($_GET['email'] ?? ''), 254);
    if ($resendEmail === '') {
        jOut(['success' => false, 'message' => 'Thiếu địa chỉ email để gửi lại liên kết.']);
    }

    require_once __DIR__ . '/../../core/auth/password_reset.php';
    $resend = pm_resend_reset_link($ithanhloc, $resendEmail, 5);
    if (!empty($resend['success'])) {
        $_SESSION['last_reset_resend_at'] = time();
    }
    jOut($resend);
}

// 3. Xử lý khi submit đổi mật khẩu mới (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    // Luôn dọn dẹp output buffer trước khi trả về JSON
    while (ob_get_level()) ob_end_clean();
    
    // Khởi động session nếu chưa có
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Chống spam / Khóa ở backend: Giới hạn tối thiểu 5 giây giữa các lần bấm submit đổi mật khẩu
    $lastSubmit = $_SESSION['last_password_reset_submit'] ?? 0;
    if (time() - $lastSubmit < 5) {
        jOut(['success' => false, 'message' => 'Bạn đang thực hiện thao tác quá nhanh. Vui lòng thử lại sau vài giây.']);
    }
    $_SESSION['last_password_reset_submit'] = time();
    
    if (!$isValid) {
        jOut(['success' => false, 'message' => $errorMsg]);
    }

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // Dọn dẹp ký tự null byte
    $password = str_replace("\0", '', $password);
    $confirmPassword = str_replace("\0", '', $confirmPassword);

    if (strlen($password) < 6) {
        jOut(['success' => false, 'message' => 'Mật khẩu mới phải có tối thiểu 6 ký tự.']);
    }
    // Chặn mật khẩu quá dài (chống DoS băm bcrypt).
    if (strlen($password) > 200) {
        jOut(['success' => false, 'message' => 'Mật khẩu mới quá dài (tối đa 200 ký tự).']);
    }
    if ($password !== $confirmPassword) {
        jOut(['success' => false, 'message' => 'Xác nhận mật khẩu mới không trùng khớp.']);
    }

    // Tiến hành cập nhật mật khẩu mới
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $upStmt = $ithanhloc->prepare('UPDATE users SET password = ? WHERE email = ?');
    if ($upStmt) {
        $upStmt->bind_param('ss', $hash, $email);
        $ok = $upStmt->execute();
        $upStmt->close();

        if ($ok) {
            // Xoá token sau khi sử dụng thành công — kiểm tra kết quả để không để token sống.
            $delStmt = $ithanhloc->prepare('DELETE FROM log_password_reset WHERE email = ?');
            if ($delStmt) {
                $delStmt->bind_param('s', $email);
                if (!$delStmt->execute()) {
                    error_log('[ResetPass] Không xoá được token reset cho email: ' . $email);
                }
                $delStmt->close();
            }

            // Vô hiệu hoá phiên đăng nhập hiện tại của trình duyệt này (nếu là chính chủ đang đăng nhập),
            // buộc đăng nhập lại bằng mật khẩu mới. (Đăng xuất toàn bộ thiết bị khác cần cơ chế session-store riêng.)
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['user_name'], $_SESSION['user_phone'], $_SESSION['phone']);
            }

            jOut(['success' => true, 'message' => 'Đặt lại mật khẩu thành công! Đang chuyển hướng về trang đăng nhập...']);
        }
    }
    jOut(['success' => false, 'message' => 'Không thể cập nhật mật khẩu lúc này. Vui lòng thử lại sau.']);
}
?>

<style>
.reset-card {
    border: 1px solid rgba(11, 75, 40, 0.12);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.reset-card:hover {
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.12);
}
.reset-header-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, var(--theme-primary, #0c4c29) 0%, #166534 100%);
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 20px auto;
    box-shadow: 0 8px 16px rgba(12, 76, 41, 0.2);
}
.form-control:focus {
    border-color: var(--theme-primary, #0c4c29);
    box-shadow: 0 0 0 4px rgba(12, 76, 41, 0.15);
}
.input-group-text {
    background-color: #f8fafc;
    border-color: #dee2e6;
    color: #64748b;
}
.btn-submit-reset {
    background: linear-gradient(135deg, var(--theme-primary, #0c4c29) 0%, #166534 100%);
    border: none;
    color: #ffffff;
    font-weight: 700;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 8px 16px rgba(12, 76, 41, 0.2);
}
.btn-submit-reset:hover:not(:disabled) {
    background: linear-gradient(135deg, #166534 0%, #14532d 100%);
    transform: translateY(-1px);
    box-shadow: 0 12px 20px rgba(12, 76, 41, 0.3);
}
.btn-submit-reset:active:not(:disabled) {
    transform: translateY(1px);
}

/* ===== Trạng thái liên kết không hợp lệ ===== */
.reset-invalid {
    padding: 8px 4px 4px;
}
.reset-invalid-icon {
    position: relative;
    width: 72px;
    height: 72px;
    margin: 0 auto 18px auto;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
    box-shadow: 0 8px 18px rgba(185, 28, 28, 0.12);
}
.reset-invalid-slash {
    position: absolute;
    width: 56px;
    height: 3px;
    border-radius: 3px;
    background: #b91c1c;
    transform: rotate(-45deg);
    opacity: 0.9;
}
.reset-invalid-hint {
    display: flex;
    align-items: flex-start;
    text-align: left;
    gap: 2px;
    background: rgba(12, 76, 41, 0.05);
    border: 1px solid rgba(12, 76, 41, 0.12);
    color: #475569;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 0.82rem;
    line-height: 1.5;
}
.reset-invalid-hint i {
    color: var(--theme-primary, #0c4c29);
    margin-top: 2px;
}
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 reset-card p-4">
                <?php if ($isValid): ?>
                <div class="text-center pt-3 pb-4">
                    <div class="reset-header-icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Đặt lại mật khẩu</h3>
                    <p class="text-muted small">Thiết lập mật khẩu mới và an toàn cho tài khoản của bạn</p>
                </div>
                <?php else: ?>
                <div class="pt-2"></div>
                <?php endif; ?>

                <div class="card-body p-0">
                    <div id="alertContainer"></div>

                    <?php if ($isValid): ?>
                        <form id="resetPasswordForm">
                            <input type="hidden" name="email" value="<?= h($email) ?>">
                            <input type="hidden" name="token" value="<?= h($token) ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-secondary">Mật khẩu mới</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" id="newPass" class="form-control py-2" placeholder="Tối thiểu 6 ký tự" required autocomplete="new-password">
                                    <span class="input-group-text" style="cursor:pointer" onclick="togglePassField('newPass')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-secondary">Xác nhận mật khẩu mới</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                    <input type="password" name="confirm_password" id="confirmPass" class="form-control py-2" placeholder="Nhập lại mật khẩu mới" required autocomplete="new-password">
                                    <span class="input-group-text" style="cursor:pointer" onclick="togglePassField('confirmPass')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-submit-reset w-100 py-2.5 rounded-3">
                                XÁC NHẬN ĐỔI MẬT KHẨU
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="reset-invalid text-center">
                            <div class="reset-invalid-icon">
                                <i class="bi bi-link-45deg"></i>
                                <span class="reset-invalid-slash"></span>
                            </div>
                            <h5 class="fw-bold text-dark mb-2">Liên kết không khả dụng</h5>
                            <p class="text-muted small mb-4 px-2"><?= h($errorMsg) ?></p>

                            <div class="reset-invalid-hint">
                                <i class="bi bi-info-circle me-2"></i>
                                <span>Mỗi liên kết chỉ dùng được một lần và có thời hạn. Hãy gửi lại yêu cầu để nhận liên kết mới.</span>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="button" id="resendResetBtn" class="btn btn-submit-reset py-2 rounded-3" data-email="<?= h($email) ?>" <?= $email === '' ? 'disabled' : '' ?>>
                                    <i class="bi bi-arrow-repeat me-1"></i> Gửi lại yêu cầu
                                </button>
                                <a href="<?= h($baseUrl) ?>/page_login" class="btn btn-link text-decoration-none fw-semibold" style="color: var(--theme-primary, #0c4c29);">
                                    Quay lại Đăng nhập
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassField(id) {
    const input = document.getElementById(id);
    const icon = event.currentTarget.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

$(function() {
    const $form = $('#resetPasswordForm');
    const $alert = $('#alertContainer');

    function showAlert(type, message) {
        $alert.html(`
            <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm mb-3 rounded-3" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2 fs-5"></i>
                    <div class="small fw-semibold">${message}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
    }

    // Phân tích phản hồi JSON. Thử parse trực tiếp; nếu có rác bao quanh thì rút phần {...}.
    function parseResp(raw) {
        if (raw && typeof raw === 'object') return raw; // jQuery đã parse sẵn
        try { return JSON.parse(raw); } catch (e) {}
        const start = String(raw).indexOf('{'), end = String(raw).lastIndexOf('}');
        if (start !== -1 && end !== -1) {
            try { return JSON.parse(String(raw).substring(start, end + 1)); } catch (e) {}
        }
        return { success: false, message: 'Dữ liệu không hợp lệ' };
    }

    // Gửi lại liên kết khôi phục (khi link không hợp lệ / hết hạn) — tối đa 5 lần/ngày ở backend
    const $resendBtn = $('#resendResetBtn');
    $resendBtn.on('click', function() {
        const email = $resendBtn.data('email') || '';
        if (!email) { showAlert('danger', 'Thiếu địa chỉ email để gửi lại liên kết.'); return; }

        $alert.empty();
        const original = $resendBtn.html();
        $resendBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>ĐANG GỬI...');

        $.post(window.location.href, { action: 'resend_reset', email: email }, function(raw) {
            const resp = parseResp(raw);
            showAlert(resp.success ? 'success' : 'danger', resp.message);
            if (resp.success) {
                // Đã gửi xong: giữ nút khóa để tránh spam, đổi nhãn
                $resendBtn.html('<i class="bi bi-check-circle me-1"></i> Đã gửi lại liên kết');
            } else {
                $resendBtn.prop('disabled', false).html(original);
            }
        }, 'text').fail(function() {
            showAlert('danger', 'Lỗi kết nối máy chủ.');
            $resendBtn.prop('disabled', false).html(original);
        });
    });

    $form.on('submit', function(e) {
        e.preventDefault();
        $alert.empty();

        const pass = $('#newPass').val();
        const confirm = $('#confirmPass').val();

        if (pass.length < 6) {
            showAlert('danger', 'Mật khẩu mới phải có tối thiểu 6 ký tự.');
            return;
        }

        if (pass !== confirm) {
            showAlert('danger', 'Mật khẩu xác nhận không trùng khớp.');
            return;
        }

        const $btn = $form.find('button[type="submit"]');
        const $inputs = $form.find('input');

        // Khóa form tại frontend chống spam bấm liên tục
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>ĐANG XỬ LÝ...');
        $inputs.prop('disabled', true);

        $.post(window.location.href, {
            action: 'update_password',
            password: pass,
            confirm_password: confirm
        }, function(raw) {
            const resp = parseResp(raw);
            if (resp.success) {
                showAlert('success', resp.message);
                setTimeout(function() {
                    window.location.href = '<?= h($baseUrl) ?>/page_login';
                }, 2000);
            } else {
                showAlert('danger', resp.message);
                // Mở khóa lại form khi có lỗi từ server
                $btn.prop('disabled', false).text('XÁC NHẬN ĐỔI MẬT KHẨU');
                $inputs.prop('disabled', false);
            }
        }, 'text').fail(function() {
            showAlert('danger', 'Lỗi kết nối máy chủ.');
            $btn.prop('disabled', false).text('XÁC NHẬN ĐỔI MẬT KHẨU');
            $inputs.prop('disabled', false);
        });
    });
});
</script>
