<?php
// Định nghĩa các hằng số/biến dùng chung
$searchAction = $baseUrl !== '' ? ($baseUrl . '/search') : '/search';
$productTable = first_existing_table($ithanhloc, ['ecommerce_product']);
$variantTable = first_existing_table($ithanhloc, ['ecommerce_product_variants']);

// productCols sẽ được lấy sau khi xác định được tên bảng để tránh lỗi khi bảng không tồn tại
$productCols = $productTable !== '' ? list_table_columns($ithanhloc, $productTable) : [];

// Biểu thức nhân VAT
$hasVatColumn        = in_array('vat', $productCols, true);
$hasVatEnabledColumn = in_array('vat_enabled', $productCols, true);
$defaultVatPercent   = app_get_default_vat_percent(); // helper từ functions.php
$vatMultiplierExpr   = $hasVatColumn
    ? ($hasVatEnabledColumn
        ? "(CASE WHEN COALESCE(p.vat_enabled, 1) = 1 THEN (1 + (COALESCE(p.vat, {$defaultVatPercent}) / 100)) ELSE 1 END)"
        : "(1 + (COALESCE(p.vat, {$defaultVatPercent}) / 100))")
    : (string)(1.0 + ($defaultVatPercent / 100.0));

$productActiveExprSql = '';
if (in_array('status', $productCols, true)) {
    $productActiveExprSql = "LOWER(TRIM(CAST(p.status AS CHAR))) IN ('true','1','on','yes','active','enabled')";
} elseif (in_array('is_active', $productCols, true)) {
    $productActiveExprSql = "LOWER(TRIM(CAST(p.is_active AS CHAR))) IN ('1','true','on','yes')";
} elseif (in_array('trang_thai', $productCols, true)) {
    $productActiveExprSql = "LOWER(TRIM(CAST(p.trang_thai AS CHAR))) IN ('true','1','on','yes','active','enabled')";
}
$productActiveWhere = $productActiveExprSql !== '' ? " AND ({$productActiveExprSql})" : '';

// Biểu thức giá dùng chung
$priceExpr = $variantTable !== ''
    ? "(COALESCE((SELECT MIN(price) FROM `{$variantTable}` WHERE product_id = p.id), 0) * {$vatMultiplierExpr})"
    : "(0 * {$vatMultiplierExpr})";

$query     = trim((string)($_GET['q'] ?? ''));
$queryLike = $query !== '' ? '%' . $query . '%' : '';

// BUG-001 FIX: Normalize từ khoá bỏ dấu tiếng Việt để tìm được cả khi
// user gõ "son tuong" hoặc "sơn tường" hoặc "SƠN TƯỜNG".
if (!function_exists('search_normalize_vn')) {
    function search_normalize_vn(string $str): string {
        $str = mb_strtolower($str, 'UTF-8');
        $map = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
            'ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
            'đ'=>'d',
        ];
        return strtr($str, $map);
    }
}

$queryNormalized     = search_normalize_vn($query);
$queryNormalizedLike = $query !== '' ? '%' . $queryNormalized . '%' : '';
// Dùng CONCAT + LOWER để match cả chuỗi bỏ dấu từ DB (MySQL không có hàm bỏ dấu tích hợp,
// nên ta dùng COLLATE utf8mb4_general_ci — collation này fold dấu về base char).
$collateExpr = "utf8mb4_general_ci";

$results = [ 'products' => [] ];
$quick   = [ 'products' => [] ];

function fmt_vnd_local($value): string {
    return fmtMoney($value); // fmtMoney() từ functions.php
}

function safe_thumb($path, string $baseUrl): string {
    global $site_fallback_logo;
    $raw = trim((string)$path);
    if ($raw === '') {
        $raw = (string)($site_fallback_logo ?: '');
    }
    // app_get_media_url() từ functions.php — xử lý URL tuyệt đối / tương đối
    return h(app_get_media_url($raw, $baseUrl));
}

function stmt_fetch_all(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    if (!$res) return [];
    $rows = $res->fetch_all(MYSQLI_ASSOC) ?: [];
    $res->close();
    return $rows;
}

