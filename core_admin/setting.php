<?php 
require_once __DIR__ . '/_admin_guard.php';
?>
<?php
// Mọi AJAX/POST action được xử lý tại /core_admin/ajax/setting.php (đã kiểm CSRF).
// File này chỉ render trang cấu hình; phần data-prep dùng app_get_config_value_by_path().

if (defined('AJAX_ONLY')) { return; }

// Legacy bridge: GET ?ajax=... và mọi POST có action -> chuyển sang endpoint AJAX mới.
if (isset($_GET['ajax'])) {
    require_once __DIR__ . '/ajax/setting.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/ajax/setting.php';
    exit;
}

$momoEnvRaw = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_env');
$momoEnvRaw = strtolower(trim((string)$momoEnvRaw));
$momoEnv = in_array($momoEnvRaw, ['test', 'testing', 'sandbox'], true) ? 'test' : 'production';

$bot_h = function($value){
    return h($value);
};

$bot_checked = function($value){
    $on = ($value === true || $value === 1 || $value === '1' || strtolower((string)$value) === 'true');
    return $on ? 'checked' : '';
};

$baseUrlLocal = rtrim((string)($baseUrl ?? ''), '/');
$vnpayReturnDefault = $baseUrlLocal !== '' ? $baseUrlLocal . '/core_admin/vnpay/vnpay_return.php' : '';
$vnpayIpnDefault = $baseUrlLocal !== '' ? $baseUrlLocal . '/core_admin/vnpay/vnpay_ipn.php' : '';

$AI_OPENAI_KEY = app_get_config_value_by_path('API_OPENAI_KEY');
$AI_GEMINI_KEY = app_get_config_value_by_path('API_GEMINI_KEY');
$AI_GMAPS_KEY  = app_get_config_value_by_path('GOOGLE_MAPS_API_KEY');
$TINYMCE_API_KEY = app_get_config_value_by_path('TINYMCE_API_KEY');

$LOGIN_ENABLED = app_get_config_value_by_path('GOOGLE_LOGIN.enabled');
$LOGIN_CLIENT_ID = app_get_config_value_by_path('GOOGLE_LOGIN.client_id');
$LOGIN_AUTO_REGISTER = app_get_config_value_by_path('GOOGLE_LOGIN.auto_register');
$LOGIN_REDIRECT_URI = app_get_config_value_by_path('GOOGLE_LOGIN.redirect_uri');
$LOGIN_SCOPE = app_get_config_value_by_path('GOOGLE_LOGIN.scope');

$ZALO_LOGIN_ENABLED = app_get_config_value_by_path('ZALO_LOGIN.enabled');
$ZALO_LOGIN_AUTH_URL = app_get_config_value_by_path('ZALO_LOGIN.auth_url');
$ZALO_LOGIN_APP_ID = app_get_config_value_by_path('ZALO_LOGIN.app_id');
$ZALO_LOGIN_SECRET = app_get_config_value_by_path('ZALO_LOGIN.secret');

$MOMO_ENABLED = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.enabled');
$MOMO_PARTNER_CODE = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.partnerCode');
$MOMO_ACCESS_KEY = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.accessKey');
$MOMO_SECRET_KEY = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.secretKey');
$MOMO_REDIRECT_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.redirectUrl');
$MOMO_IPN_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.ipnUrl');
$MOMO_CREATE_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.createUrl');
$MOMO_QUERY_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo.queryUrl');

$MOMO_TEST_PARTNER_CODE = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.partnerCode');
$MOMO_TEST_ACCESS_KEY = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.accessKey');
$MOMO_TEST_SECRET_KEY = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.secretKey');
$MOMO_TEST_REDIRECT_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.redirectUrl');
$MOMO_TEST_IPN_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.ipnUrl');
$MOMO_TEST_CREATE_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.createUrl');
$MOMO_TEST_QUERY_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.momo_test.queryUrl');

$VNPAY_ENABLED = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.enabled');
$VNPAY_TMN_CODE = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.tmnCode');
$VNPAY_HASH_SECRET = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.hashSecret');
$VNPAY_RETURN_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.returnUrl');
$VNPAY_IPN_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.ipnUrl');
$VNPAY_PAY_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.payUrl');
$VNPAY_API_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.vnpay.apiUrl');

$ZALOPAY_ENABLED = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.enabled');
$ZALOPAY_APP_ID = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.app_id');
$ZALOPAY_KEY1 = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key1');
$ZALOPAY_KEY2 = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key2');
$ZALOPAY_CREATE_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.createUrl');
$ZALOPAY_CALLBACK_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.callbackUrl');
$ZALOPAY_REDIRECT_URL = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.redirectUrl');

if ($VNPAY_RETURN_URL === '' && $vnpayReturnDefault !== '') {
    $VNPAY_RETURN_URL = $vnpayReturnDefault;
}
if ($VNPAY_IPN_URL === '' && $vnpayIpnDefault !== '') {
    $VNPAY_IPN_URL = $vnpayIpnDefault;
}
if ($VNPAY_PAY_URL === '') {
    $VNPAY_PAY_URL = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
}
if ($VNPAY_API_URL === '') {
    $VNPAY_API_URL = 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction';
}

$COD_ENABLED = app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.cod.enabled');

$XU_REVIEW_REWARD = app_get_config_value_by_path('ECOMMERCE_XU.review_reward');
$XU_VND_PER_XU = app_get_config_value_by_path('ECOMMERCE_XU.vnd_per_xu');
$XU_MAX_USE_PERCENT = app_get_config_value_by_path('ECOMMERCE_XU.max_use_percent');
$XU_EARN_PERCENT = app_get_config_value_by_path('ECOMMERCE_XU.earn_percent');

// Tự động trả lời bình luận / đánh giá
$AUTO_REPLY_ENABLED = app_get_config_value_by_path('AUTO_REPLY.enabled');
$AUTO_REPLY_Q_ENABLED = app_get_config_value_by_path('AUTO_REPLY.question_enabled');
$AUTO_REPLY_R_ENABLED = app_get_config_value_by_path('AUTO_REPLY.review_enabled');
// Mặc định bật riêng theo loại nếu key chưa từng được lưu (null)
if ($AUTO_REPLY_Q_ENABLED === null) $AUTO_REPLY_Q_ENABLED = true;
if ($AUTO_REPLY_R_ENABLED === null) $AUTO_REPLY_R_ENABLED = true;
$AUTO_REPLY_QUESTION = app_get_config_value_by_path('AUTO_REPLY.question_text');
$AUTO_REPLY_REVIEW = app_get_config_value_by_path('AUTO_REPLY.review_text');
if ($AUTO_REPLY_QUESTION === null || $AUTO_REPLY_QUESTION === '') {
    $AUTO_REPLY_QUESTION = 'Cảm ơn bạn đã đặt câu hỏi! Shop đã ghi nhận và sẽ phản hồi chi tiết trong thời gian sớm nhất. Bạn có thể liên hệ hotline để được hỗ trợ nhanh hơn nhé.';
}
if ($AUTO_REPLY_REVIEW === null || $AUTO_REPLY_REVIEW === '') {
    $AUTO_REPLY_REVIEW = 'Cảm ơn bạn đã đánh giá sản phẩm! Shop rất trân trọng phản hồi của bạn và sẽ tiếp tục cố gắng để phục vụ bạn tốt hơn.';
}

$REGION_NORTH = app_get_config_value_by_path('ECOMMERCE_REGIONS.north');
$REGION_CENTRAL = app_get_config_value_by_path('ECOMMERCE_REGIONS.central');
$REGION_SOUTH = app_get_config_value_by_path('ECOMMERCE_REGIONS.south');

$SITE_HOTLINE = app_get_config_value_by_path('SITE_HOTLINE');
$VAT_DEFAULT = app_get_config_value_by_path('ECOMMERCE_VAT_DEFAULT');

