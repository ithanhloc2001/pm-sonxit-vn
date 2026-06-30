<?php
require_once __DIR__ . '/../config.php';
// Tải thêm các hàm hệ thống và kết nối database ($db)
if (file_exists(__DIR__ . '/../functions.php')) {
    require_once __DIR__ . '/../functions.php';
}

$rootUrl = rtrim((string)$baseUrl, '/');
$assetUrl = $rootUrl . '/assets';

$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (($_POST['is_ajax'] ?? '') === '1')
);

// Xử lý logic đăng nhập/đăng ký thông qua main/login.php (Proxy)
if ($isAjax && isset($_POST['action'])) {
    if (!defined('APP_EMBED_LAYOUT')) { define('APP_EMBED_LAYOUT', true); }
    // Đảm bảo các biến cấu hình có sẵn cho main/login.php
    include __DIR__ . '/../main/login.php';
    exit;
}

// Chuyển hướng nếu đã đăng nhập
if ((int)($_SESSION['user_id'] ?? 0) > 0) {
    header('Location: ' . ($baseUrl ?: '/'));
    exit;
}

// Cấu hình Social Login & reCAPTCHA
$googleLoginConfig = $GOOGLE_LOGIN ?? [];
$googleClientId = trim((string)($googleLoginConfig['client_id'] ?? ''));
$googleLoginEnabled = !empty($googleLoginConfig['enabled']) && $googleClientId !== '' && stripos($googleClientId, 'YOUR_GOOGLE_CLIENT_ID') === false;

$zaloLoginConfig = $ZALO_LOGIN ?? [];
$zaloLoginAuthUrl = trim((string)($zaloLoginConfig['auth_url'] ?? ''));
$zaloLoginEnabled = !empty($zaloLoginConfig['enabled']) && $zaloLoginAuthUrl !== '';

$authEndpoint = '/login/';
$otpEndpoint = '/core/zns/otp.php';
$recaptchaSiteKey = trim((string)($GOOGLE_RECAPTCHA['site_key'] ?? ''));