if ($query !== '') {
    if ($productTable !== '') {
        // Xây danh sách cột search: product_name (bắt buộc), manufacturer, sku, description nếu có
        $searchCols = [];
        $searchColNames = ['product_name', 'manufacturer', 'sku'];
        // Thêm description nếu có
        if (in_array('description', $productCols, true)) {
            $searchColNames[] = 'description';
        }

        foreach ($searchColNames as $col) {
            if (in_array($col, $productCols, true)) {
                // Match có dấu (chính xác cao)
                $searchCols[] = "p.{$col} LIKE ?";
                // Match bỏ dấu bằng COLLATE utf8mb4_general_ci — fold dấu
                $searchCols[] = "p.{$col} COLLATE {$collateExpr} LIKE ?";
            }
        }
        if (!$searchCols) $searchCols[] = 'p.id > 0';

        $where = implode(' OR ', $searchCols);
        $sql   = "SELECT p.id, p.product_name, p.image_url, p.manufacturer, {$priceExpr} AS price
            FROM `{$productTable}` p
            WHERE ({$where}){$productActiveWhere}
            ORDER BY p.id DESC
            LIMIT 48";
        $stmt = $ithanhloc->prepare($sql);
        if ($stmt) {
            $types  = '';
            $values = [];
            foreach ($searchCols as $expr) {
                if (strpos($expr, 'LIKE ?') !== false) {
                    if (strpos($expr, "COLLATE {$collateExpr}") !== false) {
                        // Param bỏ dấu
                        $types .= 's';
                        $values[] = $queryNormalizedLike;
                    } else {
                        // Param giữ nguyên dấu
                        $types .= 's';
                        $values[] = $queryLike;
                    }
                }
            }
            if ($types !== '') {
                $bindArgs = [$types];
                foreach ($values as $k => $v) {
                    $bindArgs[] = &$values[$k];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindArgs);
                unset($v);
            }
            $stmt->execute();
            $results['products'] = stmt_fetch_all($stmt);
            $stmt->close();
        }
    }
} else {
    if ($productTable !== '') {
        $res = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, p.manufacturer, {$priceExpr} AS price FROM `{$productTable}` p WHERE 1 = 1{$productActiveWhere} ORDER BY p.id DESC LIMIT 12");
        if ($res) {
            $quick['products'] = $res->fetch_all(MYSQLI_ASSOC) ?: [];
            $res->close();
        }
    }
}


if ((string)($_GET['ajax'] ?? '') === '1') {
    jOut(['ok' => true, 'query' => $query, 'results' => $results, 'quick' => $quick]);
}