// Thông tin công ty dùng cho COD / hóa đơn
$COMPANY_NAME = app_get_config_value_by_path('COMPANY_INFO.name');
$COMPANY_TAX_CODE = app_get_config_value_by_path('COMPANY_INFO.tax_code');
$COMPANY_ADDRESS = app_get_config_value_by_path('COMPANY_INFO.address');
$COMPANY_HOTLINE = app_get_config_value_by_path('COMPANY_INFO.hotline');
$COMPANY_EMAIL = app_get_config_value_by_path('COMPANY_INFO.email');
$COMPANY_BANK_NAME = app_get_config_value_by_path('COMPANY_INFO.bank_name');
$COMPANY_BANK_ACCOUNT = app_get_config_value_by_path('COMPANY_INFO.bank_account');
$COMPANY_BANK_BRANCH = app_get_config_value_by_path('COMPANY_INFO.bank_branch');
$COMPANY_BANK_QR = app_get_config_value_by_path('COMPANY_INFO.bank_qr_image');
$COMPANY_BANK_QR_URL = $COMPANY_BANK_QR ? rtrim((string)($baseUrl ?? ''), '/') . '/' . ltrim((string)$COMPANY_BANK_QR, '/') : '';
?>
<style>
.bot-panel{display:grid;gap:16px;}
.bot-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 26px rgba(15,23,42,.06);}
.bot-head{display:grid;grid-template-columns:1fr auto;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid #eef2f7;}
.head-main{display:flex;flex-direction:column;gap:4px;}
.head-sub{font-size:.78rem;color:#64748b;}
.head-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
.bot-title{margin:0;font-size:1rem;font-weight:800;color:#0f172a;}
.bot-sub{margin:2px 0 0;font-size:.8rem;color:#64748b;}
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:.76rem;font-weight:700;background:#e5e7eb;color:#374151;}
.status-pill.on{background:#dcfce7;color:#166534;}
.status-pill.off{background:#fef3c7;color:#92400e;}
.bot-body{padding:18px;}
.bot-status-wrap{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;}
.bot-status-title{font-weight:700;color:#0f172a;}
.bot-status-desc{font-size:.84rem;color:#64748b;}
.toggle-container{position:relative;display:inline-block;width:46px;height:26px;}
.toggle-input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background-color:#d1d5db;transition:.3s;border-radius:999px;}
.toggle-slider:before{content:'';position:absolute;height:18px;width:18px;left:4px;bottom:4px;background:#fff;transition:.3s;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.2);}
.toggle-input:checked + .toggle-slider{background-color:var(--theme-primary);}
.toggle-input:checked + .toggle-slider:before{transform:translateX(20px);}
.form-grid-row{margin:0;}
.config-item{display:flex;flex-direction:column;gap:6px;}
.config-item label{font-size:.8rem;font-weight:700;color:#334155;}
.config-item input,.config-item textarea,.config-item select{border:1px solid #dbe3ef;border-radius:10px;padding:8px 10px;font-size:.86rem;}
.config-item textarea{min-height:76px;resize:vertical;}
.config-item{position:relative;}
.config-item.saving::after{content:'...';position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.75rem;color:#64748b;}
.config-item.saved::after{content:'\2713';position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.9rem;color:#16a34a;font-weight:700;}
.config-bool-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;}
.config-bool-row label{margin:0;}
.config-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start;}
.config-card{height:100%;display:flex;flex-direction:column;}
.config-card .bot-head{padding:12px 14px;min-height:52px;}
.config-card .bot-body{padding:14px;}
.config-section-title{font-size:.88rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;margin:0;}
.config-actions{display:flex;justify-content:flex-end;padding:0;}
.config-actions .btn{min-width:140px;}
.momo-head-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.momo-env-badge{font-size:.72rem;font-weight:700;padding:4px 8px;border-radius:999px;background:#e2e8f0;color:#334155;}
.momo-env-badge.active{background:#dcfce7;color:#166534;}
.momo-toggle-label{font-size:.72rem;font-weight:700;color:#334155;}
.momo-btn{white-space:nowrap;}
.momo-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.momo-test-result{min-height:20px;color:#64748b;}
.loading-overlay{position:absolute;inset:0;background:rgba(255,255,255,.72);display:none;align-items:center;justify-content:center;border-radius:16px;z-index:3;}
.save-bar{position:sticky;bottom:8px;z-index:2;box-shadow:0 10px 24px rgba(15,23,42,.08);}

/* Skeleton loading */
.skeleton-line{display:block;width:100%;height:10px;border-radius:999px;background:linear-gradient(90deg,#e5e7eb,#f3f4f6,#e5e7eb);background-size:200% 100%;animation:skeleton-loading 1.2s ease-in-out infinite;}
@keyframes skeleton-loading{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
.config-item.skeleton input,.config-item.skeleton textarea,.config-item.skeleton select{color:transparent;background-color:#f3f4f6;}
.config-item.skeleton label{opacity:.6;}

/* Tabs giống kiểu account.php */
.settings-shell{display:grid;gap:12px;}
.settings-tabs{display:flex;overflow-x:auto;border-radius:999px;background:#f3f4f6;padding:4px;-ms-overflow-style:none;scrollbar-width:none;}
.settings-tabs::-webkit-scrollbar{height:0;display:none;}
.settings-tab{border:none;background:transparent;color:#4b5563;font-size:.86rem;font-weight:700;padding:8px 14px;border-radius:999px;white-space:nowrap;cursor:pointer;}
.settings-tab.active{background:#fff;color:#0f172a;box-shadow:0 4px 10px rgba(15,23,42,.08);}
.settings-sections{border-radius:16px;}
.settings-section{display:none;}
.settings-section.active{display:block;}

@media (max-width: 1200px){
    .config-cards{grid-template-columns:1fr;}
}
@media (max-width: 992px){
    .bot-head{grid-template-columns:1fr;align-items:flex-start;}
    .head-actions{justify-content:flex-start;}
}

/* ===== Switch chế độ Media: làm NỔI BẬT nút đang bật ===== */
.media-mode-group .media-mode-btn{
    font-weight:600;
    opacity:.65;
    transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease;
}
.media-mode-group .media-mode-btn:hover{ opacity:.9; }
.media-mode-group .media-mode-btn--active{
    opacity:1;
    font-weight:800;
    transform:translateY(-1px) scale(1.04);
    z-index:2;
    box-shadow:0 6px 16px rgba(15,23,42,.18), 0 0 0 3px rgba(255,255,255,.85), 0 0 0 5px currentColor;
}
.media-mode-group .media-mode-btn--active::before{
    content:"\F26B"; /* bi-check-lg */
    font-family:"bootstrap-icons";
    font-weight:700;
    margin-right:.35rem;
}
#mediaModeStatus:empty{ display:none; }
#mediaModeStatus{ font-size:.72rem; font-weight:700; }
</style>

<div class="bot-panel position-relative">  
    <div id="configCardsWrap" class="settings-shell" data-momo-env="<?= $bot_h($momoEnv) ?>">
        <div class="settings-tabs mb-1">
            <button type="button" class="settings-tab active" data-target="#tab-ai-login"><i class="bi bi-cpu me-1"></i>AI &amp; Đăng nhập</button>
            <button type="button" class="settings-tab" data-target="#tab-payment"><i class="bi bi-credit-card me-1"></i>Thanh toán</button>
            <button type="button" class="settings-tab" data-target="#tab-company"><i class="bi bi-building me-1"></i>Công ty</button>
            <button type="button" class="settings-tab" data-target="#tab-ecommerce"><i class="bi bi-bag-check me-1"></i>Ecommerce</button>
            <button type="button" class="settings-tab" data-target="#tab-region"><i class="bi bi-geo-alt me-1"></i>Region</button>
            <button type="button" class="settings-tab" data-target="#tab-social"><i class="bi bi-share me-1"></i>Social & Website</button>
        </div>
        <div class="settings-sections">
            <!-- TAB 1: AI + LOGIN -->
            <div class="settings-section active" id="tab-ai-login">
                <div class="config-cards">
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-gear"></i>CẤU HÌNH AI KEY</div>
                                <div class="head-sub">Nhập API Key cho các nhà cung cấp AI</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="mb-2" style="font-size: .8rem; color: #64748b;">
                                <div><strong>OpenAI:</strong> vào <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a> (đăng nhập tài khoản OpenAI) → tạo API key mới → copy và dán vào ô "OpenAI API Key".</div>
                                <div class="mt-1"><strong>Google Gemini:</strong> vào <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a> (Google AI Studio) → tạo API key → đảm bảo project đã bật thanh toán nếu cần → copy và dán vào ô "Gemini API Key".</div>
                            </div>
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="API_OPENAI_KEY">
                                        <label for="cfg_API_OPENAI_KEY">OpenAI API Key</label>
                                        <input id="cfg_API_OPENAI_KEY" type="password" data-type="string" value="<?= $bot_h($AI_OPENAI_KEY) ?>" placeholder="OpenAI API Key">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="API_GEMINI_KEY">
                                        <label for="cfg_API_GEMINI_KEY">Gemini API Key</label>
                                        <input id="cfg_API_GEMINI_KEY" type="password" data-type="string" value="<?= $bot_h($AI_GEMINI_KEY) ?>" placeholder="Gemini API Key">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="GOOGLE_MAPS_API_KEY">
                                        <label for="cfg_GOOGLE_MAPS_API_KEY">Google Maps API Key</label>
                                        <input id="cfg_GOOGLE_MAPS_API_KEY" type="password" data-type="string" value="<?= $bot_h($AI_GMAPS_KEY) ?>" placeholder="Google Maps API Key">
                                        <small class="text-muted">Cần bật: Maps JavaScript API, Geocoding API, Places API.</small>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="TINYMCE_API_KEY">
                                        <label for="cfg_TINYMCE_API_KEY">TinyMCE API Key</label>
                                        <input id="cfg_TINYMCE_API_KEY" type="password" data-type="string" value="<?= $bot_h($TINYMCE_API_KEY) ?>" placeholder="TinyMCE API Key">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title">
                                    <i class="bi bi-gear"></i>THIẾT LẬP ĐĂNG NHẬP
                                    <div class="config-item ms-2 mb-0" data-key="GOOGLE_LOGIN.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_GOOGLE_LOGIN_enabled">Bật Google</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_GOOGLE_LOGIN_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($LOGIN_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="head-sub">Cấu hình đăng nhập Google</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <small class="mt-0">Nếu chưa có ClientID hãy truy cập <a href="https://console.cloud.google.com/apis/credentials?project=zalo-467816">Google Cloud Console</a> để tạo mới, chọn "OAuth 2.0 Client IDs", sau đó điền thông tin và lấy Client ID dán vào ô dưới.</small>
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="GOOGLE_LOGIN.client_id">
                                        <label for="cfg_GOOGLE_LOGIN_client_id">Google Client ID</label>
                                        <input id="cfg_GOOGLE_LOGIN_client_id" type="text" data-type="string" value="<?= $bot_h($LOGIN_CLIENT_ID) ?>" placeholder="Google Client ID">
                                    </div>
                                </div>
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="GOOGLE_LOGIN.redirect_uri">
                                        <label for="cfg_GOOGLE_LOGIN_redirect_uri">Google OAuth Redirect URI</label>
                                        <input id="cfg_GOOGLE_LOGIN_redirect_uri" type="text" data-type="string" value="<?= $bot_h($LOGIN_REDIRECT_URI) ?>" placeholder="https://your-domain.com/path-to-google-callback">
                                    </div>
                                </div>
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="GOOGLE_LOGIN.scope">
                                        <label for="cfg_GOOGLE_LOGIN_scope">Google OAuth Scope</label>
                                        <input id="cfg_GOOGLE_LOGIN_scope" type="text" data-type="string" value="<?= $bot_h($LOGIN_SCOPE) ?>" placeholder="openid email profile">
                                    </div>
                                </div>
                                <div class="col-12 col-md-12 d-none">
                                    <div class="config-item" data-key="GOOGLE_LOGIN.auto_register">
                                        <div class="config-bool-row">
                                            <label for="cfg_GOOGLE_LOGIN_auto_register">Google Auto Register</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_GOOGLE_LOGIN_auto_register" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($LOGIN_AUTO_REGISTER) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-12 mt-2">
                                    <hr>
                                </div>
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="ZALO_LOGIN.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_ZALO_LOGIN_enabled">Bật đăng nhập Zalo</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_ZALO_LOGIN_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($ZALO_LOGIN_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="ZALO_LOGIN.auth_url">
                                        <label for="cfg_ZALO_LOGIN_auth_url">Zalo OAuth URL</label>
                                        <input id="cfg_ZALO_LOGIN_auth_url" type="text" data-type="string" value="<?= $bot_h($ZALO_LOGIN_AUTH_URL) ?>" placeholder="https://oauth.zaloapp.com/v4/oa/permission?...">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ZALO_LOGIN.app_id">
                                        <label for="cfg_ZALO_LOGIN_app_id">Zalo App ID</label>
                                        <input id="cfg_ZALO_LOGIN_app_id" type="text" data-type="string" value="<?= $bot_h($ZALO_LOGIN_APP_ID) ?>" placeholder="Nhập App ID từ Zalo Developer">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ZALO_LOGIN.secret">
                                        <label for="cfg_ZALO_LOGIN_secret">Zalo App Secret</label>
                                        <input id="cfg_ZALO_LOGIN_secret" type="password" data-type="string" value="<?= $bot_h($ZALO_LOGIN_SECRET) ?>" placeholder="Nhập App Secret từ Zalo Developer">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: THANH TOÁN (MoMo, COD, VNPAY) -->
            <div class="settings-section" id="tab-payment">
                <div class="config-cards">
                    <?php
                        $envKey = 'production';
                        $badgeText = ($envKey === $momoEnv) ? 'Đang dùng' : 'Chưa dùng';
                        $badgeClass = ($envKey === $momoEnv) ? 'momo-env-badge active' : 'momo-env-badge';
                    ?>
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-credit-card-2-front"></i>MoMo</div>
                                <div class="head-sub">Môi trường thực tế</div>
                            </div>
                            <div class="head-actions">
                                <div class="momo-actions">
                                    <span class="<?= $badgeClass ?>" data-env="<?= $bot_h($envKey) ?>"><?= $bot_h($badgeText) ?></span>
                                </div>
                                <div class="momo-actions">
                                    <button type="button" class="btn btn-outline-primary btn-sm momo-btn momo-test-btn" data-env="<?= $bot_h($envKey) ?>">Test</button>
                                    <button type="button" class="btn btn-primary btn-sm momo-btn momo-apply-btn" data-env="<?= $bot_h($envKey) ?>">MẶC ĐỊNH</button>
                                </div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_enabled">Bật MoMo</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($MOMO_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.partnerCode">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_partnerCode">MoMo Partner Code</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_partnerCode" type="text" data-type="string" value="<?= $bot_h($MOMO_PARTNER_CODE) ?>" placeholder="MoMo Partner Code">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.accessKey">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_accessKey">MoMo Access Key</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_accessKey" type="password" data-type="string" value="<?= $bot_h($MOMO_ACCESS_KEY) ?>" placeholder="MoMo Access Key">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.secretKey">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_secretKey">MoMo Secret Key</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_secretKey" type="password" data-type="string" value="<?= $bot_h($MOMO_SECRET_KEY) ?>" placeholder="MoMo Secret Key">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.redirectUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_redirectUrl">MoMo Redirect URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_redirectUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_REDIRECT_URL) ?>" placeholder="MoMo Redirect URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.ipnUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_ipnUrl">MoMo IPN URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_ipnUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_IPN_URL) ?>" placeholder="MoMo IPN URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.createUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_createUrl">MoMo Create URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_createUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_CREATE_URL) ?>" placeholder="MoMo Create URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo.queryUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_queryUrl">MoMo Query URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_queryUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_QUERY_URL) ?>" placeholder="MoMo Query URL">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 momo-test-result" aria-live="polite"></div>
                        </div>
                    </div>

                    <?php
                        $envKey = 'test';
                        $badgeText = ($envKey === $momoEnv) ? 'Đang dùng' : 'Chưa dùng';
                        $badgeClass = ($envKey === $momoEnv) ? 'momo-env-badge active' : 'momo-env-badge';
                    ?>
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-credit-card-2-front"></i>MoMo - Test</div>
                                <div class="head-sub">Môi trường thử nghiệm</div>
                            </div>
                            <div class="head-actions">
                                <div class="momo-actions">
                                    <span class="<?= $badgeClass ?>" data-env="<?= $bot_h($envKey) ?>"><?= $bot_h($badgeText) ?></span>
                                </div>
                                <div class="momo-actions">
                                    <button type="button" class="btn btn-outline-primary btn-sm momo-btn momo-test-btn" data-env="<?= $bot_h($envKey) ?>">Test</button>
                                    <button type="button" class="btn btn-primary btn-sm momo-btn momo-apply-btn" data-env="<?= $bot_h($envKey) ?>">MẶC ĐỊNH</button>
                                </div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.partnerCode">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_partnerCode">MoMo Test Partner Code</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_partnerCode" type="text" data-type="string" value="<?= $bot_h($MOMO_TEST_PARTNER_CODE) ?>" placeholder="MoMo Test Partner Code">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.accessKey">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_accessKey">MoMo Test Access Key</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_accessKey" type="password" data-type="string" value="<?= $bot_h($MOMO_TEST_ACCESS_KEY) ?>" placeholder="MoMo Test Access Key">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.secretKey">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_secretKey">MoMo Test Secret Key</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_secretKey" type="password" data-type="string" value="<?= $bot_h($MOMO_TEST_SECRET_KEY) ?>" placeholder="MoMo Test Secret Key">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.redirectUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_redirectUrl">MoMo Test Redirect URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_redirectUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_TEST_REDIRECT_URL) ?>" placeholder="MoMo Test Redirect URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.ipnUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_ipnUrl">MoMo Test IPN URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_ipnUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_TEST_IPN_URL) ?>" placeholder="MoMo Test IPN URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.createUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_createUrl">MoMo Test Create URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_createUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_TEST_CREATE_URL) ?>" placeholder="MoMo Test Create URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.momo_test.queryUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_queryUrl">MoMo Test Query URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_momo_test_queryUrl" type="text" data-type="string" value="<?= $bot_h($MOMO_TEST_QUERY_URL) ?>" placeholder="MoMo Test Query URL">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 momo-test-result" aria-live="polite"></div>
                        </div>
                    </div>

                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-gear"></i>COD (Tiền mặt)</div>
                                <div class="head-sub">Tham số liên quan</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.cod.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_ECOMMERCE_PAYMENT_METHODS_cod_enabled">Bật thanh toán tiền mặt</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_ECOMMERCE_PAYMENT_METHODS_cod_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($COD_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-gear"></i>ZaloPay</div>
                                <div class="head-sub">Tham số liên quan</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_enabled">Bật ZaloPay</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($ZALOPAY_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.app_id">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_app_id">ZaloPay App ID</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_app_id" type="text" data-type="int" value="<?= $bot_h($ZALOPAY_APP_ID) ?>" placeholder="ZaloPay App ID">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.key1">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_key1">ZaloPay Key1 (MAC)</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_key1" type="password" data-type="string" value="<?= $bot_h($ZALOPAY_KEY1) ?>" placeholder="ZaloPay Key1">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.key2">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_key2">ZaloPay Key2</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_key2" type="password" data-type="string" value="<?= $bot_h($ZALOPAY_KEY2) ?>" placeholder="ZaloPay Key2">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.createUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_createUrl">ZaloPay Create Order URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_createUrl" type="text" data-type="string" value="<?= $bot_h($ZALOPAY_CREATE_URL) ?>" placeholder="https://.../gateway/api/create">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.callbackUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_callbackUrl">ZaloPay Callback URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_callbackUrl" type="text" data-type="string" value="<?= $bot_h($ZALOPAY_CALLBACK_URL) ?>" placeholder="URL nhận callback từ ZaloPay">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.zalopay.redirectUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_redirectUrl">ZaloPay Redirect URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_zalopay_redirectUrl" type="text" data-type="string" value="<?= $bot_h($ZALOPAY_REDIRECT_URL) ?>" placeholder="URL hiển thị kết quả trên website">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-gear"></i>VNPAY</div>
                                <div class="head-sub">Tham số liên quan</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_enabled">Bật VNPay</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($VNPAY_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.tmnCode">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_tmnCode">VNPAY TMN Code</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_tmnCode" type="text" data-type="string" value="<?= $bot_h($VNPAY_TMN_CODE) ?>" placeholder="VNPAY TMN Code">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.hashSecret">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_hashSecret">VNPAY Hash Secret</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_hashSecret" type="password" data-type="string" value="<?= $bot_h($VNPAY_HASH_SECRET) ?>" placeholder="VNPAY Hash Secret">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.returnUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_returnUrl">VNPAY Return URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_returnUrl" type="text" data-type="string" value="<?= $bot_h($VNPAY_RETURN_URL) ?>" placeholder="VNPAY Return URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.ipnUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_ipnUrl">VNPAY IPN URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_ipnUrl" type="text" data-type="string" value="<?= $bot_h($VNPAY_IPN_URL) ?>" placeholder="VNPAY IPN URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.payUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_payUrl">VNPAY Payment URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_payUrl" type="text" data-type="string" value="<?= $bot_h($VNPAY_PAY_URL) ?>" placeholder="VNPAY Payment URL">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_PAYMENT_METHODS.vnpay.apiUrl">
                                        <label for="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_apiUrl">VNPAY API URL</label>
                                        <input id="cfg_ECOMMERCE_PAYMENT_METHODS_vnpay_apiUrl" type="text" data-type="string" value="<?= $bot_h($VNPAY_API_URL) ?>" placeholder="VNPAY API URL">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <!-- TAB 3: THÔNG TIN CÔNG TY -->
        <div class="settings-section" id="tab-company">
                <!-- /./ -->    
                  <div class="config-card">
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-building"></i>Thông tin công ty</div>
                                <div class="head-sub">Cấu hình thông tin công ty dùng cho xuất hóa đơn và thanh toán COD</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.name">
                                        <label for="cfg_COMPANY_INFO_name">Tên công ty</label>
                                        <input id="cfg_COMPANY_INFO_name" type="text" data-type="string" value="<?= $bot_h($COMPANY_NAME) ?>" placeholder="VD: CÔNG TY TNHH ABC">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.tax_code">
                                        <label for="cfg_COMPANY_INFO_tax_code">Mã số thuế</label>
                                        <input id="cfg_COMPANY_INFO_tax_code" type="text" data-type="string" value="<?= $bot_h($COMPANY_TAX_CODE) ?>" placeholder="VD: 0312345678">
                                    </div>
                                </div>

                                <div class="col-12 col-md-12">
                                    <div class="config-item" data-key="COMPANY_INFO.address">
                                        <label for="cfg_COMPANY_INFO_address">Địa chỉ công ty</label>
                                        <input id="cfg_COMPANY_INFO_address" type="text" data-type="string" value="<?= $bot_h($COMPANY_ADDRESS) ?>" placeholder="Địa chỉ xuất hóa đơn / giao dịch">
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.hotline">
                                        <label for="cfg_COMPANY_INFO_hotline">Hotline công ty</label>
                                        <input id="cfg_COMPANY_INFO_hotline" type="text" data-type="string" value="<?= $bot_h($COMPANY_HOTLINE) ?>" placeholder="Hotline liên hệ trên hóa đơn">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.email">
                                        <label for="cfg_COMPANY_INFO_email">Email công ty</label>
                                        <input id="cfg_COMPANY_INFO_email" type="text" data-type="string" value="<?= $bot_h($COMPANY_EMAIL) ?>" placeholder="Email kế toán / CSKH">
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.bank_name">
                                        <label for="cfg_COMPANY_INFO_bank_name">Ngân hàng</label>
                                        <input id="cfg_COMPANY_INFO_bank_name" type="text" data-type="string" value="<?= $bot_h($COMPANY_BANK_NAME) ?>" placeholder="VD: Vietcombank">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.bank_account">
                                        <label for="cfg_COMPANY_INFO_bank_account">Số tài khoản</label>
                                        <input id="cfg_COMPANY_INFO_bank_account" type="text" data-type="string" value="<?= $bot_h($COMPANY_BANK_ACCOUNT) ?>" placeholder="Số tài khoản ngân hàng">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.bank_branch">
                                        <label for="cfg_COMPANY_INFO_bank_branch">Chi nhánh</label>
                                        <input id="cfg_COMPANY_INFO_bank_branch" type="text" data-type="string" value="<?= $bot_h($COMPANY_BANK_BRANCH) ?>" placeholder="Chi nhánh ngân hàng">
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="COMPANY_INFO.bank_qr_image">
                                        <label for="cfg_COMPANY_INFO_bank_qr_image">Ảnh QR thanh toán (ngân hàng)</label>
                                        <!-- hidden text config field lưu path để save_config hoạt động bình thường -->
                                        <input id="cfg_COMPANY_INFO_bank_qr_image" type="text" class="d-none" data-type="string" value="<?= $bot_h($COMPANY_BANK_QR) ?>">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <input id="codQrUploadInput" type="file" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm" style="max-width:260px;">
                                            <?php if ($COMPANY_BANK_QR_URL): ?>
                                                <a id="codQrPreviewLink" href="<?= $bot_h($COMPANY_BANK_QR_URL) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Xem ảnh hiện tại</a>
                                            <?php else: ?>
                                                <a id="codQrPreviewLink" href="#" target="_blank" class="btn btn-outline-secondary btn-sm d-none">Xem ảnh hiện tại</a>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">Upload ảnh QR ngân hàng dùng cho thanh toán đơn hàng (COD).</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                 
            <!-- /./ -->
            </div>
            </div>
            <!-- TAB 4: ECOMMERCE XU -->
            <div class="settings-section" id="tab-ecommerce">
                <div class="config-card">
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-gear"></i>Ecommerce</div>
                                <div class="head-sub">Tham số liên quan</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_VAT_DEFAULT">
                                        <label for="cfg_ECOMMERCE_VAT_DEFAULT">Thuế VAT mặc định (%)</label>
                                        <input id="cfg_ECOMMERCE_VAT_DEFAULT" type="text" data-type="float" value="<?= $bot_h($VAT_DEFAULT) ?>" placeholder="VD: 8">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="SITE_HOTLINE">
                                        <label for="cfg_SITE_HOTLINE">Hotline Liên Hệ</label>
                                        <input id="cfg_SITE_HOTLINE" type="text" data-type="string" value="<?= $bot_h($SITE_HOTLINE) ?>" placeholder="VD: 0909 143 900">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_XU.review_reward">
                                        <label for="cfg_ECOMMERCE_XU_review_reward">Xu thưởng đánh giá</label>
                                        <input id="cfg_ECOMMERCE_XU_review_reward" type="text" data-type="int" value="<?= $bot_h($XU_REVIEW_REWARD) ?>" placeholder="Xu thưởng đánh giá">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_XU.vnd_per_xu">
                                        <label for="cfg_ECOMMERCE_XU_vnd_per_xu">Quy đổi VNĐ / 1 xu</label>
                                        <input id="cfg_ECOMMERCE_XU_vnd_per_xu" type="text" data-type="int" value="<?= $bot_h($XU_VND_PER_XU) ?>" placeholder="Quy đổi VNĐ / 1 xu">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_XU.max_use_percent">
                                        <label for="cfg_ECOMMERCE_XU_max_use_percent">Tỷ lệ dùng xu tối đa (%)</label>
                                        <input id="cfg_ECOMMERCE_XU_max_use_percent" type="text" data-type="int" value="<?= $bot_h($XU_MAX_USE_PERCENT) ?>" placeholder="Tỷ lệ dùng xu tối đa (%)">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="ECOMMERCE_XU.earn_percent">
                                        <label for="cfg_ECOMMERCE_XU_earn_percent">Tỷ lệ hoàn xu sau giao (%)</label>
                                        <input id="cfg_ECOMMERCE_XU_earn_percent" type="text" data-type="int" value="<?= $bot_h($XU_EARN_PERCENT) ?>" placeholder="Tỷ lệ hoàn xu sau giao (%)">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tự động trả lời bình luận & đánh giá -->
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-chat-dots"></i>TỰ ĐỘNG TRẢ LỜI BÌNH LUẬN &amp; ĐÁNH GIÁ</div>
                                <div class="head-sub">Khi khách đặt câu hỏi hoặc đánh giá sản phẩm, hệ thống tự gửi một phản hồi mặc định dưới tên Quản trị viên.</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                <div class="col-12">
                                    <div class="config-item" data-key="AUTO_REPLY.enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_AUTO_REPLY_enabled">Bật tự động trả lời (công tắc tổng)</label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_AUTO_REPLY_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($AUTO_REPLY_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <small class="text-muted">Tắt để dừng hoàn toàn việc tự động trả lời (ưu tiên cao nhất).</small>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="AUTO_REPLY.question_enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_AUTO_REPLY_question_enabled">Tự động trả lời cho <strong>Câu hỏi</strong></label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_AUTO_REPLY_question_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($AUTO_REPLY_Q_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <small class="text-muted">Trả lời khi khách đặt câu hỏi (không có sao).</small>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="config-item" data-key="AUTO_REPLY.review_enabled">
                                        <div class="config-bool-row">
                                            <label for="cfg_AUTO_REPLY_review_enabled">Tự động trả lời cho <strong>Đánh giá</strong></label>
                                            <label class="toggle-container config-toggle" title="Bật/tắt">
                                                <input id="cfg_AUTO_REPLY_review_enabled" class="toggle-input" type="checkbox" data-type="bool" <?= $bot_checked($AUTO_REPLY_R_ENABLED) ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <small class="text-muted">Trả lời khi khách đánh giá (có sao).</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="config-item" data-key="AUTO_REPLY.question_text">
                                        <label for="cfg_AUTO_REPLY_question_text">Nội dung trả lời khi khách đặt câu hỏi</label>
                                        <textarea id="cfg_AUTO_REPLY_question_text" data-type="string" rows="3" placeholder="VD: Cảm ơn bạn đã đặt câu hỏi, shop sẽ phản hồi sớm nhất..."><?= $bot_h($AUTO_REPLY_QUESTION) ?></textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="config-item" data-key="AUTO_REPLY.review_text">
                                        <label for="cfg_AUTO_REPLY_review_text">Nội dung trả lời khi khách đánh giá (có sao)</label>
                                        <textarea id="cfg_AUTO_REPLY_review_text" data-type="string" rows="3" placeholder="VD: Cảm ơn bạn đã đánh giá sản phẩm..."><?= $bot_h($AUTO_REPLY_REVIEW) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: REGION -->
            <div class="settings-section" id="tab-region">
                <div class="config-card">
                    <div class="bot-card config-card">
                        <div class="bot-head">
                            <div class="head-main">
                                <div class="config-section-title"><i class="bi bi-gear"></i>Region</div>
                                <div class="head-sub">Tham số liên quan</div>
                            </div>
                        </div>
                        <div class="bot-body">
                            <div class="row g-3 form-grid-row">
                                
                                <div class="col-12 col-md-4">
                                    <div class="config-item" data-key="ECOMMERCE_REGIONS.north">
                                        <label for="cfg_ECOMMERCE_REGIONS_north">Khu vực miền Bắc</label>
                                        <input id="cfg_ECOMMERCE_REGIONS_north" type="text" data-type="string" value="<?= $bot_h($REGION_NORTH) ?>" placeholder="Khu vực miền Bắc">
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="config-item" data-key="ECOMMERCE_REGIONS.central">
                                        <label for="cfg_ECOMMERCE_REGIONS_central">Khu vực miền Trung</label>
                                        <input id="cfg_ECOMMERCE_REGIONS_central" type="text" data-type="string" value="<?= $bot_h($REGION_CENTRAL) ?>" placeholder="Khu vực miền Trung">
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="config-item" data-key="ECOMMERCE_REGIONS.south">
                                        <label for="cfg_ECOMMERCE_REGIONS_south">Khu vực miền Nam</label>
                                        <input id="cfg_ECOMMERCE_REGIONS_south" type="text" data-type="string" value="<?= $bot_h($REGION_SOUTH) ?>" placeholder="Khu vực miền Nam">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- TAB 5: SOCIAL + WEBSITE (logo, tiêu đề, website, theme-color) -->
            <div class="settings-section" id="tab-social">
                    <div class="config-card">
                        <div class="bot-card config-card">
                            <div class="bot-head">
                                <div class="head-main">
                                    <div class="config-section-title"><i class="bi bi-share"></i>Social & Website</div>
                                    <div class="head-sub">Cấu hình thông tin mạng xã hội, logo, ảnh fallback, tiêu đề, website, màu chủ đề</div>
                                </div>
                            </div>
                            <div class="bot-body">
                                <div class="row g-3 form-grid-row">
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SITE_LOGO">
                                            <label for="cfg_SITE_LOGO">Logo website</label>
                                            <!-- input ẩn để auto-save vẫn hoạt động nếu cần -->
                                            <input id="cfg_SITE_LOGO" type="hidden" data-type="string" value="">
                                            <div class="d-flex flex-column align-items-start gap-2">
                                                <div class="border rounded p-2 bg-white" style="cursor:pointer;max-width:220px;">
                                                    <img id="siteLogoPreviewImage" src="<?= h(to_abs_url((string)($site_logo ?: ($site_fallback_logo ?: '')), $baseUrl)) ?>" alt="Logo website" style="max-width:200px;max-height:80px;object-fit:contain;display:block;">
                                                </div>
                                                <small class="text-muted">Nhấn vào logo để chọn file mới. Logo sẽ được lưu tự động sau khi upload thành công.</small>
                                                <input type="file" id="siteLogoUploadInput" accept="image/*" class="d-none">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SITE_FALLBACK_LOGO">
                                            <label for="cfg_SITE_FALLBACK_LOGO">Ảnh fallback chung</label>
                                            <input id="cfg_SITE_FALLBACK_LOGO" type="hidden" data-type="string" value="">
                                            <div class="d-flex flex-column align-items-start gap-2">
                                                <div class="border rounded p-2 bg-white" style="cursor:pointer;max-width:220px;">
                                                    <img id="siteFallbackPreviewImage" src="<?= h(to_abs_url((string)($site_fallback_logo ?: ''), $baseUrl)) ?>" alt="Ảnh fallback" style="max-width:200px;max-height:80px;object-fit:contain;display:block;">
                                                </div>
                                                <small class="text-muted">Dùng khi sản phẩm/ảnh không có hình riêng. Nhấn để chọn file, hệ thống sẽ dùng ảnh này làm mặc định.</small>
                                                <input type="file" id="siteFallbackUploadInput" accept="image/*" class="d-none">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SITE_TITLE">
                                            <label for="cfg_SITE_TITLE">Tiêu đề website</label>
                                            <input id="cfg_SITE_TITLE" type="text" data-type="string" placeholder="Tiêu đề website">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SITE_URL">
                                            <label for="cfg_SITE_URL">Địa chỉ website</label>
                                            <input id="cfg_SITE_URL" type="text" data-type="string" placeholder="https://yourdomain.com">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="config-item" data-key="SITE_DESCRIPTION">
                                            <label for="cfg_SITE_DESCRIPTION">Mô tả website (meta description)</label>
                                            <textarea id="cfg_SITE_DESCRIPTION" data-type="string" rows="2" maxlength="320" placeholder="Mô tả ngắn về website, hiển thị trên Google &amp; mạng xã hội"></textarea>
                                            <small class="text-muted">Dùng cho thẻ meta description &amp; og:description khi trang không có mô tả riêng. Nên 150–160 ký tự.</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SITE_THEME_COLOR">
                                            <label for="cfg_SITE_THEME_COLOR">Màu chủ đề website (theme-color)</label>
                                            <input id="cfg_SITE_THEME_COLOR" type="color" data-type="string" value="#ee4d2d">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_FACEBOOK">
                                            <label for="cfg_SOCIAL_FACEBOOK">Facebook Page/Link</label>
                                            <input id="cfg_SOCIAL_FACEBOOK" type="text" data-type="string" placeholder="https://facebook.com/yourpage">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_ZALO">
                                            <label for="cfg_SOCIAL_ZALO">Zalo OA/Link</label>
                                            <input id="cfg_SOCIAL_ZALO" type="text" data-type="string" placeholder="https://zalo.me/yourzalo">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_YOUTUBE">
                                            <label for="cfg_SOCIAL_YOUTUBE">YouTube Channel</label>
                                            <input id="cfg_SOCIAL_YOUTUBE" type="text" data-type="string" placeholder="https://youtube.com/yourchannel">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_TIKTOK">
                                            <label for="cfg_SOCIAL_TIKTOK">TikTok</label>
                                            <input id="cfg_SOCIAL_TIKTOK" type="text" data-type="string" placeholder="https://tiktok.com/@yourtiktok">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_INSTAGRAM">
                                            <label for="cfg_SOCIAL_INSTAGRAM">Instagram</label>
                                            <input id="cfg_SOCIAL_INSTAGRAM" type="text" data-type="string" placeholder="https://instagram.com/yourinsta">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_PHONE">
                                            <label for="cfg_SOCIAL_PHONE">Số điện thoại liên hệ</label>
                                            <input id="cfg_SOCIAL_PHONE" type="text" data-type="string" placeholder="0123456789">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="SOCIAL_EMAIL">
                                            <label for="cfg_SOCIAL_EMAIL">Email liên hệ</label>
                                            <input id="cfg_SOCIAL_EMAIL" type="text" data-type="string" placeholder="contact@yourdomain.com">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="config-section-title mt-2 mb-1"><i class="bi bi-hdd-network"></i>MEDIA DOMAIN / CDN</div>
                                        <small class="text-muted d-block mb-2">Tách ảnh/video sang domain riêng để giảm tải. Để trống MEDIA_BASE_URL = phục vụ từ server chính như cũ.</small>
                                    </div>
                                    <!-- Nút switch khẩn cấp: đổi chế độ media nhanh -->
                                    <div class="col-12">
                                        <div class="config-item" data-key="MEDIA_MODE">
                                            <input id="cfg_MEDIA_MODE" type="hidden" data-type="string" value="vps">
                                        </div>
                                        <label class="d-block mb-1 fw-semibold">
                                            Chế độ hoạt động (đổi nhanh khi khẩn cấp)
                                            <span id="mediaModeStatus" class="badge rounded-pill ms-1 align-middle"></span>
                                        </label>
                                        <div class="btn-group flex-wrap media-mode-group" role="group" id="mediaModeSwitch" aria-label="Chế độ media">
                                            <button type="button" class="btn btn-outline-success btn-sm media-mode-btn" data-mode="vps" aria-pressed="false"><i class="bi bi-hdd-network me-1"></i>Media VPS</button>
                                            <button type="button" class="btn btn-outline-warning btn-sm media-mode-btn" data-mode="read_origin" aria-pressed="false"><i class="bi bi-exclamation-triangle me-1"></i>Khẩn cấp: đọc từ gốc</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm media-mode-btn" data-mode="local" aria-pressed="false"><i class="bi bi-hdd me-1"></i>Tắt (local)</button>
                                        </div>
                                        <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small" id="mediaModeWarn" style="display:none;">
                                            <i class="bi bi-exclamation-triangle me-1"></i><span id="mediaModeWarnText"></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="MEDIA_BASE_URL">
                                            <label for="cfg_MEDIA_BASE_URL">Media domain (URL đọc ảnh)</label>
                                            <input id="cfg_MEDIA_BASE_URL" type="text" data-type="string" placeholder="https://media.paintandmore.vn">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="MEDIA_RECEIVER_URL">
                                            <label for="cfg_MEDIA_RECEIVER_URL">Media receiver URL (nhận upload)</label>
                                            <input id="cfg_MEDIA_RECEIVER_URL" type="text" data-type="string" placeholder="https://media.paintandmore.vn/receiver.php">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="MEDIA_SECRET">
                                            <label for="cfg_MEDIA_SECRET">Media secret (HMAC)</label>
                                            <input id="cfg_MEDIA_SECRET" type="text" data-type="string" autocomplete="off" placeholder="Chuỗi bí mật khớp với receiver.php trên VPS">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="MEDIA_REMOTE_ENABLED">
                                            <div class="config-bool-row">
                                                <label for="cfg_MEDIA_REMOTE_ENABLED">Đẩy upload lên media VPS</label>
                                                <label class="toggle-container config-toggle" title="Bật/tắt">
                                                    <input id="cfg_MEDIA_REMOTE_ENABLED" class="toggle-input" type="checkbox" data-type="bool">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            <small class="text-muted">Bật: ảnh upload đẩy lên VPS rồi xóa local. Cần có Receiver URL + Secret.</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="MEDIA_KEEP_UPLOADS_PREFIX">
                                            <div class="config-bool-row">
                                                <label for="cfg_MEDIA_KEEP_UPLOADS_PREFIX">Giữ tiền tố /uploads/ trong URL</label>
                                                <label class="toggle-container config-toggle" title="Bật/tắt">
                                                    <input id="cfg_MEDIA_KEEP_UPLOADS_PREFIX" class="toggle-input" type="checkbox" data-type="bool">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                            <small class="text-muted">Bật (mặc định) khi subdomain map vào thư mục cha. Tắt khi map thẳng vào uploads/.</small>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="config-section-title mt-2 mb-1"><i class="bi bi-shield-lock"></i>BẢO MẬT &amp; XÁC THỰC</div>
                                        <small class="text-muted d-block mb-2">Thời gian hiệu lực của các mã/link bảo mật.</small>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="RESET_PASSWORD_TIMEOUT_MINUTES">
                                            <label for="cfg_RESET_PASSWORD_TIMEOUT_MINUTES">Hiệu lực link đặt lại mật khẩu (phút)</label>
                                            <input id="cfg_RESET_PASSWORD_TIMEOUT_MINUTES" type="number" data-type="int" min="1" placeholder="30">
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="config-item" data-key="OTP_TIMEOUT_MINUTES">
                                            <label for="cfg_OTP_TIMEOUT_MINUTES">Hiệu lực mã OTP xác thực (phút)</label>
                                            <input id="cfg_OTP_TIMEOUT_MINUTES" type="number" data-type="int" min="1" placeholder="5">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
    </div>
</div>
                                

<script>
$(function(){
    const API_URL = '<?= h($baseUrl) ?>/core_admin/setting.php';
    const $loading = $('#botLoadingOverlay');
    const $cardsWrap = $('#configCardsWrap');
    const loadedSections = {};

    const TAB_GROUPS = {
        '#tab-ai-login': 'ai-login',
        '#tab-payment': 'payment',
        '#tab-company': 'company',
        '#tab-ecommerce': 'ecommerce',
        '#tab-region': 'region',
        '#tab-social': 'social'
    };

    // Đảm bảo toastr luôn tồn tại, nếu thiếu thư viện sẽ fallback sang alert
    if (typeof toastr === 'undefined') {
        window.toastr = {
            options: {},
            success: function(msg){ alert(msg); },
            error: function(msg){ alert(msg); },
            warning: function(msg){ alert(msg); },
            info: function(msg){ alert(msg); }
        };
    }
    toastr.options = {
        positionClass: 'toast-top-right',
        timeOut: 2200,
        showDuration: 180,
        hideDuration: 180,
        preventDuplicates: true
    };

    function esc(value){
        return $('<div>').text(String(value ?? '')).html();
    }

    function normalizeMomoEnv(env){
        const raw = String(env || '').toLowerCase().trim();
        return (raw === 'test' || raw === 'testing' || raw === 'sandbox') ? 'test' : 'production';
    }

    function setLoading(on){
        if(on) $loading.css('display', 'flex');
        else $loading.hide();
    }

    function fieldName(key){
        return 'cfg_' + key.replace(/[^a-zA-Z0-9_]/g, '_');
    }

    function inputByType(item){
        const type = String(item.type || 'string');
        const key = String(item.key || '');
        const label = String(item.label || key);
        const value = item.value ?? '';
        const inputName = fieldName(key);

        if(type === 'bool'){
            const isChecked = (value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true');
            return ''
                + '<div class="config-item" data-key="' + esc(key) + '">'
                + '  <div class="config-bool-row">'
                + '    <label for="' + esc(inputName) + '">' + esc(label) + '</label>'
                + '    <label class="toggle-container config-toggle" title="Bật/tắt">'
                + '      <input id="' + esc(inputName) + '" class="toggle-input" type="checkbox" data-type="bool" ' + (isChecked ? 'checked' : '') + '>'
                + '      <span class="toggle-slider"></span>'
                + '    </label>'
                + '  </div>'
                + '</div>';
        }

        const isSecret = !!item.secret;
        const longText = type === 'json' || String(value).length > 120;

        if(longText){
            return ''
                + '<div class="config-item" data-key="' + esc(key) + '">'
                + '<label for="' + esc(inputName) + '">' + esc(label) + '</label>'
                + '<textarea id="' + esc(inputName) + '" data-type="' + esc(type) + '" placeholder="' + esc(label) + '">' + esc(value) + '</textarea>'
                + '</div>';
        }

        return ''
            + '<div class="config-item" data-key="' + esc(key) + '">'
            + '<label for="' + esc(inputName) + '">' + esc(label) + '</label>'
            + '<input id="' + esc(inputName) + '" type="' + (isSecret ? 'password' : 'text') + '" data-type="' + esc(type) + '" value="' + esc(value) + '" placeholder="' + esc(label) + '">'
            + '</div>';
    }

    function updateMomoEnvUI(env){
        const nextEnv = normalizeMomoEnv(env);
        $cardsWrap.attr('data-momo-env', nextEnv);

        $cardsWrap.find('.momo-env-badge').each(function(){
            const $badge = $(this);
            const badgeEnv = $badge.data('env');
            if(badgeEnv === nextEnv){
                $badge.addClass('active').text('Đang dùng');
            } else {
                $badge.removeClass('active').text('Chưa dùng');
            }
        });

        $cardsWrap.find('.momo-env-toggle').prop('checked', nextEnv === 'test');
    }

    function extractMomoEnv(settings){
        if(!Array.isArray(settings)) return null;
        const row = settings.find(item => item.key === 'ECOMMERCE_PAYMENT_METHODS.momo_env');
        return row ? row.value : null;
    }

    function collectSettings(){
        const payload = {};
        $cardsWrap.find('.config-item').each(function(){
            const $item = $(this);
            const key = $item.data('key');
            const $input = $item.find('input,textarea,select').first();
            const type = String($input.data('type') || 'string');
            let value = $input.val();

            if(type === 'bool'){
                value = $input.is(':checked');
            } else if(type === 'int'){
                value = parseInt(value || '0', 10);
                if(Number.isNaN(value)) value = 0;
            } else if(type === 'float'){
                value = parseFloat(value || '0');
                if(Number.isNaN(value)) value = 0;
            }
            payload[key] = value;
        });
        return payload;
    }

    function collectSingleSetting($item){
        const key = $item.data('key');
        if(!key) return null;
        const $input = $item.find('input,textarea,select').first();
        if(!$input.length) return null;
        const type = String($input.data('type') || 'string');
        let value = $input.val();

        if(type === 'bool'){
            value = $input.is(':checked');
        } else if(type === 'int'){
            value = parseInt(value || '0', 10);
            if(Number.isNaN(value)) value = 0;
        } else if(type === 'float'){
            value = parseFloat(value || '0');
            if(Number.isNaN(value)) value = 0;
        }

        const payload = {};
        payload[key] = value;
        return payload;
    }

    function loadSectionById(sectionSelector){
        const $section = $cardsWrap.find(sectionSelector);
        if(!$section.length) return;
        if(loadedSections[sectionSelector]) return;

        const group = TAB_GROUPS[sectionSelector] || '';
        setLoading(true);
        $section.find('.config-item').addClass('skeleton');

        $.get(API_URL, { ajax: 'bootstrap', group: group }, function(res){
            if(!res || !res.ok){
                toastr.error('Không tải được cấu hình');
                return;
            }
            const settings = res.settings || [];
            const env = extractMomoEnv(settings);
            if(env !== null){
                updateMomoEnvUI(env);
            }

            // Đầu tiên, clear hết value các input (trừ file/hidden) trong section
            $section.find('.config-item').each(function(){
                const $ci = $(this);
                $ci.find('input, textarea, select').each(function(){
                    const $input = $(this);
                    const type = $input.attr('type');
                    if(type === 'checkbox'){
                        $input.prop('checked', false);
                    } else if(type !== 'file' && type !== 'hidden') {
                        $input.val('');
                        $input.prop('value', '');
                    }
                });
            });

            // Sau đó, set lại value từ settings trả về
            settings.forEach(function(item){
                const key = item.key;
                if(!key) return;
                const $ci = $section.find('.config-item[data-key="' + key.replace(/"/g,'\\"') + '"]');
                if(!$ci.length) return;
                $ci.find('input, textarea, select').each(function(){
                    const $input = $(this);
                    const type = $input.attr('type');
                    if(type === 'checkbox'){
                        $input.prop('checked', item.value === true || item.value === 1 || item.value === '1' || String(item.value).toLowerCase() === 'true');
                    } else if(type !== 'file' && type !== 'hidden') {
                        $input.val(String(item.value ?? ''));
                        $input.prop('value', String(item.value ?? ''));
                    }
                });

                // Nếu là trường SITE_LOGO hoặc SITE_FALLBACK_LOGO thì cập nhật lại logo preview
                if(key === 'SITE_LOGO'){
                    var logoPath = String(item.value ?? '').trim();
                    var $logoPreviewImg = $('#siteLogoPreviewImage');
                    var $logoInputHidden = $('#cfg_SITE_LOGO');
                    if(logoPath){
                        // Route qua media domain nếu là file media; giữ nguyên URL tuyệt đối.
                        var url = (typeof window.toMediaUrl === 'function')
                            ? window.toMediaUrl(logoPath)
                            : (logoPath.match(/^https?:\/\//) ? logoPath : ('<?= h($baseUrl) ?>/' + logoPath.replace(/^\/+/, '')));
                        if($logoPreviewImg.length){
                            $logoPreviewImg.attr('src', url);
                        }
                        if($logoInputHidden.length){
                            $logoInputHidden.val(logoPath).prop('value', logoPath);
                        }
                    }
                }
                // MEDIA_MODE là input hidden (loader bỏ qua) → set tay rồi vẽ lại UI nút.
                if(key === 'MEDIA_MODE'){
                    var mmVal = String(item.value ?? '').trim().toLowerCase();
                    if(!MEDIA_MODE_INFO[mmVal]) mmVal = 'vps';
                    $('#cfg_MEDIA_MODE').val(mmVal).prop('value', mmVal);
                    paintMediaMode(mmVal);
                }
                if(key === 'SITE_FALLBACK_LOGO'){
                    var fbPath = String(item.value ?? '').trim();
                    var $fbPreviewImg = $('#siteFallbackPreviewImage');
                    var $fbInputHidden = $('#cfg_SITE_FALLBACK_LOGO');
                    if(fbPath){
                        var fbUrl = (typeof window.toMediaUrl === 'function')
                            ? window.toMediaUrl(fbPath)
                            : (fbPath.match(/^https?:\/\//) ? fbPath : ('<?= h($baseUrl) ?>/' + fbPath.replace(/^\/+/, '')));
                        if($fbPreviewImg.length){
                            $fbPreviewImg.attr('src', fbUrl);
                        }
                        if($fbInputHidden.length){
                            $fbInputHidden.val(fbPath).prop('value', fbPath);
                        }
                    }
                }
            });

            loadedSections[sectionSelector] = true;
        }, 'json').fail(function(){
            toastr.error('Lỗi kết nối khi tải dữ liệu');
        }).always(function(){
            setLoading(false);
            $section.find('.config-item').removeClass('skeleton');
        });
    }

    function autoSaveConfigItem($item){
        const payload = collectSingleSetting($item);
        if(!payload) return;

        // Hiển thị trạng thái đang lưu ngay trên item
        $item.removeClass('saved').addClass('saving');

        $.post(API_URL, {
            action: 'save_config',
            settings: JSON.stringify(payload)
        }, function(res){
            if(res && res.ok){
                // Đã lưu: hiện dấu tích xanh một lúc
                $item.removeClass('saving').addClass('saved');
                setTimeout(function(){
                    $item.removeClass('saved');
                }, 1800);
            } else {
                toastr.error((res && res.msg) ? res.msg : 'Lưu cấu hình thất bại');
                $item.removeClass('saving');
            }
        }, 'json').fail(function(){
            toastr.error('Lỗi mạng khi lưu cấu hình');
            $item.removeClass('saving');
        });
    }

    function runMomoTest($btn, env){
        const $wrap = $btn.closest('.config-card');
        const $result = $wrap.find('.momo-test-result');
        $result.text('Đang kiểm tra...');
        $btn.prop('disabled', true);
        $.post(API_URL, {action: 'test_momo', env: env}, function(res){
            if(res && res.ok){
                $result.removeClass('text-danger').addClass('text-success');
                $result.text('OK: ' + (res.message || 'Thành công') + ' (code ' + res.resultCode + ')');
            } else {
                const msg = (res && res.message) ? res.message : ((res && res.msg) ? res.msg : 'Không kết nối MoMo');
                $result.removeClass('text-success').addClass('text-danger');
                if(res && res.resultCode !== undefined){
                    $result.text('Lỗi: ' + msg + ' (code ' + res.resultCode + ')');
                } else {
                    $result.text('Lỗi: ' + msg);
                }
            }
        }, 'json').fail(function(){
            $result.removeClass('text-success').addClass('text-danger');
            $result.text('Lỗi mạng khi gọi MoMo');
        }).always(function(){
            $btn.prop('disabled', false);
        });
    }

    function setMomoEnv(env){
        const nextEnv = normalizeMomoEnv(env);
        setLoading(true);
        $.post(API_URL, {action: 'set_momo_env', env: nextEnv}, function(res){
            if(res && res.ok){
                toastr.success('Đã áp dụng chế độ ' + (res.env === 'test' ? 'TEST' : 'PRODUCTION'));
                updateMomoEnvUI(res.env || nextEnv);
            } else {
                toastr.error('Không đổi được chế độ MoMo');
            }
        }, 'json').fail(function(){
            toastr.error('Lỗi mạng khi đổi chế độ MoMo');
        }).always(function(){
            setLoading(false);
        });
    }

    // ===== Switch chế độ Media (khẩn cấp) =====
    const MEDIA_MODE_INFO = {
        vps:        { cls: 'btn-success',   label: 'Media VPS',          badge: 'text-bg-success', warn: '' },
        read_origin:{ cls: 'btn-warning',   label: 'Khẩn cấp: đọc từ gốc', badge: 'text-bg-warning', warn: 'Đang ĐỌC ảnh từ server gốc. Ảnh đã upload-chỉ-VPS (chưa có ở gốc) sẽ KHÔNG hiển thị. Upload mới ghi vào local.' },
        local:      { cls: 'btn-secondary', label: 'Tắt (local)',         badge: 'text-bg-secondary', warn: 'Đã TẮT media domain hoàn toàn — đọc & ghi đều ở server gốc như trước khi tách.' }
    };
    const MEDIA_MODE_OUTLINE = { vps: 'btn-outline-success', read_origin: 'btn-outline-warning', local: 'btn-outline-secondary' };
    function paintMediaMode(mode){
        mode = MEDIA_MODE_INFO[mode] ? mode : 'vps';
        $('#cfg_MEDIA_MODE').val(mode).prop('value', mode);
        $('.media-mode-btn').each(function(){
            const $b = $(this);
            const m = $b.data('mode');
            // Bỏ mọi class màu rồi đặt lại: active = solid + nổi bật, còn lại = outline mờ.
            $b.removeClass('btn-success btn-warning btn-secondary btn-outline-success btn-outline-warning btn-outline-secondary');
            const isActive = (m === mode);
            $b.addClass(isActive ? MEDIA_MODE_INFO[m].cls : MEDIA_MODE_OUTLINE[m]);
            $b.toggleClass('media-mode-btn--active', isActive);
            $b.attr('aria-pressed', isActive ? 'true' : 'false');
        });
        // Badge "Đang bật" cạnh tiêu đề
        const $status = $('#mediaModeStatus');
        $status.removeClass('text-bg-success text-bg-warning text-bg-secondary')
               .addClass(MEDIA_MODE_INFO[mode].badge)
               .html('<i class="bi bi-check-circle-fill me-1"></i>Đang bật: ' + MEDIA_MODE_INFO[mode].label);
        const warn = MEDIA_MODE_INFO[mode].warn;
        if (warn) { $('#mediaModeWarnText').text(warn); $('#mediaModeWarn').show(); }
        else { $('#mediaModeWarn').hide(); }
    }
    // Khi load section social xong, đồng bộ UI theo MEDIA_MODE đã lưu
    $cardsWrap.on('change', '#cfg_MEDIA_MODE', function(){ paintMediaMode($(this).val() || 'vps'); });

    $cardsWrap.on('click', '.media-mode-btn', function(){
        const mode = $(this).data('mode');
        if (mode === 'read_origin' || mode === 'local') {
            if (!confirm(MEDIA_MODE_INFO[mode].warn + '\n\nTiếp tục đổi chế độ?')) return;
        }
        setLoading(true);
        $.post(API_URL, { action: 'set_media_mode', mode: mode }, function(res){
            if (res && res.ok) {
                toastr.success('Đã chuyển chế độ media: ' + res.mode);
                paintMediaMode(res.mode);
            } else {
                toastr.error((res && res.msg) ? res.msg : 'Không đổi được chế độ media');
            }
        }, 'json').fail(function(){
            toastr.error('Lỗi mạng khi đổi chế độ media');
        }).always(function(){ setLoading(false); });
    });

    // Tabs chuyển giữa các nhóm cấu hình
    $cardsWrap.on('click', '.settings-tab', function(){
        const $btn = $(this);
        const target = $btn.data('target');
        if(!target) return;

        $cardsWrap.find('.settings-tab').removeClass('active');
        $btn.addClass('active');

        $cardsWrap.find('.settings-section').removeClass('active');
        $cardsWrap.find(String(target)).addClass('active');

        // Lazy-load nội dung cho tab mới được chọn
        loadSectionById(String(target));
    });

    // Thêm nút "Làm mới" cho mỗi card cấu hình
    $cardsWrap.find('.bot-card.config-card .bot-head').each(function(){
        const $head = $(this);
        let $actions = $head.find('.head-actions');
        if(!$actions.length){
            $actions = $('<div>', { class: 'head-actions' }).appendTo($head);
        }
        const $btn = $('<button>', {
            type: 'button',
            class: 'btn btn-outline-secondary btn-sm card-refresh-btn',
            html: '<i class="bi bi-arrow-clockwise me-1"></i>Làm mới'
        });
        $actions.append($btn);
    });

    // Xử lý nút làm mới: reload lại tab chứa card đó
    $cardsWrap.on('click', '.card-refresh-btn', function(){
        const $card = $(this).closest('.settings-section');
        if(!$card.length) return;
        const sectionId = '#' + $card.attr('id');
        if(!sectionId) return;
        // cho phép reload lại section này
        loadedSections[sectionId] = false;
        loadSectionById(sectionId);
    });
    $cardsWrap.on('click', '.momo-test-btn', function(){
        runMomoTest($(this), $(this).data('env'));
    });
    $cardsWrap.on('click', '.momo-apply-btn', function(){
        setMomoEnv($(this).data('env'));
    });
    $cardsWrap.on('change', '.momo-env-toggle', function(){
        setMomoEnv($(this).is(':checked') ? 'test' : 'production');
    });

    // Tự động lưu từng trường cấu hình khi người dùng thay đổi (auto-save per field)
    $cardsWrap.on('change', '.config-item input, .config-item textarea, .config-item select', function(e){
        const $input = $(this);
        const $item = $input.closest('.config-item');
        if(!$item.length) return;
        autoSaveConfigItem($item);
    });

    // Upload ảnh QR ngân hàng cho COD
    $cardsWrap.on('change', '#codQrUploadInput', function(){
        const input = this;
        if (!input.files || !input.files.length) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('action', 'upload_cod_qr');
        formData.append('qr_image', file);

        setLoading(true);
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res){
            if (res && res.ok) {
                toastr.success('Đã upload ảnh QR thành công');
                if (res.path) {
                    const $hidden = $('#cfg_COMPANY_INFO_bank_qr_image');
                    if ($hidden.length) {
                        $hidden.val(String(res.path));
                    }
                }
                if (res.url) {
                    // Cập nhật nút xem ảnh hiện tại nếu có
                    let $link = $('#codQrPreviewLink');
                    if ($link.length) {
                        $link.removeClass('d-none').attr('href', res.url);
                    } else {
                        const $btnWrap = $('#codQrUploadInput').closest('.config-item');
                        $link = $btnWrap.find('a[target="_blank"]');
                        if (!$link.length) {
                            $link = $('<a>', {
                                class: 'btn btn-outline-secondary btn-sm',
                                target: '_blank',
                                text: 'Xem ảnh hiện tại'
                            });
                            $btnWrap.find('.d-flex').append($link);
                        }
                        $link.attr('href', res.url);
                    }
                }
            } else {
                toastr.error((res && res.msg) ? res.msg : 'Upload ảnh QR thất bại');
            }
        }).fail(function(){
            toastr.error('Lỗi mạng khi upload ảnh QR');
        }).always(function(){
            setLoading(false);
            input.value = '';
        });
    });

    // Click vào ảnh logo để mở dialog chọn file
    $cardsWrap.on('click', '#siteLogoPreviewImage', function(){
        $('#siteLogoUploadInput').trigger('click');
    });

    // Click vào ảnh fallback để mở dialog chọn file
    $cardsWrap.on('click', '#siteFallbackPreviewImage', function(){
        $('#siteFallbackUploadInput').trigger('click');
    });

    // Upload logo website (SITE_LOGO)
    $cardsWrap.on('change', '#siteLogoUploadInput', function(){
        const input = this;
        if(!input.files || !input.files.length) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('action', 'upload_site_logo');
        formData.append('logo_image', file);

        setLoading(true);
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res){
            if(res && res.ok){
                toastr.success('Đã upload logo thành công');
                if(res.path){
                    const $inputPath = $('#cfg_SITE_LOGO');
                    if($inputPath.length){
                        $inputPath.val(String(res.path));
                        $inputPath.prop('value', String(res.path));
                    }
                }
                if(res.url){
                    const $previewImg = $('#siteLogoPreviewImage');
                    if($previewImg.length){
                        $previewImg.attr('src', res.url);
                    }
                }
            } else {
                toastr.error((res && res.msg) ? res.msg : 'Upload logo thất bại');
            }
        }).fail(function(){
            toastr.error('Lỗi mạng khi upload logo');
        }).always(function(){
            setLoading(false);
            input.value = '';
        });
    });

    // Upload ảnh fallback chung (SITE_FALLBACK_LOGO)
    $cardsWrap.on('change', '#siteFallbackUploadInput', function(){
        const input = this;
        if(!input.files || !input.files.length) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('action', 'upload_site_fallback_logo');
        formData.append('fallback_image', file);

        setLoading(true);
        $.ajax({
            url: API_URL,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res){
            if(res && res.ok){
                toastr.success('Đã upload ảnh fallback thành công');
                if(res.path){
                    const $inputPath = $('#cfg_SITE_FALLBACK_LOGO');
                    if($inputPath.length){
                        $inputPath.val(String(res.path));
                        $inputPath.prop('value', String(res.path));
                    }
                }
                if(res.url){
                    const $previewImg = $('#siteFallbackPreviewImage');
                    if($previewImg.length){
                        $previewImg.attr('src', res.url);
                    }
                }
            } else {
                toastr.error(res && res.msg ? res.msg : 'Không thể upload ảnh fallback');
            }
        }).fail(function(){
            toastr.error('Lỗi kết nối khi upload ảnh fallback');
        }).always(function(){
            setLoading(false);
            input.value = '';
        });
    });

    // Load mặc định cho tab đầu tiên (AI & Login)
    loadSectionById('#tab-ai-login');
});
</script>