$zaloError = isset($_GET['zalo_error']) ? trim((string)$_GET['zalo_error']) : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Đăng nhập | Paint & More</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Core CSS -->
    <link href="/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/style.css">
    <script src="https://www.google.com/recaptcha/api.js?render=<?= h($recaptchaSiteKey) ?>" async defer></script>
    <style>
        :root {
            --pm-primary: #0c4c29;
            --pm-primary-rgb: 12, 76, 41;
            --pm-secondary: #facc15;
            --pm-bg: #ffffff;
        }
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--pm-bg);
            margin: 0;
            overflow-x: hidden;
        }

        .auth-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Hero Side */
        .auth-hero {
            flex: 1.2;
            position: relative;
            background: #0c4c29;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px;
            color: white;
            overflow: hidden;
        }

        .auth-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: url('https://paintandmore.vn/wp-content/uploads/2026/04/son-xit-mau-rust-oleum-3.jpg') center/cover no-repeat;
            opacity: 0.3;
            filter: grayscale(20%) brightness(0.6);
            z-index: 0;
        }

        .hero-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            /*background: var(--theme-primary);*/
            z-index: 1;
        }

        .hero-blob {
            position: absolute;
            width: 500px; height: 500px;
            background: rgba(250, 204, 21, 0.08);
            filter: blur(80px);
            border-radius: 50%;
            z-index: 2;
        }
        .hero-blob:nth-child(2) {
            width: 300px; height: 300px;
            background: rgba(255, 255, 255, 0.05);
            animation-duration: 25s;
            animation-delay: -5s;
            right: -100px; top: -100px;
        }

        .auth-hero-content { position: relative; z-index: 10; width: 100%; max-width: 650px; }

        .hero-logo {
            height: 100%;
            margin-bottom: 0px;
            /* background: #ffffff; */
            /* padding: 3px; */
            border-radius: 30px;
            object-fit: cover;
            filter: brightness(3.5);
            /* border: solid; */
        }
        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -2px;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 48px;
            font-weight: 300;
        }

        .hero-features {
            display: flex;
            gap: 40px;
        }

        .feature-item i {
            font-size: 2rem;
            color: var(--pm-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feature-item span {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .feature-item span {
            font-weight: 600;
            font-size: 0.9rem;
        }
        /* Form Side */
        .auth-form-side {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            position: relative;
        }

        .auth-form-container {
            width: 100%;
            /*max-width: 480px;*/
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .form-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 32px;
            gap: 32px;
        }

        .auth-tab {
            padding: 12px 0;
            font-weight: 700;
            font-size: 1rem;
            color: #94a3b8;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .auth-tab.active {
            color: var(--pm-primary);
        }

        .auth-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--pm-primary);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 10px;
        }

        .input-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s;
            margin-bottom: 20px;
        }

        .input-group:focus-within {
            border-color: var(--pm-primary);
            background: white;
            box-shadow: 0 10px 20px rgba(var(--pm-primary-rgb), 0.08);
            transform: translateY(-2px);
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: #242425ff;
            padding-left: 20px;
        }

        .form-control {
            background: transparent !important;
            border: none !important;
            padding: 14px 20px;
            font-size: 1rem;
            color: #00000047;
            font-weight: 500;
            box-shadow: none !important;
        }

        .btn-primary-pm {
            background: var(--pm-primary);
            color: white !important;
            border: none;
            border-radius: 12px;
            padding: 6px;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            margin-top: 12px;
            transition: all 0.3s;
        }

        .btn-primary-pm:hover {
            background: #083a1f;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(var(--pm-primary-rgb), 0.2);
        }

        .social-divider {
            display: flex;
            align-items: center;
            margin: 32px 0;
            color: #cbd5e1;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .social-divider::before, .social-divider::after { content: ""; flex: 1; height: 1px; background: #f1f5f9; }
        .social-divider span { padding: 0 20px; }

        .btn-social-outline {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: white;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 12px;
            color: #334155;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none !important;
        }

        .btn-social-outline:hover { 
            background: #f8fafc; 
            border-color: #cbd5e1; 
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.05);
        }

        #googleBtnContainer { width: 100%; display: flex; justify-content: center; min-height: 44px; margin-top: 12px; }

        @media (max-width: 1199px) {
            .auth-hero { padding: 40px; }
            .hero-title { font-size: 3rem; }
        }

        @media (max-width: 991px) {
            .auth-hero { display: none; }
            .auth-form-side { flex: 1; padding: 40px 20px; }
            .auth-form-container { max-width: 100%; }
        }

        .mobile-logo {
            display: none;
            justify-content: center;
            margin-bottom: 30px;
        }
        @media (max-width: 991px) {
            .mobile-logo { display: flex; }
            .form-header {
    margin-bottom: 40px;
    text-align: center;
}
        }
        .mobile-logo img {
    height: 130px;
    margin-top: -100px;
    margin-bottom: -30px;
}

        /* Ẩn Sidebars */
        .fb-sidebar-left, .fb-sidebar-right, .fb-layout, .main-container-ad-wrap, .sidebar-overlay {
            display: none !important;
        }
        /* OTP Input Styling */
        .otp-input-group { display: flex; gap: 10px; justify-content: center; margin-bottom: 30px; }
        .otp-input {
            width: 52px; height: 64px; text-align: center; font-size: 1.75rem; font-weight: 800;
            border: 2px solid #e2e8f0; border-radius: 16px; background: #ffffff; transition: all 0.3s;
            color: var(--pm-primary);
        }
        .otp-input:focus {
            border-color: var(--pm-primary);
            box-shadow: 0 0 0 5px rgba(var(--pm-primary-rgb), 0.15); outline: none;
            transform: translateY(-2px);
        }
        
        .otp-header-banner {
            height: 120px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .otp-floating-icon {
            width: 80px; height: 80px; background: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: absolute; bottom: -40px; left: 50%; transform: translateX(-50%);
            border: 5px solid white;
        }
    </style>
    <script>
        window.authAjaxEndpoint = <?= json_encode($authEndpoint) ?>;
        window.otpEndpoint = <?= json_encode($otpEndpoint) ?>;
        window.recaptchaSiteKey = <?= json_encode($recaptchaSiteKey) ?>;
    </script>
</head>
<body>

<div class="auth-wrapper">
    <!-- Hero Section (Left) -->
    <div class="auth-hero d-none d-lg-flex">
        <div class="hero-overlay"></div>
        <div class="hero-blob"></div>
        <div class="hero-blob"></div>
        <div class="auth-hero-content">
            <div class="brand-badge">
                <img src="<?= $baseUrl ?>/image/paintmore.png" alt="Logo" class="hero-logo">
            </div>
            <h1 class="hero-title">Paint & More<br>
                <p style="font-size:2.5rem" class="text-warning">Cửa hàng Sơn Mỹ</p>
                <p style="font-size:1.5rem" class="text-warning">Kiến tạo không gian sống</p>
            </h1>
            <p class="hero-subtitle">Khám phá bộ sưu tập sơn cao cấp từ Paint & More. Chất lượng vượt trội, màu sắc bền bỉ theo thời gian.</p>
            
            <div class="hero-features d-none">
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <span>100% Chính hãng</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-truck"></i>
                    <span>Giao hàng nhanh</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-headset"></i>
                    <span>Hỗ trợ 24/7</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Section (Right) -->
    <div class="auth-form-side">
        <div class="auth-form-container">
            <!-- Mobile Logo -->
            <div class="mobile-logo">
                <img src="/image/paintmore.png" alt="Logo">
            </div>

            <div class="form-header">
                <h2 class="form-title" id="authTitle">Chào mừng bạn!</h2>
                <p class="form-subtitle" id="authSubtitle">Đăng nhập để tiếp tục trải nghiệm cùng chúng tôi.</p>
            </div>

            <div class="auth-tabs">
                <div class="auth-tab active" data-mode="login">Đăng nhập</div>
                <div class="auth-tab" data-mode="register">Đăng ký</div>
            </div>

            <div class="auth-body">
                <!-- Login -->
                <div id="loginSection">
                    <div class="auth-alert-container mb-3"></div>
                    <form id="loginForm">
                        <div class="form-group">
                            <label class="form-label">Tài khoản</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input name="username" type="text" class="form-control" placeholder="Tên đăng nhập / Email" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="d-flex justify-content-between mb-1">
                                <label class="form-label mb-0">Mật khẩu</label>
                                <a href="javascript:void(0)" id="btnForgotPass" class="small text-decoration-none fw-bold" style="color: var(--pm-primary)">Quên mật khẩu?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input name="password" id="loginPass" type="password" class="form-control" placeholder="••••••••" required />
                                <span class="input-group-text pe-3" style="cursor:pointer" onclick="togglePass('loginPass')">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <label id="zaloLoginBtn" class="fw-bold text-danger small x_btn-social-outline">Đăng nhập qua SĐT/Email</label>
                        <hr>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label small text-muted" for="rememberMe">Ghi nhớ đăng nhập</label>
                        </div>
                        <button type="submit" class="btn-primary-pm">ĐĂNG NHẬP NGAY</button>
                    </form>
                </div>

                <!-- Register -->
                <div id="registerSection" style="display:none;">
                    <div class="auth-alert-container mb-3"></div>
                    <form id="registerForm">
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-at"></i></span>
                                <input name="reg_username" type="text" class="form-control" placeholder="viết liền, không dấu" required />
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input name="reg_email" type="email" class="form-control" placeholder="user@gmail.com" required />
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Mật khẩu mới</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                <input name="reg_password" id="regPass" type="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required />
                                <span class="input-group-text pe-3" style="cursor:pointer" onclick="togglePass('regPass')">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                                               <button type="submit" class="btn-primary-pm">TẠO TÀI KHOẢN</button>
                    </form>
                </div>

                <!-- Forgot Password -->
                <div id="forgotSection" style="display:none;">
                    <div class="auth-alert-container mb-3"></div>
                    <form id="forgotForm">
                        <div class="mb-4">
                            <label class="form-label">Email tài khoản</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input name="forgot_email" type="email" class="form-control" placeholder="user@gmail.com" required />
                            </div>
                        </div>
                        <button type="submit" class="btn-primary-pm">GỬI LIÊN KẾT KHÔI PHỤC</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="javascript:void(0)" id="btnBackToLogin" class="small text-decoration-none fw-bold" style="color: var(--pm-primary)">Quay lại Đăng nhập</a>
                    </div>
                </div>
                <div class="social-divider"><span>HOẶC TIẾP TỤC VỚI</span></div>
                <div class="social-btns-container">
                    <?php if ($zaloLoginEnabled): ?>
                    <a href="<?= h($zaloLoginAuthUrl) ?>" class="btn-social-outline">
                        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0OCA0OCI+PHBhdGggZmlsbD0iIzAwNjhGRiIgZD0iTTI0IDRDMTIuOTUgNCA0IDEwLjI3IDQgMThjMCAzLjM5IDEuNTggNi40NSA0LjE3IDguODZMNyA0MWw5LjI0LTQuNDVDMTguNTkgMzcuNTcgMjEuMjMgMzggMjQgMzhjMTEuMDUgMCAyMC02LjI3IDIwLTE0UzM1LjA1IDQgMjQgNHoiLz48cGF0aCBmaWxsPSIjRkZGRkZGIiBkPSJNMzAuNSAyNmgtOGwtMS41LTVoOGwxLjUgNXptLTIuNS04aC02bC0xLjUgNWg2bDEuNS01eiIvPjwvc3ZnPg==" width="22" alt="Zalo">
                        <span>Zalo OAuth</span>
                    </a>
                    <?php endif; ?>

                    <?php if ($googleLoginEnabled): ?>
                    <div id="googleBtnContainer"></div>
                    <?php endif; ?>
                </div>

                <div class="mt-5 text-center text-muted small">
                    © 2026 Paint & More. Toàn quyền bảo lưu.<br>
                    <a href="#" class="text-decoration-none text-muted mx-2">Điều khoản</a> | 
                    <a href="#" class="text-decoration-none text-muted mx-2">Bảo mật</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal OTP -->
<div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">        
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="otp-header-banner">
                <button type="button" class="btn-close btn-close-black position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="otp-floating-icon">
                    <i class="bi bi-shield-lock-fill text-primary fs-1"></i>
                </div>
            </div>
            <div class="modal-body p-4 p-md-5 text-center mt-4">
                <h3 class="fw-bold mb-2">Xác thực OTP</h3>
                <p class="text-muted mb-4 small">Mã xác thực sẽ được gửi qua Zalo hoặc Email</p>
                <div class="auth-alert-container mb-3"></div>
                
                <!-- Step 0: Chọn phương thức nhận mã (hiển thị đầu tiên) -->
                <div id="otpStepChoose">
                    <label class="small text-muted d-block mb-3">Chọn phương thức nhận mã xác thực:</label>
                    <div class="d-flex flex-column gap-2" id="otpChooseChannels">
                        <button type="button" class="btn btn-outline-secondary py-3 rounded-pill" data-channel="zalo">
                            <i class="bi bi-phone me-2"></i> Nhận mã OTP qua Zalo
                        </button>
                        <button type="button" class="btn btn-outline-secondary py-3 rounded-pill" data-channel="email">
                            <i class="bi bi-envelope me-2"></i> Nhận mã qua Email
                        </button>
                    </div>
                </div>

                <!-- Step 1: Input Phone/Email -->
                <div id="otpStepPhone" class="d-none">
                    <div class="input-group mb-4 py-1 px-2 border-2" style="border-radius: 16px;">
                        <span class="input-group-text" id="otpInputIcon"><i class="bi bi-phone"></i></span>
                        <input id="otpPhone" type="text" class="form-control fw-bold" placeholder="Số điện thoại hoặc Email" style="font-size: 1.1rem;">
                    </div>

                    <button id="btnOtpSend" class="btn-primary-pm py-3 rounded-pill">
                        GỬI MÃ XÁC THỰC <i class="bi bi-arrow-right ms-2"></i>
                    </button>

                    <div class="mt-3">
                        <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted p-0" id="btnOtpPhoneBack">
                            <i class="bi bi-arrow-left"></i> Chọn phương thức khác
                        </button>
                    </div>
                </div>

                <!-- Step 2: Input Code -->
                <div id="otpStepVerify" class="d-none">
                    <!-- Cụm nhập mã của phiên hiện tại (sẽ ẩn khi mở chọn phương thức khác) -->
                    <div id="otpVerifyActive">
                        <div class="d-flex align-items-center justify-content-center mb-4 bg-light p-2 rounded-pill">
                            <span class="small text-muted me-2">Gửi đến:</span>
                            <span id="displayPhone" class="fw-bold small me-3"></span>
                            <a href="javascript:void(0)" id="btnOtpChangePhone" class="badge bg-white text-primary border text-decoration-none">Đổi số</a>
                        </div>

                        <div class="otp-input-group">
                            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                        </div>

                        <button id="btnOtpVerify" class="btn-primary-pm py-3 rounded-pill mb-4">
                            XÁC NHẬN MÃ <i class="bi bi-check-circle ms-2"></i>
                        </button>

                        <div class="d-flex justify-content-between align-items-center px-2">
                            <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted p-0" id="btnOtpOtherMethod">Không nhận được mã?</button>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none fw-bold p-0" id="btnOtpResend">
                                Gửi lại ngay
                            </button>
                        </div>
                    </div>

                    <!-- Chọn phương thức nhận mã khác (chọn 1 trong 2, loại trừ nhau) -->
                    <div id="otpAltChannelBox" class="d-none text-start">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="small text-muted mb-0">Chọn phương thức nhận mã:</label>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted p-0" id="btnOtpAltBack">
                                <i class="bi bi-arrow-left"></i> Quay lại
                            </button>
                        </div>
                        <div class="d-flex gap-2 mb-2" id="otpChannelChoices">
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill rounded-pill" data-channel="zalo">
                                <i class="bi bi-phone me-1"></i> OTP qua Zalo
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill rounded-pill" data-channel="email">
                                <i class="bi bi-envelope me-1"></i> Email
                            </button>
                        </div>
                        <div class="input-group mb-2 py-1 px-2 border-2" style="border-radius: 14px;">
                            <span class="input-group-text" id="otpAltIcon"><i class="bi bi-phone"></i></span>
                            <input id="otpAltInput" type="text" class="form-control" placeholder="Số điện thoại" style="font-size: 1rem;">
                        </div>
                        <button type="button" class="btn-primary-pm py-2 rounded-pill" id="btnOtpSendAlt">
                            GỬI MÃ <i class="bi bi-send ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="/assets/js/jquery-3.7.1.min.js"></script>
<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>

<?php if ($googleLoginEnabled): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>

<script>
    function parseJsonResponse(data) {
        if (typeof data === 'object') return data;
        try {
            const start = data.indexOf('{');
            const end = data.lastIndexOf('}');
            if (start !== -1 && end !== -1) return JSON.parse(data.substring(start, end + 1));
        } catch (e) { console.error('JSON Error:', e, data); }
        return { success: false, message: 'Dữ liệu không hợp lệ' };
    }

    function togglePass(id) {
        const input = document.getElementById(id);
        const icon = event.currentTarget.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text'; icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password'; icon.className = 'bi bi-eye';
        }
    }

    $(function(){
        const authEndpoint = window.authAjaxEndpoint;
        const otpEndpoint = window.otpEndpoint;
        const siteKey = window.recaptchaSiteKey;

        function showAlert(container, type, message) {
            const $container = $(container).find('.auth-alert-container').first();
            if (!$container.length) return;
            $container.html(`
                <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm mb-0 rounded-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2 fs-5"></i>
                        <div class="small fw-semibold text-start">${message}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 1.1rem;"></button>
                </div>
            `).hide().fadeIn();
        }

        function clearAlert(container) {
            $(container).find('.auth-alert-container').empty();
        }

        // Tab Switching
        $('.auth-tab').on('click', function(){
            const mode = $(this).data('mode');
            $('.auth-tab').removeClass('active');
            $(this).addClass('active');
            
            if(mode === 'login') {
                $('#loginSection').show();
                $('#registerSection').hide();
                $('#authTitle').text('Chào mừng bạn!');
                $('#authSubtitle').text('Đăng nhập để tiếp tục trải nghiệm cùng chúng tôi.');
            } else {
                $('#loginSection').hide();
                $('#registerSection').show();
                $('#authTitle').text('Tham gia ngay!');
                $('#authSubtitle').text('Tạo tài khoản để nhận nhiều ưu đãi từ Paint & More.');
            }
        });

        $('#loginForm').on('submit', function(e){
            e.preventDefault();
            const $form = $(this).closest('div');
            clearAlert($form);
            const $btn = $(this).find('button[type="submit"]');
            const username = $('[name="username"]').val();
            const password = $('[name="password"]').val();

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>XỬ LÝ...');

            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, {action: 'login'}).then(function(token) {
                        const data = { action: 'login', is_ajax: 1, username: username, password: password, 'g-recaptcha-response': token };
                        submitAuth(authEndpoint, data, $form, $btn, 'ĐĂNG NHẬP NGAY');
                    });
                });
            } else {
                const data = { action: 'login', is_ajax: 1, username: username, password: password };
                submitAuth(authEndpoint, data, $form, $btn, 'ĐĂNG NHẬP NGAY');
            }
        });

        function submitAuth(url, data, $form, $btn, originalText) {
            $.post(url, data, function(raw){
                const resp = parseJsonResponse(raw);
                if(resp.success){ 
                    showAlert($form, 'success', resp.message); 
                    setTimeout(() => location.href = resp.redirect || '/', 800); 
                } else { 
                    showAlert($form, 'danger', resp.message); 
                    $btn.prop('disabled', false).text(originalText); 
                }
            }, 'text').fail(() => { 
                showAlert($form, 'danger', 'Lỗi kết nối server'); 
                $btn.prop('disabled', false).text(originalText); 
            });
        }

        $('#registerForm').on('submit', function(e){
            e.preventDefault();
            const $form = $(this).closest('div');
            clearAlert($form);
            const $btn = $(this).find('button[type="submit"]');
            const formData = $(this).serialize();
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>XỬ LÝ...');

            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, {action: 'register'}).then(function(token) {
                        const data = formData + '&action=register&is_ajax=1&g-recaptcha-response=' + token;
                        submitAuth(authEndpoint, data, $form, $btn, 'TẠO TÀI KHOẢN');
                    });
                });
            } else {
                const data = formData + '&action=register&is_ajax=1';
                submitAuth(authEndpoint, data, $form, $btn, 'TẠO TÀI KHOẢN');
            }
        });

        // OTP Handler
        let otpMode = 'login';
        let resendTimer = null;
        const resendDefaultHtml = $('#btnOtpResend').html();
        function openOtpModal(mode = 'login') {
            otpMode = mode;
            const modal = new bootstrap.Modal('#otpModal');
            if (mode === 'forgot') {
                $('#otpModal h3').text('Khôi phục mật khẩu');
                $('#otpModal p').text('Chọn phương thức nhận mã khôi phục.');
            } else {
                $('#otpModal h3').text('Xác thực OTP');
                $('#otpModal p').text('Chọn phương thức nhận mã xác thực.');
            }
            // Luôn bắt đầu ở bước chọn phương thức
            clearAlert('#otpModal .modal-body');
            $('#otpStepVerify').addClass('d-none');
            $('#otpStepPhone').addClass('d-none');
            $('#otpAltChannelBox').addClass('d-none');
            $('#otpVerifyActive').removeClass('d-none');
            $('#otpPhone').val('');
            $('.otp-input').val('');
            // Mở lại nút gửi mã & huỷ mọi đếm ngược còn sót từ lần trước
            if (typeof releaseSendBtn === 'function') releaseSendBtn();
            if (resendTimer) { clearInterval(resendTimer); resendTimer = null; }
            $('#btnOtpResend').prop('disabled', false).html(resendDefaultHtml);
            $('#otpStepChoose').removeClass('d-none');
            modal.show();
        }

        // Đặt ô nhập SĐT/Email theo kênh đã chọn rồi sang bước nhập
        function gotoPhoneStep(channel) {
            if (channel === 'email') {
                $('#otpInputIcon').html('<i class="bi bi-envelope"></i>');
                $('#otpPhone').attr('placeholder', 'Email').val('');
            } else {
                $('#otpInputIcon').html('<i class="bi bi-phone"></i>');
                $('#otpPhone').attr('placeholder', 'Số điện thoại').val('');
            }
            $('#otpStepChoose').addClass('d-none');
            $('#otpStepPhone').removeClass('d-none');
            $('#otpPhone').focus();
        }

        // Step 0: chọn phương thức nhận mã ban đầu
        $('#otpChooseChannels button').on('click', function() {
            gotoPhoneStep($(this).data('channel'));
        });

        // Quay lại bước chọn phương thức từ bước nhập SĐT/Email
        $('#btnOtpPhoneBack').on('click', function() {
            clearAlert('#otpModal .modal-body');
            $('#otpStepPhone').addClass('d-none');
            $('#otpStepChoose').removeClass('d-none');
        });

        $('#zaloLoginBtn').on('click', () => openOtpModal('login'));
        
        $('#btnForgotPass').on('click', function() {
            clearAlert('#forgotSection');
            $('#loginSection').hide();
            $('#registerSection').hide();
            $('.auth-tabs').hide();
            $('.social-divider').hide();
            $('.social-btns-container').hide();
            $('#forgotSection').show();
            $('#authTitle').text('Khôi phục mật khẩu');
            $('#authSubtitle').text('Nhập email của bạn để nhận liên kết khôi phục mật khẩu.');
        });

        $('#btnBackToLogin').on('click', function() {
            $('#forgotSection').hide();
            $('#loginSection').show();
            $('.auth-tabs').show();
            $('.social-divider').show();
            $('.social-btns-container').show();
            $('.auth-tab').removeClass('active');
            $('.auth-tab[data-mode="login"]').addClass('active');
            $('#authTitle').text('Chào mừng bạn!');
            $('#authSubtitle').text('Đăng nhập để tiếp tục trải nghiệm cùng chúng tôi.');
        });

        $('#forgotForm').on('submit', function(e) {
            e.preventDefault();
            const $form = $('#forgotSection');
            clearAlert($form);
            const $btn = $(this).find('button[type="submit"]');
            const forgotEmail = $('[name="forgot_email"]').val();

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>XỬ LÝ...');

            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, {action: 'forgot_password'}).then(function(token) {
                        const data = { action: 'forgot-password', is_ajax: 1, forgot_email: forgotEmail, 'g-recaptcha-response': token };
                        submitForgot($btn, data, $form);
                    });
                });
            } else {
                const data = { action: 'forgot-password', is_ajax: 1, forgot_email: forgotEmail };
                submitForgot($btn, data, $form);
            }
        });

        function submitForgot($btn, data, $form) {
            $.post(authEndpoint, data, function(raw) {
                const resp = parseJsonResponse(raw);
                if (resp.success) {
                    showAlert($form, 'success', resp.message);
                    $('[name="forgot_email"]').val('');
                    // Giữ nút vô hiệu hóa trong 5 giây để tránh gửi dồn dập
                    setTimeout(() => $btn.prop('disabled', false).text('GỬI LIÊN KẾT KHÔI PHỤC'), 5000);
                } else {
                    showAlert($form, 'danger', resp.message);
                    $btn.prop('disabled', false).text('GỬI LIÊN KẾT KHÔI PHỤC');
                }
            }, 'text').fail(function() {
                showAlert($form, 'danger', 'Lỗi kết nối server');
                $btn.prop('disabled', false).text('GỬI LIÊN KẾT KHÔI PHỤC');
            });
        }

        // Thay đổi icon khi gõ email
        $('#otpPhone').on('input', function() {
            const val = $(this).val();
            if (val.indexOf('@') !== -1) {
                $('#otpInputIcon').html('<i class="bi bi-envelope"></i>');
            } else {
                $('#otpInputIcon').html('<i class="bi bi-phone"></i>');
            }
        });

        // Tạm khoá nút "Gửi mã xác thực" sau khi bấm để tránh spam (đếm ngược rồi mở lại)
        let sendCooldownTimer = null;
        const sendDefaultHtml = 'GỬI MÃ XÁC THỰC <i class="bi bi-arrow-right ms-2"></i>';
        function startSendCooldown(seconds) {
            const $btn = $('#btnOtpSend');
            let remaining = seconds;
            if (sendCooldownTimer) clearInterval(sendCooldownTimer);
            $btn.prop('disabled', true).text('Gửi lại sau ' + remaining + 's');
            sendCooldownTimer = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(sendCooldownTimer);
                    sendCooldownTimer = null;
                    $btn.prop('disabled', false).html(sendDefaultHtml);
                } else {
                    $btn.text('Gửi lại sau ' + remaining + 's');
                }
            }, 1000);
        }
        function releaseSendBtn() {
            if (sendCooldownTimer) { clearInterval(sendCooldownTimer); sendCooldownTimer = null; }
            $('#btnOtpSend').prop('disabled', false).html(sendDefaultHtml);
        }

        $('#btnOtpSend').on('click', function(){
            const $btn = $(this);
            // Đang bị khoá (đang xử lý / đếm ngược) thì bỏ qua để chống spam
            if ($btn.prop('disabled')) return;

            const $form = $('#otpModal .modal-body');
            clearAlert($form);
            const phone = $('#otpPhone').val();
            if(!phone) return showAlert($form, 'danger', 'Vui lòng nhập số điện thoại hoặc email');

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>ĐANG XỬ LÝ...');

            if (typeof grecaptcha === 'undefined') {
                showAlert($form, 'danger', 'Lỗi tải hệ thống bảo mật. Vui lòng tải lại trang.');
                releaseSendBtn();
                return;
            }

            grecaptcha.ready(function() {
                grecaptcha.execute(siteKey, {action: 'otp_send'}).then(function(token) {
                    $.post(otpEndpoint, {action: 'send', phone: phone, 'g-recaptcha-response': token}, function(raw){
                        const resp = parseJsonResponse(raw);
                        if(resp.success){
                            showAlert($form, 'success', resp.message);
                            $('#displayPhone').text(phone);
                            if (phone.indexOf('@') !== -1) {
                                $('#btnOtpChangePhone').text('Đổi Email');
                            } else {
                                $('#btnOtpChangePhone').text('Đổi số');
                            }
                            $('#otpStepPhone').addClass('d-none');
                            $('#otpStepVerify').removeClass('d-none');
                            setTimeout(() => $('.otp-input').first().focus(), 100);
                            // Khoá nút gửi trong lúc chờ; nếu user đổi số sẽ được mở lại
                            startSendCooldown(60);
                            startResendCountdown(60);
                        } else {
                            showAlert($form, 'danger', resp.message);
                            // Gửi thất bại: tạm khoá ngắn để tránh bấm dồn
                            startSendCooldown(15);
                        }
                    }, 'text').fail(function(xhr) {
                        const resp = parseJsonResponse(xhr.responseText);
                        showAlert($form, 'danger', resp.message || 'Lỗi gửi mã OTP');
                        startSendCooldown(15);
                    });
                });
            });
        });

        // Đổi số/Đổi Email → quay về bước chọn phương thức
        $('#btnOtpChangePhone').on('click', function(e) {
            e.preventDefault();
            clearAlert('#otpModal .modal-body');
            $('#otpStepVerify').addClass('d-none');
            $('#otpStepPhone').addClass('d-none');
            // Bật lại nút gửi mã và hủy đếm ngược của lần gửi trước
            releaseSendBtn();
            if (resendTimer) { clearInterval(resendTimer); resendTimer = null; }
            $('#btnOtpResend').prop('disabled', false).html(resendDefaultHtml);
            $('#otpAltChannelBox').addClass('d-none');
            $('#otpVerifyActive').removeClass('d-none');
            $('#otpStepChoose').removeClass('d-none');
        });

        // Đếm ngược cho nút "Gửi lại ngay" (khớp cooldown phía server)
        function startResendCountdown(seconds) {
            const $btn = $('#btnOtpResend');
            let remaining = seconds;
            if (resendTimer) clearInterval(resendTimer);
            $btn.prop('disabled', true).text('Gửi lại sau ' + remaining + 's');
            resendTimer = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(resendTimer);
                    resendTimer = null;
                    $btn.prop('disabled', false).html(resendDefaultHtml);
                } else {
                    $btn.text('Gửi lại sau ' + remaining + 's');
                }
            }, 1000);
        }

        // Gửi lại ngay click handler
        $('#btnOtpResend').on('click', function(e) {
            e.preventDefault();
            const $resendBtn = $(this);

            // Đang trong thời gian chờ thì bỏ qua
            if ($resendBtn.prop('disabled')) return;

            $resendBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Đang gửi...');

            const $form = $('#otpModal .modal-body');
            clearAlert($form);
            const phone = $('#otpPhone').val();
            if(!phone) {
                showAlert($form, 'danger', 'Vui lòng nhập số điện thoại hoặc email');
                $resendBtn.prop('disabled', false).html(resendDefaultHtml);
                return;
            }

            if (typeof grecaptcha === 'undefined') {
                showAlert($form, 'danger', 'Lỗi tải hệ thống bảo mật. Vui lòng tải lại trang.');
                $resendBtn.prop('disabled', false).html(resendDefaultHtml);
                return;
            }

            grecaptcha.ready(function() {
                grecaptcha.execute(siteKey, {action: 'otp_send'}).then(function(token) {
                    $.post(otpEndpoint, {action: 'send', phone: phone, 'g-recaptcha-response': token}, function(raw){
                        const resp = parseJsonResponse(raw);
                        if(resp.success){
                            showAlert($form, 'success', resp.message);
                            $('#displayPhone').text(phone);
                            if (phone.indexOf('@') !== -1) {
                                $('#btnOtpChangePhone').text('Đổi Email');
                            } else {
                                $('#btnOtpChangePhone').text('Đổi số');
                            }
                            $('.otp-input').val('');
                            setTimeout(() => $('.otp-input').first().focus(), 100);
                            startResendCountdown(60);
                        } else {
                            showAlert($form, 'danger', resp.message);
                            $resendBtn.prop('disabled', false).html(resendDefaultHtml);
                        }
                    }, 'text').fail(function(xhr) {
                        const resp = parseJsonResponse(xhr.responseText);
                        showAlert($form, 'danger', resp.message || 'Lỗi gửi mã OTP');
                        $resendBtn.prop('disabled', false).html(resendDefaultHtml);
                    });
                });
            });
        });

        // "Không nhận được mã?" → ẩn cụm nhập mã hiện tại, chỉ hiện khung chọn phương thức
        $('#btnOtpOtherMethod').on('click', function(e) {
            e.preventDefault();
            // Mặc định chọn kênh khác với kênh đang dùng ở bước nhập SĐT/Email
            const mainIsEmail = $('#otpPhone').val().indexOf('@') !== -1;
            selectAltChannel(mainIsEmail ? 'zalo' : 'email');
            $('#otpVerifyActive').addClass('d-none');
            $('#otpAltChannelBox').removeClass('d-none');
        });

        // Quay lại cụm nhập mã (không gửi lại)
        $('#btnOtpAltBack').on('click', function() {
            $('#otpAltChannelBox').addClass('d-none');
            $('#otpVerifyActive').removeClass('d-none');
        });

        // Chọn kênh: 'zalo' (OTP qua SĐT) hoặc 'email' — loại trừ nhau
        function selectAltChannel(channel) {
            $('#otpChannelChoices button').each(function() {
                const active = $(this).data('channel') === channel;
                $(this).toggleClass('btn-secondary text-white', active)
                       .toggleClass('btn-outline-secondary', !active);
            });
            if (channel === 'email') {
                $('#otpAltIcon').html('<i class="bi bi-envelope"></i>');
                $('#otpAltInput').attr('placeholder', 'Email').val('').focus();
            } else {
                $('#otpAltIcon').html('<i class="bi bi-phone"></i>');
                $('#otpAltInput').attr('placeholder', 'Số điện thoại').val('').focus();
            }
        }

        $('#otpChannelChoices button').on('click', function() {
            selectAltChannel($(this).data('channel'));
        });

        // Gửi lại mã qua kênh đã chọn (đơn kênh)
        $('#btnOtpSendAlt').on('click', function() {
            const $form = $('#otpModal .modal-body');
            clearAlert($form);
            const target = $('#otpAltInput').val().trim();
            if (!target) return showAlert($form, 'danger', 'Vui lòng nhập thông tin nhận mã');

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> ĐANG GỬI...');

            if (typeof grecaptcha === 'undefined') {
                showAlert($form, 'danger', 'Lỗi tải hệ thống bảo mật. Vui lòng tải lại trang.');
                $btn.prop('disabled', false).html('GỬI MÃ <i class="bi bi-send ms-1"></i>');
                return;
            }

            grecaptcha.ready(function() {
                grecaptcha.execute(siteKey, {action: 'otp_send'}).then(function(token) {
                    $.post(otpEndpoint, {action: 'send', phone: target, 'g-recaptcha-response': token}, function(raw){
                        const resp = parseJsonResponse(raw);
                        if(resp.success){
                            showAlert($form, 'success', resp.message);
                            // Cập nhật đích đang gửi để verify khớp danh tính
                            $('#otpPhone').val(target);
                            $('#displayPhone').text(target);
                            $('#btnOtpChangePhone').text(target.indexOf('@') !== -1 ? 'Đổi Email' : 'Đổi số');
                            $('.otp-input').val('');
                            // Reset về form nhập mã mới (ẩn khung chọn, hiện lại cụm nhập)
                            $('#otpAltChannelBox').addClass('d-none');
                            $('#otpVerifyActive').removeClass('d-none');
                            setTimeout(() => $('.otp-input').first().focus(), 100);
                            startResendCountdown(60);
                        } else {
                            showAlert($form, 'danger', resp.message);
                        }
                    }, 'text').fail(function(xhr) {
                        const resp = parseJsonResponse(xhr.responseText);
                        showAlert($form, 'danger', resp.message || 'Lỗi gửi mã OTP');
                    }).always(function() {
                        $btn.prop('disabled', false).html('GỬI MÃ <i class="bi bi-send ms-1"></i>');
                    });
                });
            });
        });

        // Gom mã từ 6 ô; nếu đủ 6 số thì tự động xác nhận
        function collectOtp() {
            let otp = '';
            $('.otp-input').each(function() { otp += $(this).val(); });
            return otp;
        }

        function maybeAutoVerify() {
            const otp = collectOtp();
            // Đủ 6 chữ số và nút xác nhận đang bật -> tự bấm xác nhận
            if (/^\d{6}$/.test(otp) && !$('#btnOtpVerify').prop('disabled')) {
                $('#btnOtpVerify').trigger('click');
            }
        }

        // OTP Input Auto-focus & Collect
        $('.otp-input').on('input', function() {
            const $this = $(this);
            // Chỉ giữ 1 chữ số ở mỗi ô
            $this.val($this.val().replace(/\D/g, '').slice(0, 1));
            if ($this.val().length === 1) {
                $this.next('.otp-input').focus();
            }
            maybeAutoVerify();
        }).on('keydown', function(e) {
            if (e.key === 'Backspace' && !$(this).val()) {
                $(this).prev('.otp-input').focus();
            }
        }).on('paste', function(e) {
            // Dán nguyên mã 6 số: tự phân bổ vào từng ô rồi xác nhận
            const text = (e.originalEvent || e).clipboardData.getData('text') || '';
            const digits = text.replace(/\D/g, '').slice(0, 6);
            if (!digits) return;
            e.preventDefault();
            const $inputs = $('.otp-input');
            $inputs.each(function(i) { $(this).val(digits[i] || ''); });
            const lastIdx = Math.min(digits.length, $inputs.length) - 1;
            $inputs.eq(lastIdx < 0 ? 0 : lastIdx).focus();
            maybeAutoVerify();
        });

        // Xoá mã đã nhập và focus ô đầu (dùng khi mã sai để nhập lại)
        function resetOtpInputs() {
            $('.otp-input').val('');
            $('.otp-input').first().focus();
        }

        $('#btnOtpVerify').on('click', function(){
            const $form = $('#otpModal .modal-body');
            clearAlert($form);

            // Collect code from inputs
            const otp = collectOtp();

            if (otp.length < 6) return showAlert($form, 'danger', 'Vui lòng nhập đủ 6 chữ số');

            const $btn = $(this); $btn.prop('disabled', true).text('XÁC THỰC...');
            $.post(otpEndpoint, {action: 'verify', otp: otp}, function(raw){
                const resp = parseJsonResponse(raw);
                if(resp.success){
                    $.post(authEndpoint, {action: 'login_phone_otp', is_ajax: 1}, function(raw2){
                        const r2 = parseJsonResponse(raw2);
                        if(r2.success){ 
                            showAlert($form, 'success', r2.message); 
                            if (otpMode === 'forgot') setTimeout(() => location.href = '/account?tab=password', 800);
                            else setTimeout(() => location.href = r2.redirect || '/', 800);
                        } else {
                            showAlert($form, 'danger', r2.message);
                            $btn.prop('disabled', false).text('XÁC NHẬN MÃ');
                            resetOtpInputs();
                        }
                    }, 'text').fail(() => {
                        showAlert($form, 'danger', 'Lỗi đăng nhập hệ thống');
                        $btn.prop('disabled', false).text('XÁC NHẬN MÃ');
                        resetOtpInputs();
                    });
                } else {
                    showAlert($form, 'danger', resp.message);
                    $btn.prop('disabled', false).text('XÁC NHẬN MÃ');
                    resetOtpInputs();
                }
            }, 'text').fail(function(xhr) {
                const resp = parseJsonResponse(xhr.responseText);
                showAlert($form, 'danger', resp.message || 'Lỗi xác thực mã');
                $btn.prop('disabled', false).text('XÁC NHẬN MÃ');
                resetOtpInputs();
            });
        });

        // Google
        <?php if ($googleLoginEnabled): ?>
        function initGoogleLogin() {
            if (typeof google === 'undefined' || !google.accounts) return setTimeout(initGoogleLogin, 250);
            google.accounts.id.initialize({
                client_id: <?= json_encode($googleClientId) ?>,
                callback: (res) => {
                    $.post(authEndpoint, {action: 'google-login', credential: res.credential, is_ajax: 1}, function(raw){
                        const resp = parseJsonResponse(raw);
                        if(resp.success) location.href = resp.redirect || '/';
                        else showAlert('#loginSection', 'danger', resp.message);
                    }, 'text');
                }
            });
            google.accounts.id.renderButton(document.getElementById("googleBtnContainer"), { theme: "outline", size: "large", width: "100%", text: "continue_with", shape: "pill" });
        }
        $(document).ready(initGoogleLogin);
        <?php endif; ?>
    });
</script>

</body>
</html>