// Gợi ý từ khóa phổ biến (đặt cố định, có thể nâng cấp lấy từ DB sau)
$popularKeywords = ['Sơn nội thất', 'Sơn ngoại thất', 'Sơn lót', 'Rust-Oleum', 'Sơn chống thấm', 'Bột trét'];
?>
<style>
    .pm-search-wrap{ max-width:1200px; margin:0 auto; padding:8px 4px 32px; }

    /* ===== HERO ===== */
    .pm-search-hero{
        position:relative; overflow:hidden; border-radius:20px; padding:32px 28px 28px;
        background:#fff; color:var(--fb-text); border:1px solid var(--order-border);
        box-shadow:0 4px 16px rgba(15,23,42,.05);
    }
    .pm-search-hero-inner{ position:relative; z-index:2; }
    .pm-search-eyebrow{
        display:inline-flex; align-items:center; gap:8px; font-size:.78rem; font-weight:600;
        background:rgba(var(--theme-primary-rgb), .08); border:1px solid rgba(var(--theme-primary-rgb), .18);
        color:var(--theme-primary);
        padding:6px 14px; border-radius:999px; letter-spacing:.05em; text-transform:uppercase;
    }
    .pm-search-title{ font-size:clamp(1.6rem, 3vw, 2.2rem); font-weight:800; margin:14px 0 6px; letter-spacing:-.01em; color:var(--fb-text); }
    .pm-search-title em{ color:var(--theme-primary); font-style:normal; }
    .pm-search-sub{ font-size:.95rem; color:var(--fb-text-sub); max-width:620px; line-height:1.55; margin:0; }

    .pm-search-form{
        margin-top:22px; display:flex; align-items:center; gap:0;
        background:#fff; border-radius:14px; padding:6px;
        border:1.5px solid var(--order-border);
        box-shadow:0 2px 6px rgba(15,23,42,.04);
        transition:border-color .15s ease, box-shadow .15s ease;
    }
    .pm-search-form:focus-within{
        border-color:var(--theme-primary);
        box-shadow:0 0 0 4px rgba(var(--theme-primary-rgb), .12);
    }
    .pm-search-form .pm-search-icon{
        width:44px; display:flex; align-items:center; justify-content:center; color:var(--theme-primary); font-size:1.1rem;
    }
    .pm-search-form input{
        flex:1; border:0; outline:none; background:transparent; color:#0f172a;
        font-size:1rem; font-weight:600; padding:12px 4px;
    }
    .pm-search-form input::placeholder{ color:#94a3b8; font-weight:500; }
    .pm-search-form .pm-search-clear{
        width:36px; height:36px; border-radius:10px; border:0; background:#f1f5f9; color:#64748b;
        display:none; align-items:center; justify-content:center; margin-right:4px; cursor:pointer;
    }
    .pm-search-form .pm-search-clear.is-visible{ display:inline-flex; }
    .pm-search-form .pm-search-clear:hover{ background:#e2e8f0; color:#0f172a; }
    .pm-search-form button[type="submit"]{
        border:0; background:var(--theme-primary); color:#fff; font-weight:700;
        padding:10px 22px; border-radius:10px; font-size:.95rem;
        display:inline-flex; align-items:center; gap:8px; transition:transform .15s ease, box-shadow .15s ease;
    }
    .pm-search-form button[type="submit"]:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(12,76,41,.35); }

    .pm-search-popular{ margin-top:18px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .pm-search-popular-label{ font-size:.8rem; color:var(--fb-text-sub); font-weight:600; margin-right:4px; }
    .pm-search-chip{
        font-size:.78rem; font-weight:600; padding:5px 12px; border-radius:999px;
        background:#f1f5f9; border:1px solid var(--order-border); color:var(--fb-text);
        cursor:pointer; text-decoration:none; transition:all .15s ease;
    }
    .pm-search-chip:hover{
        background:rgba(var(--theme-primary-rgb), .08);
        border-color:rgba(var(--theme-primary-rgb), .35);
        color:var(--theme-primary);
    }

    /* ===== RESULTS HEAD ===== */
    .pm-search-results-head{
        margin:22px 0 14px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    }
    .pm-search-results-head h2{
        margin:0; font-size:1.1rem; font-weight:800; color:var(--fb-text); display:flex; align-items:center; gap:10px;
    }
    .pm-search-results-head h2 .pm-count{
        font-size:.78rem; font-weight:700; padding:3px 10px; border-radius:999px;
        background:rgba(var(--theme-primary-rgb), .1); color:var(--theme-primary);
    }
    .pm-search-sort{
        display:flex; align-items:center; gap:8px; font-size:.85rem; color:var(--fb-text-sub);
    }
    .pm-search-sort select{
        border:1px solid var(--order-border); border-radius:10px; padding:7px 12px;
        font-size:.85rem; font-weight:600; color:var(--fb-text); background:#fff; outline:none;
    }
    .pm-search-sort select:focus{ border-color:var(--theme-primary); box-shadow:0 0 0 3px rgba(var(--theme-primary-rgb),.15); }

    /* ===== PRODUCT GRID ===== */
    .pm-search-grid{
        display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:14px;
    }
    .pm-search-card{
        position:relative; display:flex; flex-direction:column; background:#fff;
        border:1px solid var(--order-border); border-radius:14px; overflow:hidden;
        text-decoration:none; color:inherit; transition:all .2s cubic-bezier(.4,0,.2,1);
    }
    .pm-search-card:hover{
        transform:translateY(-3px); border-color:rgba(var(--theme-primary-rgb), .35);
        box-shadow:0 12px 28px rgba(15,23,42,.08);
    }
    .pm-search-card-thumb{
        aspect-ratio:1/1; background:#f8fafc; display:flex; align-items:center; justify-content:center; overflow:hidden;
        border-bottom:1px solid #f1f5f9;
    }
    .pm-search-card-thumb img{
        width:100%; height:100%; object-fit:contain; padding:10px; transition:transform .3s ease;
    }
    .pm-search-card:hover .pm-search-card-thumb img{ transform:scale(1.06); }
    .pm-search-card-body{ padding:12px 14px 14px; display:flex; flex-direction:column; gap:6px; flex-grow:1; }
    .pm-search-card-brand{
        font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700;
        color:var(--fb-text-sub);
    }
    .pm-search-card-name{
        font-size:.9rem; font-weight:700; color:var(--fb-text); line-height:1.35;
        display:-webkit-box; -webkit-line-clamp:2; line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:2.5em;
    }
    .pm-search-card-foot{
        margin-top:auto; padding-top:8px; display:flex; align-items:center; justify-content:space-between; gap:6px;
    }
    .pm-search-card-price{ font-weight:800; color:#ef3c2d; font-size:.98rem; }
    .pm-search-card-cta{
        width:30px; height:30px; border-radius:8px; background:rgba(var(--theme-primary-rgb),.1);
        color:var(--theme-primary); display:inline-flex; align-items:center; justify-content:center; font-size:.85rem;
    }
    .pm-search-card:hover .pm-search-card-cta{ background:var(--theme-primary); color:#fff; }

    /* ===== EMPTY / LOADING ===== */
    .pm-search-empty{
        text-align:center; padding:48px 24px; background:#fff; border:1px dashed #cbd5e1; border-radius:16px;
    }
    .pm-search-empty-icon{
        width:72px; height:72px; margin:0 auto 14px; border-radius:50%;
        background:rgba(var(--theme-primary-rgb),.08); color:var(--theme-primary);
        display:flex; align-items:center; justify-content:center; font-size:1.8rem;
    }
    .pm-search-empty h3{ font-size:1.05rem; font-weight:700; color:var(--fb-text); margin:0 0 6px; }
    .pm-search-empty p{ color:var(--fb-text-sub); margin:0 0 16px; font-size:.9rem; }
    .pm-search-empty .btn-clear{
        display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:.85rem;
        padding:8px 16px; border-radius:10px; background:var(--theme-primary); color:#fff; border:0; text-decoration:none;
    }

    .pm-skeleton-card{
        background:#fff; border:1px solid var(--order-border); border-radius:14px; overflow:hidden;
    }
    .pm-skeleton-thumb{ aspect-ratio:1/1; background:#f1f5f9; position:relative; overflow:hidden; }
    .pm-skeleton-thumb::after, .pm-skeleton-line::after{
        content:""; position:absolute; inset:0; transform:translateX(-100%);
        background:linear-gradient(90deg, transparent, rgba(255,255,255,.7), transparent);
        animation:pmShimmer 1.3s infinite;
    }
    .pm-skeleton-body{ padding:12px 14px; display:flex; flex-direction:column; gap:8px; }
    .pm-skeleton-line{
        position:relative; overflow:hidden; background:#e2e8f0; border-radius:6px; height:12px;
    }
    .pm-skeleton-line.lg{ height:16px; width:80%; }
    .pm-skeleton-line.sm{ height:10px; width:50%; }
    @keyframes pmShimmer{ 100%{ transform:translateX(100%); } }

    @media (max-width: 575.98px){
        .pm-search-hero{ padding:28px 18px; border-radius:16px; }
        .pm-search-form button[type="submit"] span{ display:none; }
        .pm-search-form button[type="submit"]{ padding:10px 14px; }
        .pm-search-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .pm-search-card-body{ padding:10px 10px 12px; }
    }
</style>

<div class="pm-search-wrap">
    <!-- HERO -->
    <section class="pm-search-hero">
        <div class="pm-search-hero-inner">
            <div class="pm-search-eyebrow"><i class="bi bi-stars"></i> Tìm kiếm thông minh</div>
            <h1 class="pm-search-title">Tìm sản phẩm <em>nhanh chóng</em> & chính xác</h1>
            <p class="pm-search-sub">Nhập tên sản phẩm, hãng sản xuất, mã SKU hoặc từ khóa bất kỳ để tìm trong toàn bộ kho hàng Paint &amp; More.</p>

            <form class="pm-search-form" method="get" action="<?= h($searchAction) ?>" role="search">
                <span class="pm-search-icon"><i class="bi bi-search"></i></span>
                <input type="text" name="q" id="pmSearchInput" value="<?= h($query) ?>" placeholder="Bạn đang tìm sản phẩm gì..." autocomplete="off">
                <button type="button" class="pm-search-clear<?= $query !== '' ? ' is-visible' : '' ?>" id="pmSearchClear" aria-label="Xóa từ khóa"><i class="bi bi-x-lg"></i></button>
                <button type="submit"><i class="bi bi-search"></i><span>Tìm kiếm</span></button>
            </form>

            <div class="pm-search-popular">
                <span class="pm-search-popular-label"><i class="bi bi-fire me-1"></i>Phổ biến:</span>
                <?php foreach ($popularKeywords as $kw): ?>
                    <a href="<?= h($searchAction) ?>?q=<?= urlencode($kw) ?>" class="pm-search-chip" data-kw="<?= h($kw) ?>"><?= h($kw) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- RESULTS HEAD -->
    <div class="pm-search-results-head">
        <h2 id="pmSearchHeading">
            <span id="pmSearchHeadingText"><?= $query !== '' ? 'Kết quả tìm kiếm' : 'Sản phẩm gợi ý' ?></span>
            <span class="pm-count" id="pmSearchCount">0 sản phẩm</span>
        </h2>
        <div class="pm-search-sort">
            <label for="pmSearchSort"><i class="bi bi-sliders"></i> Sắp xếp</label>
            <select id="pmSearchSort">
                <option value="relevance">Liên quan nhất</option>
                <option value="price_asc">Giá: Thấp → Cao</option>
                <option value="price_desc">Giá: Cao → Thấp</option>
                <option value="name_asc">Tên: A → Z</option>
                <option value="name_desc">Tên: Z → A</option>
            </select>
        </div>
    </div>

    <!-- RESULTS -->
    <div id="pmSearchResults" aria-live="polite"></div>
</div>

<script>
(function(){
    const resultsEl = document.getElementById('pmSearchResults');
    const headingTextEl = document.getElementById('pmSearchHeadingText');
    const countEl   = document.getElementById('pmSearchCount');
    const form      = document.querySelector('.pm-search-form');
    const input     = document.getElementById('pmSearchInput');
    const clearBtn  = document.getElementById('pmSearchClear');
    const sortSel   = document.getElementById('pmSearchSort');
    const baseUrl   = '<?= h($baseUrl) ?>';
    const fallback  = <?= json_encode($site_fallback_logo ? rtrim($baseUrl, '/') . '/' . ltrim($site_fallback_logo, '/') : '') ?>;

    let CURRENT_ITEMS = [];

    function fmtVnd(val){
        if (val === null || val === undefined || val === '') return 'Liên hệ';
        let n;
        if (typeof val === 'string'){
            const cleaned = val.replace(/[^0-9.,]/g, '').replace(/\./g, '').replace(/,/g, '.');
            n = Number(cleaned);
        } else { n = Number(val); }
        if (!Number.isFinite(n) || n <= 0) return 'Liên hệ';
        return Math.ceil(n).toLocaleString('vi-VN') + ' đ';
    }
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function safeThumb(path){
        if (!path) return fallback;
        const s = String(path).trim();
        if (!s) return fallback;
        if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(s);
        if (/^https?:\/\//i.test(s) || s.startsWith('data:')) return s;
        return baseUrl + '/' + s.replace(/^\/?/, '');
    }

    function skeletonGrid(n){
        let html = '<div class="pm-search-grid">';
        for (let i = 0; i < n; i++){
            html += `
                <div class="pm-skeleton-card">
                    <div class="pm-skeleton-thumb"></div>
                    <div class="pm-skeleton-body">
                        <div class="pm-skeleton-line sm"></div>
                        <div class="pm-skeleton-line lg"></div>
                        <div class="pm-skeleton-line"></div>
                    </div>
                </div>`;
        }
        html += '</div>';
        return html;
    }

    function emptyHtml(query){
        const hasQ = !!(query && query.trim());
        return `
            <div class="pm-search-empty">
                <div class="pm-search-empty-icon"><i class="bi bi-search-heart"></i></div>
                <h3>${hasQ ? 'Không tìm thấy sản phẩm phù hợp' : 'Chưa có sản phẩm để hiển thị'}</h3>
                <p>${hasQ ? `Không có kết quả cho "<strong>${esc(query)}</strong>". Hãy thử từ khóa khác hoặc xem các từ khóa phổ biến phía trên.` : 'Vui lòng nhập từ khóa để bắt đầu tìm kiếm.'}</p>
                ${hasQ ? `<button type="button" class="btn-clear" id="pmEmptyReset"><i class="bi bi-arrow-counterclockwise"></i> Xóa tìm kiếm</button>` : ''}
            </div>`;
    }

    function cardHtml(item){
        const pid  = item.id || 0;
        const href = (window.pmBuildProductUrl
            ? window.pmBuildProductUrl(pid, item.product_name || 'Sản phẩm')
            : (baseUrl + '/view-product?pid=' + pid));
        const name  = esc(item.product_name || 'Sản phẩm');
        const brand = esc(item.manufacturer || '—');
        const thumb = safeThumb(item.image_url);
        return `
            <a class="pm-search-card" href="${href}" title="${name}">
                <div class="pm-search-card-thumb"><img src="${thumb}" alt="${name}" loading="lazy" onerror="this.src='${fallback}'"></div>
                <div class="pm-search-card-body">
                    <div class="pm-search-card-brand">${brand}</div>
                    <div class="pm-search-card-name">${name}</div>
                    <div class="pm-search-card-foot">
                        <span class="pm-search-card-price">${fmtVnd(item.price)}</span>
                        <span class="pm-search-card-cta"><i class="bi bi-arrow-right"></i></span>
                    </div>
                </div>
            </a>`;
    }

    function applySort(items){
        const v = sortSel ? sortSel.value : 'relevance';
        const arr = items.slice();
        switch (v){
            case 'price_asc':  arr.sort((a,b) => Number(a.price||0) - Number(b.price||0)); break;
            case 'price_desc': arr.sort((a,b) => Number(b.price||0) - Number(a.price||0)); break;
            case 'name_asc':   arr.sort((a,b) => String(a.product_name||'').localeCompare(String(b.product_name||''), 'vi')); break;
            case 'name_desc':  arr.sort((a,b) => String(b.product_name||'').localeCompare(String(a.product_name||''), 'vi')); break;
        }
        return arr;
    }

    function renderResults(){
        const items = applySort(CURRENT_ITEMS);
        if (!items.length){
            const q = input ? input.value.trim() : '';
            resultsEl.innerHTML = emptyHtml(q);
            countEl.textContent = '0 sản phẩm';
            return;
        }
        let html = '<div class="pm-search-grid">';
        items.forEach(it => { html += cardHtml(it); });
        html += '</div>';
        resultsEl.innerHTML = html;
        countEl.textContent = items.length + ' sản phẩm';
    }

    function renderPayload(payload){
        const query = payload.query || '';
        const data  = query ? payload.results : payload.quick;
        CURRENT_ITEMS = (data && data.products) || [];
        headingTextEl.textContent = query ? `Kết quả cho "${query}"` : 'Sản phẩm gợi ý';
        renderResults();
    }

    function fetchResults(q){
        resultsEl.innerHTML = skeletonGrid(8);
        countEl.textContent = '...';
        const apiBase = (baseUrl || '').replace(/\/$/, '');
        const apiPath = apiBase ? (apiBase + '/search') : '/search';
        const url     = new URL(apiPath, window.location.origin);
        url.searchParams.set('ajax', '1');
        url.searchParams.set('q', q || '');
        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (!data || !data.ok){
                    resultsEl.innerHTML = emptyHtml(q);
                    countEl.textContent = '0 sản phẩm';
                    return;
                }
                renderPayload(data);
            })
            .catch(() => {
                resultsEl.innerHTML = emptyHtml(q);
                countEl.textContent = '0 sản phẩm';
            });
    }

    // CLEAR button toggle + clear
    function updateClearVisibility(){
        if (!clearBtn || !input) return;
        clearBtn.classList.toggle('is-visible', input.value.trim() !== '');
    }
    if (input){
        input.addEventListener('input', updateClearVisibility);
    }
    if (clearBtn){
        clearBtn.addEventListener('click', () => {
            if (!input) return;
            input.value = '';
            updateClearVisibility();
            input.focus();
            const base = (baseUrl || '').replace(/\/$/, '');
            window.history.replaceState({}, '', base + '/search');
            fetchResults('');
        });
    }

    // Empty-state reset
    resultsEl.addEventListener('click', (e) => {
        const btn = e.target.closest('#pmEmptyReset');
        if (!btn) return;
        if (input){ input.value = ''; updateClearVisibility(); }
        const base = (baseUrl || '').replace(/\/$/, '');
        window.history.replaceState({}, '', base + '/search');
        fetchResults('');
    });

    // Sort change
    if (sortSel){ sortSel.addEventListener('change', renderResults); }

    // Popular chip click → submit search
    document.querySelectorAll('.pm-search-chip[data-kw]').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            const kw = a.getAttribute('data-kw') || '';
            if (input){ input.value = kw; updateClearVisibility(); }
            const base = (baseUrl || '').replace(/\/$/, '');
            window.history.replaceState({}, '', base + '/search/' + encodeURIComponent(kw));
            fetchResults(kw);
        });
    });

    // Form submit
    if (form){
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const q = input ? input.value.trim() : '';
            const base = (baseUrl || '').replace(/\/$/, '');
            const next = q ? (base + '/search/' + encodeURIComponent(q)) : (base + '/search');
            window.history.replaceState({}, '', next);
            fetchResults(q);
        });
    }

    fetchResults(input ? input.value.trim() : '');
})();
</script>
